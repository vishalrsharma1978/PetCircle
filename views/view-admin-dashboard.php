<div id="view-admin-dashboard"
    class="view-section min-h-screen bg-[#070b14] text-gray-100 flex-col p-4 sm:p-8 w-full">
    <div class="admin-dashboard-shell max-w-7xl mx-auto w-full">
      <div
        class="admin-topbar flex flex-col sm:flex-row justify-between items-center mb-8 bg-gray-900 p-6 rounded-2xl shadow-sm border border-orange-500/20">
        <div class="flex items-center space-x-4 mb-4 sm:mb-0">
          <div class="w-12 h-12 bg-orange-500/15 rounded-full flex items-center justify-center">
            <i data-lucide="shield-check" class="w-6 h-6 text-orange-300"></i>
          </div>
          <div>
            <h2 class="text-2xl font-bold text-white">
              Admin Control Center
            </h2>
            <p class="text-sm text-gray-400">
              Administrator: <span id="admin-dash-email"></span>
            </p>
          </div>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3">
          <button type="button" onclick="returnToPetCircleFromAdmin()"
            title="Leave admin mode and return to the main PawCircle feed"
            class="flex items-center px-4 py-2 bg-orange-500 text-white rounded-lg text-base font-bold hover:bg-orange-600 transition-colors shadow-sm">
            <i data-lucide="arrow-left-circle" class="w-4 h-4 mr-2"></i> Return to PawCircle
          </button>
          <button onclick="logout()"
            class="flex items-center px-4 py-2 border border-slate-200 dark:border-gray-700 rounded-lg text-base font-semibold text-slate-700 dark:text-gray-200 hover:bg-orange-50 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="log-out" class="w-4 h-4 mr-2"></i> Sign Out
          </button>
        </div>
      </div>
      <div class="admin-overview-grid grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gray-900 p-6 rounded-xl shadow-sm border border-orange-500/20 flex items-center">
          <div class="p-3 bg-blue-500/10 rounded-lg text-blue-300 mr-4">
            <i data-lucide="users" class="w-6 h-6"></i>
          </div>
          <div>
            <p class="text-sm text-gray-400 font-medium">
              Total Registered Users
            </p>
            <p id="admin-dash-users" class="text-2xl font-bold text-white">
              0
            </p>
          </div>
        </div>
        <div class="bg-gray-900 p-6 rounded-xl shadow-sm border border-orange-500/20 flex items-center">
          <div class="p-3 bg-green-500/10 rounded-lg text-green-300 mr-4">
            <i data-lucide="building" class="w-6 h-6"></i>
          </div>
          <div>
            <p class="text-sm text-gray-400 font-medium">Database Status</p>
            <p class="text-xl font-bold text-green-300">Online & Secure</p>
          </div>
        </div>
      </div>
      <div class="bg-gray-900 rounded-2xl border border-orange-500/20 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-bold text-white">Assigned admin scopes</h3>
            <p class="text-sm text-gray-400">These roles control what future admin tools can manage.</p>
          </div>
          <i data-lucide="key-round" class="w-5 h-5 text-brand-500"></i>
        </div>
        <div id="admin-dash-capabilities" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
      </div>
      <div id="admin-global-console" class="hidden mt-6">
        <div class="admin-global-layout grid grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)] gap-6">
          <aside
            class="admin-sidebar rounded-3xl border border-orange-500/20 bg-gray-900 p-5 shadow-sm xl:sticky xl:top-6 xl:self-start">
            <div class="admin-sidebar-intro mb-5">
              <p class="text-xs font-black uppercase tracking-[0.24em] text-orange-300/80">Global navigation</p>
              <h3 class="mt-2 text-2xl font-black text-white">Admin dashboard</h3>
              <p class="mt-2 text-sm text-gray-400">Move between platform-wide control pages. Each section loads its own
                workspace and control surface.</p>
            </div>
            <div class="admin-nav-list space-y-2">
              <button type="button" onclick="switchAdminPanel('analytics')" data-admin-panel="analytics"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="activity" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Analytics</span>
                  <span class="mt-1 block text-xs text-gray-400">Traffic, risk and system overview</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('users')" data-admin-panel="users"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="users" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Users</span>
                  <span class="mt-1 block text-xs text-gray-400">Moderation, search, flags and profiles</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('contacts')" data-admin-panel="contacts"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="contact" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Contact Book</span>
                  <span class="mt-1 block text-xs text-gray-400">Pet Breed-wise member directory & export</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('posts')" data-admin-panel="posts"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="newspaper" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Posts</span>
                  <span class="mt-1 block text-xs text-gray-400">Content review and takedown controls</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('events')" data-admin-panel="events"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="calendar-range" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Events</span>
                  <span class="mt-1 block text-xs text-gray-400">Event ownership, cleanup and review</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('galleries')" data-admin-panel="galleries"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="images" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Galleries</span>
                  <span class="mt-1 block text-xs text-gray-400">Collections, linked media and storage review</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('sessions')" data-admin-panel="sessions"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="shield-ellipsis" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Sessions</span>
                  <span class="mt-1 block text-xs text-gray-400">Live access, revocation and device activity</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('platform')" data-admin-panel="platform"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="server-cog" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Platform</span>
                  <span class="mt-1 block text-xs text-gray-400">Operational guidance and infra direction</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('roles')" data-admin-panel="roles"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="key-round" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Roles</span>
                  <span class="mt-1 block text-xs text-gray-400">Roster, scope review and authority control</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('servers')" data-admin-panel="servers"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="server" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Servers</span>
                  <span class="mt-1 block text-xs text-gray-400">System nodes, location globe & health status</span>
                </span>
              </button>
              <button type="button" onclick="switchAdminPanel('verifications')" data-admin-panel="verifications"
                class="admin-nav-btn flex w-full items-start gap-3 rounded-2xl border px-4 py-3 text-left">
                <i data-lucide="badge-check" class="mt-0.5 h-5 w-5"></i>
                <span class="min-w-0">
                  <span class="block text-sm font-black">Verifications</span>
                  <span class="mt-1 block text-xs text-gray-400">Review user profile verification requests</span>
                </span>
              </button>
            </div>
          </aside>

          <section class="admin-workspace min-w-0 space-y-6">
            <div class="admin-workspace-header rounded-3xl border border-orange-500/20 bg-gray-900 p-6 shadow-sm">
              <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.24em] text-orange-300/80">Active workspace</p>
                  <h3 id="admin-panel-page-title" class="mt-2 text-3xl font-black text-white">Analytics</h3>
                  <p id="admin-panel-page-subtitle" class="mt-2 max-w-3xl text-sm text-gray-400">Platform-wide health,
                    moderation load, activity trends and operational signals.</p>
                </div>
                <div id="admin-panel-page-badges" class="flex flex-wrap gap-2"></div>
              </div>
            </div>

            <div id="admin-panel-analytics" class="admin-panel space-y-4"></div>
            <div id="admin-panel-users" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-contacts" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-posts" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-events" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-galleries" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-sessions" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-platform" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-roles" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-servers" class="admin-panel hidden space-y-4"></div>
            <div id="admin-panel-verifications" class="admin-panel hidden space-y-4"></div>
          </section>
        </div>
      </div>
      <div id="admin-role-management"
        class="hidden mt-6 bg-gray-900 rounded-2xl border border-orange-500/20 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-4">
          <div>
            <h3 class="text-lg font-bold text-gray-900 dark:text-white">Admin roster</h3>
            <p class="text-sm text-gray-500 dark:text-gray-400">Current admins only. Add new admins from the users table
              or a user's detail view.</p>
          </div>
          <i data-lucide="user-cog" class="w-5 h-5 text-brand-500"></i>
        </div>
        <div class="mb-4 rounded-2xl border border-orange-500/20 bg-orange-500/5 p-4 text-sm text-orange-100">
          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <p class="font-bold text-white">Adding admins has moved</p>
              <p class="mt-1 text-orange-100/80">Open a user from the Users panel to assign platform, pet_type,
                breed or owner access with the correct scope.</p>
            </div>
            <button type="button" onclick="switchAdminPanel('users')"
              class="rounded-xl border border-orange-400/40 px-4 py-2 text-base font-bold text-orange-100 hover:bg-orange-500/10">Open
              users table</button>
          </div>
        </div>
        <div id="admin-role-list" class="space-y-2 text-sm text-gray-600 dark:text-gray-300"></div>
      </div>
    </div>
  </div>