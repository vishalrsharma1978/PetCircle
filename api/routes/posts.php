<?php
/**
 * Feed posts: create/list/delete, like/unlike, comments. Written directly
 * against PetCircle-rebuild's actual posts/post_likes/post_comments schema
 * (pet_type/breed scoping, no reaction-string system — post_likes is a
 * plain toggle table) rather than ported from eSamaj's version, which
 * targets a different schema (community/religion scoping, multi-reaction
 * strings) that doesn't match what this database actually has.
 */

function enrichPosts($posts, $viewerUserId = null)
{
    $posts = $posts ?? [];
    if (empty($posts))
        return [];

    $postIds = array_column($posts, 'id');
    $authorIds = normalizeUuidList(array_column($posts, 'user_id'));
    $profileMap = fetchProfilesMap($authorIds);

    $likeCounts = [];
    $commentCounts = [];
    $viewerLikes = [];

    if (!empty($postIds)) {
        $likesRes = supabaseRequest('GET', '/rest/v1/post_likes', [
            'post_id' => 'in.(' . implode(',', $postIds) . ')',
            'select' => 'post_id,user_id',
        ]);
        foreach (($likesRes['data'] ?? []) as $row) {
            $pid = $row['post_id'];
            $likeCounts[$pid] = ($likeCounts[$pid] ?? 0) + 1;
            if ($viewerUserId && $row['user_id'] === $viewerUserId) {
                $viewerLikes[$pid] = true;
            }
        }

        $commentsRes = supabaseRequest('GET', '/rest/v1/post_comments', [
            'post_id' => 'in.(' . implode(',', $postIds) . ')',
            'is_deleted' => 'eq.false',
            'select' => 'post_id',
        ]);
        foreach (($commentsRes['data'] ?? []) as $row) {
            $pid = $row['post_id'];
            $commentCounts[$pid] = ($commentCounts[$pid] ?? 0) + 1;
        }
    }

    return array_map(function ($post) use ($profileMap, $likeCounts, $commentCounts, $viewerLikes) {
        $author = $profileMap[$post['user_id']] ?? null;
        $post['author'] = $author ? [
            'user_id' => $post['user_id'],
            'name' => $author['pet_name'] ?? $author['full_name'] ?? 'Member',
            'pet_type' => $author['pet_type'] ?? null,
            'breed' => $author['breed'] ?? null,
            'profile_photo_url' => $author['profile_photo_url'] ?? null,
        ] : ['user_id' => $post['user_id'], 'name' => 'Member'];
        $post['like_count'] = $likeCounts[$post['id']] ?? 0;
        $post['comment_count'] = $commentCounts[$post['id']] ?? 0;
        $post['is_liked_by_me'] = isset($viewerLikes[$post['id']]);
        return $post;
    }, $posts);
}

function notifyMentions($content, $authorId, $postId)
{
    if (empty($content))
        return;
    preg_match_all('/@([a-zA-Z0-9_]{5,20})/', $content, $matches);
    if (empty($matches[1]))
        return;

    $handles = array_unique(array_map('strtolower', $matches[1]));
    $res = supabaseRequest('GET', '/rest/v1/users', [
        'handle' => 'in.(' . implode(',', $handles) . ')',
        'select' => 'id,handle',
    ]);
    if (supabaseFailed($res) || empty($res['data']))
        return;

    $authorProfile = getAccountProfile($authorId);
    $authorName = $authorProfile['pet_name'] ?? 'Someone';

    foreach ($res['data'] as $user) {
        if ($user['id'] === $authorId)
            continue;
        createNotification($user['id'], 'mention_post', 'You were mentioned', "$authorName mentioned you in a post.", ['post_id' => $postId]);
    }
}

function handleCreatePost($data)
{
    if (empty($data['user_id'])) {
        jsonError("user_id required.", 400);
        return;
    }

    $content = cleanTextValue($data['content'] ?? '', 5000);
    $mediaUrl = trim((string) ($data['media_url'] ?? ''));
    if ($content === '' && $mediaUrl === '') {
        jsonError("Post content or media is required.", 400);
        return;
    }

    $postType = $data['post_type'] ?? ($mediaUrl ? 'image' : 'text');
    $allowedTypes = ['text', 'image', 'video', 'poll', 'event'];
    if (!in_array($postType, $allowedTypes, true)) {
        $postType = $mediaUrl ? 'image' : 'text';
    }
    if ($mediaUrl !== '' && $postType === 'text') {
        $path = strtolower(parse_url($mediaUrl, PHP_URL_PATH) ?: '');
        $postType = preg_match('/\.(mp4|webm|mov|m4v)$/', $path) ? 'video' : 'image';
    }

    $profile = getAccountProfile($data['user_id']);

    $body = [
        'user_id' => $data['user_id'],
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
        'post_type' => $postType,
        'pet_type' => cleanNullableText($data['pet_type'] ?? ($profile['pet_type'] ?? ''), 80),
        'breed' => cleanNullableText($data['breed'] ?? ($profile['breed'] ?? ''), 140),
    ];
    if (isset($data['hashtags']) && is_array($data['hashtags'])) {
        $body['hashtags'] = array_values(array_filter(array_map('trim', $data['hashtags'])));
    }

    $res = supabaseRequest('POST', '/rest/v1/posts', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create post.", $res);
        return;
    }

    $post = enrichPosts([$res['data'][0]], $data['user_id'])[0];
    notifyMentions($content, $data['user_id'], $post['id']);

    jsonSuccess(["post" => $post]);
}

function handleGetPosts($data)
{
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 50)) : 20;
    $offset = isset($data['offset']) ? max(0, (int) $data['offset']) : 0;

    $query = [
        'select' => 'id,user_id,content,media_url,post_type,pet_type,breed,is_deleted,created_at,updated_at,hashtags',
        'is_deleted' => 'eq.false',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];

    // Feed scope: "my pet type" (+ everything untagged) unless the caller
    // asks for everything explicitly.
    if (!empty($data['pet_type']) && empty($data['all'])) {
        $petType = cleanPlainValue($data['pet_type'], 80);
        $query['or'] = '(pet_type.is.null,pet_type.eq.' . $petType . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/posts', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch posts.", $res);
        return;
    }

    jsonSuccess(["posts" => enrichPosts($res['data'] ?? [], $data['user_id'] ?? null)]);
}

function handleGetUserPosts($data)
{
    $userId = requireUuid($data['target_user_id'] ?? $data['user_id'] ?? '', 'user_id');
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 50;

    $res = supabaseRequest('GET', '/rest/v1/posts', [
        'user_id' => 'eq.' . $userId,
        'is_deleted' => 'eq.false',
        'select' => 'id,user_id,content,media_url,post_type,pet_type,breed,is_deleted,created_at,updated_at,hashtags',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch posts.", $res);
        return;
    }

    jsonSuccess(["posts" => enrichPosts($res['data'] ?? [], $data['user_id'] ?? null)]);
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
    ], ['is_deleted' => true, 'updated_at' => gmdate('c')], ['Prefer: return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete post.", $res);
        return;
    }

    $mediaUrl = $postRes['data'][0]['media_url'] ?? null;
    if ($mediaUrl) {
        $parsed = parsePublicStorageUrl($mediaUrl);
        if ($parsed)
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
    }

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
        'select' => 'post_id',
        'limit' => '1',
    ]);
    if (supabaseFailed($check)) {
        sendSupabaseError("Failed to check like status.", $check);
        return;
    }

    if (!empty($check['data'])) {
        $del = supabaseRequest('DELETE', '/rest/v1/post_likes', ['user_id' => 'eq.' . $uid, 'post_id' => 'eq.' . $pid]);
        if (supabaseFailed($del)) {
            sendSupabaseError("Failed to unlike post.", $del);
            return;
        }
        $isLiked = false;
    } else {
        $ins = supabaseRequest('POST', '/rest/v1/post_likes', [], ['user_id' => $uid, 'post_id' => $pid], ['Prefer: resolution=ignore-duplicates,return=minimal']);
        if (supabaseFailed($ins)) {
            sendSupabaseError("Failed to like post.", $ins);
            return;
        }
        $isLiked = true;

        $postRes = supabaseRequest('GET', '/rest/v1/posts', ['id' => 'eq.' . $pid, 'select' => 'user_id', 'limit' => '1']);
        $ownerId = $postRes['data'][0]['user_id'] ?? null;
        if ($ownerId && $ownerId !== $uid) {
            $likerProfile = getAccountProfile($uid);
            $likerName = $likerProfile['pet_name'] ?? 'Someone';
            createNotification($ownerId, 'post_like', 'New like', "$likerName liked your post.", ['post_id' => $pid]);
        }
    }

    $countRes = supabaseRequest('GET', '/rest/v1/post_likes', ['post_id' => 'eq.' . $pid, 'select' => 'post_id']);
    $likeCount = count($countRes['data'] ?? []);

    jsonSuccess(["is_liked" => $isLiked, "like_count" => $likeCount, "post_id" => $pid]);
}

function handleAddComment($data)
{
    if (!requireFields($data, ['user_id', 'post_id']))
        return;
    $content = cleanTextValue($data['content'] ?? '', 2000);
    if ($content === '') {
        jsonError("Comment content is required.", 400);
        return;
    }

    $body = [
        'post_id' => $data['post_id'],
        'user_id' => $data['user_id'],
        'content' => $content,
        'parent_id' => !empty($data['parent_id']) ? $data['parent_id'] : null,
    ];
    $res = supabaseRequest('POST', '/rest/v1/post_comments', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to add comment.", $res);
        return;
    }

    $postRes = supabaseRequest('GET', '/rest/v1/posts', ['id' => 'eq.' . $data['post_id'], 'select' => 'user_id', 'limit' => '1']);
    $ownerId = $postRes['data'][0]['user_id'] ?? null;
    if ($ownerId && $ownerId !== $data['user_id']) {
        $commenterProfile = getAccountProfile($data['user_id']);
        $commenterName = $commenterProfile['pet_name'] ?? 'Someone';
        createNotification($ownerId, 'post_comment', 'New comment', "$commenterName commented on your post.", ['post_id' => $data['post_id']]);
    }

    $comment = $res['data'][0];
    $profile = getAccountProfile($data['user_id']);
    $comment['author'] = ['user_id' => $data['user_id'], 'name' => $profile['pet_name'] ?? 'Member', 'profile_photo_url' => $profile['profile_photo_url'] ?? null];

    jsonSuccess(["comment" => $comment]);
}

function handleGetComments($data)
{
    $postId = requireUuid($data['post_id'] ?? '', 'post_id');
    $res = supabaseRequest('GET', '/rest/v1/post_comments', [
        'post_id' => 'eq.' . $postId,
        'is_deleted' => 'eq.false',
        'select' => 'id,post_id,user_id,content,parent_id,created_at',
        'order' => 'created_at.asc',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch comments.", $res);
        return;
    }

    $comments = $res['data'] ?? [];
    $profileMap = fetchProfilesMap(normalizeUuidList(array_column($comments, 'user_id')));
    $comments = array_map(function ($c) use ($profileMap) {
        $author = $profileMap[$c['user_id']] ?? null;
        $c['author'] = $author ? ['user_id' => $c['user_id'], 'name' => $author['pet_name'] ?? 'Member', 'profile_photo_url' => $author['profile_photo_url'] ?? null] : ['user_id' => $c['user_id'], 'name' => 'Member'];
        return $c;
    }, $comments);

    jsonSuccess(["comments" => $comments]);
}

function handleDeleteComment($data)
{
    if (!requireFields($data, ['user_id', 'comment_id']))
        return;
    $res = supabaseRequest('PATCH', '/rest/v1/post_comments', [
        'id' => 'eq.' . $data['comment_id'],
        'user_id' => 'eq.' . $data['user_id'],
    ], ['is_deleted' => true], ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to delete comment (not found or not yours).", $res, 404);
        return;
    }

    jsonSuccess(["message" => "Comment deleted."]);
}
