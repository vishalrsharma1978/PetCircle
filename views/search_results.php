<div id="social-tab-search-results" class="social-tab-panel hidden w-full">

      <div class="mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div class="flex items-center gap-4">
          <button onclick="switchSocialTab('feed'); document.getElementById('global-search-input').value = '';"
            class="w-10 h-10 flex-shrink-0 flex items-center justify-center rounded-full bg-white dark:bg-gray-800 shadow-sm border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors text-gray-600 dark:text-gray-300"
            title="Back to Home">
            <i data-lucide="arrow-left" class="w-5 h-5"></i>
          </button>
          <div>
            <h2 class="text-3xl font-bold text-gray-900 dark:text-white font-serif">Search Results</h2>
            <p class="text-gray-500 dark:text-gray-400 mt-1" id="search-results-meta">Searching...</p>
          </div>
        </div>
      </div>



      <!-- Navigation Tabs -->
      <div class="flex overflow-x-auto gap-6 pb-0 mb-8 border-b border-gray-200 dark:border-gray-800 scrollbar-hide">
        <button onclick="switchSearchTab('all')" id="search-tab-all"
          class="pb-3 px-1 text-sm font-medium border-b-2 transition-colors border-brand-600 text-brand-600 dark:text-brand-400 dark:border-brand-400 whitespace-nowrap">All</button>
        <button onclick="switchSearchTab('pets')" id="search-tab-pets"
          class="pb-3 px-1 text-sm font-medium border-b-2 border-transparent transition-colors text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600 whitespace-nowrap">Pets</button>
        <button onclick="switchSearchTab('people')" id="search-tab-people"
          class="pb-3 px-1 text-sm font-medium border-b-2 border-transparent transition-colors text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600 whitespace-nowrap">People</button>
        <button onclick="switchSearchTab('connections')" id="search-tab-connections"
          class="pb-3 px-1 text-sm font-medium border-b-2 border-transparent transition-colors text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600 whitespace-nowrap">Connections</button>
        <button onclick="switchSearchTab('posts')" id="search-tab-posts"
          class="pb-3 px-1 text-sm font-medium border-b-2 border-transparent transition-colors text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600 whitespace-nowrap">Posts</button>
        <button onclick="switchSearchTab('events')" id="search-tab-events"
          class="pb-3 px-1 text-sm font-medium border-b-2 border-transparent transition-colors text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300 dark:hover:border-gray-600 whitespace-nowrap">Events</button>
      </div>

      <!-- Tab Content Containers -->
      <div id="search-content-all" class="search-tab-content space-y-12 block"></div>
      <div id="search-content-pets" class="search-tab-content hidden grid grid-cols-1 md:grid-cols-2 gap-6"></div>
      <div id="search-content-people"
        class="search-tab-content hidden grid grid-cols-1 md:grid-cols-2 gap-6"></div>
      <div id="search-content-connections"
        class="search-tab-content hidden grid grid-cols-1 md:grid-cols-2 gap-6"></div>
      <div id="search-content-posts" class="search-tab-content hidden space-y-6 max-w-2xl mx-auto"></div>
      <div id="search-content-events" class="search-tab-content hidden space-y-6"></div>

</div>