<?php
/**
 * Zoom calling: Server-to-Server OAuth (meeting creation) + Meeting SDK JWT
 * (client-side embed auth) for direct-message and group voice/video calls.
 * Ported from eSamaj's real, working zoom.php against this rebuild's
 * call_sessions/call_participants/call_events schema, which already existed
 * (pre-dating this rebuild) with a shape that closely matches eSamaj's own —
 * confirmed live via information_schema/pg_constraint before writing this,
 * no migration was needed. The Meeting SDK signature is always generated
 * server-side (never trust a client-computed one) and every user always
 * joins with SDK role 0 (attendee) — join_before_host handles hosting,
 * matching eSamaj exactly.
 */

function zoomGetAccessToken()
{
    $accountId = envValue('ZOOM_S2S_ACCOUNT_ID');
    $clientId = envValue('ZOOM_S2S_CLIENT_ID');
    $clientSecret = envValue('ZOOM_S2S_CLIENT_SECRET');
    if (!$accountId || !$clientId || !$clientSecret) {
        return null;
    }

    $ch = curl_init('https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($accountId));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
        'Content-Type: application/x-www-form-urlencoded',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode !== 200) {
        error_log("[pawcircle][zoom] token fetch failed | http={$httpCode} | response={$response}");
        return null;
    }
    $decoded = json_decode($response, true);
    return $decoded['access_token'] ?? null;
}

function zoomApiRequest($method, $path, $body = null)
{
    $token = zoomGetAccessToken();
    if (!$token) {
        return ['code' => 500, 'data' => ['message' => 'Could not authenticate with Zoom.']];
    }
    $ch = curl_init('https://api.zoom.us/v2' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $httpCode, 'data' => json_decode($response, true)];
}

function zoomBase64UrlEncode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

// Hand-rolled HS256 JWT for the Meeting SDK for Web — Zoom's SDK auth token,
// distinct from the S2S OAuth access token above. Role is always 0
// (attendee); join_before_host on the meeting itself handles hosting.
function generateZoomMeetingSdkJwt($meetingNumber)
{
    $clientId = envValue('ZOOM_MEETING_SDK_CLIENT_ID');
    $clientSecret = envValue('ZOOM_MEETING_SDK_CLIENT_SECRET');
    $iat = time() - 30;
    $exp = $iat + 7200;

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'appKey' => $clientId,
        'sdkKey' => $clientId,
        'mn' => $meetingNumber,
        'role' => 0,
        'iat' => $iat,
        'exp' => $exp,
        'tokenExp' => $exp,
        'video_webrtc_mode' => 1,
    ];

    $segments = [
        zoomBase64UrlEncode(json_encode($header)),
        zoomBase64UrlEncode(json_encode($payload)),
    ];
    $signature = hash_hmac('sha256', implode('.', $segments), $clientSecret, true);
    $segments[] = zoomBase64UrlEncode($signature);
    return implode('.', $segments);
}

function createZoomMeetingForCall($callType, $topic)
{
    $hostUserId = envValue('ZOOM_HOST_USER_ID');
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < 8; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    $isVideo = $callType === 'video';

    $res = zoomApiRequest('POST', '/users/' . rawurlencode($hostUserId) . '/meetings', [
        'topic' => $topic,
        'type' => 2,
        'start_time' => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
        'duration' => 60,
        'timezone' => 'UTC',
        'password' => $password,
        'settings' => [
            'join_before_host' => true,
            'waiting_room' => false,
            'host_video' => $isVideo,
            'participant_video' => $isVideo,
            'mute_upon_entry' => false,
            'approval_type' => 2,
            'audio' => 'both',
        ],
    ]);

    if ($res['code'] !== 201 || empty($res['data']['id'])) {
        error_log('[pawcircle][zoom] meeting create failed | ' . json_encode($res));
        return null;
    }
    return [
        'zoom_meeting_id' => (string) $res['data']['id'],
        'zoom_password' => $password,
        'zoom_join_url' => $res['data']['join_url'] ?? null,
    ];
}

function logCallEvent($callId, $userId, $eventType)
{
    supabaseRequest('POST', '/rest/v1/call_events', [], [
        'call_id' => $callId,
        'user_id' => $userId ?: null,
        'event_type' => $eventType,
    ], ['Prefer: return=minimal']);
}

function zoomEndMeetingIfPossible($zoomMeetingId)
{
    if (!$zoomMeetingId)
        return;
    zoomApiRequest('PUT', '/meetings/' . rawurlencode($zoomMeetingId) . '/status', ['action' => 'end']);
}

// Ends any call left ringing/active for more than 2 hours — a defensive
// sweep called at the top of every call-touching action, matching eSamaj's
// own cleanupStaleCallSessions().
function cleanupStaleCallSessions()
{
    $cutoff = gmdate('c', time() - 7200);
    $res = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'status' => 'in.(ringing,active)',
        'created_at' => 'lt.' . $cutoff,
        'select' => 'id',
    ]);
    foreach ((supabaseFailed($res) ? [] : ($res['data'] ?? [])) as $row) {
        supabaseRequest('PATCH', '/rest/v1/call_sessions', ['id' => 'eq.' . $row['id']], ['status' => 'ended', 'ended_at' => nowIsoUtc()], ['Prefer: return=minimal']);
        supabaseRequest('PATCH', '/rest/v1/call_participants', ['call_id' => 'eq.' . $row['id'], 'status' => 'in.(invited,ringing,joined)'], ['status' => 'left', 'left_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    }
}

// Returns the list of other-user ids to invite, or null if the caller isn't
// allowed to call this target (not a friend / not a group member).
function resolveCallParticipantIds($data, $callerId)
{
    if (($data['target_type'] ?? '') === 'group') {
        $groupId = $data['group_id'] ?? '';
        $membership = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'eq.' . $groupId,
            'user_id' => 'eq.' . $callerId,
            'select' => 'user_id',
            'limit' => '1',
        ]);
        if (supabaseFailed($membership) || empty($membership['data'])) {
            return null;
        }
        $membersRes = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'eq.' . $groupId,
            'select' => 'user_id',
        ]);
        $ids = array_column(supabaseFailed($membersRes) ? [] : ($membersRes['data'] ?? []), 'user_id');
        return array_values(array_diff($ids, [$callerId]));
    }

    $friendId = $data['friend_id'] ?? '';
    if (!usersAreFriends($callerId, $friendId)) {
        return null;
    }
    return [$friendId];
}

function handleZoomStartCall($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $callType = in_array($data['call_type'] ?? '', ['voice', 'video'], true) ? $data['call_type'] : 'voice';
    $targetType = in_array($data['target_type'] ?? '', ['direct', 'group'], true) ? $data['target_type'] : 'direct';

    cleanupStaleCallSessions();

    $otherIds = resolveCallParticipantIds(array_merge($data, ['target_type' => $targetType]), $userId);
    if ($otherIds === null) {
        jsonError($targetType === 'group' ? "You must be a member of this group to call it." : "You can only call friends.", 403);
        return;
    }
    if (empty($otherIds)) {
        jsonError("No one to call.", 400);
        return;
    }

    $callerProfile = getAccountProfile($userId);
    $topic = ($callerProfile['pet_name'] ?? 'PawCircle') . "'s " . ($callType === 'video' ? 'video' : 'voice') . ' call';
    $zoomMeeting = createZoomMeetingForCall($callType, $topic);
    if (!$zoomMeeting) {
        jsonError("Could not start the call. Please try again.", 502);
        return;
    }

    $sessionRes = supabaseRequest('POST', '/rest/v1/call_sessions', [], [
        'created_by' => $userId,
        'call_type' => $callType,
        'target_type' => $targetType,
        'group_id' => $targetType === 'group' ? ($data['group_id'] ?? null) : null,
        'provider' => 'zoom',
        'provider_room_id' => $zoomMeeting['zoom_meeting_id'],
        'provider_room_url' => $zoomMeeting['zoom_join_url'],
        'zoom_meeting_id' => $zoomMeeting['zoom_meeting_id'],
        'zoom_password' => $zoomMeeting['zoom_password'],
        'zoom_join_url' => $zoomMeeting['zoom_join_url'],
        'status' => 'ringing',
        'started_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);
    if (supabaseFailed($sessionRes) || empty($sessionRes['data'])) {
        sendSupabaseError("Failed to start call.", $sessionRes);
        return;
    }
    $call = $sessionRes['data'][0];

    $participantRows = [['call_id' => $call['id'], 'user_id' => $userId, 'role' => 'host', 'status' => 'joined', 'joined_at' => nowIsoUtc()]];
    foreach ($otherIds as $otherId) {
        $participantRows[] = ['call_id' => $call['id'], 'user_id' => $otherId, 'role' => 'participant', 'status' => 'invited', 'joined_at' => null];
    }
    $participantsRes = supabaseRequest('POST', '/rest/v1/call_participants', [], $participantRows, ['Prefer: return=representation']);
    if (supabaseFailed($participantsRes) || empty($participantsRes['data'])) {
        error_log('[pawcircle][zoom] call_participants insert failed | ' . json_encode($participantsRes));
        // Roll back the call session rather than leaving an unjoinable
        // "ringing" call with no invited participants.
        supabaseRequest('PATCH', '/rest/v1/call_sessions', ['id' => 'eq.' . $call['id']], ['status' => 'cancelled', 'ended_at' => nowIsoUtc()], ['Prefer: return=minimal']);
        zoomEndMeetingIfPossible($zoomMeeting['zoom_meeting_id']);
        jsonError("Could not start the call. Please try again.", 502);
        return;
    }
    logCallEvent($call['id'], $userId, 'created');

    $callerName = $callerProfile['pet_name'] ?? 'Someone';
    $groupName = null;
    if ($targetType === 'group') {
        $groupRes = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $data['group_id'], 'select' => 'name', 'limit' => '1']);
        $groupName = (!supabaseFailed($groupRes) && !empty($groupRes['data'])) ? $groupRes['data'][0]['name'] : null;
    }
    foreach ($otherIds as $otherId) {
        createNotification($otherId, 'call_invite', "{$callerName} is calling", $callType === 'video' ? 'Incoming video call' : 'Incoming voice call', [
            'call_id' => $call['id'],
            'caller_id' => $userId,
            'call_type' => $callType,
            'target_type' => $targetType,
            'group_id' => $data['group_id'] ?? null,
            'group_name' => $groupName,
        ]);
    }

    jsonSuccess([
        'call' => $call,
        'zoom' => [
            'sdk_key' => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
            'signature' => generateZoomMeetingSdkJwt($zoomMeeting['zoom_meeting_id']),
            'meeting_number' => $zoomMeeting['zoom_meeting_id'],
            'password' => $zoomMeeting['zoom_password'],
            'user_name' => $callerProfile['pet_name'] ?? 'Member',
        ],
    ]);
}

function handleZoomJoinCall($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $callId = requireUuid($data['call_id'] ?? '', 'call_id');

    $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId, 'select' => 'id,status,zoom_meeting_id,zoom_password', 'limit' => '1']);
    if (supabaseFailed($callRes) || empty($callRes['data'])) {
        jsonError("Call not found.", 404);
        return;
    }
    $call = $callRes['data'][0];
    if (!in_array($call['status'], ['ringing', 'active'], true)) {
        jsonError("This call has ended.", 410);
        return;
    }

    $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', ['call_id' => 'eq.' . $callId, 'user_id' => 'eq.' . $userId, 'select' => 'id', 'limit' => '1']);
    if (supabaseFailed($participantRes) || empty($participantRes['data'])) {
        jsonError("You weren't invited to this call.", 403);
        return;
    }

    supabaseRequest('PATCH', '/rest/v1/call_participants', ['call_id' => 'eq.' . $callId, 'user_id' => 'eq.' . $userId], ['status' => 'joined', 'joined_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    supabaseRequest('PATCH', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId], ['status' => 'active'], ['Prefer: return=minimal']);
    logCallEvent($callId, $userId, 'joined');

    $profile = getAccountProfile($userId);
    jsonSuccess([
        'zoom' => [
            'sdk_key' => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
            'signature' => generateZoomMeetingSdkJwt($call['zoom_meeting_id']),
            'meeting_number' => $call['zoom_meeting_id'],
            'password' => $call['zoom_password'],
            'user_name' => $profile['pet_name'] ?? 'Member',
        ],
    ]);
}

function handleZoomEndCall($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $callId = requireUuid($data['call_id'] ?? '', 'call_id');

    $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId, 'select' => 'id,created_by,zoom_meeting_id,status', 'limit' => '1']);
    if (supabaseFailed($callRes) || empty($callRes['data'])) {
        jsonError("Call not found.", 404);
        return;
    }
    $call = $callRes['data'][0];
    if ($call['created_by'] !== $userId) {
        jsonError("Only the caller can end this call.", 403);
        return;
    }

    supabaseRequest('PATCH', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId], ['status' => 'ended', 'ended_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    supabaseRequest('PATCH', '/rest/v1/call_participants', ['call_id' => 'eq.' . $callId, 'status' => 'in.(invited,ringing,joined)'], ['status' => 'left', 'left_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    logCallEvent($callId, $userId, 'ended');
    zoomEndMeetingIfPossible($call['zoom_meeting_id']);

    jsonSuccess(["message" => "Call ended."]);
}

// Auto-ends a call nobody besides the caller ever actually joined.
function maybeEndCallIfNobodyJoined($callId)
{
    $res = supabaseRequest('GET', '/rest/v1/call_participants', ['call_id' => 'eq.' . $callId, 'status' => 'eq.joined', 'select' => 'id']);
    if (!supabaseFailed($res) && empty($res['data'])) {
        $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId, 'select' => 'zoom_meeting_id', 'limit' => '1']);
        supabaseRequest('PATCH', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId], ['status' => 'ended', 'ended_at' => nowIsoUtc()], ['Prefer: return=minimal']);
        if (!supabaseFailed($callRes) && !empty($callRes['data'])) {
            zoomEndMeetingIfPossible($callRes['data'][0]['zoom_meeting_id']);
        }
    }
}

function handleZoomMarkParticipant($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $callId = requireUuid($data['call_id'] ?? '', 'call_id');
    $status = in_array($data['participant_status'] ?? '', ['left', 'declined', 'missed'], true) ? $data['participant_status'] : 'left';

    $body = ['status' => $status];
    if ($status === 'left')
        $body['left_at'] = nowIsoUtc();

    supabaseRequest('PATCH', '/rest/v1/call_participants', ['call_id' => 'eq.' . $callId, 'user_id' => 'eq.' . $userId], $body, ['Prefer: return=minimal']);
    logCallEvent($callId, $userId, $status);
    if ($status === 'left') {
        maybeEndCallIfNobodyJoined($callId);
    }

    jsonSuccess(["message" => "Updated."]);
}

function handleZoomGetActiveCalls($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    cleanupStaleCallSessions();

    $res = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $userId,
        'status' => 'in.(invited,ringing,joined)',
        'select' => 'call_id,status,call_sessions!inner(id,created_by,call_type,target_type,group_id,status,zoom_join_url,created_at)',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch active calls.", $res);
        return;
    }

    $calls = [];
    foreach (($res['data'] ?? []) as $row) {
        $call = $row['call_sessions'] ?? null;
        if (!$call || !in_array($call['status'], ['ringing', 'active'], true) || $call['created_by'] === $userId)
            continue;
        $calls[] = $call;
    }

    $callerIds = array_column($calls, 'created_by');
    $profileMap = fetchProfilesMap($callerIds);
    foreach ($calls as &$c) {
        $c['caller_name'] = $profileMap[$c['created_by']]['pet_name'] ?? 'Someone';
    }
    unset($c);

    jsonSuccess(["calls" => $calls]);
}

function handleZoomGetDirectCalls($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $friendId = requireUuid($data['friend_id'] ?? '', 'friend_id');
    cleanupStaleCallSessions();

    $res = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $userId,
        'select' => 'call_id,call_sessions!inner(id,created_by,call_type,target_type,status,zoom_join_url,created_at,ended_at)',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch call history.", $res);
        return;
    }

    $myCallIds = [];
    $callsById = [];
    foreach (($res['data'] ?? []) as $row) {
        $call = $row['call_sessions'] ?? null;
        if (!$call || $call['target_type'] !== 'direct')
            continue;
        $myCallIds[] = $call['id'];
        $callsById[$call['id']] = $call;
    }
    if (empty($myCallIds)) {
        jsonSuccess(["calls" => []]);
        return;
    }

    $friendRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $friendId,
        'call_id' => 'in.(' . implode(',', $myCallIds) . ')',
        'select' => 'call_id',
    ]);
    $sharedIds = array_column(supabaseFailed($friendRes) ? [] : ($friendRes['data'] ?? []), 'call_id');

    $calls = array_values(array_intersect_key($callsById, array_flip($sharedIds)));
    usort($calls, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
    jsonSuccess(["calls" => array_slice($calls, 0, 20)]);
}

function handleZoomGetGroupCalls($data)
{
    $groupId = requireUuid($data['group_id'] ?? '', 'group_id');
    cleanupStaleCallSessions();

    $res = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'target_type' => 'eq.group',
        'group_id' => 'eq.' . $groupId,
        'select' => 'id,created_by,call_type,status,zoom_join_url,created_at,ended_at',
        'order' => 'created_at.desc',
        'limit' => '20',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch call history.", $res);
        return;
    }

    jsonSuccess(["calls" => $res['data'] ?? []]);
}
