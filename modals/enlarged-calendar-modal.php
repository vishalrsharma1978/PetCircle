<div id="enlarged-calendar-modal" class="fixed inset-0 z-50 flex items-center justify-center hidden">
    <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" onclick="closeEnlargedCalendarModal()"></div>
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800 relative z-10 w-full max-w-6xl h-[90vh] flex flex-col transform transition-all m-4">
      <div class="flex items-center justify-between p-6 border-b border-gray-100 dark:border-gray-800 shrink-0">
        <div class="flex items-center gap-4">
          <h3 class="text-3xl font-extrabold text-gray-800 dark:text-white" id="enlarged-calendar-title">
            Calendar
          </h3>
          <div class="flex gap-2">
            <button onclick="
                  currentCalendarViewDate.setMonth(
                    currentCalendarViewDate.getMonth() - 1,
                  );
                  renderEnlargedCalendar();
                  renderCalendar();
                "
              class="p-2 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
              <i data-lucide="chevron-left" class="w-5 h-5"></i>
            </button>
            <button onclick="
                  currentCalendarViewDate.setMonth(
                    currentCalendarViewDate.getMonth() + 1,
                  );
                  renderEnlargedCalendar();
                  renderCalendar();
                "
              class="p-2 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
              <i data-lucide="chevron-right" class="w-5 h-5"></i>
            </button>
          </div>
        </div>
        <button onclick="closeEnlargedCalendarModal()"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
          <i data-lucide="x" class="w-6 h-6"></i>
        </button>
      </div>

      <div class="flex-1 overflow-y-auto overflow-x-auto p-6 bg-gray-50 dark:bg-gray-900/50 no-scrollbar">
        <div class="min-w-[800px]">
          <div
            class="grid grid-cols-7 text-base font-bold shadow-md text-gray-500 dark:text-gray-400 text-center mb-2 uppercase tracking-wider">
            <div>Sunday</div>
            <div>Monday</div>
            <div>Tuesday</div>
            <div>Wednesday</div>
            <div>Thursday</div>
            <div>Friday</div>
            <div>Saturday</div>
          </div>
          <div id="enlarged-calendar-grid"
            class="grid grid-cols-7 border-t border-l border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 rounded shadow-sm">
            <!-- Injected by renderEnlargedCalendar -->
          </div>
        </div>
      </div>
    </div>
  </div>