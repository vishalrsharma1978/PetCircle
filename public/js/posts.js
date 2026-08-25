// Feed: composer, post rendering, like/comment.

let composerMediaUrl = null;
let feedPosts = [];

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

function markPostMediaLoaded(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add("is-loaded");
}

function markPostMediaError(id) {
  const el = document.getElementById(id);
  if (el) el.classList.add("is-loaded", "has-error");
}

function postCardHtml(post, index = 0) {
  const author = post.author || {};
  let mediaHtml = "";
  if (post.media_url) {
    const safeUrl = escapeHtml(post.media_url);
    const frameId = `post-media-feed-${String(post.id).replace(/[^a-zA-Z0-9_-]/g, "_")}`;
    mediaHtml = `
      <div class="post-media-shell mt-3 rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-800 bg-gray-100 dark:bg-gray-950">
        <div id="${frameId}" class="post-media-frame">
          <div class="post-media-skeleton"></div>
          <img src="${safeUrl}" alt="" aria-hidden="true" class="post-media-backdrop">
          <img src="${safeUrl}" loading="lazy" decoding="async" onload="markPostMediaLoaded('${frameId}')" onerror="markPostMediaError('${frameId}')" class="post-media-contain" alt="Post media">
          <div class="post-media-error-placeholder absolute inset-0 z-10 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-900 text-gray-500">
            <i data-lucide="image-off" class="w-10 h-10 text-gray-400 mb-2"></i>
            <span class="text-xs font-semibold">Unable to load media</span>
          </div>
        </div>
      </div>`;
  }
  const isMine = currentUserObj && post.user_id === currentUserObj.id;
    const menuItems = isMine
    ? `<button onclick="event.stopPropagation(); editPost('${post.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</button>
       <button onclick="event.stopPropagation(); deletePost('${post.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 flex items-center gap-2"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>
       <button onclick="event.stopPropagation(); archivePost('${post.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i data-lucide="archive" class="w-3.5 h-3.5"></i> Archive</button>
       <button onclick="event.stopPropagation(); copyPostLink('${post.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i data-lucide="link" class="w-3.5 h-3.5"></i> Copy link</button>`
    : `<button onclick="event.stopPropagation(); reportPost('${post.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 flex items-center gap-2"><i data-lucide="flag" class="w-3.5 h-3.5"></i> Report</button>
       <button onclick="event.stopPropagation(); copyPostLink('${post.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i data-lucide="link" class="w-3.5 h-3.5"></i> Copy link</button>`;

  const menuBtn = `
    <div class="relative flex-shrink-0">
      <button onclick="event.stopPropagation(); toggleDropdownMenu(event, 'post-menu-${post.id}')" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
      <div id="post-menu-${post.id}" class="dropdown-menu hidden absolute right-0 mt-1 w-36 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-30">
        ${menuItems}
      </div>
    </div>`;

  return `
  <div class="warm-glass warm-lift rounded-2xl p-4 cursor-pointer relative" style="z-index: ${Math.max(1, 30 - (typeof index === 'number' ? index : 0))};" data-post-id="${post.id}" onclick="openPostPage('${post.id}')">
    <div class="flex items-start justify-between gap-2">
      <div class="flex items-center gap-3 min-w-0">
        <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden flex-shrink-0 ring-2 ring-transparent hover:ring-brand-400 transition-all cursor-pointer" onclick="event.stopPropagation(); openMemberProfile('${author.user_id}')">
          ${author.profile_photo_url ? `<img src="${escapeHtml(author.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="font-bold text-brand-700 dark:text-brand-300">${escapeHtml((author.name || "P")[0])}</span>`}
        </div>
        <div class="min-w-0">
          <p class="font-bold text-sm text-gray-900 dark:text-white truncate cursor-pointer hover:underline flex items-center gap-1" onclick="event.stopPropagation(); openMemberProfile('${author.user_id}')">
            <span class="truncate">${escapeHtml(author.name || "Member")}</span>
            ${author.handle ? `<span class="text-xs text-gray-500 dark:text-gray-400 font-normal flex-shrink-0">@${escapeHtml(author.handle)}</span>` : ""}
          </p>
          <p class="text-xs text-gray-400">${[author.pet_type, author.breed].filter(Boolean).map(escapeHtml).join(" · ")} · ${timeAgo(post.created_at)}</p>
        </div>
      </div>
      ${menuBtn}
    </div>
    ${post.content ? `<p class="text-sm text-gray-800 dark:text-gray-200 mt-3 whitespace-pre-wrap">${escapeHtml(post.content)}</p>` : ""}
    ${(!post.media_url && post.content) ? detectAndRenderLinkPreview(post.id, post.content, "feed-") : ""}
    ${mediaHtml}
    <div class="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
      <div class="relative group reaction-picker-container">
        <button onclick="event.stopPropagation(); togglePostLike('${post.id}')" onmouseenter="this.nextElementSibling?.classList.remove('hidden')" onmouseleave="this.nextElementSibling?.classList.add('hidden')" oncontextmenu="event.preventDefault(); event.stopPropagation(); toggleMobileReactionPicker('${post.id}')" class="flex items-center gap-1.5 text-sm font-semibold ${post.is_liked_by_me ? "text-brand-500" : "text-gray-500 dark:text-gray-400"}">
          <span class="like-icon-wrapper flex items-center justify-center w-4 h-4"><i data-lucide="heart" class="w-4 h-4 ${post.is_liked_by_me ? "fill-current" : ""}"></i></span>
          <span data-like-count>${post.like_count || 0}</span>
        </button>
        <!-- Desktop Hover Picker -->
        <div class="absolute bottom-full left-0 pb-3 hidden sm:block pointer-events-none">
          <div class="pointer-events-auto">
            ${renderReactionPickerHtml(post.id)}
          </div>
        </div>
        <!-- Mobile Tap Picker -->
        <div id="reaction-picker-${post.id}" class="mobile-reaction-picker absolute bottom-full left-0 pb-3 hidden sm:hidden z-10">
          ${renderReactionPickerHtml(post.id)}
        </div>
      </div>
      <div id="reaction-badge-${post.id}" class="inline-flex items-center gap-1 mr-auto">${renderReactionBadgeHtml(post)}</div>
      <button onclick="event.stopPropagation(); toggleComments('${post.id}')" class="flex items-center gap-1.5 text-sm font-semibold text-gray-500 dark:text-gray-400">
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

function toggleDropdownMenu(evt, menuId) {
  evt.stopPropagation();
  document.querySelectorAll('.dropdown-menu').forEach((menu) => {
    if (menu.id !== menuId) menu.classList.add("hidden");
  });
  document.getElementById(menuId)?.classList.toggle("hidden");
}
document.addEventListener("click", () => {
  document.querySelectorAll('.dropdown-menu').forEach((menu) => menu.classList.add("hidden"));
});

function postDetailSkeletonHtml() {
  return `
    <div class="mx-auto max-w-5xl space-y-4 animate-pulse">
      <div class="h-10 w-32 bg-gray-200 dark:bg-gray-800 rounded-full"></div>
      <article class="overflow-hidden rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
        <div class="flex items-start justify-between gap-4 border-b border-gray-50 dark:border-gray-800 p-4 sm:p-5">
          <div class="flex items-center gap-3 w-full">
            <div class="w-11 h-11 rounded-full bg-gray-200 dark:bg-gray-800"></div>
            <div class="flex-1 space-y-2">
              <div class="h-4 w-1/4 bg-gray-200 dark:bg-gray-800 rounded"></div>
              <div class="h-3 w-1/6 bg-gray-100 dark:bg-gray-800 rounded"></div>
            </div>
          </div>
        </div>
        <div class="space-y-4 p-4 sm:p-5">
          <div class="h-4 w-3/4 bg-gray-200 dark:bg-gray-800 rounded"></div>
          <div class="h-4 w-1/2 bg-gray-100 dark:bg-gray-800 rounded"></div>
          <div class="h-64 w-full bg-gray-200 dark:bg-gray-800 rounded-xl mt-4"></div>
        </div>
        <div class="border-y border-gray-50 dark:border-gray-800 px-4 py-3 sm:px-5">
          <div class="h-8 w-24 bg-gray-200 dark:bg-gray-800 rounded-full"></div>
        </div>
        <div class="p-4 sm:p-5">
          <div class="h-12 w-full bg-gray-200 dark:bg-gray-800 rounded-full"></div>
        </div>
      </article>
    </div>`;
}

// Pure render of the post-detail article HTML from a post object — shared
// by the instant-open-from-cache path and the normal fetch-then-render
// path in openPostPage() below.
function postDetailHtml(post) {
    const isMine = currentUserObj && post.user_id === currentUserObj.id;
    const author = post.author || {};
    const safeAuthorName = escapeHtml(author.name || "Member");
    const safeAuthorId = String(author.user_id || "").replace(/'/g, "\\'");
    const safeAvatar = author.profile_photo_url
      ? `<img src="${escapeHtml(author.profile_photo_url)}" class="w-11 h-11 rounded-full object-cover cursor-pointer" onclick="openMemberProfile('${safeAuthorId}')">`
      : `<div class="w-11 h-11 bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center font-bold text-brand-700 dark:text-brand-300 cursor-pointer" onclick="openMemberProfile('${safeAuthorId}')">${safeAuthorName[0]}</div>`;
      
    const menuItems = isMine
      ? `<button onclick="event.stopPropagation(); deletePost('${post.id}')" class="w-full text-left px-4 py-2.5 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2"><i data-lucide="trash-2" class="w-4 h-4"></i> Delete post</button>
         <button onclick="event.stopPropagation(); archivePost('${post.id}')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"><i data-lucide="archive" class="w-4 h-4"></i> Archive post</button>
         <button onclick="event.stopPropagation(); copyPostLink('${post.id}')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"><i data-lucide="link" class="w-4 h-4"></i> Copy link</button>`
      : `<button onclick="event.stopPropagation(); reportPost('${post.id}')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"><i data-lucide="flag" class="w-4 h-4"></i> Report</button>
         <button onclick="event.stopPropagation(); copyPostLink('${post.id}')" class="w-full text-left px-4 py-2.5 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"><i data-lucide="link" class="w-4 h-4"></i> Copy link</button>`;

    const moreHtml = `
      <div class="relative flex-shrink-0">
        <button onclick="toggleDropdownMenu(event, 'detail-post-menu-${post.id}')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-2 rounded-lg"><i data-lucide="more-horizontal" class="w-5 h-5"></i></button>
        <div id="detail-post-menu-${post.id}" class="dropdown-menu hidden absolute right-0 mt-1 w-44 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-30 py-1">
          ${menuItems}
        </div>
      </div>`;

    let mediaHtml = "";
    if (post.media_url) {
      const safeUrl = escapeHtml(post.media_url);
      const frameId = `post-media-detail-${String(post.id).replace(/[^a-zA-Z0-9_-]/g, "_")}`;
      mediaHtml = `
        <div class="post-media-shell rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-800 bg-gray-100 dark:bg-gray-950">
          <div id="${frameId}" class="post-media-frame">
            <div class="post-media-skeleton"></div>
            <img src="${safeUrl}" alt="" aria-hidden="true" class="post-media-backdrop">
            <img src="${safeUrl}" loading="lazy" decoding="async" onload="markPostMediaLoaded('${frameId}')" onerror="markPostMediaError('${frameId}')" class="post-media-contain" alt="Post media">
            <div class="post-media-error-placeholder absolute inset-0 z-10 flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-900 text-gray-500">
              <i data-lucide="image-off" class="w-10 h-10 text-gray-400 mb-2"></i>
              <span class="text-xs font-semibold">Unable to load media</span>
            </div>
          </div>
        </div>`;
    }

    let html = `
      <div class="mx-auto max-w-5xl space-y-4">
        <button type="button" onclick="switchSocialTab('feed')" class="inline-flex items-center gap-2 rounded-full border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-4 py-2 text-sm font-bold text-gray-700 dark:text-gray-200 hover:border-brand-200 hover:text-brand-600 transition-colors">
          <i data-lucide="arrow-left" class="h-4 w-4"></i> Back to feed
        </button>
        <article class="overflow-hidden rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm">
          <div class="flex items-start justify-between gap-4 border-b border-gray-50 dark:border-gray-800 p-4 sm:p-5">
            <div class="flex min-w-0 items-start gap-3">
              ${safeAvatar}
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-1.5 text-sm font-bold text-gray-900 dark:text-gray-100">
                  <span class="cursor-pointer hover:underline flex items-center gap-1" onclick="openMemberProfile('${safeAuthorId}')">${safeAuthorName}</span>
                  ${author.handle ? `<span class="text-xs text-gray-500 dark:text-gray-400 font-normal">@${escapeHtml(author.handle)}</span>` : ""}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">${timeAgo(post.created_at)}</div>
              </div>
            </div>
            ${moreHtml}
          </div>
          <div class="space-y-4 p-4 sm:p-5">
            ${post.content ? `<p class="text-base leading-7 text-gray-800 dark:text-gray-200 whitespace-pre-wrap break-words">${escapeHtml(post.content)}</p>` : ""}
            ${(!post.media_url && post.content) ? detectAndRenderLinkPreview(post.id, post.content, "detail-") : ""}
            ${mediaHtml}
          </div>
          <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-3 border-y border-gray-50 dark:border-gray-800 px-4 py-3 text-sm text-gray-500 dark:text-gray-400 sm:px-5">
            <div class="flex flex-wrap items-center gap-2 min-w-0">
              <div class="relative group reaction-picker-container">
                <button id="detail-like-btn-${post.id}" onclick="event.stopPropagation(); togglePostLike('${post.id}')" class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800 flex-shrink-0 ${post.is_liked_by_me ? "text-brand-500" : "text-gray-500 dark:text-gray-400"}">
                  <span class="like-icon-wrapper flex items-center justify-center w-4 h-4"><i data-lucide="heart" class="w-4 h-4 ${post.is_liked_by_me ? "fill-current" : ""}"></i></span>
                  <span id="detail-like-count-${post.id}" data-like-count class="text-sm font-medium ml-0.5">${post.like_count || 0}</span>
                </button>
                <button onclick="event.stopPropagation();" onmouseenter="this.nextElementSibling?.classList.remove('hidden')" onmouseleave="this.nextElementSibling?.classList.add('hidden')" oncontextmenu="event.preventDefault(); event.stopPropagation(); toggleMobileReactionPicker('${post.id}')" class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 hover:bg-gray-50 dark:hover:bg-gray-800 flex-shrink-0 text-gray-500 dark:text-gray-400">
                  <i data-lucide="smile" class="w-4 h-4"></i>
                </button>
                <div class="absolute bottom-full left-0 pb-3 hidden sm:block pointer-events-none">
                  <div class="pointer-events-auto">
                    ${renderReactionPickerHtml(post.id)}
                  </div>
                </div>
                <div id="reaction-picker-${post.id}" class="mobile-reaction-picker absolute bottom-full left-0 pb-3 hidden sm:hidden z-10">
                  ${renderReactionPickerHtml(post.id)}
                </div>
              </div>
              <div id="detail-reaction-badge-${post.id}" class="inline-flex items-center gap-1">${renderReactionBadgeHtml(post)}</div>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0 ml-auto pl-2">
              <div class="inline-flex items-center gap-2">
                <i data-lucide="message-circle" class="h-4 w-4"></i>
                <span data-comment-count>${post.comment_count}</span> Comments
              </div>
            </div>
          </div>
          <section class="bg-gray-50/70 dark:bg-gray-950/30 p-4 sm:p-5">
            <div class="mb-4 flex gap-2">
              <input type="text" id="detail-comment-input-${post.id}" onkeydown="if(event.key==='Enter') submitComment('${post.id}', 'detail-comment-input-${post.id}', 'detail-comment-send-btn-${post.id}')" placeholder="Join the conversation..." class="flex-1 rounded-full border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-800 outline-none focus:ring-1 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
              <button id="detail-comment-send-btn-${post.id}" onclick="submitComment('${post.id}', 'detail-comment-input-${post.id}', 'detail-comment-send-btn-${post.id}')" class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-500 text-white hover:bg-brand-600">
                <i data-lucide="send" class="h-4 w-4"></i>
              </button>
            </div>
            <div id="detail-comments-list-${post.id}" class="space-y-1"></div>
          </section>
        </article>
      </div>`;

    return html;
}

async function openPostPage(postId) {
  const container = document.getElementById("post-detail-view");
  if (!container) return;

  // Instant open: reuse a post already sitting in memory (from the feed
  // list, or a cached get_post_by_id fetch) before touching the network.
  let initialPost = feedPosts.find((p) => String(p.id) === String(postId));
  if (!initialPost) {
    const cached = peekApiCache("get_post_by_id", { post_id: postId });
    if (cached?.status === "success" && cached.post) {
      initialPost = cached.post;
      feedPosts.push(initialPost);
    }
  }

  if (initialPost) {
    container.innerHTML = postDetailHtml(initialPost);
    if (window.lucide) lucide.createIcons();
    if (initialPost.commentsLoaded) updatePostCommentsDom(postId);
  } else {
    container.innerHTML = postDetailSkeletonHtml();
  }
  switchSocialTab('post-detail');
  requestAnimationFrame(() => {
    container.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  try {
    const data = await api("get_post_by_id", { post_id: postId }, { forceRefresh: !!initialPost });
    if (data.status !== "success" || !data.post) {
      if (!initialPost) container.innerHTML = `<p class="text-center text-sm text-red-500 py-8">Post not found.</p>`;
      return;
    }

    const post = data.post;
    container.innerHTML = postDetailHtml(post);
    if (window.lucide) lucide.createIcons();

    if (!post.commentsLoaded) {
      try {
        const cData = await api("get_comments", { post_id: postId });
        if (cData.status === "success") {
          post.commentList = dedupePostComments(cData.comments || []);
          post.commentsLoaded = true;
          post.comments = post.commentList.length;
        }
      } catch (e) {
        console.error("Failed to load comments", e);
      }
    }

    const existingIdx = feedPosts.findIndex((p) => String(p.id) === String(postId));
    if (existingIdx !== -1) feedPosts[existingIdx] = post;
    else feedPosts.push(post);

    updatePostCommentsDom(postId);

  } catch (err) {
    console.error(err);
    if (!initialPost) container.innerHTML = `<p class="text-center text-sm text-red-500 py-8">Could not load post.</p>`;
  }
}

async function archivePost(postId) {
  const btn = (typeof event !== "undefined" && event) ? event.target.closest("button") : null;
  if (!(await confirmAction({ title: "Archive this post?", message: "It will be hidden from the feed. You can restore it later from Settings.", confirmLabel: "Archive", danger: false, icon: "archive" }))) return;
  setButtonLoading(btn, true);
  try {
    const data = await api("archive_post", { post_id: postId });
    if (data.status !== "success") {
      showToast(data.message || "Could not archive post.", "error");
      return;
    }
    document.querySelectorAll(`[data-post-id="${postId}"]`).forEach(el => el.remove());
    showToast("Post archived.", "success");
    if (document.getElementById('view-post')?.classList.contains('active')) {
      switchView('view-social-feed');
    }
  } catch (err) {
    console.error(err);
    showToast("Could not archive post.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function reportPost(postId) {
  if (!confirm("Report this post for inappropriate content?")) return;
  try {
    const data = await api("report_post", { post_id: postId });
    if (data.status !== "success") {
      showToast(data.message || "Could not report post.", "error");
      return;
    }
    showToast("Post reported. Our team will review it.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not report post.", "error");
  }
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

  const payload = { pet_type: currentUserObj?.pet_type || "" };

  // Posts already sitting in feedPosts reflect this session's local
  // optimistic edits (likes/reactions/comments — see togglePostLike's
  // "intentionally omitting overwrite of local state" convention), so they
  // take priority over a cached network snapshot, which could be stale
  // relative to those in-place mutations. Only fall back to the response
  // cache (and then the skeleton) when nothing's loaded yet this session.
  let hadInstantRender = false;
  if (feedPosts.length) {
    list.innerHTML = feedPosts.map(postCardHtml).join("");
    if (window.lucide) lucide.createIcons();
    hadInstantRender = true;
  } else {
    const cached = peekApiCache("get_posts", payload);
    if (cached?.status === "success" && cached.posts?.length) {
      feedPosts = cached.posts;
      list.innerHTML = cached.posts.map(postCardHtml).join("");
      if (window.lucide) lucide.createIcons();
      hadInstantRender = true;
    } else {
      list.innerHTML = postCardSkeletonListHtml(3);
    }
  }

  try {
    const data = await api("get_posts", payload, { forceRefresh: hadInstantRender });
    if (data.status !== "success") {
      if (!hadInstantRender) list.innerHTML = `<p class="text-center text-sm text-red-500 py-8">${escapeHtml(data.message || "Could not load feed.")}</p>`;
      return;
    }
    const posts = data.posts || [];
    feedPosts = posts;
    if (!posts.length) {
      list.innerHTML = `<p class="text-center text-sm text-gray-400 py-8">No posts yet — be the first to share something!</p>`;
      return;
    }
    list.innerHTML = posts.map(postCardHtml).join("");
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
    if (!hadInstantRender) list.innerHTML = `<p class="text-center text-sm text-red-500 py-8">Could not load feed.</p>`;
  }
}

// Optimistic: flip the heart/count immediately, then reconcile with (or
// revert to) the server's authoritative response.
async function togglePostLike(postId) {
  const post = feedPosts.find(p => String(p.id) === String(postId));
  if (!post) return;
  const wasLiked = post.is_liked_by_me;
  post.is_liked_by_me = !wasLiked;
  post.like_count = (parseInt(post.like_count, 10) || 0) + (wasLiked ? -1 : 1);
  updatePostLikeDom(postId);

  try {
    const data = await api("toggle_like", { post_id: postId });
    if (data.status !== "success") {
      throw new Error(data.message || "Could not update like.");
    }
    
    // Intentionally omitting overwrite of local state on success to prevent race conditions.
  } catch (err) {
    post.is_liked_by_me = wasLiked;
    post.like_count = (parseInt(post.like_count, 10) || 0) + (wasLiked ? 1 : -1);
    updatePostLikeDom(postId);
    console.error(err);
    showToast(err.message || "Could not update like.", "error");
  }
}

function updatePostLikeDom(postId) {
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  if (!post) return;

  const cards = document.querySelectorAll(`[data-post-id="${postId}"]`);
  cards.forEach(card => {
    const btn = card.querySelector("button[onclick*='togglePostLike']");
    const countEl = btn ? btn.querySelector("[data-like-count]") : null;
    const iconWrapper = btn ? btn.querySelector(".like-icon-wrapper") || btn : null;

    if (countEl) countEl.textContent = String(post.like_count || 0);
    if (btn) {
      const isLiked = Boolean(post.is_liked_by_me);
      btn.classList.toggle("text-brand-500", isLiked);
      btn.classList.toggle("text-gray-500", !isLiked);
      btn.classList.toggle("dark:text-gray-400", !isLiked);
      
      if (iconWrapper) {
        iconWrapper.innerHTML = `<i data-lucide="heart" class="w-4 h-4 ${isLiked ? 'fill-current' : ''}"></i>`;
      }
    }
  });

  const detailContainer = document.getElementById("post-detail-view");
  if (detailContainer) {
    const detailBtn = detailContainer.querySelector(`button[onclick*="togglePostLike('${postId}')"]`);
    if (detailBtn) {
      const detailCountEl = detailBtn.querySelector("[data-like-count]");
      const iconWrapper = detailBtn.querySelector(".like-icon-wrapper") || detailBtn;
      const isLiked = Boolean(post.is_liked_by_me);

      if (detailCountEl) detailCountEl.textContent = String(post.like_count || 0);
      detailBtn.classList.toggle("text-brand-500", isLiked);
      detailBtn.classList.toggle("text-gray-500", !isLiked);

      if (iconWrapper) {
        iconWrapper.innerHTML = `<i data-lucide="heart" class="w-4 h-4 ${isLiked ? 'fill-current' : ''}"></i>`;
      }
    }
  }
  
  if (typeof lucide !== "undefined") lucide.createIcons();
}

function updatePostReactionDom(postId) {
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  if (!post) return;
  
  const cards = document.querySelectorAll(`[data-post-id="${postId}"]`);
  cards.forEach(card => {
    const badge = card.querySelector(`#reaction-badge-${postId}`);
    if (badge) badge.innerHTML = renderReactionBadgeHtml(post);
  });
  
  const detailContainer = document.getElementById("post-detail-view");
  if (detailContainer) {
    const detailBadge = detailContainer.querySelector(`#detail-reaction-badge-${postId}`);
    if (detailBadge) detailBadge.innerHTML = renderReactionBadgeHtml(post);
  }
}


function getArchivedCommentIds() {
  const key = "esamaj_archived_comments_" + (currentUserObj?.email || "guest");
  return JSON.parse(localStorage.getItem(key) || "[]");
}
function saveArchivedCommentIds(ids) {
  const key = "esamaj_archived_comments_" + (currentUserObj?.email || "guest");
  localStorage.setItem(key, JSON.stringify(ids));
}

function findCommentInPost(post, commentId) {
  if (!post || !Array.isArray(post.commentList)) return null;
  return post.commentList.find((c) => String(c.id) === String(commentId)) || null;
}

const commentSubmitInFlight = new Set();

function commentCreatedMs(comment) {
  const ts = new Date(comment?.created_at || 0).getTime();
  return Number.isFinite(ts) ? ts : 0;
}

function dedupePostComments(comments) {
  const byId = new Set();
  const contentWindowKeys = new Map();
  const ordered = [];
  (Array.isArray(comments) ? comments : []).forEach((comment) => {
    if (!comment) return;
    const id = String(comment.id || "");
    if (id && byId.has(id)) return;
    const created = commentCreatedMs(comment);
    const contentKey = [
      String(comment.user_id || ""),
      String(comment.parent_id || ""),
      String(comment.content || "").trim().replace(/\s+/g, " ").toLowerCase(),
    ].join("::");
    const prior = contentWindowKeys.get(contentKey);
    if (prior && Math.abs(created - prior) <= 5000) return;
    if (id) byId.add(id);
    contentWindowKeys.set(contentKey, created);
    ordered.push(comment);
  });
  return ordered;
}

function renderPostCommentsHtml(post, contextPrefix = '') {
  if (!post.commentsLoaded) return "";
  const all = dedupePostComments(post.commentList);
  const archived = new Set(getArchivedCommentIds().map(String));
  const visible = all.filter((c) => c && !c.is_deleted && !archived.has(String(c.id)));
  if (!visible.length) {
    return `<div class="text-xs text-gray-500 py-3" data-empty-comments="${escapeHtml(post.id)}">No comments yet.</div>`;
  }

  // Option C: Flatten the thread tree. Show all chronologically.
  const sortedVisible = visible.slice().sort((a, b) => new Date(a.created_at || 0) - new Date(b.created_at || 0));

  return sortedVisible.map((c) => renderCommentNode(post, c, all, contextPrefix)).join("");
}

function renderCommentNode(post, c, allComments, contextPrefix = '') {
  const cProfile = c.profiles || {};
  const authorObj = c.author || {};
  const cAuthor = (typeof authorObj === "string" ? authorObj : authorObj.name) || cProfile.full_name || cProfile.name || "Member";
  const cHandle = c.author_handle || (cProfile.handle ? `@${cProfile.handle}` : null);
  const cAvatar = authorObj.profile_photo_url || c.profile_photo_url || cProfile.profile_photo_url;
  const isOwn = currentUserObj?.id && String(c.user_id) === String(currentUserObj.id);
  const liked = !!(c.isLiked || c.is_liked_by_me);
  const likeCount = c.likes ?? c.like_count ?? 0;
  const postId = String(post.id);
  const cid = String(c.id);
  const edited = c._edited ? ` <span class="text-[10px] text-gray-400">(edited)</span>` : "";

  let mentionHtml = "";
  if (c.parent_id) {
    const parent = allComments.find(p => String(p.id) === String(c.parent_id));
    if (parent) {
      const pProfile = parent.profiles || {};
      const pAuthor = parent.author || pProfile.full_name || pProfile.name || "Member";
      const pFirstName = pAuthor.split(' ')[0];
      mentionHtml = `<span class="text-brand-500 font-bold mr-1 cursor-pointer">@${escapeHtml(pFirstName)}</span>`;
    }
  }

  const bodyHtml = c._editing
    ? `<div class="bg-white dark:bg-gray-800 border border-brand-200 dark:border-brand-800 rounded-2xl px-3 py-2">
             <textarea id="comment-edit-input-${cid}" rows="2" class="w-full resize-none bg-transparent text-sm text-gray-700 dark:text-gray-200 focus:outline-none">${escapeHtml(c.content)}</textarea>
             <div class="flex justify-end gap-2 mt-1">
               <button onclick="cancelEditComment('${postId}','${cid}')" class="no-accent-hover text-xs font-semibold text-gray-500 hover:text-gray-700 px-2 py-1">Cancel</button>
               <button onclick="saveCommentEdit('${postId}','${cid}')" class="no-accent-hover text-xs font-bold text-white bg-brand-500 hover:bg-brand-600 rounded-full px-3 py-1">Save</button>
             </div>
           </div>`
    : `<div class="bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl px-3 py-2">
             <div class="flex items-center justify-between gap-2">
               <div class="text-xs font-bold text-gray-700 dark:text-gray-200 truncate flex items-center gap-1">${escapeHtml(cAuthor)}${cHandle ? `<span class="text-[10px] text-gray-500 font-normal mt-0.5">${escapeHtml(cHandle)}</span>` : ""}</div>
               ${isOwn ? `
                 <div class="relative flex-shrink-0">
                   <button onclick="toggleCommentMenu('${cid}', event)" class="no-accent-hover text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 p-0.5 rounded"><i data-lucide="more-horizontal" class="w-4 h-4"></i></button>
                   <div id="comment-menu-${cid}" class="comment-menu hidden absolute right-0 mt-1 w-36 bg-white dark:bg-gray-800 rounded-lg shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-30 py-1">
                     <button onclick="startEditComment('${postId}','${cid}')" class="no-accent-hover w-full text-left px-3 py-2 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</button>
                     <button onclick="archiveComment('${postId}','${cid}')" class="no-accent-hover w-full text-left px-3 py-2 text-xs text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2"><i data-lucide="archive" class="w-3.5 h-3.5"></i> Archive</button>
                     <button onclick="deleteComment('${postId}','${cid}')" class="no-accent-hover w-full text-left px-3 py-2 text-xs text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/30 flex items-center gap-2"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>
                   </div>
                 </div>` : ""}
             </div>
             <div class="text-sm text-gray-700 dark:text-gray-300 break-words whitespace-pre-wrap">${mentionHtml}${escapeHtml(c.content)}${edited}</div>
           </div>`;

  return `
        <div class="comment-thread mt-2" data-comment-thread="${cid}">
          <div class="flex gap-2 py-1.5">
            ${cAvatar
      ? `<img src="${escapeHtml(cAvatar)}" class="w-8 h-8 rounded-full object-cover flex-shrink-0" alt="">`
      : `<div class="w-8 h-8 flex-shrink-0 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-100 flex items-center justify-center text-xs font-bold">${getSocialAvatar(cAuthor)}</div>`}
            <div class="flex-1 min-w-0">
              ${bodyHtml}
              <div class="flex items-center gap-4 mt-1 px-1 text-[11px] text-gray-400">
                <button onclick="toggleCommentLike('${postId}','${cid}')" class="no-accent-hover flex items-center gap-1 hover:text-brand-500 transition-colors ${liked ? "text-brand-500 font-bold" : ""}">
                  <i data-lucide="heart" class="w-3.5 h-3.5 ${liked ? "fill-current" : ""}"></i> <span>${likeCount}</span>
                </button>
                <button onclick="toggleCommentReplyInput('${cid}', '${contextPrefix}')" class="no-accent-hover hover:text-brand-500 transition-colors font-semibold">Reply</button>
                <span>${formatDateTime(c.created_at)}</span>
              </div>
              <div id="${contextPrefix}comment-reply-box-${cid}" class="hidden mt-2 flex gap-2">
                <input type="text" id="${contextPrefix}comment-reply-input-${cid}" onkeydown="handleCommentReplyKeydown(event,'${postId}','${cid}','${contextPrefix}')" placeholder="Write a reply..." class="flex-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-full px-3 py-1.5 text-xs focus:outline-none focus:ring-1 focus:ring-brand-500">
                <button onclick="submitCommentReply('${postId}','${cid}','${contextPrefix}')" class="no-accent-hover text-white bg-brand-500 hover:bg-brand-600 rounded-full px-3 py-1.5 text-xs font-bold transition-colors">Reply</button>
              </div>
            </div>
          </div>
        </div>`;
}

function toggleCommentMenu(commentId, event) {
  if (event) event.stopPropagation();
  const menu = document.getElementById(`comment-menu-${commentId}`);
  if (!menu) return;
  const willOpen = menu.classList.contains("hidden");
  document.querySelectorAll(".comment-menu").forEach((m) => m.classList.add("hidden"));
  if (willOpen) {
    menu.classList.remove("hidden");
    if (typeof lucide !== "undefined") lucide.createIcons();
  }
}

function toggleCommentReplyInput(commentId, contextPrefix = '') {
  const box = document.getElementById(`${contextPrefix}comment-reply-box-${commentId}`);
  if (!box) return;
  box.classList.toggle("hidden");
  if (!box.classList.contains("hidden")) {
    document.getElementById(`${contextPrefix}comment-reply-input-${commentId}`)?.focus();
  }
}

function handleCommentReplyKeydown(event, postId, parentId, contextPrefix = '') {
  if (event.key !== "Enter" || event.shiftKey) return;
  event.preventDefault();
  submitCommentReply(postId, parentId, contextPrefix);
}

async function submitCommentReply(postId, parentId, contextPrefix = '') {
  const input = document.getElementById(`${contextPrefix}comment-reply-input-${parentId}`);
  if (!input) return;
  const content = input.value.trim();
  if (!content || !currentUserObj?.id) return;
  const submitKey = `${postId}:${parentId}:${currentUserObj.id}:${content.toLowerCase()}`;
  if (commentSubmitInFlight.has(submitKey)) return;
  commentSubmitInFlight.add(submitKey);
  input.disabled = true;
  try {
    const data = await api("submit_comment", {
      user_id: currentUserObj.id,
      post_id: postId,
      parent_id: parentId,
      content,
    });
    if (data.status !== "success") throw new Error(data.message || "Could not add reply.");
    const post = feedPosts.find((p) => String(p.id) === String(postId));
    if (post) {
      if (!post.commentList) post.commentList = [];
      post.commentList.push(data.comment);
      post.commentList = dedupePostComments(post.commentList);
      post.comments = post.commentList.length;
    }
    input.value = "";
    updatePostCommentsDom(postId);
  } catch (err) {
    console.error(err);
    showToast(err.message || "Could not submit reply.");
  } finally {
    input.disabled = false;
    commentSubmitInFlight.delete(submitKey);
  }
}

async function toggleCommentLike(postId, commentId) {
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  const comment = findCommentInPost(post, commentId);
  if (!comment || !currentUserObj?.id) return;

  // Optimistic update, reconciled with the server response.
  const wasLiked = !!(comment.isLiked || comment.is_liked_by_me);
  const newLiked = !wasLiked;
  const base = comment.likes ?? comment.like_count ?? 0;
  const newCount = Math.max(0, base + (newLiked ? 1 : -1));
  comment.isLiked = newLiked; comment.is_liked_by_me = newLiked;
  comment.likes = newCount; comment.like_count = newCount;
  updatePostCommentsDom(postId);

  try {
    const data = await api("toggle_comment_like", { user_id: currentUserObj.id, comment_id: commentId });
    if (data.status === "success") {
      // Intentionally omitting overwrite of local state on success to prevent race conditions.
    }
  } catch (err) {
    console.warn("Comment like failed:", err);
  }
}

function startEditComment(postId, commentId) {
  document.querySelectorAll(".comment-menu").forEach((m) => m.classList.add("hidden"));
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  const comment = findCommentInPost(post, commentId);
  if (!comment) return;
  comment._editing = true;
  updatePostCommentsDom(postId);
  document.getElementById(`comment-edit-input-${commentId}`)?.focus();
}

function cancelEditComment(postId, commentId) {
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  const comment = findCommentInPost(post, commentId);
  if (!comment) return;
  comment._editing = false;
  updatePostCommentsDom(postId);
}

async function saveCommentEdit(postId, commentId) {
  const input = document.getElementById(`comment-edit-input-${commentId}`);
  if (!input) return;
  const content = input.value.trim();
  if (!content) { showToast("Comment cannot be empty."); return; }
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  const comment = findCommentInPost(post, commentId);
  if (!comment) return;

  comment.content = content;
  comment._editing = false;
  comment._edited = true;
  updatePostCommentsDom(postId);

  try {
    const data = await api("edit_comment", { user_id: currentUserObj.id, comment_id: commentId, content });
    if (data.status !== "success") throw new Error(data.message || "Could not edit comment.");
  } catch (err) {
    console.error(err);
    showToast(err.message || "Could not save edit.");
  }
}

function archiveComment(postId, commentId) {
  document.querySelectorAll(".comment-menu").forEach((m) => m.classList.add("hidden"));
  const ids = getArchivedCommentIds();
  if (!ids.includes(String(commentId))) ids.push(String(commentId));
  saveArchivedCommentIds(ids);
  updatePostCommentsDom(postId);
  showToast("Comment archived and hidden from your view.");
}

async function deleteComment(postId, commentId) {
  document.querySelectorAll(".comment-menu").forEach((m) => m.classList.add("hidden"));
  if (!(await confirmAction({ title: "Delete this comment?", message: "This cannot be undone.", confirmLabel: "Delete" }))) return;
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  if (post && Array.isArray(post.commentList)) {
    post.commentList = post.commentList.filter((c) => String(c.id) !== String(commentId));
    post.comments = Math.max(0, Number(post.comments || 0) - 1);
  }
  updatePostCommentsDom(postId);
  if (!currentUserObj?.id) return;
  try {
    const data = await api("delete_comment", { user_id: currentUserObj.id, comment_id: commentId, post_id: postId });
    if (data.status === "success" && post && typeof data.comment_count === "number") {
      post.comments = data.comment_count;
      const count = document.getElementById(`comment-count-${postId}`);
      if (count) count.textContent = String(post.comments);
    }
  } catch (err) {
    console.error(err);
    showToast(err.message || "Could not delete comment.");
  }
}

function renderCommentSkeletonHtml() {
  return Array.from({ length: 2 }).map(() => `
        <div class="flex gap-2 py-2 animate-pulse">
          <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-800"></div>
          <div class="flex-1 bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 rounded-2xl px-3 py-3 space-y-2">
            <div class="h-3 w-28 rounded bg-gray-200 dark:bg-gray-700"></div>
            <div class="h-3 w-4/5 rounded bg-gray-100 dark:bg-gray-700"></div>
          </div>
        </div>
      `).join("");
}

function updatePostCommentsDom(postId) {
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  if (!post) return;

  const countEls = document.querySelectorAll(`[data-post-id="${postId}"] [data-comment-count], #post-detail-view [data-comment-count]`);
  countEls.forEach(el => el.textContent = String(post.comments || post.commentList?.length || 0));


  const feedList = document.querySelector(`[id="comments-list-${postId}"]`);
  if (feedList) feedList.innerHTML = renderPostCommentsHtml(post, "feed-");
  
  const detailList = document.querySelector(`[id="detail-comments-list-${postId}"]`);
  if (detailList) detailList.innerHTML = renderPostCommentsHtml(post, "detail-");

  if (typeof lucide !== "undefined") lucide.createIcons();
}

async function toggleComments(postId) {
  const box = document.getElementById(`comment-box-${postId}`);
  if (!box) return;

  const post = feedPosts.find((p) => String(p.id) === String(postId));

  if (post && !post.commentsLoaded && currentUserObj?.id) {
    const list = document.getElementById(`comments-list-${postId}`);
    box.classList.remove("hidden");
    if (list) list.innerHTML = renderCommentSkeletonHtml();
    try {
      const data = await api("get_comments", { post_id: postId });
      if (data.status === "success") {
        post.commentList = dedupePostComments(data.comments || []);
        post.commentsLoaded = true;
        post.comments = post.commentList.length;
        updatePostCommentsDom(postId);
        box.classList.remove("hidden");
        return;
      }
    } catch (err) {
      console.error("Failed to load comments", err);
      if (list) {
        list.innerHTML = `<div class="text-xs font-semibold text-red-500 py-3">Could not load comments.</div>`;
      }
      showToast("Could not load comments.");
    }
    return;
  }

  box.classList.toggle("hidden");
}

function handleCommentKeydown(event, postId) {
  if (event.key !== "Enter" || event.shiftKey) return;
  event.preventDefault();
  submitComment(postId);
}

function handleDetailCommentKeydown(event, postId) {
  if (event.key !== "Enter" || event.shiftKey) return;
  event.preventDefault();
  submitComment(postId, `detail-comment-input-${postId}`);
}

async function submitComment(postId, inputId = "") {
  const input = document.getElementById(inputId || `comment-input-${postId}`);
  if (!input) return;

  const content = input.value.trim();
  if (!content) return;
  if (!currentUserObj?.id) {
    showToast("Sign in to comment.");
    return;
  }

  const submitKey = `${postId}:root:${currentUserObj.id}:${content.toLowerCase()}`;
  if (commentSubmitInFlight.has(submitKey)) return;
  commentSubmitInFlight.add(submitKey);
  input.disabled = true;

  try {
    const data = await api("submit_comment", {
      user_id: currentUserObj.id,
      post_id: postId,
      content,
    });

    if (data.status !== "success") {
      throw new Error(data.message || "Could not add comment.");
    }

    const post = feedPosts.find((p) => String(p.id) === String(postId));
    if (post) {
      if (!post.commentList) post.commentList = [];
      post.commentList.push(data.comment);
      post.commentList = dedupePostComments(post.commentList);
      post.commentsLoaded = true;
      post.comments = post.commentList.length;
    }

    input.value = "";
    updatePostCommentsDom(postId);
    const box = document.getElementById(`comment-box-${postId}`);
    if (box) box.classList.remove("hidden");
  } catch (err) {
    console.error(err);
    showToast(err.message || "Could not submit comment.");
  } finally {
    input.disabled = false;
    commentSubmitInFlight.delete(submitKey);
  }
}

function toggleMobileReactionPicker(postId) {
  const container = document.getElementById(`reaction-picker-${postId}`);
  if (container) container.classList.toggle("hidden");
}

function popLikeHeart(postId) {
  document.querySelectorAll(`[data-post-id="${postId}"]`).forEach(card => {
    const icon = card.querySelector("button[onclick*='togglePostLike'] i");
    if (icon) {
      icon.classList.remove("reaction-pop");
      void icon.offsetWidth;
      icon.classList.add("reaction-pop");
      setTimeout(() => icon.classList.remove("reaction-pop"), 420);
    }
  });
}

function snapshotPostReaction(post) {
  return {
    is_liked_by_me: post.is_liked_by_me,
    likes: post.like_count,
    reaction: post.reaction,
    viewer_reaction: post.viewer_reaction,
    reaction_summary: { ...(post.reaction_summary || {}) },
  };
}

function restorePostReaction(post, snap) {
  post.is_liked_by_me = snap.is_liked_by_me;
  post.like_count = snap.likes;
  post.reaction = snap.reaction;
  post.viewer_reaction = snap.viewer_reaction;
  post.reaction_summary = snap.reaction_summary;
}

async function reactToPost(postId, key) {
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  if (!post) return;

  const snap = snapshotPostReaction(post);
  const result = setLocalReaction(post, key);

  updatePostReactionDom(postId);
  if (result.isAdd) {
    // optional: pop reaction animation
  }
  document.querySelectorAll(".mobile-reaction-picker").forEach((m) => m.classList.add("hidden"));

  try {
    const data = await api("set_post_reaction", {
      user_id: currentUserObj.id,
      post_id: postId,
      reaction: key,
    });
    if (data.status !== "success") throw new Error(data.message || "Could not react.");

    // Intentionally omitting overwrite of local state on success to prevent race conditions.
  } catch (err) {
    restorePostReaction(post, snap);
    updatePostReactionDom(postId);
    console.error(err);
    showToast(err.message || "Could not react.", "error");
  }
}

function openPostReactions(postId) {
  const post = feedPosts.find((p) => String(p.id) === String(postId));
  const summary = (post && post.reaction_summary) || {};
  const keys = Object.keys(summary).filter((k) => summary[k] > 0).sort((a, b) => summary[b] - summary[a]);

  let overlay = document.getElementById("post-reactions-overlay");
  if (!overlay) {
    overlay = document.createElement("div");
    overlay.id = "post-reactions-overlay";
    overlay.className = "fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 flex items-center justify-center p-4";
    overlay.onclick = (e) => { if (e.target === overlay) overlay.remove(); };
    document.body.appendChild(overlay);
  }
  overlay.innerHTML = `
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-xs max-h-[70vh] overflow-hidden flex flex-col">
      <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
        <h3 class="font-bold text-gray-900 dark:text-white">Reactions</h3>
        <button onclick="document.getElementById('post-reactions-overlay').remove()" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div class="flex-1 overflow-y-auto p-4 space-y-2">
        ${keys.length ? keys.map((k) => {
          const r = resolveReaction(k);
          return `<div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
            <span class="w-5 h-5 flex items-center justify-center">${reactionGlyphHtml(k, "w-4 h-4")}</span>
            <span class="flex-1">${escapeHtml(r ? r.label : "Reaction")}</span>
            <span class="font-semibold text-gray-400">${summary[k]}</span>
          </div>`;
        }).join("") : `<p class="text-sm text-gray-400 text-center py-6">No reactions yet.</p>`}
      </div>
    </div>`;
  if (window.lucide) lucide.createIcons();
}

function copyPostLink(postId) {
  const url = window.location.origin + "?post=" + postId;
  navigator.clipboard.writeText(url).then(() => {
    if (typeof showToast === 'function') showToast("Link copied to clipboard", "success");
  }).catch(err => {
    console.error('Could not copy link', err);
  });
  document.querySelectorAll('.dropdown-menu').forEach((menu) => menu.classList.add("hidden"));
}

async function deletePost(postId) {
  const btn = (typeof event !== "undefined" && event) ? event.target.closest("button") : null;
  if (!(await confirmAction({ title: "Delete this post?", message: "This cannot be undone.", confirmLabel: "Delete" }))) return;
  setButtonLoading(btn, true);
  try {
    const data = await api("delete_post", { post_id: postId });
    if (data.status !== "success") {
      if (typeof showToast === 'function') showToast(data.message || "Could not delete post.", "error");
      return;
    }
    document.querySelectorAll(`[data-post-id="${postId}"]`).forEach(el => el.remove());
    if (typeof showToast === 'function') showToast("Post deleted.", "success");
    if (document.getElementById('view-post')?.classList.contains('active')) {
      if (typeof switchView === 'function') switchView('view-social-feed');
    }
  } catch (err) {
    if (typeof showToast === 'function') showToast("Error deleting post.", "error");
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}


// Link preview extraction & rendering
function detectAndRenderLinkPreview(postId, text, prefix = "") {
  const urlRegex = /(https?:\/\/[^\s]+)/g;
  const match = urlRegex.exec(text);
  if (!match) return "";
  
  const url = match[1];
  // Fire and forget fetch
  fetch(`api/?action=get_link_preview`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ url })
  }).then(res => res.json()).then(data => {
    if (data.status === 'success' && data.preview) {
      const p = data.preview;
      const container = document.getElementById(`preview-container-${prefix}${postId}`);
      if (container) {
        container.innerHTML = `
          <a href="${url}" target="_blank" class="block mt-2 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
            ${p.image ? `<img src="${p.image}" class="w-full h-40 object-cover border-b border-gray-200 dark:border-gray-700" onerror="this.style.display='none'">` : ''}
            <div class="p-3">
              <h4 class="font-bold text-sm text-gray-900 dark:text-gray-100 truncate">${p.title || new URL(url).hostname}</h4>
              ${p.description ? `<p class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">${p.description}</p>` : ''}
              <p class="text-[10px] text-gray-400 mt-2 uppercase tracking-wide">${new URL(url).hostname}</p>
            </div>
          </a>
        `;
      }
    }
  }).catch(err => console.error("Link preview error:", err));
  
  return "";
}


// --- Edit Post ---

let currentEditPostId = null;

function editPost(postId) {
  // Try to find the post object
  let post = null;
  if (typeof window.posts !== 'undefined') post = window.posts.find(p => p.id === postId);
  
  // If not in global feed, we might be on post-detail
  if (!post && typeof currentPost !== 'undefined' && currentPost && currentPost.id === postId) {
    post = currentPost;
  }
  
  if (!post) {
    showToast("Post not found.", "error");
    return;
  }
  
  currentEditPostId = postId;
  
  // Inject modal if it doesn't exist
  if (!document.getElementById('edit-post-modal')) {
    const modalHtml = `
      <div id="edit-post-modal" class="fixed inset-0 z-[100] hidden flex items-center justify-center p-4 bg-gray-900/40 backdrop-blur-sm sm:p-0">
        <div class="bg-white dark:bg-gray-900 w-full max-w-lg rounded-2xl shadow-xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
          <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
            <h3 class="font-bold text-gray-900 dark:text-white">Edit Post</h3>
            <button onclick="closeEditPostModal()" class="p-2 -mr-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-full hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
              <i data-lucide="x" class="w-5 h-5"></i>
            </button>
          </div>
          <div class="p-4">
            <textarea id="edit-post-content" class="w-full h-32 bg-transparent border-0 focus:ring-0 resize-none text-gray-900 dark:text-white placeholder-gray-500 text-lg p-0" placeholder="What's on your mind?"></textarea>
          </div>
          <div class="px-4 py-3 bg-gray-50 dark:bg-gray-800/50 flex items-center justify-end">
            <button id="edit-post-submit-btn" onclick="submitEditPost()" class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-semibold rounded-full shadow-sm shadow-brand-600/20 transition-all flex items-center gap-2">
              Save Changes
            </button>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    if (window.lucide) lucide.createIcons();
  }
  
  document.getElementById('edit-post-content').value = post.content || "";
  document.getElementById('edit-post-modal').classList.remove('hidden');
}

function closeEditPostModal() {
  document.getElementById('edit-post-modal').classList.add('hidden');
  currentEditPostId = null;
}

async function submitEditPost() {
  if (!currentEditPostId) return;
  const contentEl = document.getElementById("edit-post-content");
  const content = contentEl.value.trim();
  if (!content) {
    showToast("Post content cannot be empty.", "info");
    return;
  }

  const btn = document.getElementById("edit-post-submit-btn");
  setButtonLoading(btn, true, "Saving...");
  try {
    const data = await api("edit_post", { post_id: currentEditPostId, content: content });
    if (data.status !== "success") {
      showToast(data.message || "Could not update post.", "error");
      return;
    }
    closeEditPostModal();
    if (typeof loadFeedPosts === 'function' && document.getElementById('feed-list')) {
      loadFeedPosts();
    }
    if (typeof loadPostDetail === 'function' && typeof currentPost !== 'undefined' && currentPost.id === currentEditPostId) {
      loadPostDetail(currentEditPostId);
    }
    showToast("Post updated.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not update post.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}
