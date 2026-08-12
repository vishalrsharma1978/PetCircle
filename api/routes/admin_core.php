<?php
/**
 * Admin dashboard — core/platform panels. Ported from eSamaj's admin_core.php,
 * which mixes true admin-dashboard handlers with unrelated member-facing
 * features (matrimonial matching, volunteer board, comments) co-located in
 * the same file; only the actual admin-dashboard surface is ported here.
 *
 * Capability model: this rebuild's own admin_roles table (role: owner |
 * platform_admin | pet_type_admin | breed_admin; scope_type: global |
 * pet_type | breed) rather than eSamaj's simpler requireGlobalAdminCapability
 * — see requireGlobalAdminCapability()/requireOwnerCapability() below, which
 * are new here but named the same for continuity with the ported code shape.
 *
 * "Communities" (eSamaj panel): eSamaj's version lets admins create arbitrary
 * religion-scoped sub-communities with their own theme. PetCircle has no
 * such multi-tenant concept — pet_type is a fixed taxonomy, not an
 * admin-creatable entity — so this is redesigned as a "Pet Types" panel:
 * per-pet_type accent color/font theming only (see handleAdminGet/SavePetTypeThemes),
 * stored in the same generic app_settings key/value table eSamaj uses for
 * feature_visibility/feed_layout, just without the community-creation CRUD.
 */

function userAdminCapabilityRoles($userId)
{
    $caps = fetchAdminCapabilities($userId);
    return array_column($caps, 'role');
}

function requireGlobalAdminCapability($userId)
{
    $roles = userAdminCapabilityRoles($userId);
    if (!array_intersect($roles, ['owner', 'platform_admin'])) {
        jsonError("Platform admin access is required for that action.", 403);
        exit();
    }
}

function requireOwnerCapability($userId)
{
    if (!in_array('owner', userAdminCapabilityRoles($userId), true)) {
        jsonError("Owner admin access is required for that action.", 403);
        exit();
    }
}

function adminListLimit($data, $default = 25, $max = 100)
{
    return max(1, min((int) ($data['limit'] ?? $default), $max));
}

function adminOffset($data)
{
    return max(0, (int) ($data['offset'] ?? 0));
}

function petTypeVocabulary()
{
    return ['Dog', 'Cat', 'Bird', 'Fish', 'Small Pet', 'Reptile'];
}

// --- Admin mode (re-enter password to unlock admin actions for 15 min) ---

function handleEnterAdminMode($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $sessionId = requireUuid($data['auth_session_id'] ?? '', 'session_id');
    $password = (string) ($data['password'] ?? '');
    if ($password === '') {
        jsonError("Enter your password to continue.", 400);
        return;
    }

    $caps = fetchAdminCapabilities($userId);
    if (empty($caps)) {
        jsonError("Admin access is not enabled for this account.", 403);
        return;
    }

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,password_hash',
        'limit' => '1',
    ]);
    if (supabaseFailed($res) || empty($res['data'])) {
        jsonError("Could not verify your account. Please sign in again.", 401);
        return;
    }

    $user = $res['data'][0];
    if (!password_verify($password, $user['password_hash'] ?? '')) {
        markFailedLogin($userId, $user['email'] ?? '', 'invalid_admin_mode_password');
        jsonError("Password is incorrect.", 401);
        return;
    }

    $until = gmdate('c', time() + (15 * 60));
    $patch = supabaseRequest('PATCH', '/rest/v1/user_sessions', ['id' => 'eq.' . $sessionId], [
        'admin_mode_until' => $until,
    ], ['Prefer: return=minimal']);
    if (supabaseFailed($patch)) {
        sendSupabaseError("Admin mode is not configured.", $patch);
        return;
    }

    jsonSuccess([
        'message' => 'Admin mode enabled.',
        'admin_mode_active' => true,
        'admin_mode_until' => $until,
        'admin_capabilities' => $caps,
    ]);
}

function handleExitAdminMode($data)
{
    $sessionId = requireUuid($data['auth_session_id'] ?? '', 'session_id');
    supabaseRequest('PATCH', '/rest/v1/user_sessions', ['id' => 'eq.' . $sessionId], [
        'admin_mode_until' => null,
    ], ['Prefer: return=minimal']);
    jsonSuccess(['message' => 'Admin mode disabled.', 'admin_mode_active' => false]);
}

// --- Analytics ---

function handleAdminGetAnalytics($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    // supabaseRequest() doesn't parse PostgREST's Content-Range count header,
    // so counts are computed from fetched rows (consistent with how the rest
    // of this codebase counts things, e.g. communityHubActiveGroups()).
    $counts = [];
    foreach (['users', 'posts', 'groups', 'events', 'gallery_collections', 'rescue_opportunities'] as $table) {
        $res = supabaseRequest('GET', "/rest/v1/{$table}", ['select' => 'id']);
        $counts[$table] = supabaseFailed($res) ? 0 : count($res['data'] ?? []);
    }

    $activeSessionsRes = supabaseRequest('GET', '/rest/v1/user_sessions', [
        'revoked_at' => 'is.null',
        'expires_at' => 'gt.' . nowIsoUtc(),
        'select' => 'id',
    ]);
    $counts['active_sessions'] = supabaseFailed($activeSessionsRes) ? 0 : count($activeSessionsRes['data'] ?? []);

    $failedLoginsRes = supabaseRequest('GET', '/rest/v1/user_login_events', [
        'success' => 'eq.false',
        'created_at' => 'gte.' . gmdate('c', time() - 86400),
        'select' => 'id',
    ]);
    $counts['failed_logins_24h'] = supabaseFailed($failedLoginsRes) ? 0 : count($failedLoginsRes['data'] ?? []);

    $postsByTypeRes = supabaseRequest('GET', '/rest/v1/posts', ['select' => 'pet_type', 'is_deleted' => 'eq.false']);
    $postsByPetType = [];
    foreach (($postsByTypeRes['data'] ?? []) as $row) {
        $key = $row['pet_type'] ?: 'Unspecified';
        $postsByPetType[$key] = ($postsByPetType[$key] ?? 0) + 1;
    }
    arsort($postsByPetType);

    $usersByRoleRes = supabaseRequest('GET', '/rest/v1/users', ['select' => 'role']);
    $usersByRole = [];
    foreach (($usersByRoleRes['data'] ?? []) as $row) {
        $key = $row['role'] ?: 'member';
        $usersByRole[$key] = ($usersByRole[$key] ?? 0) + 1;
    }

    jsonSuccess([
        'counts' => $counts,
        'posts_by_pet_type' => $postsByPetType,
        'users_by_role' => $usersByRole,
    ]);
}

// --- Sessions ---

function handleAdminListSessions($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $limit = adminListLimit($data);
    $offset = adminOffset($data);
    $query = [
        'select' => 'id,user_id,role,created_at,expires_at,revoked_at,admin_mode_until,user_agent',
        'order' => 'created_at.desc',
        'limit' => (string) $limit,
        'offset' => (string) $offset,
    ];
    $status = cleanPlainValue($data['status_filter'] ?? '', 40);
    if ($status === 'active') {
        $query['revoked_at'] = 'is.null';
        $query['expires_at'] = 'gt.' . nowIsoUtc();
    } elseif ($status === 'revoked') {
        $query['revoked_at'] = 'not.is.null';
    }

    $res = supabaseRequest('GET', '/rest/v1/user_sessions', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to load sessions.", $res);
        return;
    }

    $sessions = $res['data'] ?? [];
    $profileMap = fetchProfilesMap(normalizeUuidList(array_column($sessions, 'user_id')));
    foreach ($sessions as &$s) {
        $s['profile'] = $profileMap[$s['user_id']] ?? null;
        $s['is_active'] = empty($s['revoked_at']) && strtotime((string) ($s['expires_at'] ?? '')) > time();
    }
    unset($s);

    jsonSuccess(['sessions' => $sessions, 'limit' => $limit, 'offset' => $offset]);
}

function handleAdminRevokeSession($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $sessionId = requireUuid($data['session_id'] ?? '', 'session_id');

    $res = supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'id' => 'eq.' . $sessionId,
        'revoked_at' => 'is.null',
    ], ['revoked_at' => nowIsoUtc(), 'admin_mode_until' => null], ['Prefer: return=representation']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to revoke session.", $res);
        return;
    }

    jsonSuccess(['message' => 'Session revoked.', 'revoked_count' => count($res['data'] ?? [])]);
}

// --- app_settings generic key/value store (feature visibility, feed layout, pet type themes) ---

function getAppSetting($key, $default = [])
{
    $res = supabaseRequest('GET', '/rest/v1/app_settings', ['key' => 'eq.' . $key, 'select' => 'value', 'limit' => '1']);
    if (supabaseFailed($res) || empty($res['data'])) {
        return $default;
    }
    $value = $res['data'][0]['value'];
    return is_string($value) ? (json_decode($value, true) ?? $default) : $value;
}

function saveAppSetting($key, $value, $userId)
{
    return supabaseRequest('POST', '/rest/v1/app_settings', [], [
        'key' => $key,
        'value' => $value,
        'updated_at' => nowIsoUtc(),
        'updated_by' => $userId,
    ], ['Prefer: resolution=merge-duplicates,return=minimal']);
}

// --- Pet Types (theming; replaces eSamaj's Communities panel) ---

function handleAdminGetPetTypeThemes($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $themes = getAppSetting('pet_type_themes', (object) []);
    jsonSuccess(['pet_types' => petTypeVocabulary(), 'themes' => $themes]);
}

function handleAdminSavePetTypeTheme($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $petType = cleanPlainValue($data['pet_type'] ?? '', 40);
    if (!in_array($petType, petTypeVocabulary(), true)) {
        jsonError("Unknown pet_type.", 400);
        return;
    }
    $accentColor = cleanPlainValue($data['accent_color'] ?? '', 20);
    if ($accentColor !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $accentColor)) {
        jsonError("accent_color must be a hex color like #e04848.", 400);
        return;
    }
    $fontFamily = cleanPlainValue($data['font_family'] ?? '', 60);

    $themes = getAppSetting('pet_type_themes', (object) []);
    $themes = (array) $themes;
    $themes[$petType] = array_filter([
        'accent_color' => $accentColor ?: null,
        'font_family' => $fontFamily ?: null,
    ]);

    $res = saveAppSetting('pet_type_themes', $themes, $actorId);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to save theme.", $res);
        return;
    }

    jsonSuccess(['saved' => true, 'themes' => $themes]);
}

function handleGetAppConfig($data)
{
    // Public bootstrap config (no admin gate) — mirrors eSamaj's get_app_config.
    jsonSuccess([
        'pet_type_themes' => getAppSetting('pet_type_themes', (object) []),
        'feature_visibility' => getAppSetting('feature_visibility', (object) []),
        'feed_layout' => getAppSetting('feed_layout', (object) []),
    ]);
}

// --- Contact book (grouped by pet_type instead of eSamaj's religion/community) ---

function handleAdminContactBook($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $search = cleanPlainValue($data['search'] ?? '', 120);
    $petTypeFilter = cleanPlainValue($data['pet_type'] ?? '', 40);

    $query = ['select' => 'user_id,pet_name,parent_name,pet_type,breed,current_city,mobile_number', 'order' => 'pet_name.asc'];
    if ($petTypeFilter !== '') {
        $query['pet_type'] = 'eq.' . $petTypeFilter;
    }
    if ($search !== '') {
        $query['or'] = '(pet_name.ilike.*' . $search . '*,parent_name.ilike.*' . $search . '*)';
    }

    $res = supabaseRequest('GET', '/rest/v1/profiles', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to load contact book.", $res);
        return;
    }

    $rows = $res['data'] ?? [];
    $grouped = [];
    foreach ($rows as $row) {
        $key = $row['pet_type'] ?: 'Unspecified';
        $grouped[$key][] = $row;
    }
    ksort($grouped);

    jsonSuccess(['groups' => $grouped, 'total' => count($rows)]);
}

// --- Custom reactions (scoped by pet_type instead of eSamaj's religion/community) ---

function handleAdminListCustomReactions($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $res = supabaseRequest('GET', '/rest/v1/custom_reactions', ['select' => '*', 'order' => 'created_at.desc']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to load reactions.", $res);
        return;
    }
    jsonSuccess(['reactions' => $res['data'] ?? []]);
}

function handleAdminAddCustomReaction($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $label = cleanNullableText($data['label'] ?? '', 60);
    $imageUrl = cleanNullableText($data['image_url'] ?? '', 500);
    if (!$label || !$imageUrl) {
        jsonError("label and image_url are required.", 400);
        return;
    }
    $reactionKey = preg_replace('/[^a-z0-9]+/', '_', strtolower($label));
    $reactionKey = trim($reactionKey, '_') ?: ('reaction_' . substr(md5($label . microtime()), 0, 8));

    $body = [
        'reaction_key' => $reactionKey,
        'label' => $label,
        'image_url' => $imageUrl,
        'pet_type' => cleanNullableText($data['pet_type'] ?? '', 40),
        'breed' => cleanNullableText($data['breed'] ?? '', 140),
        'created_by' => $actorId,
    ];
    $res = supabaseRequest('POST', '/rest/v1/custom_reactions', [], $body, ['Prefer: return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to add reaction.", $res);
        return;
    }
    jsonSuccess(['reaction' => $res['data'][0]]);
}

function handleAdminSetCustomReactionActive($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $reactionId = requireUuid($data['reaction_id'] ?? '', 'reaction_id');

    $res = supabaseRequest('PATCH', '/rest/v1/custom_reactions', ['id' => 'eq.' . $reactionId], [
        'is_active' => !empty($data['is_active']),
    ], ['Prefer: return=minimal']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update reaction.", $res);
        return;
    }
    jsonSuccess(['updated' => true]);
}

function handleAdminDeleteCustomReaction($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $reactionId = requireUuid($data['reaction_id'] ?? '', 'reaction_id');

    $res = supabaseRequest('DELETE', '/rest/v1/custom_reactions', ['id' => 'eq.' . $reactionId]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete reaction.", $res);
        return;
    }
    jsonSuccess(['deleted' => true]);
}

// --- Feature/tab visibility (scope: global | role | pet_type | breed, replacing eSamaj's religion/community scopes) ---

function handleAdminGetFeatureVisibility($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    jsonSuccess(['feature_visibility' => getAppSetting('feature_visibility', (object) [])]);
}

function handleAdminSaveFeatureVisibility($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $config = $data['feature_visibility'] ?? null;
    if (!is_array($config)) {
        jsonError("feature_visibility must be an object.", 400);
        return;
    }
    $res = saveAppSetting('feature_visibility', $config, $actorId);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to save feature visibility.", $res);
        return;
    }
    jsonSuccess(['saved' => true]);
}

// --- Feed layout (fully generic — ported as-is) ---

function handleAdminGetFeedLayout($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    jsonSuccess(['feed_layout' => getAppSetting('feed_layout', ['sidebar' => 1, 'feed' => 2, 'widgets' => 3])]);
}

function handleAdminSaveFeedLayout($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $layout = $data['feed_layout'] ?? null;
    if (!is_array($layout)) {
        jsonError("feed_layout must be an object.", 400);
        return;
    }
    $res = saveAppSetting('feed_layout', $layout, $actorId);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to save layout.", $res);
        return;
    }
    jsonSuccess(['saved' => true]);
}

// --- Ads (fully generic — ported as-is) ---

function handleGetAds($data)
{
    $res = supabaseRequest('GET', '/rest/v1/ads', [
        'is_active' => 'eq.true',
        'select' => 'id,title,image_url,link_url,sort_order',
        'order' => 'sort_order.asc',
    ]);
    jsonSuccess(['ads' => supabaseFailed($res) ? [] : ($res['data'] ?? [])]);
}

function handleAdminListAds($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $res = supabaseRequest('GET', '/rest/v1/ads', ['select' => '*', 'order' => 'sort_order.asc']);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to load ads.", $res);
        return;
    }
    jsonSuccess(['ads' => $res['data'] ?? []]);
}

function handleAdminSaveAd($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);

    $title = cleanNullableText($data['title'] ?? '', 150);
    $imageUrl = cleanNullableText($data['image_url'] ?? '', 500);
    $isEdit = !empty($data['ad_id']);

    if (!$isEdit && (!$title || !$imageUrl)) {
        jsonError("title and image_url are required.", 400);
        return;
    }

    if ($isEdit) {
        // Partial update — only patch fields the caller actually sent, so a
        // toggle-active call doesn't need to resend title/image.
        $adId = requireUuid($data['ad_id'], 'ad_id');
        $body = ['updated_at' => nowIsoUtc()];
        if ($title)
            $body['title'] = $title;
        if ($imageUrl)
            $body['image_url'] = $imageUrl;
        if (isset($data['link_url']))
            $body['link_url'] = cleanNullableText($data['link_url'], 500);
        if (isset($data['is_active']))
            $body['is_active'] = !empty($data['is_active']);
        if (isset($data['sort_order']))
            $body['sort_order'] = (int) $data['sort_order'];

        $res = supabaseRequest('PATCH', '/rest/v1/ads', ['id' => 'eq.' . $adId], $body, ['Prefer: return=representation']);
    } else {
        $body = [
            'title' => $title,
            'image_url' => $imageUrl,
            'link_url' => cleanNullableText($data['link_url'] ?? '', 500),
            'is_active' => !isset($data['is_active']) || !empty($data['is_active']),
            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 100,
            'updated_at' => nowIsoUtc(),
        ];
        $res = supabaseRequest('POST', '/rest/v1/ads', [], $body, ['Prefer: return=representation']);
    }
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to save ad.", $res);
        return;
    }
    jsonSuccess(['ad' => $res['data'][0]]);
}

function handleAdminDeleteAd($data)
{
    $actorId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    requireGlobalAdminCapability($actorId);
    $adId = requireUuid($data['ad_id'] ?? '', 'ad_id');

    $res = supabaseRequest('DELETE', '/rest/v1/ads', ['id' => 'eq.' . $adId]);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete ad.", $res);
        return;
    }
    jsonSuccess(['deleted' => true]);
}
