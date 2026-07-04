
    function getCookieValue(name) {
      const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, "\$&");
      const match = document.cookie.match(new RegExp(`(?:^|; )${escaped}=([^;]*)`));
      return match ? decodeURIComponent(match[1]) : "";
    }

    function secureJsonHeaders() {
      const headers = { "Content-Type": "application/json" };
      const csrf = getCookieValue("pawcircle_csrf_token");
      if (csrf) headers["X-CSRF-Token"] = csrf;
      return headers;
    }

    function secureUploadHeaders() {
      const csrf = getCookieValue("pawcircle_csrf_token");
      return csrf ? { "X-CSRF-Token": csrf } : {};
    }

    // =============================
    // SUPABASE AUTH BRIDGE
    // =============================
    // Supabase Auth is now the identity provider, but the PHP backend still
    // creates the existing HttpOnly PawCircle session cookie after it verifies the
    // Supabase access token and maps auth.users.id -> public.users.id.
    let pawcircleSupabaseClient = null;
    let pawcircleAuthConfigPromise = null;

    async function loadSupabaseAuthClient() {
      if (pawcircleSupabaseClient) return pawcircleSupabaseClient;

      if (!pawcircleAuthConfigPromise) {
        pawcircleAuthConfigPromise = fetch("pawcircle_api.php", {
          method: "POST",
          credentials: "include",
          headers: secureJsonHeaders(),
          body: JSON.stringify({ action: "auth_config" }),
        }).then(async (response) => {
          const data = await response.json().catch(() => null);
          if (!response.ok || data?.status !== "success") {
            throw new Error(data?.message || "Could not load Supabase Auth configuration.");
          }
          return data;
        });
      }

      const config = await pawcircleAuthConfigPromise;
      if (!window.supabase?.createClient) {
        throw new Error("Supabase Auth library did not load.");
      }

      pawcircleSupabaseClient = window.supabase.createClient(
        config.supabase_url,
        config.supabase_anon_key,
        {
          auth: {
            persistSession: true,
            autoRefreshToken: true,
            detectSessionInUrl: true,
          },
        },
      );

      return pawcircleSupabaseClient;
    }

    async function exchangeSupabaseAuthSession(session, hints = {}) {
      const accessToken = session?.access_token;
      if (!accessToken) {
        throw new Error("Missing Supabase Auth access token.");
      }

      const headers = secureJsonHeaders();
      headers.Authorization = `Bearer ${accessToken}`;

      const response = await fetch("pawcircle_api.php", {
        method: "POST",
        credentials: "include",
        headers,
        body: JSON.stringify({
          action: "supabase_auth_exchange",
          ...hints,
        }),
      });

      const data = await response.json().catch(() => null);
      if (!response.ok || data?.status !== "success") {
        throw new Error(data?.message || "Could not create PawCircle session from Supabase Auth.");
      }

      return data;
    }

    async function loginWithSupabaseAuth(email, password, hints = {}) {
      const client = await loadSupabaseAuthClient();
      const { data, error } = await client.auth.signInWithPassword({
        email,
        password,
      });

      if (error) {
        throw new Error(error.message || "Supabase Auth sign-in failed.");
      }

      return exchangeSupabaseAuthSession(data.session, hints);
    }

    async function loginWithOAuthProvider(provider, errorSurface = "public") {
      try {
        const client = await loadSupabaseAuthClient();
        const { error } = await client.auth.signInWithOAuth({
          provider: provider,
          options: {
            redirectTo: window.location.origin + window.location.pathname
          }
        });
        if (error) {
          throw error;
        }
        // The page redirects to the provider so no further logic runs.
      } catch (err) {
        console.warn("OAuth sign in failed:", err);
        showError(errorSurface, err.message || `Failed to continue with ${provider}.`);
      }
    }

    async function completeSupabaseSessionAfterOtp(email, password, hints = {}) {
      if (!email || !password) return null;
      try {
        return await loginWithSupabaseAuth(email, password, hints);
      } catch (err) {
        // The backend has already created the PawCircle HttpOnly session after the
        // OTP succeeds. This only tries to also persist the browser's Supabase
        // Auth session. If it fails, the app can still continue using the
        // backend session and the user can sign in normally next time.
        console.warn("Could not persist Supabase Auth session after OTP:", err);
        return null;
      }
    }

    async function restoreSupabaseAuthSession() {
      try {
        const client = await loadSupabaseAuthClient();
        const { data, error } = await client.auth.getSession();
        if (error || !data?.session) return null;
        return await exchangeSupabaseAuthSession(data.session);
      } catch (err) {
        console.warn("Supabase Auth restore failed:", err);
        return null;
      }
    }

    async function api(action, payload = {}, options = {}) {
      if (isOfflineDemoMode() || isOfflineDemoUser()) {
        return offlineApiResponse(action, payload);
      }

      const response = await fetch("pawcircle_api.php", {
        method: "POST",
        credentials: "include",
        headers: secureJsonHeaders(),
        body: JSON.stringify({
          action,
          ...payload,
        }),
      });

      const text = await response.text();

      let data;
      try {
        data = JSON.parse(text);
      } catch (err) {
        console.error("Backend did not return JSON. Raw response:", text);
        throw new Error("Backend error: invalid JSON response. Check PHP terminal/logs.");
      }

      if (isAdminModeExpiryResponse(response, data) && !options.skipAdminModeRenew) {
        await renewAdminModeSession();
        return api(action, payload, { ...options, skipAdminModeRenew: true });
      }

      return data;
    }

    async function loadPackDataFromDatabase() {
      if (!currentUserObj?.id) return;
      try {
        const data = await api("get_pet_pack_members", { user_id: currentUserObj.id });
        if (data.status !== "success") {
          console.warn("Pack data load failed:", data.message || data);
          return;
        }

        currentUserObj.packMembers = Array.isArray(data.pack_members) ? data.pack_members : [];
        const birth = data.birth_details || {};
        if (birth.dob) currentUserObj.dob = birth.dob;
        if (birth.birthTime) currentUserObj.birthTime = birth.birthTime;
        if (birth.city) currentUserObj.city = birth.city;
        if (birth.gender) {
          currentUserObj.gender = birth.gender;
          currentUserObj.socialProfile = currentUserObj.socialProfile || {};
          currentUserObj.socialProfile.gender = birth.gender;
        }

        persistCurrentSession(currentUserObj);
      } catch (err) {
        console.warn("Could not load pack data:", err);
      }
    }
    async function toggleNotificationsPanel() {
      const panel = document.getElementById("notifications-panel");
      panel.classList.toggle("hidden");
      if (!panel.classList.contains("hidden")) {
        dismissUnreadNotificationsPopup();
        await loadNotifications();
        // Ensure the synthetic "complete membership" entry renders even if the
        // backend fetch was skipped (e.g. no user id yet).
        renderNotifications();
      }
    }

    function closeNotificationsPanel() {
      document.getElementById("notifications-panel")?.classList.add("hidden");
    }

    function getUnreadNotificationsPopupStorageKey() {
      const userKey = currentUserObj?.id || currentUserObj?.email || "guest";
      return `${PAWCIRCLE_UNREAD_NOTIFICATION_POPUP_KEY}_${userKey}`;
    }

    function getDismissedUnreadNotificationsCount() {
      try {
        return Number(sessionStorage.getItem(getUnreadNotificationsPopupStorageKey()) || "0");
      } catch (e) {
        return 0;
      }
    }

    function setDismissedUnreadNotificationsCount(unreadCount) {
      try {
        sessionStorage.setItem(getUnreadNotificationsPopupStorageKey(), String(unreadCount));
      } catch (e) { }
    }

    function resetDismissedUnreadNotificationsPopup() {
      try {
        sessionStorage.removeItem(getUnreadNotificationsPopupStorageKey());
      } catch (e) { }
    }

    let unreadPopupAutoHideTimer = null;

    function updateUnreadNotificationsPopup(unreadCount) {
      const popup = document.getElementById("notifications-unread-popup");
      if (!popup) return;

      if (unreadCount < 3) {
        clearTimeout(unreadPopupAutoHideTimer);
        popup.classList.add("hidden");
        resetDismissedUnreadNotificationsPopup();
        return;
      }

      const dismissedAtCount = getDismissedUnreadNotificationsCount();
      if (dismissedAtCount >= unreadCount) {
        clearTimeout(unreadPopupAutoHideTimer);
        popup.classList.add("hidden");
        return;
      }

      const countText = document.getElementById("notifications-unread-popup-count");
      if (countText) {
        countText.textContent = `${unreadCount} unread notification${unreadCount === 1 ? "" : "s"}`;
      }
      popup.classList.remove("hidden");

      // Auto-dismiss the popup after 10 seconds.
      clearTimeout(unreadPopupAutoHideTimer);
      unreadPopupAutoHideTimer = setTimeout(() => {
        dismissUnreadNotificationsPopup();
      }, 10000);
    }

    function dismissUnreadNotificationsPopup() {
      const unreadCount = (globalNotifications || []).filter((n) => !n.is_read).length;
      setDismissedUnreadNotificationsCount(unreadCount);
      document.getElementById("notifications-unread-popup")?.classList.add("hidden");
    }

    document.addEventListener("click", (event) => {
      const panel = document.getElementById("notifications-panel");
      if (!panel || panel.classList.contains("hidden")) return;
      const target = event.target;
      if (panel.contains(target) || document.getElementById("notifications-toggle-btn")?.contains(target)) return;
      closeNotificationsPanel();
    });

    function formatNotificationTime(value) {
      if (!value) return "";
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) return "";
      const diffMs = Date.now() - date.getTime();
      const diffMinutes = Math.floor(diffMs / 60000);
      if (diffMinutes < 1) return "Just now";
      if (diffMinutes < 60) return `${diffMinutes} min ago`;
      const diffHours = Math.floor(diffMinutes / 60);
      if (diffHours < 24) return `${diffHours} hour${diffHours === 1 ? "" : "s"} ago`;
      const diffDays = Math.floor(diffHours / 24);
      if (diffDays < 7) return `${diffDays} day${diffDays === 1 ? "" : "s"} ago`;
      return date.toLocaleDateString();
    }

    function notificationIconHtml(notification) {
      if (notification.type === "friend_request" || notification.type === "friend_request_sent") {
        return `<div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="user-plus" class="w-5 h-5"></i></div>`;
      }
      if (notification.type === "friend_request_accepted") {
        return `<div class="w-10 h-10 bg-green-100 text-green-600 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="user-check" class="w-5 h-5"></i></div>`;
      }
      if (notification.type === "direct_message") {
        return `<div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="message-circle" class="w-5 h-5"></i></div>`;
      }
      if (notification.type === "group_message") {
        return `<div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="messages-square" class="w-5 h-5"></i></div>`;
      }
      if (notification.type === "call_invite") {
        const icon = notification.data?.call_type === "video" ? "video" : "phone";
        return `<div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="${icon}" class="w-5 h-5"></i></div>`;
      }
      if (notification.type === "event_invite") {
        return `<div class="w-10 h-10 bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="calendar-plus" class="w-5 h-5"></i></div>`;
      }
      if (notification.type === "playdate_interest" || notification.type === "playdate_interest" || notification.type === "playdate_mutual_match") {
        return `<div class="w-10 h-10 bg-pink-100 dark:bg-pink-900/40 text-pink-600 dark:text-pink-300 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="heart" class="w-5 h-5 fill-current"></i></div>`;
      }
      return `<div class="w-10 h-10 bg-brand-100 text-brand-600 rounded-full flex items-center justify-center flex-shrink-0"><i data-lucide="bell" class="w-5 h-5"></i></div>`;
    }

    function renderNotifications() {
      const list = document.getElementById("notifications-list");
      const unreadBadge = document.getElementById("notifications-unread-count");
      const bellBadge = document.getElementById("notifications-bell-badge");
      if (!list || !unreadBadge) return;

      // Inject a synthetic "complete your membership" notification while setup is incomplete.
      const items = [...globalNotifications];
      if (currentUserObj && !currentUserObj.membership_applied) {
        items.unshift({
          id: COMPLETE_PROFILE_NOTIFICATION_ID,
          type: "complete_profile",
          title: "Complete your membership application",
          body: "Finish your profile details to unlock all breed features.",
          created_at: new Date().toISOString(),
          is_read: false,
        });
      }

      const unreadCount = items.filter((n) => !n.is_read).length;
      unreadBadge.textContent = `${unreadCount} New`;
      if (bellBadge) {
        if (unreadCount > 0) {
          bellBadge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
          bellBadge.classList.remove("hidden");
        } else {
          bellBadge.classList.add("hidden");
          bellBadge.textContent = "0";
        }
      }

      updateUnreadNotificationsPopup(unreadCount);

      if (!items.length) {
        list.innerHTML = `<div class="p-4 text-sm text-gray-500 dark:text-gray-400">No notifications yet.</div>`;
        return;
      }

      list.innerHTML = items.map((notification) => {
        if (notification.type === "complete_profile") {
          return `
          <div data-notification-open="${COMPLETE_PROFILE_NOTIFICATION_ID}" class="p-4 border-b border-gray-50 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/80 transition-colors text-sm flex gap-3 cursor-pointer min-w-0 bg-brand-50/40 dark:bg-brand-950/20">
            <div class="w-9 h-9 rounded-full bg-brand-100/50 dark:bg-brand-900/40 flex items-center justify-center flex-shrink-0">
              <i data-lucide="clipboard-list" class="w-4 h-4 text-brand-600 dark:text-brand-300"></i>
            </div>
            <div class="min-w-0 flex-1 break-words">
              <div class="flex items-start justify-between gap-3">
                <p class="font-bold text-gray-800 dark:text-gray-200">${escapeHtml(notification.title)}</p>
                <span class="inline-flex items-center rounded-full bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-300 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">New</span>
              </div>
              <p class="text-gray-700 dark:text-gray-300 mt-0.5">${escapeHtml(notification.body)}</p>
              <div class="mt-2">
                <span class="inline-flex items-center gap-1 text-xs font-bold text-brand-600 dark:text-brand-300">Complete now <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i></span>
              </div>
            </div>
          </div>`;
        }
        const data = notification.data || {};
        const friendshipId = data.friendship_id || "";
        const isUnread = !notification.is_read;
        const notificationId = notification.id || "";
        const actionState = notificationFriendActionState[String(friendshipId)] || {};
        const friendshipStatus = notification.friendship_status || data.friendship_status || "";
        const newBadgeHtml = isUnread
          ? `<span class="inline-flex items-center rounded-full bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-300 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide">New</span>`
          : "";
        let actionsHtml = "";

        if (notification.type === "friend_request" && friendshipId) {
          if (actionState.status === "loading") {
            actionsHtml = `<div class="flex items-center gap-2 mt-2 text-xs font-bold text-brand-600 dark:text-brand-300"><i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> ${actionState.action === "accept" ? "Accepting request..." : "Declining request..."}</div>`;
          } else if (actionState.status === "accepted") {
            actionsHtml = `<div class="flex items-center gap-2 mt-2 text-xs font-bold text-green-700 dark:text-green-300"><i data-lucide="check-circle-2" class="w-4 h-4"></i> Friend request accepted.</div>`;
          } else if (actionState.status === "declined") {
            actionsHtml = `<div class="flex items-center gap-2 mt-2 text-xs font-bold text-gray-600 dark:text-gray-300"><i data-lucide="x-circle" class="w-4 h-4"></i> Friend request declined.</div>`;
          } else if (friendshipStatus === "accepted") {
            actionsHtml = `<div class="flex items-center gap-2 mt-2 text-xs font-bold text-green-700 dark:text-green-300"><i data-lucide="check-circle-2" class="w-4 h-4"></i> Friend request accepted.</div>`;
          } else if (friendshipStatus && friendshipStatus !== "pending") {
            actionsHtml = `<div class="flex items-center gap-2 mt-2 text-xs font-bold text-gray-600 dark:text-gray-300"><i data-lucide="x-circle" class="w-4 h-4"></i> Friend request no longer pending.</div>`;
          } else {
            const errorHtml = actionState.status === "failed"
              ? `<p class="text-xs font-semibold text-red-600 dark:text-red-300 mt-2">${escapeHtml(actionState.message || "Request failed. Try again.")}</p>`
              : "";
            actionsHtml = `${errorHtml}<div class="flex gap-2 mt-2">
              <button type="button" data-notification-friend-action="accept" data-friendship-id="${escapeHtml(friendshipId)}" class="cursor-pointer bg-brand-500 hover:bg-brand-600 text-white px-3 py-1 rounded-lg text-xs font-bold">Accept</button>
              <button type="button" data-notification-friend-action="decline" data-friendship-id="${escapeHtml(friendshipId)}" class="cursor-pointer bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 px-3 py-1 rounded-lg text-xs font-bold">Decline</button>
            </div>`;
          }
        }

        return `
          <div data-notification-open="${escapeHtml(notificationId)}" class="p-4 border-b border-gray-50 dark:border-gray-800/50 hover:bg-gray-50 dark:hover:bg-gray-800/80 transition-colors text-sm flex gap-3 cursor-pointer min-w-0 ${isUnread ? "bg-brand-50/40 dark:bg-brand-950/20" : ""}">
            ${notificationIconHtml(notification)}
            <div class="min-w-0 flex-1 break-words">
              <div class="flex items-start justify-between gap-3">
                <p class="font-bold text-gray-800 dark:text-gray-200">${escapeHtml(notification.title || "Notification")}</p>
                ${newBadgeHtml}
              </div>
              <p class="text-gray-700 dark:text-gray-300 mt-0.5">${escapeHtml(notification.body || "")}</p>
              <p class="text-xs text-gray-500 mt-1">${escapeHtml(formatNotificationTime(notification.created_at))}</p>
              ${actionsHtml}
            </div>
          </div>`;
      }).join("");

      lucide.createIcons();
      bindNotificationActionButtons();
    }

    function bindNotificationActionButtons() {
      document.querySelectorAll("[data-notification-friend-action]").forEach((button) => {
        button.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();
          respondToNotificationFriendRequest(
            button,
            button.getAttribute("data-friendship-id"),
            button.getAttribute("data-notification-friend-action")
          );
        });
      });

      document.querySelectorAll("[data-notification-join-call]").forEach((button) => {
        button.addEventListener("click", async (event) => {
          event.preventDefault();
          event.stopPropagation();
          const notificationId = button.getAttribute("data-notification-id");
          if (notificationId) await markNotificationRead(notificationId);
          const panel = document.getElementById("notifications-panel");
          if (panel) panel.classList.add("hidden");
          joinZoomCall(button.getAttribute("data-notification-join-call"));
        });
      });

      document.querySelectorAll("[data-notification-open]").forEach((row) => {
        row.addEventListener("click", (event) => {
          event.preventDefault();
          event.stopPropagation();
          openNotificationTarget(row.getAttribute("data-notification-open"));
        });
      });
    }

    async function openNotificationTarget(notificationId) {
      if (notificationId === COMPLETE_PROFILE_NOTIFICATION_ID) {
        goToCompleteProfile();
        return;
      }
      const notification = globalNotifications.find((n) => String(n.id) === String(notificationId));
      if (!notification) return;

      await markNotificationRead(notificationId);
      const panel = document.getElementById("notifications-panel");
      if (panel) panel.classList.add("hidden");

      const data = notification.data || {};
      if (notification.type === "direct_message") {
        await openFriendChatFromNotification(data.sender_id);
        return;
      }

      if (notification.type === "group_message") {
        await openGroupChatFromNotification(data.group_id);
        return;
      }

      if (notification.type === "call_invite") {
        if (data.target_type === "group" && data.group_id) {
          await openGroupChatFromNotification(data.group_id);
          return;
        }
        await openFriendChatFromNotification(data.caller_id);
        return;
      }

      if (notification.type === "event_invite") {
        switchSocialTab("events");
        if (currentUserObj?.id) {
          await loadRealEvents();
        }
        setTimeout(() => focusEventCard(data.event_id), 150);
      }

      if (notification.type === "playdate_interest" || notification.type === "playdate_interest" || notification.type === "playdate_mutual_match") {
        switchSocialTab("playdate");
        setTimeout(() => {
          if (typeof switchPlaydateTab === "function") switchPlaydateTab("requests");
        }, 200);
      }
    }

    async function openFriendChatFromNotification(friendId) {
      if (!friendId) return;
      switchSocialTab("friends");
      if (!findFriendById(friendId)) {
        await loadRealFriends();
      }
      openFriendChat(friendId);
    }

    async function openGroupChatFromNotification(groupId) {
      if (!groupId) return;
      switchSocialTab("groups");

      let groups = getCustomGroups();
      let group = groups.find((g) => String(g.db_id || g.id) === String(groupId));
      if (!group && typeof loadRealGroups === "function") {
        await loadRealGroups();
        groups = getCustomGroups();
        group = groups.find((g) => String(g.db_id || g.id) === String(groupId));
      }

      if (group) {
        openGroupChat(group.id || group.db_id);
      } else {
        renderGroups();
        showToast("Group chat is not available yet.");
      }
    }

    async function loadNotifications() {
      if (!currentUserObj?.id) return;
      const list = document.getElementById("notifications-list");
      if (list) {
        list.innerHTML = `<div class="p-4 text-sm text-gray-500 dark:text-gray-400">Loading notifications...</div>`;
      }

      try {
        const data = await api("get_notifications", {
          user_id: currentUserObj.id,
          limit: 30,
        });
        if (data.status !== "success") {
          throw new Error(data.message || "Could not load notifications.");
        }
        globalNotifications = data.notifications || [];
        if (typeof mergeLocalPlaydateNotifications === "function") mergeLocalPlaydateNotifications();
        renderNotifications();
      } catch (err) {
        console.error(err);
        if (list) {
          list.innerHTML = `<div class="p-4 text-sm text-red-500">Could not load notifications.</div>`;
        }
      }
    }

    async function refreshNotificationsInBackground() {
      if (!currentUserObj?.id) return;
      if (document.hidden) return;
      try {
        const data = await api("get_notifications", {
          user_id: currentUserObj.id,
          limit: 30,
        });
        if (data.status !== "success") return;
        globalNotifications = data.notifications || [];
        if (typeof mergeLocalPlaydateNotifications === "function") mergeLocalPlaydateNotifications();
        renderNotifications();
        if (currentOpenFriendChatId) {
          const hasOpenChatMessage = globalNotifications.some((notification) => {
            return notification.type === "direct_message"
              && !notification.is_read
              && String(notification.data?.sender_id || "") === String(currentOpenFriendChatId);
          });
          if (hasOpenChatMessage) {
            await loadDirectMessagesInBackground(currentOpenFriendChatId, { silent: true });
          }
        }
        const friendsTab = document.getElementById("social-tab-friends");
        if (friendsTab && !friendsTab.classList.contains("hidden") && !currentOpenFriendChatId) {
          renderFriends();
        }
      } catch (err) {
        console.warn("Notification refresh failed:", err);
      }
    }

    let eventPollTimer = null;

    function ensureEventNotifications() {
      if (!currentUserObj?.id) return;
      const allEvents = typeof getAllLocalEvents === 'function' ? getAllLocalEvents() : [];
      const responses = getEventResponses();

      const now = new Date();
      allEvents.forEach(ev => {
        const isAttending = responses[String(ev.id)]?.rsvp || responses[String(ev.db_id)]?.rsvp || isOwnEvent(ev.id);
        if (!isAttending) return;
        if (!ev.date || !ev.time) return;

        const eventTimeStr = ev.date + "T" + ev.time;
        const evDate = new Date(eventTimeStr);
        if (isNaN(evDate.getTime())) return;

        const diffMs = evDate.getTime() - now.getTime();
        // Check if within next 60 seconds
        if (diffMs > 0 && diffMs <= 60000) {
          const notifId = "event_reminder_" + (ev.id || ev.db_id);
          const alreadyNotified = globalNotifications.find(n => n.id === notifId);
          if (!alreadyNotified) {
            globalNotifications.unshift({
              id: notifId,
              type: "system",
              title: "Event Starting Soon!",
              message: `Your event "${ev.title}" is starting in 1 minute.`,
              is_read: false,
              created_at: new Date().toISOString(),
              data: { event_id: ev.id || ev.db_id }
            });
            try { renderNotifications(); } catch (e) { }
            showToast(`Event "${ev.title}" is starting in 1 minute!`, "info");
          }
        }
      });
    }

    function startNotificationPolling() {
      if (notificationsPollTimer) clearInterval(notificationsPollTimer);
      notificationsPollTimer = setInterval(refreshNotificationsInBackground, 60000);

      if (eventPollTimer) clearInterval(eventPollTimer);
      eventPollTimer = setInterval(ensureEventNotifications, 10000);
    }

    function stopNotificationPolling() {
      if (notificationsPollTimer) {
        clearInterval(notificationsPollTimer);
        notificationsPollTimer = null;
      }
      if (eventPollTimer) {
        clearInterval(eventPollTimer);
        eventPollTimer = null;
      }
    }

    function markDirectMessageNotificationsRead(friendId) {
      if (!currentUserObj?.id || !friendId) return;
      const toMark = (globalNotifications || []).filter((notification) => {
        return notification.type === "direct_message"
          && !notification.is_read
          && String(notification.data?.sender_id || "") === String(friendId);
      });

      if (!toMark.length) return;
      toMark.forEach((notification) => {
        notification.is_read = true;
        if (notification.id) {
          api("mark_notification_read", {
            user_id: currentUserObj.id,
            notification_id: notification.id,
          }).catch((err) => console.warn("Could not mark message notification read:", err));
        }
      });
      renderNotifications();
      if (!currentOpenFriendChatId) renderFriends();
    }

    async function markNotificationRead(notificationId) {
      if (!currentUserObj?.id || !notificationId) return;
      const notification = globalNotifications.find((n) => String(n.id) === String(notificationId));
      if (!notification || notification.is_read) return;
      notification.is_read = true;
      renderNotifications();

      try {
        await api("mark_notification_read", {
          user_id: currentUserObj.id,
          notification_id: notificationId,
        });
      } catch (err) {
        console.warn("Could not mark notification read:", err);
      }
    }

    async function respondToNotificationFriendRequest(button, friendshipId, action) {
      if (!currentUserObj?.id || !friendshipId) return;
      const key = String(friendshipId);
      if (notificationFriendActionState[key]?.status === "loading") return;
      if (button) {
        button.disabled = true;
        button.closest("div")?.querySelectorAll("button").forEach((btn) => {
          btn.disabled = true;
        });
      }

      notificationFriendActionState[key] = { status: "loading", action };
      renderNotifications();

      try {
        const data = await api("respond_friend_request", {
          user_id: currentUserObj.id,
          friendship_id: friendshipId,
          response_action: action,
        });
        if (data.status !== "success") {
          throw new Error(data.message || "Could not update friend request.");
        }
        notificationFriendActionState[key] = {
          status: action === "accept" ? "accepted" : "declined",
          action,
        };

        const notification = globalNotifications.find((n) => String(n?.data?.friendship_id || "") === key);
        if (notification) {
          notification.is_read = true;
          if (notification.id) {
            api("mark_notification_read", {
              user_id: currentUserObj.id,
              notification_id: notification.id,
            }).catch((err) => console.warn("Could not mark notification read:", err));
          }
        }

        renderNotifications();
        await loadRealFriends();
        showToast(action === "accept" ? "Friend request accepted." : "Friend request declined.");
      } catch (err) {
        console.error(err);
        notificationFriendActionState[key] = {
          status: "failed",
          action,
          message: err.message || "Could not update friend request.",
        };
        renderNotifications();
        showToast(err.message || "Could not update friend request.");
      }
      return false;
    }
    function openAnnouncementModal() {
      document
        .getElementById("announcement-modal")
        .classList.remove("hidden");
    }
    function closeAnnouncementModal() {
      document.getElementById("announcement-modal").classList.add("hidden");
    }
    function submitAnnouncement() {
      closeAnnouncementModal();
      showToast("Announcement posted successfully!");
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
      toast.innerHTML = `<i data-lucide="${variant.icon}" class="w-4 h-4 flex-shrink-0"></i> <span class="min-w-0 break-words">${escapeHtml(String(message ?? ""))}</span>`;
      document.body.appendChild(toast);
      lucide.createIcons();

      setTimeout(() => {
        toast.classList.remove("-translate-y-full", "opacity-0");
      }, 100);
      setTimeout(() => {
        toast.classList.add("-translate-y-full", "opacity-0");
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    // Reusable themed confirm/alert dialog. Returns a Promise<boolean>.
    // showConfirm("Title", "Body text", { confirmText, cancelText, danger, icon })
    function showConfirm(title, message = "", options = {}) {
      return new Promise((resolve) => {
        const {
          confirmText = "Confirm",
          cancelText = "Cancel",
          danger = false,
          icon = danger ? "alert-triangle" : "help-circle",
          hideCancel = false,
        } = options;

        const overlay = document.createElement("div");
        overlay.className =
          "fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm opacity-0 transition-opacity duration-200";

        const accent = danger
          ? { ring: "bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-300", btn: "bg-red-600 hover:bg-red-700" }
          : { ring: "bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300", btn: "bg-brand-500 hover:bg-brand-600" };

        overlay.innerHTML = `
          <div class="w-full max-w-sm bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800 p-6 transform scale-95 transition-transform duration-200">
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0 w-11 h-11 rounded-full flex items-center justify-center ${accent.ring}">
                <i data-lucide="${escapeHtml(icon)}" class="w-5 h-5"></i>
              </div>
              <div class="min-w-0 flex-1">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">${escapeHtml(String(title ?? ""))}</h3>
                ${message ? `<p class="mt-1 text-sm text-gray-600 dark:text-gray-300 break-words">${escapeHtml(String(message))}</p>` : ""}
              </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
              ${hideCancel ? "" : `<button data-act="cancel" class="px-4 py-2 rounded-xl text-base font-bold shadow-md text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">${escapeHtml(cancelText)}</button>`}
              <button data-act="confirm" class="px-4 py-2 rounded-xl text-base font-bold shadow-md text-white ${accent.btn} transition-colors">${escapeHtml(confirmText)}</button>
            </div>
          </div>`;

        document.body.appendChild(overlay);
        if (window.lucide) lucide.createIcons();
        const card = overlay.firstElementChild;
        requestAnimationFrame(() => {
          overlay.classList.remove("opacity-0");
          card.classList.remove("scale-95");
        });

        let settled = false;
        const close = (result) => {
          if (settled) return;
          settled = true;
          overlay.classList.add("opacity-0");
          card.classList.add("scale-95");
          document.removeEventListener("keydown", onKey);
          setTimeout(() => overlay.remove(), 200);
          resolve(result);
        };
        const onKey = (e) => {
          if (e.key === "Escape") close(false);
          else if (e.key === "Enter") close(true);
        };

        overlay.addEventListener("click", (e) => {
          const act = e.target.closest("[data-act]")?.dataset.act;
          if (act === "confirm") close(true);
          else if (act === "cancel") close(false);
          else if (e.target === overlay) close(false);
        });
        document.addEventListener("keydown", onKey);
      });
    }

    // Lightweight informational dialog (single OK button) — drop-in for alert().
    function showAlert(message, title = "Notice", options = {}) {
      return showConfirm(title, message, {
        confirmText: options.confirmText || "OK",
        hideCancel: true,
        danger: options.danger || false,
        icon: options.icon,
      });
    }

    function toggleEventInterest(eventId, interested) {
      const btnContainer = document.getElementById(
        `event-action-btns-${eventId}`,
      );
      if (!btnContainer) return;
      if (interested) {
        btnContainer.innerHTML = `<button class="w-full bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 py-2 rounded-lg text-base font-bold shadow-md transition-colors flex items-center justify-center gap-2 shadow-sm"><i data-lucide="check-circle-2" class="w-4 h-4"></i> You're Interested</button>`;
        lucide.createIcons();
        showToast("Event saved! We will send you a reminder notification.");
      } else {
        btnContainer.closest(".bg-white").style.display = "none";
        showToast("Event removed from your suggestions.");
      }
    }

    function payRespects(id, btnElement) {
      if (btnElement.classList.contains("respect-paid")) return;
      const countSpan = btnElement.querySelector(".respect-count");
      let count = parseInt(countSpan.innerText);
      countSpan.innerText = count + 1;
      btnElement.classList.add(
        "respect-paid",
        "bg-gray-100",
        "dark:bg-gray-800",
      );
      btnElement.classList.remove("bg-white", "dark:bg-gray-900");
      showToast("You paid your respects.");
    }

    let activeCondolenceObitId = null;
    function openCondolenceModal(id, name) {
      activeCondolenceObitId = id;
      document.getElementById("condolence-name").innerText = name;
      document.getElementById("condolence-text").value = "";
      document.getElementById("condolence-modal").classList.remove("hidden");
    }

    function closeCondolenceModal() {
      document.getElementById("condolence-modal").classList.add("hidden");
    }

    function submitCondolence() {
      const text = document.getElementById("condolence-text").value.trim();
      if (text && activeCondolenceObitId !== null) {
        const obit = globalObits.find((o) => o.id === activeCondolenceObitId);
        if (obit) {
          if (!obit.condolences) obit.condolences = [];
          obit.condolences.push({
            id: Date.now(),
            sender: currentUserObj.socialProfile.name,
            text: text,
            likes: 0,
            likedByMe: false,
            replies: [],
          });
          renderObituaries();
        }
      }
      closeCondolenceModal();
      showToast("Your condolences have been sent to the pack.");
    }

    function likeCondolence(obitId, commentId) {
      const obit = globalObits.find((o) => o.id === obitId);
      const comment = obit.condolences.find((c) => c.id === commentId);
      if (comment.likedByMe) {
        comment.likes = (comment.likes || 1) - 1;
        comment.likedByMe = false;
      } else {
        comment.likes = (comment.likes || 0) + 1;
        comment.likedByMe = true;
      }
      renderObituaries();
    }

    function toggleReplyInput(commentId) {
      const el = document.getElementById(`reply-container-${commentId}`);
      if (el) {
        el.classList.toggle("hidden");
        if (!el.classList.contains("hidden")) {
          document.getElementById(`reply-input-${commentId}`).focus();
        }
      }
    }

    function submitReply(obitId, commentId) {
      const input = document.getElementById(`reply-input-${commentId}`);
      if (!input) return;
      const text = input.value.trim();
      if (text) {
        const obit = globalObits.find((o) => o.id === obitId);
        const comment = obit.condolences.find((c) => c.id === commentId);
        if (!comment.replies) comment.replies = [];
        comment.replies.push({
          id: Date.now(),
          sender: currentUserObj.socialProfile.name,
          text: text,
        });
        renderObituaries();
      }
    }

    async function acceptFriendRequest(id) {
      const reqIdx = globalFriendRequests.findIndex((r) => String(r.id) === String(id) || String(r.friendship_id) === String(id));
      if (reqIdx === -1) return;

      const req = globalFriendRequests[reqIdx];
      const requestKey = String(req.friendship_id || req.id);
      if (friendRequestActionState[requestKey]?.status === "loading") return;
      friendRequestActionState[requestKey] = { status: "loading", action: "accept" };
      renderFriends();

      try {
        if (req.friendship_id && currentUserObj?.id) {
          const data = await api("respond_friend_request", {
            user_id: currentUserObj.id,
            friendship_id: req.friendship_id,
            response_action: "accept",
          });

          if (data.status !== "success") {
            throw new Error(data.message || "Could not accept request.");
          }
        }

        friendRequestActionState[requestKey] = { status: "accepted", action: "accept" };
        globalFriendRequests.splice(reqIdx, 1);
        globalFriends.push({
          ...req,
          id: req.id,
          user_id: req.user_id || req.id,
          friendship_id: req.friendship_id,
          name: req.name,
          role: req.role,
          initials: req.initials,
          color: req.color,
          photo: req.photo || null,
        });

        updateFriendCountDisplays();
        renderFriends();
        await loadRealFriends();
        const notificationsPanel = document.getElementById("notifications-panel");
        if (notificationsPanel && !notificationsPanel.classList.contains("hidden")) {
          await loadNotifications();
        }
        showToast(`You are now friends with ${req.name}`);
      } catch (err) {
        console.error(err);
        friendRequestActionState[requestKey] = {
          status: "failed",
          action: "accept",
          message: err.message || "Could not accept friend request.",
        };
        renderFriends();
        showToast(err.message || "Could not accept friend request.");
      }
    }

    async function removeFriend(id) {
      try {
        const data = await api("remove_friend", {
          friend_id: id,
          user_id: currentUserObj.id
        });
        if (data.action === "removed") {
          globalFriends = globalFriends.filter((f) => String(f.id) !== String(id) && String(f.user_id) !== String(id));
          if (typeof renderFriends === "function") renderFriends();
          if (typeof updateFriendCountDisplays === "function") updateFriendCountDisplays();
          showToast("Friend removed.");
          closeUserProfile();
        }
      } catch (err) {
        console.error("Failed to remove friend", err);
        showToast(err.message || "Could not remove friend.");
      }
    }

    async function declineFriendRequest(id) {
      const req = globalFriendRequests.find((r) => String(r.id) === String(id) || String(r.friendship_id) === String(id));
      if (!req) return;
      const requestKey = String(req.friendship_id || req.id);
      if (friendRequestActionState[requestKey]?.status === "loading") return;
      friendRequestActionState[requestKey] = { status: "loading", action: "decline" };
      renderFriends();

      try {
        if (req?.friendship_id && currentUserObj?.id) {
          const data = await api("respond_friend_request", {
            user_id: currentUserObj.id,
            friendship_id: req.friendship_id,
            response_action: "decline",
          });

          if (data.status !== "success") {
            throw new Error(data.message || "Could not decline request.");
          }
        }

        friendRequestActionState[requestKey] = { status: "declined", action: "decline" };
        globalFriendRequests = globalFriendRequests.filter((r) => String(r.id) !== String(id) && String(r.friendship_id) !== String(id));
        renderFriends();
        await loadRealFriends();
        const notificationsPanel = document.getElementById("notifications-panel");
        if (notificationsPanel && !notificationsPanel.classList.contains("hidden")) {
          await loadNotifications();
        }
        showToast(`Friend request declined.`);
      } catch (err) {
        console.error(err);
        friendRequestActionState[requestKey] = {
          status: "failed",
          action: "decline",
          message: err.message || "Could not decline friend request.",
        };
        renderFriends();
        showToast(err.message || "Could not decline friend request.");
      }
    }


    // ── Real-data loaders// ── Real-data loaders ─────────────────────────────────────────────
    function applyBootstrapPosts(posts) {
      feedPosts = (posts || []).map(normalizePostFromApi);
      postsLoading = false;
      postsLoadFailed = false;
      renderFeed();
    }

    function applyBootstrapFriends(friends, requests) {
      const colors = [
        "#e74c3c",
        "#3498db",
        "#2ecc71",
        "#9b59b6",
        "#f39c12",
        "#1abc9c",
      ];

      globalFriends = friends.map((f, i) => ({
        id: f.user_id,
        user_id: f.user_id,
        friendship_id: f.friendship_id,
        name: f.name || "Member",
        initials: getSocialAvatar(f.name || "Member"),
        color: colors[i % colors.length],
        role: "Member",
        photo: f.photo || f.profile_photo_url || null,
        pet_type: f.pet_type || null,
        breed: f.breed || null,
        admin_capabilities: f.admin_capabilities || [],
        custom_tags: f.custom_tags || f.primary_interests || [],
        system_tags: f.system_tags || [],
        tags: f.tags || [],
      }));

      globalFriendRequests = requests.map((r, i) => ({
        id: r.user_id,
        user_id: r.user_id,
        friendship_id: r.friendship_id,
        name: r.name || "Member",
        initials: getSocialAvatar(r.name || "Member"),
        color: colors[i % colors.length],
        role: "Member",
        photo: r.photo || r.profile_photo_url || null,
        pet_type: r.pet_type || null,
        breed: r.breed || null,
        admin_capabilities: r.admin_capabilities || [],
        custom_tags: r.custom_tags || r.primary_interests || [],
        system_tags: r.system_tags || [],
        tags: r.tags || [],
      }));

      friendsLoading = false;
      renderFriends();
    }

    // Demo/seed groups to hide from the UI (rows still exist in the DB).
    const HIDDEN_GROUP_NAMES = new Set(["scripture study group", "local rescues"]);

    function applyBootstrapGroups(groupsFromDb) {
      const joinedGroups = [];
      const suggestedFromDb = [];
      const key = "pawcircle_custom_groups_" + currentUserObj.email;
      const existing = JSON.parse(localStorage.getItem(key) || "[]");

      groupsFromDb.forEach((g) => {
        if (HIDDEN_GROUP_NAMES.has(String(g.name || "").trim().toLowerCase())) return;
        const existingGroup = existing.find((eg) => String(eg.db_id || eg.id) === String(g.id));
        const groupObj = {
          id: g.id,
          db_id: g.id,
          name: g.name,
          desc: g.description || "",
          description: g.description || "",
          avatar_url: g.avatar_url || null,
          member_count: g.member_count || 0,
          members: (g.members || []).map((m) => m.name),
          messages: existingGroup?.messages || [],
          callLogs: existingGroup?.callLogs || [],
          is_private: g.is_private,
          packKey: g.pack_key || existingGroup?.packKey,
          isPack: !!(g.pack_key || existingGroup?.packKey),
        };

        if (g.is_member) {
          joinedGroups.push(groupObj);
        } else {
          suggestedFromDb.push({
            id: "db_" + g.id,
            db_id: g.id,
            name: g.name,
            desc: g.description || "Join the conversation.",
            description: g.description || "",
            members: `${g.member_count || 0} members`,
            member_list: Array.isArray(g.members) ? g.members : [],
            member_count: g.member_count || 0,
            colors:
              "from-gray-50 to-gray-100 dark:from-gray-900/20 dark:to-gray-800/20 border-gray-100 dark:border-gray-800/30 text-gray-500",
            icon: "users",
            avatar_url: g.avatar_url || null,
          });
        }
      });

      const localOnly = existing.filter(
        (g) => !g.db_id && String(g.id || "").startsWith("g_")
      );

      saveCustomGroups([...joinedGroups, ...localOnly]);

      const staticSuggestions = globalSuggestedGroups.filter((sg) => !sg.db_id);
      globalSuggestedGroups = [...suggestedFromDb, ...staticSuggestions];

      if (currentOpenGroupChatId && document.getElementById("group-chat-messages")) {
        const groups = getCustomGroups();
        const activeGroup = findGroupByAnyId(groups, currentOpenGroupChatId);
        if (activeGroup) {
          refreshOpenGroupChatIfNeeded(activeGroup.id || activeGroup.db_id, activeGroup);
          return;
        }
      }

      renderGroups();
    }

    function applyBootstrapEvents(events) {
      const evts = JSON.parse(
        localStorage.getItem("pawcircle_calendar_events") || "[]"
      );

      events.forEach((e) => {
        const mappedEvent = {
          id: "db_" + e.id,
          db_id: e.id,
          date: e.event_date,
          title: e.event_time ? `${String(e.event_time).slice(0, 5)} - ${e.title}` : e.title,
          link: e.meeting_url,
          description: e.description || "",
          location: e.location || "",
          audience: e.breed ? `Pet Breed: ${e.breed}` : e.pet_type ? `Pet Type: ${e.pet_type}` : "Global",
          breed: e.breed || null,
          pet_type: e.pet_type || null,
        };
        const existingIndex = evts.findIndex((le) => le.db_id === e.id);
        if (existingIndex >= 0) {
          evts[existingIndex] = { ...evts[existingIndex], ...mappedEvent };
        } else {
          evts.push(mappedEvent);
        }
      });

      localStorage.setItem("pawcircle_calendar_events", JSON.stringify(evts));

      renderCalendar();
      if (typeof renderUpcomingEvents === "function") {
        renderUpcomingEvents();
      }
    }

    async function loadSocialBootstrap() {
      if (!currentUserObj?.id) return;

      try {
        const data = await api("social_bootstrap", {
          user_id: currentUserObj.id,
          breed: currentUserObj.breed || "",
          pet_type: currentUserObj.pet_type || "",
        });

        if (data.status !== "success") {
          console.error("social_bootstrap failed", data);
          postsLoading = false;
          postsLoadFailed = true;
          feedPosts = [];
          renderFeed();
          friendsLoading = false;
          renderFriends();
          return;
        }

        applyBootstrapPosts(data.posts || []);
        applyBootstrapFriends(data.friends || [], data.requests || []);
        applyBootstrapGroups(data.groups || []);
        applyBootstrapEvents(data.events || []);
      } catch (err) {
        console.error("Failed to load social bootstrap", err);
        postsLoading = false;
        postsLoadFailed = true;
        feedPosts = [];
        renderFeed();
        friendsLoading = false;
        renderFriends();
      }
    }

    async function loadRealPosts() {
      try {
        const data = await api('get_posts', {
          user_id: currentUserObj.id,
          breed: currentUserObj.breed,
          pet_type: currentUserObj.pet_type,
        });

        if (data.status !== 'success') {
          console.error("Failed to load posts", data);
          postsLoading = false;
          postsLoadFailed = true;
          renderFeed();
          return;
        }

        const realPosts = (data.posts || []).map(normalizePostFromApi);
        feedPosts = realPosts;
        postsLoading = false;
        postsLoadFailed = false;
        renderFeed();
      } catch (err) {
        console.error("Failed to load posts", err);
        postsLoading = false;
        postsLoadFailed = true;
        renderFeed();
      }
    }

    async function loadRealFriends() {
      try {
        const data = await api('get_friends', { user_id: currentUserObj.id });
        if (data.status !== 'success') {
          console.error("Failed to load friends", data);
          friendsLoading = false;
          renderFriends();
          return;
        }

        const colors = ['#e74c3c', '#3498db', '#2ecc71', '#9b59b6', '#f39c12', '#1abc9c'];
        globalFriends = (data.friends || []).map((f, i) => ({
          id: f.user_id,
          friendship_id: f.friendship_id,
          name: f.name,
          initials: getSocialAvatar(f.name),
          color: colors[i % colors.length],
          role: f.breed || 'Member',
          photo: f.photo || null,
          pet_type: f.pet_type || null,
          breed: f.breed || null,
        }));

        globalFriendRequests = (data.requests || []).map((r, i) => ({
          id: r.user_id,
          friendship_id: r.friendship_id,
          name: r.name,
          initials: getSocialAvatar(r.name),
          color: colors[i % colors.length],
          role: r.breed || 'Member',
          photo: r.photo || null,
        }));

        friendsLoading = false;
        renderFriends();
      } catch (err) {
        console.error("Failed to load friends", err);
        friendsLoading = false;
        renderFriends();
      }
    }

    async function loadRealGroups() {
      try {
        const data = await api('get_groups', {
          user_id: currentUserObj.id,
          breed: currentUserObj.breed,
          pet_type: currentUserObj.pet_type,
        });

        if (data.status !== 'success') {
          console.error("Failed to load groups", data);
          return;
        }

        const joinedGroups = [];
        const suggestedFromDb = [];
        const key = "pawcircle_custom_groups_" + currentUserObj.email;
        const existing = JSON.parse(localStorage.getItem(key) || "[]");

        (data.groups || []).forEach((g) => {
          if (HIDDEN_GROUP_NAMES.has(String(g.name || "").trim().toLowerCase())) return;
          const existingGroup = existing.find((eg) => String(eg.db_id || eg.id) === String(g.id));
          const groupObj = {
            id: g.id,
            db_id: g.id,
            name: g.name,
            desc: g.description || "",
            description: g.description || "",
            avatar_url: g.avatar_url || null,
            member_count: g.member_count || 0,
            members: (g.members || []).map((m) => m.name),
            messages: existingGroup?.messages || [],
            callLogs: existingGroup?.callLogs || [],
            is_private: g.is_private,
            packKey: g.pack_key || existingGroup?.packKey,
            isPack: !!(g.pack_key || existingGroup?.packKey),
          };

          if (g.is_member) {
            joinedGroups.push(groupObj);
          } else {
            suggestedFromDb.push({
              id: 'db_' + g.id,
              db_id: g.id,
              name: g.name,
              desc: g.description || "Join the conversation.",
              description: g.description || "",
              members: `${g.member_count || 0} members`,
              member_list: Array.isArray(g.members) ? g.members : [],
              member_count: g.member_count || 0,
              colors: "from-gray-50 to-gray-100 dark:from-gray-900/20 dark:to-gray-800/20 border-gray-100 dark:border-gray-800/30 text-gray-500",
              icon: "users",
              avatar_url: g.avatar_url || null,
            });
          }
        });

        const localOnly = existing.filter((g) =>
          (!g.db_id && String(g.id || "").startsWith("g_")) ||
          String(g.id || "").startsWith("event_group_")
        );

        saveCustomGroups([...joinedGroups, ...localOnly]);

        const staticSuggestions = globalSuggestedGroups.filter((sg) => !sg.db_id);
        globalSuggestedGroups = [...suggestedFromDb, ...staticSuggestions];

        if (currentOpenGroupChatId && document.getElementById("group-chat-messages")) {
          const groups = getCustomGroups();
          const activeGroup = findGroupByAnyId(groups, currentOpenGroupChatId);
          if (activeGroup) {
            refreshOpenGroupChatIfNeeded(activeGroup.id || activeGroup.db_id, activeGroup);
            return;
          }
        }

        renderGroups();
      } catch (err) {
        console.error("Failed to load groups", err);
      }
    }

    async function loadRealEvents() {
      try {
        const data = await api('get_events', {
          user_id: currentUserObj.id,
          breed: currentUserObj.breed,
          pet_type: currentUserObj.pet_type,
        });

        if (data.status !== 'success') {
          console.error("Failed to load events", data);
          return;
        }

        const localOnly = JSON.parse(localStorage.getItem("pawcircle_calendar_events") || "[]")
          .filter((e) => !e.db_id)
          .filter(eventVisibleToCurrentUser);

        const dbEvents = (data.events || [])
          .filter((e) => e.event_date)
          .map((e) => ({
            id: e.id,
            db_id: e.id,
            user_id: e.created_by,
            creator: e.creator || "",
            date: e.event_date,
            title: e.event_time ? `${String(e.event_time).slice(0, 5)} - ${e.title}` : e.title,
            link: e.meeting_url,
            description: e.description || "",
            location: e.location || "",
            audience: e.visibility === "invite_only"
              ? "Invite only"
              : e.breed ? `Pet Breed: ${e.breed}` : e.pet_type ? `Pet Type: ${e.pet_type}` : "Global",
            breed: e.breed || null,
            pet_type: e.pet_type || null,
            visibility: e.visibility || "public",
            invitee_ids: Array.isArray(e.invitee_ids) ? e.invitee_ids : [],
          }));

        localStorage.setItem("pawcircle_calendar_events", JSON.stringify([...localOnly, ...dbEvents]));
        renderCalendar();
        renderUpcomingEvents();
        if (document.getElementById("enlarged-calendar-grid")) {
          renderEnlargedCalendar();
        }
      } catch (err) {
        console.error("Failed to load events", err);
      }
    }


    // ── Pack Tree// ── Pack Tree & Members Logic ────────────────────────────────────
    function openPackMembersModal() {
      if (!currentUserObj.packMembers) {
        currentUserObj.packMembers = [];
      }

      // Populate friends import list
      const importSelect = document.getElementById('fam-import-friend');
      if (importSelect) {
        importSelect.innerHTML = '<option value="">Select a friend...</option>';
        if (typeof globalFriends !== 'undefined') {
          globalFriends.forEach(f => {
            importSelect.innerHTML += `<option value="${f.id}">${f.name}</option>`;
          });
        }
      }

      renderPackMembersList();
      document.getElementById('pack-members-modal').classList.remove('hidden');
      document.getElementById('pack-members-modal').classList.add('flex');
      lucide.createIcons();
    }

    function closePackMembersModal() {
      document.getElementById('pack-members-modal').classList.add('hidden');
      document.getElementById('pack-members-modal').classList.remove('flex');
    }

    function importFriendForPack(id) {
      if (!id) return;
      if (typeof globalFriends !== 'undefined') {
        const friend = globalFriends.find(f => f.id == id);
        if (friend) {
          document.getElementById('fam-name').value = friend.name;
        }
      }
    }

    async function addPackMember() {
      const name = document.getElementById('fam-name').value.trim();
      const relation = document.getElementById('fam-relation').value;
      const dob = document.getElementById('fam-dob').value;
      const edu = document.getElementById('fam-edu').value;
      const work = document.getElementById('fam-work').value;
      const pet_profile = document.getElementById('fam-pet_profile').value;

      if (!name) {
        showToast("Name is required!");
        return;
      }

      if (!currentUserObj.packMembers) currentUserObj.packMembers = [];

      const localMember = {
        id: Date.now(),
        name,
        relation,
        dob,
        edu,
        work,
        pet_profile
      };

      let savedMember = localMember;
      if (currentUserObj?.id) {
        try {
          const data = await api("save_pet_pack_member", {
            user_id: currentUserObj.id,
            name,
            relation,
            dob,
            edu,
            work,
            pet_profile,
          });
          if (data.status !== "success") throw new Error(data.message || "Save failed");
          savedMember = data.member || localMember;
        } catch (err) {
          console.warn("Could not save pack member to database:", err);
          showToast("Could not save pack member to database.");
          return;
        }
      }

      currentUserObj.packMembers.push(savedMember);

      // Clear form
      document.getElementById('fam-name').value = '';
      document.getElementById('fam-dob').value = '';
      document.getElementById('fam-edu').value = '';
      document.getElementById('fam-work').value = '';
      document.getElementById('fam-pet_profile').value = '';

      renderPackMembersList();
      refreshPackTreeIfVisible();
      showToast("Pack member added!");

      // Save to session/local storage
      persistCurrentSession(currentUserObj);
    }

    // Re-render the pack tree view live whenever members change while it is open.
    function refreshPackTreeIfVisible() {
      const treeView = document.getElementById("view-pack-tree");
      if (treeView && treeView.classList.contains("active") && typeof renderPackTree === "function") {
        renderPackTree();
      }
    }

    async function removePackMember(id) {
      if (currentUserObj.packMembers) {
        const before = currentUserObj.packMembers.slice();
        currentUserObj.packMembers = currentUserObj.packMembers.filter(m => String(m.id) !== String(id));
        renderPackMembersList();
        refreshPackTreeIfVisible();
        persistCurrentSession(currentUserObj);
        if (currentUserObj?.id) {
          try {
            const data = await api("delete_pet_pack_member", { user_id: currentUserObj.id, member_id: id });
            if (data.status !== "success") throw new Error(data.message || "Delete failed");
          } catch (err) {
            console.warn("Could not delete pack member from database:", err);
            currentUserObj.packMembers = before;
            renderPackMembersList();
            refreshPackTreeIfVisible();
            showToast("Could not delete pack member from database.");
            return;
          }
        }
        showToast("Pack member removed.");
      }
    }

    function renderPackMembersList() {
      const list = document.getElementById('pack-members-list');
      list.innerHTML = '';

      const members = currentUserObj.packMembers || [];
      if (members.length === 0) {
        list.innerHTML = '<p class="text-sm text-gray-500 dark:text-gray-400">No pack members added yet.</p>';
        return;
      }

      members.forEach(m => {
        const initials = getSocialAvatar(m.name);
        list.innerHTML += `
            <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-300 flex items-center justify-center font-bold">
                  ${initials}
                </div>
                <div>
                  <h4 class="font-bold text-gray-800 dark:text-gray-200 text-sm">${m.name}</h4>
                  <p class="text-xs text-gray-500 dark:text-gray-400">${m.relation} ${m.dob ? '• ' + m.dob : ''}</p>
                </div>
              </div>
              <button onclick="removePackMember('${String(m.id).replace(/'/g, "\\'")}')" class="text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 p-2 rounded-lg transition-colors">
                <i data-lucide="trash-2" class="w-4 h-4"></i>
              </button>
            </div>
          `;
      });
      lucide.createIcons();
    }

    function applyPackTreeTheme() {
      const view = document.getElementById('view-pack-tree');
      if (!view) return;
      const pet_type = currentUserObj.pet_type || currentUserObj.socialProfile?.pet_type || 'Dog';
      const accent = getFaithAccentColor(pet_type);

      const bgSrcMap = {
        Hindu: "img/bg_hindu.png",
        Muslim: "img/bg_muslim.png",
        Sikh: "img/bg_sikh.png",
        Christian: "img/bg_christian.png",
        Buddhist: "img/bg_buddhist.png",
        Jain: "img/bg_jain.png",
        Parsi: "img/bg_parsi.png",
      };
      const bgSrc = bgSrcMap[pet_type] || bgSrcMap.Hindu;

      view.classList.remove('bg-gray-50', 'dark:bg-gray-950');
      view.style.backgroundSize = 'cover';
      view.style.backgroundPosition = 'center';
      view.style.backgroundAttachment = 'fixed';

      const setBg = () => {
        const isDark = document.documentElement.classList.contains('dark');
        const tint = isDark
          ? 'linear-gradient(rgba(3,7,18,0.92), rgba(3,7,18,0.95))'
          : 'linear-gradient(rgba(249,250,251,0.86), rgba(249,250,251,0.92))';
        view.style.backgroundImage = `${tint}, url('${bgSrc}')`;
      };
      setBg();
      if (!view.dataset.bgObserverAttached) {
        new MutationObserver(setBg).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        view.dataset.bgObserverAttached = 'true';
      }

      // Theme the tree connectors / node borders / hearts with the faith accent.
      const treeContainer = document.getElementById('css-pack-tree-container');
      if (treeContainer) treeContainer.style.setProperty('--faith-primary', accent);
    }

    function openPackTree() {
      switchView('view-pack-tree');
      applyPackTreeTheme();
      renderPackTree();
      ftCenterView();
    }

    function ftCenterView() {
      requestAnimationFrame(() => {
        const container = document.getElementById('ft-pan-container');
        const stage = document.getElementById('ft-pan-stage');
        if (!container || !stage) return;
        const cW = container.clientWidth || container.offsetWidth;
        const cH = container.clientHeight || container.offsetHeight || 600;
        const sW = stage.scrollWidth;
        const sH = stage.scrollHeight;
        /* Reset scale then centre */
        const newScale = Math.min(1, cW / (sW || 1), cH / (sH || 1)) * 0.85;
        const scale = Math.max(0.3, newScale);
        const panX = (cW - sW * scale) / 2;
        const panY = 40;
        stage.style.transform = `translate(${panX}px,${panY}px) scale(${scale})`;
        /* Keep ftZoom/ftResetView in sync */
        window._ftState = { scale, panX, panY };
      });
    }

    function buildNodeHtml(name, subtitle, pet_typeTheme = null, id = null, opts = {}) {
      const initials = getSocialAvatar(name);
      // Map pet_type to a color hash roughly, unless an explicit accent is supplied.
      let colorHash = opts.accent || "#9ca3af";
      if (!opts.accent && pet_typeTheme) {
        const colors = ['#3b82f6', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444', '#ec4899'];
        let hash = 0;
        for (let i = 0; i < pet_typeTheme.length; i++) {
          hash = pet_typeTheme.charCodeAt(i) + ((hash << 5) - hash);
        }
        colorHash = colors[Math.abs(hash) % colors.length];
      }

      const safeId = String(id || 0).replace(/'/g, "\\'");
      const photoInner = opts.photo
        ? `<img src="${escapeHtml(opts.photo)}" alt="">`
        : initials;
      const selfRing = opts.self ? `box-shadow: 0 0 0 3px ${colorHash}, 0 4px 10px -2px rgba(0,0,0,.18);` : "";
      const selfBadge = opts.self
        ? `<div class="node-self-badge" style="background:${colorHash}">You</div>`
        : "";

      return `
          <div class="node-card ${opts.self ? "node-self" : ""} cursor-pointer hover:shadow-lg transition-shadow bg-white dark:bg-gray-800 p-2 border border-gray-100 dark:border-gray-700" onclick="openPackMemberProfile('${name.replace(/'/g, "\\'")}', '${String(subtitle).replace(/'/g, "\\'")}', '${safeId}')">
            <div class="node-photo" style="border-color: ${colorHash}; color: ${colorHash}; ${selfRing}">
              ${photoInner}
            </div>
            ${selfBadge}
            <div class="node-name">${name}</div>
            <div class="node-details">${subtitle}</div>
          </div>
        `;
    }

    function openPackMemberProfile(name, relation, id) {
      let member = null;
      if (id && currentUserObj.packMembers) {
        member = currentUserObj.packMembers.find(m => m.id == id);
      }

      const initials = getSocialAvatar(name);

      document.getElementById('view-member-avatar-text').innerText = initials;
      document.getElementById('view-member-name').innerText = name;
      document.getElementById('view-member-relation').innerText = relation;

      let detailsHtml = '';
      if (member) {
        if (member.dob) detailsHtml += `<div class="text-sm text-gray-800 dark:text-gray-200"><span class="text-gray-500 dark:text-gray-400">DOB:</span> <span class="font-medium">${member.dob}</span></div>`;
        if (member.edu) detailsHtml += `<div class="text-sm text-gray-800 dark:text-gray-200"><span class="text-gray-500 dark:text-gray-400">Education:</span> <span class="font-medium">${member.edu}</span></div>`;
        if (member.work) detailsHtml += `<div class="text-sm text-gray-800 dark:text-gray-200"><span class="text-gray-500 dark:text-gray-400">Work:</span> <span class="font-medium">${member.work}</span></div>`;
        if (member.pet_profile) detailsHtml += `<div class="text-sm text-gray-800 dark:text-gray-200"><span class="text-gray-500 dark:text-gray-400">Pet Profile:</span> <span class="font-medium">${member.pet_profile}</span></div>`;
      }

      if (!detailsHtml) {
        detailsHtml = `<p class="text-sm text-gray-500 dark:text-gray-400">No additional details available.</p>`;
      }

      document.getElementById('view-member-details-container').innerHTML = detailsHtml;
      document.getElementById('pack-member-profile-modal').classList.remove('hidden');
      document.getElementById('pack-member-profile-modal').classList.add('flex');
    }

    function closePackMemberProfile() {
      document.getElementById('pack-member-profile-modal').classList.add('hidden');
      document.getElementById('pack-member-profile-modal').classList.remove('flex');
    }

    function toggleFriendStatus() {
      showToast("Friend request sent!");
    }

    function updatePackTreeMeta(count) {
      const sub = document.getElementById('pack-tree-subtitle');
      if (sub) {
        sub.textContent = count > 0
          ? `${count} member${count === 1 ? "" : "s"} in your pack tree.`
          : "View and manage your pack hierarchy.";
      }
    }

    function renderPackTree() {
      const container = document.getElementById('css-pack-tree-container');
      const members = currentUserObj.packMembers || [];

      const pet_type = currentUserObj.pet_type || currentUserObj.socialProfile?.pet_type || "Hindu";
      const accent = getFaithAccentColor(pet_type);
      container.style.setProperty('--faith-primary', accent);
      updatePackTreeMeta(members.length);

      const userName = currentUserObj.socialProfile?.name || "You";

      // Empty state — no pack members added yet.
      if (members.length === 0) {
        container.innerHTML = `
          <div class="text-center py-8 px-6 max-w-md mx-auto">
            <div class="w-20 h-20 rounded-full mx-auto flex items-center justify-center mb-5" style="background:${accent}1a; color:${accent};">
              <i data-lucide="users-round" class="w-10 h-10"></i>
            </div>
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-2">Start your pack tree</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Add your parents, siblings, spouse and children to see them beautifully connected here.</p>
            <button onclick="openPackMembersModal()" style="background:${accent};" class="no-faith-hover inline-flex items-center gap-2 text-white font-bold px-5 py-2.5 rounded-xl shadow-lg text-sm hover:opacity-90 transition-opacity">
              <i data-lucide="user-plus" class="w-4 h-4"></i> Add a pack member
            </button>
          </div>`;
        lucide.createIcons();
        return;
      }

      // Categorize members
      const fathers = members.filter(m => m.relation === 'Father');
      const mothers = members.filter(m => m.relation === 'Mother');
      const spouses = members.filter(m => m.relation === 'Spouse');
      const children = members.filter(m => m.relation === 'Son' || m.relation === 'Daughter');
      const siblings = members.filter(m => m.relation === 'Brother' || m.relation === 'Sister');

      // Build the HTML tree string
      let html = '<ul class="tree-root">';

      const hasParents = fathers.length > 0 || mothers.length > 0;

      if (hasParents) {
        html += '<li class="has-children">';
        html += `<div class="spouse-container">`;
        if (fathers.length > 0) html += buildNodeHtml(fathers[0].name, 'Father', 'F', fathers[0].id);
        if (fathers.length > 0 && mothers.length > 0) {
          html += `<div class="spouse-link" style="width: 50%;"></div><i data-lucide="heart" class="spouse-heart w-4 h-4"></i>`;
        }
        if (mothers.length > 0) html += buildNodeHtml(mothers[0].name, 'Mother', 'M', mothers[0].id);
        html += `</div>`;
        html += `<ul>`;
      }

      // Render older siblings
      const olderSiblings = siblings.slice(0, Math.ceil(siblings.length / 2));
      const youngerSiblings = siblings.slice(Math.ceil(siblings.length / 2));

      olderSiblings.forEach(sib => {
        html += `<li>${buildNodeHtml(sib.name, sib.relation, sib.name, sib.id)}</li>`;
      });

      // User & Spouse layer
      let liClasses = [];
      if (spouses.length > 0) liClasses.push('has-spouse');
      if (children.length > 0) liClasses.push('has-children');

      html += `<li${liClasses.length > 0 ? ` class="${liClasses.join(' ')}"` : ''}>`;
      html += `<div class="spouse-container">`;
      html += buildNodeHtml(userName, 'Me', null, 'me', { self: true, accent: accent, photo: currentUserObj.profile_photo_url || null });
      if (spouses.length > 0) {
        html += `<div class="spouse-link" style="width: 50%;"></div><i data-lucide="heart" class="spouse-heart w-4 h-4 text-pink-500"></i>`;
        html += buildNodeHtml(spouses[0].name, 'Spouse', 'S', spouses[0].id);
      }
      html += `</div>`;

      // Children layer
      if (children.length > 0) {
        html += `<ul>`;
        children.forEach(child => {
          html += `<li>${buildNodeHtml(child.name, child.relation, child.name, child.id)}</li>`;
        });
        html += `</ul>`;
      }

      html += `</li>`; // Close user level

      // Render younger siblings
      youngerSiblings.forEach(sib => {
        html += `<li>${buildNodeHtml(sib.name, sib.relation, sib.name, sib.id)}</li>`;
      });

      if (hasParents) {
        html += `</ul></li>`; // Close parents level
      }

      html += '</ul>';
      container.innerHTML = html;
      lucide.createIcons();
    }

    // ── Pet Profile & Kundali Logic ────────────────────────────────────

    let currentHoroscopeTab = 'daily';
    const zodiacSigns = ["Aries", "Taurus", "Gemini", "Cancer", "Leo", "Virgo", "Libra", "Scorpio", "Sagittarius", "Capricorn", "Aquarius", "Pisces"];

    function openHoroscopeView() {
      const select = document.getElementById('pet_profile-member-select');
      select.innerHTML = `<option value="self">Myself (${currentUserObj.socialProfile?.name || currentUserObj.name || 'User'})</option>`;
      (currentUserObj.packMembers || []).forEach(m => {
        select.innerHTML += `<option value="${m.id}">${m.name} (${m.relation})</option>`;
      });

      const pet_typeBgColors = {
        'Dog': '#ffeedb',
        'Cat': '#dcfce7',
        'Rabbit': '#e0f2fe',
        'Bird': '#fef08a',
        'Reptile': '#fde68a',
        'Fish': '#e0f2fe',
        'Small Pet': '#f3f4f6'
      };
      const darkPetTypeBgColors = {
        'Dog': '#7c2d12',
        'Cat': '#064e3b',
        'Rabbit': '#0c4a6e',
        'Bird': '#713f12',
        'Reptile': '#78350f',
        'Fish': '#082f49',
        'Small Pet': '#1f2937'
      };
      const pet_type = currentUserObj.pet_type || 'Dog';
      const viewHoroscope = document.getElementById('view-pet_profile');
      viewHoroscope.classList.remove('bg-gray-50', 'dark:bg-gray-950');

      const setBgColor = () => {
        const isDark = document.documentElement.classList.contains('dark');
        const bgColor = isDark ? (darkPetTypeBgColors[pet_type] || '#111827') : (pet_typeBgColors[pet_type] || '#f9fafb');
        viewHoroscope.style.backgroundColor = bgColor;
      };
      setBgColor();

      if (!viewHoroscope.dataset.bgObserverAttached) {
        new MutationObserver(setBgColor).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        viewHoroscope.dataset.bgObserverAttached = 'true';
      }

      switchView('view-pet_profile');
      switchHoroscopeTab('daily');
    }

    function switchHoroscopeTab(tab) {
      currentHoroscopeTab = tab;
      ['daily', 'kundali', 'match'].forEach(t => {
        const btn = document.getElementById(`tab-pet_profile-${t}`);
        const content = document.getElementById(`pet_profile-content-${t}`);
        if (t === tab) {
          btn.classList.add('bg-white', 'shadow-sm', 'text-gray-800', 'font-bold', 'dark:bg-gray-700', 'dark:text-white');
          btn.classList.remove('text-gray-500', 'font-semibold');
          content.classList.remove('hidden');
        } else {
          btn.classList.remove('bg-white', 'shadow-sm', 'text-gray-800', 'font-bold', 'dark:bg-gray-700', 'dark:text-white');
          btn.classList.add('text-gray-500', 'font-semibold');
          content.classList.add('hidden');
        }
      });
      renderHoroscopeView();
    }

    function renderHoroscopeView() {
      const select = document.getElementById('pet_profile-member-select');
      const val = select.value;

      let personName = currentUserObj.socialProfile?.name || currentUserObj.name || 'User';
      let personDob = currentUserObj.dob || '2000-01-01'; // Fallback
      let personCity = currentUserObj.city || 'Mumbai';
      let personTime = currentUserObj.birthTime || '12:00';
      let personGender = currentUserObj.socialProfile?.gender || currentUserObj.gender || 'Male';

      if (val !== 'self') {
        const m = (currentUserObj.packMembers || []).find(fm => fm.id == val);
        if (m) {
          personName = m.name;
          personDob = m.dob || '2000-01-01';
          personCity = m.birthCity || 'Mumbai';
          personTime = m.birthTime || '12:00';
          personGender = m.gender || 'Male';
        }
      }

      // Real Astronomical Calculator for Kundali using simplified Ephemeris
      const generateKundaliData = (name, dob, time, city, gender) => {
        const [year, month, day] = dob.split('-').map(Number);
        const [hour, minute] = time.split(':').map(Number);

        // Convert local time to UTC (Assuming IST +5:30)
        const date = new Date(Date.UTC(year, month - 1, day, hour - 5, minute - 30));

        // Julian Date
        let Y = date.getUTCFullYear();
        let M = date.getUTCMonth() + 1;
        let d = date.getUTCDate();
        let h = date.getUTCHours() + date.getUTCMinutes() / 60;
        if (M <= 2) { Y -= 1; M += 12; }
        const A = Math.floor(Y / 100);
        const B = 2 - A + Math.floor(A / 4);
        const JD = Math.floor(365.25 * (Y + 4716)) + Math.floor(30.6001 * (M + 1)) + d + h / 24 + B - 1524.5;
        const dDays = JD - 2451545.0; // Days since J2000.0

        // Sun's true longitude
        const gSun = (357.529 + 0.98560028 * dDays);
        const qSun = (280.459 + 0.98564736 * dDays);
        const lSun = (qSun + 1.915 * Math.sin(gSun * Math.PI / 180) + 0.020 * Math.sin(2 * gSun * Math.PI / 180));

        // Moon's true longitude (simplified perturbations)
        const lMoonMean = (218.316 + 13.176396 * dDays);
        const gMoon = (134.963 + 13.064993 * dDays);
        const lMoon = (lMoonMean + 6.289 * Math.sin(gMoon * Math.PI / 180));

        // Ayanamsa (Lahiri approx)
        const ayanamsa = (23.85 + (dDays / 365.25) * (50.29 / 3600));

        // Sidereal longitudes
        const siderealSun = ((lSun - ayanamsa) % 360 + 360) % 360;
        const siderealMoon = ((lMoon - ayanamsa) % 360 + 360) % 360;

        const nakshatras = ["Ashwini", "Bharani", "Krittika", "Rohini", "Mrigashira", "Ardra", "Punarvasu", "Pushya", "Ashlesha", "Magha", "Purva Phalguni", "Uttara Phalguni", "Hasta", "Chitra", "Swati", "Vishakha", "Anuradha", "Jyeshtha", "Mula", "Purva Ashadha", "Uttara Ashadha", "Shravana", "Dhanishta", "Shatabhisha", "Purva Bhadrapada", "Uttara Bhadrapada", "Revati"];
        const nakshatraIndex = Math.floor(siderealMoon / 13.333333);
        const nakshatraPada = Math.floor((siderealMoon % 13.333333) / 3.333333) + 1;

        const tithiAngle = ((siderealMoon - siderealSun) % 360 + 360) % 360;
        const tithiIndex = Math.floor(tithiAngle / 12);
        const tithis = ["Pratipada", "Dwitiya", "Tritiya", "Chaturthi", "Panchami", "Shashthi", "Saptami", "Ashtami", "Navami", "Dashami", "Ekadashi", "Dwadashi", "Trayodashi", "Chaturdashi", "Purnima", "Pratipada", "Dwitiya", "Tritiya", "Chaturthi", "Panchami", "Shashthi", "Saptami", "Ashtami", "Navami", "Dashami", "Ekadashi", "Dwadashi", "Trayodashi", "Chaturdashi", "Amavasya"];
        const paksha = tithiIndex < 15 ? "Shukla" : "Krishna";

        const yogaAngle = (siderealMoon + siderealSun) % 360;
        const yogaIndex = Math.floor(yogaAngle / 13.333333);
        const yogas = ["Vishkambha", "Priti", "Ayushman", "Saubhagya", "Shobhana", "Atiganda", "Sukarma", "Dhriti", "Shula", "Ganda", "Vriddhi", "Dhruva", "Vyaghata", "Harshana", "Vajra", "Siddhi", "Vyatipata", "Variyana", "Parigha", "Shiva", "Siddha", "Sadhya", "Shubha", "Shukla", "Brahma", "Indra", "Vaidhriti"];

        const karanaIndex = Math.floor(tithiAngle / 6);
        const karanas = ["Bava", "Balava", "Kaulava", "Taitila", "Garija", "Vanija", "Vishti", "Shakuni", "Chatushpada", "Naga", "Kintughna"];

        const rasiIndex = Math.floor(siderealMoon / 30);
        const rasis = ["Mesha (Aries)", "Vrishabha (Taurus)", "Mithuna (Gemini)", "Karka (Cancer)", "Simha (Leo)", "Kanya (Virgo)", "Tula (Libra)", "Vrischika (Scorpio)", "Dhanu (Sagittarius)", "Makara (Capricorn)", "Kumbha (Aquarius)", "Meena (Pisces)"];
        const planets = ["Mars", "Venus", "Mercury", "Moon", "Sun", "Mercury", "Venus", "Mars", "Jupiter", "Saturn", "Saturn", "Jupiter"];

        // Ascendant (Lagna) Approximation
        const sunRasi = Math.floor(siderealSun / 30);
        const hoursSinceSunrise = (hour + minute / 60 - 6 + 24) % 24;
        const ascendantIndex = Math.floor((sunRasi + hoursSinceSunrise / 2)) % 12;

        // Chart Data for 9 Planets
        const lMars = (355.453 + 0.52402077 * dDays) % 360;
        const lJup = (34.404 + 0.0830853 * dDays) % 360;
        const lSat = (50.077 + 0.0334596 * dDays) % 360;
        const lVen = (qSun + 48 * Math.sin((dDays * 1.6021) * Math.PI / 180)) % 360;
        const lMer = (qSun + 22 * Math.sin((dDays * 4.0923) * Math.PI / 180)) % 360;
        const lRahu = (125.04 - 0.0529538 * dDays) % 360;
        const lKetu = (lRahu + 180) % 360;

        const planetRasis = [
          { en: "Sun", hi: "सूर्य", rasi: Math.floor(((lSun - ayanamsa) % 360 + 360) % 360 / 30) },
          { en: "Moon", hi: "चंद्र", rasi: Math.floor(siderealMoon / 30) },
          { en: "Mars", hi: "मंगल", rasi: Math.floor(((lMars - ayanamsa) % 360 + 360) % 360 / 30) },
          { en: "Mercury", hi: "बुध", rasi: Math.floor(((lMer - ayanamsa) % 360 + 360) % 360 / 30) },
          { en: "Jupiter", hi: "बृहस्पति", rasi: Math.floor(((lJup - ayanamsa) % 360 + 360) % 360 / 30) },
          { en: "Venus", hi: "शुक्र", rasi: Math.floor(((lVen - ayanamsa) % 360 + 360) % 360 / 30) },
          { en: "Saturn", hi: "शनि", rasi: Math.floor(((lSat - ayanamsa) % 360 + 360) % 360 / 30) },
          { en: "Rahu", hi: "राहु", rasi: Math.floor(((lRahu - ayanamsa) % 360 + 360) % 360 / 30) },
          { en: "Ketu", hi: "केतु", rasi: Math.floor(((lKetu - ayanamsa) % 360 + 360) % 360 / 30) }
        ];

        const chartHouses = [];
        for (let i = 0; i < 12; i++) {
          const houseRasi = (ascendantIndex + i) % 12;
          chartHouses.push({
            houseIndex: i,
            rasiNum: houseRasi + 1,
            planets: planetRasis.filter(p => p.rasi === houseRasi)
          });
        }

        const weekdays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
        const weekdayDate = isNaN(date.getTime()) ? weekdays[0] : weekdays[date.getDay()];

        return {
          Nakshatra: nakshatras[nakshatraIndex] + " " + nakshatraPada + "th Pada",
          Weekday: weekdayDate,
          Tithi: tithis[tithiIndex] + " (" + paksha + " Paksha)",
          Yoga: yogas[yogaIndex],
          Karana: karanas[karanaIndex % 11],
          "Vikram Samvat": "Samvat " + (year + 57),
          God: ["Vishnu", "Shiva", "Brahma", "Ganesha", "Surya", "Kartikeya", "Indra", "Agni", "Yama", "Varuna"][nakshatraIndex % 10],
          "Yoni (Animal Sign)": ["Ashwa (Horse)", "Gaja (Elephant)", "Mesha (Sheep)", "Sarpa (Serpent)", "Shvan (Dog)", "Marjala (Cat)", "Mushaka (Rat)", "Gau (Cow)", "Mahisha (Buffalo)", "Vyaghra (Tiger)", "Mriga (Deer)", "Vanara (Monkey)", "Nakula (Mongoose)", "Simha (Lion)"][nakshatraIndex % 14],
          "Rasi Lord": planets[rasiIndex],
          Ascendant: rasis[ascendantIndex].split(' ')[0], // Get sanskrit name
          "Ascendant Lord": planets[ascendantIndex],
          Gender: gender,
          Ganam: ["Deva", "Manushya", "Rakshasa"][nakshatraIndex % 3],
          Gothram: ["Mareechi", "Vashistha", "Kashyapa", "Atri", "Bharadwaja", "Bhrigu", "Vishvamitra", "Gautama"][nakshatraIndex % 8],
          Bhutham: ["Fire", "Earth", "Air", "Water"][rasiIndex % 4],
          Sunrise: "06:12 AM",
          Sunset: "06:45 PM",
          _chartHouses: chartHouses
        };
      };

      const kData = generateKundaliData(personName, personDob, personTime, personCity, personGender);

      // Generate Real Zodiac based on DOB
      const getZodiacSign = (day, month) => {
        if ((month == 3 && day >= 21) || (month == 4 && day <= 19)) return "Aries";
        if ((month == 4 && day >= 20) || (month == 5 && day <= 20)) return "Taurus";
        if ((month == 5 && day >= 21) || (month == 6 && day <= 20)) return "Gemini";
        if ((month == 6 && day >= 21) || (month == 7 && day <= 22)) return "Cancer";
        if ((month == 7 && day >= 23) || (month == 8 && day <= 22)) return "Leo";
        if ((month == 8 && day >= 23) || (month == 9 && day <= 22)) return "Virgo";
        if ((month == 9 && day >= 23) || (month == 10 && day <= 22)) return "Libra";
        if ((month == 10 && day >= 23) || (month == 11 && day <= 21)) return "Scorpio";
        if ((month == 11 && day >= 22) || (month == 12 && day <= 21)) return "Sagittarius";
        if ((month == 12 && day >= 22) || (month == 1 && day <= 19)) return "Capricorn";
        if ((month == 1 && day >= 20) || (month == 2 && day <= 18)) return "Aquarius";
        return "Pisces";
      };

      let zDay = 1, zMonth = 1;
      if (personDob && personDob.includes('-')) {
        const parts = personDob.split('-');
        zMonth = parseInt(parts[1], 10);
        zDay = parseInt(parts[2], 10);
      }
      const zodiac = getZodiacSign(zDay, zMonth);

      if (currentHoroscopeTab === 'daily') {
        const bgImagePath = `assets/images/pet_profile/${zodiac.toLowerCase()}.png`;
        document.getElementById('pet_profile-content-daily').innerHTML = `
          <div class="relative rounded-3xl shadow-lg border border-gray-100 dark:border-gray-800 p-10 text-center overflow-hidden min-h-[400px] flex flex-col justify-center">
            <button onclick="updateBirthDetails('${val}')" class="absolute top-6 right-6 z-20 text-sm bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm px-4 py-2 rounded-full text-brand-600 dark:text-brand-400 hover:bg-white dark:hover:bg-gray-800 font-semibold flex items-center gap-1 shadow-sm transition-all"><i data-lucide="edit" class="w-4 h-4"></i> Edit Details</button>
            <!-- Background Image with Transparency -->
            <div class="absolute inset-0 bg-center bg-cover opacity-60 mix-blend-multiply dark:mix-blend-screen dark:opacity-40 transition-all duration-700" style="background-image: url('${bgImagePath}')"></div>
            <!-- Gradient Overlay for Readability -->
            <div class="absolute inset-0 bg-gradient-to-t from-white/95 via-white/80 to-white/40 dark:from-gray-900/95 dark:via-gray-900/80 dark:to-gray-900/40"></div>
            
            <!-- Content -->
            <div class="relative z-10 flex flex-col items-center">
              <div class="w-20 h-20 bg-white/60 dark:bg-gray-800/60 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-5 text-4xl shadow-xl border border-white/40 dark:border-gray-700/50">
                ✨
              </div>
              <h3 class="text-3xl font-black text-gray-900 dark:text-white mb-3 drop-shadow-sm">Daily Pet Profile for ${personName}</h3>
              <p class="inline-block px-5 py-1.5 rounded-full bg-amber-100/90 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 font-bold mb-8 shadow-sm border border-amber-200/50 dark:border-amber-800/50 backdrop-blur-md uppercase tracking-wider text-sm">Zodiac Sign: ${zodiac}</p>
              <p class="text-gray-800 dark:text-gray-200 max-w-2xl mx-auto leading-relaxed text-xl font-medium drop-shadow-sm">
                Today brings positive energy your way. The alignment of the stars suggests an unexpected opportunity in your personal life. Trust your intuition and remain open to new experiences. A conversation with a close friend will provide valuable insights.
              </p>
            </div>
          </div>
        `;
      } else if (currentHoroscopeTab === 'kundali') {
        document.getElementById('pet_profile-content-kundali').innerHTML = `
          <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 p-8">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-6 gap-3">
              <h3 class="text-xl font-bold text-gray-800 dark:text-white">Kundali (Birth Chart)</h3>
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-1 bg-gray-100 dark:bg-gray-800 p-1 rounded-lg">
                  <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 px-2 uppercase tracking-wide">Export:</span>
                  <button onclick="downloadKundaliExport(event, 'jpg', '${zodiac}')" class="text-xs bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-2.5 py-1.5 rounded shadow-sm font-bold flex items-center gap-1 transition-colors">JPG</button>
                  <button onclick="downloadKundaliExport(event, 'pdf', '${zodiac}')" class="text-xs bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 px-2.5 py-1.5 rounded shadow-sm font-bold flex items-center gap-1 transition-colors">PDF</button>
                </div>
                <button onclick="updateBirthDetails('${val}')" class="text-sm text-brand-500 hover:text-brand-600 font-semibold flex items-center gap-1"><i data-lucide="edit" class="w-4 h-4"></i> Edit Gotcha Day</button>
              </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
              <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl border border-gray-100 dark:border-gray-700 max-h-[500px] overflow-y-auto">
                <h4 class="font-bold text-gray-700 dark:text-gray-300 mb-4">Gotcha Day</h4>
                <div class="overflow-x-auto">
                  <table class="w-full text-sm text-left text-gray-700 dark:text-gray-300">
                    <tbody>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-brand-600 dark:text-brand-400 w-[40%]">Name</td>
                        <td class="py-2.5 px-3">${personName}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-brand-600 dark:text-brand-400">Date of Birth</td>
                        <td class="py-2.5 px-3">${personDob}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-brand-600 dark:text-brand-400">Time of Birth</td>
                        <td class="py-2.5 px-3">${personTime}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-brand-600 dark:text-brand-400">Place of Birth</td>
                        <td class="py-2.5 px-3">${personCity}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Nakshatra</td>
                        <td class="py-2.5 px-3 text-brand-500">${kData.Nakshatra}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Weekday</td>
                        <td class="py-2.5 px-3">${kData.Weekday}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Tithi</td>
                        <td class="py-2.5 px-3">${kData.Tithi}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Yoga</td>
                        <td class="py-2.5 px-3">${kData.Yoga}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Karana</td>
                        <td class="py-2.5 px-3">${kData.Karana}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Vikram Samvat</td>
                        <td class="py-2.5 px-3">${kData["Vikram Samvat"]}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">God</td>
                        <td class="py-2.5 px-3">${kData.God}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Gender</td>
                        <td class="py-2.5 px-3">${kData.Gender}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Rasi Lord</td>
                        <td class="py-2.5 px-3">${kData["Rasi Lord"]}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Ascendant</td>
                        <td class="py-2.5 px-3">${kData.Ascendant}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Ascendant Lord</td>
                        <td class="py-2.5 px-3">${kData["Ascendant Lord"]}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Ganam</td>
                        <td class="py-2.5 px-3">${kData.Ganam}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Yoni (Animal Sign)</td>
                        <td class="py-2.5 px-3">${kData["Yoni (Animal Sign)"]}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Gothram</td>
                        <td class="py-2.5 px-3">${kData.Gothram}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Bhutham</td>
                        <td class="py-2.5 px-3">${kData.Bhutham}</td>
                      </tr>
                      <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Sunrise</td>
                        <td class="py-2.5 px-3">${kData.Sunrise}</td>
                      </tr>
                      <tr class="hover:bg-gray-100 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="py-2.5 px-3 font-bold text-gray-900 dark:text-white">Sunset</td>
                        <td class="py-2.5 px-3">${kData.Sunset}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div class="flex items-center justify-center p-2 sm:p-6 bg-amber-50 dark:bg-amber-900/10 rounded-2xl border border-amber-100 dark:border-amber-900/30">
                <!-- Authentic North Indian Kundali Chart -->
                <div class="relative w-full max-w-[320px] aspect-square text-[10px] sm:text-xs font-semibold text-amber-900 dark:text-amber-400 text-center leading-tight">
                  <svg viewBox="0 0 300 300" class="absolute inset-0 w-full h-full stroke-amber-500 dark:stroke-amber-600 fill-transparent" stroke-width="2">
                    <rect x="4" y="4" width="292" height="292" />
                    <line x1="4" y1="4" x2="296" y2="296" />
                    <line x1="296" y1="4" x2="4" y2="296" />
                    <polygon points="150,4 296,150 150,296 4,150" />
                  </svg>
                  
                  <!-- House Labels based on standard Kundali layout -->
                  <div id="kundali-chart-houses" class="absolute inset-0"></div>
                </div>
              </div>
            </div>
          </div>
        `;

        // Render dynamic SVG House data
        const housePositions = [
          { top: '25%', left: '50%' }, // 1
          { top: '12%', left: '25%' }, // 2
          { top: '25%', left: '12%' }, // 3
          { top: '50%', left: '25%' }, // 4
          { top: '75%', left: '12%' }, // 5
          { top: '88%', left: '25%' }, // 6
          { top: '75%', left: '50%' }, // 7
          { top: '88%', left: '75%' }, // 8
          { top: '75%', left: '88%' }, // 9
          { top: '50%', left: '75%' }, // 10
          { top: '25%', left: '88%' }, // 11
          { top: '12%', left: '75%' }  // 12
        ];

        let housesHtml = "";
        kData._chartHouses.forEach(h => {
          const pos = housePositions[h.houseIndex];
          let pText = h.planets.map(p => `<div class="flex items-center gap-1 leading-none"><span class="text-blue-700 dark:text-blue-400 font-bold text-[10px] sm:text-xs">${p.en}</span><span class="text-blue-800 dark:text-blue-300 font-bold text-[10px] sm:text-xs">${p.hi}</span></div>`).join('');
          housesHtml += `
             <div class="absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center justify-center gap-0.5" style="top: ${pos.top}; left: ${pos.left};">
               <span class="text-red-600 dark:text-red-400 font-extrabold text-sm mb-0.5">${h.rasiNum}</span>
               <div class="flex flex-col items-center justify-center text-center gap-0.5">${pText}</div>
             </div>
           `;
        });
        document.getElementById('kundali-chart-houses').innerHTML = housesHtml;

      } else if (currentHoroscopeTab === 'match') {
        let otherOptions = `<option value="">Select a friend to match with...</option>`;
        if (typeof globalFriends !== 'undefined') {
          globalFriends.forEach(f => {
            if (f.name !== personName) {
              otherOptions += `<option value="${f.name}">${f.name}</option>`;
            }
          });
        }
        document.getElementById('pet_profile-content-match').innerHTML = `
          <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-800 p-8">
            <h3 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Playdate (Guna Milan)</h3>
            <div class="flex flex-col md:flex-row gap-6 items-center">
              <div class="flex-1 w-full bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 text-center relative group">
                <button onclick="updateBirthDetails('${val}')" class="absolute top-4 right-4 text-brand-500 opacity-0 group-hover:opacity-100 transition-opacity"><i data-lucide="edit" class="w-4 h-4"></i></button>
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-3 font-bold text-xl">${personName.charAt(0)}</div>
                <h4 class="font-bold text-gray-900 dark:text-white">${personName}</h4>
              </div>
              
              <div class="w-12 h-12 shrink-0 bg-brand-50 dark:bg-brand-900/30 text-brand-500 rounded-full flex items-center justify-center">
                <i data-lucide="heart" class="w-6 h-6"></i>
              </div>
              
              <div class="flex-1 w-full bg-gray-50 dark:bg-gray-800 p-6 rounded-2xl border border-gray-200 dark:border-gray-700 text-center relative group">
                <button onclick="editMatchPartner()" class="absolute top-4 right-4 text-brand-500 opacity-0 group-hover:opacity-100 transition-opacity z-10"><i data-lucide="edit" class="w-4 h-4"></i></button>
                <select id="playdate-partner" onchange="runPlaydate()" class="w-full bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-2 outline-none mb-3">
                  ${otherOptions}
                </select>
                <div id="partner-placeholder">Select a partner</div>
              </div>
            </div>
            
            <div id="playdate-result" class="hidden mt-8 text-center p-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800/50 rounded-2xl">
              <div class="text-4xl font-black text-green-600 dark:text-green-400 mb-2"><span id="match-score">28</span> / 36</div>
              <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-1">Excellent Match</h4>
              <p class="text-gray-600 dark:text-gray-300 text-sm">The Ashtakoot Guna Milan indicates highly favorable compatibility for marriage and partnership.</p>
            </div>
          </div>
        `;
      }
      lucide.createIcons();
    }

    window.downloadKundaliExport = function (event, format, zodiac) {
      const btn = event.currentTarget;
      const originalText = btn.innerHTML;
      btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>...';
      if (typeof lucide !== 'undefined') lucide.createIcons();

      const capture = () => {
        // Build an off-screen container matching user's layout request
        const originalContainer = document.getElementById('pet_profile-content-kundali').firstElementChild;
        const grid = originalContainer.querySelector('.grid');
        const originalTableDiv = grid.children[0];
        const originalChartDiv = grid.children[1];

        const exportContainer = document.createElement('div');
        exportContainer.style.position = 'absolute';
        exportContainer.style.left = '-9999px';
        exportContainer.style.top = '0';
        exportContainer.style.width = '800px';
        exportContainer.style.backgroundColor = '#ffffff';
        exportContainer.style.padding = '40px';
        exportContainer.style.display = 'flex';
        exportContainer.style.flexDirection = 'column';
        exportContainer.style.alignItems = 'center';
        exportContainer.style.gap = '30px';
        exportContainer.style.color = '#1f2937';
        exportContainer.style.fontPack = 'Inter, sans-serif';

        exportContainer.innerHTML = `
          <div style="text-align:center; width: 100%;">
            <h2 style="font-size:32px; font-weight:bold; margin-bottom:8px;">Kundali Birth Chart</h2>
            <div style="font-size:20px; font-weight:bold; color:#d97706; padding: 6px 20px; background-color:#fef3c7; border-radius:99px; display:inline-block;">
              Zodiac Sign: ${zodiac}
            </div>
          </div>
        `;

        const chartClone = originalChartDiv.cloneNode(true);
        chartClone.style.width = '100%';
        chartClone.style.backgroundColor = '#fffbeb';
        chartClone.style.borderRadius = '16px';
        chartClone.style.padding = '24px';

        const tableClone = originalTableDiv.cloneNode(true);
        tableClone.style.width = '100%';
        tableClone.style.backgroundColor = '#f9fafb';
        tableClone.style.borderRadius = '16px';
        tableClone.style.padding = '24px';
        tableClone.classList.remove('max-h-[500px]', 'overflow-y-auto');

        // Stack: Chart then Table
        exportContainer.appendChild(chartClone);
        exportContainer.appendChild(tableClone);

        document.body.appendChild(exportContainer);

        html2canvas(exportContainer, {
          backgroundColor: '#ffffff',
          scale: 2,
          useCORS: true
        }).then(canvas => {
          document.body.removeChild(exportContainer);
          const imgData = canvas.toDataURL('image/jpeg', 1.0);

          if (format === 'jpg') {
            const link = document.createElement('a');
            link.download = 'Kundali_Export.jpg';
            link.href = imgData;
            link.click();
            btn.innerHTML = originalText;
            if (typeof lucide !== 'undefined') lucide.createIcons();
          } else if (format === 'pdf') {
            const finishPdf = () => {
              const { jsPDF } = window.jspdf;
              const pdf = new jsPDF('p', 'pt', 'a4');
              const pdfWidth = pdf.internal.pageSize.getWidth();
              const pdfHeight = (canvas.height * pdfWidth) / canvas.width;
              pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
              pdf.save('Kundali_Export.pdf');
              btn.innerHTML = originalText;
              if (typeof lucide !== 'undefined') lucide.createIcons();
            };

            if (typeof window.jspdf === 'undefined') {
              const script = document.createElement('script');
              script.src = 'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js';
              script.onload = finishPdf;
              document.head.appendChild(script);
            } else {
              finishPdf();
            }
          }
        });
      };

      if (typeof html2canvas === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = capture;
        document.head.appendChild(script);
      } else {
        capture();
      }
    };

    window.editMatchPartner = function () {
      const p = document.getElementById('playdate-partner').value;
      if (p) updateBirthDetails(p);
    };

    function updateBirthDetails(memberId) {
      document.getElementById('birth-modal-member-id').value = memberId;

      let personCity = 'Mumbai';
      let personTime = '12:00';
      let personDob = '2000-01-01';
      let personGender = 'Male';

      if (memberId === 'self') {
        personCity = currentUserObj.city || 'Mumbai';
        personTime = currentUserObj.birthTime || '12:00';
        personDob = currentUserObj.dob || '2000-01-01';
        personGender = currentUserObj.gender || currentUserObj.socialProfile?.gender || 'Male';
      } else {
        let m = (currentUserObj.packMembers || []).find(fm => fm.id == memberId);
        if (!m && typeof globalFriends !== 'undefined') m = globalFriends.find(f => f.name === memberId);

        if (m) {
          personCity = m.birthCity || 'Mumbai';
          personTime = m.birthTime || '12:00';
          personDob = m.dob || '2000-01-01';
          personGender = m.gender || 'Male';
        }
      }

      document.getElementById('birth-modal-dob').value = personDob;
      document.getElementById('birth-modal-time').value = personTime;
      document.getElementById('birth-modal-city').value = personCity;
      document.getElementById('birth-modal-gender').value = personGender;

      document.getElementById('birth-details-modal').classList.remove('hidden');
      document.getElementById('birth-details-modal').classList.add('flex');
    }

    function closeBirthDetailsModal() {
      document.getElementById('birth-details-modal').classList.add('hidden');
      document.getElementById('birth-details-modal').classList.remove('flex');
    }

    async function saveBirthDetails(e) {
      e.preventDefault();
      const memberId = document.getElementById('birth-modal-member-id').value;
      const dob = document.getElementById('birth-modal-dob').value;
      const time = document.getElementById('birth-modal-time').value;
      const city = document.getElementById('birth-modal-city').value;
      const gender = document.getElementById('birth-modal-gender').value;

      if (memberId === 'self') {
        currentUserObj.dob = dob;
        currentUserObj.birthTime = time;
        currentUserObj.city = city;
        currentUserObj.gender = gender;
        if (currentUserObj.socialProfile) currentUserObj.socialProfile.gender = gender;
      } else {
        let m = (currentUserObj.packMembers || []).find(fm => fm.id == memberId);
        if (!m && typeof globalFriends !== 'undefined') m = globalFriends.find(f => f.name === memberId);

        if (m) {
          m.dob = dob;
          m.birthTime = time;
          m.birthCity = city;
          m.gender = gender;
        }
      }

      if (currentUserObj?.id) {
        try {
          const data = await api("save_birth_details", {
            user_id: currentUserObj.id,
            member_id: memberId,
            dob,
            birthTime: time,
            birthCity: city,
            gender,
          });
          if (data.status !== "success") throw new Error(data.message || "Save failed");
        } catch (err) {
          console.warn("Could not save gotcha day to database:", err);
          showToast("Could not save gotcha day to database.");
          return;
        }
      }

      persistCurrentSession(currentUserObj);

      closeBirthDetailsModal();
      renderHoroscopeView();
      if (currentHoroscopeTab === 'match') {
        setTimeout(() => { if (typeof runPlaydate === 'function') runPlaydate(); }, 100);
      }

      const toast = document.createElement('div');
      toast.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-xl shadow-lg z-50 transition-all transform translate-y-0 opacity-100 font-semibold flex items-center gap-2';
      toast.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> Birth details updated!';
      document.body.appendChild(toast);
      lucide.createIcons();
      setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-4');
        setTimeout(() => toast.remove(), 300);
      }, 3000);
    }

    function runPlaydate() {
      const partnerVal = document.getElementById('playdate-partner').value;
      const res = document.getElementById('playdate-result');
      if (partnerVal) {
        // Mock score generation
        const score = Math.floor(Math.random() * 16) + 20; // 20 to 35
        document.getElementById('match-score').innerText = score;
        res.classList.remove('hidden');
      } else {
        res.classList.add('hidden');
      }
    }

    // ── Session restore (after all functions defined) ────────────────
    (async function () {
      if (isOfflineDemoMode()) {
        updateOfflineDemoBadge();
        document.body.style.visibility = 'visible';
        return;
      }
      try {
        const restored = await api("session_me");
        const user = restored?.status === "success" ? restored.user : null;
        if (user && user.email) {
          persistCurrentSession(user);
          populateMemberDashboard(user);
          // Always restore into the feed; never auto-open the application form.
          initializeSocialFeed();
          initializeSocialFeed();
          document.body.style.visibility = 'visible';
          return;
        }
      } catch (e) {
        clearStoredSession();
        console.warn('Cookie session restore failed:', e);
      }

      try {
        const authRestored = await restoreSupabaseAuthSession();
        const authUser = authRestored?.status === "success" ? authRestored.user : null;
        if (authUser && authUser.email) {
          persistCurrentSession(authUser);
          populateMemberDashboard(authUser);
          initializeSocialFeed();
          initializeSocialFeed();
          document.body.style.visibility = 'visible';
          return;
        }
      } catch (e) {
        console.warn('Supabase Auth session restore failed:', e);
      }

      document.body.style.visibility = 'visible';
    })();

    (function initCityAutocomplete() {
      // --- City Autocomplete Logic ---
      const cityInput = document.getElementById('birth-modal-city');
      if (cityInput) {
        let cityTimeout;
        const parentDiv = cityInput.parentElement;
        parentDiv.classList.add('relative');

        const suggestionsBox = document.createElement('div');
        suggestionsBox.className = 'absolute z-50 w-full bg-gray-800 rounded-xl mt-1 overflow-y-auto max-h-32 overscroll-contain shadow-2xl hidden border border-gray-700 custom-scrollbar';
        parentDiv.appendChild(suggestionsBox);

        // Click outside to close
        document.addEventListener('click', (e) => {
          if (!parentDiv.contains(e.target)) {
            suggestionsBox.classList.add('hidden');
          }
        });

        cityInput.addEventListener('input', function () {
          clearTimeout(cityTimeout);
          const query = this.value.trim();
          if (!query) {
            suggestionsBox.classList.add('hidden');
            return;
          }

          const isPostcode = /^(?:\d{4,6}|[A-Za-z]{1,2}\d[A-Za-z\d]? ?\d[A-Za-z]{2}|[A-Za-z]\d[A-Za-z] ?\d[A-Za-z]\d)$/.test(query);

          const formatPlace = (place) => {
            const a = place.address || {};
            const city = a.city || a.town || a.village || a.suburb || a.county || place.name;
            const state = a.state || a.state_district || "";
            const country = a.country || "";
            const parts = [city, state, country].filter(p => p && p.trim() !== '');
            return [...new Set(parts)].join(', ');
          };

          if (isPostcode) {
            cityTimeout = setTimeout(() => {
              // Normalize UK postcodes (e.g. eh165hf -> EH16 5HF) to help Nominatim match them properly
              let formattedQuery = query;
              const cleanStr = query.replace(/\s/g, '').toUpperCase();
              if (/^[A-Z]{1,2}\d[A-Z\d]?\d[A-Z]{2}$/.test(cleanStr)) {
                formattedQuery = cleanStr.slice(0, -3) + ' ' + cleanStr.slice(-3);
              }

              const url = `https://nominatim.openstreetmap.org/search?postalcode=${encodeURIComponent(formattedQuery)}&format=json&addressdetails=1&email=admin@pawcircle.com&accept-language=en`;
              fetch(url)
                .then(res => res.json())
                .then(data => {
                  if (data && data.length > 0) {
                    suggestionsBox.innerHTML = '';
                    suggestionsBox.classList.remove('hidden');
                    data.forEach(place => {
                      const item = document.createElement('div');
                      item.className = 'px-4 py-3 text-base font-semibold text-gray-200 cursor-pointer hover:bg-gray-700 transition-colors';
                      item.textContent = formatPlace(place);
                      item.addEventListener('click', () => {
                        cityInput.value = formatPlace(place);
                        suggestionsBox.classList.add('hidden');
                      });
                      suggestionsBox.appendChild(item);
                    });
                  } else {
                    // Fallback to general search if postalcode search fails
                    fetch(`https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(formattedQuery)}&format=json&addressdetails=1&email=admin@pawcircle.com&accept-language=en`)
                      .then(res => res.json())
                      .then(data2 => {
                        suggestionsBox.innerHTML = '';
                        if (data2 && data2.length > 0) {
                          suggestionsBox.classList.remove('hidden');
                          data2.forEach(place => {
                            const item = document.createElement('div');
                            item.className = 'px-4 py-3 text-base font-semibold text-gray-200 cursor-pointer hover:bg-gray-700 transition-colors';
                            item.textContent = formatPlace(place);
                            item.addEventListener('click', () => {
                              cityInput.value = formatPlace(place);
                              suggestionsBox.classList.add('hidden');
                            });
                            suggestionsBox.appendChild(item);
                          });
                        } else {
                          suggestionsBox.classList.add('hidden');
                        }
                      }).catch(err => console.error(err));
                  }
                }).catch(err => console.error(err));
            }, 150);
          } else if (query.length >= 1) { // Appear from the first letter
            cityTimeout = setTimeout(() => {
              fetch(`https://geocoding-api.open-meteo.com/v1/search?name=${encodeURIComponent(query)}&count=5&language=en&format=json`)
                .then(res => res.json())
                .then(data => {
                  suggestionsBox.innerHTML = '';
                  if (data && data.results && data.results.length > 0) {
                    suggestionsBox.classList.remove('hidden');
                    data.results.forEach(place => {
                      const item = document.createElement('div');
                      item.className = 'px-4 py-3 text-base font-semibold text-gray-200 cursor-pointer hover:bg-gray-700 transition-colors';

                      const parts = [place.name, place.admin1, place.country].filter(p => p && p.trim() !== '');
                      const formattedName = [...new Set(parts)].join(', ');

                      item.textContent = formattedName;
                      item.addEventListener('click', () => {
                        cityInput.value = formattedName;
                        suggestionsBox.classList.add('hidden');
                      });
                      suggestionsBox.appendChild(item);
                    });
                  } else {
                    suggestionsBox.classList.add('hidden');
                  }
                }).catch(err => console.error(err));
            }, 150); // Fast update
          }
        });
      }
    })();
  