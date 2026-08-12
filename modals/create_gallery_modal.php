<div id="create-gallery-modal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4">
  <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeCreateGalleryModal()"></div>
  <div class="relative z-10 w-full max-w-lg max-h-[90vh] rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl overflow-hidden flex flex-col">
    <div class="shrink-0 flex items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-800">
      <div>
        <h3 id="gallery-modal-heading" class="text-lg font-bold text-gray-900 dark:text-white">Create gallery</h3>
        <p id="gallery-modal-subtitle" class="text-xs text-gray-500 dark:text-gray-400">Optional event link included.</p>
      </div>
      <button type="button" onclick="closeCreateGalleryModal()" class="no-accent-hover p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6 space-y-4 overflow-y-auto">
      <input id="gallery-modal-id" type="hidden">
      <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Gallery title
        <input id="gallery-modal-title" placeholder="e.g. Beach day with Rex"
          class="mt-1 w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-brand-500">
      </label>
      <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Link to event (optional)
        <select id="gallery-modal-event"
          class="mt-1 w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-brand-500">
          <option value="">Independent gallery</option>
        </select>
      </label>
      <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Visibility
        <select id="gallery-modal-visibility"
          class="mt-1 w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-brand-500">
          <option value="private">Private</option>
          <option value="pet_type">My pet type only</option>
          <option value="breed">My breed only</option>
          <option value="public">Public</option>
        </select>
      </label>
      <label class="block text-sm font-bold text-gray-700 dark:text-gray-300">Description
        <textarea id="gallery-modal-desc" rows="2" placeholder="Short context for this collection"
          class="mt-1 w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-brand-500"></textarea>
      </label>
      <div>
        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Media upload</label>
        <div class="flex items-center gap-3">
          <label for="gallery-modal-upload"
            class="cursor-pointer inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 font-bold shadow-sm transition-colors">
            <i data-lucide="upload-cloud" class="w-5 h-5"></i>
            <span>Select media</span>
          </label>
          <span id="gallery-modal-upload-status" class="text-xs text-gray-500 dark:text-gray-400">No files chosen</span>
          <input type="file" id="gallery-modal-upload" accept="image/*,video/*" multiple class="hidden" onchange="handleGalleryModalUpload(event)">
        </div>
        <div class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
          <input id="gallery-modal-url-input" type="url" inputmode="url" placeholder="https://example.com/photo.jpg"
            class="w-full px-4 py-2.5 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-brand-500">
          <button type="button" onclick="addGalleryMediaUrl()"
            class="no-accent-hover px-4 py-2.5 rounded-xl border border-gray-200 dark:border-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-bold">Add URL</button>
        </div>
        <!-- Newly added media show as thumbnails only; the underlying storage URLs are never displayed. -->
        <textarea id="gallery-modal-media" class="hidden" aria-hidden="true" tabindex="-1"></textarea>
        <div id="gallery-modal-staged" class="hidden mt-3"></div>
      </div>
      <div id="gallery-modal-items" class="hidden max-h-72 overflow-y-auto rounded-2xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-3"></div>
    </div>
    <div class="shrink-0 px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex justify-end gap-3">
      <button type="button" onclick="closeCreateGalleryModal()" class="no-accent-hover px-4 py-2 rounded-xl text-sm font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Cancel</button>
      <button id="gallery-modal-submit" type="button" onclick="createGalleryFromModal()" class="px-4 py-2 rounded-xl bg-brand-500 text-white text-sm font-bold">Create gallery</button>
    </div>
  </div>
</div>
