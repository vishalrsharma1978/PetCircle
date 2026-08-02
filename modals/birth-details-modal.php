<div id="birth-details-modal"
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-50 hidden flex-col items-center justify-center p-4">
    <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md flex flex-col">
      <div
        class="bg-gray-50 dark:bg-gray-800 px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between rounded-t-2xl">
        <h2 class="text-lg font-bold text-gray-800 dark:text-white flex items-center">
          <i data-lucide="clock" class="w-5 h-5 mr-2 text-brand-500"></i>
          Edit Gotcha Day
        </h2>
        <button type="button" onclick="closeBirthDetailsModal()"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 bg-transparent hover:bg-gray-100 dark:hover:bg-gray-700 p-1.5 rounded-full transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="p-6">
        <form id="birth-details-form" onsubmit="saveBirthDetails(event)" class="space-y-5">
          <input type="hidden" id="birth-modal-member-id" />

          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Date of Birth</label>
            <input type="date" id="birth-modal-dob" required
              class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-gray-900 dark:text-white transition-all shadow-sm">
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Time of Birth</label>
            <input type="time" id="birth-modal-time" required
              class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-gray-900 dark:text-white transition-all shadow-sm">
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">Gender</label>
            <select id="birth-modal-gender"
              class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-gray-900 dark:text-white transition-all shadow-sm">
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>

          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">City of Birth</label>
            <input type="text" id="birth-modal-city" placeholder="e.g. Mumbai, Maharashtra" required
              class="w-full px-4 py-3 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl focus:ring-2 focus:ring-brand-500 outline-none text-gray-900 dark:text-white transition-all shadow-sm">
          </div>

          <div class="pt-2 flex justify-end gap-3">
            <button type="button" onclick="closeBirthDetailsModal()"
              class="px-5 py-2.5 rounded-xl text-gray-600 dark:text-gray-300 font-semibold hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">Cancel</button>
            <button type="submit"
              class="px-5 py-2.5 bg-brand-500 hover:bg-brand-600 text-white rounded-xl font-bold shadow-brand-500/20 transition-all flex items-center gap-2">
              <i data-lucide="save" class="w-4 h-4"></i> Save Details
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>