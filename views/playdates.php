<div id="view-playdates" class="view-section hidden min-h-screen bg-gray-50 dark:bg-gray-950 flex-col w-full">
  <div class="max-w-2xl mx-auto w-full p-6 sm:p-8">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Playdates</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Find compatible playmates nearby.</p>
      </div>
      <button onclick="switchView('view-pet-profile')"
        class="px-3 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 text-sm font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
      </button>
    </div>

    <div class="flex bg-gray-100 dark:bg-gray-800 rounded-lg p-1 mb-6 w-fit">
      <button id="pd-tab-deck" onclick="switchPlaydateTab('deck')" class="no-accent-hover px-4 py-1.5 rounded-md text-sm font-semibold transition-colors">Deck</button>
      <button id="pd-tab-matches" onclick="switchPlaydateTab('matches')" class="no-accent-hover px-4 py-1.5 rounded-md text-sm font-semibold transition-colors">Matches</button>
      <button id="pd-tab-setup" onclick="switchPlaydateTab('setup')" class="no-accent-hover px-4 py-1.5 rounded-md text-sm font-semibold transition-colors">My Playdate Profile</button>
    </div>

    <!-- DECK -->
    <div id="pd-panel-deck">
      <div id="pd-deck-card-wrap" class="relative h-[480px]">
        <p id="pd-deck-empty" class="hidden text-center text-sm text-gray-400 py-20">No more pets to show right now — check back later, or widen your preferences.</p>
      </div>
      <div id="pd-deck-actions" class="hidden flex items-center justify-center gap-6 mt-6">
        <button onclick="swipeCurrentDeckCard('pass')" class="w-14 h-14 rounded-full bg-white dark:bg-gray-800 border-2 border-gray-200 dark:border-gray-700 shadow-md flex items-center justify-center text-gray-400 hover:text-red-500 hover:border-red-200 transition-colors">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
        <button onclick="swipeCurrentDeckCard('like')" class="w-14 h-14 rounded-full bg-brand-500 hover:bg-brand-600 shadow-md flex items-center justify-center text-white transition-colors">
          <i data-lucide="heart" class="w-6 h-6"></i>
        </button>
      </div>
    </div>

    <!-- MATCHES -->
    <div id="pd-panel-matches" class="hidden">
      <div id="pd-matches-list" class="grid grid-cols-2 sm:grid-cols-3 gap-3"></div>
    </div>

    <!-- SETUP -->
    <div id="pd-panel-setup" class="hidden bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-5 space-y-6">
      <div>
        <div class="flex items-center justify-between mb-3">
          <h3 class="font-bold text-sm text-gray-800 dark:text-gray-200">Playdate Profile</h3>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" id="pd-is-active" class="sr-only peer" checked />
            <div class="w-9 h-5 bg-gray-300 dark:bg-gray-600 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-500"></div>
          </label>
        </div>
        <p class="text-xs text-gray-400 -mt-2 mb-3">Turn this off to hide your pet from other people's decks.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Size</label>
            <select id="pd-size" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="">Select...</option>
              <option value="Small">Small</option>
              <option value="Medium">Medium</option>
              <option value="Large">Large</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Energy Level</label>
            <select id="pd-energy-level" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="">Select...</option>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Weight (kg)</label>
            <input type="number" id="pd-weight-kg" min="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Vaccination Status</label>
            <input type="text" id="pd-vaccination-status" placeholder="e.g. Up to date" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Friendly with dogs?</label>
            <select id="pd-friendly-dogs" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="">Select...</option>
              <option value="Yes">Yes</option>
              <option value="Selective">Selective</option>
              <option value="No">No</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Friendly with cats?</label>
            <select id="pd-friendly-cats" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="">Select...</option>
              <option value="Yes">Yes</option>
              <option value="Selective">Selective</option>
              <option value="No">No</option>
            </select>
          </div>
        </div>
        <div class="mt-3">
          <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Favorite Activities <span class="font-normal text-gray-400">(comma separated)</span></label>
          <input type="text" id="pd-favorite-activities" placeholder="fetch, swimming, tug of war" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
        </div>
        <div class="mt-3">
          <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Dietary Restrictions <span class="font-normal text-gray-400">(optional)</span></label>
          <input type="text" id="pd-dietary-restrictions" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
        </div>
        <button id="pd-profile-save-btn" onclick="savePlaydateProfileForm()" class="mt-4 px-4 py-2 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Save Playdate Profile</button>
      </div>

      <div class="pt-5 border-t border-gray-100 dark:border-gray-800">
        <h3 class="font-bold text-sm text-gray-800 dark:text-gray-200 mb-3">Match Preferences</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Pet Type</label>
            <select id="pd-pref-pet-type" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="Any">Any</option>
              <option value="Dog">Dog</option>
              <option value="Cat">Cat</option>
              <option value="Bird">Bird</option>
              <option value="Fish">Fish</option>
              <option value="Small Pet">Small Pet</option>
              <option value="Reptile">Reptile</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Breed</label>
            <input type="text" id="pd-pref-breed" placeholder="Any" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Size</label>
            <select id="pd-pref-size" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="Any">Any</option>
              <option value="Small">Small</option>
              <option value="Medium">Medium</option>
              <option value="Large">Large</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Energy Level</label>
            <select id="pd-pref-energy-level" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="Any">Any</option>
              <option value="Low">Low</option>
              <option value="Medium">Medium</option>
              <option value="High">High</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Gender</label>
            <select id="pd-pref-gender" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
              <option value="Any">Any</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Min age (months)</label>
              <input type="number" id="pd-pref-age-min" min="0" value="0" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400 mb-1">Max age (months)</label>
              <input type="number" id="pd-pref-age-max" min="0" value="240" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg text-sm">
            </div>
          </div>
        </div>
        <button id="pd-prefs-save-btn" onclick="savePlaydatePreferencesForm()" class="mt-4 px-4 py-2 rounded-lg text-sm font-bold text-white bg-brand-500 hover:bg-brand-600">Save Preferences</button>
      </div>
    </div>
  </div>
</div>
