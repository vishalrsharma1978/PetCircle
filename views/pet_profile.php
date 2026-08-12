<div id="view-pet-profile" class="view-section hidden min-h-screen bg-gray-50 dark:bg-gray-950 flex-col w-full">
  <div class="max-w-3xl mx-auto w-full p-6 sm:p-8">
    <div class="flex items-center justify-between mb-6">
      <button onclick="switchView('view-social-feed'); switchSocialTab('feed');"
        class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
      </button>
      <div class="flex items-center gap-2">
        <button onclick="switchView('view-pack-tree'); loadPackTree();"
          class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
          <i data-lucide="git-fork" class="w-4 h-4"></i> Pack Tree
        </button>
        <button onclick="switchView('view-playdates'); switchPlaydateTab('deck');"
          class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
          <i data-lucide="heart-handshake" class="w-4 h-4"></i> Playdates
        </button>
        <button onclick="openVerificationModal()"
          class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
          <i data-lucide="badge-check" class="w-4 h-4"></i> Get Verified
        </button>
        <button onclick="openProfileModal()"
          class="px-3 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg shadow-sm text-sm font-semibold transition-colors flex items-center gap-2">
          <i data-lucide="pencil" class="w-4 h-4"></i> Edit
        </button>
      </div>
    </div>

    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
      <div class="h-36 bg-gradient-to-r from-brand-300 to-brand-400 relative" id="pp-cover">
        <img id="pp-cover-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden" />
      </div>
      <div class="px-6 pb-6">
        <div class="-mt-10 flex items-end gap-4 mb-4">
          <div class="w-20 h-20 bg-white dark:bg-gray-800 rounded-full p-1 shadow-md flex-shrink-0">
            <div class="w-full h-full bg-brand-100 rounded-full flex items-center justify-center text-brand-900 font-bold text-2xl relative overflow-hidden" id="pp-avatar">
              <span id="pp-avatar-text">P</span>
              <img id="pp-avatar-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden rounded-full" />
            </div>
          </div>
          <div class="pb-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white truncate flex items-center gap-1.5">
              <span id="pp-pet-name">Pet Name</span>
              <span id="pp-verified-badge" class="hidden text-brand-500" title="Verified Pet Parent">
                <i data-lucide="badge-check" class="w-5 h-5"></i>
              </span>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400" id="pp-type-breed">Pet Type · Breed</p>
          </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
          <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Parent</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate" id="pp-parent-name">—</p>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">City</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate" id="pp-city">—</p>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Gender</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate" id="pp-gender">—</p>
          </div>
          <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-3 text-center">
            <p class="text-xs text-gray-500 dark:text-gray-400">Born</p>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate" id="pp-dob">—</p>
          </div>
        </div>

        <div class="mb-4">
          <h3 class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">About</h3>
          <p class="text-sm text-gray-700 dark:text-gray-300" id="pp-bio">No bio yet.</p>
        </div>

        <div id="pp-microchip-wrap" class="hidden">
          <h3 class="text-xs font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Microchip</h3>
          <p class="text-sm text-gray-700 dark:text-gray-300 font-mono" id="pp-microchip">—</p>
        </div>
      </div>
    </div>
  </div>
</div>
