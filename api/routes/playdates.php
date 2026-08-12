<?php
/**
 * Playdates/matchmaking: the pet-native replacement for eSamaj's
 * matrimonial swipe-deck engine. Reuses the deck/swipe/match *mechanism*
 * (per the rebuild plan) but the scoring is entirely new — pet_type, size,
 * energy level, friendliness, shared activities, and same-city proximity,
 * not astrology.
 *
 * Ground-truth correction versus the original plan: the plan expected
 * playdate_profiles/playdate_preferences to still carry gotra/rashi/
 * nakshatra/mangalik astrology fields needing replacement (based on old
 * PetCircle's application code, which does reference those). The *live
 * database schema* was already fully migrated to pet-appropriate columns
 * (energy_level, size, friendliness_to_dogs/cats, favorite_activities,
 * vaccination_status, etc.) with zero astrology fields — the app code
 * just never caught up. This file is written directly against that
 * already-correct schema.
 *
 * No swipe/match table existed at all (old PetCircle's matching was
 * apparently never wired to persistent swipes) — added playdate_swipes
 * via migration create_playdate_swipes. A "match" is computed live: it's
 * whichever swipe completes a mutual pair of 'like' rows, not a
 * separately stored/cached state.
 */

const PLAYDATE_ENERGY_LEVELS = ['Low', 'Medium', 'High', 'Any'];
const PLAYDATE_SIZES = ['Small', 'Medium', 'Large', 'Any'];

function getPlaydateProfileRow($userId)
{
    $res = supabaseRequest('GET', '/rest/v1/playdate_profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id,weight_kg,energy_level,size,vaccination_status,friendliness_to_dogs,friendliness_to_cats,favorite_activities,insurance_provider,dietary_restrictions,is_active',
        'limit' => '1',
    ]);
    return (!supabaseFailed($res) && !empty($res['data'])) ? $res['data'][0] : null;
}

function getPlaydatePreferencesRow($userId)
{
    $res = supabaseRequest('GET', '/rest/v1/playdate_preferences', [
        'user_id' => 'eq.' . $userId,
        'select' => 'user_id,pref_gender,pref_age_min_months,pref_age_max_months,pref_size,pref_energy_level,pref_breed,pref_pet_type',
        'limit' => '1',
    ]);
    return (!supabaseFailed($res) && !empty($res['data'])) ? $res['data'][0] : [
        'pref_gender' => 'Any', 'pref_age_min_months' => 0, 'pref_age_max_months' => 240,
        'pref_size' => 'Any', 'pref_energy_level' => 'Any', 'pref_breed' => 'Any', 'pref_pet_type' => 'Any',
    ];
}

function handleGetPlaydateProfile($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    jsonSuccess([
        'playdate_profile' => getPlaydateProfileRow($userId),
        'preferences' => getPlaydatePreferencesRow($userId),
    ]);
}

function handleSavePlaydateProfile($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $energyLevel = $data['energy_level'] ?? '';
    if ($energyLevel !== '' && !in_array($energyLevel, PLAYDATE_ENERGY_LEVELS, true))
        $energyLevel = null;
    $size = $data['size'] ?? '';
    if ($size !== '' && !in_array($size, PLAYDATE_SIZES, true))
        $size = null;

    $row = [
        'user_id' => $userId,
        'weight_kg' => isset($data['weight_kg']) && $data['weight_kg'] !== '' ? (int) $data['weight_kg'] : null,
        'energy_level' => $energyLevel ?: null,
        'size' => $size ?: null,
        'vaccination_status' => cleanNullableText($data['vaccination_status'] ?? null, 60),
        'friendliness_to_dogs' => cleanNullableText($data['friendliness_to_dogs'] ?? null, 30),
        'friendliness_to_cats' => cleanNullableText($data['friendliness_to_cats'] ?? null, 30),
        'favorite_activities' => cleanNullableText($data['favorite_activities'] ?? null, 300),
        'insurance_provider' => cleanNullableText($data['insurance_provider'] ?? null, 120),
        'dietary_restrictions' => cleanNullableText($data['dietary_restrictions'] ?? null, 300),
        'is_active' => !empty($data['is_active']),
        'updated_at' => nowIsoUtc(),
    ];

    $res = supabaseRequest('POST', '/rest/v1/playdate_profiles', ['on_conflict' => 'user_id'], $row, ['Prefer: resolution=merge-duplicates,return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError('Failed to save playdate profile.', $res);
        return;
    }

    jsonSuccess(['playdate_profile' => $res['data'][0]]);
}

function handleSavePlaydatePreferences($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $prefSize = $data['pref_size'] ?? 'Any';
    $prefSize = in_array($prefSize, PLAYDATE_SIZES, true) ? $prefSize : 'Any';
    $prefEnergy = $data['pref_energy_level'] ?? 'Any';
    $prefEnergy = in_array($prefEnergy, PLAYDATE_ENERGY_LEVELS, true) ? $prefEnergy : 'Any';
    $prefGender = $data['pref_gender'] ?? 'Any';
    $prefGender = in_array($prefGender, ['Male', 'Female', 'Any'], true) ? $prefGender : 'Any';

    $row = [
        'user_id' => $userId,
        'pref_gender' => $prefGender,
        'pref_age_min_months' => max(0, (int) ($data['pref_age_min_months'] ?? 0)),
        'pref_age_max_months' => min(600, (int) ($data['pref_age_max_months'] ?? 240)),
        'pref_size' => $prefSize,
        'pref_energy_level' => $prefEnergy,
        'pref_breed' => cleanNullableText($data['pref_breed'] ?? 'Any', 140) ?: 'Any',
        'pref_pet_type' => cleanNullableText($data['pref_pet_type'] ?? 'Any', 80) ?: 'Any',
        'updated_at' => nowIsoUtc(),
    ];

    $res = supabaseRequest('POST', '/rest/v1/playdate_preferences', ['on_conflict' => 'user_id'], $row, ['Prefer: resolution=merge-duplicates,return=representation']);
    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError('Failed to save preferences.', $res);
        return;
    }

    jsonSuccess(['preferences' => $res['data'][0]]);
}

function ageInMonthsFromDob($dob)
{
    if (!$dob)
        return null;
    $birth = strtotime($dob);
    if (!$birth)
        return null;
    $diffDays = (time() - $birth) / 86400;
    return (int) floor($diffDays / 30.44);
}

/**
 * Soft-scored preference matching (breed/size/energy/gender/activities/
 * city) — these boost ranking but never exclude a candidate from the deck.
 * pet_type and age range are hard filters, applied by the caller before
 * this is used purely for sorting.
 */
function playdateCompatibilityScore($myProfile, $myPlaydate, $myPrefs, $candidateProfile, $candidatePlaydate)
{
    $score = 0;

    if (($myPrefs['pref_breed'] ?? 'Any') === 'Any' || strcasecmp($myPrefs['pref_breed'], $candidateProfile['breed'] ?? '') === 0) {
        $score += 10;
    }

    $candSize = $candidatePlaydate['size'] ?? null;
    if (($myPrefs['pref_size'] ?? 'Any') === 'Any' || $myPrefs['pref_size'] === $candSize) {
        $score += 15;
    }
    if ($myPlaydate && $myPlaydate['size'] && $candSize && $myPlaydate['size'] === $candSize) {
        $score += 10;
    }

    $candEnergy = $candidatePlaydate['energy_level'] ?? null;
    if (($myPrefs['pref_energy_level'] ?? 'Any') === 'Any' || $myPrefs['pref_energy_level'] === $candEnergy) {
        $score += 15;
    }
    if ($myPlaydate && $myPlaydate['energy_level'] && $candEnergy && $myPlaydate['energy_level'] === $candEnergy) {
        $score += 10;
    }

    $myPetType = $myProfile['pet_type'] ?? '';
    if (strcasecmp($myPetType, 'Dog') === 0 && strtolower((string) ($candidatePlaydate['friendliness_to_dogs'] ?? '')) === 'yes') {
        $score += 10;
    }
    if (strcasecmp($myPetType, 'Cat') === 0 && strtolower((string) ($candidatePlaydate['friendliness_to_cats'] ?? '')) === 'yes') {
        $score += 10;
    }

    $mine = array_filter(array_map('trim', explode(',', strtolower((string) ($myPlaydate['favorite_activities'] ?? '')))));
    $theirs = array_filter(array_map('trim', explode(',', strtolower((string) ($candidatePlaydate['favorite_activities'] ?? '')))));
    $score += min(count(array_intersect($mine, $theirs)) * 5, 20);

    $myCity = strtolower(trim((string) ($myProfile['current_city'] ?? '')));
    $candCity = strtolower(trim((string) ($candidateProfile['current_city'] ?? '')));
    if ($myCity !== '' && $myCity === $candCity) {
        $score += 15;
    }

    if (($myPrefs['pref_gender'] ?? 'Any') === 'Any' || $myPrefs['pref_gender'] === ($candidateProfile['gender'] ?? null)) {
        $score += 5;
    }

    return $score; // max ~100
}

function handleGetPlaydateDeck($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $limit = isset($data['limit']) ? max(1, min((int) $data['limit'], 30)) : 15;

    $myProfile = getAccountProfile($userId);
    $myPlaydate = getPlaydateProfileRow($userId);
    $myPrefs = getPlaydatePreferencesRow($userId);

    // Who has this user already swiped on? Exclude from the deck.
    $swipedRes = supabaseRequest('GET', '/rest/v1/playdate_swipes', ['swiper_user_id' => 'eq.' . $userId, 'select' => 'target_user_id']);
    $excluded = array_column($swipedRes['data'] ?? [], 'target_user_id');
    $excluded[] = $userId;

    // Candidate pool: active playdate profiles, pet_type hard-filtered by preference.
    $params = [
        'is_active' => 'eq.true',
        'select' => 'user_id,weight_kg,energy_level,size,vaccination_status,friendliness_to_dogs,friendliness_to_cats,favorite_activities',
        'limit' => '200',
    ];
    $res = supabaseRequest('GET', '/rest/v1/playdate_profiles', $params);
    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to load the playdate deck.', $res);
        return;
    }

    $candidates = array_filter($res['data'] ?? [], fn($c) => !in_array($c['user_id'], $excluded, true));
    if (empty($candidates)) {
        jsonSuccess(['deck' => []]);
        return;
    }

    $candidateIds = array_column($candidates, 'user_id');
    $profileMap = fetchProfilesMapExtended($candidateIds);

    $prefPetType = $myPrefs['pref_pet_type'] ?? 'Any';
    $ageMin = (int) ($myPrefs['pref_age_min_months'] ?? 0);
    $ageMax = (int) ($myPrefs['pref_age_max_months'] ?? 600);

    $scored = [];
    foreach ($candidates as $c) {
        $cp = $profileMap[$c['user_id']] ?? null;
        if (!$cp)
            continue;

        // Hard filters: pet type preference and age range.
        if ($prefPetType !== 'Any' && strcasecmp($prefPetType, $cp['pet_type'] ?? '') !== 0)
            continue;
        $ageMonths = ageInMonthsFromDob($cp['date_of_birth'] ?? null);
        if ($ageMonths !== null && ($ageMonths < $ageMin || $ageMonths > $ageMax))
            continue;

        $score = playdateCompatibilityScore($myProfile, $myPlaydate, $myPrefs, $cp, $c);
        $scored[] = [
            'user_id' => $c['user_id'],
            'pet_name' => $cp['pet_name'] ?? 'Pet',
            'pet_type' => $cp['pet_type'] ?? null,
            'breed' => $cp['breed'] ?? null,
            'current_city' => $cp['current_city'] ?? null,
            'profile_photo_url' => $cp['profile_photo_url'] ?? null,
            'bio' => $cp['bio'] ?? null,
            'energy_level' => $c['energy_level'] ?? null,
            'size' => $c['size'] ?? null,
            'favorite_activities' => $c['favorite_activities'] ?? null,
            'compatibility_score' => min(100, $score),
        ];
    }

    usort($scored, fn($a, $b) => $b['compatibility_score'] <=> $a['compatibility_score']);
    jsonSuccess(['deck' => array_slice($scored, 0, $limit)]);
}

// Bulk profile fetch including fields fetchProfilesMap() (shared, generic
// version in core.php) doesn't select — playdates need date_of_birth/gender
// too, which would be wasteful to add to every other caller of that helper.
function fetchProfilesMapExtended($userIds)
{
    $userIds = normalizeUuidList($userIds);
    if (empty($userIds))
        return [];
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'in.(' . implode(',', $userIds) . ')',
        'select' => 'user_id,pet_name,pet_type,breed,current_city,profile_photo_url,bio,date_of_birth,gender',
    ]);
    if (supabaseFailed($res))
        return [];
    $map = [];
    foreach (($res['data'] ?? []) as $p) {
        if (!empty($p['user_id']))
            $map[$p['user_id']] = $p;
    }
    return $map;
}

function handleSwipePlaydate($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $targetId = requireUuid($data['target_user_id'] ?? '', 'target_user_id');
    $direction = in_array($data['direction'] ?? '', ['like', 'pass'], true) ? $data['direction'] : null;
    if (!$direction) {
        jsonError('direction must be like or pass.', 400);
        return;
    }
    if ($userId === $targetId) {
        jsonError('Invalid target.', 400);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/playdate_swipes', ['on_conflict' => 'swiper_user_id,target_user_id'], [
        'swiper_user_id' => $userId,
        'target_user_id' => $targetId,
        'direction' => $direction,
    ], ['Prefer: resolution=merge-duplicates,return=minimal']);

    if (supabaseFailed($res)) {
        sendSupabaseError('Failed to record swipe.', $res);
        return;
    }

    $isMatch = false;
    if ($direction === 'like') {
        $reverse = supabaseRequest('GET', '/rest/v1/playdate_swipes', [
            'swiper_user_id' => 'eq.' . $targetId,
            'target_user_id' => 'eq.' . $userId,
            'direction' => 'eq.like',
            'select' => 'id',
            'limit' => '1',
        ]);
        if (!supabaseFailed($reverse) && !empty($reverse['data'])) {
            $isMatch = true;
            $myProfile = getAccountProfile($userId);
            $theirProfile = getAccountProfile($targetId);
            $myName = $myProfile['pet_name'] ?? 'Your pet';
            $theirName = $theirProfile['pet_name'] ?? 'A pet';
            createNotification($userId, 'playdate_match', "It's a match!", "$theirName wants a playdate with $myName too.", ['matched_user_id' => $targetId]);
            createNotification($targetId, 'playdate_match', "It's a match!", "$myName wants a playdate with $theirName too.", ['matched_user_id' => $userId]);
        }
    }

    jsonSuccess(['is_match' => $isMatch]);
}

function handleGetPlaydateMatches($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $mine = supabaseRequest('GET', '/rest/v1/playdate_swipes', ['swiper_user_id' => 'eq.' . $userId, 'direction' => 'eq.like', 'select' => 'target_user_id']);
    if (supabaseFailed($mine)) {
        sendSupabaseError('Failed to load matches.', $mine);
        return;
    }
    $likedIds = array_column($mine['data'] ?? [], 'target_user_id');
    if (empty($likedIds)) {
        jsonSuccess(['matches' => []]);
        return;
    }

    $mutual = supabaseRequest('GET', '/rest/v1/playdate_swipes', [
        'swiper_user_id' => 'in.(' . implode(',', $likedIds) . ')',
        'target_user_id' => 'eq.' . $userId,
        'direction' => 'eq.like',
        'select' => 'swiper_user_id',
    ]);
    $matchedIds = array_column($mutual['data'] ?? [], 'swiper_user_id');
    if (empty($matchedIds)) {
        jsonSuccess(['matches' => []]);
        return;
    }

    $profileMap = fetchProfilesMap($matchedIds);
    $matches = array_map(fn($id) => [
        'user_id' => $id,
        'pet_name' => $profileMap[$id]['pet_name'] ?? 'Member',
        'pet_type' => $profileMap[$id]['pet_type'] ?? null,
        'breed' => $profileMap[$id]['breed'] ?? null,
        'profile_photo_url' => $profileMap[$id]['profile_photo_url'] ?? null,
    ], $matchedIds);

    jsonSuccess(['matches' => $matches]);
}
