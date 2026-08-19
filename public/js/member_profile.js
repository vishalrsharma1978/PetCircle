// View-another-member's-profile modal. Reachable from post author
// avatars/names, friends-list cards, and group member lists. Backed by a
// privacy-aware get_profile fetch (handleGetProfile in core.php) rather
// than reusing whatever happens to already be cached client-side elsewhere.

let currentMemberProfileId = null;

async function openMemberProfile(userId) {
  if (!userId) return;
  if (currentUserObj && String(userId) === String(currentUserObj.id)) {
    switchView("view-pet-profile");
    loadPetProfileView();
    return;
  }
  currentMemberProfileId = userId;
  const modal = document.getElementById("member-profile-modal");
  if (!modal) return;
  modal.classList.remove("hidden");
  modal.classList.add("flex");

  // Instant reopen: if this profile was viewed recently, render it right
  // away (no skeleton flash) from the cached response, then still refresh
  // in the background so it stays correct. First open of a given profile
  // behaves exactly as before (skeleton, then render on fetch).
  const cached = peekApiCache("get_profile", { target_user_id: userId });
  if (cached?.status === "success" && cached.profile) {
    renderMemberProfile(cached.profile, cached.friendship);
  } else {
    renderMemberProfileLoading();
  }

  try {
    const data = await api("get_profile", { target_user_id: userId }, { forceRefresh: !!cached });
    if (currentMemberProfileId !== userId) return; // modal moved on to a different profile
    if (data.status !== "success") {
      if (!cached) {
        showToast(data.message || "Could not load profile.", "error");
        closeMemberProfileModal();
      }
      return;
    }
    renderMemberProfile(data.profile, data.friendship);
  } catch (err) {
    console.error(err);
    if (!cached) {
      showToast("Could not load profile.", "error");
      closeMemberProfileModal();
    }
  }
}

function closeMemberProfileModal() {
  currentMemberProfileId = null;
  const modal = document.getElementById("member-profile-modal");
  if (!modal) return;
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

function renderMemberProfileLoading() {
  document.getElementById("mp-pet-name").textContent = "";
  document.getElementById("mp-type-breed").textContent = "";
  document.getElementById("mp-bio").textContent = "";
  document.getElementById("mp-city").textContent = "";
  document.getElementById("mp-friend-count").textContent = "";
  document.getElementById("mp-action-wrap").innerHTML = "";
  document.getElementById("mp-tags-wrap").innerHTML = "";
  document.getElementById("mp-limited-notice").classList.add("hidden");
  document.getElementById("mp-bio-wrap").classList.remove("hidden");
  setMemberProfilePresenceDot(null);
  document.getElementById("mp-verified-badge").classList.add("hidden");

  // Shaped skeleton placeholders in, real content hidden, matching the
  // hub hero's already-established overlay-skeleton pattern.
  document.getElementById("mp-cover-skeleton")?.classList.remove("hidden");
  document.getElementById("mp-avatar-skeleton")?.classList.remove("hidden");
  document.getElementById("mp-name-skeleton")?.classList.remove("hidden");
  document.getElementById("mp-type-breed")?.classList.add("hidden");
  document.getElementById("mp-action-skeleton")?.classList.remove("hidden");
  document.getElementById("mp-action-wrap")?.classList.add("hidden");
  document.getElementById("mp-tags-skeleton")?.classList.remove("hidden");
  document.getElementById("mp-tags-wrap")?.classList.add("hidden");
  document.getElementById("mp-tags-wrap")?.classList.remove("flex");
  document.getElementById("mp-city")?.classList.add("hidden");
  document.getElementById("mp-city-skeleton")?.classList.remove("hidden");
  document.getElementById("mp-friend-count")?.classList.add("hidden");
  document.getElementById("mp-friend-count-skeleton")?.classList.remove("hidden");
  document.getElementById("mp-bio")?.classList.add("hidden");
  document.getElementById("mp-bio-skeleton")?.classList.remove("hidden");

  // Note: NOT calling setAvatarPreview()/setCoverPreview() here — with no
  // URL yet they'd immediately hide the skeleton we just showed above
  // (their "no photo" branch). The real calls happen in renderMemberProfile()
  // once actual URLs (or their absence) are known.
  document.getElementById("mp-avatar-img")?.classList.add("hidden");
  document.getElementById("mp-avatar-text")?.classList.add("hidden");
  document.getElementById("mp-cover-img")?.classList.add("hidden");
}

function setMemberProfilePresenceDot(status, dotId = "mp-presence-dot") {
  const dot = document.getElementById(dotId);
  if (!dot) return;
  dot.classList.remove("presence-online", "presence-away", "presence-offline");
  if (!status || status === "hidden") {
    dot.classList.add("hidden");
    return;
  }
  dot.classList.remove("hidden");
  dot.classList.add(`presence-${status}`);
  dot.title = PRESENCE_LABELS[status] || "";
}

function memberProfileActionButtonHtml(friendship, userId, name) {
  const status = friendship?.status || "none";
  if (status === "accepted") {
    return `<button onclick="closeMemberProfileModal(); openFriendChat('${userId}', '${escapeHtml(name)}', null)" class="w-full px-4 py-2 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600 flex items-center justify-center gap-2"><i data-lucide="message-circle" class="w-4 h-4"></i> Message</button>`;
  }
  if (status === "pending_sent") {
    return `<button disabled class="w-full px-4 py-2 rounded-lg text-sm font-bold text-gray-400 bg-gray-100 dark:bg-gray-800 cursor-not-allowed">Request Pending</button>`;
  }
  if (status === "pending_received") {
    return `<p class="text-xs text-center text-gray-500 dark:text-gray-400">They've asked to be friends — respond from the Friends tab.</p>`;
  }
  return `<button onclick="sendFriendRequest('${userId}', this)" class="w-full px-4 py-2 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Add Friend</button>`;
}

// Reveals the real name/action/tags content and hides their skeleton
// placeholders — shared by both the limited and full-profile branches below.
function revealMemberProfileChrome() {
  document.getElementById("mp-name-skeleton")?.classList.add("hidden");
  document.getElementById("mp-type-breed")?.classList.remove("hidden");
  document.getElementById("mp-action-skeleton")?.classList.add("hidden");
  document.getElementById("mp-action-wrap")?.classList.remove("hidden");
  document.getElementById("mp-tags-skeleton")?.classList.add("hidden");
  document.getElementById("mp-tags-wrap")?.classList.remove("hidden");
  document.getElementById("mp-tags-wrap")?.classList.add("flex");
}

function renderMemberProfile(profile, friendship) {
  if (!profile) return;
  document.getElementById("mp-verified-badge").classList.toggle("hidden", !profile.is_verified);
  setAvatarPreview("mp-avatar-img", "mp-avatar-text", profile.profile_photo_url, (profile.pet_name || "P")[0], "mp-avatar-skeleton");
  setMemberProfilePresenceDot(profile.presence);
  revealMemberProfileChrome();

  if (profile.is_limited) {
    document.getElementById("mp-pet-name").textContent = profile.pet_name || "Member";
    document.getElementById("mp-type-breed").textContent = profile.pet_type || "";
    document.getElementById("mp-action-wrap").innerHTML = memberProfileActionButtonHtml(friendship, profile.user_id, profile.pet_name || "Member");
    document.getElementById("mp-bio-wrap").classList.add("hidden");
    document.getElementById("mp-city-skeleton")?.classList.add("hidden");
    const cityEl = document.getElementById("mp-city");
    if (cityEl) { cityEl.textContent = "—"; cityEl.classList.remove("hidden"); }
    document.getElementById("mp-friend-count-skeleton")?.classList.add("hidden");
    const friendCountEl = document.getElementById("mp-friend-count");
    if (friendCountEl) { friendCountEl.textContent = "—"; friendCountEl.classList.remove("hidden"); }
    const notice = document.getElementById("mp-limited-notice");
    notice.textContent = "This pet parent's profile is private.";
    notice.classList.remove("hidden");
    if (window.lucide) lucide.createIcons();
    return;
  }

  setCoverPreview("mp-cover-img", profile.cover_photo_url, "mp-cover-skeleton");
  document.getElementById("mp-pet-name").textContent = profile.pet_name || "Member";
  document.getElementById("mp-type-breed").textContent = [profile.pet_type, profile.breed].filter(Boolean).join(" · ");
  document.getElementById("mp-city-skeleton")?.classList.add("hidden");
  document.getElementById("mp-city").classList.remove("hidden");
  document.getElementById("mp-city").textContent = profile.current_city || "—";
  document.getElementById("mp-bio-skeleton")?.classList.add("hidden");
  document.getElementById("mp-bio").classList.remove("hidden");
  document.getElementById("mp-bio").textContent = profile.bio || "No bio yet.";
  document.getElementById("mp-action-wrap").innerHTML = memberProfileActionButtonHtml(friendship, profile.user_id, profile.pet_name || "Member");

  const tags = [profile.pet_type, profile.breed].filter(Boolean);
  document.getElementById("mp-tags-wrap").innerHTML = tags
    .map((t) => `<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300">${escapeHtml(t)}</span>`)
    .join("");

  loadMemberProfileFriendCount(profile.user_id);
  if (window.lucide) lucide.createIcons();
}

async function loadMemberProfileFriendCount(userId) {
  try {
    const data = await api("get_friends", { target_user_id: userId });
    const el = document.getElementById("mp-friend-count");
    const skeleton = document.getElementById("mp-friend-count-skeleton");
    skeleton?.classList.add("hidden");
    if (el) {
      el.classList.remove("hidden");
      el.textContent = data.status === "success" ? String((data.friends || []).length) : "—";
    }
  } catch (err) {
    console.error(err);
    document.getElementById("mp-friend-count-skeleton")?.classList.add("hidden");
    const el = document.getElementById("mp-friend-count");
    if (el) {
      el.classList.remove("hidden");
      el.textContent = "—";
    }
  }
}

// ---------------- Dedicated full-page view (posts, bio, info) ----------------
// Reached via the quick-view modal's "View full profile" button. Same
// get_profile backend/privacy handling as the modal, plus a paginated
// get_user_posts fetch the modal never needed.

let currentMemberProfilePageId = null;
const MEMBER_PROFILE_PAGE_POSTS_PAGE_SIZE = 10;
let memberProfilePagePostsOffset = 0;
let memberProfilePageHasMorePosts = false;

async function openMemberProfilePage(userId) {
  if (!userId) return;
  if (currentUserObj && String(userId) === String(currentUserObj.id)) {
    switchView("view-pet-profile");
    loadPetProfileView();
    return;
  }
  currentMemberProfilePageId = userId;
  memberProfilePagePostsOffset = 0;
  memberProfilePageHasMorePosts = false;
  switchView("view-member-profile");
  renderMemberProfilePageLoading();

  // Instant reopen for the profile chrome (name/bio/tags/etc.) if cached;
  // the posts list still shows its own skeleton and loads normally below,
  // since post history is more likely to have changed since the last visit.
  const cachedProfile = peekApiCache("get_profile", { target_user_id: userId });
  if (cachedProfile?.status === "success" && cachedProfile.profile) {
    renderMemberProfilePage(cachedProfile.profile, cachedProfile.friendship);
  }

  try {
    const [profileData, postsData] = await Promise.all([
      api("get_profile", { target_user_id: userId }, { forceRefresh: !!cachedProfile }),
      api("get_user_posts", { target_user_id: userId, limit: MEMBER_PROFILE_PAGE_POSTS_PAGE_SIZE }),
    ]);
    if (currentMemberProfilePageId !== userId) return; // page moved on to a different profile
    if (profileData.status !== "success") {
      if (!cachedProfile) {
        showToast(profileData.message || "Could not load profile.", "error");
        switchView("view-social-feed");
      }
      return;
    }
    renderMemberProfilePage(profileData.profile, profileData.friendship);
    if (!profileData.profile?.is_limited) {
      const posts = postsData.status === "success" ? (postsData.posts || []) : [];
      memberProfilePagePostsOffset = posts.length;
      memberProfilePageHasMorePosts = posts.length === MEMBER_PROFILE_PAGE_POSTS_PAGE_SIZE;
      renderMemberProfilePagePosts(posts, false);
    } else {
      document.getElementById("vmp-posts-wrap")?.classList.add("hidden");
    }
  } catch (err) {
    console.error(err);
    showToast("Could not load profile.", "error");
    switchView("view-social-feed");
  }
}

function renderMemberProfilePageLoading() {
  document.getElementById("vmp-pet-name").textContent = "";
  document.getElementById("vmp-handle").textContent = "";
  document.getElementById("vmp-type-breed").textContent = "";
  document.getElementById("vmp-bio").textContent = "";
  document.getElementById("vmp-city").textContent = "";
  document.getElementById("vmp-friend-count").textContent = "";
  document.getElementById("vmp-action-wrap").innerHTML = "";
  document.getElementById("vmp-tags-wrap").innerHTML = "";
  document.getElementById("vmp-limited-notice").classList.add("hidden");
  document.getElementById("vmp-bio-wrap").classList.remove("hidden");
  document.getElementById("vmp-posts-wrap").classList.remove("hidden");
  document.getElementById("vmp-posts-load-more-wrap").classList.add("hidden");
  setMemberProfilePresenceDot(null, "vmp-presence-dot");
  document.getElementById("vmp-verified-badge").classList.add("hidden");

  document.getElementById("vmp-cover-skeleton")?.classList.remove("hidden");
  document.getElementById("vmp-avatar-skeleton")?.classList.remove("hidden");
  document.getElementById("vmp-name-skeleton")?.classList.remove("hidden");
  document.getElementById("vmp-type-breed")?.classList.add("hidden");
  document.getElementById("vmp-action-skeleton")?.classList.remove("hidden");
  document.getElementById("vmp-action-wrap")?.classList.add("hidden");
  document.getElementById("vmp-tags-skeleton")?.classList.remove("hidden");
  document.getElementById("vmp-tags-wrap")?.classList.add("hidden");
  document.getElementById("vmp-tags-wrap")?.classList.remove("flex");
  document.getElementById("vmp-city")?.classList.add("hidden");
  document.getElementById("vmp-city-skeleton")?.classList.remove("hidden");
  document.getElementById("vmp-friend-count")?.classList.add("hidden");
  document.getElementById("vmp-friend-count-skeleton")?.classList.remove("hidden");
  document.getElementById("vmp-bio")?.classList.add("hidden");
  document.getElementById("vmp-bio-skeleton")?.classList.remove("hidden");

  document.getElementById("vmp-avatar-img")?.classList.add("hidden");
  document.getElementById("vmp-avatar-text")?.classList.add("hidden");
  document.getElementById("vmp-cover-img")?.classList.add("hidden");

  document.getElementById("vmp-posts-list").innerHTML = postCardSkeletonListHtml(3);
}

function renderMemberProfilePage(profile, friendship) {
  if (!profile) return;
  document.getElementById("vmp-verified-badge").classList.toggle("hidden", !profile.is_verified);
  document.getElementById("vmp-handle").textContent = profile.handle ? `@${profile.handle}` : "";
  setAvatarPreview("vmp-avatar-img", "vmp-avatar-text", profile.profile_photo_url, (profile.pet_name || "P")[0], "vmp-avatar-skeleton");
  setMemberProfilePresenceDot(profile.presence, "vmp-presence-dot");

  document.getElementById("vmp-name-skeleton")?.classList.add("hidden");
  document.getElementById("vmp-type-breed")?.classList.remove("hidden");
  document.getElementById("vmp-action-skeleton")?.classList.add("hidden");
  document.getElementById("vmp-action-wrap")?.classList.remove("hidden");
  document.getElementById("vmp-tags-skeleton")?.classList.add("hidden");
  document.getElementById("vmp-tags-wrap")?.classList.remove("hidden");
  document.getElementById("vmp-tags-wrap")?.classList.add("flex");

  if (profile.is_limited) {
    document.getElementById("vmp-pet-name").textContent = profile.pet_name || "Member";
    document.getElementById("vmp-type-breed").textContent = profile.pet_type || "";
    document.getElementById("vmp-action-wrap").innerHTML = memberProfileActionButtonHtml(friendship, profile.user_id, profile.pet_name || "Member");
    document.getElementById("vmp-bio-wrap").classList.add("hidden");
    document.getElementById("vmp-city-skeleton")?.classList.add("hidden");
    const cityEl = document.getElementById("vmp-city");
    if (cityEl) { cityEl.textContent = "—"; cityEl.classList.remove("hidden"); }
    document.getElementById("vmp-friend-count-skeleton")?.classList.add("hidden");
    const friendCountEl = document.getElementById("vmp-friend-count");
    if (friendCountEl) { friendCountEl.textContent = "—"; friendCountEl.classList.remove("hidden"); }
    const notice = document.getElementById("vmp-limited-notice");
    notice.textContent = "This pet parent's profile is private.";
    notice.classList.remove("hidden");
    if (window.lucide) lucide.createIcons();
    return;
  }

  setCoverPreview("vmp-cover-img", profile.cover_photo_url, "vmp-cover-skeleton");
  document.getElementById("vmp-pet-name").textContent = profile.pet_name || "Member";
  document.getElementById("vmp-type-breed").textContent = [profile.pet_type, profile.breed].filter(Boolean).join(" · ");
  document.getElementById("vmp-city-skeleton")?.classList.add("hidden");
  document.getElementById("vmp-city").classList.remove("hidden");
  document.getElementById("vmp-city").textContent = profile.current_city || "—";
  document.getElementById("vmp-bio-skeleton")?.classList.add("hidden");
  document.getElementById("vmp-bio").classList.remove("hidden");
  document.getElementById("vmp-bio").textContent = profile.bio || "No bio yet.";
  document.getElementById("vmp-action-wrap").innerHTML = memberProfileActionButtonHtml(friendship, profile.user_id, profile.pet_name || "Member");

  const tags = [profile.pet_type, profile.breed].filter(Boolean);
  document.getElementById("vmp-tags-wrap").innerHTML = tags
    .map((t) => `<span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300">${escapeHtml(t)}</span>`)
    .join("");

  loadMemberProfileFriendCountFor("vmp-friend-count", "vmp-friend-count-skeleton", profile.user_id);
  if (window.lucide) lucide.createIcons();
}

async function loadMemberProfileFriendCountFor(elId, skeletonId, userId) {
  try {
    const data = await api("get_friends", { target_user_id: userId });
    const el = document.getElementById(elId);
    document.getElementById(skeletonId)?.classList.add("hidden");
    if (el) {
      el.classList.remove("hidden");
      el.textContent = data.status === "success" ? String((data.friends || []).length) : "—";
    }
  } catch (err) {
    console.error(err);
    document.getElementById(skeletonId)?.classList.add("hidden");
    const el = document.getElementById(elId);
    if (el) {
      el.classList.remove("hidden");
      el.textContent = "—";
    }
  }
}

function renderMemberProfilePagePosts(posts, append) {
  const list = document.getElementById("vmp-posts-list");
  if (!list) return;
  const html = posts.length
    ? posts.map((p, i) => postCardHtml(p, i)).join("")
    : "";
  if (append) {
    list.insertAdjacentHTML("beforeend", html);
  } else {
    list.innerHTML = html || `<p class="text-sm text-gray-400 text-center py-6 warm-glass rounded-2xl">No posts yet.</p>`;
  }
  document.getElementById("vmp-posts-load-more-wrap")?.classList.toggle("hidden", !memberProfilePageHasMorePosts);
  if (window.lucide) lucide.createIcons();
}

async function loadMoreMemberProfilePagePosts() {
  if (!currentMemberProfilePageId) return;
  const btn = document.getElementById("vmp-posts-load-more-btn");
  setButtonLoading(btn, true, "Loading…");
  try {
    const data = await api("get_user_posts", {
      target_user_id: currentMemberProfilePageId,
      limit: MEMBER_PROFILE_PAGE_POSTS_PAGE_SIZE,
      offset: memberProfilePagePostsOffset,
    });
    if (data.status !== "success") {
      showToast(data.message || "Could not load more posts.", "error");
      return;
    }
    const posts = data.posts || [];
    memberProfilePagePostsOffset += posts.length;
    memberProfilePageHasMorePosts = posts.length === MEMBER_PROFILE_PAGE_POSTS_PAGE_SIZE;
    renderMemberProfilePagePosts(posts, true);
  } catch (err) {
    console.error(err);
    showToast("Could not load more posts.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}
