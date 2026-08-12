<?php
/**
 * Events: create/update/list/delete, RSVP, an auto-linked discussion group
 * (created the first time anyone RSVPs "going", matching eSamaj's own
 * ensureEventGroup() pattern but via a real linked_group_id FK column
 * instead of eSamaj's deterministic-id hack), friend/group invites with
 * notifications, and pet_type-bucketed analytics. Deliberately no Zoom/live
 * streaming wiring — meeting_url is a plain link field for now.
 */

function eventInputFields($data, $profile)
{
    $eventDate = cleanDateValue($data['event_date'] ?? '');
    if (!$eventDate) {
        jsonError("A valid event_date (YYYY-MM-DD) is required.", 400);
        return null;
    }
    return [
        'title' => cleanNullableText($data['title'], 200),
        'description' => cleanNullableText($data['description'] ?? '', 2000),
        'event_date' => $eventDate,
        'event_time' => cleanNullableText($data['event_time'] ?? '', 8),
        'location' => cleanNullableText($data['location'] ?? '', 300),
        'is_online' => !empty($data['is_online']),
        'meeting_url' => cleanNullableText($data['meeting_url'] ?? '', 500),
        'pet_type' => cleanNullableText($data['pet_type'] ?? ($profile['pet_type'] ?? ''), 80),
        'breed' => cleanNullableText($data['breed'] ?? '', 140),
        'banner_url' => cleanNullableText($data['banner_url'] ?? '', 500),
    ];
}

// Resolves invite_friend_ids + invite_group_ids (expanded to member user_ids
// via group_members) into a deduped list, excluding the event creator.
function resolveEventInviteeIds($data, $creatorId)
{
    $ids = [];
    foreach (normalizeUuidList($data['invite_friend_ids'] ?? []) as $id) {
        $ids[$id] = true;
    }
    $groupIds = normalizeUuidList($data['invite_group_ids'] ?? []);
    if (!empty($groupIds)) {
        $memRes = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'in.(' . implode(',', $groupIds) . ')',
            'select' => 'user_id',
        ]);
        foreach (($memRes['data'] ?? []) as $row) {
            $ids[$row['user_id']] = true;
        }
    }
    unset($ids[$creatorId]);
    return array_keys($ids);
}

function notifyEventInvitees($event, $inviteeIds, $inviterName)
{
    foreach ($inviteeIds as $userId) {
        createNotification(
            $userId,
            'event_invite',
            'You\'re invited: ' . ($event['title'] ?? 'an event'),
            ($inviterName ?: 'A friend') . ' invited you to ' . ($event['title'] ?? 'an event') . ' on ' . ($event['event_date'] ?? ''),
            ['event_id' => $event['id']]
        );
    }
}

function handleCreateEvent($data)
{
    if (!requireFields($data, ['user_id', 'title']))
        return;

    $profile = getAccountProfile($data['user_id']);
    $fields = eventInputFields($data, $profile);
    if ($fields === null)
        return;

    $body = array_merge($fields, ['created_by' => $data['user_id']]);

    $res = supabaseRequest('POST', '/rest/v1/events', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create event.", $res);
        return;
    }

    $event = $res['data'][0];
    supabaseRequest('POST', '/rest/v1/event_rsvps', [], [
        'event_id' => $event['id'],
        'user_id' => $data['user_id'],
        'status' => 'going',
    ], ['Prefer: return=minimal']);

    $inviteeIds = resolveEventInviteeIds($data, $data['user_id']);
    if (!empty($inviteeIds)) {
        notifyEventInvitees($event, $inviteeIds, $profile['pet_name'] ?? null);
    }

    jsonSuccess(["event" => $event]);
}

function handleUpdateEvent($data)
{
    if (!requireFields($data, ['user_id', 'event_id']))
        return;

    $profile = getAccountProfile($data['user_id']);
    $fields = eventInputFields($data, $profile);
    if ($fields === null)
        return;

    $res = supabaseRequest('PATCH', '/rest/v1/events', [
        'id' => 'eq.' . $data['event_id'],
        'created_by' => 'eq.' . $data['user_id'],
    ], $fields, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update event.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Event not found or you're not the organizer.", 404);
        return;
    }

    $event = $res['data'][0];
    $inviteeIds = resolveEventInviteeIds($data, $data['user_id']);
    if (!empty($inviteeIds)) {
        notifyEventInvitees($event, $inviteeIds, $profile['pet_name'] ?? null);
    }

    jsonSuccess(["event" => $event]);
}

function handleGetEvents($data)
{
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 50;
    $params = [
        'select' => 'id,title,description,event_date,event_time,location,is_online,meeting_url,pet_type,breed,banner_url,linked_group_id,created_by,created_at',
        'order' => 'event_date.asc',
        'limit' => (string) $limit,
    ];
    if (empty($data['include_past'])) {
        $params['event_date'] = 'gte.' . gmdate('Y-m-d');
    }
    if (!empty($data['pet_type']) && empty($data['all'])) {
        $petType = cleanPlainValue($data['pet_type'], 80);
        $params['or'] = '(pet_type.is.null,pet_type.eq.' . $petType . ')';
    }

    $res = supabaseRequest('GET', '/rest/v1/events', $params);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch events.", $res);
        return;
    }

    $events = $res['data'] ?? [];
    $eventIds = array_column($events, 'id');
    $rsvpCounts = [];
    $myRsvp = [];
    if (!empty($eventIds)) {
        $rsvpRes = supabaseRequest('GET', '/rest/v1/event_rsvps', [
            'event_id' => 'in.(' . implode(',', $eventIds) . ')',
            'status' => 'eq.going',
            'select' => 'event_id,user_id,status',
        ]);
        foreach (($rsvpRes['data'] ?? []) as $row) {
            $rsvpCounts[$row['event_id']] = ($rsvpCounts[$row['event_id']] ?? 0) + 1;
            if (!empty($data['user_id']) && $row['user_id'] === $data['user_id']) {
                $myRsvp[$row['event_id']] = $row['status'];
            }
        }
    }

    $myGroupIds = [];
    if (!empty($data['user_id']) && !empty($eventIds)) {
        $linkedGroupIds = normalizeUuidList(array_filter(array_column($events, 'linked_group_id')));
        if (!empty($linkedGroupIds)) {
            $memRes = supabaseRequest('GET', '/rest/v1/group_members', [
                'group_id' => 'in.(' . implode(',', $linkedGroupIds) . ')',
                'user_id' => 'eq.' . $data['user_id'],
                'select' => 'group_id',
            ]);
            foreach (($memRes['data'] ?? []) as $row) {
                $myGroupIds[$row['group_id']] = true;
            }
        }
    }

    $creatorMap = fetchProfilesMap(array_column($events, 'created_by'));

    foreach ($events as &$e) {
        $e['going_count'] = $rsvpCounts[$e['id']] ?? 0;
        $e['my_rsvp'] = $myRsvp[$e['id']] ?? null;
        $e['is_group_member'] = !empty($e['linked_group_id']) && isset($myGroupIds[$e['linked_group_id']]);
        $e['created_by_name'] = $creatorMap[$e['created_by']]['pet_name'] ?? null;
    }
    unset($e);

    jsonSuccess(["events" => $events]);
}

// Creates (or reuses) a discussion group for an event on first "going" RSVP,
// matching eSamaj's ensureEventGroup() but backed by a real FK column
// (events.linked_group_id) rather than a deterministic string id.
function ensureEventGroup($event)
{
    if (!empty($event['linked_group_id'])) {
        return $event['linked_group_id'];
    }

    $groupRes = supabaseRequest('POST', '/rest/v1/groups', [], [
        'name' => $event['title'] . ' — Event Group',
        'description' => 'Discussion group for ' . $event['title'],
        'pet_type' => $event['pet_type'] ?? null,
        'breed' => null,
        'created_by' => $event['created_by'],
        'is_private' => false,
        'scope' => 'global',
        'pack_key' => null,
    ], ['Prefer: return=representation']);
    if (supabaseFailed($groupRes) || empty($groupRes['data'])) {
        return null;
    }
    $groupId = $groupRes['data'][0]['id'];

    supabaseRequest('POST', '/rest/v1/group_members', [], [
        'group_id' => $groupId,
        'user_id' => $event['created_by'],
        'role' => 'admin',
    ], ['Prefer: resolution=ignore-duplicates,return=minimal']);

    supabaseRequest('PATCH', '/rest/v1/events', ['id' => 'eq.' . $event['id']], [
        'linked_group_id' => $groupId,
    ]);

    return $groupId;
}

function handleRsvpEvent($data)
{
    if (!requireFields($data, ['user_id', 'event_id']))
        return;
    $status = $data['status'] ?? 'going';
    $status = in_array($status, ['going', 'interested', 'not_going'], true) ? $status : 'going';

    $eventRes = supabaseRequest('GET', '/rest/v1/events', [
        'id' => 'eq.' . $data['event_id'],
        'select' => 'id,title,pet_type,created_by,linked_group_id',
        'limit' => '1',
    ]);
    if (supabaseFailed($eventRes) || empty($eventRes['data'])) {
        jsonError("Event not found.", 404);
        return;
    }
    $event = $eventRes['data'][0];

    if ($status === 'not_going') {
        $res = supabaseRequest('DELETE', '/rest/v1/event_rsvps', ['event_id' => 'eq.' . $data['event_id'], 'user_id' => 'eq.' . $data['user_id']]);
        if (!empty($event['linked_group_id'])) {
            supabaseRequest('DELETE', '/rest/v1/group_members', [
                'group_id' => 'eq.' . $event['linked_group_id'],
                'user_id' => 'eq.' . $data['user_id'],
            ]);
        }
    } else {
        $res = supabaseRequest('POST', '/rest/v1/event_rsvps', ['on_conflict' => 'event_id,user_id'], [
            'event_id' => $data['event_id'],
            'user_id' => $data['user_id'],
            'status' => $status,
        ], ['Prefer: resolution=merge-duplicates,return=minimal']);

        if ($status === 'going') {
            $groupId = ensureEventGroup($event);
            if ($groupId) {
                supabaseRequest('POST', '/rest/v1/group_members', [], [
                    'group_id' => $groupId,
                    'user_id' => $data['user_id'],
                    'role' => 'member',
                ], ['Prefer: resolution=ignore-duplicates,return=minimal']);
            }
        }
    }

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update RSVP.", $res);
        return;
    }

    jsonSuccess(["status" => $status]);
}

function handleDeleteEvent($data)
{
    if (!requireFields($data, ['user_id', 'event_id']))
        return;

    $res = supabaseRequest('DELETE', '/rest/v1/events', [
        'id' => 'eq.' . $data['event_id'],
        'created_by' => 'eq.' . $data['user_id'],
    ], null, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete event.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Event not found or you're not the organizer.", 404);
        return;
    }

    $deletedEvent = $res['data'][0];
    if (!empty($deletedEvent['linked_group_id'])) {
        supabaseRequest('DELETE', '/rest/v1/groups', ['id' => 'eq.' . $deletedEvent['linked_group_id']]);
    }

    jsonSuccess(["message" => "Event deleted."]);
}

function handleGetEventAnalytics($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $eventsRes = supabaseRequest('GET', '/rest/v1/events', ['select' => 'id,pet_type,event_date']);
    $events = supabaseFailed($eventsRes) ? [] : ($eventsRes['data'] ?? []);
    $eventIds = array_column($events, 'id');

    $eventsByPetType = [];
    foreach ($events as $e) {
        $key = $e['pet_type'] ?: 'Unspecified';
        $eventsByPetType[$key] = ($eventsByPetType[$key] ?? 0) + 1;
    }
    arsort($eventsByPetType);

    $rsvps = [];
    if (!empty($eventIds)) {
        $rsvpRes = supabaseRequest('GET', '/rest/v1/event_rsvps', [
            'event_id' => 'in.(' . implode(',', $eventIds) . ')',
            'status' => 'eq.going',
            'select' => 'event_id,user_id,created_at',
        ]);
        $rsvps = supabaseFailed($rsvpRes) ? [] : ($rsvpRes['data'] ?? []);
    }

    $myRsvpCount = 0;
    $attendanceByMonth = [];
    foreach ($rsvps as $r) {
        if ($r['user_id'] === $userId) {
            $myRsvpCount++;
        }
        $monthKey = substr((string) $r['created_at'], 0, 7);
        $attendanceByMonth[$monthKey] = ($attendanceByMonth[$monthKey] ?? 0) + 1;
    }
    ksort($attendanceByMonth);

    jsonSuccess([
        'total_events' => count($events),
        'total_attendees' => count($rsvps),
        'my_rsvps' => $myRsvpCount,
        'events_by_pet_type' => $eventsByPetType,
        'attendance_by_month' => $attendanceByMonth,
    ]);
}
