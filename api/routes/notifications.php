<?php
/**
 * Notifications: a shared createNotification() primitive used by posts,
 * friends, groups, events, and (later) rescue/playdates, plus the read
 * handlers. No WhatsApp mirroring — that integration isn't part of this
 * rebuild.
 */

function createNotification($userId, $type, $title, $body, $data = [])
{
    if (!$userId || !$type) {
        return ['code' => 400, 'data' => ['message' => 'Missing notification user or type.']];
    }

    return supabaseRequest('POST', '/rest/v1/notifications', [], [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'data' => empty($data) ? null : $data,
        'is_read' => false,
    ], ['Prefer: return=minimal']);
}

function handleGetNotifications($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 100)) : 30;

    $res = supabaseRequest('GET', '/rest/v1/notifications', [
        'user_id' => 'eq.' . $userId,
        'select' => 'id,type,title,body,data,is_read,created_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch notifications.", $res);
        return;
    }

    jsonSuccess(["notifications" => $res['data'] ?? []]);
}

function handleMarkNotificationRead($data)
{
    if (!requireFields($data, ['user_id', 'notification_id']))
        return;

    $res = supabaseRequest('PATCH', '/rest/v1/notifications', [
        'id' => 'eq.' . $data['notification_id'],
        'user_id' => 'eq.' . $data['user_id'],
    ], ['is_read' => true], ['Prefer: return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to mark notification read.", $res);
        return;
    }

    jsonSuccess(["message" => "Marked read."]);
}

function handleMarkAllNotificationsRead($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $res = supabaseRequest('PATCH', '/rest/v1/notifications', [
        'user_id' => 'eq.' . $userId,
        'is_read' => 'eq.false',
    ], ['is_read' => true], ['Prefer: return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to mark notifications read.", $res);
        return;
    }

    jsonSuccess(["message" => "All notifications marked read."]);
}
