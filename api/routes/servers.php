<?php
/**
 * Admin dashboard — Servers panel (infrastructure nodes: location globe &
 * health status). Ported directly from eSamaj's admin_core.php equivalents
 * with no conceptual changes — this was never religion-specific, just
 * infra/ops tooling. PetCircle's live `servers` table already has `pet_type`
 * instead of `religion` (a pre-existing schema rename, app code never
 * caught up), and `id` is `bigint` here rather than eSamaj's uuid, so ids
 * are validated as integers, not through requireUuid().
 */

function requireServerId($value)
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    if ($id === false || $id <= 0) {
        jsonError("Invalid server id.", 400);
        exit();
    }
    return $id;
}

function handleGetServers($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $res = supabaseRequest('GET', '/rest/v1/servers', [
        'select' => 'id,name,host,port,latitude,longitude,pet_type,status,latency_ms,created_at',
        'order' => 'name.asc',
    ]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch server nodes.", $res);
        return;
    }

    $servers = $res['data'] ?? [];

    if (empty($servers)) {
        $defaultNodes = [
            ['name' => 'US-West (Oregon)', 'host' => '54.212.10.45', 'port' => 443, 'latitude' => 45.823, 'longitude' => -120.312, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 45],
            ['name' => 'US-East (Virginia)', 'host' => '3.210.45.18', 'port' => 443, 'latitude' => 39.043, 'longitude' => -77.487, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 82],
            ['name' => 'EU-Central (Frankfurt)', 'host' => '18.197.80.3', 'port' => 443, 'latitude' => 50.110, 'longitude' => 8.682, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 140],
            ['name' => 'AP-South (Mumbai)', 'host' => '13.233.102.5', 'port' => 443, 'latitude' => 19.076, 'longitude' => 72.877, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 15],
            ['name' => 'AP-Northeast (Tokyo)', 'host' => '54.250.8.19', 'port' => 443, 'latitude' => 35.676, 'longitude' => 139.650, 'pet_type' => 'global', 'status' => 'online', 'latency_ms' => 115],
        ];
        foreach ($defaultNodes as $node) {
            $node['created_at'] = nowIsoUtc();
            supabaseRequest('POST', '/rest/v1/servers', [], $node);
        }
        $res = supabaseRequest('GET', '/rest/v1/servers', [
            'select' => 'id,name,host,port,latitude,longitude,pet_type,status,latency_ms,created_at',
            'order' => 'name.asc',
        ]);
        if (!supabaseFailed($res)) {
            $servers = $res['data'] ?? [];
        }
    }

    jsonSuccess(['servers' => $servers]);
}

function handleSaveServer($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $id = isset($data['id']) && $data['id'] !== '' ? requireServerId($data['id']) : null;
    $name = trim((string) ($data['name'] ?? ''));
    $host = trim((string) ($data['host'] ?? ''));
    $port = isset($data['port']) && $data['port'] !== '' ? (int) $data['port'] : null;
    $lat = isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null;
    $lon = isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null;
    $petType = trim((string) ($data['pet_type'] ?? 'global'));
    $status = trim((string) ($data['status'] ?? 'online'));

    if ($id !== null) {
        $payload = [];
        if ($name !== '')
            $payload['name'] = $name;
        if ($host !== '')
            $payload['host'] = $host;
        if ($port !== null)
            $payload['port'] = $port;
        if ($lat !== null)
            $payload['latitude'] = $lat;
        if ($lon !== null)
            $payload['longitude'] = $lon;
        if ($petType !== '')
            $payload['pet_type'] = $petType;
        if ($status !== '')
            $payload['status'] = $status;

        $res = supabaseRequest('PATCH', '/rest/v1/servers', ['id' => 'eq.' . $id], $payload, ['Prefer: return=minimal']);
        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to update server node.", $res);
            return;
        }
        jsonSuccess(['updated' => true]);
        return;
    }

    if ($name === '' || $host === '' || $port === null || $lat === null || $lon === null) {
        jsonError("Missing required parameters for new node.", 400);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/servers', [], [
        'name' => $name,
        'host' => $host,
        'port' => $port,
        'latitude' => $lat,
        'longitude' => $lon,
        'pet_type' => $petType,
        'status' => $status,
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to create server node.", $res);
        return;
    }
    jsonSuccess(['created' => true, 'server' => $res['data'][0]]);
}

function handleDeleteServer($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $id = requireServerId($data['id'] ?? '');

    $res = supabaseRequest('DELETE', '/rest/v1/servers', ['id' => 'eq.' . $id]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to decommission server node.", $res);
        return;
    }
    jsonSuccess(['deleted' => true]);
}

function handlePingServer($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $id = requireServerId($data['id'] ?? '');

    $res = supabaseRequest('GET', '/rest/v1/servers', ['id' => 'eq.' . $id, 'select' => 'id,host,port', 'limit' => '1']);
    if (supabaseFailed($res) || empty($res['data'])) {
        jsonError("Server node not found.", 404);
        return;
    }

    $server = $res['data'][0];
    $t1 = microtime(true);
    $fp = @fsockopen($server['host'], (int) $server['port'], $errno, $errstr, 2.5);
    $t2 = microtime(true);

    if (!$fp) {
        $latency = 9999;
        $status = 'offline';
    } else {
        fclose($fp);
        $latency = (int) round(($t2 - $t1) * 1000);
        $status = 'online';
    }

    supabaseRequest('PATCH', '/rest/v1/servers', ['id' => 'eq.' . $id], [
        'latency_ms' => $latency,
        'status' => $status,
    ], ['Prefer: return=minimal']);

    jsonSuccess(['id' => $id, 'status' => $status, 'latency_ms' => $latency]);
}
