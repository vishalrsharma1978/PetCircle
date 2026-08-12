<?php
/**
 * PawCircle auth: self-contained email/password accounts on our own `users`
 * table (password_hash via Argon2id/bcrypt), a 6-digit emailed signup code
 * (signup_verifications), and a custom session/CSRF cookie pair on top
 * (session.php). No Supabase Auth identity is created for these accounts —
 * eSamaj's build layers Supabase Auth underneath as an identity bridge for
 * Google OAuth, but PetCircle's current sign-in UI has no OAuth button wired
 * up, so that whole bridge (and the bug it introduces — signups leaving
 * users.password_hash null because account creation and password hashing
 * happen in two different places) is intentionally not carried over. This
 * can be added back if/when Google sign-in is actually wired up.
 */

function isValidUuid($value)
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim((string) $value));
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

function isLocalDevRequest()
{
    $ip = getClientIpAddress();
    return in_array($ip, ['127.0.0.1', '::1'], true)
        || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), 'localhost:')
        || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), '127.0.0.1:');
}

function testUsersEnabled()
{
    return defined('PAWCIRCLE_DEBUG') && PAWCIRCLE_DEBUG
        && isLocalDevRequest()
        && in_array(strtolower((string) envValue('PAWCIRCLE_ENABLE_TEST_USERS', '')), ['1', 'true', 'yes', 'on'], true);
}

// Local-dev-only shortcuts (see testUsersEnabled gating). Pet-flavored fixtures,
// not the religion-as-pet_type placeholders the old PetCircle codebase had.
function testUserMap()
{
    return [
        'userdog' => ['pet_name' => 'Test Dog', 'parent_name' => 'Test Parent', 'email' => 'test-user-dog@pawcircle.local', 'pet_type' => 'Dog', 'breed' => 'Labrador Retriever'],
        'usercat' => ['pet_name' => 'Test Cat', 'parent_name' => 'Test Parent', 'email' => 'test-user-cat@pawcircle.local', 'pet_type' => 'Cat', 'breed' => 'Domestic Shorthair'],
        'userbird' => ['pet_name' => 'Test Bird', 'parent_name' => 'Test Parent', 'email' => 'test-user-bird@pawcircle.local', 'pet_type' => 'Bird', 'breed' => 'Budgerigar'],
    ];
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

function normaliseAdminCapabilityRow($row)
{
    if (empty($row['role']))
        return null;
    return [
        'role' => $row['role'],
        'scope_type' => $row['scope_type'] ?? 'global',
        'scope_value' => $row['scope_value'] ?? '*',
    ];
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

function userHasOwnerCapability($userId)
{
    foreach (fetchAdminCapabilities($userId) as $cap) {
        if (($cap['role'] ?? '') === 'owner')
            return true;
    }
    return false;
}

function userHasGlobalAdminCapability($userId)
{
    foreach (fetchAdminCapabilities($userId) as $cap) {
        if (in_array($cap['role'] ?? '', ['owner', 'platform_admin'], true))
            return true;
    }
    return false;
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
    $ok = safePatchUserTrackingFields($userId, ['last_active_at' => $now], 'last_active_at');
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
        jsonError("Could not create a secure session. Please try again.", 500);
        exit();
    }

    setSessionCookies($rawToken, $csrfToken, $expiresTs);

    return [
        'expires_at' => $expiresAt,
        'csrf_token' => $csrfToken,
        'session_token' => $rawToken,
    ];
}

function fetchAppUserWithProfileById($userId)
{
    if (!isValidUuid($userId)) {
        return null;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . strtolower($userId),
        'select' => 'id,email,role,handle,is_verified,verified_at,last_login_at,last_active_at,deactivated_at,created_at,profiles(pet_name,parent_name,full_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city,visibility,online_status)',
        'limit' => '1',
    ]);

    return (($res['code'] ?? 500) < 400 && !empty($res['data'])) ? $res['data'][0] : null;
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
    $adminCaps = fetchAdminCapabilities($user['id'] ?? '');

    return [
        "id" => $user['id'],
        "name" => $profile['pet_name'] ?? $profile['full_name'] ?? ($user['email'] ?? 'Member'),
        "pet_name" => $profile['pet_name'] ?? null,
        "parent_name" => $profile['parent_name'] ?? null,
        "handle" => $user['handle'] ?? null,
        "email" => $user['email'] ?? '',
        "role" => $user['role'] ?? 'member',
        "pet_type" => $profile['pet_type'] ?? null,
        "breed" => $profile['breed'] ?? null,
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
        "admin_capabilities" => $adminCaps,
        "admin_mode_active" => false,
        "admin_mode_until" => null,
    ];
}

function handleSignup($data)
{
    if (empty($data['pet_name']) || empty($data['email']) || empty($data['password'])) {
        jsonError("Pet name, email, and password are required.", 400);
        return;
    }

    $petName = htmlspecialchars(strip_tags($data['pet_name']));
    $parentName = htmlspecialchars(strip_tags($data['parent_name'] ?? ''));
    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    $petType = htmlspecialchars(strip_tags($data['pet_type'] ?? ''));
    $breed = htmlspecialchars(strip_tags($data['breed'] ?? ''));
    $phone = htmlspecialchars(strip_tags($data['mobile_number'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError("Invalid email format.", 400);
        return;
    }
    if (strlen($password) < 10) {
        jsonError("Password must be at least 10 characters.", 400);
        return;
    }

    $checkRes = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $email, 'select' => 'id']);
    if (!empty($checkRes['data'])) {
        jsonError("An account with this email already exists.", 409);
        return;
    }

    $passwordHash = password_hash($password, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    $pendingPayload = [
        'pet_name' => $petName,
        'parent_name' => $parentName,
        'email' => $email,
        'password_hash' => $passwordHash,
        'pet_type' => $petType,
        'breed' => $breed,
        'mobile_number' => $phone,
    ];

    if (!defined('PAWCIRCLE_EMAIL_VERIFICATION_ENABLED') || constant('PAWCIRCLE_EMAIL_VERIFICATION_ENABLED') == false) {
        finalizeSignup($pendingPayload, $password);
        return;
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $emailKey = strtolower($email);

    supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['email' => 'eq.' . $emailKey]);
    $store = supabaseRequest('POST', '/rest/v1/signup_verifications', [], [
        'email' => $emailKey,
        'code_hash' => password_hash($code, PASSWORD_BCRYPT),
        'payload' => $pendingPayload,
        'attempts' => 0,
        'expires_at' => gmdate('c', time() + PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS),
    ], ['Prefer: return=minimal']);

    if (($store['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] signup verification store failed | http=" . ($store['code'] ?? 'n/a') . " | response=" . json_encode($store['data'] ?? null));
        jsonError("Could not start email verification. Please try again.", 500);
        return;
    }

    $sent = sendSignupVerificationEmail($email, $petName, $code);
    if (empty($sent['ok'])) {
        $detail = $sent['detail'] ?? 'unknown error';
        if (emailFailureIsInvalidRecipient($detail)) {
            error_log("[pawcircle][" . requestId() . "] signup verification email rejected as invalid recipient ($detail) for $email");
            supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['email' => 'eq.' . $emailKey]);
            jsonError("We couldn't send a verification code to that email address. Please double-check it and try again.", 502);
            return;
        }
        error_log("[pawcircle][" . requestId() . "] signup verification email failed ($detail) — falling back to unverified signup for $email");
        supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['email' => 'eq.' . $emailKey]);
        finalizeSignup($pendingPayload, $password);
        return;
    }

    jsonSuccess(array_filter([
        "verification_required" => true,
        "email" => $email,
        "expires_in" => PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS,
        "message" => "We've sent a 6-digit verification code to $email. Enter it to finish creating your account.",
        // Local-dev convenience so the code flow is testable without a real
        // mail provider configured — never present unless PAWCIRCLE_DEBUG=true.
        "debug_code" => (defined('PAWCIRCLE_DEBUG') && PAWCIRCLE_DEBUG) ? $code : null,
    ], fn($v) => $v !== null));
}

function emailFailureIsInvalidRecipient($detail)
{
    $d = strtolower(trim((string) $detail));
    if ($d === '')
        return false;
    if (strpos($d, 'limit') !== false || strpos($d, 'quota') !== false || strpos($d, 'exceed') !== false) {
        return false;
    }
    foreach (
        [
            'invalid email', 'invalid recipient', 'invalid address', 'invalid e-mail',
            'email is not valid', 'not a valid email', 'incorrect email', 'wrong email',
            'recipient address', 'no mx', 'mailbox', 'does not exist', 'undeliverable',
        ] as $needle
    ) {
        if (strpos($d, $needle) !== false) {
            return true;
        }
    }
    return false;
}

function sendSignupVerificationEmail($email, $petName, $code)
{
    $petName = trim((string) $petName);
    $hello = $petName !== '' ? "Hi there," : "Hi,";
    $mins = (int) round(PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS / 60);

    $subject = 'Your PawCircle verification code: ' . $code;
    $text = $hello . "\n\n"
        . "Your PawCircle email verification code is:\n\n"
        . "    $code\n\n"
        . "Enter this 6-digit code on the signup screen to finish creating " . ($petName !== '' ? "$petName's" : "your") . " account. "
        . "The code expires in $mins minutes.\n\n"
        . "If you didn't request this, you can safely ignore this email.\n\n— The PawCircle Team";

    $safeHello = htmlspecialchars($hello, ENT_QUOTES);
    $digits = '';
    foreach (str_split($code) as $d) {
        $digits .= '<span style="display:inline-block;min-width:44px;margin:0 4px;padding:12px 0;font-size:30px;font-weight:700;letter-spacing:2px;color:#2B2420;background:#FFF3E0;border-radius:10px;font-family:monospace;">' . $d . '</span>';
    }
    $html = '<div style="font-family:Segoe UI,Helvetica,Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937;">'
        . '<h2 style="margin:0 0 8px;color:#e04848;">Verify your email 🐾</h2>'
        . '<p style="margin:0 0 18px;">' . $safeHello . ' enter this code to finish creating your PawCircle account:</p>'
        . '<div style="text-align:center;margin:20px 0;">' . $digits . '</div>'
        . '<p style="margin:0 0 6px;color:#6b7280;font-size:13px;">This code expires in ' . $mins . ' minutes.</p>'
        . '<p style="margin:0;color:#9ca3af;font-size:12px;">If you didn\'t request this, you can safely ignore this email.</p>'
        . '<p style="margin:18px 0 0;font-size:13px;">— The PawCircle Team</p>'
        . '</div>';

    return sendEmailMessage($email, $subject, $text, $html);
}

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

    $dup = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $regEmail, 'select' => 'id']);
    if (!empty($dup['data'])) {
        supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']]);
        jsonError('An account with this email already exists. Please sign in.', 409);
        return;
    }

    supabaseRequest('DELETE', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']]);
    finalizeSignup($payload, $plainPassword);
}

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
    $petName = $payload['pet_name'] ?? '';
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    supabaseRequest('PATCH', '/rest/v1/signup_verifications', ['id' => 'eq.' . $row['id']], [
        'code_hash' => password_hash($code, PASSWORD_BCRYPT),
        'attempts' => 0,
        'expires_at' => gmdate('c', time() + PAWCIRCLE_SIGNUP_CODE_TTL_SECONDS),
    ], ['Prefer: return=minimal']);

    $sent = sendSignupVerificationEmail($email, $petName, $code);
    if (empty($sent['ok'])) {
        jsonError("We couldn't send the verification email. Please try again.", 502);
        return;
    }

    jsonSuccess(array_filter([
        'resent' => true,
        'email' => $email,
        'message' => "A new code is on its way to $email.",
        'debug_code' => (defined('PAWCIRCLE_DEBUG') && PAWCIRCLE_DEBUG) ? $code : null,
    ], fn($v) => $v !== null));
}

function finalizeSignup($payload, $plainPassword = '')
{
    $petName = (string) ($payload['pet_name'] ?? '');
    $parentName = (string) ($payload['parent_name'] ?? '');
    $email = filter_var((string) ($payload['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $passwordHash = (string) ($payload['password_hash'] ?? '');
    $petType = (string) ($payload['pet_type'] ?? '');
    $breed = (string) ($payload['breed'] ?? '');
    $phone = (string) ($payload['mobile_number'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) $plainPassword) < 10 || $passwordHash === '' || !password_verify((string) $plainPassword, $passwordHash)) {
        jsonError('Verification data was incomplete. Please sign up again.', 400);
        return;
    }

    // Create the app user directly with the already-hashed password (no
    // separate identity-provider step, so there's no window where the account
    // exists but can't log in — see the file header note).
    $userRes = supabaseRequest('POST', '/rest/v1/users', [], [
        'email' => $email,
        'password_hash' => $passwordHash,
        'role' => 'member',
    ], ['Prefer: return=representation']);

    if (($userRes['code'] ?? 500) >= 400 || empty($userRes['data'])) {
        sendSupabaseError('Could not create your account.', $userRes);
        return;
    }

    $userId = $userRes['data'][0]['id'];

    // full_name is NOT NULL on profiles (a leftover eSamaj constraint); keep it
    // populated with the pet's name until the profile-system rebuild step
    // revisits that column.
    $profileInsert = [
        'user_id' => $userId,
        'full_name' => $petName,
        'pet_name' => $petName ?: null,
        'parent_name' => $parentName ?: null,
        'pet_type' => $petType ?: null,
        'breed' => $breed ?: null,
        'mobile_number' => $phone ?: null,
    ];

    $profileRes = supabaseRequest('POST', '/rest/v1/profiles', [], $profileInsert, ['Prefer: resolution=merge-duplicates,return=minimal']);
    if (($profileRes['code'] ?? 500) >= 400) {
        sendSupabaseError('Account was created, but profile details could not be saved.', $profileRes);
        return;
    }

    $loginAt = markSuccessfulLogin($userId, $email, 'signup');
    $session = createUserSession($userId, 'member');
    $freshUser = fetchAppUserWithProfileById($userId);
    $payloadUser = $freshUser ? buildUserPayload($freshUser, $loginAt) : null;

    jsonSuccess([
        'message' => 'Account created successfully.',
        'session' => $session,
        'user' => $payloadUser,
    ]);
}

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
        'select' => 'id,email,role,profiles(pet_name,parent_name,full_name,pet_type,breed,membership_applied,status)',
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
            'full_name' => $test['pet_name'],
            'pet_name' => $test['pet_name'],
            'parent_name' => $test['parent_name'],
            'pet_type' => $test['pet_type'],
            'breed' => $test['breed'],
            'membership_applied' => true,
            'status' => 'active',
        ], ['Prefer: return=minimal']);

        if (($profileRes['code'] ?? 500) >= 400) {
            sendSupabaseError("Failed to create test profile.", $profileRes);
            return true;
        }

        $res = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'eq.' . $userId,
            'select' => 'id,email,role,profiles(pet_name,parent_name,full_name,pet_type,breed,membership_applied,status)',
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

function handleLogin($data, $expectedRole = 'member')
{
    $emailRaw = trim((string) ($data['email'] ?? ''));
    $passwordRaw = (string) ($data['password'] ?? '');
    if ($expectedRole === 'member' && $passwordRaw === '' && handleTestUserLogin($emailRaw)) {
        return;
    }

    if (empty($data['email']) || empty($data['password'])) {
        jsonError("Credentials cannot be empty.", 400);
        return;
    }

    $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
    $password = $data['password'];
    $scope = $expectedRole === 'admin' ? 'admin' : 'member';

    checkLoginRateLimit($email, $scope);

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'email' => 'eq.' . $email,
        'select' => 'id,email,role,handle,is_verified,verified_at,password_hash,deactivated_at,profiles(pet_name,parent_name,full_name,pet_type,breed,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city)',
        'limit' => '1',
    ]);

    if ($res['code'] === 200 && !empty($res['data'])) {
        $user = $res['data'][0];

        if (empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {
            markFailedLogin($user['id'] ?? null, $email, 'invalid_password');
            recordFailedLoginRateLimit($email, $scope);
            jsonError("Invalid email or password.", 401);
            return;
        }

        // Correct credentials on a self-deactivated account reactivate it
        // (matches the common "deactivate is reversible by logging back in"
        // pattern) — distinct from admin bans/suspensions below, which stay
        // blocked regardless of credentials.
        if (!empty($user['deactivated_at'])) {
            supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $user['id']], ['deactivated_at' => null]);
            $user['deactivated_at'] = null;
        }

        $loginPunishments = getActiveUserPunishments($user['id'] ?? '');
        $ban = activePunishmentOfType($loginPunishments, ['ban']);
        if ($ban) {
            recordFailedLoginRateLimit($email, $scope);
            jsonError("This account has been banned. Contact support if you believe this is incorrect.", 403);
            return;
        }
        $suspension = activePunishmentOfType($loginPunishments, ['suspension']);
        if ($suspension) {
            recordFailedLoginRateLimit($email, $scope);
            jsonError("This account is currently suspended.", 403);
            return;
        }

        $loginAt = markSuccessfulLogin($user['id'], $email, 'public_login');
        clearLoginRateLimit($email, $scope);
        $session = createUserSession($user['id'], 'member');
        $payload = buildUserPayload($user, $loginAt);

        jsonSuccess([
            "message" => "Authentication successful.",
            "session" => $session,
            "user" => $payload,
        ]);
        return;
    }

    markFailedLogin(null, $email, 'unknown_email_or_role');
    recordFailedLoginRateLimit($email, $scope);
    jsonError("Invalid email or password.", 401);
}

function handleSessionMe($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $user = fetchAppUserWithProfileById($userId);
    if (!$user) {
        jsonError("User not found.", 404);
        return;
    }
    jsonSuccess(['user' => buildUserPayload($user)]);
}

function handleSetHandle($data)
{
    $userId = $data['auth_user_id'] ?? null;
    if (!$userId) {
        jsonError('Unauthorized', 401);
        return;
    }

    $handle = isset($data['handle']) ? strtolower(trim(strip_tags($data['handle']))) : '';
    if (!preg_match('/^[a-zA-Z0-9_]{5,20}$/', $handle)) {
        jsonError('Handle must be 5-20 characters long and contain only letters, numbers, and underscores.', 400);
        return;
    }

    $checkHandle = supabaseRequest('GET', '/rest/v1/users', ['handle' => 'eq.' . $handle, 'select' => 'id']);
    if (!empty($checkHandle['data']) && $checkHandle['data'][0]['id'] !== $userId) {
        jsonError('This handle is already taken.', 409);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['handle' => $handle], ['Prefer: return=minimal']);
    if (($res['code'] ?? 500) >= 400) {
        jsonError('Failed to update handle.', 500);
        return;
    }

    jsonSuccess(['handle' => $handle]);
}

function handleRequestPasswordReset($data)
{
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('A valid email address is required.', 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $email, 'select' => 'id,email', 'limit' => '1']);
    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to lookup user.", $res);
        return;
    }

    $user = $res['data'][0] ?? null;
    if (!$user) {
        jsonError('No account found with this email address.', 404);
        return;
    }

    $userId = $user['id'];
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    supabaseRequest('DELETE', '/rest/v1/password_reset_tokens', ['user_id' => 'eq.' . $userId]);
    $store = supabaseRequest('POST', '/rest/v1/password_reset_tokens', [], [
        'user_id' => $userId,
        'token_hash' => password_hash($code, PASSWORD_BCRYPT),
        'expires_at' => gmdate('c', time() + 900),
    ], ['Prefer: return=minimal']);

    if (($store['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] password reset token store failed | http=" . ($store['code'] ?? 'n/a'));
        jsonError("Could not process password reset request. Please try again later.", 500);
        return;
    }

    $subject = 'Your PawCircle password reset code: ' . $code;
    $text = "Hi,\n\nYou requested to reset your password. Your PawCircle password reset code is:\n\n"
        . "    $code\n\n"
        . "Enter this 6-digit code to choose a new password. The code expires in 15 minutes.\n\n"
        . "If you didn't request this, you can safely ignore this email.\n\n— The PawCircle Team";

    $digits = '';
    foreach (str_split($code) as $d) {
        $digits .= '<span style="display:inline-block;min-width:44px;margin:0 4px;padding:12px 0;font-size:30px;font-weight:700;letter-spacing:2px;color:#2B2420;background:#FFF3E0;border-radius:10px;font-family:monospace;">' . $d . '</span>';
    }
    $html = '<div style="font-family:Segoe UI,Helvetica,Arial,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1f2937;">'
        . '<h2 style="margin:0 0 8px;color:#e04848;">Reset your password</h2>'
        . '<p style="margin:0 0 18px;">Hi, enter the code below to complete your password reset:</p>'
        . '<div style="text-align:center;margin:20px 0;">' . $digits . '</div>'
        . '<p style="margin:0 0 6px;color:#6b7280;font-size:13px;">This code expires in 15 minutes.</p>'
        . '<p style="margin:0;color:#9ca3af;font-size:12px;">If you didn\'t request this, you can safely ignore this email.</p>'
        . '<p style="margin:18px 0 0;font-size:13px;">— The PawCircle Team</p>'
        . '</div>';

    $sent = sendEmailMessage($email, $subject, $text, $html);
    if (empty($sent['ok'])) {
        error_log("[pawcircle][" . requestId() . "] password reset email failed for $email");
        jsonError("We couldn't send the verification email. Please try again.", 502);
        return;
    }

    jsonSuccess(array_filter([
        'email' => $email,
        'expires_in' => 900,
        'message' => "We've sent a 6-digit password reset code to $email.",
        'debug_code' => (defined('PAWCIRCLE_DEBUG') && PAWCIRCLE_DEBUG) ? $code : null,
    ], fn($v) => $v !== null));
}

function handleVerifyPasswordResetCode($data)
{
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $code = preg_replace('/\D/', '', (string) ($data['code'] ?? ''));

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('A valid email address is required.', 400);
        return;
    }
    if (strlen($code) !== 6) {
        jsonError('Enter the 6-digit code from your email.', 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $email, 'select' => 'id,email', 'limit' => '1']);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        jsonError('User not found.', 404);
        return;
    }

    $userId = $res['data'][0]['id'];
    $tokenRes = supabaseRequest('GET', '/rest/v1/password_reset_tokens', [
        'user_id' => 'eq.' . $userId,
        'used_at' => 'is.null',
        'select' => 'id,token_hash,expires_at',
        'order' => 'created_at.desc',
        'limit' => '1',
    ]);

    if (($tokenRes['code'] ?? 500) >= 400 || empty($tokenRes['data'])) {
        jsonError('No active password reset request found. Please request a new code.', 400);
        return;
    }

    $token = $tokenRes['data'][0];
    if (strtotime((string) $token['expires_at']) < time()) {
        jsonError('This code has expired. Please request a new one.', 410);
        return;
    }
    if (!password_verify($code, (string) $token['token_hash'])) {
        jsonError('Incorrect reset code.', 401);
        return;
    }

    jsonSuccess(['message' => 'Code verified successfully.']);
}

function handleResetPassword($data)
{
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $code = preg_replace('/\D/', '', (string) ($data['code'] ?? ''));
    $newPassword = (string) ($data['new_password'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('A valid email address is required.', 400);
        return;
    }
    if (strlen($code) !== 6) {
        jsonError('Enter the 6-digit code from your email.', 400);
        return;
    }
    if (strlen($newPassword) < 10) {
        jsonError('New password must be at least 10 characters.', 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $email, 'select' => 'id,email', 'limit' => '1']);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        jsonError('User not found.', 404);
        return;
    }

    $userId = $res['data'][0]['id'];
    $tokenRes = supabaseRequest('GET', '/rest/v1/password_reset_tokens', [
        'user_id' => 'eq.' . $userId,
        'used_at' => 'is.null',
        'select' => 'id,token_hash,expires_at',
        'order' => 'created_at.desc',
        'limit' => '1',
    ]);

    if (($tokenRes['code'] ?? 500) >= 400 || empty($tokenRes['data'])) {
        jsonError('No active password reset request found. Please request a new code.', 400);
        return;
    }

    $token = $tokenRes['data'][0];
    if (strtotime((string) $token['expires_at']) < time()) {
        jsonError('This code has expired. Please request a new one.', 410);
        return;
    }
    if (!password_verify($code, (string) $token['token_hash'])) {
        jsonError('Incorrect reset code.', 401);
        return;
    }

    $passwordHash = password_hash($newPassword, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);

    $updateUser = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['password_hash' => $passwordHash], ['Prefer: return=minimal']);
    if (($updateUser['code'] ?? 500) >= 400) {
        jsonError('Failed to update password.', 500);
        return;
    }

    supabaseRequest('PATCH', '/rest/v1/password_reset_tokens', ['id' => 'eq.' . $token['id']], ['used_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    // A password reset should not leave other sessions logged in as the old credentials.
    supabaseRequest('PATCH', '/rest/v1/user_sessions', ['user_id' => 'eq.' . $userId, 'revoked_at' => 'is.null'], ['revoked_at' => nowIsoUtc()], ['Prefer: return=minimal']);

    jsonSuccess(['message' => 'Password reset successfully. Please sign in with your new password.']);
}

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

function sendEmailMessage($to, $subject, $message, $html = null)
{
    $to = trim((string) $to);
    $subject = trim((string) $subject) ?: 'PawCircle notification';

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'mocked' => false, 'message_id' => null, 'detail' => 'A valid recipient email is required.'];
    }

    $clientId = trim((string) envValue('SENDPULSE_CLIENT_ID', ''));
    $clientSecret = trim((string) envValue('SENDPULSE_CLIENT_SECRET', ''));
    $fromEmail = envValue('PAWCIRCLE_FROM_EMAIL', '');
    $fromName = envValue('PAWCIRCLE_FROM_NAME', 'PawCircle');

    if ($clientId === '' || $clientSecret === '') {
        error_log("[pawcircle][" . requestId() . "] [Email MOCK] to $to | $subject: " . str_replace("\n", ' / ', (string) $message));
        return ['ok' => true, 'mocked' => true, 'message_id' => null, 'detail' => null];
    }

    $token = sendpulseAccessToken($clientId, $clientSecret);
    if ($token === null) {
        return ['ok' => false, 'mocked' => false, 'message_id' => null, 'detail' => 'Could not authenticate with SendPulse.'];
    }

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
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    $body = json_decode($response, true);
    $resultOk = is_array($body) ? ($body['result'] ?? null) : null;
    if ($curlErr || $httpCode >= 300 || $resultOk === false) {
        $detail = is_array($body) ? ($body['message'] ?? ($body['error_message'] ?? json_encode($body))) : substr((string) $response, 0, 500);
        error_log("[pawcircle][" . requestId() . "] [Email ERROR] http=$httpCode err=$curlErr detail=$detail");
        return ['ok' => false, 'mocked' => false, 'message_id' => null, 'detail' => $detail];
    }

    return ['ok' => true, 'mocked' => false, 'message_id' => $body['id'] ?? null, 'detail' => null];
}
