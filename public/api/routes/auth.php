<?php

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
    applyCurlTlsOptions($ch);

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
    static $ch = null;
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

    if ($ch === null) {
        $ch = curl_init();
    } else {
        curl_reset($ch);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '/auth/v1/user',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . $anonKey,
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 10,
    ]);
    applyCurlTlsOptions($ch);

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

function ensureProfileForAppUser($appUserId, $authUser, $data = [], $existingProfile = null)
{
    if (!isValidUuid($appUserId)) {
        return false;
    }

    $displayName = trim((string) ($data['name'] ?? authMetadataValue($authUser, ['full_name', 'name'], '')));
    if ($displayName === '') {
        $displayName = split_part_fallback($authUser['email'] ?? '', '@', 0, 'New Member');
    }

    $pet_type = trim((string) ($data['pet_type'] ?? authMetadataValue($authUser, ['pet_type'], '')));
    $breed = trim((string) ($data['breed'] ?? authMetadataValue($authUser, ['breed'], '')));
    $avatarUrl = trim((string) authMetadataValue($authUser, ['avatar_url', 'picture'], ''));

    if ($existingProfile !== null) {
        $patch = [];
        if ($displayName !== '' && empty($existingProfile['full_name']) && empty($existingProfile['pet_name']))
            $patch['full_name'] = $displayName;
        if ($avatarUrl !== '' && empty($existingProfile['profile_photo_url']))
            $patch['profile_photo_url'] = $avatarUrl;
        if ($pet_type !== '' && empty($existingProfile['pet_type']))
            $patch['pet_type'] = $pet_type;
        if ($breed !== '' && empty($existingProfile['breed']))
            $patch['breed'] = $breed;
        
        if (!empty($patch)) {
            supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . strtolower($appUserId)], $patch, ['Prefer: return=minimal']);
            return true;
        }
        return false;
    }

    $insert = [
        'user_id' => $appUserId,
        'full_name' => $displayName,
        'terms_accepted' => false,
        'privacy_accepted' => false,
        'accuracy_certified' => false,
    ];
    if ($pet_type !== '') $insert['pet_type'] = $pet_type;
    if ($breed !== '') $insert['breed'] = $breed;
    if ($avatarUrl !== '') $insert['profile_photo_url'] = $avatarUrl;

    supabaseRequest('POST', '/rest/v1/profiles', [], $insert, ['Prefer: resolution=ignore-duplicates,return=minimal']);
    return true;
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
        $profileList = $existing['profiles'] ?? [];
        $existingProfile = is_array($profileList) && isset($profileList[0]) ? $profileList[0] : (is_array($profileList) && !empty($profileList) ? $profileList : []);
        $patched = ensureProfileForAppUser($existing['id'], $authUser, $data, $existingProfile);
        if ($patched) {
            return fetchAppUserWithProfileById($existing['id']) ?: $existing;
        }
        return $existing;
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

        $fullUser = fetchAppUserWithProfileById($row['id']);
        $profileList = $fullUser['profiles'] ?? [];
        $existingProfile = is_array($profileList) && isset($profileList[0]) ? $profileList[0] : (is_array($profileList) && !empty($profileList) ? $profileList : []);
        $patched = ensureProfileForAppUser($row['id'], $authUser, $data, $existingProfile);
        if ($patched) {
            return fetchAppUserWithProfileById($row['id']) ?: $fullUser;
        }
        return $fullUser;
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

    $session = createUserSession($user['id'], 'member');
    $loginAt = nowIsoUtc();
    $payload = buildUserPayload($user, $loginAt);
    $payload['auth_user_id'] = $authUser['id'];

    jsonSuccess([
        'message' => 'Supabase Auth session linked successfully.',
        'session' => $session,
        'user' => $payload,
    ]);

    finishResponseEarly();

    markSuccessfulLogin($user['id'], $user['email'] ?? ($authUser['email'] ?? ''), 'supabase_auth');
    clearLoginRateLimit($user['email'] ?? ($authUser['email'] ?? ''), 'member');
}

function handleSupabaseAuthLogin($data)
{
    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $data['password'] ?? '';
    
    if (!$email || !$password) {
        jsonError("Credentials cannot be empty.", 400);
        return;
    }

    $authRes = supabaseRequest('POST', '/auth/v1/token?grant_type=password', [], [
        'email' => $email,
        'password' => $password,
    ]);

    if (($authRes['code'] ?? 500) !== 200 || empty($authRes['data']['access_token'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => $authRes['data']['error_description'] ?? "Invalid email or password."]);
        return;
    }

    $authUser = $authRes['data']['user'];
    if (!$authUser || empty($authUser['id'])) {
        jsonError('Invalid Supabase Auth session. Please sign in again.', 401);
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

    $session = createUserSession($user['id'], 'member');
    $loginAt = nowIsoUtc();
    $payload = buildUserPayload($user, $loginAt);
    $payload['auth_user_id'] = $authUser['id'];

    jsonSuccess([
        'message' => 'Supabase Auth login successful.',
        'session' => $session,
        'user' => $payload,
    ]);

    finishResponseEarly();

    markSuccessfulLogin($user['id'], $user['email'] ?? ($authUser['email'] ?? ''), 'supabase_auth');
    clearLoginRateLimit($user['email'] ?? ($authUser['email'] ?? ''), 'member');
}

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

function setPetCircleCookie($name, $value, $expires, $httpOnly)
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
    setPetCircleCookie(PAWCIRCLE_SESSION_COOKIE, $rawToken, $expiresAtTs, true);
    setPetCircleCookie(PAWCIRCLE_CSRF_COOKIE, $csrfToken, $expiresAtTs, false);
}

function clearSessionCookies()
{
    setPetCircleCookie(PAWCIRCLE_SESSION_COOKIE, '', time() - 3600, true);
    setPetCircleCookie(PAWCIRCLE_CSRF_COOKIE, '', time() - 3600, false);
}

function getRawSessionToken()
{
    return trim((string) ($_COOKIE[PAWCIRCLE_SESSION_COOKIE] ?? getBearerToken()));
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
        'pet_type admin',
        'breed admin',
        'platform administrator',
        'pet_type administrator',
        'breed administrator',
    ];
    if (in_array($normalized, $reserved, true))
        return true;

    return preg_match('/^(owner|admin|platform\s+admin|pet_type\s+admin|breed\s+admin)\b/i', $normalized) === 1;
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
    return normaliseProfileTagsInput($profile['tags'] ?? []);
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

function finalizeSignup($payload, $plainPassword = '')
{
    $name = (string) ($payload['name'] ?? '');
    $email = filter_var((string) ($payload['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $passwordHash = (string) ($payload['password_hash'] ?? '');
    $breed = (string) ($payload['breed'] ?? 'Not Specified');
    $pet_type = (string) ($payload['pet_type'] ?? '');
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
        'pet_type' => $pet_type,
        'breed' => $breed,
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
        'pet_type' => $pet_type,
        'breed' => $breed,
    ]);

    if (!$user || empty($user['id'])) {
        return;
    }

    $userId = $user['id'];

    $profilePatch = [
        'full_name' => $name,
        'breed' => $breed,
        'pet_type' => $pet_type,
        // 'primary_interests' => empty($interestsArr) ? null : $interestsArr,
        'skills' => empty($skillsArr) ? null : $skillsArr,
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
            . "Park timings and breed updates here.";
        $r = sendWhatsAppMessage($phone, $welcome, proactiveWhatsappOpts($welcome));
        if (empty($r['ok'])) {
            error_log("[pawcircle][" . requestId() . "] signup welcome WhatsApp not sent for $email");
        }
    }
}

function handleSignup($data) {
        if (empty($data['pet_name']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Pet name, email, and password are required."]);
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

        if (strlen($password) < 10) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Password must be at least 10 characters."]);
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
            'breed' => $test['breed'],
            'pet_type' => $test['pet_type'],
            'membership_applied' => true,
            'status' => 'active',
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
        'select' => 'id,email,role,password_hash,profiles(pet_name,parent_name,pet_type,breed,date_of_birth,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number,gender,bio,current_city)',
        'limit' => '1',
    ]);

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

    $action = $data['action'] ?? $_GET['action'] ?? '';
    if (!$action) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Missing or invalid action."]);
        exit();
    }
    markFailedLogin(null, $email, 'unknown_email_or_role');
    recordFailedLoginRateLimit($email, $scope);
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
}

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

    $passwordCorrect = false;
    if (!empty($user['password_hash'])) {
        $passwordCorrect = password_verify($password, $user['password_hash']);
    } else {
        $authRes = supabaseRequest('POST', '/auth/v1/token?grant_type=password', [], [
            'email' => $user['email'],
            'password' => $password,
        ]);
        $passwordCorrect = (($authRes['code'] ?? 500) === 200);
    }

    if (!$passwordCorrect) {
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
    // if (isset($data['tags']) && !isset($data['primary_interests'])) {
        // $data['primary_interests'] = $data['tags'];
    // }

    if (isset($data['skills'])) {
        $update['skills'] = is_array($data['skills'])
            ? array_values(array_filter(array_map(fn($v) => normaliseProfileTagValue($v, 40), $data['skills'])))
            : array_values(array_filter(array_map(fn($v) => normaliseProfileTagValue($v, 40), explode(',', (string) $data['skills']))));
    }

    // if (isset($data['primary_interests'])) {
        // $update['primary_interests'] = normaliseProfileTagsInput($data['primary_interests']);
    // }

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
            . "Welcome to the breed! You'll now receive important updates and reminders here.";
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

function fetchProfilesMap($userIds)
{
    $userIds = normalizeUuidList($userIds);
    if (empty($userIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'in.(' . implode(',', $userIds) . ')',
        'select' => 'user_id,full_name,profile_photo_url,breed,pet_type,current_city,mobile_number,date_of_birth,gender,occupation'
    ]);

    if (supabaseFailed($res))
        return [];

    $adminCapsMap = fetchAdminCapabilitiesMap($userIds);

    $map = [];
    foreach (($res['data'] ?? []) as $profile) {
        if (!empty($profile['user_id'])) {
            $uid = strtolower((string) $profile['user_id']);
            $profile['admin_capabilities'] = $adminCapsMap[$uid] ?? [];
            $profile['breed'] = $profile['breed'] ?? '';
            $profile['pet_type'] = $profile['pet_type'] ?? '';
            $map[$profile['user_id']] = $profile;
        }
    }
    return $map;
}

function profileAgeGroup($profile)
{
    $existing = trim((string) ($profile['age_group'] ?? ''));
    if ($existing !== '')
        return $existing;
    return ageGroupFromAge(ageFromDateOfBirth($profile['date_of_birth'] ?? null));
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
        'breed' => $profile['breed'] ?? null,
        'pet_type' => $profile['pet_type'] ?? null,
        'current_city' => $profile['current_city'] ?? null,
        'mobile_number' => $profile['mobile_number'] ?? null,
        'date_of_birth' => $profile['date_of_birth'] ?? null,
        'gender' => $profile['gender'] ?? null,
        'occupation' => $profile['occupation'] ?? null,
        // 'primary_interests' => $customTags,
        'custom_tags' => $customTags,
        'system_tags' => $systemTags,
        'tags' => $displayTags,
        'admin_capabilities' => $adminCaps,
    ];
}

function getAccountProfile($userId)
{
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id,full_name,breed,pet_type,current_city,mobile_number,date_of_birth,gender,occupation,bio,profile_photo_url,cover_photo_url,is_public,visibility,online_status,social_links,membership_applied,status',
        'limit' => '1',
    ]);

    if (!supabaseFailed($res) && !empty($res['data'])) {
        $profile = $res['data'][0];
        $profile['breed'] = $profile['breed'] ?? '';
        $profile['pet_type'] = $profile['pet_type'] ?? '';
        return $profile;
    }

    $fallback = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id,full_name,breed,pet_type,current_city,mobile_number,date_of_birth,gender,occupation,bio,profile_photo_url,cover_photo_url,is_public,membership_applied,status,primary_interests',
        'limit' => '1',
    ]);

    if (supabaseFailed($fallback) || empty($fallback['data'])) {
        return [];
    }

    $profile = $fallback['data'][0];
    $profile['breed'] = $profile['breed'] ?? '';
    $profile['pet_type'] = $profile['pet_type'] ?? '';
    return $profile;
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
        . "You'll receive event reminders, Park timings and breed updates here.";
    sendWhatsAppMessage($number, $confirm, proactiveWhatsappOpts($confirm));
}