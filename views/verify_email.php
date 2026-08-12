<div id="view-verify-email"
  class="view-section hidden min-h-screen bg-gradient-to-br from-brand-100/20 via-brand-200/10 to-brand-400/10 flex-col justify-center py-12 sm:px-6 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-md">
    <div class="flex justify-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl shadow-sm bg-brand-500 border border-white/70 overflow-hidden">
        <img src="assets/mascots/pawcircle-logo.svg" alt="PawCircle logo" class="w-11 h-11 object-contain">
      </div>
    </div>
    <div class="text-center mt-6 mb-8">
      <h2 class="text-3xl font-bold text-gray-900" style="font-family: 'Poppins'">
        Verify your email
      </h2>
    </div>
    <div class="bg-white py-8 px-4 shadow-xl sm:rounded-2xl sm:px-10 border border-brand-200/30">
      <p class="text-sm text-gray-600 text-center mb-1">
        We've sent a 6-digit code to
      </p>
      <p id="verify-email-target" class="text-sm font-semibold text-gray-900 text-center mb-6 break-all"></p>

      <div id="verify-error"
        class="hidden mb-4 bg-red-50 border-l-4 border-brand-400 p-3 rounded text-sm text-red-700"></div>

      <form id="verify-email-form">
        <div id="otp-inputs" class="flex flex-nowrap justify-center gap-1.5 sm:gap-3 mb-6" dir="ltr">
          <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="1" data-otp-index="0"
            class="otp-box flex-1 min-w-0 max-w-[3rem] h-14 sm:h-16 text-center text-xl sm:text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
          <input type="text" inputmode="numeric" maxlength="1" data-otp-index="1"
            class="otp-box flex-1 min-w-0 max-w-[3rem] h-14 sm:h-16 text-center text-xl sm:text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
          <input type="text" inputmode="numeric" maxlength="1" data-otp-index="2"
            class="otp-box flex-1 min-w-0 max-w-[3rem] h-14 sm:h-16 text-center text-xl sm:text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
          <input type="text" inputmode="numeric" maxlength="1" data-otp-index="3"
            class="otp-box flex-1 min-w-0 max-w-[3rem] h-14 sm:h-16 text-center text-xl sm:text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
          <input type="text" inputmode="numeric" maxlength="1" data-otp-index="4"
            class="otp-box flex-1 min-w-0 max-w-[3rem] h-14 sm:h-16 text-center text-xl sm:text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
          <input type="text" inputmode="numeric" maxlength="1" data-otp-index="5"
            class="otp-box flex-1 min-w-0 max-w-[3rem] h-14 sm:h-16 text-center text-xl sm:text-2xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
        </div>

        <button type="submit" id="verify-submit-btn"
          class="bg-brand-400 hover:bg-brand-300 text-white px-8 py-3 rounded-lg font-bold flex items-center shadow-md transition-colors w-full justify-center disabled:opacity-60">
          Verify &amp; Create Account
          <i data-lucide="check-circle-2" class="w-5 h-5 ml-2"></i>
        </button>
      </form>

      <div class="mt-5 text-center text-sm text-gray-600">
        Didn't get the code?
        <button id="verify-resend-btn" type="button"
          class="font-medium text-brand-400 hover:text-brand-300 disabled:text-gray-400 disabled:no-underline">
          Resend code
        </button>
      </div>
    </div>
    <div class="mt-6 text-center">
      <button onclick="switchView('view-signup')" class="text-base font-semibold text-gray-600 hover:text-brand-400">
        &larr; Back to sign up
      </button>
    </div>
  </div>
</div>
