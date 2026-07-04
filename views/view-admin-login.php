<div id="view-admin-login"
    class="view-section min-h-screen bg-gray-900 flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
      <div class="flex justify-center">
        <div class="w-16 h-16 bg-gray-800 border border-gray-700 rounded-xl shadow-lg flex items-center justify-center">
          <i data-lucide="shield-check" class="w-8 h-8 text-brand-100"></i>
        </div>
      </div>
      <h2 class="mt-6 text-center text-3xl font-bold text-white" style="font-family: &quot;DM Serif Display&quot;">
        Admin Portal
      </h2>
      <p class="mt-2 text-center text-sm text-gray-400">
        Authorized personnel only.
      </p>
    </div>
    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
      <div class="bg-gray-800 py-8 px-4 shadow-xl sm:rounded-2xl sm:px-10 border border-gray-700">
        <div id="admin-error"
          class="hidden mb-4 bg-red-900/50 border-l-4 border-red-500 p-3 rounded text-sm text-red-200"></div>
        <form id="admin-login-form" autocomplete="off" class="space-y-6">
          <div>
            <label class="block text-base font-semibold text-gray-300">Admin Email</label>
            <div class="mt-1 input-icon-wrap">
              <i data-lucide="mail" class="icon h-4 w-4 text-gray-500" style="color: #6b7280"></i>
              <input type="email" id="admin-email" name="admin_email" required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"
                class="block w-full pl-10 pr-3 py-3 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-100 text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-base font-semibold text-gray-300">Password</label>
            <div class="mt-1 input-icon-wrap">
              <i data-lucide="lock" class="icon h-4 w-4" style="color: #6b7280"></i>
              <input type="password" id="admin-password" name="admin_password" required autocomplete="new-password"
                class="block w-full pl-10 pr-3 py-3 border border-gray-600 bg-gray-700 text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-brand-100 text-sm" />
            </div>
          </div>
          <button type="submit" id="admin-submit-btn"
            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-gray-900 bg-brand-100 hover:bg-white transition-all">
            Secure Sign In
          </button>
        </form>
      </div>
      <div class="mt-6 text-center">
        <button onclick="switchView('view-public-login')"
          class="text-base font-semibold text-gray-400 hover:text-white flex items-center justify-center w-full">
          <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back to Public
          Portal
        </button>
      </div>
    </div>
  </div>