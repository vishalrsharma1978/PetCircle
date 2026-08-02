<div id="edit-post-modal"
    class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2"><i data-lucide="pencil"
            class="w-5 h-5 text-brand-500"></i> Edit Post</h2>
        <button onclick="closeEditPostModal()"
          class="no-faith-hover text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-full"><i
            data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div class="p-6 overflow-y-auto max-h-[60vh]">
        <textarea id="edit-post-textarea" rows="5" placeholder="Edit your post..."
          class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-200 focus:border-brand-300 text-sm resize-none"></textarea>

        <div class="mt-4 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Media</label>
            <input type="hidden" id="edit-post-media-url">
            <input type="hidden" id="edit-post-media-urls">
            <div id="edit-post-media-preview"></div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Hashtags</label>
            <input type="text" id="edit-post-hashtags" placeholder="Type a hashtag and press Enter"
              onkeydown="handlePostHashtagKeydown(event, 'edit-post-hashtags', 'edit-post-hashtags-chips')"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-200 focus:border-brand-300 text-sm">
            <div id="edit-post-hashtags-chips" class="mt-2 flex flex-wrap gap-2"></div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Linked event</label>
            <input type="text" id="edit-post-events" list="edit-post-event-options" placeholder="Type to search events"
              oninput="syncPostEventChoice('edit-post-events', 'edit-post-event-id')"
              onchange="syncPostEventChoice('edit-post-events', 'edit-post-event-id')"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 rounded-lg focus:ring-2 focus:ring-brand-200 focus:border-brand-300 text-sm">
            <input type="hidden" id="edit-post-event-id">
            <datalist id="edit-post-event-options"></datalist>
          </div>
          <div class="flex items-center mt-2">
            <input type="checkbox" id="edit-post-archived"
              class="mr-2 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
            <label for="edit-post-archived" class="text-sm text-gray-700 dark:text-gray-300">Archive this post</label>
          </div>
        </div>
      </div>
      <div
        class="bg-gray-50 dark:bg-gray-900/60 px-6 py-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3">
        <button onclick="closeEditPostModal()"
          class="no-faith-hover px-4 py-2 text-base font-semibold text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white">Cancel</button>
        <button id="edit-post-save-btn" onclick="saveEditedPost()"
          style="--no-faith-hover-bg: var(--brand-500,#e04848); --no-faith-hover-color:#fff; --no-faith-hover-border:var(--brand-500,#e04848);"
          class="no-faith-hover px-5 py-2 text-base font-bold text-white bg-brand-500 hover:bg-brand-600 rounded-lg shadow-sm flex items-center gap-2"><span>Save</span></button>
      </div>
    </div>
  </div>