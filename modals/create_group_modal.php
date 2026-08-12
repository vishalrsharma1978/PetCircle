<div id="create-group-modal" class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]">
    <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between flex-shrink-0">
      <h3 class="font-bold text-gray-900 dark:text-white">Create a group</h3>
      <button onclick="closeCreateGroupModal()" class="p-1.5 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div class="flex-1 overflow-y-auto p-5 space-y-3">
      <input type="text" id="new-group-name" placeholder="Group name" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      <textarea id="new-group-description" rows="2" placeholder="What's this group about?" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white resize-none"></textarea>
      <select id="new-group-scope" onchange="document.getElementById('new-group-breed-wrap').style.display = this.value==='breed' ? 'block' : 'none'"
        class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
        <option value="global">Open to all pet types</option>
        <option value="pet_type">Scoped to my pet type</option>
        <option value="breed">Scoped to my breed</option>
      </select>
      <div id="new-group-breed-wrap" style="display:none;">
        <input type="text" id="new-group-breed" placeholder="Breed" class="w-full px-3 py-2 border border-gray-200 dark:border-gray-700 rounded-lg text-sm bg-gray-50 dark:bg-gray-800 dark:text-white">
      </div>
    </div>
    <div class="px-5 py-4 border-t border-gray-100 dark:border-gray-800 flex-shrink-0">
      <button type="button" id="group-modal-submit-btn" onclick="submitCreateGroup()" class="w-full px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Create group</button>
    </div>
  </div>
</div>
