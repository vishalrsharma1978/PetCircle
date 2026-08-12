<?php
/**
 * Rescue & Seva marketplace — genuinely PetCircle-original (no eSamaj
 * equivalent), ported from PetCircle's public/api/routes/rescue.php with
 * two real bugs fixed along the way:
 *
 *  1. Table names: the route queried /rest/v1/rescue_opportunities and
 *     /rest/v1/rescue_applications, but the live tables were named
 *     volunteer_opportunities/volunteer_applications — fixed by renaming
 *     the tables (migration rename_volunteer_to_rescue_tables) rather than
 *     rewriting every call site, since the code/route-name/product-name all
 *     already say "rescue".
 *  2. Every mutating handler checked `($res['status'] ?? 500) >= 300` to
 *     detect failure, but supabaseRequest() returns a 'code' key, not
 *     'status' — so that check was *always* true regardless of whether the
 *     write succeeded, meaning create/update/delete/apply/archive always
 *     reported failure to the caller even on success. Replaced with the
 *     shared supabaseFailed() helper used everywhere else in this codebase.
 *
 * Also fixed: handleApplyRescueOpportunity read $opp['owner_id'] to notify
 * the organizer, but the opportunity was fetched with a select list that
 * never included owner_id, so that notification silently never fired.
 * And: 'skills' was being json_encode()'d before being placed in the
 * request body, which supabaseRequest() then json_encode()s again — double
 * encoding a jsonb array column into a jsonb *string*. Fixed by passing the
 * PHP array through directly.
 */

function rescueAllowedCategories()
{
    return ['seva', 'teaching', 'medical', 'event', 'fundraising', 'environment', 'elderly', 'tech'];
}

function rescueAllowedUrgency()
{
    return ['low', 'medium', 'high'];
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

function handleCreateRescueOpportunity($data)
{
    $ownerId = requireUuid($data['user_id'] ?? '', 'owner_id');
    $title = trim((string) ($data['title'] ?? ''));
    $org = trim((string) ($data['org'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));

    if ($title === '' || $org === '' || $location === '') {
        jsonError('Title, organization, and location are required.', 400);
        return;
    }

    $category = strtolower(trim((string) ($data['category'] ?? 'seva')));
    if (!in_array($category, rescueAllowedCategories(), true))
        $category = 'seva';

    $urgency = strtolower(trim((string) ($data['urgency'] ?? 'medium')));
    if (!in_array($urgency, rescueAllowedUrgency(), true))
        $urgency = 'medium';

    $slots = max(1, min(100000, (int) ($data['slots'] ?? 10)));

    $eventDate = trim((string) ($data['event_date'] ?? ''));
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
        'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 4000),
        'skills' => $skills,
        'status' => 'open',
    ];

    $res = supabaseRequest('POST', '/rest/v1/rescue_opportunities', [], $payload, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError('Failed to create the rescue opportunity.', $res);
        return;
    }

    jsonSuccess(['opportunity' => normalizeRescueOpportunity($res['data'][0], 0)]);
}

function handleUpdateRescueOpportunity($data)
{
    $id = requireUuid($data['id'] ?? '', 'id');
    $userId = $data['user_id'] ?? '';

    $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $id, 'select' => 'id,owner_id']);
    if (supabaseFailed($own) || empty($own['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    if ($own['data'][0]['owner_id'] !== $userId && $role !== 'admin') {
        jsonError('You can only edit opportunities you posted.', 403);
        return;
    }

    $title = trim((string) ($data['title'] ?? ''));
    $org = trim((string) ($data['org'] ?? ''));
    $location = trim((string) ($data['location'] ?? ''));
    if ($title === '' || $org === '' || $location === '') {
        jsonError('Title, organization, and location are required.', 400);
        return;
    }

    $category = strtolower(trim((string) ($data['category'] ?? 'seva')));
    if (!in_array($category, rescueAllowedCategories(), true))
        $category = 'seva';
    $urgency = strtolower(trim((string) ($data['urgency'] ?? 'medium')));
    if (!in_array($urgency, rescueAllowedUrgency(), true))
        $urgency = 'medium';
    $slots = max(1, min(100000, (int) ($data['slots'] ?? 10)));
    $eventDate = trim((string) ($data['event_date'] ?? ''));
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
        'description' => mb_substr(trim((string) ($data['description'] ?? '')), 0, 4000),
    ];

    $res = supabaseRequest('PATCH', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $id], $payload, ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to update the rescue opportunity.', $res);
        return;
    }

    jsonSuccess(['message' => 'Opportunity updated.']);
}

function handleGetRescueOpportunities($data)
{
    $params = [
        'select' => 'id,owner_id,title,org,category,location,event_date,slots,urgency,contact,description,skills,status,created_at',
        'order' => 'created_at.desc',
    ];

    $category = strtolower(trim((string) ($data['category'] ?? '')));
    if ($category !== '' && $category !== 'all' && in_array($category, rescueAllowedCategories(), true)) {
        $params['category'] = 'eq.' . $category;
    }
    if (!empty($data['owner_id']) && isValidUuid($data['owner_id'])) {
        $params['owner_id'] = 'eq.' . strtolower(trim((string) $data['owner_id']));
    }
    if (empty($data['include_closed'])) {
        $params['status'] = 'eq.open';
    }

    $res = supabaseRequest('GET', '/rest/v1/rescue_opportunities', $params);
    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to load rescue opportunities.', $res);
        return;
    }
    $opps = $res['data'] ?? [];

    $counts = [];
    if (!empty($opps)) {
        $ids = array_column($opps, 'id');
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

    $out = array_map(fn($o) => normalizeRescueOpportunity($o, $counts[$o['id']] ?? 0), $opps);
    jsonSuccess(['opportunities' => $out]);
}

function handleDeleteRescueOpportunity($data)
{
    $oppId = requireUuid($data['opportunity_id'] ?? $data['id'] ?? '', 'opportunity_id');
    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] ?? '';
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    $isAdmin = ($role === 'admin' || $role === 'owner');

    $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $oppId, 'select' => 'id,owner_id']);
    if (supabaseFailed($own) || empty($own['data'])) {
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
    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to delete the opportunity.', $res);
        return;
    }

    jsonSuccess(['opportunity_id' => $oppId]);
}

function handleArchiveRescueOpportunity($data)
{
    $oppId = requireUuid($data['opportunity_id'] ?? $data['id'] ?? '', 'opportunity_id');
    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] ?? '';
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    $isAdmin = ($role === 'admin' || $role === 'owner');

    $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $oppId, 'select' => 'id,owner_id']);
    if (supabaseFailed($own) || empty($own['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }

    $ownerId = $own['data'][0]['owner_id'] ?? '';
    if ($ownerId !== $authUserId && !$isAdmin) {
        jsonError('Only admins or the creator can archive opportunities.', 403);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $oppId], ['status' => 'archived'], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to archive the opportunity.', $res);
        return;
    }

    jsonSuccess(['opportunity_id' => $oppId]);
}

function handleApplyRescueOpportunity($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $oppId = requireUuid($data['opportunity_id'] ?? '', 'opportunity_id');
    $name = trim((string) ($data['name'] ?? ''));
    $phone = trim((string) ($data['phone'] ?? ''));
    if ($name === '') {
        jsonError('Your name is required.', 400);
        return;
    }

    // owner_id must be selected here (PetCircle's original didn't, so the
    // owner-notification below silently never fired).
    $oppRes = supabaseRequest('GET', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $oppId, 'select' => 'id,owner_id,slots,status']);
    if (supabaseFailed($oppRes) || empty($oppRes['data'])) {
        jsonError('Opportunity not found.', 404);
        return;
    }
    $opp = $oppRes['data'][0];
    if (($opp['status'] ?? 'open') !== 'open') {
        jsonError('This opportunity is closed.', 409);
        return;
    }

    $dup = supabaseRequest('GET', '/rest/v1/rescue_applications', ['opportunity_id' => 'eq.' . $oppId, 'user_id' => 'eq.' . $userId, 'select' => 'id']);
    if (!supabaseFailed($dup) && !empty($dup['data'])) {
        jsonError('You have already signed up for this opportunity.', 409);
        return;
    }

    $countRes = supabaseRequest('GET', '/rest/v1/rescue_applications', ['opportunity_id' => 'eq.' . $oppId, 'select' => 'id']);
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
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError('Failed to sign up. Please try again.', $res);
        return;
    }

    $ownerId = $opp['owner_id'] ?? '';
    if ($ownerId && $ownerId !== $userId) {
        createNotification($ownerId, 'rescue_application', 'New rescue application', "$name applied for your rescue opportunity.", ['opportunity_id' => $oppId, 'applicant_id' => $userId]);
    }

    jsonSuccess(['application' => $res['data'][0], 'filled' => $filled + 1]);
}

// api/index.php auto-injects the caller's own user_id into every
// authenticated request's $data (see the non-admin branch of the auth
// gate) — so a naive "filter by opportunity_id AND user_id" here would
// always additionally restrict results to the caller's own applications,
// which silently defeats the opportunity owner's "see who applied" view
// (PetCircle's original had this same latent issue). Split into two
// explicit, unambiguous cases instead: viewing one opportunity's full
// applicant list (owner/admin only) vs. viewing the caller's own
// applications across all opportunities.
function handleGetRescueApplications($data)
{
    if (!empty($data['opportunity_id']) && isValidUuid($data['opportunity_id'])) {
        $oppId = strtolower(trim((string) $data['opportunity_id']));
        $own = supabaseRequest('GET', '/rest/v1/rescue_opportunities', ['id' => 'eq.' . $oppId, 'select' => 'id,owner_id']);
        if (supabaseFailed($own) || empty($own['data'])) {
            jsonError('Opportunity not found.', 404);
            return;
        }
        $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] ?? '';
        $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
        $isAdmin = ($role === 'admin' || $role === 'owner');
        if ($own['data'][0]['owner_id'] !== $authUserId && !$isAdmin) {
            jsonError('Only the organizer can view applicants for this opportunity.', 403);
            return;
        }

        $res = supabaseRequest('GET', '/rest/v1/rescue_applications', [
            'opportunity_id' => 'eq.' . $oppId,
            'select' => 'id,opportunity_id,user_id,name,phone,status,created_at',
            'order' => 'created_at.desc',
        ]);
    } else {
        // No opportunity_id: "my applications" — user_id here is always the
        // caller's own (auto-injected), which is exactly what we want.
        $userId = requireUuid($data['user_id'] ?? '', 'user_id');
        $res = supabaseRequest('GET', '/rest/v1/rescue_applications', [
            'user_id' => 'eq.' . $userId,
            'select' => 'id,opportunity_id,user_id,name,phone,status,created_at',
            'order' => 'created_at.desc',
        ]);
    }

    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to load applications.', $res);
        return;
    }

    jsonSuccess(['applications' => $res['data'] ?? []]);
}

function handleDeleteRescueApplication($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $oppId = requireUuid($data['opportunity_id'] ?? '', 'opportunity_id');

    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] ?? '';
    $role = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['role'] ?? '';
    if ($userId !== $authUserId && $role !== 'admin') {
        jsonError('Unauthorized', 403);
        return;
    }

    $res = supabaseRequest('DELETE', '/rest/v1/rescue_applications', [
        'opportunity_id' => 'eq.' . $oppId,
        'user_id' => 'eq.' . $userId,
    ], null, ['Prefer: return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to withdraw application.', $res);
        return;
    }

    jsonSuccess(['message' => 'Application withdrawn.']);
}
