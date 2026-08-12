<div id="gallery-lightbox" class="gallery-lightbox fixed inset-0 z-[80] bg-white/95 dark:bg-black/95 backdrop-blur-xl flex flex-col items-center justify-center">
  <div class="absolute top-5 left-0 right-0 z-30 flex items-center justify-between gap-4 px-5 sm:px-10 pointer-events-none">
    <div class="min-w-0">
      <p id="gallery-lightbox-title" class="text-gray-900 dark:text-white text-base sm:text-lg font-bold truncate">Gallery</p>
      <p id="gallery-lightbox-counter" class="text-sm text-gray-500 dark:text-white/45 mt-0.5">0 / 0</p>
    </div>
    <button type="button" onclick="closeGallerySlideshow()"
      class="no-accent-hover pointer-events-auto w-10 h-10 rounded-full bg-gray-900/10 dark:bg-white/10 border border-gray-900/10 dark:border-white/10 text-gray-900 dark:text-white inline-flex items-center justify-center flex-shrink-0">
      <i data-lucide="x" class="w-5 h-5"></i>
    </button>
  </div>
  <div class="relative w-full flex items-center justify-center pt-16 sm:pt-20">
    <button id="gallery-lightbox-prev" type="button" onclick="moveGallerySlideshow(-1)"
      class="no-accent-hover absolute left-3 sm:left-6 z-10 w-11 h-11 rounded-full bg-gray-900/10 dark:bg-white/10 border border-gray-900/10 dark:border-white/10 text-gray-900 dark:text-white inline-flex items-center justify-center">
      <i data-lucide="arrow-left" class="w-5 h-5"></i>
    </button>
    <div id="gallery-lightbox-track" class="gallery-lightbox-track flex gap-6 overflow-x-auto w-screen px-[4vw] sm:px-[7vw]"></div>
    <button id="gallery-lightbox-next" type="button" onclick="moveGallerySlideshow(1)"
      class="no-accent-hover absolute right-3 sm:right-6 z-10 w-11 h-11 rounded-full bg-gray-900/10 dark:bg-white/10 border border-gray-900/10 dark:border-white/10 text-gray-900 dark:text-white inline-flex items-center justify-center">
      <i data-lucide="arrow-right" class="w-5 h-5"></i>
    </button>
  </div>
  <div id="gallery-lightbox-thumbs" class="mt-4 flex max-w-[92vw] items-center justify-center gap-2 overflow-x-auto no-scrollbar"></div>
</div>
