// Zoom voice/video calling: Meeting SDK for Web embed, adapted from eSamaj's
// real implementation (community_proj/public/js/zoom.js). Scope trimmed to
// what this app actually needs — no localStorage-cached "custom groups" call
// log (this app's groups/messages are always fetched live from the DB), no
// ebook-reader zoom or upload-video-compression helpers (unrelated features
// eSamaj happens to bundle in the same file).

let zoomSdkReady = false;

function prepareZoomSdk() {
  if (zoomSdkReady) return;
  if (!window.ZoomMtg) {
    throw new Error("Zoom Meeting SDK is not loaded. Check the vendor script tags.");
  }
  ZoomMtg.setZoomJSLib("https://source.zoom.us/6.1.0/lib", "/av");
  ZoomMtg.preLoadWasm();
  ZoomMtg.prepareWebSDK();
  zoomSdkReady = true;
}

function getZoomDisplayName() {
  const candidates = [currentUserObj?.pet_name, currentUserObj?.name, currentUserObj?.email, "PawCircle Member"];
  let name = candidates.find((v) => typeof v === "string" && v.trim().length > 0);
  name = String(name || "PawCircle Member").replace(/[^\p{L}\p{N}\s._@-]/gu, "").replace(/\s+/g, " ").trim();
  if (!name) name = "PawCircle Member";
  return name.slice(0, 128);
}

function getZoomEmail() {
  return typeof currentUserObj?.email === "string" ? currentUserObj.email.trim() : "";
}

let currentZoomJoinUrl = null;
let currentZoomCallId = null;
let currentZoomCallGroupId = null;
let currentZoomCallFriendId = null;
let zoomCallHasJoined = false;
let zoomLeaveInProgress = false;

function activateZoomCallShell(joinUrl = null) {
  currentZoomJoinUrl = joinUrl || currentZoomJoinUrl;
  zoomCallHasJoined = false;
  const root = document.getElementById("zmmtg-root");
  if (root) {
    root.style.display = "";
    root.removeAttribute("aria-hidden");
  }
  document.body.classList.remove("zoom-call-minimized", "zoom-call-compact", "zoom-call-fullscreen");
  document.body.classList.add("zoom-call-active", "zoom-call-large", "zoom-call-prejoin");
  updateZoomSizeToggleButton();
}

function markZoomCallJoined() {
  zoomCallHasJoined = true;
  document.body.classList.remove("zoom-call-prejoin");
  updateZoomSizeToggleButton();
}

function toggleZoomToolbarMenu() {
  document.getElementById("zoom-toolbar-menu")?.classList.toggle("hidden");
}

function hideZoomToolbarMenu() {
  document.getElementById("zoom-toolbar-menu")?.classList.add("hidden");
}

function nudgeZoomLayoutResize() {
  [0, 80, 220, 500].forEach((delay) => setTimeout(() => window.dispatchEvent(new Event("resize")), delay));
}

function setZoomCallView(mode) {
  if (mode === "compact" && !zoomCallHasJoined) {
    showToast("Compact mode is available after you join the call.", "info");
    return;
  }
  document.body.classList.remove("zoom-call-compact", "zoom-call-large", "zoom-call-fullscreen");
  if (mode === "compact") document.body.classList.add("zoom-call-compact");
  else if (mode === "fullscreen") document.body.classList.add("zoom-call-fullscreen");
  else document.body.classList.add("zoom-call-large");
  updateZoomSizeToggleButton();
  nudgeZoomLayoutResize();
}

function toggleZoomCallSize() {
  if (!zoomCallHasJoined) {
    showToast("Compact mode is available after you join the call.", "info");
    return;
  }
  setZoomCallView(document.body.classList.contains("zoom-call-compact") ? "large" : "compact");
}

function toggleZoomFullscreen() {
  setZoomCallView(document.body.classList.contains("zoom-call-fullscreen") ? "large" : "fullscreen");
}

function updateZoomSizeToggleButton() {
  const sizeBtn = document.getElementById("zoom-size-toggle-btn");
  const fullscreenBtn = document.getElementById("zoom-fullscreen-toggle-btn");
  if (sizeBtn) {
    if (!zoomCallHasJoined || document.body.classList.contains("zoom-call-prejoin")) {
      sizeBtn.textContent = "Compact";
      sizeBtn.disabled = true;
    } else {
      sizeBtn.disabled = false;
      sizeBtn.textContent = document.body.classList.contains("zoom-call-compact") ? "Large" : "Compact";
    }
  }
  if (fullscreenBtn) {
    fullscreenBtn.textContent = document.body.classList.contains("zoom-call-fullscreen") ? "Exit Fullscreen" : "Fullscreen";
  }
}

function popOutZoomCall() {
  if (!currentZoomJoinUrl) {
    showToast("No Zoom join link is available yet.", "info");
    return;
  }
  window.open(currentZoomJoinUrl, "_blank", "noopener,noreferrer");
}

function unlockZoomPageScroll() {
  const unlock = () => {
    ["overflow", "position", "height", "width", "top", "left"].forEach((p) => {
      document.documentElement.style.removeProperty(p);
      document.body.style.removeProperty(p);
    });
  };
  unlock();
  setTimeout(unlock, 100);
  setTimeout(unlock, 500);
}

function stopZoomMediaElements() {
  const root = document.getElementById("zmmtg-root");
  const nodes = [...document.querySelectorAll("video, audio"), ...(root ? Array.from(root.querySelectorAll("video, audio")) : [])];
  nodes.forEach((node) => {
    try {
      const stream = node.srcObject;
      if (stream && typeof stream.getTracks === "function") {
        stream.getTracks().forEach((t) => { try { t.stop(); } catch (e) { } });
      }
      node.pause?.();
      node.srcObject = null;
      node.removeAttribute("src");
      node.load?.();
    } catch (err) {
      console.warn("Could not stop Zoom media element:", err);
    }
  });
}

function clearZoomSdkDom() {
  const root = document.getElementById("zmmtg-root");
  if (!root) return;
  stopZoomMediaElements();
  root.style.setProperty("display", "none", "important");
  root.setAttribute("aria-hidden", "true");
  try {
    root.querySelectorAll("iframe, video, audio").forEach((node) => node.remove());
  } catch (err) {
    console.warn("Could not clear Zoom SDK DOM:", err);
  }
}

function minimizeZoomCallShell() {
  document.body.classList.remove("zoom-call-active", "zoom-call-compact", "zoom-call-large", "zoom-call-fullscreen", "zoom-call-prejoin");
  document.body.classList.add("zoom-call-minimized");
  const root = document.getElementById("zmmtg-root");
  if (root) root.style.display = "none";
  hideZoomToolbarMenu();
  unlockZoomPageScroll();
}

function restoreZoomCallShell() {
  const root = document.getElementById("zmmtg-root");
  if (root) root.style.display = "";
  document.body.classList.remove("zoom-call-minimized", "zoom-call-compact", "zoom-call-fullscreen", "zoom-call-prejoin");
  document.body.classList.add("zoom-call-active", "zoom-call-large");
  updateZoomSizeToggleButton();
  nudgeZoomLayoutResize();
}

function cleanupZoomShellState() {
  document.body.classList.remove("zoom-call-active", "zoom-call-minimized", "zoom-call-compact", "zoom-call-large", "zoom-call-fullscreen", "zoom-call-prejoin");
  hideZoomToolbarMenu();
  unlockZoomPageScroll();
  stopZoomMediaElements();
  setTimeout(stopZoomMediaElements, 500);
  const root = document.getElementById("zmmtg-root");
  if (root) root.style.display = "none";
  setTimeout(clearZoomSdkDom, 1200);
  currentZoomJoinUrl = null;
  zoomCallHasJoined = false;
}

function markCurrentZoomParticipantLeft() {
  if (!currentZoomCallId) return Promise.resolve(null);
  const callId = currentZoomCallId;
  const groupId = currentZoomCallGroupId;
  const friendId = currentZoomCallFriendId;
  currentZoomCallId = null;
  currentZoomCallGroupId = null;
  currentZoomCallFriendId = null;

  return api("zoom_mark_participant", { call_id: callId, status: "left" })
    .then((data) => {
      if (data?.status === "success" && groupId && String(currentGroupId) === String(groupId)) {
        refreshGroupMessages();
      } else if (data?.status === "success" && friendId && String(currentFriendChatId) === String(friendId)) {
        refreshFriendChatMessages();
      }
      return data;
    })
    .catch((err) => {
      console.warn("Could not mark Zoom participant left:", err);
      return null;
    });
}

function leaveZoomMeetingSdk() {
  return new Promise((resolve) => {
    if (!window.ZoomMtg || typeof ZoomMtg.leaveMeeting !== "function") {
      stopZoomMediaElements();
      resolve();
      return;
    }
    let finished = false;
    const finish = (result) => {
      if (finished) return;
      finished = true;
      resolve(result);
    };
    try {
      ZoomMtg.leaveMeeting({
        success: (result) => {
          try { if (typeof ZoomMtg.destroy === "function") ZoomMtg.destroy(); } catch (e) { }
          stopZoomMediaElements();
          finish(result);
        },
        error: (err) => {
          console.warn("Zoom leaveMeeting returned an error:", err);
          try { if (typeof ZoomMtg.destroy === "function") ZoomMtg.destroy(); } catch (e) { }
          stopZoomMediaElements();
          finish(err);
        },
      });
      setTimeout(() => finish(), 2500);
    } catch (err) {
      console.warn("Could not leave Zoom meeting cleanly:", err);
      finish(err);
    }
  });
}

async function leaveZoomCallShell() {
  if (zoomLeaveInProgress) return;
  zoomLeaveInProgress = true;
  try {
    await leaveZoomMeetingSdk();
    cleanupZoomShellState();
    await markCurrentZoomParticipantLeft();
  } catch (err) {
    console.warn("leaveZoomCallShell error", err);
  } finally {
    cleanupZoomShellState();
    zoomLeaveInProgress = false;
  }
}

async function joinZoomMeetingInPage(zoom, options = {}) {
  prepareZoomSdk();
  activateZoomCallShell(zoom.joinUrl || null);
  currentZoomCallId = options.callId || currentZoomCallId;
  currentZoomCallGroupId = options.groupId || null;
  currentZoomCallFriendId = options.friendId || null;

  const leaveUrl = `${window.location.origin}${window.location.pathname}`;
  const displayName = String(options.userName || getZoomDisplayName()).replace(/\s+/g, " ").trim().slice(0, 128);

  const joinConfig = {
    sdkKey: String(zoom.sdk_key || "").trim(),
    signature: String(zoom.signature || "").trim(),
    meetingNumber: String(zoom.meeting_number || "").replace(/\D/g, ""),
    passWord: String(zoom.password || ""),
    userName: displayName || "PawCircle Member",
    userEmail: String(options.userEmail || getZoomEmail() || "").trim(),
  };

  if (!joinConfig.meetingNumber) throw new Error("Missing Zoom meeting number.");
  if (!joinConfig.signature) throw new Error("Missing Zoom SDK signature.");

  return new Promise((resolve, reject) => {
    ZoomMtg.init({
      leaveUrl,
      patchJsMedia: true,
      success: () => {
        ZoomMtg.join({
          ...joinConfig,
          success: (joinSuccess) => {
            markZoomCallJoined();
            setZoomCallView("large");
            if (currentZoomCallGroupId && String(currentGroupId) === String(currentZoomCallGroupId)) refreshGroupMessages();
            if (currentZoomCallFriendId && String(currentFriendChatId) === String(currentZoomCallFriendId)) refreshFriendChatMessages();
            resolve(joinSuccess);
          },
          error: (joinError) => {
            console.error("[Zoom] Join error", joinError);
            showToast(joinError?.errorMessage || "Could not join the call.", "error");
            reject(joinError);
          },
        });
      },
      error: (initError) => {
        console.error("[Zoom] Init error", initError);
        reject(initError);
      },
    });
  });
}

async function startZoomCall({ callType, targetType, friendId = null, groupId = null }, btn = null) {
  if (btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  showToast("Starting call…", "info");
  try {
    const data = await api("zoom_start_call", {
      call_type: callType,
      target_type: targetType,
      friend_id: friendId || "",
      group_id: groupId || "",
    });
    if (data.status !== "success") throw new Error(data.message || "Could not start the call.");

    await joinZoomMeetingInPage(data.zoom, {
      userName: getZoomDisplayName(),
      userEmail: getZoomEmail(),
      callId: data.call?.id || null,
      groupId,
      friendId,
    });

    if (groupId && String(currentGroupId) === String(groupId)) refreshGroupMessages();
    if (friendId && String(currentFriendChatId) === String(friendId)) refreshFriendChatMessages();
  } catch (err) {
    console.error(err);
    showToast(err?.message || "Could not start the call.", "error");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function joinZoomCall(callId) {
  try {
    const data = await api("zoom_join_call", { call_id: callId });
    if (data.status !== "success") throw new Error(data.message || "Could not join the call.");

    await joinZoomMeetingInPage(data.zoom, {
      userName: getZoomDisplayName(),
      userEmail: getZoomEmail(),
      callId,
      groupId: currentGroupId || null,
      friendId: !currentGroupId ? currentFriendChatId : null,
    });
  } catch (err) {
    console.error(err);
    showToast(err?.message || "Could not join the call.", "error");
  }
}

function isCallLive(call) {
  if (!["ringing", "active"].includes(call.status)) return false;
  const startedAt = new Date(call.started_at || call.created_at).getTime();
  return Date.now() - startedAt < 2 * 60 * 60 * 1000;
}

function formatCallLogTime(value) {
  if (!value) return "Unknown time";
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return "Unknown time";
  return d.toLocaleString([], { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" });
}

// Inline "call card" rendered in-line with the message list, matching
// eSamaj's group-call-timeline-card visual pattern (adapted: no localStorage
// "creating"/"failed" states here, since calls are always fetched live).
function renderCallLogCardHtml(call, fallbackName = "A friend") {
  const live = isCallLive(call);
  const icon = call.call_type === "voice" ? "phone" : "video";
  const typeLabel = call.call_type === "voice" ? "Voice call" : "Video call";
  const creator = call.created_by === currentUserObj?.id ? "You" : escapeHtml(call.caller_name || call.created_by_name || fallbackName);
  const started = formatCallLogTime(call.started_at || call.created_at);
  const stateClass = live
    ? "border-emerald-200 bg-emerald-50 dark:border-emerald-900/60 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300"
    : "border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50 text-gray-600 dark:text-gray-300";
  const stateText = live ? "Live now" : "Ended";
  const actionHtml = live
    ? `<button onclick="joinZoomCall('${call.id}')" class="bg-brand-500 hover:bg-brand-600 text-white px-3 py-1.5 rounded-full text-xs font-bold shadow-sm flex-shrink-0">Join</button>`
    : `<span class="text-xs font-semibold text-gray-500 dark:text-gray-400 flex-shrink-0">Ended</span>`;

  return `
  <div class="my-3 flex justify-center">
    <div class="group-call-timeline-card rounded-2xl border ${stateClass} p-3 flex items-center justify-between gap-3 shadow-sm">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-9 h-9 rounded-full bg-white/70 dark:bg-black/20 flex items-center justify-center shrink-0"><i data-lucide="${icon}" class="w-4 h-4"></i></div>
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="font-bold text-sm">${typeLabel}</span>
            <span class="text-[10px] uppercase tracking-wide font-black px-2 py-0.5 rounded-full bg-white/70 dark:bg-black/20">${stateText}</span>
          </div>
          <p class="text-xs opacity-80 truncate">${creator} started this call · ${started}</p>
        </div>
      </div>
      ${actionHtml}
    </div>
  </div>`;
}

// ---------------- Incoming-call detection (polled alongside notifications) ----------------

const seenIncomingCallIds = new Set();
let activeIncomingCallId = null;

async function checkForIncomingZoomCalls() {
  try {
    const data = await api("zoom_get_active_calls", {});
    if (data.status !== "success") return;
    const calls = data.calls || [];
    const ringing = calls.find((c) => c.status === "ringing" && c.created_by !== currentUserObj?.id && !seenIncomingCallIds.has(c.id));
    if (ringing) showIncomingCallToast(ringing);
  } catch (err) {
    console.warn("Could not check for incoming calls:", err);
  }
}

function showIncomingCallToast(call) {
  const toast = document.getElementById("incoming-call-toast");
  if (!toast) return;
  activeIncomingCallId = call.id;
  const icon = call.call_type === "voice" ? "phone" : "video";
  const typeLabel = call.call_type === "voice" ? "Voice call" : "Video call";
  toast.innerHTML = `
    <div class="w-9 h-9 rounded-full bg-white/15 flex items-center justify-center flex-shrink-0"><i data-lucide="${icon}" class="w-4 h-4"></i></div>
    <div class="min-w-0 flex-1">
      <p class="text-sm font-bold">${escapeHtml(call.caller_name || "Someone")}</p>
      <p class="text-xs opacity-75">${typeLabel} · incoming</p>
    </div>
    <button class="incoming-call-decline" onclick="declineIncomingZoomCall('${call.id}')">Decline</button>
    <button class="incoming-call-accept" onclick="acceptIncomingZoomCall('${call.id}')">Accept</button>`;
  toast.classList.remove("hidden");
  toast.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function hideIncomingCallToast() {
  const toast = document.getElementById("incoming-call-toast");
  if (toast) {
    toast.classList.add("hidden");
    toast.classList.remove("flex");
  }
  activeIncomingCallId = null;
}

async function acceptIncomingZoomCall(callId) {
  seenIncomingCallIds.add(callId);
  hideIncomingCallToast();
  await joinZoomCall(callId);
}

async function declineIncomingZoomCall(callId) {
  seenIncomingCallIds.add(callId);
  hideIncomingCallToast();
  try {
    await api("zoom_mark_participant", { call_id: callId, status: "declined" });
  } catch (err) {
    console.warn("Could not decline call:", err);
  }
}
