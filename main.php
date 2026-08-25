<?php require_once 'api/utils/security_headers.php'; ?>
<!doctype html>
<html lang="en">

<?php include 'components/head.php'; ?>

<body class="bg-gray-50 dark:bg-gray-950 min-h-screen text-gray-800 dark:text-gray-200">
  <svg class="sr-only" aria-hidden="true" style="display:none;">
    <symbol id="icon-paw" viewBox="0 0 24 24">
      <ellipse cx="12" cy="15.2" rx="6" ry="5" />
      <ellipse cx="5" cy="8" rx="2.5" ry="3.1" />
      <ellipse cx="11.2" cy="5.2" rx="2.5" ry="3.1" />
      <ellipse cx="17.4" cy="8" rx="2.5" ry="3.1" />
      <ellipse cx="20" cy="12.4" rx="2.2" ry="2.9" />
    </symbol>

    <!-- Kennel icon (step 31) for the Hub tab, which is icon-only (no text
         label). The other 7 tabs use Lucide's own real icons directly
         (paw-print/heart/dog/bone/images/hand-heart/book-open-text — this
         pinned lucide@1.22.0 build actually has all of these) rather than
         a full set of hand-drawn paw variants, which read as scattered
         disconnected blobs at nav-icon size instead of a recognizable paw. -->
    <symbol id="icon-kennel" viewBox="0 0 24 24">
      <path d="M4 11 L12 4 L20 11" />
      <path d="M5.5 11 L5.5 20 L18.5 20 L18.5 11" />
      <path d="M9.5 20 L9.5 15.5 A2.5 2.5 0 0 1 14.5 15.5 L14.5 20" />
    </symbol>
  </svg>

  <!-- ==================== PUBLIC LOGIN ==================== -->
  <?php include 'views/public_login.php'; ?>

  <!-- ==================== SIGNUP ==================== -->
  <?php include 'views/signup.php'; ?>

  <!-- ==================== VERIFY EMAIL ==================== -->
  <?php include 'views/verify_email.php'; ?>

  <!-- ==================== FORGOT PASSWORD ==================== -->
  <?php include 'views/forgot_password.php'; ?>

  <!-- ==================== SOCIAL FEED (main hub after login) ==================== -->
  <?php include 'views/social_feed.php'; ?>

  <!-- ==================== PET PROFILE ==================== -->
  <?php include 'views/pet_profile.php'; ?>

  <!-- ==================== VIEW ANOTHER MEMBER'S FULL PROFILE ==================== -->
  <?php include 'views/member_profile_page.php'; ?>

  <!-- ==================== PACK TREE ==================== -->
  <?php include 'views/pack_tree.php'; ?>

  <!-- ==================== PLAYDATES / MATCHMAKING ==================== -->
  <?php include 'views/playdates.php'; ?>

  <!-- ==================== ADMIN DASHBOARD ==================== -->
  <?php include 'views/admin_dashboard.php'; ?>



  <!-- Edit Pack Profile Modal -->
  <?php include 'modals/profile_modal.php'; ?>

  <!-- Manage Pack Members Modal -->
  <?php include 'modals/pack_members_modal.php'; ?>

  <!-- Group Chat Modal -->
  <?php include 'modals/group_chat_modal.php'; ?>

  <!-- Create Group Modal -->
  <?php include 'modals/create_group_modal.php'; ?>

  <!-- Create Event Modal -->
  <?php include 'modals/create_event_modal.php'; ?>

  <!-- Event Share Sheet (copy link / native share / QR / ICS) -->
  <?php include 'modals/event_share_modal.php'; ?>

  <!-- Create Gallery Modal -->
  <?php include 'modals/create_gallery_modal.php'; ?>

  <!-- Gallery Lightbox -->
  <?php include 'modals/gallery_lightbox.php'; ?>

  <?php include 'modals/rescue_apply_modal.php'; ?>
  <?php include 'modals/create_rescue_modal.php'; ?>
  <?php include 'modals/verification_modal.php'; ?>
  <?php include 'modals/admin_mode_modal.php'; ?>
  <?php include 'modals/confirm_modal.php'; ?>
  <?php include 'modals/image_cropper_modal.php'; ?>
  <?php include 'modals/enlarged_calendar_modal.php'; ?>
  <?php include 'modals/member_profile_modal.php'; ?>
  <?php include 'modals/set_handle_modal.php'; ?>

  <!-- Zoom call shell: floating toolbar + minimized "return to call" chip.
       The Meeting SDK injects its own #zmmtg-root container at ZoomMtg.init()
       time — nothing to hand-author for the call surface itself. -->
  <div id="pawcircle-zoom-toolbar">
    <button type="button" id="zoom-toolbar-main" onclick="toggleZoomToolbarMenu()">PawCircle Call</button>
    <div id="zoom-toolbar-menu" class="hidden">
      <button type="button" id="zoom-size-toggle-btn" onclick="toggleZoomCallSize()">Compact</button>
      <button type="button" id="zoom-fullscreen-toggle-btn" onclick="toggleZoomFullscreen()">Fullscreen</button>
      <button type="button" onclick="popOutZoomCall()">Pop out</button>
      <button type="button" onclick="minimizeZoomCallShell()">Minimize</button>
      <button type="button" onclick="leaveZoomCallShell()">Leave call</button>
      <button type="button" id="zoom-end-call-btn" onclick="endZoomCallForEveryone()" class="hidden">End for everyone</button>
    </div>
  </div>
  <div id="zoom-return-chip" aria-live="polite">
    <button type="button" onclick="restoreZoomCallShell()">Return to call</button>
    <button type="button" onclick="leaveZoomCallShell()">Leave</button>
  </div>
  <div id="incoming-call-toast" aria-live="polite"></div>

  <script src="js/vendor.js?v=<?= assetVer('js/vendor.js') ?>" defer></script>
  <script src="js/state.js?v=<?= assetVer('js/state.js') ?>" defer></script>
  <script src="js/core.js?v=<?= assetVer('js/core.js') ?>" defer></script>
  <script src="js/auth.js?v=<?= assetVer('js/auth.js') ?>" defer></script>
  <script src="js/profile.js?v=<?= assetVer('js/profile.js') ?>" defer></script>
  <script src="js/cropper.js?v=<?= assetVer('js/cropper.js') ?>" defer></script>
  <script src="js/verification.js?v=<?= assetVer('js/verification.js') ?>" defer></script>
  <script src="js/hub_widgets.js?v=<?= assetVer('js/hub_widgets.js') ?>" defer></script>
  <script src="js/posts.js?v=<?= assetVer('js/posts.js') ?>" defer></script>
  <script src="js/friends.js?v=<?= assetVer('js/friends.js') ?>" defer></script>
  <script src="js/member_profile.js?v=<?= assetVer('js/member_profile.js') ?>" defer></script>
  <script src="js/groups.js?v=<?= assetVer('js/groups.js') ?>" defer></script>
  <script src="js/events.js?v=<?= assetVer('js/events.js') ?>" defer></script>
  <script src="js/galleries.js?v=<?= assetVer('js/galleries.js') ?>" defer></script>
  <script src="js/settings.js?v=<?= assetVer('js/settings.js') ?>" defer></script>
  <script src="js/rescue.js?v=<?= assetVer('js/rescue.js') ?>" defer></script>
  <script src="js/care_guides.js?v=<?= assetVer('js/care_guides.js') ?>" defer></script>
  <script src="js/community_hub.js?v=<?= assetVer('js/community_hub.js') ?>" defer></script>
  <script src="js/admin.js?v=<?= assetVer('js/admin.js') ?>" defer></script>
  <script src="js/admin_users.js?v=<?= assetVer('js/admin_users.js') ?>" defer></script>
  <script src="js/admin_content.js?v=<?= assetVer('js/admin_content.js') ?>" defer></script>
  <script src="js/admin_config.js?v=<?= assetVer('js/admin_config.js') ?>" defer></script>
  <script src="js/admin_servers.js?v=<?= assetVer('js/admin_servers.js') ?>" defer></script>
  <script src="js/pack_tree.js?v=<?= assetVer('js/pack_tree.js') ?>" defer></script>
  <script src="js/playdates.js?v=<?= assetVer('js/playdates.js') ?>" defer></script>
  <script src="js/api.js?v=<?= assetVer('js/api.js') ?>" defer></script>

  <!-- Zoom Meeting SDK for Web + peer libraries (vendored, matching eSamaj's
       pinned 6.1.0 build). zoom.js depends on api()/showToast() from api.js
       and currentFriendChatId/currentGroupId from friends.js/groups.js. -->
  <script src="js/vendor/react.min.js" defer></script>
  <script src="js/vendor/react-dom.min.js" defer></script>
  <script src="js/vendor/redux.min.js" defer></script>
  <script src="js/vendor/redux-thunk.min.js" defer></script>
  <script src="js/vendor/lodash.min.js" defer></script>
  <script src="js/vendor/zoom-meeting-6.1.0.min.js" defer></script>
  <script src="js/zoom.js?v=<?= assetVer('js/zoom.js') ?>" defer></script>
</body>

</html>
