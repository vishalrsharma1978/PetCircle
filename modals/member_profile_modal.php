<div id="member-profile-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto">
    <div class="h-28 bg-gradient-to-r from-brand-300 to-brand-400 relative rounded-t-2xl overflow-hidden">
      <img id="mp-cover-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden">
      <div id="mp-cover-skeleton" class="absolute inset-0 bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
      <button onclick="closeMemberProfileModal()" class="absolute top-3 right-3 p-1.5 rounded-full bg-black/30 hover:bg-black/50 text-white z-10"><i data-lucide="x" class="w-4 h-4"></i></button>
    </div>
    <div class="px-6 pb-6">
      <div class="flex items-end gap-4 mb-3">
        <div class="w-20 h-20 bg-white dark:bg-gray-800 rounded-full p-1 shadow-md flex-shrink-0 relative -mt-10">
          <div class="w-full h-full bg-brand-100 dark:bg-brand-900/40 rounded-full flex items-center justify-center text-brand-900 dark:text-brand-300 font-bold text-2xl relative overflow-hidden" id="mp-avatar">
            <span id="mp-avatar-text">P</span>
            <img id="mp-avatar-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden rounded-full">
            <div id="mp-avatar-skeleton" class="absolute inset-0 bg-gray-200 dark:bg-gray-700 animate-pulse rounded-full"></div>
          </div>
          <span id="mp-presence-dot" class="profile-status-dot hidden absolute bottom-0.5 right-0.5 w-4 h-4 rounded-full border-2 border-white dark:border-gray-900"></span>
        </div>
        <div class="pb-1 min-w-0 flex-1">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white truncate flex items-center gap-1.5">
            <span id="mp-pet-name">Member</span>
            <span id="mp-verified-badge" class="hidden text-brand-500 flex-shrink-0" title="Verified Pet Parent"><i data-lucide="badge-check" class="w-4 h-4"></i></span>
          </h2>
          <div id="mp-name-skeleton" class="space-y-1.5">
            <div class="h-5 w-32 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
            <div class="h-3 w-24 rounded bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
          </div>
          <p class="text-sm text-gray-500 dark:text-gray-400 hidden" id="mp-type-breed">—</p>
        </div>
      </div>

      <div id="mp-action-skeleton" class="h-9 w-full rounded-lg bg-gray-100 dark:bg-gray-800 animate-pulse mb-4"></div>
      <div id="mp-action-wrap" class="mb-4 hidden"></div>

      <button type="button" onclick="closeMemberProfileModal(); openMemberProfilePage(currentMemberProfileId);"
        class="w-full px-4 py-2 rounded-lg text-sm font-semibold text-brand-600 dark:text-brand-400 bg-brand-50 dark:bg-brand-900/20 hover:bg-brand-100 dark:hover:bg-brand-900/40 transition-colors mb-4 flex items-center justify-center gap-2">
        <i data-lucide="user" class="w-4 h-4"></i> View full profile
      </button>

      <div id="mp-tags-skeleton" class="flex gap-1.5 mb-4">
        <div class="h-6 w-16 rounded-full bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
        <div class="h-6 w-20 rounded-full bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
      </div>
      <div id="mp-tags-wrap" class="hidden flex-wrap gap-1.5 mb-4"></div>

      <div class="grid grid-cols-2 gap-3 mb-4">
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
          <p class="text-xs text-gray-500 dark:text-gray-400">City</p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate hidden" id="mp-city">—</p>
          <div id="mp-city-skeleton" class="h-4 w-16 mx-auto mt-1 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
          <p class="text-xs text-gray-500 dark:text-gray-400">Friends</p>
          <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 hidden" id="mp-friend-count">—</p>
          <div id="mp-friend-count-skeleton" class="h-4 w-8 mx-auto mt-1 rounded bg-gray-200 dark:bg-gray-800 animate-pulse"></div>
        </div>
      </div>

      <div id="mp-bio-wrap" class="mb-1">
        <h3 class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">About</h3>
        <p class="text-sm text-gray-700 dark:text-gray-300 hidden" id="mp-bio">—</p>
        <div id="mp-bio-skeleton" class="space-y-1.5">
          <div class="h-3 w-full rounded bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
          <div class="h-3 w-4/5 rounded bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
        </div>
      </div>

      <div id="mp-limited-notice" class="hidden text-sm text-gray-500 dark:text-gray-400 text-center py-6"></div>
    </div>
  </div>
</div>
