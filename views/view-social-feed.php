<div id="view-social-feed" class="view-section min-h-screen flex-col transition-colors duration-500 bg-gray-50">
    <!-- Social Feed Header with Logo & Branding -->
    <div id="social-feed-top-header"
      class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800 w-full transition-colors duration-500 relative z-50">
      <div class="max-w-7xl mx-auto px-4 py-3">
        <div
          class="grid grid-cols-[minmax(0,1fr)_auto] xl:grid-cols-[auto_minmax(260px,1fr)_auto] items-center gap-3 xl:gap-6">
          <!-- Branding (left) -->
          <div class="flex items-center gap-3 sm:gap-4 min-w-0">
            <!-- Larger white circular logo frame -->
            <div id="social-header-logo-frame"
              class="relative flex-shrink-0 w-14 h-14 sm:w-16 sm:h-16 xl:w-20 xl:h-20 bg-white rounded-full shadow-lg flex items-center justify-center overflow-hidden"
              style="border: 4px solid #e04848">
              <div id="social-header-logo"
                class="flex items-center justify-center text-3xl sm:text-4xl xl:text-5xl bg-white"
                style="width: 100%; height: 100%; background: #ffffff"></div>
            </div>

            <!-- Branding and greeting text -->
            <div class="flex-1 min-w-0">
              <h1
                class="text-xl sm:text-2xl xl:text-3xl font-extrabold text-gray-900 dark:text-white leading-tight truncate"
                style="font-family: &quot;DM Serif Display&quot;">
                PawCircle
              </h1>
              <p class="text-sm font-semibold text-gray-700 dark:text-gray-300 mt-0.5 truncate"
                id="social-header-greeting-native"></p>
              <p class="text-xs text-gray-500 dark:text-gray-400 truncate" id="social-header-greeting-en"></p>
            </div>
          </div>

          <!-- Search bar (center, expanded width) -->
          <div class="col-span-2 xl:col-span-1 xl:col-start-2 row-start-2 xl:row-start-1 flex justify-center min-w-0">
            <div class="relative w-full xl:max-w-xl">
              <i data-lucide="search"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400 dark:text-gray-500"></i>
              <input type="text" id="global-search-input" placeholder="Search breed members..."
                onkeydown="if(event.key==='Enter'){event.preventDefault();runGlobalSearch(this.value);}"
                class="bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 rounded-full py-2.5 pl-12 pr-5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-brand-300 transition-colors shadow-sm" />
            </div>
          </div>

          <!-- Action buttons (right) -->
          <div class="flex items-center justify-end gap-2 sm:gap-3">
            <button onclick="
                  switchView('view-social-feed');
                  switchSocialTab('feed');
                "
              class="header-action-btn bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors p-2 rounded-xl border-2"
              title="Home">
              <i data-lucide="home" class="w-5 h-5"></i>
            </button>

            <div class="relative">
              <button id="notifications-toggle-btn" onclick="toggleNotificationsPanel()"
                class="header-action-btn relative bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors p-2 rounded-xl border-2"
                title="Notifications">
                <i data-lucide="bell" class="w-5 h-5"></i>
                <span id="notifications-bell-badge"
                  class="hidden absolute -top-2 -right-2 min-w-[1.25rem] h-5 px-1 rounded-full bg-brand-500 text-white text-[10px] font-bold leading-5 text-center shadow-md ring-2 ring-white dark:ring-gray-900">0</span>
              </button>
              <!-- Notifications Panel -->
              <div id="notifications-panel"
                class="hidden fixed left-3 right-3 top-24 sm:absolute sm:left-auto sm:right-0 sm:top-auto sm:mt-3 sm:w-80 bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-800 overflow-hidden transform transition-all"
                style="z-index: 9999; max-width: calc(100vw - 1.5rem);">
                <div
                  class="p-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
                  <h4 class="font-bold text-gray-800 dark:text-gray-200">
                    Notifications
                  </h4>
                  <div class="flex items-center gap-2">
                    <span id="notifications-unread-count"
                      class="text-xs bg-brand-100 dark:bg-brand-900/50 text-brand-600 dark:text-brand-400 px-2 py-0.5 rounded-full font-bold">0
                      New</span>
                    <button type="button" onclick="closeNotificationsPanel()"
                      class="sm:hidden p-1 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
                      aria-label="Close notifications">
                      <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                  </div>
                </div>
                <div id="notifications-list" class="max-h-[55vh] sm:max-h-[350px] overflow-y-auto">
                  <div class="p-4 text-sm text-gray-500 dark:text-gray-400">Open notifications to load updates.</div>
                </div>
                <div
                  class="p-3 text-center border-t border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                  <span class="text-brand-500 font-semibold text-sm">View All</span>
                </div>
              </div>

              <div id="notifications-unread-popup"
                class="hidden absolute -right-5 top-full mt-3 w-72 max-w-[calc(100vw-1.5rem)] rounded-2xl border border-brand-100 dark:border-brand-900/60 bg-white dark:bg-gray-900 shadow-xl"
                style="z-index: 9998;" role="status" aria-live="polite">
                <span
                  class="absolute -top-2 right-9 w-4 h-4 rotate-45 bg-white dark:bg-gray-900 border-l border-t border-brand-100 dark:border-brand-900/60"
                  aria-hidden="true"></span>
                <div class="flex items-start gap-3 p-4">
                  <div
                    class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 flex items-center justify-center flex-shrink-0">
                    <i data-lucide="bell-ring" class="w-5 h-5"></i>
                  </div>
                  <div class="min-w-0 flex-1">
                    <p class="text-base font-bold shadow-md text-gray-900 dark:text-gray-100">You have unread notifications</p>
                    <p id="notifications-unread-popup-count" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                      Open notifications to catch up.
                    </p>
                  </div>
                  <button type="button" onclick="dismissUnreadNotificationsPopup()"
                    class="no-faith-hover p-1 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 flex-shrink-0"
                    aria-label="Dismiss unread notifications alert">
                    <i data-lucide="x" class="w-4 h-4"></i>
                  </button>
                </div>
              </div>
            </div>

            <div class="relative">
              <button id="toolbar-profile-btn" type="button" onclick="toggleProfileMenu(event)"
                class="header-action-btn bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors p-0.5 rounded-full border-2 overflow-hidden w-10 h-10 flex items-center justify-center"
                title="Account" aria-haspopup="true" aria-expanded="false">
                <span id="toolbar-avatar-letter"
                  class="w-full h-full rounded-full bg-brand-100 dark:bg-brand-900/40 text-brand-600 dark:text-brand-300 flex items-center justify-center font-bold text-sm">U</span>
                <img id="toolbar-avatar-img" src="" alt="" class="w-full h-full object-cover rounded-full hidden">
              </button>
              <div id="toolbar-profile-menu"
                class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-50">
                <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                  <p id="toolbar-menu-name" class="text-base font-bold shadow-md text-gray-900 dark:text-gray-100 truncate">User</p>
                  <p id="toolbar-menu-email" class="text-xs text-gray-500 dark:text-gray-400 truncate"></p>
                </div>
                <button type="button" onclick="openProfileModal(); closeProfileMenu();"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i
                    data-lucide="pencil" class="w-4 h-4"></i> Edit profile</button>
                <button type="button"
                  onclick="switchView('view-social-feed'); switchSocialTab('my-posts'); closeProfileMenu();"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i
                    data-lucide="layout-list" class="w-4 h-4"></i> Posts</button>
                <button type="button"
                  onclick="switchView('view-social-feed'); switchSocialTab('settings'); closeProfileMenu();"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i
                    data-lucide="settings" class="w-4 h-4"></i> Settings</button>
                <button type="button" onclick="toggleDarkMode();"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i
                    id="dark-mode-icon" data-lucide="moon" class="w-4 h-4"></i> Toggle dark mode</button>
                <button type="button"
                  onclick="navigator.clipboard.writeText(window.location.href); showToast('Profile link copied!', 'success'); closeProfileMenu();"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i
                    data-lucide="link" class="w-4 h-4"></i> Copy profile link</button>
                <div class="border-t border-gray-100 dark:border-gray-700"></div>
                <button id="toolbar-admin-mode-btn" type="button" onclick="enterAdminModeFromMenu();"
                  class="hidden w-full text-left px-4 py-3 text-sm hover:bg-orange-50 dark:hover:bg-orange-900/30 text-orange-700 dark:text-orange-300 items-center gap-3"><i
                    data-lucide="shield-check" class="w-4 h-4"></i> Enter admin mode</button>
                <button type="button" onclick="closeProfileMenu(); logout();"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-red-50 dark:hover:bg-red-900/30 text-red-600 dark:text-red-300 flex items-center gap-3"><i
                    data-lucide="log-out" class="w-4 h-4"></i> Sign out</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ======= Cover Photo Header ======= -->
    <div class="w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shadow-sm">
      <!-- Cover Photo -->
      <div class="relative w-full bg-white dark:bg-gray-900 overflow-hidden group rounded-b-xl cursor-pointer"
        id="cover-photo-container" style="aspect-ratio: 6 / 1; min-height: 120px;" onclick="openCoverPhotoMenu(event)">
        <!-- Cover image (hidden until uploaded) -->
        <img id="cover-photo-img" src="" alt="Cover Photo"
          class="hidden absolute inset-0 w-full h-full object-cover object-center cursor-pointer"
          onclick="openCoverPhotoMenu(event)" />
        <!-- Default decorative pattern -->
        <div id="cover-photo-pattern" class="absolute inset-0" style="
              background-image: repeating-linear-gradient(
                135deg,
                transparent,
                transparent 30px,
                rgba(255, 255, 255, 0.08) 30px,
                rgba(255, 255, 255, 0.08) 60px
              );
            "></div>
        <div
          class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">
          <div class="rounded-full bg-black/30 p-3">
            <i data-lucide="camera" class="w-6 h-6 text-white"></i>
          </div>
        </div>
        <!-- Cover photo action menu (shown on click) -->
        <div id="cover-photo-menu"
          class="hidden absolute z-30 bg-white dark:bg-gray-800 rounded-xl shadow-2xl border-2 border-gray-200 dark:border-gray-700 overflow-hidden min-w-[200px]">
          <button type="button" onclick="viewCoverPhoto()"
            class="no-faith-hover w-full flex items-center gap-3 px-4 py-3 text-base font-semibold text-gray-800 dark:text-gray-100 bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300 text-left transition-colors">
            <i data-lucide="eye" class="w-4 h-4"></i>
            <span>View photo</span>
          </button>
          <button type="button" onclick="document.getElementById('cover-photo-input').click(); closeCoverPhotoMenu();"
            class="no-faith-hover w-full flex items-center gap-3 px-4 py-3 text-base font-semibold text-gray-800 dark:text-gray-100 bg-white dark:bg-gray-800 hover:bg-blue-50 dark:hover:bg-blue-900/30 hover:text-blue-700 dark:hover:text-blue-300 text-left border-t border-gray-100 dark:border-gray-700 transition-colors">
            <i data-lucide="camera" class="w-4 h-4"></i>
            <span>Change cover photo</span>
          </button>
        </div>
        <input type="file" id="cover-photo-input" accept="image/jpeg,image/png,image/webp" class="hidden"
          onchange="handleCoverPhotoUpload(this)" />
      </div>

      <!-- Cover Photo Lightbox / Viewer -->
      <div id="cover-photo-lightbox" class="hidden fixed inset-0 bg-black/90 z-[200] items-center justify-center p-4"
        onclick="closeCoverPhotoLightbox(event)">
        <button type="button" onclick="closeCoverPhotoLightbox()"
          class="no-faith-hover absolute top-4 right-4 w-10 h-10 flex items-center justify-center bg-white/10 hover:bg-white/20 text-white rounded-full"
          title="Close">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
        <img id="cover-photo-lightbox-img" src="" alt="Cover Photo"
          class="max-w-full max-h-full object-contain rounded-lg shadow-2xl" />
      </div>

      <!-- Profile Info Row -->
      <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex flex-col xl:flex-row xl:items-end gap-4 pb-4">
          <!-- Avatar (overlapping cover) -->
          <div class="relative flex-shrink-0 self-start w-max -mt-12 sm:-mt-16 z-10">
            <div id="cover-profile-frame"
              class="faith-frame faith-frame-shell faith-frame-generic faith-frame-bg-generic w-36 h-36 sm:w-44 sm:h-44 text-brand-900 dark:text-brand-100 relative cursor-pointer group/avatar"
              onclick="openProfileModal()">
              <div id="cover-profile-media" class="faith-frame-photo font-extrabold text-4xl text-gray-700">
                <span id="cover-profile-letter">U</span>
                <img id="cover-profile-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden" />
                <!-- Camera overlay on hover -->
                <div
                  class="absolute inset-0 bg-black/28 flex items-center justify-center opacity-0 group-hover/avatar:opacity-100 transition-opacity rounded-full">
                  <i data-lucide="camera" class="w-6 h-6 text-white"></i>
                </div>
              </div>
            </div>
            <!-- Online dot -->
            <span
              class="profile-status-dot w-4 h-4 bg-green-500 border-2 border-white dark:border-gray-900 rounded-full block"></span>
          </div>

          <!-- Name + stats -->
          <div class="w-full xl:flex-1 pb-1 min-w-0">
            <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white leading-tight break-words"
              style="overflow-wrap: normal;" id="cover-profile-name">
              User Name
            </h2>
            <p class="text-gray-500 dark:text-gray-400 text-sm mt-0.5" id="cover-profile-friends">
              <span class="font-semibold text-gray-700 dark:text-gray-300">0</span>
              friends
            </p>
            <!-- Friend avatar stack placeholder -->
            <div class="flex -space-x-2 mt-2" id="cover-friend-stack"></div>
            <!-- Profile interest tags -->
            <div class="flex flex-wrap gap-1.5 mt-3" id="cover-profile-tags"></div>
          </div>

          <!-- Action buttons -->
          <div class="w-full xl:w-auto flex items-center gap-2 pb-2 flex-wrap">
            <button onclick="openProfileModal()"
              style="--no-faith-hover-bg: var(--brand-500, #e04848); --no-faith-hover-color: #ffffff; --no-faith-hover-border: var(--brand-500, #e04848);"
              class="no-faith-hover bg-brand-100 hover:bg-brand-500 text-brand-700 hover:text-white dark:bg-gray-900 dark:hover:bg-gray-800 dark:text-brand-200 dark:hover:text-brand-100 dark:border dark:border-brand-900/60 dark:hover:border-brand-700 flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
              <i data-lucide="pencil" class="w-4 h-4"></i>
              <span>Edit profile</span>
            </button>
            <button onclick="openPackMembersModal()"
              style="--no-faith-hover-bg: var(--brand-500, #e04848); --no-faith-hover-color: #ffffff; --no-faith-hover-border: var(--brand-500, #e04848);"
              class="no-faith-hover bg-brand-100 hover:bg-brand-500 text-brand-700 hover:text-white dark:bg-gray-900 dark:hover:bg-gray-800 dark:text-brand-200 dark:hover:text-brand-100 dark:border dark:border-brand-900/60 dark:hover:border-brand-700 flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
              <i data-lucide="users" class="w-4 h-4"></i>
              <span>Add Pack</span>
            </button>
            <button onclick="openPackTree()"
              style="--no-faith-hover-bg: var(--brand-500, #e04848); --no-faith-hover-color: #ffffff; --no-faith-hover-border: var(--brand-500, #e04848);"
              class="no-faith-hover bg-brand-100 hover:bg-brand-500 text-brand-700 hover:text-white dark:bg-gray-900 dark:hover:bg-gray-800 dark:text-brand-200 dark:hover:text-brand-100 dark:border dark:border-brand-900/60 dark:hover:border-brand-700 flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
              <i data-lucide="network" class="w-4 h-4"></i>
              <span>Pack Tree</span>
            </button>
            <button onclick="openHoroscopeView()"
              style="--no-faith-hover-bg: var(--brand-500, #e04848); --no-faith-hover-color: #ffffff; --no-faith-hover-border: var(--brand-500, #e04848);"
              class="no-faith-hover bg-brand-100 hover:bg-brand-500 text-brand-700 hover:text-white dark:bg-gray-900 dark:hover:bg-gray-800 dark:text-brand-200 dark:hover:text-brand-100 dark:border dark:border-brand-900/60 dark:hover:border-brand-700 flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
              <i data-lucide="star" class="w-4 h-4"></i>
              <span>Pet Profile</span>
            </button>
            <div class="relative">
              <button onclick="document.getElementById('profile-more-menu').classList.toggle('hidden')"
                class="flex items-center gap-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
                <i data-lucide="more-horizontal" class="w-4 h-4"></i>
              </button>
              <div id="profile-more-menu"
                class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-50">
                <button
                  onclick="navigator.clipboard.writeText(window.location.href); showToast('Profile link copied!', 'success'); document.getElementById('profile-more-menu').classList.add('hidden');"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i
                    data-lucide="link" class="w-4 h-4"></i> Copy Link</button>
                <button
                  onclick="switchSocialTab('settings'); switchAccountSettingsSection('overview'); document.getElementById('profile-more-menu').classList.add('hidden');"
                  class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i
                    data-lucide="settings" class="w-4 h-4"></i> Settings</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Divider + tab strip  -->
        <div id="social-tab-strip"
          class="nav-scroll relative border-t border-gray-200 dark:border-gray-700 flex items-center gap-1 overflow-x-auto no-scrollbar pt-1">
          <button data-tab="feed" onclick="switchSocialTab('feed')"
            class="social-tab-item px-4 py-3 text-sm font-semibold text-brand-500 whitespace-nowrap">
            PawFeed
          </button>
          <button data-tab="friends" onclick="switchSocialTab('friends')"
            class="social-tab-item px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            PawPals
          </button>
          <button data-tab="connections" onclick="switchSocialTab('connections')"
            class="social-tab-item px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            <i data-lucide="phone" class="w-3.5 h-3.5 inline-block align-[-2px] mr-1"></i>PawParents
          </button>
          <button data-tab="groups" onclick="switchSocialTab('groups')"
            class="social-tab-item px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            PetGroups
          </button>
          <!-- Posts tab removed from navbar — your posts are shown when you open your own profile. -->
          <button data-tab="my-posts" onclick="switchSocialTab('my-posts')"
            class="social-tab-item hidden px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            Posts
          </button>
          
          <!-- Rescue Marketplace hidden for now (code retained). -->
          <button data-tab="rescue" onclick="switchSocialTab('rescue')"
            class="social-tab-item hidden px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            Rescue Marketplace
          </button>
          <button data-tab="events" onclick="switchSocialTab('events')"
            class="social-tab-item px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            PetEvents
          </button>
          <button data-tab="galleries" onclick="switchSocialTab('galleries')"
            class="social-tab-item px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            PawSmiles
          </button>

          <button data-tab="playdate" onclick="switchSocialTab('playdate')"
            class="social-tab-item px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            <span class="inline-flex items-center gap-1">
              <i data-lucide="heart-handshake" class="w-4 h-4"></i>
              <span>PawMatches</span>
            </span>
          </button>
          <!-- Obituaries hidden for now (code retained). -->
          <button data-tab="obituaries" onclick="switchSocialTab('obituaries')"
            class="social-tab-item hidden px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            Obituaries
          </button>
          <!-- PawCircle hidden for now (code retained). -->
          <button onclick="switchView('view-content-hub')"
            class="social-tab-item hidden px-4 py-3 text-sm font-semibold text-gray-600 dark:text-gray-400 whitespace-nowrap">
            PawCircle
          </button>

          <div id="social-tab-indicator"
            class="absolute bottom-0 h-0.5 bg-brand-500 transition-all duration-300 ease-out"></div>
        </div>
      </div>
    </div>
    <!-- ======= End Cover Photo Header ======= -->

    <div id="main-content-area" class="max-w-7xl mx-auto w-full px-4 py-6 flex flex-col lg:flex-row gap-6 relative">
      <!-- Left Sidebar -->
      <aside id="social-left-sidebar" class="hidden lg:flex flex-col w-56 flex-shrink-0 self-start">
        <div class="flex-1 min-h-0 space-y-1 pr-2">
          <!-- Hidden compatibility nodes for legacy JS that reference sidebar profile -->
          <span id="sidebar-profile-avatar" class="hidden"><span id="sidebar-profile-avatar-letter"></span><img
              id="sidebar-profile-avatar-img" src="" alt="" /></span><span id="sidebar-profile-name"
            class="hidden"></span>
          <div class="h-4"></div>

          <!-- Legacy left navigation kept in DOM (hidden); navigation now lives in the top tab strip & right-side Quick Links -->
          <div class="flex flex-col gap-1 mt-4">
            <button id="tab-btn-feed" onclick="switchSocialTab('feed')"
              class="social-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-amber-50 dark:bg-amber-900/30 text-amber-500 p-1.5 rounded-lg">
                <i data-lucide="layout-list" class="w-5 h-5"></i>
              </div>
              PawFeed
            </button>
            <button id="tab-btn-friends" onclick="switchSocialTab('friends')"
              class="social-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-blue-50 dark:bg-blue-900/30 text-blue-500 p-1.5 rounded-lg">
                <i data-lucide="users" class="w-5 h-5"></i>
              </div>
              PawPals
            </button>
            <button id="tab-btn-connections" onclick="switchSocialTab('connections')"
              class="social-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-teal-50 dark:bg-teal-900/30 text-teal-500 p-1.5 rounded-lg">
                <i data-lucide="phone" class="w-5 h-5"></i>
              </div>
              PawParents
            </button>
            <button id="tab-btn-groups" onclick="switchSocialTab('groups')"
              class="social-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-emerald-50 dark:bg-emerald-900/30 text-emerald-500 p-1.5 rounded-lg">
                <i data-lucide="landmark" class="w-5 h-5"></i>
              </div>
              PetGroups
            </button>
            
            <button id="tab-btn-rescue" onclick="switchSocialTab('rescue')"
              class="social-tab-btn hidden w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-rose-50 dark:bg-rose-900/30 text-rose-500 p-1.5 rounded-lg shrink-0">
                <i data-lucide="hand-heart" class="w-5 h-5"></i>
              </div>
              <span class="flex-1 text-left leading-tight whitespace-normal">Rescue Marketplace</span>
            </button>
            <button id="tab-btn-events" onclick="switchSocialTab('events')"
              class="social-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-purple-50 dark:bg-purple-900/30 text-purple-500 p-1.5 rounded-lg">
                <i data-lucide="calendar-heart" class="w-5 h-5"></i>
              </div>
              PetEvents
            </button>
            <button id="tab-btn-galleries" onclick="switchSocialTab('galleries')"
              class="social-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-violet-50 dark:bg-violet-900/30 text-violet-500 p-1.5 rounded-lg">
                <i data-lucide="images" class="w-5 h-5"></i>
              </div>
              PawSmiles
            </button>


            <button id="tab-btn-playdate" onclick="switchSocialTab('playdate')"
              class="social-tab-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-pink-50 dark:bg-pink-900/30 text-pink-500 p-1.5 rounded-lg">
                <i data-lucide="heart-handshake" class="w-5 h-5"></i>
              </div>
              PawMatches
            </button>
            <button id="tab-btn-obituaries" onclick="switchSocialTab('obituaries')"
              class="social-tab-btn hidden w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-gray-50 dark:bg-gray-800/30 text-gray-500 p-1.5 rounded-lg">
                <i data-lucide="flower" class="w-5 h-5"></i>
              </div>
              Obituaries
            </button>

            <!-- ── New feature shortcuts ── -->
            <div class="hidden pt-4 pb-1 px-1 text-xs font-black uppercase tracking-wider text-gray-400">Explore</div>

            <!-- PawCircle hidden for now (code retained). -->
            <button onclick="switchView('view-content-hub')"
              class="social-tab-btn no-faith-hover hidden w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
              <div class="bg-red-50 dark:bg-red-900/30 text-red-500 p-1.5 rounded-lg">
                <i data-lucide="tv-2" class="w-5 h-5"></i>
              </div>
              PawCircle
            </button>
          </div>

        </div>
      </aside>
      <aside id="settings-left-sidebar" class="hidden lg:hidden flex-col w-72 flex-shrink-0 self-start">
        <div class="space-y-2 pr-2">
          <button type="button" onclick="switchSocialTab('feed')"
            class="w-full flex items-center gap-3 px-3 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
            Back to PawCircle
          </button>
          <div class="pt-6 pb-2 px-3 text-xs font-black uppercase tracking-wider text-gray-400">Account settings</div>
          <button type="button" onclick="switchAccountSettingsSection('overview')" data-settings-section="overview"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="layout-grid" class="w-5 h-5 text-brand-500"></i> Overview</button>
          <button type="button" onclick="switchAccountSettingsSection('personal')" data-settings-section="personal"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="id-card" class="w-5 h-5 text-blue-500"></i> Personal details</button>
          <button type="button" onclick="switchAccountSettingsSection('security')" data-settings-section="security"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="shield-check" class="w-5 h-5 text-emerald-500"></i> Security</button>
          <button type="button" onclick="switchAccountSettingsSection('privacy')" data-settings-section="privacy"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="lock" class="w-5 h-5 text-green-600"></i> Privacy</button>
          <button type="button" onclick="switchAccountSettingsSection('galleries')" data-settings-section="galleries"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="images" class="w-5 h-5 text-violet-500"></i> Events & galleries</button>
          <button type="button" onclick="switchAccountSettingsSection('posts')" data-settings-section="posts"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="panel-top" class="w-5 h-5 text-pink-500"></i> Posts</button>
          <button type="button" onclick="switchAccountSettingsSection('archived')" data-settings-section="archived"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="archive" class="w-5 h-5 text-amber-500"></i> Archived posts</button>
          
          <button type="button" onclick="switchAccountSettingsSection('pack')" data-settings-section="pack"
            class="settings-side-btn no-faith-hover w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="network" class="w-5 h-5 text-teal-500"></i> Pack & pet_profile</button>
          <button type="button" onclick="switchAccountSettingsSection('blocked')" data-settings-section="blocked"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="ban" class="w-5 h-5 text-gray-500"></i> Blocked accounts</button>
          <button type="button" onclick="switchAccountSettingsSection('danger')" data-settings-section="danger"
            class="settings-side-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl text-red-600 hover:bg-white dark:hover:bg-gray-900 font-semibold transition-colors"><i
              data-lucide="triangle-alert" class="w-5 h-5"></i> Account actions</button>
        </div>
      </aside>

      <!-- Main Feed -->
      <div id="social-main-column" class="flex-1 max-w-2xl mx-auto space-y-6">
        <!-- Tab: Feed -->
        <div id="social-tab-feed" class="social-tab-content space-y-6">
          <!-- Smart Filters & Suvichar -->
          <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
            <div id="feed-filters" class="nav-scroll flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
              <button onclick="setActiveFilter(this, 'All Posts')"
                class="feed-filter-btn px-6 py-2 rounded-full bg-[#f97316] text-white text-base font-bold whitespace-nowrap shadow-md hover:bg-[#ea580c] transition-colors border-2 border-[#f97316]">All Posts</button>
              <button onclick="setActiveFilter(this, 'My Pack')"
                class="feed-filter-btn px-6 py-2 rounded-full bg-white text-gray-600 hover:text-[#f97316] hover:border-[#f97316] text-base font-semibold whitespace-nowrap shadow-sm border-2 border-gray-200 transition-all">My Pack</button>
              <button onclick="setActiveFilter(this, 'Playdate')"
                class="feed-filter-btn px-6 py-2 rounded-full bg-white text-gray-600 hover:text-[#f97316] hover:border-[#f97316] text-base font-semibold whitespace-nowrap shadow-sm border-2 border-gray-200 transition-all">Playdate</button>
              <button onclick="setActiveFilter(this, 'Business')"
                class="feed-filter-btn px-6 py-2 rounded-full bg-white text-gray-600 hover:text-[#f97316] hover:border-[#f97316] text-base font-semibold whitespace-nowrap shadow-sm border-2 border-gray-200 transition-all">Business</button>
              <button onclick="setActiveFilter(this, 'Announcements')"
                class="feed-filter-btn px-6 py-2 rounded-full bg-white text-gray-800 hover:bg-black hover:text-white text-base font-semibold whitespace-nowrap shadow-sm border-2 border-black transition-all">Announcements</button>
            </div>
            <button id="send-suvichar-btn" onclick="sendSuvichar()"
              style="--no-faith-hover-bg:#f97316;--no-faith-hover-color:#ffffff;--no-faith-hover-border:#f97316;"
              class="no-faith-hover bg-orange-500 text-white border border-orange-500 px-4 py-2 rounded-xl text-base font-bold shadow-md flex items-center gap-2 shadow-lg shadow-orange-500/20 transition-all transform hover:scale-105 whitespace-nowrap flex-shrink-0">
              <i data-lucide="sun" class="w-4 h-4"></i> Send Suvichar
            </button>
          </div>

          <!-- Simplified Post Composer -->
          <div id="feed-post-composer" class="bg-white/80 dark:bg-gray-900/80 backdrop-blur-xl rounded-3xl shadow-lg shadow-gray-200/50 dark:shadow-black/20 border border-gray-100/80 dark:border-gray-800/80 p-4 sm:p-5 transition-all duration-300 hover:shadow-xl">
            <div class="flex gap-4 items-start">
              <div id="feed-create-avatar" class="w-12 h-12 bg-gradient-to-br from-brand-100 to-brand-200 dark:from-brand-900/60 dark:to-brand-800/40 rounded-full flex items-center justify-center text-brand-700 dark:text-brand-200 font-bold flex-shrink-0 shadow-inner">U</div>
              <div class="flex-1 min-w-0 space-y-4">
                <textarea id="feed-post-input" rows="1" placeholder="Share a moment from your pet's world..." onfocus="setPostComposerExpanded(true);" oninput="autoResizePostComposerTextarea();" class="w-full resize-none overflow-hidden rounded-2xl border border-transparent bg-gray-50/80 dark:bg-gray-800/50 px-4 py-3.5 text-base text-gray-800 dark:text-gray-100 outline-none transition-all duration-300 focus:border-brand-400/50 focus:bg-white dark:focus:bg-gray-900 focus:shadow-md dark:focus:shadow-brand-900/20"></textarea>
                <div id="feed-post-expanded-fields" class="space-y-4 hidden">
                  <input type="file" id="feed-post-media-input" accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,video/x-m4v" multiple class="hidden" onchange="handlePostMediaSelected(event)" />
                  <div id="feed-post-feeling-row" class="hidden space-y-3 rounded-2xl border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 p-3.5">
                    <div class="flex items-center justify-between gap-2 px-1">
                      <p class="text-[13px] font-semibold text-gray-700 dark:text-gray-300">How is your pet feeling?</p>
                      <button type="button" onclick="clearPostFeeling()" class="text-xs font-medium text-gray-500 hover:text-gray-800 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">Clear</button>
                    </div>
                    <input type="hidden" id="feed-post-feeling" value="" />
                    <input type="hidden" id="feed-post-activity" value="" />
                    <div id="feed-post-feeling-selected" class="hidden flex items-center justify-center gap-2 rounded-xl border border-brand-200 bg-brand-50/50 px-4 py-2.5 text-sm font-medium text-brand-700 dark:border-brand-900/50 dark:bg-brand-900/20 dark:text-brand-300"></div>
                    <div id="feed-post-feeling-options" class="grid grid-cols-2 gap-2 sm:grid-cols-3"></div>
                  </div>
                  <div id="feed-post-tag-row" class="hidden space-y-2 rounded-2xl border border-sky-100 bg-sky-50/50 p-2 dark:border-sky-900/50 dark:bg-sky-950/20">
                    <div class="flex items-center gap-2">
                      <i data-lucide="users" class="h-4 w-4 text-sky-500"></i>
                      <input type="text" id="feed-post-tags-input" list="feed-post-tags-options" placeholder="Tag friends (type a name and press Enter)" onkeydown="handlePostTaggedPawPalsKeydown(event)" onblur="addTaggedPawPalsFromInput()" class="w-full rounded-xl border border-sky-100 bg-white px-3 py-2 text-sm text-gray-700 outline-none transition-colors focus:border-sky-400 dark:border-sky-900/40 dark:bg-gray-900 dark:text-gray-200" />
                      <button type="button" onclick="addTaggedPawPalsFromInput()" class="rounded-xl bg-sky-500 px-3 py-2 text-xs font-bold text-white hover:bg-sky-600">Add</button>
                    </div>
                    <datalist id="feed-post-tags-options"></datalist>
                    <div id="feed-post-tags-chips" class="flex flex-wrap gap-2"></div>
                  </div>
                  <div id="feed-post-media-url-row" class="hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-2">
                    <div class="flex items-center gap-2">
                      <input type="url" id="feed-post-media-url" placeholder="Paste a secure media URL" class="w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-700 dark:text-gray-200 outline-none transition-colors focus:border-brand-400 dark:focus:border-brand-400" />
                      <button type="button" onclick="addPostMediaUrl()" class="px-3 py-2 rounded-xl bg-[#f97316] text-white text-xs font-bold hover:bg-[#ea580c]">Add</button>
                    </div>
                  </div>
                  <div id="feed-post-media-preview" class="hidden rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50"></div>
                </div>
              </div>
            </div>
            <!-- Quick action icons under composer -->
            <div id="feed-post-quick-actions" class="mt-4 hidden p-2.5 bg-gray-50/50 dark:bg-gray-800/30 rounded-2xl border border-gray-100 dark:border-gray-800/60 text-sm flex flex-row items-center gap-2 overflow-x-auto hide-scrollbar">
              <button id="feed-post-open-media" type="button" onclick="openPostMediaPicker()" class="flex flex-row items-center gap-2 px-3 py-2 rounded-xl text-gray-600 dark:text-gray-300 hover:bg-brand-50 hover:text-brand-600 dark:hover:bg-brand-900/30 dark:hover:text-brand-400 transition-colors">
                <i data-lucide="image" class="w-4 h-4"></i>
                <span class="text-xs font-semibold">Media</span>
              </button>
              <button id="feed-post-tag" type="button" onclick="openPostTagInput()" class="flex flex-row items-center gap-2 px-3 py-2 rounded-xl text-gray-600 dark:text-gray-300 hover:bg-sky-50 hover:text-sky-600 dark:hover:bg-sky-900/30 dark:hover:text-sky-400 transition-colors">
                <i data-lucide="at-sign" class="w-4 h-4"></i>
                <span class="text-xs font-semibold">Tag</span>
              </button>
              <button id="feed-post-open-feeling" type="button" onclick="openPostFeelingPicker()" class="flex flex-row items-center gap-2 px-3 py-2 rounded-xl text-gray-600 dark:text-gray-300 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-900/30 dark:hover:text-amber-400 transition-colors">
                <i id="feed-post-feeling-icon" data-lucide="smile-plus" class="w-4 h-4"></i>
                <span class="text-xs font-semibold">Feeling</span>
              </button>
              <button id="feed-post-event" type="button" onclick="openAddEventModal(new Date().toISOString().split('T')[0])" class="flex flex-row items-center gap-2 px-3 py-2 rounded-xl text-gray-600 dark:text-gray-300 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/30 dark:hover:text-blue-400 transition-colors">
                <i data-lucide="calendar" class="w-4 h-4"></i>
                <span class="text-xs font-semibold">Event</span>
              </button>
              <button id="feed-post-media-url" type="button" onclick="togglePostMediaUrlInput()" class="flex flex-row items-center gap-2 px-3 py-2 rounded-xl text-gray-600 dark:text-gray-300 hover:bg-violet-50 hover:text-violet-600 dark:hover:bg-violet-900/30 dark:hover:text-violet-400 transition-colors">
                <i data-lucide="link" class="w-4 h-4"></i>
                <span class="text-xs font-semibold">Link</span>
              </button>
            </div>
            <div class="mt-4 flex items-center justify-between gap-3 pt-2">
              <p id="feed-post-status" class="hidden text-xs font-medium text-gray-500 dark:text-gray-400"></p>
              <div class="flex items-center gap-3 ml-auto">
                <button id="feed-post-cancel-btn" type="button" onclick="resetPostComposer()" class="px-5 py-2.5 rounded-full text-sm font-semibold text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-all duration-200">Cancel</button>
                <button id="feed-post-submit-btn" type="button" onclick="submitPost()" class="px-6 py-2.5 rounded-full bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white text-sm font-bold shadow-md hover:shadow-lg transform hover:-translate-y-0.5 transition-all duration-200 active:scale-95">Post</button>
              </div>
            </div>
          </div>
          <script>
            (function(){
              const composer = document.getElementById('feed-post-composer');
              const input = document.getElementById('feed-post-input');

              if (input) {
                if (typeof autoResizePostComposerTextarea === 'function') autoResizePostComposerTextarea();
                input.addEventListener('input', function() {
                  if (typeof autoResizePostComposerTextarea === 'function') autoResizePostComposerTextarea();
                });
              }

              if (typeof updateFeedFeelingIcon === 'function') updateFeedFeelingIcon();

              if (composer) {
                composer.addEventListener('focusin', function(){
                  setPostComposerExpanded(true);
                });
                composer.addEventListener('focusout', function(){
                  setTimeout(function(){
                    if (!composer.contains(document.activeElement)) {
                      setPostComposerExpanded(false);
                    }
                  }, 0);
                });
              }

            })();
          </script>

          <!-- Feed Highlights Container -->
          <div id="social-feed-posts" class="space-y-6">
            <!-- Posts will be injected here via JS -->
          </div>
        </div>

        <!-- Tab: Post Detail -->
        <div id="social-tab-post-detail" class="social-tab-content space-y-6 hidden">
          <div id="post-detail-view"></div>
        </div>

        <!-- Tab: Friends -->
        <div id="social-tab-friends" class="social-tab-content space-y-6 hidden">
          <div
            class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-8 text-center">
            <i data-lucide="users" class="w-12 h-12 text-gray-300 dark:text-gray-600 mx-auto mb-4"></i>
            <h3 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-2">
              Your Friends
            </h3>
            <p class="text-gray-500 dark:text-gray-400 text-sm">
              Connect with members of your breed here.
            </p>
          </div>
        </div>

        <!-- Tab: Connections -->
        <div id="social-tab-connections" class="social-tab-content space-y-6 hidden">
          <!-- Header with gradient + live count -->
          <div class="rounded-2xl overflow-hidden border border-gray-100 dark:border-gray-800 shadow-sm">
            <div class="p-6 sm:p-7 text-white"
              style="background: linear-gradient(135deg, var(--faith-accent,#0d9488), color-mix(in srgb, var(--faith-accent,#0d9488) 55%, #111827));">
              <div class="flex items-center justify-between gap-4 flex-wrap">
                <div>
                  <h3 class="text-2xl font-bold" style="font-family:'DM Serif Display'">Pet Breed Connections</h3>
                  <p class="text-sm text-white/80 mt-1">Reach out to coordinators, trustees and rescues across the
                    samaj.</p>
                </div>
                <div class="text-right">
                  <p class="text-3xl font-extrabold leading-none" id="connections-count">0</p>
                  <p class="text-xs uppercase tracking-wider text-white/70 mt-1">connections</p>
                </div>
              </div>
            </div>
            <div class="bg-white dark:bg-gray-900 p-4 sm:p-5 space-y-4">
              <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-1">
                  <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                  <input type="text" id="connections-search" oninput="renderConnections()"
                    placeholder="Search by name, place or role..."
                    class="w-full pl-9 pr-4 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-200 dark:text-gray-200">
                </div>
                <input type="text" id="connections-place-filter" oninput="renderConnections()"
                  placeholder="Filter by place..."
                  class="w-full sm:w-48 px-4 py-2.5 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-200 dark:text-gray-200">
                <div class="flex bg-gray-100 dark:bg-gray-800 rounded-xl p-1 shrink-0">
                  <button id="connections-view-grid" onclick="setConnectionsView('grid')"
                    class="px-3 py-1.5 rounded-lg text-gray-500 dark:text-gray-400 transition-colors" title="Grid view">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                  </button>
                  <button id="connections-view-list" onclick="setConnectionsView('list')"
                    class="px-3 py-1.5 rounded-lg text-gray-500 dark:text-gray-400 transition-colors" title="List view">
                    <i data-lucide="list" class="w-4 h-4"></i>
                  </button>
                </div>
              </div>
              <!-- Quick place chips -->
              <div id="connections-chips" class="flex flex-wrap gap-2"></div>
            </div>
          </div>

          <div id="connections-results"></div>
        </div>

        <!-- Tab: Groups (Official Packs + dynamic groups all rendered by renderGroups) -->
        <div id="social-tab-groups" class="social-tab-content space-y-6 hidden"></div>

        <!-- Tab: Live Events -->
        <!-- Tab: Rescue Marketplace -->
        <div id="social-tab-rescue" class="social-tab-content space-y-6 hidden"></div>

        <!-- Tab: Events -->
        <div id="social-tab-events" class="social-tab-content space-y-6 hidden">
          <!-- Sub-tab switch: Events list / Analytics -->
          <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-800">
            <button id="events-subtab-btn-list" onclick="setEventsSubTab('list')"
              class="events-subtab-btn px-4 py-3 text-base font-bold shadow-md border-b-2 border-transparent text-gray-500 dark:text-gray-400 transition-colors">
              <i data-lucide="calendar-heart" class="w-4 h-4 inline-block -mt-0.5"></i> Events
            </button>
            <button id="events-subtab-btn-analytics" onclick="setEventsSubTab('analytics')"
              class="events-subtab-btn px-4 py-3 text-base font-bold shadow-md border-b-2 border-transparent text-gray-500 dark:text-gray-400 transition-colors">
              <i data-lucide="bar-chart-3" class="w-4 h-4 inline-block -mt-0.5"></i> Analytics
            </button>
          </div>

          <!-- Sub-panel: Events list -->
          <div id="events-subtab-list" class="space-y-6">
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-200"
                  style="font-family: &quot;DM Serif Display&quot;">
                  Upcoming Events
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                  Discover religious and social events near you.
                </p>
              </div>
              <button onclick="openAddEventModal(new Date().toISOString().split('T')[0])"
                class="bg-brand-500 hover:bg-brand-600 text-white px-5 py-2.5 rounded-xl text-base font-bold shadow-md flex items-center gap-2 shadow-lg shadow-brand-500/30 transition-colors w-full sm:w-auto justify-center">
                <i data-lucide="plus" class="w-4 h-4"></i> Create Event
              </button>
            </div>

            <div class="space-y-4" id="upcoming-events-container">
            </div>

            <div id="past-events-section" class="hidden space-y-4 pt-2">
              <div class="flex items-center gap-3">
                <h3 class="text-lg font-bold text-gray-700 dark:text-gray-300">Past events</h3>
                <span class="h-px flex-1 bg-gray-100 dark:bg-gray-800"></span>
              </div>
              <div class="space-y-4" id="past-events-container"></div>
            </div>

            <div class="pt-2">
              <button onclick="openAllEventsPanel()"
                class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 text-base font-bold shadow-md text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                <i data-lucide="calendar-search" class="w-4 h-4"></i> View all events
              </button>
            </div>
          </div>

          <!-- Sub-panel: Analytics -->
          <div id="events-subtab-analytics" class="hidden space-y-6">
            <div>
              <h3 class="text-2xl font-bold text-gray-800 dark:text-gray-200"
                style="font-family: &quot;DM Serif Display&quot;">
                Event Analytics
              </h3>
              <p class="text-sm text-gray-500 mt-1">Attendance trends and audience make-up, computed from your
                breed's real events.</p>
            </div>
            <div id="events-analytics-stats" class="grid grid-cols-2 lg:grid-cols-4 gap-3"></div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div
                class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm p-6">
                <h4 class="text-base font-bold text-gray-800 dark:text-white mb-4">Attendance Over Time</h4>
                <canvas id="chart-attendance" height="220"></canvas>
              </div>
              <div
                class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-100 dark:border-gray-800 shadow-sm p-6">
                <h4 class="text-base font-bold text-gray-800 dark:text-white mb-4">Audience by Pet Type</h4>
                <canvas id="chart-demographics" height="220"></canvas>
              </div>
            </div>
            <p id="events-analytics-source" class="text-xs text-gray-400"></p>
          </div>
        </div>

        <!-- Tab: All events (button-access only; not in the nav bar) -->
        <div id="social-tab-all-events" class="social-tab-content space-y-6 hidden"></div>

        <!-- Tab: Galleries -->
        <div id="social-tab-galleries" class="social-tab-content space-y-6 hidden"></div>


        <!-- Tab: Playdate -->
        <div id="social-tab-playdate" class="social-tab-content space-y-4 hidden">
          <!-- Rendered dynamically by renderPlaydate() -->
        </div>

        <!-- Playdate Profile Detail Modal -->
        <?php include __DIR__ . '/../modals/mm-detail-modal.php'; ?>

        <!-- Playdate Forward Modal -->
        <?php include __DIR__ . '/../modals/mm-forward-modal.php'; ?>

        <!-- Tab: Obituaries -->
        <div id="social-tab-obituaries" class="social-tab-content space-y-6 hidden"></div>



        <!-- Tab: Account Settings -->
        <div id="social-tab-settings" class="social-tab-content space-y-6 hidden"></div>

        <!-- Tab: Posts -->
        <div id="social-tab-my-posts" class="social-tab-content space-y-6 hidden">
          <div
            class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-800 p-4 sm:p-6 mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
              <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">Posts</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">View and manage all the posts you have shared
                  with the breed.</p>
              </div>
              <div class="flex items-center gap-3">
                <button id="toggle-archived-posts-btn" onclick="toggleArchivedPosts()"
                  class="px-4 py-2 text-base font-semibold border border-gray-200 dark:border-gray-700 rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors dark:text-gray-200 whitespace-nowrap">View
                  Archived</button>
              </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
              <div class="relative flex-1">
                <i data-lucide="search" class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                <input type="text" id="my-posts-search" oninput="renderMyPosts()" placeholder="Search your posts..."
                  class="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-gray-900 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
              </div>
              <select id="my-posts-filter" onchange="renderMyPosts()"
                class="w-full sm:w-48 px-4 py-2 border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 text-gray-900 dark:text-gray-100 rounded-xl focus:ring-2 focus:ring-brand-500 focus:border-brand-500 text-sm">
                <option value="all">All Posts</option>
                <option value="text">Text Posts</option>
                <option value="media">Media Posts</option>
              </select>
            </div>
          </div>
          <div id="my-posts-container" class="space-y-6"></div>
        </div>
      </div>

      <!-- Right Sidebar -->
      <div id="social-right-sidebar" class="block lg:hidden xl:block w-full xl:w-72 xl:mr-3 flex-shrink-0 self-start space-y-6">
        <!-- Today's Highlight: Festival / Daily Inspiration -->
        <div id="sidebar-highlight-card"
          class="w-full rounded-xl bg-white dark:bg-gray-900 shadow-sm border border-gray-200 dark:border-gray-700 mb-4 overflow-hidden">
          <div id="sidebar-highlight-banner" class="h-20 relative flex items-center justify-center"
            style="background: var(--faith-accent, #f59e0b)">
            <i id="sidebar-highlight-icon" data-lucide="sparkles" class="w-10 h-10 text-white drop-shadow"></i>
          </div>
          <div class="p-4">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">
              <span id="sidebar-highlight-label">Today's Highlight</span>
            </div>
            <div class="font-bold text-gray-900 dark:text-white text-base leading-tight mt-1"
              id="sidebar-highlight-title">
              Welcome to PawCircle
            </div>
            <div class="text-xs text-gray-600 dark:text-gray-400 mt-1.5 leading-relaxed" id="sidebar-highlight-text">
              Connect with your breed, share moments, and celebrate
              together.
            </div>
            <button onclick="switchSocialTab('events')"
              class="faith-accent-btn mt-3 w-full text-xs font-semibold py-2 rounded-lg transition-colors text-white"
              style="background: var(--faith-accent, #f59e0b)">
              View Events
            </button>
          </div>
        </div>

        <!-- Innovative left rail (placeholder for future ad inventory) -->
        <div id="left-ad-rail" class="space-y-4 pt-1"></div>

        <!-- The scrolling "Dharmic Granth" holy-book panel was removed from the
             sidebar; it now lives in the dedicated Dharmic Granth navbar tab. -->



        <!-- Sidebar Calendar Widget -->
        <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5">
          <div id="sidebar-calendar-container">
            <!-- Sidebar calendar injected here -->
          </div>
        </div>


      </div>
      </aside>
    </div>
  </div>