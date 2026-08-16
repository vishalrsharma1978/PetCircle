<div id="broadcast-modal"
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[150] hidden flex-col items-center justify-center p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between shrink-0">
        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200 flex items-center gap-2">
          <i data-lucide="megaphone" class="w-5 h-5 text-brand-500"></i> Broadcast a Message
        </h2>
        <button onclick="closeBroadcastModal()"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6 space-y-4 overflow-y-auto modal-scroll">
        <p class="text-xs text-gray-500 dark:text-gray-400">Send the same message to selected friends, packs &amp;
          groups.</p>
        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Message</label>
          <textarea id="broadcast-message-text" rows="4" placeholder="Type your announcement..."
            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-800 dark:text-gray-200 focus:outline-none focus:ring-2 focus:ring-brand-500"></textarea>
        </div>
        <div>
          <div class="flex items-center justify-between gap-3 mb-1">
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300">Send to</label>
            <span id="broadcast-select-summary" class="text-xs text-gray-500 dark:text-gray-400">0 selected</span>
          </div>
          <div class="flex gap-2 mb-2">
            <button type="button" id="broadcast-tab-friends" onclick="switchBroadcastTab('friends')"
              class="px-3 py-1 rounded-full text-xs font-bold bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300 transition-colors"><i
                data-lucide="user" class="w-3 h-3 inline -mt-0.5"></i> Friends</button>
            <button type="button" id="broadcast-tab-groups" onclick="switchBroadcastTab('groups')"
              class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"><i
                data-lucide="users" class="w-3 h-3 inline -mt-0.5"></i> Groups</button>
          </div>
          <div class="flex items-center gap-3 mb-2">
            <button type="button" onclick="toggleAllBroadcastTargets(true)"
              class="text-xs font-bold text-brand-600 dark:text-brand-300 hover:underline">Select all</button>
            <button type="button" onclick="toggleAllBroadcastTargets(false)"
              class="text-xs font-bold text-gray-500 dark:text-gray-400 hover:underline">Clear</button>
          </div>
          <div id="broadcast-friends-list"
            class="modal-scroll max-h-56 overflow-y-auto grid gap-2 rounded-xl bg-gray-50 dark:bg-gray-800/60 p-2 border border-gray-100 dark:border-gray-700">
          </div>
          <div id="broadcast-target-list"
            class="modal-scroll max-h-56 overflow-y-auto hidden grid gap-2 rounded-xl bg-gray-50 dark:bg-gray-800/60 p-2 border border-gray-100 dark:border-gray-700">
          </div>
        </div>
      </div>
      <div
        class="shrink-0 px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex justify-end gap-3">
        <button onclick="closeBroadcastModal()"
          class="px-5 py-2 text-gray-600 dark:text-gray-300 font-semibold hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">Cancel</button>
        <button id="broadcast-send-btn" onclick="sendBroadcastMessage()"
          class="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-lg shadow transition-colors flex items-center gap-2">
          <i data-lucide="send" class="w-4 h-4"></i> Send broadcast
        </button>
      </div>
    </div>
  </div>