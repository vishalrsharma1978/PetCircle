// Admin dashboard: shell, shared state/helpers, dispatcher, entry point
// (password re-entry -> 15-min admin mode window), Analytics, Sessions, and
// the static Platform panel. Ported from eSamaj's admin_core.php/admin.js
// pattern (render*Panel -> load* -> api() -> redraw), consolidated rather
// than duplicated (the source had several panels defined twice).

let adminModeActiveUntil = null;

const adminConsoleState = {
  activePanel: "analytics",
  sessions: { statusFilter: "", offset: 0, limit: 20 },
};

const ADMIN_PANEL_TITLES = {
  analytics: "Analytics",
  users: "Users",
  contacts: "Contact Book",
  posts: "Posts",
  events: "Events",
  galleries: "Galleries",
  sessions: "Sessions",
  platform: "Platform",
  roles: "Roles",
  servers: "Servers",
  pet_types: "Pet Types",
  reactions: "Custom Reactions",
  features: "Features & Tabs",
  layout: "Feed Layout",
  ads: "Ads",
};

function adminPanelLoader(panel) {
  const loaders = {
    analytics: loadAdminAnalytics,
    users: typeof loadAdminUsers === "function" ? loadAdminUsers : null,
    contacts: typeof loadAdminContactBook === "function" ? loadAdminContactBook : null,
    posts: typeof loadAdminPosts === "function" ? loadAdminPosts : null,
    events: typeof loadAdminEvents === "function" ? loadAdminEvents : null,
    galleries: typeof loadAdminGalleries === "function" ? loadAdminGalleries : null,
    sessions: loadAdminSessions,
    platform: renderAdminPlatformPanel,
    roles: typeof loadAdminRoles === "function" ? loadAdminRoles : null,
    servers: typeof loadAdminServers === "function" ? loadAdminServers : null,
    pet_types: typeof loadAdminPetTypes === "function" ? loadAdminPetTypes : null,
    reactions: typeof loadAdminReactions === "function" ? loadAdminReactions : null,
    features: typeof loadAdminFeatures === "function" ? loadAdminFeatures : null,
    layout: typeof loadAdminLayout === "function" ? loadAdminLayout : null,
    ads: typeof loadAdminAds === "function" ? loadAdminAds : null,
  };
  return loaders[panel];
}

function switchAdminPanel(panel) {
  document.querySelectorAll(".admin-panel").forEach((el) => el.classList.add("hidden"));
  document.getElementById(`admin-panel-${panel}`)?.classList.remove("hidden");
  document.querySelectorAll("[data-admin-panel]").forEach((btn) => {
    const active = btn.dataset.adminPanel === panel;
    btn.classList.toggle("bg-brand-500/15", active);
    btn.classList.toggle("text-white", active);
    btn.classList.toggle("text-gray-300", !active);
  });
  adminConsoleState.activePanel = panel;
  const titleEl = document.getElementById("admin-panel-page-title");
  if (titleEl) titleEl.textContent = ADMIN_PANEL_TITLES[panel] || panel;

  const loader = adminPanelLoader(panel);
  if (typeof loader === "function") loader();
  if (window.lucide) lucide.createIcons();
}

// ---------------- Shared list-panel helpers ----------------

function adminFilterInput(id, placeholder, value = "") {
  return `<input type="text" id="${id}" placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(value)}"
    class="px-3 py-2 border border-gray-700 rounded-lg text-sm bg-gray-800 text-white placeholder-gray-500">`;
}

function adminFilterSelect(id, value, options) {
  const opts = options.map(([optValue, label]) =>
    `<option value="${escapeHtml(optValue)}" ${optValue === value ? "selected" : ""}>${escapeHtml(label)}</option>`
  ).join("");
  return `<select id="${id}" class="px-3 py-2 border border-gray-700 rounded-lg text-sm bg-gray-800 text-white">${opts}</select>`;
}

function adminPagerHtml(offset, limit, rowCount) {
  const hasNext = rowCount >= limit;
  const hasPrev = offset > 0;
  return `
    <div class="flex items-center justify-between pt-3 text-sm text-gray-400">
      <span>Showing ${offset + 1}-${offset + rowCount}</span>
      <div class="flex gap-2">
        <button ${hasPrev ? "" : "disabled"} onclick="adminChangePage(-1)" class="px-3 py-1.5 rounded-lg border border-gray-700 ${hasPrev ? "hover:bg-gray-800 text-gray-200" : "opacity-40 cursor-not-allowed"}">Previous</button>
        <button ${hasNext ? "" : "disabled"} onclick="adminChangePage(1)" class="px-3 py-1.5 rounded-lg border border-gray-700 ${hasNext ? "hover:bg-gray-800 text-gray-200" : "opacity-40 cursor-not-allowed"}">Next</button>
      </div>
    </div>`;
}

// Generic pager step: relies on the currently active panel's state slice
// (keyed the same as the panel name) having offset/limit fields.
function adminChangePage(direction) {
  const key = adminConsoleState.activePanel;
  const state = adminConsoleState[key];
  if (!state) return;
  state.offset = Math.max(0, state.offset + direction * state.limit);
  const loader = adminPanelLoader(key);
  if (typeof loader === "function") loader();
}

function adminMetricCard(label, value, icon) {
  return `
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 flex items-center gap-3">
      <div class="w-10 h-10 rounded-lg bg-brand-500/10 text-brand-300 flex items-center justify-center flex-shrink-0">
        <i data-lucide="${icon}" class="w-5 h-5"></i>
      </div>
      <div>
        <p class="text-xs text-gray-400">${escapeHtml(label)}</p>
        <p class="text-xl font-bold text-white">${escapeHtml(String(value))}</p>
      </div>
    </div>`;
}

function adminBucketList(title, rows) {
  const items = rows.length
    ? rows.map(([label, count]) => `
        <div class="flex items-center justify-between text-sm py-1.5 border-b border-gray-800 last:border-0">
          <span class="text-gray-300">${escapeHtml(label)}</span>
          <span class="font-bold text-white">${escapeHtml(String(count))}</span>
        </div>`).join("")
    : `<p class="text-sm text-gray-500 py-2">No data yet.</p>`;
  return `
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
      <h4 class="text-sm font-bold text-gray-200 mb-2">${escapeHtml(title)}</h4>
      ${items}
    </div>`;
}

// ---------------- Admin mode entry ----------------

function openAdminEntry() {
  if (adminModeActiveUntil && Date.now() < adminModeActiveUntil) {
    enterAdminDashboard();
  } else {
    openAdminModeModal();
  }
}

function openAdminModeModal() {
  const modal = document.getElementById("admin-mode-modal");
  if (!modal) return;
  document.getElementById("admin-mode-modal-error")?.classList.add("hidden");
  document.getElementById("admin-mode-password").value = "";
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeAdminModeModal() {
  document.getElementById("admin-mode-modal")?.classList.add("hidden");
  document.getElementById("admin-mode-modal")?.classList.remove("flex");
}

async function submitAdminModePassword() {
  const btn = document.getElementById("admin-mode-submit-btn");
  const errorEl = document.getElementById("admin-mode-modal-error");
  errorEl?.classList.add("hidden");
  setButtonLoading(btn, true, "Unlocking…");

  try {
    const password = document.getElementById("admin-mode-password").value;
    const data = await api("enter_admin_mode", { password });
    if (data.status !== "success") {
      errorEl.textContent = data.message || "Could not unlock admin mode.";
      errorEl.classList.remove("hidden");
      return;
    }
    adminModeActiveUntil = Date.parse(data.admin_mode_until);
    closeAdminModeModal();
    enterAdminDashboard();
  } catch (err) {
    console.error(err);
    errorEl.textContent = "Something went wrong. Please try again.";
    errorEl.classList.remove("hidden");
  } finally {
    setButtonLoading(btn, false);
  }
}

function enterAdminDashboard() {
  switchView("view-admin-dashboard");
  document.getElementById("admin-dash-email").textContent = currentUserObj?.email || "";
  document.getElementById("admin-dash-users").textContent = "…";
  renderAdminCapabilities();
  renderAdminModeExpiry();
  switchAdminPanel("analytics");
}

function renderAdminModeExpiry() {
  const el = document.getElementById("admin-dash-mode-expiry");
  if (!el) return;
  if (!adminModeActiveUntil) {
    el.textContent = "—";
    return;
  }
  const mins = Math.max(0, Math.round((adminModeActiveUntil - Date.now()) / 60000));
  el.textContent = `${mins} min`;
}

function renderAdminCapabilities() {
  const box = document.getElementById("admin-dash-capabilities");
  if (!box) return;
  const caps = currentUserObj?.admin_capabilities || [];
  box.innerHTML = caps.length
    ? caps.map((c) => `
        <div class="bg-gray-800 rounded-xl p-3 text-sm">
          <div class="font-bold text-white capitalize">${escapeHtml(c.role.replace(/_/g, " "))}</div>
          <div class="text-gray-400 text-xs mt-0.5">${escapeHtml(c.scope_type)}${c.scope_value && c.scope_value !== "*" ? `: ${escapeHtml(c.scope_value)}` : ""}</div>
        </div>`).join("")
    : `<p class="text-sm text-gray-400">No scopes assigned.</p>`;
}

async function exitAdminModeAndReturn() {
  try {
    await api("exit_admin_mode", {});
  } catch (err) {
    console.warn("exit_admin_mode failed:", err);
  }
  adminModeActiveUntil = null;
  switchView("view-social-feed");
  switchSocialTab("hub");
}

document.addEventListener("click", (e) => {
  if (e.target.id === "admin-mode-modal") closeAdminModeModal();
});

// ---------------- Analytics panel ----------------

async function loadAdminAnalytics() {
  const box = document.getElementById("admin-panel-analytics");
  if (!box) return;
  box.innerHTML = `<p class="text-sm text-gray-400 py-8 text-center">Loading analytics…</p>`;

  try {
    const data = await api("admin_get_analytics", {});
    if (data.status !== "success") {
      box.innerHTML = `<p class="text-sm text-gray-400 py-8 text-center">Could not load analytics.</p>`;
      return;
    }

    const c = data.counts || {};
    document.getElementById("admin-dash-users").textContent = c.users ?? 0;

    const metricCards = [
      adminMetricCard("Users", c.users ?? 0, "users"),
      adminMetricCard("Posts", c.posts ?? 0, "newspaper"),
      adminMetricCard("Groups", c.groups ?? 0, "users-round"),
      adminMetricCard("Events", c.events ?? 0, "calendar-range"),
      adminMetricCard("Galleries", c.gallery_collections ?? 0, "images"),
      adminMetricCard("Rescue posts", c.rescue_opportunities ?? 0, "hand-heart"),
      adminMetricCard("Active sessions", c.active_sessions ?? 0, "shield-ellipsis"),
      adminMetricCard("Failed logins (24h)", c.failed_logins_24h ?? 0, "alert-triangle"),
    ].join("");

    const postsByPetType = Object.entries(data.posts_by_pet_type || {});
    const usersByRole = Object.entries(data.users_by_role || {});

    box.innerHTML = `
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">${metricCards}</div>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
        ${adminBucketList("Posts by pet type", postsByPetType)}
        ${adminBucketList("Users by role", usersByRole)}
      </div>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
    box.innerHTML = `<p class="text-sm text-gray-400 py-8 text-center">Could not load analytics.</p>`;
  }
}

// ---------------- Sessions panel ----------------

async function loadAdminSessions() {
  const box = document.getElementById("admin-panel-sessions");
  if (!box) return;
  const state = adminConsoleState.sessions;

  box.innerHTML = `
    <div class="flex flex-wrap gap-2 mb-3">
      ${adminFilterSelect("admin-sessions-status", state.statusFilter, [["", "All"], ["active", "Active"], ["revoked", "Revoked"]])}
    </div>
    <div id="admin-sessions-list" class="space-y-2"><p class="text-sm text-gray-400 py-6 text-center">Loading…</p></div>`;
  document.getElementById("admin-sessions-status").onchange = (e) => { state.statusFilter = e.target.value; state.offset = 0; loadAdminSessions(); };

  try {
    const data = await api("admin_list_sessions", { status_filter: state.statusFilter, offset: state.offset, limit: state.limit });
    const list = document.getElementById("admin-sessions-list");
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-sm text-gray-400 py-6 text-center">Could not load sessions.</p>`;
      return;
    }
    const sessions = data.sessions || [];
    list.innerHTML = sessions.length
      ? sessions.map((s) => `
          <div class="bg-gray-900 border border-gray-800 rounded-xl p-3 flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-bold text-white truncate">${escapeHtml(s.profile?.pet_name || s.user_id)}</p>
              <p class="text-xs text-gray-400 truncate">${escapeHtml(s.user_agent || "Unknown device")} · created ${escapeHtml(timeAgo(s.created_at))}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${s.is_active ? "bg-emerald-500/15 text-emerald-300" : "bg-gray-700 text-gray-400"}">${s.is_active ? "Active" : "Inactive"}</span>
              ${s.is_active ? `<button onclick="adminRevokeSession('${s.id}')" class="px-2.5 py-1 rounded-lg bg-red-500/15 text-red-300 text-xs font-bold hover:bg-red-500/25">Revoke</button>` : ""}
            </div>
          </div>`).join("") + adminPagerHtml(state.offset, state.limit, sessions.length)
      : `<p class="text-sm text-gray-400 py-6 text-center">No sessions found.</p>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
  }
}

async function adminRevokeSession(sessionId) {
  if (!confirm("Revoke this session?")) return;
  try {
    const data = await api("admin_revoke_session", { session_id: sessionId });
    if (data.status !== "success") {
      showToast(data.message || "Could not revoke session.", "error");
      return;
    }
    showToast("Session revoked.", "success");
    loadAdminSessions();
  } catch (err) {
    console.error(err);
  }
}

// ---------------- Platform panel (static) ----------------

function renderAdminPlatformPanel() {
  const box = document.getElementById("admin-panel-platform");
  if (!box) return;
  box.innerHTML = `
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
      <h4 class="font-bold text-white mb-2">Infrastructure monitoring</h4>
      <p class="text-sm text-gray-400">Real-time infra metrics (CPU, memory, request latency) live outside this console. See the Servers panel for node-level health and the Sessions panel for live access review.</p>
    </div>
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
      <h4 class="font-bold text-white mb-2">What this console controls</h4>
      <p class="text-sm text-gray-400">User moderation and roles, content takedown, pet-type theming, feature/tab visibility, feed layout, custom reactions, sponsored ad slots, and infrastructure node tracking. Live streaming (Zoom) admin controls are deferred — no Zoom integration exists yet in this rebuild.</p>
    </div>`;
}
