// Friends: sub-tabs (My Friends / Discover), search, requests, list.

let friendSearchDebounceTimer = null;

function switchFriendsSubtab(subtab) {
  document.getElementById("friends-subtab-mine")?.classList.toggle("hidden", subtab !== "mine");
  document.getElementById("friends-subtab-discover")?.classList.toggle("hidden", subtab !== "discover");
  document.querySelectorAll("[data-friends-subtab]").forEach((btn) => {
    btn.classList.toggle("active-subtab", btn.dataset.friendsSubtab === subtab);
  });
}

function debounceFriendSearch(query) {
  clearTimeout(friendSearchDebounceTimer);
  friendSearchDebounceTimer = setTimeout(() => runFriendSearch(query), 300);
}

function discoverCardHtml(u) {
  return `
  <div class="warm-glass warm-lift rounded-2xl p-4 flex items-center justify-between gap-2" data-search-user-id="${u.user_id}">
    <div class="flex items-center gap-3 min-w-0">
      <div class="w-11 h-11 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center flex-shrink-0 overflow-hidden">
        ${u.profile_photo_url ? `<img src="${escapeHtml(u.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="font-bold text-brand-700 dark:text-brand-300">${escapeHtml((u.pet_name || "P")[0])}</span>`}
      </div>
      <div class="min-w-0">
        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">${escapeHtml(u.pet_name || "Member")}</p>
        <p class="text-xs text-gray-400">${[u.pet_type, u.breed].filter(Boolean).map(escapeHtml).join(" · ")}</p>
      </div>
    </div>
    <button onclick="sendFriendRequest('${u.user_id}', this)" class="text-xs font-bold text-white bg-brand-500 hover:bg-brand-600 px-3 py-1.5 rounded-lg flex-shrink-0">Add</button>
  </div>`;
}

async function runFriendSearch(query) {
  const results = document.getElementById("friend-search-results");
  if (!results) return;
  if (!query || query.trim().length < 2) {
    results.innerHTML = `<p class="text-sm text-gray-400 col-span-full py-4">Search above to find pets to add.</p>`;
    return;
  }
  results.innerHTML = rowCardSkeletonListHtml(2);
  try {
    const data = await api("search_users", { query: query.trim() });
    if (data.status !== "success") return;
    const users = data.results || [];
    results.innerHTML = users.length
      ? users.map(discoverCardHtml).join("")
      : `<p class="text-sm text-gray-400 col-span-full py-4">No pets found.</p>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
  }
}

async function sendFriendRequest(friendId, btn) {
  if (btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.textContent = "…";
  }
  try {
    const data = await api("send_friend_request", { friend_id: friendId });
    if (data.status !== "success") {
      showToast(data.message || "Could not send friend request.", "error");
      if (btn) {
        btn.disabled = false;
        btn.textContent = "Add";
      }
      return;
    }
    if (btn) {
      btn.textContent = "Requested";
      btn.classList.remove("bg-brand-500", "hover:bg-brand-600", "text-white");
      btn.classList.add("bg-gray-100", "dark:bg-gray-800", "text-gray-400");
    }
    showToast("Friend request sent.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not send friend request.", "error");
    if (btn) {
      btn.disabled = false;
      btn.textContent = "Add";
    }
  }
}

async function unfriendUser(userId, name, btn) {
  if (!(await confirmAction({ title: `Remove ${name} from your friends?`, confirmLabel: "Remove" }))) return;
  setButtonLoading(btn, true);
  try {
    const data = await api("remove_friend", { friend_id: userId });
    if (data.status !== "success") {
      showToast(data.message || "Could not remove friend.", "error");
      setButtonLoading(btn, false);
      return;
    }
    showToast(`Removed ${name}.`, "success");
    if (String(currentFriendChatId) === String(userId)) closeFriendChat();
    loadFriendsList();
  } catch (err) {
    console.error(err);
    showToast("Could not remove friend.", "error");
    setButtonLoading(btn, false);
  }
}

async function blockFriendUser(userId, name) {
  if (!confirm(`Block ${name}? They won't be able to interact with you, and your friendship will be removed.`)) return;
  try {
    const data = await api("block_user", { blocked_id: userId });
    if (data.status !== "success") {
      showToast(data.message || "Could not block user.", "error");
      return;
    }
    showToast(`Blocked ${name}.`, "success");
    if (String(currentFriendChatId) === String(userId)) closeFriendChat();
    loadFriendsList();
  } catch (err) {
    console.error(err);
    showToast("Could not block user.", "error");
  }
}

// Shared "..." dropdown for a friend — friends-list cards and the chat
// header both embed this directly next to a toggleDropdownMenu() button,
// matching the same pattern posts.js already uses for its post menus.
function friendActionsMenuHtml(userId, name, menuId) {
  const safeName = escapeHtml(name).replace(/'/g, "\\'");
  return `
    <div id="${menuId}" class="dropdown-menu hidden absolute right-0 mt-1 w-44 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-30 py-1">
      <button onclick="event.stopPropagation(); openMemberProfile('${userId}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i data-lucide="user" class="w-3.5 h-3.5"></i> View profile</button>
      <button onclick="event.stopPropagation(); unfriendUser('${userId}', '${safeName}', null)" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i data-lucide="user-minus" class="w-3.5 h-3.5"></i> Remove friend</button>
      <button onclick="event.stopPropagation(); blockFriendUser('${userId}', '${safeName}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 flex items-center gap-2"><i data-lucide="user-x" class="w-3.5 h-3.5"></i> Block</button>
    </div>`;
}

function friendRequestCardHtml(r) {
  return `
  <div class="flex items-center justify-between p-3 warm-glass rounded-xl" data-friendship-id="${r.friendship_id}">
    <div class="flex items-center gap-2 min-w-0">
      <div class="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center flex-shrink-0 overflow-hidden ring-2 ring-transparent hover:ring-brand-400 transition-all">
        ${r.profile_photo_url ? `<img src="${escapeHtml(r.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="text-xs font-bold text-brand-700 dark:text-brand-300">${escapeHtml((r.name || "P")[0])}</span>`}
      </div>
      <div class="min-w-0">
        <p class="text-sm font-bold text-gray-900 dark:text-white truncate">${escapeHtml(r.name)}</p>
        <p class="text-xs text-gray-400">${[r.pet_type, r.breed].filter(Boolean).map(escapeHtml).join(" · ")}</p>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <button onclick="respondFriendRequest('${r.friendship_id}', 'accept', this)" class="text-xs font-bold text-white bg-brand-500 hover:bg-brand-600 px-3 py-1.5 rounded-lg">Accept</button>
      <button onclick="respondFriendRequest('${r.friendship_id}', 'decline', this)" class="text-xs font-bold text-gray-500 bg-gray-100 dark:bg-gray-800 px-3 py-1.5 rounded-lg">Decline</button>
    </div>
  </div>`;
}

async function respondFriendRequest(friendshipId, action, btn) {
  const row = document.querySelector(`[data-friendship-id="${friendshipId}"]`);
  row?.querySelectorAll("button").forEach((b) => {
    b.disabled = true;
    b.classList.add("opacity-50", "pointer-events-none");
  });
  try {
    const data = await api("respond_friend_request", { friendship_id: friendshipId, response_action: action });
    if (data.status !== "success") {
      showToast(data.message || "Could not update friend request.", "error");
      row?.querySelectorAll("button").forEach((b) => {
        b.disabled = false;
        b.classList.remove("opacity-50", "pointer-events-none");
      });
      return;
    }
    row?.remove();
    if (action === "accept") {
      showToast("Friend request accepted.", "success");
      loadFriendsList();
    }
  } catch (err) {
    console.error(err);
    showToast("Could not update friend request.", "error");
    row?.querySelectorAll("button").forEach((b) => {
      b.disabled = false;
      b.classList.remove("opacity-50", "pointer-events-none");
    });
  }
}

function renderFriendRequests(list, requests) {
  list.innerHTML = requests.length ? requests.map(friendRequestCardHtml).join("") : `<p class="text-xs text-gray-400">No pending requests.</p>`;
  if (window.lucide) lucide.createIcons();
}

async function loadFriendRequests() {
  const list = document.getElementById("friend-requests-list");
  if (!list) return;

  const cached = peekApiCache("get_friend_requests", {});
  if (cached?.status === "success") {
    renderFriendRequests(list, cached.requests || []);
  } else {
    list.innerHTML = rowCardSkeletonListHtml(1);
  }

  try {
    const data = await api("get_friend_requests", {}, { forceRefresh: !!cached });
    if (data.status !== "success") return;
    renderFriendRequests(list, data.requests || []);
  } catch (err) {
    console.error(err);
  }
}

// Per-friend unread DM badge — mirrors eSamaj's derive-from-notifications
// approach (direct_messages has no is_read column of its own).
let friendUnreadCounts = {};

async function loadFriendUnreadCounts() {
  try {
    const data = await api("get_notifications", {});
    if (data.status !== "success") return;
    const counts = {};
    (data.notifications || []).forEach((n) => {
      const senderId = n.data?.sender_id;
      if (n.type === "direct_message" && !n.is_read && senderId) {
        counts[senderId] = (counts[senderId] || 0) + 1;
      }
    });
    friendUnreadCounts = counts;
  } catch (err) {
    console.error(err);
  }
}

function friendCardUnreadBadgeHtml(friendId) {
  const count = friendUnreadCounts[friendId] || 0;
  if (count <= 0) return "";
  return `<span class="notif-pulse absolute -top-1 -right-1 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-brand-500 text-white text-[10px] font-bold leading-[1.1rem] text-center ring-2 ring-white dark:ring-gray-900">${count > 99 ? "99+" : count}</span>`;
}

function friendCardHtml(f) {
  return `
        <div class="flex items-center justify-between p-3 warm-glass rounded-xl">
          <div class="flex items-center gap-2 min-w-0 cursor-pointer" onclick="openMemberProfile('${f.user_id}')">
            <div class="relative w-9 h-9 flex-shrink-0">
              <div class="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden ring-2 ring-transparent hover:ring-brand-400 transition-all">
                ${f.profile_photo_url ? `<img src="${escapeHtml(f.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="text-xs font-bold text-brand-700 dark:text-brand-300">${escapeHtml((f.name || "P")[0])}</span>`}
              </div>
              ${friendCardUnreadBadgeHtml(f.user_id)}
              ${presenceDotHtml(f.presence, "absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full border-2 border-white dark:border-gray-900")}
            </div>
            <div class="min-w-0">
              <p class="text-sm font-bold text-gray-900 dark:text-white truncate hover:underline">${escapeHtml(f.name)}</p>
              <p class="text-xs text-gray-400">${[f.pet_type, f.breed].filter(Boolean).map(escapeHtml).join(" · ")}</p>
            </div>
          </div>
          <div class="flex items-center gap-1 flex-shrink-0">
            <button onclick="openFriendChat('${f.user_id}', '${escapeHtml(f.name)}', ${f.profile_photo_url ? `'${escapeHtml(f.profile_photo_url)}'` : "null"}, '${f.presence || ""}')" class="text-xs font-bold text-brand-500 flex items-center gap-1"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Message</button>
            <div class="relative flex-shrink-0">
              <button onclick="toggleDropdownMenu(event, 'friend-menu-${f.user_id}')" title="More" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="more-vertical" class="w-3.5 h-3.5"></i></button>
              ${friendActionsMenuHtml(f.user_id, f.name, `friend-menu-${f.user_id}`)}
            </div>
          </div>
        </div>`;
}

function renderFriendsList(list, friends) {
  list.innerHTML = friends.length
    ? friends.map(friendCardHtml).join("")
    : `<p class="text-xs text-gray-400">No friends yet — search above to find pets to connect with.</p>`;
  if (window.lucide) lucide.createIcons();
}

async function loadFriendsList() {
  const list = document.getElementById("friends-list");
  if (!list) return;

  const cached = peekApiCache("get_friends", {});
  if (cached?.status === "success") {
    renderFriendsList(list, cached.friends || []);
  } else {
    list.innerHTML = rowCardSkeletonListHtml(2);
  }

  try {
    await loadFriendUnreadCounts();
    const data = await api("get_friends", {}, { forceRefresh: !!cached });
    if (data.status !== "success") return;
    renderFriendsList(list, data.friends || []);
  } catch (err) {
    console.error(err);
  }
}

function loadFriendsTab() {
  switchFriendsSubtab("mine");
  loadFriendRequests();
  loadFriendsList();
}

// ---------------- In-place friend chat (no dedicated Messages tab) ----------------
// Matches eSamaj: opening a chat replaces the Friends tab's normal content
// with a chat shell in the same container; closing it restores the list.
// Replies and reactions match eSamaj's actual DM feature set, adapted from
// eSamaj's mobile-only swipe/long-press gestures to hover-revealed buttons
// since this is a desktop web app.

let currentFriendChatId = null;
let currentFriendChatName = "";
let friendChatPollTimer = null;
let currentFriendChatMessages = [];
let currentFriendChatCalls = [];
let friendChatReplyTo = null; // { id, label, text }
let friendChatOpenReactionPickerId = null;

// Optimistic outbox: sends render instantly as a pending pseudo-message here,
// kept OUTSIDE currentFriendChatMessages so the 3s poll's full-array replace
// (refreshFriendChatMessages) never wipes an unconfirmed or failed send.
let friendChatOutbox = [];

// Reactions in flight: the 3s poll's full-array replace (refreshFriendChatMessages)
// would otherwise clobber an optimistically-applied reaction before the
// react_direct_message call resolves — reapply from here after every poll fetch.
let friendChatPendingReactions = new Map();

const CHAT_REACTION_EMOJIS = ["❤️", "👍", "😂", "😮", "😢", "😡"];

// Sent-message status: clock (pending) / alert-circle+retry (failed) /
// check (sent, not yet seen) / check-check (seen — real read_at from the
// server, not a fabricated signal). Only rendered on the sender's own bubbles.
function friendChatMessageStatusHtml(m) {
  if (m._status === "pending") {
    return `<i data-lucide="clock" class="w-3 h-3 opacity-70"></i>`;
  }
  if (m._status === "failed") {
    return `
      <button onclick="retryFriendChatOutboxMessage('${m.clientId}')" class="text-red-100 hover:text-white" title="Failed to send — tap to retry"><i data-lucide="alert-circle" class="w-3 h-3"></i></button>
      <button onclick="discardFriendChatOutboxMessage('${m.clientId}')" class="text-red-100 hover:text-white" title="Discard"><i data-lucide="x" class="w-3 h-3"></i></button>`;
  }
  return m.read_at
    ? `<i data-lucide="check-check" class="w-3 h-3"></i>`
    : `<i data-lucide="check" class="w-3 h-3 opacity-70"></i>`;
}

function friendChatBubbleHtml(m) {
  const isMine = currentUserObj && m.sender_id === currentUserObj.id;
  const isOutbox = !!m._status;
  const reactions = m.reactions && typeof m.reactions === "object" ? m.reactions : {};
  const distinctEmojis = [...new Set(Object.values(reactions))];

  const replyStripHtml = m.reply_to_text
    ? `<div class="rounded-lg p-2 mb-2 flex items-center gap-1.5 text-xs italic ${isMine ? "bg-white/15 text-white/80" : "bg-black/5 dark:bg-white/10 text-gray-500 dark:text-gray-400"}">
        <i data-lucide="reply" class="w-3 h-3 flex-shrink-0"></i>
        <span class="truncate">${escapeHtml(m.reply_to_text)}</span>
      </div>`
    : "";

  const reactionPillHtml = distinctEmojis.length
    ? `<div class="absolute -bottom-2.5 ${isMine ? "right-2" : "left-2"} bg-white dark:bg-gray-800 rounded-full px-1.5 py-0.5 text-xs shadow-sm border border-gray-100 dark:border-gray-700">${distinctEmojis.join(" ")}</div>`
    : "";

  const statusRowHtml = isMine
    ? `<div class="flex items-center justify-end gap-1 mt-1 ${m._status === "failed" ? "" : "text-white/70"}">${friendChatMessageStatusHtml(m)}</div>`
    : "";

  const hoverActionsHtml = isOutbox ? "" : `
      <div class="opacity-0 group-hover/msg:opacity-100 transition-opacity flex items-center gap-0.5 flex-shrink-0">
        <button onclick="toggleFriendChatReactionPicker('${m.id}')" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" title="React"><i data-lucide="smile" class="w-3.5 h-3.5"></i></button>
        <button onclick="startFriendChatReply('${m.id}')" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" title="Reply"><i data-lucide="reply" class="w-3.5 h-3.5"></i></button>
      </div>`;

  const reactionPickerHtml = isOutbox ? "" : `
    <div id="reaction-picker-${m.id}" class="hidden absolute ${isMine ? "right-0" : "left-0"} -top-10 bg-white dark:bg-gray-800 rounded-full shadow-lg border border-gray-100 dark:border-gray-700 px-2 py-1.5 flex items-center gap-1 z-10">
      ${CHAT_REACTION_EMOJIS.map((e) => `<button onclick="reactToFriendChatMessage('${m.id}', '${e}')" class="text-base hover:scale-125 transition-transform">${e}</button>`).join("")}
    </div>`;

  return `
  <div class="group/msg relative flex ${isMine ? "justify-end" : "justify-start"} ${distinctEmojis.length ? "mb-3" : ""}">
    <div class="flex items-center gap-0.5 max-w-[85%] ${isMine ? "flex-row-reverse" : "flex-row"}">
      <div class="relative max-w-full px-4 py-2.5 rounded-2xl text-sm shadow-sm ${m._status === "pending" ? "opacity-70" : ""} ${isMine ? "bg-brand-500 text-white rounded-br-none" : "bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-100 border border-gray-100 dark:border-gray-700 rounded-bl-none"}">
        ${replyStripHtml}
        ${m.content ? escapeHtml(m.content) : ""}
        ${m.media_url ? `<img src="${escapeHtml(m.media_url)}" class="mt-2 rounded-lg max-h-56 object-cover">` : ""}
        <div id="preview-container-friend-chat-${m.id}" class="mt-2"></div>
        ${statusRowHtml}
        ${reactionPillHtml}
      </div>
      ${hoverActionsHtml}
    </div>
    ${reactionPickerHtml}
  </div>`;
}

function toggleFriendChatReactionPicker(messageId) {
  const wasOpen = friendChatOpenReactionPickerId === messageId;
  document.querySelectorAll('[id^="reaction-picker-"]').forEach((el) => el.classList.add("hidden"));
  friendChatOpenReactionPickerId = wasOpen ? null : messageId;
  if (!wasOpen) document.getElementById(`reaction-picker-${messageId}`)?.classList.remove("hidden");
}

// Optimistic: apply the reaction locally and re-render immediately, then
// reconcile with (or roll back to) the server's authoritative response —
// matches the same pattern as the message outbox below.
async function reactToFriendChatMessage(messageId, emoji) {
  friendChatOpenReactionPickerId = null;
  const msg = currentFriendChatMessages.find((m) => String(m.id) === String(messageId));
  if (!msg) return;
  const previousReactions = { ...(msg.reactions || {}) };
  msg.reactions = { ...previousReactions, [currentUserObj.id]: emoji };
  friendChatPendingReactions.set(String(messageId), msg.reactions);
  renderFriendChatTimeline();
  try {
    const data = await api("react_direct_message", { message_id: messageId, reaction: emoji });
    if (data.status !== "success") {
      msg.reactions = previousReactions;
      renderFriendChatTimeline();
      showToast(data.message || "Could not react.", "error");
      return;
    }
    msg.reactions = data.reactions || previousReactions;
    renderFriendChatTimeline();
  } catch (err) {
    console.error(err);
    msg.reactions = previousReactions;
    renderFriendChatTimeline();
    showToast("Could not react.", "error");
  } finally {
    friendChatPendingReactions.delete(String(messageId));
  }
}

function startFriendChatReply(messageId) {
  const msg = currentFriendChatMessages.find((m) => String(m.id) === String(messageId));
  if (!msg) return;
  const isMine = currentUserObj && msg.sender_id === currentUserObj.id;
  friendChatReplyTo = {
    id: msg.id,
    label: isMine ? "yourself" : (document.getElementById("friend-chat-name")?.textContent || "them"),
    text: msg.content || (msg.media_url ? "Photo" : "Message"),
  };
  renderFriendChatReplyStrip();
  document.getElementById("friend-chat-input")?.focus();
}

function cancelFriendChatReply() {
  friendChatReplyTo = null;
  renderFriendChatReplyStrip();
}

function renderFriendChatReplyStrip() {
  const strip = document.getElementById("friend-chat-reply-strip");
  if (!strip) return;
  if (!friendChatReplyTo) {
    strip.classList.add("hidden");
    strip.innerHTML = "";
    return;
  }
  strip.classList.remove("hidden");
  strip.innerHTML = `
    <div class="flex items-center justify-between gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700">
      <div class="flex items-center gap-2 min-w-0">
        <i data-lucide="reply" class="w-3.5 h-3.5 text-gray-400 flex-shrink-0"></i>
        <div class="min-w-0">
          <p class="text-xs font-bold text-gray-700 dark:text-gray-200">Replying to ${escapeHtml(friendChatReplyTo.label)}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs italic">${escapeHtml(friendChatReplyTo.text)}</p>
        </div>
      </div>
      <button onclick="cancelFriendChatReply()" class="p-1 rounded-full text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 flex-shrink-0"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>`;
  if (window.lucide) lucide.createIcons();
}

async function openFriendChat(friendId, name, photoUrl, presence) {
  currentFriendChatId = friendId;
  currentFriendChatName = name || "Friend";
  friendChatReplyTo = null;
  friendChatOpenReactionPickerId = null;
  friendChatOutbox = [];
  document.getElementById("friends-normal-view")?.classList.add("hidden");
  const shell = document.getElementById("friend-chat-shell");
  shell.classList.remove("hidden");
  shell.classList.add("flex");
  document.getElementById("friend-chat-name").textContent = name || "Friend";
  const menuWrap = document.getElementById("friend-chat-menu-wrap");
  if (menuWrap) menuWrap.innerHTML = friendActionsMenuHtml(friendId, currentFriendChatName, "friend-chat-menu");
  setAvatarPreview("friend-chat-avatar-img", "friend-chat-avatar-text", photoUrl, (name || "P")[0]);
  const presenceDot = document.getElementById("friend-chat-presence-dot");
  if (presenceDot) {
    presenceDot.classList.remove("hidden", "presence-online", "presence-away", "presence-offline");
    if (presence && presence !== "hidden") {
      presenceDot.classList.add(`presence-${presence}`);
      const subtitle = document.getElementById("friend-chat-subtitle");
      if (subtitle) subtitle.textContent = PRESENCE_LABELS[presence] || "Direct message";
    } else {
      presenceDot.classList.add("hidden");
      const subtitle = document.getElementById("friend-chat-subtitle");
      if (subtitle) subtitle.textContent = "Direct message";
    }
  }
  document.getElementById("friend-chat-messages").innerHTML = rowCardSkeletonListHtml(2);
  renderFriendChatReplyStrip();
  await refreshFriendChatMessages();
  startFriendChatPolling();
  if (window.lucide) lucide.createIcons();
}

function closeFriendChat() {
  currentFriendChatId = null;
  currentFriendChatName = "";
  friendChatReplyTo = null;
  friendChatOpenReactionPickerId = null;
  friendChatOutbox = [];
  stopFriendChatPolling();
  const shell = document.getElementById("friend-chat-shell");
  shell.classList.add("hidden");
  shell.classList.remove("flex");
  document.getElementById("friends-normal-view")?.classList.remove("hidden");
  loadFriendsTab();
}

// Pending/failed outbox entries rendered as pseudo-messages, merged with the
// server-confirmed messages + call-log cards. Kept as its own render pass so
// both the 3s poll and an instant post-send call can trigger it.
function friendChatOutboxAsMessages() {
  return friendChatOutbox.map((o) => ({
    id: null,
    clientId: o.clientId,
    sender_id: currentUserObj?.id,
    content: o.content,
    media_url: null,
    reply_to_id: o.reply_to_id || null,
    reply_to_text: o.reply_to_text || null,
    reactions: {},
    created_at: o.created_at,
    read_at: null,
    _status: o.status,
  }));
}

function renderFriendChatTimeline() {
  const container = document.getElementById("friend-chat-messages");
  if (!container) return;
  const timeline = [
    ...currentFriendChatMessages.map((m) => ({ at: m.created_at, html: friendChatBubbleHtml(m) })),
    ...friendChatOutboxAsMessages().map((m) => ({ at: m.created_at, html: friendChatBubbleHtml(m) })),
    ...currentFriendChatCalls.map((c) => ({ at: c.started_at || c.created_at, html: renderCallLogCardHtml(c, document.getElementById("friend-chat-name")?.textContent || "A friend") })),
  ].sort((a, b) => new Date(a.at) - new Date(b.at));

  container.innerHTML = timeline.map((t) => t.html).join("") || `<p class="text-center text-sm text-gray-400 py-8">No messages yet — say hello!</p>`;
  container.scrollTop = container.scrollHeight;
  if (window.lucide) lucide.createIcons();
  
  if (typeof detectAndRenderLinkPreview === 'function') {
    [...currentFriendChatMessages, ...friendChatOutboxAsMessages()].forEach(m => {
      detectAndRenderLinkPreview(m.id, m.content, 'friend-chat-');
    });
  }
}

async function refreshFriendChatMessages() {
  if (!currentFriendChatId) return;
  if (friendChatOpenReactionPickerId) return; // don't wipe an open picker mid-interaction
  try {
    const [msgData, callData] = await Promise.all([
      api("get_direct_messages", { friend_id: currentFriendChatId }),
      api("zoom_get_direct_calls", { friend_id: currentFriendChatId, limit: 20 }).catch(() => null),
    ]);
    if (msgData.status !== "success") return;
    currentFriendChatMessages = msgData.messages || [];
    if (friendChatPendingReactions.size) {
      currentFriendChatMessages.forEach((m) => {
        if (friendChatPendingReactions.has(String(m.id))) {
          m.reactions = friendChatPendingReactions.get(String(m.id));
        }
      });
    }
    currentFriendChatCalls = callData?.status === "success" ? (callData.calls || []) : [];
    renderFriendChatTimeline();
    if (currentFriendChatMessages.length) loadNotifications();
  } catch (err) {
    console.error(err);
  }
}

function startFriendChatPolling() {
  stopFriendChatPolling();
  friendChatPollTimer = setInterval(refreshFriendChatMessages, 3000);
}

function stopFriendChatPolling() {
  clearInterval(friendChatPollTimer);
  friendChatPollTimer = null;
}

function sendFriendChatMessage() {
  if (!currentFriendChatId) return;
  const input = document.getElementById("friend-chat-input");
  const content = input.value.trim();
  if (!content) return;
  input.value = "";
  const replyTo = friendChatReplyTo;
  friendChatReplyTo = null;
  renderFriendChatReplyStrip();

  const clientId = `out_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
  friendChatOutbox.push({
    clientId,
    content,
    reply_to_id: replyTo?.id || "",
    reply_to_text: replyTo?.text || null,
    created_at: new Date().toISOString(),
    status: "pending",
  });
  renderFriendChatTimeline();
  sendFriendChatOutboxEntry(clientId);
}

async function sendFriendChatOutboxEntry(clientId) {
  const entry = friendChatOutbox.find((o) => o.clientId === clientId);
  if (!entry) return;
  entry.status = "pending";
  renderFriendChatTimeline();
  try {
    const data = await api("send_direct_message", {
      recipient_id: currentFriendChatId,
      content: entry.content,
      reply_to_id: entry.reply_to_id || "",
    });
    if (data.status !== "success") {
      entry.status = "failed";
      renderFriendChatTimeline();
      showToast(data.message || "Could not send message.", "error");
      return;
    }
    friendChatOutbox = friendChatOutbox.filter((o) => o.clientId !== clientId);
    currentFriendChatMessages.push({ ...data.message_row, reply_to_text: entry.reply_to_text });
    renderFriendChatTimeline();
  } catch (err) {
    console.error(err);
    entry.status = "failed";
    renderFriendChatTimeline();
    showToast("Could not send message.", "error");
  }
}

function retryFriendChatOutboxMessage(clientId) {
  const entry = friendChatOutbox.find((o) => o.clientId === clientId);
  if (!entry || entry.status !== "failed") return;
  sendFriendChatOutboxEntry(clientId);
}

function discardFriendChatOutboxMessage(clientId) {
  friendChatOutbox = friendChatOutbox.filter((o) => o.clientId !== clientId);
  renderFriendChatTimeline();
}

document.addEventListener("click", (e) => {
  if (!friendChatOpenReactionPickerId) return;
  if (e.target.closest('[id^="reaction-picker-"]') || e.target.closest('[onclick^="toggleFriendChatReactionPicker"]')) return;
  document.querySelectorAll('[id^="reaction-picker-"]').forEach((el) => el.classList.add("hidden"));
  friendChatOpenReactionPickerId = null;
});
