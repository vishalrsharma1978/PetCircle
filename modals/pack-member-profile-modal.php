<div id="pack-member-profile-modal"
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[60] hidden flex-col items-center justify-center p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col relative">
      <div class="h-24 bg-gradient-to-r from-brand-400 to-brand-600"></div>
      <button onclick="closePackMemberProfile()"
        class="absolute top-4 right-4 text-white hover:text-gray-200 bg-black/20 hover:bg-black/40 p-1.5 rounded-full transition-colors z-10">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="px-6 pb-6 relative -mt-12">
        <div
          class="w-24 h-24 rounded-full border-4 border-white dark:border-gray-900 bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-3xl font-bold text-brand-600 dark:text-brand-300 shadow-md mx-auto mb-4 overflow-hidden">
          <span id="view-member-avatar-text"></span>
        </div>

        <div class="text-center mb-6">
          <h2 class="text-xl font-bold text-gray-900 dark:text-white" id="view-member-name">Name</h2>
          <p class="text-sm text-brand-600 dark:text-brand-400 font-medium mt-1" id="view-member-relation">Relation</p>
        </div>

        <div class="bg-gray-50 dark:bg-gray-800/50 rounded-xl p-4 mb-6 space-y-3 text-left"
          id="view-member-details-container">
          <!-- Details dynamically injected here -->
        </div>

        <div class="flex gap-3">
          <button onclick="toggleFriendStatus()"
            class="flex-1 py-2.5 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl transition-colors flex justify-center items-center gap-2 text-sm shadow-sm">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Add Friend
          </button>
          <button onclick="closePackMemberProfile()"
            class="flex-1 py-2.5 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-semibold rounded-xl transition-colors text-sm shadow-sm">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>