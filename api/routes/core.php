<?php
require_once __DIR__ . '/s3_storage.php';

/**
 * Core profile system: account/pet profile read+update, pet_type/breed
 * changes, and photo uploads to Supabase Storage. Adapted from PetCircle's
 * current core.php/auth.php (which already have this mostly right — no
 * legacy religious content lives here), with the WhatsApp side-effects on
 * handleUpdateProfile dropped (that integration isn't part of this rebuild)
 * and handleChangePetTypeBreed's group-membership cleanup kept, since it's
 * a genuinely good pet-native rule: your groups are scoped by pet_type/breed,
 * so changing either drops memberships that no longer make sense.
 */

function cleanNullableText($value, $maxLength = 500)
{
    if ($value === null || $value === '')
        return null;
    $clean = trim(strip_tags((string) $value));
    if ($clean === '')
        return null;
    return substr($clean, 0, $maxLength);
}

function cleanTextValue($value, $maxLength = 5000)
{
    $text = trim((string) ($value ?? ''));
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($maxLength > 0 && strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }
    return $text;
}

function cleanDateValue($value)
{
    $value = trim((string) ($value ?? ''));
    if ($value === '')
        return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function requireFields($data, $fields)
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            jsonError(implode(', ', $fields) . " required.", 400, ["missing" => $field]);
            return false;
        }
    }
    return true;
}

function normalizeUuidList($ids)
{
    $out = [];
    foreach ((array) $ids as $id) {
        $id = trim((string) $id);
        if ($id !== '' && preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
            $out[] = strtolower($id);
        }
    }
    return array_values(array_unique($out));
}

// Matches the live profiles_visibility_check / gallery_collections_visibility_check
// DB constraints exactly — 'friends' is not a valid value, 'breed'/'pet_type' are.
function normalizeVisibility($value)
{
    $value = strtolower(trim((string) $value));
    return in_array($value, ['public', 'breed', 'pet_type', 'private'], true) ? $value : 'public';
}

function getAccountProfile($userId)
{
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id,full_name,pet_name,parent_name,breed,pet_type,current_city,mobile_number,date_of_birth,gender,bio,profile_photo_url,cover_photo_url,microchip_number,is_public,visibility,online_status,social_links,membership_applied,status,primary_interests,skills,privacy_settings',
        'limit' => '1',
    ]);

    if (!supabaseFailed($res) && !empty($res['data'])) {
        $profile = $res['data'][0];
        $profile['breed'] = $profile['breed'] ?? '';
        $profile['pet_type'] = $profile['pet_type'] ?? '';
        return $profile;
    }

    return [];
}

function fetchProfilesMap($userIds)
{
    $userIds = normalizeUuidList($userIds);
    if (empty($userIds))
        return [];

    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'in.(' . implode(',', $userIds) . ')',
        'select' => 'user_id,pet_name,parent_name,full_name,pet_type,breed,profile_photo_url,current_city',
    ]);
    if (supabaseFailed($res))
        return [];

    $map = [];
    foreach (($res['data'] ?? []) as $profile) {
        if (!empty($profile['user_id'])) {
            $map[$profile['user_id']] = $profile;
        }
    }
    return $map;
}

// Presence is derived from users.last_active_at at read time (see plan step
// 14 Part C) unless the pet parent has set a manual override via Settings >
// Security (step 16 Part C1) — profiles.online_status: 'auto' (or empty)
// falls through to the activity-based computation below; any of
// online/away/busy/offline wins outright, matching eSamaj's own simple,
// no-expiry manual-status behavior.
function derivePresenceStatus($lastActiveAt, $manualStatus = null)
{
    if (in_array($manualStatus, ['online', 'away', 'busy', 'offline'], true)) {
        return $manualStatus;
    }
    if (!$lastActiveAt) {
        return 'offline';
    }
    $ts = strtotime($lastActiveAt);
    if ($ts === false) {
        return 'offline';
    }
    $diffSeconds = time() - $ts;
    if ($diffSeconds < 5 * 60) {
        return 'online';
    }
    if ($diffSeconds < 30 * 60) {
        return 'away';
    }
    return 'offline';
}

function fetchPresenceMap($userIds)
{
    $userIds = normalizeUuidList($userIds);
    if (empty($userIds)) {
        return [];
    }
    $res = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'in.(' . implode(',', $userIds) . ')',
        'select' => 'id,last_active_at,profiles(online_status)',
    ]);
    if (supabaseFailed($res)) {
        return [];
    }
    $map = [];
    foreach (($res['data'] ?? []) as $row) {
        $profileEmbed = normaliseProfileEmbed($row['profiles'] ?? []);
        $manualStatus = $profileEmbed['online_status'] ?? null;
        $map[$row['id']] = derivePresenceStatus($row['last_active_at'] ?? null, $manualStatus);
    }
    return $map;
}

// Resolves the relationship between two users for the profile-viewing UI's
// action button (Add Friend / Request Pending / Message / Your Profile).
function resolveFriendshipStatus($userId, $otherId)
{
    if ($userId === $otherId) {
        return ['status' => 'self', 'friendship_id' => null];
    }
    $res = supabaseRequest('GET', '/rest/v1/friendships', [
        'or' => '(and(requester.eq.' . $userId . ',addressee.eq.' . $otherId . '),and(requester.eq.' . $otherId . ',addressee.eq.' . $userId . '))',
        'select' => 'id,requester,addressee,status',
        'limit' => '1',
    ]);
    if (supabaseFailed($res) || empty($res['data'])) {
        return ['status' => 'none', 'friendship_id' => null];
    }
    $row = $res['data'][0];
    if ($row['status'] === 'accepted') {
        return ['status' => 'accepted', 'friendship_id' => $row['id']];
    }
    return ['status' => $row['requester'] === $userId ? 'pending_sent' : 'pending_received', 'friendship_id' => $row['id']];
}

// Own profile (no target_user_id, or target === caller) returns everything,
// unfiltered — matches the existing behavior every current self-fetch caller
// (hub_widgets.js, profile.js) already relies on. Viewing ANOTHER member's
// profile applies real privacy filtering: normalizeVisibility()'s
// public|pet_type|breed|private against the viewer's own pet_type/breed
// (bypassed entirely for accepted friends), plus per-field hide_phone/
// hide_email toggles from profiles.privacy_settings. A blocked view returns
// a graceful minimal "limited" card rather than a 404.
function handleGetProfile($data)
{
    $callerId = requireUuid($data['user_id'] ?? '', 'user_id');
    $targetId = !empty($data['target_user_id']) ? requireUuid($data['target_user_id'], 'target_user_id') : $callerId;
    if (!$targetId) {
        return;
    }

    $profile = getAccountProfile($targetId);
    if (empty($profile)) {
        jsonError("Profile not found.", 404);
        return;
    }

    // is_verified/handle live on users, not profiles — getAccountProfile()'s
    // select is shared by other callers that don't need them, so a small
    // targeted query here rather than widening it.
    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $targetId,
        'select' => 'is_verified,verified_at,handle',
        'limit' => '1',
    ]);
    if (!supabaseFailed($userRes) && !empty($userRes['data'])) {
        $profile['is_verified'] = $userRes['data'][0]['is_verified'] ?? false;
        $profile['verified_at'] = $userRes['data'][0]['verified_at'] ?? null;
        $profile['handle'] = $userRes['data'][0]['handle'] ?? null;
    }

    if ($targetId === $callerId) {
        $profile['presence'] = fetchPresenceMap([$targetId])[$targetId] ?? 'offline';
        jsonSuccess(['profile' => $profile, 'friendship' => ['status' => 'self', 'friendship_id' => null]]);
        return;
    }

    $friendship = resolveFriendshipStatus($callerId, $targetId);
    $isFriend = $friendship['status'] === 'accepted';
    $privacy = is_array($profile['privacy_settings'] ?? null) ? $profile['privacy_settings'] : [];

    if (!$isFriend) {
        $visibility = normalizeVisibility($profile['visibility'] ?? 'public');
        $allowed = true;
        if ($visibility === 'private') {
            $allowed = false;
        } elseif ($visibility === 'pet_type' || $visibility === 'breed') {
            $viewerProfile = getAccountProfile($callerId);
            $allowed = $visibility === 'pet_type'
                ? (($viewerProfile['pet_type'] ?? null) === ($profile['pet_type'] ?? null))
                : (($viewerProfile['breed'] ?? null) === ($profile['breed'] ?? null));
        }
        if (!$allowed) {
            jsonSuccess([
                'profile' => [
                    'user_id' => $profile['user_id'],
                    'pet_name' => $profile['pet_name'],
                    'profile_photo_url' => $profile['profile_photo_url'],
                    'pet_type' => $profile['pet_type'],
                    'is_limited' => true,
                ],
                'friendship' => $friendship,
            ]);
            return;
        }

        if (!empty($privacy['hide_phone'])) {
            $profile['mobile_number'] = null;
        }
    }

    $profile['presence'] = empty($privacy['hide_online_status']) || $isFriend
        ? (fetchPresenceMap([$targetId])[$targetId] ?? 'offline')
        : 'hidden';

    jsonSuccess(['profile' => $profile, 'friendship' => $friendship]);
}

function handleUpdateProfile($data)
{
    if (empty($data['user_id'])) {
        jsonError("user_id is required.", 400);
        return;
    }
    $userId = $data['user_id'];

    $allowed = [
        'pet_name',
        'parent_name',
        'full_name',
        'date_of_birth',
        'gender',
        'bio',
        'current_city',
        'microchip_number',
        'profile_photo_url',
        'cover_photo_url',
        'occupation',
    ];

    $update = [];
    foreach ($allowed as $field) {
        if (isset($data[$field])) {
            $update[$field] = is_string($data[$field]) ? cleanNullableText($data[$field], $field === 'bio' ? 800 : 240) : $data[$field];
        }
    }

    if (isset($data['mobile_number'])) {
        $update['mobile_number'] = cleanNullableText($data['mobile_number'], 40);
    }

    if (array_key_exists('visibility', $data)) {
        $visibility = normalizeVisibility($data['visibility']);
        $update['visibility'] = $visibility;
        $update['is_public'] = $visibility !== 'private';
    }

    // pet_type/breed are changed via change_pet_type_breed (it also has to
    // clean up now-mismatched group memberships), not this generic update.

    if (empty($update)) {
        jsonSuccess(["message" => "Nothing to update."]);
        return;
    }

    $existing = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'profile_photo_url,cover_photo_url',
    ]);
    $oldProfilePhoto = null;
    $oldCoverPhoto = null;
    if (!supabaseFailed($existing) && !empty($existing['data'])) {
        $oldProfilePhoto = $existing['data'][0]['profile_photo_url'] ?? null;
        $oldCoverPhoto = $existing['data'][0]['cover_photo_url'] ?? null;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], $update, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Profile update failed.", $res);
        return;
    }

    jsonSuccess(["message" => "Profile updated.", "profile" => $res['data'][0]]);

    // Clean up replaced photo files from storage after responding.
    if (isset($update['profile_photo_url']) && $oldProfilePhoto && $oldProfilePhoto !== $update['profile_photo_url']) {
        if ($s3Info = parseS3Url($oldProfilePhoto)) {
            s3DeleteObject($s3Info['key']);
        } else if ($parsed = parsePublicStorageUrl($oldProfilePhoto)) {
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
        }
    }
    if (isset($update['cover_photo_url']) && $oldCoverPhoto && $oldCoverPhoto !== $update['cover_photo_url']) {
        if ($s3Info = parseS3Url($oldCoverPhoto)) {
            s3DeleteObject($s3Info['key']);
        } else if ($parsed = parsePublicStorageUrl($oldCoverPhoto)) {
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
        }
    }
}

function handleChangePetTypeBreed($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $petType = cleanNullableText($data['pet_type'] ?? '', 80);
    $breed = cleanNullableText($data['breed'] ?? '', 140);
    if (!$userId || !$petType || !$breed) {
        jsonError("user_id, pet_type and breed are required.", 400);
        return;
    }

    $oldProfile = getAccountProfile($userId);

    // Groups are scoped by pet_type/breed — changing either means existing
    // memberships no longer make sense, so drop them (matches PetCircle's
    // existing rule; groups themselves land in the build-sequence step 4).
    $membershipRes = supabaseRequest('GET', '/rest/v1/group_members', [
        'user_id' => 'eq.' . $userId,
        'select' => 'group_id',
    ]);
    $removedGroupIds = normalizeUuidList(array_column($membershipRes['data'] ?? [], 'group_id'));
    if (!empty($removedGroupIds)) {
        supabaseRequest('DELETE', '/rest/v1/group_members', ['user_id' => 'eq.' . $userId]);
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
    ], [
        'pet_type' => $petType,
        'breed' => $breed,
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to change pet type/breed.", $res);
        return;
    }

    jsonSuccess([
        "profile" => $res['data'][0] ?? getAccountProfile($userId),
        "removed_group_count" => count($removedGroupIds),
        "previous" => [
            "pet_type" => $oldProfile['pet_type'] ?? null,
            "breed" => $oldProfile['breed'] ?? null,
        ],
    ]);
}

function parsePublicStorageUrl($url)
{
    if (empty($url))
        return null;
    $marker = '/storage/v1/object/public/';
    $pos = strpos($url, $marker);
    if ($pos === false)
        return null;
    $sub = substr($url, $pos + strlen($marker));
    $parts = explode('/', $sub, 2);
    if (count($parts) < 2)
        return null;
    return ['bucket' => $parts[0], 'path' => $parts[1]];
}

function supabaseStorageDelete($bucket, $path)
{
    $supabaseUrl = rtrim(envValue('SUPABASE_URL'), '/');
    $secretKey = envValue('SUPABASE_SECRET_KEY');
    if (!$bucket || !$path)
        return false;
    $ch = curl_init("{$supabaseUrl}/storage/v1/object/{$bucket}/{$path}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$secretKey}", "apikey: {$secretKey}"]);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ($httpCode === 200 || $httpCode === 204);
}

function handlePhotoUpload()
{
    $supabaseUrl = rtrim(envValue('SUPABASE_URL'), '/');
    $secretKey = envValue('SUPABASE_SECRET_KEY');
    $maxPostMediaBytes = 50 * 1024 * 1024;

    if (!isset($_FILES['photo'])) {
        jsonError("No photo field in request.", 400);
        return;
    }
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds the server upload size limit.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds the form size limit.',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        ];
        jsonError($uploadErrors[$_FILES['photo']['error']] ?? "Upload error.", 400);
        return;
    }

    $file = $_FILES['photo'];

    // Logical bucket names map onto the actual Supabase buckets PetCircle's
    // project already has (a single shared 'profiles' bucket for both avatar
    // and cover photos, differentiated by object path).
    $bucketMap = [
        'profile-photos' => 'profiles',
        'cover-photos' => 'profiles',
        'post-media' => 'posts',
        'gallery-media' => 'gallery',
        'event-banner' => 'events',
        'verification' => 'verification',
        'reactions' => 'reactions',
        'ads' => 'ads',
    ];
    $requestedBucket = isset($_POST['bucket']) && trim((string) $_POST['bucket']) !== ''
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['bucket'])
        : 'profile-photos';
    if (!isset($bucketMap[$requestedBucket])) {
        jsonError("Invalid storage bucket.", 400);
        return;
    }
    $actualBucket = $bucketMap[$requestedBucket];

    // Post/gallery media allow larger files and video; every other bucket
    // (avatars, covers, verification docs, reaction/ad assets) stays image-only
    // at the original 2MB cap.
    $mediaBuckets = ['post-media', 'gallery-media'];
    $isMediaBucket = in_array($requestedBucket, $mediaBuckets, true);
    $imageTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $videoTypes = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v'];
    $allowedTypes = $isMediaBucket ? array_merge($imageTypes, $videoTypes) : ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes = $isMediaBucket ? (25 * 1024 * 1024) : (2 * 1024 * 1024);

    $detectedType = $file['type'];
    if (class_exists('finfo') && is_uploaded_file($file['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $sniffed = $finfo->file($file['tmp_name']);
        if ($sniffed)
            $detectedType = $sniffed;
    }
    if (!in_array($detectedType, $allowedTypes, true)) {
        jsonError($isMediaBucket
            ? "Only JPG, PNG, WebP, GIF, MP4, WebM, MOV, or M4V files are allowed."
            : "Only JPG, PNG, and WebP files are allowed.", 400);
        return;
    }

    if ($file['size'] > $maxBytes) {
        jsonError("File exceeds the " . ($isMediaBucket ? "25MB" : "2MB") . " limit.", 413);
        return;
    }

    $mimeExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-m4v' => 'm4v',
    ];
    $extension = $mimeExtensions[$detectedType];
    $filename = uniqid('media_') . '.' . $extension;

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] ?? '';
    $userId = isValidUuid($authUserId) ? strtolower($authUserId) : null;
    $folder = '';
    if ($isMediaBucket && isset($_POST['folder']) && trim((string) $_POST['folder']) !== '') {
        $folder = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['folder']), 0, 80);
    }
    $pathParts = array_filter([$userId, $folder, $filename]);
    $objectPath = implode('/', $pathParts);

    if (function_exists('s3IsConfigured') && s3IsConfigured()) {
        $s3Key = $actualBucket . '/' . $objectPath;
        $fp = fopen($file['tmp_name'], 'r');
        $s3Res = s3PutObject($s3Key, $fp, $detectedType);
        if ($s3Res['code'] === 200 || $s3Res['code'] === 204) {
            $publicUrl = s3PublicUrl($s3Key);
            jsonSuccess(["photo_url" => $publicUrl, "bucket" => $requestedBucket, "path" => $objectPath, "mime_type" => $detectedType]);
        } else {
            jsonError("Storage upload to S3 failed. HTTP " . $s3Res['code'], 500);
        }
        return;
    }

    $uploadUrl = "{$supabaseUrl}/storage/v1/object/{$actualBucket}/{$objectPath}";
    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$secretKey}",
        "apikey: {$secretKey}",
        "Content-Type: {$detectedType}",
        "x-upsert: true",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file['tmp_name']));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpCode === 200 || $httpCode === 201) {
        $publicUrl = "{$supabaseUrl}/storage/v1/object/public/{$actualBucket}/{$objectPath}";
        jsonSuccess(["photo_url" => $publicUrl, "bucket" => $requestedBucket, "path" => $objectPath, "mime_type" => $detectedType]);
    } else {
        $err = json_decode($response, true);
        error_log("[pawcircle][" . requestId() . "] photo upload failed | http=$httpCode | response=" . $response);
        jsonError("Storage upload failed: " . ($err['message'] ?? 'unknown error'), 500);
    }
}
