<div id="rescue-apply-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <h3 id="rescue-apply-title" class="font-bold text-gray-900 dark:text-white truncate">Apply</h3>
      <button onclick="closeRescueApplyModal()" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div class="p-6 space-y-3">
      <div id="rescue-apply-error" class="hidden bg-red-50 border-l-4 border-red-400 p-3 rounded text-sm text-red-700"></div>
      <div>
        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Your name</label>
        <input type="text" id="rescue-apply-name" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Phone <span class="font-normal text-gray-400">(optional)</span></label>
        <input type="tel" id="rescue-apply-phone" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
      </div>
      <button id="rescue-apply-submit-btn" onclick="submitRescueApplication()" class="w-full py-2.5 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Submit application</button>
    </div>
  </div>
</div>
