<div id="announcement-modal"
    class="fixed inset-0 z-[60] hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-3xl w-full max-w-lg overflow-hidden shadow-2xl transform transition-all">
      <div
        class="p-6 border-b border-gray-100 dark:border-gray-800 flex justify-between items-center bg-gray-50 dark:bg-gray-800/50">
        <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
          <i data-lucide="megaphone" class="w-5 h-5 text-brand-500"></i> Post
          Announcement
        </h3>
        <button onclick="closeAnnouncementModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Title</label>
          <input type="text" placeholder="E.g., Community Gathering this Sunday"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 text-gray-800 dark:text-gray-200 focus:outline-none focus:border-brand-500" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Description</label>
          <textarea rows="4" placeholder="What's happening? Provide details..."
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl px-4 py-2 text-gray-800 dark:text-gray-200 focus:outline-none focus:border-brand-500 resize-none"></textarea>
        </div>
      </div>
      <div
        class="p-6 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3 bg-gray-50 dark:bg-gray-800/50">
        <button onclick="closeAnnouncementModal()"
          class="px-5 py-2 text-gray-600 dark:text-gray-300 font-semibold rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
          Cancel
        </button>
        <button onclick="submitAnnouncement()"
          class="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold rounded-xl transition-colors shadow-lg shadow-brand-500/30">
          Post
        </button>
      </div>
    </div>
  </div>