<div id="share-post-modal"
    class="fixed inset-0 z-[9999] hidden items-center justify-center bg-gray-900/60 backdrop-blur-sm p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2"><i data-lucide="share-2"
            class="w-5 h-5 text-brand-500"></i> Share Post</h2>
        <button onclick="closeShareModal()"
          class="no-faith-hover text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-1.5 rounded-full"><i
            data-lucide="x" class="w-5 h-5"></i></button>
      </div>
      <div class="p-5">
        <button onclick="sharePostCopyLink()"
          style="--no-faith-hover-bg: var(--brand-500,#e04848); --no-faith-hover-color:#fff; --no-faith-hover-border:var(--brand-500,#e04848);"
          class="no-faith-hover w-full flex items-center justify-center gap-2 bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-300 font-bold py-2.5 rounded-xl text-sm mb-4 border border-brand-100 dark:border-brand-800"><i
            data-lucide="link" class="w-4 h-4"></i> Copy link</button>
        <div class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-2 flex items-center gap-2"><i
            data-lucide="message-circle" class="w-3.5 h-3.5"></i> Share to friends via chat</div>
        <div id="share-friends-list" class="max-h-64 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-800">
        </div>
      </div>
    </div>
  </div>