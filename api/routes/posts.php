<?php
/**
 * Feed posts: create/list/delete, like/unlike, comments. Written directly
 * against PetCircle-rebuild's actual posts/post_likes/post_comments schema
 * (pet_type/breed scoping, no reaction-string system — post_likes is a
 * plain toggle table) rather than ported from eSamaj's version, which
 * targets a different schema (community/religion scoping, multi-reaction
 * strings) that doesn't match what this database actually has.
 */

/**
 * The one place the posts column list lives. It used to be copy-pasted into
 * four separate queries (the feed, profile posts, single-post fetch, and the
 * community hub's trending widget); adding group_id to three of those four
 * would have been a silent bug rather than a loud one — enrichPosts() would
 * see no group_id on the fourth and render a group post as an ordinary one.
 * Keep every posts SELECT going through here.
 */
function postsSelectColumns()
{
    return 'id,user_id,group_id,content,media_url,post_type,pet_type,breed,is_deleted,created_at,updated_at,hashtags';
}

/**
 * True if $viewerId is allowed to read or interact with $postId.
 *
 * Ordinary posts (group_id IS NULL) are open to any authenticated user, as
 * before. Group posts are members-only, so every endpoint that takes a bare
 * post_id and would otherwise reveal or mutate its content needs this — the
 * post id alone is not proof of entitlement.
 */
function viewerCanSeePost($postId, $viewerId)
{
    if (!isValidUuid($postId))
        return false;

    $res = supabaseRequest('GET', '/rest/v1/posts', [
        'id' => 'eq.' . $postId,
        'select' => 'group_id',
        'limit' => '1',
    ]);
    if (supabaseFailed($res) || empty($res['data']))
        return false;

    $groupId = $res['data'][0]['group_id'] ?? null;
    return $groupId === null ? true : isGroupMember($groupId, $viewerId);
}

function enrichPosts($posts, $viewerUserId = null)
{
    $posts = $posts ?? [];
    if (empty($posts))
        return [];

    $postIds = array_column($posts, 'id');
    $authorIds = normalizeUuidList(array_column($posts, 'user_id'));
    $profileMap = fetchProfilesMap($authorIds);

    // Handle lives on users, not profiles — a small targeted query rather
    // than widening fetchProfilesMap()'s select, since that helper is reused
    // by callers that don't need it.
    $handleMap = [];
    if (!empty($authorIds)) {
        $handleRes = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'in.(' . implode(',', $authorIds) . ')',
            'select' => 'id,handle',
        ]);
        foreach (($handleRes['data'] ?? []) as $row) {
            if (!empty($row['handle'])) {
                $handleMap[$row['id']] = $row['handle'];
            }
        }
    }

    // Group posts: resolve the group's display identity, plus the *viewer's*
    // role in it (that's what decides whether the card offers a moderator
    // Delete). Both queries are behind the emptiness guard on purpose — a feed
    // with no group posts in it must keep costing the same 4 Supabase round
    // trips it always has, not 6.
    $groupMap = [];
    $viewerGroupRoles = [];
    $groupIds = normalizeUuidList(array_filter(array_map(function ($p) {
        return $p['group_id'] ?? null;
    }, $posts)));

    if (!empty($groupIds)) {
        $groupsRes = supabaseRequest('GET', '/rest/v1/groups', [
            'id' => 'in.(' . implode(',', $groupIds) . ')',
            'select' => 'id,name,avatar_url',
        ]);
        foreach (($groupsRes['data'] ?? []) as $row) {
            $groupMap[$row['id']] = $row;
        }

        if ($viewerUserId) {
            $rolesRes = supabaseRequest('GET', '/rest/v1/group_members', [
                'group_id' => 'in.(' . implode(',', $groupIds) . ')',
                'user_id' => 'eq.' . $viewerUserId,
                'select' => 'group_id,role',
            ]);
            foreach (($rolesRes['data'] ?? []) as $row) {
                $viewerGroupRoles[$row['group_id']] = $row['role'];
            }
        }
    }

    $likeCounts = [];
    $reactionSummaries = [];
    $viewerLikes = [];
    $viewerReactions = [];
    $commentCounts = [];

    if (!empty($postIds)) {
        $likesRes = supabaseRequest('GET', '/rest/v1/post_likes', [
            'post_id' => 'in.(' . implode(',', $postIds) . ')',
            'select' => 'post_id,user_id,reaction',
        ]);
        foreach (($likesRes['data'] ?? []) as $row) {
            $pid = $row['post_id'];
            $keys = array_filter(array_map('trim', explode(',', $row['reaction'] ?? 'Liked')));
            foreach ($keys as $r) {
                if ($r === 'Liked') {
                    $likeCounts[$pid] = ($likeCounts[$pid] ?? 0) + 1;
                    if ($viewerUserId && $row['user_id'] === $viewerUserId) {
                        $viewerLikes[$pid] = true;
                    }
                } else {
                    if (!isset($reactionSummaries[$pid])) $reactionSummaries[$pid] = [];
                    $reactionSummaries[$pid][$r] = ($reactionSummaries[$pid][$r] ?? 0) + 1;
                    if ($viewerUserId && $row['user_id'] === $viewerUserId) {
                        $viewerReactions[$pid] = $r;
                    }
                }
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

    return array_map(function ($post) use ($profileMap, $handleMap, $likeCounts, $reactionSummaries, $viewerLikes, $viewerReactions, $commentCounts, $groupMap, $viewerGroupRoles) {
        $authorId = $post['user_id'];
        $author = $profileMap[$authorId] ?? null;

        $post['author'] = $author ? [
            'user_id' => $authorId,
            'name' => $author['pet_name'] ?? $author['full_name'] ?? 'Member',
            'handle' => $handleMap[$authorId] ?? null,
            'pet_type' => $author['pet_type'] ?? null,
            'breed' => $author['breed'] ?? null,
            'profile_photo_url' => $author['profile_photo_url'] ?? null,
        ] : ['user_id' => $authorId, 'name' => 'Member', 'handle' => $handleMap[$authorId] ?? null];
        $post['like_count'] = $likeCounts[$post['id']] ?? 0;
        $post['reaction_summary'] = $reactionSummaries[$post['id']] ?? [];
        $post['comment_count'] = $commentCounts[$post['id']] ?? 0;
        $post['is_liked_by_me'] = isset($viewerLikes[$post['id']]);
        $post['viewer_reaction'] = $viewerReactions[$post['id']] ?? null;

        // Present only on group posts. The frontend keys its whole header
        // variant off `post.group` being non-null, so read group_id defensively
        // (?? null) — a caller with a stale SELECT should degrade to an
        // ordinary-looking post rather than fatal.
        $groupId = $post['group_id'] ?? null;
        $post['group'] = ($groupId && isset($groupMap[$groupId])) ? [
            'id' => $groupId,
            'name' => $groupMap[$groupId]['name'] ?? 'Group',
            'avatar_url' => $groupMap[$groupId]['avatar_url'] ?? null,
            'my_role' => $viewerGroupRoles[$groupId] ?? null,
        ] : null;

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

    // Optional: post into a group instead of to your own feed. Deliberately
    // isValidUuid() and not requireUuid() — the latter exit()s, which would
    // kill every ordinary post that legitimately omits group_id.
    $groupId = null;
    if (!empty($data['group_id'])) {
        $groupId = trim((string) $data['group_id']);
        if (!isValidUuid($groupId)) {
            jsonError("Invalid group_id.", 400);
            return;
        }
        // Without this, any authenticated user could inject posts into any
        // group — including a private one they were never in — and those posts
        // would then be pushed into every real member's feed.
        if (!isGroupMember($groupId, $data['user_id'])) {
            jsonError("You must be a member of this group to post in it.", 403);
            return;
        }
    }

    $profile = getAccountProfile($data['user_id']);

    $body = [
        'user_id' => $data['user_id'],
        'group_id' => $groupId,
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
        'post_type' => $postType,
        // Still backfilled for group posts even though the feed ignores
        // pet_type for them — it keeps the author meta line and any later
        // pet-type analytics intact.
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

    $viewerId = $data['user_id'] ?? null;

    $query = [
        'select' => postsSelectColumns(),
        'is_deleted' => 'eq.false',
        'is_archived' => 'eq.false',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];

    // Feed scope: "my pet type" (+ everything untagged) unless the caller
    // asks for everything explicitly. Applies to ordinary posts only — a post
    // from a group you're in always shows, whatever pet type it carries.
    $petType = '';
    if (!empty($data['pet_type']) && empty($data['all'])) {
        $petType = cleanFilterValue($data['pet_type'], 80);
    }

    // Visibility: ordinary posts (group_id IS NULL) plus posts from groups the
    // viewer belongs to, and nothing else. Without this every group post would
    // land in every user's feed, which is the whole point of the feature
    // inverted.
    $myGroupIds = $viewerId ? getMyGroupIds($viewerId) : [];

    if (empty($myGroupIds)) {
        // No memberships, so there is nothing to union in. An empty in.() list
        // is legal PostgREST and matches zero rows, but a plain is.null is
        // cheaper and lets the partial idx_posts_no_group_created_at apply.
        $query['group_id'] = 'is.null';
        if ($petType !== '') {
            $query['or'] = '(pet_type.is.null,pet_type.eq.' . $petType . ')';
        }
    } else {
        $clauses = ['or(group_id.is.null,group_id.in.(' . implode(',', $myGroupIds) . '))'];
        if ($petType !== '') {
            $clauses[] = 'or(group_id.not.is.null,pet_type.is.null,pet_type.eq.' . $petType . ')';
        }
        // Both clauses fold into a single and= on purpose. Do NOT also set
        // $query['or'] here: PostgREST ANDs top-level params together, so a
        // stray or= would re-apply the pet-type filter to group posts and
        // undo the bypass above.
        $query['and'] = '(' . implode(',', $clauses) . ')';
    }

    if (!empty($data['search_query'])) {
        $safe = str_replace(['*', ',', '(', ')'], '', $data['search_query']);
        $query['content'] = 'ilike.*' . $safe . '*';
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
    // Archived posts are private — only ever the caller's own, never a
    // target_user_id's, regardless of what's passed.
    $wantsArchived = !empty($data['archived']);
    $userId = $wantsArchived
        ? requireUuid($data['user_id'] ?? '', 'user_id')
        : requireUuid($data['target_user_id'] ?? $data['user_id'] ?? '', 'user_id');
    if (!$userId) {
        return;
    }
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 50;
    $offset = isset($data['offset']) ? max(0, (int) $data['offset']) : 0;

    $viewerId = $data['user_id'] ?? null;
    $isSelf = ($viewerId !== null && $userId === $viewerId);

    $params = [
        'user_id' => 'eq.' . $userId,
        'is_deleted' => 'eq.false',
        'is_archived' => $wantsArchived ? 'eq.true' : 'eq.false',
        'select' => postsSelectColumns(),
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];

    // Your own group posts show on your own profile unconditionally. Someone
    // else's only show if you're in that group too — otherwise visiting the
    // author's profile would be a complete bypass of members-only visibility.
    // A single top-level or= is enough here; unlike the feed, this endpoint has
    // no pet-type filter to combine with.
    if (!$isSelf) {
        $myGroupIds = getMyGroupIds($viewerId);
        $params['or'] = empty($myGroupIds)
            ? '(group_id.is.null)'
            : '(group_id.is.null,group_id.in.(' . implode(',', $myGroupIds) . '))';
    }

    $res = supabaseRequest('GET', '/rest/v1/posts', $params);

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

    // Ownership is now decided in PHP rather than by scoping the query, because
    // a group admin may delete another member's post. That makes the id guard
    // load-bearing: it is the only thing standing between a malformed post_id
    // and the unscoped PATCH below.
    $postId = trim((string) $data['post_id']);
    if (!isValidUuid($postId)) {
        jsonError("Invalid post_id.", 400);
        return;
    }

    $postRes = supabaseRequest('GET', '/rest/v1/posts', [
        'id' => 'eq.' . $postId,
        'select' => 'id,user_id,group_id,media_url',
        'limit' => '1',
    ]);
    if (supabaseFailed($postRes) || empty($postRes['data'])) {
        sendSupabaseError("Post not found.", $postRes, 404);
        return;
    }
    $post = $postRes['data'][0];

    $canDelete = ($post['user_id'] === $data['user_id']);
    if (!$canDelete && !empty($post['group_id'])) {
        // Group admins moderate their own group. Not 'moderator' — that role
        // exists in the enum but is never assigned by any code path.
        $canDelete = (getGroupRole($post['group_id'], $data['user_id']) === 'admin');
    }
    if (!$canDelete) {
        // 404 rather than 403: a 403 would confirm the post exists to someone
        // who has no business knowing that.
        jsonError("Post not found.", 404);
        return;
    }

    // The user_id filter is deliberately absent from this PATCH. Leaving it in
    // would make an admin deleting someone else's post match zero rows —
    // which PostgREST answers 2xx, so supabaseFailed() stays false and the API
    // would cheerfully report "Post deleted." while nothing was deleted. The
    // authorization decision above is the only gate, by design.
    $res = supabaseRequest('PATCH', '/rest/v1/posts', [
        'id' => 'eq.' . $postId,
    ], ['is_deleted' => true, 'updated_at' => gmdate('c')], ['Prefer: return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete post.", $res);
        return;
    }

    // Runs for admin deletes too, removing another member's storage object.
    // Intended: the post is gone, its media should go with it.
    $mediaUrl = $post['media_url'] ?? null;
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

    // A post id on its own is not proof of entitlement: without this, a
    // non-member holding a group post's id could write likes into a
    // members-only thread, where they'd render for real members.
    if (!viewerCanSeePost($pid, $uid)) {
        jsonError("Post not found.", 404);
        return;
    }

    $check = supabaseRequest('GET', '/rest/v1/post_likes', [
        'user_id' => 'eq.' . $uid,
        'post_id' => 'eq.' . $pid,
        'select' => 'post_id,reaction',
        'limit' => '1',
    ]);
    if (supabaseFailed($check)) {
        sendSupabaseError("Failed to check like status.", $check);
        return;
    }

    $isLiked = false;
    $reaction = null;
    
    if (!empty($check['data'])) {
        $existing = $check['data'][0];
        $keys = array_filter(array_map('trim', explode(',', $existing['reaction'] ?? 'Liked')));
        $idx = array_search('Liked', $keys);
        
        if ($idx !== false) {
            unset($keys[$idx]);
            $isLiked = false;
        } else {
            $keys[] = 'Liked';
            $isLiked = true;
        }
        
        if (empty($keys)) {
            $del = supabaseRequest('DELETE', '/rest/v1/post_likes', ['user_id' => 'eq.' . $uid, 'post_id' => 'eq.' . $pid]);
            if (supabaseFailed($del)) {
                sendSupabaseError("Failed to unlike post.", $del);
                return;
            }
        } else {
            $reaction = implode(',', $keys);
            $upd = supabaseRequest('PATCH', '/rest/v1/post_likes', ['user_id' => 'eq.' . $uid, 'post_id' => 'eq.' . $pid], ['reaction' => $reaction]);
            if (supabaseFailed($upd)) {
                sendSupabaseError("Failed to update like status.", $upd);
                return;
            }
        }
    } else {
        $ins = supabaseRequest('POST', '/rest/v1/post_likes', [], ['user_id' => $uid, 'post_id' => $pid, 'reaction' => 'Liked'], ['Prefer: resolution=ignore-duplicates,return=minimal']);
        if (supabaseFailed($ins)) {
            sendSupabaseError("Failed to like post.", $ins);
            return;
        }
        $isLiked = true;
        $reaction = 'Liked';
        
        $postRes = supabaseRequest('GET', '/rest/v1/posts', ['id' => 'eq.' . $pid, 'select' => 'user_id', 'limit' => '1']);
        $ownerId = $postRes['data'][0]['user_id'] ?? null;
        if ($ownerId && $ownerId !== $uid) {
            $likerProfile = getAccountProfile($uid);
            $likerName = $likerProfile['pet_name'] ?? 'Someone';
            createNotification($ownerId, 'post_like', 'New like', "$likerName liked your post.", ['post_id' => $pid]);
        }
    }

    $summaryRes = supabaseRequest('GET', '/rest/v1/post_likes', [
        'post_id' => 'eq.' . $pid,
        'select' => 'post_id,reaction',
    ]);
    
    $likeCount = 0;
    $reactionSummary = [];
    $viewerReaction = null;
    
    foreach (($summaryRes['data'] ?? []) as $row) {
        $keys = array_filter(array_map('trim', explode(',', $row['reaction'] ?? 'Liked')));
        foreach ($keys as $r) {
            if ($r === 'Liked') {
                $likeCount++;
            } else {
                $reactionSummary[$r] = ($reactionSummary[$r] ?? 0) + 1;
            }
        }
    }
    
    if ($reaction) {
        $myKeys = array_filter(array_map('trim', explode(',', $reaction)));
        foreach ($myKeys as $k) {
            if ($k !== 'Liked') $viewerReaction = $k;
        }
    }

    jsonSuccess([
        "is_liked" => $isLiked, 
        "like_count" => $likeCount,
        "reaction" => $viewerReaction,
        "reaction_summary" => $reactionSummary
    ]);
}

function handleSetPostReaction($data)
{
    if (!requireFields($data, ['user_id', 'post_id', 'reaction']))
        return;
    $uid = $data['user_id'];
    $pid = $data['post_id'];
    $newReaction = trim($data['reaction']);

    if (!viewerCanSeePost($pid, $uid)) {
        jsonError("Post not found.", 404);
        return;
    }

    $check = supabaseRequest('GET', '/rest/v1/post_likes', [
        'user_id' => 'eq.' . $uid,
        'post_id' => 'eq.' . $pid,
        'select' => 'post_id,reaction',
        'limit' => '1',
    ]);
    
    $isLiked = false;
    $viewerReaction = null;
    $dbReactionStr = null;

    if (!empty($check['data'])) {
        $existing = $check['data'][0];
        $keys = array_filter(array_map('trim', explode(',', $existing['reaction'] ?? 'Liked')));
        
        $isLiked = in_array('Liked', $keys);
        
        // Remove any non-Liked reactions since only 1 reaction is allowed per user
        $newKeys = [];
        if ($isLiked) $newKeys[] = 'Liked';
        
        // If the new reaction is different from the old one, add it.
        // If it's the same, it means they are toggling it off (so we don't add it).
        $oldReaction = null;
        foreach ($keys as $k) {
            if ($k !== 'Liked') $oldReaction = $k;
        }
        
        if ($oldReaction !== $newReaction) {
            $newKeys[] = $newReaction;
            $viewerReaction = $newReaction;
        } else {
            $viewerReaction = null;
        }
        
        if (empty($newKeys)) {
            $del = supabaseRequest('DELETE', '/rest/v1/post_likes', ['user_id' => 'eq.' . $uid, 'post_id' => 'eq.' . $pid]);
        } else {
            $dbReactionStr = implode(',', $newKeys);
            $upd = supabaseRequest('PATCH', '/rest/v1/post_likes', ['user_id' => 'eq.' . $uid, 'post_id' => 'eq.' . $pid], ['reaction' => $dbReactionStr]);
        }
    } else {
        $dbReactionStr = $newReaction;
        $viewerReaction = $newReaction;
        $ins = supabaseRequest('POST', '/rest/v1/post_likes', [], ['user_id' => $uid, 'post_id' => $pid, 'reaction' => $dbReactionStr], ['Prefer: resolution=ignore-duplicates,return=minimal']);

        $postRes = supabaseRequest('GET', '/rest/v1/posts', ['id' => 'eq.' . $pid, 'select' => 'user_id', 'limit' => '1']);
        $ownerId = $postRes['data'][0]['user_id'] ?? null;
        if ($ownerId && $ownerId !== $uid) {
            $likerProfile = getAccountProfile($uid);
            $likerName = $likerProfile['pet_name'] ?? 'Someone';
            createNotification($ownerId, 'post_like', 'New reaction', "$likerName reacted to your post.", ['post_id' => $pid]);
        }
    }

    $summaryRes = supabaseRequest('GET', '/rest/v1/post_likes', [
        'post_id' => 'eq.' . $pid,
        'select' => 'post_id,reaction',
    ]);
    
    $likeCount = 0;
    $reactionSummary = [];
    foreach (($summaryRes['data'] ?? []) as $row) {
        $keys = array_filter(array_map('trim', explode(',', $row['reaction'] ?? 'Liked')));
        foreach ($keys as $r) {
            if ($r === 'Liked') {
                $likeCount++;
            } else {
                $reactionSummary[$r] = ($reactionSummary[$r] ?? 0) + 1;
            }
        }
    }

    jsonSuccess([
        "is_liked" => $isLiked, 
        "like_count" => $likeCount,
        "reaction" => $viewerReaction,
        "reaction_summary" => $reactionSummary
    ]);
}

function handleAddComment($data)
{
    if (!requireFields($data, ['post_id', 'content', 'user_id']))
        return;

    // This lookup used to happen after the insert, purely to find the owner to
    // notify. It moves ahead of the insert so the same round trip also decides
    // entitlement: commenting on a group post you're not in would otherwise
    // inject content into a closed thread and fire a notification at its
    // author. Net cost is unchanged — one query, used twice.
    $postRes = supabaseRequest('GET', '/rest/v1/posts', [
        'id' => 'eq.' . $data['post_id'],
        'select' => 'user_id,group_id',
        'limit' => '1',
    ]);
    if (supabaseFailed($postRes) || empty($postRes['data'])) {
        jsonError("Post not found.", 404);
        return;
    }
    $parentPost = $postRes['data'][0];
    if (!empty($parentPost['group_id']) && !isGroupMember($parentPost['group_id'], $data['user_id'])) {
        jsonError("Post not found.", 404);
        return;
    }

    $body = [
        'post_id' => $data['post_id'],
        'user_id' => $data['user_id'],
        'content' => cleanTextValue($data['content']),
        'created_at' => nowIsoUtc()
    ];
    if (!empty($data['parent_id'])) {
        $body['parent_id'] = $data['parent_id'];
    }

    $res = supabaseRequest('POST', '/rest/v1/post_comments', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to add comment.", $res);
        return;
    }

    $ownerId = $parentPost['user_id'] ?? null;
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

    // Easy one to miss, because a comment thread reads like a sub-resource
    // that inherits the parent's protection — it doesn't. Ungated, this is a
    // direct content leak of a members-only discussion to anyone holding the
    // post id.
    if (!viewerCanSeePost($postId, $data['user_id'] ?? null)) {
        jsonError("Post not found.", 404);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/post_comments', [
        'post_id' => 'eq.' . $postId,
        'is_deleted' => 'eq.false',
        'select' => 'id,post_id,user_id,content,parent_id,created_at,is_deleted',
        'order' => 'created_at.asc',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch comments.", $res);
        return;
    }

    $comments = $res['data'] ?? [];
    
    // Fetch comment likes for these comments
    $commentIds = array_column($comments, 'id');
    $commentLikesRes = [];
    if (!empty($commentIds)) {
        $likesRes = supabaseRequest('GET', '/rest/v1/comment_likes', [
            'comment_id' => 'in.(' . implode(',', $commentIds) . ')',
            'select' => 'comment_id,user_id',
        ]);
        if (!supabaseFailed($likesRes)) {
            $commentLikesRes = $likesRes['data'] ?? [];
        }
    }

    $likeCounts = [];
    $viewerLikes = [];
    $viewerId = $data['user_id'] ?? null;
    foreach ($commentLikesRes as $likeRow) {
        $cid = $likeRow['comment_id'];
        $likeCounts[$cid] = ($likeCounts[$cid] ?? 0) + 1;
        if ($viewerId && $likeRow['user_id'] === $viewerId) {
            $viewerLikes[$cid] = true;
        }
    }

    $profileMap = fetchProfilesMap(normalizeUuidList(array_column($comments, 'user_id')));
    $comments = array_map(function ($c) use ($profileMap, $likeCounts, $viewerLikes) {
        $author = $profileMap[$c['user_id']] ?? null;
        $c['author'] = $author ? ['user_id' => $c['user_id'], 'name' => $author['pet_name'] ?? 'Member', 'profile_photo_url' => $author['profile_photo_url'] ?? null] : ['user_id' => $c['user_id'], 'name' => 'Member'];
        $c['likes'] = $likeCounts[$c['id']] ?? 0;
        $c['is_liked_by_me'] = !empty($viewerLikes[$c['id']]);
        return $c;
    }, $comments);

    jsonSuccess(["comments" => $comments]);
}

function handleEditComment($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $commentId = requireUuid($data['comment_id'] ?? '', 'comment_id');

    $content = cleanTextValue($data['content'] ?? '', 2000);
    if ($content === '') {
        jsonError("Comment content cannot be empty.");
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/post_comments', [
        'id' => 'eq.' . $commentId,
        'user_id' => 'eq.' . $userId,
    ], ['content' => $content], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to edit comment.", $res);
        return;
    }

    $comment = $res['data'][0] ?? null;
    if (!$comment) {
        jsonError("Comment not found or not yours.", 404);
        return;
    }

    $profile = getAccountProfile($userId);
    $comment['author'] = ['user_id' => $userId, 'name' => $profile['pet_name'] ?? 'Member', 'profile_photo_url' => $profile['profile_photo_url'] ?? null];

    jsonSuccess(["comment" => $comment]);
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

function handleGetPostById($data)
{
    if (!requireFields($data, ['post_id']))
        return;
    
    $res = supabaseRequest('GET', '/rest/v1/posts', [
        'id' => 'eq.' . $data['post_id'],
        'is_deleted' => 'eq.false',
        'is_archived' => 'eq.false',
        'select' => postsSelectColumns(),
        'limit' => '1',
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch post.", $res);
        return;
    }

    if (empty($res['data'])) {
        jsonError("Post not found.", 404);
        return;
    }

    // copyPostLink() mints shareable ?post=<uuid> URLs, so this id travels
    // outside the group it belongs to. 404 rather than 403 — the latter would
    // confirm the post exists to a non-member.
    $groupId = $res['data'][0]['group_id'] ?? null;
    if ($groupId && !isGroupMember($groupId, $data['user_id'] ?? null)) {
        jsonError("Post not found.", 404);
        return;
    }

    $post = enrichPosts($res['data'], $data['user_id'] ?? null)[0];
    jsonSuccess(["post" => $post]);
}

/**
 * Posts belonging to one group — powers the Posts tab in the group chat modal.
 *
 * Note the membership check: its neighbour handleGetGroupMessages (groups.php)
 * has none, which is a known pre-existing hole, not a pattern to copy. This
 * one follows handleSendGroupMessage instead.
 */
function handleGetGroupPosts($data)
{
    if (!requireFields($data, ['user_id', 'group_id']))
        return;

    $groupId = trim((string) $data['group_id']);
    if (!isValidUuid($groupId)) {
        jsonError("Invalid group_id.", 400);
        return;
    }
    if (!isGroupMember($groupId, $data['user_id'])) {
        jsonError("You must be a member of this group to see its posts.", 403);
        return;
    }

    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 50)) : 20;
    $offset = isset($data['offset']) ? max(0, (int) $data['offset']) : 0;

    $res = supabaseRequest('GET', '/rest/v1/posts', [
        'group_id' => 'eq.' . $groupId,
        'is_deleted' => 'eq.false',
        'is_archived' => 'eq.false',
        'select' => postsSelectColumns(),
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch group posts.", $res);
        return;
    }

    jsonSuccess(["posts" => enrichPosts($res['data'] ?? [], $data['user_id'])]);
}

function handleArchivePost($data)
{
    if (!requireFields($data, ['user_id', 'post_id']))
        return;

    $res = supabaseRequest('PATCH', '/rest/v1/posts', [
        'id' => 'eq.' . $data['post_id'],
        'user_id' => 'eq.' . $data['user_id'],
    ], ['is_archived' => true, 'updated_at' => gmdate('c')], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to archive post.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Post not found or you're not the organizer.", 404);
        return;
    }

    jsonSuccess(["message" => "Post archived."]);
}

function handleUnarchivePost($data)
{
    if (!requireFields($data, ['user_id', 'post_id']))
        return;

    $res = supabaseRequest('PATCH', '/rest/v1/posts', [
        'id' => 'eq.' . $data['post_id'],
        'user_id' => 'eq.' . $data['user_id'],
    ], ['is_archived' => false, 'updated_at' => gmdate('c')], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to unarchive post.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Post not found.", 404);
        return;
    }

    jsonSuccess(["message" => "Post restored to your feed."]);
}

function handleReportPost($data)
{
    if (!requireFields($data, ['user_id', 'post_id']))
        return;
    
    $res = supabaseRequest('POST', '/rest/v1/post_reports', [], [
        'post_id' => $data['post_id'],
        'reporter_id' => $data['user_id'],
        'reason' => $data['reason'] ?? 'Inappropriate content',
    ], ['Prefer: return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to report post.", $res);
        return;
    }

    jsonSuccess(["message" => "Post reported successfully."]);
}

function handleGetActiveReactions($data)
{
    $petType = $data['pet_type'] ?? '';
    $breed = $data['breed'] ?? '';
    
    $query = [
        'is_active' => 'eq.true',
        'select' => '*',
        'order' => 'created_at.asc'
    ];

    if ($petType) {
        $query['or'] = '(pet_type.is.null,pet_type.eq.' . cleanPlainValue($petType, 40) . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/custom_reactions', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch reactions.", $res);
        return;
    }

    jsonSuccess(["reactions" => $res['data'] ?? []]);
}

function handleGetLinkPreview($data) {
    $url = $data['url'] ?? '';
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        jsonError('Invalid URL', 400);
        return;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $html = curl_exec($ch);
    curl_close($ch);
    
    if (!$html) {
        jsonSuccess(['preview' => null]);
        return;
    }
    
    $title = '';
    $description = '';
    $image = '';
    
    // Extract title
    if (preg_match('/<meta[^>]*property=["\'"]og:title["\'"][^>]*content=["\'"]([^"\']*)["\'"][^>]*>/i', $html, $matches) || 
        preg_match('/<meta[^>]*content=["\'"]([^"\']*)["\'"][^>]*property=["\'"]og:title["\'"][^>]*>/i', $html, $matches)) {
        $title = $matches[1];
    } elseif (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
        $title = $matches[1];
    }
    
    // Extract description
    if (preg_match('/<meta[^>]*property=["\'"]og:description["\'"][^>]*content=["\'"]([^"\']*)["\'"][^>]*>/i', $html, $matches) || 
        preg_match('/<meta[^>]*content=["\'"]([^"\']*)["\'"][^>]*property=["\'"]og:description["\'"][^>]*>/i', $html, $matches)) {
        $description = $matches[1];
    } elseif (preg_match('/<meta[^>]*name=["\'"]description["\'"][^>]*content=["\'"]([^"\']*)["\'"][^>]*>/i', $html, $matches) || 
              preg_match('/<meta[^>]*content=["\'"]([^"\']*)["\'"][^>]*name=["\'"]description["\'"][^>]*>/i', $html, $matches)) {
        $description = $matches[1];
    }
    
    // Extract image
    if (preg_match('/<meta[^>]*property=["\'"]og:image["\'"][^>]*content=["\'"]([^"\']*)["\'"][^>]*>/i', $html, $matches) || 
        preg_match('/<meta[^>]*content=["\'"]([^"\']*)["\'"][^>]*property=["\'"]og:image["\'"][^>]*>/i', $html, $matches)) {
        $image = $matches[1];
    }
    
    // Clean up
    if ($title) {
        $title = trim(html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($description) {
        $description = trim(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
    if ($image && !preg_match('/^https?:\/\//i', $image)) {
        $parsed = parse_url($url);
        $base = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? '');
        if (substr($image, 0, 1) === '/') {
            $image = $base . $image;
        } else {
            $image = $base . '/' . $image;
        }
    }
    
    if (!$title && !$image) {
        jsonSuccess(['preview' => null]);
        return;
    }
    
    jsonSuccess(['preview' => [
        'title' => $title, 
        'description' => $description, 
        'image' => $image, 
        'url' => $url
    ]]);
}


function handleEditPost($data)
{
    if (!requireFields($data, ['user_id', 'post_id', 'content']))
        return;

    $userId = requireUuid($data['user_id'], 'user_id');
    $postId = requireUuid($data['post_id'], 'post_id');
    $content = trim($data['content']);

    // Ensure it belongs to the user
    $check = supabaseRequest('GET', '/rest/v1/posts', [
        'id' => 'eq.' . $postId,
        'user_id' => 'eq.' . $userId,
        'select' => 'id'
    ]);
    
    if (supabaseFailed($check) || empty($check['data'])) {
        jsonError("Post not found or permission denied.", 403);
        return;
    }

    $updateData = ['content' => $content];
    if (isset($data['media_url'])) {
        $updateData['media_url'] = $data['media_url'];
    }

    $res = supabaseRequest('PATCH', '/rest/v1/posts', [
        'id' => 'eq.' . $postId,
        'user_id' => 'eq.' . $userId
    ], $updateData);

    if (supabaseFailed($res)) {
        jsonError("Could not update post.");
        return;
    }

    jsonSuccess(["message" => "Post updated successfully", "post_id" => $postId]);
}
