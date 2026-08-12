<div id="create-event-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
      <div>
        <h3 id="event-modal-heading" class="font-bold text-gray-900 dark:text-white">Create an event</h3>
        <p id="event-modal-subtitle" class="text-xs text-gray-500 dark:text-gray-400">Invite friends and groups once it's set up.</p>
      </div>
      <button onclick="closeCreateEventModal()" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto p-5 space-y-3">
      <input type="hidden" id="event-modal-id">
      <input type="text" id="new-event-title" placeholder="Event title" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      <div class="grid grid-cols-2 gap-2">
        <input type="date" id="new-event-date" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <input type="time" id="new-event-time" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </div>

      <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
        <input type="checkbox" id="new-event-is-online" onchange="document.getElementById('new-event-meeting-url-wrap').classList.toggle('hidden', !this.checked)" class="rounded border-gray-300">
        This is an online event
      </label>
      <div id="new-event-meeting-url-wrap" class="hidden">
        <input type="url" id="new-event-meeting-url" placeholder="Meeting link (Zoom, Meet, etc.)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </div>
      <input type="text" id="new-event-location" placeholder="Location" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      <textarea id="new-event-description" rows="2" placeholder="Details" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white resize-none"></textarea>

      <div>
        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1.5">Banner image (optional)</label>
        <div class="flex items-center gap-3">
          <label for="new-event-banner-input" class="cursor-pointer inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 text-xs font-bold">
            <i data-lucide="image-plus" class="w-4 h-4"></i> Choose image
          </label>
          <span id="new-event-banner-status" class="text-xs text-gray-400">No image chosen</span>
          <input type="file" id="new-event-banner-input" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="handleEventBannerUpload(this)">
        </div>
        <input type="hidden" id="new-event-banner-url">
        <img id="new-event-banner-preview" src="" alt="" class="hidden mt-2 w-full h-28 object-cover rounded-lg">
      </div>

      <div>
        <div class="flex gap-4 border-b border-gray-100 dark:border-gray-800">
          <button type="button" data-invite-tab="friends" onclick="switchEventInviteTab('friends')" class="event-invite-tab-btn pb-2 text-xs font-bold border-b-2">Invite friends</button>
          <button type="button" data-invite-tab="groups" onclick="switchEventInviteTab('groups')" class="event-invite-tab-btn pb-2 text-xs font-bold border-b-2">Invite groups</button>
        </div>
        <div id="event-invite-friends-list" class="mt-2 max-h-32 overflow-y-auto space-y-1"></div>
        <div id="event-invite-groups-list" class="mt-2 max-h-32 overflow-y-auto space-y-1 hidden"></div>
      </div>
    </div>
    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex-shrink-0">
      <button id="event-modal-submit-btn" type="button" onclick="submitEventModal()" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Create event</button>
    </div>
  </div>
</div>
