<?php

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

function normaliseAdminRole($role)
{
    $role = strtolower(trim((string) $role));
    $aliases = [
        'supreme_overlord_admin' => 'owner',
        'super_admin' => 'owner',
        'platform_admin' => 'platform_admin',
        'pet_type_leader' => 'pet_type_admin',
        'pet_type_admin' => 'pet_type_admin',
        'breed_manager' => 'breed_admin',
        'breed_admin' => 'breed_admin',
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
        'pet_type_admin' => 'Pet Type admin',
        'breed_admin' => 'Breed admin',
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
    if (!in_array($scopeType, ['global', 'pet_type', 'breed'], true)) {
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

function userHasAdminCapability($userId)
{
    return !empty(fetchAdminCapabilities($userId));
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
            'select' => 'user_id,full_name,pet_type,breed',
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
        $row['pet_type'] = $profile['pet_type'] ?? '';
        $row['breed'] = $profile['breed'] ?? '';
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
    if (!in_array($role, ['owner', 'platform_admin', 'pet_type_admin', 'breed_admin'], true)) {
        jsonError("Choose a valid admin role.", 400);
        return;
    }

    $scopeType = strtolower(cleanPlainValue($data['scope_type'] ?? 'global', 40));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 160);
    if ($role === 'owner' || $role === 'platform_admin') {
        $scopeType = 'global';
        $scopeValue = '*';
    } elseif ($role === 'pet_type_admin') {
        $scopeType = 'pet_type';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Pet Type admins require a pet_type scope.", 400);
            return;
        }
    } elseif ($role === 'breed_admin') {
        $scopeType = 'breed';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Breed admins require a breed scope.", 400);
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
    if (!in_array($role, ['owner', 'platform_admin', 'pet_type_admin', 'breed_admin'], true)) {
        jsonError("Choose a valid admin role.", 400);
        return;
    }

    $scopeType = strtolower(cleanPlainValue($data['scope_type'] ?? 'global', 40));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 160);
    if ($role === 'owner' || $role === 'platform_admin') {
        $scopeType = 'global';
        $scopeValue = '*';
    } elseif ($role === 'pet_type_admin') {
        $scopeType = 'pet_type';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Pet Type admins require a pet_type scope.", 400);
            return;
        }
    } elseif ($role === 'breed_admin') {
        $scopeType = 'breed';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Breed admins require a breed scope.", 400);
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
        'select' => 'id,email,role,created_at,last_login_at,last_active_at,deactivated_at,profiles(full_name,pet_type,breed,status,visibility,online_status,profile_photo_url,cover_photo_url,mobile_number,current_city,occupation,date_of_birth)',
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
    if (!in_array($role, ['owner', 'platform_admin', 'pet_type_admin', 'breed_admin'], true)) {
        jsonError("Choose a valid admin role.", 400);
        return;
    }

    $scopeType = strtolower(cleanPlainValue($data['scope_type'] ?? 'global', 40));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 160);
    if ($role === 'owner' || $role === 'platform_admin') {
        $scopeType = 'global';
        $scopeValue = '*';
    } elseif ($role === 'pet_type_admin') {
        $scopeType = 'pet_type';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Pet Type admins require a pet_type scope.", 400);
            return;
        }
    } elseif ($role === 'breed_admin') {
        $scopeType = 'breed';
        if ($scopeValue === '' || $scopeValue === '*') {
            jsonError("Breed admins require a breed scope.", 400);
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
    $phpSorts = ['name_asc', 'name_desc', 'pet_type_asc', 'pet_type_desc', 'breed_asc', 'breed_desc', 'status_asc', 'status_desc', 'active_asc'];
    $pet_typeFilters = adminChoiceList($data['pet_type'] ?? [], ['Dog', 'Cat', 'Bird', 'Rabbit', 'Reptile', 'Fish', 'Other']);
    $breedFilter = cleanPlainValue($data['breed'] ?? '', 120);
    $statusFilters = array_map('strtolower', adminChoiceList($data['status_filter'] ?? [], ['active', 'online', 'offline', 'deactivated']));
    $needsPhpFilter = !empty($pet_typeFilters) || $breedFilter !== '' || !empty($statusFilters);
    $needsPhpSort = in_array($sort, $phpSorts, true);
    $needsPhpPage = $needsPhpSort || $needsPhpFilter;
    $flagFilters = array_map('strtolower', adminChoiceList($data['flag_filter'] ?? [], ['flagged', 'watch', 'warning', 'blacklist', 'suspension', 'ban']));
    $query = [
        'select' => 'id,email,role,created_at,last_login_at,last_active_at,deactivated_at,profiles(full_name,pet_type,breed,status,visibility,online_status,profile_photo_url,mobile_number)',
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
        $users = array_values(array_filter($users, function ($row) use ($pet_typeFilters, $breedFilter, $statusFilters) {
            $profile = $row['profile'] ?? [];
            if (!empty($pet_typeFilters) && !in_array(strtolower((string) ($profile['pet_type'] ?? '')), array_map('strtolower', $pet_typeFilters), true)) {
                return false;
            }
            if ($breedFilter !== '' && stripos((string) ($profile['breed'] ?? ''), $breedFilter) === false) {
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
                'pet_type' => $profileA['pet_type'] ?? '',
                'breed' => $profileA['breed'] ?? '',
                'status' => $a['deactivated_at'] ? 'deactivated' : ($profileA['status'] ?? ''),
                'active' => $a['last_active_at'] ?? '',
                default => '',
            };
            $valueB = match ($field) {
                'name' => $profileB['full_name'] ?? $b['email'] ?? '',
                'pet_type' => $profileB['pet_type'] ?? '',
                'breed' => $profileB['breed'] ?? '',
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
        'select' => 'id,user_id,content,media_url,post_type,breed,pet_type,is_deleted,created_at,updated_at,title,description,hashtags',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];

    $sort = strtolower(cleanPlainValue($data['sort'] ?? 'created_desc', 40));
    $query['order'] = $sort === 'created_asc' ? 'created_at.asc' : 'created_at.desc';
    $type = cleanPlainValue($data['post_type'] ?? '', 40);
    if ($type !== '')
        $query['post_type'] = 'eq.' . $type;
    $pet_type = cleanPlainValue($data['pet_type'] ?? '', 80);
    if ($pet_type !== '')
        $query['pet_type'] = 'eq.' . $pet_type;
    $breed = cleanPlainValue($data['breed'] ?? '', 120);
    if ($breed !== '')
        $query['breed'] = 'eq.' . $breed;
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
        'select' => 'id,title,description,event_date,event_time,location,is_online,meeting_url,pet_type,breed,created_by,created_at,updated_at',
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
    $pet_type = cleanPlainValue($data['pet_type'] ?? '', 80);
    if ($pet_type !== '')
        $query['pet_type'] = 'eq.' . $pet_type;
    $breed = cleanPlainValue($data['breed'] ?? '', 120);
    if ($breed !== '')
        $query['breed'] = 'eq.' . $breed;
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

function handleAdminGetAnalytics($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $since = gmdate('c', time() - (7 * 24 * 60 * 60));
    $recentUsers = recentRows('users', 'id,email,created_at,last_login_at,last_active_at,role', 500);
    $recentPosts = recentRows('posts', 'id,user_id,post_type,pet_type,breed,is_deleted,created_at', 500);
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
        'posts_by_pet_type' => bucketCounts($recentPosts, 'pet_type'),
        'posts_by_breed' => bucketCounts($recentPosts, 'breed'),
        'generated_at' => nowIsoUtc(),
        'note' => 'Limited data demo. Counts are sampled through the current REST API. Grafana/Postgres metrics will be used for exact infrastructure analytics.',
    ]);
}

function handleAdminContactBook($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $breedFilter = cleanPlainValue($data['breed'] ?? '', 120);
    $search = trim((string) ($data['search'] ?? ''));

    $query = [
        'select' => 'full_name,breed,pet_type,current_city,mobile_number',
        'order' => 'breed.asc.nullslast,full_name.asc.nullslast',
        'limit' => '5000',
    ];
    if ($breedFilter !== '') {
        $query['breed'] = 'ilike.*' . str_replace(['%', '*', ',', '(', ')'], '', $breedFilter) . '*';
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
        $breed = trim((string) ($row['breed'] ?? '')) ?: 'Unspecified';
        $communities[$breed] = ($communities[$breed] ?? 0) + 1;
        $contacts[] = [
            'name' => $row['full_name'] ?? 'Member',
            'breed' => $breed,
            'address' => $row['current_city'] ?? '',
            'phone' => $row['mobile_number'] ?? '',
            'pet_type' => $row['pet_type'] ?? '',
        ];
    }
    ksort($communities);
    $breedList = [];
    foreach ($communities as $name => $count) {
        $breedList[] = ['breed' => $name, 'count' => $count];
    }

    jsonSuccess([
        'contacts' => $contacts,
        'communities' => $breedList,
        'total' => count($contacts),
    ]);
}

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

    // Breed distribution (member profiles only via inner join)
    $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
        'select' => 'breed,users!inner(role)',
        'users.role' => 'eq.member',
    ]);

    $commCount = [];
    foreach (($profilesRes['data'] ?? []) as $p) {
        $c = $p['breed'] ?? 'Not Specified';
        $commCount[$c] = ($commCount[$c] ?? 0) + 1;
    }

    $communities = [];
    foreach ($commCount as $name => $count) {
        $communities[] = ['breed' => $name, 'count' => $count];
    }
    usort($communities, fn($a, $b) => $b['count'] - $a['count']);

    return ['totalUsers' => $totalUsers, 'communities' => $communities];
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

function handleGetServers($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $res = supabaseRequest('GET', '/rest/v1/servers', [
        'select' => 'id,name,host,port,latitude,longitude,pet_type,status,latency_ms,created_at',
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
            ['name' => 'US-West (Oregon)', 'host' => '54.212.10.45', 'port' => 443, 'latitude' => 45.823, 'longitude' => -120.312, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 45],
            ['name' => 'US-East (Virginia)', 'host' => '3.210.45.18', 'port' => 443, 'latitude' => 39.043, 'longitude' => -77.487, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 82],
            ['name' => 'EU-Central (Frankfurt)', 'host' => '18.197.80.3', 'port' => 443, 'latitude' => 50.110, 'longitude' => 8.682, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 140],
            ['name' => 'AP-South (Mumbai)', 'host' => '13.233.102.5', 'port' => 443, 'latitude' => 19.076, 'longitude' => 72.877, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 15],
            ['name' => 'AP-Northeast (Tokyo)', 'host' => '54.250.8.19', 'port' => 443, 'latitude' => 35.676, 'longitude' => 139.650, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 115]
        ];

        foreach ($defaultNodes as $node) {
            $node['created_at'] = nowIsoUtc();
            supabaseRequest('POST', '/rest/v1/servers', [], $node);
        }

        // Re-fetch seeded servers
        $res = supabaseRequest('GET', '/rest/v1/servers', [
            'select' => 'id,name,host,port,latitude,longitude,pet_type,status,latency_ms,created_at',
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
    $pet_type = trim((string) ($data['pet_type'] ?? 'global'));
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
        if ($pet_type !== '')
            $payload['pet_type'] = $pet_type;
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
        'pet_type' => $pet_type,
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