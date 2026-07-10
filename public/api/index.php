<?php
/**
 * PawCircle Secure Backend API - Supabase REST API Edition
 * Communicates entirely via the Supabase REST API using the Secret Key.
 * No direct database connection required.
 */

// Show only fatal errors in output — warnings (e.g. curl_close deprecation)
// must not bleed into JSON responses and corrupt them
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

define('PAWCIRCLE_BACKEND_BUILD', 'supabase-auth-bridge-otp-v1-2026-06-19');

if (!function_exists('mb_substr')) {

}
// Load .env first so PAWCIRCLE_DEBUG / ALLOWED_ORIGINS / Supabase keys are all available below.
$envFile = dirname(__DIR__, 2) . '/.env';
if (file_exists($envFile)) {
    $parsed = parse_ini_file($envFile);
    if ($parsed) {
        foreach ($parsed as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}





// Debug flag — when off (production), detailed Supabase/internal errors are logged
// server-side and replaced with a generic message + request id for the client.
define('PAWCIRCLE_DEBUG', in_array(strtolower((string) (getenv('PAWCIRCLE_DEBUG') ?: ($_ENV['PAWCIRCLE_DEBUG'] ?? ''))), ['1', 'true', 'yes', 'on'], true));

// A short id attached to each response and error log line for correlation.


// --- CORS: only reflect explicitly allowed origins ---
// Configure ALLOWED_ORIGINS in .env as a comma-separated list. Falls back to the
// production + local origins below if unset.
$allowedOriginsRaw = getenv('ALLOWED_ORIGINS') ?: ($_ENV['ALLOWED_ORIGINS'] ?? '');
$allowedOrigins = array_values(array_filter(array_map('trim', explode(',', $allowedOriginsRaw))));
if (empty($allowedOrigins)) {
    $allowedOrigins = [
        'https://pawcircle-n7ap.onrender.com',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
        'http://localhost:5500',
        'http://127.0.0.1:5500',
    ];
}
$requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($requestOrigin !== '' && in_array($requestOrigin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: {$requestOrigin}");
    header("Vary: Origin");
    header("Access-Control-Allow-Credentials: true");
} else {
    header("Access-Control-Allow-Origin: " . $allowedOrigins[0]);
    header("Vary: Origin");
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With, X-CSRF-Token");

// --- Security headers ---
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Frame-Options: SAMEORIGIN");
header("Permissions-Policy: camera=(self), microphone=(self), geolocation=()");
if (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}

define('PAWCIRCLE_SESSION_COOKIE', 'pawcircle_session_token');
define('PAWCIRCLE_CSRF_COOKIE', 'pawcircle_csrf_token');
define('PAWCIRCLE_SESSION_TTL_SECONDS', 60 * 60 * 24 * 7);
define('PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS', 60 * 15);
define('PAWCIRCLE_SIGNUP_CODE_MAX_ATTEMPTS', 6);
// Email verification is temporarily disabled (SendPulse account pending review):
// signups create the account immediately. Set to true to re-enable the emailed
// 6-digit code flow once SendPulse is verified.
define('PAWCIRCLE_EMAIL_VERIFICATION_ENABLED', false);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}


require_once __DIR__ . '/utils/response_helpers.php';
require_once __DIR__ . '/utils/supabase_client.php';

require_once __DIR__ . '/routes/core.php';
require_once __DIR__ . '/routes/auth.php';
require_once __DIR__ . '/routes/social.php';
require_once __DIR__ . '/routes/admin.php';
require_once __DIR__ . '/routes/integrations.php';
require_once __DIR__ . '/routes/notifications.php';
require_once __DIR__ . '/routes/rescue.php';

// Read action
// Do NOT read php://input for multipart requests (file uploads) —
// it conflicts with PHP's internal $_FILES parsing and causes a fatal error.
$isMultipart = isset($_SERVER['CONTENT_TYPE']) &&
    strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;

$inputData = $isMultipart ? [] : (json_decode(file_get_contents("php://input"), true) ?? []);
$action = $_GET['action'] ?? $inputData['action'] ?? '';

if ($action === 'ping') {
    echo json_encode(["status" => "pong", "message" => "PHP Backend is active and connected to Supabase REST API.", "backend_build" => PAWCIRCLE_BACKEND_BUILD]);
    exit();
}

// Add this temporary block below $action === 'ping'
if ($action === 'check_db_row') {
    if (!PAWCIRCLE_DEBUG)
        exit('Forbidden');
    $uid = $_GET['user_id'] ?? '';
    $res = supabaseRequest('GET', '/rest/v1/profiles', ['user_id' => 'eq.' . $uid]);
    header('Content-Type: application/json');
    echo json_encode($res);
    exit();
}

if ($action === 'check_tables') {
    if (!PAWCIRCLE_DEBUG) {
        jsonError("Not found.", 404);
        exit();
    }
    $tables = ['users', 'profiles'];
    $results = [];
    foreach ($tables as $t) {
        $r = supabaseRequest('GET', '/rest/v1/' . $t, ['limit' => '1']);
        $results[$t] = $r['code'] === 200 ? 'OK' : 'ERROR (HTTP ' . $r['code'] . '): ' . json_encode($r['data']);
    }
    echo json_encode($results);
    exit();
}

$publicActions = [
    'auth_config',
    'supabase_auth_exchange',
    'supabase_auth_login',
    'public_signup',
    'signup',
    'verify_signup',
    'resend_signup_code',
    'public_login',
    'admin_login',
    'get_pet_services',
    'ebook_redirect',
    'ebook_file',
];
$adminActions = ['get_stats', 'get_admin_dashboard', 'exit_admin_mode', 'list_admin_roles', 'grant_admin_role', 'update_admin_role', 'revoke_admin_role', 'admin_get_user_detail', 'admin_grant_user_role', 'admin_add_user_note', 'admin_resolve_user_action', 'admin_clear_user_session_history', 'admin_list_users', 'admin_update_user_status', 'admin_list_posts', 'admin_moderate_post', 'admin_list_events', 'admin_delete_event', 'admin_list_galleries', 'admin_delete_gallery', 'admin_list_sessions', 'admin_revoke_session', 'admin_get_analytics', 'admin_contact_book', 'admin_list_verification_requests', 'admin_review_verification_request', 'get_servers', 'save_server', 'delete_server', 'ping_server'];
$adminSharedActions = ['session_me', 'logout', 'enter_admin_mode', 'sign_out_other_devices'];

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
        // The backend uses a Supabase service key, so client-provided user_id
        // must never be authoritative. Protected member endpoints always run
        // as the signed-in user from the HttpOnly session cookie.
        $inputData['user_id'] = $authContext['user_id'];
    }
}

switch ($action) {
    case 'auth_config':
        handleAuthConfig();
        break;
    case 'supabase_auth_exchange':
        handleSupabaseAuthExchange($inputData);
        break;
    case 'supabase_auth_login':
        handleSupabaseAuthLogin($inputData);
        break;
    case 'signup':
        handleSignup($inputData);
        break;
    case 'verify_signup':
        handleVerifySignup($inputData);
        break;
    case 'resend_signup_code':
        handleResendSignupCode($inputData);
        break;

    case 'public_login':
        handleLogin($inputData, 'member');
        break;
    case 'admin_login':
        jsonError("Use normal sign in, then enter admin mode from your account menu.", 410);
        break;
    case 'session_me':
        handleSessionMe($inputData);
        break;
    case 'enter_admin_mode':
        handleEnterAdminMode($inputData);
        break;
    case 'exit_admin_mode':
        handleExitAdminMode($inputData);
        break;
    case 'get_admin_dashboard':
        handleGetAdminDashboard($inputData);
        break;
    case 'list_admin_roles':
        handleListAdminRoles($inputData);
        break;
    case 'grant_admin_role':
        handleGrantAdminRole($inputData);
        break;
    case 'update_admin_role':
        handleUpdateAdminRole($inputData);
        break;
    case 'revoke_admin_role':
        handleRevokeAdminRole($inputData);
        break;
    case 'admin_get_user_detail':
        handleAdminGetUserDetail($inputData);
        break;
    case 'admin_grant_user_role':
        handleAdminGrantUserRole($inputData);
        break;
    case 'admin_add_user_note':
        handleAdminAddUserNote($inputData);
        break;
    case 'admin_resolve_user_action':
        handleAdminResolveUserAction($inputData);
        break;
    case 'admin_clear_user_session_history':
        handleAdminClearUserSessionHistory($inputData);
        break;
    case 'admin_list_users':
        handleAdminListUsers($inputData);
        break;
    case 'admin_update_user_status':
        handleAdminUpdateUserStatus($inputData);
        break;
    case 'admin_list_posts':
        handleAdminListPosts($inputData);
        break;
    case 'admin_moderate_post':
        handleAdminModeratePost($inputData);
        break;
    case 'admin_list_events':
        handleAdminListEvents($inputData);
        break;
    case 'admin_delete_event':
        handleAdminDeleteEvent($inputData);
        break;
    case 'admin_list_galleries':
        handleAdminListGalleries($inputData);
        break;
    case 'admin_delete_gallery':
        handleAdminDeleteGallery($inputData);
        break;
    case 'admin_list_sessions':
        handleAdminListSessions($inputData);
        break;
    case 'admin_revoke_session':
        handleAdminRevokeSession($inputData);
        break;
    case 'admin_get_analytics':
        handleAdminGetAnalytics($inputData);
        break;
    case 'admin_contact_book':
        handleAdminContactBook($inputData);
        break;
    case 'logout':
        handleLogout($inputData);
        break;
    case 'sign_out_other_devices':
        handleSignOutOtherDevices($inputData);
        break;
    case 'get_stats':
        handleGetStats();
        break;
    case 'update_profile':
        handleUpdateProfile($inputData);
        break;
    case 'save_birth_details':
        handleSaveBirthDetails($inputData);
        break;
    case 'get_pet_pack_members':
        handleGetPetPackMembers($inputData);
        break;
    case 'save_pet_pack_member':
        handleSavePetPackMember($inputData);
        break;
    case 'delete_pet_pack_member':
        handleDeletePetPackMember($inputData);
        break;

    case 'send_email':
        handleSendEmail($inputData);
        break;

    case 'upload_photo':
        handlePhotoUpload();
        break;

    // Holy books / ebook library
    case 'get_pet_services':
        handleGetPetServices($inputData);
        break;
    case 'ebook_redirect':
        handleEbookRedirect($inputData);
        break;
    case 'ebook_file':
        handleEbookFile($inputData);
        break;

    case 'social_bootstrap':
        handleSocialBootstrap($inputData);
        break;
    case 'track_activity':
        handleTrackActivity($inputData);
        break;

    // Posts
    case 'create_post':
        handleCreatePost($inputData);
        break;
    case 'get_posts':
        handleGetPosts($inputData);
        break;
    case 'get_user_posts':
        handleGetUserPosts($inputData);
        break;
    case 'update_post':
        handleUpdatePost($inputData);
        break;
    case 'delete_post':
        handleDeletePost($inputData);
        break;

    // Account settings
    case 'get_account_settings':
        handleGetAccountSettings($inputData);
        break;
    case 'update_account_settings':
        handleUpdateAccountSettings($inputData);
        break;
    case 'change_account_credentials':
        handleChangeAccountCredentials($inputData);
        break;
    case 'change_pet_type_breed':
        handleChangePetTypeBreed($inputData);
        break;
    case 'deactivate_account':
        handleDeactivateAccount($inputData);
        break;
    case 'delete_account_permanently':
        handleDeleteAccountPermanently($inputData);
        break;

    // Likes
    case 'toggle_like':
        handleToggleLike($inputData);
        break;
    case 'toggle_comment_like':
        handleToggleCommentLike($inputData);
        break;

    // Comments
    case 'submit_comment':
        handleSubmitComment($inputData);
        break;
    case 'get_comments':
        handleGetComments($inputData);
        break;
    case 'edit_comment':
        handleEditComment($inputData);
        break;
    case 'delete_comment':
        handleDeleteComment($inputData);
        break;

    // Events
    case 'save_event':
        handleSaveEvent($inputData);
        break;
    case 'delete_event':
        handleDeleteEvent($inputData);
        break;
    case 'get_events':
        handleGetEvents($inputData);
        break;

    // Galleries
    case 'create_gallery':
        handleCreateGallery($inputData);
        break;
    case 'get_galleries':
        handleGetGalleries($inputData);
        break;
    case 'update_gallery':
        handleUpdateGallery($inputData);
        break;
    case 'delete_gallery':
        handleDeleteGallery($inputData);
        break;
    case 'add_gallery_item':
        handleAddGalleryItem($inputData);
        break;
    case 'delete_gallery_item':
        handleDeleteGalleryItem($inputData);
        break;

    // Groups
    case 'create_group':
        handleCreateGroup($inputData);
        break;
    case 'join_group':
        handleJoinGroup($inputData);
        break;
    case 'leave_group':
        handleLeaveGroup($inputData);
        break;
    case 'add_group_members':
        handleAddGroupMembers($inputData);
        break;
    case 'update_group_member_role':
        handleUpdateGroupMemberRole($inputData);
        break;
    case 'remove_group_member':
        handleRemoveGroupMember($inputData);
        break;
    case 'join_pack':
        handleJoinPack($inputData);
        break;
    case 'send_group_message':
        handleSendGroupMessage($inputData);
        break;
    case 'broadcast_message':
        handleBroadcastMessage($inputData);
        break;
    case 'get_group_messages':
        handleGetGroupMessages($inputData);
        break;
    case 'get_groups':
        handleGetGroups($inputData);
        break;
    case 'get_group':
        handleGetGroup($inputData);
        break;
    case 'send_direct_message':
        handleSendDirectMessage($inputData);
        break;
    case 'get_direct_messages':
        handleGetDirectMessages($inputData);
        break;

    // Friends
    case 'send_friend_request':
        handleSendFriendRequest($inputData);
        break;
    case 'respond_friend_request':
        handleRespondFriendRequest($inputData);
        break;
    case 'remove_friend':
        handleRemoveFriend($inputData);
        break;
    case 'get_friends':
        handleGetFriends($inputData);
        break;
    case 'search_members':
        handleSearchMembers($inputData);
        break;
    case 'get_notifications':
        handleGetNotifications($inputData);
        break;
    case 'mark_notification_read':
        handleMarkNotificationRead($inputData);
        break;

    // Zoom Calls
    case 'zoom_test':
        handleZoomTest($inputData);
        break;
    case 'zoom_start_call':
        handleZoomStartCall($inputData);
        break;
    case 'zoom_join_call':
        handleZoomJoinCall($inputData);
        break;
    case 'zoom_end_call':
        handleZoomEndCall($inputData);
        break;
    case 'zoom_get_active_calls':
        handleZoomGetActiveCalls($inputData);
        break;
    case 'zoom_get_direct_calls':
        handleZoomGetDirectCalls($inputData);
        break;
    case 'zoom_get_group_calls':
        handleZoomGetGroupCalls($inputData);
        break;
    case 'zoom_mark_participant':
        handleZoomMarkParticipant($inputData);
        break;

    // Playdate & Playdate
    case 'save_playdate_profile':
        handleSavePlaydateProfile($inputData);
        break;
    case 'get_playdate_profile':
        handleGetPlaydateProfile($inputData);
        break;
    case 'search_playdate':
        handleSearchPlaydate($inputData);
        break;
    case 'send_playdate_interest':
        handleSendPlaydateInterest($inputData);
        break;
    case 'respond_playdate_interest':
        handleRespondPlaydateInterest($inputData);
        break;
    case 'get_playdate_interests':
        handleGetPlaydateInterests($inputData);
        break;
    case 'save_playdate_preferences':
        handleSavePlaydatePreferences($inputData);
        break;
    case 'get_playdate_preferences':
        handleGetPlaydatePreferences($inputData);
        break;
    case 'get_playdate_deck':
        handleGetPlaydateDeck($inputData);
        break;
    case 'get_playdate_pool':
        handleGetPlaydatePool($inputData);
        break;
    case 'swipe_playdate':
        handleSwipePlaydate($inputData);
        break;
    case 'get_playdate_matches':
        handleGetPlaydateMatches($inputData);
        break;

    case 'submit_advertising_enquiry':
        handleSubmitAdvertisingEnquiry($inputData);
        break;

    case 'forward_playdate_profile':
        handleForwardPlaydateProfile($inputData);
        break;

    // Verification
    case 'submit_verification_request':
        handleSubmitVerificationRequest($inputData);
        break;
    case 'admin_list_verification_requests':
        handleAdminListVerificationRequests($inputData);
        break;
    case 'admin_review_verification_request':
        handleAdminReviewVerificationRequest($inputData);
        break;

    // Privacy settings
    case 'save_privacy_settings':
        handleSavePrivacySettings($inputData);
        break;
    case 'get_privacy_settings':
        handleGetPrivacySettings($inputData);
        break;

    // WhatsApp number linking + verification (profile is the single source of truth)
    case 'request_whatsapp_verification':
        handleRequestWhatsappVerification($inputData);
        break;
    case 'verify_whatsapp_number':
        handleVerifyWhatsappNumber($inputData);
        break;

    // Rescue Marketplace
    case 'create_rescue_opportunity':
        handleCreateRescueOpportunity($inputData);
        break;
    case 'update_rescue_opportunity':
        handleUpdateRescueOpportunity($inputData);
        break;
    case 'get_rescue_opportunities':
        handleGetRescueOpportunities($inputData);
        break;
    case 'delete_rescue_opportunity':
        handleDeleteRescueOpportunity($inputData);
        break;
    case 'apply_rescue_opportunity':
        handleApplyRescueOpportunity($inputData);
        break;
    case 'get_rescue_applications':
        handleGetRescueApplications($inputData);
        break;
    case 'delete_rescue_application':
        handleDeleteRescueApplication($inputData);
        break;
    case 'archive_rescue_opportunity':
        handleArchiveRescueOpportunity($inputData);
        break;

    // Messaging
    case 'send_whatsapp':
        handleSendWhatsapp($inputData);
        break;

    // Event analytics
    case 'get_event_analytics':
        handleGetEventAnalytics($inputData);
        break;

    // Servers Infrastructure
    case 'get_servers':
        handleGetServers($inputData);
        break;
    case 'save_server':
        handleSaveServer($inputData);
        break;
    case 'delete_server':
        handleDeleteServer($inputData);
        break;
    case 'ping_server':
        handlePingServer($inputData);
        break;

    default:
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid endpoint request."]);
        break;
}

// ---------------------------------------------------------------------------
// ZOOM TEST
// ---------------------------------------------------------------------------





// ---------------------------------------------------------------------------
// SUPABASE AUTH BRIDGE
// ---------------------------------------------------------------------------
// The app keeps public.users.id as the internal app user id, while Supabase
// Auth becomes the identity provider. These helpers map:
// auth.users.id -> public.users.auth_user_id -> public.users.id.






























// Flush the HTTP response to the client so slow best-effort work (e.g. outbound
// WhatsApp sends) doesn't delay the user-visible response. No-op without PHP-FPM.


// Signed server-side session helpers. The raw token is only stored in an
// HttpOnly cookie; Supabase stores hashes so a table leak does not expose
// reusable browser credentials.




































































// ── Login / activity tracking helpers ──
// These helpers are deliberately "safe": if the recommended schema has not
// been run yet, the app still logs the failure server-side and continues.







































// ---------------------------------------------------------------------------
// HOLY BOOKS / EBOOK LIBRARY
// ---------------------------------------------------------------------------
























































// ---------------------------------------------------------------------------
// SIGNUP
// ---------------------------------------------------------------------------


// Build and send the signup verification email (best-effort, returns the
// sendEmailMessage result array).


// Verify the emailed 6-digit code and, on success, create the real account.


// Regenerate and re-send the verification code for a pending signup.


// Create the real user + profile + session from a verified pending payload.
// Emits the same success response the original signup flow returned.


// ---------------------------------------------------------------------------
// MEMBER LOGIN
// ---------------------------------------------------------------------------




// ---------------------------------------------------------------------------
// ADMIN LOGIN — returns user + stats in one response
// ---------------------------------------------------------------------------












































































// ---------------------------------------------------------------------------
// STATS (standalone endpoint + shared helper)
// Uses HEAD + Prefer: count=exact to avoid fetching all rows
// ---------------------------------------------------------------------------




// ---------------------------------------------------------------------------
// PHOTO UPLOAD
// Uses PUT (not POST) — Supabase Storage REST requires PUT for uploads
// ---------------------------------------------------------------------------


// Helper: parse a Supabase public storage URL into bucket/path


// Helper: delete an object from Supabase Storage


// ---------------------------------------------------------------------------
// UPDATE PROFILE — called by submitMembership() and saveProfile()
// Updates the profiles row for the given user_id
// ---------------------------------------------------------------------------


// ---------------------------------------------------------------------------
// FAMILY TREE + HOROSCOPE BIRTH DETAILS
// ---------------------------------------------------------------------------


















// ============================================================
// POSTS
// ============================================================

// ============================================================
// SOCIAL DATA HELPERS
// ============================================================





















// SOCIAL BOOTSTRAP






























// Returns the caller's role in a group ('admin' | 'member') or null if not a member.




// ============================================================
// POSTS
// ============================================================

































// ============================================================
// COMMENTS
// ============================================================





// Edits a comment/reply. Ownership is enforced by filtering on user_id so a user
// can only modify their own comment. Replies (rows with a parent_id) edit exactly
// the same way as top-level comments.


// Soft-deletes a comment/reply (sets is_deleted = true). Ownership is enforced by
// the user_id filter. Works identically for replies-to-replies.




// ============================================================
// EVENTS
// ============================================================

































// ============================================================
// GROUPS
// ============================================================







// Add one or more people to a group. Any existing member can add others
// (WhatsApp-style). Returns the refreshed group with its members list.


// Promote a member to admin or demote an admin back to member. Admins only.


// Remove a member from a group. Admins only (members leave via leave_group).


// Prebuilt mandals (Yuva, Mahila, Senior, ...) are shared, global backend groups
// identified by a stable pack_key. Joining creates the group if it does not yet
// exist, then adds the caller as a member so group calls and chat work for real.




// Core "post one message to one group" logic, shared by single send and
// broadcast. Returns a result array instead of emitting an HTTP response so it
// can be called in a loop. On success: ['ok'=>true,'message'=>..,'notifications'=>..].
// On failure: ['ok'=>false,'code'=>int,'error'=>string,'reason'=>string].




// Broadcast: post one message to many groups/communities at once.








// Fetch a single group enriched with its members list (and the caller's role).




// ============================================================
// FRIENDS
// ============================================================











// ============================================================
// ZOOM CALLS
// ============================================================













































// ---------------------------------------------------------------------------
// MATRIMONIAL & MATCHMAKING
// ---------------------------------------------------------------------------
// Required Supabase tables (run this SQL in your Supabase SQL editor):
//
// CREATE TABLE playdate_profiles (
//   id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
//   user_id UUID REFERENCES users(id) ON DELETE CASCADE UNIQUE,
//   is_published BOOLEAN DEFAULT false,
//   height_cm INTEGER, weight_kg INTEGER, blood_group TEXT,
//   diet TEXT, complexion TEXT, about_self TEXT,
//   highest_education TEXT, occupation TEXT, annual_income TEXT,
//   current_city TEXT, current_country TEXT DEFAULT 'India',
//   gotra TEXT, rashi TEXT, nakshatra TEXT, mangalik TEXT,
//   birth_time TEXT, birth_place TEXT,
//   father_name TEXT, mother_name TEXT, siblings INTEGER DEFAULT 0,
//   native_place TEXT, about_family TEXT,
//   pref_age_min INTEGER, pref_age_max INTEGER,
//   pref_height_min INTEGER, pref_height_max INTEGER,
//   pref_education TEXT, pref_working_status TEXT,
//   privacy_settings JSONB DEFAULT '{"hidePhotos": false, "hideContact": true}',
//   created_at TIMESTAMPTZ DEFAULT now(),
//   updated_at TIMESTAMPTZ DEFAULT now()
// );
//
// CREATE TABLE playdate_interests (
//   id UUID DEFAULT gen_random_uuid() PRIMARY KEY,
//   from_user_id UUID REFERENCES users(id),
//   to_user_id UUID REFERENCES users(id),
//   status TEXT DEFAULT 'pending',
//   message TEXT,
//   created_at TIMESTAMPTZ DEFAULT now(),
//   responded_at TIMESTAMPTZ,
//   UNIQUE(from_user_id, to_user_id)
// );






// ---------------------------------------------------------------------------
// KUNDALI SCORING (PHP Translation)
// ---------------------------------------------------------------------------




// ---------------------------------------------------------------------------
// NEW MATCHMAKING ENDPOINTS
// ---------------------------------------------------------------------------




// Returns the full playdate pool: every signed-up member of the site
// (sourced from the `profiles` table) enriched with their playdate biodata
// where it exists. This replaces the old hard-coded placeholder profiles so the
// Discover / Search / Swipe tabs all browse real members. The result is shaped
// in the camelCase form the frontend playdate renderers expect.








// Records an advertising enquiry by dropping a notification into every active
// owner's inbox so the platform team can follow up with the enquirer.












// ─────────────────────────────────────────────────────────────────────────
// VERIFICATION REQUESTS
// ─────────────────────────────────────────────────────────────────────────







// ─────────────────────────────────────────────────────────────────────────
// PRIVACY SETTINGS (dedicated endpoints)
// ─────────────────────────────────────────────────────────────────────────





// ─────────────────────────────────────────────────────────────────────────
// WHATSAPP NUMBER LINKING + VERIFICATION
// The user's WhatsApp number lives in their profile (privacy_settings.whatsappNumber
// once verified, mirrored to profiles.mobile_number). A one-time code is sent over
// WhatsApp to prove ownership before the number is linked.
// ─────────────────────────────────────────────────────────────────────────

// Read the current privacy_settings object for a user (always an array).


// Merge $patch into the stored privacy_settings (preserving unrelated keys).






// ─────────────────────────────────────────────────────────────────────────
// VOLUNTEER MARKETPLACE
// ─────────────────────────────────────────────────────────────────────────










// Shape a DB row into the structure the frontend expects.












// ─────────────────────────────────────────────────────────────────────────
// MESSAGING
// ─────────────────────────────────────────────────────────────────────────

// WhatsApp Business Cloud API configuration, read from .env:
//   WHATSAPP_ACCESS_TOKEN          – permanent/temporary access token
//   WHATSAPP_PHONE_NUMBER_ID       – the "Phone number ID" of the sending number
//   WHATSAPP_BUSINESS_ACCOUNT_ID   – WABA id (used for template management/webhooks)
//   WHATSAPP_API_VERSION           – Graph API version (default v21.0)
//   WHATSAPP_DEFAULT_COUNTRY_CODE  – prepended to 10-digit national numbers (default 91)
//   WHATSAPP_DEFAULT_TEMPLATE      – optional approved template used for *proactive*
//                                    messages (outside the 24h customer-service window).
//                                    The notification text is passed as body param {{1}}.
//   WHATSAPP_DEFAULT_TEMPLATE_LANG – template language code (default en_US)


// True only when live credentials are present; otherwise sends are mocked/logged.


// Convert any user-entered number into the digits-only E.164 form the API expects
// (e.g. "098765 43210" → "919876543210"). National 10-digit numbers get the
// configured country code prepended.


// Build the opts used for *proactive* (business-initiated) messages. When a default
// approved template is configured we route through it (required by WhatsApp when the
// 24-hour customer-service window is closed); otherwise we fall back to plain text.


// Low-level sender. $opts:
//   template => approved template name (sends a template message instead of text)
//   lang     => template language code (default en_US)
//   params   => ordered body parameters ({{1}}, {{2}}, …)


// Look up a user's WhatsApp opt-in + number from their profile.


// Best-effort: send a WhatsApp message to a user. Honours their opt-in unless
// $force is true (used for transactional confirmations such as signup). Never throws.




// ─────────────────────────────────────────────────────────────────────────
// TRANSACTIONAL EMAIL  (SendPulse SMTP API — works on Render, port 443)
// Used ONLY for signup email verification codes. With no API credentials
// configured it falls back to a logged "mock" send so dev keeps working.
// Configure via env (set these in the Render dashboard):
//   SENDPULSE_CLIENT_ID     – SendPulse REST API ID (a.k.a. "API user ID").
//   SENDPULSE_CLIENT_SECRET – SendPulse REST API Secret. Without both of these,
//                             sends are mocked.
//   PAWCIRCLE_FROM_EMAIL       – From address. Must be a sender you've verified in
//                             your SendPulse account.
//   PAWCIRCLE_FROM_NAME        – From display name (default "PawCircle").
// ─────────────────────────────────────────────────────────────────────────


// Fetch a SendPulse OAuth access token (client_credentials grant). Tokens are
// short-lived; verification emails are infrequent so we just fetch a fresh one
// per send. Returns the bearer token string, or null on failure.


// Low-level transactional email send via SendPulse. Returns a result array and
// never throws or emits any HTTP response, so it can be reused by signup
// verification. Falls back to a logged "mock" send when no SendPulse
// credentials are configured.
//   => ['ok' => bool, 'mocked' => bool, 'message_id' => ?string, 'detail' => ?string]


// ─────────────────────────────────────────────────────────────────────────
// EVENT ANALYTICS
// ─────────────────────────────────────────────────────────────────────────



// ─────────────────────────────────────────────────────────────────────────
// SERVER INFRASTRUCTURE MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────








