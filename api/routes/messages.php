<?php
/**
 * Direct messages between friends: send/list/react/reply. Unread-badge state
 * is still derived client-side from notifications (see handleGetDirectMessages,
 * which marks the sender's direct_message notifications read as a side effect
 * of fetching the thread). direct_messages.read_at additionally gives a real
 * per-message "seen" signal for the double-tick UI — group_messages has no
 * equivalent column; per-member group read receipts are out of scope.
 */

function usersAreFriends($userId, $otherId)
{
    if (!$userId || !$otherId || $userId === $otherId)
        return false;
    $res = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(and(requester.eq.' . $userId . ',addressee.eq.' . $otherId . '),and(requester.eq.' . $otherId . ',addressee.eq.' . $userId . '))',
        'status' => 'eq.accepted',
        'select' => 'id',
        'limit' => '1',
    ]);
    return !supabaseFailed($res) && !empty($res['data']);
}

function handleSendDirectMessage($data)
{
    if (!requireFields($data, ['user_id', 'recipient_id']))
        return;
    $content = cleanTextValue($data['content'] ?? '', 3000);
    $mediaUrl = trim((string) ($data['media_url'] ?? ''));
    if ($content === '' && $mediaUrl === '') {
        jsonError("Message content or media is required.", 400);
        return;
    }
    if (!usersAreFriends($data['user_id'], $data['recipient_id'])) {
        jsonError("You can only message friends.", 403);
        return;
    }

    $body = [
        'sender_id' => $data['user_id'],
        'recipient_id' => $data['recipient_id'],
        'content' => $content === '' ? null : $content,
        'media_url' => $mediaUrl === '' ? null : $mediaUrl,
    ];
    if (!empty($data['reply_to_id'])) {
        $body['reply_to_id'] = $data['reply_to_id'];
    }

    $res = supabaseRequest('POST', '/rest/v1/direct_messages', [], $body, ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to send message.", $res);
        return;
    }

    $senderProfile = getAccountProfile($data['user_id']);
    $senderName = $senderProfile['pet_name'] ?? 'Someone';
    $preview = $content !== '' ? mb_substr($content, 0, 80) : 'Sent a photo';
    createNotification($data['recipient_id'], 'direct_message', $senderName, $preview, ['sender_id' => $data['user_id']]);

    jsonSuccess(["message_row" => $res['data'][0]]);
}

// Batch-attaches reply_to_text to any message with a reply_to_id, so the
// client never has to make a second round-trip per quoted message.
function enrichMessagesWithReplyText($messages, $table)
{
    $replyIds = array_values(array_unique(array_filter(array_column($messages, 'reply_to_id'))));
    if (empty($replyIds)) {
        foreach ($messages as &$m) {
            $m['reply_to_text'] = null;
        }
        unset($m);
        return $messages;
    }

    $res = supabaseRequest('GET', "/rest/v1/{$table}", [
        'id' => 'in.(' . implode(',', $replyIds) . ')',
        'select' => 'id,content,media_url',
    ]);
    $textById = [];
    foreach ((supabaseFailed($res) ? [] : ($res['data'] ?? [])) as $row) {
        $textById[$row['id']] = $row['content'] ?: ($row['media_url'] ? '📷 Photo' : 'Message');
    }

    foreach ($messages as &$m) {
        $m['reply_to_text'] = $m['reply_to_id'] ? ($textById[$m['reply_to_id']] ?? 'Message') : null;
    }
    unset($m);
    return $messages;
}

function handleGetDirectMessages($data)
{
    if (!requireFields($data, ['user_id', 'friend_id']))
        return;
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 200)) : 60;

    $res = supabaseRequest('GET', '/rest/v1/direct_messages', [
        'or' => '(and(sender_id.eq.' . $data['user_id'] . ',recipient_id.eq.' . $data['friend_id'] . '),and(sender_id.eq.' . $data['friend_id'] . ',recipient_id.eq.' . $data['user_id'] . '))',
        'is_deleted' => 'eq.false',
        'select' => 'id,sender_id,recipient_id,content,media_url,created_at,reply_to_id,reactions,read_at',
        'order' => 'created_at.asc',
        'limit' => (string) $limit,
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch messages.", $res);
        return;
    }

    // Opening a thread clears its unread badge — mirrors eSamaj's own
    // read-derived-from-notifications approach (direct_messages itself has
    // no is_read column).
    supabaseRequest('PATCH', '/rest/v1/notifications', [
        'user_id' => 'eq.' . $data['user_id'],
        'type' => 'eq.direct_message',
        'data->>sender_id' => 'eq.' . $data['friend_id'],
        'is_read' => 'eq.false',
    ], ['is_read' => true], ['Prefer: return=minimal']);

    // Fetching the thread also marks the OTHER person's messages as seen —
    // the read receipt behind the double-tick UI. Scoped to messages sent BY
    // the friend TO the caller, never the caller's own messages.
    $nowIso = nowIsoUtc();
    supabaseRequest('PATCH', '/rest/v1/direct_messages', [
        'sender_id' => 'eq.' . $data['friend_id'],
        'recipient_id' => 'eq.' . $data['user_id'],
        'read_at' => 'is.null',
    ], ['read_at' => $nowIso], ['Prefer: return=minimal']);

    $messages = enrichMessagesWithReplyText($res['data'] ?? [], 'direct_messages');
    foreach ($messages as &$m) {
        if ($m['sender_id'] === $data['friend_id'] && $m['recipient_id'] === $data['user_id'] && empty($m['read_at'])) {
            $m['read_at'] = $nowIso;
        }
    }
    unset($m);

    jsonSuccess(["messages" => $messages]);
}

// Upsert-or-unset the caller's own reaction entry in the message's reactions
// JSONB map ({user_id: emoji}). Read-modify-write, matching eSamaj's own
// logic exactly — same theoretical race window under concurrent reactions
// on the same message, acceptable for this access pattern.
function handleReactToDirectMessage($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $messageId = requireUuid($data['message_id'] ?? '', 'message_id');
    $emoji = trim((string) ($data['reaction'] ?? ''));

    $msgRes = supabaseRequest('GET', '/rest/v1/direct_messages', [
        'id' => 'eq.' . $messageId,
        'select' => 'id,sender_id,recipient_id,reactions',
        'limit' => '1',
    ]);
    if (supabaseFailed($msgRes) || empty($msgRes['data'])) {
        jsonError("Message not found.", 404);
        return;
    }
    $message = $msgRes['data'][0];
    if ($message['sender_id'] !== $userId && $message['recipient_id'] !== $userId) {
        jsonError("You can't react to this message.", 403);
        return;
    }

    $reactions = is_array($message['reactions'] ?? null) ? $message['reactions'] : [];
    if ($emoji === '') {
        unset($reactions[$userId]);
    } else {
        $reactions[$userId] = $emoji;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/direct_messages', ['id' => 'eq.' . $messageId], ['reactions' => $reactions], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update reaction.", $res);
        return;
    }

    jsonSuccess(["reactions" => $reactions]);
}

function handleGetConversations($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $res = supabaseRequest('GET', '/rest/v1/direct_messages', [
        'or' => '(sender_id.eq.' . $userId . ',recipient_id.eq.' . $userId . ')',
        'is_deleted' => 'eq.false',
        'select' => 'sender_id,recipient_id,content,media_url,created_at',
        'order' => 'created_at.desc',
        'limit' => '300',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch conversations.", $res);
        return;
    }

    $latestByFriend = [];
    foreach (($res['data'] ?? []) as $row) {
        $otherId = $row['sender_id'] === $userId ? $row['recipient_id'] : $row['sender_id'];
        if (!isset($latestByFriend[$otherId])) {
            $latestByFriend[$otherId] = $row;
        }
    }

    $profileMap = fetchProfilesMap(array_keys($latestByFriend));
    $conversations = [];
    foreach ($latestByFriend as $friendId => $row) {
        $p = $profileMap[$friendId] ?? null;
        $conversations[] = [
            'user_id' => $friendId,
            'name' => $p['pet_name'] ?? 'Member',
            'profile_photo_url' => $p['profile_photo_url'] ?? null,
            'last_message' => $row['content'] ?? ($row['media_url'] ? '📷 Photo' : ''),
            'last_message_at' => $row['created_at'],
        ];
    }

    usort($conversations, fn($a, $b) => strcmp($b['last_message_at'], $a['last_message_at']));

    jsonSuccess(["conversations" => $conversations]);
}
