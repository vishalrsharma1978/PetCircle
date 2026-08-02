<div id="remove-friend-confirm-modal"
    class="fixed inset-0 z-[10000] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-3xl shadow-2xl w-full max-w-sm flex flex-col p-6 animate-[fadeSlideIn_0.3s_ease] text-center relative">
      <div
        class="w-16 h-16 bg-red-100 dark:bg-red-900/30 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4">
        <i data-lucide="alert-triangle" class="w-8 h-8"></i>
      </div>
      <h3 class="text-xl font-bold text-gray-800 dark:text-gray-100 mb-2 font-serif">Remove Friend</h3>
      <p class="text-gray-500 dark:text-gray-400 text-sm mb-6">Are you sure you want to remove <strong id="rfc-name"
          class="text-gray-800 dark:text-gray-200"></strong> from your friends? You will no longer see their private
        updates.</p>
      <div class="flex gap-3 w-full">
        <button onclick="closeRemoveFriendConfirm()"
          class="flex-1 py-2.5 rounded-xl text-base font-bold border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">Cancel</button>
        <button id="rfc-confirm-btn"
          class="flex-1 py-2.5 rounded-xl text-base font-bold bg-red-600 text-white hover:bg-red-700 transition-colors shadow-lg shadow-red-500/20">Remove</button>
      </div>
    </div>
  </div>