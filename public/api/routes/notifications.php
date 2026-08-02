<?php

function createNotification($userId, $type, $title, $body, $data = [])
{
    if (!$userId || !$type) {
        return ['code' => 400, 'data' => ['message' => 'Missing notification user or type.']];
    }

    $res = supabaseRequest('POST', '/rest/v1/notifications', [], [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'data' => empty($data) ? null : $data,
        'is_read' => false,
    ], ['Prefer: return=minimal']);

    // Automatically mirror the notification to WhatsApp for opted-in users.
    // High-frequency / low-value types are skipped to avoid spamming the channel.
    $whatsappSkipTypes = ['direct_message', 'friend_request_sent'];
    if (($res['code'] ?? 500) < 300 && !in_array($type, $whatsappSkipTypes, true)) {
        $waMessage = trim($title . (strlen(trim((string) $body)) ? "\n\n" . $body : ''));
        if ($waMessage !== '') {
            notifyUserWhatsApp($userId, $waMessage);
        }
    }

    return $res;
}

function handleGetNotifications($data)
{
    $userId = $data['user_id'] ?? '';
    if (!$userId) {
        jsonError("user_id required.", 400);
        return;
    }

    $limit = isset($data['limit']) ? max(1, min(100, intval($data['limit']))) : 30;
    $res = supabaseRequest('GET', '/rest/v1/notifications', [
        'user_id' => 'eq.' . $userId,
        'type' => 'neq.friend_request_sent',
        'select' => 'id,user_id,type,title,body,data,is_read,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch notifications.", $res);
        return;
    }

    $notifications = $res['data'] ?? [];
    $friendshipIds = [];
    foreach ($notifications as $notification) {
        $friendshipId = $notification['data']['friendship_id'] ?? null;
        if ($friendshipId) {
            $friendshipIds[] = $friendshipId;
        }
    }

    $friendshipStatuses = [];
    $friendshipIds = normalizeUuidList($friendshipIds);
    if (!empty($friendshipIds)) {
        $friendshipRes = supabaseRequest('GET', '/rest/v1/friendships', [
            'id' => 'in.(' . implode(',', $friendshipIds) . ')',
            'select' => 'id,status'
        ]);

        if (!supabaseFailed($friendshipRes)) {
            foreach (($friendshipRes['data'] ?? []) as $friendship) {
                if (!empty($friendship['id'])) {
                    $friendshipStatuses[$friendship['id']] = $friendship['status'] ?? null;
                }
            }
        }
    }

    $unreadCount = 0;
    foreach ($notifications as &$notification) {
        $friendshipId = $notification['data']['friendship_id'] ?? null;
        if ($friendshipId) {
            $notification['friendship_status'] = $friendshipStatuses[$friendshipId] ?? 'removed';
        }
        if (empty($notification['is_read'])) {
            $unreadCount++;
        }
    }
    unset($notification);

    jsonSuccess([
        "notifications" => $notifications,
        "unread_count" => $unreadCount,
    ]);
}

function handleMarkNotificationRead($data)
{
    $userId = $data['user_id'] ?? '';
    $notificationId = $data['notification_id'] ?? '';

    if (!$userId || !$notificationId) {
        jsonError("user_id and notification_id required.", 400);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/notifications', [
        'id' => 'eq.' . $notificationId,
        'user_id' => 'eq.' . $userId,
    ], [
        'is_read' => true,
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to mark notification read.", $res);
        return;
    }

    jsonSuccess(["notification" => $res['data'][0] ?? null]);
}

function normalizeEventPayload($data)
{
    $title = cleanTextValue($data['title'] ?? '', 180);
    $description = cleanTextValue($data['description'] ?? $data['desc'] ?? '', 3000);
    $location = cleanTextValue($data['location'] ?? '', 300);

    $allowedFrequencies = ['none', 'daily', 'weekly', 'monthly'];
    $frequency = strtolower(trim((string) ($data['recurrence_frequency'] ?? $data['frequency'] ?? 'none')));
    if (!in_array($frequency, $allowedFrequencies, true)) {
        $frequency = 'none';
    }

    $allowedVisibility = ['public', 'breed', 'pet_type', 'invite_only'];
    $visibility = strtolower(trim((string) ($data['visibility'] ?? 'public')));
    if (!in_array($visibility, $allowedVisibility, true)) {
        $visibility = 'public';
    }

    return [
        'title' => $title,
        'description' => $description === '' ? null : $description,
        'event_date' => $data['event_date'] ?? $data['date'] ?? null,
        'event_time' => $data['event_time'] ?? $data['time'] ?? null,
        'location' => $location === '' ? null : $location,
        'is_online' => isset($data['is_online']) ? (bool) $data['is_online'] : !empty($data['meeting_url']) || !empty($data['link']),
        'meeting_url' => trim((string) ($data['meeting_url'] ?? $data['link'] ?? '')) ?: null,
        'pet_type' => ($pet_type = cleanPlainValue($data['pet_type'] ?? '', 80)) === '' ? null : $pet_type,
        'breed' => ($breed = cleanPlainValue($data['breed'] ?? '', 120)) === '' ? null : $breed,
        'banner_url' => trim((string) ($data['banner_url'] ?? '')) ?: null,
        'recurrence_frequency' => $frequency,
        
    ];
}

function handleSaveEvent($data)
{
    if (empty($data['user_id']) || empty($data['title'])) {
        jsonError("user_id and title required.", 400);
        return;
    }

    $body = normalizeEventPayload($data);
    if ($body['title'] === '') {
        jsonError("Event title cannot be empty.", 400);
        return;
    }

    if (!empty($data['event_id'])) {
        $res = supabaseRequest('PATCH', '/rest/v1/events', [
            'id' => 'eq.' . $data['event_id'],
            'created_by' => 'eq.' . $data['user_id'],
        ], $body, ['Prefer: return=representation']);
    } else {
        $body['created_by'] = $data['user_id'];
        $res = supabaseRequest('POST', '/rest/v1/events', [], $body, ['Prefer: return=representation']);
    }

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to save event.", $res);
        return;
    }

    $event = $res['data'][0];
    $rawInviteeIds = $data['invitee_ids'] ?? [];
    if (is_string($rawInviteeIds)) {
        $rawInviteeIds = array_filter(array_map('trim', explode(',', $rawInviteeIds)));
    }
    $inviteeIds = normalizeUuidList(is_array($rawInviteeIds) ? $rawInviteeIds : []);

    $rawGroupIds = $data['group_ids'] ?? [];
    if (is_string($rawGroupIds)) {
        $rawGroupIds = array_filter(array_map('trim', explode(',', $rawGroupIds)));
    }
    $groupIds = normalizeUuidList(is_array($rawGroupIds) ? $rawGroupIds : []);

    if (!empty($groupIds)) {
        $groupMemberRows = getMemberRowsForGroups($groupIds);
        $extractedIds = array_column($groupMemberRows ?? [], 'user_id');
        $inviteeIds = uniqueUserIds(array_merge($inviteeIds, $extractedIds));
    }

    $invitesCreated = 0;
    $eventId = $event['id'] ?? null;
    $eventDate = $event['event_date'] ?? null;
    $eventTitle = $event['title'] ?? 'Breed event';

    foreach ($inviteeIds as $inviteeId) {
        if ($inviteeId === strtolower((string) $data['user_id']))
            continue;
        $notification = createNotification(
            $inviteeId,
            'event_invite',
            'Event invitation',
            'You were invited to ' . $eventTitle . ($eventDate ? ' on ' . $eventDate : '') . '.',
            [
                'event_id' => $eventId,
                'event_title' => $eventTitle,
                'event_date' => $eventDate,
                'inviter_id' => $data['user_id'],
                'meeting_url' => $event['meeting_url'] ?? null,
            ]
        );

        if (!supabaseFailed($notification)) {
            $invitesCreated++;
        }
    }

    // Persist the invitee list so invite-only events can gate access, and so
    // the owner can re-open the event later to add more people or see who is
    // already invited. Best-effort: requires the event_invitees table
    // (see migrations/001_event_invitees_visibility.sql).
    if ($eventId && !empty($inviteeIds)) {
        $inviteeRows = [];
        foreach ($inviteeIds as $inviteeId) {
            $inviteeRows[] = ['event_id' => $eventId, 'user_id' => $inviteeId];
        }
        supabaseRequest(
            'POST',
            '/rest/v1/event_invitees',
            ['on_conflict' => 'event_id,user_id'],
            $inviteeRows,
            ['Prefer: resolution=ignore-duplicates,return=minimal']
        );
    }

    jsonSuccess([
        "event" => $event,
        "invitee_ids" => $inviteeIds,
        "invites" => [
            "requested" => count($inviteeIds),
            "created" => $invitesCreated,
        ],
    ]);
}

function handleDeleteEvent($data)
{
    if (empty($data['event_id'])) {
        jsonError("event_id required.", 400);
        return;
    }

    $eventId = cleanPlainValue($data['event_id'], 80);

    $query = ['id' => 'eq.' . $eventId];
    if (!empty($data['user_id'])) {
        $query['created_by'] = 'eq.' . $data['user_id'];
    }

    // Before deleting the event, clean up dependencies that might cause foreign key constraints
    supabaseRequest('DELETE', '/rest/v1/gallery_collections', ['event_id' => 'eq.' . $eventId]);
    supabaseRequest('DELETE', '/rest/v1/call_sessions', ['group_id' => 'eq.' . $eventId, 'target_type' => 'eq.group']);
    supabaseRequest('DELETE', '/rest/v1/group_messages', ['group_id' => 'eq.' . $eventId]);
    supabaseRequest('DELETE', '/rest/v1/groups', ['id' => 'eq.' . $eventId]);

    $res = supabaseRequest('DELETE', '/rest/v1/events', $query);

    if (supabaseFailed($res)) {
        file_put_contents(__DIR__ . '/debug_delete_error.txt', json_encode(['data' => $data, 'query' => $query, 'res' => $res]));
        sendSupabaseError("Failed to delete event.", $res);
        return;
    }

    jsonSuccess(["event_id" => $data['event_id']]);
}

function handleGetEvents($data)
{
    $select = 'id,title,description,event_date,event_time,location,is_online,meeting_url,pet_type,breed,banner_url,created_by,created_at,updated_at';
    $query = [
        'select' => $select,
        'order' => 'event_date.asc,event_time.asc',
        'limit' => isset($data['limit']) ? (string) max(1, min((int) $data['limit'], 100)) : '100',
    ];

    $userId = cleanPlainValue($data['user_id'] ?? '', 80);

    if (!empty($data['event_id']))
        $query['id'] = 'eq.' . $data['event_id'];
    if (!empty($data['created_by']))
        $query['created_by'] = 'eq.' . $data['created_by'];
    if (!empty($data['from_date']))
        $query['event_date'] = 'gte.' . $data['from_date'];

    $res = supabaseRequest('GET', '/rest/v1/events', $query);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch events.", $res);
        return;
    }

    $events = $res['data'] ?? [];

    // In the general feed, also surface (a) invite-only events this user is
    // invited to and (b) the user's own events — both are excluded from the
    // audience query above when invite-only.
    if (empty($data['event_id']) && empty($data['created_by']) && $userId !== '') {
        $invRes = supabaseRequest('GET', '/rest/v1/event_invitees', [
            'select' => 'event_id',
            'user_id' => 'eq.' . $userId,
        ]);
        $invitedEventIds = supabaseFailed($invRes) ? [] : array_values(array_unique(array_column($invRes['data'] ?? [], 'event_id')));
        $alreadyHave = array_column($events, 'id');
        $missing = array_values(array_diff($invitedEventIds, $alreadyHave));
        if (!empty($missing)) {
            $extraRes = supabaseRequest('GET', '/rest/v1/events', [
                'select' => $select,
                'id' => 'in.(' . implode(',', $missing) . ')',
                'order' => 'event_date.asc,event_time.asc',
            ]);
            if (!supabaseFailed($extraRes) && !empty($extraRes['data'])) {
                $events = array_merge($events, $extraRes['data']);
            }
        }

        // The creator should always see their own events on the calendar.
        $ownRes = supabaseRequest('GET', '/rest/v1/events', [
            'select' => $select,
            'created_by' => 'eq.' . $userId,
            'order' => 'event_date.asc,event_time.asc',
        ]);
        if (!supabaseFailed($ownRes) && !empty($ownRes['data'])) {
            $haveIds = array_column($events, 'id');
            foreach ($ownRes['data'] as $ownEvent) {
                if (!in_array($ownEvent['id'] ?? null, $haveIds, true)) {
                    $events[] = $ownEvent;
                }
            }
        }
    }

    // Attach the persisted invitee list so the event owner can pre-select /
    // add invitees when editing. Best-effort (requires event_invitees table).
    $eventIds = array_filter(array_column($events, 'id'));
    if (!empty($eventIds)) {
        $inviteeRes = supabaseRequest('GET', '/rest/v1/event_invitees', [
            'select' => 'event_id,user_id',
            'event_id' => 'in.(' . implode(',', $eventIds) . ')',
        ]);
        $inviteesByEvent = [];
        if (!supabaseFailed($inviteeRes)) {
            foreach ($inviteeRes['data'] ?? [] as $row) {
                $inviteesByEvent[$row['event_id']][] = $row['user_id'];
            }
        }
        foreach ($events as &$ev) {
            $ev['invitee_ids'] = $inviteesByEvent[$ev['id'] ?? ''] ?? [];
        }
        unset($ev);
    }

    $profileMap = fetchProfilesMap(array_column($events, 'created_by'));

    foreach ($events as &$event) {
        $profile = $profileMap[$event['created_by'] ?? ''] ?? [];
        $event['creator'] = profileSummary($profile);
        $event['pet_type'] = $event['pet_type'] ?? '';
        $event['breed'] = $event['breed'] ?? '';
    }
    unset($event);

    jsonSuccess(["events" => $events]);
}

function handleGetEventAnalytics($data)
{
    // Monthly event counts for the last 12 months
    $attendanceRes = supabaseRequest('GET', '/rest/v1/events', [
        'select' => 'id,event_date,attendees_count',
        'event_date' => 'gte.' . date('Y-m-d', strtotime('-12 months')),
        'order' => 'event_date.asc',
    ]);

    $monthly = [];
    foreach (($attendanceRes['data'] ?? []) as $ev) {
        $month = substr($ev['event_date'] ?? '', 0, 7); // YYYY-MM
        if (!$month)
            continue;
        if (!isset($monthly[$month])) {
            $monthly[$month] = ['month' => $month, 'events' => 0, 'attendees' => 0];
        }
        $monthly[$month]['events']++;
        $monthly[$month]['attendees'] += (int) ($ev['attendees_count'] ?? 0);
    }
    $monthlyList = array_values($monthly);

    // Pet Type demographics from profiles (for doughnut chart)
    $demoRes = supabaseRequest('GET', '/rest/v1/profiles', [
        'select' => 'pet_type',
    ]);

    $pet_typeCounts = [];
    foreach (($demoRes['data'] ?? []) as $p) {
        $rel = $p['pet_type'] ?? 'Unknown';
        if ($rel === '' || $rel === null)
            $rel = 'Unknown';
        $pet_typeCounts[$rel] = ($pet_typeCounts[$rel] ?? 0) + 1;
    }
    arsort($pet_typeCounts);
    $demographics = [];
    foreach ($pet_typeCounts as $rel => $count) {
        $demographics[] = ['pet_type' => $rel, 'count' => $count];
    }

    jsonSuccess([
        'monthly_attendance' => $monthlyList,
        'pet_type_demographics' => $demographics,
    ]);
}