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

async function api(action, payload = {}, options = {}) {
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
