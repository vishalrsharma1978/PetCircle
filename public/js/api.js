// Thin fetch wrapper + CSRF header plumbing, matching community_proj's
// session-cookie + X-CSRF-Token pattern (see api/routes/session.php).

function getCookieValue(name) {
  const value = `; ${document.cookie}`;
  const parts = value.split(`; ${name}=`);
  if (parts.length === 2) return decodeURIComponent(parts.pop().split(";").shift());
  return "";
}

function secureJsonHeaders() {
  const headers = { "Content-Type": "application/json" };
  const csrf = getCookieValue("pawcircle_csrf_token");
  if (csrf) headers["X-CSRF-Token"] = csrf;
  return headers;
}

// For multipart/form-data uploads: no Content-Type here — the browser sets
// it (with the multipart boundary) automatically when the body is FormData.
function secureUploadHeaders() {
  const csrf = getCookieValue("pawcircle_csrf_token");
  return csrf ? { "X-CSRF-Token": csrf } : {};
}

// ---------------- Response cache + in-flight dedup (step 24) ----------------
// A plain in-memory cache — this app is a single-page shell (switchView/
// switchSocialTab only toggle visibility, never a real navigation), so a
// session-lifetime Map is enough to make "reopen something just viewed"
// instant. Deliberately NOT persisted to localStorage/sessionStorage: a hard
// reload already goes through the real session_me restore path, and letting
// stale data survive a genuine refresh would be a correctness regression.
//
// Only read-only, non-realtime actions get a TTL here. Anything polled
// (notifications, direct/group messages, presence, zoom_*) is intentionally
// left out — caching those would reintroduce the stale-overwrites-optimistic
// -UI race class already fixed in step 16. Mutating actions are never cached;
// see CACHE_INVALIDATES below for how they clear related cached reads.
const CACHE_TTL_MS = {
  get_profile: 25000,
  get_friends: 25000,
  get_friend_requests: 20000,
  get_groups: 25000,
  get_galleries: 25000,
  get_rescue_opportunities: 25000,
  get_events: 20000,
  get_posts: 15000,
  get_user_posts: 15000,
  get_post_by_id: 15000,
  get_community_hub: 20000,
  get_care_guides: 300000,
  get_app_config: 300000,
  get_ads: 120000,
};

// How stale a cached entry is still allowed to be for peekApiCache()'s
// "render instantly, revalidate after" use — deliberately longer than
// CACHE_TTL_MS (which governs api()'s own silent cache-read), since a
// slightly-stale instant render beats a skeleton, as long as it's not
// ancient (bounds staleness on a long-lived tab that's been open for hours).
const CACHE_PEEK_MAX_AGE_MS = 10 * 60 * 1000;

// Mutating action -> cached read action(s) it should invalidate. Coarse by
// design: clears every cached entry for that action name (not just the
// current payload) rather than trying to track precise per-id dependencies.
const CACHE_INVALIDATES = {
  update_profile: ["get_profile"],
  set_handle: ["get_profile"],
  change_pet_type_breed: ["get_profile"],
  upload_photo: ["get_profile"],
  create_post: ["get_posts", "get_user_posts", "get_community_hub"],
  delete_post: ["get_posts", "get_post_by_id", "get_user_posts", "get_community_hub"],
  archive_post: ["get_posts", "get_user_posts"],
  unarchive_post: ["get_posts", "get_user_posts"],
  toggle_like: ["get_posts", "get_post_by_id"],
  set_post_reaction: ["get_posts", "get_post_by_id"],
  submit_comment: ["get_post_by_id"],
  respond_friend_request: ["get_friends", "get_friend_requests"],
  remove_friend: ["get_friends"],
  block_user: ["get_friends"],
  unblock_user: ["get_friends"],
  create_group: ["get_groups", "get_community_hub"],
  join_group: ["get_groups"],
  leave_group: ["get_groups"],
  create_event: ["get_events", "get_community_hub"],
  update_event: ["get_events"],
  delete_event: ["get_events"],
  rsvp_event: ["get_events"],
  create_gallery: ["get_galleries", "get_community_hub"],
  update_gallery: ["get_galleries"],
  delete_gallery: ["get_galleries"],
  create_rescue_opportunity: ["get_rescue_opportunities"],
  update_rescue_opportunity: ["get_rescue_opportunities"],
  archive_rescue_opportunity: ["get_rescue_opportunities"],
};

const apiResponseCache = new Map();
const apiInFlight = new Map();

function stableCacheKey(action, payload) {
  const sortedKeys = Object.keys(payload || {}).sort();
  const sortedPayload = {};
  for (const k of sortedKeys) sortedPayload[k] = payload[k];
  return action + "|" + JSON.stringify(sortedPayload);
}

// Synchronous cache peek for "render instantly, revalidate after" call
// sites (step 24 Part B) — returns cloned cached data if present and not
// too stale, else null. Never throws, never hits the network.
function peekApiCache(action, payload = {}) {
  const entry = apiResponseCache.get(stableCacheKey(action, payload));
  if (!entry || Date.now() - entry.time > CACHE_PEEK_MAX_AGE_MS) return null;
  return JSON.parse(JSON.stringify(entry.data));
}

// Pre-seeds the cache with an already-fetched result (step 24 Part C's
// batch pre-fetch) so a subsequent api(action, payload) call from an
// unmodified widget loader resolves from memory instead of the network.
function seedApiCache(action, payload, data) {
  if (!(action in CACHE_TTL_MS)) return;
  apiResponseCache.set(stableCacheKey(action, payload), { data, time: Date.now() });
}

function invalidateApiCache(action) {
  const targets = CACHE_INVALIDATES[action];
  if (!targets) return;
  for (const key of apiResponseCache.keys()) {
    for (const target of targets) {
      if (key.startsWith(target + "|")) {
        apiResponseCache.delete(key);
        break;
      }
    }
  }
}

async function api(action, payload = {}, options = {}) {
  const cacheKey = stableCacheKey(action, payload);
  const isCacheable = action in CACHE_TTL_MS;

  if (isCacheable && !options.forceRefresh) {
    const entry = apiResponseCache.get(cacheKey);
    if (entry && Date.now() - entry.time < CACHE_TTL_MS[action]) {
      return JSON.parse(JSON.stringify(entry.data));
    }
    if (apiInFlight.has(cacheKey)) {
      return apiInFlight.get(cacheKey).then((data) => JSON.parse(JSON.stringify(data)));
    }
  }

  const fetchPromise = (async () => {
    const response = await fetch("api/index.php", {
      method: "POST",
      credentials: "include",
      headers: secureJsonHeaders(),
      body: JSON.stringify({ action, ...payload }),
    });

    const text = await response.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (err) {
      console.error("Backend did not return JSON. Raw response:", text);
      throw new Error("Backend error: invalid JSON response. Check the PHP server logs.");
    }

    if (isCacheable && data?.status === "success") {
      apiResponseCache.set(cacheKey, { data, time: Date.now() });
    }
    invalidateApiCache(action);
    return data;
  })();

  if (isCacheable) {
    apiInFlight.set(cacheKey, fetchPromise);
    fetchPromise.finally(() => apiInFlight.delete(cacheKey));
  }

  return fetchPromise;
}

// One POST carrying several sub-requests (step 24 Part C) — cuts N separate
// HTTP round trips down to 1 for call sites that fire several independent
// reads together (e.g. the dashboard's hub-widget fan-out). The backend
// (handleBatchRequest, api/routes/batch.php) re-checks each sub-action's own
// auth/visibility, same as if it had been called individually.
async function apiBatch(requests) {
  const response = await fetch("api/index.php", {
    method: "POST",
    credentials: "include",
    headers: secureJsonHeaders(),
    body: JSON.stringify({ action: "batch", requests }),
  });
  const text = await response.text();
  let data;
  try {
    data = JSON.parse(text);
  } catch (err) {
    console.error("Backend did not return JSON. Raw response:", text);
    throw new Error("Backend error: invalid JSON response. Check the PHP server logs.");
  }
  return data;
}

function escapeHtml(str) {
  const div = document.createElement("div");
  div.textContent = String(str ?? "");
  return div.innerHTML;
}

function showToast(message, type = "info") {
  const variants = {
    info: { bg: "bg-gray-900", icon: "info" },
    success: { bg: "bg-green-600", icon: "check-circle-2" },
    error: { bg: "bg-red-600", icon: "alert-circle" },
    warning: { bg: "bg-amber-500", icon: "alert-triangle" },
  };
  const variant = variants[type] || variants.info;
  const toast = document.createElement("div");
  toast.className =
    `fixed top-4 left-1/2 -translate-x-1/2 ${variant.bg} text-white px-6 py-3 rounded-xl shadow-2xl z-[100] transform -translate-y-full opacity-0 transition-all duration-300 flex items-center gap-2 max-w-[90vw]`;
  toast.innerHTML = `<i data-lucide="${variant.icon}" class="w-4 h-4 flex-shrink-0"></i> <span class="min-w-0 break-words">${escapeHtml(message)}</span>`;
  document.body.appendChild(toast);
  if (window.lucide) lucide.createIcons();

  setTimeout(() => toast.classList.remove("-translate-y-full", "opacity-0"), 100);
  setTimeout(() => {
    toast.classList.add("-translate-y-full", "opacity-0");
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}
