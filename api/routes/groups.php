<?php
/**
 * Groups: create/join/leave, listing, and group chat. `scope` + `pack_key`
 * on the groups table aren't fully documented anywhere upstream (best
 * inference: scope is 'global' | 'pet_type' | 'breed', pack_key is a
 * derived filter key like "pet_type:Dog" or "breed:Labrador Retriever") —
 * computed server-side here rather than trusted from the client. Revisit if
 * this turns out to not match the original intent.
 */

/**
 * Group ids the caller belongs to, newest membership first.
 *
 * The 100 cap is a URL-length budget, not a product rule: these ids go into a
 * PostgREST in.() list at ~37 bytes each, against an ~8KB URL ceiling. 100 ids
 * is ~3.7KB and leaves generous headroom. A user in more than 100 groups stops
 * seeing feed posts from their *oldest* memberships (hence joined_at.desc).
 *
 * Every id is passed through normalizeUuidList(), whose /^[0-9a-fA-F-]{36}$/
 * character class excludes every PostgREST metacharacter (, ( ) . *). That is
 * the property callers rely on when interpolating the result into an in.()
 * clause — do not relax it.
 */
function getMyGroupIds($userId)
{
    if (!isValidUuid($userId))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'user_id' => 'eq.' . $userId,
        'select' => 'group_id',
        'order' => 'joined_at.desc',
        'limit' => '100',
    ]);
    if (supabaseFailed($res))
        return [];

    return normalizeUuidList(array_column($res['data'] ?? [], 'group_id'));
}

function isGroupMember($groupId, $userId)
{
    if (!isValidUuid($groupId) || !isValidUuid($userId))
        return false;

    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id',
        'limit' => '1',
    ]);
    return !supabaseFailed($res) && !empty($res['data']);
}

/**
 * 'admin' | 'moderator' | 'member' | null (not a member). Only 'admin' is
 * ever actually assigned today — handleCreateGroup gives it to the creator
 * and everyone else joins as 'member'; 'moderator' exists in the enum but is
 * never written by any code path.
 */
function getGroupRole($groupId, $userId)
{
    if (!isValidUuid($groupId) || !isValidUuid($userId))
        return null;

    $res = supabaseRequest('GET', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $groupId,
        'user_id' => 'eq.' . $userId,
        'select' => 'role',
        'limit' => '1',
    ]);
    if (supabaseFailed($res) || empty($res['data']))
        return null;

    return $res['data'][0]['role'] ?? null;
}

function computePackKey($scope, $petType, $breed)
{
    if ($scope === 'breed' && $breed)
        return 'breed:' . strtolower($breed);
    if ($scope === 'pet_type' && $petType)
        return 'pet_type:' . strtolower($petType);
    return null;
}

function handleCreateGroup($data)
{
    if (!requireFields($data, ['user_id', 'name']))
        return;

    $name = cleanNullableText($data['name'], 120);
    $description = cleanNullableText($data['description'] ?? '', 500);
    $scope = $data['scope'] ?? 'global';
    $scope = in_array($scope, ['global', 'pet_type', 'breed'], true) ? $scope : 'global';
    $petType = cleanNullableText($data['pet_type'] ?? '', 80);
    $breed = cleanNullableText($data['breed'] ?? '', 140);

    $body = [
        'name' => $name,
        'description' => $description,
        'pet_type' => $scope === 'global' ? null : $petType,
        'breed' => $scope === 'breed' ? $breed : null,
        'created_by' => $data['user_id'],
        'is_private' => !empty($data['is_private']),
        'scope' => $scope,
        'pack_key' => computePackKey($scope, $petType, $breed),
    ];

    $res = supabaseRequest('POST', '/rest/v1/groups', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create group.", $res);
        return;
    }

    $group = $res['data'][0];
    supabaseRequest('POST', '/rest/v1/group_members', [], [
        'group_id' => $group['id'],
        'user_id' => $data['user_id'],
        'role' => 'admin',
    ], ['Prefer: return=minimal']);

    jsonSuccess(["group" => $group]);
}

function handleGetGroups($data)
{
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 50;
    $params = [
        'select' => 'id,name,description,avatar_url,pet_type,breed,is_private,scope,created_by,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ];
    if (!empty($data['pet_type']) && empty($data['all'])) {
        $petType = cleanFilterValue($data['pet_type'], 80);
        $params['or'] = '(scope.eq.global,pet_type.eq.' . $petType . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/groups', $params);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch groups.", $res);
        return;
    }

    $groups = $res['data'] ?? [];
    $groupIds = array_column($groups, 'id');
    $myMemberships = [];
    if (!empty($data['user_id']) && !empty($groupIds)) {
        $memRes = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'in.(' . implode(',', $groupIds) . ')',
            'user_id' => 'eq.' . $data['user_id'],
            'select' => 'group_id,role',
        ]);
        foreach (($memRes['data'] ?? []) as $row) {
            $myMemberships[$row['group_id']] = $row['role'];
        }
    }

    $countsRes = empty($groupIds) ? ['data' => []] : supabaseRequest('GET', '/rest/v1/group_members', [
        'group_id' => 'in.(' . implode(',', $groupIds) . ')',
        'select' => 'group_id',
    ]);
    $counts = [];
    foreach (($countsRes['data'] ?? []) as $row) {
        $counts[$row['group_id']] = ($counts[$row['group_id']] ?? 0) + 1;
    }

    foreach ($groups as &$g) {
        $g['member_count'] = $counts[$g['id']] ?? 0;
        $g['my_role'] = $myMemberships[$g['id']] ?? null;
        $g['is_member'] = isset($myMemberships[$g['id']]);
    }
    unset($g);

    jsonSuccess(["groups" => $groups]);
}

function handleJoinGroup($data)
{
    if (!requireFields($data, ['user_id', 'group_id']))
        return;

    $groupRes = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $data['group_id'], 'select' => 'id,is_private', 'limit' => '1']);
    if (supabaseFailed($groupRes) || empty($groupRes['data'])) {
        jsonError("Group not found.", 404);
        return;
    }
    if (!empty($groupRes['data'][0]['is_private'])) {
        jsonError("This group is private. Ask a member to add you.", 403);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/group_members', [], [
        'group_id' => $data['group_id'],
        'user_id' => $data['user_id'],
        'role' => 'member',
    ], ['Prefer: resolution=ignore-duplicates,return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to join group.", $res);
        return;
    }

    jsonSuccess(["message" => "Joined group."]);
}

function handleLeaveGroup($data)
{
    if (!requireFields($data, ['user_id', 'group_id']))
        return;

    $res = supabaseRequest('DELETE', '/rest/v1/group_members', [
        'group_id' => 'eq.' . $data['group_id'],
        'user_id' => 'eq.' . $data['user_id'],
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to leave group.", $res);
        return;
    }

    jsonSuccess(["message" => "Left group."]);
}

function handleSendGroupMessage($data)
{
    if (!requireFields($data, ['user_id', 'group_id']))
        return;
    $content = cleanTextValue($data['content'] ?? '', 3000);
    $mediaUrl = trim((string) ($data['media_url'] ?? ''));
    if ($content === '' && $mediaUrl === '') {
        jsonError("Message content or media is required.", 400);
        return;
    }

    if (!isGroupMember($data['group_id'], $data['user_id'])) {
        jsonError("You must be a member of this group to message it.", 403);
        return;
    }

    $body = [
        'group_id' => $data['group_id'],
        'sender_id' => $data['user_id'],
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
    ];
    if (!empty($data['reply_to_id'])) {
        $body['reply_to_id'] = $data['reply_to_id'];
    }

    $res = supabaseRequest('POST', '/rest/v1/group_messages', [], $body, ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to send message.", $res);
        return;
    }

    jsonSuccess(["message_row" => $res['data'][0]]);
}

function handleGetGroupMessages($data)
{
    $groupId = requireUuid($data['group_id'] ?? '', 'group_id');
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 200)) : 50;

    $res = supabaseRequest('GET', '/rest/v1/group_messages', [
        'group_id' => 'eq.' . $groupId,
        'is_deleted' => 'eq.false',
        'select' => 'id,group_id,sender_id,content,media_url,created_at,reply_to_id,reactions',
        'order' => 'created_at.asc',
        'limit' => (string) $limit,
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch messages.", $res);
        return;
    }

    $messages = enrichMessagesWithReplyText($res['data'] ?? [], 'group_messages');
    $profileMap = fetchProfilesMap(normalizeUuidList(array_column($messages, 'sender_id')));
    foreach ($messages as &$m) {
        $p = $profileMap[$m['sender_id']] ?? null;
        $m['sender_name'] = $p['pet_name'] ?? 'Member';
        $m['sender_photo'] = $p['profile_photo_url'] ?? null;
    }
    unset($m);

    jsonSuccess(["messages" => $messages]);
}

function handleReactToGroupMessage($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $messageId = requireUuid($data['message_id'] ?? '', 'message_id');
    $emoji = trim((string) ($data['reaction'] ?? ''));

    $msgRes = supabaseRequest('GET', '/rest/v1/group_messages', [
        'id' => 'eq.' . $messageId,
        'select' => 'id,group_id,reactions',
        'limit' => '1',
    ]);
    if (supabaseFailed($msgRes) || empty($msgRes['data'])) {
        jsonError("Message not found.", 404);
        return;
    }
    $message = $msgRes['data'][0];

    if (!isGroupMember($message['group_id'], $userId)) {
        jsonError("You must be a member of this group to react.", 403);
        return;
    }

    $reactions = is_array($message['reactions'] ?? null) ? $message['reactions'] : [];
    if ($emoji === '') {
        unset($reactions[$userId]);
    } else {
        $reactions[$userId] = $emoji;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/group_messages', ['id' => 'eq.' . $messageId], ['reactions' => $reactions], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update reaction.", $res);
        return;
    }

    jsonSuccess(["reactions" => $reactions]);
}
