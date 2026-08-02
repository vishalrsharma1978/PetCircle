<?php

function handleZoomTest($data)
{
    $required = [
        'ZOOM_S2S_ACCOUNT_ID',
        'ZOOM_S2S_CLIENT_ID',
        'ZOOM_S2S_CLIENT_SECRET',
        'ZOOM_MEETING_SDK_CLIENT_ID',
        'ZOOM_MEETING_SDK_CLIENT_SECRET',
        'ZOOM_HOST_USER_ID',
        'APP_BASE_URL'
    ];

    $missing = [];

    foreach ($required as $key) {
        if (!envValue($key)) {
            $missing[] = $key;
        }
    }

    if (!empty($missing)) {
        jsonError("Missing Zoom .env values.", 500, [
            "missing" => $missing
        ]);
        return;
    }

    $token = zoomGetAccessToken();

    jsonSuccess([
        "message" => "Zoom Server-to-Server OAuth is working.",
        "host_user_id" => envValue('ZOOM_HOST_USER_ID'),
        "app_base_url" => envValue('APP_BASE_URL'),
        "token_preview" => substr($token, 0, 12) . "..."
    ]);
}

function generateZoomMeetingSdkJwt($meetingNumber, $role = 0)
{
    $clientId = envValue('ZOOM_MEETING_SDK_CLIENT_ID');
    $clientSecret = envValue('ZOOM_MEETING_SDK_CLIENT_SECRET');

    if (!$clientId || !$clientSecret) {
        jsonError("Zoom Meeting SDK credentials are missing from .env", 500);
        exit();
    }

    $iat = time() - 30;
    $exp = $iat + 60 * 60 * 2;

    $header = [
        "alg" => "HS256",
        "typ" => "JWT"
    ];

    $payload = [
        "appKey" => $clientId,
        "sdkKey" => $clientId,
        "mn" => (string) $meetingNumber,
        "role" => (int) $role,
        "iat" => $iat,
        "exp" => $exp,
        "tokenExp" => $exp,
        "video_webrtc_mode" => 1
    ];

    $segments = [
        base64UrlEncodeRaw(json_encode($header)),
        base64UrlEncodeRaw(json_encode($payload))
    ];

    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, $clientSecret, true);

    $segments[] = base64UrlEncodeRaw($signature);

    return implode('.', $segments);
}

function zoomGetAccessToken()
{
    $accountId = envValue('ZOOM_S2S_ACCOUNT_ID');
    $clientId = envValue('ZOOM_S2S_CLIENT_ID');
    $clientSecret = envValue('ZOOM_S2S_CLIENT_SECRET');

    if (!$accountId || !$clientId || !$clientSecret) {
        jsonError("Zoom Server-to-Server OAuth credentials are missing from .env", 500);
        exit();
    }

    $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($accountId);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $data = json_decode($response, true);

    if ($httpCode >= 400 || empty($data['access_token'])) {
        jsonError("Failed to get Zoom access token.", 500, [
            "zoom_http_code" => $httpCode,
            "zoom_response" => $data
        ]);
        exit();
    }

    return $data['access_token'];
}

function zoomApiRequest($method, $path, $body = null)
{
    $token = zoomGetAccessToken();

    $ch = curl_init('https://api.zoom.us/v2' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [
        "code" => $httpCode,
        "data" => json_decode($response, true)
    ];
}

function createZoomMeetingForCall($callType, $topic)
{
    $hostUserId = envValue('ZOOM_HOST_USER_ID');

    if (!$hostUserId) {
        jsonError("ZOOM_HOST_USER_ID is missing from .env", 500);
        exit();
    }

    $isVideo = $callType === 'video';

    $password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

    $payload = [
        "topic" => $topic,
        "type" => 2,
        "start_time" => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
        "duration" => 60,
        "timezone" => "UTC",
        "password" => $password,
        "settings" => [
            "join_before_host" => true,
            "waiting_room" => false,
            "host_video" => $isVideo,
            "participant_video" => $isVideo,
            "mute_upon_entry" => false,
            "approval_type" => 2,
            "audio" => "both"
        ]
    ];

    $res = zoomApiRequest(
        'POST',
        '/users/' . rawurlencode($hostUserId) . '/meetings',
        $payload
    );

    if ($res['code'] >= 400 || empty($res['data']['id'])) {
        jsonError("Failed to create Zoom meeting.", 500, [
            "zoom_http_code" => $res['code'],
            "zoom_response" => $res['data']
        ]);
        exit();
    }

    return $res['data'];
}

function handleZoomStartCall($data)
{

    cleanupStaleCallSessions();
    if (empty($data['user_id']) || empty($data['call_type']) || empty($data['target_type'])) {
        jsonError("user_id, call_type and target_type are required.");
        return;
    }

    $callerId = $data['user_id'];
    $callType = $data['call_type'];

    if (!in_array($callType, ['voice', 'video'], true)) {
        jsonError("call_type must be voice or video.");
        return;
    }

    if (!in_array($data['target_type'], ['direct', 'selected_users', 'group'], true)) {
        jsonError("target_type must be direct, selected_users or group.");
        return;
    }

    $participantIds = resolveCallParticipants($data);

    $topic = $callType === 'video'
        ? 'PawCircle Video Call'
        : 'PawCircle Voice Call';

    $zoomMeeting = createZoomMeetingForCall($callType, $topic);

    $meetingId = (string) $zoomMeeting['id'];
    $password = $zoomMeeting['password'] ?? '';
    $joinUrl = $zoomMeeting['join_url'] ?? null;

    $dbGroupId = $data['target_type'] === 'group' ? ($data['group_id'] ?? null) : null;
    if ($dbGroupId && str_starts_with($dbGroupId, 'event_group_')) {
        $eventId = substr($dbGroupId, 12);
        $dbGroupId = $eventId;

        // Ensure shadow group exists for foreign key constraints
        $groupCheck = supabaseRequest('GET', '/rest/v1/groups', ['id' => 'eq.' . $eventId, 'select' => 'id', 'limit' => '1']);
        if (empty($groupCheck['data'])) {
            $eventCheck = supabaseRequest('GET', '/rest/v1/events', ['id' => 'eq.' . $eventId, 'select' => 'title', 'limit' => '1']);
            $evTitle = $eventCheck['data'][0]['title'] ?? 'Event';

            supabaseRequest('POST', '/rest/v1/groups', [], [
                'id' => $eventId,
                'name' => 'Event: ' . $evTitle,
                'created_by' => $callerId,
                'is_private' => true
            ]);
        }
    }

    $callRes = supabaseRequest(
        'POST',
        '/rest/v1/call_sessions',
        [],
        [
            'created_by' => $callerId,
            'call_type' => $callType,
            'target_type' => $data['target_type'],
            'group_id' => $dbGroupId,
            'provider' => 'zoom',
            'zoom_meeting_id' => $meetingId,
            'zoom_password' => $password,
            'zoom_join_url' => $joinUrl,
            'status' => 'active',
            'started_at' => gmdate('c')
        ],
        ['Prefer: return=representation']
    );

    if ($callRes['code'] >= 400 || empty($callRes['data'][0]['id'])) {
        file_put_contents(__DIR__ . '/debug_call_error.txt', json_encode(['data' => $data, 'res' => $callRes]));
        jsonError("Failed to save call session.", 500, [
            "supabase_response" => $callRes['data']
        ]);
        return;
    }

    $call = $callRes['data'][0];
    insertCallParticipants($call['id'], $callerId, $participantIds);
    $callNotifications = notifyCallParticipants($call, $callerId, $participantIds);

    $signature = generateZoomMeetingSdkJwt($meetingId, 0);

    jsonSuccess([
        "call" => $call,
        "zoom" => [
            "sdkKey" => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
            "meetingNumber" => $meetingId,
            "password" => $password,
            "signature" => $signature,
            "role" => 0,
            "joinUrl" => $joinUrl
        ],
        "notifications" => [
            "participants_created" => $callNotifications["created"],
            "participants_attempted" => $callNotifications["attempted"],
        ]
    ]);
}

function handleZoomJoinCall($data)
{
    if (empty($data['user_id']) || empty($data['call_id'])) {
        jsonError("user_id and call_id are required.");
        return;
    }

    $userId = $data['user_id'];
    $callId = $data['call_id'];

    $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'eq.' . $callId,
        'status' => 'in.(ringing,active)',
        'select' => 'id,created_by,call_type,target_type,group_id,zoom_meeting_id,zoom_password,zoom_join_url,status,started_at,ended_at,created_at',
        'limit' => '1'
    ]);

    if (empty($callRes['data'])) {
        jsonError("Call not found or already ended.", 404);
        return;
    }

    $call = $callRes['data'][0];

    $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'user_id' => 'eq.' . $userId,
        'select' => 'id,status',
        'limit' => '1'
    ]);

    if (empty($participantRes['data'])) {
        if ($call['target_type'] === 'group') {
            supabaseRequest('POST', '/rest/v1/call_participants', [], [
                [
                    'call_id' => $callId,
                    'user_id' => $userId,
                    'role' => 'participant',
                    'status' => 'joined',
                    'joined_at' => gmdate('c')
                ]
            ]);
        } else {
            jsonError("You are not invited to this call.", 403);
            return;
        }
    } else {
        supabaseRequest(
            'PATCH',
            '/rest/v1/call_participants',
            [
                'call_id' => 'eq.' . $callId,
                'user_id' => 'eq.' . $userId
            ],
            [
                'status' => 'joined',
                'joined_at' => gmdate('c')
            ]
        );
    }

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_sessions',
        ['id' => 'eq.' . $callId],
        ['status' => 'active']
    );

    $meetingId = $call['zoom_meeting_id'];
    $signature = generateZoomMeetingSdkJwt($meetingId, 0);

    jsonSuccess([
        "call" => $call,
        "zoom" => [
            "sdkKey" => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
            "meetingNumber" => $meetingId,
            "password" => $call['zoom_password'] ?? '',
            "signature" => $signature,
            "role" => 0,
            "joinUrl" => $call['zoom_join_url'] ?? null
        ]
    ]);
}

function zoomEndMeetingIfPossible($meetingId)
{
    if (!$meetingId) {
        return null;
    }

    // Best-effort cleanup. Zoom returns an error if the meeting is already ended/not live;
    // we should not fail the app's own DB cleanup because of that.
    return zoomApiRequest('PUT', '/meetings/' . rawurlencode((string) $meetingId) . '/status', [
        'action' => 'end'
    ]);
}

function handleZoomEndCall($data)
{
    if (empty($data['user_id']) || empty($data['call_id'])) {
        jsonError("user_id and call_id are required.");
        return;
    }

    $userId = $data['user_id'];
    $callId = $data['call_id'];

    $ownerRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'eq.' . $callId,
        'created_by' => 'eq.' . $userId,
        'select' => 'id,zoom_meeting_id',
        'limit' => '1'
    ]);

    if (empty($ownerRes['data'])) {
        jsonError("Only the call creator can end this call.", 403);
        return;
    }

    $meetingId = $ownerRes['data'][0]['zoom_meeting_id'] ?? null;
    $endedAt = gmdate('c');

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_sessions',
        ['id' => 'eq.' . $callId],
        [
            'status' => 'ended',
            'ended_at' => $endedAt
        ]
    );

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_participants',
        [
            'call_id' => 'eq.' . $callId,
            'status' => 'in.(invited,ringing,joined)'
        ],
        [
            'status' => 'left',
            'left_at' => $endedAt
        ]
    );

    zoomEndMeetingIfPossible($meetingId);

    jsonSuccess(["message" => "Call ended.", "call_ended" => true, "ended_at" => $endedAt]);
}

function handleZoomGetActiveCalls($data)
{
    if (empty($data['user_id'])) {
        jsonError("user_id is required.");
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $data['user_id'],
        'status' => 'in.(invited,ringing,joined)',
        'select' => 'id,status,call_sessions(id,created_by,call_type,target_type,group_id,status,created_at,started_at)',
        'order' => 'created_at.desc'
    ]);

    jsonSuccess([
        "calls" => $res['data'] ?? []
    ]);
}

function handleZoomGetDirectCalls($data)
{
    cleanupStaleCallSessions();

    if (empty($data['user_id']) || empty($data['friend_id'])) {
        jsonError("user_id and friend_id are required.");
        return;
    }

    $userId = $data['user_id'];
    $friendId = $data['friend_id'];
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 50)) : 20;

    if (!areFriends($userId, $friendId)) {
        jsonError("You can only view calls with accepted friends.", 403);
        return;
    }

    $userParticipantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $userId,
        'select' => 'call_id,status,joined_at,left_at'
    ]);

    $friendParticipantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'user_id' => 'eq.' . $friendId,
        'select' => 'call_id'
    ]);

    if ($userParticipantRes['code'] >= 400 || $friendParticipantRes['code'] >= 400) {
        jsonError("Failed to load direct call participants.", 500, [
            "user_participants" => $userParticipantRes['data'] ?? null,
            "friend_participants" => $friendParticipantRes['data'] ?? null
        ]);
        return;
    }

    $friendCallIds = array_flip(normalizeUuidList(array_column($friendParticipantRes['data'] ?? [], 'call_id')));
    $participantByCallId = [];
    $sharedCallIds = [];

    foreach (($userParticipantRes['data'] ?? []) as $row) {
        $callId = $row['call_id'] ?? null;
        if ($callId && isset($friendCallIds[strtolower($callId)])) {
            $participantByCallId[$callId] = $row;
            $sharedCallIds[] = $callId;
        }
    }

    $sharedCallIds = normalizeUuidList($sharedCallIds);
    if (empty($sharedCallIds)) {
        jsonSuccess(["calls" => []]);
        return;
    }

    $callsRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'in.(' . implode(',', $sharedCallIds) . ')',
        'target_type' => 'in.(direct,selected_users)',
        'provider' => 'eq.zoom',
        'select' => 'id,created_by,call_type,target_type,group_id,status,created_at,started_at,ended_at',
        'order' => 'created_at.desc',
        'limit' => (string) $limit
    ]);

    if ($callsRes['code'] >= 400) {
        jsonError("Failed to load direct calls.", 500, ["supabase_response" => $callsRes['data']]);
        return;
    }

    $calls = $callsRes['data'] ?? [];
    $creatorIds = normalizeUuidList(array_values(array_unique(array_column($calls, 'created_by'))));
    $profilesByUserId = [];

    if (!empty($creatorIds)) {
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
            'user_id' => 'in.(' . implode(',', $creatorIds) . ')',
            'select' => 'user_id,full_name,profile_photo_url'
        ]);

        if ($profilesRes['code'] < 400) {
            foreach (($profilesRes['data'] ?? []) as $profile) {
                $profilesByUserId[$profile['user_id']] = $profile;
            }
        }
    }

    foreach ($calls as &$call) {
        $profile = $profilesByUserId[$call['created_by'] ?? ''] ?? null;
        $participant = $participantByCallId[$call['id'] ?? ''] ?? null;

        $call['created_by_name'] = $profile['full_name'] ?? 'Member';
        $call['created_by_avatar_url'] = $profile['profile_photo_url'] ?? null;
        $call['participant_status'] = $participant['status'] ?? null;
        $call['participant_joined_at'] = $participant['joined_at'] ?? null;
        $call['participant_left_at'] = $participant['left_at'] ?? null;
    }
    unset($call);

    jsonSuccess(["calls" => $calls]);
}

function handleZoomMarkParticipant($data)
{
    if (empty($data['user_id']) || empty($data['call_id']) || empty($data['participant_status'])) {
        jsonError("user_id, call_id and participant_status are required.");
        return;
    }

    $allowed = ['declined', 'left', 'missed'];

    if (!in_array($data['participant_status'], $allowed, true)) {
        jsonError("Invalid participant_status.");
        return;
    }

    $userId = $data['user_id'];
    $callId = $data['call_id'];

    $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'user_id' => 'eq.' . $userId,
        'select' => 'id,status',
        'limit' => '1'
    ]);

    if (empty($participantRes['data'])) {
        jsonError("You are not a participant in this call.", 403);
        return;
    }

    $now = gmdate('c');
    $patch = [
        'status' => $data['participant_status']
    ];

    if ($data['participant_status'] === 'left') {
        $patch['left_at'] = $now;
    }

    supabaseRequest(
        'PATCH',
        '/rest/v1/call_participants',
        [
            'call_id' => 'eq.' . $callId,
            'user_id' => 'eq.' . $userId
        ],
        $patch
    );

    $endCheck = ["ended" => false];
    if ($data['participant_status'] === 'left') {
        $endCheck = maybeEndCallIfNobodyJoined($callId);
    }

    jsonSuccess([
        "call_ended" => !empty($endCheck['ended']),
        "ended_at" => $endCheck['ended_at'] ?? null
    ]);
}

function handleGetPlaydatePreferences($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $res = supabaseRequest('GET', '/rest/v1/playdate_preferences', ['user_id' => 'eq.' . $userId]);
    $prefs = isset($res['data'][0]) ? $res['data'][0] : null;
    jsonSuccess(['preferences' => $prefs]);
}

function handleSavePlaydatePreferences($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? ($data['user_id'] ?? ''), 'user_id');
    $userCheck = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id',
        'limit' => '1',
    ]);
    if (($userCheck['code'] ?? 500) >= 400 || empty($userCheck['data'])) {
        jsonError("Could not verify your account before saving preferences. Please sign in again.", 401);
        return;
    }
    $prefs = [
        'user_id' => $userId,
        'pref_gender' => cleanNullableText($data['pref_gender'] ?? 'Any', 20),
        'pref_age_min' => intval($data['pref_age_min'] ?? 18),
        'pref_age_max' => intval($data['pref_age_max'] ?? 50),
        'pref_height_min' => intval($data['pref_height_min'] ?? 100),
        'pref_height_max' => intval($data['pref_height_max'] ?? 220),
        'pref_breed' => cleanNullableText($data['pref_breed'] ?? 'Any', 100),
        'pref_pet_type' => cleanNullableText($data['pref_pet_type'] ?? 'Any', 100),
        'pref_marital_status' => cleanNullableText($data['pref_marital_status'] ?? 'Any', 50),
        'pref_education' => cleanNullableText($data['pref_education'] ?? 'Any', 100),
        'pref_working' => cleanNullableText($data['pref_working'] ?? 'Any', 50),
    ];

    $res = supabaseRequest('POST', '/rest/v1/playdate_preferences', ['on_conflict' => 'user_id'], $prefs, ['Prefer: resolution=merge-duplicates,return=representation']);
    if (($res['code'] ?? 500) >= 400) {
        $details = strtolower((string) ($res['data']['details'] ?? $res['data']['message'] ?? ''));
        if (str_contains($details, 'foreign key constraint')) {
            jsonError("Playdate preferences table is linked to the wrong users table. Run the playdate preferences foreign-key repair SQL.", 500, ["err" => $res['data']]);
            return;
        }
        jsonError("Failed to save preferences.", 500, ["err" => $res['data']]);
        return;
    }
    jsonSuccess(['preferences' => $res['data'][0] ?? []]);
}

function handleGetPlaydatePool($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $selfId = strtolower($userId);

    // 1. Every signed-up member.
    $profRes = supabaseRequest('GET', '/rest/v1/profiles', [
        'select' => 'user_id,full_name,profile_photo_url,breed,pet_type,current_city,date_of_birth,gender,occupation',
        'limit' => '2000',
    ]);
    $profiles = $profRes['data'] ?? [];

    // 2. All playdate biodata, keyed by user for an in-memory merge.
    $bioRes = supabaseRequest('GET', '/rest/v1/playdate_profiles', ['limit' => '2000']);
    $bioMap = [];
    foreach (($bioRes['data'] ?? []) as $b) {
        if (!empty($b['user_id'])) {
            $bioMap[strtolower((string) $b['user_id'])] = $b;
        }
    }

    // 3. People this user already swiped on, so we can flag them.
    $swipedRes = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'user_id' => 'eq.' . $userId,
        'select' => 'to_user_id',
    ]);
    $swiped = [];
    foreach (($swipedRes['data'] ?? []) as $row) {
        if (!empty($row['to_user_id'])) {
            $swiped[strtolower((string) $row['to_user_id'])] = true;
        }
    }

    $out = [];
    foreach ($profiles as $p) {
        $uid = strtolower((string) ($p['user_id'] ?? ''));
        if ($uid === '' || $uid === $selfId) {
            continue; // never show the searcher themselves
        }
        $bio = $bioMap[$uid] ?? [];

        $privacy = ['hidePhotos' => false, 'hideContact' => true];
        if (!empty($bio['privacy_settings'])) {
            $decoded = is_array($bio['privacy_settings'])
                ? $bio['privacy_settings']
                : json_decode((string) $bio['privacy_settings'], true);
            if (is_array($decoded)) {
                $privacy = array_merge($privacy, $decoded);
            }
        }

        $dob = $p['date_of_birth'] ?? '';

        $out[] = [
            'id' => $uid,
            'user_id' => $uid,
            'name' => ($p['full_name'] ?? '') !== '' ? $p['full_name'] : 'Breed Member',
            'profile_photo_url' => $p['profile_photo_url'] ?? '',
            'pet_type' => $p['pet_type'] ?? '',
            'breed' => $p['breed'] ?? '',
            'gender' => ($bio['gender'] ?? '') !== '' ? ($bio['gender'] ?? '') : ($p['gender'] ?? ''),
            'age' => ageFromDateOfBirth($dob),
            'dob' => $dob,
            'city' => ($p['current_city'] ?? '') !== '' ? $p['current_city'] : ($bio['current_city'] ?? ''),
            'country' => $bio['current_country'] ?? 'India',
            'height' => isset($bio['height_cm']) ? (int) $bio['height_cm'] : null,
            'weight' => isset($bio['weight_kg']) ? (int) $bio['weight_kg'] : null,
            'bloodGroup' => $bio['blood_group'] ?? '',
            'diet' => $bio['diet'] ?? '',
            'complexion' => $bio['complexion'] ?? '',
            'education' => $bio['highest_education'] ?? '',
            'occupation' => ($bio['occupation'] ?? '') !== '' ? ($bio['occupation'] ?? '') : ($p['occupation'] ?? ''),
            'income' => $bio['annual_income'] ?? '',
            'rashi' => $bio['rashi'] ?? '',
            'nakshatra' => $bio['nakshatra'] ?? '',
            'mangalik' => $bio['mangalik'] ?? '',
            'birthTime' => $bio['birth_time'] ?? '',
            'birthPlace' => $bio['birth_place'] ?? '',
            'fatherName' => $bio['father_name'] ?? '',
            'motherName' => $bio['mother_name'] ?? '',
            'siblings' => isset($bio['siblings']) ? (int) $bio['siblings'] : 0,
            'nativePlace' => $bio['native_place'] ?? '',
            'aboutFamily' => $bio['about_family'] ?? '',
            'aboutSelf' => $bio['about_self'] ?? '',
            'prefAgeMin' => isset($bio['pref_age_min']) ? (int) $bio['pref_age_min'] : null,
            'prefAgeMax' => isset($bio['pref_age_max']) ? (int) $bio['pref_age_max'] : null,
            'prefHeightMin' => isset($bio['pref_height_min']) ? (int) $bio['pref_height_min'] : null,
            'prefHeightMax' => isset($bio['pref_height_max']) ? (int) $bio['pref_height_max'] : null,
            'prefEducation' => $bio['pref_education'] ?? '',
            'prefWorking' => $bio['pref_working_status'] ?? '',
            'privacy' => $privacy,
            'isPublished' => true,
            'hasBiodata' => !empty($bio),
            'createdAt' => $bio['created_at'] ?? '',
            'alreadyContacted' => isset($swiped[$uid]),
        ];
    }

    jsonSuccess(['profiles' => $out]);
}

function handleGetPlaydateDeck($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    // 1. Get User Profile and Preferences
    $profRes = supabaseRequest('GET', '/rest/v1/playdate_profiles', ['user_id' => 'eq.' . $userId]);
    $prefRes = supabaseRequest('GET', '/rest/v1/playdate_preferences', ['user_id' => 'eq.' . $userId]);
    $myProfile = isset($profRes['data'][0]) ? $profRes['data'][0] : [];
    $prefs = isset($prefRes['data'][0]) ? $prefRes['data'][0] : [];
    // 2. Get past swiped IDs
    $swipedRes = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'user_id' => 'eq.' . $userId,
        'select' => 'to_user_id'
    ]);
    $swipedIds = [];
    foreach (($swipedRes['data'] ?? []) as $row) {
        if (!empty($row['to_user_id']))
            $swipedIds[] = $row['to_user_id'];
    }
    $swipedIds[] = $userId; // exclude self

    // 3. Fetch potential candidates (Active profiles)
    $candidatesRes = supabaseRequest('GET', '/rest/v1/playdate_profiles', [
        'is_published' => 'eq.true',
        'limit' => '500' // fetch enough to filter
    ]);

    $candidates = $candidatesRes['data'] ?? [];
    $scoredCandidates = [];

    foreach ($candidates as $cand) {
        if (in_array($cand['user_id'], $swipedIds))
            continue;

        // STAGE 1: Hard Filtering
        if (!empty($prefs['pref_gender']) && $prefs['pref_gender'] !== 'Any' && ($cand['gender'] ?? '') !== $prefs['pref_gender'])
            continue;
        if (!empty($prefs['pref_marital_status']) && $prefs['pref_marital_status'] !== 'Any' && ($cand['marital_status'] ?? '') !== $prefs['pref_marital_status'])
            continue;

        $age = (int) ($cand['age'] ?? 0);
        if (!empty($prefs['pref_age_min']) && $age < $prefs['pref_age_min'])
            continue;
        if (!empty($prefs['pref_age_max']) && $age > $prefs['pref_age_max'])
            continue;

        // Exact same gotra exclusion (if applicable)
        if (!empty($myProfile['gotra']) && !empty($cand['gotra']) && strtolower(trim($myProfile['gotra'])) === strtolower(trim($cand['gotra']))) {
            continue; // Exclude same gotra
        }

        // STAGE 2: Weighted Scoring
        $score = 0;

        // Breed/Pet Type Match (30 pts)
        if (!empty($prefs['pref_pet_type']) && $prefs['pref_pet_type'] !== 'Any') {
            if (($cand['pet_type'] ?? '') === $prefs['pref_pet_type'])
                $score += 15;
        } else if (($cand['pet_type'] ?? '') === ($myProfile['pet_type'] ?? '')) {
            $score += 15;
        }

        if (!empty($prefs['pref_breed']) && $prefs['pref_breed'] !== 'Any') {
            if (($cand['breed'] ?? '') === $prefs['pref_breed'])
                $score += 15;
        } else if (($cand['breed'] ?? '') === ($myProfile['breed'] ?? '')) {
            $score += 15;
        }

        // Kundali Score (30 pts scaled from 36)
        $guna = calculateGunaScorePHP($myProfile, $cand);
        $gunaPoints = ($guna['total'] / 36.0) * 30.0;
        $score += $gunaPoints;

        // Age/Income/Education (40 pts)
        // We give full 40 if they fall in preferences, or partial if close.
        $score += 40; // baseline, assuming they passed hard filtering

        $cand['match_score'] = min(100, round($score));
        $scoredCandidates[] = $cand;
    }

    // Sort by match_score DESC
    usort($scoredCandidates, function ($a, $b) {
        return $b['match_score'] <=> $a['match_score'];
    });

    // Return top 20
    jsonSuccess(['profiles' => array_slice($scoredCandidates, 0, 20), 'preferences' => $prefs]);
}

function handleSwipePlaydate($data)
{
    $fromUserId = requireUuid($data['user_id'] ?? '', 'user_id');
    $toUserId = requireUuid($data['targetUserId'] ?? '', 'targetUserId');
    $action = $data['action'] ?? 'PASS'; // 'LIKE' or 'PASS'

    if ($fromUserId === $toUserId) {
        jsonError('Cannot swipe on yourself.');
        return;
    }

    $status = $action === 'LIKE' ? 'pending' : 'rejected';

    // Check if mutual
    $isMutual = false;
    if ($action === 'LIKE') {
        $check = supabaseRequest('GET', '/rest/v1/playdate_interests', [
            'user_id' => 'eq.' . $toUserId,
            'to_user_id' => 'eq.' . $fromUserId,
            'status' => 'eq.pending'
        ]);
        if (!empty($check['data'])) {
            $isMutual = true;
            $status = 'accepted';

            // update theirs to accepted
            supabaseRequest('PATCH', '/rest/v1/playdate_interests', [
                'id' => 'eq.' . $check['data'][0]['id']
            ], ['status' => 'accepted']);

            // send notification to both
            createNotification($toUserId, 'playdate_mutual_match', "It's a Match!", "You have a mutual match!", ['matched_user_id' => $fromUserId]);
        } else {
            // Non-mutual like: notify the recipient that a connection request arrived,
            // so it lands in their playdate window and home notification screen.
            $fromName = 'A breed member';
            $fromProfiles = fetchProfilesMap([$fromUserId]);
            if (!empty($fromProfiles[$fromUserId]['full_name'])) {
                $fromName = $fromProfiles[$fromUserId]['full_name'];
            }
            createNotification(
                $toUserId,
                'playdate_interest',
                'New playdate connection request',
                $fromName . ' sent you a connection request. Open Playdate to respond.',
                ['from_user_id' => $fromUserId]
            );
        }
    }

    // Insert ours
    $res = supabaseRequest('POST', '/rest/v1/playdate_interests', ['on_conflict' => 'user_id,to_user_id'], [
        'user_id' => $fromUserId,
        'to_user_id' => $toUserId,
        'status' => $status
    ], ['Prefer: resolution=merge-duplicates']);

    jsonSuccess(['mutual' => $isMutual]);
}

function handleGetPlaydateMatches($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $res1 = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'user_id' => 'eq.' . $userId,
        'status' => 'eq.accepted',
        'select' => 'to_user_id,playdate_profiles!playdate_interests_to_user_id_fkey(*)'
    ]);
    $res2 = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'to_user_id' => 'eq.' . $userId,
        'status' => 'eq.accepted',
        'select' => 'user_id,playdate_profiles!playdate_interests_user_id_fkey(*)'
    ]);

    $matches = [];
    foreach (($res1['data'] ?? []) as $row) {
        if (!empty($row['playdate_profiles']))
            $matches[] = $row['playdate_profiles'];
    }
    foreach (($res2['data'] ?? []) as $row) {
        if (!empty($row['playdate_profiles']))
            $matches[] = $row['playdate_profiles'];
    }

    // unique
    $unique = [];
    foreach ($matches as $m) {
        if (!isset($unique[$m['id']]))
            $unique[$m['id']] = $m;
    }

    jsonSuccess(['matches' => array_values($unique)]);
}

function handleSearchPlaydate($data)
{
    $query = ['is_published' => 'eq.true', 'order' => 'created_at.desc', 'limit' => '50'];

    if (!empty($data['gender']))
        $query['gender'] = 'eq.' . $data['gender'];
    if (!empty($data['mangalik']))
        $query['mangalik'] = 'eq.' . $data['mangalik'];
    if (!empty($data['city']))
        $query['current_city'] = 'ilike.*' . $data['city'] . '*';

    $res = supabaseRequest('GET', '/rest/v1/playdate_profiles', $query);

    jsonSuccess(['profiles' => $res['data'] ?? []]);
}

function handleSendPlaydateInterest($data)
{
    $fromUserId = requireUuid($data['user_id'] ?? '', 'user_id');
    $toUserId = requireUuid($data['to_user_id'] ?? '', 'to_user_id');

    if ($fromUserId === $toUserId) {
        jsonError('Cannot send interest to yourself.', 400);
        return;
    }

    // Check for existing interest
    $existing = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'from_user_id' => 'eq.' . $fromUserId,
        'to_user_id' => 'eq.' . $toUserId,
        'limit' => '1',
    ]);

    if (!empty($existing['data'])) {
        jsonError('Interest already sent.', 409);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/playdate_interests', [], [
        'from_user_id' => $fromUserId,
        'to_user_id' => $toUserId,
        'status' => 'pending',
        'message' => substr(trim((string) ($data['message'] ?? '')), 0, 500),
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400) {
        jsonError('Failed to send interest.', 500);
        return;
    }

    jsonSuccess(['interest' => $res['data'][0] ?? $res['data']]);
}

function handleRespondPlaydateInterest($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $interestId = requireUuid($data['interest_id'] ?? '', 'interest_id');
    $response = in_array($data['response'] ?? '', ['accepted', 'rejected']) ? $data['response'] : 'rejected';

    $res = supabaseRequest('PATCH', '/rest/v1/playdate_interests', [
        'id' => 'eq.' . $interestId,
        'to_user_id' => 'eq.' . $userId,
    ], [
        'status' => $response,
        'responded_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400) {
        jsonError('Failed to respond to interest.', 500);
        return;
    }

    jsonSuccess(['interest' => $res['data'][0] ?? $res['data'], 'status' => $response]);
}

function handleGetPlaydateInterests($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $sent = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'from_user_id' => 'eq.' . $userId,
        'order' => 'created_at.desc',
    ]);

    $received = supabaseRequest('GET', '/rest/v1/playdate_interests', [
        'to_user_id' => 'eq.' . $userId,
        'order' => 'created_at.desc',
    ]);

    jsonSuccess([
        'sent' => $sent['data'] ?? [],
        'received' => $received['data'] ?? [],
    ]);
}

function handleRequestWhatsappVerification($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? $data['user_id'] ?? '', 'user_id');
    $number = normalizeWhatsappNumber($data['number'] ?? '', whatsappConfig()['default_country']);
    if ($number === '' || strlen($number) < 8) {
        jsonError('Enter a valid WhatsApp number.', 400);
        return;
    }

    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expires = time() + 600; // 10 minutes

    $write = writePrivacySettings($userId, [
        'whatsappOtpHash' => hashSessionSecret($number . '|' . $code),
        'whatsappOtpExpires' => $expires,
        'whatsappOtpNumber' => $number,
    ]);
    if (($write['code'] ?? 500) >= 300) {
        jsonError('Could not start verification. Please try again.', 502);
        return;
    }

    $message = "Your PawCircle verification code is $code. It expires in 10 minutes. "
        . "Reply STOP to opt out of WhatsApp updates.";
    $result = sendWhatsAppMessage($number, $message, proactiveWhatsappOpts($message));
    if (empty($result['ok'])) {
        jsonError('Could not send the verification code over WhatsApp.', 502, [
            'detail' => $result['detail'] ?? ($result['error'] ?? 'send_failed'),
        ]);
        return;
    }

    jsonSuccess([
        'sent' => true,
        'mocked' => !empty($result['mocked']),
        'number' => $number,
        // In dev (no live credentials) surface the code so the flow is testable.
        'dev_code' => (!empty($result['mocked']) && PAWCIRCLE_DEBUG) ? $code : null,
    ]);
}

function whatsappConfig()
{
    return [
        'token' => envValue('WHATSAPP_ACCESS_TOKEN', ''),
        'phone_number_id' => envValue('WHATSAPP_PHONE_NUMBER_ID', ''),
        'waba_id' => envValue('WHATSAPP_BUSINESS_ACCOUNT_ID', ''),
        'version' => envValue('WHATSAPP_API_VERSION', 'v21.0'),
        'default_country' => preg_replace('/\D+/', '', envValue('WHATSAPP_DEFAULT_COUNTRY_CODE', '91')),
    ];
}

function whatsappEnabled()
{
    $c = whatsappConfig();
    return $c['token'] !== '' && $c['phone_number_id'] !== '';
}

function normalizeWhatsappNumber($number, $defaultCountry = '91')
{
    $digits = preg_replace('/\D+/', '', (string) $number);
    if ($digits === '') {
        return '';
    }
    $digits = ltrim($digits, '0');
    if (strlen($digits) <= 10) {
        $digits = $defaultCountry . $digits;
    }
    return $digits;
}

function proactiveWhatsappOpts($message)
{
    $tpl = envValue('WHATSAPP_DEFAULT_TEMPLATE', '');
    if ($tpl === '') {
        return [];
    }
    return [
        'template' => $tpl,
        'lang' => envValue('WHATSAPP_DEFAULT_TEMPLATE_LANG', 'en_US'),
        'params' => [$message],
    ];
}

function getUserWhatsappTarget($userId)
{
    if (!$userId) {
        return null;
    }
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'mobile_number,privacy_settings',
        'limit' => '1',
    ]);
    if (($res['code'] ?? 500) >= 300 || empty($res['data'])) {
        return null;
    }
    $p = $res['data'][0];
    $raw = $p['privacy_settings'] ?? [];
    $ps = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
    return [
        'opted_in' => (bool) ($ps['whatsappNotifications'] ?? $ps['whatsapp'] ?? false),
        'number' => trim((string) ($ps['whatsappNumber'] ?? $p['mobile_number'] ?? '')),
    ];
}

function notifyUserWhatsApp($userId, $message, $force = false)
{
    try {
        $target = getUserWhatsappTarget($userId);
        if (!$target || $target['number'] === '') {
            return false;
        }
        if (!$force && !$target['opted_in']) {
            return false;
        }
        $res = sendWhatsAppMessage($target['number'], $message, proactiveWhatsappOpts($message));
        return !empty($res['ok']);
    } catch (\Throwable $e) {
        error_log("[pawcircle][" . requestId() . "] notifyUserWhatsApp failed: " . $e->getMessage());
        return false;
    }
}

function handleSendWhatsapp($data)
{
    $number = trim((string) ($data['number'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));

    if (empty($number) || empty($message)) {
        jsonError('Number and message are required.', 400);
        return;
    }

    $opts = [];
    if (!empty($data['template'])) {
        $opts['template'] = (string) $data['template'];
        $opts['lang'] = (string) ($data['lang'] ?? envValue('WHATSAPP_DEFAULT_TEMPLATE_LANG', 'en_US'));
        if (!empty($data['params']) && is_array($data['params'])) {
            $opts['params'] = $data['params'];
        }
    }

    $result = sendWhatsAppMessage($number, $message, $opts);
    if (empty($result['ok'])) {
        jsonError('Failed to send WhatsApp message.', 502, ['detail' => $result['detail'] ?? ($result['error'] ?? 'unknown')]);
        return;
    }

    jsonSuccess([
        'sent' => true,
        'mocked' => !empty($result['mocked']),
        'message_id' => $result['message_id'] ?? null,
    ]);
}