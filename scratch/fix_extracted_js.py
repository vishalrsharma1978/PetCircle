import subprocess
import re
import os

js_dir = 'd:/pet_proj/PetCircle/js'
if not os.path.exists(js_dir):
    os.makedirs(js_dir)

out = subprocess.check_output(['git', 'show', 'HEAD:pawcircle_frontend.html'], cwd='d:/pet_proj/PetCircle')
text = out.decode('utf-8')

scripts = re.findall(r'<script[^>]*>(.*?)</script>', text, re.DOTALL | re.IGNORECASE)

# Looking at the sizes from before:
# Script 8: length 1193926
# Script 9: length 126970
# Script 16: length 72293
# Script 17: length 64971

large_scripts = []
for s in scripts:
    if len(s) > 1000:
        large_scripts.append(s)

# There are 6 large scripts. Let's map them by their sizes to be exact:
size_map = {}
for s in large_scripts:
    size_map[len(s)] = s
    print(f"Stored script of size {len(s)}")

# We expect: 6726, 1542, 1193926, 126970, 72293, 64971
# Write core.js
if 1193926 in size_map:
    with open(os.path.join(js_dir, 'core.js'), 'w', encoding='utf-8') as f:
        f.write(size_map[1193926])

# Write main.js (previously it overwrote core.js)
if 126970 in size_map:
    with open(os.path.join(js_dir, 'main.js'), 'w', encoding='utf-8') as f:
        f.write(size_map[126970])

# Write admin.js
if 72293 in size_map:
    with open(os.path.join(js_dir, 'admin.js'), 'w', encoding='utf-8') as f:
        f.write(size_map[72293])

# Write script_9.js
if 64971 in size_map:
    with open(os.path.join(js_dir, 'script_9.js'), 'w', encoding='utf-8') as f:
        f.write(size_map[64971])

print("Rewrote JS files correctly.")

# Now fix index.php
index_path = 'd:/pet_proj/PetCircle/index.php'
with open(index_path, 'r', encoding='utf-8') as f:
    index_text = f.read()

# Currently it has:
# <script src="js/main.js"></script>
# <script src="js/main.js"></script>
# <script src="js/admin.js"></script>
# <script src="js/script_9.js"></script>
# We need to replace the FIRST occurrence of main.js with core.js
index_text = index_text.replace('<script src="js/main.js"></script>', '<script src="js/core.js"></script>', 1)

with open(index_path, 'w', encoding='utf-8') as f:
    f.write(index_text)

print("Fixed index.php")
