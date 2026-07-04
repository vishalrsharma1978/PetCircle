<div id="link-gallery-choice-modal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeLinkGalleryChoiceModal()"></div>
    <div
      class="relative z-10 w-full max-w-md rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-2xl overflow-hidden">
      <div class="flex items-center justify-between gap-3 px-6 py-4 border-b border-gray-100 dark:border-gray-800">
        <div>
          <h3 class="text-lg font-bold text-gray-900 dark:text-white">Link gallery</h3>
          <p class="text-xs text-gray-500 dark:text-gray-400">Add a gallery to <span id="link-gallery-event-title">this
              event</span>.</p>
        </div>
        <button type="button" onclick="closeLinkGalleryChoiceModal()"
          class="no-faith-hover p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <input id="link-gallery-event-id" type="hidden">
        <button type="button" onclick="createNewGalleryForSelectedEvent()"
          style="--no-faith-hover-bg: rgb(31 41 55); --no-faith-hover-color: rgb(243 244 246); --no-faith-hover-border: rgb(249 115 22);"
          class="w-full no-faith-hover rounded-2xl border border-brand-200 dark:border-gray-700 bg-brand-50 dark:bg-gray-800 p-4 text-left text-brand-700 dark:text-gray-100 transition-colors hover:border-brand-300 dark:hover:border-brand-500">
          <span class="flex items-center gap-3 font-bold"><i data-lucide="plus"
              class="w-5 h-5 text-brand-500 dark:text-brand-300"></i> Create new gallery</span>
          <span class="mt-1 block text-sm text-brand-700/80 dark:text-gray-300">Start a new gallery already linked to
            this event.</span>
        </button>
        <div class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 p-4">
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300">Use existing gallery
            <select id="link-gallery-existing-select"
              class="mt-2 w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white rounded-xl focus:outline-none focus:border-brand-500"></select>
          </label>
          <p id="link-gallery-existing-empty" class="hidden mt-2 text-xs text-gray-500 dark:text-gray-400">No available
            galleries found. Create a new linked gallery instead.</p>
          <button id="link-gallery-existing-submit" type="button" onclick="linkExistingGalleryToEvent()"
            style="--no-faith-hover-bg: rgb(55 65 81); --no-faith-hover-color: rgb(255 255 255); --no-faith-hover-border: rgb(75 85 99);"
            class="no-faith-hover mt-3 w-full px-4 py-2 rounded-xl bg-gray-900 dark:bg-gray-700 text-white dark:text-gray-100 border border-gray-900 dark:border-gray-600 text-base font-bold shadow-md">
            Link selected gallery
          </button>
        </div>
      </div>
    </div>
  </div>