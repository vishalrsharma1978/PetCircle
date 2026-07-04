<div id="admin-server-modal" class="fixed inset-0 z-[13000] hidden items-center justify-center bg-gray-950/80 backdrop-blur-sm p-4">
          <div class="w-full max-w-lg rounded-3xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-6 shadow-2xl relative animate-in fade-in zoom-in-95 duration-200">
            <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-800 pb-4 mb-4">
              <h3 id="admin-server-modal-title" class="text-xl font-bold text-gray-900 dark:text-white">Create Server Node</h3>
              <button type="button" onclick="closeServerModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-white">
                <i data-lucide="x" class="w-5 h-5"></i>
              </button>
            </div>
            <form id="admin-server-form" onsubmit="saveServerNode(event)" class="space-y-4">
              <input type="hidden" id="admin-server-id" value="" />
              <div>
                <label class="block text-xs font-black uppercase text-gray-400 mb-1">Server Name *</label>
                <input type="text" id="admin-server-name" required placeholder="e.g. AP-South (Mumbai)" class="w-full rounded-xl border border-gray-250 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-2 text-sm focus:border-brand-500 focus:outline-none dark:text-white" />
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-black uppercase text-gray-400 mb-1">Host/IP *</label>
                  <input type="text" id="admin-server-host" required placeholder="e.g. 13.233.102.5" class="w-full rounded-xl border border-gray-250 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-2 text-sm focus:border-brand-500 focus:outline-none dark:text-white" />
                </div>
                <div>
                  <label class="block text-xs font-black uppercase text-gray-400 mb-1">Port *</label>
                  <input type="number" id="admin-server-port" required placeholder="80" value="80" class="w-full rounded-xl border border-gray-250 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-2 text-sm focus:border-brand-500 focus:outline-none dark:text-white" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-black uppercase text-gray-400 mb-1">Latitude (-90 to 90) *</label>
                  <input type="number" step="any" min="-90" max="90" id="admin-server-latitude" required placeholder="19.076" class="w-full rounded-xl border border-gray-250 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-2 text-sm focus:border-brand-500 focus:outline-none dark:text-white" />
                </div>
                <div>
                  <label class="block text-xs font-black uppercase text-gray-400 mb-1">Longitude (-180 to 180) *</label>
                  <input type="number" step="any" min="-180" max="180" id="admin-server-longitude" required placeholder="72.877" class="w-full rounded-xl border border-gray-250 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-2 text-sm focus:border-brand-500 focus:outline-none dark:text-white" />
                </div>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-black uppercase text-gray-400 mb-1">Pet Type Scope *</label>
                  <select id="admin-server-pet_type" required class="w-full rounded-xl border border-gray-250 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-2 text-sm focus:border-brand-500 focus:outline-none dark:text-white">
                    <option value="global">Global (No Scope)</option>
                    <option value="hinduism">Hinduism</option>
                    <option value="islam">Islam</option>
                    <option value="christianity">Christianity</option>
                    <option value="sikhism">Sikhism</option>
                    <option value="jainism">Jainism</option>
                    <option value="buddhism">Buddhism</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-black uppercase text-gray-400 mb-1">Status</label>
                  <select id="admin-server-status" required class="w-full rounded-xl border border-gray-250 dark:border-gray-800 bg-gray-50 dark:bg-gray-950 px-4 py-2 text-sm focus:border-brand-500 focus:outline-none dark:text-white">
                    <option value="online">Online</option>
                    <option value="offline">Offline</option>
                  </select>
                </div>
              </div>
              <div class="flex justify-end gap-3 border-t border-gray-100 dark:border-gray-800 pt-4 mt-6">
                <button type="button" onclick="closeServerModal()" class="rounded-xl border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-850 px-4 py-2 text-base font-bold shadow-md">Cancel</button>
                <button type="submit" class="rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-5 py-2">Save Node</button>
              </div>
            </form>
          </div>
        </div>