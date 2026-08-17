<?php
/**
 * Account settings: overview/security bundle, credential changes, sign-out-
 * other-devices, privacy toggles, blocking, and account deactivation/
 * permanent deletion. Scoped to real, working features only — see the
 * step-13g plan for what was deliberately not ported from eSamaj's 10-section
 * Settings tab (Community/Family/Galleries/Posts/Archived sections either
 * don't map onto this app or duplicate existing tabs).
 *
 * Deletion note: almost every users-referencing table in this schema already
 * has ON DELETE CASCADE (confirmed via information_schema before writing
 * this), so a plain DELETE FROM users cascades correctly across friendships,
 * posts, groups membership, galleries, playdates, notifications, sessions,
 * etc. The few SET NULL / NO ACTION columns (events.created_by,
 * groups.created_by, group_messages.sender_id, users.verified_by,
 * verification_requests.reviewed_by) are handled explicitly below.
 */

function handleGetAccountSettings($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,role,is_verified,verified_at,handle,created_at,last_login_at,last_active_at',
        'limit' => '1',
    ]);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("Account not found.", 404);
        return;
    }
    $user = $userRes['data'][0];
    $profile = getAccountProfile($userId);

    $sessionsRes = supabaseRequest('GET', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $userId,
        'revoked_at' => 'is.null',
        'expires_at' => 'gt.' . nowIsoUtc(),
        'select' => 'id,user_agent,created_at,last_seen_at',
        'order' => 'last_seen_at.desc.nullslast',
    ]);
    $sessions = supabaseFailed($sessionsRes) ? [] : ($sessionsRes['data'] ?? []);

    jsonSuccess([
        'account' => $user,
        'profile' => $profile,
        'active_sessions' => $sessions,
        'current_session_id' => $data['auth_session_id'] ?? null,
    ]);
}

// Manual presence override (step 16 Part C1) — 'auto' (or unset) leaves
// presence purely activity-derived (derivePresenceStatus in core.php);
// any of online/away/busy/offline wins outright wherever presence is read.
function handleSetOnlineStatus($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $status = (string) ($data['online_status'] ?? '');
    if (!in_array($status, ['auto', 'online', 'away', 'busy', 'offline'], true)) {
        jsonError("online_status must be one of: auto, online, away, busy, offline.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], ['online_status' => $status], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update online status.", $res);
        return;
    }

    jsonSuccess(["online_status" => $status]);
}

// ---------------- Credential changes ----------------

function handleChangeAccountCredentials($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $currentPassword = (string) ($data['current_password'] ?? '');
    if ($currentPassword === '') {
        jsonError("Enter your current password to confirm this change.", 400);
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', ['id' => 'eq.' . $userId, 'select' => 'id,email,password_hash', 'limit' => '1']);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("Account not found.", 404);
        return;
    }
    $user = $userRes['data'][0];
    if (empty($user['password_hash']) || !password_verify($currentPassword, (string) $user['password_hash'])) {
        jsonError("Current password is incorrect.", 401);
        return;
    }

    $body = [];
    if (!empty($data['new_email'])) {
        $newEmail = filter_var($data['new_email'], FILTER_SANITIZE_EMAIL);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            jsonError("Enter a valid email address.", 400);
            return;
        }
        $existing = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $newEmail, 'select' => 'id', 'limit' => '1']);
        if (!supabaseFailed($existing) && !empty($existing['data']) && $existing['data'][0]['id'] !== $userId) {
            jsonError("That email is already in use.", 409);
            return;
        }
        $body['email'] = $newEmail;
    }
    if (!empty($data['new_password'])) {
        $newPassword = (string) $data['new_password'];
        if (strlen($newPassword) < 10) {
            jsonError("New password must be at least 10 characters.", 400);
            return;
        }
        $body['password_hash'] = password_hash($newPassword, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    if (empty($body)) {
        jsonError("Nothing to update.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], $body, ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update account credentials.", $res);
        return;
    }

    jsonSuccess(["message" => "Credentials updated."]);
}

function handleSignOutOtherDevices($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $currentSessionId = $data['auth_session_id'] ?? '';

    $res = supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $userId,
        'id' => 'neq.' . $currentSessionId,
        'revoked_at' => 'is.null',
    ], ['revoked_at' => nowIsoUtc()], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to sign out other devices.", $res);
        return;
    }

    jsonSuccess(["signed_out_count" => count($res['data'] ?? [])]);
}

// ---------------- Privacy ----------------

$GLOBALS['SETTINGS_PRIVACY_KEYS'] = ['hide_online_status', 'hide_phone', 'hide_email', 'hide_from_playdates'];

function handleGetPrivacySettings($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $res = supabaseRequest('GET', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId, 'select' => 'privacy_settings', 'limit' => '1']);
    if (supabaseFailed($res) || empty($res['data'])) {
        jsonError("Profile not found.", 404);
        return;
    }
    $settings = $res['data'][0]['privacy_settings'] ?? [];
    jsonSuccess(["privacy_settings" => is_array($settings) ? $settings : []]);
}

function handleSavePrivacySettings($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $existingRes = supabaseRequest('GET', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId, 'select' => 'privacy_settings', 'limit' => '1']);
    $existing = (!supabaseFailed($existingRes) && !empty($existingRes['data']) && is_array($existingRes['data'][0]['privacy_settings']))
        ? $existingRes['data'][0]['privacy_settings']
        : [];

    foreach ($GLOBALS['SETTINGS_PRIVACY_KEYS'] as $key) {
        if (array_key_exists($key, $data)) {
            $existing[$key] = !empty($data[$key]);
        }
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], ['privacy_settings' => $existing], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to save privacy settings.", $res);
        return;
    }

    jsonSuccess(["privacy_settings" => $existing]);
}

// ---------------- Blocking ----------------

function handleBlockUser($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $blockedId = requireUuid($data['blocked_id'] ?? '', 'blocked_id');
    if ($userId === $blockedId) {
        jsonError("You can't block yourself.", 400);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/user_blocks', ['on_conflict' => 'blocker_id,blocked_id'], [
        'blocker_id' => $userId,
        'blocked_id' => $blockedId,
    ], ['Prefer: resolution=ignore-duplicates,return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to block user.", $res);
        return;
    }

    supabaseRequest('DELETE', '/rest/v1/friendships', [
        'or' => '(and(requester.eq.' . $userId . ',addressee.eq.' . $blockedId . '),and(requester.eq.' . $blockedId . ',addressee.eq.' . $userId . '))',
    ]);

    jsonSuccess(["message" => "User blocked."]);
}

function handleUnblockUser($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $blockedId = requireUuid($data['blocked_id'] ?? '', 'blocked_id');

    $res = supabaseRequest('DELETE', '/rest/v1/user_blocks', [
        'blocker_id' => 'eq.' . $userId,
        'blocked_id' => 'eq.' . $blockedId,
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to unblock user.", $res);
        return;
    }

    jsonSuccess(["message" => "User unblocked."]);
}

function handleGetBlockedUsers($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $res = supabaseRequest('GET', '/rest/v1/user_blocks', [
        'blocker_id' => 'eq.' . $userId,
        'select' => 'blocked_id,created_at',
        'order' => 'created_at.desc',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch blocked users.", $res);
        return;
    }

    $rows = $res['data'] ?? [];
    $profileMap = fetchProfilesMap(array_column($rows, 'blocked_id'));
    $blocked = [];
    foreach ($rows as $row) {
        $p = $profileMap[$row['blocked_id']] ?? null;
        $blocked[] = [
            'user_id' => $row['blocked_id'],
            'name' => $p['pet_name'] ?? 'Member',
            'pet_type' => $p['pet_type'] ?? null,
            'breed' => $p['breed'] ?? null,
            'profile_photo_url' => $p['profile_photo_url'] ?? null,
            'blocked_at' => $row['created_at'],
        ];
    }

    jsonSuccess(["blocked" => $blocked]);
}

// ---------------- Danger zone ----------------

function verifyCurrentPasswordOrFail($userId, $password)
{
    $userRes = supabaseRequest('GET', '/rest/v1/users', ['id' => 'eq.' . $userId, 'select' => 'id,password_hash', 'limit' => '1']);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("Account not found.", 404);
        return false;
    }
    $hash = (string) ($userRes['data'][0]['password_hash'] ?? '');
    if (empty($hash) || !password_verify((string) $password, $hash)) {
        jsonError("Password is incorrect.", 401);
        return false;
    }
    return true;
}

function handleDeactivateAccount($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    if (!verifyCurrentPasswordOrFail($userId, $data['password'] ?? ''))
        return;

    $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['deactivated_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to deactivate account.", $res);
        return;
    }
    supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], ['online_status' => 'offline'], ['Prefer: return=minimal']);
    supabaseRequest('PATCH', '/rest/v1/user_sessions', ['user_id' => 'eq.' . $userId, 'revoked_at' => 'is.null'], ['revoked_at' => nowIsoUtc()], ['Prefer: return=minimal']);

    jsonSuccess(["message" => "Account deactivated. Log back in any time to reactivate it."]);
}

function handleDeleteAccountPermanently($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    if (($data['confirm_text'] ?? '') !== 'DELETE') {
        jsonError('Type DELETE to confirm permanent account deletion.', 400);
        return;
    }
    if (!verifyCurrentPasswordOrFail($userId, $data['password'] ?? ''))
        return;

    $profileRes = supabaseRequest('GET', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId, 'select' => 'profile_photo_url,cover_photo_url', 'limit' => '1']);
    $profile = (!supabaseFailed($profileRes) && !empty($profileRes['data'])) ? $profileRes['data'][0] : [];
    foreach (['profile_photo_url', 'cover_photo_url'] as $field) {
        $parsed = parsePublicStorageUrl($profile[$field] ?? '');
        if ($parsed)
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
    }

    // A few users(id) references use SET NULL / NO ACTION rather than
    // CASCADE (checked live via information_schema before writing this) —
    // null those out explicitly so the delete below can't fail on a stray
    // NO ACTION constraint (users.verified_by, verification_requests.reviewed_by).
    supabaseRequest('PATCH', '/rest/v1/users', ['verified_by' => 'eq.' . $userId], ['verified_by' => null], ['Prefer: return=minimal']);
    supabaseRequest('PATCH', '/rest/v1/verification_requests', ['reviewed_by' => 'eq.' . $userId], ['reviewed_by' => null], ['Prefer: return=minimal']);

    $res = supabaseRequest('DELETE', '/rest/v1/users', ['id' => 'eq.' . $userId], null, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to delete account.", $res);
        return;
    }

    jsonSuccess(["message" => "Account permanently deleted."]);
}
