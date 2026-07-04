<div id="add-event-modal" class="fixed inset-0 z-[60] flex items-center justify-center hidden p-4">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeAddEventModal()"></div>

    <!-- Modal Content -->
    <div
      class="bg-white dark:bg-gray-900 rounded-2xl shadow-2xl border border-gray-100 dark:border-gray-800 relative z-10 w-full max-w-md max-h-[90vh] transform transition-all overflow-hidden flex flex-col">
      <div class="shrink-0 flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-800">
        <div>
          <h3 id="event-modal-heading" class="text-xl font-bold text-gray-800 dark:text-white">
            Add New Event
          </h3>
          <p id="event-modal-subtitle" class="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Adding event to:
            <span id="event-modal-date-display" class="font-bold text-brand-600 dark:text-brand-400"></span>
          </p>
        </div>
        <button onclick="closeAddEventModal()"
          class="no-faith-hover p-2 rounded-lg text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>

      <div class="modal-scroll overflow-y-auto px-6 py-5 space-y-4">
        <input id="event-modal-id" type="hidden">
        <div>
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Event Title *</label>
          <input type="text" id="event-modal-title" placeholder="e.g. Pet Breed Potluck"
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
        </div>
        <div id="event-modal-date-row" class="hidden">
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Event Date *</label>
          <input type="date" id="event-modal-date"
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
        </div>
        <div>
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Event Audience</label>
          <select id="event-modal-audience" onchange="handleEventAudienceChange()"
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
            <option value="global">Global (Everyone on PawCircle)</option>
            <option value="pet_type">Pet Type (My Faith Only)</option>
            <option value="breed">Pet Breed (My Specific Caste/Sect)</option>
            <option value="all_friends">All Friends</option>
            <option value="few_friends">A few friends</option>
            <option value="invite_only">Invite only (only people I select)</option>
          </select>
          <p id="event-audience-hint"
            class="hidden text-xs text-amber-600 dark:text-amber-400 mt-1.5 flex items-center gap-1">
            <i data-lucide="lock" class="w-3.5 h-3.5"></i>
            Only the members & groups you select below will be able to see and connect to this event.
          </p>
        </div>
        <div>
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Event Type *</label>
          <select id="event-modal-type"
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
            <option value="online">Online</option>
            <option value="in_person">In Person</option>
          </select>
        </div>
        <div>
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Time (Optional)</label>
          <input type="time" id="event-modal-time"
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
        </div>
        <div>
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Description (Optional)</label>
          <textarea id="event-modal-desc" rows="3" placeholder="Add some details..."
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500"></textarea>
        </div>
        <div>
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Meeting Link (Optional)</label>
          <input type="url" id="event-modal-link" placeholder="https://zoom.us/j/123456789"
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
        </div>
        <div class="rounded-xl border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 p-3">
          <div class="flex items-start justify-between gap-3">
            <div>
              <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300">Linked Gallery (Optional)</label>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Create the event first, then attach a gallery
                immediately or link one from gallery creation.</p>
              <label class="mt-3 inline-flex items-center gap-2 text-xs font-bold text-gray-600 dark:text-gray-300">
                <input id="event-modal-create-gallery" type="checkbox"
                  class="rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                Open linked gallery creation after saving
              </label>
            </div>
            <button type="button"
              onclick="switchSocialTab('settings'); switchAccountSettingsSection('galleries'); closeAddEventModal();"
              class="no-faith-hover shrink-0 inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-gray-900 text-white dark:bg-gray-700 dark:text-gray-100 text-xs font-bold">
              <i data-lucide="images" class="w-3.5 h-3.5"></i> Manage
            </button>
          </div>
        </div>
        <div>
          <div class="flex items-center justify-between gap-3 mb-1">
            <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300">Invite friends &amp; broadcast to
              groups (Optional)</label>
            <span id="event-invite-summary" class="text-xs text-gray-500 dark:text-gray-400">0 selected</span>
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400 mb-2 flex items-center gap-1">
            <i data-lucide="megaphone" class="w-3.5 h-3.5"></i>
            Pick the <b>Groups</b> tab to broadcast this event to every member of the groups you select.
          </p>
          <input type="hidden" id="event-modal-invite" />
          <input type="hidden" id="event-modal-invite-groups" />
          <div class="flex gap-2 mb-2">
            <button type="button" id="event-invite-tab-friends" onclick="switchEventInviteTab('friends')"
              class="px-3 py-1 rounded-full text-xs font-bold bg-brand-50 text-brand-600 dark:bg-brand-900/40 dark:text-brand-300 transition-colors">Friends</button>
            <button type="button" id="event-invite-tab-groups" onclick="switchEventInviteTab('groups')"
              class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"><i
                data-lucide="megaphone" class="w-3 h-3 inline -mt-0.5"></i> Broadcast to Groups</button>
          </div>
          <div id="event-invite-friend-list"
            class="modal-scroll max-h-40 overflow-y-auto grid gap-2 rounded-xl bg-gray-50 dark:bg-gray-800/60 p-2 border border-gray-100 dark:border-gray-700">
          </div>
          <div id="event-invite-group-list"
            class="modal-scroll max-h-40 overflow-y-auto hidden grid gap-2 rounded-xl bg-gray-50 dark:bg-gray-800/60 p-2 border border-gray-100 dark:border-gray-700">
          </div>
        </div>

        <!-- Recurring frequency -->
        <div>
          <label class="block text-base font-bold shadow-md text-gray-700 dark:text-gray-300 mb-1">Recurrence</label>
          <select id="event-modal-frequency"
            class="w-full px-4 py-2 border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white rounded-lg focus:outline-none focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
            <option value="none">One-time (No Repeat)</option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
          </select>
        </div>

        <!-- QR Ticket + Calendar Sync -->
        <div class="flex gap-2">
          <button type="button" onclick="openEventQRModal()"
            class="no-faith-hover flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <i data-lucide="qr-code" class="w-4 h-4"></i> QR Ticket
          </button>
          <button type="button" onclick="downloadEventICS()"
            class="no-faith-hover flex-1 flex items-center justify-center gap-2 px-3 py-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-xs font-bold text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
            <i data-lucide="calendar-plus" class="w-4 h-4"></i> Add to Calendar
          </button>
        </div>
      </div>

      <div
        class="shrink-0 px-6 py-4 border-t border-gray-100 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 flex justify-end gap-3">
        <button onclick="closeAddEventModal()"
          class="px-5 py-2 text-gray-600 dark:text-gray-300 font-semibold hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
          Cancel
        </button>
        <button id="event-modal-submit-btn" onclick="saveEventFromModal()"
          class="px-5 py-2 bg-brand-500 hover:bg-brand-600 text-white font-bold rounded-lg shadow transition-colors">
          Save Event
        </button>
      </div>
    </div>
  </div>