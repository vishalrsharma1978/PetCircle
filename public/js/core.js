// Shared UI plumbing (view switching, dark mode, error display, breed
// dropdowns, mascot interaction). Grows as later build steps land — kept
// deliberately small for the auth slice rather than pre-stubbing functions
// for features that don't exist yet.

const breedsByPetType = {
  Dog: ["Labrador Retriever", "Golden Retriever", "German Shepherd", "Poodle", "Bulldog", "Beagle", "Rottweiler", "Dachshund", "Corgi", "Husky", "Pug", "Shih Tzu", "Boxer", "Chihuahua", "Mixed Breed", "other"],
  Cat: ["Persian", "Maine Coon", "Siamese", "Ragdoll", "Bengal", "Sphynx", "British Shorthair", "Abyssinian", "Scottish Fold", "Mixed Breed", "other"],
  Bird: ["Parrot", "Canary", "Finch", "Cockatiel", "Lovebird", "Macaw", "Mixed Breed", "other"],
  Fish: ["Betta", "Goldfish", "Guppy", "Tetra", "Cichlid", "Koi", "other"],
  "Small Pet": ["Hamster", "Guinea Pig", "Mouse", "Rat", "Gerbil", "Chinchilla", "Ferret", "other"],
  Reptile: ["Bearded Dragon", "Gecko", "Snake", "Turtle", "Iguana", "other"],
  Other: ["other"],
};

function updateBreedOptions(typeSelectId, breedSelectId) {
  const type = document.getElementById(typeSelectId).value;
  const breedSelect = document.getElementById(breedSelectId);
  breedSelect.innerHTML = '<option value="">Select Breed...</option>';
  if (type && breedsByPetType[type]) {
    breedsByPetType[type].forEach((b) => {
      const opt = document.createElement("option");
      opt.value = b;
      opt.textContent = b === "other" ? "Other..." : b;
      breedSelect.appendChild(opt);
    });
  }
  toggleCustomBreedInput();
}

function toggleCustomBreedInput() {
  const breedSelect = document.getElementById("reg-breed");
  const customWrap = document.getElementById("reg-custom-breed-wrap");
  if (breedSelect && customWrap) {
    customWrap.style.display = breedSelect.value === "other" ? "block" : "none";
  }
}

function clearErrors() {
  document.querySelectorAll('[id$="-error"]').forEach((el) => {
    el.classList.add("hidden");
    el.textContent = "";
  });
}

function showFieldError(errorId, message) {
  const el = document.getElementById(errorId);
  if (!el) return;
  el.textContent = message;
  el.classList.remove("hidden");
}

// Pushes an accent colour into the document-level CSS variables the brand
// palette is derived from (tailwind.config.js's `brand.*` colors all read
// `var(--brand-N, <fallback hex>)`). Mirrors eSamaj's applyAccentColor()
// (core.js:1601) — same color-mix() ramp, driven by pet_type instead of
// religion/community.
function applyAccentColor(accent) {
  const root = document.documentElement;
  root.style.setProperty("--brand-50", `color-mix(in srgb, ${accent} 10%, white)`);
  root.style.setProperty("--brand-100", `color-mix(in srgb, ${accent} 20%, white)`);
  root.style.setProperty("--brand-200", `color-mix(in srgb, ${accent} 40%, white)`);
  root.style.setProperty("--brand-300", `color-mix(in srgb, ${accent} 60%, white)`);
  root.style.setProperty("--brand-400", `color-mix(in srgb, ${accent} 80%, white)`);
  root.style.setProperty("--brand-500", accent);
  root.style.setProperty("--brand-900", `color-mix(in srgb, ${accent} 20%, black)`);
}

// Applies the logged-in user's pet_type accent color, if an admin has set
// one via the Pet Types admin panel. Safe to call repeatedly; does nothing
// (keeps the built-in default) when no theme is configured for this pet_type.
async function applyPetTypeTheme() {
  const petType = currentUserObj?.pet_type;
  if (!petType) return;
  try {
    const data = await api("get_app_config", {});
    if (data.status !== "success") return;
    const theme = (data.pet_type_themes || {})[petType];
    if (theme?.accent_color) applyAccentColor(theme.accent_color);
  } catch (err) {
    console.warn("Could not load pet type theme:", err);
  }
}

function switchView(viewId) {
  document.querySelectorAll(".view-section").forEach((el) => el.classList.remove("active"));
  const target = document.getElementById(viewId);
  if (target) target.classList.add("active");
  clearErrors();
}

function toggleDarkMode() {
  const root = document.documentElement;
  const isDark = root.classList.toggle("dark");
  try {
    localStorage.setItem("pawcircle_dark_mode", isDark ? "1" : "0");
  } catch (e) {
    // ignore
  }
}

function applyStoredDarkModePreference() {
  try {
    if (localStorage.getItem("pawcircle_dark_mode") === "1") {
      document.documentElement.classList.add("dark");
    }
  } catch (e) {
    // ignore
  }
}

// Pet-type selector on the login page's left pane recolors --login-accent as
// a lightweight live preview of the per-pet_type theming mechanism that
// admins configure properly in the admin dashboard (build step 11).
const PET_TYPE_PREVIEW_ACCENTS = {
  "": "#f97316",
  Dog: "#f97316",
  Cat: "#8b5cf6",
  Bird: "#0ea5e9",
  Rabbit: "#ec4899",
  Fish: "#06b6d4",
  Reptile: "#22c55e",
  "Small Pet": "#eab308",
  Other: "#e04848",
};

function handleLoginPetTypeChange(petType) {
  const accent = PET_TYPE_PREVIEW_ACCENTS[petType] || PET_TYPE_PREVIEW_ACCENTS[""];
  const leftPane = document.getElementById("lp-left-pane");
  const rightPane = document.getElementById("lp-right-pane");
  [leftPane, rightPane, document.getElementById("view-public-login")].forEach((el) => {
    if (el) el.style.setProperty("--login-accent", accent);
  });
}

// Password-field mascot: pupils track the mouse, paws cover the eyes while a
// password is being typed. See mascot.md for the reference implementation
// this is adapted from (element IDs here match views/signup.php's markup).
function initPasswordMascot(pwInputId, eyeToggleId, mascotId) {
  const pwInput = document.getElementById(pwInputId);
  const eyeToggle = document.getElementById(eyeToggleId);
  const mascot = document.getElementById(mascotId);
  if (!pwInput || !eyeToggle || !mascot) return;

  const pawL = mascot.querySelector(".paw-l");
  const pawR = mascot.querySelector(".paw-r");
  const lids = mascot.querySelector(".lids");
  if (!pawL || !pawR) return;

  document.addEventListener("mousemove", (e) => {
    if (pwInput.type === "text") return;
    const rect = mascot.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    const maxMove = 3;
    const moveX = Math.max(-maxMove, Math.min(maxMove, (e.clientX - centerX) / 50));
    const moveY = Math.max(-maxMove, Math.min(maxMove, (e.clientY - centerY) / 50));
    mascot.querySelectorAll(".pupil").forEach((p) => {
      p.style.transform = `translate(${moveX}px, ${moveY}px)`;
    });
  });

  function updateCover() {
    if (pwInput.type === "password" && pwInput.value.length > 0) {
      pawL.style.transform = "translate(2px, -57px) rotate(15deg)";
      pawR.style.transform = "translate(-2px, -57px) rotate(-15deg)";
      if (lids) lids.setAttribute("height", "11");
    } else {
      pawL.style.transform = "";
      pawR.style.transform = "";
      if (lids) lids.setAttribute("height", document.activeElement === pwInput && pwInput.type === "password" ? "28" : "11");
    }
  }

  pwInput.addEventListener("input", updateCover);
  pwInput.addEventListener("focus", updateCover);
  pwInput.addEventListener("blur", () => {
    pawL.style.transform = "";
    pawR.style.transform = "";
    if (lids) lids.setAttribute("height", "11");
  });

  eyeToggle.addEventListener("click", () => {
    pwInput.type = pwInput.type === "text" ? "password" : "text";
    const icon = eyeToggle.querySelector("svg[id$='Icon'], i");
    if (icon && icon.tagName === "I") {
      icon.setAttribute("data-lucide", pwInput.type === "text" ? "eye-off" : "eye");
      if (window.lucide) lucide.createIcons();
    }
    updateCover();
  });
}

// ---------------- Social feed shell (tabs, notifications) ----------------

const SOCIAL_TAB_LOADERS = {
  hub: () => loadCommunityHubTab(),
  feed: () => loadFeedPosts(),
  friends: () => loadFriendsTab(),
  groups: () => loadGroupsTab(),
  events: () => loadEventsTab(),
  galleries: () => loadGalleriesTab(),
  rescue: () => loadRescueTab(),
  guides: () => loadGuidesTab(),
  settings: () => loadAccountSettings(),
};

function switchSocialTab(tab) {
  document.querySelectorAll(".social-tab-panel").forEach((el) => el.classList.add("hidden"));
  document.getElementById(`social-tab-${tab}`)?.classList.remove("hidden");
  document.querySelectorAll("[data-social-tab]").forEach((btn) => {
    btn.classList.toggle("active-tab", btn.dataset.socialTab === tab);
  });
  const loader = SOCIAL_TAB_LOADERS[tab];
  if (typeof loader === "function") loader();
}

// ---------------- Skeleton loaders ----------------
// Matches eSamaj's own pattern: plain Tailwind `animate-pulse` on gray blocks
// shaped like the eventual content, no shimmer/gradient sweep, no separate
// component. Rendered immediately (before the API call fires) so a tab never
// shows an empty/"nothing here" state while a request is still in flight.

function postCardSkeletonHtml() {
  return `
  <div class="warm-glass rounded-2xl p-4 animate-pulse">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-full bg-gray-200 dark:bg-gray-800 flex-shrink-0"></div>
      <div class="flex-1 space-y-2">
        <div class="h-4 w-36 rounded bg-gray-200 dark:bg-gray-800"></div>
        <div class="h-3 w-24 rounded bg-gray-100 dark:bg-gray-800"></div>
      </div>
    </div>
    <div class="mt-3 space-y-2">
      <div class="h-4 w-full rounded bg-gray-100 dark:bg-gray-800"></div>
      <div class="h-4 w-5/6 rounded bg-gray-100 dark:bg-gray-800"></div>
    </div>
  </div>`;
}
function postCardSkeletonListHtml(count = 3) {
  return Array.from({ length: count }).map(postCardSkeletonHtml).join("");
}

function rowCardSkeletonHtml() {
  return `
  <div class="warm-glass rounded-2xl p-4 flex items-center gap-4 animate-pulse">
    <div class="w-11 h-11 rounded-full bg-gray-200 dark:bg-gray-800 flex-shrink-0"></div>
    <div class="flex-1 min-w-0 space-y-2">
      <div class="h-4 w-32 rounded-full bg-gray-200 dark:bg-gray-800"></div>
      <div class="h-3 w-20 rounded-full bg-gray-100 dark:bg-gray-800/70"></div>
    </div>
    <div class="h-8 w-16 rounded-lg bg-gray-100 dark:bg-gray-800 flex-shrink-0"></div>
  </div>`;
}
function rowCardSkeletonListHtml(count = 4) {
  return Array.from({ length: count }).map(rowCardSkeletonHtml).join("");
}

function tileSkeletonHtml() {
  return `<div class="aspect-square rounded-xl bg-gray-100 dark:bg-gray-800 animate-pulse"></div>`;
}
function tileSkeletonListHtml(count = 6) {
  return Array.from({ length: count }).map(tileSkeletonHtml).join("");
}

// ---------------- Profile dropdown menu ----------------
// Matches eSamaj's toggleProfileMenu()/closeProfileMenu() pattern: the
// avatar button toggles a dropdown instead of navigating directly, closes
// the notifications popover first (mutual exclusivity), and closes on
// click-outside or Escape.

function toggleProfileMenu(event) {
  if (event) event.stopPropagation();
  const menu = document.getElementById("profile-menu");
  const btn = document.getElementById("profile-menu-btn");
  if (!menu) return;
  const willOpen = menu.classList.contains("hidden");
  if (willOpen) {
    document.getElementById("notifications-panel")?.classList.add("hidden");
    document.getElementById("profile-menu-name").textContent = currentUserObj?.pet_name || "Pet";
    document.getElementById("profile-menu-email").textContent = currentUserObj?.email || "";
  }
  menu.classList.toggle("hidden");
  btn?.setAttribute("aria-expanded", willOpen ? "true" : "false");
  if (willOpen && window.lucide) lucide.createIcons();
}

function closeProfileMenu() {
  document.getElementById("profile-menu")?.classList.add("hidden");
  document.getElementById("profile-menu-btn")?.setAttribute("aria-expanded", "false");
}

function copyProfileLink() {
  navigator.clipboard.writeText(window.location.href);
  showToast("Profile link copied!", "success");
}

document.addEventListener("click", (e) => {
  const menu = document.getElementById("profile-menu");
  const btn = document.getElementById("profile-menu-btn");
  if (!menu || menu.classList.contains("hidden")) return;
  if (menu.contains(e.target) || btn?.contains(e.target)) return;
  closeProfileMenu();
});

document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") closeProfileMenu();
});

function timeAgo(value) {
  if (!value) return "";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "";
  const diffMinutes = Math.floor((Date.now() - date.getTime()) / 60000);
  if (diffMinutes < 1) return "just now";
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

let notificationsPollTimer = null;

async function loadNotifications() {
  try {
    const data = await api("get_notifications", {});
    if (data.status !== "success") return;
    renderNotifications(data.notifications || []);
  } catch (err) {
    console.warn("Could not load notifications:", err);
  }
  if (typeof checkForIncomingZoomCalls === "function") checkForIncomingZoomCalls();
}

// Matches eSamaj's own cadence: no websockets/realtime anywhere in the
// reference app either — just a 60s background poll plus a refresh whenever
// the tab regains focus, which is what actually makes badges/messages
// "arrive" without the user manually reopening anything.
function startNotificationPolling() {
  if (notificationsPollTimer) return;
  notificationsPollTimer = setInterval(loadNotifications, 60000);
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") loadNotifications();
  });
}

function renderNotifications(notifications) {
  const list = document.getElementById("notifications-list");
  const badge = document.getElementById("notif-badge");
  if (!list) return;

  const unreadCount = notifications.filter((n) => !n.is_read).length;
  if (badge) {
    if (unreadCount > 0) {
      badge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
      badge.classList.remove("hidden");
    } else {
      badge.classList.add("hidden");
    }
  }
  updateMessagesHeaderBadge(notifications);

  if (!notifications.length) {
    list.innerHTML = `<div class="p-4 text-sm text-gray-400 text-center">No notifications yet.</div>`;
    return;
  }

  list.innerHTML = notifications.map((n) => `
    <div class="p-3 border-b border-gray-50 dark:border-gray-800/50 text-sm ${n.is_read ? "" : "bg-brand-50/40 dark:bg-brand-950/20"}">
      <p class="font-bold text-gray-800 dark:text-gray-100">${escapeHtml(n.title || "Notification")}</p>
      <p class="text-gray-600 dark:text-gray-300 mt-0.5">${escapeHtml(n.body || "")}</p>
      <p class="text-xs text-gray-400 mt-1">${timeAgo(n.created_at)}</p>
    </div>
  `).join("");
}

// ---------------- Messages: header icon, no dedicated tab ----------------
// eSamaj doesn't have a "Messages" tab at all — the message-circle header
// icon just navigates into the Friends tab (chat is rendered in-place there,
// see friends.js). Matches eSamaj's openMessagesFromHeader() exactly.

function updateMessagesHeaderBadge(notifications) {
  const badge = document.getElementById("messages-header-badge");
  if (!badge) return;
  const unreadCount = notifications.filter((n) => !n.is_read && n.type === "direct_message").length;
  if (unreadCount > 0) {
    badge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
    badge.classList.remove("hidden");
  } else {
    badge.classList.add("hidden");
  }
}

function openMessagesFromHeader() {
  switchView("view-social-feed");
  switchSocialTab("friends");
  requestAnimationFrame(() => {
    document.getElementById("social-tab-friends")?.scrollIntoView({ behavior: "smooth", block: "start" });
  });
}

function toggleNotificationsPanel() {
  const panel = document.getElementById("notifications-panel");
  if (!panel) return;
  const willOpen = panel.classList.contains("hidden");
  panel.classList.toggle("hidden");
  if (willOpen) loadNotifications();
}

async function markAllNotificationsRead(btn) {
  if (btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    await api("mark_all_notifications_read", {});
    loadNotifications();
  } catch (err) {
    console.warn("Could not mark notifications read:", err);
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

document.addEventListener("click", (e) => {
  const panel = document.getElementById("notifications-panel");
  if (!panel || panel.classList.contains("hidden")) return;
  if (panel.contains(e.target) || document.getElementById("notif-bell-btn")?.contains(e.target)) return;
  panel.classList.add("hidden");
});

document.addEventListener("DOMContentLoaded", () => {
  applyStoredDarkModePreference();
  if (window.lucide) lucide.createIcons();

  initPasswordMascot("reg-password", "eyeToggle", "mascot");

  const publicPwToggle = document.getElementById("loginEyeToggle");
  const publicPwInput = document.getElementById("public-password");
  if (publicPwToggle && publicPwInput) {
    publicPwToggle.addEventListener("click", () => {
      publicPwInput.type = publicPwInput.type === "text" ? "password" : "text";
      const icon = document.getElementById("loginEyeIcon");
      if (icon) {
        icon.setAttribute("data-lucide", publicPwInput.type === "text" ? "eye-off" : "eye");
        if (window.lucide) lucide.createIcons();
      }
    });
  }

  // Body visibility is restored by auth.js once the session check (and
  // resulting view choice) completes, to avoid a flash of the wrong view.
});


// --- Global Search ---
let globalSearchTimeout = null;
let currentSearchTab = 'all';
let lastSearchData = { members: [], posts: [], events: [], connections: [] };

function getRecentSearches() {
  try {
    return JSON.parse(localStorage.getItem('esamaj_recent_searches') || '[]');
  } catch (e) {
    return [];
  }
}
function saveRecentSearch(q) {
  if (!q || q.trim().length === 0) return;
  let recents = getRecentSearches();
  recents = recents.filter(x => x.toLowerCase() !== q.toLowerCase());
  recents.unshift(q);
  recents = recents.slice(0, 5);
  localStorage.setItem('esamaj_recent_searches', JSON.stringify(recents));
}
function clearRecentSearches() {
  localStorage.removeItem('esamaj_recent_searches');
  showGlobalSearchDropdown(document.getElementById('global-search-input').value);
}

function showGlobalSearchDropdown(query) {
  const dropdown = document.getElementById('global-search-dropdown');
  if (!dropdown) return;

  let html = '';
  const qTrim = query.trim();

  if (qTrim) {
    html += `
                <div class="p-3 text-sm text-gray-500 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 rounded-t-xl transition-colors border-b border-gray-100 dark:border-gray-800/60" onclick="runAdvancedSearch('${escapeHtml(qTrim)}')">
                    <i data-lucide="search" class="inline-block w-4 h-4 mr-2 text-brand-500"></i>
                    Press enter to search for "<span class="font-semibold text-gray-700 dark:text-gray-300">${escapeHtml(qTrim)}</span>"
                </div>
            `;

    // Autocomplete local connections matching typed query
    const matches = (window.globalFriends || []).filter(f => (f.name || '').toLowerCase().includes(qTrim.toLowerCase())).slice(0, 3);
    if (matches.length > 0) {
      html += `
                    <div class="p-3 border-b border-gray-100 dark:border-gray-800/60">
                        <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Matching Connections</div>
                        <div class="space-y-1">
                            ${matches.map(m => `
                                <div onclick="openUserProfile('${escapeHtml((m.name || 'Member').replace(/'/g, "\\'"))}', '${escapeHtml((m.role || 'Member').replace(/'/g, "\\'"))}', '${String(m.id).replace(/'/g, "\\'")}', '${escapeHtml(m.photo || '')}')" class="flex items-center gap-3 px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                    <i data-lucide="user" class="w-4 h-4 text-brand-500"></i>
                                    <span>${escapeHtml(m.name)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
    } else {
        html += `
                      <div class="p-3 border-b border-gray-100 dark:border-gray-800/60">
                          <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Matching Connections</div>
                          <div class="text-sm text-gray-500 px-3 py-1.5 italic">No matching connections found.</div>
                      </div>
                  `;
      }
  } else {
    // Show recent searches if empty
    const recents = getRecentSearches();
    if (recents.length > 0) {
      html += `
                    <div class="p-3 border-b border-gray-100 dark:border-gray-800/60">
                        <div class="flex justify-between items-center mb-2">
                            <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider">Recent Searches</div>
                            <button onclick="clearRecentSearches()" class="text-[10px] font-bold text-red-500 hover:underline">Clear All</button>
                        </div>
                        <div class="space-y-1">
                            ${recents.map(r => `
                                <div onclick="document.getElementById('global-search-input').value = '${escapeHtml(r)}'; runAdvancedSearch('${escapeHtml(r)}');" class="flex items-center justify-between px-3 py-1.5 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                                    <span class="flex items-center gap-3">
                                        <i data-lucide="history" class="w-4 h-4 text-gray-400"></i>
                                        <span>${escapeHtml(r)}</span>
                                    </span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
    }
  }

  // Suggested searches panel
  html += `
            <div class="p-4">
                <div class="text-[10px] font-bold text-gray-400 dark:text-gray-500 uppercase tracking-wider mb-2">Suggested Searches</div>
                <div class="space-y-1">
                    <div onclick="const input = document.getElementById('global-search-input'); if (input) { input.value = 'Dog'; input.blur(); } runAdvancedSearch('Dog')" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                        <i data-lucide="bone" class="w-4 h-4 text-brand-500"></i>
                        <span>Find Dogs</span>
                    </div>
                    <div onclick="document.getElementById('global-search-input').value = 'Events'; runAdvancedSearch('', { showAllEvents: true })" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                        <i data-lucide="calendar-heart" class="w-4 h-4 text-brand-500"></i>
                        <span>Explore All Events</span>
                    </div>
                    <div data-feature-gate="matchmaking" onclick="switchView('view-social-feed'); switchSocialTab('playdates');" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                        <i data-lucide="heart-handshake" class="w-4 h-4 text-brand-500"></i>
                        <span>Playdates</span>
                    </div>
                    <div onclick="switchView('view-social-feed'); switchSocialTab('friends'); switchFriendsView('discover');" class="flex items-center gap-3 px-3 py-2 text-sm text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors">
                        <i data-lucide="user-plus" class="w-4 h-4 text-brand-500"></i>
                        <span>Discover People / Add Friends</span>
                    </div>
                </div>
            </div>
        `;

  if (!html) {
    html = `
        <div class="p-3 text-sm text-gray-500 italic text-center py-4">
            Type to start searching...
        </div>
      `;
  }

  dropdown.innerHTML = html;
  if (typeof lucide !== 'undefined') lucide.createIcons({ root: dropdown });
  dropdown.classList.remove('hidden');
}

function debouncedGlobalTypeahead(query) {
  clearTimeout(globalSearchTimeout);
  globalSearchTimeout = setTimeout(() => {
    showGlobalSearchDropdown(query);
  }, 150);
}

document.addEventListener('click', (e) => {
  const searchContainer = document.querySelector('#global-search-input')?.closest('.relative');
  if (searchContainer && !searchContainer.contains(e.target)) {
    document.getElementById('global-search-dropdown')?.classList.add('hidden');
  }
});

async function runAdvancedSearch(query, options = {}) {
  let q = (query || "").trim();
  const dropdown = document.getElementById('global-search-dropdown');
  if (dropdown) dropdown.classList.add('hidden');

  // Also set input value
  const input = document.getElementById('global-search-input');
  if (input) {
    input.value = q.trim();
    input.blur();
  }

  // Save query to recent searches
  if (q && !options.religion && !options.showAllEvents && !options.skipSaveRecent) {
    saveRecentSearch(q);
  }
  sessionStorage.setItem("esamaj_last_search_query", q);

  if (typeof switchView === "function") switchView("view-social-feed");
  if (typeof switchSocialTab === "function") switchSocialTab("search-results");

  let displayText = q ? `Results for "${q}"` : "Search Results";
  if (options.religion) {
    displayText = `Members: ${options.religion}`;
  } else if (options.showAllEvents) {
    displayText = "All Community Events";
  }
  const metaObj = document.getElementById('search-results-meta');
  if (metaObj) metaObj.innerText = q ? `Searching for "${q}"...` : "Searching...";

  ['all', 'pets', 'people', 'connections', 'posts', 'events'].forEach(tab => {
    const tabEl = document.getElementById(`search-content-${tab}`);
    if (tabEl) tabEl.innerHTML = '<div class="col-span-full py-12 text-center text-gray-500"><div class="inline-block animate-spin w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full mb-4"></div><p>Searching...</p></div>';
  });

  try {
    // Build API payloads
    const memberPayload = { user_id: currentUserObj?.id, limit: 24 };
    if (options.religion) {
      memberPayload.religion = options.religion;
    } else if (q) {
      memberPayload.query = q;
    }

    const postPayload = {
      user_id: currentUserObj?.id,
      religion: currentUserObj?.religion,
      community: currentUserObj?.community,
      limit: 10
    };
    if (q) postPayload.search_query = q;

    const eventPayload = {
      user_id: currentUserObj?.id,
      religion: currentUserObj?.religion,
      community: currentUserObj?.community,
      limit: 10
    };
    if (q) eventPayload.search_query = q;

    // Perform requests
    const [membersRes, postsRes, eventsRes] = await Promise.all([
      api("search_users", memberPayload),
      api("get_posts", postPayload),
      api("get_events", eventPayload)
    ]);

    // Connections filtering
    let connections = [];
    if (q) {
      const lcQuery = q.toLowerCase();
      connections = (window.globalFriends || []).filter(f =>
        (f.name || "").toLowerCase().includes(lcQuery) ||
        (f.current_city || "").toLowerCase().includes(lcQuery) ||
        (f.gotra || "").toLowerCase().includes(lcQuery)
      );
    } else {
      connections = window.globalFriends || [];
    }

    lastSearchData = {
      members: (membersRes?.results || []),
      posts: (postsRes?.posts || []).map(p => typeof normalizePostFromApi === 'function' ? normalizePostFromApi(p) : p),
      events: (eventsRes?.events || []).map((e) => ({
        id: e.id,
        db_id: e.id,
        user_id: e.created_by,
        creator: e.creator || "",
        date: e.event_date,
        title: e.event_time ? `${String(e.event_time).slice(0, 5)} - ${e.title}` : e.title,
        link: e.meeting_url,
        description: e.description || "",
        location: e.location || "",
        community: e.community || null,
        religion: e.religion || null
      })),
      connections: connections
    };

    const mObj = document.getElementById('search-results-meta');
    if (mObj) mObj.innerText = displayText;

    // Focus the appropriate tab
    if (options.religion) {
      switchSearchTab('people');
    } else if (options.showAllEvents) {
      switchSearchTab('events');
    } else {
      switchSearchTab('all');
    }
  } catch (err) {
    console.error("Search error:", err);
    const eObj = document.getElementById('search-results-meta');
    if (eObj) eObj.innerText = `Error searching. Please try again.`;
  }
}

function switchSearchTab(tab) {
  currentSearchTab = tab;
  ['all', 'pets', 'people', 'connections', 'posts', 'events'].forEach(t => {
    const btn = document.getElementById(`search-tab-${t}`);
    const content = document.getElementById(`search-content-${t}`);
    if (t === tab) {
      btn.classList.remove('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-600');
      btn.classList.add('border-brand-600', 'text-brand-600', 'dark:text-brand-400', 'dark:border-brand-400');
      content.classList.remove('hidden');
    } else {
      btn.classList.remove('border-brand-600', 'text-brand-600', 'dark:text-brand-400', 'dark:border-brand-400');
      btn.classList.add('border-transparent', 'text-gray-500', 'hover:text-gray-700', 'hover:border-gray-300', 'dark:text-gray-400', 'dark:hover:text-gray-300', 'dark:hover:border-gray-600');
      content.classList.add('hidden');
    }
  });
  renderSearchResults();
}

let searchFilters = { religion: '', gender: '', community: '', sortBy: 'name_asc' };

function toggleSearchFilters() {
  const panel = document.getElementById('search-filters-panel');
  if (panel) {
    panel.classList.toggle('hidden');
  }
}

function resetSearchFilters() {
  document.getElementById('search-filter-religion').value = '';
  document.getElementById('search-filter-gender').value = '';
  document.getElementById('search-filter-community').value = '';
  document.getElementById('search-sort-by').value = 'name_asc';
  applySearchFilters();
}

function applySearchFilters() {
  searchFilters.religion = document.getElementById('search-filter-religion').value;
  searchFilters.gender = document.getElementById('search-filter-gender').value;
  searchFilters.community = document.getElementById('search-filter-community').value.trim().toLowerCase();
  searchFilters.sortBy = document.getElementById('search-sort-by').value;
  renderSearchResults();
}

function renderSearchResults() {
  const { members, posts, events, connections } = lastSearchData;
  const renderEmpty = (msg) => `<div class="col-span-full py-12 text-center text-gray-500 bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm">${msg}</div>`;

  // Apply local filtering & sorting to members
  let filteredMembers = [...members];
  if (searchFilters.religion) {
    filteredMembers = filteredMembers.filter(m => (m.religion || '').toLowerCase() === searchFilters.religion.toLowerCase());
  }
  if (searchFilters.gender) {
    filteredMembers = filteredMembers.filter(m => (m.gender || '').toLowerCase() === searchFilters.gender.toLowerCase());
  }
  if (searchFilters.community) {
    filteredMembers = filteredMembers.filter(m => (m.community || '').toLowerCase().includes(searchFilters.community));
  }

  if (searchFilters.sortBy === 'name_asc') {
    filteredMembers.sort((a, b) => (a.name || a.full_name || '').localeCompare(b.name || b.full_name || ''));
  } else if (searchFilters.sortBy === 'name_desc') {
    filteredMembers.sort((a, b) => (b.name || b.full_name || '').localeCompare(a.name || a.full_name || ''));
  } else if (searchFilters.sortBy === 'age_asc') {
    filteredMembers.sort((a, b) => (a.age || 0) - (b.age || 0));
  } else if (searchFilters.sortBy === 'age_desc') {
    filteredMembers.sort((a, b) => (b.age || 0) - (a.age || 0));
  }

  const qLower = (document.getElementById('global-search-input')?.value || "").toLowerCase().trim();
  let petMatches = filteredMembers;
  let peopleMatches = filteredMembers;

  if (qLower) {
    petMatches = filteredMembers.filter(m => (m.pet_name || "").toLowerCase().includes(qLower));
    peopleMatches = filteredMembers.filter(m => (m.parent_name || m.name || m.full_name || "").toLowerCase().includes(qLower));
  }

  const renderPeopleSection = (list, limit = list.length) => {
    if (!list || list.length === 0) return renderEmpty("No people/pets found.");
    return list.slice(0, limit).map(m => {
      const id = m.id || m.user_id;
      const name = m.pet_name || m.name || m.full_name || "Pet";
      const parent_name = m.parent_name || m.name || m.full_name || "";
      const photo = m.photo || m.profile_photo_url || null;
      const initials = escapeHtml((name || "P")[0].toUpperCase());
      const safePhoto = photo ? escapeHtml(photo) : null;
      
      return `
      <div class="warm-glass warm-lift rounded-2xl p-4 flex items-center justify-between gap-2" data-search-user-id="${id}">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-11 h-11 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center flex-shrink-0 overflow-hidden">
            ${safePhoto ? `<img src="${safePhoto}" class="w-full h-full object-cover">` : `<span class="font-bold text-brand-700 dark:text-brand-300">${initials}</span>`}
          </div>
          <div class="min-w-0">
            <p class="text-sm font-bold text-gray-900 dark:text-white truncate">${escapeHtml(name)}</p>
            <p class="text-xs text-gray-400">${parent_name ? "with " + escapeHtml(parent_name) : "Member"}</p>
          </div>
        </div>
        <button onclick="sendFriendRequest('${id}', this)" class="text-xs font-bold text-white bg-brand-500 hover:bg-brand-600 px-3 py-1.5 rounded-lg flex-shrink-0">Add</button>
      </div>`;
    }).join("");
  };

  const renderPostsSection = (list, limit = list.length) => {
    if (!list || list.length === 0) return renderEmpty("No posts found.");
    return list.slice(0, limit).map(p => postCardHtml(p)).join("");
  };

  const renderEventsSection = (list, limit = list.length) => {
    if (!list || list.length === 0) return renderEmpty("No events found.");
    return list.slice(0, limit).map(e => eventCardHtml(e, false)).join("");
  };

  if (currentSearchTab === 'all') {
    const allContainer = document.getElementById('search-content-all');
    let html = "";
    if (petMatches.length > 0) html += `<div class="mb-8"><h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Pets</h3><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">${renderPeopleSection(petMatches, 3)}</div><button onclick="switchSearchTab('pets')" class="mt-4 text-brand-600 dark:text-brand-400 font-medium text-sm hover:underline">See all pet results &rarr;</button></div>`;
    if (peopleMatches.length > 0) html += `<div class="mb-8"><h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">People</h3><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">${renderPeopleSection(peopleMatches, 3)}</div><button onclick="switchSearchTab('people')" class="mt-4 text-brand-600 dark:text-brand-400 font-medium text-sm hover:underline">See all people results &rarr;</button></div>`;
    if (connections.length > 0) html += `<div class="mb-8"><h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Connections</h3><div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">${renderPeopleSection(connections, 3)}</div><button onclick="switchSearchTab('connections')" class="mt-4 text-brand-600 dark:text-brand-400 font-medium text-sm hover:underline">See all connection results &rarr;</button></div>`;
    if (posts.length > 0) html += `<div class="mb-8"><h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Posts</h3><div class="space-y-4 max-w-2xl mx-auto">${renderPostsSection(posts, 3)}</div><button onclick="switchSearchTab('posts')" class="mt-4 text-brand-600 dark:text-brand-400 font-medium text-sm hover:underline">See all post results &rarr;</button></div>`;
    if (events.length > 0) html += `<div class="mb-8"><h3 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Events</h3><div class="space-y-4">${renderEventsSection(events, 3)}</div><button onclick="switchSearchTab('events')" class="mt-4 text-brand-600 dark:text-brand-400 font-medium text-sm hover:underline">See all event results &rarr;</button></div>`;
    if (!html) html = renderEmpty("No results found for your query.");
    allContainer.innerHTML = html;
  } else if (currentSearchTab === 'pets') {
    document.getElementById('search-content-pets').innerHTML = renderPeopleSection(petMatches);
  } else if (currentSearchTab === 'people') {
    document.getElementById('search-content-people').innerHTML = renderPeopleSection(peopleMatches);
  } else if (currentSearchTab === 'connections') {
    document.getElementById('search-content-connections').innerHTML = renderPeopleSection(connections);
  } else if (currentSearchTab === 'posts') {
    document.getElementById('search-content-posts').innerHTML = renderPostsSection(posts);
  } else if (currentSearchTab === 'events') {
    document.getElementById('search-content-events').innerHTML = renderEventsSection(events);
  }
  if (typeof lucide !== 'undefined') lucide.createIcons();
}
