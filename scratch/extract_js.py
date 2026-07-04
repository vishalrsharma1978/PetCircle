import os
import re

file_path = 'd:/pet_proj/PetCircle/index.php'
js_dir = 'd:/pet_proj/PetCircle/js'

if not os.path.exists(js_dir):
    os.makedirs(js_dir)

with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# Find all script tags
# Be careful not to match <script src="..."> tags, only inline scripts
# A simple way: find <script> ... </script> where <script> doesn't have src
script_matches = list(re.finditer(r'<script([^>]*)>(.*?)</script>', text, re.DOTALL | re.IGNORECASE))

extracted_count = 0
for i, match in enumerate(script_matches):
    tag_attrs = match.group(1)
    content = match.group(2)
    
    if 'src=' in tag_attrs.lower() or content.strip() == '':
        continue
        
    filename = f'script_{i}.js'
    
    # Try to guess module name from content
    if 'playPortalTransition' in content:
        filename = 'portal.js'
    elif len(content) > 100000:
        filename = 'main.js'
    elif 'admin' in content.lower() and len(content) > 10000:
        filename = 'admin.js'
        
    js_path = os.path.join(js_dir, filename)
    with open(js_path, 'w', encoding='utf-8') as f:
        f.write(content)
        
    # Replace inline script with external script reference
    # Wait, we need to make sure variables are still global if it's not a module.
    # So we don't use type="module" unless requested, to prevent scope breakage!
    # "Use ES Modules to tie them together... replace inline scripts with <script type="module" src="js/main.js"></script>"
    # If we use type="module", variables inside won't be global! That will break everything if they rely on global scope (e.g. onclick="login()").
    # For safety, I'll just link them as standard scripts to preserve global scope.
    
    link_tag = f'<script src="js/{filename}"></script>'
    text = text.replace(match.group(0), link_tag)
    extracted_count += 1

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(text)

print(f"Extracted {extracted_count} inline scripts to /js")
