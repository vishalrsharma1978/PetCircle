<?php
/**
 * Pack Tree: a pet's lineage/pack network (parents, siblings, littermates,
 * offspring, mates) — the pet-native rebuild of eSamaj's Family Tree.
 * Written directly against pet_pack_members' actual schema (pet_name,
 * relation, date_of_birth, gender, pet_type, breed, microchip_number) —
 * eSamaj's family_members table carries education/work/horoscope fields
 * that don't exist here and wouldn't make sense for pets anyway.
 *
 * The live pet_pack_members_relation_check constraint only allows exactly
 * these three values — found by querying pg_constraint after the DB
 * rejected a richer relation vocabulary (Sibling/Littermate/Offspring/Mate)
 * that seemed reasonable but isn't what's actually enforced.
 */

const PACK_RELATIONS = ['Sibling Pet', 'Parent', 'Other'];

function handleGetPackMembers($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $res = supabaseRequest('GET', '/rest/v1/pet_pack_members', [
        'owner_user_id' => 'eq.' . $userId,
        'select' => 'id,owner_user_id,linked_user_id,pet_name,relation,date_of_birth,gender,pet_type,breed,microchip_number,sort_order,created_at',
        'order' => 'sort_order.asc,created_at.asc',
    ]);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch pack members.", $res);
        return;
    }

    $members = $res['data'] ?? [];
    $linkedIds = normalizeUuidList(array_column($members, 'linked_user_id'));
    $profileMap = fetchProfilesMap($linkedIds);
    foreach ($members as &$m) {
        $linked = $m['linked_user_id'] ? ($profileMap[$m['linked_user_id']] ?? null) : null;
        $m['linked_profile_photo_url'] = $linked['profile_photo_url'] ?? null;
    }
    unset($m);

    jsonSuccess(["pack_members" => $members]);
}

function handleSavePackMember($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $petName = cleanNullableText($data['pet_name'] ?? '', 180);
    if (!$petName) {
        jsonError("Pack member name is required.", 400);
        return;
    }

    $relation = cleanNullableText($data['relation'] ?? 'Other', 40) ?: 'Other';
    if (!in_array($relation, PACK_RELATIONS, true))
        $relation = 'Other';

    $linkedUserId = cleanNullableText($data['linked_user_id'] ?? null, 80);
    if ($linkedUserId && !isValidUuid($linkedUserId))
        $linkedUserId = null;

    $row = [
        'owner_user_id' => $userId,
        'linked_user_id' => $linkedUserId,
        'pet_name' => $petName,
        'relation' => $relation,
        'date_of_birth' => cleanDateValue($data['date_of_birth'] ?? null),
        'gender' => cleanNullableText($data['gender'] ?? null, 40),
        'pet_type' => cleanNullableText($data['pet_type'] ?? null, 80),
        'breed' => cleanNullableText($data['breed'] ?? null, 140),
        'microchip_number' => cleanNullableText($data['microchip_number'] ?? null, 60),
        'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 100,
    ];

    if (!empty($data['id']) && isValidUuid($data['id'])) {
        $res = supabaseRequest('PATCH', '/rest/v1/pet_pack_members', [
            'id' => 'eq.' . $data['id'],
            'owner_user_id' => 'eq.' . $userId,
        ], $row, ['Prefer: return=representation']);
    } else {
        $res = supabaseRequest('POST', '/rest/v1/pet_pack_members', [], $row, ['Prefer: return=representation']);
    }

    if (supabaseFailed($res) || empty($res['data'])) {
        sendSupabaseError("Failed to save pack member.", $res);
        return;
    }

    jsonSuccess(["pack_member" => $res['data'][0]]);
}

function handleDeletePackMember($data)
{
    if (!requireFields($data, ['user_id', 'id']))
        return;

    $res = supabaseRequest('DELETE', '/rest/v1/pet_pack_members', [
        'id' => 'eq.' . $data['id'],
        'owner_user_id' => 'eq.' . $data['user_id'],
    ], null, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to delete pack member.", $res);
        return;
    }
    if (empty($res['data'])) {
        jsonError("Pack member not found.", 404);
        return;
    }

    jsonSuccess(["message" => "Pack member removed."]);
}
