<div id="profile-modal"
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
      <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-800 flex items-center">
          <i data-lucide="user-cog" class="w-5 h-5 mr-2 text-brand-500"></i>
          Edit Profile
        </h2>
        <button onclick="closeProfileModal()"
          class="text-gray-400 hover:text-gray-600 bg-white hover:bg-gray-100 p-1.5 rounded-full transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6 overflow-y-auto">
        <form id="profile-form" class="space-y-4">
          <div class="relative mb-10 mt-2">
            <!-- Banner -->
            <div
              class="h-24 bg-gradient-to-r from-brand-300 to-brand-400 rounded-xl relative group cursor-pointer overflow-hidden"
              id="prof-banner" onclick="document.getElementById('cover-photo-input').click()">
              <div
                class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                <i data-lucide="camera" class="w-6 h-6 text-white"></i>
              </div>
            </div>
            <!-- Profile Pic -->
            <div class="absolute -bottom-6 left-4 flex items-end gap-3">
              <div class="w-16 h-16 bg-white rounded-full p-1 shadow-sm">
                <div
                  class="w-full h-full bg-brand-100 rounded-full flex items-center justify-center text-brand-900 font-bold text-xl relative group cursor-pointer overflow-hidden"
                  id="profile-modal-avatar" onclick="
                      document.getElementById('avatar-upload-input').click()
                    ">
                  <span id="profile-modal-avatar-text">U</span>
                  <img id="profile-modal-avatar-img" src="" alt=""
                    class="absolute inset-0 w-full h-full object-cover hidden rounded-full" />
                  <div
                    class="absolute inset-0 bg-black/30 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity">
                    <i data-lucide="camera" class="w-4 h-4 text-white"></i>
                  </div>
                </div>
                <input type="file" id="avatar-upload-input" accept="image/jpeg,image/png,image/webp" class="hidden"
                  onchange="handleAvatarUpload(this)" />
              </div>
              <div class="pb-1">
                <h3 class="font-bold text-gray-800 text-lg leading-tight" id="profile-modal-title-name">
                  User Name
                </h3>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
            <div>
              <p class="text-sm font-semibold text-gray-800">
                Public Profile
              </p>
              <p class="text-xs text-gray-500">
                Allow others to find and view your profile
              </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
              <input type="checkbox" id="prof-public-toggle" class="sr-only peer" checked />
              <div
                class="w-9 h-5 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-500">
              </div>
            </label>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Full Name (Pet's Name)</label>
            <input type="text" id="prof-name"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 focus:border-brand-300 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Parent / Owner Name</label>
            <input type="text" id="prof-parent-name" placeholder="e.g. John Smith"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 focus:border-brand-300 text-sm" />
          </div>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Gender</label>
              <select id="prof-gender"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 text-sm bg-white">
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Pet Breed</label>
              <input type="text" id="prof-breed"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-1 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Pet Type</label>
              <input type="text" id="prof-pet_type"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Age Group</label>
              <select id="prof-age"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 text-sm">
                <option value="">Select...</option>
                <option value="Under 18">Under 18</option>
                <option value="18 – 25">18 – 25</option>
                <option value="26 – 40">26 – 40</option>
                <option value="41 – 60">41 – 60</option>
                <option value="60+">60+</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Occupation</label>
              <input type="text" id="prof-occupation"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Contact No</label>
            <input type="tel" id="prof-contact"
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 text-sm" />
            <div class="mt-2 flex items-start gap-2">
              <input type="checkbox" id="prof-share-contact" checked="checked" class="mt-1">
              <label for="prof-share-contact" class="text-xs text-gray-600 leading-tight">
                I here by agree to share my contact info with breed folks.
              </label>
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Bio</label>
            <textarea id="prof-bio" rows="3" placeholder="Tell your breed a bit about yourself..."
              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 focus:border-brand-300 text-sm resize-none"></textarea>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tags / Interests</label>
            <p class="text-xs text-gray-400 mb-2">Add tags so the breed knows your interests and skills.</p>
            <div id="prof-tags-list" class="flex flex-wrap gap-1.5 mb-2"></div>
            <div class="flex gap-2">
              <input type="text" id="prof-tag-input" maxlength="30" placeholder="e.g. Rescueing, Music, Business"
                onkeydown="handleProfileTagKeydown(event)"
                class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-brand-200 focus:border-brand-300 text-sm" />
              <button type="button" onclick="addProfileTag()"
                style="--no-faith-hover-bg: var(--brand-500, #e04848); --no-faith-hover-color:#ffffff; --no-faith-hover-border: var(--brand-500, #e04848);"
                class="no-faith-hover px-4 py-2 text-base font-bold text-white bg-brand-400 hover:bg-brand-500 rounded-lg shadow-sm transition-colors flex items-center gap-1 whitespace-nowrap">
                <i data-lucide="plus" class="w-4 h-4"></i> Add
              </button>
            </div>
          </div>
        </form>
      </div>
      <div class="bg-gray-50 px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
        <button type="button" onclick="closeProfileModal()"
          class="px-4 py-2 text-base font-semibold text-gray-600 hover:text-gray-800 transition-colors">
          Cancel
        </button>
        <button id="save-profile-btn" type="button" onclick="saveProfile()"
          class="px-5 py-2 text-base font-bold text-white bg-brand-400 hover:bg-brand-500 rounded-lg shadow-sm transition-colors flex items-center gap-2">
          <span>Save Changes</span>
        </button>
      </div>
    </div>
  </div>