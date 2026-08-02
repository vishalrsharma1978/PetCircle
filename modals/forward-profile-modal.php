<div id="forward-profile-modal"
    class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col relative animate-[fadeSlideIn_0.3s_ease]">
      <div class="p-4 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center">
        <h3 class="font-bold text-lg text-gray-800 dark:text-gray-200">Forward Profile</h3>
        <button onclick="closeForwardProfileModal()" class="text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-4 overflow-y-auto max-h-[60vh]">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Select friends to introduce <span
            id="forward-requester-name" class="font-bold"></span> to:</p>
        <div id="forward-friends-list"></div>
      </div>
      <div class="p-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-2">
        <button onclick="closeForwardProfileModal()"
          class="px-4 py-2 rounded-xl text-base font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Cancel</button>
        <button onclick="submitForwardProfile()"
          class="px-4 py-2 rounded-xl text-base font-bold bg-brand-500 text-white hover:bg-brand-600 shadow-lg shadow-brand-500/30 transition-all flex items-center gap-2"><i
            data-lucide="forward" class="w-4 h-4"></i> Forward</button>
      </div>
    </div>
  </div>