import os
import re

file_path = 'd:/pet_proj/PetCircle/api/routes/social.php'

with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# Replace $var['key'] = $var['key'];
# We can just remove these lines entirely.
# For example: "        $post['breed'] = $post['breed'];"
text = re.sub(r'^\s*\$[a-zA-Z0-9_]+\[[\'"][a-zA-Z0-9_]+[\'"]\]\s*=\s*\$[a-zA-Z0-9_]+\[[\'"][a-zA-Z0-9_]+[\'"]\];\s*$', '', text, flags=re.MULTILINE)

# This will leave empty if statements if they were like:
# if (isset($post['breed'])) {
#     
# }
# Let's remove empty if blocks that look like this
text = re.sub(r'^\s*if\s*\(\s*isset\(\$[a-zA-Z0-9_]+\[[\'"][a-zA-Z0-9_]+[\'"]\]\)\s*\)\s*\{\s*\}\s*$', '', text, flags=re.MULTILINE)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(text)

print("Removed self-assignments in social.php")
