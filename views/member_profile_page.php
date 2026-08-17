<div id="view-member-profile" class="view-section hidden min-h-screen bg-gray-50 dark:bg-gray-950 flex-col w-full">
  <div class="max-w-3xl mx-auto w-full p-6 sm:p-8">
    <div class="flex items-center justify-between mb-6">
      <button onclick="switchView('view-social-feed'); switchSocialTab('feed');"
        class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
      </button>
      <div id="vmp-action-skeleton" class="h-9 w-32 rounded-lg bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
      <div id="vmp-action-wrap" class="hidden"></div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
      <div class="h-36 bg-gradient-to-r from-brand-300 to-brand-400 relative" id="vmp-cover">
        <img id="vmp-cover-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden" />
        <div id="vmp-cover-skeleton" class="absolute inset-0 bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
      </div>
      <div class="px-6 pb-6">
        <div class="-mt-10 flex items-end gap-4 mb-4">
          <div class="w-20 h-20 bg-white dark:bg-gray-800 rounded-full p-1 shadow-md flex-shrink-0 relative">
            <div class="w-full h-full bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center text-brand-900 dark:text-brand-300 font-bold text-2xl relative overflow-hidden" id="vmp-avatar">
              <span id="vmp-avatar-text">P</span>
              <img id="vmp-avatar-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden rounded-full" />
              <div id="vmp-avatar-skeleton" class="absolute inset-0 bg-gray-200 dark:bg-gray-700 animate-pulse rounded-full"></div>
            </div>
            <span id="vmp-presence-dot" class="profile-status-dot hidden absolute bottom-0.5 right-0.5 w-4 h-4 rounded-full border-2 border-white dark:border-gray-900"></span>
          </div>
          <div class="pb-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white truncate flex items-center gap-1.5">
              <span id="vmp-pet-name"></span>
              <span id="vmp-handle" class="text-sm text-gray-500 dark:text-gray-400 font-normal"></span>
              <span id="vmp-verified-badge" class="hidden text-brand-500 flex-shrink-0" title="Verified Pet Parent">
                <i data-lucide="badge-check" class="w-5 h-5"></i>
              </span>
            </h2>
            <div id="vmp-name-skeleton" class="space-y-1.5">
              <div class="h-6 sm:h-7 w-40 rounded-lg bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
              <div class="h-3 w-24 rounded bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400 hidden" id="vmp-type-breed"></p>
          </div>
        </div>

        <div id="vmp-tags-skeleton" class="flex gap-1.5 mb-4">
          <div class="h-6 w-16 rounded-full bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
          <div class="h-6 w-20 rounded-full bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
        </div>
        <div id="vmp-tags-wrap" class="hidden flex-wrap gap-1.5 mb-4"></div>

        <div class="grid grid-cols-2 gap-3 mb-6">
          <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">City</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate hidden" id="vmp-city">—</p>
            <div id="vmp-city-skeleton" class="h-4 w-16 mx-auto mt-1 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Friends</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 hidden" id="vmp-friend-count">—</p>
            <div id="vmp-friend-count-skeleton" class="h-4 w-8 mx-auto mt-1 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
          </div>
        </div>

        <div id="vmp-bio-wrap" class="mb-1">
          <h3 class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">About</h3>
          <p class="text-sm text-gray-700 dark:text-gray-300 hidden" id="vmp-bio">—</p>
          <div id="vmp-bio-skeleton" class="space-y-1.5">
            <div class="h-3 w-full rounded bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
            <div class="h-3 w-4/5 rounded bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
          </div>
        </div>

        <div id="vmp-limited-notice" class="hidden text-sm text-gray-500 dark:text-gray-400 text-center py-6"></div>
      </div>
    </div>

    <div id="vmp-posts-wrap" class="mt-6">
      <h3 class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-2">Posts</h3>
      <div id="vmp-posts-list" class="space-y-3"></div>
      <div id="vmp-posts-load-more-wrap" class="hidden mt-4 text-center">
        <button id="vmp-posts-load-more-btn" type="button" onclick="loadMoreMemberProfilePagePosts()"
          class="px-4 py-2 rounded-lg text-sm font-semibold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
          Load more
        </button>
      </div>
    </div>
  </div>
</div>
