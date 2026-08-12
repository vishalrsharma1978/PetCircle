<div id="view-forgot-password"
  class="view-section hidden min-h-screen bg-gradient-to-br from-brand-100/20 via-brand-200/10 to-brand-400/10 flex-col justify-center py-12 sm:px-6 lg:px-8">
  <div class="sm:mx-auto sm:w-full sm:max-w-md">
    <div class="flex justify-center">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl shadow-sm bg-brand-500 border border-white/70 overflow-hidden">
        <img src="assets/mascots/pawcircle-logo.svg" alt="PawCircle logo" class="w-11 h-11 object-contain">
      </div>
    </div>
    <div class="text-center mt-6 mb-8">
      <h2 class="text-3xl font-bold text-gray-900" style="font-family: 'Poppins'">
        Reset password
      </h2>
    </div>

    <!-- STEP 1: REQUEST CODE (EMAIL ENTRY) -->
    <div id="forgot-step-request" class="bg-white py-8 px-4 shadow-xl sm:rounded-2xl sm:px-10 border border-brand-200/30">
      <p class="text-sm text-gray-600 text-center mb-6">
        Enter your email address and we'll send you a 6-digit code to reset your password.
      </p>

      <div id="forgot-request-error" class="hidden mb-4 bg-red-50 border-l-4 border-brand-400 p-3 rounded text-sm text-red-700"></div>

      <form id="forgot-request-form" novalidate class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">Email address</label>
          <div class="mt-1 relative">
            <i data-lucide="mail" class="absolute left-3.5 top-3.5 w-4 h-4 text-gray-400 pointer-events-none"></i>
            <input type="email" id="forgot-email" required
              class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-300 text-sm"
              placeholder="you@example.com" />
          </div>
        </div>

        <button type="submit" id="forgot-request-submit-btn"
          class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-brand-400 hover:bg-brand-300 transition-colors">
          Send Verification Code
        </button>
      </form>

      <div class="mt-6 text-center">
        <button onclick="switchView('view-public-login')" class="text-sm font-medium text-gray-600 hover:text-brand-400">
          &larr; Back to sign in
        </button>
      </div>
    </div>

    <!-- STEP 2: VERIFY CODE -->
    <div id="forgot-step-verify-code" class="hidden bg-white py-8 px-4 shadow-xl sm:rounded-2xl sm:px-10 border border-brand-200/30">
      <p class="text-sm text-gray-600 text-center mb-1">
        We've sent a 6-digit code to
      </p>
      <p id="forgot-verify-email-target" class="text-sm font-semibold text-gray-900 text-center mb-6 break-all"></p>

      <div id="forgot-verify-code-error" class="hidden mb-4 bg-red-50 border-l-4 border-brand-400 p-3 rounded text-sm text-red-700"></div>

      <form id="forgot-verify-code-form" novalidate class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Verification Code</label>
          <div id="forgot-otp-inputs" class="flex flex-nowrap justify-center gap-1.5 sm:gap-3 mb-6" dir="ltr">
            <input type="text" inputmode="numeric" maxlength="1" data-forgot-otp-index="0"
              class="forgot-otp-box w-10 sm:w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
            <input type="text" inputmode="numeric" maxlength="1" data-forgot-otp-index="1"
              class="forgot-otp-box w-10 sm:w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
            <input type="text" inputmode="numeric" maxlength="1" data-forgot-otp-index="2"
              class="forgot-otp-box w-10 sm:w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
            <input type="text" inputmode="numeric" maxlength="1" data-forgot-otp-index="3"
              class="forgot-otp-box w-10 sm:w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
            <input type="text" inputmode="numeric" maxlength="1" data-forgot-otp-index="4"
              class="forgot-otp-box w-10 sm:w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
            <input type="text" inputmode="numeric" maxlength="1" data-forgot-otp-index="5"
              class="forgot-otp-box w-10 sm:w-12 h-12 text-center text-xl font-bold border-2 border-gray-300 rounded-xl focus:border-brand-400 focus:ring-2 focus:ring-brand-200 outline-none transition-all" />
          </div>
        </div>

        <button type="submit" id="forgot-verify-code-submit-btn"
          class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-brand-400 hover:bg-brand-300 transition-colors">
          Verify Code
        </button>
      </form>

      <div class="mt-6 text-center">
        <button type="button" onclick="showForgotStepRequest()" class="text-sm font-medium text-gray-600 hover:text-brand-400">
          &larr; Request code again
        </button>
      </div>
    </div>

    <!-- STEP 3: ENTER NEW PASSWORD -->
    <div id="forgot-step-new-password" class="hidden bg-white py-8 px-4 shadow-xl sm:rounded-2xl sm:px-10 border border-brand-200/30">
      <p class="text-sm text-gray-600 text-center mb-6">
        Code verified. Choose a strong new password for your account.
      </p>

      <div id="forgot-new-password-error" class="hidden mb-4 bg-red-50 border-l-4 border-brand-400 p-3 rounded text-sm text-red-700"></div>

      <form id="forgot-new-password-form" novalidate class="space-y-6">
        <div>
          <label class="block text-sm font-medium text-gray-700">New Password</label>
          <div class="mt-1 relative">
            <i data-lucide="lock" class="absolute left-3.5 top-3.5 w-4 h-4 text-gray-400 pointer-events-none"></i>
            <input type="password" id="forgot-new-password" required autocomplete="new-password"
              class="block w-full pl-10 pr-3 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-300 text-sm"
              placeholder="Min 10 characters" />
          </div>
        </div>

        <button type="submit" id="forgot-new-password-submit-btn"
          class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-brand-400 hover:bg-brand-300 transition-colors">
          Reset Password
        </button>
      </form>
    </div>

  </div>
</div>
