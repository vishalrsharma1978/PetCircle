<div id="view-content-hub" class="view-section min-h-screen flex-col w-full">
    <!-- Header bar (theme-aware, faith-accented) -->
    <div class="px-6 py-4 flex items-center justify-between sticky top-0 z-50 backdrop-blur-sm"
      style="background: color-mix(in srgb, var(--faith-accent,#e04848) 12%, var(--ch-bg)); border-bottom: 1px solid var(--ch-border);">
      <div class="flex items-center gap-3">
        <span class="w-10 h-10 rounded-xl flex items-center justify-center text-white shrink-0"
          style="background: var(--faith-accent,#e04848)">
          <i data-lucide="tv-2" class="w-5 h-5"></i>
        </span>
        <div>
          <h2 class="font-bold text-lg leading-tight" style="font-family:'Poppins',sans-serif; color: var(--ch-text)">
            PawCircle</h2>
          <p class="text-xs" id="ch-subtitle" style="color: var(--ch-subtext)">Discourses, kirtans &amp; spiritual
            content</p>
        </div>
      </div>
      <button onclick="switchView('view-social-feed')"
        class="no-faith-hover flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
        style="background: var(--faith-accent,#e04848); color:#fff;">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back
      </button>
    </div>

    <div class="px-6 py-6 space-y-10 min-h-screen" style="background: var(--ch-bg)">

      <!-- Hero banner -->
      <div id="ch-hero" class="ch-hero">
        <span class="ch-hero-eyebrow"><i data-lucide="sparkles" class="w-3 h-3"></i> Spiritual Streaming</span>
        <h2 class="mt-4 text-3xl sm:text-4xl font-black leading-tight" style="font-family:'DM Serif Display', serif;">
          Nourish your soul, <span id="ch-hero-rel">every day</span>.
        </h2>
        <p class="mt-2 text-sm sm:text-base max-w-xl" style="color: rgba(255,255,255,.85)">
          Stream discourses, kirtans, bhajans and sacred recitations curated for your breed — anytime, anywhere.
        </p>
        <div class="mt-5 flex flex-wrap items-center gap-3">
          <button id="ch-hero-cta" onclick="chPlayFeatured()"
            class="no-faith-hover inline-flex items-center gap-2 px-5 py-2.5 rounded-full bg-white text-gray-900 text-base font-bold shadow-lg hover:scale-[1.03] transition-transform">
            <i data-lucide="play" class="w-4 h-4 fill-current"></i> Start watching
          </button>
          <span class="inline-flex items-center gap-2 text-xs font-semibold" style="color: rgba(255,255,255,.85)">
            <i data-lucide="library" class="w-4 h-4"></i> <span id="ch-hero-count">0</span> pieces of content
          </span>
        </div>
      </div>

      <!-- Active player -->
      <div id="ch-player-section" class="max-w-3xl mx-auto hidden">
        <div class="ch-player-wrap mb-3">
          <!-- YouTube live embed -->
          <div id="ch-yt-wrap" style="display:none; position:relative; width:100%; aspect-ratio:16/9; background:#000;">
            <iframe id="ch-yt-player" class="absolute inset-0 w-full h-full" style="border:0;"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
              allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
          </div>
          <video id="ch-video-player" controls preload="metadata" class="w-full max-h-[360px] bg-black"
            style="display:none"></video>
          <audio id="ch-audio-player" controls preload="metadata" class="w-full" style="display:none"></audio>
          <!-- Custom controls overlay for video -->
          <div class="ch-player-controls" id="ch-custom-controls" style="display:none">
            <button class="ch-ctrl-btn" id="ch-play-btn" onclick="chTogglePlay()">
              <svg viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z" />
              </svg>
            </button>
            <div class="ch-progress" id="ch-progress-bar" onclick="chSeek(event)">
              <div class="ch-progress-fill" id="ch-progress-fill"></div>
            </div>
            <span class="ch-time" id="ch-time-display">0:00 / 0:00</span>
            <input type="range" class="ch-vol" id="ch-vol-slider" min="0" max="1" step="0.05" value="1"
              oninput="chSetVolume(this.value)">
            <button class="ch-ctrl-btn" onclick="chToggleMute()">
              <svg viewBox="0 0 24 24" id="ch-mute-icon">
                <path
                  d="M3 9v6h4l5 5V4L7 9H3zm13.5 3A4.5 4.5 0 0 0 14 7.97v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z" />
              </svg>
            </button>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <span id="ch-live-badge"
            class="hidden items-center gap-1.5 px-2 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-black uppercase tracking-wide">
            <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span> Live
          </span>
          <h3 class="font-bold text-base" id="ch-now-playing-title" style="color: var(--ch-text)"></h3>
        </div>
        <p class="text-sm mt-0.5" id="ch-now-playing-sub" style="color: var(--ch-subtext)"></p>
      </div>

      <!-- Empty state when the user's pet_type has no content -->
      <div id="ch-empty-state" class="hidden max-w-md mx-auto text-center py-16">
        <span class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white"
          style="background: var(--faith-accent,#e04848)">
          <i data-lucide="tv-2" class="w-7 h-7"></i>
        </span>
        <h3 class="font-bold text-lg" style="color: var(--ch-text)">No discourses yet</h3>
        <p class="text-sm mt-1" style="color: var(--ch-subtext)">We're still curating spiritual content for your
          breed. Check back soon.</p>
      </div>

      <!-- Featured spotlight -->
      <div id="ch-section-featured" class="hidden">
        <div class="ch-section-head">
          <span class="ch-section-bar"></span>
          <i data-lucide="radio-tower" class="w-5 h-5" style="color:var(--faith-accent,#e04848)"></i>
          <h3 class="font-bold text-lg" style="color: var(--ch-text)">Live for your breed</h3>
        </div>
        <div id="ch-featured-slot" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
      </div>

      <!-- Row: Recent Pravachans (Video) -->
      <div id="ch-section-video">
        <div class="ch-section-head">
          <span class="ch-section-bar"></span>
          <i data-lucide="compass" class="w-5 h-5" style="color:var(--faith-accent,#e04848)"></i>
          <h3 class="font-bold text-lg" style="color: var(--ch-text)">Explore all faith channels</h3>
        </div>
        <div class="ch-row-scroll" id="ch-row-video"></div>
      </div>

      <!-- Row: Kirtans (Audio) -->
      <div id="ch-section-audio">
        <div class="ch-section-head">
          <span class="ch-section-bar"></span>
          <i data-lucide="music" class="w-5 h-5" style="color:var(--faith-accent,#e04848)"></i>
          <h3 class="font-bold text-lg" style="color: var(--ch-text)">Kirtans &amp; Bhajans</h3>
        </div>
        <div class="ch-row-scroll" id="ch-row-audio"></div>
      </div>

      <!-- Row: Trending -->
      <div id="ch-section-trending">
        <div class="ch-section-head">
          <span class="ch-section-bar"></span>
          <i data-lucide="flame" class="w-5 h-5" style="color:var(--faith-accent,#e04848)"></i>
          <h3 class="font-bold text-lg" style="color: var(--ch-text)">Trending This Week</h3>
        </div>
        <div class="ch-row-scroll" id="ch-row-trending"></div>
      </div>

    </div>
  </div>