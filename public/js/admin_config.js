// Admin dashboard: Pet Types (theming, replaces eSamaj's Communities panel),
// Contact Book, Custom Reactions, Features & Tabs, Feed Layout, and Ads.
// Each of these is generic-mechanism-with-domain-specific-fields in eSamaj
// (app_settings key/value store, scoped image uploads) — ported with
// pet_type/breed scoping instead of religion/community.

const ADMIN_FEATURE_TABS = ["hub", "feed", "friends", "groups", "events", "galleries", "rescue", "guides", "messages"];

// ---------------- Pet Types (theming) ----------------

async function loadAdminPetTypes() {
  const box = document.getElementById("admin-panel-pet_types");
  if (!box) return;
  box.innerHTML = `<div id="admin-pet-types-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"><p class="text-sm text-gray-400 py-6 text-center col-span-full">Loading…</p></div>`;

  try {
    const data = await api("admin_get_pet_type_themes", {});
    const grid = document.getElementById("admin-pet-types-grid");
    if (data.status !== "success") {
      grid.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center col-span-full">Could not load pet type themes.</p>`;
      return;
    }
    const themes = data.themes || {};
    grid.innerHTML = (data.pet_types || ADMIN_PET_TYPES).map((petType) => {
      const t = themes[petType] || {};
      const safeId = petType.replace(/\s+/g, "-").toLowerCase();
      return `
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
          <h4 class="font-bold text-white mb-3">${escapeHtml(petType)}</h4>
          <div class="flex items-center gap-2 mb-2">
            <label class="text-xs text-gray-400 w-24">Accent color</label>
            <input type="color" id="pt-color-${safeId}" value="${escapeHtml(t.accent_color || "#e04848")}" class="w-10 h-8 rounded border border-gray-700 bg-gray-800">
            <input type="text" id="pt-color-text-${safeId}" value="${escapeHtml(t.accent_color || "")}" placeholder="#e04848" class="flex-1 px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white">
          </div>
          <div class="flex items-center gap-2 mb-3">
            <label class="text-xs text-gray-400 w-24">Font</label>
            <input type="text" id="pt-font-${safeId}" value="${escapeHtml(t.font_family || "")}" placeholder="Inter" class="flex-1 px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white">
          </div>
          <button onclick="saveAdminPetTypeTheme('${escapeHtml(petType)}', '${safeId}', this)" class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white">Save</button>
        </div>`;
    }).join("");

    (data.pet_types || ADMIN_PET_TYPES).forEach((petType) => {
      const safeId = petType.replace(/\s+/g, "-").toLowerCase();
      const colorInput = document.getElementById(`pt-color-${safeId}`);
      const colorText = document.getElementById(`pt-color-text-${safeId}`);
      if (colorInput && colorText) {
        colorInput.oninput = () => { colorText.value = colorInput.value; };
      }
    });
  } catch (err) {
    console.error(err);
  }
}

async function saveAdminPetTypeTheme(petType, safeId, btn) {
  const accentColor = document.getElementById(`pt-color-text-${safeId}`).value.trim();
  const fontFamily = document.getElementById(`pt-font-${safeId}`).value.trim();
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("admin_save_pet_type_theme", { pet_type: petType, accent_color: accentColor, font_family: fontFamily });
    if (data.status !== "success") {
      showToast(data.message || "Could not save theme.", "error");
      return;
    }
    showToast(`${petType} theme saved.`, "success");
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

// ---------------- Contact Book ----------------

adminConsoleState.contacts = { search: "", petType: "" };

async function loadAdminContactBook() {
  const box = document.getElementById("admin-panel-contacts");
  if (!box) return;
  const state = adminConsoleState.contacts;

  box.innerHTML = `
    <div class="flex flex-wrap gap-2 mb-3">
      ${adminFilterInput("admin-contacts-search", "Search name…", state.search)}
      ${adminFilterSelect("admin-contacts-pet-type", state.petType, [["", "All pet types"], ...ADMIN_PET_TYPES.map((t) => [t, t])])}
    </div>
    <div id="admin-contacts-results"><p class="text-sm text-gray-400 py-6 text-center">Loading…</p></div>`;

  const bind = (id, field) => {
    document.getElementById(id).oninput = document.getElementById(id).onchange = (e) => {
      state[field] = e.target.value;
      loadAdminContactBookResults();
    };
  };
  bind("admin-contacts-search", "search");
  bind("admin-contacts-pet-type", "petType");

  loadAdminContactBookResults();
}

async function loadAdminContactBookResults() {
  const box = document.getElementById("admin-contacts-results");
  if (!box) return;
  const state = adminConsoleState.contacts;
  try {
    const data = await api("admin_contact_book", { search: state.search, pet_type: state.petType });
    if (data.status !== "success") {
      box.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Could not load contacts.</p>`;
      return;
    }
    const groups = data.groups || {};
    const groupKeys = Object.keys(groups).sort();
    box.innerHTML = groupKeys.length
      ? groupKeys.map((key) => `
          <div class="mb-4">
            <h4 class="text-sm font-bold text-gray-200 mb-2">${escapeHtml(key)} <span class="text-gray-500 font-normal">(${groups[key].length})</span></h4>
            <div class="space-y-1">
              ${groups[key].map((c) => `
                <div class="text-xs text-gray-400 flex justify-between border-b border-gray-800 py-1.5">
                  <span class="text-gray-200">${escapeHtml(c.pet_name || "Unnamed")} <span class="text-gray-500">(${escapeHtml(c.parent_name || "")})</span></span>
                  <span>${escapeHtml(c.current_city || "")} ${escapeHtml(c.mobile_number || "")}</span>
                </div>`).join("")}
            </div>
          </div>`).join("")
      : `<p class="text-sm text-gray-400 py-6 text-center">No contacts found.</p>`;
  } catch (err) {
    console.error(err);
  }
}

// ---------------- Custom Reactions ----------------

async function loadAdminReactions() {
  const box = document.getElementById("admin-panel-reactions");
  if (!box) return;
  box.innerHTML = `
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 mb-3">
      <h4 class="font-bold text-white mb-3">Add a reaction</h4>
      <div class="flex flex-wrap gap-2 items-center mb-2">
        <input type="file" id="admin-reaction-file" accept="image/jpeg,image/png,image/webp" class="text-xs text-gray-300">
        <input type="text" id="admin-reaction-label" placeholder="Label (e.g. Zoomies)" class="px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white">
        ${adminFilterSelect("admin-reaction-pet-type", "", [["", "Any pet type"], ...ADMIN_PET_TYPES.map((t) => [t, t])])}
      </div>
      <button id="admin-reaction-submit-btn" onclick="submitAdminReaction()" class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white">Add reaction</button>
    </div>
    <div id="admin-reactions-list" class="grid grid-cols-2 sm:grid-cols-4 gap-3"><p class="text-sm text-gray-400 py-6 text-center col-span-full">Loading…</p></div>`;
  loadAdminReactionsList();
}

async function loadAdminReactionsList() {
  const list = document.getElementById("admin-reactions-list");
  if (!list) return;
  try {
    const data = await api("admin_list_custom_reactions", {});
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center col-span-full">Could not load reactions.</p>`;
      return;
    }
    const reactions = data.reactions || [];
    list.innerHTML = reactions.length
      ? reactions.map((r) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3 text-center">
            <img src="${escapeHtml(r.image_url)}" alt="" class="w-12 h-12 mx-auto object-contain mb-2">
            <p class="text-xs font-bold text-gray-200 truncate">${escapeHtml(r.label)}</p>
            <p class="text-[10px] text-gray-500 mb-2">${escapeHtml(r.pet_type || "Any pet type")}</p>
            <div class="flex justify-center gap-1.5">
              <button onclick="toggleAdminReaction('${r.id}', ${!r.is_active}, this)" class="px-2 py-0.5 rounded text-[10px] font-bold ${r.is_active ? "bg-gray-700 text-gray-300" : "bg-emerald-500/15 text-emerald-300"}">${r.is_active ? "Archive" : "Restore"}</button>
              <button onclick="deleteAdminReaction('${r.id}', this)" class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/15 text-red-300">Delete</button>
            </div>
          </div>`).join("")
      : `<p class="text-sm text-gray-400 py-6 text-center col-span-full">No custom reactions yet.</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function submitAdminReaction() {
  const fileInput = document.getElementById("admin-reaction-file");
  const label = document.getElementById("admin-reaction-label").value.trim();
  const petType = document.getElementById("admin-reaction-pet-type").value;
  const file = fileInput.files?.[0];
  if (!file || !label) {
    showToast("An image and a label are required.", "info");
    return;
  }
  const btn = document.getElementById("admin-reaction-submit-btn");
  setButtonLoading(btn, true, "Uploading…");
  try {
    const uploadResult = await uploadPhotoFile(file, "reactions");
    if (uploadResult.status !== "success") {
      showToast(uploadResult.message || "Upload failed.", "error");
      return;
    }
    const data = await api("admin_add_custom_reaction", { label, image_url: uploadResult.photo_url, pet_type: petType });
    if (data.status !== "success") {
      showToast(data.message || "Could not add reaction.", "error");
      return;
    }
    showToast("Reaction added.", "success");
    fileInput.value = "";
    document.getElementById("admin-reaction-label").value = "";
    loadAdminReactionsList();
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function toggleAdminReaction(reactionId, isActive, btn) {
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("admin_set_custom_reaction_active", { reaction_id: reactionId, is_active: isActive ? "1" : "" });
    if (data.status !== "success") {
      showToast(data.message || "Could not update reaction.", "error");
      return;
    }
    loadAdminReactionsList();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function deleteAdminReaction(reactionId, btn) {
  if (!confirm("Delete this reaction?")) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("admin_delete_custom_reaction", { reaction_id: reactionId });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete reaction.", "error");
      return;
    }
    showToast("Reaction deleted.", "success");
    loadAdminReactionsList();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

// ---------------- Features & Tabs ----------------

async function loadAdminFeatures() {
  const box = document.getElementById("admin-panel-features");
  if (!box) return;
  box.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Loading…</p>`;

  try {
    const data = await api("admin_get_feature_visibility", {});
    if (data.status !== "success") {
      box.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Could not load feature visibility.</p>`;
      return;
    }
    const config = data.feature_visibility || {};
    const globalConfig = config.global || {};

    box.innerHTML = `
      <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
        <h4 class="font-bold text-white mb-3">Global tab visibility</h4>
        <p class="text-xs text-gray-400 mb-3">Hidden tabs disappear from the main nav for everyone unless overridden elsewhere. Per-role/pet_type/breed overrides can be layered on top of this once a specific need comes up — this console currently manages the global layer.</p>
        <div class="space-y-2">
          ${ADMIN_FEATURE_TABS.map((tab) => `
            <div class="flex items-center justify-between text-sm">
              <span class="text-gray-200 capitalize">${escapeHtml(tab)}</span>
              <select id="feat-${tab}" class="px-2 py-1 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white">
                <option value="visible" ${globalConfig[tab] !== "hidden" ? "selected" : ""}>Visible</option>
                <option value="hidden" ${globalConfig[tab] === "hidden" ? "selected" : ""}>Hidden</option>
              </select>
            </div>`).join("")}
        </div>
        <button id="admin-features-save-btn" onclick="saveAdminFeatureVisibility()" class="mt-4 px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white">Save</button>
      </div>`;
  } catch (err) {
    console.error(err);
  }
}

async function saveAdminFeatureVisibility() {
  const globalConfig = {};
  ADMIN_FEATURE_TABS.forEach((tab) => {
    globalConfig[tab] = document.getElementById(`feat-${tab}`).value;
  });
  const btn = document.getElementById("admin-features-save-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("admin_save_feature_visibility", { feature_visibility: { global: globalConfig } });
    if (data.status !== "success") {
      showToast(data.message || "Could not save.", "error");
      return;
    }
    showToast("Feature visibility saved.", "success");
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

// ---------------- Feed Layout ----------------

let adminLayoutStaged = null;
const ADMIN_LAYOUT_BLOCK_LABELS = { sidebar: "Navigation Sidebar", feed: "Main Feed", widgets: "Right Widgets" };

async function loadAdminLayout() {
  const box = document.getElementById("admin-panel-layout");
  if (!box) return;
  box.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Loading…</p>`;

  try {
    const data = await api("admin_get_feed_layout", {});
    if (data.status !== "success") {
      box.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Could not load layout.</p>`;
      return;
    }
    const layout = data.feed_layout || { sidebar: 1, feed: 2, widgets: 3 };
    adminLayoutStaged = Object.entries(layout).sort((a, b) => a[1] - b[1]).map(([key]) => key);
    renderAdminLayoutList();
  } catch (err) {
    console.error(err);
  }
}

function renderAdminLayoutList() {
  const box = document.getElementById("admin-panel-layout");
  if (!box) return;
  box.innerHTML = `
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
      <h4 class="font-bold text-white mb-3">Block order</h4>
      <div class="space-y-2">
        ${adminLayoutStaged.map((key, i) => `
          <div class="flex items-center justify-between bg-gray-800 rounded-lg px-3 py-2">
            <span class="text-sm text-gray-200">${escapeHtml(ADMIN_LAYOUT_BLOCK_LABELS[key] || key)}</span>
            <div class="flex gap-1">
              <button ${i === 0 ? "disabled" : ""} onclick="moveAdminLayoutBlock(${i}, -1)" class="px-2 py-1 rounded bg-gray-700 text-xs text-white ${i === 0 ? "opacity-30" : "hover:bg-gray-600"}">↑</button>
              <button ${i === adminLayoutStaged.length - 1 ? "disabled" : ""} onclick="moveAdminLayoutBlock(${i}, 1)" class="px-2 py-1 rounded bg-gray-700 text-xs text-white ${i === adminLayoutStaged.length - 1 ? "opacity-30" : "hover:bg-gray-600"}">↓</button>
            </div>
          </div>`).join("")}
      </div>
      <button id="admin-layout-save-btn" onclick="saveAdminLayout()" class="mt-4 px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white">Save Layout</button>
    </div>`;
}

function moveAdminLayoutBlock(index, direction) {
  const target = index + direction;
  if (target < 0 || target >= adminLayoutStaged.length) return;
  [adminLayoutStaged[index], adminLayoutStaged[target]] = [adminLayoutStaged[target], adminLayoutStaged[index]];
  renderAdminLayoutList();
}

async function saveAdminLayout() {
  const layout = {};
  adminLayoutStaged.forEach((key, i) => { layout[key] = i + 1; });
  const btn = document.getElementById("admin-layout-save-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("admin_save_feed_layout", { feed_layout: layout });
    if (data.status !== "success") {
      showToast(data.message || "Could not save layout.", "error");
      return;
    }
    showToast("Layout saved.", "success");
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

// ---------------- Ads ----------------

let adminEditingAdId = null;

async function loadAdminAds() {
  const box = document.getElementById("admin-panel-ads");
  if (!box) return;
  box.innerHTML = `
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 mb-3">
      <h4 class="font-bold text-white mb-3" id="admin-ad-form-title">New ad</h4>
      <div class="flex flex-wrap gap-2 items-center mb-2">
        <input type="file" id="admin-ad-file" accept="image/jpeg,image/png,image/webp" class="text-xs text-gray-300">
        <input type="text" id="admin-ad-title" placeholder="Title" class="px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white">
        <input type="text" id="admin-ad-link" placeholder="Link URL (optional)" class="px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white w-56">
        <label class="flex items-center gap-1.5 text-xs text-gray-300"><input type="checkbox" id="admin-ad-active" checked> Active</label>
      </div>
      <div class="flex gap-2">
        <button id="admin-ad-submit-btn" onclick="submitAdminAd()" class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white">Save</button>
        <button onclick="cancelAdminAdEdit()" id="admin-ad-cancel-btn" class="hidden px-3 py-1.5 rounded-lg bg-gray-700 text-xs font-bold text-white">Cancel</button>
      </div>
    </div>
    <div id="admin-ads-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3"><p class="text-sm text-gray-400 py-6 text-center col-span-full">Loading…</p></div>`;
  loadAdminAdsList();
}

async function loadAdminAdsList() {
  const list = document.getElementById("admin-ads-list");
  if (!list) return;
  try {
    const data = await api("admin_list_ads", {});
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center col-span-full">Could not load ads.</p>`;
      return;
    }
    const ads = data.ads || [];
    list.innerHTML = ads.length
      ? ads.map((ad) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3 flex gap-3">
            <img src="${escapeHtml(ad.image_url)}" alt="" class="w-16 h-16 object-cover rounded-lg flex-shrink-0">
            <div class="min-w-0 flex-1">
              <p class="text-sm font-bold text-white truncate">${escapeHtml(ad.title)}</p>
              <p class="text-xs text-gray-500 truncate">${escapeHtml(ad.link_url || "")}</p>
              <div class="flex gap-1.5 mt-1.5">
                <button onclick='editAdminAd(${JSON.stringify(ad).replace(/'/g, "&apos;")})' class="px-2 py-0.5 rounded text-[10px] font-bold bg-gray-700 text-gray-300">Edit</button>
                <button onclick="toggleAdminAd('${ad.id}', ${!ad.is_active}, this)" class="px-2 py-0.5 rounded text-[10px] font-bold ${ad.is_active ? "bg-gray-700 text-gray-300" : "bg-emerald-500/15 text-emerald-300"}">${ad.is_active ? "Deactivate" : "Activate"}</button>
                <button onclick="deleteAdminAd('${ad.id}', this)" class="px-2 py-0.5 rounded text-[10px] font-bold bg-red-500/15 text-red-300">Delete</button>
              </div>
            </div>
          </div>`).join("")
      : `<p class="text-sm text-gray-400 py-6 text-center col-span-full">No ads yet.</p>`;
  } catch (err) {
    console.error(err);
  }
}

function editAdminAd(ad) {
  adminEditingAdId = ad.id;
  document.getElementById("admin-ad-form-title").textContent = `Editing: ${ad.title}`;
  document.getElementById("admin-ad-title").value = ad.title || "";
  document.getElementById("admin-ad-link").value = ad.link_url || "";
  document.getElementById("admin-ad-active").checked = !!ad.is_active;
  document.getElementById("admin-ad-cancel-btn").classList.remove("hidden");
}

function cancelAdminAdEdit() {
  adminEditingAdId = null;
  document.getElementById("admin-ad-form-title").textContent = "New ad";
  document.getElementById("admin-ad-title").value = "";
  document.getElementById("admin-ad-link").value = "";
  document.getElementById("admin-ad-active").checked = true;
  document.getElementById("admin-ad-cancel-btn").classList.add("hidden");
}

async function submitAdminAd() {
  const fileInput = document.getElementById("admin-ad-file");
  const title = document.getElementById("admin-ad-title").value.trim();
  const linkUrl = document.getElementById("admin-ad-link").value.trim();
  const isActive = document.getElementById("admin-ad-active").checked;
  const file = fileInput.files?.[0];

  if (!title) {
    showToast("A title is required.", "info");
    return;
  }
  if (!adminEditingAdId && !file) {
    showToast("An image is required for a new ad.", "info");
    return;
  }

  const btn = document.getElementById("admin-ad-submit-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    let imageUrl = null;
    if (file) {
      const uploadResult = await uploadPhotoFile(file, "ads");
      if (uploadResult.status !== "success") {
        showToast(uploadResult.message || "Upload failed.", "error");
        return;
      }
      imageUrl = uploadResult.photo_url;
    }

    const payload = { title, link_url: linkUrl, is_active: isActive ? "1" : "" };
    if (imageUrl) payload.image_url = imageUrl;
    if (adminEditingAdId) payload.ad_id = adminEditingAdId;
    else if (!imageUrl) {
      showToast("An image is required.", "info");
      return;
    }

    const data = await api("admin_save_ad", payload);
    if (data.status !== "success") {
      showToast(data.message || "Could not save ad.", "error");
      return;
    }
    showToast("Ad saved.", "success");
    fileInput.value = "";
    cancelAdminAdEdit();
    loadAdminAdsList();
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function toggleAdminAd(adId, isActive, btn) {
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    await api("admin_save_ad", { ad_id: adId, is_active: isActive ? "1" : "" });
    loadAdminAdsList();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function deleteAdminAd(adId, btn) {
  if (!confirm("Delete this ad?")) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("admin_delete_ad", { ad_id: adId });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete ad.", "error");
      return;
    }
    showToast("Ad deleted.", "success");
    loadAdminAdsList();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}
