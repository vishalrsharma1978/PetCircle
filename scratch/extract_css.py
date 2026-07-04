import os
import re

file_path = 'd:/pet_proj/PetCircle/pawcircle_frontend.html'
out_dir = 'd:/pet_proj/PetCircle/css'

if not os.path.exists(out_dir):
    os.makedirs(out_dir)

with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# Find all style tags and their full string
style_matches = list(re.finditer(r'(<style.*?>)(.*?)(</style>)', text, re.DOTALL))

for i, match in enumerate(style_matches):
    full_tag = match.group(0)
    content = match.group(2)
    
    name = f'style_{i}.css'
    if 'portal-overlay' in content:
        name = 'portal.css'
    elif 'DYNAMIC ADMIN RELIGION THEME OVERRIDES' in content or '.admin-mode' in content:
        name = 'admin-theme.css'
    elif len(content) > 50000:
        name = 'main.css'
        
    css_path = os.path.join(out_dir, name)
    with open(css_path, 'w', encoding='utf-8') as f:
        f.write(content)
        
    # Replace the style tag with a link tag
    link_tag = f'<link rel="stylesheet" href="css/{name}">'
    text = text.replace(full_tag, link_tag)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(text)

print(f"Extracted {len(style_matches)} CSS files.")
