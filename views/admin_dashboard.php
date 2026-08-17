<div id="view-admin-dashboard" class="view-section hidden min-h-screen bg-[#0b0f1a] text-gray-100 flex-col p-4 sm:p-8 w-full">
  <div class="admin-dashboard-shell max-w-7xl mx-auto w-full">

    <div class="flex flex-col sm:flex-row justify-between items-center mb-8 bg-gray-900 p-6 rounded-2xl shadow-sm border border-brand-500/20">
      <div class="flex items-center space-x-4 mb-4 sm:mb-0">
        <div class="w-12 h-12 bg-brand-500/15 rounded-full flex items-center justify-center">
          <i data-lucide="shield-check" class="w-6 h-6 text-brand-300"></i>
        </div>
        <div>
          <h2 class="text-2xl font-bold text-white">PawCircle Admin</h2>
          <p class="text-sm text-gray-400">Administrator: <span id="admin-dash-email"></span></p>
        </div>
      </div>
      <div class="flex flex-wrap items-center justify-end gap-3">
        <button type="button" onclick="exitAdminModeAndReturn()"
          class="flex items-center px-4 py-2 bg-brand-500 text-white rounded-lg text-sm font-bold hover:bg-brand-600 transition-colors shadow-sm">
          <i data-lucide="arrow-left-circle" class="w-4 h-4 mr-2"></i> Return to PawCircle
        </button>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
      <div class="bg-gray-900 p-6 rounded-xl shadow-sm border border-brand-500/20 flex items-center">
        <div class="p-3 bg-blue-500/10 rounded-lg text-blue-300 mr-4"><i data-lucide="users" class="w-6 h-6"></i></div>
        <div>
          <p class="text-sm text-gray-400 font-medium">Total Registered Users</p>
          <p id="admin-dash-users" class="text-2xl font-bold text-white">0</p>
        </div>
      </div>
      <div class="bg-gray-900 p-6 rounded-xl shadow-sm border border-brand-500/20 flex items-center">
        <div class="p-3 bg-green-500/10 rounded-lg text-green-300 mr-4"><i data-lucide="building" class="w-6 h-6"></i></div>
        <div>
          <p class="text-sm text-gray-400 font-medium">Database Status</p>
          <p class="text-xl font-bold text-green-300">Online &amp; Secure</p>
        </div>
      </div>
      <div class="bg-gray-900 p-6 rounded-xl shadow-sm border border-brand-500/20 flex items-center">
        <div class="p-3 bg-amber-500/10 rounded-lg text-amber-300 mr-4"><i data-lucide="clock" class="w-6 h-6"></i></div>
        <div>
          <p class="text-sm text-gray-400 font-medium">Admin mode expires</p>
          <p id="admin-dash-mode-expiry" class="text-xl font-bold text-white">—</p>
        </div>
      </div>
    </div>

    <div class="bg-gray-900 rounded-2xl border border-brand-500/20 p-6 shadow-sm mb-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-bold text-white">Your admin scopes</h3>
          <p class="text-sm text-gray-400">What this session can manage.</p>
        </div>
        <i data-lucide="key-round" class="w-5 h-5 text-brand-500"></i>
      </div>
      <div id="admin-dash-capabilities" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
    </div>

    <div class="admin-global-layout grid grid-cols-1 xl:grid-cols-[260px_minmax(0,1fr)] gap-6">
      <aside class="rounded-3xl border border-brand-500/20 bg-gray-900 p-4 shadow-sm xl:sticky xl:top-6 xl:self-start">
        <div class="admin-nav-list space-y-1">
          <?php
          $adminNavItems = [
            ['analytics', 'activity', 'Analytics'],
            ['users', 'users', 'Users'],
            ['verification', 'badge-check', 'Verification Requests'],
            ['contacts', 'contact', 'Contact Book'],
            ['posts', 'newspaper', 'Posts'],
            ['events', 'calendar-range', 'Events'],
            ['galleries', 'images', 'Galleries'],
            ['sessions', 'shield-ellipsis', 'Sessions'],
            ['platform', 'server-cog', 'Platform'],
            ['roles', 'key-round', 'Roles'],
            ['servers', 'server', 'Servers'],
            ['pet_types', 'paw-print', 'Pet Types'],
            ['reactions', 'smile-plus', 'Custom Reactions'],
            ['features', 'toggle-right', 'Features & Tabs'],
            ['layout', 'layout', 'Feed Layout'],
            ['ads', 'megaphone', 'Ads'],
          ];
          foreach ($adminNavItems as [$key, $icon, $label]): ?>
          <button type="button" onclick="switchAdminPanel('<?= $key ?>')" data-admin-panel="<?= $key ?>"
            class="admin-nav-btn flex w-full items-center gap-3 rounded-xl border border-transparent px-3 py-2.5 text-left text-sm font-semibold text-gray-300 hover:bg-gray-800">
            <i data-lucide="<?= $icon ?>" class="h-4 w-4 shrink-0"></i>
            <span><?= $label ?></span>
          </button>
          <?php endforeach; ?>
        </div>
      </aside>

      <section class="min-w-0 space-y-6">
        <div class="rounded-3xl border border-brand-500/20 bg-gray-900 p-6 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.2em] text-brand-300/80">Active workspace</p>
          <h3 id="admin-panel-page-title" class="mt-2 text-2xl font-black text-white">Analytics</h3>
        </div>

        <div id="admin-panel-analytics" class="admin-panel space-y-4"></div>
        <div id="admin-panel-users" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-verification" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-contacts" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-posts" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-events" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-galleries" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-sessions" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-platform" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-roles" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-servers" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-pet_types" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-reactions" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-features" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-layout" class="admin-panel hidden space-y-4"></div>
        <div id="admin-panel-ads" class="admin-panel hidden space-y-4"></div>
      </section>
    </div>
  </div>
</div>
