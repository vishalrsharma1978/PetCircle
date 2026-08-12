// Account Settings: a focused, real-features-only port of eSamaj's Settings
// tab (Overview, Personal details, Security, Privacy, Blocked accounts,
// Danger zone). eSamaj's Community/Family/Galleries/Posts/Archived sections
// were deliberately not ported — see the step-13g plan for why (they either
// don't map onto this pet-focused app or duplicate tabs this app already has).

const SETTINGS_SECTIONS = [
  { key: "overview", label: "Overview", icon: "layout-dashboard" },
  { key: "personal", label: "Personal details", icon: "id-card" },
  { key: "security", label: "Security", icon: "shield-check" },
  { key: "privacy", label: "Privacy", icon: "eye-off" },
  { key: "blocked", label: "Blocked accounts", icon: "user-x" },
  { key: "danger", label: "Danger zone", icon: "triangle-alert" },
];

let settingsState = {
  activeSection: "overview",
  account: null,
  profile: null,
  activeSessions: [],
  currentSessionId: null,
  privacySettings: {},
  blockedUsers: [],
};

async function loadAccountSettings() {
  const container = document.getElementById("social-tab-settings");
  if (!container) return;
  container.innerHTML = `<div class="warm-glass rounded-2xl p-8 text-center text-sm text-gray-400">Loading settings…</div>`;
  try {
    const [accountData, privacyData, blockedData] = await Promise.all([
      api("get_account_settings", {}),
      api("get_privacy_settings", {}),
      api("get_blocked_users", {}),
    ]);
    if (accountData.status === "success") {
      settingsState.account = accountData.account;
      settingsState.profile = accountData.profile;
      settingsState.activeSessions = accountData.active_sessions || [];
      settingsState.currentSessionId = accountData.current_session_id;
    }
    settingsState.privacySettings = privacyData.status === "success" ? (privacyData.privacy_settings || {}) : {};
    settingsState.blockedUsers = blockedData.status === "success" ? (blockedData.blocked || []) : [];
  } catch (err) {
    console.error(err);
  }
  renderSettingsTab();
}

function switchSettingsSection(key) {
  settingsState.activeSection = key;
  renderSettingsTab();
}

function renderSettingsTab() {
  const container = document.getElementById("social-tab-settings");
  if (!container) return;

  container.innerHTML = `
    <div class="flex flex-col lg:flex-row gap-6">
      <aside class="warm-glass rounded-[20px] p-2 flex lg:flex-col gap-1 overflow-x-auto lg:overflow-visible lg:w-56 flex-shrink-0">
        ${SETTINGS_SECTIONS.map((s) => `
          <button type="button" onclick="switchSettingsSection('${s.key}')" data-settings-section="${s.key}"
            class="social-tab-btn ${settingsState.activeSection === s.key ? "active-tab" : ""} whitespace-nowrap flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm font-medium">
            <i data-lucide="${s.icon}" class="w-4 h-4"></i> ${s.label}
          </button>`).join("")}
      </aside>
      <div class="flex-1 min-w-0">${renderSettingsSectionHtml(settingsState.activeSection)}</div>
    </div>`;
  if (window.lucide) lucide.createIcons();
}

function renderSettingsSectionHtml(section) {
  switch (section) {
    case "personal": return renderSettingsPersonalHtml();
    case "security": return renderSettingsSecurityHtml();
    case "privacy": return renderSettingsPrivacyHtml();
    case "blocked": return renderSettingsBlockedHtml();
    case "danger": return renderSettingsDangerHtml();
    default: return renderSettingsOverviewHtml();
  }
}

// ---------------- Overview ----------------

function renderSettingsOverviewHtml() {
  const account = settingsState.account || {};
  const profile = settingsState.profile || {};
  return `
    <div class="warm-glass rounded-2xl overflow-hidden">
      <div class="h-20 bg-gradient-to-r from-brand-400 to-brand-600"></div>
      <div class="p-5 -mt-10">
        <div class="w-16 h-16 rounded-full bg-white dark:bg-gray-900 p-1 shadow-lg">
          <div class="w-full h-full rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden">
            ${profile.profile_photo_url ? `<img src="${escapeHtml(profile.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="font-bold text-brand-700 dark:text-brand-300 text-xl">${escapeHtml((profile.pet_name || "P")[0])}</span>`}
          </div>
        </div>
        <h3 class="mt-3 text-lg font-extrabold text-gray-900 dark:text-white">${escapeHtml(profile.pet_name || "Your pet")}</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400">${escapeHtml(account.email || "")}</p>
        <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
          <div><p class="text-[10px] uppercase text-gray-400 font-bold">Member since</p><p class="text-sm text-gray-800 dark:text-gray-200">${account.created_at ? new Date(account.created_at).toLocaleDateString() : "—"}</p></div>
          <div><p class="text-[10px] uppercase text-gray-400 font-bold">Verified</p><p class="text-sm text-gray-800 dark:text-gray-200">${account.is_verified ? "Yes" : "Not yet"}</p></div>
          <div><p class="text-[10px] uppercase text-gray-400 font-bold">Active sessions</p><p class="text-sm text-gray-800 dark:text-gray-200">${settingsState.activeSessions.length}</p></div>
        </div>
      </div>
    </div>`;
}

// ---------------- Personal details ----------------

function renderSettingsPersonalHtml() {
  const p = settingsState.profile || {};
  return `
    <div class="warm-glass rounded-2xl p-5 space-y-3 max-w-lg">
      <h3 class="font-bold text-gray-900 dark:text-white">Personal details</h3>
      <label class="block text-xs font-bold text-gray-600 dark:text-gray-400">Your name
        <input id="settings-parent-name" value="${escapeHtml(p.parent_name || "")}" class="mt-1 w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </label>
      <label class="block text-xs font-bold text-gray-600 dark:text-gray-400">Phone
        <input id="settings-mobile" value="${escapeHtml(p.mobile_number || "")}" class="mt-1 w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </label>
      <label class="block text-xs font-bold text-gray-600 dark:text-gray-400">Occupation
        <input id="settings-occupation" value="${escapeHtml(p.occupation || "")}" class="mt-1 w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </label>
      <label class="block text-xs font-bold text-gray-600 dark:text-gray-400">City
        <input id="settings-city" value="${escapeHtml(p.current_city || "")}" class="mt-1 w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </label>
      <label class="block text-xs font-bold text-gray-600 dark:text-gray-400">Bio
        <textarea id="settings-bio" rows="3" class="mt-1 w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white resize-none">${escapeHtml(p.bio || "")}</textarea>
      </label>
      <label class="block text-xs font-bold text-gray-600 dark:text-gray-400">Profile visibility
        <select id="settings-visibility" class="mt-1 w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
          <option value="public" ${p.visibility === "public" ? "selected" : ""}>Public</option>
          <option value="pet_type" ${p.visibility === "pet_type" ? "selected" : ""}>My pet type only</option>
          <option value="breed" ${p.visibility === "breed" ? "selected" : ""}>My breed only</option>
          <option value="private" ${p.visibility === "private" ? "selected" : ""}>Private</option>
        </select>
      </label>
      <button id="settings-personal-save-btn" type="button" onclick="saveSettingsPersonal()" class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Save changes</button>
    </div>`;
}

async function saveSettingsPersonal() {
  const btn = document.getElementById("settings-personal-save-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("update_profile", {
      parent_name: document.getElementById("settings-parent-name").value.trim(),
      mobile_number: document.getElementById("settings-mobile").value.trim(),
      occupation: document.getElementById("settings-occupation").value.trim(),
      current_city: document.getElementById("settings-city").value.trim(),
      bio: document.getElementById("settings-bio").value.trim(),
      visibility: document.getElementById("settings-visibility").value,
    });
    if (data.status !== "success") {
      showToast(data.message || "Could not save changes.", "error");
      return;
    }
    showToast("Personal details saved.", "success");
    loadAccountSettings();
  } catch (err) {
    console.error(err);
    showToast("Could not save changes.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

// ---------------- Security ----------------

function renderSettingsSecurityHtml() {
  const account = settingsState.account || {};
  const sessions = settingsState.activeSessions || [];
  return `
    <div class="space-y-5 max-w-lg">
      <div class="warm-glass rounded-2xl p-5 space-y-3">
        <h3 class="font-bold text-gray-900 dark:text-white">Change email or password</h3>
        <p class="text-xs text-gray-500 dark:text-gray-400">Current email: ${escapeHtml(account.email || "")}</p>
        <input id="settings-current-password" type="password" placeholder="Current password" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <input id="settings-new-email" type="email" placeholder="New email (optional)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <input id="settings-new-password" type="password" placeholder="New password (optional, 10+ chars)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <button id="settings-credentials-save-btn" type="button" onclick="saveSettingsCredentials()" class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Update</button>
      </div>

      <div class="warm-glass rounded-2xl p-5 space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="font-bold text-gray-900 dark:text-white">Signed-in devices (${sessions.length})</h3>
          <button type="button" onclick="signOutOtherDevices()" class="text-xs font-bold text-red-500 hover:underline">Sign out other devices</button>
        </div>
        <div class="space-y-2">
          ${sessions.length ? sessions.map((s) => `
            <div class="flex items-center justify-between text-sm p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800">
              <span class="truncate text-gray-700 dark:text-gray-200">${escapeHtml(s.user_agent || "Unknown device")}${String(s.id) === String(settingsState.currentSessionId) ? ` <span class="text-[10px] font-bold text-brand-500 uppercase">This device</span>` : ""}</span>
              <span class="text-xs text-gray-400 flex-shrink-0">${s.last_seen_at ? timeAgo(s.last_seen_at) : ""}</span>
            </div>`).join("") : `<p class="text-xs text-gray-400">No active sessions found.</p>`}
        </div>
      </div>
    </div>`;
}

async function saveSettingsCredentials() {
  const currentPassword = document.getElementById("settings-current-password").value;
  const newEmail = document.getElementById("settings-new-email").value.trim();
  const newPassword = document.getElementById("settings-new-password").value;
  if (!currentPassword) {
    showToast("Enter your current password.", "info");
    return;
  }
  if (!newEmail && !newPassword) {
    showToast("Enter a new email or new password.", "info");
    return;
  }
  const btn = document.getElementById("settings-credentials-save-btn");
  setButtonLoading(btn, true, "Updating…");
  try {
    const data = await api("change_account_credentials", {
      current_password: currentPassword,
      new_email: newEmail,
      new_password: newPassword,
    });
    if (data.status !== "success") {
      showToast(data.message || "Could not update credentials.", "error");
      return;
    }
    showToast("Credentials updated.", "success");
    loadAccountSettings();
  } catch (err) {
    console.error(err);
    showToast("Could not update credentials.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function signOutOtherDevices() {
  if (!confirm("Sign out of all other devices? Only this device will stay signed in.")) return;
  try {
    const data = await api("sign_out_other_devices", {});
    if (data.status !== "success") {
      showToast(data.message || "Could not sign out other devices.", "error");
      return;
    }
    showToast(`Signed out ${data.signed_out_count} other device(s).`, "success");
    loadAccountSettings();
  } catch (err) {
    console.error(err);
    showToast("Could not sign out other devices.", "error");
  }
}

// ---------------- Privacy ----------------

const SETTINGS_PRIVACY_TOGGLES = [
  { key: "hide_online_status", label: "Hide my online status", hint: "Other pet parents won't see when you're active." },
  { key: "hide_phone", label: "Hide my phone number", hint: "Only visible to you, even on your public profile." },
  { key: "hide_email", label: "Hide my email", hint: "Your email is never shown by default — this keeps it that way everywhere." },
];

function renderSettingsPrivacyHtml() {
  const settings = settingsState.privacySettings || {};
  return `
    <div class="warm-glass rounded-2xl p-5 space-y-4 max-w-lg">
      <h3 class="font-bold text-gray-900 dark:text-white">Privacy</h3>
      ${SETTINGS_PRIVACY_TOGGLES.map((t) => `
        <label class="flex items-start justify-between gap-3 py-2 border-b border-gray-100 dark:border-gray-800 last:border-0 cursor-pointer">
          <span>
            <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">${escapeHtml(t.label)}</span>
            <span class="block text-xs text-gray-400">${escapeHtml(t.hint)}</span>
          </span>
          <input type="checkbox" data-privacy-key="${t.key}" ${settings[t.key] ? "checked" : ""} onchange="saveSettingsPrivacyToggle('${t.key}', this.checked)" class="mt-1 rounded border-gray-300 flex-shrink-0">
        </label>`).join("")}
    </div>`;
}

async function saveSettingsPrivacyToggle(key, checked) {
  try {
    const data = await api("save_privacy_settings", { [key]: checked });
    if (data.status !== "success") {
      showToast(data.message || "Could not save privacy setting.", "error");
      return;
    }
    settingsState.privacySettings = data.privacy_settings || settingsState.privacySettings;
    showToast("Privacy setting saved.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not save privacy setting.", "error");
  }
}

// ---------------- Blocked accounts ----------------

function renderSettingsBlockedHtml() {
  const blocked = settingsState.blockedUsers || [];
  return `
    <div class="space-y-5 max-w-lg">
      <div class="warm-glass rounded-2xl p-5 space-y-3">
        <h3 class="font-bold text-gray-900 dark:text-white">Block someone</h3>
        <div class="relative">
          <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-gray-400"></i>
          <input type="text" id="settings-block-search-input" placeholder="Search pets by name…" oninput="debounceSettingsBlockSearch(this.value)"
            class="w-full pl-9 pr-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        </div>
        <div id="settings-block-search-results" class="space-y-2"></div>
      </div>
      <div class="warm-glass rounded-2xl p-5 space-y-2">
        <h3 class="font-bold text-gray-900 dark:text-white">Blocked accounts (${blocked.length})</h3>
        ${blocked.length ? blocked.map((b) => `
          <div class="flex items-center justify-between p-2.5 rounded-lg bg-gray-50 dark:bg-gray-800" data-blocked-user-id="${escapeHtml(b.user_id)}">
            <div class="flex items-center gap-2 min-w-0">
              <div class="w-8 h-8 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center flex-shrink-0 overflow-hidden">
                ${b.profile_photo_url ? `<img src="${escapeHtml(b.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="text-xs font-bold text-brand-700 dark:text-brand-300">${escapeHtml((b.name || "P")[0])}</span>`}
              </div>
              <span class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate">${escapeHtml(b.name)}</span>
            </div>
            <button type="button" onclick="unblockSettingsUser('${escapeHtml(b.user_id)}')" class="text-xs font-bold text-brand-500 flex-shrink-0">Unblock</button>
          </div>`).join("") : `<p class="text-xs text-gray-400">No blocked accounts.</p>`}
      </div>
    </div>`;
}

let settingsBlockSearchTimer = null;
function debounceSettingsBlockSearch(query) {
  clearTimeout(settingsBlockSearchTimer);
  settingsBlockSearchTimer = setTimeout(() => runSettingsBlockSearch(query), 300);
}

async function runSettingsBlockSearch(query) {
  const results = document.getElementById("settings-block-search-results");
  if (!results) return;
  if (!query || query.trim().length < 2) {
    results.innerHTML = "";
    return;
  }
  try {
    const data = await api("search_users", { query: query.trim() });
    if (data.status !== "success") return;
    const users = data.results || [];
    results.innerHTML = users.length
      ? users.map((u) => `
        <div class="flex items-center justify-between p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
          <span class="text-sm text-gray-700 dark:text-gray-200 truncate">${escapeHtml(u.pet_name || "Member")}</span>
          <button onclick="blockSettingsUser('${escapeHtml(u.user_id)}', this)" class="text-xs font-bold text-red-500 flex-shrink-0">Block</button>
        </div>`).join("")
      : `<p class="text-xs text-gray-400 p-2">No pets found.</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function blockSettingsUser(userId, btn) {
  if (!confirm("Block this account? They won't be able to interact with you, and any friendship will be removed.")) return;
  try {
    const data = await api("block_user", { blocked_id: userId });
    if (data.status !== "success") {
      showToast(data.message || "Could not block user.", "error");
      return;
    }
    showToast("User blocked.", "success");
    if (btn) btn.closest("div").remove();
    loadAccountSettings();
  } catch (err) {
    console.error(err);
    showToast("Could not block user.", "error");
  }
}

async function unblockSettingsUser(userId) {
  try {
    const data = await api("unblock_user", { blocked_id: userId });
    if (data.status !== "success") {
      showToast(data.message || "Could not unblock user.", "error");
      return;
    }
    document.querySelector(`[data-blocked-user-id="${userId}"]`)?.remove();
    settingsState.blockedUsers = settingsState.blockedUsers.filter((b) => String(b.user_id) !== String(userId));
    showToast("User unblocked.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not unblock user.", "error");
  }
}

// ---------------- Danger zone ----------------

function renderSettingsDangerHtml() {
  return `
    <div class="space-y-5 max-w-lg">
      <div class="rounded-2xl border border-amber-200 dark:border-amber-900/50 bg-amber-50 dark:bg-amber-900/10 p-5 space-y-3">
        <h3 class="font-bold text-amber-800 dark:text-amber-300">Deactivate account</h3>
        <p class="text-xs text-amber-700 dark:text-amber-400">Your profile is hidden and you're signed out everywhere. Log back in any time with your password to reactivate.</p>
        <input id="settings-deactivate-password" type="password" placeholder="Current password" class="w-full px-3 py-2 border border-amber-200 dark:border-amber-900/50 rounded-lg text-sm bg-white dark:bg-gray-900 dark:text-white">
        <button id="settings-deactivate-btn" type="button" onclick="confirmDeactivateAccount()" class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-amber-500 hover:bg-amber-600">Deactivate account</button>
      </div>

      <div class="rounded-2xl border border-red-200 dark:border-red-900/50 bg-red-50 dark:bg-red-900/10 p-5 space-y-3">
        <h3 class="font-bold text-red-700 dark:text-red-400">Permanently delete account</h3>
        <p class="text-xs text-red-600 dark:text-red-400">This cannot be undone. All your posts, galleries, friendships, and pack data are permanently removed.</p>
        <input id="settings-delete-password" type="password" placeholder="Current password" class="w-full px-3 py-2 border border-red-200 dark:border-red-900/50 rounded-lg text-sm bg-white dark:bg-gray-900 dark:text-white">
        <input id="settings-delete-confirm-text" type="text" placeholder='Type "DELETE" to confirm' class="w-full px-3 py-2 border border-red-200 dark:border-red-900/50 rounded-lg text-sm bg-white dark:bg-gray-900 dark:text-white">
        <button id="settings-delete-btn" type="button" onclick="confirmDeleteAccount()" class="px-4 py-2 rounded-xl text-sm font-bold text-white bg-red-600 hover:bg-red-700">Permanently delete my account</button>
      </div>
    </div>`;
}

async function confirmDeactivateAccount() {
  const password = document.getElementById("settings-deactivate-password").value;
  if (!password) {
    showToast("Enter your password to confirm.", "info");
    return;
  }
  if (!confirm("Deactivate your account now? You'll be signed out everywhere.")) return;
  const btn = document.getElementById("settings-deactivate-btn");
  setButtonLoading(btn, true, "Deactivating…");
  try {
    const data = await api("deactivate_account", { password });
    if (data.status !== "success") {
      showToast(data.message || "Could not deactivate account.", "error");
      return;
    }
    showToast("Account deactivated.", "success");
    logout();
  } catch (err) {
    console.error(err);
    showToast("Could not deactivate account.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function confirmDeleteAccount() {
  const password = document.getElementById("settings-delete-password").value;
  const confirmText = document.getElementById("settings-delete-confirm-text").value.trim();
  if (!password || confirmText !== "DELETE") {
    showToast('Enter your password and type "DELETE" to confirm.', "info");
    return;
  }
  if (!confirm("This permanently deletes your account and cannot be undone. Continue?")) return;
  const btn = document.getElementById("settings-delete-btn");
  setButtonLoading(btn, true, "Deleting…");
  try {
    const data = await api("delete_account_permanently", { password, confirm_text: confirmText });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete account.", "error");
      return;
    }
    showToast("Account deleted.", "success");
    logout();
  } catch (err) {
    console.error(err);
    showToast("Could not delete account.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}
