<?php
/**
 * Pet Community Hub — replaces eSamaj's "Pravachan Hub" (content_hub.php),
 * which was 100% hardcoded sample/demo data (placeholder MP4/MP3 clips
 * mislabeled with real religious teachers' names, plus a curated list of
 * external YouTube channel IDs). None of that is real content and none of
 * it belongs in a pet app, so rather than inventing an equivalent fake pet
 * version, this is a genuinely live, DB-backed aggregation over data that
 * already exists on the platform: trending posts, spotlighted care guides,
 * active groups, upcoming events, and fresh galleries. No external embeds,
 * no placeholder content, no fabricated URLs.
 */

function handleGetCommunityHub($data)
{
    $petType = cleanPlainValue($data['pet_type'] ?? '', 80);
    $viewerId = $data['user_id'] ?? null;

    jsonSuccess([
        'trending_posts' => communityHubTrendingPosts($petType, $viewerId),
        'spotlight_guides' => communityHubSpotlightGuides(),
        'active_groups' => communityHubActiveGroups($petType),
        'upcoming_events' => communityHubUpcomingEvents($petType),
        'fresh_galleries' => communityHubFreshGalleries(),
    ]);
}

function communityHubTrendingPosts($petType, $viewerId)
{
    $query = [
        'select' => 'id,user_id,content,media_url,post_type,pet_type,breed,is_deleted,created_at,updated_at,hashtags',
        'is_deleted' => 'eq.false',
        'order' => 'created_at.desc',
        'limit' => '40',
    ];
    if ($petType !== '') {
        $query['or'] = '(pet_type.is.null,pet_type.eq.' . $petType . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/posts', $query);
    if (supabaseFailed($res)) {
        return [];
    }

    $posts = enrichPosts($res['data'] ?? [], $viewerId);
    usort($posts, function ($a, $b) {
        $scoreA = ($a['like_count'] ?? 0) * 2 + ($a['comment_count'] ?? 0);
        $scoreB = ($b['like_count'] ?? 0) * 2 + ($b['comment_count'] ?? 0);
        return $scoreB <=> $scoreA;
    });

    return array_slice($posts, 0, 6);
}

function communityHubSpotlightGuides()
{
    $rows = getCareGuideRows();
    if (!$rows['ok']) {
        return [];
    }

    $bestPerCategory = [];
    foreach ($rows['data'] as $row) {
        $category = normalizeCareGuideCategoryKey($row['service_type'] ?? '');
        $sortOrder = intval($row['sort_order'] ?? 100);
        if (!isset($bestPerCategory[$category]) || $sortOrder < $bestPerCategory[$category]['sort_order']) {
            $bestPerCategory[$category] = $row;
            $bestPerCategory[$category]['sort_order'] = $sortOrder;
        }
    }

    return array_map('normaliseCareGuideRowForFrontend', array_values($bestPerCategory));
}

function communityHubActiveGroups($petType)
{
    $params = [
        'select' => 'id,name,description,avatar_url,pet_type,breed,is_private,scope,created_by,created_at',
        'order' => 'created_at.desc',
        'limit' => '30',
    ];
    if ($petType !== '') {
        $params['or'] = '(scope.eq.global,pet_type.eq.' . $petType . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/groups', $params);
    if (supabaseFailed($res)) {
        return [];
    }

    $groups = $res['data'] ?? [];
    $groupIds = array_column($groups, 'id');
    $counts = [];
    if (!empty($groupIds)) {
        $countsRes = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'in.(' . implode(',', $groupIds) . ')',
            'select' => 'group_id',
        ]);
        foreach (($countsRes['data'] ?? []) as $row) {
            $counts[$row['group_id']] = ($counts[$row['group_id']] ?? 0) + 1;
        }
    }

    foreach ($groups as &$g) {
        $g['member_count'] = $counts[$g['id']] ?? 0;
    }
    unset($g);

    usort($groups, function ($a, $b) {
        return ($b['member_count'] ?? 0) <=> ($a['member_count'] ?? 0);
    });

    return array_slice($groups, 0, 4);
}

function communityHubUpcomingEvents($petType)
{
    $params = [
        'select' => 'id,title,description,event_date,event_time,location,is_online,pet_type,breed,banner_url,created_by',
        'event_date' => 'gte.' . gmdate('Y-m-d'),
        'order' => 'event_date.asc',
        'limit' => '30',
    ];

    $res = supabaseRequest('GET', '/rest/v1/events', $params);
    if (supabaseFailed($res)) {
        return [];
    }

    $events = $res['data'] ?? [];
    if ($petType !== '') {
        $events = array_values(array_filter($events, function ($e) use ($petType) {
            return empty($e['pet_type']) || $e['pet_type'] === $petType;
        }));
    }

    return array_slice($events, 0, 4);
}

function communityHubFreshGalleries()
{
    $params = [
        'select' => 'id,owner_user_id,event_id,title,description,visibility,created_at',
        'visibility' => 'eq.public',
        'order' => 'created_at.desc',
        'limit' => '6',
    ];

    $res = supabaseRequest('GET', '/rest/v1/gallery_collections', $params);
    if (supabaseFailed($res)) {
        return [];
    }

    $galleries = $res['data'] ?? [];

    $galleryIds = array_column($galleries, 'id');
    $covers = [];
    if (!empty($galleryIds)) {
        $itemsRes = supabaseRequest('GET', '/rest/v1/gallery_items', [
            'gallery_id' => 'in.(' . implode(',', $galleryIds) . ')',
            'select' => 'gallery_id,media_url,sort_order',
            'order' => 'sort_order.asc',
        ]);
        foreach (($itemsRes['data'] ?? []) as $row) {
            if (!isset($covers[$row['gallery_id']])) {
                $covers[$row['gallery_id']] = $row['media_url'];
            }
        }
    }

    foreach ($galleries as &$g) {
        $g['cover_url'] = $covers[$g['id']] ?? null;
    }
    unset($g);

    return $galleries;
}
