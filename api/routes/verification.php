<?php
/**
 * "Verified Pet Parent" flow — replaces eSamaj's Aadhaar/PAN/passport KYC
 * (admin_core.php's handleSubmitVerificationRequest, id_type restricted to
 * government ID types). The live verification_requests table had already
 * been partly forward-ported (parent_name, microchip_number instead of
 * full_name/id_number — same "schema ahead of app code" pattern seen
 * elsewhere in this rebuild), but microchip_number was NOT NULL, which
 * would force every submission through microchip ownership even though not
 * every pet is chipped. Migration redesign_verification_requests_for_pet_parent
 * made it nullable, renamed id_type -> proof_type with a proof-type check
 * constraint, and added pet_photo_url/owner_photo_url/proof_document_url/
 * current_city so proof can be a photo pair or a document instead.
 *
 * Admin review (handleAdminListVerificationRequests / ...ReviewVerification
 * Request) is wired through this app's own admin_roles/requireAdminMode
 * gate (see auth.php) rather than eSamaj's simpler requireGlobalAdminCapability
 * — the actual admin dashboard UI to drive it is a step 11 concern, this
 * just exposes the actions.
 */

function verificationAllowedProofTypes()
{
    return ['microchip', 'vet_record', 'adoption_papers', 'photo_only'];
}

function handleSubmitVerification($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $existing = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'user_id' => 'eq.' . $userId,
        'status' => 'eq.pending',
        'select' => 'id',
        'limit' => '1',
    ]);
    if (!supabaseFailed($existing) && !empty($existing['data'])) {
        jsonError("A verification request is already pending for this account.", 409);
        return;
    }

    $parentName = cleanNullableText($data['parent_name'] ?? '', 150);
    if (!$parentName) {
        jsonError("parent_name is required.", 400);
        return;
    }

    $proofType = strtolower(trim((string) ($data['proof_type'] ?? '')));
    if (!in_array($proofType, verificationAllowedProofTypes(), true)) {
        jsonError("Invalid proof_type.", 400);
        return;
    }

    $microchipNumber = cleanNullableText($data['microchip_number'] ?? '', 60);
    if ($proofType === 'microchip' && !$microchipNumber) {
        jsonError("microchip_number is required for microchip verification.", 400);
        return;
    }

    $petPhotoUrl = cleanNullableText($data['pet_photo_url'] ?? '', 500);
    $ownerPhotoUrl = cleanNullableText($data['owner_photo_url'] ?? '', 500);
    if (!$petPhotoUrl || !$ownerPhotoUrl) {
        jsonError("A pet photo and an owner photo are both required.", 400);
        return;
    }

    $proofDocumentUrl = cleanNullableText($data['proof_document_url'] ?? '', 500);
    if (in_array($proofType, ['vet_record', 'adoption_papers'], true) && !$proofDocumentUrl) {
        jsonError("A supporting document photo is required for this proof type.", 400);
        return;
    }

    $body = [
        'user_id' => $userId,
        'parent_name' => $parentName,
        'proof_type' => $proofType,
        'microchip_number' => $microchipNumber,
        'pet_photo_url' => $petPhotoUrl,
        'owner_photo_url' => $ownerPhotoUrl,
        'proof_document_url' => $proofDocumentUrl,
        'current_city' => cleanNullableText($data['current_city'] ?? '', 150),
        'reason' => cleanNullableText($data['reason'] ?? '', 1000),
        'status' => 'pending',
    ];

    $res = supabaseRequest('POST', '/rest/v1/verification_requests', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to submit verification request.", $res);
        return;
    }

    jsonSuccess(['request' => $res['data'][0]]);
}

function handleGetMyVerificationStatus($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'is_verified,verified_at',
        'limit' => '1',
    ]);
    $isVerified = !supabaseFailed($userRes) && !empty($userRes['data']) ? !!$userRes['data'][0]['is_verified'] : false;
    $verifiedAt = !supabaseFailed($userRes) && !empty($userRes['data']) ? ($userRes['data'][0]['verified_at'] ?? null) : null;

    $reqRes = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'user_id' => 'eq.' . $userId,
        'select' => 'id,proof_type,status,reason,created_at,reviewed_at',
        'order' => 'created_at.desc',
        'limit' => '1',
    ]);
    $latestRequest = !supabaseFailed($reqRes) && !empty($reqRes['data']) ? $reqRes['data'][0] : null;

    jsonSuccess([
        'is_verified' => $isVerified,
        'verified_at' => $verifiedAt,
        'latest_request' => $latestRequest,
    ]);
}

function handleAdminListVerificationRequests($data)
{
    $params = ['select' => 'id,user_id,parent_name,proof_type,microchip_number,pet_photo_url,owner_photo_url,proof_document_url,current_city,reason,status,created_at,reviewed_at,reviewed_by'];
    $status = $data['status'] ?? null;
    if ($status && in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $params['status'] = 'eq.' . $status;
    }
    $params['order'] = 'created_at.desc';

    $res = supabaseRequest('GET', '/rest/v1/verification_requests', $params);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch verification requests.", $res);
        return;
    }

    $requests = $res['data'] ?? [];
    $profileMap = fetchProfilesMap(normalizeUuidList(array_column($requests, 'user_id')));
    foreach ($requests as &$r) {
        $p = $profileMap[$r['user_id']] ?? null;
        $r['pet_name'] = $p['pet_name'] ?? null;
        $r['pet_type'] = $p['pet_type'] ?? null;
        $r['breed'] = $p['breed'] ?? null;
    }
    unset($r);

    jsonSuccess(['requests' => $requests]);
}

function handleAdminReviewVerificationRequest($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $requestId = requireUuid($data['request_id'] ?? '', 'request_id');
    $action = strtolower(trim((string) ($data['action'] ?? '')));

    if (!in_array($action, ['approve', 'reject'], true)) {
        jsonError('action must be "approve" or "reject".', 400);
        return;
    }

    $reqRes = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'id' => 'eq.' . $requestId,
        'select' => 'id,user_id,status',
        'limit' => '1',
    ]);
    if (supabaseFailed($reqRes) || empty($reqRes['data'])) {
        jsonError("Verification request not found.", 404);
        return;
    }
    $vreq = $reqRes['data'][0];
    if ($vreq['status'] !== 'pending') {
        jsonError("This request has already been reviewed.", 409);
        return;
    }

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';
    $updateRes = supabaseRequest('PATCH', '/rest/v1/verification_requests', ['id' => 'eq.' . $requestId], [
        'status' => $newStatus,
        'reviewed_at' => nowIsoUtc(),
        'reviewed_by' => $actorId,
    ], ['Prefer: return=minimal']);
    if (supabaseFailed($updateRes)) {
        sendSupabaseError("Failed to update verification request.", $updateRes);
        return;
    }

    if ($action === 'approve') {
        supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $vreq['user_id']], [
            'is_verified' => true,
            'verified_at' => nowIsoUtc(),
            'verified_by' => $actorId,
        ], ['Prefer: return=minimal']);
        createNotification($vreq['user_id'], 'verification_approved', "You're a Verified Pet Parent!", "Your verification request was approved.", ['request_id' => $requestId]);
    } else {
        createNotification($vreq['user_id'], 'verification_rejected', 'Verification request update', "Your verification request wasn't approved. You can submit a new one anytime.", ['request_id' => $requestId]);
    }

    jsonSuccess(['reviewed' => true, 'action' => $action, 'request_id' => $requestId]);
}
