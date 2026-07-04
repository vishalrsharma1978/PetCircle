<div id="view-pack-tree"
    class="view-section min-h-screen bg-gray-50 dark:bg-gray-950 flex-col w-full relative overflow-auto p-8">
    <div class="max-w-7xl mx-auto w-full">
      <!-- Header -->
      <div class="flex items-center justify-between mb-8">
        <div>
          <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2" id="pack-tree-title">My Pack Tree</h2>
          <p class="text-gray-500 dark:text-gray-400" id="pack-tree-subtitle">View and manage your pack hierarchy.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="openPackMembersModal()"
            style="--no-faith-hover-bg: var(--brand-500,#e04848); --no-faith-hover-color:#fff; --no-faith-hover-border:var(--brand-500,#e04848);"
            class="no-faith-hover px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg shadow-sm font-semibold transition-colors flex items-center gap-2">
            <i data-lucide="user-plus" class="w-4 h-4"></i> Add Pet
          </button>
          <button onclick="switchView('view-social-feed')"
            class="no-faith-hover px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
            <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Feed
          </button>
        </div>
      </div>

      <!-- Tree Container with pan/zoom -->
      <div
        class="bg-white dark:bg-gray-900 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-800 min-h-[600px] relative overflow-hidden">
        <div id="ft-pan-container">
          <div id="ft-pan-stage">
            <div class="css-pack-tree" id="css-pack-tree-container">
              <!-- Tree dynamically rendered here -->
            </div>
          </div>
          <!-- Zoom controls -->
          <div class="ft-zoom-controls">
            <button class="ft-zoom-btn no-faith-hover" onclick="ftZoom(0.2)" title="Zoom in">+</button>
            <button class="ft-zoom-btn no-faith-hover" onclick="ftZoom(-0.2)" title="Zoom out">−</button>
            <button class="ft-zoom-btn no-faith-hover" onclick="ftResetView()" title="Reset"
              style="font-size:11px;">⊙</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Pack member side-panel -->
    <div id="ft-side-panel">
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-base font-bold text-gray-800 dark:text-white">Member Profile</h3>
        <button onclick="closeFTSidePanel()" class="no-faith-hover p-1 rounded-lg text-gray-400 hover:text-gray-600">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="flex flex-col items-center px-5 py-6 gap-3">
        <div id="ft-panel-avatar"
          class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-bold text-white shadow-lg"
          style="background: var(--faith-accent, #e04848)"></div>
        <h4 id="ft-panel-name" class="text-xl font-bold text-gray-900 dark:text-white text-center"></h4>
        <span id="ft-panel-relation" class="px-3 py-1 rounded-full text-xs font-bold text-white"
          style="background: var(--faith-accent, #e04848)"></span>
      </div>
      <div class="px-5 space-y-3 text-sm text-gray-600 dark:text-gray-300" id="ft-panel-details"></div>
    </div>
  </div>