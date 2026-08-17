<div id="create-rescue-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
      <h3 class="font-bold text-gray-900 dark:text-white" id="rescue-modal-title">Post an opportunity</h3>
      <button onclick="closeCreateRescueModal()" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto p-5 space-y-3">
      <input type="hidden" id="rescue-modal-editing-id" value="">
      <div class="grid grid-cols-2 gap-2">
        <input type="text" id="new-rescue-title" placeholder="Title" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <input type="text" id="new-rescue-org" placeholder="Organization" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </div>
      <div class="grid grid-cols-2 gap-2">
        <select id="new-rescue-category" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
          <option value="seva">Seva</option>
          <option value="teaching">Teaching</option>
          <option value="medical">Medical</option>
          <option value="event">Event</option>
          <option value="fundraising">Fundraising</option>
          <option value="environment">Environment</option>
          <option value="elderly">Senior Pet Care</option>
          <option value="tech">Tech</option>
        </select>
        <select id="new-rescue-urgency" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
          <option value="low">Low urgency</option>
          <option value="medium" selected>Medium urgency</option>
          <option value="high">High urgency</option>
        </select>
      </div>
      <input type="text" id="new-rescue-location" placeholder="Location" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      <div class="grid grid-cols-2 gap-2">
        <input type="date" id="new-rescue-date" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <input type="number" id="new-rescue-slots" min="1" value="10" placeholder="Slots" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </div>
      <input type="text" id="new-rescue-contact" placeholder="Contact (phone or email)" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      <textarea id="new-rescue-description" rows="2" placeholder="Describe what's needed" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white resize-none"></textarea>
    </div>
    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex-shrink-0">
      <button type="button" id="rescue-modal-submit-btn" onclick="submitCreateRescueOpportunity()" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Post opportunity</button>
    </div>
  </div>
</div>
