<?php
/**
 * Bundles several independent read-only calls into one HTTP round trip
 * (step 24 Part C) — e.g. the dashboard's hub-widget fan-out, which
 * previously fired 6+ separate requests on every login.
 *
 * Deliberately scoped to an explicit whitelist of read-only, non-admin
 * actions rather than the full $routes map: several handlers call
 * requireUuid(), which exit()s the whole PHP process on failure — fine for
 * a single-action request, but fatal for a batch (it would kill every
 * other bundled sub-request too, not just the bad one). Restricting to a
 * known-safe whitelist and pre-validating the one caller-suppliable field
 * that flows into requireUuid() (target_user_id) keeps that failure mode
 * from ever being reached.
 */

function isValidUuidString($value)
{
    return is_string($value) && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value) === 1;
}

function handleBatchRequest($data)
{
    $requests = is_array($data['requests'] ?? null) ? $data['requests'] : [];
    if (empty($requests)) {
        jsonError('batch requires at least one sub-request.', 400);
        return;
    }
    if (count($requests) > 10) {
        jsonError('batch supports at most 10 sub-requests.', 400);
        return;
    }

    $allowed = [
        'get_app_config' => 'handleGetAppConfig',
        'get_profile' => 'handleGetProfile',
        'get_friends' => 'handleGetFriends',
        'get_community_hub' => 'handleGetCommunityHub',
        'get_ads' => 'handleGetAds',
        'get_events' => 'handleGetEvents',
    ];

    $authUserId = $data['auth_user_id'] ?? null;
    $authRole = $data['auth_role'] ?? null;
    $authSessionId = $data['auth_session_id'] ?? null;
    $callerUserId = $data['user_id'] ?? null;

    $results = [];
    foreach ($requests as $req) {
        $subAction = is_string($req['action'] ?? null) ? $req['action'] : '';
        $subPayload = is_array($req['payload'] ?? null) ? $req['payload'] : [];

        if (!isset($allowed[$subAction])) {
            $results[] = ['action' => $subAction, 'status' => 'error', 'message' => 'Action not batchable.'];
            continue;
        }
        if (isset($subPayload['target_user_id']) && !isValidUuidString($subPayload['target_user_id'])) {
            $results[] = ['action' => $subAction, 'status' => 'error', 'message' => 'Invalid target_user_id.'];
            continue;
        }

        // Always the outer, already-authenticated session's identity —
        // never let a sub-request's own payload override who's asking.
        $subPayload['auth_user_id'] = $authUserId;
        $subPayload['auth_role'] = $authRole;
        $subPayload['auth_session_id'] = $authSessionId;
        $subPayload['user_id'] = $callerUserId;

        $handlerName = $allowed[$subAction];
        ob_start();
        $handlerName($subPayload);
        $raw = ob_get_clean();
        $decoded = json_decode($raw, true);
        $results[] = array_merge(
            ['action' => $subAction],
            is_array($decoded) ? $decoded : ['status' => 'error', 'message' => 'Invalid handler response.']
        );
    }

    jsonSuccess(['results' => $results]);
}
