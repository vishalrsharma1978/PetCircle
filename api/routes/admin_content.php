<?php
/**
 * Admin dashboard — Posts/Events/Galleries moderation panels. Ported from
 * eSamaj's admin_core.php equivalents, with the free-text "religion" filter
 * replaced by pet_type (posts/events already carry it; gallery_collections
 * doesn't, so gallery pet_type filtering is done via the owner's profile).
 */

function handleAdminListPosts($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data, 25, 100);
    $offset = adminOffset($data);

    $query = [
        // Deliberately narrower than postsSelectColumns() (no updated_at /
        // hashtags — the moderation table doesn't show them), but group_id is
        // needed: without it enrichPosts() emits group:null and a group post
        // would be indistinguishable from an ordinary one in the admin list.
        // Global admins moderate every post, group ones included, so this
        // panel is not membership-filtered.
        'select' => 'id,user_id,group_id,content,media_url,post_type,pet_type,breed,is_deleted,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    $typeFilter = cleanPlainValue($data['type_filter'] ?? '', 20);
    if (in_array($typeFilter, ['text', 'image', 'video', 'poll', 'event'], true)) {
        $query['post_type'] = 'eq.' . $typeFilter;
    }
    $petTypeFilter = cleanPlainValue($data['pet_type'] ?? '', 40);
    if ($petTypeFilter !== '') {
        $query['pet_type'] = 'eq.' . $petTypeFilter;
    }
    $search = cleanPlainValue($data['search'] ?? '', 200);
    if ($search !== '') {
        $query['content'] = 'ilike.*' . $search . '*';
    }
    $statusFilter = cleanPlainValue($data['status_filter'] ?? '', 20);
    if ($statusFilter === 'deleted') {
        $query['is_deleted'] = 'eq.true';
    } elseif ($statusFilter !== 'all') {
        $query['is_deleted'] = 'eq.false';
    }

    $res = supabaseRequest('GET', '/rest/v1/posts', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to list posts.", $res);
        return;
    }

    $posts = enrichPosts($res['data'] ?? [], null);
    jsonSuccess(['posts' => $posts, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminModeratePost($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $postId = requireUuid($data['post_id'] ?? '', 'post_id');
    $op = strtolower(trim((string) ($data['op'] ?? 'hide')));
    if (!in_array($op, ['hide', 'restore'], true)) {
        jsonError("op must be hide or restore.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/posts', ['id' => 'eq.' . $postId], [
        'is_deleted' => $op === 'hide',
        'updated_at' => nowIsoUtc(),
    ], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to moderate post.", $res);
        return;
    }
    jsonSuccess(['op' => $op, 'applied' => true]);
}

function handleAdminListEvents($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data, 25, 100);
    $offset = adminOffset($data);

    $query = [
        'select' => 'id,title,description,event_date,event_time,location,is_online,pet_type,breed,created_by,created_at',
        'order' => 'event_date.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    $petTypeFilter = cleanPlainValue($data['pet_type'] ?? '', 40);
    if ($petTypeFilter !== '') {
        $query['pet_type'] = 'eq.' . $petTypeFilter;
    }
    $search = cleanPlainValue($data['search'] ?? '', 200);
    if ($search !== '') {
        $query['title'] = 'ilike.*' . $search . '*';
    }

    $res = supabaseRequest('GET', '/rest/v1/events', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to list events.", $res);
        return;
    }

    $events = $res['data'] ?? [];
    $profileMap = fetchProfilesMap(normalizeUuidList(array_column($events, 'created_by')));
    foreach ($events as &$e) {
        $e['organizer'] = $profileMap[$e['created_by']] ?? null;
    }
    unset($e);

    jsonSuccess(['events' => $events, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminDeleteEvent($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $eventId = requireUuid($data['event_id'] ?? '', 'event_id');

    supabaseRequest('DELETE', '/rest/v1/event_rsvps', ['event_id' => 'eq.' . $eventId]);
    $res = supabaseRequest('DELETE', '/rest/v1/events', ['id' => 'eq.' . $eventId]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete event.", $res);
        return;
    }
    jsonSuccess(['deleted' => true]);
}

function handleAdminListGalleries($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data, 25, 100);
    $offset = adminOffset($data);

    $query = [
        'select' => 'id,owner_user_id,event_id,title,description,visibility,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    $visibilityFilter = cleanPlainValue($data['visibility_filter'] ?? '', 20);
    if (in_array($visibilityFilter, ['public', 'breed', 'pet_type', 'private'], true)) {
        $query['visibility'] = 'eq.' . $visibilityFilter;
    }
    $search = cleanPlainValue($data['search'] ?? '', 200);
    if ($search !== '') {
        $query['title'] = 'ilike.*' . $search . '*';
    }

    $res = supabaseRequest('GET', '/rest/v1/gallery_collections', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to list galleries.", $res);
        return;
    }

    $galleries = $res['data'] ?? [];
    $ownerIds = normalizeUuidList(array_column($galleries, 'owner_user_id'));
    $profileMap = fetchProfilesMap($ownerIds);

    $petTypeFilter = cleanPlainValue($data['pet_type'] ?? '', 40);
    if ($petTypeFilter !== '') {
        $galleries = array_values(array_filter($galleries, function ($g) use ($profileMap, $petTypeFilter) {
            return ($profileMap[$g['owner_user_id']]['pet_type'] ?? '') === $petTypeFilter;
        }));
    }

    $galleryIds = array_column($galleries, 'id');
    $itemCounts = [];
    if (!empty($galleryIds)) {
        $itemsRes = supabaseRequest('GET', '/rest/v1/gallery_items', [
            'gallery_id' => 'in.(' . implode(',', $galleryIds) . ')',
            'select' => 'gallery_id',
        ]);
        foreach (($itemsRes['data'] ?? []) as $row) {
            $itemCounts[$row['gallery_id']] = ($itemCounts[$row['gallery_id']] ?? 0) + 1;
        }
    }

    foreach ($galleries as &$g) {
        $g['owner'] = $profileMap[$g['owner_user_id']] ?? null;
        $g['item_count'] = $itemCounts[$g['id']] ?? 0;
    }
    unset($g);

    jsonSuccess(['galleries' => $galleries, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminDeleteGallery($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $galleryId = requireUuid($data['gallery_id'] ?? '', 'gallery_id');

    $itemsRes = supabaseRequest('GET', '/rest/v1/gallery_items', ['gallery_id' => 'eq.' . $galleryId, 'select' => 'media_url']);
    $res = supabaseRequest('DELETE', '/rest/v1/gallery_collections', ['id' => 'eq.' . $galleryId]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete gallery.", $res);
        return;
    }

    supabaseRequest('DELETE', '/rest/v1/gallery_items', ['gallery_id' => 'eq.' . $galleryId]);
    foreach (($itemsRes['data'] ?? []) as $item) {
        $parsed = parsePublicStorageUrl($item['media_url'] ?? '');
        if ($parsed)
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
    }

    jsonSuccess(['deleted' => true]);
}
