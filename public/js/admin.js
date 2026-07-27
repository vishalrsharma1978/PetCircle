

    const userProfilePackCache = {};



    function normalizeProfileRelation(value) {

      return String(value || "").trim().toLowerCase();

    }



    function userProfileMiniNode(member, fallbackName, relationLabel, colorClass = "bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200") {

      const name = member?.name || fallbackName || "Member";

      return `

        <div class="text-center min-w-[4.5rem] max-w-[6.5rem]">

          <div class="w-11 h-11 ${colorClass} rounded-full mx-auto flex items-center justify-center font-bold text-xs shadow-sm ring-2 ring-white dark:ring-gray-900">

            ${escapeHtml(getSocialAvatar(name))}

          </div>

          <div class="mt-1 text-[11px] font-bold text-gray-800 dark:text-gray-200 truncate">${escapeHtml(name)}</div>

          <div class="text-[10px] text-gray-500 dark:text-gray-400 font-medium truncate">${escapeHtml(relationLabel || member?.relation || "")}</div>

        </div>`;

    }



    function renderUserProfilePackTree(name, members = [], options = {}) {

      const container = document.getElementById("upm-pack-tree-container");

      if (!container) return;



      if (options.loading) {

        container.innerHTML = `

          <div class="h-40 flex flex-col items-center justify-center text-sm text-gray-500 dark:text-gray-400">

            <i data-lucide="loader-2" class="w-5 h-5 animate-spin mb-2"></i>

            Loading pack tree...

          </div>`;

        if (typeof lucide !== "undefined") lucide.createIcons();

        return;

      }



      if (options.error) {

        container.innerHTML = `

          <div class="h-40 flex flex-col items-center justify-center text-center text-sm text-gray-500 dark:text-gray-400 px-4">

            <i data-lucide="network" class="w-7 h-7 text-gray-300 mb-2"></i>

            <p class="font-bold text-gray-800 dark:text-gray-200">Pack tree unavailable</p>

            <p class="mt-1">${escapeHtml(options.error)}</p>

          </div>`;

        if (typeof lucide !== "undefined") lucide.createIcons();

        return;

      }



      const list = Array.isArray(members) ? members : [];

      const byRelation = (relation) => list.filter((m) => normalizeProfileRelation(m.relation) === relation);

      const fathers = byRelation("father");

      const mothers = byRelation("mother");

      const spouses = byRelation("spouse");

      const siblings = list.filter((m) => ["brother", "sister"].includes(normalizeProfileRelation(m.relation)));

      const children = list.filter((m) => ["son", "daughter"].includes(normalizeProfileRelation(m.relation)));

      const hasAnyPack = fathers.length || mothers.length || spouses.length || siblings.length || children.length;



      if (!hasAnyPack) {

        container.innerHTML = `

          <div class="h-40 flex flex-col items-center justify-center text-center text-sm text-gray-500 dark:text-gray-400 px-4">

            <i data-lucide="users-round" class="w-8 h-8 text-gray-300 mb-2"></i>

            <p class="font-bold text-gray-800 dark:text-gray-200">No pack tree shared yet</p>

            <p class="mt-1">${escapeHtml(name || "This member")} has not added pack members.</p>

          </div>`;

        if (typeof lucide !== "undefined") lucide.createIcons();

        return;

      }



      const parentsHtml = [...fathers, ...mothers].map((member) => {

        const relation = normalizeProfileRelation(member.relation);

        const colors = relation === "mother"

          ? "bg-pink-100 text-pink-700 dark:bg-pink-900/50 dark:text-pink-200"

          : "bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-200";

        return userProfileMiniNode(member, null, member.relation, colors);

      }).join("");

      const spousesHtml = spouses.map((member) => userProfileMiniNode(member, null, member.relation, "bg-rose-100 text-rose-700 dark:bg-rose-900/50 dark:text-rose-200")).join("");

      const siblingsHtml = siblings.map((member) => userProfileMiniNode(member, null, member.relation)).join("");

      const childrenHtml = children.map((member) => userProfileMiniNode(member, null, member.relation, "bg-emerald-100 text-emerald-700 dark:bg-emerald-900/50 dark:text-emerald-200")).join("");

      const selfHtml = userProfileMiniNode(null, name || "This user", "This User", "bg-white text-gray-900 dark:bg-gray-700 dark:text-white border-2 border-brand-500");



      container.innerHTML = `

        <div class="space-y-4">

          ${parentsHtml ? `<div class="flex items-start justify-center gap-6 flex-wrap">${parentsHtml}</div>` : ""}

          ${parentsHtml ? `<div class="mx-auto h-6 w-px bg-gray-300 dark:bg-gray-600"></div>` : ""}

          <div class="flex items-start justify-center gap-5 flex-wrap">

            ${siblingsHtml ? `<div class="flex items-start justify-center gap-3 flex-wrap">${siblingsHtml}</div>` : ""}

            <div class="flex flex-col items-center">

              <!-- Couple row: self + spouse avatars sit on the same level, joined by a line + heart -->

              <div class="flex items-start justify-center gap-1.5">

                ${selfHtml}

                ${spousesHtml ? `

                  <div class="flex items-center gap-1 h-11 shrink-0">

                    <span class="h-px w-4 bg-gray-300 dark:bg-gray-600"></span>

                    <i data-lucide="heart" class="w-4 h-4 text-pink-400 shrink-0"></i>

                    <span class="h-px w-4 bg-gray-300 dark:bg-gray-600"></span>

                  </div>

                  ${spousesHtml}` : ""}

              </div>

              ${childrenHtml ? `<div class="h-5 w-px bg-gray-300 dark:bg-gray-600"></div>` : ""}

              ${childrenHtml ? `<div class="flex items-start justify-center gap-4 flex-wrap pt-1">${childrenHtml}</div>` : ""}

            </div>

          </div>

        </div>`;

      if (typeof lucide !== "undefined") lucide.createIcons();

    }



    async function loadUserProfilePackTree(userId, name) {

      if (!userId) {

        renderUserProfilePackTree(name, [], { error: "No profile id was found for this member." });

        return;

      }

      const cacheKey = String(userId);

      if (userProfilePackCache[cacheKey]) {

        renderUserProfilePackTree(name, userProfilePackCache[cacheKey]);

        return;

      }

      renderUserProfilePackTree(name, [], { loading: true });

      try {

        const data = await api("get_pet_pack_members", { target_user_id: userId });

        if (data.status !== "success") throw new Error(data.message || "Could not load pack tree.");

        const members = Array.isArray(data.pack_members) ? data.pack_members : [];

        userProfilePackCache[cacheKey] = members;

        renderUserProfilePackTree(name, members);

      } catch (err) {

        console.warn("Could not load viewed profile pack tree:", err);

        renderUserProfilePackTree(name, [], { error: err.message || "Try again later." });

      }

    }



    function updateUserProfileActionState(userId, name) {

      const btn = document.getElementById("upm-friend-status-btn");

      const optionsContainer = document.getElementById("upm-options-container");

      if (!btn) return;

      const safeName = String(name || "Member").replace(/'/g, "\\'");

      const safeUserId = String(userId || "").replace(/'/g, "\\'");

      const isSelf = currentUserObj?.id && String(currentUserObj.id) === String(userId);

      const isFriend = userId && typeof findFriendById === "function" && Boolean(findFriendById(userId));

      const pending = (globalFriendSearchResults || []).find((m) => String(m.user_id) === String(userId) && m.relationship_status === "pending");



      btn.className = "no-faith-hover flex-1 py-2 rounded-xl text-base font-bold border shadow-lg transition-all flex items-center justify-center gap-2";

      if (optionsContainer) {

        optionsContainer.classList.add("hidden");

        optionsContainer.classList.remove("block");

      }



      if (isSelf) {

        btn.className += " bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-gray-700 shadow-gray-500/10";

        btn.innerHTML = `<i data-lucide="user" class="w-4 h-4"></i> Your Profile`;

        btn.onclick = () => closeUserProfile();

      } else if (isFriend) {

        btn.className += " bg-emerald-600 text-white border-emerald-600 shadow-emerald-500/20";

        btn.innerHTML = `<i data-lucide="message-circle" class="w-4 h-4"></i> Message Friend`;

        btn.onclick = () => {

          closeUserProfile();

          openFriendChat(userId);

        };

        if (optionsContainer) {

          optionsContainer.classList.remove("hidden");

          optionsContainer.classList.add("block");

          let pinnedIds = JSON.parse(localStorage.getItem('pawcircle_pinned_friends') || '[]');

          const isPinned = pinnedIds.includes(String(userId));

          document.getElementById('upm-pin-text').textContent = isPinned ? 'Unpin Friend' : 'Pin Friend';

        }

      } else if (pending) {

        btn.className += " bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200 border-amber-200 dark:border-amber-800 shadow-amber-500/10";

        btn.innerHTML = `<i data-lucide="clock-3" class="w-4 h-4"></i> Request Pending`;

        btn.onclick = null;

      } else {

        btn.className += " bg-brand-500 text-white border-brand-500 shadow-brand-500/20";

        btn.innerHTML = `<i data-lucide="user-plus" class="w-4 h-4"></i> Add Friend`;

        btn.onclick = () => userId ? sendFriendRequestToMember(safeUserId) : showToast(`${safeName} could not be added right now.`);

      }

      if (typeof lucide !== "undefined") lucide.createIcons();

    }



    function resolveUserProfileTagSource(userId, name) {

      const id = String(userId || "");

      const pools = [

        currentUserObj,

        ...(typeof globalFriends !== "undefined" ? globalFriends : []),

        ...(typeof globalFriendRequests !== "undefined" ? globalFriendRequests : []),

        ...(typeof globalFriendSearchResults !== "undefined" ? globalFriendSearchResults : []),

        ...(typeof feedPosts !== "undefined" ? feedPosts : []),

      ].filter(Boolean);



      if (id) {

        const foundById = pools.find((item) =>

          String(item.id || item.user_id || item.sender_id || "") === id

        );

        if (foundById) return foundById;

      }



      const loweredName = String(name || "").trim().toLowerCase();

      if (loweredName) {

        const foundByName = pools.find((item) =>

          String(item.name || item.author || item.sender || "").trim().toLowerCase() === loweredName

        );

        if (foundByName) return foundByName;

      }



      return { name, tags: [] };

    }



    function renderUserProfileTags(userId, name) {

      const box = document.getElementById("upm-profile-tags");

      if (!box) return;

      const source = resolveUserProfileTagSource(userId, name);

      box.innerHTML = renderProfileTagPills(source, { limit: 8, wrapperClass: "justify-center" });

      if (typeof lucide !== "undefined") lucide.createIcons();

    }



    function openUserProfile(name, role = "Pet Breed Member", userId = null, photoUrl = null) {

      const modal = document.getElementById("user-profile-modal");

      document.getElementById("upm-name").textContent = name;

      document.getElementById("upm-role").textContent = role;

      renderUserProfileTags(userId, name);

      if (userId) modal.dataset.userId = userId;

      else delete modal.dataset.userId;

      updateUserProfileActionState(userId, name);

      loadUserProfilePackTree(userId, name);



      // Show profile photo if available, otherwise show initials

      const avatarImg = document.getElementById("upm-avatar-img");

      const avatarDiv = document.getElementById("upm-avatar");



      // Try to resolve photo from multiple sources

      let resolvedPhoto = photoUrl || null;

      if (!resolvedPhoto && userId) {

        // Check globalFriends for a photo

        const friend = (typeof globalFriends !== 'undefined' ? globalFriends : []).find(f => String(f.id) === String(userId) || String(f.user_id) === String(userId));

        if (friend && friend.photo) resolvedPhoto = friend.photo;

        // Check feedPosts for profile photo

        if (!resolvedPhoto) {

          const post = (typeof feedPosts !== 'undefined' ? feedPosts : []).find(p => String(p.user_id) === String(userId));

          if (post && post.profilePhoto) resolvedPhoto = post.profilePhoto;

        }

      }

      // Also try to find by name if no userId match

      if (!resolvedPhoto && name) {

        const postByName = (typeof feedPosts !== 'undefined' ? feedPosts : []).find(p => p.author === name && p.profilePhoto);

        if (postByName) resolvedPhoto = postByName.profilePhoto;

        if (!resolvedPhoto) {

          const friendByName = (typeof globalFriends !== 'undefined' ? globalFriends : []).find(f => f.name === name && f.photo);

          if (friendByName) resolvedPhoto = friendByName.photo;

        }

      }



      if (resolvedPhoto) {

        avatarImg.src = resolvedPhoto;

        avatarImg.classList.remove("hidden");

        avatarDiv.classList.add("hidden");

      } else {

        avatarImg.classList.add("hidden");

        avatarImg.src = "";

        avatarDiv.classList.remove("hidden");

        avatarDiv.textContent = name.charAt(0).toUpperCase();

      }



      modal.classList.remove("hidden");

      modal.classList.add("flex");

      document.body.style.overflow = "hidden";



      // Apply pet_type-aware solid color to the header cover

      const headerCover = document.getElementById("upm-header-cover");

      if (headerCover) {

        // Without fetching the profile, we don't know the remote user's pet type here.

        // We will just use the fallback solid color instead of the buggy gradient.

        headerCover.style.background = getPetTypeSolidColor("other");

      }

      if (typeof lucide !== 'undefined') lucide.createIcons();

    }



    function closeUserProfile() {

      const modal = document.getElementById("user-profile-modal");

      modal.classList.add("hidden");

      modal.classList.remove("flex");

      document.body.style.overflow = "";

    }



    function introduceMeTo(name, friendId, btnElem = null) {

      if (!friendId) {

        showToast("Friend ID not found.");

        return;

      }



      if (btnElem) {

        if (btnElem.disabled) return;

        btnElem.disabled = true;

        const oldContent = btnElem.innerHTML;

        btnElem.innerHTML = `<i data-lucide="check" class="w-4 h-4"></i>`;

        if (typeof lucide !== "undefined") lucide.createIcons();

        setTimeout(() => {

          if (btnElem) {

            btnElem.disabled = false;

            btnElem.innerHTML = oldContent;

            if (typeof lucide !== "undefined") lucide.createIcons();

          }

        }, 2000);

      }



      showToast(

        name

          ? `Introduction request sent to ${name}!`

          : "Introduction request sent!"

      );



      const introMessage = `[introduction_request]Hi ${name || "there"}, can you introduce me to some of your friends?`;

      if (typeof sendDirectMessage === 'function') {

        sendDirectMessage(friendId, introMessage, "introduction_request");

      }

    }



    function showForwardProfileModal(requesterId) {

      const modal = document.getElementById("forward-profile-modal");

      modal.dataset.requesterId = requesterId;



      const list = document.getElementById("forward-friends-list");

      list.innerHTML = "";



      const requester = findFriendById(requesterId);

      document.getElementById("forward-requester-name").textContent = requester ? (requester.name || "Member") : "Member";



      if (globalFriends.length === 0) {

        list.innerHTML = `<p class="text-sm text-gray-500">You don't have any friends to forward to.</p>`;

      } else {

        globalFriends.forEach(f => {

          if (String(f.id) !== String(requesterId) && String(f.user_id) !== String(requesterId)) {

            list.innerHTML += `

              <label class="flex items-center justify-between p-3 border border-gray-100 dark:border-gray-800 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer mb-2 transition-colors">

                <div class="flex items-center gap-3">

                  <div class="w-8 h-8 rounded-full bg-brand-100 dark:bg-brand-900/30 text-brand-600 flex items-center justify-center font-bold text-xs border border-brand-200/50">${getSocialAvatar(f.name || "M")}</div>

                  <div>

                    <div class="font-bold text-sm text-gray-800 dark:text-gray-200">${escapeHtml(f.name || "Member")}</div>

                  </div>

                </div>

                <input type="checkbox" value="${f.id || f.user_id}" data-name="${escapeHtml(f.name || "Member")}" class="forward-friend-checkbox w-4 h-4 text-brand-600 bg-gray-100 border-gray-300 rounded focus:ring-brand-500 dark:focus:ring-brand-600 dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600">

              </label>

            `;

          }

        });

      }



      if (typeof lucide !== 'undefined') lucide.createIcons();

      modal.classList.remove("hidden");

      modal.classList.add("flex");

    }



    function closeForwardProfileModal() {

      const modal = document.getElementById("forward-profile-modal");

      modal.classList.add("hidden");

      modal.classList.remove("flex");

    }



    function submitForwardProfile() {

      const modal = document.getElementById("forward-profile-modal");

      const requesterId = modal.dataset.requesterId;

      const requester = findFriendById(requesterId);

      if (!requester) return;



      const checkboxes = document.querySelectorAll(".forward-friend-checkbox:checked");

      if (checkboxes.length === 0) {

        showToast("Please select at least one friend.");

        return;

      }



      const selectedNames = [];

      checkboxes.forEach(cb => {

        selectedNames.push(cb.dataset.name);

        const friendId = cb.value;

        const profileMsg = `I want to introduce you to ${requester.name || "a member"}.`;

        sendDirectMessage(friendId, profileMsg);

      });



      const summaryText = `[profile_forward:${selectedNames.join(", ")}]I have introduced you to some of my friends!`;

      sendDirectMessage(requesterId, summaryText);



      closeForwardProfileModal();

      showToast("Profile forwarded successfully!");

    }



    const SUVICHAR_QUOTES = [

      "“The mind is everything. What you think you become.” — Buddha",

      "“In a gentle way, you can shake the world.” — Mahatma Gandhi",

      "“Arise, awake, and stop not till the goal is reached.” — Swami Vivekananda",

      "“Where there is love there is life.” — Mahatma Gandhi",

      "“The best way to find yourself is to lose yourself in the service of others.” — Mahatma Gandhi",

      "“Happiness is when what you think, what you say, and what you do are in harmony.” — Mahatma Gandhi",

      "सत्यमेव जयते — Truth alone triumphs.",

      "“A noble heart offers kindness, even to those who cannot return it.”",

    ];



    function sendSuvichar() {

      const quote = SUVICHAR_QUOTES[Math.floor(Math.random() * SUVICHAR_QUOTES.length)];

      const author =

        currentUserObj?.name || currentUserObj?.full_name || "You";

      const suvichar = {

        id: "suvichar_" + Date.now(),

        user_id: currentUserObj?.id || "me",

        author,

        initials: getSocialAvatar(author),

        time: "Just now",

        content: quote,

        media_url: null,

        post_type: "text",

        breed: currentUserObj?.breed || null,

        pet_type: currentUserObj?.pet_type || null,

        audience: "Suvichar · Daily Inspiration",

        specialBlock: `

          <div class="bg-gradient-to-br from-amber-100 to-orange-50 dark:from-amber-900/30 dark:to-gray-900 rounded-xl p-5 mt-3 border border-amber-200 dark:border-amber-900/40 flex items-start gap-3">

            <i data-lucide="sun" class="w-6 h-6 text-amber-500 shrink-0"></i>

            <p class="text-sm font-semibold text-amber-900 dark:text-amber-100 italic leading-relaxed">Suvichar of the day, shared with your breed.</p>

          </div>`,

        likes: 0,

        comments: 0,

        isLiked: false,

        commentList: [],

        commentsLoaded: true,

        avatarClass: "bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-100",

        profilePhoto:

          currentUserObj?.profilePhoto ||

          currentUserObj?.profile_photo_url ||

          null,

      };



      feedPosts.unshift(suvichar);



      // Reset to "All Posts" so the new suvichar is always visible, syncing the filter row UI.

      activeFeedFilter = "All Posts";

      const allBtn = document.querySelector("#feed-filters .feed-filter-btn");

      if (allBtn) applyFilterButtonStyles(allBtn);



      renderFeed();

      showToast("Suvichar shared to your feed!");

    }



    function applyFilterButtonStyles(activeBtn) {

      const accent = getComputedStyle(document.documentElement).getPropertyValue('--faith-accent').trim() || '#f97316';

      const buttons = document.querySelectorAll("#feed-filters .feed-filter-btn");

      buttons.forEach((btn) => {

        btn.className =

          "feed-filter-btn px-6 py-2 rounded-full bg-white text-base font-semibold shadow-sm whitespace-nowrap transition-all border-2";

        if (btn.textContent.trim() === "Announcements") {

          btn.className += " text-gray-800 border-black hover:bg-black hover:text-white";

        } else {

          btn.className += " text-gray-600 border-gray-200";

          btn.style.removeProperty('--hover-accent');

        }

      });

      if (activeBtn) {

        activeBtn.className =

          "feed-filter-btn px-6 py-2 rounded-full text-white text-base font-bold whitespace-nowrap shadow-md transition-colors border-2";

        activeBtn.style.backgroundColor = accent;

        activeBtn.style.borderColor = accent;

      }

    }



    function setActiveFilter(clickedBtn, filterName) {

      activeFeedFilter = filterName;

      applyFilterButtonStyles(clickedBtn);

      renderFeed();

      showToast("Filtered to: " + filterName);

    }



    function toggleUserProfileMenu(evt) {

      if (evt) evt.stopPropagation();

      const menu = document.getElementById("upm-options-menu");

      if (menu.classList.contains("hidden")) {

        menu.classList.remove("hidden");

        const outsideClickListener = (e) => {

          if (!menu.contains(e.target)) {

            menu.classList.add("hidden");

            document.removeEventListener('click', outsideClickListener);

          }

        };

        setTimeout(() => document.addEventListener('click', outsideClickListener), 0);

      } else {

        menu.classList.add("hidden");

      }

    }



    function handlePinFriendClick(evt) {

      if (evt) evt.stopPropagation();

      const userId = document.getElementById('user-profile-modal').dataset.userId;

      if (!userId) return;

      let pinnedIds = JSON.parse(localStorage.getItem('pawcircle_pinned_friends') || '[]');

      const strId = String(userId);

      if (pinnedIds.includes(strId)) {

        pinnedIds = pinnedIds.filter(pid => pid !== strId);

        showToast("Friend unpinned.");

        document.getElementById('upm-pin-text').textContent = 'Pin Friend';

      } else {

        pinnedIds.push(strId);

        showToast("Friend pinned.");

        document.getElementById('upm-pin-text').textContent = 'Unpin Friend';

      }

      localStorage.setItem('pawcircle_pinned_friends', JSON.stringify(pinnedIds));

      if (typeof renderFriends === "function") renderFriends();

      document.getElementById("upm-options-menu").classList.add("hidden");

    }



    function handleRemoveFriendClick(evt) {

      if (evt) evt.stopPropagation();

      document.getElementById("upm-options-menu").classList.add("hidden");

      const userId = document.getElementById('user-profile-modal').dataset.userId;

      const name = document.getElementById('upm-name').textContent;

      if (userId) {

        document.getElementById('rfc-name').textContent = name || "this friend";

        document.getElementById('rfc-confirm-btn').onclick = () => {

          closeRemoveFriendConfirm();

          removeFriend(userId);

        };

        document.getElementById('remove-friend-confirm-modal').classList.remove('hidden');

        document.getElementById('remove-friend-confirm-modal').classList.add('flex');

      }

    }



    function closeRemoveFriendConfirm() {

      document.getElementById('remove-friend-confirm-modal').classList.add('hidden');

      document.getElementById('remove-friend-confirm-modal').classList.remove('flex');

    }



    /* ================================================================

       MODULE 1 – PERSONALIZED DASHBOARD & PRIVACY CONTROLS

    ================================================================ */



    function applyTrustBadge(isVerified) {

      const wrap = document.getElementById('dash-trust-badge-wrap');

      if (!wrap) return;

      if (isVerified) {

        wrap.innerHTML = `<span class="trust-badge">

          <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>

          Verified

        </span>`;

      } else {

        wrap.innerHTML = '';

      }

    }



    /* ── Privacy settings (lives inside Account Settings → Privacy) ───────── */

    const PRIVACY_DEFAULTS = {

      hidePlaydate: false,

      privateTree: false,

      whatsappNotifications: false,

      whatsappNumber: "",

      whatsappVerified: false,

      hideOnlineStatus: false,

      hidePhone: false,

      hideEmail: false,

    };

    let privacyState = { ...PRIVACY_DEFAULTS };

    let privacyLoadedForUser = null;



    function privacyStorageKey() {

      return 'pawcircle_privacy:' + (currentUserObj?.id || currentUserObj?.email || 'guest');

    }



    function readLocalPrivacy() {

      try {

        const saved = JSON.parse(localStorage.getItem(privacyStorageKey()) || '{}');

        // migrate legacy "whatsapp" key

        if (saved.whatsapp != null && saved.whatsappNotifications == null) {

          saved.whatsappNotifications = saved.whatsapp;

        }

        return { ...PRIVACY_DEFAULTS, ...saved };

      } catch (e) {

        return { ...PRIVACY_DEFAULTS };

      }

    }



    // Pull the latest privacy settings from the backend; fall back to local cache.

    async function loadPrivacySettings() {

      privacyState = readLocalPrivacy();

      const uid = currentUserObj?.id;

      if (uid && privacyLoadedForUser !== uid) {

        try {

          const res = await api('get_privacy_settings', { user_id: uid });

          if (res?.status === 'success' && res.privacy_settings && typeof res.privacy_settings === 'object') {

            privacyState = { ...PRIVACY_DEFAULTS, ...res.privacy_settings };

            localStorage.setItem(privacyStorageKey(), JSON.stringify(privacyState));

          }

          privacyLoadedForUser = uid;

        } catch (e) {

          /* offline / not wired — keep local cache */

        }

      }

      if (accountSettingsState?.activeSection === 'privacy') renderAccountSettings();

    }



    function privacyToggleRow(key, title, desc) {

      return `

        <div class="flex items-center justify-between gap-4 py-4 border-b border-gray-100 dark:border-gray-800 last:border-0">

          <div class="min-w-0">

            <p class="text-base font-bold text-gray-900 dark:text-gray-100">${title}</p>

            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">${desc}</p>

          </div>

          <label class="privacy-toggle shrink-0">

            <input type="checkbox" id="priv-${key}" ${privacyState[key] ? 'checked' : ''} onchange="savePrivacySettings()">

            <span class="privacy-slider"></span>

          </label>

        </div>`;

    }



    function renderSettingsPrivacyPanelHtml() {

      const profile = accountSettingsState?.profile || {};

      return `

        <section class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-6">

          <div class="flex items-center justify-between gap-3 mb-2">

            <div>

              <h3 class="text-xl font-bold text-gray-900 dark:text-white">Privacy &amp; notifications</h3>

              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Control who can find you and how the breed can reach you.</p>

            </div>

            <i data-lucide="lock" class="w-5 h-5" style="color:var(--faith-accent,#16a34a)"></i>

          </div>

          <div class="mt-2">

            ${privacyToggleRow('hidePlaydate', 'Hide me from Playdate', 'Your profile will not appear in the playdate deck or searches.')}

            ${privacyToggleRow('privateTree', 'Keep my pack tree private', 'Only you can view your pack tree connections.')}

            ${privacyToggleRow('hideOnlineStatus', 'Hide my online status', 'Other members will not see when you are active.')}

            ${privacyToggleRow('hidePhone', 'Hide my phone number', 'Your phone is hidden from members who are not friends.')}

            ${privacyToggleRow('hideEmail', 'Hide my email address', 'Your email is hidden from members who are not friends.')}

          </div>

          <p id="privacy-save-status" class="mt-4 text-xs font-semibold text-gray-400"></p>

        </section>`;

    }



    // Toggle the WhatsApp config section open/closed and persist the choice.

    function onWhatsappToggle() {

      const on = document.getElementById('priv-whatsappNotifications')?.checked;

      const cfg = document.getElementById('whatsapp-config');

      if (cfg) cfg.classList.toggle('hidden', !on);

      if (on && window.lucide) lucide.createIcons();

      savePrivacySettings();

    }



    let _privacySaveTimer = null;

    async function savePrivacySettings() {

      Object.keys(PRIVACY_DEFAULTS).forEach((key) => {

        const el = document.getElementById('priv-' + key);

        if (el) privacyState[key] = el.checked;

      });

      // Capture the WhatsApp number (intl phone field) if present

      if (document.getElementById('privacy-whatsapp-number')) {

        privacyState.whatsappNumber = getPhoneValue('privacy-whatsapp-number');

      }

      localStorage.setItem(privacyStorageKey(), JSON.stringify(privacyState));



      const statusEl = document.getElementById('privacy-save-status');

      if (statusEl) statusEl.textContent = 'Saving…';



      clearTimeout(_privacySaveTimer);

      _privacySaveTimer = setTimeout(async () => {

        if (!currentUserObj?.id) {

          if (statusEl) statusEl.textContent = 'Saved on this device.';

          return;

        }

        try {

          await api('save_privacy_settings', { user_id: currentUserObj.id, privacy_settings: privacyState });

          if (statusEl) statusEl.textContent = 'All changes saved.';

        } catch (e) {

          if (statusEl) statusEl.textContent = 'Saved locally (offline).';

        }

      }, 350);

    }



    // Jump straight to the Privacy section of Account Settings.

    function openPrivacySettings() {

      if (typeof switchView === 'function') switchView('view-social-feed');

      if (typeof switchSocialTab === 'function') switchSocialTab('settings');

      switchAccountSettingsSection('privacy');

      loadPrivacySettings();

    }



    /* ================================================================

       MODULE 0 – VERIFICATION REQUEST (Settings > Security)

    ================================================================ */



    async function submitVerificationRequest() {

      const name = document.getElementById('verif-name')?.value.trim();

      const idType = document.getElementById('verif-id-type')?.value;

      const idNumber = document.getElementById('verif-id-number')?.value.trim();

      const reason = document.getElementById('verif-reason')?.value.trim();



      if (!name || !idType || !idNumber) {

        if (typeof showToast === 'function') showToast('Please fill in all required fields.', 'error');

        return;

      }



      const btn = document.querySelector('#verification-form-wrap button[onclick="submitVerificationRequest()"]');

      if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }



      try {

        await api('submit_verification_request', {

          full_name: name,

          id_type: idType,

          id_number: idNumber,

          reason: reason || '',

        });

        if (typeof showToast === 'function') showToast('Verification application submitted! Admins will review within 2–5 days.', 'success');

        /* Store locally so the UI updates immediately */

        if (currentUserObj) currentUserObj.verification_pending = true;

        if (typeof renderAccountSettings === 'function') renderAccountSettings();

      } catch (err) {

        /* Graceful degradation if API endpoint not wired yet */

        if (typeof showToast === 'function')

          showToast('Application recorded locally. Wire up "submit_verification_request" on the backend to persist.', 'info');

        if (currentUserObj) currentUserObj.verification_pending = true;

        const wrap = document.getElementById('verification-form-wrap');

        if (wrap) wrap.innerHTML = '<p class="text-sm text-emerald-600 dark:text-emerald-400 font-semibold">✓ Application submitted — pending admin review.</p>';

      } finally {

        if (btn) { btn.disabled = false; }

      }

    }



    /* ================================================================

       MODULE 2 – EVENT MODULE: QR CHECK-IN & ICS DOWNLOAD

    ================================================================ */



    function openEventQRModal() {

      const titleEl = document.getElementById('event-modal-title');

      const dateEl = document.getElementById('event-modal-date');

      const title = (titleEl?.value || 'My Event').trim();

      const dateStr = dateEl?.value || new Date().toISOString().slice(0, 10);



      document.getElementById('qr-event-name').textContent = title;

      const container = document.getElementById('qr-code-container');

      container.innerHTML = '';



      const ticketData = JSON.stringify({

        event: title,

        date: dateStr,

        userId: currentUserObj?.id || 'guest',

        ts: Date.now(),

      });



      if (typeof QRCode !== 'undefined') {

        new QRCode(container, {

          text: ticketData,

          width: 200,

          height: 200,

          colorDark: '#1f2937',

          colorLight: '#ffffff',

          correctLevel: QRCode.CorrectLevel.M,

        });

      } else {

        container.innerHTML = '<p class="text-xs text-gray-400">QR library not loaded.</p>';

      }



      document.getElementById('event-qr-modal').classList.add('open');

      if (window.lucide) lucide.createIcons();

    }



    function closeEventQRModal() {

      document.getElementById('event-qr-modal').classList.remove('open');

    }



    function downloadEventICS() {

      const titleEl = document.getElementById('event-modal-title');

      const dateEl = document.getElementById('event-modal-date');

      const timeEl = document.getElementById('event-modal-time');

      const descEl = document.getElementById('event-modal-desc');



      const title = (titleEl?.value || 'PawCircle Event').trim();

      const dateStr = (dateEl?.value || new Date().toISOString().slice(0, 10)).replace(/-/g, '');

      const time = (timeEl?.value || '09:00').replace(':', '') + '00';

      const desc = (descEl?.value || '').replace(/\n/g, '\\n');



      const dtStart = `${dateStr}T${time}`;

      const dtEnd = `${dateStr}T${String(Number(time.slice(0, 2)) + 1).padStart(2, '0')}${time.slice(2)}`;



      const ics = [

        'BEGIN:VCALENDAR',

        'VERSION:2.0',

        'PRODID:-//PawCircle//EN',

        'BEGIN:VEVENT',

        `DTSTART:${dtStart}`,

        `DTEND:${dtEnd}`,

        `SUMMARY:${title}`,

        `DESCRIPTION:${desc}`,

        'END:VEVENT',

        'END:VCALENDAR',

      ].join('\r\n');



      const blob = new Blob([ics], { type: 'text/calendar;charset=utf-8' });

      const a = document.createElement('a');

      a.href = URL.createObjectURL(blob);

      a.download = title.replace(/\s+/g, '_') + '.ics';

      a.click();

      URL.revokeObjectURL(a.href);

      if (typeof showToast === 'function') showToast('Calendar file downloaded!', 'success');

    }



    /* ================================================================

       MODULE 2b – EVENT ANALYTICS (Chart.js)

    ================================================================ */



    let _analyticsChartAttendance = null;

    let _analyticsChartDemo = null;

    let eventsActiveSubTab = 'list';



    // Switch between the Events list and the Analytics sub-panel inside the Events tab.

    function setEventsSubTab(which) {

      eventsActiveSubTab = (which === 'analytics') ? 'analytics' : 'list';

      const listPanel = document.getElementById('events-subtab-list');

      const anaPanel = document.getElementById('events-subtab-analytics');

      if (listPanel) listPanel.classList.toggle('hidden', eventsActiveSubTab !== 'list');

      if (anaPanel) anaPanel.classList.toggle('hidden', eventsActiveSubTab !== 'analytics');



      document.querySelectorAll('.events-subtab-btn').forEach((btn) => {

        btn.classList.remove('text-brand-600', 'dark:text-brand-400');

        btn.classList.add('text-gray-500', 'dark:text-gray-400');

        btn.style.borderBottomColor = 'transparent';

      });

      const activeBtn = document.getElementById('events-subtab-btn-' + eventsActiveSubTab);

      if (activeBtn) {

        activeBtn.classList.add('text-brand-600', 'dark:text-brand-400');

        activeBtn.classList.remove('text-gray-500', 'dark:text-gray-400');

        activeBtn.style.borderBottomColor = 'var(--faith-accent, #f97316)';

      }

      if (eventsActiveSubTab === 'analytics') renderEventAnalytics();

      if (window.lucide) lucide.createIcons();

    }



    // Open the Events tab directly on the Analytics sub-panel.

    function openEventsAnalytics() {

      if (typeof switchView === 'function') switchView('view-social-feed');

      if (typeof switchSocialTab === 'function') switchSocialTab('events');

      setEventsSubTab('analytics');

    }



    const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];



    // Compute analytics straight from the events the user can actually see locally,

    // including their own RSVPs — used as the source of truth / offline fallback.

    function computeLocalEventAnalytics() {

      const events = (typeof getAllLocalEvents === 'function' ? getAllLocalEvents() : []) || [];

      const responses = (typeof getEventResponses === 'function' ? getEventResponses() : {}) || {};



      // Attendance per month (last 6 calendar months)

      const now = new Date();

      const buckets = [];

      for (let i = 5; i >= 0; i--) {

        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);

        buckets.push({ key: `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`, label: MONTH_LABELS[d.getMonth()], attendees: 0, events: 0 });

      }

      const bucketByKey = Object.fromEntries(buckets.map(b => [b.key, b]));



      let totalRsvp = 0;

      const pet_typeCounts = {};

      events.forEach((ev) => {

        if (!ev?.date) return;

        const key = String(ev.date).slice(0, 7);

        const b = bucketByKey[key];

        const eid = ev.db_id || ev.id;

        const attendees = Number(ev.attendees_count ?? ev.attendees ?? 0) || 0;

        const myRsvp = responses[String(eid)]?.rsvp ? 1 : 0;

        totalRsvp += myRsvp;

        if (b) {

          b.events += 1;

          b.attendees += attendees + myRsvp;

        }

        const rel = ev.pet_type || ev.audience || ev.breed || 'Pet Breed';

        pet_typeCounts[rel] = (pet_typeCounts[rel] || 0) + 1;

      });



      const demographics = Object.entries(pet_typeCounts)

        .sort((a, b) => b[1] - a[1])

        .map(([pet_type, count]) => ({ pet_type, count }));



      return {

        monthly: buckets.map(b => ({ month: b.label, attendees: b.attendees, events: b.events })),

        demographics,

        totals: {

          events: events.length,

          attendees: buckets.reduce((s, b) => s + b.attendees, 0),

          myRsvps: totalRsvp,

          pet_types: demographics.length,

        },

        source: 'local',

      };

    }



    // Try the backend for accurate, breed-wide numbers; fall back to local.

    async function fetchEventAnalytics() {

      const local = computeLocalEventAnalytics();

      if (!currentUserObj?.id) return local;

      try {

        const res = await api('get_event_analytics', { user_id: currentUserObj.id });

        if (res?.status !== 'success') return local;



        const monthlyRaw = Array.isArray(res.monthly_attendance) ? res.monthly_attendance : [];

        const demoRaw = Array.isArray(res.pet_type_demographics) ? res.pet_type_demographics : [];

        if (!monthlyRaw.length && !demoRaw.length) return local;



        const monthly = monthlyRaw.map((m) => {

          const mm = parseInt(String(m.month).slice(5, 7), 10);

          return { month: MONTH_LABELS[(mm - 1 + 12) % 12] || m.month, attendees: Number(m.attendees) || 0, events: Number(m.events) || 0 };

        });

        const demographics = demoRaw.map((d) => ({ pet_type: d.pet_type || 'Unknown', count: Number(d.count) || 0 }));

        return {

          monthly: monthly.length ? monthly : local.monthly,

          demographics: demographics.length ? demographics : local.demographics,

          totals: {

            events: monthly.reduce((s, m) => s + m.events, 0),

            attendees: monthly.reduce((s, m) => s + m.attendees, 0),

            myRsvps: local.totals.myRsvps,

            pet_types: demographics.length,

          },

          source: 'backend',

        };

      } catch (e) {

        return local;

      }

    }



    function analyticsStatCard(label, value, icon) {

      return `

        <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm p-4">

          <div class="flex items-center gap-2 text-gray-400">

            <i data-lucide="${icon}" class="w-4 h-4"></i>

            <span class="text-xs font-black uppercase tracking-wider">${label}</span>

          </div>

          <p class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white">${value}</p>

        </div>`;

    }



    async function renderEventAnalytics() {

      const accent = getComputedStyle(document.documentElement)

        .getPropertyValue('--faith-accent').trim() || '#e04848';

      const isDark = document.documentElement.classList.contains('dark');

      const gridColor = isDark ? 'rgba(255,255,255,0.08)' : 'rgba(0,0,0,0.05)';

      const tickColor = isDark ? '#9ca3af' : '#6b7280';

      const ringColor = isDark ? '#111827' : '#ffffff';



      const data = await fetchEventAnalytics();



      /* Stat cards */

      const statsEl = document.getElementById('events-analytics-stats');

      if (statsEl) {

        statsEl.innerHTML =

          analyticsStatCard('Total events', data.totals.events, 'calendar-heart') +

          analyticsStatCard('Total attendees', data.totals.attendees, 'users') +

          analyticsStatCard('My RSVPs', data.totals.myRsvps, 'check-circle') +

          analyticsStatCard('PetTypes', data.totals.pet_types, 'landmark');

      }

      const srcEl = document.getElementById('events-analytics-source');

      if (srcEl) {

        srcEl.textContent = data.source === 'backend'

          ? 'Live figures from breed events across the platform.'

          : 'Figures computed from events visible on this device.';

      }



      /* --- Bar chart: Attendance over time --- */

      const ctxBar = document.getElementById('chart-attendance');

      if (ctxBar && typeof Chart !== 'undefined') {

        if (_analyticsChartAttendance) _analyticsChartAttendance.destroy();

        _analyticsChartAttendance = new Chart(ctxBar, {

          type: 'bar',

          data: {

            labels: data.monthly.map(m => m.month),

            datasets: [{

              label: 'Attendees',

              data: data.monthly.map(m => m.attendees),

              backgroundColor: accent + 'cc',

              borderColor: accent,

              borderWidth: 1,

              borderRadius: 6,

            }],

          },

          options: {

            responsive: true,

            plugins: { legend: { display: false } },

            scales: {

              y: { beginAtZero: true, ticks: { color: tickColor, precision: 0 }, grid: { color: gridColor } },

              x: { ticks: { color: tickColor }, grid: { display: false } },

            },

          },

        });

      }



      /* --- Doughnut: Audience by pet_type --- */

      const ctxPie = document.getElementById('chart-demographics');

      if (ctxPie && typeof Chart !== 'undefined') {

        if (_analyticsChartDemo) _analyticsChartDemo.destroy();

        const demo = data.demographics.length ? data.demographics : [{ pet_type: 'No data', count: 1 }];

        const shades = ['', 'cc', 'aa', '88', '66', '44', '33', '22'];

        _analyticsChartDemo = new Chart(ctxPie, {

          type: 'doughnut',

          data: {

            labels: demo.map(d => d.pet_type),

            datasets: [{

              data: demo.map(d => d.count),

              backgroundColor: demo.map((_, i) => accent + (shades[i] || '22')),

              borderWidth: 2,

              borderColor: ringColor,

            }],

          },

          options: {

            responsive: true,

            plugins: { legend: { position: 'bottom', labels: { color: tickColor, font: { size: 12 } } } },

            cutout: '60%',

          },

        });

      }

    }



    /* Wire content-hub rendering via MutationObserver on active class */

    document.addEventListener('DOMContentLoaded', function () {

      const sectionsToWatch = {

        'view-content-hub': () => { if (typeof renderContentHub === 'function') renderContentHub(); },

      };

      const observer = new MutationObserver(() => {

        Object.entries(sectionsToWatch).forEach(([id, fn]) => {

          const el = document.getElementById(id);

          if (el && el.classList.contains('active')) fn();

        });

      });

      document.querySelectorAll('.view-section').forEach(el =>

        observer.observe(el, { attributes: true, attributeFilter: ['class'] })

      );

    });



    /* ================================================================

       MODULE 3 – FAMILY TREE PAN/ZOOM + SIDE PANEL

    ================================================================ */



    (function initPackTreePanZoom() {

      /* State is shared with ftCenterView via window._ftState */

      function getState() {

        return window._ftState || { scale: 1, panX: 0, panY: 0 };

      }

      function setState(s, x, y) {

        window._ftState = { scale: s, panX: x, panY: y };

      }

      let dragging = false, startX = 0, startY = 0, originX = 0, originY = 0;



      function applyTransform() {

        const { scale, panX, panY } = getState();

        const stage = document.getElementById('ft-pan-stage');

        if (stage) stage.style.transform = `translate(${panX}px,${panY}px) scale(${scale})`;

        originX = panX; originY = panY;

      }



      window.ftZoom = function (delta) {

        const st = getState();

        const s = Math.min(2.5, Math.max(0.3, st.scale + delta));

        setState(s, st.panX, st.panY);

        applyTransform();

      };



      window.ftResetView = function () {

        if (typeof ftCenterView === 'function') { ftCenterView(); return; }

        setState(1, 0, 0); applyTransform();

      };



      document.addEventListener('DOMContentLoaded', () => {

        const container = document.getElementById('ft-pan-container');

        if (!container) return;



        container.addEventListener('mousedown', e => {

          if (e.button !== 0) return;

          dragging = true;

          const st = getState();

          startX = e.clientX - st.panX;

          startY = e.clientY - st.panY;

          container.classList.add('grabbing');

        });

        document.addEventListener('mousemove', e => {

          if (!dragging) return;

          const st = getState();

          const x = e.clientX - startX;

          const y = e.clientY - startY;

          setState(st.scale, x, y);

          applyTransform();

        });

        document.addEventListener('mouseup', () => {

          dragging = false;

          const c = document.getElementById('ft-pan-container');

          if (c) c.classList.remove('grabbing');

        });



        container.addEventListener('wheel', e => {

          e.preventDefault();

          ftZoom(e.deltaY < 0 ? 0.1 : -0.1);

        }, { passive: false });



        /* Touch pinch/pan */

        let lastTouchDist = null;

        container.addEventListener('touchstart', e => {

          if (e.touches.length === 1) {

            dragging = true;

            const st = getState();

            startX = e.touches[0].clientX - st.panX;

            startY = e.touches[0].clientY - st.panY;

          } else if (e.touches.length === 2) {

            lastTouchDist = Math.hypot(

              e.touches[0].clientX - e.touches[1].clientX,

              e.touches[0].clientY - e.touches[1].clientY

            );

          }

        }, { passive: true });

        container.addEventListener('touchmove', e => {

          if (e.touches.length === 1 && dragging) {

            const st = getState();

            setState(st.scale, e.touches[0].clientX - startX, e.touches[0].clientY - startY);

            applyTransform();

          } else if (e.touches.length === 2 && lastTouchDist) {

            const dist = Math.hypot(

              e.touches[0].clientX - e.touches[1].clientX,

              e.touches[0].clientY - e.touches[1].clientY

            );

            ftZoom((dist - lastTouchDist) * 0.005);

            lastTouchDist = dist;

          }

        }, { passive: true });

        container.addEventListener('touchend', () => { dragging = false; lastTouchDist = null; });

      });

    })();



    /* Side panel */

    const _origOpenPackMemberProfile = typeof openPackMemberProfile === 'function'

      ? openPackMemberProfile : null;



    window.openPackMemberProfile = function (name, subtitle, id) {

      const panel = document.getElementById('ft-side-panel');

      if (!panel) { if (_origOpenPackMemberProfile) _origOpenPackMemberProfile(name, subtitle, id); return; }



      document.getElementById('ft-panel-name').textContent = name || 'Unknown';

      document.getElementById('ft-panel-relation').textContent = subtitle || '';



      const avatar = document.getElementById('ft-panel-avatar');

      avatar.textContent = (name || '?')[0].toUpperCase();



      /* Populate member details */

      let member = null;

      if (id && id !== 'me' && currentUserObj?.packMembers) {

        member = currentUserObj.packMembers.find(m => String(m.id) === String(id));

      } else if (id === 'me') {

        member = { dob: currentUserObj?.dob, city: currentUserObj?.city, mobile_number: currentUserObj?.mobile_number };

      }



      const det = document.getElementById('ft-panel-details');

      const rows = [];

      if (member?.dob) rows.push(`<div><span class="font-semibold text-gray-800 dark:text-gray-200">DOB:</span> ${member.dob}</div>`);

      if (member?.city) rows.push(`<div><span class="font-semibold text-gray-800 dark:text-gray-200">City:</span> ${member.city}</div>`);

      if (member?.mobile_number) rows.push(`<div><span class="font-semibold text-gray-800 dark:text-gray-200">Mobile:</span> ${member.mobile_number}</div>`);

      if (member?.email) rows.push(`<div><span class="font-semibold text-gray-800 dark:text-gray-200">Email:</span> ${member.email}</div>`);

      det.innerHTML = rows.length ? rows.join('') : '<p class="text-xs text-gray-400">No additional details.</p>';



      panel.classList.add('open');

      if (window.lucide) lucide.createIcons();

    };



    window.closeFTSidePanel = function () {

      const panel = document.getElementById('ft-side-panel');

      if (panel) panel.classList.remove('open');

    };



    /* ================================================================

       MODULE 4 – CONTENT HUB

    ================================================================ */



    // Working sample media so the player actually plays (royalty-free public samples).

    const CH_V = 'https://storage.googleapis.com/gtv-videos-bucket/sample/';

    const CH_A = 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-';

    const chThumb = (color, text) => `https://placehold.co/400x225/${color}/ffffff?text=${encodeURIComponent(text)}`;



    // Each entry is tagged with a pet_type so the hub can show only the user's faith.

    const CH_ALL_CONTENT = [

      // Hindu

      { id: 'h-v1', pet_type: 'Dog', type: 'video', title: 'Bhagavad Gita Chapter 2 Discourse', speaker: 'Swami Sarvapriyananda', thumb: chThumb('f97316', 'Gita Ch.2'), src: CH_V + 'BigBuckBunny.mp4' },

      { id: 'h-v2', pet_type: 'Dog', type: 'video', trending: true, title: 'Ramayana Katha Sunderkand', speaker: 'Morari Bapu', thumb: chThumb('ea580c', 'Ram Katha'), src: CH_V + 'ElephantsDream.mp4' },

      { id: 'h-a1', pet_type: 'Dog', type: 'audio', title: 'Hanuman Chalisa', speaker: 'Hari Om Sharan', thumb: chThumb('f97316', 'Chalisa'), src: CH_A + '1.mp3' },

      { id: 'h-a2', pet_type: 'Dog', type: 'audio', trending: true, title: 'Om Namah Shivaya Dhun', speaker: 'Anuradha Paudwal', thumb: chThumb('ea580c', 'Shiv Dhun'), src: CH_A + '2.mp3' },



      // Muslim

      { id: 'm-v1', pet_type: 'Cat', type: 'video', title: 'Juma Khutbah on Inner Peace', speaker: 'Sheikh Omar Suleiman', thumb: chThumb('10b981', 'Khutbah'), src: CH_V + 'ForBiggerBlazes.mp4' },

      { id: 'm-v2', pet_type: 'Cat', type: 'video', trending: true, title: 'Seerah of the Prophet', speaker: 'Mufti Menk', thumb: chThumb('059669', 'Seerah'), src: CH_V + 'ForBiggerEscapes.mp4' },

      { id: 'm-a1', pet_type: 'Cat', type: 'audio', title: 'Surah Al-Rahman Recitation', speaker: 'Sheikh Mishary Rashid', thumb: chThumb('10b981', 'Tilawat'), src: CH_A + '3.mp3' },

      { id: 'm-a2', pet_type: 'Cat', type: 'audio', trending: true, title: 'Surah Yaseen', speaker: 'Sheikh Sudais', thumb: chThumb('059669', 'Yaseen'), src: CH_A + '4.mp3' },



      // Sikh

      { id: 's-v1', pet_type: 'Bird', type: 'video', title: 'Guru Granth Sahib Katha', speaker: 'Giani Pinderpal Singh', thumb: chThumb('3b82f6', 'Katha'), src: CH_V + 'ForBiggerFun.mp4' },

      { id: 's-v2', pet_type: 'Bird', type: 'video', trending: true, title: 'Sakhi of Guru Nanak Dev Ji', speaker: 'Bhai Sahib', thumb: chThumb('2563eb', 'Sakhi'), src: CH_V + 'ForBiggerJoyrides.mp4' },

      { id: 's-a1', pet_type: 'Bird', type: 'audio', title: 'Waheguru Simran', speaker: 'Bhai Harjinder Singh', thumb: chThumb('3b82f6', 'Simran'), src: CH_A + '5.mp3' },

      { id: 's-a2', pet_type: 'Bird', type: 'audio', trending: true, title: 'Japji Sahib Path', speaker: 'Bhai Gurpreet Singh', thumb: chThumb('2563eb', 'Japji'), src: CH_A + '6.mp3' },



      // Christian

      { id: 'c-v1', pet_type: 'Rabbit', type: 'video', title: 'Sunday Homily on Love and Forgiveness', speaker: 'Fr. Mike Schmitz', thumb: chThumb('8b5cf6', 'Homily'), src: CH_V + 'ForBiggerMeltdowns.mp4' },

      { id: 'c-v2', pet_type: 'Rabbit', type: 'video', trending: true, title: 'The Sermon on the Mount', speaker: 'Pastor John', thumb: chThumb('7c3aed', 'Sermon'), src: CH_V + 'SubaruOutbackOnStreetAndDirt.mp4' },

      { id: 'c-a1', pet_type: 'Rabbit', type: 'audio', title: 'Amazing Grace Choir', speaker: 'Brooklyn Tabernacle Choir', thumb: chThumb('8b5cf6', 'Hymn'), src: CH_A + '7.mp3' },

      { id: 'c-a2', pet_type: 'Rabbit', type: 'audio', trending: true, title: 'How Great Thou Art', speaker: 'Pet Breed Choir', thumb: chThumb('7c3aed', 'Worship'), src: CH_A + '8.mp3' },



      // Buddhist

      { id: 'b-v1', pet_type: 'Reptile', type: 'video', title: 'Buddha Dhamma Talk', speaker: 'Ajahn Brahm', thumb: chThumb('f59e0b', 'Dhamma'), src: CH_V + 'TearsOfSteel.mp4' },

      { id: 'b-v2', pet_type: 'Reptile', type: 'video', trending: true, title: 'Mindfulness and the Four Noble Truths', speaker: 'Thich Nhat Hanh', thumb: chThumb('d97706', 'Mindfulness'), src: CH_V + 'VolkswagenGTIReview.mp4' },

      { id: 'b-a1', pet_type: 'Reptile', type: 'audio', title: 'Metta Meditation Chant', speaker: 'Imee Ooi', thumb: chThumb('f59e0b', 'Metta'), src: CH_A + '9.mp3' },

      { id: 'b-a2', pet_type: 'Reptile', type: 'audio', trending: true, title: 'Heart Sutra Recitation', speaker: 'Sangha', thumb: chThumb('d97706', 'Sutra'), src: CH_A + '10.mp3' },



      // Jain

      { id: 'j-v1', pet_type: 'Fish', type: 'video', title: 'Mahavir Jayanti Pravachan', speaker: 'Muni Tarun Sagar Ji', thumb: chThumb('ef4444', 'Mahavir'), src: CH_V + 'WeAreGoingOnBullrun.mp4' },

      { id: 'j-v2', pet_type: 'Fish', type: 'video', trending: true, title: 'Paryushana and the Path of Ahimsa', speaker: 'Acharya Vidyasagar', thumb: chThumb('dc2626', 'Paryushana'), src: CH_V + 'WhatCarCanYouGetForAGrand.mp4' },

      { id: 'j-a1', pet_type: 'Fish', type: 'audio', title: 'Navkar Mantra', speaker: 'Stavan Pack', thumb: chThumb('ef4444', 'Navkar'), src: CH_A + '11.mp3' },

      { id: 'j-a2', pet_type: 'Fish', type: 'audio', trending: true, title: 'Bhaktamar Stotra', speaker: 'Pandit Ji', thumb: chThumb('dc2626', 'Stotra'), src: CH_A + '12.mp3' },



      // Parsi

      { id: 'p-v1', pet_type: 'Small Pet', type: 'video', title: 'Zarathushtra and the Gathas Explained', speaker: 'Ervad Dr. Ramiyar', thumb: chThumb('e04848', 'Gathas'), src: CH_V + 'BigBuckBunny.mp4' },

      { id: 'p-a1', pet_type: 'Small Pet', type: 'audio', title: 'Ashem Vohu Prayer', speaker: 'Mobed', thumb: chThumb('e04848', 'Prayer'), src: CH_A + '13.mp3' },

      { id: 'p-a2', pet_type: 'Small Pet', type: 'audio', trending: true, title: 'Yatha Ahu Vairyo', speaker: 'Mobed', thumb: chThumb('b91c1c', 'Yatha'), src: CH_A + '14.mp3' },

    ];



    // Curated devotional YouTube channels that run live broadcasts. We embed each

    // channel's *current* live stream via YouTube's keyless endpoint:

    //   https://www.youtube.com/embed/live_stream?channel=<CHANNEL_ID>

    // YouTube automatically surfaces whatever that channel is streaming live right

    // now (and shows an offline state otherwise) — no API key or quota required.

    const CH_LIVE_CHANNELS = [

      { pet_type: 'Dog', name: 'Aastha TV', desc: 'Bhajans, aarti & live pawcircle', channelId: 'UCRUAdVm9ZOF4JheOd8qIQHA', accent: '#f97316' },

      { pet_type: 'Dog', name: 'Sanskar TV', desc: 'Devotional & satsang, 24/7', channelId: 'UCT_QwW7Tbew5qrYNb2auqAQ', accent: '#ea580c' },

      { pet_type: 'Dog', name: 'Satsang', desc: 'Spiritual discourses', channelId: 'UCJeQx6mAyNdPUc9sJA866Xw', accent: '#d97706' },

      { pet_type: 'Bird', name: 'SGPC Sri Amritsar', desc: 'Live Gurbani Kirtan — Sri Harmandir Sahib', channelId: 'UCYn6UEtQ771a_OWSiNBoG8w', accent: '#2563eb' },

      { pet_type: 'Cat', name: 'Makkah Live TV', desc: 'Quran recitation from Makkah', channelId: 'UC04RHDai67rTJ-MX8Dq2CqA', accent: '#10b981' },

      { pet_type: 'Rabbit', name: 'EWTN', desc: 'Live Catholic Mass & devotions', channelId: 'UCijDos-LUTh9RQvSCMQqN6Q', accent: '#8b5cf6' },

      { pet_type: 'Rabbit', name: 'Daily TV Mass', desc: 'Daily Catholic Mass', channelId: 'UCi6JtCVy4XKu4BSG-AE2chg', accent: '#7c3aed' },

      { pet_type: 'Reptile', name: 'Plum Village', desc: 'Dharma talks & guided meditation', channelId: 'UCcv7KJIAsiddB2YRegvrF7g', accent: '#f59e0b' },

      { pet_type: 'Fish', name: 'Paras Channel', desc: 'Jain bhakti, stavan & pawcircle', channelId: 'UC_CSVqktYBaCMSLrmDNyGxA', accent: '#ef4444' },

    ];



    // Resolve the pet_type whose content we should show.

    function chUserPetType() {

      const r = currentUserObj?.pet_type || currentUserObj?.socialProfile?.pet_type || '';

      const known = ['Dog', 'Cat', 'Bird', 'Rabbit', 'Reptile', 'Fish', 'Small Pet'];

      return known.includes(r) ? r : '';

    }



    // The active, pet_type-filtered content list (falls back to all when pet_type unknown).

    function chVisibleContent() {

      const rel = chUserPetType();

      return rel ? CH_ALL_CONTENT.filter(i => i.pet_type === rel) : CH_ALL_CONTENT;

    }



    function renderContentHub() {

      // Mirror the user's faith accent onto the hub.

      if (typeof getFaithAccentColor === 'function') {

        document.documentElement.style.setProperty('--faith-accent', getFaithAccentColor(currentUserObj?.pet_type));

      }

      const rel = chUserPetType();

      const subtitle = document.getElementById('ch-subtitle');

      if (subtitle) subtitle.textContent = rel

        ? `Live park, kirtan & pawcircle for the ${rel} breed`

        : 'Live park, kirtan & pawcircle from across faiths';



      // The user's-faith channels are featured; the rest are offered to explore.

      const mine = rel ? CH_LIVE_CHANNELS.filter(c => c.pet_type === rel) : [];

      const featured = mine.length ? mine : CH_LIVE_CHANNELS.slice();

      const explore = CH_LIVE_CHANNELS.filter(c => !featured.includes(c));



      // Hero wiring

      const heroRel = document.getElementById('ch-hero-rel');

      if (heroRel) heroRel.textContent = rel ? `for the ${rel} breed` : 'every day';

      const heroCount = document.getElementById('ch-hero-count');

      if (heroCount) heroCount.textContent = String(CH_LIVE_CHANNELS.length);

      const hero = document.getElementById('ch-hero');

      if (hero) hero.classList.remove('hidden');



      chFeaturedChannelId = featured[0]?.channelId || null;

      renderCHFeatured('ch-featured-slot', featured);

      renderCHRow('ch-row-video', explore);



      // Toggle section visibility / empty state

      const toggle = (secId, has) => { const el = document.getElementById(secId); if (el) el.classList.toggle('hidden', !has); };

      toggle('ch-section-featured', featured.length);

      toggle('ch-section-video', explore.length);

      toggle('ch-section-audio', false);   // audio/trending replaced by live channels

      toggle('ch-section-trending', false);

      const empty = document.getElementById('ch-empty-state');

      if (empty) empty.classList.toggle('hidden', CH_LIVE_CHANNELS.length > 0);



      if (window.lucide) lucide.createIcons();

    }



    let chFeaturedChannelId = null;

    function chPlayFeatured() {

      const ch = CH_LIVE_CHANNELS.find(c => c.channelId === chFeaturedChannelId) || CH_LIVE_CHANNELS[0];

      if (ch) chPlayChannel(ch.channelId, ch.name, ch.desc);

    }



    // Keyless gradient art for a live channel card (no external thumbnail needed).

    function chChannelArt(ch) {

      const a = ch.accent || 'var(--faith-accent,#e04848)';

      return `<div class="ch-thumb-media" style="background:linear-gradient(135deg, ${a} 0%, color-mix(in srgb, ${a} 55%, #1a1a1a) 100%); display:flex; align-items:center; justify-content:center;">

          <span class="ch-thumb-tag">${escapeHtml(ch.pet_type)}</span>

          <span class="ch-type-pill" style="background:#dc2626;"><span style="width:6px;height:6px;border-radius:50%;background:#fff;display:inline-block"></span> LIVE</span>

          <i data-lucide="radio-tower" style="width:38px;height:38px;color:rgba(255,255,255,.92)"></i>

          <div class="ch-play-overlay"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></div>

        </div>`;

    }



    // Builds a safe onclick string (single-quoted JS args inside a double-quoted attribute).

    function chChannelOnclick(ch) {

      const esc = (s) => String(s).replace(/\\/g, "\\\\").replace(/'/g, "\\'");

      return `chPlayChannel('${esc(ch.channelId)}', '${esc(ch.name)}', '${esc(ch.desc)}')`;

    }



    function renderCHRow(containerId, channels) {

      const el = document.getElementById(containerId);

      if (!el) return;

      el.innerHTML = channels.map(ch => `

        <div class="ch-thumb" onclick="${chChannelOnclick(ch)}">

          ${chChannelArt(ch)}

          <div class="ch-thumb-label">

            <div class="truncate">${escapeHtml(ch.name)}</div>

            <div class="ch-thumb-sub truncate"><i data-lucide="radio" class="w-3 h-3"></i> ${escapeHtml(ch.desc)}</div>

          </div>

        </div>`).join('');

    }



    function renderCHFeatured(containerId, channels) {

      const el = document.getElementById(containerId);

      if (!el) return;

      el.innerHTML = channels.map(ch => {

        const a = ch.accent || 'var(--faith-accent,#e04848)';

        return `<div class="ch-featured" onclick="${chChannelOnclick(ch)}" style="background:linear-gradient(135deg, ${a} 0%, color-mix(in srgb, ${a} 50%, #111) 100%);">

          <div class="ch-featured-scrim">

            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-black uppercase mb-2"><span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> Live</span>

            <h4 class="font-bold text-lg leading-snug">${escapeHtml(ch.name)}</h4>

            <p class="text-sm mt-1" style="color: rgba(255,255,255,.85)">${escapeHtml(ch.desc)}</p>

            <span class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-white text-gray-900 text-xs font-bold">

              <i data-lucide="play" class="w-3.5 h-3.5 fill-current"></i> Watch live

            </span>

          </div>

        </div>`;

      }).join('');

    }



    // Embeds a channel's current live broadcast via YouTube's keyless live_stream endpoint.

    function chPlayChannel(channelId, name, desc) {

      const section = document.getElementById('ch-player-section');

      const ytWrap = document.getElementById('ch-yt-wrap');

      const yt = document.getElementById('ch-yt-player');

      const video = document.getElementById('ch-video-player');

      const audio = document.getElementById('ch-audio-player');

      const controls = document.getElementById('ch-custom-controls');

      const liveBadge = document.getElementById('ch-live-badge');

      if (!section) return;



      // Stop any native sample players, swap in the YouTube live embed.

      if (video) { try { video.pause(); } catch (e) { } video.style.display = 'none'; video.removeAttribute('src'); }

      if (audio) { try { audio.pause(); } catch (e) { } audio.style.display = 'none'; audio.removeAttribute('src'); }

      if (controls) controls.style.display = 'none';

      if (ytWrap) ytWrap.style.display = 'block';

      if (yt) yt.src = `https://www.youtube.com/embed/live_stream?channel=${encodeURIComponent(channelId)}&autoplay=1&rel=0`;



      const titleEl = document.getElementById('ch-now-playing-title');

      const subEl = document.getElementById('ch-now-playing-sub');

      if (titleEl) titleEl.textContent = name || 'Live stream';

      if (subEl) subEl.textContent = desc || '';

      if (liveBadge) { liveBadge.classList.remove('hidden'); liveBadge.classList.add('inline-flex'); }



      section.classList.remove('hidden');

      section.scrollIntoView({ behavior: 'smooth', block: 'start' });

    }



    function chPlay(id) {

      const item = CH_ALL_CONTENT.find(i => i.id === id);

      if (!item) return;

      const rowId = item.type === 'audio' ? 'ch-row-audio' : 'ch-row-video';



      const isAudio = rowId === 'ch-row-audio' || !!item.isAudio;

      const section = document.getElementById('ch-player-section');

      const video = document.getElementById('ch-video-player');

      const audio = document.getElementById('ch-audio-player');

      const controls = document.getElementById('ch-custom-controls');



      document.getElementById('ch-now-playing-title').textContent = item.title;

      document.getElementById('ch-now-playing-sub').textContent = item.speaker;



      if (isAudio) {

        video.style.display = 'none'; controls.style.display = 'none';

        audio.style.display = 'block';

        audio.src = item.src || '';

        audio.play().catch(() => { });

      } else {

        audio.style.display = 'none';

        video.style.display = 'block'; controls.style.display = 'flex';

        video.src = item.src || '';

        video.play().catch(() => { });

        chBindVideoEvents();

      }



      section.classList.remove('hidden');

      section.scrollIntoView({ behavior: 'smooth', block: 'start' });

    }



    function chBindVideoEvents() {

      const video = document.getElementById('ch-video-player');

      const fill = document.getElementById('ch-progress-fill');

      const time = document.getElementById('ch-time-display');

      if (!video) return;



      video.ontimeupdate = () => {

        if (!video.duration) return;

        fill.style.width = (video.currentTime / video.duration * 100) + '%';

        time.textContent = chFmtTime(video.currentTime) + ' / ' + chFmtTime(video.duration);

      };

    }



    function chFmtTime(s) {

      const m = Math.floor(s / 60), sec = Math.floor(s % 60);

      return `${m}:${String(sec).padStart(2, '0')}`;

    }



    function chTogglePlay() {

      const video = document.getElementById('ch-video-player');

      const btn = document.getElementById('ch-play-btn');

      if (!video) return;

      if (video.paused) {

        video.play();

        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>';

      } else {

        video.pause();

        btn.innerHTML = '<svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:currentColor"><path d="M8 5v14l11-7z"/></svg>';

      }

    }



    function chSeek(e) {

      const video = document.getElementById('ch-video-player');

      const bar = document.getElementById('ch-progress-bar');

      if (!video || !bar) return;

      const rect = bar.getBoundingClientRect();

      video.currentTime = ((e.clientX - rect.left) / rect.width) * video.duration;

    }



    function chSetVolume(v) {

      const video = document.getElementById('ch-video-player');

      if (video) video.volume = parseFloat(v);

    }



    function chToggleMute() {

      const video = document.getElementById('ch-video-player');

      if (!video) return;

      video.muted = !video.muted;

      const icon = document.getElementById('ch-mute-icon');

      if (icon) {

        icon.querySelector('path').setAttribute('d', video.muted

          ? 'M16.5 12A4.5 4.5 0 0 0 14 7.97v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51A8.796 8.796 0 0 0 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06A8.99 8.99 0 0 0 17.73 18L19 19.27 20.27 18 5.27 3 4.27 3zM12 4L9.91 6.09 12 8.18V4z'

          : 'M3 9v6h4l5 5V4L7 9H3zm13.5 3A4.5 4.5 0 0 0 14 7.97v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z');

      }

    }



  