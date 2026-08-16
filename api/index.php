<?php
/**
 * PawCircle Backend API - Supabase REST API Edition
 * Communicates entirely via the Supabase REST API using the Secret Key.
 * No direct database connection required. Mirrors community_proj/api/index.php's
 * bootstrap/CORS/session-gating pattern.
 *
 * NOTE: this only wires up auth so far. As each feature area is built
 * (per the rebuild plan's build sequence), its route file gets require_once'd
 * here and its actions get added to $publicActions/$routes below.
 */

if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, $length);
    }
}

ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header_remove('X-Powered-By');

define('PAWCIRCLE_BACKEND_BUILD', 'rebuild-v1');

$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $parsed = parse_ini_file($envFile);
    if ($parsed) {
        foreach ($parsed as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

require_once __DIR__ . '/utils/supabase_client.php';
require_once __DIR__ . '/utils/response_helpers.php';
require_once __DIR__ . '/utils/build_id.php';

define('PAWCIRCLE_DEBUG', in_array(strtolower((string) envValue('PAWCIRCLE_DEBUG', '')), ['1', 'true', 'yes', 'on'], true));

require_once __DIR__ . '/routes/session.php';
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/core.php';
require_once __DIR__ . '/routes/settings.php';
require_once __DIR__ . '/routes/notifications.php';
require_once __DIR__ . '/routes/zoom.php';
require_once __DIR__ . '/routes/posts.php';
require_once __DIR__ . '/routes/friends.php';
require_once __DIR__ . '/routes/groups.php';
require_once __DIR__ . '/routes/events.php';
require_once __DIR__ . '/routes/galleries.php';
require_once __DIR__ . '/routes/messages.php';
require_once __DIR__ . '/routes/pack_tree.php';
require_once __DIR__ . '/routes/rescue.php';
require_once __DIR__ . '/routes/playdates.php';
require_once __DIR__ . '/routes/url_utils.php';
require_once __DIR__ . '/routes/care_guides.php';
require_once __DIR__ . '/routes/community_hub.php';
require_once __DIR__ . '/routes/verification.php';
require_once __DIR__ . '/routes/admin_core.php';
require_once __DIR__ . '/routes/admin_users.php';
require_once __DIR__ . '/routes/admin_content.php';
require_once __DIR__ . '/routes/servers.php';

// --- CORS: only reflect explicitly allowed origins ---
$allowedOriginsRaw = envValue('ALLOWED_ORIGINS', '');
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsRaw))));
if (empty($allowedOrigins)) {
    $allowedOrigins = [
        'http://localhost:5500',
        'http://127.0.0.1:5500',
    ];
}
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin !== '') {
    if (in_array($requestOrigin, $allowedOrigins, true) || preg_match('/^http:\/\/(localhost|127\.0\.0\.1):\d+$/', $requestOrigin)) {
        header("Access-Control-Allow-Origin: {$requestOrigin}");
        header("Vary: Origin");
        header("Access-Control-Allow-Credentials: true");
    } else {
        header("Vary: Origin");
    }
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-CSRF-Token");

require_once __DIR__ . '/utils/security_headers.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

define('PAWCIRCLE_SESSION_COOKIE', 'pawcircle_session_token');
define('PAWCIRCLE_CSRF_COOKIE', 'pawcircle_csrf_token');
define('PAWCIRCLE_SESSION_TTL_SECONDS', 60 * 60 * 24 * 90);
define('PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS', 60 * 15);
define('PAWCIRCLE_SIGNUP_CODE_MAX_ATTEMPTS', 6);
define('PAWCIRCLE_EMAIL_VERIFICATION_ENABLED', true);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

$isMultipart = isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;
$isGet = $_SERVER['REQUEST_METHOD'] === 'GET';
$inputData = ($isMultipart || $isGet) ? [] : (json_decode(file_get_contents("php://input"), true) ?? []);
if ($isGet) {
    $inputData = array_merge($inputData, $_GET);
}
$action = $_GET['action'] ?? $inputData['action'] ?? '';

if ($action === 'ping') {
    echo json_encode(["status" => "pong", "message" => "PHP backend is active and connected to Supabase REST API.", "backend_build" => PAWCIRCLE_BACKEND_BUILD]);
    exit();
}

$publicActions = [
    'signup',
    'verify_signup',
    'resend_signup_code',
    'public_login',
    'get_build_id',
    'request_password_reset',
    'verify_password_reset_code',
    'reset_password',
    'get_care_guides',
    'guide_redirect',
    'guide_file',
];
$adminActions = [
    'list_verification_requests',
    'review_verification_request',
    'admin_get_analytics',
    'admin_list_sessions',
    'admin_revoke_session',
    'admin_get_pet_type_themes',
    'admin_save_pet_type_theme',
    'admin_contact_book',
    'admin_list_custom_reactions',
    'admin_add_custom_reaction',
    'admin_set_custom_reaction_active',
    'admin_delete_custom_reaction',
    'admin_get_feature_visibility',
    'admin_save_feature_visibility',
    'admin_get_feed_layout',
    'admin_save_feed_layout',
    'admin_list_ads',
    'admin_save_ad',
    'admin_delete_ad',
    'admin_list_users',
    'admin_get_user_detail',
    'admin_update_user_status',
    'admin_add_user_note',
    'admin_resolve_user_action',
    'admin_clear_user_session_history',
    'list_admin_roles',
    'grant_admin_role',
    'update_admin_role',
    'revoke_admin_role',
    'admin_list_posts',
    'admin_moderate_post',
    'admin_list_events',
    'admin_delete_event',
    'admin_list_galleries',
    'admin_delete_gallery',
    'admin_get_servers',
    'admin_save_server',
    'admin_delete_server',
    'admin_ping_server',
];
$adminSharedActions = ['session_me', 'logout'];

if (!in_array($action, $publicActions, true)) {
    $authContext = requireAuthenticatedSession(true);
    $inputData['auth_user_id'] = $authContext['user_id'];
    $inputData['auth_role'] = $authContext['role'];
    $inputData['auth_session_id'] = $authContext['session_id'];
    $GLOBALS['PAWCIRCLE_AUTH_CONTEXT'] = $authContext;
    if ($action !== 'logout') {
        enforceActiveUserPunishments($authContext['user_id'], $action, $authContext['role'] ?? 'member');
    }

    if (in_array($action, $adminActions, true)) {
        requireAdminMode($authContext);
    } elseif (($authContext['role'] ?? '') === 'admin') {
        if (!in_array($action, $adminSharedActions, true)) {
            jsonError("Admin sessions cannot use member account actions.", 403);
            exit();
        }
    } else {
        $inputData['user_id'] = $authContext['user_id'];
    }
}

$routes = [
    'get_build_id' => 'handleGetBuildId',
    'signup' => 'handleSignup',
    'verify_signup' => 'handleVerifySignup',
    'resend_signup_code' => 'handleResendSignupCode',
    'public_login' => 'handleLogin',
    'session_me' => 'handleSessionMe',
    'logout' => 'handleLogout',
    'set_handle' => 'handleSetHandle',
    'request_password_reset' => 'handleRequestPasswordReset',
    'verify_password_reset_code' => 'handleVerifyPasswordResetCode',
    'reset_password' => 'handleResetPassword',
    'track_activity' => 'handleTrackActivity',
    'get_profile' => 'handleGetProfile',
    'update_profile' => 'handleUpdateProfile',
    'change_pet_type_breed' => 'handleChangePetTypeBreed',
    'upload_photo' => 'handlePhotoUpload',

    // Account settings
    'get_account_settings' => 'handleGetAccountSettings',
    'change_account_credentials' => 'handleChangeAccountCredentials',
    'sign_out_other_devices' => 'handleSignOutOtherDevices',
    'get_privacy_settings' => 'handleGetPrivacySettings',
    'save_privacy_settings' => 'handleSavePrivacySettings',
    'block_user' => 'handleBlockUser',
    'unblock_user' => 'handleUnblockUser',
    'get_blocked_users' => 'handleGetBlockedUsers',
    'deactivate_account' => 'handleDeactivateAccount',
    'delete_account_permanently' => 'handleDeleteAccountPermanently',

    // Notifications
    'get_notifications' => 'handleGetNotifications',
    'mark_notification_read' => 'handleMarkNotificationRead',
    'mark_all_notifications_read' => 'handleMarkAllNotificationsRead',

    // Posts
    'create_post' => 'handleCreatePost',
    'edit_post' => 'handleEditPost',
    'get_link_preview' => 'handleGetLinkPreview',
    'get_posts' => 'handleGetPosts',
    'get_post_by_id' => 'handleGetPostById',
    'get_user_posts' => 'handleGetUserPosts',
    'delete_post' => 'handleDeletePost',
    'archive_post' => 'handleArchivePost',
    'report_post' => 'handleReportPost',
    'toggle_like' => 'handleToggleLike',
    'set_post_reaction' => 'handleSetPostReaction',
    'get_active_reactions' => 'handleGetActiveReactions',
    'add_comment' => 'handleAddComment',
    'submit_comment' => 'handleSubmitComment',
    'get_comments' => 'handleGetComments',
    'delete_comment' => 'handleDeleteComment',
    'edit_comment' => 'handleEditComment',
    'toggle_comment_like' => 'handleToggleCommentLike',

    // Friends
    'search_users' => 'handleSearchUsers',
    'send_friend_request' => 'handleSendFriendRequest',
    'respond_friend_request' => 'handleRespondFriendRequest',
    'remove_friend' => 'handleRemoveFriend',
    'get_friends' => 'handleGetFriends',
    'get_friend_requests' => 'handleGetFriendRequests',

    // Groups
    'create_group' => 'handleCreateGroup',
    'get_groups' => 'handleGetGroups',
    'join_group' => 'handleJoinGroup',
    'leave_group' => 'handleLeaveGroup',
    'send_group_message' => 'handleSendGroupMessage',
    'get_group_messages' => 'handleGetGroupMessages',
    'react_group_message' => 'handleReactToGroupMessage',

    // Events
    'create_event' => 'handleCreateEvent',
    'update_event' => 'handleUpdateEvent',
    'get_events' => 'handleGetEvents',
    'rsvp_event' => 'handleRsvpEvent',
    'delete_event' => 'handleDeleteEvent',
    'get_event_analytics' => 'handleGetEventAnalytics',

    // Galleries
    'create_gallery' => 'handleCreateGallery',
    'update_gallery' => 'handleUpdateGallery',
    'add_gallery_item' => 'handleAddGalleryItem',
    'delete_gallery_item' => 'handleDeleteGalleryItem',
    'get_galleries' => 'handleGetGalleries',
    'get_gallery_items' => 'handleGetGalleryItems',
    'delete_gallery' => 'handleDeleteGallery',

    // Direct messages
    'send_direct_message' => 'handleSendDirectMessage',
    'get_direct_messages' => 'handleGetDirectMessages',
    'react_direct_message' => 'handleReactToDirectMessage',
    'get_conversations' => 'handleGetConversations',

    // Zoom calling
    'zoom_start_call' => 'handleZoomStartCall',
    'zoom_join_call' => 'handleZoomJoinCall',
    'zoom_end_call' => 'handleZoomEndCall',
    'zoom_mark_participant' => 'handleZoomMarkParticipant',
    'zoom_get_active_calls' => 'handleZoomGetActiveCalls',
    'zoom_get_direct_calls' => 'handleZoomGetDirectCalls',
    'zoom_get_group_calls' => 'handleZoomGetGroupCalls',

    // Pack Tree
    'get_pack_members' => 'handleGetPackMembers',
    'save_pack_member' => 'handleSavePackMember',
    'delete_pack_member' => 'handleDeletePackMember',

    // Rescue & Seva marketplace
    'create_rescue_opportunity' => 'handleCreateRescueOpportunity',
    'update_rescue_opportunity' => 'handleUpdateRescueOpportunity',
    'get_rescue_opportunities' => 'handleGetRescueOpportunities',
    'delete_rescue_opportunity' => 'handleDeleteRescueOpportunity',
    'archive_rescue_opportunity' => 'handleArchiveRescueOpportunity',
    'apply_rescue_opportunity' => 'handleApplyRescueOpportunity',
    'get_rescue_applications' => 'handleGetRescueApplications',
    'delete_rescue_application' => 'handleDeleteRescueApplication',

    // Playdates / matchmaking
    'get_playdate_profile' => 'handleGetPlaydateProfile',
    'save_playdate_profile' => 'handleSavePlaydateProfile',
    'save_playdate_preferences' => 'handleSavePlaydatePreferences',
    'get_playdate_deck' => 'handleGetPlaydateDeck',
    'swipe_playdate' => 'handleSwipePlaydate',
    'get_playdate_matches' => 'handleGetPlaydateMatches',

    // Pet Care & Training Guides library
    'get_care_guides' => 'handleGetCareGuides',
    'guide_redirect' => 'handleGuideRedirect',
    'guide_file' => 'handleGuideFile',

    // Community Hub
    'get_community_hub' => 'handleGetCommunityHub',

    // Verified Pet Parent
    'submit_verification' => 'handleSubmitVerification',
    'get_my_verification_status' => 'handleGetMyVerificationStatus',
    'list_verification_requests' => 'handleAdminListVerificationRequests',
    'review_verification_request' => 'handleAdminReviewVerificationRequest',

    // Admin dashboard
    'enter_admin_mode' => 'handleEnterAdminMode',
    'exit_admin_mode' => 'handleExitAdminMode',
    'get_app_config' => 'handleGetAppConfig',
    'get_ads' => 'handleGetAds',

    'admin_get_analytics' => 'handleAdminGetAnalytics',

    'admin_list_sessions' => 'handleAdminListSessions',
    'admin_revoke_session' => 'handleAdminRevokeSession',

    'admin_get_pet_type_themes' => 'handleAdminGetPetTypeThemes',
    'admin_save_pet_type_theme' => 'handleAdminSavePetTypeTheme',
    'admin_contact_book' => 'handleAdminContactBook',

    'admin_list_custom_reactions' => 'handleAdminListCustomReactions',
    'admin_add_custom_reaction' => 'handleAdminAddCustomReaction',
    'admin_set_custom_reaction_active' => 'handleAdminSetCustomReactionActive',
    'admin_delete_custom_reaction' => 'handleAdminDeleteCustomReaction',

    'admin_get_feature_visibility' => 'handleAdminGetFeatureVisibility',
    'admin_save_feature_visibility' => 'handleAdminSaveFeatureVisibility',

    'admin_get_feed_layout' => 'handleAdminGetFeedLayout',
    'admin_save_feed_layout' => 'handleAdminSaveFeedLayout',

    'admin_list_ads' => 'handleAdminListAds',
    'admin_save_ad' => 'handleAdminSaveAd',
    'admin_delete_ad' => 'handleAdminDeleteAd',

    'admin_list_users' => 'handleAdminListUsers',
    'admin_get_user_detail' => 'handleAdminGetUserDetail',
    'admin_update_user_status' => 'handleAdminUpdateUserStatus',
    'admin_add_user_note' => 'handleAdminAddUserNote',
    'admin_resolve_user_action' => 'handleAdminResolveUserAction',
    'admin_clear_user_session_history' => 'handleAdminClearUserSessionHistory',

    'list_admin_roles' => 'handleListAdminRoles',
    'grant_admin_role' => 'handleGrantAdminRole',
    'update_admin_role' => 'handleUpdateAdminRole',
    'revoke_admin_role' => 'handleRevokeAdminRole',

    'admin_list_posts' => 'handleAdminListPosts',
    'admin_moderate_post' => 'handleAdminModeratePost',
    'admin_list_events' => 'handleAdminListEvents',
    'admin_delete_event' => 'handleAdminDeleteEvent',
    'admin_list_galleries' => 'handleAdminListGalleries',
    'admin_delete_gallery' => 'handleAdminDeleteGallery',

    'admin_get_servers' => 'handleGetServers',
    'admin_save_server' => 'handleSaveServer',
    'admin_delete_server' => 'handleDeleteServer',
    'admin_ping_server' => 'handlePingServer',
];

function handleGetBuildId($data)
{
    jsonSuccess(['build_id' => appBuildId(), 'backend_build' => PAWCIRCLE_BACKEND_BUILD]);
}

if (isset($routes[$action])) {
    $handler = $routes[$action];
    $handler($inputData);
} else {
    jsonError("Unknown action: " . htmlspecialchars($action), 404);
}
