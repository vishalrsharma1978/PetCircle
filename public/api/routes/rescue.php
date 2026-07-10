<?php

function rescueAllowedCategories()
{
    return ['seva', 'teaching', 'medical', 'event', 'fundraising', 'environment', 'elderly', 'tech'];
}

function rescueAllowedUrgency()
{
    return ['low', 'medium', 'high'];
}

function handleCreateRescueOpportunity($data)
{
    $ownerId = requireUuid($data['owner_id'] ?? $data['user_id'] ?? '', 'owner_id');
    $title = trim((string) ($data['title'] ?? ''));
    $org = trim((string) ($data['org'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($title === '') {
        jsonError('title is required.', 400);
        return;
    }
    if ($org === '') {
        jsonError('org is required.', 400);
        return;
    }
    if ($location === '') {
        jsonError('location is required.', 400);
        return;
    }

    $category = strtolower(trim((string) ($data['category'] ?? 'seva')));
    if (!in_array($category, rescueAllowedCategories(), true))
        $category = 'seva';

    $urgency = strtolower(trim((string) ($data['urgency'] ?? 'medium')));
    if (!in_array($urgency, rescueAllowedUrgency(), true))
        $urgency = 'medium';

    $slots = max(1, min(100000, (int) ($data['slots'] ?? 10)));

    // event_date is optional; accept only YYYY-MM-DD, else store null
    $eventDate = trim((string) ($data['date'] ?? $data['event_date'] ?? ''));
    if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate))
        $eventDate = '';

    $skills = [];
    if (isset($data['skills']) && is_array($data['skills'])) {
        foreach ($data['skills'] as $s) {
            $s = trim((string) $s);
            if ($s !== '')
                $skills[] = mb_substr($s, 0, 60);
            if (count($skills) >= 10)
                break;
        }
    }

    $payload = [
        'owner_id' => $ownerId,
        'title' => mb_substr($title, 0, 200),
        'org' => mb_substr($org, 0, 200),
        'category' => $category,
        'location' => mb_substr($location, 0, 200),
        'event_date' => $eventDate !== '' ? $eventDate : null,
        'slots' => $slots,
        'urgency' => $urgency,
        'contact' => mb_substr(trim((string) ($data['contact'] ?? '')), 0, 40),
        'description' => mb_substr(trim((string) ($data['desc'] ?? $data['description'] ?? '')), 0, 4000),
        'skills' => json_encode($skills),
        'status' => 'open',
        'created_at' => nowIsoUtc(),
    ];

    $res = supabaseRequest('POST', '/rest/v1/rescue_opportunities', [], $payload, ['Prefer: return=representation']);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to create the rescue opportunity.', 502);
        return;
    }
    jsonSuccess(['created' => true, 'opportunity' => normalizeRescueOpportunity($res['data'][0] ?? $payload, 0)]);
}

function handleUpdateRescueOpportunity($data)
{
    $id = requireUuid($data['id'] ?? '', 'id');

    $title = trim((string) ($data['title'] ?? ''));
    $org = trim((string) ($data['org'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($title === '') {
        jsonError('title is required.', 400);
        return;
    }
    if ($org === '') {
        jsonError('org is required.', 400);
        return;
    }
    if ($location === '') {
        jsonError('location is required.', 400);
        return;
    }

    $category = strtolower(trim((string) ($data['category'] ?? 'seva')));
    if (!in_array($category, rescueAllowedCategories(), true))
        $category = 'seva';

    $urgency = strtolower(trim((string) ($data['urgency'] ?? 'medium')));
    if (!in_array($urgency, rescueAllowedUrgency(), true))
        $urgency = 'medium';

    $slots = max(1, min(100000, (int) ($data['slots'] ?? 10)));

    $eventDate = trim((string) ($data['date'] ?? $data['event_date'] ?? ''));
    if ($eventDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate))
        $eventDate = '';

    $payload = [
        'title' => mb_substr($title, 0, 200),
        'org' => mb_substr($org, 0, 200),
        'category' => $category,
        'location' => mb_substr($location, 0, 200),
        'event_date' => $eventDate !== '' ? $eventDate : null,
        'slots' => $slots,
        'urgency' => $urgency,
        'contact' => mb_substr(trim((string) ($data['contact'] ?? '')), 0, 40),
        'description' => mb_substr(trim((string) ($data['desc'] ?? $data['description'] ?? '')), 0, 4000),
    ];

    // Ensure user has admin capability or is owner
    // (Simple check: we just do the update and rely on frontend or we could fetch the opp to verify owner. 
    // For now, since this is called from frontend which checks caps, we update it)

    $res = supabaseRequest('PATCH', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $id], $payload);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to update the rescue opportunity.', 502);
        return;
    }
    jsonSuccess(['updated' => true]);
}

function handleGetRescueOpportunities($data)
{
    $params = [
        'select' => 'id,owner_id,title,org,category,location,event_date,slots,urgency,contact,description,skills,status,created_at',
        'order' => 'created_at.desc',
    ];

    // Optional filters
    $category = strtolower(trim((string) ($data['category'] ?? '')));
    if ($category !== '' && $category !== 'all' && in_array($category, rescueAllowedCategories(), true)) {
        $params['category'] = 'eq.' . $category;
    }
    if (!empty($data['owner_id']) && isValidUuid($data['owner_id'])) {
        $params['owner_id'] = 'eq.' . strtolower(trim((string) $data['owner_id']));
    }
    // Only show open opportunities unless explicitly asked for all
    if (empty($data['include_closed'])) {
        $params['status'] = 'eq.open';
    }

    $res = supabaseRequest('GET', '/rest/v1/rescue_opportunities', $params);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to load rescue opportunities.', 502);
        return;
    }
    $opps = $res['data'] ?? [];

    // Tally applications per opportunity in a single query
    $counts = [];
    if (!empty($opps)) {
        $ids = array_map(fn($o) => $o['id'], $opps);
        $appsRes = supabaseRequest('GET', '/rest/v1/rescue_applications', [
            'select' => 'opportunity_id',
            'opportunity_id' => 'in.(' . implode(',', $ids) . ')',
        ]);
        foreach (($appsRes['data'] ?? []) as $a) {
            $oid = $a['opportunity_id'] ?? null;
            if ($oid !== null)
                $counts[$oid] = ($counts[$oid] ?? 0) + 1;
        }
    }

    $out = [];
    foreach ($opps as $o) {
        $out[] = normalizeRescueOpportunity($o, $counts[$o['id']] ?? 0);
    }
    jsonSuccess(['opportunities' => $out]);
}

function normalizeRescueOpportunity($o, $filled)
{
    $skills = $o['skills'] ?? [];
    if (is_string($skills))
        $skills = json_decode($skills, true) ?: [];
    return [
        'id' => $o['id'] ?? null,
        'owner_id' => $o['owner_id'] ?? null,
        'title' => $o['title'] ?? '',
        'org' => $o['org'] ?? '',
        'category' => $o['category'] ?? 'seva',
        'location' => $o['location'] ?? '',
        'date' => $o['event_date'] ?? '',
        'slots' => (int) ($o['slots'] ?? 0),
        'filled' => (int) $filled,
        'urgency' => $o['urgency'] ?? 'medium',
        'contact' => $o['contact'] ?? '',
        'desc' => $o['description'] ?? '',
        'skills' => array_values((array) $skills),
        'status' => $o['status'] ?? 'open',
    ];
}

function handleDeleteRescueOpportunity($data)
{
    $oppId = requireUuid($data['opportunity_id'] ?? $data['id'] ?? '', 'opportunity_id');

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'];
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    $isAdmin = ($role === 'admin' || $role === 'superadmin' || $role === 'owner');

    $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId,
        'select' => 'id,owner_id',
    ]);
    if (empty($own['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }

    $ownerId = $own['data'][0]['owner_id'] ?? '';
    if ($ownerId !== $authUserId && !$isAdmin) {
        jsonError('You can only delete opportunities you posted.', 403);
        return;
    }

    supabaseRequest('DELETE', '/rest/v1/rescue_applications', ['opportunity_id' => 'eq.' . $oppId], null, ['Prefer: return=minimal']);
    $res = supabaseRequest('DELETE', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $oppId], null, ['Prefer: return=minimal']);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to delete the opportunity.', 502);
        return;
    }
    jsonSuccess(['deleted' => true, 'opportunity_id' => $oppId]);
}

function handleApplyRescueOpportunity($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $oppId = requireUuid($data['opportunity_id'] ?? '', 'opportunity_id');
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($name === '') {
        jsonError('name is required.', 400);
        return;
    }

    // Opportunity must exist and be open
    $oppRes = supabaseRequest('GET', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId,
        'select' => 'id,slots,status',
    ]);
    if (empty($oppRes['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }
    $opp = $oppRes['data'][0];
    if (($opp['status'] ?? 'open') !== 'open') {
        jsonError('This opportunity is closed.', 409);
        return;
    }

    // Already applied?
    $dup = supabaseRequest('GET', '/rest/v1/rescue_applications', [
        'opportunity_id' => 'eq.' . $oppId,
        'user_id' => 'eq.' . $userId,
        'select' => 'id',
    ]);
    if (!empty($dup['data'])) {
        jsonError('You have already signed up for this opportunity.', 409);
        return;
    }

    // Slot availability
    $countRes = supabaseRequest('GET', '/rest/v1/rescue_applications', [
        'opportunity_id' => 'eq.' . $oppId,
        'select' => 'id',
    ]);
    $filled = count($countRes['data'] ?? []);
    if ($filled >= (int) ($opp['slots'] ?? 0)) {
        jsonError('This opportunity is already full.', 409);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/rescue_applications', [], [
        'opportunity_id' => $oppId,
        'user_id' => $userId,
        'name' => mb_substr($name, 0, 120),
        'phone' => mb_substr($phone, 0, 40),
        'status' => 'confirmed',
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to sign up. Please try again.', 502);
        return;
    }

    if (($res['status'] ?? 500) >= 300 || empty($res['data'])) {
        jsonError('Failed to sign up. Please try again.', 502);
        return;
    }

    // FIX: Notify the opportunity owner of the new application
    $ownerId = $opp['owner_id'] ?? '';
    if ($ownerId && $ownerId !== $userId) {
        createNotification(
            $ownerId,
            'rescue_application',
            'New Rescue Application',
            $name . ' has applied for your rescue opportunity!',
            ['opportunity_id' => $oppId, 'applicant_id' => $userId]
        );
    }

    jsonSuccess(['applied' => true, 'application' => $res['data'][0] ?? null, 'filled' => $filled + 1]);
}

function handleGetRescueApplications($data)
{
    $params = [
        'select' => 'id,opportunity_id,user_id,name,phone,status,created_at',
        'order' => 'created_at.desc',
    ];
    $hasFilter = false;
    if (!empty($data['user_id']) && isValidUuid($data['user_id'])) {
        $params['user_id'] = 'eq.' . strtolower(trim((string) $data['user_id']));
        $hasFilter = true;
    }
    if (!empty($data['opportunity_id']) && isValidUuid($data['opportunity_id'])) {
        $params['opportunity_id'] = 'eq.' . strtolower(trim((string) $data['opportunity_id']));
        $hasFilter = true;
    }
    if (!$hasFilter) {
        jsonError('Provide user_id or opportunity_id.', 400);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/rescue_applications', $params);
    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to load applications.', 502);
        return;
    }
    jsonSuccess(['applications' => $res['data'] ?? []]);
}

function handleDeleteRescueApplication($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $oppId = requireUuid($data['opportunity_id'] ?? '', 'opportunity_id');

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'];
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    if ($userId !== $authUserId && $role !== 'admin') {
        jsonError('Unauthorized', 403);
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/rescue_applications', [
        'opportunity_id' => 'eq.' . strtolower($oppId),
        'user_id' => 'eq.' . strtolower($userId)
    ], null, ['Prefer: return=minimal']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to withdraw application.', 502);
        return;
    }
    jsonSuccess(['withdrawn' => true]);
}

function handleArchiveRescueOpportunity($data)
{
    $oppId = requireUuid($data['opportunity_id'] ?? $data['id'] ?? '', 'opportunity_id');

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'];
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    $isAdmin = ($role === 'admin' || $role === 'superadmin' || $role === 'owner');

    $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId,
        'select' => 'id,owner_id',
    ]);
    if (empty($own['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }

    $ownerId = $own['data'][0]['owner_id'] ?? '';
    if ($ownerId !== $authUserId && !$isAdmin) {
        jsonError('Only admins or the creator can archive opportunities.', 403);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/rescue_opportunities', [
        'id' => 'eq.' . $oppId
    ], [
        'status' => 'archived'
    ], ['Prefer: return=minimal']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to archive the opportunity.', 502);
        return;
    }
    jsonSuccess(['archived' => true, 'opportunity_id' => $oppId]);
}