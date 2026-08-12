<div id="pack-members-modal"
  class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[60] hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
    <div class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
      <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center">
        <i data-lucide="users" class="w-5 h-5 mr-2 text-brand-500"></i>
        Manage Pack Members
      </h2>
      <button onclick="closePackMembersModal()"
        class="text-gray-400 hover:text-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 p-1.5 rounded-full transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6 overflow-y-auto flex-1 flex flex-col gap-6">
      <!-- Add New Member Form -->
      <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
        <div class="flex items-center justify-between mb-3 flex-wrap gap-2">
          <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">Add Pack Member</h3>
          <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Auto-fill from friends:</span>
            <select id="pack-import-friend" onchange="importFriendForPack(this.value)"
              class="px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-xs focus:ring-2 focus:ring-brand-200">
              <option value="">Select a friend...</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pet Name</label>
            <input type="text" id="pack-name"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Relation</label>
            <select id="pack-relation"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm">
              <option value="Parent">Parent</option>
              <option value="Sibling Pet">Sibling Pet</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pet Type</label>
            <select id="pack-pet-type" onchange="updateBreedOptions('pack-pet-type','pack-breed')"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm">
              <option value="">Select...</option>
              <option value="Dog">Dog</option>
              <option value="Cat">Cat</option>
              <option value="Bird">Bird</option>
              <option value="Fish">Fish</option>
              <option value="Small Pet">Small Pet</option>
              <option value="Reptile">Reptile</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Breed</label>
            <select id="pack-breed"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm">
              <option value="">Select Breed...</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Date of Birth</label>
            <input type="date" id="pack-dob"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Gender</label>
            <select id="pack-gender"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm">
              <option value="">Select...</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Unknown">Unknown</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Microchip Number <span class="font-normal text-gray-400">(optional)</span></label>
            <input type="text" id="pack-microchip"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
          </div>
        </div>
        <input type="hidden" id="pack-editing-id" value="">
        <input type="hidden" id="pack-linked-user-id" value="">
        <div class="mt-4 flex justify-end gap-2">
          <button id="pack-cancel-edit-btn" onclick="resetPackMemberForm()" style="display:none;"
            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 font-semibold text-sm rounded-lg transition-colors">
            Cancel edit
          </button>
          <button id="pack-save-btn" onclick="savePackMember()"
            class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-lg transition-colors flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> <span id="pack-save-btn-label">Add Member</span>
          </button>
        </div>
      </div>

      <!-- Member List -->
      <div>
        <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-3">Current Members</h3>
        <div id="pack-members-list" class="space-y-3"></div>
      </div>
    </div>
  </div>
</div>
