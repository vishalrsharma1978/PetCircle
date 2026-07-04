import sys

file_path = "d:/pet_proj/PetCircle/pawcircle_api.php"
with open(file_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

# Ranges to remove (1-indexed inclusive)
ranges_to_remove = [
    (159, 209), # supabaseRequest and docblock
    (746, 749), # jsonSuccess
    (751, 759), # jsonError
    (1762, 1773), # requireUuid and docblock
    (1775, 1779), # isValidUuid and docblock
    (1781, 1788), # requireIntId
    (1794, 1797), # nowIsoUtc
    (5315, 5318), # supabaseFailed
    (5320, 5340), # sendSupabaseError
]

# Sort in reverse order to not mess up indices during removal
ranges_to_remove.sort(reverse=True)

for start, end in ranges_to_remove:
    # start is 1-indexed, so index is start - 1
    # end is inclusive, so we remove from start-1 to end
    del lines[start-1:end]

# Inject require_once at the old location of supabaseRequest (which is now index 158)
injection = """
require_once __DIR__ . '/api/utils/response_helpers.php';
require_once __DIR__ . '/api/utils/supabase_client.php';
"""
lines.insert(158, injection)

with open(file_path, "w", encoding="utf-8") as f:
    f.writelines(lines)

print("Successfully replaced functions in pawcircle_api.php")
