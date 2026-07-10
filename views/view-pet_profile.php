<div id="view-pet_profile"
    class="view-section min-h-screen bg-gray-50 dark:bg-gray-950 flex-col w-full relative overflow-auto p-8 hidden">
    <div class="max-w-6xl mx-auto w-full">
      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">Pet Profile & Kundali</h2>
          <p class="text-gray-500 dark:text-gray-400">View daily horoscopes, check Kundali, and match compatibility.</p>
        </div>
        <button onclick="switchView('view-social-feed')"
          class="px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
          <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
        </button>
      </div>

      <!-- Controls & Tabs -->
      <div
        class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 p-6 mb-6 flex flex-col md:flex-row gap-6 items-center justify-between">
        <div class="w-full md:w-1/3">
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Select Pack Pet</label>
          <select id="pet_profile-member-select" onchange="renderHoroscopeView()"
            class="w-full bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 text-gray-900 dark:text-white rounded-xl px-4 py-3 outline-none focus:ring-2 focus:ring-brand-500 transition-all shadow-sm">
            <!-- Options populated by JS -->
          </select>
        </div>
        <div
          class="nav-scroll flex gap-2 w-full md:w-auto bg-gray-100 dark:bg-gray-800 p-1 rounded-xl overflow-x-auto no-scrollbar"
          style="--hscroll-fade:#f3f4f6;--hscroll-fade-dark:#1f2937;">
          <button onclick="switchHoroscopeTab('daily')" id="tab-pet_profile-daily"
            class="flex-1 md:flex-none px-6 py-2.5 rounded-lg text-base font-bold bg-white shadow-sm text-gray-800 dark:bg-gray-700 dark:text-white transition-all whitespace-nowrap">Daily
            Pet Profile</button>
          <button onclick="switchHoroscopeTab('kundali')" id="tab-pet_profile-kundali"
            class="flex-1 md:flex-none px-6 py-2.5 rounded-lg text-sm font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-white transition-all whitespace-nowrap">Kundali</button>
          <button onclick="switchHoroscopeTab('match')" id="tab-pet_profile-match"
            class="flex-1 md:flex-none px-6 py-2.5 rounded-lg text-sm font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-white transition-all whitespace-nowrap">Playdate</button>
        </div>
      </div>

      <!-- Content Area -->
      <div id="pet_profile-content-daily" class="space-y-6">
        <!-- Daily Pet Profile populated by JS -->
      </div>

      <div id="pet_profile-content-kundali" class="hidden space-y-6">
        <!-- Kundali populated by JS -->
      </div>

      <div id="pet_profile-content-match" class="hidden space-y-6">
        <!-- Playdate populated by JS -->
      </div>

    </div>
  </div>