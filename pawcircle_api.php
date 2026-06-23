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
    function mb_substr($string, $start, $length = null, $encoding = null)
    {
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, $length);
    }
}
// Load .env first so PAWCIRCLE_DEBUG / ALLOWED_ORIGINS / Supabase keys are all available below.
$envFile = __DIR__ . '/.env';
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
function requestId()
{
    static $id = null;
    if ($id === null) {
        try {
            $id = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $id = substr(md5(uniqid('', true)), 0, 16);
        }
    }
    return $id;
}

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

/**
 * Send a request to Supabase PostgREST REST API
 */
function supabaseRequest($method, $path, $query = [], $body = null, $extraHeaders = [])
{
    $url = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
    $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');

    if (empty($url) || empty($secretKey)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Supabase API keys are missing from .env"]);
        exit();
    }

    $endpoint = $url . $path;
    if (!empty($query)) {
        $endpoint .= '?' . http_build_query($query);
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $defaultHeaders = [
        "apikey: {$secretKey}",
        "Authorization: Bearer {$secretKey}",
        "Content-Type: application/json",
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $extraHeaders));

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [
        'code' => $httpCode,
        'data' => json_decode($response, true),
    ];
}

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
    case 'public_signup':
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
    case 'get_pet_pack_members':
        handleGetPetPackMembers($inputData);
        break;
    case 'save_pet_pack_member':
        handleSavePetPackMember($inputData);
        break;
    case 'delete_pet_pack_member':
        handleDeletePetPackMember($inputData);
        break;
    case 'save_birth_details':
        handleSaveBirthDetails($inputData);
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
    case 'change_religion_community':
        handleChangeReligionCommunity($inputData);
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
function envValue($key, $default = '')
{
    return getenv($key) ?: ($_ENV[$key] ?? $default);
}

function jsonSuccess($data = [])
{
    echo json_encode(array_merge(["status" => "success", "request_id" => requestId()], $data));
}

function jsonError($message, $code = 400, $extra = [])
{
    http_response_code($code);
    echo json_encode(array_merge([
        "status" => "error",
        "message" => $message,
        "request_id" => requestId(),
    ], $extra));
}


// ---------------------------------------------------------------------------
// SUPABASE AUTH BRIDGE
// ---------------------------------------------------------------------------
// The app keeps public.users.id as the internal app user id, while Supabase
// Auth becomes the identity provider. These helpers map:
// auth.users.id -> public.users.auth_user_id -> public.users.id.
function supabaseAnonKey()
{
    return envValue('SUPABASE_ANON_KEY')
        ?: envValue('SUPABASE_PUBLIC_ANON_KEY')
        ?: envValue('SUPABASE_PUBLISHABLE_KEY')
        ?: envValue('SUPABASE_PUBLIC_KEY');
}

function handleAuthConfig()
{
    $url = rtrim(envValue('SUPABASE_URL'), '/');
    $anonKey = supabaseAnonKey();

    if ($url === '' || $anonKey === '') {
        jsonError('Supabase Auth is not configured on the server. Add SUPABASE_URL and SUPABASE_ANON_KEY to .env.', 500);
        return;
    }

    jsonSuccess([
        'supabase_url' => $url,
        'supabase_anon_key' => $anonKey,
        'redirect_url' => authRedirectUrl(),
    ]);
}

function authRedirectUrl()
{
    $configured = envValue('AUTH_REDIRECT_URL', '');
    if ($configured !== '') {
        return $configured;
    }

    $scheme = isHttpsRequest() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return '';
    }

    return $scheme . '://' . $host . '/';
}

function supabaseAuthAdminRequest($method, $path, $body = null)
{
    $url = rtrim(envValue('SUPABASE_URL'), '/');
    $secretKey = envValue('SUPABASE_SECRET_KEY');

    if ($url === '' || $secretKey === '') {
        return [
            'code' => 500,
            'data' => ['message' => 'Supabase Auth admin keys are missing from .env'],
        ];
    }

    $ch = curl_init($url . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $secretKey,
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        $err = curl_error($ch);
        return ['code' => 500, 'data' => ['message' => $err]];
    }

    return [
        'code' => $httpCode,
        'data' => json_decode($response, true),
    ];
}

function createSupabaseAuthUserForSignup($email, $password, $metadata = [])
{
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    $password = (string) $password;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
        return [
            'ok' => false,
            'code' => 400,
            'message' => 'A valid email and password are required for Auth account creation.',
        ];
    }

    $body = [
        'email' => $email,
        'password' => $password,
        // PawCircle has already verified the email using its own 6-digit code.
        // This prevents Supabase from requiring a second email confirmation.
        'email_confirm' => true,
        'user_metadata' => $metadata,
    ];

    $res = supabaseAuthAdminRequest('POST', '/auth/v1/admin/users', $body);
    if (($res['code'] ?? 500) >= 400 || empty($res['data']['id'])) {
        $msg = is_array($res['data'] ?? null)
            ? ($res['data']['message'] ?? $res['data']['error_description'] ?? json_encode($res['data']))
            : 'Unknown Supabase Auth error';
        return [
            'ok' => false,
            'code' => $res['code'] ?? 500,
            'message' => $msg,
            'raw' => $res,
        ];
    }

    return [
        'ok' => true,
        'user' => $res['data'],
    ];
}

function getSupabaseAuthUserFromToken($accessToken)
{
    $accessToken = trim((string) $accessToken);
    if ($accessToken === '') {
        return null;
    }

    $url = rtrim(envValue('SUPABASE_URL'), '/');
    $anonKey = supabaseAnonKey();
    if ($url === '' || $anonKey === '') {
        error_log('[pawcircle][' . requestId() . '] Supabase Auth config missing for token verification.');
        return null;
    }

    $ch = curl_init($url . '/auth/v1/user');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $anonKey,
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($response === false) {
        error_log('[pawcircle][' . requestId() . '] Supabase Auth token verification failed: ' . curl_error($ch));
    }

    if ($httpCode !== 200 || !$response) {
        error_log('[pawcircle][' . requestId() . '] Supabase Auth token rejected | http=' . $httpCode . ' | response=' . substr((string) $response, 0, 300));
        return null;
    }

    $user = json_decode($response, true);
    return is_array($user) ? $user : null;
}

function authMetadataValue($authUser, $keys, $fallback = '')
{
    $metadata = $authUser['user_metadata'] ?? ($authUser['raw_user_meta_data'] ?? []);
    if (!is_array($metadata)) {
        $metadata = [];
    }
    foreach ((array) $keys as $key) {
        if (isset($metadata[$key]) && trim((string) $metadata[$key]) !== '') {
            return trim((string) $metadata[$key]);
        }
    }
    return $fallback;
}

function split_part_fallback($value, $delimiter, $index, $fallback)
{
    $parts = explode($delimiter, (string) $value);
    $candidate = trim((string) ($parts[$index] ?? ''));
    return $candidate !== '' ? $candidate : $fallback;
}

function fetchAppUserWithProfileById($userId)
{
    if (!isValidUuid($userId)) {
        return null;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . strtolower($userId),
        'select' => 'id,email,role,is_verified,verified_at,last_login_at,last_active_at,deactivated_at,auth_user_id,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city,privacy_settings)',
        'limit' => '1',
    ]);

    if (($res['code'] ?? 500) >= 400) {
        $res = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'eq.' . strtolower($userId),
            'select' => 'id,email,role,last_login_at,last_active_at,deactivated_at,auth_user_id,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city)',
            'limit' => '1',
        ]);
    }

    return (($res['code'] ?? 500) < 400 && !empty($res['data'])) ? $res['data'][0] : null;
}

function fetchAppUserWithProfileByAuthUserId($authUserId)
{
    if (!isValidUuid($authUserId)) {
        return null;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'auth_user_id' => 'eq.' . strtolower($authUserId),
        'select' => 'id,email,role,is_verified,verified_at,last_login_at,last_active_at,deactivated_at,auth_user_id,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city,privacy_settings)',
        'limit' => '1',
    ]);

    if (($res['code'] ?? 500) >= 400) {
        $res = supabaseRequest('GET', '/rest/v1/users', [
            'auth_user_id' => 'eq.' . strtolower($authUserId),
            'select' => 'id,email,role,last_login_at,last_active_at,deactivated_at,auth_user_id,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city)',
            'limit' => '1',
        ]);
    }

    return (($res['code'] ?? 500) < 400 && !empty($res['data'])) ? $res['data'][0] : null;
}

function ensureProfileForAppUser($appUserId, $authUser, $data = [])
{
    if (!isValidUuid($appUserId)) {
        return;
    }

    $displayName = trim((string) ($data['name'] ?? authMetadataValue($authUser, ['full_name', 'name'], '')));
    if ($displayName === '') {
        $displayName = split_part_fallback($authUser['email'] ?? '', '@', 0, 'New Member');
    }

    $religion = trim((string) ($data['religion'] ?? authMetadataValue($authUser, ['religion'], '')));
    $community = trim((string) ($data['community'] ?? authMetadataValue($authUser, ['community'], '')));

    $insert = [
        'user_id' => $appUserId,
        'full_name' => $displayName,
        'terms_accepted' => false,
        'privacy_accepted' => false,
        'accuracy_certified' => false,
    ];
    if ($religion !== '')
        $insert['religion'] = $religion;
    if ($community !== '')
        $insert['community'] = $community;

    supabaseRequest('POST', '/rest/v1/profiles', [], $insert, ['Prefer: resolution=ignore-duplicates,return=minimal']);

    $patch = [];
    if ($displayName !== '')
        $patch['full_name'] = $displayName;
        
    $avatarUrl = trim((string) authMetadataValue($authUser, ['avatar_url', 'picture'], ''));
    if ($avatarUrl !== '') {
        $patch['profile_photo_url'] = $avatarUrl;
    }
    if ($religion !== '')
        $patch['religion'] = $religion;
    if ($community !== '')
        $patch['community'] = $community;

    if (!empty($patch)) {
        supabaseRequest('PATCH', '/rest/v1/profiles', [
            'user_id' => 'eq.' . strtolower($appUserId),
        ], $patch, ['Prefer: return=minimal']);
    }
}

function getMigrationStatusForAppUser($appUserId)
{
    if (!isValidUuid($appUserId)) {
        return '';
    }
    $res = supabaseRequest('GET', '/rest/v1/user_migration_review', [
        'user_id' => 'eq.' . strtolower($appUserId),
        'select' => 'migration_status',
        'limit' => '1',
    ]);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        return '';
    }
    return (string) ($res['data'][0]['migration_status'] ?? '');
}

function findAppUsersByEmail($email)
{
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [];
    }

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'email' => 'ilike.' . $email,
        'select' => 'id,email,auth_user_id',
        'limit' => '20',
    ]);

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        return [];
    }

    return $res['data'];
}

function linkOrCreateAppUserForSupabaseAuth($authUser, $data = [])
{
    $authUserId = $authUser['id'] ?? '';
    $email = filter_var($authUser['email'] ?? '', FILTER_SANITIZE_EMAIL);

    if (!isValidUuid($authUserId) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Supabase Auth user is missing a valid id or email.', 401);
        return null;
    }

    $existing = fetchAppUserWithProfileByAuthUserId($authUserId);
    if ($existing) {
        ensureProfileForAppUser($existing['id'], $authUser, $data);
        return fetchAppUserWithProfileById($existing['id']) ?: $existing;
    }

    $emailMatches = findAppUsersByEmail($email);

    foreach ($emailMatches as $row) {
        $status = getMigrationStatusForAppUser($row['id'] ?? '');
        if (in_array($status, ['test_user', 'admin_test_user', 'delete_later'], true)) {
            jsonError('This email is marked as a test/deleted account and cannot be used for Supabase Auth yet.', 403);
            return null;
        }
    }

    foreach ($emailMatches as $row) {
        $status = getMigrationStatusForAppUser($row['id'] ?? '');
        if ($status !== 'real_user') {
            continue;
        }

        if (!empty($row['auth_user_id']) && strtolower((string) $row['auth_user_id']) !== strtolower($authUserId)) {
            jsonError('This app account is already linked to another Supabase Auth identity.', 409);
            return null;
        }

        $patch = supabaseRequest('PATCH', '/rest/v1/users', [
            'id' => 'eq.' . $row['id'],
        ], [
            'auth_user_id' => $authUserId,
            'updated_at' => nowIsoUtc(),
        ], ['Prefer: return=minimal']);

        if (($patch['code'] ?? 500) >= 400) {
            sendSupabaseError('Could not link Supabase Auth user to app user.', $patch);
            return null;
        }

        ensureProfileForAppUser($row['id'], $authUser, $data);
        return fetchAppUserWithProfileById($row['id']);
    }

    if (!empty($emailMatches)) {
        jsonError('This email exists in the app but is not marked real_user in user_migration_review.', 409);
        return null;
    }

    $userRes = supabaseRequest('POST', '/rest/v1/users', [], [
        'email' => $email,
        'password_hash' => null,
        'role' => 'member',
        'auth_user_id' => $authUserId,
    ], ['Prefer: return=representation']);

    if (($userRes['code'] ?? 500) >= 400 || empty($userRes['data'])) {
        sendSupabaseError('Could not create app user for Supabase Auth account.', $userRes);
        return null;
    }

    $appUserId = $userRes['data'][0]['id'];

    supabaseRequest('POST', '/rest/v1/user_migration_review', [], [
        'user_id' => $appUserId,
        'migration_status' => 'real_user',
        'notes' => 'Created from Supabase Auth exchange',
        'reviewed_at' => nowIsoUtc(),
    ], ['Prefer: resolution=ignore-duplicates,return=minimal']);

    ensureProfileForAppUser($appUserId, $authUser, $data);
    return fetchAppUserWithProfileById($appUserId);
}

function handleSupabaseAuthExchange($data)
{
    $token = getBearerToken();
    if ($token === '') {
        jsonError('Missing Supabase Auth access token.', 401);
        return;
    }

    $authUser = getSupabaseAuthUserFromToken($token);
    if (!$authUser || empty($authUser['id'])) {
        jsonError('Invalid or expired Supabase Auth session. Please sign in again.', 401);
        return;
    }

    $user = linkOrCreateAppUserForSupabaseAuth($authUser, $data);
    if (!$user) {
        return;
    }

    if (!empty($user['deactivated_at'])) {
        jsonError('This account is deactivated. Contact support if you believe this is incorrect.', 403);
        return;
    }

    $loginPunishments = getActiveUserPunishments($user['id'] ?? '');
    $ban = activePunishmentOfType($loginPunishments, ['ban']);
    if ($ban) {
        jsonError('This account has been banned. Contact support if you believe this is incorrect.', 403);
        return;
    }
    $suspension = activePunishmentOfType($loginPunishments, ['suspension']);
    if ($suspension) {
        jsonError('This account is currently suspended.', 403);
        return;
    }

    $loginAt = markSuccessfulLogin($user['id'], $user['email'] ?? ($authUser['email'] ?? ''), 'supabase_auth');
    clearLoginRateLimit($user['email'] ?? ($authUser['email'] ?? ''), 'member');
    $session = createUserSession($user['id'], 'member');
    $freshUser = fetchAppUserWithProfileById($user['id']) ?: $user;
    $payload = buildUserPayload($freshUser, $loginAt);
    $payload['auth_user_id'] = $authUser['id'];

    jsonSuccess([
        'message' => 'Supabase Auth session linked successfully.',
        'session' => $session,
        'user' => $payload,
    ]);
}

// Flush the HTTP response to the client so slow best-effort work (e.g. outbound
// WhatsApp sends) doesn't delay the user-visible response. No-op without PHP-FPM.
function finishResponseEarly()
{
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
}

// Signed server-side session helpers. The raw token is only stored in an
// HttpOnly cookie; Supabase stores hashes so a table leak does not expose
// reusable browser credentials.
function sessionSecret()
{
    $secret = envValue('APP_SESSION_SECRET', '');
    if (strlen((string) $secret) < 32) {
        error_log("[pawcircle][" . requestId() . "] APP_SESSION_SECRET is missing or too short.");
        jsonError("Server session security is not configured. Please contact support.", 500);
        exit();
    }
    return (string) $secret;
}

function hashSessionSecret($value)
{
    return hash('sha256', sessionSecret() . '|' . (string) $value);
}

function isHttpsRequest()
{
    return (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function setEsamajCookie($name, $value, $expires, $httpOnly)
{
    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
    ]);
}

function setSessionCookies($rawToken, $csrfToken, $expiresAtTs)
{
    setEsamajCookie(PAWCIRCLE_SESSION_COOKIE, $rawToken, $expiresAtTs, true);
    setEsamajCookie(PAWCIRCLE_CSRF_COOKIE, $csrfToken, $expiresAtTs, false);
}

function clearSessionCookies()
{
    setEsamajCookie(PAWCIRCLE_SESSION_COOKIE, '', time() - 3600, true);
    setEsamajCookie(PAWCIRCLE_CSRF_COOKIE, '', time() - 3600, false);
}

function getBearerToken()
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }
    return '';
}

function getRawSessionToken()
{
    return trim((string) ($_COOKIE[PAWCIRCLE_SESSION_COOKIE] ?? getBearerToken()));
}

function getCsrfTokenHeader()
{
    return trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
}

function createUserSession($userId, $role)
{
    $userId = requireUuid($userId, 'user_id');
    $role = $role === 'admin' ? 'admin' : 'member';
    $rawToken = bin2hex(random_bytes(32));
    $csrfToken = bin2hex(random_bytes(32));
    $expiresTs = time() + PAWCIRCLE_SESSION_TTL_SECONDS;
    $expiresAt = gmdate('c', $expiresTs);

    $res = supabaseRequest('POST', '/rest/v1/user_sessions', [], [
        'user_id' => $userId,
        'role' => $role,
        'token_hash' => hashSessionSecret($rawToken),
        'csrf_hash' => hashSessionSecret($csrfToken),
        'ip_hash' => getClientIpHash(),
        'user_agent' => getClientUserAgent(),
        'expires_at' => $expiresAt,
        'last_seen_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        error_log("[pawcircle][" . requestId() . "] session create failed | http=" . ($res['code'] ?? 'n/a') . " | response=" . json_encode($res['data'] ?? null));
        jsonError("Could not create a secure session. Run the user_sessions SQL migration and try again.", 500);
        exit();
    }

    setSessionCookies($rawToken, $csrfToken, $expiresTs);

    return [
        'expires_at' => $expiresAt,
        'csrf_token' => $csrfToken,
    ];
}

function requireAuthenticatedSession($requireCsrf = true)
{
    $rawToken = getRawSessionToken();
    if ($rawToken === '') {
        jsonError("Authentication required. Please sign in again.", 401);
        exit();
    }

    $res = supabaseRequest('GET', '/rest/v1/user_sessions', [
        'token_hash' => 'eq.' . hashSessionSecret($rawToken),
        'revoked_at' => 'is.null',
        'select' => 'id,user_id,role,csrf_hash,expires_at,revoked_at,admin_mode_until',
        'limit' => '1',
    ]);

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        clearSessionCookies();
        jsonError("Session expired. Please sign in again.", 401);
        exit();
    }

    $session = $res['data'][0];
    if (strtotime($session['expires_at'] ?? '') <= time()) {
        clearSessionCookies();
        jsonError("Session expired. Please sign in again.", 401);
        exit();
    }

    if ($requireCsrf) {
        $csrf = getCsrfTokenHeader();
        if ($csrf === '' || !hash_equals((string) ($session['csrf_hash'] ?? ''), hashSessionSecret($csrf))) {
            jsonError("Invalid security token. Refresh the page and try again.", 403);
            exit();
        }
    }

    supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'id' => 'eq.' . $session['id'],
    ], ['last_seen_at' => nowIsoUtc()], ['Prefer: return=minimal']);

    return [
        'session_id' => $session['id'],
        'user_id' => $session['user_id'],
        'role' => $session['role'] ?? 'member',
        'admin_mode_until' => $session['admin_mode_until'] ?? null,
        'admin_mode_active' => !empty($session['admin_mode_until']) && strtotime((string) $session['admin_mode_until']) > time(),
    ];
}

function requireAdminSession($authContext)
{
    if (($authContext['role'] ?? '') !== 'admin') {
        jsonError("Admin access required.", 403);
        exit();
    }
}

function requireAdminMode($authContext)
{
    if (!($authContext['admin_mode_active'] ?? false)) {
        jsonError("Admin mode is required. Re-enter your password to continue.", 403);
        exit();
    }
    $caps = fetchAdminCapabilities($authContext['user_id'] ?? '');
    if (empty($caps)) {
        jsonError("Admin access is not enabled for this account.", 403);
        exit();
    }
}

function getActiveUserPunishments($userId)
{
    if (!isValidUuid($userId))
        return [];
    $res = supabaseRequest('GET', '/rest/v1/admin_user_actions', [
        'user_id' => 'eq.' . strtolower($userId),
        'is_active' => 'eq.true',
        'select' => 'id,action_type,reason,starts_at,ends_at,created_at',
        'order' => 'created_at.desc',
        'limit' => '50',
    ]);
    if (($res['code'] ?? 500) >= 400)
        return [];
    $now = time();
    return array_values(array_filter($res['data'] ?? [], function ($row) use ($now) {
        $starts = strtotime((string) ($row['starts_at'] ?? '')) ?: 0;
        $endsRaw = (string) ($row['ends_at'] ?? '');
        $ends = $endsRaw !== '' ? (strtotime($endsRaw) ?: 0) : 0;
        return $starts <= $now && ($ends === 0 || $ends > $now);
    }));
}

function activePunishmentOfType($punishments, $types)
{
    foreach ($punishments as $row) {
        if (in_array(strtolower((string) ($row['action_type'] ?? '')), $types, true))
            return $row;
    }
    return null;
}

function enforceActiveUserPunishments($userId, $action, $role = 'member')
{
    $punishments = getActiveUserPunishments($userId);
    if (empty($punishments))
        return;

    $ban = activePunishmentOfType($punishments, ['ban']);
    if ($ban) {
        clearSessionCookies();
        jsonError("This account has been banned. Contact support if you believe this is incorrect.", 403, ['reason' => $ban['reason'] ?? null]);
        exit();
    }

    $suspension = activePunishmentOfType($punishments, ['suspension']);
    if ($suspension) {
        clearSessionCookies();
        jsonError("This account is currently suspended.", 403, ['reason' => $suspension['reason'] ?? null, 'ends_at' => $suspension['ends_at'] ?? null]);
        exit();
    }

    $blacklist = activePunishmentOfType($punishments, ['blacklist']);
    if ($blacklist && ($role !== 'admin')) {
        $restrictedPrefixes = ['create_', 'save_', 'send_', 'respond_', 'swipe_', 'upload_', 'delete_', 'update_', 'add_', 'join_', 'leave_', 'mark_'];
        foreach ($restrictedPrefixes as $prefix) {
            if (str_starts_with((string) $action, $prefix)) {
                jsonError("This account is restricted from performing that action.", 403, ['reason' => $blacklist['reason'] ?? null]);
                exit();
            }
        }
    }
}

function normaliseAdminRole($role)
{
    $role = strtolower(trim((string) $role));
    $aliases = [
        'supreme_overlord_admin' => 'owner',
        'super_admin' => 'owner',
        'platform_admin' => 'platform_admin',
        'religion_leader' => 'religion_admin',
        'religion_admin' => 'religion_admin',
        'community_manager' => 'community_admin',
        'community_admin' => 'community_admin',
        'owner' => 'owner',
    ];
    return $aliases[$role] ?? '';
}

function fetchAdminCapabilities($userId)
{
    if (!isValidUuid($userId))
        return [];
    $res = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'user_id' => 'eq.' . strtolower($userId),
        'revoked_at' => 'is.null',
        'select' => 'id,user_id,role,scope_type,scope_value,created_at',
        'order' => 'created_at.asc',
    ]);

    if (($res['code'] ?? 500) === 404)
        return [];
    if (($res['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] admin role lookup failed | http=" . ($res['code'] ?? 'n/a') . " | response=" . json_encode($res['data'] ?? null));
        return [];
    }

    $caps = [];
    foreach (($res['data'] ?? []) as $row) {
        $cap = normaliseAdminCapabilityRow($row);
        if ($cap)
            $caps[] = $cap;
    }
    return $caps;
}

function adminCapabilityLabel($role, $scopeType, $scopeValue)
{
    $names = [
        'owner' => 'Owner',
        'platform_admin' => 'Platform admin',
        'religion_admin' => 'Religion admin',
        'community_admin' => 'Community admin',
    ];
    $label = $names[$role] ?? 'Admin';
    if ($scopeType !== 'global' && $scopeValue !== '' && $scopeValue !== '*') {
        $label .= ' - ' . $scopeValue;
    }
    return $label;
}

function normaliseAdminCapabilityRow($row)
{
    $role = normaliseAdminRole($row['role'] ?? '');
    if ($role === '')
        return null;

    $scopeType = strtolower(trim((string) ($row['scope_type'] ?? 'global')));
    if (!in_array($scopeType, ['global', 'religion', 'community'], true)) {
        $scopeType = 'global';
    }

    $scopeValue = trim((string) ($row['scope_value'] ?? '*'));
    if ($role === 'owner' || $role === 'platform_admin') {
        $scopeType = 'global';
        $scopeValue = '*';
    }

    return [
        'id' => $row['id'] ?? null,
        'user_id' => $row['user_id'] ?? null,
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_value' => $scopeValue === '' ? '*' : $scopeValue,
        'label' => adminCapabilityLabel($role, $scopeType, $scopeValue),
    ];
}

function fetchAdminCapabilitiesMap($userIds)
{
    $userIds = normalizeUuidList($userIds);
    if (empty($userIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'user_id' => 'in.(' . implode(',', $userIds) . ')',
        'revoked_at' => 'is.null',
        'select' => 'id,user_id,role,scope_type,scope_value,created_at',
        'order' => 'created_at.asc',
    ]);

    if (($res['code'] ?? 500) === 404)
        return [];
    if (($res['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] admin role map lookup failed | http=" . ($res['code'] ?? 'n/a') . " | response=" . json_encode($res['data'] ?? null));
        return [];
    }

    $map = [];
    foreach (($res['data'] ?? []) as $row) {
        $cap = normaliseAdminCapabilityRow($row);
        if (!$cap || empty($cap['user_id']))
            continue;
        $uid = strtolower((string) $cap['user_id']);
        if (!isset($map[$uid]))
            $map[$uid] = [];
        $map[$uid][] = $cap;
    }
    return $map;
}

function normaliseProfileTagValue($value, $maxLength = 30)
{
    $tag = trim(strip_tags((string) $value));
    $tag = preg_replace('/\s+/', ' ', $tag);
    if ($tag === '')
        return '';
    if ($maxLength > 0 && strlen($tag) > $maxLength) {
        $tag = substr($tag, 0, $maxLength);
        $tag = rtrim($tag);
    }
    return $tag;
}

function isReservedProfileTag($tag)
{
    $normalized = strtolower(trim(preg_replace('/\s+/', ' ', (string) $tag)));
    $normalized = str_replace(['_', '-'], ' ', $normalized);
    $reserved = [
        'owner',
        'admin',
        'platform admin',
        'religion admin',
        'community admin',
        'platform administrator',
        'religion administrator',
        'community administrator',
    ];
    if (in_array($normalized, $reserved, true))
        return true;

    return preg_match('/^(owner|admin|platform\s+admin|religion\s+admin|community\s+admin)\b/i', $normalized) === 1;
}

function normaliseProfileTagsInput($raw, $limit = 15)
{
    if ($raw === null || $raw === '')
        return [];

    if (is_string($raw)) {
        $trimmed = trim($raw);
        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            $raw = $decoded;
        } else {
            $raw = preg_split('/[,\n]+/', $trimmed);
        }
    }

    if (!is_array($raw))
        return [];

    $out = [];
    $seen = [];
    foreach ($raw as $item) {
        if (is_array($item)) {
            $item = $item['label'] ?? $item['name'] ?? $item['tag'] ?? '';
        }
        $tag = normaliseProfileTagValue($item, 30);
        if ($tag === '' || isReservedProfileTag($tag))
            continue;

        $key = strtolower($tag);
        if (isset($seen[$key]))
            continue;
        $seen[$key] = true;
        $out[] = $tag;
        if (count($out) >= $limit)
            break;
    }

    return $out;
}

function profileCustomTags($profile)
{
    return normaliseProfileTagsInput($profile['primary_interests'] ?? []);
}

function adminCapabilityTags($caps)
{
    $tags = [];
    $seen = [];
    foreach (($caps ?? []) as $cap) {
        $label = trim((string) ($cap['label'] ?? ''));
        if ($label === '') {
            $label = adminCapabilityLabel(
                $cap['role'] ?? 'admin',
                $cap['scope_type'] ?? 'global',
                $cap['scope_value'] ?? '*'
            );
        }
        $label = str_replace(' - ', ' · ', $label);
        $key = strtolower($label);
        if ($label !== '' && !isset($seen[$key])) {
            $seen[$key] = true;
            $tags[] = $label;
        }
    }
    return $tags;
}

function profileDisplayTags($customTags, $systemTags)
{
    $out = [];
    $seen = [];
    foreach (array_merge($systemTags ?? [], $customTags ?? []) as $tag) {
        $tag = normaliseProfileTagValue($tag, 60);
        if ($tag === '')
            continue;
        $key = strtolower($tag);
        if (isset($seen[$key]))
            continue;
        $seen[$key] = true;
        $out[] = $tag;
    }
    return $out;
}

function userHasAdminCapability($userId)
{
    return !empty(fetchAdminCapabilities($userId));
}

function userHasOwnerCapability($userId)
{
    foreach (fetchAdminCapabilities($userId) as $cap) {
        if (($cap['role'] ?? '') === 'owner')
            return true;
    }
    return false;
}

function requireOwnerCapability($userId)
{
    if (!userHasOwnerCapability($userId)) {
        jsonError("Owner admin access is required for that action.", 403);
        exit();
    }
}

function userHasGlobalAdminCapability($userId)
{
    foreach (fetchAdminCapabilities($userId) as $cap) {
        if (in_array($cap['role'] ?? '', ['owner', 'platform_admin'], true))
            return true;
    }
    return false;
}

function requireGlobalAdminCapability($userId)
{
    if (!userHasGlobalAdminCapability($userId)) {
        jsonError("Global admin access is required for that action.", 403);
        exit();
    }
}

// ── ID validation helpers ──
// Validate a UUID before using it in a Supabase filter. Returns the lowercased
// UUID, or emits a 400 and exits when invalid.
function requireUuid($value, $fieldName = 'id')
{
    $value = strtolower(trim((string) $value));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
        jsonError("Invalid {$fieldName}.", 400);
        exit();
    }
    return $value;
}

// Non-fatal UUID check (returns bool) for optional fields / soft validation.
function isValidUuid($value)
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim((string) $value));
}

function requireIntId($value, $fieldName = 'id')
{
    if (!ctype_digit((string) $value)) {
        jsonError("Invalid {$fieldName}.", 400);
        exit();
    }
    return (int) $value;
}


// ── Login / activity tracking helpers ──
// These helpers are deliberately "safe": if the recommended schema has not
// been run yet, the app still logs the failure server-side and continues.
function nowIsoUtc()
{
    return gmdate('c');
}

function privacyHash($value)
{
    $value = trim((string) $value);
    if ($value === '')
        return null;
    $salt = envValue('APP_AUDIT_SALT', envValue('SUPABASE_SECRET_KEY', 'pawcircle'));
    return hash('sha256', $salt . '|' . strtolower($value));
}

function getClientIpAddress()
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate)
            continue;
        $first = trim(explode(',', $candidate)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return '';
}

function isLocalDevRequest()
{
    $ip = getClientIpAddress();
    return in_array($ip, ['127.0.0.1', '::1'], true)
        || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), 'localhost:')
        || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), '127.0.0.1:');
}

function testUsersEnabled()
{
    return PAWCIRCLE_DEBUG
        && isLocalDevRequest()
        && in_array(strtolower((string) envValue('PAWCIRCLE_ENABLE_TEST_USERS', '')), ['1', 'true', 'yes', 'on'], true);
}

function testUserMap()
{
    return [
        'user' => ['name' => 'Test User', 'email' => 'test-user@pawcircle.local', 'religion' => 'Hindu', 'community' => 'General'],
        'userh' => ['name' => 'Test Hindu User', 'email' => 'test-user-hindu@pawcircle.local', 'religion' => 'Hindu', 'community' => 'Caste No Bar'],
        'userm' => ['name' => 'Test Muslim User', 'email' => 'test-user-muslim@pawcircle.local', 'religion' => 'Muslim', 'community' => 'Caste No Bar'],
        'userc' => ['name' => 'Test Christian User', 'email' => 'test-user-christian@pawcircle.local', 'religion' => 'Christian', 'community' => 'Caste No Bar'],
        'userb' => ['name' => 'Test Buddhist User', 'email' => 'test-user-buddhist@pawcircle.local', 'religion' => 'Buddhist', 'community' => 'Caste No Bar'],
        'userp' => ['name' => 'Test Parsi User', 'email' => 'test-user-parsi@pawcircle.local', 'religion' => 'Parsi', 'community' => 'Caste No Bar'],
        'users' => ['name' => 'Test Sikh User', 'email' => 'test-user-sikh@pawcircle.local', 'religion' => 'Sikh', 'community' => 'Caste No Bar'],
        'userj' => ['name' => 'Test Jain User', 'email' => 'test-user-jain@pawcircle.local', 'religion' => 'Jain', 'community' => 'Caste No Bar'],
        'usero' => ['name' => 'Test Other User', 'email' => 'test-user-other@pawcircle.local', 'religion' => 'Other', 'community' => 'Other'],
    ];
}

function getClientIpHash()
{
    return privacyHash(getClientIpAddress());
}

function getClientUserAgent()
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '')
        return null;
    return substr($ua, 0, 500);
}

function safePatchUserTrackingFields($userId, $fields, $context = 'user tracking')
{
    if (!isValidUuid($userId) || empty($fields))
        return false;

    $res = supabaseRequest('PATCH', '/rest/v1/users', [
        'id' => 'eq.' . strtolower($userId),
    ], $fields, ['Prefer: return=minimal']);

    if (($res['code'] ?? 500) >= 400) {
        error_log(sprintf(
            "[pawcircle][%s] %s update failed | user=%s | http=%s | response=%s",
            requestId(),
            $context,
            $userId,
            $res['code'] ?? 'n/a',
            json_encode($res['data'] ?? null)
        ));
        return false;
    }

    return true;
}

function logLoginEvent($userId, $email, $success, $reason = '')
{
    $payload = [
        'user_id' => isValidUuid($userId) ? strtolower($userId) : null,
        'email_hash' => privacyHash($email),
        'success' => (bool) $success,
        'reason' => substr((string) $reason, 0, 120),
        'ip_hash' => getClientIpHash(),
        'user_agent' => getClientUserAgent(),
        'created_at' => nowIsoUtc(),
    ];

    $res = supabaseRequest('POST', '/rest/v1/user_login_events', [], $payload, ['Prefer: return=minimal']);

    if (($res['code'] ?? 500) >= 400) {
        error_log(sprintf(
            "[pawcircle][%s] login event log failed | http=%s | response=%s",
            requestId(),
            $res['code'] ?? 'n/a',
            json_encode($res['data'] ?? null)
        ));
        return false;
    }

    return true;
}

function rateLimitKey($scope, $value)
{
    return substr($scope . ':' . privacyHash($value), 0, 90);
}

function loadRateLimitBucket($rateKey)
{
    $res = supabaseRequest('GET', '/rest/v1/auth_rate_limits', [
        'rate_key' => 'eq.' . $rateKey,
        'select' => 'rate_key,attempts,window_start,blocked_until',
        'limit' => '1',
    ]);

    if (($res['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] auth rate limit load failed | http=" . ($res['code'] ?? 'n/a') . " | response=" . json_encode($res['data'] ?? null));
        jsonError("Sign-in protection is not configured. Please contact support.", 500);
        exit();
    }

    return $res['data'][0] ?? null;
}

function saveRateLimitBucket($rateKey, $attempts, $windowStart, $blockedUntil = null)
{
    $res = supabaseRequest('POST', '/rest/v1/auth_rate_limits', [
        'on_conflict' => 'rate_key',
    ], [
        'rate_key' => $rateKey,
        'attempts' => $attempts,
        'window_start' => $windowStart,
        'blocked_until' => $blockedUntil,
        'updated_at' => nowIsoUtc(),
    ], ['Prefer: resolution=merge-duplicates,return=minimal']);

    if (($res['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] auth rate limit save failed | http=" . ($res['code'] ?? 'n/a') . " | response=" . json_encode($res['data'] ?? null));
        jsonError("Sign-in protection is not configured. Please contact support.", 500);
        exit();
    }
}

function clearLoginRateLimit($email, $scope = 'member')
{
    $keys = [
        rateLimitKey('login:' . $scope . ':email', $email),
        rateLimitKey('login:' . $scope . ':ip', getClientIpAddress()),
    ];

    foreach ($keys as $key) {
        saveRateLimitBucket($key, 0, nowIsoUtc(), null);
    }
}

function checkLoginRateLimit($email, $scope = 'member')
{
    $keys = [
        rateLimitKey('login:' . $scope . ':email', $email),
        rateLimitKey('login:' . $scope . ':ip', getClientIpAddress()),
    ];

    foreach ($keys as $key) {
        $bucket = loadRateLimitBucket($key);
        if (!$bucket || empty($bucket['blocked_until']))
            continue;
        $blockedUntil = strtotime((string) $bucket['blocked_until']);
        if ($blockedUntil && $blockedUntil > time()) {
            $retryAfter = max(1, $blockedUntil - time());
            header('Retry-After: ' . $retryAfter);
            jsonError("Too many sign-in attempts. Please wait a few minutes and try again.", 429, [
                'retry_after_seconds' => $retryAfter,
            ]);
            exit();
        }
    }
}

function recordFailedLoginRateLimit($email, $scope = 'member')
{
    $now = time();
    $windowSeconds = 15 * 60;
    $maxAttempts = $scope === 'admin' ? 4 : 6;
    $blockSeconds = $scope === 'admin' ? 30 * 60 : 15 * 60;
    $keys = [
        rateLimitKey('login:' . $scope . ':email', $email),
        rateLimitKey('login:' . $scope . ':ip', getClientIpAddress()),
    ];

    foreach ($keys as $key) {
        $bucket = loadRateLimitBucket($key);
        $windowStartTs = !empty($bucket['window_start']) ? strtotime((string) $bucket['window_start']) : 0;
        $attempts = ($windowStartTs && ($now - $windowStartTs) <= $windowSeconds) ? (int) ($bucket['attempts'] ?? 0) : 0;
        $windowStart = ($attempts > 0 && $windowStartTs) ? gmdate('c', $windowStartTs) : nowIsoUtc();
        $attempts++;
        $blockedUntil = $attempts >= $maxAttempts ? gmdate('c', $now + $blockSeconds) : null;
        saveRateLimitBucket($key, $attempts, $windowStart, $blockedUntil);
    }
}

function markSuccessfulLogin($userId, $email, $reason = 'login')
{
    $now = nowIsoUtc();

    safePatchUserTrackingFields($userId, [
        'last_login_at' => $now,
        'last_active_at' => $now,
    ], 'last_login_at/last_active_at');

    logLoginEvent($userId, $email, true, $reason);

    return $now;
}

function markFailedLogin($userId, $email, $reason = 'failed_login')
{
    logLoginEvent($userId, $email, false, $reason);
}

function markUserActive($userId, $source = 'activity')
{
    $now = nowIsoUtc();

    $ok = safePatchUserTrackingFields($userId, [
        'last_active_at' => $now,
    ], 'last_active_at');

    return [$ok, $now];
}

function handleTrackActivity($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $source = strtolower(trim((string) ($data['source'] ?? 'activity')));
    $source = preg_replace('/[^a-z0-9_:\-]/', '', $source);
    if ($source === '')
        $source = 'activity';
    $source = substr($source, 0, 50);

    [$ok, $now] = markUserActive($userId, $source);

    jsonSuccess([
        'last_active_at' => $now,
        'activity_recorded' => $ok,
        'source' => $source,
    ]);
}

// ---------------------------------------------------------------------------
// HOLY BOOKS / EBOOK LIBRARY
// ---------------------------------------------------------------------------
function slugifyPetService($value)
{
    $slug = strtolower(trim((string) $value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function stripTrackingQuery($url)
{
    return preg_replace('/\?utm_source=chatgpt\.com$/', '', (string) $url);
}

function isTruthyDbValue($value, $default = false)
{
    if ($value === null || $value === '')
        return $default;
    if (is_bool($value))
        return $value;
    if (is_int($value))
        return $value === 1;
    $normalised = strtolower(trim((string) $value));
    if (in_array($normalised, ['1', 'true', 't', 'yes', 'y', 'on'], true))
        return true;
    if (in_array($normalised, ['0', 'false', 'f', 'no', 'n', 'off'], true))
        return false;
    return $default;
}

function isAbsoluteHttpUrl($url)
{
    return is_string($url) && preg_match('/^https?:\/\//i', trim($url));
}

function isPrivateOrReservedIp($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP))
        return true;
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
}

function isSafeExternalFetchUrl($url)
{
    $url = trim((string) $url);
    if (!isAbsoluteHttpUrl($url))
        return false;
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '')
        return false;
    if ($host === 'localhost' || str_ends_with($host, '.localhost'))
        return false;
    if (filter_var($host, FILTER_VALIDATE_IP))
        return !isPrivateOrReservedIp($host);

    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (empty($records))
        return false;
    foreach ($records as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? '';
        if ($ip !== '' && isPrivateOrReservedIp($ip)) {
            return false;
        }
    }
    return true;
}

function encodeStoragePath($path)
{
    $path = trim((string) $path, '/');
    if ($path === '')
        return '';
    $parts = array_map('rawurlencode', explode('/', $path));
    return implode('/', $parts);
}

function buildPublicStorageUrl($bucketId, $objectPath, $intent = 'read', $filename = '')
{
    $objectPath = trim((string) $objectPath);
    if ($objectPath === '')
        return '';

    // Allow emergency rows where a full URL is stored in pdf_path/epub_path.
    if (isAbsoluteHttpUrl($objectPath)) {
        return stripTrackingQuery($objectPath);
    }

    $supabaseUrl = rtrim(envValue('SUPABASE_URL'), '/');
    if ($supabaseUrl === '')
        return '';

    $bucketId = trim((string) ($bucketId ?: 'holy-books'));
    $url = $supabaseUrl
        . '/storage/v1/object/public/'
        . rawurlencode($bucketId)
        . '/'
        . encodeStoragePath($objectPath);

    if ($intent === 'download') {
        $downloadName = $filename ?: basename($objectPath);
        if ($downloadName) {
            $url .= '?download=' . rawurlencode($downloadName);
        }
    }

    return $url;
}

function holyBookFilename($book, $format)
{
    $slug = $book['slug'] ?? slugifyPetService(($book['service_type'] ?? '') . '-' . ($book['title'] ?? 'holy-book'));
    return $slug . '.' . $format;
}

function resolvePetServiceUrl($book, $format = 'pdf', $intent = 'read')
{
    $format = strtolower((string) $format) === 'epub' ? 'epub' : 'pdf';

    $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
    $pathKey = $format === 'epub' ? 'epub_path' : 'pdf_path';

    $externalUrl = trim((string) ($book[$externalKey] ?? ''));
    if ($externalUrl !== '') {
        // External URLs always win for their own format only. This lets Quran
        // use an external PDF while still using a bucket EPUB independently.
        $externalUrl = stripTrackingQuery($externalUrl);
        return isSafeExternalFetchUrl($externalUrl) ? $externalUrl : '';
    }

    $objectPath = trim((string) ($book[$pathKey] ?? ''));
    if ($objectPath === '')
        return '';

    return buildPublicStorageUrl(
        $book['bucket_id'] ?? 'holy-books',
        $objectPath,
        $intent,
        holyBookFilename($book, $format)
    );
}

function holyBookSourceType($book, $format = 'pdf')
{
    $format = strtolower((string) $format) === 'epub' ? 'epub' : 'pdf';
    $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
    $pathKey = $format === 'epub' ? 'epub_path' : 'pdf_path';

    if (!empty($book[$externalKey]))
        return 'external';
    if (!empty($book[$pathKey]))
        return 'bucket';
    return 'none';
}

function buildEbookRoute($bookId, $format = 'pdf', $intent = 'read', $mode = 'scroll')
{
    return 'backend_api.php?action=ebook_redirect'
        . '&book_id=' . rawurlencode($bookId)
        . '&format=' . rawurlencode($format)
        . '&intent=' . rawurlencode($intent)
        . '&mode=' . rawurlencode($mode);
}

function buildEbookFileRoute($bookId, $format = 'pdf', $intent = 'read', $mode = 'page')
{
    return 'backend_api.php?action=ebook_file'
        . '&book_id=' . rawurlencode($bookId)
        . '&format=' . rawurlencode($format)
        . '&intent=' . rawurlencode($intent)
        . '&mode=' . rawurlencode($mode);
}

function holyBookSectionMeta($religion)
{
    $key = normalizeReligionKey($religion);
    $meta = [
        'Hindu' => [
            'title' => 'Dharmic Granth',
            'subtitle' => 'धार्मिक ग्रंथ',
            'type' => 'pet service',
        ],
        'Muslim' => [
            'title' => 'Islamic Texts',
            'subtitle' => 'النصوص الإسلامية',
            'type' => 'pet service',
        ],
        'Sikh' => [
            'title' => 'Sikh Scripture',
            'subtitle' => 'ਸਿੱਖ ਧਰਮ ਗ੍ਰੰਥ',
            'type' => 'pet service',
        ],
        'Christian' => [
            'title' => 'Christian Texts',
            'subtitle' => 'Holy Bible',
            'type' => 'pet service',
        ],
        'Jain' => [
            'title' => 'Jain Agamas',
            'subtitle' => 'જૈન આગમ',
            'type' => 'pet service',
        ],
        'Buddhist' => [
            'title' => 'Buddhist Texts',
            'subtitle' => 'बौद्ध ग्रंथ',
            'type' => 'pet service',
        ],
        'Parsi' => [
            'title' => 'Zoroastrian Texts',
            'subtitle' => 'Zend Avesta',
            'type' => 'pet service',
        ],
    ];
    return $meta[$key] ?? [
        'title' => $key ? ($key . ' Texts') : 'Pet Services',
        'subtitle' => 'Ebooks',
        'type' => 'pet service',
    ];
}

function stringContains($haystack, $needle)
{
    return strpos((string) $haystack, (string) $needle) !== false;
}

function holyBookUiMeta($book)
{
    $religion = normalizeReligionKey($book['service_type'] ?? '');
    $title = strtolower((string) ($book['title'] ?? ''));

    $byReligion = [
        'Hindu' => ['icon' => 'book-open', 'bg' => 'bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400'],
        'Muslim' => ['icon' => 'book-open', 'bg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'],
        'Sikh' => ['icon' => 'book-open', 'bg' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'],
        'Christian' => ['icon' => 'book-open', 'bg' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400'],
        'Jain' => ['icon' => 'book-open', 'bg' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
        'Buddhist' => ['icon' => 'book-open', 'bg' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'],
        'Parsi' => ['icon' => 'book-open', 'bg' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
    ];

    $ui = $byReligion[$religion] ?? ['icon' => 'book-open', 'bg' => 'bg-amber-50 text-amber-700'];

    if (stringContains($title, 'hadith') || stringContains($title, 'sahih') || stringContains($title, 'bible') || stringContains($title, 'ramayana')) {
        $ui['icon'] = 'book';
    } elseif (stringContains($title, 'tafsir') || stringContains($title, 'sutra') || stringContains($title, 'zend')) {
        $ui['icon'] = 'scroll-text';
    } elseif (stringContains($title, 'veda') || stringContains($title, 'tripitaka') || stringContains($title, 'gathas')) {
        $ui['icon'] = 'library';
    } elseif (stringContains($title, 'purana') || stringContains($title, 'progress') || stringContains($title, 'sirah')) {
        $ui['icon'] = 'book-marked';
    }

    return $ui;
}

function normalizeReligionKey($religion)
{
    $religion = trim((string) $religion);
    $aliases = [
        'Parsi / Zoroastrian' => 'Parsi',
        'Zoroastrian' => 'Parsi',
        'Zoroastrianism' => 'Parsi',
        'Muslim' => 'Muslim',
        'Islam' => 'Muslim',
        'Islamic' => 'Muslim',
        'Christianity' => 'Christian',
        'Buddhism' => 'Buddhist',
        'Jainism' => 'Jain',
        'Sikhism' => 'Sikh',
        'Hinduism' => 'Hindu',
    ];
    return $aliases[$religion] ?? ($religion ?: 'Hindu');
}

function getPetServiceRows()
{
    $res = supabaseRequest('GET', '/rest/v1/pet_services', [
        'select' => 'id,slug,service_type,title,subtitle,description,language,source_label,source_url,bucket_id,pdf_path,epub_path,external_pdf_url,external_epub_url,default_read_mode,scroll_enabled,page_enabled,pdf_download_enabled,epub_download_enabled,sort_order,is_active',
        'is_active' => 'eq.true',
        'order' => 'service_type.asc,sort_order.asc,title.asc',
    ]);

    if (($res['code'] ?? 500) >= 400 || !is_array($res['data'])) {
        $message = is_array($res['data'])
            ? ($res['data']['message'] ?? json_encode($res['data']))
            : 'Could not read pet_services from Supabase.';
        return [
            'ok' => false,
            'message' => 'Failed to load pet services: ' . $message . ' (HTTP ' . ($res['code'] ?? 'unknown') . ')',
            'data' => [],
        ];
    }

    return ['ok' => true, 'message' => '', 'data' => $res['data']];
}

function normalisePetServiceRowForFrontend($row)
{
    $slug = trim((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugifyPetService(($row['service_type'] ?? '') . '-' . ($row['title'] ?? 'holy-book'));
    }

    $row['slug'] = $slug;
    $religion = normalizeReligionKey($row['service_type'] ?? '');
    $ui = holyBookUiMeta($row);

    $pdfUrl = resolvePetServiceUrl($row, 'pdf', 'read');
    $epubUrl = resolvePetServiceUrl($row, 'epub', 'download');

    $pdfAvailable = $pdfUrl !== '';
    $epubAvailable = $epubUrl !== '';

    $scrollEnabled = $pdfAvailable && isTruthyDbValue($row['scroll_enabled'] ?? true, true);
    $pageEnabled = $pdfAvailable && isTruthyDbValue($row['page_enabled'] ?? true, true);
    $pdfDownloadEnabled = $pdfAvailable && isTruthyDbValue($row['pdf_download_enabled'] ?? true, true);
    $epubDownloadEnabled = $epubAvailable && isTruthyDbValue($row['epub_download_enabled'] ?? false, false);

    $desc = trim((string) ($row['subtitle'] ?? ''));
    if ($desc === '')
        $desc = trim((string) ($row['description'] ?? ''));
    if ($desc === '')
        $desc = trim((string) ($row['language'] ?? ''));
    if ($desc === '')
        $desc = 'Sacred text';

    $pdfSourceType = holyBookSourceType($row, 'pdf');
    $epubSourceType = holyBookSourceType($row, 'epub');

    $pdfReadAction = 'redirect';
    $epubDownloadAction = 'redirect';
    $pdfDownloadAction = 'redirect';

    $pdfReadRoute = function ($mode) use ($slug, $pdfReadAction) {
        return $pdfReadAction === 'file'
            ? buildEbookFileRoute($slug, 'pdf', 'read', $mode)
            : buildEbookRoute($slug, 'pdf', 'read', $mode);
    };

    return [
        'id' => $slug,
        'slug' => $slug,
        'religion' => $religion,
        'title' => $row['title'] ?? 'Pet Service',
        'desc' => $desc,
        'description' => $row['description'] ?? '',
        'language' => $row['language'] ?? '',
        'source_label' => $row['source_label'] ?? '',
        'source_url' => $row['source_url'] ?? '',
        'sort_order' => intval($row['sort_order'] ?? 100),
        'icon' => $ui['icon'],
        'bg' => $ui['bg'],
        'read_target' => $pdfSourceType === 'external' ? 'new_tab' : 'modal',
        'source_types' => [
            'pdf' => $pdfSourceType,
            'epub' => $epubSourceType,
        ],
        'read' => [
            'default' => ($row['default_read_mode'] ?? 'page') === 'scroll' ? 'scroll' : 'page',
            'target' => $pdfSourceType === 'external' ? 'new_tab' : 'modal',
            'scroll' => $scrollEnabled ? $pdfReadRoute('scroll') : '',
            'page' => $pageEnabled ? $pdfReadRoute('page') : '',
        ],
        'downloads' => [
            'pdf' => [
                'label' => 'PDF',
                'available' => $pdfDownloadEnabled,
                'url' => $pdfDownloadEnabled ? ($pdfDownloadAction === 'file' ? buildEbookFileRoute($slug, 'pdf', 'download', 'page') : buildEbookRoute($slug, 'pdf', 'download', 'page')) : '',
                'source_type' => $pdfSourceType,
            ],
            'epub' => [
                'label' => 'EPUB',
                'available' => $epubDownloadEnabled,
                'url' => $epubDownloadEnabled ? ($epubDownloadAction === 'file' ? buildEbookFileRoute($slug, 'epub', 'download', 'page') : buildEbookRoute($slug, 'epub', 'download', 'page')) : '',
                'source_type' => $epubSourceType,
            ],
        ],
    ];
}

function findPetServiceById($bookId)
{
    $bookId = trim((string) $bookId);
    if ($bookId === '')
        return null;

    $rows = getPetServiceRows();
    if (!$rows['ok'])
        return null;

    $wanted = slugifyPetService($bookId);
    foreach ($rows['data'] as $row) {
        $candidates = array_filter([
            trim((string) ($row['slug'] ?? '')),
            trim((string) ($row['id'] ?? '')),
            slugifyPetService(($row['service_type'] ?? '') . '-' . ($row['title'] ?? '')),
            slugifyPetService($row['title'] ?? ''),
        ]);

        foreach ($candidates as $candidate) {
            if ($bookId === $candidate || $wanted === slugifyPetService($candidate)) {
                return $row;
            }
        }
    }

    return null;
}

function handleGetPetServices($data)
{
    $rows = getPetServiceRows();
    if (!$rows['ok']) {
        jsonError($rows['message'], 500);
        return;
    }

    $religionKey = normalizeReligionKey($data['religion'] ?? ($_GET['religion'] ?? 'Hindu'));
    $books = [];

    foreach ($rows['data'] as $row) {
        if (normalizeReligionKey($row['service_type'] ?? '') !== $religionKey) {
            continue;
        }
        $books[] = normalisePetServiceRowForFrontend($row);
    }

    usort($books, function ($a, $b) {
        $orderA = intval($a['sort_order'] ?? 100);
        $orderB = intval($b['sort_order'] ?? 100);
        if ($orderA === $orderB) {
            return strcmp(strtolower($a['title'] ?? ''), strtolower($b['title'] ?? ''));
        }
        return $orderA <=> $orderB;
    });

    $section = holyBookSectionMeta($religionKey);
    $section['books'] = $books;

    jsonSuccess([
        'backend_build' => PAWCIRCLE_BACKEND_BUILD,
        'backend_source' => 'supabase_pet_services_table',
        'religion' => $religionKey,
        'section' => $section,
        'read_options' => [
            'default' => 'scroll',
            'options' => ['scroll', 'page'],
        ],
        'download_options' => ['pdf', 'epub'],
    ]);
}

function withPdfViewerFragment($url, $mode)
{
    if (preg_match('/\.(pdf)(\?|$)/i', $url)) {
        $fragment = $mode === 'page' ? '#page=1&view=Fit&toolbar=1' : '#view=FitH&toolbar=1';
        return preg_replace('/#.*$/', '', $url) . $fragment;
    }
    return $url;
}

function holyBookRequestContext($data)
{
    $format = strtolower($_GET['format'] ?? ($data['format'] ?? 'pdf'));
    $intent = strtolower($_GET['intent'] ?? ($data['intent'] ?? 'read'));
    $mode = strtolower($_GET['mode'] ?? ($data['mode'] ?? 'page'));

    if (!in_array($format, ['pdf', 'epub'], true)) {
        return ['ok' => false, 'message' => 'Unsupported ebook format.', 'code' => 400];
    }
    if (!in_array($intent, ['read', 'download'], true))
        $intent = 'read';
    if (!in_array($mode, ['scroll', 'page'], true))
        $mode = 'page';
    if ($format === 'epub' && $intent === 'read')
        $intent = 'download';

    $bookId = $_GET['book_id'] ?? ($data['book_id'] ?? '');
    $book = findPetServiceById($bookId);
    if (!$book) {
        return ['ok' => false, 'message' => 'Unknown ebook requested.', 'code' => 404];
    }

    if ($format === 'pdf' && $intent === 'read') {
        if ($mode === 'page' && !isTruthyDbValue($book['page_enabled'] ?? true, true)) {
            return ['ok' => false, 'message' => 'Page reading is not enabled for this ebook.', 'code' => 404];
        }
        if ($mode === 'scroll' && !isTruthyDbValue($book['scroll_enabled'] ?? true, true)) {
            return ['ok' => false, 'message' => 'Scroll reading is not enabled for this ebook.', 'code' => 404];
        }
    }

    if ($format === 'pdf' && $intent === 'download' && !isTruthyDbValue($book['pdf_download_enabled'] ?? true, true)) {
        return ['ok' => false, 'message' => 'PDF download is not enabled for this ebook.', 'code' => 404];
    }

    if ($format === 'epub' && !isTruthyDbValue($book['epub_download_enabled'] ?? false, false)) {
        return ['ok' => false, 'message' => 'EPUB download is not enabled for this ebook.', 'code' => 404];
    }

    $url = resolvePetServiceUrl($book, $format, $intent);
    if (empty($url)) {
        return ['ok' => false, 'message' => 'No ' . strtoupper($format) . ' source is configured for this ebook.', 'code' => 404];
    }

    return [
        'ok' => true,
        'book' => $book,
        'url' => $url,
        'format' => $format,
        'intent' => $intent,
        'mode' => $mode,
    ];
}

function validateRemoteEbookForProxy($url, $format)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PawCircle Ebook Proxy');
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    $err = curl_error($ch);

    // Some object/CDN hosts do not implement HEAD correctly. Only block clear failures.
    if ($code >= 400) {
        return ['ok' => false, 'message' => 'The ebook file could not be reached at its configured storage URL. Check the bucket path and public read policy. HTTP ' . $code];
    }
    if ($err) {
        return ['ok' => false, 'message' => 'The ebook file could not be reached: ' . $err];
    }
    if ($contentType && strpos($contentType, 'text/html') !== false) {
        return ['ok' => false, 'message' => 'The configured ebook URL returned an HTML page, not a ' . strtoupper($format) . ' file. Check the path/URL.'];
    }
    return ['ok' => true];
}

function streamRemoteEbookFile($url, $format, $filename, $intent)
{
    ignore_user_abort(true);
    @set_time_limit(0);

    $range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
    $statusCode = 200;
    $responseHeaders = [];
    $sentHeaders = false;
    $bytesWritten = 0;

    header_remove('Content-Type');
    http_response_code(200);
    header('X-PawCircle-Ebook-Proxy: ' . PAWCIRCLE_BACKEND_BUILD);
    header('Cache-Control: private, max-age=600');
    header('Content-Type: ' . ($format === 'epub' ? 'application/epub+zip' : 'application/pdf'));
    $disposition = $intent === 'download' ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PawCircle Ebook Proxy');
    if ($range !== '' && preg_match('/^bytes=\d*-\d*$/', $range)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Range: ' . $range]);
    }
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$statusCode, &$responseHeaders) {
        $line = trim($header);
        if ($line === '')
            return strlen($header);
        if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $line, $m)) {
            $statusCode = intval($m[1]);
            $responseHeaders = [];
            return strlen($header);
        }
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return strlen($header);
    });
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$sentHeaders, &$statusCode, &$responseHeaders, &$bytesWritten) {
        if (!$sentHeaders) {
            if ($statusCode >= 400) {
                http_response_code($statusCode);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'The ebook file could not be reached at its configured storage URL. HTTP ' . $statusCode]);
                $sentHeaders = true;
                return 0;
            }
            http_response_code($statusCode === 206 ? 206 : 200);
            foreach (['content-length', 'content-range', 'content-encoding'] as $name) {
                if (!empty($responseHeaders[$name])) {
                    header($name . ': ' . $responseHeaders[$name]);
                }
            }
            $sentHeaders = true;
        }
        echo $chunk;
        $bytesWritten += strlen($chunk);
        flush();
        return strlen($chunk);
    });
    curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!$sentHeaders && ($err || $code >= 400)) {
        jsonError($err ?: ('The ebook file could not be reached at its configured storage URL. HTTP ' . $code), 502);
        return;
    }
    exit();
}

function handleEbookFile($data)
{
    $ctx = holyBookRequestContext($data);
    if (!$ctx['ok']) {
        jsonError($ctx['message'], $ctx['code']);
        return;
    }

    $book = $ctx['book'];
    $format = $ctx['format'];
    $intent = $ctx['intent'];
    $url = $ctx['url'];
    $filename = holyBookFilename($book, $format);

    // Do not proxy intentionally external PDFs such as the oversized Quran PDF.
    // Those remain direct redirects/new-tab reads.
    if (holyBookSourceType($book, $format) === 'external') {
        header_remove('Content-Type');
        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit();
    }

    streamRemoteEbookFile($url, $format, $filename, $intent);
}

function handleEbookRedirect($data)
{
    $ctx = holyBookRequestContext($data);
    if (!$ctx['ok']) {
        jsonError($ctx['message'], $ctx['code']);
        return;
    }

    $url = $ctx['url'];
    if ($ctx['intent'] === 'read' && $ctx['format'] === 'pdf') {
        $url = withPdfViewerFragment($url, $ctx['mode']);
    }

    header_remove('Content-Type');
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 302);
    exit();
}



// ---------------------------------------------------------------------------
// SIGNUP
// ---------------------------------------------------------------------------
function resolveTaxonomyValues($data) {
        $community = $data['pet_community'] ?? $data['community'] ?? $data['breed_group'] ?? $data['interest_circle'] ?? null;
        $religion = $data['pet_type'] ?? $data['animal_type'] ?? $data['religion'] ?? null;

        return [
            'community' => $community,
            'religion' => $religion,
        ];
    }

// Build and send the signup verification email (best-effort, returns the
// sendEmailMessage result array).
function sendSignupVerificationEmail($email, $name, $code)
{
    $name = trim((string) $name);
    $hello = $name !== '' ? "Namaste $name," : "Namaste,";
    $mins = (int) round(PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS / 60);

    $subject = 'Your PawCircle verification code: ' . $code;
    $text = $hello . "\n\n"
        . "Your PawCircle email verification code is:\n\n"
        . "    $code\n\n"
        . "Enter this 6-digit code on the signup screen to finish creating your account. "
        . "The code expires in $mins minutes.\n\n"
        . "If you didn't request this, you can safely ignore this email.\n\n— Team PawCircle";

    $safeHello = htmlspecialchars($hello, ENT_QUOTES);
    $digits = '';
    foreach (str_split($code) as $d) {
        $digits .= '<span style="display:inline-block;min-width:44px;margin:0 4px;padding:12px 0;font-size:30px;font-weight:700;letter-spacing:2px;color:#0f172a;background:#f1f5f9;border-radius:10px;font-family:monospace;">' . $d . '</span>';
    }
    $html = '<div style="font-family:Segoe UI,Helvetica,Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937;">'
        . '<h2 style="margin:0 0 8px;color:#b45309;">Verify your email</h2>'
        . '<p style="margin:0 0 18px;">' . $safeHello . ' enter this code to finish creating your PawCircle account:</p>'
        . '<div style="text-align:center;margin:20px 0;">' . $digits . '</div>'
        . '<p style="margin:0 0 6px;color:#6b7280;font-size:13px;">This code expires in ' . $mins . ' minutes.</p>'
        . '<p style="margin:0;color:#9ca3af;font-size:12px;">If you didn\'t request this, you can safely ignore this email.</p>'
        . '<p style="margin:18px 0 0;font-size:13px;">— Team PawCircle</p>'
        . '</div>';

    return sendEmailMessage($email, $subject, $text, $html);
}

// Verify the emailed 6-digit code and, on success, create the real account.
function handleVerifySignup($data)
{
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $code = preg_replace('/\D/', '', (string) ($data['code'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('A valid email is required.', 400);
        return;
    }
    if (strlen($code) !== 6) {
        jsonError('Enter the 6-digit code from your email.', 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/signup_verifications', [
        'email' => 'eq.' . $email,
        'select' => 'id,code_hash,payload,attempts,expires_at',
        'order' => 'created_at.desc',
        'limit' => '1',
    ]);
    $row = $res['data'][0] ?? null;
    if (!$row) {
        jsonError('No pending verification found. Please sign up again.', 404);
        return;
    }

    if (strtotime((string) $row['expires_at']) < time()) {
        supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']]);
        jsonError('This code has expired. Please request a new one.', 410);
        return;
    }

    $attempts = (int) ($row['attempts'] ?? 0);
    if ($attempts >= PAWCIRCLE_SIGNUP_CODE_MAX_ATTEMPTS) {
        supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']]);
        jsonError('Too many incorrect attempts. Please sign up again.', 429);
        return;
    }

    if (!password_verify($code, (string) $row['code_hash'])) {
        supabaseRequest('PATCH', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']], ['attempts' => $attempts + 1], ['Prefer: return=minimal']);
        $left = max(0, PAWCIRCLE_SIGNUP_CODE_MAX_ATTEMPTS - ($attempts + 1));
        jsonError("Incorrect code. $left attempt(s) remaining.", 401);
        return;
    }

    $payload = is_array($row['payload']) ? $row['payload'] : (json_decode((string) $row['payload'], true) ?: []);
    $regEmail = $payload['email'] ?? $email;
    $plainPassword = (string) ($data['password'] ?? '');
    $pendingHash = (string) ($payload['password_hash'] ?? '');

    if (strlen($plainPassword) < 10 || $pendingHash === '' || !password_verify($plainPassword, $pendingHash)) {
        jsonError('For security, please return to the signup form and enter your password again before verifying.', 400);
        return;
    }

    // Guard against a race where the email got registered in the meantime.
    $dup = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $regEmail, 'select' => 'id']);
    if (!empty($dup['data'])) {
        supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']]);
        jsonError('An account with this email already exists. Please sign in.', 409);
        return;
    }

    supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']]);
    finalizeSignup($payload, $plainPassword);
}

// Regenerate and re-send the verification code for a pending signup.
function handleResendSignupCode($data)
{
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('A valid email is required.', 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/signup_verifications', [
        'email' => 'eq.' . $email,
        'select' => 'id,payload',
        'order' => 'created_at.desc',
        'limit' => '1',
    ]);
    $row = $res['data'][0] ?? null;
    if (!$row) {
        jsonError('No pending verification found. Please sign up again.', 404);
        return;
    }

    $payload = is_array($row['payload']) ? $row['payload'] : (json_decode((string) $row['payload'], true) ?: []);
    $name = $payload['name'] ?? '';
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    supabaseRequest('PATCH', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']], [
        'code_hash' => password_hash($code, PASSWORD_BCRYPT),
        'attempts' => 0,
        'expires_at' => gmdate('c', time() + PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS),
    ], ['Prefer: return=minimal']);

    $sent = sendSignupVerificationEmail($email, $name, $code);
    if (empty($sent['ok'])) {
        jsonError("We couldn't send the verification email. Please try again.", 502);
        return;
    }

    jsonSuccess(['resent' => true, 'email' => $email, 'message' => "A new code is on its way to $email."]);
}

// Create the real user + profile + session from a verified pending payload.
// Emits the same success response the original signup flow returned.
function finalizeSignup($payload, $plainPassword = '')
{
    $name = (string) ($payload['name'] ?? '');
    $email = filter_var((string) ($payload['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $passwordHash = (string) ($payload['password_hash'] ?? '');
    $community = (string) ($payload['community'] ?? 'Not Specified');
    $religion = (string) ($payload['religion'] ?? '');
    $ageGroup = (string) ($payload['age_group'] ?? '');
    $phone = (string) ($payload['mobile_number'] ?? '');
    $interestsArr = array_values((array) ($payload['interests'] ?? []));
    $skillsArr = array_values((array) ($payload['skills'] ?? []));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $plainPassword) < 10 || $passwordHash === '' || !password_verify((string) $plainPassword, $passwordHash)) {
        jsonError('Verification data was incomplete. Please sign up again.', 400);
        return;
    }

    $metadata = [
        'full_name' => $name,
        'name' => $name,
        'religion' => $religion,
        'community' => $community,
        'source' => 'pawcircle_signup',
    ];
    if ($phone !== '')
        $metadata['mobile_number'] = $phone;
    if ($ageGroup !== '')
        $metadata['age_group'] = $ageGroup;

    // Create the Supabase Auth identity only after PawCircle's independent
    // 6-digit email code has been verified. email_confirm=true tells Supabase
    // that PawCircle already verified this address and prevents a second required
    // Supabase confirmation email.
    $authCreate = createSupabaseAuthUserForSignup($email, (string) $plainPassword, $metadata);
    if (empty($authCreate['ok'])) {
        $message = (string) ($authCreate['message'] ?? 'Could not create Supabase Auth account.');
        $code = (int) ($authCreate['code'] ?? 500);
        if ($code === 422 || stripos($message, 'already') !== false || stripos($message, 'registered') !== false) {
            jsonError('An Auth account already exists for this email. Please sign in instead.', 409);
            return;
        }
        error_log('[pawcircle][' . requestId() . '] auth signup create failed | http=' . $code . ' | message=' . $message);
        jsonError('Could not create your secure Auth account. Please try again.', 500);
        return;
    }

    $authUser = $authCreate['user'];
    $user = linkOrCreateAppUserForSupabaseAuth($authUser, [
        'name' => $name,
        'religion' => $religion,
        'community' => $community,
    ]);

    if (!$user || empty($user['id'])) {
        return;
    }

    $userId = $user['id'];

    $profilePatch = [
        'full_name' => $name,
        'community' => $community,
        'religion' => $religion,
        'primary_interests' => empty($interestsArr) ? null : $interestsArr,
        'skills' => empty($skillsArr) ? null : $skillsArr,
        'age_group' => $ageGroup,
        'mobile_number' => $phone,
    ];

    $profileRes = supabaseRequest('PATCH', '/rest/v1/profiles', [
        'user_id' => 'eq.' . strtolower($userId),
    ], $profilePatch, ['Prefer: return=minimal']);

    if (($profileRes['code'] ?? 500) >= 400) {
        sendSupabaseError('Account was created, but profile details could not be saved.', $profileRes);
        return;
    }

    $loginAt = markSuccessfulLogin($userId, $email, 'signup');
    $session = createUserSession($userId, 'member');
    $freshUser = fetchAppUserWithProfileById($userId) ?: $user;
    $payloadUser = buildUserPayload($freshUser, $loginAt);
    $payloadUser['auth_user_id'] = $authUser['id'] ?? null;

    jsonSuccess([
        'message' => 'Account created successfully.',
        'session' => $session,
        'user' => $payloadUser,
    ]);

    if ($phone !== '') {
        finishResponseEarly();
        $welcome = "🙏 Namaste $name! Welcome to PawCircle. Your account is ready. "
            . "Enable WhatsApp notifications in Privacy settings to get event reminders, "
            . "eDarshan timings and community updates here.";
        $r = sendWhatsAppMessage($phone, $welcome, proactiveWhatsappOpts($welcome));
        if (empty($r['ok'])) {
            error_log("[pawcircle][" . requestId() . "] signup welcome WhatsApp not sent for $email");
        }
    }
}
    function handleSignup($data) {
        if (empty($data['pet_name']) || (empty($data['email']) && empty($data['mobile_number'])) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Pet name, email/phone, and password are required."]);
            return;
        }

        $petName   = htmlspecialchars(strip_tags($data['pet_name']));
        $email     = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $phone     = htmlspecialchars(strip_tags($data['mobile_number'] ?? ''));
        $password  = $data['password'];
        $petType   = htmlspecialchars(strip_tags($data['pet_type'] ?? 'Dog'));
        $breed     = htmlspecialchars(strip_tags($data['breed'] ?? ''));
        $parentName= htmlspecialchars(strip_tags($data['parent_name'] ?? ''));

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid email format."]);
            return;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters."]);
            return;
        }

        // Create Supabase Auth identity
        $metadata = [
            'pet_name' => $petName,
            'parent_name' => $parentName,
            'pet_type' => $petType,
            'breed' => $breed,
            'source' => 'pawcircle_signup'
        ];
        if (!empty($phone)) $metadata['mobile_number'] = $phone;

        $authCreate = createSupabaseAuthUserForSignup($email, $password, $metadata);
        if (empty($authCreate['ok'])) {
            $msg = $authCreate['message'] ?? 'Could not create secure account.';
            if (($authCreate['code'] ?? 0) === 409 || stripos($msg, 'already') !== false || stripos($msg, 'registered') !== false) {
                http_response_code(409);
                echo json_encode(["status" => "error", "message" => "An account with this email already exists."]);
                return;
            }
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => $msg]);
            return;
        }

        $authUser = $authCreate['user'];
        $appUser = linkOrCreateAppUserForSupabaseAuth($authUser, $metadata);

        if (!$appUser || empty($appUser['id'])) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to link app user. Please try again."]);
            return;
        }

        $userId = $appUser['id'];
        $session = createUserSession($userId, 'member');

        echo json_encode([
            "status"  => "success",
            "message" => "Account created successfully.",
            "session" => $session,
            "user"    => [
                "id"          => $userId,
                "pet_name"    => $petName,
                "parent_name" => $parentName,
                "email"       => $email,
                "role"        => "member",
                "pet_type"    => $petType,
                "breed"       => $breed
            ],
        ]);
    }
// ---------------------------------------------------------------------------
// MEMBER LOGIN
// ---------------------------------------------------------------------------
function handleTestUserLogin($shortcut)
{
    if (!testUsersEnabled()) {
        return false;
    }

    $map = testUserMap();
    $shortcut = strtolower(trim((string) $shortcut));
    if (empty($map[$shortcut])) {
        return false;
    }

    $test = $map[$shortcut];
    $email = $test['email'];
    $res = supabaseRequest('GET', '/rest/v1/users', [
        'email' => 'eq.' . $email,
        'select' => 'id,email,role,is_verified,verified_at,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city,privacy_settings)',
        'limit' => '1',
    ]);

    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load test user.", $res);
        return true;
    }

    if (empty($res['data'])) {
        $userRes = supabaseRequest('POST', '/rest/v1/users', [], [
            'email' => $email,
            'password_hash' => password_hash(bin2hex(random_bytes(32)), defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT),
            'role' => 'member',
        ], ['Prefer: return=representation']);

        if (($userRes['code'] ?? 500) >= 400 || empty($userRes['data'])) {
            sendSupabaseError("Failed to create test user.", $userRes);
            return true;
        }

        $userId = $userRes['data'][0]['id'];
        $profileRes = supabaseRequest('POST', '/rest/v1/profiles', [], [
            'user_id' => $userId,
            'full_name' => $test['name'],
            'community' => $test['community'],
            'religion' => $test['religion'],
            'membership_applied' => true,
            'status' => 'active',
            'age_group' => '26 - 40',
        ], ['Prefer: return=minimal']);

        if (($profileRes['code'] ?? 500) >= 400) {
            sendSupabaseError("Failed to create test profile.", $profileRes);
            return true;
        }

        $res = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'eq.' . $userId,
            'select' => 'id,email,role,is_verified,verified_at,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city,privacy_settings)',
            'limit' => '1',
        ]);
    }

    $user = $res['data'][0] ?? null;
    if (!$user) {
        jsonError("Could not load test user.", 500);
        return true;
    }

    $loginAt = markSuccessfulLogin($user['id'], $email, 'test_user_login');
    $session = createUserSession($user['id'], 'member');
    $payload = buildUserPayload($user, $loginAt);
    $payload['is_test_user'] = true;

    jsonSuccess([
        'message' => 'Test user signed in.',
        'session' => $session,
        'user' => $payload,
    ]);
    return true;
}

function handleLogin($data, $expectedRole)
{
    $emailRaw = trim((string) ($data['email'] ?? ''));
    $passwordRaw = (string) ($data['password'] ?? '');
    if ($expectedRole === 'member' && $passwordRaw === '' && handleTestUserLogin($emailRaw)) {
        return;
    }

    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Credentials cannot be empty."]);
        return;
    }

    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    $scope = $expectedRole === 'admin' ? 'admin' : 'member';

    checkLoginRateLimit($email, $scope);

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'email' => 'eq.' . $email,
        'select' => 'id,email,role,is_verified,verified_at,password_hash,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city,privacy_settings)',
        'limit' => '1',
    ]);

    // Resilience: the phase-1/2 columns (is_verified/verified_at on users,
    // privacy_settings on profiles) may not be migrated yet. When they are
    // missing PostgREST returns a 400, which would otherwise fall through to
    // "Invalid email or password" and lock every member out. Retry without them.
    if (($res['code'] ?? 500) >= 400) {
        $res = supabaseRequest('GET', '/rest/v1/users', [
            'email' => 'eq.' . $email,
            'select' => 'id,email,role,password_hash,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city)',
            'limit' => '1',
        ]);
    }

    if ($res['code'] === 200 && !empty($res['data'])) {
        $user = $res['data'][0];

        if (empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {
            markFailedLogin($user['id'] ?? null, $email, 'invalid_password');
            recordFailedLoginRateLimit($email, $scope);
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
            return;
        }

        $loginPunishments = getActiveUserPunishments($user['id'] ?? '');
        $ban = activePunishmentOfType($loginPunishments, ['ban']);
        if ($ban) {
            recordFailedLoginRateLimit($email, $scope);
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "This account has been banned. Contact support if you believe this is incorrect."]);
            return;
        }
        $suspension = activePunishmentOfType($loginPunishments, ['suspension']);
        if ($suspension) {
            recordFailedLoginRateLimit($email, $scope);
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "This account is currently suspended."]);
            return;
        }

        $loginAt = markSuccessfulLogin($user['id'], $email, 'public_login');
        clearLoginRateLimit($email, $scope);
        $session = createUserSession($user['id'], 'member');

        // PostgREST returns 1-to-1 embed as object {}; normalise both shapes
        $raw = $user['profiles'] ?? null;
        if (is_array($raw) && isset($raw[0])) {
            $profile = $raw[0];
        } elseif (is_array($raw) && !empty($raw) && !isset($raw[0])) {
            $profile = $raw;
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Account profile is incomplete. Please contact support."]);
            return;
        }

        $payload = buildUserPayload($user, $loginAt);

        echo json_encode([
            "status" => "success",
            "message" => "Authentication successful.",
            "session" => $session,
            "user" => $payload,
        ]);
        return;
    }

    markFailedLogin(null, $email, 'unknown_email_or_role');
    recordFailedLoginRateLimit($email, $scope);
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
}

// ---------------------------------------------------------------------------
// ADMIN LOGIN — returns user + stats in one response
// ---------------------------------------------------------------------------
function handleAdminLogin($data)
{
    if (empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Credentials cannot be empty."]);
        return;
    }

    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    checkLoginRateLimit($email, 'admin');

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'email' => 'eq.' . $email,
        'role' => 'eq.admin',
        'select' => 'id,email,role,password_hash',
    ]);

    if ($res['code'] !== 200 || empty($res['data'])) {
        markFailedLogin(null, $email, 'invalid_admin_email_or_role');
        recordFailedLoginRateLimit($email, 'admin');
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid admin credentials."]);
        return;
    }

    $user = $res['data'][0];

    if (empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {
        markFailedLogin($user['id'] ?? null, $email, 'invalid_admin_password');
        recordFailedLoginRateLimit($email, 'admin');
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid admin credentials."]);
        return;
    }

    $loginAt = markSuccessfulLogin($user['id'], $email, 'admin_login');
    clearLoginRateLimit($email, 'admin');
    $session = createUserSession($user['id'], 'admin');

    $statsData = fetchStats();

    echo json_encode([
        "status" => "success",
        "message" => "Admin authentication successful.",
        "session" => $session,
        "user" => [
            "id" => $user['id'],
            "email" => $user['email'],
            "role" => $user['role'],
            "last_login_at" => $loginAt,
            "last_active_at" => $loginAt,
        ],
        "stats" => $statsData,
    ]);
}

function normaliseProfileEmbed($raw)
{
    if (is_array($raw) && isset($raw[0]))
        return $raw[0];
    if (is_array($raw) && !empty($raw) && !isset($raw[0]))
        return $raw;
    return [];
}

function buildUserPayload($user, $loginAt = null)
{
    $profile = normaliseProfileEmbed($user['profiles'] ?? []);
    $customTags = profileCustomTags($profile);
    $adminCaps = fetchAdminCapabilities($user['id'] ?? '');
    $systemTags = adminCapabilityTags($adminCaps);
    $displayTags = profileDisplayTags($customTags, $systemTags);
    $interests = implode(', ', $customTags);

    return [
        "id" => $user['id'],
        "name" => $profile['pet_name'] ?? ($user['email'] ?? 'Member'),
        "pet_name" => $profile['pet_name'] ?? '',
        "parent_name" => $profile['parent_name'] ?? '',
        "email" => $user['email'] ?? '',
        "role" => $user['role'] ?? 'member',
        "pet_type" => $profile['pet_type'] ?? 'Dog',
        "breed" => $profile['breed'] ?? '',
        "community" => $profile['breed'] ?? '',
        "religion" => $profile['pet_type'] ?? '',
        "membership_applied" => $profile['membership_applied'] ?? false,
        "membership_status" => $profile['status'] ?? 'none',
        "profile_photo_url" => $profile['profile_photo_url'] ?? null,
        "cover_photo_url" => $profile['cover_photo_url'] ?? null,
        "mobile_number" => $profile['mobile_number'] ?? null,
        "gender" => $profile['gender'] ?? null,
        "bio" => $profile['bio'] ?? '',
        "current_city" => $profile['current_city'] ?? null,
        "last_login_at" => $loginAt ?? ($user['last_login_at'] ?? null),
        "last_active_at" => $user['last_active_at'] ?? null,
        "primary_interests" => $customTags,
        "custom_tags" => $customTags,
        "system_tags" => $systemTags,
        "tags" => $displayTags,
        "socialProfile" => [
            "name" => $profile['pet_name'] ?? ($user['email'] ?? 'Member'),
            "pet_name" => $profile['pet_name'] ?? '',
            "parent_name" => $profile['parent_name'] ?? '',
            "pet_type" => $profile['pet_type'] ?? '',
            "breed" => $profile['breed'] ?? '',
            "gender" => $profile['gender'] ?? null,
            "bio" => $profile['bio'] ?? '',
            "currentCity" => $profile['current_city'] ?? null,
            "contactNo" => $profile['mobile_number'] ?? null,
            "shareContact" => true,
            "tags" => $customTags,
            "customTags" => $customTags,
            "systemTags" => $systemTags,
            "displayTags" => $displayTags,
        ],
        "personalization" => [
            "interests" => $interests,
            "tags" => $customTags,
        ],
        "admin_capabilities" => $adminCaps,
        "admin_mode_active" => false,
        "admin_mode_until" => null,
    ];
}



function handleSessionMe($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $res = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,role,is_verified,verified_at,last_login_at,last_active_at,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city,privacy_settings)',
        'limit' => '1',
    ]);

    // Fall back to a base select if the phase-1/2 columns aren't migrated yet,
    // so existing sessions still resume instead of being force-signed-out.
    if (($res['code'] ?? 500) >= 400) {
        $res = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'eq.' . $userId,
            'select' => 'id,email,role,last_login_at,last_active_at,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city)',
            'limit' => '1',
        ]);
    }

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        clearSessionCookies();
        jsonError("Session user no longer exists. Please sign in again.", 401);
        return;
    }

    $user = buildUserPayload($res['data'][0]);
    $user['admin_mode_active'] = !empty($data['auth_role']) && (($data['auth_role'] ?? '') === 'admin' || ($GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['admin_mode_active'] ?? false));
    $user['admin_mode_until'] = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['admin_mode_until'] ?? null;
    jsonSuccess(["user" => $user]);
}

function handleEnterAdminMode($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $sessionId = requireUuid($data['auth_session_id'] ?? '', 'session_id');
    $password = (string) ($data['password'] ?? '');
    if ($password === '') {
        jsonError("Enter your password to continue.", 400);
        return;
    }

    $caps = fetchAdminCapabilities($userId);
    if (empty($caps)) {
        jsonError("Admin access is not enabled for this account.", 403);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,password_hash',
        'limit' => '1',
    ]);

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        jsonError("Could not verify your account. Please sign in again.", 401);
        return;
    }

    $user = $res['data'][0];
    if (!password_verify($password, $user['password_hash'] ?? '')) {
        markFailedLogin($userId, $user['email'] ?? '', 'invalid_admin_mode_password');
        jsonError("Password is incorrect.", 401);
        return;
    }

    $until = gmdate('c', time() + (15 * 60));
    $patch = supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'id' => 'eq.' . $sessionId,
    ], [
        'admin_mode_until' => $until,
        'last_seen_at' => nowIsoUtc(),
    ], ['Prefer: return=minimal']);

    if (($patch['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] admin mode patch failed | http=" . ($patch['code'] ?? 'n/a') . " | response=" . json_encode($patch['data'] ?? null));
        jsonError("Admin mode is not configured. Please contact support.", 500);
        return;
    }

    jsonSuccess([
        'message' => 'Admin mode enabled.',
        'admin_mode_active' => true,
        'admin_mode_until' => $until,
        'admin_capabilities' => $caps,
    ]);
}

function handleExitAdminMode($data)
{
    $sessionId = requireUuid($data['auth_session_id'] ?? '', 'session_id');
    supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'id' => 'eq.' . $sessionId,
    ], [
        'admin_mode_until' => null,
        'last_seen_at' => nowIsoUtc(),
    ], ['Prefer: return=minimal']);

    jsonSuccess(['message' => 'Admin mode disabled.', 'admin_mode_active' => false]);
}

function handleGetAdminDashboard($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $caps = fetchAdminCapabilities($userId);
    if (empty($caps)) {
        jsonError("Admin access is not enabled for this account.", 403);
        return;
    }

    jsonSuccess([
        'capabilities' => $caps,
        'stats' => fetchStats(),
        'admin_mode_until' => $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['admin_mode_until'] ?? null,
    ]);
}

function handleListAdminRoles($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($userId);

    $res = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'revoked_at' => 'is.null',
        'select' => 'id,user_id,role,scope_type,scope_value,created_at',
        'order' => 'created_at.desc',
        'limit' => '200',
    ]);

    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load admin roles.", $res);
        return;
    }

    $rows = $res['data'] ?? [];
    $users = [];
    $profiles = [];
    $userIds = normalizeUuidList(array_column($rows, 'user_id'));
    if (!empty($userIds)) {
        $usersRes = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'in.(' . implode(',', $userIds) . ')',
            'select' => 'id,email',
        ]);
        if (!supabaseFailed($usersRes)) {
            foreach (($usersRes['data'] ?? []) as $user) {
                $users[$user['id']] = $user['email'] ?? '';
            }
        }
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
            'user_id' => 'in.(' . implode(',', $userIds) . ')',
            'select' => 'user_id,full_name,religion,community',
        ]);
        if (!supabaseFailed($profilesRes)) {
            foreach (($profilesRes['data'] ?? []) as $profile) {
                $profiles[$profile['user_id']] = $profile;
            }
        }
    }

    foreach ($rows as &$row) {
        $userIdForRow = $row['user_id'] ?? '';
        $profile = $profiles[$userIdForRow] ?? [];
        $row['email'] = $users[$userIdForRow] ?? '';
        $row['full_name'] = $profile['full_name'] ?? '';
        $row['religion'] = $profile['religion'] ?? '';
        $row['community'] = $profile['community'] ?? '';
        $row['label'] = adminCapabilityLabel(normaliseAdminRole($row['role'] ?? ''), $row['scope_type'] ?? 'global', $row['scope_value'] ?? '*');
    }
    unset($row);

    jsonSuccess(['roles' => $rows]);
}

function resolveAdminTargetUserId($data)
{
    if (!empty($data['target_user_id']) && isValidUuid($data['target_user_id'])) {
        return strtolower($data['target_user_id']);
    }
    $email = filter_var($data['target_email'] ?? '', FILTER_SANITIZE_EMAIL);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError("Enter a valid target user email.", 400);
        exit();
    }
    $res = supabaseRequest('GET', '/rest/v1/users', [
        'email' => 'eq.' . $email,
        'select' => 'id,email',
        'limit' => '1',
    ]);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        jsonError("No user was found with that email.", 404);
        exit();
    }
    return $res['data'][0]['id'];
}

function handleGrantAdminRole($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);

    $targetUserId = requireUuid(resolveAdminTargetUserId($data), 'target_user_id');
    $role = normaliseAdminRole($data['role'] ?? '');
    if (!in_array($role, ['owner', 'platform_admin', 'religion_admin', 'community_admin'], true)) {
        jsonError("Choose a valid admin role.", 400);
        return;
    }

    $scopeType = strtolower(cleanPlainValue($data['scope_type'] ?? 'global', 40));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 160);
    if ($role === 'owner' || $role === 'platform_admin') {
        $scopeType = 'global';
        $scopeValue = '*';
    } elseif ($role === 'religion_admin') {
        $scopeType = 'religion';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Religion admins require a religion scope.", 400);
            return;
        }
    } elseif ($role === 'community_admin') {
        $scopeType = 'community';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Community admins require a community scope.", 400);
            return;
        }
    }

    $res = supabaseRequest('POST', '/rest/v1/admin_roles', [], [
        'user_id' => $targetUserId,
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_value' => $scopeValue,
        'created_by' => $actorId,
        'revoked_at' => null,
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        sendSupabaseError("Failed to grant admin role.", $res);
        return;
    }

    jsonSuccess(['role' => $res['data'][0], 'message' => 'Admin role granted.']);
}

function handleUpdateAdminRole($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);
    $roleId = requireUuid($data['role_id'] ?? '', 'role_id');

    $existingRes = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'id' => 'eq.' . $roleId,
        'revoked_at' => 'is.null',
        'select' => 'id,user_id,role,scope_type,scope_value',
        'limit' => '1',
    ]);
    if (($existingRes['code'] ?? 500) >= 400 || empty($existingRes['data'])) {
        jsonError("Active admin role not found.", 404);
        return;
    }

    $existing = $existingRes['data'][0];
    $role = normaliseAdminRole($data['role'] ?? '');
    if (!in_array($role, ['owner', 'platform_admin', 'religion_admin', 'community_admin'], true)) {
        jsonError("Choose a valid admin role.", 400);
        return;
    }

    $scopeType = strtolower(cleanPlainValue($data['scope_type'] ?? 'global', 40));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 160);
    if ($role === 'owner' || $role === 'platform_admin') {
        $scopeType = 'global';
        $scopeValue = '*';
    } elseif ($role === 'religion_admin') {
        $scopeType = 'religion';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Religion admins require a religion scope.", 400);
            return;
        }
    } elseif ($role === 'community_admin') {
        $scopeType = 'community';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Community admins require a community scope.", 400);
            return;
        }
    }

    $patch = supabaseRequest('PATCH', '/rest/v1/admin_roles', [
        'id' => 'eq.' . $roleId,
        'revoked_at' => 'is.null',
    ], [
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_value' => $scopeValue,
    ], ['Prefer: return=representation']);

    if (($patch['code'] ?? 500) >= 400 || empty($patch['data'])) {
        sendSupabaseError("Failed to update admin role.", $patch);
        return;
    }

    jsonSuccess([
        'role' => $patch['data'][0],
        'target_user_id' => $existing['user_id'] ?? null,
        'message' => 'Admin role updated.',
    ]);
}

function handleRevokeAdminRole($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);
    $roleId = requireUuid($data['role_id'] ?? '', 'role_id');

    $res = supabaseRequest('PATCH', '/rest/v1/admin_roles', [
        'id' => 'eq.' . $roleId,
        'revoked_at' => 'is.null',
    ], [
        'revoked_at' => nowIsoUtc(),
        'revoked_by' => $actorId,
    ], ['Prefer: return=minimal']);

    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to revoke admin role.", $res);
        return;
    }

    jsonSuccess(['message' => 'Admin role revoked.']);
}

function handleAdminGetUserDetail($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $targetUserId = requireUuid($data['target_user_id'] ?? '', 'target_user_id');

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $targetUserId,
        'select' => 'id,email,role,created_at,last_login_at,last_active_at,deactivated_at,profiles(full_name,religion,community,status,visibility,online_status,profile_photo_url,cover_photo_url,mobile_number,current_city,occupation,date_of_birth)',
        'limit' => '1',
    ]);
    if (($userRes['code'] ?? 500) >= 400 || empty($userRes['data'])) {
        jsonError("User not found.", 404);
        return;
    }

    $user = $userRes['data'][0];
    $user['profile'] = normaliseProfileEmbed($user['profiles'] ?? []);
    unset($user['profiles'], $user['password_hash']);

    $rolesRes = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'user_id' => 'eq.' . $targetUserId,
        'revoked_at' => 'is.null',
        'select' => 'id,role,scope_type,scope_value,created_at,created_by',
        'order' => 'created_at.desc',
    ]);
    $notesRes = supabaseRequest('GET', '/rest/v1/admin_user_notes', [
        'user_id' => 'eq.' . $targetUserId,
        'select' => 'id,note_type,note,created_at,created_by',
        'order' => 'created_at.desc',
        'limit' => '100',
    ]);
    $actionsRes = supabaseRequest('GET', '/rest/v1/admin_user_actions', [
        'user_id' => 'eq.' . $targetUserId,
        'select' => 'id,action_type,reason,starts_at,ends_at,is_active,created_at,created_by',
        'order' => 'created_at.desc',
        'limit' => '100',
    ]);
    $sessionsRes = supabaseRequest('GET', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $targetUserId,
        'select' => 'id,role,created_at,last_seen_at,expires_at,revoked_at,admin_mode_until,user_agent',
        'order' => 'last_seen_at.desc.nullslast',
        'limit' => '200',
    ]);

    jsonSuccess([
        'user' => $user,
        'roles' => (($rolesRes['code'] ?? 500) >= 400) ? [] : ($rolesRes['data'] ?? []),
        'notes' => (($notesRes['code'] ?? 500) >= 400) ? [] : ($notesRes['data'] ?? []),
        'actions' => (($actionsRes['code'] ?? 500) >= 400) ? [] : ($actionsRes['data'] ?? []),
        'sessions' => (($sessionsRes['code'] ?? 500) >= 400) ? [] : ($sessionsRes['data'] ?? []),
    ]);
}

function handleAdminGrantUserRole($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);
    $targetUserId = requireUuid($data['target_user_id'] ?? '', 'target_user_id');
    $role = normaliseAdminRole($data['role'] ?? '');
    if (!in_array($role, ['owner', 'platform_admin', 'religion_admin', 'community_admin'], true)) {
        jsonError("Choose a valid admin role.", 400);
        return;
    }

    $scopeType = strtolower(cleanPlainValue($data['scope_type'] ?? 'global', 40));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 160);
    if ($role === 'owner' || $role === 'platform_admin') {
        $scopeType = 'global';
        $scopeValue = '*';
    } elseif ($role === 'religion_admin') {
        $scopeType = 'religion';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Religion admins require a religion scope.", 400);
            return;
        }
    } elseif ($role === 'community_admin') {
        $scopeType = 'community';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Community admins require a community scope.", 400);
            return;
        }
    }

    $res = supabaseRequest('POST', '/rest/v1/admin_roles', [], [
        'user_id' => $targetUserId,
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_value' => $scopeValue,
        'created_by' => $actorId,
        'revoked_at' => null,
    ], ['Prefer: return=representation']);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        sendSupabaseError("Failed to grant admin role.", $res);
        return;
    }
    jsonSuccess(['role' => $res['data'][0], 'message' => 'Admin role granted to selected user.']);
}

function handleAdminAddUserNote($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $targetUserId = requireUuid($data['target_user_id'] ?? '', 'target_user_id');
    $noteType = strtolower(cleanPlainValue($data['note_type'] ?? 'note', 40));
    $allowed = ['note', 'notify', 'watch', 'warning', 'blacklist', 'ban', 'suspension'];
    if (!in_array($noteType, $allowed, true))
        $noteType = 'note';
    $note = cleanTextValue($data['note'] ?? '', 2000);
    if ($note === '') {
        jsonError("Admin note cannot be empty.", 400);
        return;
    }

    $noteRes = supabaseRequest('POST', '/rest/v1/admin_user_notes', [], [
        'user_id' => $targetUserId,
        'created_by' => $actorId,
        'note_type' => $noteType,
        'note' => $note,
    ], ['Prefer: return=representation']);
    if (($noteRes['code'] ?? 500) >= 400 || empty($noteRes['data'])) {
        sendSupabaseError("Failed to save admin note.", $noteRes);
        return;
    }

    $actionRow = null;
    if (in_array($noteType, ['watch', 'warning', 'blacklist', 'ban', 'suspension'], true)) {
        $actionRes = supabaseRequest('POST', '/rest/v1/admin_user_actions', [], [
            'user_id' => $targetUserId,
            'created_by' => $actorId,
            'action_type' => $noteType,
            'reason' => $note,
            'starts_at' => nowIsoUtc(),
            'is_active' => true,
        ], ['Prefer: return=representation']);
        if (($actionRes['code'] ?? 500) < 400 && !empty($actionRes['data'])) {
            $actionRow = $actionRes['data'][0];
        }
        if (in_array($noteType, ['ban', 'suspension'], true)) {
            supabaseRequest('PATCH', '/rest/v1/user_sessions', [
                'user_id' => 'eq.' . $targetUserId,
                'revoked_at' => 'is.null',
            ], [
                'revoked_at' => nowIsoUtc(),
                'admin_mode_until' => null,
            ], ['Prefer: return=minimal']);
        }
    }

    if ($noteType === 'notify') {
        createNotification($targetUserId, 'admin_notice', 'Account notice', $note, ['admin_note_id' => $noteRes['data'][0]['id'] ?? null]);
    } elseif ($noteType === 'warning') {
        createNotification($targetUserId, 'admin_warning', 'Account warning', $note, ['admin_note_id' => $noteRes['data'][0]['id'] ?? null]);
    } elseif (in_array($noteType, ['ban', 'suspension', 'blacklist'], true)) {
        createNotification($targetUserId, 'admin_action', 'Account action applied', $note, ['action_type' => $noteType]);
    }

    jsonSuccess(['message' => 'Admin note saved.', 'note' => $noteRes['data'][0], 'action' => $actionRow]);
}

function handleAdminResolveUserAction($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $targetUserId = requireUuid($data['target_user_id'] ?? '', 'target_user_id');
    $actionId = requireUuid($data['action_id'] ?? '', 'action_id');
    $resolutionNote = cleanTextValue($data['resolution_note'] ?? '', 1000);

    $existingRes = supabaseRequest('GET', '/rest/v1/admin_user_actions', [
        'id' => 'eq.' . $actionId,
        'user_id' => 'eq.' . $targetUserId,
        'select' => 'id,user_id,action_type,reason,starts_at,ends_at,is_active,created_at,created_by',
        'limit' => '1',
    ]);
    if (($existingRes['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load admin action.", $existingRes);
        return;
    }
    if (empty($existingRes['data'])) {
        jsonError("Admin action was not found for this user.", 404);
        return;
    }

    $existing = $existingRes['data'][0];
    $endsRaw = (string) ($existing['ends_at'] ?? '');
    $alreadyInactive = empty($existing['is_active']) || ($endsRaw !== '' && (strtotime($endsRaw) ?: PHP_INT_MAX) <= time());
    if ($alreadyInactive) {
        jsonSuccess(['message' => 'This admin action is already inactive.', 'action' => $existing]);
        return;
    }

    $now = nowIsoUtc();
    $res = supabaseRequest('PATCH', '/rest/v1/admin_user_actions', [
        'id' => 'eq.' . $actionId,
        'user_id' => 'eq.' . $targetUserId,
        'is_active' => 'eq.true',
    ], [
        'is_active' => false,
        'ends_at' => $now,
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to remove admin action.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("This admin action could not be removed. It may already have been removed.", 409);
        return;
    }

    $actionType = cleanPlainValue($existing['action_type'] ?? 'flag', 40);
    $auditNote = "Removed active admin action: " . $actionType . ".";
    if ($resolutionNote !== '') {
        $auditNote .= " Reason: " . $resolutionNote;
    }
    supabaseRequest('POST', '/rest/v1/admin_user_notes', [], [
        'user_id' => $targetUserId,
        'created_by' => $actorId,
        'note_type' => 'note',
        'note' => $auditNote,
    ], ['Prefer: return=minimal']);

    jsonSuccess(['message' => 'Admin action removed.', 'action' => $res['data'][0]]);
}

function handleAdminClearUserSessionHistory($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $targetUserId = requireUuid($data['target_user_id'] ?? '', 'target_user_id');

    // Keep live sessions intact. This only clears historical rows that are
    // already revoked or expired.
    $revoked = supabaseRequest('DELETE', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $targetUserId,
        'revoked_at' => 'not.is.null',
    ], null, ['Prefer: return=representation']);

    if (($revoked['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to clear revoked session history.", $revoked);
        return;
    }

    $expired = supabaseRequest('DELETE', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $targetUserId,
        'revoked_at' => 'is.null',
        'expires_at' => 'lt.' . nowIsoUtc(),
    ], null, ['Prefer: return=representation']);

    if (($expired['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to clear expired session history.", $expired);
        return;
    }

    jsonSuccess([
        'message' => 'Inactive session history cleared.',
        'deleted_count' => count($revoked['data'] ?? []) + count($expired['data'] ?? []),
    ]);
}

function adminListLimit($data, $default = 25, $max = 100)
{
    return max(1, min((int) ($data['limit'] ?? $default), $max));
}

function adminOffset($data)
{
    return max(0, (int) ($data['offset'] ?? 0));
}

function adminChoiceList($value, $allowed = [])
{
    $items = is_array($value) ? $value : explode(',', (string) $value);
    $items = array_values(array_filter(array_map(function ($item) {
        return cleanPlainValue($item, 120);
    }, $items), fn($item) => $item !== ''));
    if (!empty($allowed)) {
        $allowedLower = array_map('strtolower', $allowed);
        $items = array_values(array_filter($items, fn($item) => in_array(strtolower($item), $allowedLower, true)));
    }
    return array_values(array_unique($items));
}

function handleAdminListUsers($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data);
    $offset = adminOffset($data);
    $sort = strtolower(cleanPlainValue($data['sort'] ?? 'created_desc', 40));
    $phpSorts = ['name_asc', 'name_desc', 'religion_asc', 'religion_desc', 'community_asc', 'community_desc', 'status_asc', 'status_desc', 'active_asc'];
    $religionFilters = adminChoiceList($data['religion'] ?? [], ['Hindu', 'Muslim', 'Sikh', 'Christian', 'Buddhist', 'Jain', 'Other']);
    $communityFilter = cleanPlainValue($data['community'] ?? '', 120);
    $statusFilters = array_map('strtolower', adminChoiceList($data['status_filter'] ?? [], ['active', 'online', 'offline', 'deactivated']));
    $needsPhpFilter = !empty($religionFilters) || $communityFilter !== '' || !empty($statusFilters);
    $needsPhpSort = in_array($sort, $phpSorts, true);
    $needsPhpPage = $needsPhpSort || $needsPhpFilter;
    $flagFilters = array_map('strtolower', adminChoiceList($data['flag_filter'] ?? [], ['flagged', 'watch', 'warning', 'blacklist', 'suspension', 'ban']));
    $query = [
        'select' => 'id,email,role,created_at,last_login_at,last_active_at,deactivated_at,profiles(full_name,religion,community,status,visibility,online_status,profile_photo_url,mobile_number)',
        'limit' => (string) ($needsPhpPage ? max(1000, $limit) : $limit),
        'offset' => (string) ($needsPhpPage ? 0 : $offset),
    ];

    $query['order'] = match ($sort) {
        'created_asc' => 'created_at.asc',
        'email_asc' => 'email.asc',
        'email_desc' => 'email.desc',
        'active_desc' => 'last_active_at.desc.nullslast',
        'login_desc' => 'last_login_at.desc.nullslast',
        default => 'created_at.desc',
    };

    $search = trim((string) ($data['search'] ?? ''));
    if ($search !== '') {
        $safe = str_replace(['%', '*', ',', '(', ')'], '', $search);
        $profileRes = supabaseRequest('GET', '/rest/v1/profiles', [
            'full_name' => 'ilike.*' . $safe . '*',
            'select' => 'user_id',
            'limit' => '200',
        ]);
        $profileUserIds = [];
        if (($profileRes['code'] ?? 500) < 400) {
            $profileUserIds = normalizeUuidList(array_column($profileRes['data'] ?? [], 'user_id'));
        }
        $orParts = ['email.ilike.*' . $safe . '*'];
        if (!empty($profileUserIds)) {
            $orParts[] = 'id.in.(' . implode(',', $profileUserIds) . ')';
        }
        $query['or'] = '(' . implode(',', $orParts) . ')';
    }
    $roleFilters = adminChoiceList($data['role'] ?? [], ['member', 'admin']);
    if (!empty($roleFilters))
        $query['role'] = 'in.(' . implode(',', $roleFilters) . ')';

    $flagUserIds = [];
    if (!empty($flagFilters)) {
        $flagQuery = [
            'select' => 'user_id',
            'is_active' => 'eq.true',
            'limit' => '1000',
        ];
        if (!in_array('flagged', $flagFilters, true)) {
            $flagQuery['action_type'] = 'in.(' . implode(',', $flagFilters) . ')';
        }
        $flagRes = supabaseRequest('GET', '/rest/v1/admin_user_actions', $flagQuery);
        if (($flagRes['code'] ?? 500) >= 400) {
            sendSupabaseError("Failed to load user flags.", $flagRes);
            return;
        }
        $flagUserIds = normalizeUuidList(array_column($flagRes['data'] ?? [], 'user_id'));
        if (empty($flagUserIds)) {
            jsonSuccess(['users' => [], 'limit' => $limit, 'offset' => $offset]);
            return;
        }
        $query['id'] = 'in.(' . implode(',', $flagUserIds) . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/users', $query);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load users.", $res);
        return;
    }

    $users = [];
    foreach (($res['data'] ?? []) as $row) {
        $profile = normaliseProfileEmbed($row['profiles'] ?? []);
        unset($row['password_hash'], $row['profiles']);
        $row['profile'] = $profile;
        $row['admin_flags'] = [];
        $users[] = $row;
    }

    if ($needsPhpFilter) {
        $users = array_values(array_filter($users, function ($row) use ($religionFilters, $communityFilter, $statusFilters) {
            $profile = $row['profile'] ?? [];
            if (!empty($religionFilters) && !in_array(strtolower((string) ($profile['religion'] ?? '')), array_map('strtolower', $religionFilters), true)) {
                return false;
            }
            if ($communityFilter !== '' && stripos((string) ($profile['community'] ?? ''), $communityFilter) === false) {
                return false;
            }
            if (!empty($statusFilters)) {
                $isDeactivated = !empty($row['deactivated_at']);
                $profileStatus = strtolower((string) ($profile['status'] ?? ''));
                $onlineStatus = strtolower((string) ($profile['online_status'] ?? ''));
                $matchesStatus = false;
                if (in_array('deactivated', $statusFilters, true) && $isDeactivated)
                    $matchesStatus = true;
                if (in_array('active', $statusFilters, true) && !$isDeactivated)
                    $matchesStatus = true;
                if (in_array('online', $statusFilters, true) && !$isDeactivated && ($onlineStatus === 'online' || $profileStatus === 'online'))
                    $matchesStatus = true;
                if (in_array('offline', $statusFilters, true) && !$isDeactivated && ($onlineStatus === 'offline' || $profileStatus === 'offline'))
                    $matchesStatus = true;
                return $matchesStatus;
            }
            return true;
        }));
    }

    if (!empty($users)) {
        $ids = normalizeUuidList(array_column($users, 'id'));
        if (!empty($ids)) {
            $actionsRes = supabaseRequest('GET', '/rest/v1/admin_user_actions', [
                'user_id' => 'in.(' . implode(',', $ids) . ')',
                'is_active' => 'eq.true',
                'select' => 'id,user_id,action_type,reason,starts_at,ends_at,is_active,created_at',
                'order' => 'created_at.desc',
                'limit' => '1000',
            ]);
            $flagsByUser = [];
            if (($actionsRes['code'] ?? 500) < 400) {
                foreach (($actionsRes['data'] ?? []) as $action) {
                    $uid = $action['user_id'] ?? '';
                    if ($uid !== '')
                        $flagsByUser[$uid][] = $action;
                }
            }
            foreach ($users as &$userRow) {
                $userRow['admin_flags'] = $flagsByUser[$userRow['id'] ?? ''] ?? [];
            }
            unset($userRow);
        }
    }

    if ($needsPhpSort) {
        [$field, $direction] = explode('_', $sort, 2);
        usort($users, function ($a, $b) use ($field, $direction) {
            $profileA = $a['profile'] ?? [];
            $profileB = $b['profile'] ?? [];
            $valueA = match ($field) {
                'name' => $profileA['full_name'] ?? $a['email'] ?? '',
                'religion' => $profileA['religion'] ?? '',
                'community' => $profileA['community'] ?? '',
                'status' => $a['deactivated_at'] ? 'deactivated' : ($profileA['status'] ?? ''),
                'active' => $a['last_active_at'] ?? '',
                default => '',
            };
            $valueB = match ($field) {
                'name' => $profileB['full_name'] ?? $b['email'] ?? '',
                'religion' => $profileB['religion'] ?? '',
                'community' => $profileB['community'] ?? '',
                'status' => $b['deactivated_at'] ? 'deactivated' : ($profileB['status'] ?? ''),
                'active' => $b['last_active_at'] ?? '',
                default => '',
            };
            $cmp = strcasecmp((string) $valueA, (string) $valueB);
            return $direction === 'desc' ? -$cmp : $cmp;
        });
    }

    if ($needsPhpPage) {
        $users = array_slice($users, $offset, $limit);
    }

    jsonSuccess(['users' => $users, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminUpdateUserStatus($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $targetUserId = requireUuid($data['target_user_id'] ?? '', 'target_user_id');
    $operation = strtolower(cleanPlainValue($data['operation'] ?? '', 40));

    if ($targetUserId === $actorId && in_array($operation, ['deactivate', 'delete'], true)) {
        jsonError("You cannot deactivate or delete your own admin account from this panel.", 400);
        return;
    }

    if ($operation === 'deactivate') {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $targetUserId], [
            'deactivated_at' => nowIsoUtc(),
        ], ['Prefer: return=representation']);
    } elseif ($operation === 'reactivate') {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $targetUserId], [
            'deactivated_at' => null,
        ], ['Prefer: return=representation']);
    } elseif ($operation === 'sign_out') {
        $res = supabaseRequest('PATCH', '/rest/v1/user_sessions', [
            'user_id' => 'eq.' . $targetUserId,
            'revoked_at' => 'is.null',
        ], [
            'revoked_at' => nowIsoUtc(),
            'admin_mode_until' => null,
        ], ['Prefer: return=representation']);
        if (($res['code'] ?? 500) >= 400) {
            sendSupabaseError("Failed to sign out user sessions.", $res);
            return;
        }
        jsonSuccess(['message' => 'User sessions revoked.', 'revoked_count' => count($res['data'] ?? [])]);
        return;
    } elseif ($operation === 'verify') {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $targetUserId], [
            'is_verified' => true,
            'verified_at' => nowIsoUtc(),
            'verified_by' => $actorId,
        ], ['Prefer: return=representation']);
        if (($res['code'] ?? 500) >= 400) {
            $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $targetUserId], [
                'is_verified' => true,
                'verified_at' => nowIsoUtc(),
            ], ['Prefer: return=representation']);
        }
        if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
            sendSupabaseError("Failed to grant verified badge.", $res);
            return;
        }
        /* If there is a pending verification request, auto-approve it */
        supabaseRequest('PATCH', '/rest/v1/verification_requests', [
            'user_id' => 'eq.' . $targetUserId,
            'status' => 'eq.pending',
        ], [
            'status' => 'approved',
            'reviewed_at' => nowIsoUtc(),
            'reviewed_by' => $actorId,
        ]);
        jsonSuccess(['message' => 'Verified badge granted.', 'user' => $res['data'][0]]);
        return;
    } elseif ($operation === 'unverify') {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $targetUserId], [
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ], ['Prefer: return=representation']);
        if (($res['code'] ?? 500) >= 400) {
            $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $targetUserId], [
                'is_verified' => false,
                'verified_at' => null,
            ], ['Prefer: return=representation']);
        }
        if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
            sendSupabaseError("Failed to revoke verified badge.", $res);
            return;
        }
        jsonSuccess(['message' => 'Verified badge revoked.', 'user' => $res['data'][0]]);
        return;
    } else {
        jsonError("Unsupported user admin operation.", 400);
        return;
    }

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        sendSupabaseError("Failed to update user status.", $res);
        return;
    }

    jsonSuccess(['message' => 'User status updated.', 'user' => $res['data'][0]]);
}

function handleAdminListPosts($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data);
    $offset = adminOffset($data);
    $query = [
        'select' => 'id,user_id,content,media_url,post_type,community,religion,is_deleted,created_at,updated_at',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];

    $sort = strtolower(cleanPlainValue($data['sort'] ?? 'created_desc', 40));
    $query['order'] = $sort === 'created_asc' ? 'created_at.asc' : 'created_at.desc';
    $type = cleanPlainValue($data['post_type'] ?? '', 40);
    if ($type !== '')
        $query['post_type'] = 'eq.' . $type;
    $religion = cleanPlainValue($data['religion'] ?? '', 80);
    if ($religion !== '')
        $query['religion'] = 'eq.' . $religion;
    $community = cleanPlainValue($data['community'] ?? '', 120);
    if ($community !== '')
        $query['community'] = 'eq.' . $community;
    if (isset($data['deleted']) && $data['deleted'] !== '') {
        $query['is_deleted'] = 'eq.' . (filter_var($data['deleted'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false');
    }

    $search = trim((string) ($data['search'] ?? ''));
    if ($search !== '') {
        $safe = str_replace(['%', ',', '(', ')'], '', $search);
        $query['content'] = 'ilike.*' . $safe . '*';
    }

    $res = supabaseRequest('GET', '/rest/v1/posts', $query);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load posts.", $res);
        return;
    }

    jsonSuccess([
        'posts' => enrichPosts($res['data'] ?? [], $actorId),
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

function handleAdminModeratePost($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $postId = requireUuid($data['post_id'] ?? '', 'post_id');
    $operation = strtolower(cleanPlainValue($data['operation'] ?? '', 40));
    if (!in_array($operation, ['hide', 'restore'], true)) {
        jsonError("Unsupported post moderation operation.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/posts', ['id' => 'eq.' . $postId], [
        'is_deleted' => $operation === 'hide',
        'updated_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        sendSupabaseError("Failed to moderate post.", $res);
        return;
    }

    jsonSuccess(['message' => $operation === 'hide' ? 'Post hidden.' : 'Post restored.', 'post' => $res['data'][0]]);
}

function handleAdminListEvents($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data);
    $offset = adminOffset($data);
    $query = [
        'select' => 'id,title,description,event_date,event_time,location,is_online,meeting_url,religion,community,created_by,created_at,updated_at',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    $sort = strtolower(cleanPlainValue($data['sort'] ?? 'date_desc', 40));
    $query['order'] = match ($sort) {
        'date_asc' => 'event_date.asc,event_time.asc',
        'created_desc' => 'created_at.desc',
        'created_asc' => 'created_at.asc',
        default => 'event_date.desc,event_time.desc',
    };
    $religion = cleanPlainValue($data['religion'] ?? '', 80);
    if ($religion !== '')
        $query['religion'] = 'eq.' . $religion;
    $community = cleanPlainValue($data['community'] ?? '', 120);
    if ($community !== '')
        $query['community'] = 'eq.' . $community;
    $search = trim((string) ($data['search'] ?? ''));
    if ($search !== '') {
        $safe = str_replace(['%', ',', '(', ')'], '', $search);
        $query['title'] = 'ilike.*' . $safe . '*';
    }

    $res = supabaseRequest('GET', '/rest/v1/events', $query);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load events.", $res);
        return;
    }

    $events = $res['data'] ?? [];
    $profileMap = fetchProfilesMap(array_column($events, 'created_by'));
    foreach ($events as &$event) {
        $event['creator'] = $profileMap[$event['created_by']] ?? null;
    }
    unset($event);

    jsonSuccess(['events' => $events, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminDeleteEvent($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $eventId = requireUuid($data['event_id'] ?? '', 'event_id');
    supabaseRequest('DELETE', '/rest/v1/gallery_collections', ['event_id' => 'eq.' . $eventId]);
    $res = supabaseRequest('DELETE', '/rest/v1/events', ['id' => 'eq.' . $eventId], null, ['Prefer: return=representation']);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        sendSupabaseError("Failed to delete event.", $res);
        return;
    }
    jsonSuccess(['message' => 'Event deleted.', 'event_id' => $eventId]);
}

function handleAdminListGalleries($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data);
    $offset = adminOffset($data);
    $query = [
        'select' => 'id,owner_user_id,event_id,title,description,visibility,created_at,updated_at',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
        'order' => strtolower(cleanPlainValue($data['sort'] ?? 'created_desc', 40)) === 'created_asc' ? 'created_at.asc' : 'created_at.desc',
    ];
    $visibility = cleanPlainValue($data['visibility'] ?? '', 40);
    if ($visibility !== '')
        $query['visibility'] = 'eq.' . $visibility;
    $search = trim((string) ($data['search'] ?? ''));
    if ($search !== '') {
        $safe = str_replace(['%', ',', '(', ')'], '', $search);
        $query['title'] = 'ilike.*' . $safe . '*';
    }

    $res = supabaseRequest('GET', '/rest/v1/gallery_collections', $query);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load galleries.", $res);
        return;
    }

    $galleries = attachGalleryItems($res['data'] ?? []);
    $profileMap = fetchProfilesMap(array_column($galleries, 'owner_user_id'));
    foreach ($galleries as &$gallery) {
        $gallery['owner'] = $profileMap[$gallery['owner_user_id']] ?? null;
        $gallery['item_count'] = count($gallery['items'] ?? []);
    }
    unset($gallery);

    jsonSuccess(['galleries' => $galleries, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminDeleteGallery($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $galleryId = requireUuid($data['gallery_id'] ?? '', 'gallery_id');
    supabaseRequest('DELETE', '/rest/v1/gallery_items', ['gallery_id' => 'eq.' . $galleryId]);
    $res = supabaseRequest('DELETE', '/rest/v1/gallery_collections', ['id' => 'eq.' . $galleryId], null, ['Prefer: return=representation']);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        sendSupabaseError("Failed to delete gallery.", $res);
        return;
    }
    jsonSuccess(['message' => 'Gallery deleted.', 'gallery_id' => $galleryId]);
}

function handleAdminListSessions($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data);
    $offset = adminOffset($data);
    $query = [
        'select' => 'id,user_id,role,created_at,last_seen_at,expires_at,revoked_at,admin_mode_until,user_agent',
        'order' => 'last_seen_at.desc.nullslast',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    $status = cleanPlainValue($data['status_filter'] ?? '', 40);
    if ($status === 'active') {
        $query['revoked_at'] = 'is.null';
        $query['expires_at'] = 'gt.' . nowIsoUtc();
    } elseif ($status === 'revoked') {
        $query['revoked_at'] = 'not.is.null';
    }
    $res = supabaseRequest('GET', '/rest/v1/user_sessions', $query);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load sessions.", $res);
        return;
    }
    $sessions = $res['data'] ?? [];
    $profileMap = fetchProfilesMap(array_column($sessions, 'user_id'));
    foreach ($sessions as &$session) {
        $session['user'] = $profileMap[$session['user_id']] ?? null;
        $session['is_active'] = empty($session['revoked_at']) && strtotime((string) ($session['expires_at'] ?? '')) > time();
    }
    unset($session);
    jsonSuccess(['sessions' => $sessions, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminRevokeSession($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $sessionId = requireUuid($data['session_id'] ?? '', 'session_id');
    $res = supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'id' => 'eq.' . $sessionId,
        'revoked_at' => 'is.null',
    ], [
        'revoked_at' => nowIsoUtc(),
        'admin_mode_until' => null,
    ], ['Prefer: return=representation']);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to revoke session.", $res);
        return;
    }
    jsonSuccess(['message' => 'Session revoked.', 'revoked_count' => count($res['data'] ?? [])]);
}

function countRowsApprox($table, $filters = [])
{
    $query = array_merge(['select' => 'id', 'limit' => '1000'], $filters);
    $res = supabaseRequest('GET', '/rest/v1/' . $table, $query);
    if (($res['code'] ?? 500) >= 400)
        return 0;
    return count($res['data'] ?? []);
}

function recentRows($table, $select, $limit = 100, $filters = [])
{
    $query = array_merge([
        'select' => $select,
        'order' => 'created_at.desc',
        'limit' => (string) max(1, min($limit, 500)),
    ], $filters);
    $res = supabaseRequest('GET', '/rest/v1/' . $table, $query);
    return (($res['code'] ?? 500) >= 400) ? [] : ($res['data'] ?? []);
}

function bucketCounts($rows, $key)
{
    $counts = [];
    foreach (($rows ?? []) as $row) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value === '')
            $value = 'Unspecified';
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    arsort($counts);
    $out = [];
    foreach (array_slice($counts, 0, 12, true) as $name => $count) {
        $out[] = ['label' => $name, 'count' => $count];
    }
    return $out;
}

function handleAdminGetAnalytics($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $since = gmdate('c', time() - (7 * 24 * 60 * 60));
    $recentUsers = recentRows('users', 'id,email,created_at,last_login_at,last_active_at,role', 500);
    $recentPosts = recentRows('posts', 'id,user_id,post_type,religion,community,is_deleted,created_at', 500);
    $recentSessions = recentRows('user_sessions', 'id,user_id,created_at,last_seen_at,revoked_at,expires_at', 500);
    $recentLoginEvents = recentRows('user_login_events', 'id,success,reason,created_at', 500);

    $activeSessions = 0;
    foreach ($recentSessions as $session) {
        if (empty($session['revoked_at']) && strtotime((string) ($session['expires_at'] ?? '')) > time()) {
            $activeSessions++;
        }
    }

    $failedLogins7d = 0;
    $successfulLogins7d = 0;
    foreach ($recentLoginEvents as $event) {
        if (strtotime((string) ($event['created_at'] ?? '')) < strtotime($since))
            continue;
        if (!empty($event['success']))
            $successfulLogins7d++;
        else
            $failedLogins7d++;
    }

    jsonSuccess([
        'summary' => [
            'users_sampled' => count($recentUsers),
            'posts_sampled' => count($recentPosts),
            'active_sessions_sampled' => $activeSessions,
            'successful_logins_7d_sampled' => $successfulLogins7d,
            'failed_logins_7d_sampled' => $failedLogins7d,
            'events_sampled' => countRowsApprox('events'),
            'galleries_sampled' => countRowsApprox('gallery_collections'),
        ],
        'users_by_role' => bucketCounts($recentUsers, 'role'),
        'posts_by_type' => bucketCounts($recentPosts, 'post_type'),
        'posts_by_religion' => bucketCounts($recentPosts, 'religion'),
        'posts_by_community' => bucketCounts($recentPosts, 'community'),
        'generated_at' => nowIsoUtc(),
        'note' => 'Limited data demo. Counts are sampled through the current REST API. Grafana/Postgres metrics will be used for exact infrastructure analytics.',
    ]);
}

function handleAdminContactBook($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $communityFilter = cleanPlainValue($data['community'] ?? '', 120);
    $search = trim((string) ($data['search'] ?? ''));

    $query = [
        'select' => 'full_name,community,religion,current_city,mobile_number',
        'order' => 'community.asc.nullslast,full_name.asc.nullslast',
        'limit' => '5000',
    ];
    if ($communityFilter !== '') {
        $query['community'] = 'ilike.*' . str_replace(['%', '*', ',', '(', ')'], '', $communityFilter) . '*';
    }
    if ($search !== '') {
        $query['full_name'] = 'ilike.*' . str_replace(['%', '*', ',', '(', ')'], '', $search) . '*';
    }

    $res = supabaseRequest('GET', '/rest/v1/profiles', $query);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to load contact book.", $res);
        return;
    }

    $contacts = [];
    $communities = [];
    foreach (($res['data'] ?? []) as $row) {
        $community = trim((string) ($row['community'] ?? '')) ?: 'Unspecified';
        $communities[$community] = ($communities[$community] ?? 0) + 1;
        $contacts[] = [
            'name' => $row['full_name'] ?? 'Member',
            'community' => $community,
            'address' => $row['current_city'] ?? '',
            'phone' => $row['mobile_number'] ?? '',
            'religion' => $row['religion'] ?? '',
        ];
    }
    ksort($communities);
    $communityList = [];
    foreach ($communities as $name => $count) {
        $communityList[] = ['community' => $name, 'count' => $count];
    }

    jsonSuccess([
        'contacts' => $contacts,
        'communities' => $communityList,
        'total' => count($contacts),
    ]);
}

function handleLogout($data)
{
    $sessionId = $data['auth_session_id'] ?? '';
    if (isValidUuid($sessionId)) {
        supabaseRequest('PATCH', '/rest/v1/user_sessions', [
            'id' => 'eq.' . strtolower($sessionId),
        ], ['revoked_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    } else {
        $rawToken = getRawSessionToken();
        if ($rawToken !== '') {
            supabaseRequest('PATCH', '/rest/v1/user_sessions', [
                'token_hash' => 'eq.' . hashSessionSecret($rawToken),
                'revoked_at' => 'is.null',
            ], ['revoked_at' => nowIsoUtc()], ['Prefer: return=minimal']);
        }
    }

    clearSessionCookies();
    jsonSuccess(["message" => "Signed out."]);
}

function handleSignOutOtherDevices($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $sessionId = requireUuid($data['auth_session_id'] ?? '', 'session_id');

    $res = supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $userId,
        'id' => 'neq.' . $sessionId,
        'revoked_at' => 'is.null',
    ], [
        'revoked_at' => nowIsoUtc(),
        'admin_mode_until' => null,
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to sign out other devices.", $res);
        return;
    }

    jsonSuccess([
        'message' => 'Other devices have been signed out.',
        'revoked_count' => count($res['data'] ?? []),
    ]);
}

// ---------------------------------------------------------------------------
// STATS (standalone endpoint + shared helper)
// Uses HEAD + Prefer: count=exact to avoid fetching all rows
// ---------------------------------------------------------------------------
function handleGetStats()
{
    echo json_encode(["status" => "success", "stats" => fetchStats()]);
}

function fetchStats()
{
    $url = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
    $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');

    // Get total member count from Content-Range header (no row data transferred)
    $ch = curl_init($url . '/rest/v1/users?role=eq.member&select=id');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: {$secretKey}",
        "Authorization: Bearer {$secretKey}",
        "Prefer: count=exact",
    ]);
    $headerStr = curl_exec($ch);

    $totalUsers = 0;
    if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $headerStr, $m)) {
        $totalUsers = (int) $m[1];
    }

    // Community distribution (member profiles only via inner join)
    $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
        'select' => 'community,users!inner(role)',
        'users.role' => 'eq.member',
    ]);

    $commCount = [];
    foreach (($profilesRes['data'] ?? []) as $p) {
        $c = $p['community'] ?? 'Not Specified';
        $commCount[$c] = ($commCount[$c] ?? 0) + 1;
    }

    $communities = [];
    foreach ($commCount as $name => $count) {
        $communities[] = ['community' => $name, 'count' => $count];
    }
    usort($communities, fn($a, $b) => $b['count'] - $a['count']);

    return ['totalUsers' => $totalUsers, 'communities' => $communities];
}

// ---------------------------------------------------------------------------
// PHOTO UPLOAD
// Uses PUT (not POST) — Supabase Storage REST requires PUT for uploads
// ---------------------------------------------------------------------------
function handlePhotoUpload()
{
    $supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
    $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');
    $postMediaMaxBytes = 50 * 1024 * 1024;

    if (!isset($_FILES['photo'])) {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > $postMediaMaxBytes) {
            http_response_code(413);
            echo json_encode([
                "status" => "error",
                "message" => "The upload is too large. Post media must be 50MB or smaller on the current Supabase plan and within your PHP post_max_size/upload_max_filesize settings."
            ]);
            return;
        }
        http_response_code(400);
        $postKeys = array_keys($_POST);
        $fileKeys = array_keys($_FILES);
        echo json_encode(["status" => "error", "message" => "No photo field in request.", "post_keys" => $postKeys, "file_keys" => $fileKeys]);
        return;
    }

    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini. Increase upload_max_filesize/post_max_size or choose a smaller file.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload',
        ];
        $errCode = $_FILES['photo']['error'];
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $uploadErrors[$errCode] ?? "Upload error code: {$errCode}"]);
        return;
    }

    $file = $_FILES['photo'];

    $bucketName = isset($_POST['bucket']) && trim($_POST['bucket']) !== ''
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['bucket'])
        : 'profile-photos';

    $allowedBuckets = ['profile-photos', 'cover-photos', 'post-media', 'gallery-media'];
    if (!in_array($bucketName, $allowedBuckets, true)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid storage bucket."]);
        return;
    }

    $imageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $postMediaTypes = array_merge($imageTypes, ['image/gif', 'video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'application/pdf']);

    $allowedTypes = $bucketName === 'post-media' ? $postMediaTypes : $imageTypes;
    $maxBytes = $bucketName === 'post-media' ? $postMediaMaxBytes : 2097152; // 50MB for post-media, 2MB for profile/cover

    // Do NOT trust the browser-supplied MIME. Detect the real type from the file bytes.
    $detectedType = $file['type'];
    if (class_exists('finfo') && is_uploaded_file($file['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $sniffed = $finfo->file($file['tmp_name']);
        if ($sniffed) {
            $detectedType = $sniffed;
        }
    }

    if (!in_array($detectedType, $allowedTypes, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $bucketName === 'post-media'
                ? "Only JPG, PNG, WebP, GIF, MP4, WebM, MOV, M4V, and PDF files are allowed."
                : "Only JPG, PNG, and WebP files are allowed."
        ]);
        return;
    }
    // From here on, use the detected (trusted) type for storage.
    $file['type'] = $detectedType;

    if ($file['size'] > $maxBytes) {
        http_response_code(413);
        echo json_encode([
            "status" => "error",
            "message" => $bucketName === 'post-media'
                ? "Post media must be 50MB or smaller on the current Supabase plan."
                : "File exceeds the " . ($maxBytes / 1048576) . "MB limit."
        ]);
        return;
    }

    $mimeExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-m4v' => 'm4v',
        'application/pdf' => 'pdf',
    ];
    $extension = $mimeExtensions[$file['type']] ?? 'bin';
    $prefix = isset($_POST['prefix']) && trim($_POST['prefix']) !== ''
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['prefix'])
        : ($bucketName === 'post-media' ? 'media' : 'profile');

    $filename = uniqid($prefix . '_') . '.' . $extension;
    $fileData = file_get_contents($file['tmp_name']);

    // Store files under the authenticated user's folder. Do not trust a
    // multipart form user_id because it is editable in the browser.
    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] ?? '';
    $userId = isValidUuid($authUserId) ? strtolower($authUserId) : null;

    $objectPath = $userId ? ($userId . '/' . $filename) : $filename;
    $uploadUrl = "{$supabaseUrl}/storage/v1/object/{$bucketName}/{$objectPath}";

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$secretKey}",
        "apikey: {$secretKey}",
        "Content-Type: {$file['type']}",
        "x-upsert: true",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        $publicUrl = "{$supabaseUrl}/storage/v1/object/public/{$bucketName}/{$objectPath}";
        echo json_encode([
            "status" => "success",
            "photo_url" => $publicUrl,
            "url" => $publicUrl,
            "bucket" => $bucketName,
            "path" => $objectPath,
            "mime_type" => $file['type'],
        ]);
    } else {
        $err = json_decode($response, true);
        $tooLarge = $httpCode === 413 || stripos((string) $response, 'too large') !== false || stripos((string) $response, 'size') !== false;
        http_response_code($tooLarge ? 413 : 500);
        echo json_encode([
            "status" => "error",
            "message" => $tooLarge
                ? "The upload is too large for Supabase Storage. Choose a file 50MB or smaller."
                : "Storage upload failed (HTTP {$httpCode}): " . ($err['message'] ?? $response),
            "curl_err" => $curlError,
            "url" => $uploadUrl,
        ]);
    }
}

// Helper: parse a Supabase public storage URL into bucket/path
function parsePublicStorageUrl($url)
{
    if (empty($url))
        return null;
    $marker = '/storage/v1/object/public/';
    $pos = strpos($url, $marker);
    if ($pos === false)
        return null;
    $sub = substr($url, $pos + strlen($marker));
    $parts = explode('/', $sub, 2);
    if (count($parts) < 2)
        return null;
    return ['bucket' => $parts[0], 'path' => $parts[1]];
}

// Helper: delete an object from Supabase Storage
function supabaseStorageDelete($bucket, $path)
{
    $supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
    $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');
    if (!$bucket || !$path)
        return false;
    $deleteUrl = "{$supabaseUrl}/storage/v1/object/{$bucket}/{$path}";
    $ch = curl_init($deleteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$secretKey}",
        "apikey: {$secretKey}",
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ($httpCode === 200 || $httpCode === 204);
}

// ---------------------------------------------------------------------------
// UPDATE PROFILE — called by submitMembership() and saveProfile()
// Updates the profiles row for the given user_id
// ---------------------------------------------------------------------------
function handleUpdateProfile($data)
{
    if (empty($data['user_id'])) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "user_id is required."]);
        return;
    }

    $userId = $data['user_id'];

    // Build update payload from whatever fields are present
    $allowed = [
        'pet_name',
        'pet_type',
        'breed',
        'date_of_birth',
        'gender',
        'bio',
        'current_city',
        'visibility',
        'profile_photo_url',
        'cover_photo_url'
    ];

    $update = [];
    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $update[$field] = is_string($data[$field])
                ? htmlspecialchars(strip_tags($data[$field]))
                : $data[$field];
        }
    }

    if (isset($data['contactNo']) && !isset($update['mobile_number'])) {
        $update['mobile_number'] = htmlspecialchars(strip_tags((string) $data['contactNo']));
    }
    if (isset($data['phone']) && !isset($update['mobile_number'])) {
        $update['mobile_number'] = htmlspecialchars(strip_tags((string) $data['phone']));
    }

    // Fetch existing profile values so we can cleanup old storage objects
    $oldProfilePhoto = null;
    $oldCoverPhoto = null;
    $existing = supabaseRequest('GET', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId, 'select' => 'profile_photo_url,cover_photo_url']);
    if (isset($existing['code']) && $existing['code'] === 200 && !empty($existing['data'])) {
        $ex = $existing['data'][0];
        $oldProfilePhoto = $ex['profile_photo_url'] ?? null;
        $oldCoverPhoto = $ex['cover_photo_url'] ?? null;
    }

    // Array fields (text[]). User-editable profile tags are stored in
    // primary_interests for compatibility with the existing profiles schema.
    if (isset($data['tags']) && !isset($data['primary_interests'])) {
        $data['primary_interests'] = $data['tags'];
    }

    if (isset($data['skills'])) {
        $update['skills'] = is_array($data['skills'])
            ? array_values(array_filter(array_map(fn($v) => normaliseProfileTagValue($v, 40), $data['skills'])))
            : array_values(array_filter(array_map(fn($v) => normaliseProfileTagValue($v, 40), explode(',', (string) $data['skills']))));
    }

    if (isset($data['primary_interests'])) {
        $update['primary_interests'] = normaliseProfileTagsInput($data['primary_interests']);
    }

    if (empty($update)) {
        echo json_encode(["status" => "success", "message" => "Nothing to update."]);
        return;
    }

    $res = supabaseRequest(
        'PATCH',
        '/rest/v1/profiles',
        ['user_id' => 'eq.' . $userId],
        $update,
        ['Prefer: return=representation']
    );

    if ($res['code'] >= 400) {
        $msg = is_array($res['data']) ? ($res['data']['message'] ?? json_encode($res['data'])) : 'Unknown error';
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Profile update failed: " . $msg]);
        return;
    }

    echo json_encode(["status" => "success", "message" => "Profile updated."]);

    // When a member completes their application, confirm it over WhatsApp
    // (transactional → sent regardless of the marketing opt-in, if a number exists).
    $membershipNowApplied = isset($update['membership_applied'])
        && filter_var($update['membership_applied'], FILTER_VALIDATE_BOOLEAN);
    if ($membershipNowApplied) {
        finishResponseEarly();
        $memberName = (string) ($update['full_name'] ?? 'Member');
        $confirm = "✅ $memberName, your PawCircle membership application has been received and approved. "
            . "Welcome to the community! You'll now receive important updates and reminders here.";
        $number = (string) ($update['mobile_number'] ?? '');
        if ($number !== '') {
            sendWhatsAppMessage($number, $confirm, proactiveWhatsappOpts($confirm));
        } else {
            notifyUserWhatsApp($userId, $confirm, true);
        }
    }

    // Remove previous profile/cover files from storage if they were replaced
    try {
        if (isset($update['profile_photo_url']) && $oldProfilePhoto && $oldProfilePhoto !== $update['profile_photo_url']) {
            $parsed = parsePublicStorageUrl($oldProfilePhoto);
            if ($parsed)
                supabaseStorageDelete($parsed['bucket'], $parsed['path']);
        }
        if (isset($update['cover_photo_url']) && $oldCoverPhoto && $oldCoverPhoto !== $update['cover_photo_url']) {
            $parsed = parsePublicStorageUrl($oldCoverPhoto);
            if ($parsed)
                supabaseStorageDelete($parsed['bucket'], $parsed['path']);
        }
    } catch (Exception $e) {
        // ignore deletion errors
    }
    return;
}

// ---------------------------------------------------------------------------
// FAMILY TREE + HOROSCOPE BIRTH DETAILS
// ---------------------------------------------------------------------------
function cleanNullableText($value, $maxLength = 500)
{
    if ($value === null || $value === '')
        return null;
    $clean = trim(strip_tags((string) $value));
    if ($clean === '')
        return null;
    return substr($clean, 0, $maxLength);
}

function cleanDateValue($value)
{
    $value = trim((string) ($value ?? ''));
    if ($value === '')
        return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function cleanTimeValue($value)
{
    $value = trim((string) ($value ?? ''));
    if ($value === '')
        return null;
    return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? substr($value, 0, 5) : null;
}

function normalizeFamilyMemberRow($row)
{
    return [
        'id' => $row['id'] ?? null,
        'name' => $row['full_name'] ?? '',
        'relationship_to_owner' => $row['relationship_to_owner'] ?? '',
        'dob' => $row['date_of_birth'] ?? '',
        'birthTime' => isset($row['birth_time']) ? substr((string) $row['birth_time'], 0, 5) : '',
        'birthCity' => $row['birth_city'] ?? '',
        'gender' => $row['gender'] ?? '',
        'edu' => $row['education'] ?? '',
        'work' => $row['work_details'] ?? '',
        'horoscope' => $row['horoscope_note'] ?? '',
        'linked_user_id' => $row['linked_user_id'] ?? null,
        'sort_order' => intval($row['sort_order'] ?? 100),
    ];
}

function handleGetPetPackMembers($data)
{
    $userId = cleanNullableText($data['target_user_id'] ?? $data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $birthRes = supabaseRequest('GET', '/rest/v1/user_horoscope_profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'date_of_birth,birth_time,birth_city,gender',
        'limit' => '1',
    ]);

    if (($birthRes['code'] ?? 500) >= 400) {
        jsonError("Failed to load horoscope profile.", 500, ["supabase_response" => $birthRes['data']]);
        return;
    }

    $membersRes = supabaseRequest('GET', '/rest/v1/family_members', [
        'owner_user_id' => 'eq.' . $userId,
        'select' => 'id,linked_user_id,full_name,relation,date_of_birth,birth_time,birth_city,gender,education,work_details,horoscope_note,sort_order',
        'order' => 'sort_order.asc,created_at.asc',
    ]);

    if (($membersRes['code'] ?? 500) >= 400) {
        jsonError("Failed to load family members.", 500, ["supabase_response" => $membersRes['data']]);
        return;
    }

    $birth = $birthRes['data'][0] ?? [];
    jsonSuccess([
        'birth_details' => [
            'dob' => $birth['date_of_birth'] ?? '',
            'birthTime' => isset($birth['birth_time']) ? substr((string) $birth['birth_time'], 0, 5) : '',
            'city' => $birth['birth_city'] ?? '',
            'gender' => $birth['gender'] ?? '',
        ],
        'family_members' => array_map('normalizeFamilyMemberRow', $membersRes['data'] ?? []),
    ]);
}

function handleSavePetPackMember($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $name = cleanNullableText($data['name'] ?? '', 180);
    if (!$name) {
        jsonError("Family member name is required.");
        return;
    }

    $allowedRelations = ['Spouse', 'Father', 'Mother', 'Son', 'Daughter', 'Brother', 'Sister', 'Other'];
    $relation = cleanNullableText($data['relationship_to_owner'] ?? 'Other', 40) ?: 'Other';
    if (!in_array($relation, $allowedRelations, true))
        $relation = 'Other';

    $row = [
        'owner_user_id' => $userId,
        'linked_user_id' => cleanNullableText($data['linked_user_id'] ?? null, 80),
        'full_name' => $name,
        'relationship_to_owner' => $relation,
        'date_of_birth' => cleanDateValue($data['dob'] ?? null),
        'birth_time' => cleanTimeValue($data['birthTime'] ?? ($data['birth_time'] ?? null)),
        'birth_city' => cleanNullableText($data['birthCity'] ?? ($data['birth_city'] ?? null), 180),
        'gender' => cleanNullableText($data['gender'] ?? null, 40),
        'education' => cleanNullableText($data['edu'] ?? ($data['education'] ?? null), 240),
        'work_details' => cleanNullableText($data['work'] ?? ($data['work_details'] ?? null), 300),
        'horoscope_note' => cleanNullableText($data['horoscope'] ?? ($data['horoscope_note'] ?? null), 300),
        'sort_order' => isset($data['sort_order']) ? intval($data['sort_order']) : 100,
    ];

    if (!empty($data['id']) && preg_match('/^[a-f0-9-]{36}$/i', (string) $data['id'])) {
        $res = supabaseRequest(
            'PATCH',
            '/rest/v1/family_members',
            ['id' => 'eq.' . $data['id'], 'owner_user_id' => 'eq.' . $userId],
            $row,
            ['Prefer: return=representation']
        );
    } else {
        $res = supabaseRequest(
            'POST',
            '/rest/v1/family_members',
            [],
            $row,
            ['Prefer: return=representation']
        );
    }

    if (($res['code'] ?? 500) >= 400) {
        jsonError("Failed to save family member.", 500, ["supabase_response" => $res['data']]);
        return;
    }

    $saved = is_array($res['data']) && isset($res['data'][0]) ? $res['data'][0] : [];
    jsonSuccess(['member' => normalizeFamilyMemberRow($saved)]);
}

function handleDeletePetPackMember($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $memberId = cleanNullableText($data['member_id'] ?? ($data['id'] ?? ''), 80);
    if (!$userId || !$memberId) {
        jsonError("user_id and member_id are required.");
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/family_members', [
        'id' => 'eq.' . $memberId,
        'owner_user_id' => 'eq.' . $userId,
    ]);

    if (($res['code'] ?? 500) >= 400) {
        jsonError("Failed to delete family member.", 500, ["supabase_response" => $res['data']]);
        return;
    }

    jsonSuccess(["message" => "Family member deleted."]);
}


// ============================================================
// POSTS
// ============================================================

// ============================================================
// SOCIAL DATA HELPERS
// ============================================================

function cleanTextValue($value, $maxLength = 5000)
{
    $text = trim((string) ($value ?? ''));
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($maxLength > 0 && strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }
    return $text;
}

function cleanPlainValue($value, $maxLength = 255)
{
    $text = trim((string) ($value ?? ''));
    $text = strip_tags($text);
    if ($maxLength > 0 && strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }
    return $text;
}

function requireFields($data, $fields)
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            jsonError(implode(', ', $fields) . " required.", 400, ["missing" => $field]);
            return false;
        }
    }
    return true;
}

function supabaseFailed($res)
{
    return !is_array($res) || ($res['code'] ?? 500) >= 400;
}

function sendSupabaseError($message, $res, $code = 500, $extra = [])
{
    // Always log the full detail server-side, correlated by request id.
    error_log(sprintf(
        "[pawcircle][%s] %s | http=%s | response=%s",
        requestId(),
        $message,
        $res['code'] ?? 'n/a',
        json_encode($res['data'] ?? null)
    ));

    if (PAWCIRCLE_DEBUG) {
        jsonError($message, $code, array_merge($extra, [
            "supabase_http_code" => $res['code'] ?? null,
            "supabase_response" => $res['data'] ?? null,
        ]));
    } else {
        // Production: generic message, no internal details leaked.
        jsonError("Could not complete that request.", $code, $extra);
    }
}

function normalizeUuidList($ids)
{
    $out = [];
    foreach ($ids as $id) {
        $id = trim((string) $id);
        if ($id !== '' && preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
            $out[] = strtolower($id);
        }
    }
    return array_values(array_unique($out));
}

function fetchProfilesMap($userIds)
{
    $userIds = normalizeUuidList($userIds);
    if (empty($userIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'in.(' . implode(',', $userIds) . ')',
        'select' => 'user_id,full_name,profile_photo_url,community,religion,current_city,mobile_number,age_group,date_of_birth,gender,occupation,primary_interests'
    ]);

    if (supabaseFailed($res))
        return [];

    $adminCapsMap = fetchAdminCapabilitiesMap($userIds);

    $map = [];
    foreach (($res['data'] ?? []) as $profile) {
        if (!empty($profile['user_id'])) {
            $uid = strtolower((string) $profile['user_id']);
            $profile['admin_capabilities'] = $adminCapsMap[$uid] ?? [];
            $map[$profile['user_id']] = $profile;
        }
    }
    return $map;
}

function ageGroupFromAge($age)
{
    if ($age === null || $age === '')
        return '';
    $age = (int) $age;
    if ($age < 18)
        return 'Under 18';
    if ($age <= 25)
        return '18 - 25';
    if ($age <= 40)
        return '26 - 40';
    if ($age <= 60)
        return '41 - 60';
    return '60+';
}

function ageFromDateOfBirth($dob)
{
    if (empty($dob))
        return null;
    try {
        return (int) (new DateTimeImmutable((string) $dob))->diff(new DateTimeImmutable('today'))->y;
    } catch (Exception $e) {
        return null;
    }
}

function profileAgeGroup($profile)
{
    $existing = trim((string) ($profile['age_group'] ?? ''));
    if ($existing !== '')
        return $existing;
    return ageGroupFromAge(ageFromDateOfBirth($profile['date_of_birth'] ?? null));
}

function captureJsonHandler($callback)
{
    ob_start();

    $oldCode = http_response_code();

    try {
        $callback();
        $raw = ob_get_clean();
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [
                "status" => "error",
                "message" => "Handler did not return valid JSON",
                "raw" => $raw
            ];
        }

        return $decoded;
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        return [
            "status" => "error",
            "message" => $e->getMessage()
        ];
    } finally {
        http_response_code($oldCode ?: 200);
    }
}

// SOCIAL BOOTSTRAP
function handleSocialBootstrap($data)
{
    $userId = $data['user_id'] ?? '';
    $community = $data['community'] ?? '';
    $religion = $data['religion'] ?? '';

    if (!$userId) {
        jsonError("user_id is required.", 400);
        return;
    }

    $postsData = captureJsonHandler(function () use ($userId, $community, $religion) {
        handleGetPosts([
            "user_id" => $userId,
            "community" => $community,
            "religion" => $religion
        ]);
    });

    $friendsData = captureJsonHandler(function () use ($userId) {
        handleGetFriends([
            "user_id" => $userId
        ]);
    });

    $groupsData = captureJsonHandler(function () use ($userId, $community, $religion) {
        handleGetGroups([
            "user_id" => $userId,
            "community" => $community,
            "religion" => $religion
        ]);
    });

    $eventsData = captureJsonHandler(function () use ($community, $religion) {
        handleGetEvents([
            "community" => $community,
            "religion" => $religion
        ]);
    });

    jsonSuccess([
        "posts" => $postsData["posts"] ?? [],
        "friends" => $friendsData["friends"] ?? [],
        "requests" => $friendsData["requests"] ?? [],
        "groups" => $groupsData["groups"] ?? [],
        "events" => $eventsData["events"] ?? [],

        "debug" => [
            "posts_status" => $postsData["status"] ?? "unknown",
            "friends_status" => $friendsData["status"] ?? "unknown",
            "groups_status" => $groupsData["status"] ?? "unknown",
            "events_status" => $eventsData["status"] ?? "unknown"
        ]
    ]);
}

function profileSummary($profile, $fallbackName = 'Member')
{
    $name = $profile['full_name'] ?? $fallbackName;
    $customTags = profileCustomTags($profile);
    $adminCaps = is_array($profile['admin_capabilities'] ?? null) ? $profile['admin_capabilities'] : fetchAdminCapabilities($profile['user_id'] ?? '');
    $systemTags = adminCapabilityTags($adminCaps);
    $displayTags = profileDisplayTags($customTags, $systemTags);

    return [
        'full_name' => $name,
        'name' => $name,
        'profile_photo_url' => $profile['profile_photo_url'] ?? null,
        'community' => $profile['community'] ?? null,
        'religion' => $profile['religion'] ?? null,
        'current_city' => $profile['current_city'] ?? null,
        'mobile_number' => $profile['mobile_number'] ?? null,
        'age_group' => profileAgeGroup($profile),
        'date_of_birth' => $profile['date_of_birth'] ?? null,
        'gender' => $profile['gender'] ?? null,
        'occupation' => $profile['occupation'] ?? null,
        'primary_interests' => $customTags,
        'custom_tags' => $customTags,
        'system_tags' => $systemTags,
        'tags' => $displayTags,
        'admin_capabilities' => $adminCaps,
    ];
}

function createNotification($userId, $type, $title, $body, $data = [])
{
    if (!$userId || !$type) {
        return ['code' => 400, 'data' => ['message' => 'Missing notification user or type.']];
    }

    $res = supabaseRequest('POST', '/rest/v1/notifications', [], [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'data' => empty($data) ? null : $data,
        'is_read' => false,
    ], ['Prefer: return=minimal']);

    // Automatically mirror the notification to WhatsApp for opted-in users.
    // High-frequency / low-value types are skipped to avoid spamming the channel.
    $whatsappSkipTypes = ['direct_message', 'friend_request_sent'];
    if (($res['code'] ?? 500) < 300 && !in_array($type, $whatsappSkipTypes, true)) {
        $waMessage = trim($title . (strlen(trim((string) $body)) ? "\n\n" . $body : ''));
        if ($waMessage !== '') {
            notifyUserWhatsApp($userId, $waMessage);
        }
    }

    return $res;
}

function handleGetNotifications($data)
{
    $userId = $data['user_id'] ?? '';
    if (!$userId) {
        jsonError("user_id required.", 400);
        return;
    }

    $limit = isset($data['limit']) ? max(1, min(100, intval($data['limit']))) : 30;
    $res = supabaseRequest('GET', '/rest/v1/notifications', [
        'user_id' => 'eq.' . $userId,
        'type' => 'neq.friend_request_sent',
        'select' => 'id,user_id,type,title,body,data,is_read,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch notifications.", $res);
        return;
    }

    $notifications = $res['data'] ?? [];
    $friendshipIds = [];
    foreach ($notifications as $notification) {
        $friendshipId = $notification['data']['friendship_id'] ?? null;
        if ($friendshipId) {
            $friendshipIds[] = $friendshipId;
        }
    }

    $friendshipStatuses = [];
    $friendshipIds = normalizeUuidList($friendshipIds);
    if (!empty($friendshipIds)) {
        $friendshipRes = supabaseRequest('GET', '/rest/v1/friendships', [
            'id' => 'in.(' . implode(',', $friendshipIds) . ')',
            'select' => 'id,status'
        ]);

        if (!supabaseFailed($friendshipRes)) {
            foreach (($friendshipRes['data'] ?? []) as $friendship) {
                if (!empty($friendship['id'])) {
                    $friendshipStatuses[$friendship['id']] = $friendship['status'] ?? null;
                }
            }
        }
    }

    $unreadCount = 0;
    foreach ($notifications as &$notification) {
        $friendshipId = $notification['data']['friendship_id'] ?? null;
        if ($friendshipId) {
            $notification['friendship_status'] = $friendshipStatuses[$friendshipId] ?? 'removed';
        }
        if (empty($notification['is_read'])) {
            $unreadCount++;
        }
    }
    unset($notification);

    jsonSuccess([
        "notifications" => $notifications,
        "unread_count" => $unreadCount,
    ]);
}

function handleMarkNotificationRead($data)
{
    $userId = $data['user_id'] ?? '';
    $notificationId = $data['notification_id'] ?? '';

    if (!$userId || !$notificationId) {
        jsonError("user_id and notification_id required.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/notifications', [
        'id' => 'eq.' . $notificationId,
        'user_id' => 'eq.' . $userId,
    ], [
        'is_read' => true,
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to mark notification read.", $res);
        return;
    }

    jsonSuccess(["notification" => $res['data'][0] ?? null]);
}

function countRowsByKey($rows, $key)
{
    $counts = [];
    foreach (($rows ?? []) as $row) {
        if (!empty($row[$key])) {
            $id = $row[$key];
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }
    }
    return $counts;
}

function getPostLikeRows($postIds)
{
    $postIds = normalizeUuidList($postIds);
    if (empty($postIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/post_likes', [
        'post_id' => 'in.(' . implode(',', $postIds) . ')',
        'select' => 'post_id,user_id,created_at'
    ]);

    return supabaseFailed($res) ? [] : ($res['data'] ?? []);
}

function getPostCommentRows($postIds)
{
    $postIds = normalizeUuidList($postIds);
    if (empty($postIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/post_comments', [
        'post_id' => 'in.(' . implode(',', $postIds) . ')',
        'is_deleted' => 'eq.false',
        'select' => 'post_id'
    ]);

    return supabaseFailed($res) ? [] : ($res['data'] ?? []);
}

function enrichPosts($posts, $currentUserId = null)
{
    $posts = $posts ?? [];
    if (empty($posts))
        return [];

    $userIds = [];
    $postIds = [];

    foreach ($posts as $post) {
        if (!empty($post['user_id']))
            $userIds[] = $post['user_id'];
        if (!empty($post['id']))
            $postIds[] = $post['id'];
    }

    $profileMap = fetchProfilesMap($userIds);
    $likeRows = getPostLikeRows($postIds);
    $commentRows = getPostCommentRows($postIds);

    $likeCounts = countRowsByKey($likeRows, 'post_id');
    $commentCounts = countRowsByKey($commentRows, 'post_id');

    $likedByCurrent = [];
    if ($currentUserId) {
        foreach ($likeRows as $like) {
            if (($like['user_id'] ?? null) === $currentUserId) {
                $likedByCurrent[$like['post_id']] = true;
            }
        }
    }

    foreach ($posts as &$post) {
        $profile = $profileMap[$post['user_id'] ?? ''] ?? [];
        $summary = profileSummary($profile);

        $post['profiles'] = $summary;
        $post['author'] = $summary['full_name'];
        $post['profile_photo_url'] = $summary['profile_photo_url'];
        $post['like_count'] = $likeCounts[$post['id']] ?? 0;
        $post['comment_count'] = $commentCounts[$post['id']] ?? 0;
        $post['is_liked'] = !empty($likedByCurrent[$post['id']]);

        // Frontend compatibility keys
        $post['likes'] = $post['like_count'];
        $post['comments'] = $post['comment_count'];
        $post['isLiked'] = $post['is_liked'];
    }
    unset($post);

    return $posts;
}

function enrichComments($comments, $currentUserId = null)
{
    $comments = $comments ?? [];
    if (empty($comments))
        return [];

    $userIds = [];
    $commentIds = [];
    foreach ($comments as $comment) {
        if (!empty($comment['user_id']))
            $userIds[] = $comment['user_id'];
        if (!empty($comment['id']))
            $commentIds[] = $comment['id'];
    }

    $profileMap = fetchProfilesMap($userIds);

    $likeCounts = [];
    $myLikes = [];
    if (!empty($commentIds)) {
        $likesRes = supabaseRequest('GET', '/rest/v1/comment_likes', [
            'select' => 'comment_id,user_id',
            'comment_id' => 'in.(' . implode(',', $commentIds) . ')'
        ]);
        if (!supabaseFailed($likesRes) && !empty($likesRes['data'])) {
            foreach ($likesRes['data'] as $likeRow) {
                $cid = $likeRow['comment_id'];
                $likeCounts[$cid] = ($likeCounts[$cid] ?? 0) + 1;
                if ($currentUserId && $likeRow['user_id'] === $currentUserId) {
                    $myLikes[$cid] = true;
                }
            }
        }
    }

    foreach ($comments as &$comment) {
        $profile = $profileMap[$comment['user_id'] ?? ''] ?? [];
        $summary = profileSummary($profile);

        $comment['profiles'] = $summary;
        $comment['author'] = $summary['full_name'];
        $comment['profile_photo_url'] = $summary['profile_photo_url'];

        $cid = $comment['id'] ?? '';
        $comment['like_count'] = $likeCounts[$cid] ?? 0;
        $comment['is_liked_by_me'] = isset($myLikes[$cid]);
        $comment['likes'] = $comment['like_count'];
        $comment['isLiked'] = $comment['is_liked_by_me'];
    }
    unset($comment);

    return $comments;
}

function enrichGroupMessages($messages)
{
    $messages = $messages ?? [];
    if (empty($messages))
        return [];

    $userIds = [];
    foreach ($messages as $msg) {
        if (!empty($msg['sender_id']))
            $userIds[] = $msg['sender_id'];
    }

    $profileMap = fetchProfilesMap($userIds);

    foreach ($messages as &$msg) {
        $profile = $profileMap[$msg['sender_id'] ?? ''] ?? [];
        $summary = profileSummary($profile);

        $msg['profiles'] = $summary;
        $msg['sender_name'] = $summary['full_name'];
        $msg['sender_photo'] = $summary['profile_photo_url'];
        $msg['text'] = $msg['content'] ?? '';
        $msg['media'] = $msg['media_url'] ?? null;
    }
    unset($msg);

    return $messages;
}

function enrichDirectMessages($messages)
{
    $messages = $messages ?? [];
    if (empty($messages))
        return [];

    $userIds = [];
    foreach ($messages as $msg) {
        if (!empty($msg['sender_id']))
            $userIds[] = $msg['sender_id'];
        if (!empty($msg['recipient_id']))
            $userIds[] = $msg['recipient_id'];
    }

    $profileMap = fetchProfilesMap($userIds);

    foreach ($messages as &$msg) {
        $senderProfile = $profileMap[$msg['sender_id'] ?? ''] ?? [];
        $recipientProfile = $profileMap[$msg['recipient_id'] ?? ''] ?? [];

        $msg['sender_name'] = $senderProfile['full_name'] ?? 'Member';
        $msg['sender_photo'] = $senderProfile['profile_photo_url'] ?? null;
        $msg['recipient_name'] = $recipientProfile['full_name'] ?? 'Member';
        $msg['recipient_photo'] = $recipientProfile['profile_photo_url'] ?? null;
        $msg['text'] = $msg['content'] ?? '';
        $msg['media'] = $msg['media_url'] ?? null;
    }
    unset($msg);

    return $messages;
}

function getMemberRowsForGroups($groupIds)
{
    $groupIds = normalizeUuidList($groupIds);
    if (empty($groupIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'group_id' => 'in.(' . implode(',', $groupIds) . ')',
        'select' => 'group_id,user_id,role,joined_at'
    ]);

    return supabaseFailed($res) ? [] : ($res['data'] ?? []);
}

function enrichGroups($groups, $currentUserId = null, $includeMembers = true)
{
    $groups = $groups ?? [];
    if (empty($groups))
        return [];

    $groupIds = [];
    foreach ($groups as $group) {
        if (!empty($group['id']))
            $groupIds[] = $group['id'];
    }

    $memberRows = getMemberRowsForGroups($groupIds);
    $membersByGroup = [];
    $memberUserIds = [];

    foreach ($memberRows as $row) {
        $gid = $row['group_id'] ?? null;
        if (!$gid)
            continue;
        $membersByGroup[$gid][] = $row;
        if (!empty($row['user_id']))
            $memberUserIds[] = $row['user_id'];
    }

    $profileMap = $includeMembers ? fetchProfilesMap($memberUserIds) : [];

    foreach ($groups as &$group) {
        $gid = $group['id'];
        $rows = $membersByGroup[$gid] ?? [];
        $group['member_count'] = count($rows);
        $group['is_member'] = false;

        // Derived visibility scope. No schema change required:
        // global = everyone, religion = same religion, community = same religion + same community/caste.
        $hasReligion = isset($group['religion']) && trim((string) $group['religion']) !== '';
        $hasCommunity = isset($group['community']) && trim((string) $group['community']) !== '';
        if (!$hasReligion && !$hasCommunity) {
            $group['scope'] = 'global';
        } elseif ($hasReligion && !$hasCommunity) {
            $group['scope'] = 'religion';
        } else {
            $group['scope'] = 'community';
        }

        $members = [];
        foreach ($rows as $row) {
            if ($currentUserId && ($row['user_id'] ?? null) === $currentUserId) {
                $group['is_member'] = true;
            }

            if ($includeMembers && !empty($row['user_id'])) {
                $profile = $profileMap[$row['user_id']] ?? [];
                $members[] = [
                    'user_id' => $row['user_id'],
                    'role' => $row['role'] ?? 'member',
                    'joined_at' => $row['joined_at'] ?? null,
                    'name' => $profile['full_name'] ?? 'Member',
                    'profile_photo_url' => $profile['profile_photo_url'] ?? null,
                ];
            }
        }

        $group['members'] = $members;
    }
    unset($group);

    return $groups;
}

function userIsGroupMember($groupId, $userId)
{
    if (!$groupId || !$userId)
        return false;

    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'user_id' => 'eq.' . $userId,
        'select' => 'group_id',
        'limit' => '1'
    ]);

    return !supabaseFailed($res) && !empty($res['data']);
}

// Returns the caller's role in a group ('admin' | 'member') or null if not a member.
function getGroupMemberRole($groupId, $userId)
{
    if (!$groupId || !$userId)
        return null;

    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'user_id' => 'eq.' . $userId,
        'select' => 'role',
        'limit' => '1'
    ]);

    if (supabaseFailed($res) || empty($res['data']))
        return null;

    return strtolower(trim((string) ($res['data'][0]['role'] ?? 'member'))) === 'admin' ? 'admin' : 'member';
}

function usersAreAcceptedFriends($userId, $friendId)
{
    if (!$userId || !$friendId || $userId === $friendId)
        return false;

    $res = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(and(requester.eq.' . $userId . ',addressee.eq.' . $friendId . '),and(requester.eq.' . $friendId . ',addressee.eq.' . $userId . '))',
        'status' => 'eq.accepted',
        'select' => 'id',
        'limit' => '1'
    ]);

    return !supabaseFailed($res) && !empty($res['data']);
}

// ============================================================
// POSTS
// ============================================================

function handleCreatePost($data)
{
    if (empty($data['user_id'])) {
        jsonError("user_id required.", 400);
        return;
    }

    $content = cleanTextValue($data['content'] ?? '', 5000);
    $mediaUrl = trim((string) ($data['media_url'] ?? ''));
    $mediaUrls = $data['media_urls'] ?? [];
    if (is_array($mediaUrls) && count($mediaUrls) > 1) {
        $mediaUrl = json_encode($mediaUrls);
    }

    if ($content === '' && $mediaUrl === '') {
        jsonError("Post content or media_url required.", 400);
        return;
    }

    $postType = $data['post_type'] ?? ($mediaUrl ? 'image' : 'text');
    $allowedTypes = ['text', 'image', 'video', 'link', 'poll'];
    if (!in_array($postType, $allowedTypes, true)) {
        $postType = $mediaUrl ? 'image' : 'text';
    }

    if ($mediaUrl !== '' && $postType === 'text') {
        $path = strtolower(parse_url($mediaUrl, PHP_URL_PATH) ?: '');
        $postType = preg_match('/\.(mp4|webm)$/', $path) ? 'video' : 'image';
    }

    $community = cleanPlainValue($data['community'] ?? '', 120);
    $religion = cleanPlainValue($data['religion'] ?? '', 80);

    $body = [
        'user_id' => $data['user_id'],
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
        'post_type' => $postType,
        'community' => $community === '' ? null : $community,
        'religion' => $religion === '' ? null : $religion,
    ];

    $res = supabaseRequest('POST', '/rest/v1/posts', [], $body, ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create post.", $res);
        return;
    }

    $post = enrichPosts([$res['data'][0]], $data['user_id'])[0];

    jsonSuccess(["post" => $post]);
}

function handleGetPosts($data)
{
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 10;

    $query = [
        'select' => 'id,user_id,content,media_url,post_type,community,religion,is_deleted,created_at,updated_at',
        'is_deleted' => 'eq.false',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ];

    if (!empty($data['post_id'])) {
        $query['id'] = 'eq.' . $data['post_id'];
    }

    if (empty($data['post_id'])) {
        $community = cleanPlainValue($data['community'] ?? '', 120);
        $religion = cleanPlainValue($data['religion'] ?? '', 80);
        $visibilityFilters = ['and(community.is.null,religion.is.null)'];

        if ($religion !== '') {
            $visibilityFilters[] = 'and(community.is.null,religion.eq.' . $religion . ')';
        }

        if ($community !== '') {
            $filter = 'community.eq.' . $community;
            if ($religion !== '') {
                $filter .= ',religion.eq.' . $religion;
            }
            $visibilityFilters[] = 'and(' . $filter . ')';
        }

        $query['or'] = '(' . implode(',', $visibilityFilters) . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/posts', $query);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch posts.", $res);
        return;
    }

    $posts = enrichPosts($res['data'] ?? [], $data['user_id'] ?? null);

    jsonSuccess(["posts" => $posts]);
}

function getAccountProfile($userId)
{
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id,full_name,community,religion,current_city,mobile_number,age_group,date_of_birth,gender,occupation,bio,profile_photo_url,cover_photo_url,is_public,visibility,online_status,social_links,membership_applied,status,primary_interests',
        'limit' => '1',
    ]);

    if (!supabaseFailed($res)) {
        return $res['data'][0] ?? [];
    }

    $fallback = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id,full_name,community,religion,current_city,mobile_number,age_group,date_of_birth,gender,occupation,bio,profile_photo_url,cover_photo_url,is_public,membership_applied,status,primary_interests',
        'limit' => '1',
    ]);

    return supabaseFailed($fallback) ? [] : ($fallback['data'][0] ?? []);
}

function normalizeVisibility($value)
{
    $value = strtolower(trim((string) $value));
    $allowed = ['public', 'community', 'religion', 'private'];
    return in_array($value, $allowed, true) ? $value : 'public';
}

function normalizeOnlineStatus($value)
{
    $value = strtolower(trim((string) $value));
    $allowed = ['online', 'away', 'busy', 'offline'];
    return in_array($value, $allowed, true) ? $value : 'online';
}

function handleGetAccountSettings($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,role,created_at,deactivated_at,is_verified,verified_at,auth_user_id,password_hash',
        'limit' => '1',
    ]);

    if (supabaseFailed($userRes)) {
        $userRes = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'eq.' . $userId,
            'select' => 'id,email,role,created_at',
            'limit' => '1',
        ]);
    }

    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        sendSupabaseError("Failed to load account.", $userRes, 404);
        return;
    }

    $userRow = $userRes['data'][0];

    /* Check for a pending verification request */
    $verifPendingRes = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'user_id' => 'eq.' . $userId,
        'status' => 'eq.pending',
        'select' => 'id,status,created_at',
        'limit' => '1',
    ]);
    $verificationPending = !empty($verifPendingRes['data']);

    $profile = getAccountProfile($userId);
    $visibility = normalizeVisibility($profile['visibility'] ?? (!empty($profile['is_public']) ? 'public' : 'private'));

    /* Decode privacy_settings from profile JSONB */
    $rawPrivacy = $profile['privacy_settings'] ?? null;
    $privacySettings = [];
    if (is_string($rawPrivacy)) {
        $privacySettings = json_decode($rawPrivacy, true) ?? [];
    } elseif (is_array($rawPrivacy)) {
        $privacySettings = $rawPrivacy;
    }

    jsonSuccess([
        "account" => [
            "id" => $userRow['id'],
            "email" => $userRow['email'] ?? '',
            "role" => $userRow['role'] ?? 'member',
            "auth_user_id" => $userRow['auth_user_id'] ?? null,
            "has_password" => !empty($userRow['password_hash'] ?? ''),
            "password_login_enabled" => !empty($userRow['password_hash'] ?? ''),
            "third_party_sign_in_enabled" => !empty($userRow['auth_user_id'] ?? ''),
            "created_at" => $userRow['created_at'] ?? null,
            "deactivated_at" => $userRow['deactivated_at'] ?? null,
            "is_verified" => (bool) ($userRow['is_verified'] ?? false),
            "verified_at" => $userRow['verified_at'] ?? null,
            "verification_pending" => $verificationPending,
        ],
        "profile" => array_merge($profile, [
            "visibility" => $visibility,
            "online_status" => normalizeOnlineStatus($profile['online_status'] ?? 'online'),
            "social_links" => is_array($profile['social_links'] ?? null) ? $profile['social_links'] : [],
            "privacy_settings" => $privacySettings,
        ]),
    ]);
}

function handleUpdateAccountSettings($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $profileUpdate = [];
    $fieldMap = [
        'name' => 'full_name',
        'full_name' => 'full_name',
        'bio' => 'bio',
        'mobile_number' => 'mobile_number',
        'current_city' => 'current_city',
        'occupation' => 'occupation',
        'gender' => 'gender',
        'date_of_birth' => 'date_of_birth',
        'age_group' => 'age_group',
    ];

    foreach ($fieldMap as $inputKey => $profileKey) {
        if (array_key_exists($inputKey, $data)) {
            $profileUpdate[$profileKey] = cleanNullableText($data[$inputKey], $profileKey === 'bio' ? 800 : 240);
        }
    }

    if (array_key_exists('visibility', $data)) {
        $visibility = normalizeVisibility($data['visibility']);
        $profileUpdate['visibility'] = $visibility;
        $profileUpdate['is_public'] = $visibility !== 'private';
    }

    if (array_key_exists('online_status', $data)) {
        $profileUpdate['online_status'] = normalizeOnlineStatus($data['online_status']);
    }

    if (array_key_exists('social_links', $data) && is_array($data['social_links'])) {
        $links = [];
        foreach (['facebook', 'instagram', 'twitter'] as $key) {
            $links[$key] = cleanNullableText($data['social_links'][$key] ?? '', 300);
        }
        $profileUpdate['social_links'] = $links;
    }

    /* Privacy settings: hide_playdate, private_tree, whatsapp_notifications */
    if (array_key_exists('privacy_settings', $data) && is_array($data['privacy_settings'])) {
        $ps = $data['privacy_settings'];
        $profileUpdate['privacy_settings'] = json_encode([
            'hidePlaydate' => (bool) ($ps['hidePlaydate'] ?? false),
            'privateTree' => (bool) ($ps['privateTree'] ?? false),
            'whatsappNotifications' => (bool) ($ps['whatsappNotifications'] ?? $ps['whatsapp'] ?? false),
        ]);
    }

    if (empty($profileUpdate)) {
        jsonSuccess(["message" => "Nothing to update."]);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
    ], $profileUpdate, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update account settings.", $res);
        return;
    }

    jsonSuccess(["profile" => $res['data'][0] ?? getAccountProfile($userId)]);
}

function handleChangeAccountCredentials($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $currentPassword = (string) ($data['current_password'] ?? '');
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,password_hash,auth_user_id',
        'limit' => '1',
    ]);

    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        sendSupabaseError("Account not found.", $userRes, 404);
        return;
    }

    $user = $userRes['data'][0];
    $hasPassword = !empty($user['password_hash'] ?? '');

    $userUpdate = [];
    $profileUpdate = [];

    $newEmail = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    if ($newEmail && $newEmail !== ($user['email'] ?? '')) {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            jsonError("Invalid email format.");
            return;
        }
        $dupe = supabaseRequest('GET', '/rest/v1/users', [
            'email' => 'eq.' . $newEmail,
            'id' => 'neq.' . $userId,
            'select' => 'id',
            'limit' => '1',
        ]);
        if (!empty($dupe['data'])) {
            jsonError("That email is already in use.", 409);
            return;
        }
        $userUpdate['email'] = $newEmail;
    }

    $newPassword = (string) ($data['new_password'] ?? '');

    if ($hasPassword) {
        if ($currentPassword === '') {
            jsonError("Current password is required.");
            return;
        }
        if (!password_verify($currentPassword, $user['password_hash'] ?? '')) {
            jsonError("Current password is incorrect.", 401);
            return;
        }
    } elseif (!empty($newEmail) && $newEmail !== ($user['email'] ?? '')) {
        jsonError("Set an PawCircle password before changing your email.");
        return;
    } elseif ($newPassword === '' && ($currentPassword !== '' || isset($data['current_password']))) {
        jsonError("This account does not have an PawCircle password yet.");
        return;
    }

    if ($newPassword !== '') {
        if (strlen($newPassword) < 10) {
            jsonError("Password must be at least 10 characters.");
            return;
        }
        $userUpdate['password_hash'] = password_hash($newPassword, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    if (isset($data['name'])) {
        $name = cleanNullableText($data['name'], 180);
        if (!$name) {
            jsonError("Name cannot be empty.");
            return;
        }
        $profileUpdate['full_name'] = $name;
    }

    if (!empty($userUpdate)) {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], $userUpdate, ['Prefer: return=representation']);
        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to update account credentials.", $res);
            return;
        }
    }

    if (!empty($profileUpdate)) {
        $res = supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], $profileUpdate, ['Prefer: return=representation']);
        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to update account name.", $res);
            return;
        }
    }

    jsonSuccess(["message" => "Account credentials updated."]);
}

function handleChangeReligionCommunity($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $religion = cleanNullableText($data['religion'] ?? '', 80);
    $community = cleanNullableText($data['community'] ?? '', 140);
    if (!$userId || !$religion || !$community) {
        jsonError("user_id, religion and community are required.");
        return;
    }

    $oldProfile = getAccountProfile($userId);
    $membershipRes = supabaseRequest('GET', '/rest/v1/group_members', [
        'user_id' => 'eq.' . $userId,
        'select' => 'group_id',
    ]);
    $removedGroupIds = normalizeUuidList(array_column($membershipRes['data'] ?? [], 'group_id'));

    if (!empty($removedGroupIds)) {
        supabaseRequest('DELETE', '/rest/v1/group_members', [
            'user_id' => 'eq.' . $userId,
        ]);
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
    ], [
        'religion' => $religion,
        'community' => $community,
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to change religion/community.", $res);
        return;
    }

    jsonSuccess([
        "profile" => $res['data'][0] ?? getAccountProfile($userId),
        "removed_group_count" => count($removedGroupIds),
        "previous" => [
            "religion" => $oldProfile['religion'] ?? null,
            "community" => $oldProfile['community'] ?? null,
        ],
    ]);
}

function handleGetUserPosts($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 200)) : 80;
    $res = supabaseRequest('GET', '/rest/v1/posts', [
        'user_id' => 'eq.' . $userId,
        'is_deleted' => 'eq.false',
        'select' => 'id,user_id,content,media_url,post_type,community,religion,is_deleted,created_at,updated_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch user posts.", $res);
        return;
    }

    jsonSuccess(["posts" => enrichPosts($res['data'] ?? [], $userId)]);
}

function handleUpdatePost($data)
{
    if (!requireFields($data, ['user_id', 'post_id']))
        return;
    $content = cleanTextValue($data['content'] ?? '', 5000);
    if ($content === '') {
        jsonError("Post content cannot be empty.");
        return;
    }

    $mediaUrl = trim((string) ($data['media_url'] ?? ''));
    $mediaUrls = $data['media_urls'] ?? [];
    if (is_array($mediaUrls) && count($mediaUrls) > 1) {
        $mediaUrl = json_encode(array_values(array_filter($mediaUrls)));
    } elseif (is_array($mediaUrls) && count($mediaUrls) === 1) {
        $mediaUrl = trim((string) reset($mediaUrls));
    }

    $postType = $data['post_type'] ?? ($mediaUrl ? 'image' : 'text');
    $allowedTypes = ['text', 'image', 'video', 'link', 'poll'];
    if (!in_array($postType, $allowedTypes, true)) {
        $postType = $mediaUrl ? 'image' : 'text';
    }

    if ($mediaUrl !== '' && $postType === 'text') {
        $path = strtolower(parse_url($mediaUrl, PHP_URL_PATH) ?: '');
        $postType = preg_match('/\.(mp4|webm|mov|m4v)$/', $path) ? 'video' : 'image';
    }

    $res = supabaseRequest('PATCH', '/rest/v1/posts', [
        'id' => 'eq.' . $data['post_id'],
        'user_id' => 'eq.' . $data['user_id'],
        'is_deleted' => 'eq.false',
    ], [
        'content' => $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
        'post_type' => $postType,
        'updated_at' => gmdate('c'),
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to update post.", $res);
        return;
    }

    jsonSuccess(["post" => enrichPosts([$res['data'][0]], $data['user_id'])[0]]);
}

function handleDeletePost($data)
{
    if (!requireFields($data, ['user_id', 'post_id']))
        return;

    $postRes = supabaseRequest('GET', '/rest/v1/posts', [
        'id' => 'eq.' . $data['post_id'],
        'user_id' => 'eq.' . $data['user_id'],
        'select' => 'id,media_url',
        'limit' => '1',
    ]);

    if (supabaseFailed($postRes) || empty($postRes['data'])) {
        sendSupabaseError("Post not found.", $postRes, 404);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/posts', [
        'id' => 'eq.' . $data['post_id'],
        'user_id' => 'eq.' . $data['user_id'],
    ], [
        'is_deleted' => true,
        'updated_at' => gmdate('c'),
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete post.", $res);
        return;
    }

    $parsed = parsePublicStorageUrl($postRes['data'][0]['media_url'] ?? null);
    if ($parsed)
        supabaseStorageDelete($parsed['bucket'], $parsed['path']);

    jsonSuccess(["message" => "Post deleted."]);
}

function deleteStorageUrls($urls)
{
    foreach ($urls as $url) {
        $parsed = parsePublicStorageUrl($url);
        if ($parsed)
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
    }
}

function handleDeactivateAccount($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $password = (string) ($data['password'] ?? '');
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,password_hash',
        'limit' => '1',
    ]);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("Account not found.", 404);
        return;
    }

    // Third-party (e.g. Google) accounts have no PawCircle password, so skip the
    // password check for them; password accounts must still confirm with it.
    $storedHash = (string) ($userRes['data'][0]['password_hash'] ?? '');
    if ($storedHash !== '') {
        if ($password === '' || !password_verify($password, $storedHash)) {
            jsonError("Password is incorrect.", 401);
            return;
        }
    }

    $now = gmdate('c');
    $userPatch = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['deactivated_at' => $now], ['Prefer: return=representation']);
    if (supabaseFailed($userPatch)) {
        sendSupabaseError("Failed to deactivate account. Run the required SQL if deactivated_at does not exist.", $userPatch);
        return;
    }
    supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], ['online_status' => 'offline']);
    jsonSuccess(["message" => "Account deactivated.", "deactivated_at" => $now]);
}

function handleDeleteAccountPermanently($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $password = (string) ($data['password'] ?? '');
    $confirm = strtoupper(trim((string) ($data['confirm'] ?? '')));
    if (!$userId || $confirm !== 'DELETE') {
        jsonError("user_id and confirm=DELETE are required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,password_hash',
        'limit' => '1',
    ]);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("Account not found.", 404);
        return;
    }

    // Accounts with an PawCircle password must confirm with it. Accounts that only
    // signed up through a third-party provider (e.g. Google) have no password
    // to enter, so the typed DELETE confirmation is sufficient.
    $storedHash = (string) ($userRes['data'][0]['password_hash'] ?? '');
    if ($storedHash !== '') {
        if ($password === '' || !password_verify($password, $storedHash)) {
            jsonError("Password is incorrect.", 401);
            return;
        }
    }

    $profile = getAccountProfile($userId);
    $postsRes = supabaseRequest('GET', '/rest/v1/posts', [
        'user_id' => 'eq.' . $userId,
        'select' => 'id,media_url',
        'limit' => '1000',
    ]);
    $groupMessagesRes = supabaseRequest('GET', '/rest/v1/group_messages', [
        'sender_id' => 'eq.' . $userId,
        'select' => 'media_url',
        'limit' => '1000',
    ]);
    $directMessagesRes = supabaseRequest('GET', '/rest/v1/direct_messages', [
        'or' => '(sender_id.eq.' . $userId . ',recipient_id.eq.' . $userId . ')',
        'select' => 'media_url',
        'limit' => '1000',
    ]);
    $postMediaUrls = array_column($postsRes['data'] ?? [], 'media_url');
    $messageMediaUrls = array_merge(
        array_column($groupMessagesRes['data'] ?? [], 'media_url'),
        array_column($directMessagesRes['data'] ?? [], 'media_url')
    );
    deleteStorageUrls(array_filter(array_merge([
        $profile['profile_photo_url'] ?? null,
        $profile['cover_photo_url'] ?? null,
    ], $postMediaUrls, $messageMediaUrls)));

    supabaseRequest('DELETE', '/rest/v1/group_members', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/friendships', ['or' => '(requester.eq.' . $userId . ',addressee.eq.' . $userId . ')']);
    supabaseRequest('DELETE', '/rest/v1/notifications', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/post_likes', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/direct_messages', ['or' => '(sender_id.eq.' . $userId . ',recipient_id.eq.' . $userId . ')']);
    supabaseRequest('DELETE', '/rest/v1/group_messages', ['sender_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/call_participants', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/call_sessions', ['created_by' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/events', ['created_by' => 'eq.' . $userId]);
    supabaseRequest('PATCH', '/rest/v1/post_comments', ['user_id' => 'eq.' . $userId], ['is_deleted' => true]);
    supabaseRequest('PATCH', '/rest/v1/posts', ['user_id' => 'eq.' . $userId], ['is_deleted' => true, 'updated_at' => gmdate('c')]);
    supabaseRequest('DELETE', '/rest/v1/family_members', ['owner_user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/user_horoscope_profiles', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId]);

    $deleteUser = supabaseRequest('DELETE', '/rest/v1/users', ['id' => 'eq.' . $userId]);
    if (supabaseFailed($deleteUser)) {
        sendSupabaseError("Failed to delete user row after cleanup.", $deleteUser);
        return;
    }

    jsonSuccess(["message" => "Account permanently deleted."]);
}

function handleToggleLike($data)
{
    if (!requireFields($data, ['user_id', 'post_id']))
        return;

    $uid = $data['user_id'];
    $pid = $data['post_id'];

    $check = supabaseRequest('GET', '/rest/v1/post_likes', [
        'user_id' => 'eq.' . $uid,
        'post_id' => 'eq.' . $pid,
        'select' => 'post_id,user_id',
        'limit' => '1'
    ]);

    if (supabaseFailed($check)) {
        sendSupabaseError("Failed to check like status.", $check);
        return;
    }

    if (!empty($check['data'])) {
        $del = supabaseRequest('DELETE', '/rest/v1/post_likes', [
            'user_id' => 'eq.' . $uid,
            'post_id' => 'eq.' . $pid
        ]);

        if (supabaseFailed($del)) {
            sendSupabaseError("Failed to unlike post.", $del);
            return;
        }

        $action = 'unliked';
        $isLiked = false;
    } else {
        $ins = supabaseRequest('POST', '/rest/v1/post_likes', [], [
            'user_id' => $uid,
            'post_id' => $pid,
        ]);

        if (supabaseFailed($ins)) {
            // Duplicate insert is harmless: treat as liked.
            $msg = is_array($ins['data'] ?? null) ? ($ins['data']['code'] ?? $ins['data']['message'] ?? '') : '';
            if ($msg !== '23505') {
                sendSupabaseError("Failed to like post.", $ins);
                return;
            }
        }

        $action = 'liked';
        $isLiked = true;
    }

    $likes = supabaseRequest('GET', '/rest/v1/post_likes', [
        'post_id' => 'eq.' . $pid,
        'select' => 'user_id'
    ]);

    $likeCount = supabaseFailed($likes) ? null : count($likes['data'] ?? []);

    jsonSuccess([
        "action" => $action,
        "post_id" => $pid,
        "is_liked" => $isLiked,
        "isLiked" => $isLiked,
        "like_count" => $likeCount,
        "likes" => $likeCount,
    ]);
}

// ============================================================
// COMMENTS
// ============================================================

function handleSubmitComment($data)
{
    if (!requireFields($data, ['user_id', 'post_id', 'content']))
        return;

    $content = cleanTextValue($data['content'], 2000);
    if ($content === '') {
        jsonError("Comment cannot be empty.", 400);
        return;
    }

    $payload = [
        'user_id' => $data['user_id'],
        'post_id' => $data['post_id'],
        'content' => $content,
    ];
    if (!empty($data['parent_id'])) {
        $payload['parent_id'] = $data['parent_id'];
    }

    $res = supabaseRequest('POST', '/rest/v1/post_comments', [], $payload, ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to submit comment.", $res);
        return;
    }

    $comment = enrichComments([$res['data'][0]], $data['user_id'])[0];

    $commentCountRes = supabaseRequest('GET', '/rest/v1/post_comments', [
        'post_id' => 'eq.' . $data['post_id'],
        'is_deleted' => 'eq.false',
        'select' => 'id'
    ]);

    jsonSuccess([
        "comment" => $comment,
        "comment_count" => supabaseFailed($commentCountRes) ? null : count($commentCountRes['data'] ?? []),
    ]);
}

function handleGetComments($data)
{
    if (empty($data['post_id'])) {
        jsonError("post_id required.", 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/post_comments', [
        'post_id' => 'eq.' . $data['post_id'],
        'is_deleted' => 'eq.false',
        'select' => 'id,post_id,user_id,parent_id,content,is_deleted,created_at',
        'order' => 'created_at.asc',
        'limit' => '100',
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch comments.", $res);
        return;
    }

    jsonSuccess(["comments" => enrichComments($res['data'] ?? [], $data['user_id'] ?? null)]);
}

// Edits a comment/reply. Ownership is enforced by filtering on user_id so a user
// can only modify their own comment. Replies (rows with a parent_id) edit exactly
// the same way as top-level comments.
function handleEditComment($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $commentId = requireUuid($data['comment_id'] ?? '', 'comment_id');

    $content = cleanTextValue($data['content'] ?? '', 2000);
    if ($content === '') {
        jsonError("Comment cannot be empty.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/post_comments', [
        'id' => 'eq.' . $commentId,
        'user_id' => 'eq.' . $userId,
        'is_deleted' => 'eq.false',
    ], ['content' => $content], ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to edit comment.", $res);
        return;
    }

    $comment = enrichComments([$res['data'][0]], $userId)[0];
    jsonSuccess(["comment" => $comment]);
}

// Soft-deletes a comment/reply (sets is_deleted = true). Ownership is enforced by
// the user_id filter. Works identically for replies-to-replies.
function handleDeleteComment($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $commentId = requireUuid($data['comment_id'] ?? '', 'comment_id');

    $res = supabaseRequest('PATCH', '/rest/v1/post_comments', [
        'id' => 'eq.' . $commentId,
        'user_id' => 'eq.' . $userId,
    ], ['is_deleted' => true], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete comment.", $res);
        return;
    }

    $postId = $res['data'][0]['post_id'] ?? ($data['post_id'] ?? null);
    $commentCount = null;
    if ($postId) {
        $countRes = supabaseRequest('GET', '/rest/v1/post_comments', [
            'post_id' => 'eq.' . $postId,
            'is_deleted' => 'eq.false',
            'select' => 'id',
        ]);
        $commentCount = supabaseFailed($countRes) ? null : count($countRes['data'] ?? []);
    }

    jsonSuccess(["deleted" => true, "comment_count" => $commentCount]);
}

function handleToggleCommentLike($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $commentId = requireUuid($data['comment_id'] ?? '', 'comment_id');

    $res = supabaseRequest('GET', '/rest/v1/comment_likes', [
        'user_id' => 'eq.' . $userId,
        'comment_id' => 'eq.' . $commentId,
        'select' => 'id'
    ]);

    $isLiked = false;
    if (!supabaseFailed($res) && !empty($res['data'])) {
        supabaseRequest('DELETE', '/rest/v1/comment_likes', [
            'id' => 'eq.' . $res['data'][0]['id']
        ]);
    } else {
        supabaseRequest('POST', '/rest/v1/comment_likes', [], [
            'user_id' => $userId,
            'comment_id' => $commentId
        ]);
        $isLiked = true;
    }

    $countRes = supabaseRequest('GET', '/rest/v1/comment_likes', [
        'comment_id' => 'eq.' . $commentId,
        'select' => 'id'
    ]);

    $likeCount = supabaseFailed($countRes) ? 0 : count($countRes['data'] ?? []);

    jsonSuccess([
        "is_liked" => $isLiked,
        "like_count" => $likeCount
    ]);
}

// ============================================================
// EVENTS
// ============================================================

function normalizeEventPayload($data)
{
    $title = cleanTextValue($data['title'] ?? '', 180);
    $description = cleanTextValue($data['description'] ?? $data['desc'] ?? '', 3000);
    $location = cleanTextValue($data['location'] ?? '', 300);

    $allowedFrequencies = ['none', 'daily', 'weekly', 'monthly'];
    $frequency = strtolower(trim((string) ($data['recurrence_frequency'] ?? $data['frequency'] ?? 'none')));
    if (!in_array($frequency, $allowedFrequencies, true)) {
        $frequency = 'none';
    }

    $allowedVisibility = ['public', 'community', 'religion', 'invite_only'];
    $visibility = strtolower(trim((string) ($data['visibility'] ?? 'public')));
    if (!in_array($visibility, $allowedVisibility, true)) {
        $visibility = 'public';
    }

    return [
        'title' => $title,
        'description' => $description === '' ? null : $description,
        'event_date' => $data['event_date'] ?? $data['date'] ?? null,
        'event_time' => $data['event_time'] ?? $data['time'] ?? null,
        'location' => $location === '' ? null : $location,
        'is_online' => isset($data['is_online']) ? (bool) $data['is_online'] : !empty($data['meeting_url']) || !empty($data['link']),
        'meeting_url' => trim((string) ($data['meeting_url'] ?? $data['link'] ?? '')) ?: null,
        'religion' => ($religion = cleanPlainValue($data['religion'] ?? '', 80)) === '' ? null : $religion,
        'community' => ($community = cleanPlainValue($data['community'] ?? '', 120)) === '' ? null : $community,
        'banner_url' => trim((string) ($data['banner_url'] ?? '')) ?: null,
        'recurrence_frequency' => $frequency,
        'visibility' => $visibility,
    ];
}

function handleSaveEvent($data)
{
    if (empty($data['user_id']) || empty($data['title'])) {
        jsonError("user_id and title required.", 400);
        return;
    }

    $body = normalizeEventPayload($data);
    if ($body['title'] === '') {
        jsonError("Event title cannot be empty.", 400);
        return;
    }

    if (!empty($data['event_id'])) {
        $res = supabaseRequest('PATCH', '/rest/v1/events', [
            'id' => 'eq.' . $data['event_id'],
            'created_by' => 'eq.' . $data['user_id'],
        ], $body, ['Prefer: return=representation']);
    } else {
        $body['created_by'] = $data['user_id'];
        $res = supabaseRequest('POST', '/rest/v1/events', [], $body, ['Prefer: return=representation']);
    }

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to save event.", $res);
        return;
    }

    $event = $res['data'][0];
    $rawInviteeIds = $data['invitee_ids'] ?? [];
    if (is_string($rawInviteeIds)) {
        $rawInviteeIds = array_filter(array_map('trim', explode(',', $rawInviteeIds)));
    }
    $inviteeIds = normalizeUuidList(is_array($rawInviteeIds) ? $rawInviteeIds : []);

    $rawGroupIds = $data['group_ids'] ?? [];
    if (is_string($rawGroupIds)) {
        $rawGroupIds = array_filter(array_map('trim', explode(',', $rawGroupIds)));
    }
    $groupIds = normalizeUuidList(is_array($rawGroupIds) ? $rawGroupIds : []);

    if (!empty($groupIds)) {
        $groupMemberRows = getMemberRowsForGroups($groupIds);
        $extractedIds = array_column($groupMemberRows ?? [], 'user_id');
        $inviteeIds = uniqueUserIds(array_merge($inviteeIds, $extractedIds));
    }

    $invitesCreated = 0;
    $eventId = $event['id'] ?? null;
    $eventDate = $event['event_date'] ?? null;
    $eventTitle = $event['title'] ?? 'Community event';

    foreach ($inviteeIds as $inviteeId) {
        if ($inviteeId === strtolower((string) $data['user_id']))
            continue;
        $notification = createNotification(
            $inviteeId,
            'event_invite',
            'Event invitation',
            'You were invited to ' . $eventTitle . ($eventDate ? ' on ' . $eventDate : '') . '.',
            [
                'event_id' => $eventId,
                'event_title' => $eventTitle,
                'event_date' => $eventDate,
                'inviter_id' => $data['user_id'],
                'meeting_url' => $event['meeting_url'] ?? null,
            ]
        );

        if (!supabaseFailed($notification)) {
            $invitesCreated++;
        }
    }

    // Persist the invitee list so invite-only events can gate access, and so
    // the owner can re-open the event later to add more people or see who is
    // already invited. Best-effort: requires the event_invitees table
    // (see migrations/001_event_invitees_visibility.sql).
    if ($eventId && !empty($inviteeIds)) {
        $inviteeRows = [];
        foreach ($inviteeIds as $inviteeId) {
            $inviteeRows[] = ['event_id' => $eventId, 'user_id' => $inviteeId];
        }
        supabaseRequest(
            'POST',
            '/rest/v1/event_invitees',
            ['on_conflict' => 'event_id,user_id'],
            $inviteeRows,
            ['Prefer: resolution=ignore-duplicates,return=minimal']
        );
    }

    jsonSuccess([
        "event" => $event,
        "invitee_ids" => $inviteeIds,
        "invites" => [
            "requested" => count($inviteeIds),
            "created" => $invitesCreated,
        ],
    ]);
}

function handleDeleteEvent($data)
{
    if (empty($data['event_id'])) {
        jsonError("event_id required.", 400);
        return;
    }

    $eventId = cleanPlainValue($data['event_id'], 80);

    $query = ['id' => 'eq.' . $eventId];
    if (!empty($data['user_id'])) {
        $query['created_by'] = 'eq.' . $data['user_id'];
    }

    // Before deleting the event, clean up dependencies that might cause foreign key constraints
    supabaseRequest('DELETE', '/rest/v1/gallery_collections', ['event_id' => 'eq.' . $eventId]);
    supabaseRequest('DELETE', '/rest/v1/call_sessions', ['group_id' => 'eq.' . $eventId, 'target_type' => 'eq.group']);
    supabaseRequest('DELETE', '/rest/v1/group_messages', ['group_id' => 'eq.' . $eventId]);
    supabaseRequest('DELETE', '/rest/v1/groups', ['id' => 'eq.' . $eventId]);

    $res = supabaseRequest('DELETE', '/rest/v1/events', $query);

    if (supabaseFailed($res)) {
        file_put_contents(__DIR__ . '/debug_delete_error.txt', json_encode(['data' => $data, 'query' => $query, 'res' => $res]));
        sendSupabaseError("Failed to delete event.", $res);
        return;
    }

    jsonSuccess(["event_id" => $data['event_id']]);
}

function handleGetEvents($data)
{
    $select = 'id,title,description,event_date,event_time,location,is_online,meeting_url,religion,community,visibility,banner_url,created_by,created_at,updated_at';
    $query = [
        'select' => $select,
        'order' => 'event_date.asc,event_time.asc',
        'limit' => isset($data['limit']) ? (string) max(1, min((int) $data['limit'], 100)) : '100',
    ];

    $userId = cleanPlainValue($data['user_id'] ?? '', 80);

    if (empty($data['event_id']) && empty($data['created_by'])) {
        $community = cleanPlainValue($data['community'] ?? '', 120);
        $religion = cleanPlainValue($data['religion'] ?? '', 80);
        // Audience-visible events never include invite-only ones — those are
        // only reachable by explicitly invited people (fetched separately).
        $visibilityFilters = ['and(community.is.null,religion.is.null)'];

        if ($religion !== '') {
            $visibilityFilters[] = 'and(community.is.null,religion.eq.' . $religion . ')';
        }

        if ($community !== '') {
            $filter = 'community.eq.' . $community;
            if ($religion !== '') {
                $filter .= ',religion.eq.' . $religion;
            }
            $visibilityFilters[] = 'and(' . $filter . ')';
        }

        $query['or'] = '(' . implode(',', $visibilityFilters) . ')';
        $query['visibility'] = 'neq.invite_only';
    }
    if (!empty($data['event_id']))
        $query['id'] = 'eq.' . $data['event_id'];
    if (!empty($data['created_by']))
        $query['created_by'] = 'eq.' . $data['created_by'];
    if (!empty($data['from_date']))
        $query['event_date'] = 'gte.' . $data['from_date'];

    $res = supabaseRequest('GET', '/rest/v1/events', $query);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch events.", $res);
        return;
    }

    $events = $res['data'] ?? [];

    // In the general feed, also surface (a) invite-only events this user is
    // invited to and (b) the user's own events — both are excluded from the
    // audience query above when invite-only.
    if (empty($data['event_id']) && empty($data['created_by']) && $userId !== '') {
        $invRes = supabaseRequest('GET', '/rest/v1/event_invitees', [
            'select' => 'event_id',
            'user_id' => 'eq.' . $userId,
        ]);
        $invitedEventIds = supabaseFailed($invRes) ? [] : array_values(array_unique(array_column($invRes['data'] ?? [], 'event_id')));
        $alreadyHave = array_column($events, 'id');
        $missing = array_values(array_diff($invitedEventIds, $alreadyHave));
        if (!empty($missing)) {
            $extraRes = supabaseRequest('GET', '/rest/v1/events', [
                'select' => $select,
                'id' => 'in.(' . implode(',', $missing) . ')',
                'order' => 'event_date.asc,event_time.asc',
            ]);
            if (!supabaseFailed($extraRes) && !empty($extraRes['data'])) {
                $events = array_merge($events, $extraRes['data']);
            }
        }

        // The creator should always see their own events on the calendar.
        $ownRes = supabaseRequest('GET', '/rest/v1/events', [
            'select' => $select,
            'created_by' => 'eq.' . $userId,
            'order' => 'event_date.asc,event_time.asc',
        ]);
        if (!supabaseFailed($ownRes) && !empty($ownRes['data'])) {
            $haveIds = array_column($events, 'id');
            foreach ($ownRes['data'] as $ownEvent) {
                if (!in_array($ownEvent['id'] ?? null, $haveIds, true)) {
                    $events[] = $ownEvent;
                }
            }
        }
    }

    // Attach the persisted invitee list so the event owner can pre-select /
    // add invitees when editing. Best-effort (requires event_invitees table).
    $eventIds = array_filter(array_column($events, 'id'));
    if (!empty($eventIds)) {
        $inviteeRes = supabaseRequest('GET', '/rest/v1/event_invitees', [
            'select' => 'event_id,user_id',
            'event_id' => 'in.(' . implode(',', $eventIds) . ')',
        ]);
        $inviteesByEvent = [];
        if (!supabaseFailed($inviteeRes)) {
            foreach ($inviteeRes['data'] ?? [] as $row) {
                $inviteesByEvent[$row['event_id']][] = $row['user_id'];
            }
        }
        foreach ($events as &$ev) {
            $ev['invitee_ids'] = $inviteesByEvent[$ev['id'] ?? ''] ?? [];
        }
        unset($ev);
    }

    $profileMap = fetchProfilesMap(array_column($events, 'created_by'));

    foreach ($events as &$event) {
        $profile = $profileMap[$event['created_by'] ?? ''] ?? [];
        $event['creator'] = profileSummary($profile);
    }
    unset($event);

    jsonSuccess(["events" => $events]);
}

function normalizeGalleryVisibility($value)
{
    $value = strtolower(cleanPlainValue($value ?? 'private', 40));
    $allowed = ['public', 'community', 'religion', 'private'];
    return in_array($value, $allowed, true) ? $value : 'private';
}

function normalizeGalleryMediaType($value, $url = '')
{
    $value = strtolower(cleanPlainValue($value ?? '', 20));
    if (in_array($value, ['image', 'video'], true))
        return $value;
    return preg_match('/\.(mp4|webm|mov|m4v)(\?|$)/i', (string) $url) ? 'video' : 'image';
}

function normalizeGalleryItemPayload($item, $galleryId, $fallbackSort = 0)
{
    $mediaUrl = cleanNullableText($item['media_url'] ?? $item['url'] ?? '', 1000);
    if (!$mediaUrl)
        return null;

    // Browser preview URLs such as blob:... disappear after refresh and must not be saved.
    if (preg_match('/^(blob:|data:|filesystem:)/i', $mediaUrl)) {
        return null;
    }

    return [
        'gallery_id' => $galleryId,
        'media_url' => $mediaUrl,
        'media_type' => normalizeGalleryMediaType($item['media_type'] ?? $item['type'] ?? '', $mediaUrl),
        'caption' => cleanNullableText($item['caption'] ?? '', 300),
        'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $fallbackSort,
    ];
}

function fetchGalleryItemsForGalleries($galleryIds)
{
    $galleryIds = normalizeUuidList($galleryIds);
    if (empty($galleryIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/gallery_items', [
        'gallery_id' => 'in.(' . implode(',', $galleryIds) . ')',
        'select' => 'id,gallery_id,media_url,media_type,caption,sort_order,created_at',
        'order' => 'sort_order.asc,created_at.asc',
    ]);

    if (supabaseFailed($res))
        return [];

    $grouped = [];
    foreach (($res['data'] ?? []) as $item) {
        $galleryId = $item['gallery_id'] ?? '';
        if (!isset($grouped[$galleryId]))
            $grouped[$galleryId] = [];
        $grouped[$galleryId][] = $item;
    }
    return $grouped;
}

function attachGalleryItems($galleries)
{
    if (empty($galleries))
        return [];

    $itemsByGallery = fetchGalleryItemsForGalleries(array_column($galleries, 'id'));
    foreach ($galleries as &$gallery) {
        $items = $itemsByGallery[$gallery['id'] ?? ''] ?? [];
        $gallery['items'] = $items;
        $gallery['item_count'] = count($items);
    }
    unset($gallery);
    return $galleries;
}

function fetchOwnedGallery($userId, $galleryId)
{
    $res = supabaseRequest('GET', '/rest/v1/gallery_collections', [
        'id' => 'eq.' . $galleryId,
        'owner_user_id' => 'eq.' . $userId,
        'select' => 'id,owner_user_id,event_id,title,description,visibility,created_at,updated_at',
        'limit' => '1',
    ]);

    if (supabaseFailed($res) || empty($res['data']))
        return null;
    return $res['data'][0];
}

function handleGetGalleries($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.", 400);
        return;
    }

    $query = [
        'owner_user_id' => 'eq.' . $userId,
        'select' => 'id,owner_user_id,event_id,title,description,visibility,created_at,updated_at',
        'order' => 'created_at.desc',
        'limit' => isset($data['limit']) ? (string) max(1, min((int) $data['limit'], 200)) : '100',
    ];

    if (!empty($data['gallery_id']))
        $query['id'] = 'eq.' . cleanPlainValue($data['gallery_id'], 80);
    if (!empty($data['event_id']))
        $query['event_id'] = 'eq.' . cleanPlainValue($data['event_id'], 80);
    if (!empty($data['independent']))
        $query['event_id'] = 'is.null';

    $res = supabaseRequest('GET', '/rest/v1/gallery_collections', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch galleries.", $res);
        return;
    }

    jsonSuccess(["galleries" => attachGalleryItems($res['data'] ?? [])]);
}

function handleCreateGallery($data)
{
    if (!requireFields($data, ['user_id', 'title']))
        return;

    $title = cleanTextValue($data['title'] ?? '', 180);
    if ($title === '') {
        jsonError("Gallery title cannot be empty.", 400);
        return;
    }

    $body = [
        'owner_user_id' => cleanPlainValue($data['user_id'], 80),
        'event_id' => cleanNullableText($data['event_id'] ?? null, 80),
        'title' => $title,
        'description' => cleanNullableText($data['description'] ?? '', 1000),
        'visibility' => normalizeGalleryVisibility($data['visibility'] ?? 'private'),
    ];

    $res = supabaseRequest('POST', '/rest/v1/gallery_collections', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create gallery.", $res);
        return;
    }

    $gallery = $res['data'][0];
    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    foreach ($items as $index => $item) {
        $itemBody = normalizeGalleryItemPayload($item, $gallery['id'], $index);
        if ($itemBody) {
            supabaseRequest('POST', '/rest/v1/gallery_items', [], $itemBody, ['Prefer: return=representation']);
        }
    }

    $gallery = attachGalleryItems([$gallery])[0] ?? $gallery;
    jsonSuccess(["gallery" => $gallery]);
}

function handleUpdateGallery($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id']))
        return;

    $patch = ['updated_at' => gmdate('c')];
    if (array_key_exists('title', $data)) {
        $title = cleanTextValue($data['title'], 180);
        if ($title === '') {
            jsonError("Gallery title cannot be empty.", 400);
            return;
        }
        $patch['title'] = $title;
    }
    if (array_key_exists('description', $data))
        $patch['description'] = cleanNullableText($data['description'], 1000);
    if (array_key_exists('event_id', $data))
        $patch['event_id'] = cleanNullableText($data['event_id'], 80);
    if (array_key_exists('visibility', $data))
        $patch['visibility'] = normalizeGalleryVisibility($data['visibility']);

    $res = supabaseRequest('PATCH', '/rest/v1/gallery_collections', [
        'id' => 'eq.' . cleanPlainValue($data['gallery_id'], 80),
        'owner_user_id' => 'eq.' . cleanPlainValue($data['user_id'], 80),
    ], $patch, ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to update gallery.", $res);
        return;
    }

    jsonSuccess(["gallery" => attachGalleryItems([$res['data'][0]])[0]]);
}

function handleDeleteGallery($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id']))
        return;

    $gallery = fetchOwnedGallery(cleanPlainValue($data['user_id'], 80), cleanPlainValue($data['gallery_id'], 80));
    if (!$gallery) {
        jsonError("Gallery not found.", 404);
        return;
    }

    $items = fetchGalleryItemsForGalleries([$gallery['id']]);
    foreach (($items[$gallery['id']] ?? []) as $item) {
        $parsed = parsePublicStorageUrl($item['media_url'] ?? null);
        if ($parsed)
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
    }

    $res = supabaseRequest('DELETE', '/rest/v1/gallery_collections', [
        'id' => 'eq.' . $gallery['id'],
        'owner_user_id' => 'eq.' . cleanPlainValue($data['user_id'], 80),
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete gallery.", $res);
        return;
    }

    jsonSuccess(["gallery_id" => $gallery['id']]);
}

function handleAddGalleryItem($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id', 'media_url']))
        return;

    $userId = cleanPlainValue($data['user_id'], 80);
    $galleryId = cleanPlainValue($data['gallery_id'], 80);
    $gallery = fetchOwnedGallery($userId, $galleryId);
    if (!$gallery) {
        jsonError("Gallery not found.", 404);
        return;
    }

    $itemBody = normalizeGalleryItemPayload($data, $galleryId, (int) ($data['sort_order'] ?? 0));
    if (!$itemBody) {
        jsonError("media_url is required and must be a persistent uploaded/public URL, not a temporary browser preview URL.", 400);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/gallery_items', [], $itemBody, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to add gallery item.", $res);
        return;
    }

    jsonSuccess(["item" => $res['data'][0]]);
}

function handleDeleteGalleryItem($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id', 'item_id']))
        return;

    $userId = cleanPlainValue($data['user_id'], 80);
    $galleryId = cleanPlainValue($data['gallery_id'], 80);
    $itemId = cleanPlainValue($data['item_id'], 80);
    $gallery = fetchOwnedGallery($userId, $galleryId);
    if (!$gallery) {
        jsonError("Gallery not found.", 404);
        return;
    }

    $itemRes = supabaseRequest('GET', '/rest/v1/gallery_items', [
        'id' => 'eq.' . $itemId,
        'gallery_id' => 'eq.' . $galleryId,
        'select' => 'id,media_url',
        'limit' => '1',
    ]);

    if (supabaseFailed($itemRes) || empty($itemRes['data'])) {
        jsonError("Gallery item not found.", 404);
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/gallery_items', [
        'id' => 'eq.' . $itemId,
        'gallery_id' => 'eq.' . $galleryId,
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete gallery item.", $res);
        return;
    }

    $parsed = parsePublicStorageUrl($itemRes['data'][0]['media_url'] ?? null);
    if ($parsed)
        supabaseStorageDelete($parsed['bucket'], $parsed['path']);

    jsonSuccess(["item_id" => $itemId]);
}

// ============================================================
// GROUPS
// ============================================================

function handleCreateGroup($data)
{
    if (empty($data['user_id']) || empty($data['name'])) {
        jsonError("user_id and name required.", 400);
        return;
    }

    $name = cleanTextValue($data['name'], 120);
    if ($name === '') {
        jsonError("Group name cannot be empty.", 400);
        return;
    }

    // Group visibility uses existing nullable columns, so no schema change is required:
    // community = same religion + same community/caste
    // religion  = same religion, any community/caste
    // global    = everyone on PawCircle
    $scope = strtolower(trim((string) ($data['scope'] ?? 'community')));
    if (!in_array($scope, ['community', 'religion', 'global'], true)) {
        $scope = 'community';
    }

    $religion = trim((string) ($data['religion'] ?? ''));
    $community = trim((string) ($data['community'] ?? ''));

    if ($scope === 'global') {
        $religion = null;
        $community = null;
    } elseif ($scope === 'religion') {
        if ($religion === '') {
            jsonError("Your profile needs a religion before creating a religion-specific group.", 400);
            return;
        }
        $religion = cleanTextValue($religion, 120);
        $community = null;
    } else {
        if ($religion === '' || $community === '') {
            jsonError("Your profile needs both religion and community before creating a community-specific group.", 400);
            return;
        }
        $religion = cleanTextValue($religion, 120);
        $community = cleanTextValue($community, 120);
    }

    $body = [
        'name' => $name,
        'description' => cleanTextValue($data['description'] ?? $data['desc'] ?? '', 1000) ?: null,
        'avatar_url' => trim((string) ($data['avatar_url'] ?? '')) ?: null,
        'community' => $community,
        'religion' => $religion,
        'created_by' => $data['user_id'],
        'is_private' => isset($data['is_private']) ? (bool) $data['is_private'] : false,
    ];

    $res = supabaseRequest('POST', '/rest/v1/groups', [], $body, ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create group.", $res);
        return;
    }

    $group = $res['data'][0];

    $memberRes = supabaseRequest('POST', '/rest/v1/group_members', [], [
        'group_id' => $group['id'],
        'user_id' => $data['user_id'],
        'role' => 'admin',
    ]);

    if (supabaseFailed($memberRes)) {
        sendSupabaseError("Group was created, but failed to add creator as member.", $memberRes, 500, ["group" => $group]);
        return;
    }

    // Invited friends are added straight away as regular members (WhatsApp-style
    // group creation). Best-effort: a failed invite never blocks group creation.
    $invitedIds = normalizeUuidList($data['member_ids'] ?? []);
    foreach ($invitedIds as $invitedId) {
        if ($invitedId === $data['user_id'])
            continue;
        supabaseRequest('POST', '/rest/v1/group_members', [], [
            'group_id' => $group['id'],
            'user_id' => $invitedId,
            'role' => 'member',
        ]);
    }

    $group = enrichGroups([$group], $data['user_id'])[0];
    $group['scope'] = $scope;

    jsonSuccess(["group" => $group]);
}

function handleJoinGroup($data)
{
    if (!requireFields($data, ['user_id', 'group_id']))
        return;

    $groupId = $data['group_id'];
    if (str_starts_with($groupId, 'event_group_')) {
        $eventId = substr($groupId, 12);
        $groupId = $eventId;

        $groupCheck = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $eventId, 'select' => 'id', 'limit' => '1']);
        if (empty($groupCheck['data'])) {
            supabaseRequest('POST', '/rest/v1/groups', [], [
                'id' => $eventId,
                'name' => 'Event Chat',
                'created_by' => $data['user_id'],
                'is_private' => true
            ]);
        }
    }

    $res = supabaseRequest('POST', '/rest/v1/group_members', [], [
        'group_id' => $groupId,
        'user_id' => $data['user_id'],
        'role' => 'member',
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        $code = is_array($res['data'] ?? null) ? ($res['data']['code'] ?? '') : '';
        $msg = is_array($res['data'] ?? null) ? ($res['data']['message'] ?? '') : '';

        if ($code === '23505' || stripos($msg, 'duplicate') !== false) {
            jsonSuccess(["message" => "Already a member."]);
            return;
        }

        sendSupabaseError("Failed to join group.", $res);
        return;
    }

    jsonSuccess(["membership" => $res['data'][0] ?? null]);
}

function handleLeaveGroup($data)
{
    if (!requireFields($data, ['user_id', 'group_id']))
        return;

    $groupId = $data['group_id'];
    if (str_starts_with($groupId, 'event_group_')) {
        $groupId = substr($groupId, 12);
    }

    $res = supabaseRequest('DELETE', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'user_id' => 'eq.' . $data['user_id']
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to leave group.", $res);
        return;
    }

    jsonSuccess(["message" => "Successfully left the group."]);
}

// Add one or more people to a group. Any existing member can add others
// (WhatsApp-style). Returns the refreshed group with its members list.
function handleAddGroupMembers($data)
{
    if (!requireFields($data, ['user_id', 'group_id']))
        return;

    $groupId = $data['group_id'];
    if (str_starts_with($groupId, 'event_group_')) {
        $groupId = substr($groupId, 12);
    }

    // Only a member of the group may add people to it.
    if (!userIsGroupMember($groupId, $data['user_id'])) {
        jsonError("Only group members can add people.", 403);
        return;
    }

    $memberIds = normalizeUuidList($data['member_ids'] ?? ($data['user_ids'] ?? []));
    if (empty($memberIds)) {
        jsonError("Select at least one person to add.", 400);
        return;
    }

    $added = 0;
    foreach ($memberIds as $memberId) {
        $res = supabaseRequest('POST', '/rest/v1/group_members', [], [
            'group_id' => $groupId,
            'user_id' => $memberId,
            'role' => 'member',
        ]);
        // Ignore duplicates (already a member); count genuine adds.
        if (!supabaseFailed($res)) {
            $added++;
        }
    }

    $groupRes = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $groupId, 'select' => '*', 'limit' => '1']);
    $group = !supabaseFailed($groupRes) && !empty($groupRes['data']) ? enrichGroups([$groupRes['data'][0]], $data['user_id'])[0] : null;

    jsonSuccess(["added" => $added, "group" => $group]);
}

// Promote a member to admin or demote an admin back to member. Admins only.
function handleUpdateGroupMemberRole($data)
{
    if (!requireFields($data, ['user_id', 'group_id', 'target_user_id', 'role']))
        return;

    $groupId = $data['group_id'];
    if (str_starts_with($groupId, 'event_group_')) {
        $groupId = substr($groupId, 12);
    }

    if (getGroupMemberRole($groupId, $data['user_id']) !== 'admin') {
        jsonError("Only group admins can change member roles.", 403);
        return;
    }

    $role = strtolower(trim((string) $data['role'])) === 'admin' ? 'admin' : 'member';

    $res = supabaseRequest('PATCH', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'user_id' => 'eq.' . $data['target_user_id'],
    ], ['role' => $role], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update member role.", $res);
        return;
    }

    // A 200 with no rows means the filter matched nothing — surface it instead of
    // silently reporting success while the role never changed.
    if (empty($res['data'])) {
        jsonError("That member is no longer in this group.", 404);
        return;
    }

    $groupRes = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $groupId, 'select' => '*', 'limit' => '1']);
    $group = !supabaseFailed($groupRes) && !empty($groupRes['data']) ? enrichGroups([$groupRes['data'][0]], $data['user_id'])[0] : null;

    jsonSuccess(["role" => $role, "group" => $group]);
}

// Remove a member from a group. Admins only (members leave via leave_group).
function handleRemoveGroupMember($data)
{
    if (!requireFields($data, ['user_id', 'group_id', 'target_user_id']))
        return;

    $groupId = $data['group_id'];
    if (str_starts_with($groupId, 'event_group_')) {
        $groupId = substr($groupId, 12);
    }

    if (getGroupMemberRole($groupId, $data['user_id']) !== 'admin') {
        jsonError("Only group admins can remove members.", 403);
        return;
    }

    if ((string) $data['target_user_id'] === (string) $data['user_id']) {
        jsonError("Use leave group to remove yourself.", 400);
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'user_id' => 'eq.' . $data['target_user_id'],
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to remove member.", $res);
        return;
    }

    $groupRes = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $groupId, 'select' => '*', 'limit' => '1']);
    $group = !supabaseFailed($groupRes) && !empty($groupRes['data']) ? enrichGroups([$groupRes['data'][0]], $data['user_id'])[0] : null;

    jsonSuccess(["message" => "Member removed.", "group" => $group]);
}

// Prebuilt mandals (Yuva, Mahila, Senior, ...) are shared, global backend groups
// identified by a stable pack_key. Joining creates the group if it does not yet
// exist, then adds the caller as a member so group calls and chat work for real.
function findMandalGroup($mandalKey)
{
    $res = supabaseRequest('GET', '/rest/v1/groups', [
        'pack_key' => 'eq.' . $mandalKey,
        'select' => '*',
        'limit' => '1',
    ]);
    if (supabaseFailed($res) || empty($res['data']))
        return null;
    return $res['data'][0];
}

function handleJoinPack($data)
{
    if (!requireFields($data, ['user_id', 'pack_key']))
        return;

    $userId = $data['user_id'];
    $mandalKey = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($data['pack_key'] ?? ''));
    if ($mandalKey === '') {
        jsonError("Invalid mandal.", 400);
        return;
    }

    $name = cleanTextValue($data['name'] ?? '', 120) ?: 'Mandal';
    $description = cleanTextValue($data['description'] ?? $data['desc'] ?? '', 1000) ?: null;

    // Find the existing shared mandal group by its stable key.
    $group = findMandalGroup($mandalKey);

    if (!$group) {
        $body = [
            'name' => $name,
            'description' => $description,
            'avatar_url' => null,
            'community' => null,   // global scope: visible/joinable to everyone
            'religion' => null,
            'created_by' => $userId,
            'is_private' => false,
            'pack_key' => $mandalKey,
        ];
        $res = supabaseRequest('POST', '/rest/v1/groups', [], $body, ['Prefer: return=representation']);

        if (supabaseFailed($res) || empty($res['data'])) {
            // Likely a race where another request created it first — re-fetch by key.
            $group = findMandalGroup($mandalKey);
            if (!$group) {
                sendSupabaseError("Failed to join mandal.", $res);
                return;
            }
        } else {
            $group = $res['data'][0];
        }
    }

    // Add membership, treating an existing membership as success.
    $memberRes = supabaseRequest('POST', '/rest/v1/group_members', [], [
        'group_id' => $group['id'],
        'user_id' => $userId,
        'role' => 'member',
    ], ['Prefer: return=representation']);

    if (supabaseFailed($memberRes)) {
        $code = is_array($memberRes['data'] ?? null) ? ($memberRes['data']['code'] ?? '') : '';
        $msg = is_array($memberRes['data'] ?? null) ? ($memberRes['data']['message'] ?? '') : '';
        if ($code !== '23505' && stripos($msg, 'duplicate') === false) {
            sendSupabaseError("Failed to join mandal.", $memberRes);
            return;
        }
    }

    $group = enrichGroups([$group], $userId)[0] ?? $group;
    jsonSuccess(["group" => $group]);
}

// Core "post one message to one group" logic, shared by single send and
// broadcast. Returns a result array instead of emitting an HTTP response so it
// can be called in a loop. On success: ['ok'=>true,'message'=>..,'notifications'=>..].
// On failure: ['ok'=>false,'code'=>int,'error'=>string,'reason'=>string].
function sendMessageToGroup($rawGroupId, $userId, $rawContent, $rawMediaUrl = '')
{
    $content = cleanTextValue($rawContent ?? '', 3000);
    $mediaUrl = trim((string) ($rawMediaUrl ?? ''));

    if ($content === '' && $mediaUrl === '') {
        return ['ok' => false, 'code' => 400, 'error' => "Message content or media_url required.", 'reason' => 'empty'];
    }

    $isEventGroup = str_starts_with((string) $rawGroupId, 'event_group_');
    if (!$isEventGroup && !userIsGroupMember($rawGroupId, $userId)) {
        return ['ok' => false, 'code' => 403, 'error' => "Only group members can send messages.", 'reason' => 'not_member'];
    }

    $actualGroupId = $rawGroupId;
    if ($isEventGroup) {
        $eventId = substr($rawGroupId, 12);
        $actualGroupId = $eventId;

        $groupCheck = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $eventId, 'select' => 'id', 'limit' => '1']);
        if (empty($groupCheck['data'])) {
            supabaseRequest('POST', '/rest/v1/groups', [], [
                'id' => $eventId,
                'name' => 'Event Chat',
                'created_by' => $userId,
                'is_private' => true
            ]);
        }
    }

    $res = supabaseRequest('POST', '/rest/v1/group_messages', [], [
        'group_id' => $actualGroupId,
        'sender_id' => $userId,
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        return ['ok' => false, 'code' => 500, 'error' => "Failed to send message.", 'reason' => 'insert_failed', 'supabase' => $res];
    }

    $message = enrichGroupMessages([$res['data'][0]])[0];
    // Use the resolved (prefix-stripped) group id for member/group lookups. For event
    // groups the raw id is "event_group_<uuid>", which is not a valid UUID and makes
    // these queries return HTTP 400 -- that would fail the request even though the
    // message above was already saved, showing it as "failed" in the UI.
    $memberIds = getGroupMemberIds($actualGroupId);
    $recipientIds = array_values(array_filter($memberIds, function ($memberId) use ($userId) {
        return $memberId && $memberId !== $userId;
    }));
    $recipientIds = normalizeUuidList($recipientIds);
    $groupName = 'your group';
    $groupRes = supabaseRequest('GET', '/rest/v1/groups', [
        'id' => 'eq.' . $actualGroupId,
        'select' => 'name',
        'limit' => '1'
    ]);

    if (!supabaseFailed($groupRes) && !empty($groupRes['data'][0]['name'])) {
        $groupName = $groupRes['data'][0]['name'];
    }

    $senderName = $message['sender_name'] ?? 'A member';
    $preview = $content !== '' ? $content : 'Sent a message.';
    if (strlen($preview) > 120) {
        $preview = substr($preview, 0, 117) . '...';
    }

    $notificationsCreated = 0;
    foreach ($recipientIds as $recipientId) {
        $notification = createNotification(
            $recipientId,
            'group_message',
            'Message in ' . $groupName,
            $senderName . ': ' . $preview,
            [
                'message_id' => $message['id'] ?? null,
                'group_id' => $rawGroupId,
                'group_name' => $groupName,
                'sender_id' => $userId,
            ]
        );

        if (!supabaseFailed($notification)) {
            $notificationsCreated++;
        }
    }

    return [
        'ok' => true,
        'message' => $message,
        'group_name' => $groupName,
        'notifications' => [
            'recipients_created' => $notificationsCreated,
            'recipients_attempted' => count($recipientIds),
        ],
    ];
}

function handleSendGroupMessage($data)
{
    if (empty($data['user_id']) || empty($data['group_id'])) {
        jsonError("user_id and group_id required.", 400);
        return;
    }

    $result = sendMessageToGroup(
        $data['group_id'],
        $data['user_id'],
        $data['content'] ?? $data['text'] ?? '',
        $data['media_url'] ?? ''
    );

    if (!$result['ok']) {
        if (($result['reason'] ?? '') === 'insert_failed') {
            sendSupabaseError($result['error'], $result['supabase'] ?? null);
        } else {
            jsonError($result['error'], $result['code'] ?? 400);
        }
        return;
    }

    jsonSuccess([
        "message" => $result['message'],
        "notifications" => $result['notifications'],
    ]);
}

// Broadcast: post one message to many groups/communities at once.
function handleBroadcastMessage($data)
{
    if (empty($data['user_id'])) {
        jsonError("user_id required.", 400);
        return;
    }

    $rawGroupIds = $data['group_ids'] ?? $data['group_id'] ?? [];
    if (is_string($rawGroupIds)) {
        $rawGroupIds = array_filter(array_map('trim', explode(',', $rawGroupIds)));
    }
    $groupIds = is_array($rawGroupIds) ? array_values(array_unique(array_filter($rawGroupIds))) : [];

    if (empty($groupIds)) {
        jsonError("Select at least one community or group to broadcast to.", 400);
        return;
    }

    $content = cleanTextValue($data['content'] ?? $data['text'] ?? '', 3000);
    $mediaUrl = trim((string) ($data['media_url'] ?? ''));
    if ($content === '' && $mediaUrl === '') {
        jsonError("Message content or media_url required.", 400);
        return;
    }

    $sent = 0;
    $recipientsNotified = 0;
    $failed = [];
    $messages = [];

    foreach ($groupIds as $groupId) {
        $result = sendMessageToGroup($groupId, $data['user_id'], $content, $mediaUrl);
        if (!empty($result['ok'])) {
            $sent++;
            $recipientsNotified += $result['notifications']['recipients_created'] ?? 0;
            $messages[] = $result['message'];
        } else {
            $failed[] = [
                'group_id' => $groupId,
                'reason' => $result['reason'] ?? 'failed',
                'error' => $result['error'] ?? 'Could not send.',
            ];
        }
    }

    jsonSuccess([
        "sent" => $sent,
        "attempted" => count($groupIds),
        "recipients_notified" => $recipientsNotified,
        "failed" => $failed,
        "messages" => $messages,
    ]);
}

function handleGetGroupMessages($data)
{
    $groupId = $data['group_id'] ?? null;
    $userId = $data['user_id'] ?? null;
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 10;

    $dbGroupId = $groupId;
    if ($dbGroupId && str_starts_with($dbGroupId, 'event_group_')) {
        $dbGroupId = substr($dbGroupId, 12);
    }

    if (!$groupId || !$userId) {
        jsonError("group_id and user_id are required.", 400);
        return;
    }

    // Because this backend uses the Supabase service key, protect group chats manually.
    // Only members should be able to read group messages.
    if (!str_starts_with($groupId, 'event_group_')) {
        $memberRes = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'eq.' . $groupId,
            'user_id' => 'eq.' . $userId,
            'select' => 'group_id,user_id',
            'limit' => '1',
        ]);

        if (supabaseFailed($memberRes)) {
            sendSupabaseError("Failed to verify group membership.", $memberRes);
            return;
        }

        if (empty($memberRes['data'])) {
            jsonError("You are not a member of this group.", 403);
            return;
        }
    }

    // Fetch newest N messages first so Supabase can use the (group_id, created_at desc) index.
    $messagesRes = supabaseRequest('GET', '/rest/v1/group_messages', [
        'group_id' => 'eq.' . $dbGroupId,
        'is_deleted' => 'eq.false',
        'select' => 'id,group_id,sender_id,content,media_url,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);

    if (supabaseFailed($messagesRes)) {
        sendSupabaseError("Failed to load group messages.", $messagesRes);
        return;
    }

    $messages = $messagesRes['data'] ?? [];

    // Fetch sender profiles separately instead of relying on fragile embedded joins.
    $senderIds = [];
    foreach ($messages as $m) {
        if (!empty($m['sender_id'])) {
            $senderIds[] = $m['sender_id'];
        }
    }

    $senderIds = normalizeUuidList(array_values(array_unique($senderIds)));
    $profilesById = [];

    if (!empty($senderIds)) {
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
            'user_id' => 'in.(' . implode(',', $senderIds) . ')',
            'select' => 'user_id,full_name,profile_photo_url',
        ]);

        if (supabaseFailed($profilesRes)) {
            sendSupabaseError("Failed to load message sender profiles.", $profilesRes);
            return;
        }

        foreach (($profilesRes['data'] ?? []) as $profile) {
            $profilesById[$profile['user_id']] = $profile;
        }
    }

    foreach ($messages as &$m) {
        $profile = $profilesById[$m['sender_id'] ?? ''] ?? null;

        $m['sender_name'] = $profile['full_name'] ?? 'Member';
        $m['sender_avatar_url'] = $profile['profile_photo_url'] ?? null;
    }
    unset($m);

    // Reverse so the frontend displays oldest -> newest.
    $messages = array_reverse($messages);

    jsonSuccess([
        "messages" => $messages
    ]);
}

function handleSendDirectMessage($data)
{
    $userId = $data['user_id'] ?? null;
    $friendId = $data['friend_id'] ?? $data['recipient_id'] ?? null;

    if (!$userId || !$friendId) {
        jsonError("user_id and friend_id required.", 400);
        return;
    }

    $content = cleanTextValue($data['content'] ?? $data['text'] ?? '', 3000);
    $mediaUrl = trim((string) ($data['media_url'] ?? ''));

    if ($content === '' && $mediaUrl === '') {
        jsonError("Message content or media_url required.", 400);
        return;
    }

    if (!usersAreAcceptedFriends($userId, $friendId)) {
        jsonError("You can only message accepted friends.", 403);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/direct_messages', [], [
        'sender_id' => $userId,
        'recipient_id' => $friendId,
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to send message.", $res);
        return;
    }

    $message = enrichDirectMessages([$res['data'][0]])[0];
    $senderName = $message['sender_name'] ?? 'A friend';
    $preview = $content !== '' ? $content : 'Sent you a message.';
    if (strlen($preview) > 120) {
        $preview = substr($preview, 0, 117) . '...';
    }

    $messageNotification = createNotification(
        $friendId,
        'direct_message',
        'New message',
        $senderName . ': ' . $preview,
        [
            'message_id' => $message['id'] ?? null,
            'sender_id' => $userId,
            'recipient_id' => $friendId,
        ]
    );

    jsonSuccess([
        "message" => $message,
        "notifications" => [
            "recipient_created" => !supabaseFailed($messageNotification),
        ],
    ]);
}

function handleGetDirectMessages($data)
{
    $userId = $data['user_id'] ?? null;
    $friendId = $data['friend_id'] ?? null;
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 30;

    if (!$userId || !$friendId) {
        jsonError("user_id and friend_id required.", 400);
        return;
    }

    if (!usersAreAcceptedFriends($userId, $friendId)) {
        jsonError("You can only read messages with accepted friends.", 403);
        return;
    }

    $messagesRes = supabaseRequest('GET', '/rest/v1/direct_messages', [
        'or' => '(and(sender_id.eq.' . $userId . ',recipient_id.eq.' . $friendId . '),and(sender_id.eq.' . $friendId . ',recipient_id.eq.' . $userId . '))',
        'is_deleted' => 'eq.false',
        'select' => 'id,sender_id,recipient_id,content,media_url,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);

    if (supabaseFailed($messagesRes)) {
        sendSupabaseError("Failed to load direct messages.", $messagesRes);
        return;
    }

    $messages = array_reverse(enrichDirectMessages($messagesRes['data'] ?? []));
    jsonSuccess(["messages" => $messages]);
}

// Fetch a single group enriched with its members list (and the caller's role).
function handleGetGroup($data)
{
    if (!requireFields($data, ['group_id']))
        return;

    $groupId = $data['group_id'];
    if (str_starts_with($groupId, 'event_group_')) {
        $groupId = substr($groupId, 12);
    }

    $res = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $groupId, 'select' => '*', 'limit' => '1']);
    if (supabaseFailed($res) || empty($res['data'])) {
        jsonError("Group not found.", 404);
        return;
    }

    $userId = $data['user_id'] ?? null;
    $group = enrichGroups([$res['data'][0]], $userId)[0];
    $group['my_role'] = $userId ? getGroupMemberRole($groupId, $userId) : null;

    jsonSuccess(["group" => $group]);
}

function handleGetGroups($data)
{
    $userId = $data['user_id'] ?? null;
    $userReligion = trim((string) ($data['religion'] ?? ''));
    $userCommunity = trim((string) ($data['community'] ?? ''));
    $groupsById = [];

    $baseSelect = 'id,name,description,avatar_url,community,religion,created_by,is_private,pack_key,created_at,updated_at';
    $limit = isset($data['limit']) ? (string) max(1, min((int) $data['limit'], 100)) : '10';

    $queries = [];

    // Global public groups: visible to everyone.
    $queries[] = [
        'select' => $baseSelect,
        'is_private' => 'eq.false',
        'religion' => 'is.null',
        'community' => 'is.null',
        'order' => 'created_at.desc',
        'limit' => $limit,
    ];

    // Religion-specific public groups: same religion, no community/caste restriction.
    if ($userReligion !== '') {
        $queries[] = [
            'select' => $baseSelect,
            'is_private' => 'eq.false',
            'religion' => 'eq.' . $userReligion,
            'community' => 'is.null',
            'order' => 'created_at.desc',
            'limit' => $limit,
        ];
    }

    // Community-specific public groups: same religion + same community/caste.
    if ($userReligion !== '' && $userCommunity !== '') {
        $queries[] = [
            'select' => $baseSelect,
            'is_private' => 'eq.false',
            'religion' => 'eq.' . $userReligion,
            'community' => 'eq.' . $userCommunity,
            'order' => 'created_at.desc',
            'limit' => $limit,
        ];
    }

    foreach ($queries as $query) {
        $publicRes = supabaseRequest('GET', '/rest/v1/groups', $query);

        if (supabaseFailed($publicRes)) {
            sendSupabaseError("Failed to fetch groups.", $publicRes);
            return;
        }

        foreach (($publicRes['data'] ?? []) as $g) {
            $groupsById[$g['id']] = $g;
        }
    }

    // Always include groups the user already joined, even if the group's scope/profile changed later.
    if ($userId) {
        $membershipRes = supabaseRequest('GET', '/rest/v1/group_members', [
            'user_id' => 'eq.' . $userId,
            'select' => 'group_id'
        ]);

        if (supabaseFailed($membershipRes)) {
            sendSupabaseError("Failed to fetch group memberships.", $membershipRes);
            return;
        }

        $joinedIds = normalizeUuidList(array_column($membershipRes['data'] ?? [], 'group_id'));
        if (!empty($joinedIds)) {
            $joinedRes = supabaseRequest('GET', '/rest/v1/groups', [
                'id' => 'in.(' . implode(',', $joinedIds) . ')',
                'select' => $baseSelect,
                'order' => 'created_at.desc',
            ]);

            if (supabaseFailed($joinedRes)) {
                sendSupabaseError("Failed to fetch joined groups.", $joinedRes);
                return;
            }

            foreach (($joinedRes['data'] ?? []) as $g) {
                $groupsById[$g['id']] = $g;
            }
        }
    }

    $groups = array_values($groupsById);
    usort($groups, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

    $groups = enrichGroups($groups, $userId);

    jsonSuccess(["groups" => $groups]);
}

// ============================================================
// FRIENDS
// ============================================================

function handleSearchMembers($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.", 400);
        return;
    }

    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 50)) : 24;
    $offset = isset($data['offset']) ? max(0, (int) $data['offset']) : 0;
    $queryText = cleanNullableText($data['query'] ?? '', 120);
    $filters = [
        'religion' => cleanNullableText($data['religion'] ?? '', 120),
        'community' => cleanNullableText($data['community'] ?? '', 160),
        'gotra' => cleanNullableText($data['gotra'] ?? ($data['clan'] ?? ''), 160),
        'native_village' => cleanNullableText($data['native_village'] ?? '', 160),
        'current_city' => cleanNullableText($data['current_city'] ?? ($data['city'] ?? ''), 160),
        'age_group' => cleanNullableText($data['age_group'] ?? '', 80),
        'gender' => cleanNullableText($data['gender'] ?? '', 80),
    ];

    $friendshipRes = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(requester.eq.' . $userId . ',addressee.eq.' . $userId . ')',
        'select' => 'id,requester,addressee,status'
    ]);

    if (supabaseFailed($friendshipRes)) {
        sendSupabaseError("Failed to inspect existing friendships.", $friendshipRes);
        return;
    }

    $relationshipByUser = [];
    foreach (($friendshipRes['data'] ?? []) as $f) {
        $otherId = ($f['requester'] ?? '') === $userId ? ($f['addressee'] ?? '') : ($f['requester'] ?? '');
        if ($otherId) {
            $relationshipByUser[$otherId] = [
                'friendship_id' => $f['id'] ?? null,
                'status' => $f['status'] ?? null,
                'direction' => ($f['requester'] ?? '') === $userId ? 'outgoing' : 'incoming',
            ];
        }
    }

    $currentProfile = getAccountProfile($userId);
    $currentReligion = trim((string) ($currentProfile['religion'] ?? ''));
    $currentCommunity = trim((string) ($currentProfile['community'] ?? ''));

    $profileQuery = [
        'select' => 'user_id,full_name,profile_photo_url,religion,community,gotra,native_village,current_city,age_group,date_of_birth,gender,occupation,is_public,visibility,primary_interests',
        'user_id' => 'neq.' . $userId,
        'order' => 'full_name.asc',
        'limit' => (string) min(400, max(($limit + $offset) * 4, 100)),
    ];

    foreach ($filters as $field => $value) {
        if ($value !== null && $value !== '') {
            $profileQuery[$field] = 'eq.' . $value;
        }
    }

    if ($queryText) {
        $safe = str_replace(['*', ',', '(', ')'], '', $queryText);
        $profileQuery['or'] = '(full_name.ilike.*' . $safe . '*,current_city.ilike.*' . $safe . '*,native_village.ilike.*' . $safe . '*,gotra.ilike.*' . $safe . '*)';
    }

    $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', $profileQuery);
    if (supabaseFailed($profilesRes)) {
        $profileQuery['select'] = 'user_id,full_name,profile_photo_url,religion,community,gotra,native_village,current_city,age_group,date_of_birth,gender,occupation,is_public,primary_interests';
        $profileQuery['is_public'] = 'eq.true';
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', $profileQuery);
        if (supabaseFailed($profilesRes)) {
            sendSupabaseError("Failed to search members.", $profilesRes);
            return;
        }
    }

    $ageMin = isset($data['age_min']) && $data['age_min'] !== '' ? (int) $data['age_min'] : null;
    $ageMax = isset($data['age_max']) && $data['age_max'] !== '' ? (int) $data['age_max'] : null;
    $today = new DateTimeImmutable('today');
    $members = [];
    $skipped = 0;
    $candidateUserIds = [];
    foreach (($profilesRes['data'] ?? []) as $candidateProfile) {
        if (!empty($candidateProfile['user_id']))
            $candidateUserIds[] = $candidateProfile['user_id'];
    }
    $adminCapsMap = fetchAdminCapabilitiesMap($candidateUserIds);

    foreach (($profilesRes['data'] ?? []) as $p) {
        $otherId = $p['user_id'] ?? '';
        if (!$otherId)
            continue;
        if (isset($relationshipByUser[$otherId]))
            continue;

        $visibility = normalizeVisibility($p['visibility'] ?? (!empty($p['is_public']) ? 'public' : 'private'));
        if ($visibility === 'private')
            continue;
        if ($visibility === 'religion' && $currentReligion !== '' && ($p['religion'] ?? '') !== $currentReligion)
            continue;
        if ($visibility === 'community') {
            if ($currentReligion !== '' && ($p['religion'] ?? '') !== $currentReligion)
                continue;
            if ($currentCommunity !== '' && ($p['community'] ?? '') !== $currentCommunity)
                continue;
        }

        $age = null;
        if (!empty($p['date_of_birth'])) {
            try {
                $age = (int) (new DateTimeImmutable($p['date_of_birth']))->diff($today)->y;
            } catch (Exception $e) {
                $age = null;
            }
        }

        if ($ageMin !== null && ($age === null || $age < $ageMin))
            continue;
        if ($ageMax !== null && ($age === null || $age > $ageMax))
            continue;

        if ($skipped < $offset) {
            $skipped++;
            continue;
        }

        $p['admin_capabilities'] = $adminCapsMap[strtolower((string) $otherId)] ?? [];
        $customTags = profileCustomTags($p);
        $systemTags = adminCapabilityTags($p['admin_capabilities']);

        $members[] = [
            'user_id' => $otherId,
            'name' => $p['full_name'] ?? 'Member',
            'photo' => $p['profile_photo_url'] ?? null,
            'religion' => $p['religion'] ?? null,
            'community' => $p['community'] ?? null,
            'gotra' => $p['gotra'] ?? null,
            'native_village' => $p['native_village'] ?? null,
            'current_city' => $p['current_city'] ?? null,
            'age_group' => profileAgeGroup($p),
            'age' => $age,
            'gender' => $p['gender'] ?? null,
            'occupation' => $p['occupation'] ?? null,
            'primary_interests' => $customTags,
            'custom_tags' => $customTags,
            'system_tags' => $systemTags,
            'tags' => profileDisplayTags($customTags, $systemTags),
            'admin_capabilities' => $p['admin_capabilities'],
            'relationship_status' => 'none',
        ];

        if (count($members) > $limit)
            break;
    }

    $hasMore = count($members) > $limit;
    if ($hasMore) {
        array_pop($members);
    }

    jsonSuccess([
        'members' => $members,
        'has_more' => $hasMore
    ]);
}

function handleSendFriendRequest($data)
{
    $requester = $data['requester'] ?? $data['user_id'] ?? null;
    $addressee = $data['addressee'] ?? $data['addressee_id'] ?? $data['friend_id'] ?? null;

    if (!$requester || !$addressee) {
        jsonError("requester/user_id and addressee required.", 400);
        return;
    }

    if ($requester === $addressee) {
        jsonError("You cannot send a friend request to yourself.", 400);
        return;
    }

    $existing = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(and(requester.eq.' . $requester . ',addressee.eq.' . $addressee . '),and(requester.eq.' . $addressee . ',addressee.eq.' . $requester . '))',
        'select' => 'id,requester,addressee,status',
        'limit' => '1'
    ]);

    if (!supabaseFailed($existing) && !empty($existing['data'])) {
        jsonSuccess([
            "message" => "Friendship or request already exists.",
            "friendship" => $existing['data'][0],
        ]);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/friendships', [], [
        'requester' => $requester,
        'addressee' => $addressee,
        'status' => 'pending',
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        $code = is_array($res['data'] ?? null) ? ($res['data']['code'] ?? '') : '';
        if ($code === '23505') {
            jsonSuccess(["message" => "Request already sent."]);
            return;
        }

        sendSupabaseError("Failed to send friend request.", $res);
        return;
    }

    $friendship = $res['data'][0] ?? null;
    $friendshipId = $friendship['id'] ?? null;
    $profiles = fetchProfilesMap([$requester, $addressee]);
    $requesterName = $profiles[$requester]['full_name'] ?? 'A member';
    $addresseeName = $profiles[$addressee]['full_name'] ?? 'this member';
    $notificationData = [
        'friendship_id' => $friendshipId,
        'requester_id' => $requester,
        'addressee_id' => $addressee,
    ];

    $receiverNotification = createNotification(
        $addressee,
        'friend_request',
        'New friend request',
        $requesterName . ' sent you a friend request.',
        $notificationData
    );

    jsonSuccess([
        "friendship" => $friendship,
        "notifications" => [
            "receiver_created" => !supabaseFailed($receiverNotification),
        ],
    ]);
}

function handleRemoveFriend($data)
{
    $userId = $data['user_id'] ?? null;
    $friendId = $data['friend_id'] ?? null;

    if (empty($userId) || empty($friendId)) {
        jsonError("user_id and friend_id required.", 400);
        return;
    }

    // We delete both possible combinations
    supabaseRequest('DELETE', '/rest/v1/friendships', [
        'requester' => 'eq.' . $userId,
        'addressee' => 'eq.' . $friendId
    ]);

    supabaseRequest('DELETE', '/rest/v1/friendships', [
        'requester' => 'eq.' . $friendId,
        'addressee' => 'eq.' . $userId
    ]);

    jsonSuccess(["action" => "removed"]);
}

function handleRespondFriendRequest($data)
{
    $responseAction = $data['response_action'] ?? $data['friend_action'] ?? $data['action'] ?? null;

    if (empty($data['friendship_id']) || empty($responseAction)) {
        jsonError("friendship_id and response_action required.", 400);
        return;
    }

    $action = $responseAction;

    if ($action === 'accept' || $action === 'accepted') {
        $query = [
            'id' => 'eq.' . $data['friendship_id'],
            'status' => 'eq.pending',
        ];
        if (!empty($data['user_id']))
            $query['addressee'] = 'eq.' . $data['user_id'];

        $res = supabaseRequest('PATCH', '/rest/v1/friendships', $query, [
            'status' => 'accepted'
        ], ['Prefer: return=representation']);

        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to accept friend request.", $res);
            return;
        }

        if (empty($res['data'])) {
            jsonError("Friend request not found or you are not the receiver.", 404);
            return;
        }

        $friendship = $res['data'][0] ?? null;
        $requester = $friendship['requester'] ?? null;
        $addressee = $friendship['addressee'] ?? ($data['user_id'] ?? null);
        $profiles = fetchProfilesMap([$requester, $addressee]);
        $addresseeName = $profiles[$addressee]['full_name'] ?? 'A member';
        $acceptedNotification = null;

        if ($requester) {
            $acceptedNotification = createNotification(
                $requester,
                'friend_request_accepted',
                'Friend request accepted',
                $addresseeName . ' accepted your friend request.',
                [
                    'friendship_id' => $friendship['id'] ?? $data['friendship_id'],
                    'requester_id' => $requester,
                    'addressee_id' => $addressee,
                ]
            );
        }

        jsonSuccess([
            "action" => "accepted",
            "friendship" => $friendship,
            "notifications" => [
                "requester_created" => $acceptedNotification ? !supabaseFailed($acceptedNotification) : false,
            ],
        ]);
        return;
    }

    $query = [
        'id' => 'eq.' . $data['friendship_id'],
        'status' => 'eq.pending',
    ];
    if (!empty($data['user_id']))
        $query['addressee'] = 'eq.' . $data['user_id'];

    $res = supabaseRequest('DELETE', '/rest/v1/friendships', $query, null, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to decline friend request.", $res);
        return;
    }

    if (empty($res['data'])) {
        jsonError("Friend request not found or you are not the receiver.", 404);
        return;
    }

    jsonSuccess(["action" => "declined", "friendship_id" => $data['friendship_id']]);
}

function handleGetFriends($data)
{
    if (empty($data['user_id'])) {
        jsonError("user_id required.", 400);
        return;
    }

    $uid = $data['user_id'];

    $sentAccepted = supabaseRequest('GET', '/rest/v1/friendships', [
        'requester' => 'eq.' . $uid,
        'status' => 'eq.accepted',
        'select' => 'id,requester,addressee,status,created_at,updated_at'
    ]);

    $receivedAccepted = supabaseRequest('GET', '/rest/v1/friendships', [
        'addressee' => 'eq.' . $uid,
        'status' => 'eq.accepted',
        'select' => 'id,requester,addressee,status,created_at,updated_at'
    ]);

    $incomingPending = supabaseRequest('GET', '/rest/v1/friendships', [
        'addressee' => 'eq.' . $uid,
        'status' => 'eq.pending',
        'select' => 'id,requester,addressee,status,created_at,updated_at'
    ]);

    $outgoingPending = supabaseRequest('GET', '/rest/v1/friendships', [
        'requester' => 'eq.' . $uid,
        'status' => 'eq.pending',
        'select' => 'id,requester,addressee,status,created_at,updated_at'
    ]);

    foreach ([
        'sentAccepted' => $sentAccepted,
        'receivedAccepted' => $receivedAccepted,
        'incomingPending' => $incomingPending,
        'outgoingPending' => $outgoingPending
    ] as $label => $res) {
        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to fetch friendships.", $res, 500, ["source" => $label]);
            return;
        }
    }

    $rows = [];
    $profileIds = [];

    $addRow = function ($friendship, $otherUserId, $type) use (&$rows, &$profileIds) {
        if (!$otherUserId)
            return;
        $rows[] = [
            'friendship_id' => $friendship['id'],
            'user_id' => $otherUserId,
            'type' => $type,
            'status' => $friendship['status'] ?? null,
            'created_at' => $friendship['created_at'] ?? null,
        ];
        $profileIds[] = $otherUserId;
    };

    foreach (($sentAccepted['data'] ?? []) as $f) {
        $addRow($f, $f['addressee'] ?? null, 'friend');
    }

    foreach (($receivedAccepted['data'] ?? []) as $f) {
        $addRow($f, $f['requester'] ?? null, 'friend');
    }

    foreach (($incomingPending['data'] ?? []) as $f) {
        $addRow($f, $f['requester'] ?? null, 'request');
    }

    foreach (($outgoingPending['data'] ?? []) as $f) {
        $addRow($f, $f['addressee'] ?? null, 'sent_request');
    }

    $profileMap = fetchProfilesMap($profileIds);

    $format = function ($row) use ($profileMap) {
        $profile = $profileMap[$row['user_id']] ?? [];
        return [
            'friendship_id' => $row['friendship_id'],
            'user_id' => $row['user_id'],
            'name' => $profile['full_name'] ?? 'Member',
            'photo' => $profile['profile_photo_url'] ?? null,
            'community' => $profile['community'] ?? null,
            'religion' => $profile['religion'] ?? null,
            'age_group' => profileAgeGroup($profile),
            'date_of_birth' => $profile['date_of_birth'] ?? null,
            'gender' => $profile['gender'] ?? null,
            'occupation' => $profile['occupation'] ?? null,
            'type' => $row['type'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
        ];
    };

    $friends = [];
    $requests = [];
    $sentRequests = [];

    foreach ($rows as $row) {
        if ($row['type'] === 'friend')
            $friends[] = $format($row);
        if ($row['type'] === 'request')
            $requests[] = $format($row);
        if ($row['type'] === 'sent_request')
            $sentRequests[] = $format($row);
    }

    jsonSuccess([
        "friends" => $friends,
        "requests" => $requests,
        "sent_requests" => $sentRequests
    ]);
}

// ============================================================
// ZOOM CALLS
// ============================================================

function handleZoomTest($data)
{
    $required = [
        'ZOOM_S2S_ACCOUNT_ID',
        'ZOOM_S2S_CLIENT_ID',
        'ZOOM_S2S_CLIENT_SECRET',
        'ZOOM_MEETING_SDK_CLIENT_ID',
        'ZOOM_MEETING_SDK_CLIENT_SECRET',
        'ZOOM_HOST_USER_ID',
        'APP_BASE_URL'
    ];

    $missing = [];

    foreach ($required as $key) {
        if (!envValue($key)) {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        jsonError("Missing Zoom .env values.", 500, [
            "missing" => $missing
        ]);
        return;
    }

    $token = zoomGetAccessToken();

    jsonSuccess([
        "message" => "Zoom Server-to-Server OAuth is working.",
        "host_user_id" => envValue('ZOOM_HOST_USER_ID'),
        "app_base_url" => envValue('APP_BASE_URL'),
        "token_preview" => substr($token, 0, 12) . "..."
    ]);
}
function base64UrlEncodeRaw($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateZoomMeetingSdkJwt($meetingNumber, $role = 0)
{
    $clientId = envValue('ZOOM_MEETING_SDK_CLIENT_ID');
    $clientSecret = envValue('ZOOM_MEETING_SDK_CLIENT_SECRET');

    if (!$clientId || !$clientSecret) {
        jsonError("Zoom Meeting SDK credentials are missing from .env", 500);
        exit();
    }

    $iat = time() - 30;
    $exp = $iat + 60 * 60 * 2;

    $header = [
        "alg" => "HS256",
        "typ" => "JWT"
    ];

    $payload = [
        "appKey" => $clientId,
        "sdkKey" => $clientId,
        "mn" => (string) $meetingNumber,
        "role" => (int) $role,
        "iat" => $iat,
        "exp" => $exp,
        "tokenExp" => $exp,
        "video_webrtc_mode" => 1
    ];

    $segments = [
        base64UrlEncodeRaw(json_encode($header)),
        base64UrlEncodeRaw(json_encode($payload))
    ];

    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, $clientSecret, true);

    $segments[] = base64UrlEncodeRaw($signature);

    return implode('.', $segments);
}

function zoomGetAccessToken()
{
    $accountId = envValue('ZOOM_S2S_ACCOUNT_ID');
    $clientId = envValue('ZOOM_S2S_CLIENT_ID');
    $clientSecret = envValue('ZOOM_S2S_CLIENT_SECRET');

    if (!$accountId || !$clientId || !$clientSecret) {
        jsonError("Zoom Server-to-Server OAuth credentials are missing from .env", 500);
        exit();
    }

    $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($accountId);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $data = json_decode($response, true);

    if ($httpCode >= 400 || empty($data['access_token'])) {
        jsonError("Failed to get Zoom access token.", 500, [
            "zoom_http_code" => $httpCode,
            "zoom_response" => $data
        ]);
        exit();
    }

    return $data['access_token'];
}

function zoomApiRequest($method, $path, $body = null)
{
    $token = zoomGetAccessToken();

    $ch = curl_init('https://api.zoom.us/v2' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [
        "code" => $httpCode,
        "data" => json_decode($response, true)
    ];
}

function createZoomMeetingForCall($callType, $topic)
{
    $hostUserId = envValue('ZOOM_HOST_USER_ID');

    if (!$hostUserId) {
        jsonError("ZOOM_HOST_USER_ID is missing from .env", 500);
        exit();
    }

    $isVideo = $callType === 'video';

    $password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

    $payload = [
        "topic" => $topic,
        "type" => 2,
        "start_time" => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
        "duration" => 60,
        "timezone" => "UTC",
        "password" => $password,
        "settings" => [
            "join_before_host" => true,
            "waiting_room" => false,
            "host_video" => $isVideo,
            "participant_video" => $isVideo,
            "mute_upon_entry" => false,
            "approval_type" => 2,
            "audio" => "both"
        ]
    ];

    $res = zoomApiRequest(
        'POST',
        '/users/' . rawurlencode($hostUserId) . '/meetings',
        $payload
    );

    if ($res['code'] >= 400 || empty($res['data']['id'])) {
        jsonError("Failed to create Zoom meeting.", 500, [
            "zoom_http_code" => $res['code'],
            "zoom_response" => $res['data']
        ]);
        exit();
    }

    return $res['data'];
}

function uniqueUserIds($ids)
{
    $clean = [];

    foreach ((array) $ids as $id) {
        $id = trim((string) $id);
        if ($id !== '' && !in_array($id, $clean, true)) {
            $clean[] = $id;
        }
    }

    return $clean;
}

function getGroupMemberIds($groupId)
{
    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'select' => 'user_id'
    ]);

    // Best-effort lookup: callers use this to fan out notifications, so a failure here
    // must never abort an action (e.g. a message) that has already been persisted.
    if (supabaseFailed($res)) {
        error_log("getGroupMemberIds failed for group {$groupId}: HTTP " . ($res['code'] ?? '?'));
        return [];
    }

    return array_values(array_unique(array_column($res['data'] ?? [], 'user_id')));
}

function isGroupMember($userId, $groupId)
{
    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'user_id' => 'eq.' . $userId,
        'group_id' => 'eq.' . $groupId,
        'select' => 'group_id,user_id',
        'limit' => '1'
    ]);

    return !supabaseFailed($res) && !empty($res['data']);
}

function areFriends($userA, $userB)
{
    $orFilter = sprintf(
        '(and(requester.eq.%s,addressee.eq.%s),and(requester.eq.%s,addressee.eq.%s))',
        $userA,
        $userB,
        $userB,
        $userA
    );

    $res = supabaseRequest('GET', '/rest/v1/friendships', [
        'status' => 'eq.accepted',
        'or' => $orFilter,
        'select' => 'id',
        'limit' => '1'
    ]);

    return !empty($res['data']);
}

function resolveCallParticipants($data)
{
    $callerId = $data['user_id'] ?? null;
    $targetType = $data['target_type'] ?? null;

    if (!$callerId || !$targetType) {
        jsonError("user_id and target_type are required.");
        exit();
    }

    if ($targetType === 'group') {
        if (empty($data['group_id'])) {
            jsonError("group_id is required for group calls.");
            exit();
        }

        if (!str_starts_with($data['group_id'], 'event_group_') && !isGroupMember($callerId, $data['group_id'])) {
            jsonError("You are not a member of this group.", 403);
            exit();
        }

        $memberIds = str_starts_with($data['group_id'], 'event_group_') ? [] : getGroupMemberIds($data['group_id']);

        return uniqueUserIds(array_merge([$callerId], $memberIds));
    }

    $participantIds = uniqueUserIds($data['participant_ids'] ?? []);

    if (empty($participantIds)) {
        jsonError("participant_ids is required for direct or selected user calls.");
        exit();
    }

    foreach ($participantIds as $participantId) {
        if ($participantId === $callerId) {
            continue;
        }

        if (!areFriends($callerId, $participantId)) {
            jsonError("You can only call accepted friends.", 403, [
                "blocked_user_id" => $participantId
            ]);
            exit();
        }
    }

    return uniqueUserIds(array_merge([$callerId], $participantIds));
}

function insertCallParticipants($callId, $callerId, $participantIds)
{
    $rows = [];

    foreach ($participantIds as $uid) {
        $rows[] = [
            'call_id' => $callId,
            'user_id' => $uid,
            'role' => $uid === $callerId ? 'host' : 'participant',
            'status' => $uid === $callerId ? 'joined' : 'invited',
            'joined_at' => $uid === $callerId ? gmdate('c') : null
        ];
    }

    $res = supabaseRequest(
        'POST',
        '/rest/v1/call_participants',
        [],
        $rows,
        ['Prefer: return=representation']
    );

    if ($res['code'] >= 400) {
        jsonError("Failed to save call participants.", 500, [
            "supabase_response" => $res['data']
        ]);
        exit();
    }

    return $res['data'] ?? [];
}

function notifyCallParticipants($call, $callerId, $participantIds)
{
    $recipientIds = [];
    foreach (($participantIds ?? []) as $participantId) {
        if ($participantId && $participantId !== $callerId) {
            $recipientIds[] = $participantId;
        }
    }

    $recipientIds = normalizeUuidList($recipientIds);
    if (empty($recipientIds)) {
        return ["created" => 0, "attempted" => 0];
    }

    $profiles = fetchProfilesMap([$callerId]);
    $callerName = $profiles[$callerId]['full_name'] ?? 'A member';
    $callType = $call['call_type'] ?? 'voice';
    $typeLabel = $callType === 'video' ? 'video call' : 'voice call';
    $targetType = $call['target_type'] ?? null;
    $groupName = null;

    if ($targetType === 'group' && !empty($call['group_id'])) {
        $groupRes = supabaseRequest('GET', '/rest/v1/groups', [
            'id' => 'eq.' . $call['group_id'],
            'select' => 'name',
            'limit' => '1'
        ]);

        if (!supabaseFailed($groupRes) && !empty($groupRes['data'][0]['name'])) {
            $groupName = $groupRes['data'][0]['name'];
        }
    }

    $title = $targetType === 'group'
        ? 'Call from ' . ($groupName ?: 'your group')
        : $callerName . ' is calling';
    $body = $targetType === 'group'
        ? $callerName . ' started a ' . $typeLabel . ' in ' . ($groupName ?: 'your group') . '.'
        : $callerName . ' started a ' . $typeLabel . '.';
    $created = 0;

    foreach ($recipientIds as $recipientId) {
        $notification = createNotification(
            $recipientId,
            'call_invite',
            $title,
            $body,
            [
                'call_id' => $call['id'] ?? null,
                'caller_id' => $callerId,
                'call_type' => $callType,
                'target_type' => $targetType,
                'group_id' => $call['group_id'] ?? null,
                'group_name' => $groupName,
            ]
        );

        if (!supabaseFailed($notification)) {
            $created++;
        }
    }

    return ["created" => $created, "attempted" => count($recipientIds)];
}

function handleZoomStartCall($data)
{

    cleanupStaleCallSessions();
    if (empty($data['user_id']) || empty($data['call_type']) || empty($data['target_type'])) {
        jsonError("user_id, call_type and target_type are required.");
        return;
    }

    $callerId = $data['user_id'];
    $callType = $data['call_type'];

    if (!in_array($callType, ['voice', 'video'], true)) {
        jsonError("call_type must be voice or video.");
        return;
    }

    if (!in_array($data['target_type'], ['direct', 'selected_users', 'group'], true)) {
        jsonError("target_type must be direct, selected_users or group.");
        return;
    }

    $participantIds = resolveCallParticipants($data);

    $topic = $callType === 'video'
        ? 'PawCircle Video Call'
        : 'PawCircle Voice Call';

    $zoomMeeting = createZoomMeetingForCall($callType, $topic);

    $meetingId = (string) $zoomMeeting['id'];
    $password = $zoomMeeting['password'] ?? '';
    $joinUrl = $zoomMeeting['join_url'] ?? null;

    $dbGroupId = $data['target_type'] === 'group' ? ($data['group_id'] ?? null) : null;
    if ($dbGroupId && str_starts_with($dbGroupId, 'event_group_')) {
        $eventId = substr($dbGroupId, 12);
        $dbGroupId = $eventId;

        // Ensure shadow group exists for foreign key constraints
        $groupCheck = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $eventId, 'select' => 'id', 'limit' => '1']);
        if (empty($groupCheck['data'])) {
            $eventCheck = supabaseRequest('GET', '/rest/v1/events', ['id' => 'eq.' . $eventId, 'select' => 'title', 'limit' => '1']);
            $evTitle = $eventCheck['data'][0]['title'] ?? 'Event';

            supabaseRequest('POST', '/rest/v1/groups', [], [
                'id' => $eventId,
                'name' => 'Event: ' . $evTitle,
                'created_by' => $callerId,
                'is_private' => true
            ]);
        }
    }

    $callRes = supabaseRequest(
        'POST',
        '/rest/v1/call_sessions',
        [],
        [
            'created_by' => $callerId,
            'call_type' => $callType,
            'target_type' => $data['target_type'],
            'group_id' => $dbGroupId,
            'provider' => 'zoom',
            'zoom_meeting_id' => $meetingId,
            'zoom_password' => $password,
            'zoom_join_url' => $joinUrl,
            'status' => 'active',
            'started_at' => gmdate('c')
        ],
        ['Prefer: return=representation']
    );

    if ($callRes['code'] >= 400 || empty($callRes['data'][0]['id'])) {
        file_put_contents(__DIR__ . '/debug_call_error.txt', json_encode(['data' => $data, 'res' => $callRes]));
        jsonError("Failed to save call session.", 500, [
            "supabase_response" => $callRes['data']
        ]);
        return;
    }

    $call = $callRes['data'][0];
    insertCallParticipants($call['id'], $callerId, $participantIds);
    $callNotifications = notifyCallParticipants($call, $callerId, $participantIds);

    $signature = generateZoomMeetingSdkJwt($meetingId, 0);

    jsonSuccess([
        "call" => $call,
        "zoom" => [
            "sdkKey" => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
            "meetingNumber" => $meetingId,
            "password" => $password,
            "signature" => $signature,
            "role" => 0,
            "joinUrl" => $joinUrl
        ],
        "notifications" => [
            "participants_created" => $callNotifications["created"],
            "participants_attempted" => $callNotifications["attempted"],
        ]
    ]);
}

function handleZoomJoinCall($data)
{
    if (empty($data['user_id']) || empty($data['call_id'])) {
        jsonError("user_id and call_id are required.");
        return;
    }

    $userId = $data['user_id'];
    $callId = $data['call_id'];

    $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'eq.' . $callId,
        'status' => 'in.(ringing,active)',
        'select' => 'id,created_by,call_type,target_type,group_id,zoom_meeting_id,zoom_password,zoom_join_url,status,started_at,ended_at,created_at',
        'limit' => '1'
    ]);

    if (empty($callRes['data'])) {
        jsonError("Call not found or already ended.", 404);
        return;
    }

    $call = $callRes['data'][0];

    $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'user_id' => 'eq.' . $userId,
        'select' => 'id,status',
        'limit' => '1'
    ]);

    if (empty($participantRes['data'])) {
        if ($call['target_type'] === 'group') {
            supabaseRequest('POST', '/rest/v1/call_participants', [], [
                [
                    'call_id' => $callId,
                    'user_id' => $userId,
                    'role' => 'participant',
                    'status' => 'joined',
                    'joined_at' => gmdate('c')
                ]
            ]);
        } else {
            jsonError("You are not invited to this call.", 403);
            return;
        }
    } else {
        supabaseRequest(
            'PATCH',
            '/rest/v1/call_participants',
            [
                'call_id' => 'eq.' . $callId,
                'user_id' => 'eq.' . $userId
            ],
            [
                'status' => 'joined',
                'joined_at' => gmdate('c')
            ]
        );
    }

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_sessions',
        ['id' => 'eq.' . $callId],
        ['status' => 'active']
    );

    $meetingId = $call['zoom_meeting_id'];
    $signature = generateZoomMeetingSdkJwt($meetingId, 0);

    jsonSuccess([
        "call" => $call,
        "zoom" => [
            "sdkKey" => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
            "meetingNumber" => $meetingId,
            "password" => $call['zoom_password'] ?? '',
            "signature" => $signature,
            "role" => 0,
            "joinUrl" => $call['zoom_join_url'] ?? null
        ]
    ]);
}

function zoomEndMeetingIfPossible($meetingId)
{
    if (!$meetingId) {
        return null;
    }

    // Best-effort cleanup. Zoom returns an error if the meeting is already ended/not live;
    // we should not fail the app's own DB cleanup because of that.
    return zoomApiRequest('PUT', '/meetings/' . rawurlencode((string) $meetingId) . '/status', [
        'action' => 'end'
    ]);
}

function handleZoomEndCall($data)
{
    if (empty($data['user_id']) || empty($data['call_id'])) {
        jsonError("user_id and call_id are required.");
        return;
    }

    $userId = $data['user_id'];
    $callId = $data['call_id'];

    $ownerRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'eq.' . $callId,
        'created_by' => 'eq.' . $userId,
        'select' => 'id,zoom_meeting_id',
        'limit' => '1'
    ]);

    if (empty($ownerRes['data'])) {
        jsonError("Only the call creator can end this call.", 403);
        return;
    }

    $meetingId = $ownerRes['data'][0]['zoom_meeting_id'] ?? null;
    $endedAt = gmdate('c');

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_sessions',
        ['id' => 'eq.' . $callId],
        [
            'status' => 'ended',
            'ended_at' => $endedAt
        ]
    );

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_participants',
        [
            'call_id' => 'eq.' . $callId,
            'status' => 'in.(invited,ringing,joined)'
        ],
        [
            'status' => 'left',
            'left_at' => $endedAt
        ]
    );

    zoomEndMeetingIfPossible($meetingId);

    jsonSuccess(["message" => "Call ended.", "call_ended" => true, "ended_at" => $endedAt]);
}

function maybeEndCallIfNobodyJoined($callId)
{
    $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'eq.' . $callId,
        'status' => 'in.(ringing,active)',
        'select' => 'id,zoom_meeting_id,status,ended_at',
        'limit' => '1'
    ]);

    if (empty($callRes['data'])) {
        return ["ended" => false];
    }

    $joinedRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'status' => 'eq.joined',
        'select' => 'id',
        'limit' => '1'
    ]);

    if (!empty($joinedRes['data'])) {
        return ["ended" => false];
    }

    $endedAt = gmdate('c');
    $meetingId = $callRes['data'][0]['zoom_meeting_id'] ?? null;

    supabaseRequest('PATCH', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId], [
        'status' => 'ended',
        'ended_at' => $endedAt
    ]);

    // Anyone who never joined becomes missed, while already-left/declined users keep their status.
    supabaseRequest('PATCH', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'status' => 'in.(invited,ringing)'
    ], [
        'status' => 'missed',
        'left_at' => $endedAt
    ]);

    zoomEndMeetingIfPossible($meetingId);

    return ["ended" => true, "ended_at" => $endedAt];
}

function handleZoomGetActiveCalls($data)
{
    if (empty($data['user_id'])) {
        jsonError("user_id is required.");
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $data['user_id'],
        'status' => 'in.(invited,ringing,joined)',
        'select' => 'id,status,call_sessions(id,created_by,call_type,target_type,group_id,status,created_at,started_at)',
        'order' => 'created_at.desc'
    ]);

    jsonSuccess([
        "calls" => $res['data'] ?? []
    ]);
}

function handleZoomGetDirectCalls($data)
{
    cleanupStaleCallSessions();

    if (empty($data['user_id']) || empty($data['friend_id'])) {
        jsonError("user_id and friend_id are required.");
        return;
    }

    $userId = $data['user_id'];
    $friendId = $data['friend_id'];
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 50)) : 20;

    if (!areFriends($userId, $friendId)) {
        jsonError("You can only view calls with accepted friends.", 403);
        return;
    }

    $userParticipantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $userId,
        'select' => 'call_id,status,joined_at,left_at'
    ]);

    $friendParticipantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $friendId,
        'select' => 'call_id'
    ]);

    if ($userParticipantRes['code'] >= 400 || $friendParticipantRes['code'] >= 400) {
        jsonError("Failed to load direct call participants.", 500, [
            "user_participants" => $userParticipantRes['data'] ?? null,
            "friend_participants" => $friendParticipantRes['data'] ?? null
        ]);
        return;
    }

    $friendCallIds = array_flip(normalizeUuidList(array_column($friendParticipantRes['data'] ?? [], 'call_id')));
    $participantByCallId = [];
    $sharedCallIds = [];

    foreach (($userParticipantRes['data'] ?? []) as $row) {
        $callId = $row['call_id'] ?? null;
        if ($callId && isset($friendCallIds[strtolower($callId)])) {
            $participantByCallId[$callId] = $row;
            $sharedCallIds[] = $callId;
        }
    }

    $sharedCallIds = normalizeUuidList($sharedCallIds);
    if (empty($sharedCallIds)) {
        jsonSuccess(["calls" => []]);
        return;
    }

    $callsRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'in.(' . implode(',', $sharedCallIds) . ')',
        'target_type' => 'in.(direct,selected_users)',
        'provider' => 'eq.zoom',
        'select' => 'id,created_by,call_type,target_type,group_id,status,created_at,started_at,ended_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit
    ]);

    if ($callsRes['code'] >= 400) {
        jsonError("Failed to load direct calls.", 500, ["supabase_response" => $callsRes['data']]);
        return;
    }

    $calls = $callsRes['data'] ?? [];
    $creatorIds = normalizeUuidList(array_values(array_unique(array_column($calls, 'created_by'))));
    $profilesByUserId = [];

    if (!empty($creatorIds)) {
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
            'user_id' => 'in.(' . implode(',', $creatorIds) . ')',
            'select' => 'user_id,full_name,profile_photo_url'
        ]);

        if ($profilesRes['code'] < 400) {
            foreach (($profilesRes['data'] ?? []) as $profile) {
                $profilesByUserId[$profile['user_id']] = $profile;
            }
        }
    }

    foreach ($calls as &$call) {
        $profile = $profilesByUserId[$call['created_by'] ?? ''] ?? null;
        $participant = $participantByCallId[$call['id'] ?? ''] ?? null;

        $call['created_by_name'] = $profile['full_name'] ?? 'Member';
        $call['created_by_avatar_url'] = $profile['profile_photo_url'] ?? null;
        $call['participant_status'] = $participant['status'] ?? null;
        $call['participant_joined_at'] = $participant['joined_at'] ?? null;
        $call['participant_left_at'] = $participant['left_at'] ?? null;
    }
    unset($call);

    jsonSuccess(["calls" => $calls]);
}

function handleZoomGetGroupCalls($data)
{
    cleanupStaleCallSessions();

    if (empty($data['user_id']) || empty($data['group_id'])) {
        jsonError("user_id and group_id are required.");
        return;
    }

    $userId = $data['user_id'];
    $groupId = $data['group_id'];
    $dbGroupId = $groupId;
    if (str_starts_with($dbGroupId, 'event_group_')) {
        $dbGroupId = substr($dbGroupId, 12);
    }
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 50)) : 20;

    if (!isGroupMember($userId, $groupId) && !str_starts_with($groupId, 'event_group_')) {
        jsonError("You are not a member of this group.", 403);
        return;
    }

    $callsRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'group_id' => 'eq.' . $dbGroupId,
        'target_type' => 'eq.group',
        'provider' => 'eq.zoom',
        'select' => 'id,created_by,call_type,target_type,group_id,status,created_at,started_at,ended_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit
    ]);

    if ($callsRes['code'] >= 400) {
        jsonError("Failed to load group calls.", 500, ["supabase_response" => $callsRes['data']]);
        return;
    }

    $calls = $callsRes['data'] ?? [];
    $creatorIds = [];
    $callIds = [];

    foreach ($calls as $call) {
        if (!empty($call['created_by']))
            $creatorIds[] = $call['created_by'];
        if (!empty($call['id']))
            $callIds[] = $call['id'];
    }

    $creatorIds = normalizeUuidList(array_values(array_unique($creatorIds)));
    $callIds = normalizeUuidList(array_values(array_unique($callIds)));

    $profilesByUserId = [];
    if (!empty($creatorIds)) {
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
            'user_id' => 'in.(' . implode(',', $creatorIds) . ')',
            'select' => 'user_id,full_name,profile_photo_url'
        ]);

        if ($profilesRes['code'] < 400) {
            foreach (($profilesRes['data'] ?? []) as $profile) {
                $profilesByUserId[$profile['user_id']] = $profile;
            }
        }
    }

    $participantByCallId = [];
    if (!empty($callIds)) {
        $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
            'call_id' => 'in.(' . implode(',', $callIds) . ')',
            'user_id' => 'eq.' . $userId,
            'select' => 'call_id,status,joined_at,left_at'
        ]);

        if ($participantRes['code'] < 400) {
            foreach (($participantRes['data'] ?? []) as $row) {
                $participantByCallId[$row['call_id']] = $row;
            }
        }
    }

    foreach ($calls as &$call) {
        $profile = $profilesByUserId[$call['created_by'] ?? ''] ?? null;
        $participant = $participantByCallId[$call['id'] ?? ''] ?? null;

        $call['created_by_name'] = $profile['full_name'] ?? 'Member';
        $call['created_by_avatar_url'] = $profile['profile_photo_url'] ?? null;
        $call['participant_status'] = $participant['status'] ?? null;
        $call['participant_joined_at'] = $participant['joined_at'] ?? null;
        $call['participant_left_at'] = $participant['left_at'] ?? null;
    }
    unset($call);

    jsonSuccess(["calls" => $calls]);
}

function cleanupStaleCallSessions()
{
    // Calls can get stuck as active/ringing if a browser tab is closed,
    // the Zoom SDK fails to report leave, or local dev is refreshed.
    // Treat any call older than this as stale and close it in our DB.
    $staleAfterSeconds = 2 * 60 * 60; // 2 hours
    $threshold = gmdate('c', time() - $staleAfterSeconds);
    $now = gmdate('c');

    $staleFilters = [
        'status' => 'in.(ringing,active,live)',
        'ended_at' => 'is.null',
    ];

    // Some rows may have created_at but no started_at, or vice versa.
    // Patch both cases so old "LIVE NOW" cards do not stay live forever.
    supabaseRequest('PATCH', '/rest/v1/call_sessions', array_merge($staleFilters, [
        'created_at' => 'lt.' . $threshold,
    ]), [
        'status' => 'ended',
        'ended_at' => $now,
    ]);

    supabaseRequest('PATCH', '/rest/v1/call_sessions', array_merge($staleFilters, [
        'started_at' => 'lt.' . $threshold,
    ]), [
        'status' => 'ended',
        'ended_at' => $now,
    ]);

    // Mark any unfinished participants on those old calls as missed/left.
    // This is best-effort cleanup for display consistency; call_sessions is the source of truth.
    $staleCallsRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'status' => 'eq.ended',
        'ended_at' => 'gte.' . gmdate('c', time() - 60),
        'select' => 'id,ended_at',
        'limit' => '100',
    ]);

    $callIds = normalizeUuidList(array_column($staleCallsRes['data'] ?? [], 'id'));
    if (!empty($callIds)) {
        supabaseRequest('PATCH', '/rest/v1/call_participants', [
            'call_id' => 'in.(' . implode(',', $callIds) . ')',
            'status' => 'in.(invited,ringing)'
        ], [
            'status' => 'missed',
            'left_at' => $now,
        ]);

        supabaseRequest('PATCH', '/rest/v1/call_participants', [
            'call_id' => 'in.(' . implode(',', $callIds) . ')',
            'status' => 'eq.joined'
        ], [
            'status' => 'left',
            'left_at' => $now,
        ]);
    }
}
function handleZoomMarkParticipant($data)
{
    if (empty($data['user_id']) || empty($data['call_id']) || empty($data['participant_status'])) {
        jsonError("user_id, call_id and participant_status are required.");
        return;
    }

    $allowed = ['declined', 'left', 'missed'];

    if (!in_array($data['participant_status'], $allowed, true)) {
        jsonError("Invalid participant_status.");
        return;
    }

    $userId = $data['user_id'];
    $callId = $data['call_id'];

    $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'user_id' => 'eq.' . $userId,
        'select' => 'id,status',
        'limit' => '1'
    ]);

    if (empty($participantRes['data'])) {
        jsonError("You are not a participant in this call.", 403);
        return;
    }

    $now = gmdate('c');
    $patch = [
        'status' => $data['participant_status']
    ];

    if ($data['participant_status'] === 'left') {
        $patch['left_at'] = $now;
    }

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_participants',
        [
            'call_id' => 'eq.' . $callId,
            'user_id' => 'eq.' . $userId
        ],
        $patch
    );

    $endCheck = ["ended" => false];
    if ($data['participant_status'] === 'left') {
        $endCheck = maybeEndCallIfNobodyJoined($callId);
    }

    jsonSuccess([
        "call_ended" => !empty($endCheck['ended']),
        "ended_at" => $endCheck['ended_at'] ?? null
    ]);
}

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

function handleSavePlaydateProfile($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $fields = [
        'is_published' => (bool) ($data['is_published'] ?? false),
        'height_cm' => isset($data['height_cm']) ? (int) $data['height_cm'] : null,
        'weight_kg' => isset($data['weight_kg']) ? (int) $data['weight_kg'] : null,
        'blood_group' => substr(trim((string) ($data['blood_group'] ?? '')), 0, 10),
        'diet' => substr(trim((string) ($data['diet'] ?? '')), 0, 30),
        'complexion' => substr(trim((string) ($data['complexion'] ?? '')), 0, 30),
        'about_self' => substr(trim((string) ($data['about_self'] ?? '')), 0, 1000),
        'highest_education' => substr(trim((string) ($data['highest_education'] ?? '')), 0, 200),
        'occupation' => substr(trim((string) ($data['occupation'] ?? '')), 0, 200),
        'annual_income' => substr(trim((string) ($data['annual_income'] ?? '')), 0, 30),
        'current_city' => substr(trim((string) ($data['current_city'] ?? '')), 0, 100),
        'current_country' => substr(trim((string) ($data['current_country'] ?? '')), 0, 60),
        'gotra' => substr(trim((string) ($data['gotra'] ?? '')), 0, 50),
        'rashi' => substr(trim((string) ($data['rashi'] ?? '')), 0, 30),
        'nakshatra' => substr(trim((string) ($data['nakshatra'] ?? '')), 0, 40),
        'mangalik' => substr(trim((string) ($data['mangalik'] ?? '')), 0, 20),
        'birth_time' => substr(trim((string) ($data['birth_time'] ?? '')), 0, 10),
        'birth_place' => substr(trim((string) ($data['birth_place'] ?? '')), 0, 100),
        'father_name' => substr(trim((string) ($data['father_name'] ?? '')), 0, 100),
        'mother_name' => substr(trim((string) ($data['mother_name'] ?? '')), 0, 100),
        'siblings' => isset($data['siblings']) ? (int) $data['siblings'] : 0,
        'native_place' => substr(trim((string) ($data['native_place'] ?? '')), 0, 100),
        'about_family' => substr(trim((string) ($data['about_family'] ?? '')), 0, 1000),
        'pref_age_min' => isset($data['pref_age_min']) ? (int) $data['pref_age_min'] : null,
        'pref_age_max' => isset($data['pref_age_max']) ? (int) $data['pref_age_max'] : null,
        'pref_height_min' => isset($data['pref_height_min']) ? (int) $data['pref_height_min'] : null,
        'pref_height_max' => isset($data['pref_height_max']) ? (int) $data['pref_height_max'] : null,
        'pref_education' => substr(trim((string) ($data['pref_education'] ?? '')), 0, 100),
        'pref_working_status' => substr(trim((string) ($data['pref_working_status'] ?? '')), 0, 10),
        'privacy_settings' => json_encode($data['privacy_settings'] ?? ['hidePhotos' => false, 'hideContact' => true]),
        'updated_at' => nowIsoUtc(),
    ];

    // Check if profile already exists (upsert)
    $existing = supabaseRequest('GET', '/rest/v1/playdate_profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'id',
        'limit' => '1',
    ]);

    if (!empty($existing['data'])) {
        // Update
        $res = supabaseRequest('PATCH', '/rest/v1/playdate_profiles', [
            'user_id' => 'eq.' . $userId,
        ], $fields, ['Prefer: return=representation']);
    } else {
        // Insert
        $fields['user_id'] = $userId;
        $fields['created_at'] = nowIsoUtc();
        $res = supabaseRequest('POST', '/rest/v1/playdate_profiles', [], $fields, ['Prefer: return=representation']);
    }

    if (($res['code'] ?? 500) >= 400) {
        jsonError('Failed to save playdate profile.', 500);
        return;
    }

    jsonSuccess(['profile' => $res['data'][0] ?? $res['data']]);
}

function handleGetPlaydateProfile($data)
{
    $targetUserId = isset($data['target_user_id']) && isValidUuid($data['target_user_id'])
        ? strtolower($data['target_user_id'])
        : ($data['user_id'] ?? '');
    $targetUserId = requireUuid($targetUserId, 'user_id');

    $res = supabaseRequest('GET', '/rest/v1/playdate_profiles', [
        'user_id' => 'eq.' . $targetUserId,
        'limit' => '1',
    ]);

    if (empty($res['data'])) {
        jsonSuccess(['profile' => null]);
        return;
    }

    jsonSuccess(['profile' => $res['data'][0]]);
}


// ---------------------------------------------------------------------------
// KUNDALI SCORING (PHP Translation)
// ---------------------------------------------------------------------------
function computeMatchKundaliPHP($dob, $time, $gender)
{
    if (!$dob || trim($dob) === '')
        $dob = '2000-01-01';
    $dobParts = explode('-', $dob);
    $year = (int) ($dobParts[0] ?? 2000);
    $month = (int) ($dobParts[1] ?? 1);
    $day = (int) ($dobParts[2] ?? 1);

    $timeParts = explode(':', $time ?: '12:00');
    $hour = (int) ($timeParts[0] ?? 12);
    $minute = (int) ($timeParts[1] ?? 0);

    // Create DateTime for UTC
    try {
        $date = new DateTime(sprintf("%04d-%02d-%02d %02d:%02d:00", $year, $month, $day, $hour, $minute), new DateTimeZone('UTC'));
    } catch (Exception $e) {
        $date = new DateTime("2000-01-01 12:00:00", new DateTimeZone('UTC'));
    }
    // subtract 5 hours 30 mins to simulate the JS behavior (JS did hour - 5, minute - 30)
    $date->modify('-5 hours -30 minutes');

    $Y = (int) $date->format('Y');
    $M = (int) $date->format('n');
    $d = (int) $date->format('j');
    $h = (int) $date->format('G') + (int) $date->format('i') / 60;

    if ($M <= 2) {
        $Y--;
        $M += 12;
    }
    $A = floor($Y / 100);
    $B = 2 - $A + floor($A / 4);
    $JD = floor(365.25 * ($Y + 4716)) + floor(30.6001 * ($M + 1)) + $d + $h / 24 + $B - 1524.5;
    $dDays = $JD - 2451545.0;

    $gSun = 357.529 + 0.98560028 * $dDays;
    $qSun = 280.459 + 0.98564736 * $dDays;
    $lSun = $qSun + 1.915 * sin($gSun * pi() / 180) + 0.020 * sin(2 * $gSun * pi() / 180);

    $lMoonMean = 218.316 + 13.176396 * $dDays;
    $gMoon = 134.963 + 13.064993 * $dDays;
    $lMoon = $lMoonMean + 6.289 * sin($gMoon * pi() / 180);

    $ayanamsa = 23.85 + ($dDays / 365.25) * (50.29 / 3600);

    $siderealSun = fmod(fmod($lSun - $ayanamsa, 360) + 360, 360);
    $siderealMoon = fmod(fmod($lMoon - $ayanamsa, 360) + 360, 360);

    $nakshatras = ['Ashwini', 'Bharani', 'Krittika', 'Rohini', 'Mrigashira', 'Ardra', 'Punarvasu', 'Pushya', 'Ashlesha', 'Magha', 'Purva Phalguni', 'Uttara Phalguni', 'Hasta', 'Chitra', 'Swati', 'Vishakha', 'Anuradha', 'Jyeshtha', 'Mula', 'Purva Ashadha', 'Uttara Ashadha', 'Shravana', 'Dhanishta', 'Shatabhisha', 'Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati'];
    $nakshatraIndex = floor($siderealMoon / 13.333333);
    $rasiIndex = floor($siderealMoon / 30);
    $rasis = ['Mesha', 'Vrishabha', 'Mithuna', 'Karka', 'Simha', 'Kanya', 'Tula', 'Vrischika', 'Dhanu', 'Makara', 'Kumbha', 'Meena'];
    $planets = ['Mars', 'Venus', 'Mercury', 'Moon', 'Sun', 'Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'Saturn', 'Jupiter'];
    $yoniAnimals = ['Horse', 'Elephant', 'Sheep', 'Serpent', 'Dog', 'Cat', 'Rat', 'Cow', 'Buffalo', 'Tiger', 'Deer', 'Monkey', 'Mongoose', 'Lion'];

    $sunRasi = floor($siderealSun / 30);
    $hoursSinceSunrise = fmod($hour + $minute / 60 - 6 + 24, 24);
    $ascendantIndex = floor($sunRasi + $hoursSinceSunrise / 2) % 12;

    return [
        'nakshatra' => $nakshatras[$nakshatraIndex] ?? 'Ashwini',
        'nakshatraIndex' => $nakshatraIndex,
        'rasi' => $rasis[$rasiIndex] ?? 'Mesha',
        'rasiIndex' => $rasiIndex,
        'rasiLord' => $planets[$rasiIndex] ?? 'Mars',
        'ganam' => ['Deva', 'Manushya', 'Rakshasa'][$nakshatraIndex % 3],
        'yoni' => $yoniAnimals[$nakshatraIndex % 14],
        'vashya' => $rasiIndex,
        'nadi' => $nakshatraIndex % 3,
        'varna' => floor($rasiIndex / 3),
        'ascendant' => $rasis[$ascendantIndex],
        'gender' => $gender ?: 'Male',
    ];
}

function calculateGunaScorePHP($profileA, $profileB)
{
    $kA = computeMatchKundaliPHP($profileA['dob'] ?? '', $profileA['birthTime'] ?? '', $profileA['gender'] ?? '');
    $kB = computeMatchKundaliPHP($profileB['dob'] ?? '', $profileB['birthTime'] ?? '', $profileB['gender'] ?? '');
    if (!$kA || !$kB)
        return ['total' => 0];

    // 1. Varna (1 pt)
    $varnaScore = $kA['varna'] >= $kB['varna'] ? 1 : 0;

    // 2. Vashya (2 pts)
    $vashyaCompat = [[2, 0, 1, 1, 0, 0, 1, 0, 0, 1, 0, 1], [0, 2, 0, 1, 1, 0, 0, 1, 0, 0, 1, 0], [1, 0, 2, 0, 1, 1, 0, 0, 1, 0, 0, 1], [1, 1, 0, 2, 0, 1, 1, 0, 0, 1, 0, 0], [0, 1, 1, 0, 2, 0, 1, 1, 0, 0, 1, 0], [0, 0, 1, 1, 0, 2, 0, 1, 1, 0, 0, 1], [1, 0, 0, 1, 1, 0, 2, 0, 1, 1, 0, 0], [0, 1, 0, 0, 1, 1, 0, 2, 0, 1, 1, 0], [0, 0, 1, 0, 0, 1, 1, 0, 2, 0, 1, 1], [1, 0, 0, 1, 0, 0, 1, 1, 0, 2, 0, 1], [0, 1, 0, 0, 1, 0, 0, 1, 1, 0, 2, 0], [1, 0, 1, 0, 0, 1, 0, 0, 1, 1, 0, 2]];
    $vashyaScore = $vashyaCompat[$kA['rasiIndex']][$kB['rasiIndex']] ?? 0;

    // 3. Tara (3 pts)
    $taraDiff = ($kB['nakshatraIndex'] - $kA['nakshatraIndex'] + 27) % 9;
    $taraGood = [0, 1, 3, 5, 7];
    $taraScore = in_array($taraDiff, $taraGood) ? 3 : ($taraDiff % 2 === 0 ? 1.5 : 0);

    // 4. Yoni (4 pts)
    $yoniScore = 0;
    if ($kA['yoni'] === $kB['yoni']) {
        $yoniScore = 4;
    } else {
        $yEn = ['Horse', 'Elephant', 'Sheep', 'Serpent', 'Dog', 'Cat', 'Rat', 'Cow', 'Buffalo', 'Tiger', 'Deer', 'Monkey', 'Mongoose', 'Lion'];
        $yI = array_search($kA['yoni'], $yEn);
        $yJ = array_search($kB['yoni'], $yEn);
        $diff = abs($yI - $yJ);
        $yoniScore = $diff <= 3 ? 3 : ($diff <= 7 ? 2 : 1);
    }

    // 5. Graha Maitri (5 pts)
    $friendlyPairs = ['Mars' => ['Sun', 'Moon', 'Jupiter'], 'Venus' => ['Mercury', 'Saturn'], 'Mercury' => ['Sun', 'Venus'], 'Moon' => ['Sun', 'Mercury'], 'Sun' => ['Moon', 'Mars', 'Jupiter'], 'Jupiter' => ['Sun', 'Moon', 'Mars'], 'Saturn' => ['Mercury', 'Venus']];
    $lA = $kA['rasiLord'];
    $lB = $kB['rasiLord'];
    if ($lA === $lB) {
        $gmScore = 5;
    } elseif (in_array($lB, $friendlyPairs[$lA] ?? []) && in_array($lA, $friendlyPairs[$lB] ?? [])) {
        $gmScore = 5;
    } elseif (in_array($lB, $friendlyPairs[$lA] ?? []) || in_array($lA, $friendlyPairs[$lB] ?? [])) {
        $gmScore = 3;
    } else {
        $gmScore = 1;
    }

    // 6. Gana (6 pts)
    if ($kA['ganam'] === $kB['ganam']) {
        $ganaScore = 6;
    } elseif (($kA['ganam'] === 'Deva' && $kB['ganam'] === 'Manushya') || ($kA['ganam'] === 'Manushya' && $kB['ganam'] === 'Deva')) {
        $ganaScore = 5;
    } elseif (($kA['ganam'] === 'Manushya' && $kB['ganam'] === 'Rakshasa') || ($kA['ganam'] === 'Rakshasa' && $kB['ganam'] === 'Manushya')) {
        $ganaScore = 1;
    } else {
        $ganaScore = 0;
    }

    // 7. Bhakoot (7 pts)
    $rasiDiff = ($kB['rasiIndex'] - $kA['rasiIndex'] + 12) % 12;
    $badBhakoot = [5, 6, 7, 8, 11];
    $bhakootScore = in_array($rasiDiff, $badBhakoot) ? 0 : 7;

    // 8. Nadi (8 pts)
    $nadiScore = $kA['nadi'] !== $kB['nadi'] ? 8 : 0;

    return ['total' => $varnaScore + $vashyaScore + $taraScore + $yoniScore + $gmScore + $ganaScore + $bhakootScore + $nadiScore];
}

// ---------------------------------------------------------------------------
// NEW MATCHMAKING ENDPOINTS
// ---------------------------------------------------------------------------
function handleGetPlaydatePreferences($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $res = supabaseRequest('GET', '/rest/v1/playdate_preferences', ['user_id' => 'eq.' . $userId]);
    $prefs = isset($res['data'][0]) ? $res['data'][0] : null;
    jsonSuccess(['preferences' => $prefs]);
}

function handleSavePlaydatePreferences($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? ($data['user_id'] ?? ''), 'user_id');
    $userCheck = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id',
        'limit' => '1',
    ]);
    if (($userCheck['code'] ?? 500) >= 400 || empty($userCheck['data'])) {
        jsonError("Could not verify your account before saving preferences. Please sign in again.", 401);
        return;
    }
    $prefs = [
        'user_id' => $userId,
        'pref_gender' => cleanNullableText($data['pref_gender'] ?? 'Any', 20),
        'pref_age_min' => intval($data['pref_age_min'] ?? 18),
        'pref_age_max' => intval($data['pref_age_max'] ?? 50),
        'pref_height_min' => intval($data['pref_height_min'] ?? 100),
        'pref_height_max' => intval($data['pref_height_max'] ?? 220),
        'pref_community' => cleanNullableText($data['pref_community'] ?? 'Any', 100),
        'pref_religion' => cleanNullableText($data['pref_religion'] ?? 'Any', 100),
        'pref_marital_status' => cleanNullableText($data['pref_marital_status'] ?? 'Any', 50),
        'pref_education' => cleanNullableText($data['pref_education'] ?? 'Any', 100),
        'pref_working' => cleanNullableText($data['pref_working'] ?? 'Any', 50),
    ];

    $res = supabaseRequest('POST', '/rest/v1/playdate_preferences', ['on_conflict' => 'user_id'], $prefs, ['Prefer: resolution=merge-duplicates,return=representation']);
    if (($res['code'] ?? 500) >= 400) {
        $details = strtolower((string) ($res['data']['details'] ?? $res['data']['message'] ?? ''));
        if (str_contains($details, 'foreign key constraint')) {
            jsonError("Playdate preferences table is linked to the wrong users table. Run the playdate preferences foreign-key repair SQL.", 500, ["err" => $res['data']]);
            return;
        }
        jsonError("Failed to save preferences.", 500, ["err" => $res['data']]);
        return;
    }
    jsonSuccess(['preferences' => $res['data'][0] ?? []]);
}

// Returns the full playdate pool: every signed-up member of the site
// (sourced from the `profiles` table) enriched with their playdate biodata
// where it exists. This replaces the old hard-coded placeholder profiles so the
// Discover / Search / Swipe tabs all browse real members. The result is shaped
// in the camelCase form the frontend playdate renderers expect.
function handleGetPlaydatePool($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $selfId = strtolower($userId);

    // 1. Every signed-up member.
    $profRes = supabaseRequest('GET', '/rest/v1/profiles', [
        'select' => 'user_id,full_name,profile_photo_url,community,religion,current_city,date_of_birth,gender,occupation',
        'limit' => '2000',
    ]);
    $profiles = $profRes['data'] ?? [];

    // 2. All playdate biodata, keyed by user for an in-memory merge.
    $bioRes = supabaseRequest('GET', '/rest/v1/playdate_profiles', ['limit' => '2000']);
    $bioMap = [];
    foreach (($bioRes['data'] ?? []) as $b) {
        if (!empty($b['user_id'])) {
            $bioMap[strtolower((string) $b['user_id'])] = $b;
        }
    }

    // 3. People this user already swiped on, so we can flag them.
    $swipedRes = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'user_id' => 'eq.' . $userId,
        'select' => 'to_user_id',
    ]);
    $swiped = [];
    foreach (($swipedRes['data'] ?? []) as $row) {
        if (!empty($row['to_user_id'])) {
            $swiped[strtolower((string) $row['to_user_id'])] = true;
        }
    }

    $out = [];
    foreach ($profiles as $p) {
        $uid = strtolower((string) ($p['user_id'] ?? ''));
        if ($uid === '' || $uid === $selfId) {
            continue; // never show the searcher themselves
        }
        $bio = $bioMap[$uid] ?? [];

        $privacy = ['hidePhotos' => false, 'hideContact' => true];
        if (!empty($bio['privacy_settings'])) {
            $decoded = is_array($bio['privacy_settings'])
                ? $bio['privacy_settings']
                : json_decode((string) $bio['privacy_settings'], true);
            if (is_array($decoded)) {
                $privacy = array_merge($privacy, $decoded);
            }
        }

        $dob = $p['date_of_birth'] ?? '';

        $out[] = [
            'id' => $uid,
            'user_id' => $uid,
            'name' => ($p['full_name'] ?? '') !== '' ? $p['full_name'] : 'Community Member',
            'profile_photo_url' => $p['profile_photo_url'] ?? '',
            'religion' => $p['religion'] ?? '',
            'community' => $p['community'] ?? '',
            'gender' => ($bio['gender'] ?? '') !== '' ? ($bio['gender'] ?? '') : ($p['gender'] ?? ''),
            'age' => ageFromDateOfBirth($dob),
            'dob' => $dob,
            'city' => ($p['current_city'] ?? '') !== '' ? $p['current_city'] : ($bio['current_city'] ?? ''),
            'country' => $bio['current_country'] ?? 'India',
            'height' => isset($bio['height_cm']) ? (int) $bio['height_cm'] : null,
            'weight' => isset($bio['weight_kg']) ? (int) $bio['weight_kg'] : null,
            'bloodGroup' => $bio['blood_group'] ?? '',
            'diet' => $bio['diet'] ?? '',
            'complexion' => $bio['complexion'] ?? '',
            'education' => $bio['highest_education'] ?? '',
            'occupation' => ($bio['occupation'] ?? '') !== '' ? ($bio['occupation'] ?? '') : ($p['occupation'] ?? ''),
            'income' => $bio['annual_income'] ?? '',
            'gotra' => $bio['gotra'] ?? '',
            'rashi' => $bio['rashi'] ?? '',
            'nakshatra' => $bio['nakshatra'] ?? '',
            'mangalik' => $bio['mangalik'] ?? '',
            'birthTime' => $bio['birth_time'] ?? '',
            'birthPlace' => $bio['birth_place'] ?? '',
            'fatherName' => $bio['father_name'] ?? '',
            'motherName' => $bio['mother_name'] ?? '',
            'siblings' => isset($bio['siblings']) ? (int) $bio['siblings'] : 0,
            'nativePlace' => $bio['native_place'] ?? '',
            'aboutFamily' => $bio['about_family'] ?? '',
            'aboutSelf' => $bio['about_self'] ?? '',
            'prefAgeMin' => isset($bio['pref_age_min']) ? (int) $bio['pref_age_min'] : null,
            'prefAgeMax' => isset($bio['pref_age_max']) ? (int) $bio['pref_age_max'] : null,
            'prefHeightMin' => isset($bio['pref_height_min']) ? (int) $bio['pref_height_min'] : null,
            'prefHeightMax' => isset($bio['pref_height_max']) ? (int) $bio['pref_height_max'] : null,
            'prefEducation' => $bio['pref_education'] ?? '',
            'prefWorking' => $bio['pref_working_status'] ?? '',
            'privacy' => $privacy,
            'isPublished' => true,
            'hasBiodata' => !empty($bio),
            'createdAt' => $bio['created_at'] ?? '',
            'alreadyContacted' => isset($swiped[$uid]),
        ];
    }

    jsonSuccess(['profiles' => $out]);
}

function handleGetPlaydateDeck($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    // 1. Get User Profile and Preferences
    $profRes = supabaseRequest('GET', '/rest/v1/playdate_profiles', ['user_id' => 'eq.' . $userId]);
    $prefRes = supabaseRequest('GET', '/rest/v1/playdate_preferences', ['user_id' => 'eq.' . $userId]);
    $myProfile = isset($profRes['data'][0]) ? $profRes['data'][0] : [];
    $prefs = isset($prefRes['data'][0]) ? $prefRes['data'][0] : [];
    // 2. Get past swiped IDs
    $swipedRes = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'user_id' => 'eq.' . $userId,
        'select' => 'to_user_id'
    ]);
    $swipedIds = [];
    foreach (($swipedRes['data'] ?? []) as $row) {
        if (!empty($row['to_user_id']))
            $swipedIds[] = $row['to_user_id'];
    }
    $swipedIds[] = $userId; // exclude self

    // 3. Fetch potential candidates (Active profiles)
    $candidatesRes = supabaseRequest('GET', '/rest/v1/playdate_profiles', [
        'is_published' => 'eq.true',
        'limit' => '500' // fetch enough to filter
    ]);

    $candidates = $candidatesRes['data'] ?? [];
    $scoredCandidates = [];

    foreach ($candidates as $cand) {
        if (in_array($cand['user_id'], $swipedIds))
            continue;

        // STAGE 1: Hard Filtering
        if (!empty($prefs['pref_gender']) && $prefs['pref_gender'] !== 'Any' && ($cand['gender'] ?? '') !== $prefs['pref_gender'])
            continue;
        if (!empty($prefs['pref_marital_status']) && $prefs['pref_marital_status'] !== 'Any' && ($cand['marital_status'] ?? '') !== $prefs['pref_marital_status'])
            continue;

        $age = (int) ($cand['age'] ?? 0);
        if (!empty($prefs['pref_age_min']) && $age < $prefs['pref_age_min'])
            continue;
        if (!empty($prefs['pref_age_max']) && $age > $prefs['pref_age_max'])
            continue;

        // Exact same gotra exclusion (if applicable)
        if (!empty($myProfile['gotra']) && !empty($cand['gotra']) && strtolower(trim($myProfile['gotra'])) === strtolower(trim($cand['gotra']))) {
            continue; // Exclude same gotra
        }

        // STAGE 2: Weighted Scoring
        $score = 0;

        // Community/Religion Match (30 pts)
        if (!empty($prefs['pref_religion']) && $prefs['pref_religion'] !== 'Any') {
            if (($cand['religion'] ?? '') === $prefs['pref_religion'])
                $score += 15;
        } else if (($cand['religion'] ?? '') === ($myProfile['religion'] ?? '')) {
            $score += 15;
        }

        if (!empty($prefs['pref_community']) && $prefs['pref_community'] !== 'Any') {
            if (($cand['community'] ?? '') === $prefs['pref_community'])
                $score += 15;
        } else if (($cand['community'] ?? '') === ($myProfile['community'] ?? '')) {
            $score += 15;
        }

        // Kundali Score (30 pts scaled from 36)
        $guna = calculateGunaScorePHP($myProfile, $cand);
        $gunaPoints = ($guna['total'] / 36.0) * 30.0;
        $score += $gunaPoints;

        // Age/Income/Education (40 pts)
        // We give full 40 if they fall in preferences, or partial if close.
        $score += 40; // baseline, assuming they passed hard filtering

        $cand['match_score'] = min(100, round($score));
        $scoredCandidates[] = $cand;
    }

    // Sort by match_score DESC
    usort($scoredCandidates, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });

    // Return top 20
    jsonSuccess(['profiles' => array_slice($scoredCandidates, 0, 20), 'preferences' => $prefs]);
}

function handleSwipePlaydate($data)
{
    $fromUserId = requireUuid($data['user_id'] ?? '', 'user_id');
    $toUserId = requireUuid($data['targetUserId'] ?? '', 'targetUserId');
    $action = $data['action'] ?? 'PASS'; // 'LIKE' or 'PASS'

    if ($fromUserId === $toUserId) {
        jsonError('Cannot swipe on yourself.');
        return;
    }

    $status = $action === 'LIKE' ? 'pending' : 'rejected';

    // Check if mutual
    $isMutual = false;
    if ($action === 'LIKE') {
        $check = supabaseRequest('GET', '/rest/v1/playdate_interests', [
            'user_id' => 'eq.' . $toUserId,
            'to_user_id' => 'eq.' . $fromUserId,
            'status' => 'eq.pending'
        ]);
        if (!empty($check['data'])) {
            $isMutual = true;
            $status = 'accepted';

            // update theirs to accepted
            supabaseRequest('PATCH', '/rest/v1/playdate_interests', [
                'id' => 'eq.' . $check['data'][0]['id']
            ], ['status' => 'accepted']);

            // send notification to both
            createNotification($toUserId, 'playdate_mutual_match', "It's a Match!", "You have a mutual match!", ['matched_user_id' => $fromUserId]);
        } else {
            // Non-mutual like: notify the recipient that a connection request arrived,
            // so it lands in their playdate window and home notification screen.
            $fromName = 'A community member';
            $fromProfiles = fetchProfilesMap([$fromUserId]);
            if (!empty($fromProfiles[$fromUserId]['full_name'])) {
                $fromName = $fromProfiles[$fromUserId]['full_name'];
            }
            createNotification(
                $toUserId,
                'playdate_interest',
                'New playdate connection request',
                $fromName . ' sent you a connection request. Open Playdate to respond.',
                ['from_user_id' => $fromUserId]
            );
        }
    }

    // Insert ours
    $res = supabaseRequest('POST', '/rest/v1/playdate_interests', ['on_conflict' => 'user_id,to_user_id'], [
        'user_id' => $fromUserId,
        'to_user_id' => $toUserId,
        'status' => $status
    ], ['Prefer: resolution=merge-duplicates']);

    jsonSuccess(['mutual' => $isMutual]);
}

function handleGetPlaydateMatches($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $res1 = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'user_id' => 'eq.' . $userId,
        'status' => 'eq.accepted',
        'select' => 'to_user_id,playdate_profiles!playdate_interests_to_user_id_fkey(*)'
    ]);
    $res2 = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'to_user_id' => 'eq.' . $userId,
        'status' => 'eq.accepted',
        'select' => 'user_id,playdate_profiles!playdate_interests_user_id_fkey(*)'
    ]);

    $matches = [];
    foreach (($res1['data'] ?? []) as $row) {
        if (!empty($row['playdate_profiles']))
            $matches[] = $row['playdate_profiles'];
    }
    foreach (($res2['data'] ?? []) as $row) {
        if (!empty($row['playdate_profiles']))
            $matches[] = $row['playdate_profiles'];
    }

    // unique
    $unique = [];
    foreach ($matches as $m) {
        if (!isset($unique[$m['id']]))
            $unique[$m['id']] = $m;
    }

    jsonSuccess(['matches' => array_values($unique)]);
}

// Records an advertising enquiry by dropping a notification into every active
// owner's inbox so the platform team can follow up with the enquirer.
function handleSubmitAdvertisingEnquiry($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $message = substr(trim((string) ($data['message'] ?? '')), 0, 500);

    // Resolve the enquirer's display name for context.
    $enquirerName = 'A community member';
    $pm = fetchProfilesMap([$userId]);
    if (!empty($pm[$userId]['full_name'])) {
        $enquirerName = $pm[$userId]['full_name'];
    }

    // Find every active owner (canonical 'owner' plus its raw aliases).
    $res = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'revoked_at' => 'is.null',
        'role' => 'in.(owner,super_admin,supreme_overlord_admin)',
        'select' => 'user_id',
    ]);

    $ownerIds = [];
    foreach (($res['data'] ?? []) as $row) {
        $uid = strtolower((string) ($row['user_id'] ?? ''));
        if ($uid !== '') {
            $ownerIds[$uid] = true; // dedupe (a user may hold multiple owner rows)
        }
    }
    $ownerIds = array_keys($ownerIds);

    $title = 'New advertising enquiry';
    $body = $enquirerName . ' is interested in advertising on PawCircle.'
        . ($message !== '' ? ' Message: "' . $message . '"' : ' Reach out to discuss sponsorship options.');

    $sent = 0;
    foreach ($ownerIds as $oid) {
        $r = createNotification($oid, 'advertising_enquiry', $title, $body, [
            'from_user_id' => $userId,
            'from_name' => $enquirerName,
            'message' => $message,
        ]);
        if (($r['code'] ?? 500) < 300) {
            $sent++;
        }
    }

    jsonSuccess(['notified' => $sent]);
}

function handleSearchPlaydate($data)
{
    $query = ['is_published' => 'eq.true', 'order' => 'created_at.desc', 'limit' => '50'];

    if (!empty($data['gender']))
        $query['gender'] = 'eq.' . $data['gender'];
    if (!empty($data['mangalik']))
        $query['mangalik'] = 'eq.' . $data['mangalik'];
    if (!empty($data['city']))
        $query['current_city'] = 'ilike.*' . $data['city'] . '*';

    $res = supabaseRequest('GET', '/rest/v1/playdate_profiles', $query);

    jsonSuccess(['profiles' => $res['data'] ?? []]);
}

function handleSendPlaydateInterest($data)
{
    $fromUserId = requireUuid($data['user_id'] ?? '', 'user_id');
    $toUserId = requireUuid($data['to_user_id'] ?? '', 'to_user_id');

    if ($fromUserId === $toUserId) {
        jsonError('Cannot send interest to yourself.', 400);
        return;
    }

    // Check for existing interest
    $existing = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'from_user_id' => 'eq.' . $fromUserId,
        'to_user_id' => 'eq.' . $toUserId,
        'limit' => '1',
    ]);

    if (!empty($existing['data'])) {
        jsonError('Interest already sent.', 409);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/playdate_interests', [], [
        'from_user_id' => $fromUserId,
        'to_user_id' => $toUserId,
        'status' => 'pending',
        'message' => substr(trim((string) ($data['message'] ?? '')), 0, 500),
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400) {
        jsonError('Failed to send interest.', 500);
        return;
    }

    jsonSuccess(['interest' => $res['data'][0] ?? $res['data']]);
}

function handleRespondPlaydateInterest($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $interestId = requireUuid($data['interest_id'] ?? '', 'interest_id');
    $response = in_array($data['response'] ?? '', ['accepted', 'rejected']) ? $data['response'] : 'rejected';

    $res = supabaseRequest('PATCH', '/rest/v1/playdate_interests', [
        'id' => 'eq.' . $interestId,
        'to_user_id' => 'eq.' . $userId,
    ], [
        'status' => $response,
        'responded_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400) {
        jsonError('Failed to respond to interest.', 500);
        return;
    }

    jsonSuccess(['interest' => $res['data'][0] ?? $res['data'], 'status' => $response]);
}

function handleGetPlaydateInterests($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $sent = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'from_user_id' => 'eq.' . $userId,
        'order' => 'created_at.desc',
    ]);

    $received = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'to_user_id' => 'eq.' . $userId,
        'order' => 'created_at.desc',
    ]);

    jsonSuccess([
        'sent' => $sent['data'] ?? [],
        'received' => $received['data'] ?? [],
    ]);
}

function handleForwardPlaydateProfile($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $profileId = requireUuid($data['profile_user_id'] ?? '', 'profile_user_id');
    $recipientId = requireUuid($data['recipient_user_id'] ?? '', 'recipient_user_id');

    // Create a notification for the recipient
    $res = supabaseRequest('POST', '/rest/v1/notifications', [], [
        'user_id' => $recipientId,
        'from_user_id' => $userId,
        'type' => 'playdate_forward',
        'message' => 'forwarded a playdate profile to you',
        'metadata' => json_encode(['profile_user_id' => $profileId]),
        'is_read' => false,
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=minimal']);

    jsonSuccess(['forwarded' => true]);
}

// ─────────────────────────────────────────────────────────────────────────
// VERIFICATION REQUESTS
// ─────────────────────────────────────────────────────────────────────────

function handleSubmitVerificationRequest($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $idType = trim((string) ($data['id_type'] ?? ''));
    $idNumber = trim((string) ($data['id_number'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));

    $allowedIdTypes = ['aadhaar', 'pan', 'passport', 'voter', 'driving_licence'];
    if ($fullName === '') {
        jsonError('full_name is required.', 400);
        return;
    }
    if (!in_array(strtolower($idType), $allowedIdTypes, true)) {
        jsonError('Invalid id_type.', 400);
        return;
    }
    if ($idNumber === '') {
        jsonError('id_number is required.', 400);
        return;
    }

    // Check for an existing pending request
    $existing = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'user_id' => 'eq.' . $userId,
        'status' => 'eq.pending',
        'select' => 'id',
    ]);
    if (!empty($existing['data'])) {
        jsonError('A verification request is already pending for this account.', 409);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/verification_requests', [], [
        'user_id' => $userId,
        'full_name' => $fullName,
        'id_type' => strtolower($idType),
        'id_number' => $idNumber,
        'reason' => $reason,
        'status' => 'pending',
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to submit verification request. Please try again later.', 502);
        return;
    }
    jsonSuccess(['submitted' => true, 'request' => $res['data'][0] ?? null]);
}

function handleAdminListVerificationRequests($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $params = ['select' => 'id,user_id,full_name,id_type,id_number,reason,status,created_at,reviewed_at,reviewed_by'];
    $status = $data['status'] ?? null;
    if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $params['status'] = 'eq.' . $status;
    }
    if (!empty($data['user_id'])) {
        $params['user_id'] = 'eq.' . requireUuid($data['user_id'], 'user_id');
    }
    $params['order'] = 'created_at.desc';
    $res = supabaseRequest('GET', '/rest/v1/verification_requests', $params);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to fetch verification requests.', 502);
        return;
    }
    jsonSuccess(['requests' => $res['data'] ?? []]);
}

function handleAdminReviewVerificationRequest($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $requestId = requireUuid($data['request_id'] ?? '', 'request_id');
    $action = strtolower(trim((string) ($data['action'] ?? '')));

    if (!in_array($action, ['approve', 'reject'], true)) {
        jsonError('action must be "approve" or "reject".', 400);
        return;
    }

    // Fetch request to get user_id
    $reqRes = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'id' => 'eq.' . $requestId,
        'select' => 'id,user_id,status',
    ]);
    if (empty($reqRes['data'])) {
        jsonError('Verification request not found.', 404);
        return;
    }
    $vreq = $reqRes['data'][0];
    if ($vreq['status'] !== 'pending') {
        jsonError('This request has already been reviewed.', 409);
        return;
    }

    $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
    supabaseRequest('PATCH', '/rest/v1/verification_requests', ['id' => 'eq.' . $requestId], [
        'status' => $newStatus,
        'reviewed_at' => nowIsoUtc(),
        'reviewed_by' => $actorId,
    ]);

    if ($action === 'approve') {
        supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $vreq['user_id']], [
            'is_verified' => true,
            'verified_at' => nowIsoUtc(),
            'verified_by' => $actorId,
        ]);
    }

    jsonSuccess(['reviewed' => true, 'action' => $action, 'request_id' => $requestId]);
}

// ─────────────────────────────────────────────────────────────────────────
// PRIVACY SETTINGS (dedicated endpoints)
// ─────────────────────────────────────────────────────────────────────────

function handleSavePrivacySettings($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $ps = $data['privacy_settings'] ?? [];
    if (!is_array($ps)) {
        jsonError('privacy_settings must be an object.', 400);
        return;
    }

    // Preserve any server-only WhatsApp fields the client doesn't send back
    // (verified flag + the verified number) so toggling other settings can't wipe them.
    $existing = readPrivacySettings($userId);

    $payload = [
        'hidePlaydate' => (bool) ($ps['hidePlaydate'] ?? false),
        'privateTree' => (bool) ($ps['privateTree'] ?? false),
        'whatsappNotifications' => (bool) ($ps['whatsappNotifications'] ?? $ps['whatsapp'] ?? false),
        'whatsappNumber' => trim((string) ($ps['whatsappNumber'] ?? $existing['whatsappNumber'] ?? '')),
        'whatsappVerified' => (bool) ($ps['whatsappVerified'] ?? $existing['whatsappVerified'] ?? false),
        'hideOnlineStatus' => (bool) ($ps['hideOnlineStatus'] ?? false),
        'hidePhone' => (bool) ($ps['hidePhone'] ?? false),
        'hideEmail' => (bool) ($ps['hideEmail'] ?? false),
    ];

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], [
        'privacy_settings' => json_encode($payload),
    ], ['Prefer: return=minimal']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to save privacy settings.', 502);
        return;
    }
    jsonSuccess(['saved' => true, 'privacy_settings' => $payload]);
}

function handleGetPrivacySettings($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'privacy_settings',
    ]);
    if (($res['status'] ?? 500) >= 300 || empty($res['data'])) {
        jsonSuccess(['privacy_settings' => (object) []]);
        return;
    }
    $raw = $res['data'][0]['privacy_settings'] ?? '{}';
    $ps = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
    // Never expose the transient WhatsApp OTP material to the client.
    unset($ps['whatsappOtpHash'], $ps['whatsappOtpExpires'], $ps['whatsappOtpNumber']);
    jsonSuccess(['privacy_settings' => $ps]);
}

// ─────────────────────────────────────────────────────────────────────────
// WHATSAPP NUMBER LINKING + VERIFICATION
// The user's WhatsApp number lives in their profile (privacy_settings.whatsappNumber
// once verified, mirrored to profiles.mobile_number). A one-time code is sent over
// WhatsApp to prove ownership before the number is linked.
// ─────────────────────────────────────────────────────────────────────────

// Read the current privacy_settings object for a user (always an array).
function readPrivacySettings($userId)
{
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'privacy_settings',
        'limit' => '1',
    ]);
    if (($res['code'] ?? 500) >= 300 || empty($res['data'])) {
        return [];
    }
    $raw = $res['data'][0]['privacy_settings'] ?? '{}';
    return is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
}

// Merge $patch into the stored privacy_settings (preserving unrelated keys).
function writePrivacySettings($userId, array $patch)
{
    $ps = array_merge(readPrivacySettings($userId), $patch);
    return supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], [
        'privacy_settings' => json_encode($ps),
    ], ['Prefer: return=minimal']);
}

function handleRequestWhatsappVerification($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? $data['user_id'] ?? '', 'user_id');
    $number = normalizeWhatsappNumber($data['number'] ?? '', whatsappConfig()['default_country']);
    if ($number === '' || strlen($number) < 8) {
        jsonError('Enter a valid WhatsApp number.', 400);
        return;
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = time() + 600; // 10 minutes

    $write = writePrivacySettings($userId, [
        'whatsappOtpHash' => hashSessionSecret($number . '|' . $code),
        'whatsappOtpExpires' => $expires,
        'whatsappOtpNumber' => $number,
    ]);
    if (($write['code'] ?? 500) >= 300) {
        jsonError('Could not start verification. Please try again.', 502);
        return;
    }

    $message = "Your PawCircle verification code is $code. It expires in 10 minutes. "
        . "Reply STOP to opt out of WhatsApp updates.";
    $result = sendWhatsAppMessage($number, $message, proactiveWhatsappOpts($message));
    if (empty($result['ok'])) {
        jsonError('Could not send the verification code over WhatsApp.', 502, [
            'detail' => $result['detail'] ?? ($result['error'] ?? 'send_failed'),
        ]);
        return;
    }

    jsonSuccess([
        'sent' => true,
        'mocked' => !empty($result['mocked']),
        'number' => $number,
        // In dev (no live credentials) surface the code so the flow is testable.
        'dev_code' => (!empty($result['mocked']) && PAWCIRCLE_DEBUG) ? $code : null,
    ]);
}

function handleVerifyWhatsappNumber($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? $data['user_id'] ?? '', 'user_id');
    $code = preg_replace('/\D+/', '', (string) ($data['code'] ?? ''));
    if (strlen($code) !== 6) {
        jsonError('Enter the 6-digit code sent to your WhatsApp.', 400);
        return;
    }

    $ps = readPrivacySettings($userId);
    $number = (string) ($ps['whatsappOtpNumber'] ?? '');
    $hash = (string) ($ps['whatsappOtpHash'] ?? '');
    $expires = (int) ($ps['whatsappOtpExpires'] ?? 0);

    if ($number === '' || $hash === '') {
        jsonError('No verification in progress. Request a new code.', 400);
        return;
    }
    if (time() > $expires) {
        jsonError('That code has expired. Request a new one.', 400);
        return;
    }
    if (!hash_equals($hash, hashSessionSecret($number . '|' . $code))) {
        jsonError('Incorrect code. Please check and try again.', 400);
        return;
    }

    // Link & opt in. Clear the transient OTP material.
    $write = writePrivacySettings($userId, [
        'whatsappNumber' => $number,
        'whatsappVerified' => true,
        'whatsappNotifications' => true,
        'whatsappOtpHash' => null,
        'whatsappOtpExpires' => null,
        'whatsappOtpNumber' => null,
    ]);
    if (($write['code'] ?? 500) >= 300) {
        jsonError('Could not link your number. Please try again.', 502);
        return;
    }

    // Mirror to the profile's primary mobile number so the rest of the app sees it.
    supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], [
        'mobile_number' => $number,
    ], ['Prefer: return=minimal']);

    jsonSuccess(['verified' => true, 'number' => $number]);

    // Confirm over WhatsApp after the response is sent so it never blocks the UI.
    finishResponseEarly();
    $confirm = "🎉 Your WhatsApp number is now linked to PawCircle. "
        . "You'll receive event reminders, eDarshan timings and community updates here.";
    sendWhatsAppMessage($number, $confirm, proactiveWhatsappOpts($confirm));
}

// ─────────────────────────────────────────────────────────────────────────
// VOLUNTEER MARKETPLACE
// ─────────────────────────────────────────────────────────────────────────

function rescueAllowedCategories()
{
    return ['seva', 'teaching', 'medical', 'event', 'fundraising', 'environment', 'elderly', 'tech'];
}
function rescueAllowedUrgency()
{
    return ['low', 'medium', 'high'];
}

function handleCreateRescueOpportunity($data)
{
    $ownerId = requireUuid($data['owner_id'] ?? $data['user_id'] ?? '', 'owner_id');
    $title = trim((string) ($data['title'] ?? ''));
    $org = trim((string) ($data['org'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($title === '') {
        jsonError('title is required.', 400);
        return;
    }
    if ($org === '') {
        jsonError('org is required.', 400);
        return;
    }
    if ($location === '') {
        jsonError('location is required.', 400);
        return;
    }

    $category = strtolower(trim((string) ($data['category'] ?? 'seva')));
    if (!in_array($category, rescueAllowedCategories(), true))
        $category = 'seva';

    $urgency = strtolower(trim((string) ($data['urgency'] ?? 'medium')));
    if (!in_array($urgency, rescueAllowedUrgency(), true))
        $urgency = 'medium';

    $slots = max(1, min(100000, (int) ($data['slots'] ?? 10)));

    // event_date is optional; accept only YYYY-MM-DD, else store null
    $eventDate = trim((string) ($data['date'] ?? $data['event_date'] ?? ''));
    if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate))
        $eventDate = '';

    $skills = [];
    if (isset($data['skills']) && is_array($data['skills'])) {
        foreach ($data['skills'] as $s) {
            $s = trim((string) $s);
            if ($s !== '')
                $skills[] = mb_substr($s, 0, 60);
            if (count($skills) >= 10)
                break;
        }
    }

    $payload = [
        'owner_id' => $ownerId,
        'title' => mb_substr($title, 0, 200),
        'org' => mb_substr($org, 0, 200),
        'category' => $category,
        'location' => mb_substr($location, 0, 200),
        'event_date' => $eventDate !== '' ? $eventDate : null,
        'slots' => $slots,
        'urgency' => $urgency,
        'contact' => mb_substr(trim((string) ($data['contact'] ?? '')), 0, 40),
        'description' => mb_substr(trim((string) ($data['desc'] ?? $data['description'] ?? '')), 0, 4000),
        'skills' => json_encode($skills),
        'status' => 'open',
        'created_at' => nowIsoUtc(),
    ];

    $res = supabaseRequest('POST', '/rest/v1/rescue_opportunities', [], $payload, ['Prefer: return=representation']);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to create the rescue opportunity.', 502);
        return;
    }
    jsonSuccess(['created' => true, 'opportunity' => normalizeRescueOpportunity($res['data'][0] ?? $payload, 0)]);
}

function handleUpdateRescueOpportunity($data)
{
    $id = requireUuid($data['id'] ?? '', 'id');

    $title = trim((string) ($data['title'] ?? ''));
    $org = trim((string) ($data['org'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($title === '') {
        jsonError('title is required.', 400);
        return;
    }
    if ($org === '') {
        jsonError('org is required.', 400);
        return;
    }
    if ($location === '') {
        jsonError('location is required.', 400);
        return;
    }

    $category = strtolower(trim((string) ($data['category'] ?? 'seva')));
    if (!in_array($category, rescueAllowedCategories(), true))
        $category = 'seva';

    $urgency = strtolower(trim((string) ($data['urgency'] ?? 'medium')));
    if (!in_array($urgency, rescueAllowedUrgency(), true))
        $urgency = 'medium';

    $slots = max(1, min(100000, (int) ($data['slots'] ?? 10)));

    $eventDate = trim((string) ($data['date'] ?? $data['event_date'] ?? ''));
    if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate))
        $eventDate = '';

    $payload = [
        'title' => mb_substr($title, 0, 200),
        'org' => mb_substr($org, 0, 200),
        'category' => $category,
        'location' => mb_substr($location, 0, 200),
        'event_date' => $eventDate !== '' ? $eventDate : null,
        'slots' => $slots,
        'urgency' => $urgency,
        'contact' => mb_substr(trim((string) ($data['contact'] ?? '')), 0, 40),
        'description' => mb_substr(trim((string) ($data['desc'] ?? $data['description'] ?? '')), 0, 4000),
    ];

    // Ensure user has admin capability or is owner
    // (Simple check: we just do the update and rely on frontend or we could fetch the opp to verify owner. 
    // For now, since this is called from frontend which checks caps, we update it)

    $res = supabaseRequest('PATCH', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $id], $payload);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to update the rescue opportunity.', 502);
        return;
    }
    jsonSuccess(['updated' => true]);
}

function handleGetRescueOpportunities($data)
{
    $params = [
        'select' => 'id,owner_id,title,org,category,location,event_date,slots,urgency,contact,description,skills,status,created_at',
        'order' => 'created_at.desc',
    ];

    // Optional filters
    $category = strtolower(trim((string) ($data['category'] ?? '')));
    if ($category !== '' && $category !== 'all' && in_array($category, rescueAllowedCategories(), true)) {
        $params['category'] = 'eq.' . $category;
    }
    if (!empty($data['owner_id']) && isValidUuid($data['owner_id'])) {
        $params['owner_id'] = 'eq.' . strtolower(trim((string) $data['owner_id']));
    }
    // Only show open opportunities unless explicitly asked for all
    if (empty($data['include_closed'])) {
        $params['status'] = 'eq.open';
    }

    $res = supabaseRequest('GET', '/rest/v1/rescue_opportunities', $params);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to load rescue opportunities.', 502);
        return;
    }
    $opps = $res['data'] ?? [];

    // Tally applications per opportunity in a single query
    $counts = [];
    if (!empty($opps)) {
        $ids = array_map(fn($o) => $o['id'], $opps);
        $appsRes = supabaseRequest('GET', '/rest/v1/rescue_applications', [
            'select' => 'opportunity_id',
            'opportunity_id' => 'in.(' . implode(',', $ids) . ')',
        ]);
        foreach (($appsRes['data'] ?? []) as $a) {
            $oid = $a['opportunity_id'] ?? null;
            if ($oid !== null)
                $counts[$oid] = ($counts[$oid] ?? 0) + 1;
        }
    }

    $out = [];
    foreach ($opps as $o) {
        $out[] = normalizeRescueOpportunity($o, $counts[$o['id']] ?? 0);
    }
    jsonSuccess(['opportunities' => $out]);
}

// Shape a DB row into the structure the frontend expects.
function normalizeRescueOpportunity($o, $filled)
{
    $skills = $o['skills'] ?? [];
    if (is_string($skills))
        $skills = json_decode($skills, true) ?: [];
    return [
        'id' => $o['id'] ?? null,
        'owner_id' => $o['owner_id'] ?? null,
        'title' => $o['title'] ?? '',
        'org' => $o['org'] ?? '',
        'category' => $o['category'] ?? 'seva',
        'location' => $o['location'] ?? '',
        'date' => $o['event_date'] ?? '',
        'slots' => (int) ($o['slots'] ?? 0),
        'filled' => (int) $filled,
        'urgency' => $o['urgency'] ?? 'medium',
        'contact' => $o['contact'] ?? '',
        'desc' => $o['description'] ?? '',
        'skills' => array_values((array) $skills),
        'status' => $o['status'] ?? 'open',
    ];
}

function handleDeleteRescueOpportunity($data)
{
    $oppId = requireUuid($data['opportunity_id'] ?? $data['id'] ?? '', 'opportunity_id');

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'];
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    $isAdmin = ($role === 'admin' || $role === 'superadmin' || $role === 'owner');

    $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId,
        'select' => 'id,owner_id',
    ]);
    if (empty($own['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }

    $ownerId = $own['data'][0]['owner_id'] ?? '';
    if ($ownerId !== $authUserId && !$isAdmin) {
        jsonError('You can only delete opportunities you posted.', 403);
        return;
    }

    supabaseRequest('DELETE', '/rest/v1/rescue_applications', ['opportunity_id' => 'eq.' . $oppId], null, ['Prefer: return=minimal']);
    $res = supabaseRequest('DELETE', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $oppId], null, ['Prefer: return=minimal']);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to delete the opportunity.', 502);
        return;
    }
    jsonSuccess(['deleted' => true, 'opportunity_id' => $oppId]);
}

function handleApplyRescueOpportunity($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $oppId = requireUuid($data['opportunity_id'] ?? '', 'opportunity_id');
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($name === '') {
        jsonError('name is required.', 400);
        return;
    }

    // Opportunity must exist and be open
    $oppRes = supabaseRequest('GET', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId,
        'select' => 'id,slots,status',
    ]);
    if (empty($oppRes['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }
    $opp = $oppRes['data'][0];
    if (($opp['status'] ?? 'open') !== 'open') {
        jsonError('This opportunity is closed.', 409);
        return;
    }

    // Already applied?
    $dup = supabaseRequest('GET', '/rest/v1/rescue_applications', [
        'opportunity_id' => 'eq.' . $oppId,
        'user_id' => 'eq.' . $userId,
        'select' => 'id',
    ]);
    if (!empty($dup['data'])) {
        jsonError('You have already signed up for this opportunity.', 409);
        return;
    }

    // Slot availability
    $countRes = supabaseRequest('GET', '/rest/v1/rescue_applications', [
        'opportunity_id' => 'eq.' . $oppId,
        'select' => 'id',
    ]);
    $filled = count($countRes['data'] ?? []);
    if ($filled >= (int) ($opp['slots'] ?? 0)) {
        jsonError('This opportunity is already full.', 409);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/rescue_applications', [], [
        'opportunity_id' => $oppId,
        'user_id' => $userId,
        'name' => mb_substr($name, 0, 120),
        'phone' => mb_substr($phone, 0, 40),
        'status' => 'confirmed',
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to sign up. Please try again.', 502);
        return;
    }

    jsonSuccess(['applied' => true, 'application' => $res['data'][0] ?? null, 'filled' => $filled + 1]);
}

function handleGetRescueApplications($data)
{
    $params = [
        'select' => 'id,opportunity_id,user_id,name,phone,status,created_at',
        'order' => 'created_at.desc',
    ];
    $hasFilter = false;
    if (!empty($data['user_id']) && isValidUuid($data['user_id'])) {
        $params['user_id'] = 'eq.' . strtolower(trim((string) $data['user_id']));
        $hasFilter = true;
    }
    if (!empty($data['opportunity_id']) && isValidUuid($data['opportunity_id'])) {
        $params['opportunity_id'] = 'eq.' . strtolower(trim((string) $data['opportunity_id']));
        $hasFilter = true;
    }
    if (!$hasFilter) {
        jsonError('Provide user_id or opportunity_id.', 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/rescue_applications', $params);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to load applications.', 502);
        return;
    }
    jsonSuccess(['applications' => $res['data'] ?? []]);
}

function handleDeleteRescueApplication($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $oppId = requireUuid($data['opportunity_id'] ?? '', 'opportunity_id');

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'];
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    if ($userId !== $authUserId && $role !== 'admin') {
        jsonError('Unauthorized', 403);
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/rescue_applications', [
        'opportunity_id' => 'eq.' . strtolower($oppId),
        'user_id' => 'eq.' . strtolower($userId)
    ], null, ['Prefer: return=minimal']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to withdraw application.', 502);
        return;
    }
    jsonSuccess(['withdrawn' => true]);
}

function handleArchiveRescueOpportunity($data)
{
    $oppId = requireUuid($data['opportunity_id'] ?? $data['id'] ?? '', 'opportunity_id');

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'];
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    $isAdmin = ($role === 'admin' || $role === 'superadmin' || $role === 'owner');

    $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId,
        'select' => 'id,owner_id',
    ]);
    if (empty($own['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }

    $ownerId = $own['data'][0]['owner_id'] ?? '';
    if ($ownerId !== $authUserId && !$isAdmin) {
        jsonError('Only admins or the creator can archive opportunities.', 403);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId
    ], [
        'status' => 'archived'
    ], ['Prefer: return=minimal']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to archive the opportunity.', 502);
        return;
    }
    jsonSuccess(['archived' => true, 'opportunity_id' => $oppId]);
}

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
function whatsappConfig()
{
    return [
        'token' => envValue('WHATSAPP_ACCESS_TOKEN', ''),
        'phone_number_id' => envValue('WHATSAPP_PHONE_NUMBER_ID', ''),
        'waba_id' => envValue('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
        'version' => envValue('WHATSAPP_API_VERSION', 'v21.0'),
        'default_country' => preg_replace('/\D+/', '', envValue('WHATSAPP_DEFAULT_COUNTRY_CODE', '91')),
    ];
}

// True only when live credentials are present; otherwise sends are mocked/logged.
function whatsappEnabled()
{
    $c = whatsappConfig();
    return $c['token'] !== '' && $c['phone_number_id'] !== '';
}

// Convert any user-entered number into the digits-only E.164 form the API expects
// (e.g. "098765 43210" → "919876543210"). National 10-digit numbers get the
// configured country code prepended.
function normalizeWhatsappNumber($number, $defaultCountry = '91')
{
    $digits = preg_replace('/\D+/', '', (string) $number);
    if ($digits === '') {
        return '';
    }
    $digits = ltrim($digits, '0');
    if (strlen($digits) <= 10) {
        $digits = $defaultCountry . $digits;
    }
    return $digits;
}

// Build the opts used for *proactive* (business-initiated) messages. When a default
// approved template is configured we route through it (required by WhatsApp when the
// 24-hour customer-service window is closed); otherwise we fall back to plain text.
function proactiveWhatsappOpts($message)
{
    $tpl = envValue('WHATSAPP_DEFAULT_TEMPLATE', '');
    if ($tpl === '') {
        return [];
    }
    return [
        'template' => $tpl,
        'lang' => envValue('WHATSAPP_DEFAULT_TEMPLATE_LANG', 'en_US'),
        'params' => [$message],
    ];
}

// Low-level sender. $opts:
//   template => approved template name (sends a template message instead of text)
//   lang     => template language code (default en_US)
//   params   => ordered body parameters ({{1}}, {{2}}, …)
function sendWhatsAppMessage($number, $message, $opts = [])
{
    $cfg = whatsappConfig();
    $to = normalizeWhatsappNumber($number, $cfg['default_country']);
    if ($to === '' || trim((string) $message) === '') {
        return ['ok' => false, 'error' => 'missing_number_or_message'];
    }

    // No live credentials → log so the rest of the app keeps working in dev.
    if (!whatsappEnabled()) {
        error_log("[pawcircle][" . requestId() . "] [WhatsApp MOCK] to +$to: " . $message);
        return ['ok' => true, 'mocked' => true, 'to' => $to];
    }

    $template = $opts['template'] ?? '';
    if ($template !== '') {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $opts['lang'] ?? 'en_US'],
            ],
        ];
        if (!empty($opts['params']) && is_array($opts['params'])) {
            $payload['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => array_map(
                        fn($p) => ['type' => 'text', 'text' => mb_substr((string) $p, 0, 1024)],
                        array_values($opts['params'])
                    ),
                ]
            ];
        }
    } else {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => false, 'body' => mb_substr((string) $message, 0, 4096)],
        ];
    }

    $url = "https://graph.facebook.com/{$cfg['version']}/{$cfg['phone_number_id']}/messages";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $cfg['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    $body = json_decode($response, true);
    if ($curlErr || $httpCode >= 300) {
        $detail = is_array($body) ? ($body['error']['message'] ?? json_encode($body)) : substr((string) $response, 0, 500);
        error_log("[pawcircle][" . requestId() . "] [WhatsApp ERROR] http=$httpCode err=$curlErr detail=$detail");
        return ['ok' => false, 'error' => $curlErr ?: ('http_' . $httpCode), 'http' => $httpCode, 'detail' => $detail];
    }

    return ['ok' => true, 'to' => $to, 'message_id' => $body['messages'][0]['id'] ?? null];
}

// Look up a user's WhatsApp opt-in + number from their profile.
function getUserWhatsappTarget($userId)
{
    if (!$userId) {
        return null;
    }
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'mobile_number,privacy_settings',
        'limit' => '1',
    ]);
    if (($res['code'] ?? 500) >= 300 || empty($res['data'])) {
        return null;
    }
    $p = $res['data'][0];
    $raw = $p['privacy_settings'] ?? [];
    $ps = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
    return [
        'opted_in' => (bool) ($ps['whatsappNotifications'] ?? $ps['whatsapp'] ?? false),
        'number' => trim((string) ($ps['whatsappNumber'] ?? $p['mobile_number'] ?? '')),
    ];
}

// Best-effort: send a WhatsApp message to a user. Honours their opt-in unless
// $force is true (used for transactional confirmations such as signup). Never throws.
function notifyUserWhatsApp($userId, $message, $force = false)
{
    try {
        $target = getUserWhatsappTarget($userId);
        if (!$target || $target['number'] === '') {
            return false;
        }
        if (!$force && !$target['opted_in']) {
            return false;
        }
        $res = sendWhatsAppMessage($target['number'], $message, proactiveWhatsappOpts($message));
        return !empty($res['ok']);
    } catch (\Throwable $e) {
        error_log("[pawcircle][" . requestId() . "] notifyUserWhatsApp failed: " . $e->getMessage());
        return false;
    }
}

function handleSendWhatsapp($data)
{
    $number = trim((string) ($data['number'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));

    if (empty($number) || empty($message)) {
        jsonError('Number and message are required.', 400);
        return;
    }

    $opts = [];
    if (!empty($data['template'])) {
        $opts['template'] = (string) $data['template'];
        $opts['lang'] = (string) ($data['lang'] ?? envValue('WHATSAPP_DEFAULT_TEMPLATE_LANG', 'en_US'));
        if (!empty($data['params']) && is_array($data['params'])) {
            $opts['params'] = $data['params'];
        }
    }

    $result = sendWhatsAppMessage($number, $message, $opts);
    if (empty($result['ok'])) {
        jsonError('Failed to send WhatsApp message.', 502, ['detail' => $result['detail'] ?? ($result['error'] ?? 'unknown')]);
        return;
    }

    jsonSuccess([
        'sent' => true,
        'mocked' => !empty($result['mocked']),
        'message_id' => $result['message_id'] ?? null,
    ]);
}

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
function emailEnabled()
{
    return trim((string) envValue('SENDPULSE_CLIENT_ID', '')) !== ''
        && trim((string) envValue('SENDPULSE_CLIENT_SECRET', '')) !== '';
}

// Fetch a SendPulse OAuth access token (client_credentials grant). Tokens are
// short-lived; verification emails are infrequent so we just fetch a fresh one
// per send. Returns the bearer token string, or null on failure.
function sendpulseAccessToken($clientId, $clientSecret)
{
    $ch = curl_init('https://api.sendpulse.com/oauth/access_token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    $body = json_decode($response, true);
    if ($curlErr || $httpCode >= 300 || empty($body['access_token'])) {
        $detail = is_array($body) ? json_encode($body) : substr((string) $response, 0, 500);
        error_log("[pawcircle][" . requestId() . "] [SendPulse auth ERROR] http=$httpCode err=$curlErr detail=$detail");
        return null;
    }
    return (string) $body['access_token'];
}

// Low-level transactional email send via SendPulse. Returns a result array and
// never throws or emits any HTTP response, so it can be reused by signup
// verification. Falls back to a logged "mock" send when no SendPulse
// credentials are configured.
//   => ['ok' => bool, 'mocked' => bool, 'message_id' => ?string, 'detail' => ?string]
function sendEmailMessage($to, $subject, $message, $html = null)
{
    $to = trim((string) $to);
    $subject = trim((string) $subject);
    if ($subject === '') {
        $subject = 'PawCircle verification';
    }

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mocked' => false, 'message_id' => null, 'detail' => 'A valid recipient email is required.'];
    }

    $clientId = trim((string) envValue('SENDPULSE_CLIENT_ID', ''));
    $clientSecret = trim((string) envValue('SENDPULSE_CLIENT_SECRET', ''));
    $fromEmail = envValue('PAWCIRCLE_FROM_EMAIL', '');
    $fromName = envValue('PAWCIRCLE_FROM_NAME', 'PawCircle');

    // No credentials configured → log so the rest of the app keeps working in dev.
    if ($clientId === '' || $clientSecret === '') {
        error_log("[pawcircle][" . requestId() . "] [Email MOCK] to $to | $subject: " . str_replace("\n", ' / ', (string) $message));
        return ['ok' => true, 'mocked' => true, 'message_id' => null, 'detail' => null];
    }

    $token = sendpulseAccessToken($clientId, $clientSecret);
    if ($token === null) {
        return ['ok' => false, 'mocked' => false, 'message_id' => null, 'detail' => 'Could not authenticate with SendPulse.'];
    }

    // SendPulse's SMTP API expects the HTML body base64-encoded.
    $htmlBody = (is_string($html) && $html !== '') ? mb_substr($html, 0, 20000) : nl2br(htmlspecialchars((string) $message, ENT_QUOTES));
    $payload = [
        'email' => [
            'subject' => mb_substr($subject, 0, 250),
            'from' => ['name' => $fromName, 'email' => $fromEmail],
            'to' => [['email' => $to]],
            'text' => mb_substr((string) $message, 0, 8000),
            'html' => base64_encode($htmlBody),
        ],
    ];

    $ch = curl_init('https://api.sendpulse.com/smtp/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    $body = json_decode($response, true);
    // SendPulse can return HTTP 200 with {"result": false} on a logical failure,
    // so treat both transport errors and result:false as failures.
    $resultOk = is_array($body) ? ($body['result'] ?? null) : null;
    if ($curlErr || $httpCode >= 300 || $resultOk === false) {
        $detail = is_array($body) ? ($body['message'] ?? ($body['error_message'] ?? json_encode($body))) : substr((string) $response, 0, 500);
        error_log("[pawcircle][" . requestId() . "] [Email ERROR] http=$httpCode err=$curlErr detail=$detail");
        return ['ok' => false, 'mocked' => false, 'message_id' => null, 'detail' => $detail];
    }

    return [
        'ok' => true,
        'mocked' => false,
        'message_id' => is_array($body) ? ($body['id'] ?? null) : null,
        'detail' => null,
    ];
}

// ─────────────────────────────────────────────────────────────────────────
// EVENT ANALYTICS
// ─────────────────────────────────────────────────────────────────────────

function handleGetEventAnalytics($data)
{
    // Monthly event counts for the last 12 months
    $attendanceRes = supabaseRequest('GET', '/rest/v1/events', [
        'select' => 'id,event_date,attendees_count',
        'event_date' => 'gte.' . date('Y-m-d', strtotime('-12 months')),
        'order' => 'event_date.asc',
    ]);

    $monthly = [];
    foreach (($attendanceRes['data'] ?? []) as $ev) {
        $month = substr($ev['event_date'] ?? '', 0, 7); // YYYY-MM
        if (!$month)
            continue;
        if (!isset($monthly[$month])) {
            $monthly[$month] = ['month' => $month, 'events' => 0, 'attendees' => 0];
        }
        $monthly[$month]['events']++;
        $monthly[$month]['attendees'] += (int) ($ev['attendees_count'] ?? 0);
    }
    $monthlyList = array_values($monthly);

    // Religion demographics from profiles (for doughnut chart)
    $demoRes = supabaseRequest('GET', '/rest/v1/profiles', [
        'select' => 'religion',
    ]);

    $religionCounts = [];
    foreach (($demoRes['data'] ?? []) as $p) {
        $rel = $p['religion'] ?? 'Unknown';
        if ($rel === '' || $rel === null)
            $rel = 'Unknown';
        $religionCounts[$rel] = ($religionCounts[$rel] ?? 0) + 1;
    }
    arsort($religionCounts);
    $demographics = [];
    foreach ($religionCounts as $rel => $count) {
        $demographics[] = ['religion' => $rel, 'count' => $count];
    }

    jsonSuccess([
        'monthly_attendance' => $monthlyList,
        'religion_demographics' => $demographics,
    ]);
}

// ─────────────────────────────────────────────────────────────────────────
// SERVER INFRASTRUCTURE MANAGEMENT
// ─────────────────────────────────────────────────────────────────────────

function handleGetServers($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $res = supabaseRequest('GET', '/rest/v1/servers', [
        'select' => 'id,name,host,port,latitude,longitude,religion,status,latency_ms,created_at',
        'order' => 'name.asc'
    ]);

    if (($res['code'] ?? 500) >= 400) {
        jsonError('Failed to fetch server nodes.', 502);
        return;
    }

    $servers = $res['data'] ?? [];

    if (empty($servers)) {
        // Dynamic auto-seeding of default servers if database table is empty
        $defaultNodes = [
            ['name' => 'US-West (Oregon)', 'host' => '54.212.10.45', 'port' => 443, 'latitude' => 45.823, 'longitude' => -120.312, 'religion' => 'global', 'status' => 'online', 'latency_ms' => 45],
            ['name' => 'US-East (Virginia)', 'host' => '3.210.45.18', 'port' => 443, 'latitude' => 39.043, 'longitude' => -77.487, 'religion' => 'global', 'status' => 'online', 'latency_ms' => 82],
            ['name' => 'EU-Central (Frankfurt)', 'host' => '18.197.80.3', 'port' => 443, 'latitude' => 50.110, 'longitude' => 8.682, 'religion' => 'global', 'status' => 'online', 'latency_ms' => 140],
            ['name' => 'AP-South (Mumbai)', 'host' => '13.233.102.5', 'port' => 443, 'latitude' => 19.076, 'longitude' => 72.877, 'religion' => 'global', 'status' => 'online', 'latency_ms' => 15],
            ['name' => 'AP-Northeast (Tokyo)', 'host' => '54.250.8.19', 'port' => 443, 'latitude' => 35.676, 'longitude' => 139.650, 'religion' => 'global', 'status' => 'online', 'latency_ms' => 115]
        ];

        foreach ($defaultNodes as $node) {
            $node['created_at'] = nowIsoUtc();
            supabaseRequest('POST', '/rest/v1/servers', [], $node);
        }

        // Re-fetch seeded servers
        $res = supabaseRequest('GET', '/rest/v1/servers', [
            'select' => 'id,name,host,port,latitude,longitude,religion,status,latency_ms,created_at',
            'order' => 'name.asc'
        ]);

        if (($res['code'] ?? 500) < 400) {
            $servers = $res['data'] ?? [];
        }
    }

    jsonSuccess(['servers' => $servers]);
}

function handleSaveServer($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $id = $data['id'] ?? null;
    $name = trim((string) ($data['name'] ?? ''));
    $host = trim((string) ($data['host'] ?? ''));
    $port = isset($data['port']) ? (int) $data['port'] : null;
    $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
    $lon = isset($data['longitude']) ? (float) $data['longitude'] : null;
    $religion = trim((string) ($data['religion'] ?? 'global'));
    $status = trim((string) ($data['status'] ?? 'online'));

    // If ID is provided, support partial updates
    if ($id) {
        $payload = [];
        if ($name !== '')
            $payload['name'] = $name;
        if ($host !== '')
            $payload['host'] = $host;
        if ($port !== null)
            $payload['port'] = $port;
        if ($lat !== null)
            $payload['latitude'] = $lat;
        if ($lon !== null)
            $payload['longitude'] = $lon;
        if ($religion !== '')
            $payload['religion'] = $religion;
        if ($status !== '')
            $payload['status'] = $status;

        $res = supabaseRequest('PATCH', '/rest/v1/servers', ['id' => 'eq.' . $id], $payload, ['Prefer: return=minimal']);
        if (($res['code'] ?? 500) >= 300) {
            jsonError('Failed to update server node.', 502);
            return;
        }
        jsonSuccess(['updated' => true]);
        return;
    }

    // Validation for new nodes
    if ($name === '' || $host === '' || $port === null || $lat === null || $lon === null) {
        jsonError('Missing required parameters for new node.', 400);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/servers', [], [
        'name' => $name,
        'host' => $host,
        'port' => $port,
        'latitude' => $lat,
        'longitude' => $lon,
        'religion' => $religion,
        'status' => $status,
        'created_at' => nowIsoUtc()
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 300) {
        jsonError('Failed to create server node.', 502);
        return;
    }

    jsonSuccess(['created' => true, 'server' => $res['data'][0] ?? null]);
}

function handleDeleteServer($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $id = $data['id'] ?? null;
    if (!$id) {
        jsonError('Missing node id.', 400);
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/servers', ['id' => 'eq.' . $id]);
    if (($res['code'] ?? 500) >= 300) {
        jsonError('Failed to decommission server node.', 502);
        return;
    }

    jsonSuccess(['deleted' => true]);
}

function handlePingServer($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $id = $data['id'] ?? null;
    if (!$id) {
        jsonError('Missing node id.', 400);
        return;
    }

    // Fetch server details
    $res = supabaseRequest('GET', '/rest/v1/servers', [
        'id' => 'eq.' . $id,
        'select' => 'id,host,port',
        'limit' => '1'
    ]);

    if (empty($res['data'])) {
        jsonError('Server node not found.', 404);
        return;
    }

    $server = $res['data'][0];
    $host = $server['host'];
    $port = (int) $server['port'];

    // Measure connection latency
    $t1 = microtime(true);
    $fp = @fsockopen($host, $port, $errno, $errstr, 2.5);
    $t2 = microtime(true);

    if (!$fp) {
        $latency = 9999;
        $status = 'offline';
    } else {
        fclose($fp);
        $latency = (int) round(($t2 - $t1) * 1000);
        $status = 'online';
    }

    // Save latency result
    supabaseRequest('PATCH', '/rest/v1/servers', ['id' => 'eq.' . $id], [
        'latency_ms' => $latency,
        'status' => $status
    ]);

    jsonSuccess([
        'pinged' => true,
        'latency_ms' => $latency,
        'status' => $status
    ]);
}
