<div id="create-group-modal"
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[150] hidden flex-col items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200">
          Create New Group
        </h2>
        <button onclick="closeCreateGroupModal()"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Group Name</label>
          <input type="text" id="group-modal-name"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500"
            placeholder="e.g. Sunday Prayer Group" />
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Description
            (optional)</label>
          <textarea id="group-modal-desc"
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500 resize-none"
            rows="3" placeholder="What is this group about?"></textarea>
        </div>
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Invite friends
              (optional)</label>
            <span id="group-invite-count" class="text-xs font-bold text-brand-500"></span>
          </div>
          <input type="text" id="group-invite-search" oninput="renderGroupInviteFriends()"
            class="w-full px-4 py-2 mb-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-sm text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500"
            placeholder="Search friends to add…" />
          <div id="group-invite-list"
            class="max-h-44 overflow-y-auto rounded-lg border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-800">
          </div>
        </div>
      </div>
      <div
        class="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3">
        <button onclick="closeCreateGroupModal()"
          class="px-4 py-2 text-base font-bold text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">Cancel</button>
        <button onclick="saveGroupFromModal()"
          class="bg-brand-500 hover:bg-brand-600 text-white px-5 py-2 rounded-lg text-base font-bold shadow-sm transition-colors">Create
          Group</button>
      </div>
    </div>
  </div>