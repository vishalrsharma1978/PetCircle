// Groups: create/join/leave, listing, and group chat modal.

let currentGroupId = null;
let allGroupsCache = [];

function openCreateGroupModal() {
  const modal = document.getElementById("create-group-modal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeCreateGroupModal() {
  const modal = document.getElementById("create-group-modal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

function filterGroupsBySearch(query) {
  const q = query.trim().toLowerCase();
  const list = document.getElementById("groups-list");
  if (!list) return;
  const filtered = q ? allGroupsCache.filter((g) => (g.name || "").toLowerCase().includes(q)) : allGroupsCache;
  list.innerHTML = filtered.length ? filtered.map(groupCardHtml).join("") : `<p class="text-sm text-gray-400 col-span-2">No groups match "${escapeHtml(query)}".</p>`;
  if (window.lucide) lucide.createIcons();
}

async function submitCreateGroup() {
  const name = document.getElementById("new-group-name").value.trim();
  if (!name) {
    showToast("Give the group a name.", "info");
    return;
  }
  const scope = document.getElementById("new-group-scope").value;
  const breed = document.getElementById("new-group-breed").value.trim();

  const btn = document.getElementById("group-modal-submit-btn");
  setButtonLoading(btn, true, "Creating…");
  try {
    const data = await api("create_group", {
      name,
      description: document.getElementById("new-group-description").value.trim(),
      scope,
      pet_type: currentUserObj?.pet_type || "",
      breed: scope === "breed" ? breed : "",
    });
    if (data.status !== "success") {
      showToast(data.message || "Could not create group.", "error");
      return;
    }
    document.getElementById("new-group-name").value = "";
    document.getElementById("new-group-description").value = "";
    closeCreateGroupModal();
    showToast("Group created.", "success");
    loadGroupsTab();
  } catch (err) {
    console.error(err);
    showToast("Could not create group.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

function groupCardHtml(g) {
  const actionBtn = g.is_member
    ? `<button onclick="openGroupChat('${g.id}', '${escapeHtml(g.name)}')" class="text-xs font-bold text-white bg-brand-500 hover:bg-brand-600 px-3 py-1.5 rounded-lg">Open chat</button>`
    : `<button onclick="joinGroup('${g.id}', this)" class="text-xs font-bold text-brand-500 border border-brand-200 dark:border-brand-800 px-3 py-1.5 rounded-lg">Join</button>`;

  return `
  <div class="warm-glass warm-lift rounded-2xl p-4">
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <p class="font-bold text-gray-900 dark:text-white truncate">${escapeHtml(g.name)}</p>
        <p class="text-xs text-gray-400">${g.member_count} member${g.member_count === 1 ? "" : "s"} · ${escapeHtml(g.scope === "breed" ? g.breed || "breed" : g.scope === "pet_type" ? g.pet_type || "pet type" : "open to all")}</p>
      </div>
      ${actionBtn}
    </div>
    ${g.description ? `<p class="text-sm text-gray-600 dark:text-gray-300 mt-2">${escapeHtml(g.description)}</p>` : ""}
  </div>`;
}

async function loadGroupsTab() {
  const list = document.getElementById("groups-list");
  if (!list) return;
  const searchInput = document.getElementById("groups-search-input");
  if (searchInput) searchInput.value = "";
  list.innerHTML = rowCardSkeletonListHtml(4);
  try {
    const data = await api("get_groups", { pet_type: currentUserObj?.pet_type || "" });
    if (data.status !== "success") return;
    allGroupsCache = data.groups || [];
    list.innerHTML = allGroupsCache.length ? allGroupsCache.map(groupCardHtml).join("") : `<p class="text-sm text-gray-400 col-span-2">No groups yet — tap + to start one.</p>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
  }
}

async function joinGroup(groupId, btn) {
  if (btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("join_group", { group_id: groupId });
    if (data.status !== "success") {
      showToast(data.message || "Could not join group.", "error");
      return;
    }
    loadGroupsTab();
  } catch (err) {
    console.error(err);
    showToast("Could not join group.", "error");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

// Reply/react match the friend-chat-shell treatment exactly (friends.js) —
// same hover-revealed buttons, same fixed 6-emoji set, same reply-strip
// composer pattern, adapted here for the group chat modal.

let currentGroupChatMessages = [];
let currentGroupChatCalls = [];
let groupChatReplyTo = null; // { id, label, text }
let groupChatOpenReactionPickerId = null;
let groupChatPollTimer = null;

// Optimistic outbox: sends render instantly as a pending pseudo-message here,
// kept OUTSIDE currentGroupChatMessages so the 3s poll's full-array replace
// (refreshGroupMessages) never wipes an unconfirmed or failed send.
let groupChatOutbox = [];

// Reactions in flight: the 3s poll's full-array replace (refreshGroupMessages)
// would otherwise clobber an optimistically-applied reaction before the
// react_group_message call resolves — reapply from here after every poll fetch.
let groupChatPendingReactions = new Map();

// Group messages have no per-message read tracking (group_messages has no
// read_at column — per-member read receipts are out of scope), so this is
// simpler than the DM version: clock (pending) / alert-circle+retry (failed)
// / check (sent). No seen/check-check state.
function groupChatMessageStatusHtml(m) {
  if (m._status === "pending") {
    return `<i data-lucide="clock" class="w-3 h-3 opacity-70"></i>`;
  }
  if (m._status === "failed") {
    return `
      <button onclick="retryGroupChatOutboxMessage('${m.clientId}')" class="text-red-100 hover:text-white" title="Failed to send — tap to retry"><i data-lucide="alert-circle" class="w-3 h-3"></i></button>
      <button onclick="discardGroupChatOutboxMessage('${m.clientId}')" class="text-red-100 hover:text-white" title="Discard"><i data-lucide="x" class="w-3 h-3"></i></button>`;
  }
  return `<i data-lucide="check" class="w-3 h-3 opacity-70"></i>`;
}

function groupMessageHtml(m) {
  const isMine = currentUserObj && m.sender_id === currentUserObj.id;
  const isOutbox = !!m._status;
  const reactions = m.reactions && typeof m.reactions === "object" ? m.reactions : {};
  const distinctEmojis = [...new Set(Object.values(reactions))];

  const replyStripHtml = m.reply_to_text
    ? `<div class="rounded-lg p-2 mb-1 flex items-center gap-1.5 text-xs italic ${isMine ? "bg-white/15 text-white/80" : "bg-black/5 dark:bg-white/10 text-gray-500 dark:text-gray-400"}">
        <i data-lucide="reply" class="w-3 h-3 flex-shrink-0"></i>
        <span class="truncate">${escapeHtml(m.reply_to_text)}</span>
      </div>`
    : "";

  const reactionPillHtml = distinctEmojis.length
    ? `<div class="absolute -bottom-2.5 ${isMine ? "right-2" : "left-2"} bg-white dark:bg-gray-800 rounded-full px-1.5 py-0.5 text-xs shadow-sm border border-gray-100 dark:border-gray-700">${distinctEmojis.join(" ")}</div>`
    : "";

  const statusRowHtml = isMine
    ? `<div class="flex items-center justify-end gap-1 mt-1 ${m._status === "failed" ? "" : "text-white/70"}">${groupChatMessageStatusHtml(m)}</div>`
    : "";

  const hoverActionsHtml = isOutbox ? "" : `
      <div class="opacity-0 group-hover/msg:opacity-100 transition-opacity flex items-center gap-0.5 flex-shrink-0">
        <button onclick="toggleGroupChatReactionPicker('${m.id}')" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" title="React"><i data-lucide="smile" class="w-3.5 h-3.5"></i></button>
        <button onclick="startGroupChatReply('${m.id}')" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" title="Reply"><i data-lucide="reply" class="w-3.5 h-3.5"></i></button>
      </div>`;

  const reactionPickerHtml = isOutbox ? "" : `
    <div id="group-reaction-picker-${m.id}" class="hidden absolute ${isMine ? "right-0" : "left-0"} -top-10 bg-white dark:bg-gray-800 rounded-full shadow-lg border border-gray-100 dark:border-gray-700 px-2 py-1.5 flex items-center gap-1 z-10">
      ${CHAT_REACTION_EMOJIS.map((e) => `<button onclick="reactToGroupChatMessage('${m.id}', '${e}')" class="text-base hover:scale-125 transition-transform">${e}</button>`).join("")}
    </div>`;

  return `
  <div class="group/msg relative flex ${isMine ? "justify-end" : "justify-start"} ${distinctEmojis.length ? "mb-3" : ""}">
    <div class="flex items-center gap-0.5 max-w-[85%] ${isMine ? "flex-row-reverse" : "flex-row"}">
      <div class="relative max-w-full ${m._status === "pending" ? "opacity-70" : ""} ${isMine ? "bg-brand-500 text-white" : "bg-gray-100 dark:bg-gray-800 text-gray-800 dark:text-gray-100"} rounded-2xl px-3 py-2">
        ${replyStripHtml}
        ${!isMine ? `<p class="text-xs font-bold opacity-70">${escapeHtml(m.sender_name)}</p>` : ""}
        <p class="text-sm">${escapeHtml(m.content || "")}</p>
        ${statusRowHtml}
        ${reactionPillHtml}
      </div>
      ${hoverActionsHtml}
    </div>
    ${reactionPickerHtml}
  </div>`;
}

function toggleGroupChatReactionPicker(messageId) {
  const wasOpen = groupChatOpenReactionPickerId === messageId;
  document.querySelectorAll('[id^="group-reaction-picker-"]').forEach((el) => el.classList.add("hidden"));
  groupChatOpenReactionPickerId = wasOpen ? null : messageId;
  if (!wasOpen) document.getElementById(`group-reaction-picker-${messageId}`)?.classList.remove("hidden");
}

// Optimistic: apply the reaction locally and re-render immediately, then
// reconcile with (or roll back to) the server's authoritative response.
async function reactToGroupChatMessage(messageId, emoji) {
  groupChatOpenReactionPickerId = null;
  const msg = currentGroupChatMessages.find((m) => String(m.id) === String(messageId));
  if (!msg) return;
  const previousReactions = { ...(msg.reactions || {}) };
  msg.reactions = { ...previousReactions, [currentUserObj.id]: emoji };
  groupChatPendingReactions.set(String(messageId), msg.reactions);
  renderGroupChatTimeline();
  try {
    const data = await api("react_group_message", { message_id: messageId, reaction: emoji });
    if (data.status !== "success") {
      msg.reactions = previousReactions;
      renderGroupChatTimeline();
      showToast(data.message || "Could not react.", "error");
      return;
    }
    msg.reactions = data.reactions || previousReactions;
    renderGroupChatTimeline();
  } catch (err) {
    console.error(err);
    msg.reactions = previousReactions;
    renderGroupChatTimeline();
    showToast("Could not react.", "error");
  } finally {
    groupChatPendingReactions.delete(String(messageId));
  }
}

function startGroupChatReply(messageId) {
  const msg = currentGroupChatMessages.find((m) => String(m.id) === String(messageId));
  if (!msg) return;
  const isMine = currentUserObj && msg.sender_id === currentUserObj.id;
  groupChatReplyTo = {
    id: msg.id,
    label: isMine ? "yourself" : (msg.sender_name || "them"),
    text: msg.content || (msg.media_url ? "Photo" : "Message"),
  };
  renderGroupChatReplyStrip();
  document.getElementById("group-chat-input")?.focus();
}

function cancelGroupChatReply() {
  groupChatReplyTo = null;
  renderGroupChatReplyStrip();
}

function renderGroupChatReplyStrip() {
  const strip = document.getElementById("group-chat-reply-strip");
  if (!strip) return;
  if (!groupChatReplyTo) {
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
          <p class="text-xs font-bold text-gray-700 dark:text-gray-200">Replying to ${escapeHtml(groupChatReplyTo.label)}</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs italic">${escapeHtml(groupChatReplyTo.text)}</p>
        </div>
      </div>
      <button onclick="cancelGroupChatReply()" class="p-1 rounded-full text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 flex-shrink-0"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>`;
  if (window.lucide) lucide.createIcons();
}

async function openGroupChat(groupId, name) {
  currentGroupId = groupId;
  groupChatReplyTo = null;
  groupChatOpenReactionPickerId = null;
  groupChatOutbox = [];
  document.getElementById("group-chat-title").textContent = name;
  renderGroupChatReplyStrip();
  const modal = document.getElementById("group-chat-modal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  await refreshGroupMessages();
  startGroupChatPolling();
}

function closeGroupChatModal() {
  currentGroupId = null;
  groupChatReplyTo = null;
  groupChatOpenReactionPickerId = null;
  groupChatOutbox = [];
  stopGroupChatPolling();
  const modal = document.getElementById("group-chat-modal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

// Pending/failed outbox entries rendered as pseudo-messages, merged with the
// server-confirmed messages + call-log cards. Kept as its own render pass so
// both the 3s poll and an instant post-send call can trigger it.
function groupChatOutboxAsMessages() {
  return groupChatOutbox.map((o) => ({
    id: null,
    clientId: o.clientId,
    sender_id: currentUserObj?.id,
    sender_name: currentUserObj?.pet_name || "You",
    content: o.content,
    media_url: null,
    reply_to_id: o.reply_to_id || null,
    reply_to_text: o.reply_to_text || null,
    reactions: {},
    created_at: o.created_at,
    _status: o.status,
  }));
}

function renderGroupChatTimeline() {
  const container = document.getElementById("group-chat-messages");
  if (!container) return;
  const timeline = [
    ...currentGroupChatMessages.map((m) => ({ at: m.created_at, html: groupMessageHtml(m) })),
    ...groupChatOutboxAsMessages().map((m) => ({ at: m.created_at, html: groupMessageHtml(m) })),
    ...currentGroupChatCalls.map((c) => ({ at: c.started_at || c.created_at, html: renderCallLogCardHtml(c, "Member") })),
  ].sort((a, b) => new Date(a.at) - new Date(b.at));

  container.innerHTML = timeline.map((t) => t.html).join("") || `<p class="text-center text-sm text-gray-400 py-8">No messages yet — say hello!</p>`;
  container.scrollTop = container.scrollHeight;
  if (window.lucide) lucide.createIcons();
  
  if (typeof detectAndRenderLinkPreview === 'function') {
    [...currentGroupChatMessages, ...groupChatOutboxAsMessages()].forEach(m => {
      detectAndRenderLinkPreview(m.id, m.content, 'group-chat-');
    });
  }
}

async function refreshGroupMessages() {
  if (!currentGroupId) return;
  if (groupChatOpenReactionPickerId) return; // don't wipe an open picker mid-interaction
  try {
    const [msgData, callData] = await Promise.all([
      api("get_group_messages", { group_id: currentGroupId }),
      api("zoom_get_group_calls", { group_id: currentGroupId, limit: 20 }).catch(() => null),
    ]);
    if (msgData.status !== "success") return;
    currentGroupChatMessages = msgData.messages || [];
    if (groupChatPendingReactions.size) {
      currentGroupChatMessages.forEach((m) => {
        if (groupChatPendingReactions.has(String(m.id))) {
          m.reactions = groupChatPendingReactions.get(String(m.id));
        }
      });
    }
    currentGroupChatCalls = callData?.status === "success" ? (callData.calls || []) : [];
    renderGroupChatTimeline();
  } catch (err) {
    console.error(err);
  }
}

function startGroupChatPolling() {
  stopGroupChatPolling();
  groupChatPollTimer = setInterval(refreshGroupMessages, 3000);
}

function stopGroupChatPolling() {
  clearInterval(groupChatPollTimer);
  groupChatPollTimer = null;
}

function submitGroupMessage() {
  if (!currentGroupId) return;
  const input = document.getElementById("group-chat-input");
  const content = input.value.trim();
  if (!content) return;
  input.value = "";
  const replyTo = groupChatReplyTo;
  groupChatReplyTo = null;
  renderGroupChatReplyStrip();

  const clientId = `out_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
  groupChatOutbox.push({
    clientId,
    content,
    reply_to_id: replyTo?.id || "",
    reply_to_text: replyTo?.text || null,
    created_at: new Date().toISOString(),
    status: "pending",
  });
  renderGroupChatTimeline();
  sendGroupChatOutboxEntry(clientId);
}

async function sendGroupChatOutboxEntry(clientId) {
  const entry = groupChatOutbox.find((o) => o.clientId === clientId);
  if (!entry) return;
  entry.status = "pending";
  renderGroupChatTimeline();
  try {
    const data = await api("send_group_message", {
      group_id: currentGroupId,
      content: entry.content,
      reply_to_id: entry.reply_to_id || "",
    });
    if (data.status !== "success") {
      entry.status = "failed";
      renderGroupChatTimeline();
      showToast(data.message || "Could not send message.", "error");
      return;
    }
    groupChatOutbox = groupChatOutbox.filter((o) => o.clientId !== clientId);
    currentGroupChatMessages.push({
      ...data.message_row,
      reply_to_text: entry.reply_to_text,
      sender_name: currentUserObj?.pet_name || "You",
    });
    renderGroupChatTimeline();
  } catch (err) {
    console.error(err);
    entry.status = "failed";
    renderGroupChatTimeline();
    showToast("Could not send message.", "error");
  }
}

function retryGroupChatOutboxMessage(clientId) {
  const entry = groupChatOutbox.find((o) => o.clientId === clientId);
  if (!entry || entry.status !== "failed") return;
  sendGroupChatOutboxEntry(clientId);
}

function discardGroupChatOutboxMessage(clientId) {
  groupChatOutbox = groupChatOutbox.filter((o) => o.clientId !== clientId);
  renderGroupChatTimeline();
}

document.addEventListener("click", (e) => {
  if (!groupChatOpenReactionPickerId) return;
  if (e.target.closest('[id^="group-reaction-picker-"]') || e.target.closest('[onclick^="toggleGroupChatReactionPicker"]')) return;
  document.querySelectorAll('[id^="group-reaction-picker-"]').forEach((el) => el.classList.add("hidden"));
  groupChatOpenReactionPickerId = null;
});

async function leaveCurrentGroup() {
  if (!currentGroupId) return;
  if (!confirm("Leave this group?")) return;
  const btn = document.getElementById("group-chat-leave-btn");
  setButtonLoading(btn, true, "Leaving…");
  try {
    await api("leave_group", { group_id: currentGroupId });
    closeGroupChatModal();
    loadGroupsTab();
  } catch (err) {
    console.error(err);
    showToast("Could not leave group.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}
