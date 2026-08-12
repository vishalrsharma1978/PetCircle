<?php
/**
 * Galleries: photo/video collections, optionally linked to an event.
 */

function handleCreateGallery($data)
{
    if (!requireFields($data, ['user_id', 'title']))
        return;

    $visibility = normalizeVisibility($data['visibility'] ?? 'public');

    $body = [
        'owner_user_id' => $data['user_id'],
        'event_id' => !empty($data['event_id']) ? $data['event_id'] : null,
        'title' => cleanNullableText($data['title'], 150),
        'description' => cleanNullableText($data['description'] ?? '', 1000),
        'visibility' => $visibility,
    ];

    $res = supabaseRequest('POST', '/rest/v1/gallery_collections', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create gallery.", $res);
        return;
    }

    $gallery = $res['data'][0];
    $gallery['items'] = [];

    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    if (!empty($items)) {
        $itemRows = [];
        foreach ($items as $index => $item) {
            if (empty($item['media_url']))
                continue;
            $mediaType = $item['media_type'] ?? 'image';
            $mediaType = in_array($mediaType, ['image', 'video'], true) ? $mediaType : 'image';
            $itemRows[] = [
                'gallery_id' => $gallery['id'],
                'media_url' => trim((string) $item['media_url']),
                'media_type' => $mediaType,
                'caption' => cleanNullableText($item['caption'] ?? '', 300),
                'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $index,
            ];
        }
        if (!empty($itemRows)) {
            $itemsRes = supabaseRequest('POST', '/rest/v1/gallery_items', [], $itemRows, ['Prefer: return=representation']);
            if (!supabaseFailed($itemsRes) && !empty($itemsRes['data'])) {
                $gallery['items'] = $itemsRes['data'];
            }
        }
    }

    jsonSuccess(["gallery" => $gallery]);
}

function handleUpdateGallery($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id']))
        return;

    $body = [];
    if (array_key_exists('title', $data))
        $body['title'] = cleanNullableText($data['title'], 150);
    if (array_key_exists('description', $data))
        $body['description'] = cleanNullableText($data['description'] ?? '', 1000);
    if (array_key_exists('event_id', $data))
        $body['event_id'] = !empty($data['event_id']) ? $data['event_id'] : null;
    if (array_key_exists('visibility', $data))
        $body['visibility'] = normalizeVisibility($data['visibility']);

    if (empty($body)) {
        jsonError("Nothing to update.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/gallery_collections', [
        'id' => 'eq.' . $data['gallery_id'],
        'owner_user_id' => 'eq.' . $data['user_id'],
    ], $body, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update gallery.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Gallery not found or you're not the owner.", 404);
        return;
    }

    $gallery = $res['data'][0];
    $itemsRes = supabaseRequest('GET', '/rest/v1/gallery_items', [
        'gallery_id' => 'eq.' . $data['gallery_id'],
        'select' => 'id,gallery_id,media_url,media_type,caption,sort_order,created_at',
        'order' => 'sort_order.asc,created_at.asc',
    ]);
    $gallery['items'] = supabaseFailed($itemsRes) ? [] : ($itemsRes['data'] ?? []);

    jsonSuccess(["gallery" => $gallery]);
}

function handleDeleteGalleryItem($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id', 'item_id']))
        return;

    $owns = supabaseRequest('GET', '/rest/v1/gallery_collections', [
        'id' => 'eq.' . $data['gallery_id'],
        'owner_user_id' => 'eq.' . $data['user_id'],
        'select' => 'id',
        'limit' => '1',
    ]);
    if (supabaseFailed($owns) || empty($owns['data'])) {
        jsonError("Gallery not found or you're not the owner.", 404);
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/gallery_items', [
        'id' => 'eq.' . $data['item_id'],
        'gallery_id' => 'eq.' . $data['gallery_id'],
    ], null, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to remove media item.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Media item not found.", 404);
        return;
    }

    $parsed = parsePublicStorageUrl($res['data'][0]['media_url'] ?? '');
    if ($parsed)
        supabaseStorageDelete($parsed['bucket'], $parsed['path']);

    jsonSuccess(["message" => "Media item removed."]);
}

function handleAddGalleryItem($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id', 'media_url']))
        return;

    $owns = supabaseRequest('GET', '/rest/v1/gallery_collections', [
        'id' => 'eq.' . $data['gallery_id'],
        'owner_user_id' => 'eq.' . $data['user_id'],
        'select' => 'id',
        'limit' => '1',
    ]);
    if (supabaseFailed($owns) || empty($owns['data'])) {
        jsonError("Gallery not found or you're not the owner.", 404);
        return;
    }

    $mediaType = $data['media_type'] ?? 'image';
    $mediaType = in_array($mediaType, ['image', 'video'], true) ? $mediaType : 'image';
    $body = [
        'gallery_id' => $data['gallery_id'],
        'media_url' => trim((string) $data['media_url']),
        'media_type' => $mediaType,
        'caption' => cleanNullableText($data['caption'] ?? '', 300),
        'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,
    ];

    $res = supabaseRequest('POST', '/rest/v1/gallery_items', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to add item to gallery.", $res);
        return;
    }

    jsonSuccess(["item" => $res['data'][0]]);
}

function handleGetGalleries($data)
{
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 40;
    $params = [
        'select' => 'id,owner_user_id,event_id,title,description,visibility,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ];
    if (!empty($data['owner_user_id'])) {
        $params['owner_user_id'] = 'eq.' . $data['owner_user_id'];
    } else {
        $params['visibility'] = 'eq.public';
    }

    $res = supabaseRequest('GET', '/rest/v1/gallery_collections', $params);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch galleries.", $res);
        return;
    }

    $galleries = $res['data'] ?? [];
    $galleryIds = array_column($galleries, 'id');
    $itemsByGallery = [];
    if (!empty($galleryIds)) {
        $itemsRes = supabaseRequest('GET', '/rest/v1/gallery_items', [
            'gallery_id' => 'in.(' . implode(',', $galleryIds) . ')',
            'select' => 'id,gallery_id,media_url,media_type,caption,sort_order,created_at',
            'order' => 'sort_order.asc,created_at.asc',
        ]);
        foreach (($itemsRes['data'] ?? []) as $item) {
            $itemsByGallery[$item['gallery_id']][] = $item;
        }
    }
    foreach ($galleries as &$g) {
        $items = $itemsByGallery[$g['id']] ?? [];
        $g['items'] = $items;
        $g['cover_url'] = $items[0]['media_url'] ?? null;
    }
    unset($g);

    jsonSuccess(["galleries" => $galleries]);
}

function handleGetGalleryItems($data)
{
    $galleryId = requireUuid($data['gallery_id'] ?? '', 'gallery_id');
    $res = supabaseRequest('GET', '/rest/v1/gallery_items', [
        'gallery_id' => 'eq.' . $galleryId,
        'select' => 'id,gallery_id,media_url,media_type,caption,sort_order,created_at',
        'order' => 'sort_order.asc,created_at.asc',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch gallery items.", $res);
        return;
    }

    jsonSuccess(["items" => $res['data'] ?? []]);
}

function handleDeleteGallery($data)
{
    if (!requireFields($data, ['user_id', 'gallery_id']))
        return;

    $itemsRes = supabaseRequest('GET', '/rest/v1/gallery_items', ['gallery_id' => 'eq.' . $data['gallery_id'], 'select' => 'media_url']);
    $res = supabaseRequest('DELETE', '/rest/v1/gallery_collections', [
        'id' => 'eq.' . $data['gallery_id'],
        'owner_user_id' => 'eq.' . $data['user_id'],
    ], null, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete gallery.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Gallery not found or you're not the owner.", 404);
        return;
    }

    supabaseRequest('DELETE', '/rest/v1/gallery_items', ['gallery_id' => 'eq.' . $data['gallery_id']]);
    foreach (($itemsRes['data'] ?? []) as $item) {
        $parsed = parsePublicStorageUrl($item['media_url'] ?? '');
        if ($parsed)
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
    }

    jsonSuccess(["message" => "Gallery deleted."]);
}
