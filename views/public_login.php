<div id="view-public-login"
  class="view-section active h-auto lg:h-[100dvh] lg:max-h-[100dvh] overflow-y-auto lg:overflow-hidden flex flex-col lg:flex-row items-stretch w-full"
  style="--login-accent: #f97316;">

  <!-- ── LEFT PANE: Feature Showcase & Feed ────────────────────────────── -->
  <style>
    @media (min-width: 1024px) {
      #lp-left-pane { width: 63% !important; flex: none !important; }
      #lp-right-pane { width: 37% !important; flex: none !important; }
    }
  </style>
  <div id="lp-left-pane"
    class="w-full lg:w-[55%] h-auto lg:h-full lg:max-h-[100dvh] flex flex-col p-4 sm:p-5 lg:p-6 overflow-hidden overflow-x-hidden no-scrollbar relative"
    style="background:linear-gradient(160deg, color-mix(in srgb, var(--login-accent) 32%, #ffffff) 0%, color-mix(in srgb, var(--login-accent) 22%, #f8fafc) 100%); transition:background 0.5s ease, --login-accent 0.5s ease">

    <!-- Trailing Paws Background -->
    <section class="den" id="login-den" aria-hidden="true"
      style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; pointer-events: none; opacity: 0.4; z-index: 0;">
      <svg class="trail" viewBox="0 0 480 900" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"
        xmlns:xlink="http://www.w3.org/1999/xlink" style="width: 100%; height: 100%;">
        <path id="loginTrailPath"
          d="M 60 30 C 210 110 20 230 220 290 S 50 460 250 520 S 30 690 230 760 S 70 850 190 890" fill="none"
          stroke="currentColor" stroke-opacity="0.1" stroke-width="2" />
        <g opacity="0">
          <use href="#icon-paw" xlink:href="#icon-paw" width="18" height="18" x="-9" y="-9" />
          <animateMotion dur="10s" begin="0s" repeatCount="indefinite" rotate="auto">
            <mpath href="#loginTrailPath" xlink:href="#loginTrailPath" />
          </animateMotion>
          <animate attributeName="opacity" values="0;0.9;0.9;0" keyTimes="0;0.1;0.85;1" dur="10s" begin="0s"
            repeatCount="indefinite" />
        </g>
        <g opacity="0">
          <use href="#icon-paw" xlink:href="#icon-paw" width="18" height="18" x="-9" y="-9" />
          <animateMotion dur="10s" begin="3s" repeatCount="indefinite" rotate="auto">
            <mpath href="#loginTrailPath" xlink:href="#loginTrailPath" />
          </animateMotion>
          <animate attributeName="opacity" values="0;0.9;0.9;0" keyTimes="0;0.1;0.85;1" dur="10s" begin="3s"
            repeatCount="indefinite" />
        </g>
        <g opacity="0">
          <use href="#icon-paw" xlink:href="#icon-paw" width="18" height="18" x="-9" y="-9" />
          <animateMotion dur="10s" begin="6s" repeatCount="indefinite" rotate="auto">
            <mpath href="#loginTrailPath" xlink:href="#loginTrailPath" />
          </animateMotion>
          <animate attributeName="opacity" values="0;0.9;0.9;0" keyTimes="0;0.1;0.85;1" dur="10s" begin="6s"
            repeatCount="indefinite" />
        </g>
      </svg>
    </section>

    <!-- Brand header -->
    <div class="flex items-center gap-3 mb-4 flex-shrink-0 relative z-10">
      <div
        class="w-11 h-11 rounded-2xl bg-white/60 border border-amber-200/70 flex items-center justify-center relative shadow-sm flex-shrink-0 backdrop-blur-md">
        <i data-lucide="paw-print" class="w-4.5 h-4.5 text-violet-900"></i>
        <i data-lucide="paw-print" class="w-2.5 h-2.5 text-violet-700 absolute right-1.5 top-1.5"></i>
      </div>
      <div>
        <div class="text-2xl font-bold tracking-tight text-black leading-tight" style="font-family: 'Poppins'">
          PawCircle</div>
        <div class="text-[11px] font-black text-amber-500 tracking-[0.2em] uppercase">Pet Circle</div>
      </div>
      <span
        class="ml-auto flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-green-100/80 text-green-700 border border-green-200/80 flex-shrink-0 backdrop-blur-sm">
        <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse inline-block"></span>
        <span id="lp-online-count">— online</span>
      </span>
    </div>

    <!-- Live rotating feed card (hidden on small screens: the left pane is a
         compact decorative banner there, not a competing full-height panel —
         see the mobile-first composition note in main.css) -->
    <div id="lp-feed-card"
      class="hidden lg:block flex-1 min-h-0 rounded-2xl warm-glass warm-lift p-4 mb-2 relative z-10 transition-all duration-500">
    </div>

    <div class="mt-auto pt-2 relative z-10 w-full flex flex-col items-start">
      <div>
        <label class="block text-xs font-medium mb-1 text-gray-700">Your Pet
          Type</label>
        <div class="relative">
          <select id="login-pet-type"
            class="block w-full max-w-[200px] pl-4 pr-10 py-1.5 text-xs border border-gray-300 rounded-xl focus:outline-none focus:border-transparent bg-white/90 text-gray-900 appearance-none transition-all focus-ring-dynamic"
            onchange="handleLoginPetTypeChange(this.value)">
            <option class="text-gray-900" value="">I don't have a pet / Just exploring</option>
            <option class="text-gray-900" value="Dog">Dog</option>
            <option class="text-gray-900" value="Cat">Cat</option>
            <option class="text-gray-900" value="Bird">Bird</option>
            <option class="text-gray-900" value="Rabbit">Rabbit</option>
            <option class="text-gray-900" value="Fish">Fish</option>
            <option class="text-gray-900" value="Reptile">Reptile</option>
            <option class="text-gray-900" value="Small Pet">Small Pet</option>
            <option class="text-gray-900" value="Other">Other</option>
          </select>
          <i data-lucide="chevron-down" class="absolute right-3 top-3.5 w-4 h-4 text-gray-500 pointer-events-none"></i>
        </div>
      </div>
    </div>
  </div>

  <!-- ── RIGHT PANE: Login Form ────────────────────────────────────────── -->
  <div id="lp-right-pane"
    class="w-full lg:w-[45%] h-auto lg:h-full flex flex-col items-center justify-start pt-8 sm:pt-10 lg:pt-16 pb-8 bg-white border-t lg:border-t-0 lg:border-l border-gray-100 p-6 sm:px-8 overflow-y-auto overflow-x-hidden"
    style="transition: --login-accent 0.5s ease;">

    <div class="w-full max-w-[460px] p-6 sm:p-7">

      <!-- Welcome Header -->
      <div class="mb-6 text-center">
        <div id="lp-brand-badge"
          class="inline-flex items-center justify-center w-16 h-16 rounded-2xl shadow-sm mb-3 border border-white/70 overflow-hidden transition-colors duration-500 breathing-glow"
          style="background-color: var(--login-accent);">
          <img id="lp-brand-badge-img" src="assets/mascots/pawcircle-logo.svg" alt="PawCircle logo" class="w-11 h-11 object-contain"
            loading="eager" decoding="async" />
        </div>
        <h3 class="text-3xl font-bold text-gray-900" style="font-family: 'Poppins'">Welcome back</h3>
        <p class="mt-2 text-sm text-gray-500">Let the tail-wagging fun begin!</p>
        <span class="av2-try-link mt-3" onclick="openAuthV2Login()">✨ Try our playful new look</span>
      </div>

      <div id="public-error"
        class="hidden mb-6 bg-red-50 border-l-4 border-red-400 p-3 rounded-lg text-sm text-red-700"></div>

      <form id="public-login-form" novalidate autocomplete="off" class="space-y-10">

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Email address</label>
          <div class="relative">
            <i data-lucide="mail" class="absolute left-3.5 top-3.5 w-4 h-4 text-gray-400 pointer-events-none"></i>
            <input type="email" id="public-email" name="public_email" required autocomplete="off" autocapitalize="off"
              autocorrect="off" spellcheck="false" style="color: #111827 !important;"
              class="block w-full pl-10 pr-3 py-3 border border-gray-200 rounded-xl focus:outline-none focus:border-transparent text-sm bg-gray-50 text-gray-900 transition-all focus-ring-dynamic"
              placeholder="you@example.com" />
          </div>
        </div>

        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="block text-sm font-semibold text-gray-700">Password</label>
            <button type="button" onclick="switchView('view-forgot-password'); showForgotStepRequest();"
              class="text-xs font-semibold" style="color: var(--login-accent);">Forgot password?</button>
          </div>
          <div class="relative flex flex-col">
            <div class="relative w-full">
              <i data-lucide="lock" class="absolute left-3.5 top-3.5 w-4 h-4 text-gray-400 pointer-events-none"></i>
              <input type="password" id="public-password" name="public_password" required autocomplete="new-password"
                style="color: #111827 !important;"
                class="block w-full pl-10 pr-12 py-3 border border-gray-200 rounded-xl focus:outline-none focus:border-transparent text-sm bg-gray-50 text-gray-900 transition-all focus-ring-dynamic"
                placeholder="••••••••" data-pw-toggle="1" />

              <button type="button" class="absolute right-3 top-3 text-gray-400 hover:text-gray-600 transition-colors"
                id="loginEyeToggle" aria-label="Show password">
                <i data-lucide="eye" id="loginEyeIcon" class="w-5 h-5"></i>
              </button>
            </div>
          </div>
        </div>

        <button type="submit" id="public-submit-btn"
          class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-base font-bold text-white transition-all hover:-translate-y-0.5 duration-500"
          style="background-color: var(--login-accent); box-shadow: 0 4px 14px 0 rgba(0,0,0,0.1);">
          Sign in
        </button>
      </form>

      <!-- Links -->
      <div class="mt-8 text-center">
        <span class="text-sm text-gray-500">New to PawCircle?</span>
        <button onclick="switchView('view-signup')"
          class="font-bold flex items-center justify-center w-full mt-3 py-3 rounded-xl transition-colors bg-gray-50 hover:bg-gray-100"
          style="color: var(--login-accent)">
          <i data-lucide="user-plus" class="w-4 h-4 mr-2"></i> Create an Account
        </button>
      </div>

      <!-- No separate admin login page: an already-signed-in member with an
           admin_roles grant unlocks admin mode from the header shield icon
           (see openAdminEntry()/modals/admin_mode_modal.php) instead. -->

    </div>
  </div>
</div>
