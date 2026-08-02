<div id="view-member-dashboard" class="view-section min-h-screen bg-gray-50 flex-col transition-colors duration-500">
    <!-- Top bar -->
    <div
      class="bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 px-4 sm:px-6 py-4 flex flex-wrap items-center justify-between shadow-sm gap-2 transition-colors duration-500">
      <div class="flex items-center gap-3">
        <div
          class="w-9 h-9 bg-gradient-to-br from-brand-400 to-brand-300 rounded-lg flex items-center justify-center shrink-0">
          <i data-lucide="users" class="w-4 h-4 text-white"></i>
        </div>
        <span class="font-bold text-gray-900 truncate"
          style="font-family: &quot;DM Serif Display&quot;; font-size: 18px">PawCircle</span>
      </div>
      <div class="flex items-center gap-2 sm:gap-3 overflow-hidden">
        <div class="flex items-center gap-2 truncate">
          <div id="dash-logo-topbar" class="flex items-center justify-center text-xl shrink-0"></div>
          <span class="text-sm font-semibold text-gray-700 truncate" id="dash-name-topbar">User</span>
        </div>
        <button onclick="logout()"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 transition-colors shrink-0">
          <i data-lucide="log-out" class="w-3.5 h-3.5"></i> <span class="hidden sm:inline">Sign Out</span>
        </button>
      </div>
    </div>

    <!-- Dashboard content -->
    <div class="max-w-2xl mx-auto w-full px-4 py-8 relative z-10">
      <!-- Welcome banner -->
      <div id="dash-banner"
        class="bg-gradient-to-r from-brand-400 to-brand-300 rounded-2xl p-6 mb-6 text-white shadow-lg relative overflow-hidden transition-all duration-500 hover:shadow-xl hover:scale-[1.01]">
        <div id="dash-banner-symbol"
          class="absolute right-6 top-1/2 -translate-y-1/2 text-8xl pointer-events-none transform flex items-center justify-center opacity-50 sm:opacity-100"
          style="line-height: 1"></div>
        <div class="relative z-10 pr-16 sm:pr-24 break-words">
          <p class="text-base font-semibold text-white/80 mb-1" id="dash-greeting-sub">
            Pet Breed Portal
          </p>
          <div class="text-xs text-white/70 mb-0.5 uppercase tracking-widest font-semibold" id="dash-greeting-en">
            Welcome
          </div>
          <h2 class="text-2xl sm:text-3xl font-bold break-all sm:break-words"
            style="font-family: &quot;DM Serif Display&quot;; overflow-wrap: anywhere;">
            <span id="dash-greeting">Namaste</span>,
            <span id="dash-name">User</span>!
          </h2>
          <p class="text-sm text-white/80 mt-1">
            Welcome to your PawCircle member portal.
            <span id="dash-trust-badge-wrap"></span>
          </p>
        </div>
      </div>

      <!-- Daily Mantra / Quote -->
      

      <!-- Membership form container -->
      <div id="membership-form-container"></div>
    </div>
  </div>