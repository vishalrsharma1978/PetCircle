<?php

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

function setPawCircleCookie($name, $value, $expires, $httpOnly)
{
    $options = [
        'expires' => $expires,
        'path' => '/',
        'secure' => isHttpsRequest(),
        'httponly' => $httpOnly,
        'samesite' => 'Lax',
        'domain' => '',
    ];

    if (headers_sent()) {
        error_log('[pawcircle] Attempted to set cookie after headers were sent: ' . $name);
        return;
    }

    setcookie($name, $value, $options);
}

function setSessionCookies($rawToken, $csrfToken, $expiresAtTs)
{
    setPawCircleCookie(PAWCIRCLE_SESSION_COOKIE, $rawToken, $expiresAtTs, true);
    setPawCircleCookie(PAWCIRCLE_CSRF_COOKIE, $csrfToken, $expiresAtTs, false);
}

function clearSessionCookies()
{
    setPawCircleCookie(PAWCIRCLE_SESSION_COOKIE, '', time() - 3600, true);
    setPawCircleCookie(PAWCIRCLE_CSRF_COOKIE, '', time() - 3600, false);
    unset($_COOKIE[PAWCIRCLE_SESSION_COOKIE], $_COOKIE[PAWCIRCLE_CSRF_COOKIE]);
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

    $code = $res['code'] ?? 500;
    if ($code >= 500) {
        jsonError("Failed to verify session due to a server error. Please try again.", 502);
        exit();
    }

    if ($code >= 400 || empty($res['data'])) {
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

    $tokenFromCookie = !empty($_COOKIE[PAWCIRCLE_SESSION_COOKIE]);

    if ($requireCsrf && $tokenFromCookie) {
        $csrf = getCsrfTokenHeader();
        if ($csrf === '' || !hash_equals((string) ($session['csrf_hash'] ?? ''), hashSessionSecret($csrf))) {
            jsonError("Invalid security token. Refresh the page and try again.", 403);
            exit();
        }
    }

    supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'id' => 'eq.' . $session['id'],
    ], [
        'last_seen_at' => nowIsoUtc(),
        'expires_at' => gmdate('c', time() + PAWCIRCLE_SESSION_TTL_SECONDS)
    ], ['Prefer: return=minimal']);

    return [
        'session_id' => $session['id'],
        'user_id' => $session['user_id'],
        'role' => $session['role'] ?? 'member',
        'admin_mode_until' => $session['admin_mode_until'] ?? null,
        'admin_mode_active' => !empty($session['admin_mode_until']) && strtotime((string) $session['admin_mode_until']) > time(),
    ];
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
