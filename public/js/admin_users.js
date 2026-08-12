// Admin dashboard: Users and Roles panels. Ported from eSamaj's
// admin_users.php/admin.js Users+Roles panels — religion/community filter
// columns replaced with pet_type/breed, and role grants use this rebuild's
// admin_roles vocabulary (owner | platform_admin | pet_type_admin |
// breed_admin) instead of eSamaj's religion_admin/community_admin.

const ADMIN_PET_TYPES = ["Dog", "Cat", "Bird", "Fish", "Small Pet", "Reptile"];

adminConsoleState.users = { search: "", roleFilter: "", statusFilter: "", petType: "", offset: 0, limit: 20 };
adminConsoleState.roles = {};
let adminUsersCache = {};

function adminUserStatusBadges(user) {
  const badges = [];
  if (user.deactivated_at) badges.push('<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-700 text-gray-300">Deactivated</span>');
  if (user.is_verified) badges.push('<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-500/15 text-brand-300">Verified</span>');
  (user.active_flags || []).forEach((flag) => {
    const styles = { watch: "bg-blue-500/15 text-blue-300", warning: "bg-amber-500/15 text-amber-300", blacklist: "bg-orange-500/15 text-orange-300", suspension: "bg-red-500/15 text-red-300", ban: "bg-red-600/25 text-red-300" };
    badges.push(`<span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${styles[flag] || "bg-gray-700 text-gray-300"} capitalize">${escapeHtml(flag)}</span>`);
  });
  return badges.join(" ");
}

async function loadAdminUsers() {
  const box = document.getElementById("admin-panel-users");
  if (!box) return;
  const state = adminConsoleState.users;

  box.innerHTML = `
    <div class="flex flex-wrap gap-2 mb-3">
      ${adminFilterInput("admin-users-search", "Search email…", state.search)}
      ${adminFilterSelect("admin-users-role", state.roleFilter, [["", "All roles"], ["member", "Member"], ["admin", "Admin"]])}
      ${adminFilterSelect("admin-users-status", state.statusFilter, [["", "Any status"], ["active", "Active"], ["deactivated", "Deactivated"]])}
      ${adminFilterSelect("admin-users-pet-type", state.petType, [["", "Any pet type"], ...ADMIN_PET_TYPES.map((t) => [t, t])])}
    </div>
    <div id="admin-users-list" class="space-y-2"><p class="text-sm text-gray-400 py-6 text-center">Loading…</p></div>
    <div id="admin-user-detail-wrap"></div>`;

  const bind = (id, field) => {
    document.getElementById(id).oninput = document.getElementById(id).onchange = (e) => {
      state[field] = e.target.value;
      state.offset = 0;
      loadAdminUsersList();
    };
  };
  bind("admin-users-search", "search");
  bind("admin-users-role", "roleFilter");
  bind("admin-users-status", "statusFilter");
  bind("admin-users-pet-type", "petType");

  loadAdminUsersList();
}

async function loadAdminUsersList() {
  const list = document.getElementById("admin-users-list");
  if (!list) return;
  const state = adminConsoleState.users;

  try {
    const data = await api("admin_list_users", {
      search: state.search, role_filter: state.roleFilter, status_filter: state.statusFilter,
      pet_type: state.petType, offset: state.offset, limit: state.limit,
    });
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Could not load users.</p>`;
      return;
    }
    const users = data.users || [];
    users.forEach((u) => { adminUsersCache[u.id] = u; });

    list.innerHTML = users.length
      ? users.map((u) => `
          <button onclick="openAdminUserDetail('${u.id}')" class="w-full text-left bg-gray-900 border border-gray-800 rounded-xl p-3 flex items-center justify-between gap-3 hover:border-brand-500/40 transition-colors">
            <div class="min-w-0">
              <p class="text-sm font-bold text-white truncate">${escapeHtml(u.profile?.pet_name || "Unnamed")} <span class="font-normal text-gray-400">· ${escapeHtml(u.email)}</span></p>
              <p class="text-xs text-gray-400 truncate">${escapeHtml([u.profile?.pet_type, u.profile?.breed].filter(Boolean).join(" · ") || "No pet type set")} · role: ${escapeHtml(u.role)}</p>
            </div>
            <div class="flex flex-wrap gap-1 justify-end flex-shrink-0 max-w-[45%]">${adminUserStatusBadges(u)}</div>
          </button>`).join("") + adminPagerHtml(state.offset, state.limit, users.length)
      : `<p class="text-sm text-gray-400 py-6 text-center">No users match these filters.</p>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
  }
}

async function openAdminUserDetail(userId) {
  const wrap = document.getElementById("admin-user-detail-wrap");
  if (!wrap) return;
  wrap.innerHTML = `<div class="fixed inset-0 z-[170] bg-gray-950/70 flex items-start justify-center p-4 overflow-y-auto" id="admin-user-detail-overlay">
    <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-2xl my-8 p-6"><p class="text-sm text-gray-400 py-8 text-center">Loading…</p></div>
  </div>`;
  document.getElementById("admin-user-detail-overlay").onclick = (e) => { if (e.target.id === "admin-user-detail-overlay") wrap.innerHTML = ""; };

  try {
    const data = await api("admin_get_user_detail", { user_id: userId });
    if (data.status !== "success") {
      wrap.innerHTML = "";
      showToast(data.message || "Could not load user.", "error");
      return;
    }
    renderAdminUserDetail(data.user);
  } catch (err) {
    console.error(err);
  }
}

function adminDetailFact(label, value) {
  return `<div><p class="text-[10px] uppercase tracking-wide text-gray-500 font-bold">${escapeHtml(label)}</p><p class="text-sm text-gray-200">${value || "—"}</p></div>`;
}

function renderAdminUserDetail(user) {
  const wrap = document.getElementById("admin-user-detail-wrap");
  if (!wrap) return;
  const p = user.profile || {};

  const rolesHtml = (user.admin_roles || []).length
    ? user.admin_roles.map((r) => `
        <div class="flex items-center justify-between text-sm bg-gray-800 rounded-lg px-3 py-2">
          <span class="text-gray-200 capitalize">${escapeHtml(r.role.replace(/_/g, " "))} <span class="text-gray-500">(${escapeHtml(r.scope_type)}${r.scope_value && r.scope_value !== "*" ? ":" + escapeHtml(r.scope_value) : ""})</span></span>
          <button onclick="adminRevokeRole('${r.id}', '${user.id}', this)" class="text-red-400 hover:text-red-300 text-xs font-bold">Revoke</button>
        </div>`).join("")
    : `<p class="text-xs text-gray-500">No admin roles granted.</p>`;

  const notesHtml = (user.notes || []).slice(0, 8).map((n) => `
    <div class="text-xs text-gray-400 border-l-2 border-gray-700 pl-2 py-0.5">
      <span class="font-bold text-gray-300 capitalize">${escapeHtml(n.note_type)}</span> — ${escapeHtml(n.note)} <span class="text-gray-600">(${escapeHtml(timeAgo(n.created_at))})</span>
    </div>`).join("") || `<p class="text-xs text-gray-500">No notes yet.</p>`;

  const actionsHtml = (user.actions || []).filter((a) => a.is_active).map((a) => `
    <div class="flex items-center justify-between text-sm bg-red-500/10 border border-red-500/20 rounded-lg px-3 py-2">
      <span class="text-red-200 capitalize">${escapeHtml(a.action_type)} — ${escapeHtml(a.reason || "")}</span>
      <button onclick="adminResolveAction('${a.id}', '${user.id}', this)" class="text-emerald-400 hover:text-emerald-300 text-xs font-bold">Clear</button>
    </div>`).join("") || `<p class="text-xs text-gray-500">No active flags.</p>`;

  const sessionsHtml = (user.sessions || []).slice(0, 8).map((s) => `
    <div class="text-xs text-gray-400 flex justify-between border-b border-gray-800 py-1">
      <span>${escapeHtml(s.user_agent || "Unknown device")}</span>
      <span class="${!s.revoked_at ? "text-emerald-400" : "text-gray-600"}">${!s.revoked_at ? "active" : "revoked"} · ${escapeHtml(timeAgo(s.created_at))}</span>
    </div>`).join("") || `<p class="text-xs text-gray-500">No session history.</p>`;

  document.getElementById("admin-user-detail-wrap").innerHTML = `
    <div class="fixed inset-0 z-[170] bg-gray-950/70 flex items-start justify-center p-4 overflow-y-auto" id="admin-user-detail-overlay">
      <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-2xl my-8 p-6 space-y-5">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="text-lg font-bold text-white">${escapeHtml(p.pet_name || "Unnamed pet")}</h3>
            <p class="text-sm text-gray-400">${escapeHtml(user.email)}</p>
          </div>
          <button onclick="document.getElementById('admin-user-detail-wrap').innerHTML=''" class="p-1.5 rounded-full hover:bg-gray-800 text-gray-400"><i data-lucide="x" class="w-5 h-5"></i></button>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          ${adminDetailFact("Pet type", escapeHtml([p.pet_type, p.breed].filter(Boolean).join(" · ")))}
          ${adminDetailFact("City", escapeHtml(p.current_city || ""))}
          ${adminDetailFact("Role", escapeHtml(user.role))}
          ${adminDetailFact("Joined", escapeHtml(timeAgo(user.created_at)))}
        </div>

        <div class="flex flex-wrap gap-2">
          <button onclick="adminUserStatusOp('${user.id}','${user.deactivated_at ? "reactivate" : "deactivate"}', this)" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs font-bold text-gray-200">${user.deactivated_at ? "Reactivate" : "Deactivate"}</button>
          <button onclick="adminUserStatusOp('${user.id}','sign_out', this)" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs font-bold text-gray-200">Sign out everywhere</button>
          <button onclick="adminUserStatusOp('${user.id}','${user.is_verified ? "unverify" : "verify"}', this)" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs font-bold text-gray-200">${user.is_verified ? "Remove verified badge" : "Mark verified"}</button>
          <button onclick="adminClearSessionHistory('${user.id}', this)" class="px-3 py-1.5 rounded-lg bg-gray-800 hover:bg-gray-700 text-xs font-bold text-gray-200">Clear old sessions</button>
        </div>

        <div>
          <h4 class="text-sm font-bold text-gray-200 mb-2">Admin roles</h4>
          <div class="space-y-1.5 mb-2">${rolesHtml}</div>
          <div class="flex flex-wrap gap-2 items-center">
            <select id="admin-grant-role-select" class="px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white">
              <option value="platform_admin">platform_admin</option>
              <option value="pet_type_admin">pet_type_admin</option>
              <option value="breed_admin">breed_admin</option>
              <option value="owner">owner</option>
            </select>
            <input id="admin-grant-role-scope" placeholder="scope value (e.g. Dog)" class="px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white w-40">
            <button onclick="adminGrantRoleFromDetail('${user.id}', this)" class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white">Grant</button>
          </div>
        </div>

        <div>
          <h4 class="text-sm font-bold text-gray-200 mb-2">Active flags</h4>
          <div class="space-y-1.5">${actionsHtml}</div>
        </div>

        <div>
          <h4 class="text-sm font-bold text-gray-200 mb-2">Add note / flag</h4>
          <div class="flex flex-wrap gap-2 mb-2">
            <select id="admin-note-type" class="px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white">
              <option value="general">general note</option>
              <option value="watch">watch</option>
              <option value="warning">warning</option>
              <option value="blacklist">blacklist</option>
              <option value="suspension">suspension</option>
              <option value="ban">ban</option>
            </select>
            <input id="admin-note-duration" type="number" min="0" placeholder="duration (days, 0=indefinite)" class="px-2 py-1.5 rounded-lg text-xs bg-gray-800 border border-gray-700 text-white w-56">
          </div>
          <textarea id="admin-note-text" rows="2" placeholder="Note details…" class="w-full px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white mb-2"></textarea>
          <button onclick="adminAddNote('${user.id}', this)" class="px-3 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 text-xs font-bold text-white">Save</button>
          <div class="mt-3 space-y-1">${notesHtml}</div>
        </div>

        <div>
          <h4 class="text-sm font-bold text-gray-200 mb-2">Recent sessions</h4>
          <div>${sessionsHtml}</div>
        </div>
      </div>
    </div>`;
  document.getElementById("admin-user-detail-overlay").onclick = (e) => { if (e.target.id === "admin-user-detail-overlay") document.getElementById("admin-user-detail-wrap").innerHTML = ""; };
  if (window.lucide) lucide.createIcons();
}

async function adminUserStatusOp(userId, op, btn) {
  if (btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("admin_update_user_status", { user_id: userId, op });
    if (data.status !== "success") {
      showToast(data.message || "Could not update user.", "error");
      return;
    }
    showToast("Updated.", "success");
    openAdminUserDetail(userId);
    loadAdminUsersList();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function adminClearSessionHistory(userId, btn) {
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("admin_clear_user_session_history", { user_id: userId });
    if (data.status !== "success") {
      showToast(data.message || "Could not clear sessions.", "error");
      return;
    }
    showToast(`Cleared ${data.cleared_count} old session(s).`, "success");
    openAdminUserDetail(userId);
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function adminAddNote(userId, btn) {
  const noteType = document.getElementById("admin-note-type").value;
  const note = document.getElementById("admin-note-text").value.trim();
  const durationDays = document.getElementById("admin-note-duration").value;
  if (!note) {
    showToast("Note text is required.", "info");
    return;
  }
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("admin_add_user_note", { user_id: userId, note_type: noteType, note, duration_days: durationDays });
    if (data.status !== "success") {
      showToast(data.message || "Could not save note.", "error");
      return;
    }
    showToast("Saved.", "success");
    openAdminUserDetail(userId);
    loadAdminUsersList();
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function adminResolveAction(actionId, userId, btn) {
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("admin_resolve_user_action", { action_id: actionId });
    if (data.status !== "success") {
      showToast(data.message || "Could not clear flag.", "error");
      return;
    }
    showToast("Flag cleared.", "success");
    openAdminUserDetail(userId);
    loadAdminUsersList();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function adminGrantRoleFromDetail(userId, btn) {
  const role = document.getElementById("admin-grant-role-select").value;
  const scopeValueRaw = document.getElementById("admin-grant-role-scope").value.trim();
  const scopeType = role === "pet_type_admin" ? "pet_type" : role === "breed_admin" ? "breed" : "global";
  setButtonLoading(btn, true, "Granting…");
  try {
    const data = await api("grant_admin_role", { user_id: userId, role, scope_type: scopeType, scope_value: scopeValueRaw || "*" });
    if (data.status !== "success") {
      showToast(data.message || "Could not grant role.", "error");
      return;
    }
    showToast("Role granted.", "success");
    openAdminUserDetail(userId);
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function adminRevokeRole(roleId, userId, btn) {
  if (!confirm("Revoke this admin role?")) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("revoke_admin_role", { role_id: roleId });
    if (data.status !== "success") {
      showToast(data.message || "Could not revoke role.", "error");
      return;
    }
    showToast("Role revoked.", "success");
    if (userId) openAdminUserDetail(userId);
    if (adminConsoleState.activePanel === "roles") loadAdminRoles();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

// ---------------- Roles panel ----------------

async function loadAdminRoles() {
  const box = document.getElementById("admin-panel-roles");
  if (!box) return;
  box.innerHTML = `
    <div class="bg-brand-500/10 border border-brand-500/20 rounded-xl p-4 text-sm text-brand-100">
      To grant a new role, open a user from the Users panel and use "Grant" under Admin roles.
    </div>
    <div id="admin-roles-list" class="space-y-2"><p class="text-sm text-gray-400 py-6 text-center">Loading…</p></div>`;

  try {
    const data = await api("list_admin_roles", {});
    const list = document.getElementById("admin-roles-list");
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">${escapeHtml(data.message || "Could not load roles.")}</p>`;
      return;
    }
    const roles = data.roles || [];
    list.innerHTML = roles.length
      ? roles.map((r) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3 flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-bold text-white truncate">${escapeHtml(r.profile?.pet_name || r.email)} <span class="font-normal text-gray-400">· ${escapeHtml(r.email)}</span></p>
              <p class="text-xs text-gray-400 capitalize">${escapeHtml(r.role.replace(/_/g, " "))} (${escapeHtml(r.scope_type)}${r.scope_value && r.scope_value !== "*" ? ":" + escapeHtml(r.scope_value) : ""})</p>
            </div>
            <button onclick="adminRevokeRole('${r.id}', '${r.user_id}', this)" class="px-2.5 py-1 rounded-lg bg-red-500/15 text-red-300 text-xs font-bold hover:bg-red-500/25 flex-shrink-0">Revoke</button>
          </div>`).join("")
      : `<p class="text-sm text-gray-400 py-6 text-center">No active admin roles.</p>`;
  } catch (err) {
    console.error(err);
  }
}
