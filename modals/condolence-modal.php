<div id="condolence-modal"
    class="fixed inset-0 z-[60] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-3xl w-full max-w-md overflow-hidden shadow-2xl transform transition-all">
      <div
        class="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2"
          style="font-family: &quot;DM Serif Display&quot;">
          Send Condolences
        </h3>
        <button onclick="closeCondolenceModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
      </div>
      <div class="p-6">
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
          Send a message to the pack of
          <span id="condolence-name" class="font-bold text-gray-900 dark:text-white"></span>.
        </p>
        <textarea id="condolence-text" rows="4" placeholder="Write your message here..."
          class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-3 text-gray-800 dark:text-gray-200 focus:outline-none focus:border-gray-500 resize-none text-sm"></textarea>
      </div>
      <div
        class="p-6 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50">
        <button onclick="closeCondolenceModal()"
          class="px-5 py-2 text-gray-600 dark:text-gray-300 font-semibold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors text-sm">
          Cancel
        </button>
        <button onclick="submitCondolence()"
          class="px-5 py-2 bg-gray-900 text-white font-bold rounded-xl hover:bg-black transition-colors text-sm shadow-lg shadow-gray-900/30">
          Send Message
        </button>
      </div>
    </div>
  </div>