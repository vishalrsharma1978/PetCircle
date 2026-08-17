<div id="set-handle-modal"
  class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
    <div class="bg-gray-50 dark:bg-gray-950 px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
        <i data-lucide="at-sign" class="w-5 h-5 mr-2 text-brand-500"></i>
        Pick a handle
      </h2>
      <button onclick="closeSetHandleModal()"
        class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 p-1.5 rounded-full transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6">
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Your handle shows up next to your pet's name on posts. Pick one now, or skip and set it later from Settings &gt; Security.</p>
      <div id="set-handle-modal-error" class="hidden mb-4 bg-red-50 border-l-4 border-red-400 p-3 rounded text-sm text-red-700"></div>
      <form onsubmit="event.preventDefault(); submitSetHandleModal();">
        <div class="flex items-center gap-2 mb-4">
          <span class="text-gray-400 font-bold">@</span>
          <input type="text" id="set-handle-input" placeholder="pawsome_pup" autocomplete="off"
            class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
        </div>
        <div class="flex gap-2">
          <button type="button" onclick="closeSetHandleModal()"
            class="flex-1 px-4 py-2.5 rounded-lg text-sm font-bold text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
            Skip for now
          </button>
          <button type="submit" id="set-handle-modal-submit-btn"
            class="flex-1 px-4 py-2.5 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600 transition-colors">
            Save
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
