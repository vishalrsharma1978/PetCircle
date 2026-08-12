// Pack Tree: pan/zoom tree rendering, list view, and the manage-members modal.
// Renamed throughout from eSamaj's ft-*/Family Tree naming (see main.css's
// "PACK TREE PAN/ZOOM" section and .css-pack-tree rules).

let packMembersCache = [];
let packZoomLevel = 1;
let packPanX = 0;
let packPanY = 0;
let packPanDragging = false;
let packPanStart = { x: 0, y: 0 };

function applyPackTransform() {
  const stage = document.getElementById("pack-pan-stage");
  if (stage) stage.style.transform = `translate(${packPanX}px, ${packPanY}px) scale(${packZoomLevel})`;
}

function packZoom(delta) {
  packZoomLevel = Math.max(0.4, Math.min(2, packZoomLevel + delta));
  applyPackTransform();
}

function packResetView() {
  packZoomLevel = 1;
  packPanX = 0;
  packPanY = 0;
  applyPackTransform();
}

function initPackPanZoom() {
  const container = document.getElementById("pack-pan-container");
  if (!container || container.dataset.panInit) return;
  container.dataset.panInit = "1";

  container.addEventListener("mousedown", (e) => {
    packPanDragging = true;
    container.classList.add("grabbing");
    packPanStart = { x: e.clientX - packPanX, y: e.clientY - packPanY };
  });
  document.addEventListener("mousemove", (e) => {
    if (!packPanDragging) return;
    packPanX = e.clientX - packPanStart.x;
    packPanY = e.clientY - packPanStart.y;
    applyPackTransform();
  });
  document.addEventListener("mouseup", () => {
    packPanDragging = false;
    container.classList.remove("grabbing");
  });
  container.addEventListener("wheel", (e) => {
    e.preventDefault();
    packZoom(e.deltaY < 0 ? 0.1 : -0.1);
  }, { passive: false });
}

function switchPackTreeView(view) {
  document.getElementById("pack-tree-view").classList.toggle("hidden", view !== "tree");
  document.getElementById("pack-list-view").classList.toggle("hidden", view !== "list");
  document.getElementById("pack-view-tree").classList.toggle("text-brand-500", view === "tree");
  document.getElementById("pack-view-list").classList.toggle("text-brand-500", view === "list");
}

function packNodeCardHtml(member, isSelf) {
  const initial = escapeHtml((member.pet_name || "P")[0]);
  const photo = member.linked_profile_photo_url
    ? `<img src="${escapeHtml(member.linked_profile_photo_url)}" alt="">`
    : initial;
  const details = [member.relation, member.pet_type].filter(Boolean).map(escapeHtml).join(" · ");
  const selfBadge = isSelf
    ? `<div class="node-self-badge" style="background: var(--brand-500, #e04848);">You</div>`
    : "";
  const clickAttr = isSelf ? "" : `onclick="openPackSidePanel('${member.id}')"`;

  return `
  <div class="node-card ${isSelf ? "node-self" : ""}" ${clickAttr} style="${isSelf ? "" : "cursor:pointer;"}">
    <div class="node-photo">${photo}</div>
    ${selfBadge}
    <div class="node-name">${escapeHtml(isSelf ? (currentUserObj?.pet_name || "Me") : member.pet_name)}</div>
    <div class="node-details">${details}</div>
  </div>`;
}

// Note: pet_pack_members.relation only allows 'Parent' | 'Sibling Pet' |
// 'Other' (a live DB check constraint — confirmed via pg_constraint, not a
// guess), so there's no "Mate"/spouse pairing concept for this schema. The
// tree is a flat one-level structure: the current pet at the root, every
// pack member as a direct child.
function renderPackTree(members) {
  const container = document.getElementById("css-pack-tree-container");
  if (!container) return;

  const selfNode = packNodeCardHtml({}, true);
  const childrenHtml = members.length
    ? `<ul>${members.map((m) => `<li>${packNodeCardHtml(m, false)}</li>`).join("")}</ul>`
    : "";

  container.innerHTML = `
    <ul class="tree-root">
      <li class="${members.length ? "has-children" : ""}">
        ${selfNode}
        ${childrenHtml}
      </li>
    </ul>`;

  if (window.lucide) lucide.createIcons();
  initPackPanZoom();
}

function renderPackList(members) {
  const container = document.getElementById("pack-list-container");
  if (!container) return;
  if (!members.length) {
    container.innerHTML = `<p class="text-sm text-gray-400 text-center py-12">No pack members yet — add one to get started.</p>`;
    return;
  }
  container.innerHTML = `<div class="space-y-2">${members.map((m) => `
    <div class="flex items-center justify-between p-3 rounded-xl border border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50 cursor-pointer" onclick="openPackSidePanel('${m.id}')">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center flex-shrink-0 font-bold text-brand-700 dark:text-brand-300 overflow-hidden">
          ${m.linked_profile_photo_url ? `<img src="${escapeHtml(m.linked_profile_photo_url)}" class="w-full h-full object-cover">` : escapeHtml((m.pet_name || "P")[0])}
        </div>
        <div class="min-w-0">
          <p class="font-bold text-sm text-gray-900 dark:text-white truncate">${escapeHtml(m.pet_name)}</p>
          <p class="text-xs text-gray-400">${[m.relation, m.pet_type, m.breed].filter(Boolean).map(escapeHtml).join(" · ")}</p>
        </div>
      </div>
    </div>`).join("")}</div>`;
}

async function loadPackTree() {
  try {
    const data = await api("get_pack_members", {});
    if (data.status !== "success") {
      showToast(data.message || "Could not load pack tree.", "error");
      return;
    }
    packMembersCache = data.pack_members || [];
    renderPackTree(packMembersCache);
    renderPackList(packMembersCache);
  } catch (err) {
    console.error(err);
    showToast("Could not load pack tree.", "error");
  }
}

// ---------------- Side panel ----------------

let currentSidePanelMemberId = null;

function openPackSidePanel(memberId) {
  const member = packMembersCache.find((m) => m.id === memberId);
  if (!member) return;
  currentSidePanelMemberId = memberId;

  const avatar = document.getElementById("pack-panel-avatar");
  avatar.innerHTML = member.linked_profile_photo_url
    ? `<img src="${escapeHtml(member.linked_profile_photo_url)}" class="w-full h-full object-cover">`
    : escapeHtml((member.pet_name || "P")[0]);
  document.getElementById("pack-panel-name").textContent = member.pet_name;
  document.getElementById("pack-panel-relation").textContent = member.relation;

  const details = document.getElementById("pack-panel-details");
  const rows = [
    ["Pet type", member.pet_type],
    ["Breed", member.breed],
    ["Gender", member.gender],
    ["Date of birth", member.date_of_birth],
    ["Microchip", member.microchip_number],
  ].filter(([, v]) => v);
  details.innerHTML = rows.map(([label, value]) => `
    <div class="flex justify-between border-b border-gray-50 dark:border-gray-800 pb-2">
      <span class="text-gray-400">${escapeHtml(label)}</span>
      <span class="font-semibold">${escapeHtml(value)}</span>
    </div>`).join("") || `<p class="text-gray-400 text-center">No further details.</p>`;

  document.getElementById("pack-side-panel").classList.add("open");
}

function closePackSidePanel() {
  currentSidePanelMemberId = null;
  document.getElementById("pack-side-panel")?.classList.remove("open");
}

async function deleteCurrentPackMember() {
  if (!currentSidePanelMemberId) return;
  if (!confirm("Remove this pack member?")) return;
  const btn = document.getElementById("pack-panel-delete-btn");
  setButtonLoading(btn, true, "Removing…");
  try {
    const data = await api("delete_pack_member", { id: currentSidePanelMemberId });
    if (data.status !== "success") {
      showToast(data.message || "Could not remove pack member.", "error");
      return;
    }
    closePackSidePanel();
    loadPackTree();
  } catch (err) {
    console.error(err);
    showToast("Could not remove pack member.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

// ---------------- Manage members modal ----------------

async function openPackMembersModal() {
  const modal = document.getElementById("pack-members-modal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
  resetPackMemberForm();
  renderPackMembersModalList();
  await populateFriendImportDropdown();
}

function closePackMembersModal() {
  document.getElementById("pack-members-modal")?.classList.add("hidden");
  document.getElementById("pack-members-modal")?.classList.remove("flex");
}

function renderPackMembersModalList() {
  const list = document.getElementById("pack-members-list");
  if (!list) return;
  list.innerHTML = packMembersCache.length
    ? packMembersCache.map((m) => `
      <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800">
        <div class="min-w-0">
          <p class="font-bold text-sm text-gray-900 dark:text-white truncate">${escapeHtml(m.pet_name)}</p>
          <p class="text-xs text-gray-400">${[m.relation, m.pet_type, m.breed].filter(Boolean).map(escapeHtml).join(" · ")}</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button onclick="editPackMember('${m.id}')" class="text-gray-400 hover:text-brand-500"><i data-lucide="pencil" class="w-4 h-4"></i></button>
          <button onclick="deletePackMemberFromModal('${m.id}', this)" class="text-gray-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
        </div>
      </div>`).join("")
    : `<p class="text-xs text-gray-400">No pack members yet.</p>`;
  if (window.lucide) lucide.createIcons();
}

async function populateFriendImportDropdown() {
  const select = document.getElementById("pack-import-friend");
  if (!select) return;
  try {
    const data = await api("get_friends", {});
    if (data.status !== "success") return;
    const friends = data.friends || [];
    select.innerHTML = '<option value="">Select a friend...</option>' + friends.map((f) => `<option value="${f.user_id}">${escapeHtml(f.name)}</option>`).join("");
    select.dataset.friends = JSON.stringify(friends);
  } catch (err) {
    console.error(err);
  }
}

function importFriendForPack(friendId) {
  if (!friendId) return;
  const select = document.getElementById("pack-import-friend");
  const friends = JSON.parse(select.dataset.friends || "[]");
  const friend = friends.find((f) => f.user_id === friendId);
  if (!friend) return;

  document.getElementById("pack-name").value = friend.name || "";
  document.getElementById("pack-linked-user-id").value = friend.user_id;
  if (friend.pet_type) {
    document.getElementById("pack-pet-type").value = friend.pet_type;
    selectBreedWithValue("pack-pet-type", "pack-breed", friend.pet_type, friend.breed);
  }
}

function resetPackMemberForm() {
  ["pack-name", "pack-dob", "pack-microchip"].forEach((id) => (document.getElementById(id).value = ""));
  document.getElementById("pack-relation").value = "Parent";
  document.getElementById("pack-pet-type").value = "";
  document.getElementById("pack-breed").innerHTML = '<option value="">Select Breed...</option>';
  document.getElementById("pack-gender").value = "";
  document.getElementById("pack-editing-id").value = "";
  document.getElementById("pack-linked-user-id").value = "";
  document.getElementById("pack-import-friend").value = "";
  document.getElementById("pack-save-btn-label").textContent = "Add Member";
  document.getElementById("pack-cancel-edit-btn").style.display = "none";
}

function editPackMember(memberId) {
  const member = packMembersCache.find((m) => m.id === memberId);
  if (!member) return;

  document.getElementById("pack-name").value = member.pet_name || "";
  document.getElementById("pack-relation").value = member.relation || "Other";
  document.getElementById("pack-dob").value = member.date_of_birth || "";
  document.getElementById("pack-gender").value = member.gender || "";
  document.getElementById("pack-microchip").value = member.microchip_number || "";
  document.getElementById("pack-editing-id").value = member.id;
  document.getElementById("pack-linked-user-id").value = member.linked_user_id || "";
  selectBreedWithValue("pack-pet-type", "pack-breed", member.pet_type, member.breed);

  document.getElementById("pack-save-btn-label").textContent = "Save Changes";
  document.getElementById("pack-cancel-edit-btn").style.display = "inline-flex";
}

async function savePackMember() {
  const petName = document.getElementById("pack-name").value.trim();
  if (!petName) {
    showToast("Enter a name for this pack member.", "info");
    return;
  }

  const payload = {
    id: document.getElementById("pack-editing-id").value || undefined,
    pet_name: petName,
    relation: document.getElementById("pack-relation").value,
    pet_type: document.getElementById("pack-pet-type").value,
    breed: document.getElementById("pack-breed").value,
    date_of_birth: document.getElementById("pack-dob").value,
    gender: document.getElementById("pack-gender").value,
    microchip_number: document.getElementById("pack-microchip").value.trim(),
    linked_user_id: document.getElementById("pack-linked-user-id").value || undefined,
  };

  const btn = document.getElementById("pack-save-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("save_pack_member", payload);
    if (data.status !== "success") {
      showToast(data.message || "Could not save pack member.", "error");
      return;
    }
    resetPackMemberForm();
    await loadPackTree();
    renderPackMembersModalList();
    showToast("Pack tree updated.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not save pack member.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function deletePackMemberFromModal(memberId, btn) {
  if (!confirm("Remove this pack member?")) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("delete_pack_member", { id: memberId });
    if (data.status !== "success") {
      showToast(data.message || "Could not remove pack member.", "error");
      if (btn) {
        btn.disabled = false;
        btn.classList.remove("opacity-50", "pointer-events-none");
      }
      return;
    }
    await loadPackTree();
    renderPackMembersModalList();
  } catch (err) {
    console.error(err);
    showToast("Could not remove pack member.", "error");
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}
