<?php
/**
 * Admin dashboard — Users and Roles panels. Ported from eSamaj's
 * admin_users.php, with `religion`/`community` filter columns replaced by
 * `pet_type`/`breed` (the fields PetCircle's `profiles` table actually has),
 * and role grants using this rebuild's own admin_roles vocabulary
 * (owner | platform_admin | pet_type_admin | breed_admin; scope_type:
 * global | pet_type | breed — see the CHECK constraints on admin_roles)
 * instead of eSamaj's religion_admin/community_admin tiers.
 */

function adminModerationNoteTypes()
{
    return ['general', 'watch', 'warning', 'blacklist', 'suspension', 'ban'];
}

function adminModerationFlagTypes()
{
    // Which note types also create a time-bounded admin_user_actions flag.
    return ['watch', 'warning', 'blacklist', 'suspension', 'ban'];
}

function handleAdminListUsers($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data, 25, 100);
    $offset = adminOffset($data);

    $query = [
        'select' => 'id,email,role,is_verified,deactivated_at,created_at,last_login_at,last_active_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    $roleFilter = cleanPlainValue($data['role_filter'] ?? '', 20);
    if (in_array($roleFilter, ['member', 'admin'], true)) {
        $query['role'] = 'eq.' . $roleFilter;
    }
    $statusFilter = cleanPlainValue($data['status_filter'] ?? '', 20);
    if ($statusFilter === 'active') {
        $query['deactivated_at'] = 'is.null';
    } elseif ($statusFilter === 'deactivated') {
        $query['deactivated_at'] = 'not.is.null';
    }
    $search = cleanPlainValue($data['search'] ?? '', 120);
    if ($search !== '') {
        $query['email'] = 'ilike.*' . $search . '*';
    }

    $res = supabaseRequest('GET', '/rest/v1/users', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to list users.", $res);
        return;
    }

    $users = $res['data'] ?? [];
    $userIds = array_column($users, 'id');
    $profileMap = fetchProfilesMap($userIds);

    $petTypeFilter = cleanPlainValue($data['pet_type'] ?? '', 40);
    if ($petTypeFilter !== '') {
        $users = array_values(array_filter($users, function ($u) use ($profileMap, $petTypeFilter) {
            return ($profileMap[$u['id']]['pet_type'] ?? '') === $petTypeFilter;
        }));
    }

    $activeFlags = [];
    if (!empty($userIds)) {
        $flagsRes = supabaseRequest('GET', '/rest/v1/admin_user_actions', [
            'user_id' => 'in.(' . implode(',', $userIds) . ')',
            'is_active' => 'eq.true',
            'select' => 'user_id,action_type,ends_at',
        ]);
        foreach (($flagsRes['data'] ?? []) as $row) {
            if (!empty($row['ends_at']) && strtotime($row['ends_at']) < time())
                continue;
            $activeFlags[$row['user_id']][] = $row['action_type'];
        }
    }

    foreach ($users as &$u) {
        $u['profile'] = $profileMap[$u['id']] ?? null;
        $u['active_flags'] = $activeFlags[$u['id']] ?? [];
    }
    unset($u);

    jsonSuccess(['users' => $users, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminGetUserDetail($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $userRes = supabaseRequest('GET', '/rest/v1/users', ['id' => 'eq.' . $userId, 'select' => 'id,email,role,is_verified,verified_at,deactivated_at,created_at,last_login_at,last_active_at,handle', 'limit' => '1']);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("User not found.", 404);
        return;
    }
    $user = $userRes['data'][0];
    $user['profile'] = getAccountProfile($userId);

    $rolesRes = supabaseRequest('GET', '/rest/v1/admin_roles', ['user_id' => 'eq.' . $userId, 'revoked_at' => 'is.null', 'select' => 'id,role,scope_type,scope_value,created_at']);
    $user['admin_roles'] = $rolesRes['data'] ?? [];

    $notesRes = supabaseRequest('GET', '/rest/v1/admin_user_notes', ['user_id' => 'eq.' . $userId, 'select' => 'id,note_type,note,created_by,created_at', 'order' => 'created_at.desc', 'limit' => '50']);
    $user['notes'] = $notesRes['data'] ?? [];

    $actionsRes = supabaseRequest('GET', '/rest/v1/admin_user_actions', ['user_id' => 'eq.' . $userId, 'select' => 'id,action_type,reason,starts_at,ends_at,is_active,created_by,created_at', 'order' => 'created_at.desc', 'limit' => '50']);
    $user['actions'] = $actionsRes['data'] ?? [];

    $sessionsRes = supabaseRequest('GET', '/rest/v1/user_sessions', ['user_id' => 'eq.' . $userId, 'select' => 'id,created_at,expires_at,revoked_at,user_agent', 'order' => 'created_at.desc', 'limit' => '20']);
    $user['sessions'] = $sessionsRes['data'] ?? [];

    $loginEventsRes = supabaseRequest('GET', '/rest/v1/user_login_events', ['user_id' => 'eq.' . $userId, 'select' => 'id,success,reason,created_at', 'order' => 'created_at.desc', 'limit' => '20']);
    $user['login_events'] = $loginEventsRes['data'] ?? [];

    jsonSuccess(['user' => $user]);
}

function handleAdminUpdateUserStatus($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $op = strtolower(trim((string) ($data['op'] ?? '')));

    if (!in_array($op, ['deactivate', 'reactivate', 'sign_out', 'verify', 'unverify'], true)) {
        jsonError("Invalid op.", 400);
        return;
    }

    if ($op === 'deactivate') {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['deactivated_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    } elseif ($op === 'reactivate') {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['deactivated_at' => null], ['Prefer: return=minimal']);
    } elseif ($op === 'sign_out') {
        $res = supabaseRequest('PATCH', '/rest/v1/user_sessions', ['user_id' => 'eq.' . $userId, 'revoked_at' => 'is.null'], ['revoked_at' => nowIsoUtc(), 'admin_mode_until' => null], ['Prefer: return=minimal']);
    } elseif ($op === 'verify') {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['is_verified' => true, 'verified_at' => nowIsoUtc(), 'verified_by' => $actorId], ['Prefer: return=minimal']);
    } else {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['is_verified' => false, 'verified_at' => null, 'verified_by' => null], ['Prefer: return=minimal']);
    }

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update user status.", $res);
        return;
    }
    jsonSuccess(['op' => $op, 'applied' => true]);
}

function handleAdminAddUserNote($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $noteType = strtolower(trim((string) ($data['note_type'] ?? 'general')));
    if (!in_array($noteType, adminModerationNoteTypes(), true)) {
        jsonError("Invalid note_type.", 400);
        return;
    }
    $note = cleanNullableText($data['note'] ?? '', 2000);
    if (!$note) {
        jsonError("note is required.", 400);
        return;
    }

    $noteRes = supabaseRequest('POST', '/rest/v1/admin_user_notes', [], [
        'user_id' => $userId,
        'created_by' => $actorId,
        'note_type' => $noteType,
        'note' => $note,
    ], ['Prefer: return=representation']);
    if (supabaseFailed($noteRes) || empty($noteRes['data'])) {
        sendSupabaseError("Failed to add note.", $noteRes);
        return;
    }

    $action = null;
    if (in_array($noteType, adminModerationFlagTypes(), true)) {
        $durationDays = isset($data['duration_days']) ? max(0, (int) $data['duration_days']) : 0;
        $endsAt = $durationDays > 0 ? gmdate('c', time() + ($durationDays * 86400)) : null;

        $actionRes = supabaseRequest('POST', '/rest/v1/admin_user_actions', [], [
            'user_id' => $userId,
            'created_by' => $actorId,
            'action_type' => $noteType,
            'reason' => $note,
            'starts_at' => nowIsoUtc(),
            'ends_at' => $endsAt,
            'is_active' => true,
        ], ['Prefer: return=representation']);
        if (!supabaseFailed($actionRes) && !empty($actionRes['data'])) {
            $action = $actionRes['data'][0];
        }

        if (in_array($noteType, ['ban', 'suspension'], true)) {
            supabaseRequest('PATCH', '/rest/v1/user_sessions', ['user_id' => 'eq.' . $userId, 'revoked_at' => 'is.null'], [
                'revoked_at' => nowIsoUtc(),
                'admin_mode_until' => null,
            ], ['Prefer: return=minimal']);
            createNotification($userId, 'admin_moderation_action', 'Account action taken', "Your account has been {$noteType}ed. Contact support if you believe this is a mistake.", ['action_type' => $noteType]);
        }
    }

    jsonSuccess(['note' => $noteRes['data'][0], 'action' => $action]);
}

function handleAdminResolveUserAction($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $actionId = requireUuid($data['action_id'] ?? '', 'action_id');

    $res = supabaseRequest('PATCH', '/rest/v1/admin_user_actions', ['id' => 'eq.' . $actionId, 'is_active' => 'eq.true'], [
        'is_active' => false,
        'ends_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to resolve action.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Action not found or already resolved.", 404);
        return;
    }

    $resolved = $res['data'][0];
    supabaseRequest('POST', '/rest/v1/admin_user_notes', [], [
        'user_id' => $resolved['user_id'],
        'created_by' => $actorId,
        'note_type' => 'general',
        'note' => "Cleared {$resolved['action_type']} flag.",
    ], ['Prefer: return=minimal']);

    jsonSuccess(['resolved' => true, 'action' => $resolved]);
}

function handleAdminClearUserSessionHistory($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    // Only clears already-revoked/expired rows — never touches a live session.
    $res = supabaseRequest('DELETE', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $userId,
        'or' => '(revoked_at.not.is.null,expires_at.lt.' . nowIsoUtc() . ')',
    ], null, ['Prefer: return=representation']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to clear session history.", $res);
        return;
    }
    jsonSuccess(['cleared_count' => count($res['data'] ?? [])]);
}

// --- Roles panel ---

function adminRoleVocabulary()
{
    return ['owner', 'platform_admin', 'pet_type_admin', 'breed_admin'];
}

function validateAdminRoleScope($role, $scopeType, $scopeValue)
{
    if (!in_array($role, adminRoleVocabulary(), true)) {
        return "Invalid role.";
    }
    if (!in_array($scopeType, ['global', 'pet_type', 'breed'], true)) {
        return "Invalid scope_type.";
    }
    if ($role === 'pet_type_admin' && ($scopeType !== 'pet_type' || $scopeValue === '')) {
        return "pet_type_admin requires scope_type=pet_type and a scope_value.";
    }
    if ($role === 'breed_admin' && ($scopeType !== 'breed' || $scopeValue === '')) {
        return "breed_admin requires scope_type=breed and a scope_value.";
    }
    if (in_array($role, ['owner', 'platform_admin'], true) && $scopeType !== 'global') {
        return "{$role} must have scope_type=global.";
    }
    return null;
}

function handleListAdminRoles($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);

    $res = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'revoked_at' => 'is.null',
        'select' => 'id,user_id,role,scope_type,scope_value,created_by,created_at',
        'order' => 'created_at.asc',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to list admin roles.", $res);
        return;
    }

    $roles = $res['data'] ?? [];
    $roleUserIds = normalizeUuidList(array_column($roles, 'user_id'));
    $profileMap = fetchProfilesMap($roleUserIds);
    $emailMap = [];
    if (!empty($roleUserIds)) {
        $emailsRes = supabaseRequest('GET', '/rest/v1/users', ['id' => 'in.(' . implode(',', $roleUserIds) . ')', 'select' => 'id,email']);
        foreach (($emailsRes['data'] ?? []) as $row) {
            $emailMap[$row['id']] = $row['email'];
        }
    }

    foreach ($roles as &$r) {
        $r['profile'] = $profileMap[$r['user_id']] ?? null;
        $r['email'] = $emailMap[$r['user_id']] ?? null;
    }
    unset($r);

    jsonSuccess(['roles' => $roles]);
}

function handleGrantAdminRole($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);
    $targetUserId = requireUuid($data['user_id'] ?? '', 'user_id');

    $role = strtolower(trim((string) ($data['role'] ?? '')));
    $scopeType = strtolower(trim((string) ($data['scope_type'] ?? 'global')));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 140) ?: '*';
    if ($scopeType === 'global')
        $scopeValue = '*';

    $error = validateAdminRoleScope($role, $scopeType, $scopeValue);
    if ($error) {
        jsonError($error, 400);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/admin_roles', [], [
        'user_id' => $targetUserId,
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_value' => $scopeValue,
        'created_by' => $actorId,
    ], ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to grant role.", $res);
        return;
    }
    jsonSuccess(['role' => $res['data'][0]]);
}

function handleUpdateAdminRole($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);
    $roleId = requireUuid($data['role_id'] ?? '', 'role_id');

    $role = strtolower(trim((string) ($data['role'] ?? '')));
    $scopeType = strtolower(trim((string) ($data['scope_type'] ?? 'global')));
    $scopeValue = cleanPlainValue($data['scope_value'] ?? '*', 140) ?: '*';
    if ($scopeType === 'global')
        $scopeValue = '*';

    $error = validateAdminRoleScope($role, $scopeType, $scopeValue);
    if ($error) {
        jsonError($error, 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/admin_roles', ['id' => 'eq.' . $roleId], [
        'role' => $role,
        'scope_type' => $scopeType,
        'scope_value' => $scopeValue,
    ], ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to update role.", $res);
        return;
    }
    jsonSuccess(['role' => $res['data'][0]]);
}

function handleRevokeAdminRole($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireOwnerCapability($actorId);
    $roleId = requireUuid($data['role_id'] ?? '', 'role_id');

    $res = supabaseRequest('PATCH', '/rest/v1/admin_roles', ['id' => 'eq.' . $roleId, 'revoked_at' => 'is.null'], [
        'revoked_at' => nowIsoUtc(),
        'revoked_by' => $actorId,
    ], ['Prefer: return=representation']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to revoke role.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Role grant not found or already revoked.", 404);
        return;
    }
    jsonSuccess(['revoked' => true]);
}
