// Shared client-side app state. Kept intentionally small for the auth slice;
// grows as the feed/profile system (build step 3-4) is added.

let currentUserObj = null;

const PAWCIRCLE_SESSION_STORAGE_KEY = "pawcircle_current_user";

function persistCurrentSession(user) {
  currentUserObj = user || null;
  try {
    if (user) {
      sessionStorage.setItem(PAWCIRCLE_SESSION_STORAGE_KEY, JSON.stringify(user));
    } else {
      sessionStorage.removeItem(PAWCIRCLE_SESSION_STORAGE_KEY);
    }
  } catch (e) {
    // sessionStorage unavailable (private browsing etc.) — in-memory state still works.
  }
}

function restorePersistedSession() {
  try {
    const raw = sessionStorage.getItem(PAWCIRCLE_SESSION_STORAGE_KEY);
    if (raw) {
      currentUserObj = JSON.parse(raw);
      return currentUserObj;
    }
  } catch (e) {
    // ignore
  }
  return null;
}
