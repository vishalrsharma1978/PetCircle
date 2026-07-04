<!doctype html>
<html lang="en">

<head>
<?php include '../components/head.php'; ?>
</head>

<body class="bg-gray-50 min-h-screen text-gray-800">
  <svg class="sr-only" aria-hidden="true" style="display:none;">
    <symbol id="icon-paw" viewBox="0 0 24 24">
      <ellipse cx="12" cy="15.2" rx="6" ry="5" />
      <ellipse cx="5" cy="8" rx="2.5" ry="3.1" />
      <ellipse cx="11.2" cy="5.2" rx="2.5" ry="3.1" />
      <ellipse cx="17.4" cy="8" rx="2.5" ry="3.1" />
      <ellipse cx="20" cy="12.4" rx="2.2" ry="2.9" />
    </symbol>
  </svg>

  <!-- ==================== PUBLIC LOGIN ==================== -->

  <?php include '../views/view-public-login.php'; ?>

  <!-- ==================== ADMIN LOGIN ==================== -->
  <?php include '../views/view-admin-login.php'; ?>

  <!-- ==================== SIGNUP ==================== -->
  <?php include '../views/view-signup.php'; ?>

  <!-- ==================== MEMBER DASHBOARD ==================== -->
  <?php include '../views/view-member-dashboard.php'; ?>

  <!-- ==================== SOCIAL FEED ==================== -->
  <?php include '../views/view-social-feed.php'; ?>

  <!-- Gotcha Day Modal -->
  <?php include '../modals/birth-details-modal.php'; ?>

  <!-- Profile Customization Modal -->
  <?php include '../modals/profile-modal.php'; ?>

  <!-- ==================== ADMIN DASHBOARD ==================== -->
  <?php include '../views/view-admin-dashboard.php'; ?>

  <script src="js/core.js"></script>

  <!-- Add Pet Modal -->
  <?php include '../modals/add-member-modal.php'; ?>

  <!-- Broadcast Message Modal -->
  <?php include '../modals/broadcast-modal.php'; ?>

  <!-- Create Group Modal -->
  <?php include '../modals/create-group-modal.php'; ?>

  <!-- Add Event Modal -->
  <?php include '../modals/add-event-modal.php'; ?>
  <!-- ===== EVENT QR MODAL ===== -->
  <?php include '../modals/event-qr-modal.php'; ?>

  <?php include '../modals/link-gallery-choice-modal.php'; ?>
  <?php include '../modals/create-gallery-modal.php'; ?>
  <div id="gallery-lightbox"
    class="gallery-lightbox fixed inset-0 z-[80] bg-white/95 dark:bg-black/95 backdrop-blur-xl flex flex-col items-center justify-center">
    <img id="gallery-lightbox-post-bg" class="gallery-lightbox-post-bg" alt="" aria-hidden="true">
    <div
      class="absolute top-5 left-0 right-0 z-30 flex items-center justify-between gap-4 px-5 sm:px-10 pointer-events-none">
      <div class="min-w-0">
        <p id="gallery-lightbox-title" class="text-gray-900 dark:text-white text-base sm:text-lg font-bold truncate">
          Gallery</p>
        <p id="gallery-lightbox-counter" class="text-sm text-gray-500 dark:text-white/45 mt-0.5">0 / 0</p>
      </div>
      <button type="button" onclick="closeGallerySlideshow()"
        class="no-faith-hover pointer-events-auto w-11 h-11 rounded-full bg-gray-900/10 dark:bg-white/10 border border-gray-900/10 dark:border-white/10 text-gray-900 dark:text-white inline-flex items-center justify-center">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
    </div>
    <div class="relative w-full flex items-center justify-center pt-16 sm:pt-20">
      <button id="gallery-lightbox-prev" type="button" onclick="moveGallerySlideshow(-1)"
        class="no-faith-hover absolute left-3 sm:left-6 z-10 w-11 h-11 rounded-full bg-gray-900/10 dark:bg-white/10 border border-gray-900/10 dark:border-white/10 text-gray-900 dark:text-white inline-flex items-center justify-center">
        <i data-lucide="arrow-left" class="w-5 h-5"></i>
      </button>
      <div id="gallery-lightbox-track"
        class="gallery-lightbox-track flex gap-6 overflow-x-auto w-screen px-[4vw] sm:px-[7vw]"></div>
      <button id="gallery-lightbox-next" type="button" onclick="moveGallerySlideshow(1)"
        class="no-faith-hover absolute right-3 sm:right-6 z-10 w-11 h-11 rounded-full bg-gray-900/10 dark:bg-white/10 border border-gray-900/10 dark:border-white/10 text-gray-900 dark:text-white inline-flex items-center justify-center">
        <i data-lucide="arrow-right" class="w-5 h-5"></i>
      </button>
    </div>
    <div id="gallery-lightbox-dots" class="mt-7 flex items-center justify-center gap-2"></div>
    <div id="gallery-lightbox-thumbs"
      class="mt-4 flex max-w-[92vw] items-center justify-center gap-2 overflow-x-auto no-scrollbar"></div>
  </div>
  <!-- Enlarged Calendar Modal -->
  <?php include '../modals/enlarged-calendar-modal.php'; ?>

  <!-- Announcement Modal -->
  <?php include '../modals/announcement-modal.php'; ?>

  <!-- Condolence Modal -->
  <?php include '../modals/condolence-modal.php'; ?>

  <script src="js/main.js"></script>

  <div id="pawcircle-zoom-toolbar">
    <button type="button" id="zoom-toolbar-main" onclick="toggleZoomToolbarMenu()">
      PawCircle Call
    </button>

    <div id="zoom-toolbar-menu" class="hidden">
      <button type="button" id="zoom-size-toggle-btn" onclick="toggleZoomCallSize()">
        Compact
      </button>

      <button type="button" id="zoom-fullscreen-toggle-btn" onclick="toggleZoomFullscreen()">
        Fullscreen
      </button>

      <button type="button" onclick="popOutZoomCall()">
        Pop out
      </button>

      <button type="button" onclick="minimizeZoomCallShell()">
        Minimize
      </button>

      <button type="button" onclick="leaveZoomCallShell()">
        Leave call
      </button>
    </div>
  </div>

  <div id="zoom-return-chip" aria-live="polite">
    <button type="button" onclick="restoreZoomCallShell()">Return to call</button>
    <button type="button" onclick="leaveZoomCallShell()">Leave</button>
  </div>
  <!-- Pack Pets Customization Modal -->
  <?php include '../modals/pack-members-modal.php'; ?>

  <!-- View Member Profile Modal -->
  <?php include '../modals/pack-member-profile-modal.php'; ?>

  <!-- ==================== FAMILY TREE VIEW ==================== -->
  <?php include '../views/view-pack-tree.php'; ?>

  <!-- ==================== HOROSCOPE VIEW ==================== -->
  <?php include '../views/view-pet_profile.php'; ?>

  <link rel="stylesheet" href="css/style_4.css">

  <script src="https://source.zoom.us/6.1.0/lib/vendor/react.min.js"></script>
  <script src="https://source.zoom.us/6.1.0/lib/vendor/react-dom.min.js"></script>
  <script src="https://source.zoom.us/6.1.0/lib/vendor/redux.min.js"></script>
  <script src="https://source.zoom.us/6.1.0/lib/vendor/redux-thunk.min.js"></script>
  <script src="https://source.zoom.us/6.1.0/lib/vendor/lodash.min.js"></script>
  <script src="https://source.zoom.us/zoom-meeting-6.1.0.min.js"></script>

  <!-- User Profile Modal -->
  <!-- Forward Profile Modal -->
  <?php include '../modals/forward-profile-modal.php'; ?>

  <?php include '../modals/user-profile-modal.php'; ?>

  <!-- Edit Post Modal -->
  <?php include '../modals/edit-post-modal.php'; ?>

  <!-- Share Post Modal -->
  <?php include '../modals/share-post-modal.php'; ?>

  <script src="js/admin.js"></script>

  <!-- ==================== CONTENT HUB ==================== -->
  <?php include '../views/view-content-hub.php'; ?>

  <?php include '../modals/remove-friend-confirm-modal.php'; ?>
  <script src="js/script_9.js"></script>
</body>

</html>
