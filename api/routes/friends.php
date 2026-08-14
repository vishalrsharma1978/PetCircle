<?php
/**
 * Friends: requests, accept/decline, removal, listing, and a basic
 * pet-scoped member search to find people to friend.
 */

function handleSearchUsers($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $query = cleanNullableText($data['query'] ?? '', 80);
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 40)) : 20;

    $params = [
        'select' => 'user_id,pet_name,parent_name,pet_type,breed,profile_photo_url,current_city',
        'user_id' => 'neq.' . $userId,
        'limit' => (string) $limit,
    ];
    if ($query !== null) {
        $safe = str_replace(['*', ',', '(', ')'], '', $query);
        $params['or'] = '(pet_name.ilike.*' . $safe . '*,parent_name.ilike.*' . $safe . '*)';
    }

    $res = supabaseRequest('GET', '/rest/v1/profiles', $params);
    if (supabaseFailed($res)) {
        sendSupabaseError("Search failed.", $res);
        return;
    }

    jsonSuccess(["results" => $res['data'] ?? []]);
}

function handleSendFriendRequest($data)
{
    if (!requireFields($data, ['user_id', 'friend_id']))
        return;
    $userId = $data['user_id'];
    $friendId = $data['friend_id'];
    if ($userId === $friendId) {
        jsonError("You can't friend yourself.", 400);
        return;
    }

    $existing = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(and(requester.eq.' . $userId . ',addressee.eq.' . $friendId . '),and(requester.eq.' . $friendId . ',addressee.eq.' . $userId . '))',
        'select' => 'id,status',
        'limit' => '1',
    ]);
    if (!supabaseFailed($existing) && !empty($existing['data'])) {
        jsonError("A friend request already exists between you two.", 409, ['status' => $existing['data'][0]['status'] ?? null]);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/friendships', [], [
        'requester' => $userId,
        'addressee' => $friendId,
        'status' => 'pending',
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to send friend request.", $res);
        return;
    }

    $requesterProfile = getAccountProfile($userId);
    $requesterName = $requesterProfile['pet_name'] ?? 'Someone';
    createNotification($friendId, 'friend_request', 'New friend request', "$requesterName wants to be friends.", ['friendship_id' => $res['data'][0]['id']]);

    jsonSuccess(["friendship" => $res['data'][0]]);
}

function handleRespondFriendRequest($data)
{
    if (!requireFields($data, ['user_id', 'friendship_id', 'response_action']))
        return;
    $action = $data['response_action'];
    if (!in_array($action, ['accept', 'decline'], true)) {
        jsonError("response_action must be accept or decline.", 400);
        return;
    }

    $check = supabaseRequest('GET', '/rest/v1/friendships', [
        'id' => 'eq.' . $data['friendship_id'],
        'addressee' => 'eq.' . $data['user_id'],
        'status' => 'eq.pending',
        'select' => 'id,requester',
        'limit' => '1',
    ]);
    if (supabaseFailed($check) || empty($check['data'])) {
        jsonError("Friend request not found.", 404);
        return;
    }

    if ($action === 'decline') {
        $res = supabaseRequest('DELETE', '/rest/v1/friendships', ['id' => 'eq.' . $data['friendship_id']]);
        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to decline request.", $res);
            return;
        }
        jsonSuccess(["message" => "Request declined."]);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/friendships', ['id' => 'eq.' . $data['friendship_id']], [
        'status' => 'accepted',
        'updated_at' => gmdate('c'),
    ], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to accept request.", $res);
        return;
    }

    $requesterId = $check['data'][0]['requester'];
    $accepterProfile = getAccountProfile($data['user_id']);
    $accepterName = $accepterProfile['pet_name'] ?? 'Someone';
    createNotification($requesterId, 'friend_request_accepted', 'Friend request accepted', "$accepterName accepted your friend request.", []);

    jsonSuccess(["message" => "Friend request accepted."]);
}

function handleRemoveFriend($data)
{
    if (!requireFields($data, ['user_id', 'friend_id']))
        return;

    $res = supabaseRequest('DELETE', '/rest/v1/friendships', [
        'or' => '(and(requester.eq.' . $data['user_id'] . ',addressee.eq.' . $data['friend_id'] . '),and(requester.eq.' . $data['friend_id'] . ',addressee.eq.' . $data['user_id'] . '))',
    ], null, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to remove friend.", $res);
        return;
    }

    jsonSuccess(["action" => empty($res['data']) ? "not_found" : "removed"]);
}

function handleGetFriends($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $res = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(requester.eq.' . $userId . ',addressee.eq.' . $userId . ')',
        'status' => 'eq.accepted',
        'select' => 'id,requester,addressee',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch friends.", $res);
        return;
    }

    $friendIds = [];
    $friendshipByUser = [];
    foreach (($res['data'] ?? []) as $row) {
        $otherId = $row['requester'] === $userId ? $row['addressee'] : $row['requester'];
        $friendIds[] = $otherId;
        $friendshipByUser[$otherId] = $row['id'];
    }

    $profileMap = fetchProfilesMap($friendIds);
    $friends = [];
    foreach ($friendIds as $fid) {
        $p = $profileMap[$fid] ?? null;
        $friends[] = [
            'user_id' => $fid,
            'friendship_id' => $friendshipByUser[$fid],
            'name' => $p['pet_name'] ?? 'Member',
            'pet_type' => $p['pet_type'] ?? null,
            'breed' => $p['breed'] ?? null,
            'profile_photo_url' => $p['profile_photo_url'] ?? null,
        ];
    }

    jsonSuccess(["friends" => $friends]);
}

function handleGetFriendRequests($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $res = supabaseRequest('GET', '/rest/v1/friendships', [
        'addressee' => 'eq.' . $userId,
        'status' => 'eq.pending',
        'select' => 'id,requester,created_at',
        'order' => 'created_at.desc',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch friend requests.", $res);
        return;
    }

    $requesterIds = array_column($res['data'] ?? [], 'requester');
    $profileMap = fetchProfilesMap($requesterIds);

    $requests = array_map(function ($row) use ($profileMap) {
        $p = $profileMap[$row['requester']] ?? null;
        return [
            'friendship_id' => $row['id'],
            'user_id' => $row['requester'],
            'name' => $p['pet_name'] ?? 'Member',
            'pet_type' => $p['pet_type'] ?? null,
            'breed' => $p['breed'] ?? null,
            'profile_photo_url' => $p['profile_photo_url'] ?? null,
            'created_at' => $row['created_at'],
        ];
    }, $res['data'] ?? []);

    jsonSuccess(["requests" => $requests]);
}
