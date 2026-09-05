<div id="view-social-feed" class="view-section hidden min-h-screen bg-gray-50 dark:bg-gray-950 flex-col w-full">

  <!-- ======= Top Header (branding + search + action icons) ======= -->
  <div class="bg-white/95 dark:bg-gray-900/95 backdrop-blur-sm border-b border-gray-200 dark:border-gray-800 w-full relative z-50">
    <div class="max-w-7xl mx-auto px-4 py-3">
      <div class="grid grid-cols-[minmax(0,1fr)_auto] xl:grid-cols-[auto_minmax(260px,1fr)_auto] items-center gap-3 xl:gap-6">
        <!-- Branding -->
        <button onclick="switchView('view-social-feed'); switchSocialTab('hub');" class="no-accent-hover flex items-center gap-2 sm:gap-3 min-w-0 text-left">
          <!-- The tile is overflow-hidden (it clips the logo into the rounded
               square), so the hover paw-puffs cannot live inside it. This
               wrapper is their escape hatch — see .pc-brand-logo in motion.css. -->
          <span class="pc-brand-logo">
            <span class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-brand-500 flex items-center justify-center overflow-hidden flex-shrink-0">
              <img src="assets/mascots/pawcircle-logo.svg" alt="" class="w-7 h-7 object-contain">
            </span>
            <span class="pc-paw-puffs" aria-hidden="true"></span>
          </span>
          <div class="min-w-0">
            <h1 class="text-lg sm:text-xl font-extrabold text-gray-900 dark:text-white leading-tight truncate" style="font-family:'Poppins'">PawCircle</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400 truncate hidden sm:block">Find your pack</p>
          </div>
        </button>

        <!-- Search -->
        <div class="col-span-2 xl:col-span-1 xl:col-start-2 row-start-2 xl:row-start-1 flex justify-center min-w-0">
          <div class="relative w-full xl:max-w-xl">
            <i data-lucide="search" class="absolute left-4 top-3 w-5 h-5 text-gray-400 dark:text-gray-500"></i>
            <input type="search" name="global_search_query" id="global-search-input" placeholder="Search pets, posts, events…" 
              autocomplete="new-password" spellcheck="false"
              onkeydown="if(event.key==='Enter'){event.preventDefault();runAdvancedSearch(this.value);}"
              oninput="debouncedGlobalTypeahead(this.value)" 
              onfocus="showGlobalSearchDropdown(this.value)"
              onclick="showGlobalSearchDropdown(this.value)"
              class="bg-gray-100 dark:bg-gray-800 dark:text-gray-200 dark:placeholder-gray-500 rounded-full py-2.5 pl-12 pr-5 text-sm w-full focus:outline-none focus:ring-2 focus:ring-brand-500">
            <div id="global-search-dropdown" class="hidden absolute top-full left-0 right-0 mt-2 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 rounded-xl shadow-xl overflow-hidden z-[100] max-h-[80vh] overflow-y-auto"></div>
          </div>
        </div>

        <!-- Action icons -->
        <div class="flex items-center justify-end gap-2 sm:gap-3">
          <button id="mobile-nav-toggle" onclick="toggleMobileNav()" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-nav-drawer"
            class="no-accent-hover lg:hidden bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border-2 border-brand-200 dark:border-brand-900/60 text-brand-500 dark:text-brand-400 transition-colors p-2 rounded-xl" title="Menu">
            <i data-lucide="menu" class="w-5 h-5"></i>
          </button>
          <div class="relative">
            <button id="messages-header-btn" onclick="openMessagesFromHeader()"
              class="no-accent-hover relative bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border-2 border-brand-200 dark:border-brand-900/60 text-brand-500 dark:text-brand-400 transition-colors p-2 rounded-xl" title="Messages">
              <i data-lucide="message-circle" class="w-5 h-5"></i>
              <span id="messages-header-badge" class="notif-pulse hidden absolute -top-2 -right-2 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-brand-500 text-white text-[10px] font-bold leading-[1.1rem] text-center ring-2 ring-white dark:ring-gray-900">0</span>
            </button>
          </div>
          <div class="relative">
            <button id="notif-bell-btn" onclick="toggleNotificationsPanel()"
              class="no-accent-hover relative bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 border-2 border-brand-200 dark:border-brand-900/60 text-brand-500 dark:text-brand-400 transition-colors p-2 rounded-xl" title="Notifications">
              <i data-lucide="bell" class="w-5 h-5"></i>
              <span id="notif-badge" class="hidden absolute -top-2 -right-2 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-brand-500 text-white text-[10px] font-bold leading-[1.1rem] text-center ring-2 ring-white dark:ring-gray-900">0</span>
            </button>
            <div id="notifications-panel" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] bg-white dark:bg-gray-900 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-800 overflow-hidden">
              <div class="p-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h4 class="font-bold text-sm text-gray-800 dark:text-gray-100">Notifications</h4>
                <button onclick="markAllNotificationsRead(this)" class="text-xs font-semibold text-brand-500">Mark all read</button>
              </div>
              <div id="notifications-list" class="max-h-96 overflow-y-auto"></div>
            </div>
          </div>
          <div class="relative">
            <button id="profile-menu-btn" onclick="toggleProfileMenu(event)"
              class="no-accent-hover w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden flex-shrink-0 border-2 border-brand-200 dark:border-brand-900/60"
              title="Account" aria-haspopup="true" aria-expanded="false">
              <span id="header-avatar-letter" class="font-bold text-brand-700 dark:text-brand-300">P</span>
              <img id="header-avatar-img" src="" alt="" class="w-full h-full object-cover hidden">
            </button>
            <div id="profile-menu" class="hidden absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-50">
              <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700">
                <p id="profile-menu-name" class="text-sm font-bold text-gray-900 dark:text-white truncate">Pet Name</p>
                <p id="profile-menu-email" class="text-xs text-gray-400 truncate"></p>
              </div>
              <button onclick="openProfileModal(); closeProfileMenu();" class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i data-lucide="pencil" class="w-4 h-4"></i> Edit Profile</button>
              <button onclick="switchView('view-pet-profile'); loadPetProfileView(); closeProfileMenu();" class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i data-lucide="user" class="w-4 h-4"></i> View Full Profile</button>
              <button onclick="toggleDarkMode(); closeProfileMenu();" class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i data-lucide="moon" class="w-4 h-4"></i> Toggle Dark Mode</button>
              <button onclick="copyProfileLink(); closeProfileMenu();" class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i data-lucide="link" class="w-4 h-4"></i> Copy Profile Link</button>
              <button onclick="switchSocialTab('settings'); closeProfileMenu();" class="w-full text-left px-4 py-3 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-3"><i data-lucide="settings" class="w-4 h-4"></i> Settings</button>
              <div class="border-t border-gray-100 dark:border-gray-700"></div>
              <button id="admin-entry-btn" onclick="openAdminEntry(); closeProfileMenu();" class="hidden w-full text-left px-4 py-3 text-sm hover:bg-brand-50 dark:hover:bg-brand-900/20 text-brand-600 dark:text-brand-400 flex items-center gap-3"><i data-lucide="shield-check" class="w-4 h-4"></i> Enter Admin Mode</button>
              <button onclick="closeProfileMenu(); logout();" class="w-full text-left px-4 py-3 text-sm hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 flex items-center gap-3"><i data-lucide="log-out" class="w-4 h-4"></i> Sign Out</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ======= Mobile Nav Drawer (hamburger, lg:hidden) ======= -->
  <div id="mobile-nav-backdrop" onclick="closeMobileNav()" class="hidden lg:hidden fixed inset-0 bg-black/40 z-[99]" aria-hidden="true"></div>
  <aside id="mobile-nav-drawer" class="lg:hidden fixed left-0 top-0 h-full w-[82%] max-w-xs bg-white dark:bg-gray-900 z-[100] flex flex-col shadow-2xl" style="transform: translateX(-100%);" aria-hidden="true">
    <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3">
      <div class="w-12 h-12 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden flex-shrink-0 border-2 border-brand-200 dark:border-brand-900/60">
        <span id="drawer-avatar-letter" class="font-bold text-brand-700 dark:text-brand-300">P</span>
        <img id="drawer-avatar-img" src="" alt="" class="w-full h-full object-cover hidden">
      </div>
      <div class="min-w-0 flex-1">
        <p id="drawer-profile-name" class="text-sm font-bold text-gray-900 dark:text-white truncate">Pet Name</p>
        <button onclick="switchView('view-pet-profile'); loadPetProfileView(); closeMobileNav();" class="text-xs font-semibold text-brand-600 dark:text-brand-400">View full profile</button>
      </div>
      <button onclick="closeMobileNav()" aria-label="Close menu" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 flex-shrink-0"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto p-2">
      <button data-social-tab="hub" onclick="switchSocialTab('hub'); closeMobileNav();" title="Kennel" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><svg class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><use href="#icon-kennel"></use></svg> Kennel</button>
      <button data-social-tab="feed" onclick="switchSocialTab('feed'); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="paw-print" class="w-9 h-9"></i> Paw-Bites</button>
      <button data-social-tab="friends" onclick="switchSocialTab('friends'); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="dog" class="w-9 h-9"></i> Pawpals</button>
      <button data-social-tab="groups" onclick="switchSocialTab('groups'); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><div class="relative w-9 h-9 flex-shrink-0"><i data-lucide="dog" class="w-6 h-6 absolute top-0 left-0"></i><i data-lucide="dog" class="w-7 h-7 absolute bottom-0 right-0"></i></div> Packs</button>
      <button data-social-tab="events" onclick="switchSocialTab('events'); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="bone" class="w-9 h-9"></i> PawFest</button>
      <button data-social-tab="galleries" onclick="switchSocialTab('galleries'); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="images" class="w-9 h-9"></i> Pawprints</button>
      <button data-social-tab="settings" onclick="switchSocialTab('settings'); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="settings" class="w-5 h-5"></i> Settings</button>

      <div class="my-2 border-t border-gray-100 dark:border-gray-800"></div>

      <button onclick="switchView('view-pack-tree'); loadPackTree(); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="git-fork" class="w-5 h-5"></i> Pack Tree</button>
      <button onclick="switchView('view-playdates'); switchPlaydateTab('deck'); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="heart" class="w-5 h-5"></i> Playdates</button>
      <button onclick="openVerificationModal(); closeMobileNav();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium"><i data-lucide="badge-check" class="w-5 h-5"></i> Get Verified</button>
      <button id="drawer-admin-entry-btn" onclick="openAdminEntry(); closeMobileNav();" class="hidden no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-brand-600 dark:text-brand-400"><i data-lucide="shield-check" class="w-5 h-5"></i> Enter Admin Mode</button>
    </div>
    <div class="p-2 border-t border-gray-100 dark:border-gray-800">
      <button onclick="closeMobileNav(); logout();" class="no-accent-hover drawer-nav-btn w-full flex items-center gap-3 px-4 py-3 rounded-xl font-medium text-red-600 dark:text-red-400"><i data-lucide="log-out" class="w-5 h-5"></i> Sign Out</button>
    </div>
  </aside>

  <!-- ======= Cover Photo Header (avatar, stats, actions, tab strip) ======= -->
  <div class="w-full bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 shadow-sm">
    <div class="relative w-full overflow-hidden bg-gradient-to-r from-brand-300 to-brand-400" style="aspect-ratio: 6 / 1; min-height: 120px;">
      <div id="hub-hero-cover-skeleton" class="absolute inset-0 bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
      <img id="hub-hero-cover-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden">
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6">
      <div class="flex flex-col xl:flex-row xl:items-end gap-4 pb-4">
        <!-- Avatar (overlapping cover) -->
        <div class="relative flex-shrink-0 self-start w-max -mt-12 sm:-mt-16 z-10">
          <div class="w-36 h-36 sm:w-44 sm:h-44 rounded-full p-1.5 bg-white dark:bg-gray-900 shadow-lg">
            <div class="w-full h-full bg-brand-100 rounded-full flex items-center justify-center text-brand-900 font-extrabold text-4xl relative overflow-hidden">
              <div id="hub-hero-avatar-skeleton" class="absolute inset-0 bg-gray-200 dark:bg-gray-800 animate-pulse rounded-full"></div>
              <span id="hub-hero-avatar-text" class="hidden">P</span>
              <img id="hub-hero-avatar-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden rounded-full">
            </div>
          </div>
        </div>

        <!-- Name + stats -->
        <div class="w-full xl:flex-1 pb-1 min-w-0">
          <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white leading-tight truncate flex items-center gap-2">
            <span id="hub-hero-pet-name">
              <div class="h-8 sm:h-9 w-48 rounded-lg bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
            </span>
            <span id="hub-verified-badge" class="hidden text-brand-500" title="Verified Pet Parent">
              <i data-lucide="badge-check" class="w-6 h-6"></i>
            </span>
          </h2>
          <p class="text-gray-500 dark:text-gray-400 text-sm mt-0.5" id="hub-hero-friends">
            <span id="hub-hero-friends-count" class="font-semibold text-gray-700 dark:text-gray-300"><span class="inline-block h-4 w-6 rounded bg-gray-200 dark:bg-gray-800 animate-pulse align-middle"></span></span> friends
          </p>
          <div class="flex flex-wrap gap-1.5 mt-3" id="hub-hero-tags"></div>
        </div>

        <!-- Action buttons -->
        <div class="w-full xl:w-auto flex items-center gap-2 pb-2 flex-wrap">
          <button onclick="openProfileModal()"
            class="bg-brand-100 hover:bg-brand-500 text-brand-700 hover:text-white dark:bg-gray-800 dark:hover:bg-brand-600 dark:text-brand-200 flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
            <i data-lucide="pencil" class="w-4 h-4"></i> <span>Edit</span>
          </button>
          <button onclick="switchView('view-pack-tree'); loadPackTree();"
            class="bg-brand-100 hover:bg-brand-500 text-brand-700 hover:text-white dark:bg-gray-800 dark:hover:bg-brand-600 dark:text-brand-200 flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
            <i data-lucide="git-fork" class="w-4 h-4"></i> <span>Pack Tree</span>
          </button>
          <button onclick="switchView('view-playdates'); switchPlaydateTab('deck');"
            class="bg-brand-100 hover:bg-brand-500 text-brand-700 hover:text-white dark:bg-gray-800 dark:hover:bg-brand-600 dark:text-brand-200 flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
            <i data-lucide="heart" class="w-4 h-4"></i> <span>Playdates</span>
          </button>
          <button onclick="openVerificationModal()"
            class="bg-brand-500 hover:bg-brand-600 text-white flex items-center gap-2 font-semibold text-sm px-4 py-2 rounded-lg shadow transition-colors">
            <i data-lucide="badge-check" class="w-4 h-4"></i> <span>Get Verified</span>
          </button>
        </div>
      </div>

      <!-- Tab strip. -->
      <nav id="social-tab-nav" class="relative border-t border-gray-200 dark:border-gray-700 flex items-center gap-2 overflow-x-auto no-scrollbar pt-1.5">
        <!-- Kennel tab. On hover motion.js widens this button and the five
             flex-1 siblings give up the width, so the whole scene plays INSIDE
             the button and nothing has to escape the nav's overflow.
             Two stacked graphics, cross-faded: .kennel-icon at rest, and
             .kennel-scene once open. The icon is inlined rather than
             <use href="#icon-kennel"> because a <use> instance lives in a
             shadow tree that neither document CSS nor querySelector can reach
             (see the note in auth_mascots.php), so its door could never be
             animated. The drawer's twin keeps its <use> — this is a
             pointer-hover affordance and the drawer is the touch surface. -->
        <button data-social-tab="hub" onclick="switchSocialTab('hub')" title="Kennel" class="no-accent-hover social-tab-strip-item kennel-tab flex-shrink-0 py-3.5 whitespace-nowrap flex items-center justify-center">
          <svg class="kennel-icon w-9 h-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M4 11 L12 4 L20 11" />
            <path d="M5.5 11 L5.5 20 L18.5 20 L18.5 11" />
            <path d="M9.5 20 L9.5 15.5 A2.5 2.5 0 0 1 14.5 15.5 L14.5 20" />
          </svg>
          <svg class="kennel-scene" viewBox="0 0 210 64" aria-hidden="true" focusable="false">
            <!-- The house sits on the LEFT, roughly where the resting icon is,
                 so opening the button reveals space to walk through instead of
                 relocating the building. The pet therefore enters from the
                 right.

                 SVG has no z-index, so this order IS the depth. The house is
                 built back-to-front first (wall, then the doorway void cut into
                 it, then the roof over the top), and only then the animal, so
                 it walks IN FRONT of the house until it reaches the doorway.
                 The door panel comes last of all so it can swing shut over the
                 animal that just went inside. -->
            <ellipse class="kennel-ground" cx="42" cy="58.5" rx="32" ry="3" />
            <g class="kennel-house">
              <path class="kennel-wall" d="M18 34 L18 58 L66 58 L66 34 Z" />
              <path class="kennel-wall-shade" d="M54 34 L66 34 L66 58 L54 58 Z" />
              <path class="kennel-hole" d="M33 58 L33 47 A9 9 0 0 1 51 47 L51 58 Z" />
              <path class="kennel-roof" d="M12 35 L42 19 L72 35 Z" />
              <path class="kennel-roof-lit" d="M12 35 L42 19 L42 35 Z" />
              <path class="kennel-shingle" d="M21 30 H63 M26 26 H58 M31 22 H53" />
              <path class="kennel-eave" d="M12 35 H72" />
            </g>
            <g class="kennel-actor">
              <g class="kennel-actor-slot" transform="translate(0 29) scale(0.15)"></g>
            </g>
            <path class="kennel-door-panel" d="M33 58 L33 47 A9 9 0 0 1 51 47 L51 58 Z" />
          </svg>
        </button>
        <button data-social-tab="feed" onclick="switchSocialTab('feed')" class="no-accent-hover social-tab-strip-item flex-1 min-w-0 px-5 py-3.5 text-base font-semibold whitespace-nowrap flex items-center justify-center gap-2"><i data-lucide="paw-print" class="w-8 h-8"></i>Paw-Bites</button>
        <button data-social-tab="friends" onclick="switchSocialTab('friends')" class="no-accent-hover social-tab-strip-item flex-1 min-w-0 px-5 py-3.5 text-base font-semibold whitespace-nowrap flex items-center justify-center gap-2"><i data-lucide="dog" class="w-8 h-8"></i>Pawpals</button>
        <button data-social-tab="groups" onclick="switchSocialTab('groups')" class="no-accent-hover social-tab-strip-item flex-1 min-w-0 px-5 py-3.5 text-base font-semibold whitespace-nowrap flex items-center justify-center gap-2"><div class="relative w-8 h-8 flex-shrink-0"><i data-lucide="dog" class="w-5 h-5 absolute top-0 left-0"></i><i data-lucide="dog" class="w-7 h-7 absolute bottom-0 right-0"></i></div>Packs</button>
        <button data-social-tab="events" onclick="switchSocialTab('events')" class="no-accent-hover social-tab-strip-item flex-1 min-w-0 px-5 py-3.5 text-base font-semibold whitespace-nowrap flex items-center justify-center gap-2"><i data-lucide="bone" class="w-8 h-8"></i>PawFest</button>
        <button data-social-tab="galleries" onclick="switchSocialTab('galleries')" class="no-accent-hover social-tab-strip-item flex-1 min-w-0 px-5 py-3.5 text-base font-semibold whitespace-nowrap flex items-center justify-center gap-2"><i data-lucide="images" class="w-8 h-8"></i>Pawprints</button>
        <span id="social-tab-underline" aria-hidden="true"></span>
      </nav>

    </div>
  </div>

  <div class="max-w-[1440px] mx-auto w-full px-4 py-6 flex flex-col lg:flex-row gap-6">
    <!-- ======= Left Sidebar ======= -->
    <aside id="social-left-sidebar" class="hidden lg:flex flex-col w-72 flex-shrink-0 self-start sticky top-6">
      <div id="social-left-nav" class="warm-glass rounded-[20px] p-3 flex flex-col gap-2">
        <button data-social-tab="feed" onclick="switchSocialTab('feed')" class="no-accent-hover social-tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
          <div class="p-1.5 rounded-lg"><i data-lucide="paw-print" class="w-7 h-7"></i></div> Paw-Bites
        </button>
        <button data-social-tab="friends" onclick="switchSocialTab('friends')" class="no-accent-hover social-tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
          <div class="p-1.5 rounded-lg"><i data-lucide="dog" class="w-7 h-7"></i></div> Pawpals
        </button>
        <button data-social-tab="groups" onclick="switchSocialTab('groups')" class="no-accent-hover social-tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
          <div class="p-1.5 rounded-lg"><div class="relative w-7 h-7 flex-shrink-0"><i data-lucide="dog" class="w-5 h-5 absolute top-0 left-0"></i><i data-lucide="dog" class="w-6 h-6 absolute bottom-0 right-0"></i></div></div> Packs
        </button>
        <button data-social-tab="events" onclick="switchSocialTab('events')" class="no-accent-hover social-tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
          <div class="p-1.5 rounded-lg"><i data-lucide="bone" class="w-7 h-7"></i></div> PawFest
        </button>
        <button data-social-tab="galleries" onclick="switchSocialTab('galleries')" class="no-accent-hover social-tab-btn w-full flex items-center gap-3 px-5 py-3.5 rounded-xl text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 font-medium transition-colors">
          <div class="p-1.5 rounded-lg"><i data-lucide="images" class="w-7 h-7"></i></div> Pawprints
        </button>
      </div>
    </aside>

    <!-- ======= Main Column ======= -->
    <div id="social-main-column" class="flex-1 min-w-0 mx-auto lg:mx-0 w-full space-y-4">

    <!-- ======= HUB TAB ======= -->
    <div id="social-tab-hub" class="social-tab-panel hidden">
      <div id="hub-content"><p class="text-center text-sm text-gray-400 py-8">Loading your community hub…</p></div>
    </div>

    <!-- ======= FEED TAB ======= -->
    <div id="social-tab-feed" class="social-tab-panel hidden space-y-4">
      <div class="warm-glass rounded-2xl p-4">
        <div class="flex items-start gap-3">
          <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden flex-shrink-0" id="composer-avatar">
            <span id="composer-avatar-text" class="font-bold text-brand-700 dark:text-brand-300">P</span>
            <img id="composer-avatar-img" src="" alt="" class="w-full h-full object-cover hidden">
          </div>
          <textarea id="composer-content" rows="1" placeholder="What's your pack up to today?" onfocus="expandComposer()"
            class="flex-1 resize-none border-0 focus:ring-0 text-sm dark:text-white placeholder-gray-400 bg-gray-50 dark:bg-gray-800/60 rounded-2xl px-4 py-2.5 transition-all"></textarea>
        </div>
        <div id="composer-extra" class="hidden mt-3">
          <!-- Audience picker. A native <select> on purpose: it gets mobile
               pickers, keyboard access and long-name truncation for free.
               Populated by loadComposerAudience() with the groups you're in. -->
          <div class="flex items-center gap-2 mb-3">
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 flex-shrink-0">Post to</span>
            <select id="composer-audience" class="text-xs font-bold rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 dark:text-white px-2 py-1.5 max-w-[70%] truncate">
              <option value="">My feed</option>
            </select>
          </div>
          <div id="composer-media-preview" class="hidden mb-3">
            <img id="composer-media-img" src="" alt="" class="max-h-48 rounded-xl object-cover">
            <button type="button" onclick="clearComposerMedia()" class="text-xs text-red-500 font-semibold mt-1">Remove photo</button>
          </div>
          <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-800">
            <label class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 cursor-pointer hover:text-brand-500">
              <i data-lucide="image" class="w-4 h-4"></i> Photo
              <input type="file" id="composer-media-input" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="handleComposerMediaUpload(this)">
            </label>
            <button id="composer-submit-btn" onclick="submitPost()"
              class="px-5 py-2 rounded-xl text-sm font-bold text-white bg-brand-500 hover:bg-brand-600 transition-colors">Post</button>
          </div>
        </div>
      </div>

      <div id="feed-list" class="space-y-4">
        <p class="text-center text-sm text-gray-400 py-8">Loading feed…</p>
      </div>
    </div>

    <!-- ======= POST DETAIL TAB ======= -->
    <div id="social-tab-post-detail" class="social-tab-panel hidden">
      <div id="post-detail-view" class="post-detail-shell"></div>
    </div>

    <!-- ======= FRIENDS TAB ======= -->
    <!-- No separate "Messages" tab (matches eSamaj) — chatting with a friend
         replaces this tab's content in place with #friend-chat-shell below,
         then closeFriendChat() restores #friends-normal-view. -->
    <div id="social-tab-friends" class="social-tab-panel hidden space-y-5">
      <div id="friends-normal-view" class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
          <div>
            <h3 class="text-xl font-extrabold text-gray-900 dark:text-white font-heading">Friends</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Pets in your pack, and new pals to meet.</p>
          </div>
        </div>

        <div class="flex gap-6 border-b border-gray-100 dark:border-gray-800">
          <button type="button" data-friends-subtab="mine" onclick="switchFriendsSubtab('mine')" class="subtab-btn pb-3 text-sm font-bold border-b-2 transition-colors">My Friends</button>
          <button type="button" data-friends-subtab="discover" onclick="switchFriendsSubtab('discover')" class="subtab-btn pb-3 text-sm font-bold border-b-2 transition-colors">Discover</button>
        </div>

        <div id="friends-subtab-mine" class="space-y-5">
          <div>
            <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Requests</h4>
            <div id="friend-requests-list" class="space-y-2"></div>
          </div>
          <div>
            <h4 class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Your friends</h4>
            <div id="friends-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
          </div>
        </div>

        <div id="friends-subtab-discover" class="hidden space-y-4">
          <div class="relative">
            <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-gray-400"></i>
            <input type="text" id="friend-search-input" placeholder="Search pets by name…" autocomplete="off" oninput="debounceFriendSearch(this.value)"
              class="w-full pl-9 pr-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
          </div>
          <div id="friend-search-results" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <p class="text-sm text-gray-400 col-span-full py-4">Search above to find pets to add.</p>
          </div>
        </div>
      </div>

      <div id="friend-chat-shell" class="hidden bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden flex-col h-[80vh] max-h-[85vh] sm:h-[650px] sm:max-h-[75vh]">
        <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3 flex-shrink-0">
          <button onclick="closeFriendChat()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-full flex-shrink-0"><i data-lucide="arrow-left" class="w-5 h-5"></i></button>
          <div class="relative w-10 h-10 flex-shrink-0">
            <div class="w-10 h-10 rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden relative">
              <span id="friend-chat-avatar-text" class="font-bold text-brand-700 dark:text-brand-300">P</span>
              <img id="friend-chat-avatar-img" src="" alt="" class="hidden absolute inset-0 w-full h-full object-cover">
            </div>
            <span id="friend-chat-presence-dot" class="profile-status-dot hidden absolute bottom-0 right-0 w-3 h-3 rounded-full border-2 border-white dark:border-gray-900"></span>
          </div>
          <div class="min-w-0 flex-1">
            <h3 id="friend-chat-name" class="font-bold text-gray-900 dark:text-white truncate">Friend</h3>
            <p class="text-xs text-gray-400" id="friend-chat-subtitle">Direct message</p>
          </div>
          <button onclick="startZoomCall({callType:'voice',targetType:'direct',friendId:currentFriendChatId}, this)" class="p-2 rounded-full text-brand-500 hover:bg-brand-100 dark:hover:bg-brand-900/40 flex-shrink-0" title="Voice call"><i data-lucide="phone" class="w-5 h-5"></i></button>
          <button onclick="startZoomCall({callType:'video',targetType:'direct',friendId:currentFriendChatId}, this)" class="p-2 rounded-full text-brand-500 hover:bg-brand-100 dark:hover:bg-brand-900/40 flex-shrink-0" title="Video call"><i data-lucide="video" class="w-5 h-5"></i></button>
          <div class="relative flex-shrink-0">
            <button onclick="toggleDropdownMenu(event, 'friend-chat-menu')" class="p-2 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800" title="More"><i data-lucide="more-vertical" class="w-5 h-5"></i></button>
            <div id="friend-chat-menu-wrap"></div>
          </div>
        </div>
        <div id="friend-chat-messages" class="flex-1 overflow-y-auto p-4 space-y-3 bg-gray-50 dark:bg-gray-950"></div>
        <div id="friend-chat-reply-strip" class="hidden flex-shrink-0"></div>
        <div class="p-3 border-t border-gray-100 dark:border-gray-800 flex items-end gap-2 flex-shrink-0">
          <textarea id="friend-chat-input" rows="1" placeholder="Type a message…" onkeydown="if(event.key==='Enter' &amp;&amp; !event.shiftKey){event.preventDefault();sendFriendChatMessage();}"
            class="flex-1 resize-none px-4 py-2.5 border border-gray-200 dark:border-gray-700 rounded-2xl text-sm bg-gray-100 dark:bg-gray-800 dark:text-white outline-none"></textarea>
          <button id="friend-chat-send-btn" onclick="sendFriendChatMessage()" class="w-10 h-10 rounded-full bg-brand-500 hover:bg-brand-600 text-white flex items-center justify-center flex-shrink-0"><i data-lucide="send" class="w-4 h-4"></i></button>
        </div>
      </div>
    </div>

    <!-- ======= GROUPS TAB ======= -->
    <div id="social-tab-groups" class="social-tab-panel hidden space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <h3 class="text-xl font-extrabold text-gray-900 dark:text-white font-heading">Groups</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">Find your pack's circles, or start your own.</p>
        </div>
        <button type="button" onclick="openCreateGroupModal()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold flex-shrink-0">
          <i data-lucide="plus" class="w-4 h-4"></i> Add group
        </button>
      </div>
      <div class="relative">
        <i data-lucide="search" class="absolute left-3 top-3 w-4 h-4 text-gray-400"></i>
        <input type="text" id="groups-search-input" placeholder="Search groups…" oninput="filterGroupsBySearch(this.value)"
          class="w-full pl-9 pr-3 py-2.5 border border-gray-200 dark:border-gray-700 rounded-xl text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </div>
      <div id="groups-list" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
    </div>

    <!-- ======= EVENTS TAB ======= -->
    <div id="social-tab-events" class="social-tab-panel hidden space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <h3 class="text-xl font-extrabold text-gray-900 dark:text-white font-heading">Events</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">Meetups, playdates, and pack gatherings.</p>
        </div>
        <button type="button" onclick="openCreateEventModal()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold flex-shrink-0">
          <i data-lucide="plus" class="w-4 h-4"></i> Add event
        </button>
      </div>

      <div class="flex gap-6 border-b border-gray-100 dark:border-gray-800">
        <button type="button" data-events-subtab="list" onclick="switchEventsSubtab('list')" class="subtab-btn pb-3 text-sm font-bold border-b-2 transition-colors">Events</button>
        <button type="button" data-events-subtab="analytics" onclick="switchEventsSubtab('analytics')" class="subtab-btn pb-3 text-sm font-bold border-b-2 transition-colors">Analytics</button>
      </div>

      <div id="events-subtab-list" class="space-y-5">
        <div id="events-list" class="space-y-3"></div>

        <div id="past-events-section" class="hidden">
          <button type="button" onclick="togglePastEvents()" class="flex items-center gap-2 text-sm font-bold text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
            <i data-lucide="chevron-down" id="past-events-chevron" class="w-4 h-4 transition-transform"></i>
            Past events (<span id="past-events-count">0</span>)
          </button>
          <div id="past-events-list" class="hidden mt-3 space-y-3"></div>
        </div>
      </div>

      <div id="events-subtab-analytics" class="hidden space-y-5">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
          <div class="warm-glass rounded-xl px-3 py-3 text-center">
            <p id="events-stat-total" class="text-lg font-extrabold text-gray-900 dark:text-white leading-none">0</p>
            <p class="text-[10px] uppercase tracking-wide text-gray-400 mt-1">Total events</p>
          </div>
          <div class="warm-glass rounded-xl px-3 py-3 text-center">
            <p id="events-stat-attendees" class="text-lg font-extrabold text-gray-900 dark:text-white leading-none">0</p>
            <p class="text-[10px] uppercase tracking-wide text-gray-400 mt-1">Total attendees</p>
          </div>
          <div class="warm-glass rounded-xl px-3 py-3 text-center">
            <p id="events-stat-my-rsvps" class="text-lg font-extrabold text-gray-900 dark:text-white leading-none">0</p>
            <p class="text-[10px] uppercase tracking-wide text-gray-400 mt-1">My RSVPs</p>
          </div>
          <div class="warm-glass rounded-xl px-3 py-3 text-center">
            <p id="events-stat-pet-types" class="text-lg font-extrabold text-gray-900 dark:text-white leading-none">0</p>
            <p class="text-[10px] uppercase tracking-wide text-gray-400 mt-1">Pet types</p>
          </div>
        </div>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <div class="warm-glass rounded-2xl p-4">
            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Attendance over time</p>
            <canvas id="events-chart-attendance" height="180"></canvas>
          </div>
          <div class="warm-glass rounded-2xl p-4">
            <p class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Events by pet type</p>
            <canvas id="events-chart-pet-types" height="180"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- ======= GALLERIES TAB ======= -->
    <!-- Fully JS-rendered (renderMainGalleriesTab() in galleries.js), matching eSamaj's
         actual Gallery Library component structure — header/stats/filters/results are
         all built client-side rather than templated here. -->
    <div id="social-tab-galleries" class="social-tab-panel hidden"></div>

    <!-- ======= RESCUE & SEVA TAB ======= -->
    <div id="social-tab-rescue" class="social-tab-panel hidden space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
          <h3 class="text-xl font-extrabold text-gray-900 dark:text-white font-heading">Rescue &amp; Seva</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400">Help opportunities and rescue needs from your pack.</p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button type="button" id="rescue-my-applications-btn" onclick="toggleRescueMyApplications()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 text-sm font-bold">
            <i data-lucide="clipboard-list" class="w-4 h-4"></i> My Applications
          </button>
          <button type="button" onclick="openCreateRescueModal()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold">
            <i data-lucide="plus" class="w-4 h-4"></i> Post opportunity
          </button>
        </div>
      </div>

      <div id="rescue-category-chips" class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
        <button data-rescue-category="" onclick="filterRescueByCategory('')" class="rescue-category-chip">All</button>
        <button data-rescue-category="seva" onclick="filterRescueByCategory('seva')" class="rescue-category-chip">Seva</button>
        <button data-rescue-category="teaching" onclick="filterRescueByCategory('teaching')" class="rescue-category-chip">Teaching</button>
        <button data-rescue-category="medical" onclick="filterRescueByCategory('medical')" class="rescue-category-chip">Medical</button>
        <button data-rescue-category="event" onclick="filterRescueByCategory('event')" class="rescue-category-chip">Event</button>
        <button data-rescue-category="fundraising" onclick="filterRescueByCategory('fundraising')" class="rescue-category-chip">Fundraising</button>
        <button data-rescue-category="environment" onclick="filterRescueByCategory('environment')" class="rescue-category-chip">Environment</button>
        <button data-rescue-category="elderly" onclick="filterRescueByCategory('elderly')" class="rescue-category-chip">Senior Pet Care</button>
        <button data-rescue-category="tech" onclick="filterRescueByCategory('tech')" class="rescue-category-chip">Tech</button>
      </div>

      <div id="rescue-list" class="space-y-3"></div>
    </div>

    <!-- ======= CARE GUIDES TAB ======= -->
    <div id="social-tab-guides" class="social-tab-panel hidden space-y-4">
      <div class="rounded-2xl overflow-hidden border border-brand-200/70 dark:border-brand-900/50 bg-gradient-to-br from-brand-50 via-orange-50 to-white dark:from-brand-900/25 dark:via-gray-900 dark:to-gray-900 p-6 relative">
        <div class="absolute right-6 top-1/2 -translate-y-1/2 text-brand-300/40 dark:text-brand-700/30 pointer-events-none hidden sm:block">
          <i data-lucide="book-open-text" class="w-20 h-20"></i>
        </div>
        <div class="relative sm:pr-28">
          <p class="text-[11px] font-black uppercase tracking-widest text-brand-600 dark:text-brand-400 mb-1">Pet Care Library</p>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white">Care &amp; Training Guides</h2>
          <p class="text-sm text-gray-600 dark:text-gray-300 mt-2 max-w-xl">Practical, vet-aware guidance on training, health, nutrition, behavior, first aid, and grooming — written for every kind of pet parent.</p>
        </div>
      </div>

      <div id="guides-category-chips" class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1"></div>

      <div id="guides-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <p class="text-center text-sm text-gray-400 py-8 col-span-full">Loading guides…</p>
      </div>
    </div>

    <!-- ======= SETTINGS TAB ======= -->
    <!-- Reached from the profile dropdown ("Settings"), not the main tab strip/sidebar —
         matches eSamaj's real entry point. Fully JS-rendered (renderSettingsTab() in
         settings.js), same pattern as the Galleries tab. -->
    <div id="social-tab-settings" class="social-tab-panel hidden"></div>

    <!-- ======= SEARCH RESULTS TAB ======= -->
    <?php include 'views/search_results.php'; ?>

    </div>

    <!-- ======= Right Widget Rail ======= -->
    <div id="social-right-sidebar" class="hidden xl:flex xl:flex-col w-96 flex-shrink-0 self-start gap-6 sticky top-24">
      <!-- Highlight slideshow (Care Guides + Rescue & Seva spotlight, since
           both were removed from the primary nav) -->
      <div id="hub-highlight-card" class="warm-glass warm-lift breathing-glow rounded-[20px] overflow-hidden hidden">
        <div id="hub-highlight-banner" class="h-24 relative flex items-center justify-center overflow-hidden bg-gradient-to-br from-brand-400 to-brand-600">
          <i id="hub-highlight-icon" data-lucide="sparkles" class="w-10 h-10 text-white drop-shadow relative z-10"></i>
          <button onclick="hubSpotlightPrev()" aria-label="Previous" class="absolute left-1.5 top-1/2 -translate-y-1/2 p-1 rounded-full bg-black/20 hover:bg-black/40 text-white transition-colors">
            <i data-lucide="chevron-left" class="w-4 h-4"></i>
          </button>
          <button onclick="hubSpotlightNext()" aria-label="Next" class="absolute right-1.5 top-1/2 -translate-y-1/2 p-1 rounded-full bg-black/20 hover:bg-black/40 text-white transition-colors">
            <i data-lucide="chevron-right" class="w-4 h-4"></i>
          </button>
        </div>
        <div class="p-4">
          <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400" id="hub-highlight-kicker">Spotlight</div>
          <div class="font-bold text-gray-900 dark:text-white text-base leading-tight mt-1" id="hub-highlight-title">Loading…</div>
          <div class="text-xs text-gray-600 dark:text-gray-400 mt-1.5 leading-relaxed" id="hub-highlight-text"></div>
          <div id="hub-highlight-dots" class="flex items-center justify-center gap-1.5 mt-3"></div>
          <button id="hub-highlight-cta" onclick="openHubSpotlightItem()"
            class="mt-2 w-full text-xs font-semibold py-2 rounded-lg transition-colors text-white bg-brand-500 hover:bg-brand-600">
            Read guide
          </button>
        </div>
      </div>

      <!-- Ads rail -->
      <div id="hub-ads-widget" class="warm-glass rounded-[20px] overflow-hidden"></div>

      <!-- Calendar widget -->
      <div id="hub-calendar-widget" class="warm-glass rounded-[20px] p-5"></div>
    </div>
  </div>
</div>
