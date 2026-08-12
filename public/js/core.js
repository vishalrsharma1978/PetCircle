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
