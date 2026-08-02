<div id="user-profile-modal"
    class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
    <div
      class="user-profile-modal-panel bg-white dark:bg-gray-900 rounded-3xl shadow-2xl w-full max-w-lg flex flex-col relative animate-[fadeSlideIn_0.3s_ease]">
      <!-- Header / Cover -->
      <div id="upm-header-cover" class="h-32 relative rounded-t-3xl"
        style="background: linear-gradient(to right, #f97316, #fb923c)">
        <div class="absolute top-4 right-4 flex items-center gap-2">
          <div id="upm-options-container" class="relative group hidden">
            <button onclick="toggleUserProfileMenu(event)"
              class="text-white hover:bg-white/20 p-1.5 rounded-full transition-colors">
              <i data-lucide="more-vertical" class="w-5 h-5"></i>
            </button>
            <div id="upm-options-menu"
              class="hidden absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 py-2 z-50">
              <button onclick="handlePinFriendClick(event)"
                class="w-full text-left px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                <i data-lucide="pin" class="w-4 h-4"></i> <span id="upm-pin-text">Pin Friend</span>
              </button>
              <button onclick="handleRemoveFriendClick(event)"
                class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                <i data-lucide="user-minus" class="w-4 h-4"></i> Remove Friend
              </button>
            </div>
          </div>
          <button onclick="closeUserProfile()"
            class="text-white hover:bg-white/20 p-1.5 rounded-full transition-colors">
            <i data-lucide="x" class="w-5 h-5"></i>
          </button>
        </div>
        <div
          class="absolute left-1/2 bottom-0 -translate-x-1/2 translate-y-1/2 w-24 h-24 bg-white dark:bg-gray-800 rounded-full p-1 shadow-xl ring-4 ring-white dark:ring-gray-900">
          <img id="upm-avatar-img" src="" alt="" class="w-full h-full rounded-full object-cover hidden">
          <div id="upm-avatar"
            class="w-full h-full rounded-full bg-brand-100 dark:bg-brand-900/50 flex items-center justify-center text-brand-600 dark:text-brand-300 text-3xl font-bold uppercase">
            U
          </div>
        </div>
      </div>

      <!-- Profile Info (fixed header — does not scroll) -->
      <div class="user-profile-modal-body px-6 pt-16 relative shrink-0">
        <div class="flex flex-col items-center">
          <h2 id="upm-name" class="text-2xl font-bold text-gray-800 dark:text-gray-100 mt-3 font-serif">User Name</h2>
          <p id="upm-role" class="text-sm text-gray-500 dark:text-gray-400">Pet Breed Member</p>
          <div id="upm-profile-tags" class="mt-3 flex flex-wrap justify-center gap-1.5"></div>

          <div class="mt-4 flex gap-3 w-full">
            <button id="upm-friend-status-btn"
              class="no-faith-hover flex-1 bg-emerald-600 text-white py-2 rounded-xl text-base font-bold border border-emerald-600 shadow-lg shadow-emerald-500/20 transition-all flex items-center justify-center gap-2">
              <i data-lucide="check-circle-2" class="w-4 h-4"></i> Friends
            </button>
            <button
              onclick="introduceMeTo(document.getElementById('upm-name').textContent, document.getElementById('user-profile-modal').dataset.userId, this)"
              style="--no-faith-hover-bg:#2563eb; --no-faith-hover-color:#ffffff; --no-faith-hover-border:#2563eb;"
              class="no-faith-hover flex-1 bg-blue-600 text-white hover:bg-blue-700 py-2 rounded-xl text-base font-bold border border-blue-600 shadow-lg shadow-blue-500/30 transition-all flex items-center justify-center gap-2">
              <i data-lucide="handshake" class="w-4 h-4"></i> Introduce Me
            </button>
          </div>
        </div>
      </div>

      <!-- Interactive Pack Tree (scrolls independently of the header) -->
      <div
        class="user-profile-modal-body px-6 pb-6 pt-6 mt-4 flex-1 overflow-y-auto modal-scroll max-h-[55vh] rounded-b-3xl border-t border-gray-100 dark:border-gray-800">
        <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 mb-4 flex items-center gap-2">
          <i data-lucide="network" class="w-4 h-4 text-brand-500"></i> Pack Tree (Interactive)
        </h3>
        <div id="upm-pack-tree-container"
          class="bg-gray-50 dark:bg-gray-800/50 rounded-2xl p-4 border border-gray-100 dark:border-gray-700 min-h-[13rem]">
          <div class="h-40 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400">
            Loading pack tree...
          </div>
        </div>
      </div>
    </div>
  </div>