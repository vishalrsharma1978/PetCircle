<div id="add-member-modal"
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm z-[150] hidden flex-col items-center justify-center p-4">
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col max-h-[80vh]">
      <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
        <h2 class="text-lg font-bold text-gray-800 dark:text-gray-200">
          Add Pets
        </h2>
        <button onclick="closeAddMemberModal()"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="overflow-y-auto p-4" id="add-member-list">
      </div>
    </div>
  </div>