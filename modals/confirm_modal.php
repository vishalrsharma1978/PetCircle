<!-- Reusable confirm dialog — replaces native confirm() for delete/archive
     actions across the app. confirmAction() in core.js returns a Promise
     that resolves true/false; callers do
     `if (!(await confirmAction({...}))) return;` in place of confirm(). -->
<div id="confirm-modal-backdrop" class="hidden fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[200] items-center justify-center p-4" onclick="if (event.target === this) resolveConfirmModal(false);">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
    <div class="p-6">
      <div class="flex items-start gap-3 mb-4">
        <div id="confirm-modal-icon-wrap" class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400">
          <i id="confirm-modal-icon" data-lucide="trash-2" class="w-5 h-5"></i>
        </div>
        <div class="min-w-0 pt-1.5">
          <h3 id="confirm-modal-title" class="font-bold text-gray-900 dark:text-white text-base leading-tight"></h3>
        </div>
      </div>
      <p id="confirm-modal-message" class="text-sm text-gray-600 dark:text-gray-300 mb-5"></p>
      <div class="flex items-center justify-end gap-2">
        <button id="confirm-modal-cancel-btn" onclick="resolveConfirmModal(false)" class="no-accent-hover px-4 py-2 rounded-lg text-sm font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">Cancel</button>
        <button id="confirm-modal-confirm-btn" onclick="resolveConfirmModal(true)" class="no-accent-hover px-4 py-2 rounded-lg text-sm font-semibold text-white bg-red-500 hover:bg-red-600 transition-colors">Delete</button>
      </div>
    </div>
  </div>
</div>
