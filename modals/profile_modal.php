<div id="profile-modal"
  class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
  <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
    <div class="bg-gray-50 dark:bg-gray-950 px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
      <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100 flex items-center">
        <i data-lucide="user-cog" class="w-5 h-5 mr-2 text-brand-500"></i>
        Edit Pack Profile
      </h2>
      <button onclick="closeProfileModal()"
        class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 p-1.5 rounded-full transition-colors">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="p-6 overflow-y-auto">
      <div id="profile-modal-error" class="hidden mb-4 bg-red-50 border-l-4 border-red-400 p-3 rounded text-sm text-red-700"></div>
      <form id="profile-form" class="space-y-4">
        <div class="relative mb-10 mt-2">
          <!-- Cover photo -->
          <div class="h-24 bg-gradient-to-r from-brand-300 to-brand-400 rounded-xl relative group cursor-pointer overflow-hidden"
            id="prof-banner" onclick="document.getElementById('cover-photo-input').click()">
            <img id="prof-banner-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden" />
            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
              <i data-lucide="camera" class="w-6 h-6 text-white"></i>
            </div>
          </div>
          <input type="file" id="cover-photo-input" accept="image/jpeg,image/png,image/webp" class="hidden"
            onchange="handleCoverUpload(this)" />

          <!-- Avatar -->
          <div class="absolute -bottom-6 left-4 flex items-end gap-3">
            <div class="w-16 h-16 bg-white dark:bg-gray-800 rounded-full p-1 shadow-sm">
              <div class="w-full h-full bg-brand-100 rounded-full flex items-center justify-center text-brand-900 font-bold text-xl relative group cursor-pointer overflow-hidden"
                id="profile-modal-avatar" onclick="document.getElementById('avatar-upload-input').click()">
                <span id="profile-modal-avatar-text">P</span>
                <img id="profile-modal-avatar-img" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden rounded-full" />
                <div class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                  <i data-lucide="camera" class="w-4 h-4 text-white"></i>
                </div>
              </div>
              <input type="file" id="avatar-upload-input" accept="image/jpeg,image/png,image/webp" class="hidden"
                onchange="handleAvatarUpload(this)" />
            </div>
            <div class="pb-1">
              <h3 class="font-bold text-gray-800 dark:text-gray-100 text-lg leading-tight" id="profile-modal-title-name">Pet Name</h3>
            </div>
          </div>
        </div>

        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700">
          <div>
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">Public Profile</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Allow others to find and view this profile</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="prof-public-toggle" class="sr-only peer" checked />
            <div class="w-9 h-5 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-500">
            </div>
          </label>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pet Name</label>
            <input type="text" id="prof-pet-name"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 focus:border-brand-300 dark:focus:border-brand-500 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pet Parent Name</label>
            <input type="text" id="prof-parent-name"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 focus:border-brand-300 dark:focus:border-brand-500 text-sm" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pet Type</label>
            <select id="prof-pet-type" onchange="updateBreedOptions('prof-pet-type','prof-breed')"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 text-sm bg-white">
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
            <select id="prof-breed"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 text-sm bg-white">
              <option value="">Select Breed...</option>
            </select>
          </div>
        </div>
        <p class="text-xs text-amber-600 dark:text-amber-400 -mt-2">Changing pet type or breed removes you from groups scoped to your current one.</p>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Gender</label>
            <select id="prof-gender"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 text-sm bg-white">
              <option value="">Select...</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Unknown">Unknown</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Date of Birth</label>
            <input type="date" id="prof-dob"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 text-sm" />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">City</label>
            <input type="text" id="prof-city"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Contact No</label>
            <input type="tel" id="prof-contact"
              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 text-sm" />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Microchip Number <span class="font-normal text-gray-400">(optional)</span></label>
          <input type="text" id="prof-microchip"
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 text-sm" />
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Bio</label>
          <textarea id="prof-bio" rows="3" placeholder="Tell the pack a bit about your pet..."
            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white rounded-lg focus:ring-2 focus:ring-brand-200 dark:focus:ring-brand-500 focus:border-brand-300 dark:focus:border-brand-500 text-sm resize-none"></textarea>
        </div>
      </form>
    </div>
    <div class="bg-gray-50 dark:bg-gray-950 px-6 py-4 border-t border-gray-100 dark:border-gray-800 flex justify-end gap-3">
      <button type="button" onclick="closeProfileModal()"
        class="px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 transition-colors">
        Cancel
      </button>
      <button id="save-profile-btn" type="button" onclick="saveProfile()"
        class="px-5 py-2 text-sm font-bold text-white bg-brand-400 hover:bg-brand-500 rounded-lg shadow-sm transition-colors flex items-center gap-2">
        <span>Save Changes</span>
      </button>
    </div>
  </div>
</div>
