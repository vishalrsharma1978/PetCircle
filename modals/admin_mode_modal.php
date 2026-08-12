<div id="admin-mode-modal"
  class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
    <div class="bg-gray-50 dark:bg-gray-950 px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
        <i data-lucide="shield" class="w-5 h-5 mr-2 text-brand-500"></i>
        Enter Admin Mode
      </h2>
      <button onclick="closeAdminModeModal()"
        class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 p-1.5 rounded-full transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6">
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Re-enter your password to unlock admin actions for 15 minutes.</p>
      <div id="admin-mode-modal-error" class="hidden mb-4 bg-red-50 border-l-4 border-red-400 p-3 rounded text-sm text-red-700"></div>
      <input type="password" id="admin-mode-password" placeholder="Password"
        onkeydown="if(event.key==='Enter'){submitAdminModePassword();}"
        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm mb-4">
      <button id="admin-mode-submit-btn" onclick="submitAdminModePassword()"
        class="w-full px-4 py-2.5 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600 transition-colors">
        Unlock Admin Mode
      </button>
    </div>
  </div>
</div>
