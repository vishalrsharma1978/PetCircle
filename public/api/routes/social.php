<?php

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

    $membersRes = supabaseRequest('GET', '/rest/v1/pet_pack_members', [
        'owner_user_id' => 'eq.' . $userId,
        'select' => 'id,linked_user_id,pet_name,relation,date_of_birth,gender,pet_type,breed,microchip_number,sort_order',
        'order' => 'sort_order.asc,created_at.asc',
    ]);

    if (($membersRes['code'] ?? 500) >= 400) {
        jsonError("Failed to load pack members.", 500, ["supabase_response" => $membersRes['data']]);
        return;
    }

    jsonSuccess([
        'birth_details' => new stdClass(),
        'pack_members' => array_map('normalizePetPackMemberRow', $membersRes['data'] ?? []),
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
        jsonError("Pack member name is required.");
        return;
    }

    $allowedRelations = ['Sibling Pet', 'Parent', 'Other'];
    $relation = cleanNullableText($data['relation'] ?? 'Other', 40) ?: 'Other';
    if (!in_array($relation, $allowedRelations, true))
        $relation = 'Other';

    $row = [
        'owner_user_id' => $userId,
        'linked_user_id' => cleanNullableText($data['linked_user_id'] ?? null, 80),
        'pet_name' => $name,
        'relation' => $relation,
        'date_of_birth' => cleanDateValue($data['dob'] ?? null),
        'gender' => cleanNullableText($data['gender'] ?? null, 40),
        'pet_type' => cleanNullableText($data['pet_type'] ?? null, 100),
        'breed' => cleanNullableText($data['breed'] ?? null, 100),
        'microchip_number' => cleanNullableText($data['microchip_number'] ?? null, 100),
        'sort_order' => isset($data['sort_order']) ? intval($data['sort_order']) : 100,
    ];

    if (!empty($data['id']) && preg_match('/^[a-f0-9-]{36}$/i', (string) $data['id'])) {
        $res = supabaseRequest(
            'PATCH',
            '/rest/v1/pet_pack_members',
            ['id' => 'eq.' . $data['id'], 'owner_user_id' => 'eq.' . $userId],
            $row,
            ['Prefer: return=representation']
        );
    } else {
        $res = supabaseRequest(
            'POST',
            '/rest/v1/pet_pack_members',
            [],
            $row,
            ['Prefer: return=representation']
        );
    }

    if (($res['code'] ?? 500) >= 400) {
        jsonError("Failed to save pack member.", 500, ["supabase_response" => $res['data']]);
        return;
    }

    $saved = is_array($res['data']) && isset($res['data'][0]) ? $res['data'][0] : [];
    jsonSuccess(['member' => normalizePetPackMemberRow($saved)]);
}

function handleDeletePetPackMember($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $memberId = cleanNullableText($data['member_id'] ?? ($data['id'] ?? ''), 80);
    if (!$userId || !$memberId) {
        jsonError("user_id and member_id are required.");
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/pet_pack_members', [
        'id' => 'eq.' . $memberId,
        'owner_user_id' => 'eq.' . $userId,
    ]);

    if (($res['code'] ?? 500) >= 400) {
        jsonError("Failed to delete pack member.", 500, ["supabase_response" => $res['data']]);
        return;
    }

    jsonSuccess(["message" => "Pack member deleted."]);
}

function normalizePetPackMemberRow($row)
{
    return [
        'id' => $row['id'] ?? null,
        'name' => $row['pet_name'] ?? '',
        'relation' => $row['relation'] ?? '',
        'dob' => $row['date_of_birth'] ?? '',
        'gender' => $row['gender'] ?? '',
        'pet_type' => $row['pet_type'] ?? '',
        'breed' => $row['breed'] ?? '',
        'microchip_number' => $row['microchip_number'] ?? '',
        'linked_user_id' => $row['linked_user_id'] ?? null,
        'sort_order' => intval($row['sort_order'] ?? 100),
    ];
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


        $post['like_count'] = $likeCounts[$post['id']] ?? 0;
        $post['comment_count'] = $commentCounts[$post['id']] ?? 0;
        $post['is_liked'] = !empty($likedByCurrent[$post['id']]);


        // Frontend compatibility keys



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


        $cid = $comment['id'] ?? '';
        $comment['like_count'] = $likeCounts[$cid] ?? 0;
        $comment['is_liked_by_me'] = isset($myLikes[$cid]);


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
        // global = everyone, pet_type = same pet_type, breed = same pet_type + same breed/caste.
        $hasPetType = isset($group['pet_type']) && trim((string) $group['pet_type']) !== '';
        $hasBreed = isset($group['breed']) && trim((string) $group['breed']) !== '';
        if (!$hasPetType && !$hasBreed) {
            $group['scope'] = 'global';
        } elseif ($hasPetType && !$hasBreed) {
            $group['scope'] = 'pet_type';
        } else {
            $group['scope'] = 'breed';
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

function getAcceptedFriendIds($userId)
{
    if (!$userId) {
        return [];
    }

    $res = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(requester.eq.' . $userId . ',addressee.eq.' . $userId . ')',
        'status' => 'eq.accepted',
        'select' => 'requester,addressee',
        'limit' => '500',
    ]);

    if (supabaseFailed($res) || empty($res['data'])) {
        return [];
    }

    $ids = [];
    foreach (($res['data'] ?? []) as $row) {
        $requester = strtolower(trim((string) ($row['requester'] ?? '')));
        $addressee = strtolower(trim((string) ($row['addressee'] ?? '')));
        if ($requester !== '' && $requester !== strtolower(trim((string) $userId))) {
            $ids[] = $requester;
        }
        if ($addressee !== '' && $addressee !== strtolower(trim((string) $userId))) {
            $ids[] = $addressee;
        }
    }

    return normalizeUuidList($ids);
}

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

    $breed = cleanPlainValue($data['breed'] ?? '', 120);
    $pet_type = cleanPlainValue($data['pet_type'] ?? '', 80);

    $body = [
        'user_id' => $data['user_id'],
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
        'post_type' => $postType,
        'breed' => $breed === '' ? null : $breed,
        'pet_type' => $pet_type === '' ? null : $pet_type,
        'title' => cleanNullableText($data['title'] ?? null, 240),
        'description' => cleanNullableText($data['description'] ?? null, 5000),
    ];

    if (isset($data['hashtags']) && is_array($data['hashtags'])) {
        $body['hashtags'] = array_values(array_filter($data['hashtags']));
    }

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
        'select' => 'id,user_id,content,media_url,post_type,breed,pet_type,is_deleted,created_at,updated_at,title,description,hashtags',
        'is_deleted' => 'eq.false',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ];

    if (!empty($data['post_id'])) {
        $query['id'] = 'eq.' . $data['post_id'];
    }

    if (empty($data['post_id'])) {
        $breed = cleanPlainValue($data['breed'] ?? '', 120);
        $pet_type = cleanPlainValue($data['pet_type'] ?? '', 80);
        $orFilters = [];
        $visibilityFilters = ['and(breed.is.null,pet_type.is.null)'];

        if ($pet_type !== '') {
            $visibilityFilters[] = 'and(breed.is.null,pet_type.eq.' . $pet_type . ')';
        }

        if ($breed !== '') {
            $filter = 'breed.eq.' . $breed;
            if ($pet_type !== '') {
                $filter .= ',pet_type.eq.' . $pet_type;
            }
            $visibilityFilters[] = 'and(' . $filter . ')';
        }

        $orFilters = array_merge($orFilters, $visibilityFilters);

        $currentUserId = strtolower(trim((string) ($data['user_id'] ?? '')));
        $socialAuthorIds = [];
        if (isValidUuid($currentUserId)) {
            $socialAuthorIds[] = $currentUserId;
            $socialAuthorIds = array_merge($socialAuthorIds, getAcceptedFriendIds($currentUserId));
            $socialAuthorIds = normalizeUuidList(array_values(array_unique($socialAuthorIds)));
        }

        if (!empty($socialAuthorIds)) {
            $orFilters[] = 'user_id.in.(' . implode(',', $socialAuthorIds) . ')';
        }

        $query['or'] = '(' . implode(',', array_values(array_unique($orFilters))) . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/posts', $query);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch posts.", $res);
        return;
    }

    $posts = enrichPosts($res['data'] ?? [], $data['user_id'] ?? null);

    jsonSuccess(["posts" => $posts]);
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
        'select' => 'id,user_id,content,media_url,post_type,breed,pet_type,is_deleted,created_at,updated_at,title,description,hashtags',
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

function normalizeGalleryVisibility($value)
{
    $value = strtolower(cleanPlainValue($value ?? 'private', 40));
    $allowed = ['public', 'breed', 'pet_type', 'private'];
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
    // breed = same pet_type + same breed/caste
    // pet_type  = same pet_type, any breed/caste
    // global    = everyone on PawCircle
    $scope = strtolower(trim((string) ($data['scope'] ?? 'breed')));
    if (!in_array($scope, ['breed', 'pet_type', 'global'], true)) {
        $scope = 'breed';
    }

    $pet_type = trim((string) ($data['pet_type'] ?? ''));
    $breed = trim((string) ($data['breed'] ?? ''));

    if ($scope === 'global') {
        $pet_type = null;
        $breed = null;
    } elseif ($scope === 'pet_type') {
        if ($pet_type === '') {
            jsonError("Your profile needs a pet_type before creating a pet_type-specific group.", 400);
            return;
        }
        $pet_type = cleanTextValue($pet_type, 120);
        $breed = null;
    } else {
        if ($pet_type === '' || $breed === '') {
            jsonError("Your profile needs both pet_type and breed before creating a breed-specific group.", 400);
            return;
        }
        $pet_type = cleanTextValue($pet_type, 120);
        $breed = cleanTextValue($breed, 120);
    }

    $body = [
        'name' => $name,
        'description' => cleanTextValue($data['description'] ?? $data['desc'] ?? '', 1000) ?: null,
        'avatar_url' => trim((string) ($data['avatar_url'] ?? '')) ?: null,
        'breed' => $breed,
        'pet_type' => $pet_type,
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
        jsonError("Select at least one breed or group to broadcast to.", 400);
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
    $userPetType = trim((string) ($data['pet_type'] ?? ''));
    $userBreed = trim((string) ($data['breed'] ?? ''));
    $groupsById = [];

    $baseSelect = 'id,name,description,avatar_url,breed,pet_type,created_by,is_private,pack_key,created_at,updated_at';
    $limit = isset($data['limit']) ? (string) max(1, min((int) $data['limit'], 100)) : '10';

    $queries = [];

    // Global public groups: visible to everyone.
    $queries[] = [
        'select' => $baseSelect,
        'is_private' => 'eq.false',
        'pet_type' => 'is.null',
        'breed' => 'is.null',
        'order' => 'created_at.desc',
        'limit' => $limit,
    ];

    // Pet Type-specific public groups: same pet_type, no breed/caste restriction.
    if ($userPetType !== '') {
        $queries[] = [
            'select' => $baseSelect,
            'is_private' => 'eq.false',
            'pet_type' => 'eq.' . $userPetType,
            'breed' => 'is.null',
            'order' => 'created_at.desc',
            'limit' => $limit,
        ];
    }

    // Breed-specific public groups: same pet_type + same breed/caste.
    if ($userPetType !== '' && $userBreed !== '') {
        $queries[] = [
            'select' => $baseSelect,
            'is_private' => 'eq.false',
            'pet_type' => 'eq.' . $userPetType,
            'breed' => 'eq.' . $userBreed,
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
        'pet_type' => cleanNullableText($data['pet_type'] ?? '', 120),
        'breed' => cleanNullableText($data['breed'] ?? '', 160),
        'current_city' => cleanNullableText($data['current_city'] ?? ($data['city'] ?? ''), 160),
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
    $currentPetType = trim((string) ($currentProfile['pet_type'] ?? ''));
    $currentBreed = trim((string) ($currentProfile['breed'] ?? ''));

    $profileQuery = [
        'select' => 'user_id,full_name,profile_photo_url,pet_type,breed,current_city,date_of_birth,gender,is_public',
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
        $profileQuery['or'] = '(full_name.ilike.*' . $safe . '*,current_city.ilike.*' . $safe . '*)';
    }

    $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', $profileQuery);
    
    // Resilience: Fallback if any columns fail (e.g. is_public not available)
    if (supabaseFailed($profilesRes)) {
        $profileQuery['select'] = 'user_id,full_name,profile_photo_url,pet_type,breed,current_city,date_of_birth,gender';
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', $profileQuery);
        if (supabaseFailed($profilesRes)) {
            sendSupabaseError("Failed to search members.", $profilesRes);
            return;
        }
    }

    if (!empty($profilesRes['data'])) {
        foreach ($profilesRes['data'] as &$p) {
            $p['pet_type'] = $p['pet_type'] ?? '';
            $p['breed'] = $p['breed'] ?? '';
            // For backward compatibility during migration


        }
        unset($p);
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
        if ($visibility === 'pet_type' && $currentPetType !== '' && ($p['pet_type'] ?? '') !== $currentPetType)
            continue;
        if ($visibility === 'breed') {
            if ($currentPetType !== '' && ($p['pet_type'] ?? '') !== $currentPetType)
                continue;
            if ($currentBreed !== '' && ($p['breed'] ?? '') !== $currentBreed)
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
            'pet_type' => $p['pet_type'] ?? null,
            'breed' => $p['breed'] ?? null,
            'current_city' => $p['current_city'] ?? null,
            'age' => $age,
            'gender' => $p['gender'] ?? null,
            'occupation' => $p['occupation'] ?? null,
            // 'primary_interests' => $customTags,
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
            'breed' => $profile['breed'] ?? null,
            'pet_type' => $profile['pet_type'] ?? null,
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