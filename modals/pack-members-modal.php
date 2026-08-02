<div id="pack-members-modal"
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[60] hidden flex-col items-center justify-center p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
      <div
        class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center">
          <i data-lucide="users" class="w-5 h-5 mr-2 text-brand-500"></i>
          Manage Pack Pets
        </h2>
        <button onclick="closePackMembersModal()"
          class="text-gray-400 hover:text-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-100 dark:hover:bg-gray-600 p-1.5 rounded-full transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6 overflow-y-auto flex-1 flex flex-col gap-6">
        <!-- Add New Member Form -->
        <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-base font-bold text-gray-800 dark:text-gray-200">Add New Member</h3>
            <div class="flex items-center gap-2">
              <span class="text-xs text-gray-500">Auto-fill from friends:</span>
              <select id="fam-import-friend" onchange="importFriendForPack(this.value)"
                class="px-2 py-1 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-xs focus:ring-2 focus:ring-brand-200">
                <option value="">Select a friend...</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Name</label>
              <input type="text" id="fam-name"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Relationship</label>
              <select id="fam-relation"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm">
                <option value="Spouse">Spouse</option>
                <option value="Father">Father</option>
                <option value="Mother">Mother</option>
                <option value="Son">Son</option>
                <option value="Daughter">Daughter</option>
                <option value="Brother">Brother</option>
                <option value="Sister">Sister</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Date of Birth</label>
              <input type="date" id="fam-dob"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Education</label>
              <input type="text" id="fam-edu"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Work Details</label>
              <input type="text" id="fam-work"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pet Profile
                (Breed/Rashi)</label>
              <input type="text" id="fam-pet_profile"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
          </div>
          <div class="mt-4 flex justify-end">
            <button onclick="addPackMember()"
              class="px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white font-semibold text-sm rounded-lg transition-colors flex items-center gap-2">
              <i data-lucide="plus" class="w-4 h-4"></i> Add Pet
            </button>
          </div>
        </div>

        <!-- Member List -->
        <div>
          <h3 class="text-base font-bold text-gray-800 dark:text-gray-200 mb-3">Current Members</h3>
          <div id="pack-members-list" class="space-y-3">
            <!-- Dynamically populated -->
          </div>
        </div>
      </div>
    </div>
  </div>