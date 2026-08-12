<div id="view-pack-tree"
  class="view-section hidden min-h-screen bg-gray-50 dark:bg-gray-950 flex-col w-full relative overflow-auto p-8">
  <div class="max-w-7xl mx-auto w-full">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h2 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">My Pack Tree</h2>
        <p class="text-gray-500 dark:text-gray-400">Trace your pet's lineage, littermates, and pack network.</p>
      </div>
      <div class="flex items-center gap-2">
        <!-- List / Tree view toggle -->
        <div class="flex bg-gray-100 dark:bg-gray-800 rounded-lg p-1 shrink-0">
          <button id="pack-view-tree" onclick="switchPackTreeView('tree')"
            class="no-accent-hover px-3 py-1.5 rounded-md text-sm font-semibold transition-colors flex items-center gap-1.5" title="Tree view">
            <i data-lucide="git-fork" class="w-4 h-4"></i> Tree
          </button>
          <button id="pack-view-list" onclick="switchPackTreeView('list')"
            class="no-accent-hover px-3 py-1.5 rounded-md text-sm font-semibold transition-colors flex items-center gap-1.5" title="List view">
            <i data-lucide="list" class="w-4 h-4"></i> List
          </button>
        </div>
        <button onclick="openPackMembersModal()"
          style="--no-accent-hover-bg: var(--brand-500,#e04848); --no-accent-hover-color:#fff; --no-accent-hover-border:var(--brand-500,#e04848);"
          class="no-accent-hover px-4 py-2 bg-brand-500 hover:bg-brand-600 text-white rounded-lg shadow-sm font-semibold transition-colors flex items-center gap-2">
          <i data-lucide="user-plus" class="w-4 h-4"></i> Add Pack Member
        </button>
        <button onclick="switchView('view-social-feed'); switchSocialTab('feed');"
          class="no-accent-hover px-4 py-2 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm text-gray-700 dark:text-gray-200 font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors flex items-center gap-2">
          <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Feed
        </button>
      </div>
    </div>

    <!-- Tree Container with pan/zoom -->
    <div id="pack-tree-view"
      class="bg-white dark:bg-gray-900 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-800 min-h-[600px] relative overflow-hidden">
      <div id="pack-pan-container">
        <div id="pack-pan-stage">
          <div class="css-pack-tree" id="css-pack-tree-container">
            <!-- Tree dynamically rendered here -->
          </div>
        </div>
        <!-- Zoom controls -->
        <div class="pack-zoom-controls">
          <button class="pack-zoom-btn no-accent-hover" onclick="packZoom(0.2)" title="Zoom in">+</button>
          <button class="pack-zoom-btn no-accent-hover" onclick="packZoom(-0.2)" title="Zoom out">−</button>
          <button class="pack-zoom-btn no-accent-hover" onclick="packResetView()" title="Reset" style="font-size:11px;">⊙</button>
        </div>
      </div>
    </div>

    <!-- List view (toggle) -->
    <div id="pack-list-view"
      class="hidden bg-white dark:bg-gray-900 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-800 min-h-[600px] p-6">
      <div id="pack-list-container"></div>
    </div>
  </div>

  <!-- Pack member side-panel -->
  <div id="pack-side-panel">
    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-800">
      <h3 class="text-base font-bold text-gray-800 dark:text-white">Pack Member Profile</h3>
      <button onclick="closePackSidePanel()" class="no-accent-hover p-1 rounded-lg text-gray-400 hover:text-gray-600">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="flex flex-col items-center px-5 py-6 gap-3">
      <div id="pack-panel-avatar"
        class="w-20 h-20 rounded-full flex items-center justify-center text-2xl font-bold text-white shadow-lg overflow-hidden"
        style="background: var(--brand-500, #e04848)"></div>
      <h4 id="pack-panel-name" class="text-xl font-bold text-gray-900 dark:text-white text-center"></h4>
      <span id="pack-panel-relation" class="px-3 py-1 rounded-full text-xs font-bold text-white"
        style="background: var(--brand-500, #e04848)"></span>
    </div>
    <div class="px-5 space-y-3 text-sm text-gray-600 dark:text-gray-300" id="pack-panel-details"></div>
    <div class="px-5 pb-6 mt-4">
      <button id="pack-panel-delete-btn" onclick="deleteCurrentPackMember()"
        class="w-full py-2 rounded-lg text-sm font-bold text-red-600 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors">
        Remove from pack tree
      </button>
    </div>
  </div>
</div>
