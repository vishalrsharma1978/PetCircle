// Admin dashboard: Posts / Events / Galleries moderation panels. Ported
// from eSamaj's admin_core.php equivalents — the free-text "religion"
// filter is replaced by pet_type (posts/events already carry it directly;
// galleries are filtered by the owner's pet_type server-side).

adminConsoleState.posts = { search: "", typeFilter: "", petType: "", statusFilter: "", offset: 0, limit: 20 };
adminConsoleState.events = { search: "", petType: "", offset: 0, limit: 20 };
adminConsoleState.galleries = { search: "", visibilityFilter: "", petType: "", offset: 0, limit: 20 };
adminConsoleState.verification = { statusFilter: "pending" };

// ---------------- Verification Requests ----------------
// list_verification_requests/review_verification_request existed as working
// admin actions with zero admin UI anywhere — a submitted "Verified Pet
// Parent" request could never actually be approved or rejected. This panel
// closes that gap.

const ADMIN_VERIFICATION_PROOF_LABELS = {
  microchip: "Microchip",
  vet_record: "Vet record",
  adoption_papers: "Adoption papers",
  photo_only: "Photo only",
};

async function loadAdminVerificationRequests() {
  const box = document.getElementById("admin-panel-verification");
  if (!box) return;
  const state = adminConsoleState.verification;

  box.innerHTML = `
    <div class="flex flex-wrap gap-2 mb-3">
      ${adminFilterSelect("admin-verification-status", state.statusFilter, [["pending", "Pending"], ["approved", "Approved"], ["rejected", "Rejected"], ["", "All"]])}
    </div>
    <div id="admin-verification-list" class="space-y-2"><p class="text-sm text-gray-400 py-6 text-center">Loading…</p></div>`;

  document.getElementById("admin-verification-status").onchange = (e) => {
    state.statusFilter = e.target.value;
    loadAdminVerificationRequestsList();
  };

  loadAdminVerificationRequestsList();
}

async function loadAdminVerificationRequestsList() {
  const list = document.getElementById("admin-verification-list");
  if (!list) return;
  const state = adminConsoleState.verification;
  try {
    const data = await api("list_verification_requests", { status: state.statusFilter });
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">${escapeHtml(data.message || "Could not load requests.")}</p>`;
      return;
    }
    const requests = data.requests || [];
    list.innerHTML = requests.length
      ? requests.map((r) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3" data-verification-request-id="${r.id}">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm font-bold text-white truncate">${escapeHtml(r.pet_name || "Unnamed pet")} <span class="font-normal text-gray-400">· ${escapeHtml(r.parent_name || "")}</span></p>
                <p class="text-xs text-gray-400 mt-0.5">${escapeHtml([r.pet_type, r.breed].filter(Boolean).join(" · ") || "No pet type set")} · ${escapeHtml(r.current_city || "")}</p>
                <p class="text-xs text-gray-500 mt-1">${escapeHtml(ADMIN_VERIFICATION_PROOF_LABELS[r.proof_type] || r.proof_type)}${r.microchip_number ? " · Microchip: " + escapeHtml(r.microchip_number) : ""}</p>
                ${r.reason ? `<p class="text-xs text-gray-400 mt-1 italic">"${escapeHtml(r.reason)}"</p>` : ""}
                <div class="flex gap-2 mt-2">
                  ${r.pet_photo_url ? `<a href="${escapeHtml(r.pet_photo_url)}" target="_blank" rel="noopener" class="text-[10px] font-bold text-brand-400 hover:underline">Pet photo</a>` : ""}
                  ${r.owner_photo_url ? `<a href="${escapeHtml(r.owner_photo_url)}" target="_blank" rel="noopener" class="text-[10px] font-bold text-brand-400 hover:underline">Owner photo</a>` : ""}
                  ${r.proof_document_url ? `<a href="${escapeHtml(r.proof_document_url)}" target="_blank" rel="noopener" class="text-[10px] font-bold text-brand-400 hover:underline">Proof document</a>` : ""}
                </div>
                <p class="text-[10px] text-gray-600 mt-1.5">Submitted ${escapeHtml(timeAgo(r.created_at))}</p>
              </div>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase flex-shrink-0 ${r.status === "approved" ? "bg-emerald-500/15 text-emerald-300" : r.status === "rejected" ? "bg-red-500/15 text-red-300" : "bg-amber-500/15 text-amber-300"}">${escapeHtml(r.status)}</span>
            </div>
            ${r.status === "pending" ? `
              <div class="flex gap-2 mt-3">
                <button onclick="adminReviewVerification('${r.id}', 'approve', this)" class="px-3 py-1.5 rounded-lg bg-emerald-500/15 text-emerald-300 text-xs font-bold hover:bg-emerald-500/25">Approve</button>
                <button onclick="adminReviewVerification('${r.id}', 'reject', this)" class="px-3 py-1.5 rounded-lg bg-red-500/15 text-red-300 text-xs font-bold hover:bg-red-500/25">Reject</button>
              </div>` : ""}
          </div>`).join("")
      : `<p class="text-sm text-gray-400 py-6 text-center">No ${state.statusFilter || ""} verification requests.</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function adminReviewVerification(requestId, action, btn) {
  if (btn) {
    btn.disabled = true;
    btn.closest("div").querySelectorAll("button").forEach((b) => b.classList.add("opacity-50", "pointer-events-none"));
  }
  try {
    const data = await api("review_verification_request", { request_id: requestId, action });
    if (data.status !== "success") {
      showToast(data.message || "Could not review request.", "error");
      return;
    }
    showToast(action === "approve" ? "Request approved." : "Request rejected.", "success");
    loadAdminVerificationRequestsList();
  } catch (err) {
    console.error(err);
    showToast("Could not review request.", "error");
  }
}

// ---------------- Posts ----------------

async function loadAdminPosts() {
  const box = document.getElementById("admin-panel-posts");
  if (!box) return;
  const state = adminConsoleState.posts;

  box.innerHTML = `
    <div class="flex flex-wrap gap-2 mb-3">
      ${adminFilterInput("admin-posts-search", "Search content…", state.search)}
      ${adminFilterSelect("admin-posts-type", state.typeFilter, [["", "All types"], ["text", "Text"], ["image", "Image"], ["video", "Video"], ["poll", "Poll"], ["event", "Event"]])}
      ${adminFilterSelect("admin-posts-pet-type", state.petType, [["", "Any pet type"], ...ADMIN_PET_TYPES.map((t) => [t, t])])}
      ${adminFilterSelect("admin-posts-status", state.statusFilter, [["", "Visible"], ["deleted", "Hidden"], ["all", "All"]])}
    </div>
    <div id="admin-posts-list" class="space-y-2"><p class="text-sm text-gray-400 py-6 text-center">Loading…</p></div>`;

  const bind = (id, field) => {
    document.getElementById(id).oninput = document.getElementById(id).onchange = (e) => {
      state[field] = e.target.value;
      state.offset = 0;
      loadAdminPostsList();
    };
  };
  bind("admin-posts-search", "search");
  bind("admin-posts-type", "typeFilter");
  bind("admin-posts-pet-type", "petType");
  bind("admin-posts-status", "statusFilter");

  loadAdminPostsList();
}

async function loadAdminPostsList() {
  const list = document.getElementById("admin-posts-list");
  if (!list) return;
  const state = adminConsoleState.posts;
  try {
    const data = await api("admin_list_posts", {
      search: state.search, type_filter: state.typeFilter, pet_type: state.petType,
      status_filter: state.statusFilter, offset: state.offset, limit: state.limit,
    });
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Could not load posts.</p>`;
      return;
    }
    const posts = data.posts || [];
    list.innerHTML = posts.length
      ? posts.map((p) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3 ${p.is_deleted ? "opacity-60" : ""}">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <p class="text-sm text-gray-200 truncate">${escapeHtml(p.author?.name || "Member")} <span class="text-gray-500">· ${escapeHtml(p.post_type)} · ${escapeHtml([p.pet_type, p.breed].filter(Boolean).join(" · "))}</span></p>
                <p class="text-sm text-gray-400 mt-1 line-clamp-2">${escapeHtml(p.content || "(media post)")}</p>
                <p class="text-xs text-gray-600 mt-1">${escapeHtml(timeAgo(p.created_at))} · ${p.like_count} likes · ${p.comment_count} comments ${p.is_deleted ? "· <span class=\"text-red-400\">hidden</span>" : ""}</p>
              </div>
              <button onclick="adminModeratePostAction('${p.id}', '${p.is_deleted ? "restore" : "hide"}', this)" class="px-2.5 py-1 rounded-lg text-xs font-bold flex-shrink-0 ${p.is_deleted ? "bg-emerald-500/15 text-emerald-300 hover:bg-emerald-500/25" : "bg-red-500/15 text-red-300 hover:bg-red-500/25"}">${p.is_deleted ? "Restore" : "Hide"}</button>
            </div>
          </div>`).join("") + adminPagerHtml(state.offset, state.limit, posts.length)
      : `<p class="text-sm text-gray-400 py-6 text-center">No posts match these filters.</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function adminModeratePostAction(postId, op, btn) {
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("admin_moderate_post", { post_id: postId, op });
    if (data.status !== "success") {
      showToast(data.message || "Could not moderate post.", "error");
      return;
    }
    showToast(op === "hide" ? "Post hidden." : "Post restored.", "success");
    loadAdminPostsList();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

// ---------------- Events ----------------

async function loadAdminEvents() {
  const box = document.getElementById("admin-panel-events");
  if (!box) return;
  const state = adminConsoleState.events;

  box.innerHTML = `
    <div class="flex flex-wrap gap-2 mb-3">
      ${adminFilterInput("admin-events-search", "Search title…", state.search)}
      ${adminFilterSelect("admin-events-pet-type", state.petType, [["", "Any pet type"], ...ADMIN_PET_TYPES.map((t) => [t, t])])}
    </div>
    <div id="admin-events-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3"><p class="text-sm text-gray-400 py-6 text-center col-span-full">Loading…</p></div>`;

  const bind = (id, field) => {
    document.getElementById(id).oninput = document.getElementById(id).onchange = (e) => {
      state[field] = e.target.value;
      state.offset = 0;
      loadAdminEventsList();
    };
  };
  bind("admin-events-search", "search");
  bind("admin-events-pet-type", "petType");

  loadAdminEventsList();
}

async function loadAdminEventsList() {
  const list = document.getElementById("admin-events-list");
  if (!list) return;
  const state = adminConsoleState.events;
  try {
    const data = await api("admin_list_events", { search: state.search, pet_type: state.petType, offset: state.offset, limit: state.limit });
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center col-span-full">Could not load events.</p>`;
      return;
    }
    const events = data.events || [];
    list.innerHTML = events.length
      ? events.map((e) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3">
            <p class="text-sm font-bold text-white truncate">${escapeHtml(e.title)}</p>
            <p class="text-xs text-gray-400 mt-1">${escapeHtml(e.event_date || "")} ${escapeHtml(e.event_time || "")} · ${escapeHtml(e.location || (e.is_online ? "Online" : ""))}</p>
            <p class="text-xs text-gray-500 mt-1">By ${escapeHtml(e.organizer?.pet_name || "Member")} · ${escapeHtml([e.pet_type, e.breed].filter(Boolean).join(" · "))}</p>
            <button onclick="adminDeleteEventAction('${e.id}', this)" class="mt-2 px-2.5 py-1 rounded-lg bg-red-500/15 text-red-300 text-xs font-bold hover:bg-red-500/25">Delete</button>
          </div>`).join("") + `<div class="col-span-full">${adminPagerHtml(state.offset, state.limit, events.length)}</div>`
      : `<p class="text-sm text-gray-400 py-6 text-center col-span-full">No events match these filters.</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function adminDeleteEventAction(eventId, btn) {
  if (!(await confirmAction({ title: "Delete this event?", message: "This also removes its RSVPs.", confirmLabel: "Delete" }))) return;
  setButtonLoading(btn, true);
  try {
    const data = await api("admin_delete_event", { event_id: eventId });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete event.", "error");
      return;
    }
    showToast("Event deleted.", "success");
    loadAdminEventsList();
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

// ---------------- Galleries ----------------

async function loadAdminGalleries() {
  const box = document.getElementById("admin-panel-galleries");
  if (!box) return;
  const state = adminConsoleState.galleries;

  box.innerHTML = `
    <div class="flex flex-wrap gap-2 mb-3">
      ${adminFilterInput("admin-galleries-search", "Search title…", state.search)}
      ${adminFilterSelect("admin-galleries-visibility", state.visibilityFilter, [["", "Any visibility"], ["public", "Public"], ["pet_type", "Pet type"], ["breed", "Breed"], ["private", "Private"]])}
      ${adminFilterSelect("admin-galleries-pet-type", state.petType, [["", "Any pet type"], ...ADMIN_PET_TYPES.map((t) => [t, t])])}
    </div>
    <div id="admin-galleries-list" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"><p class="text-sm text-gray-400 py-6 text-center col-span-full">Loading…</p></div>`;

  const bind = (id, field) => {
    document.getElementById(id).oninput = document.getElementById(id).onchange = (e) => {
      state[field] = e.target.value;
      state.offset = 0;
      loadAdminGalleriesList();
    };
  };
  bind("admin-galleries-search", "search");
  bind("admin-galleries-visibility", "visibilityFilter");
  bind("admin-galleries-pet-type", "petType");

  loadAdminGalleriesList();
}

async function loadAdminGalleriesList() {
  const list = document.getElementById("admin-galleries-list");
  if (!list) return;
  const state = adminConsoleState.galleries;
  try {
    const data = await api("admin_list_galleries", {
      search: state.search, visibility_filter: state.visibilityFilter, pet_type: state.petType,
      offset: state.offset, limit: state.limit,
    });
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center col-span-full">Could not load galleries.</p>`;
      return;
    }
    const galleries = data.galleries || [];
    list.innerHTML = galleries.length
      ? galleries.map((g) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3">
            <p class="text-sm font-bold text-white truncate">${escapeHtml(g.title)}</p>
            <p class="text-xs text-gray-400 mt-1">${g.item_count} item(s) · ${escapeHtml(g.visibility)}</p>
            <p class="text-xs text-gray-500 mt-1">By ${escapeHtml(g.owner?.pet_name || "Member")}</p>
            <button onclick="adminDeleteGalleryAction('${g.id}', this)" class="mt-2 px-2.5 py-1 rounded-lg bg-red-500/15 text-red-300 text-xs font-bold hover:bg-red-500/25">Delete</button>
          </div>`).join("") + `<div class="col-span-full">${adminPagerHtml(state.offset, state.limit, galleries.length)}</div>`
      : `<p class="text-sm text-gray-400 py-6 text-center col-span-full">No galleries match these filters.</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function adminDeleteGalleryAction(galleryId, btn) {
  if (!(await confirmAction({ title: "Delete this gallery?", message: "This also removes all its items.", confirmLabel: "Delete" }))) return;
  setButtonLoading(btn, true);
  try {
    const data = await api("admin_delete_gallery", { gallery_id: galleryId });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete gallery.", "error");
      return;
    }
    showToast("Gallery deleted.", "success");
    loadAdminGalleriesList();
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}
