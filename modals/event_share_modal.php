<div id="event-share-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden flex flex-col">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
      <h3 id="event-share-title" class="font-bold text-gray-900 dark:text-white truncate">Share event</h3>
      <button onclick="closeEventShareModal()" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div class="p-5 space-y-4">
      <div id="event-share-qr" class="flex items-center justify-center bg-white p-3 rounded-xl border border-gray-100"></div>
      <div class="grid grid-cols-2 gap-2">
        <button type="button" onclick="copyEventLink()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 text-sm font-bold hover:bg-gray-50 dark:hover:bg-gray-800"><i data-lucide="link" class="w-4 h-4"></i> Copy link</button>
        <button type="button" onclick="shareEventNative()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-200 text-sm font-bold hover:bg-gray-50 dark:hover:bg-gray-800"><i data-lucide="share-2" class="w-4 h-4"></i> Share</button>
      </div>
      <button type="button" onclick="downloadEventIcs()" class="w-full inline-flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold"><i data-lucide="calendar-plus" class="w-4 h-4"></i> Add to calendar (.ics)</button>
    </div>
  </div>
</div>
