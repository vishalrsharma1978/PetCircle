// Feed: composer, post rendering, like/comment.

let composerMediaUrl = null;

async function handleComposerMediaUpload(input) {
  const file = input.files?.[0];
  if (!file) return;
  try {
    const data = await uploadPhotoFile(file, "post-media");
    if (data.status !== "success") {
      showToast(data.message || "Could not upload photo.", "error");
      return;
    }
    composerMediaUrl = data.photo_url;
    document.getElementById("composer-media-img").src = data.photo_url;
    document.getElementById("composer-media-preview").classList.remove("hidden");
  } catch (err) {
    console.error(err);
    showToast("Could not upload photo.", "error");
  }
}

function clearComposerMedia() {
  composerMediaUrl = null;
  document.getElementById("composer-media-preview").classList.add("hidden");
  document.getElementById("composer-media-input").value = "";
}

async function submitPost() {
  const contentEl = document.getElementById("composer-content");
  const content = contentEl.value.trim();
  if (!content && !composerMediaUrl) {
    showToast("Write something or add a photo first.", "info");
    return;
  }

  const btn = document.getElementById("composer-submit-btn");
  setButtonLoading(btn, true, "Posting…");
  try {
    const data = await api("create_post", { content, media_url: composerMediaUrl || "" });
    if (data.status !== "success") {
      showToast(data.message || "Could not create post.", "error");
      return;
    }
    contentEl.value = "";
    clearComposerMedia();
    loadFeedPosts();
  } catch (err) {
    console.error(err);
    showToast("Could not create post.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

function postCardHtml(post) {
  const author = post.author || {};
  const mediaHtml = post.media_url
    ? `<img src="${escapeHtml(post.media_url)}" alt="" class="w-full rounded-xl mt-3 max-h-96 object-cover">`
    : "";
  const isMine = currentUserObj && post.user_id === currentUserObj.id;
  const deleteBtn = isMine
    ? `<button onclick="deletePost('${post.id}')" class="text-gray-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>`
    : "";

  return `
  <div class="warm-glass warm-lift rounded-2xl p-4" data-post-id="${post.id}">
    <div class="flex items-start justify-between gap-2">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden flex-shrink-0 ring-2 ring-transparent hover:ring-brand-400 transition-all">
          ${author.profile_photo_url ? `<img src="${escapeHtml(author.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="font-bold text-brand-700 dark:text-brand-300">${escapeHtml((author.name || "P")[0])}</span>`}
        </div>
        <div class="min-w-0">
          <p class="font-bold text-sm text-gray-900 dark:text-white truncate">${escapeHtml(author.name || "Member")}</p>
          <p class="text-xs text-gray-400">${[author.pet_type, author.breed].filter(Boolean).map(escapeHtml).join(" · ")} · ${timeAgo(post.created_at)}</p>
        </div>
      </div>
      ${deleteBtn}
    </div>
    ${post.content ? `<p class="text-sm text-gray-800 dark:text-gray-200 mt-3 whitespace-pre-wrap">${escapeHtml(post.content)}</p>` : ""}
    ${mediaHtml}
    <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
      <button onclick="togglePostLike('${post.id}')" class="flex items-center gap-1.5 text-sm font-semibold ${post.is_liked_by_me ? "text-brand-500" : "text-gray-500 dark:text-gray-400"}">
        <i data-lucide="heart" class="w-4 h-4 ${post.is_liked_by_me ? "fill-current" : ""}"></i>
        <span data-like-count>${post.like_count}</span>
      </button>
      <button onclick="toggleCommentBox('${post.id}')" class="flex items-center gap-1.5 text-sm font-semibold text-gray-500 dark:text-gray-400">
        <i data-lucide="message-circle" class="w-4 h-4"></i>
        <span data-comment-count>${post.comment_count}</span>
      </button>
    </div>
    <div id="comments-box-${post.id}" class="hidden mt-3 pt-3 border-t border-gray-100 dark:border-gray-800 space-y-2">
      <div id="comments-list-${post.id}" class="space-y-2"></div>
      <div class="flex items-center gap-2">
        <input type="text" id="comment-input-${post.id}" placeholder="Write a comment…" onkeydown="if(event.key==='Enter'){submitComment('${post.id}');}"
          class="flex-1 px-3 py-1.5 border border-gray-200 dark:border-gray-700 rounded-full text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <button id="comment-send-btn-${post.id}" onclick="submitComment('${post.id}')" class="text-sm font-bold text-brand-500">Send</button>
      </div>
    </div>
  </div>`;
}

function expandComposer() {
  document.getElementById("composer-extra")?.classList.remove("hidden");
  document.getElementById("composer-content")?.setAttribute("rows", "3");
  if (window.lucide) lucide.createIcons();
}

function loadComposerAvatar() {
  if (!currentUserObj) return;
  setAvatarPreview("composer-avatar-img", "composer-avatar-text", currentUserObj.profile_photo_url, (currentUserObj.pet_name || "P")[0]);
}

async function loadFeedPosts() {
  const list = document.getElementById("feed-list");
  if (!list) return;
  loadComposerAvatar();
  list.innerHTML = postCardSkeletonListHtml(3);
  try {
    const data = await api("get_posts", { pet_type: currentUserObj?.pet_type || "" });
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-center text-sm text-red-500 py-8">${escapeHtml(data.message || "Could not load feed.")}</p>`;
      return;
    }
    const posts = data.posts || [];
    if (!posts.length) {
      list.innerHTML = `<p class="text-center text-sm text-gray-400 py-8">No posts yet — be the first to share something!</p>`;
      return;
    }
    list.innerHTML = posts.map(postCardHtml).join("");
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
    list.innerHTML = `<p class="text-center text-sm text-red-500 py-8">Could not load feed.</p>`;
  }
}

// Optimistic: flip the heart/count immediately, then reconcile with (or
// revert to) the server's authoritative response — same pattern as message
// reactions in friends.js/groups.js.
async function togglePostLike(postId) {
  const card = document.querySelector(`[data-post-id="${postId}"]`);
  if (!card) return;
  const btn = card.querySelector("button[onclick*='togglePostLike']");
  if (!btn || btn.disabled) return;
  const icon = btn.querySelector("i");
  const countEl = btn.querySelector("[data-like-count]");

  const wasLiked = btn.classList.contains("text-brand-500");
  const previousCount = parseInt(countEl.textContent, 10) || 0;
  const optimisticLiked = !wasLiked;

  const applyState = (liked, count) => {
    countEl.textContent = String(count);
    btn.classList.toggle("text-brand-500", liked);
    btn.classList.toggle("text-gray-500", !liked);
    icon.classList.toggle("fill-current", liked);
  };

  btn.disabled = true;
  applyState(optimisticLiked, Math.max(0, previousCount + (optimisticLiked ? 1 : -1)));

  try {
    const data = await api("toggle_like", { post_id: postId });
    if (data.status !== "success") {
      applyState(wasLiked, previousCount);
      showToast(data.message || "Could not update like.", "error");
      return;
    }
    applyState(data.is_liked, data.like_count);
  } catch (err) {
    console.error(err);
    applyState(wasLiked, previousCount);
    showToast("Could not update like.", "error");
  } finally {
    btn.disabled = false;
  }
}

function toggleCommentBox(postId) {
  const box = document.getElementById(`comments-box-${postId}`);
  if (!box) return;
  const willOpen = box.classList.contains("hidden");
  box.classList.toggle("hidden");
  if (willOpen) loadComments(postId);
}

async function loadComments(postId) {
  const list = document.getElementById(`comments-list-${postId}`);
  if (!list) return;
  try {
    const data = await api("get_comments", { post_id: postId });
    if (data.status !== "success") return;
    list.innerHTML = (data.comments || []).map((c) => `
      <div class="text-sm">
        <span class="font-bold text-gray-800 dark:text-gray-100">${escapeHtml(c.author?.name || "Member")}</span>
        <span class="text-gray-600 dark:text-gray-300">${escapeHtml(c.content)}</span>
      </div>`).join("") || `<p class="text-xs text-gray-400">No comments yet.</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function submitComment(postId) {
  const input = document.getElementById(`comment-input-${postId}`);
  const content = input.value.trim();
  if (!content) return;
  const btn = document.getElementById(`comment-send-btn-${postId}`);
  setButtonLoading(btn, true, "…");
  try {
    const data = await api("add_comment", { post_id: postId, content });
    if (data.status !== "success") {
      showToast(data.message || "Could not add comment.", "error");
      return;
    }
    input.value = "";
    loadComments(postId);
    const card = document.querySelector(`[data-post-id="${postId}"]`);
    const countEl = card?.querySelector("[data-comment-count]");
    if (countEl) countEl.textContent = String((parseInt(countEl.textContent, 10) || 0) + 1);
  } catch (err) {
    console.error(err);
    showToast("Could not add comment.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function deletePost(postId) {
  if (!confirm("Delete this post?")) return;
  try {
    const data = await api("delete_post", { post_id: postId });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete post.", "error");
      return;
    }
    document.querySelector(`[data-post-id="${postId}"]`)?.remove();
  } catch (err) {
    console.error(err);
    showToast("Could not delete post.", "error");
  }
}
