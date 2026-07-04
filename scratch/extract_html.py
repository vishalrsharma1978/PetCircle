import os
import re

file_path = 'd:/pet_proj/PetCircle/pawcircle_frontend.html'
out_file = 'd:/pet_proj/PetCircle/index.php'
components_dir = 'd:/pet_proj/PetCircle/components'

if not os.path.exists(components_dir):
    os.makedirs(components_dir)

with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# Extract head
head_match = re.search(r'<head>(.*?)</head>', text, re.DOTALL)
if head_match:
    head_content = head_match.group(1)
    with open(os.path.join(components_dir, 'head.php'), 'w', encoding='utf-8') as f:
        f.write(head_content)
    text = text.replace(head_match.group(0), '<head>\n<?php include \'components/head.php\'; ?>\n</head>')

# Extract portal overlay (naive extraction based on class="portal-overlay")
# Find the start of the div
portal_start = text.find('<div class="portal-overlay"')
if portal_start != -1:
    # Match braces to find the end
    div_count = 0
    in_div = False
    idx = portal_start
    while idx < len(text):
        if text[idx:idx+4] == '<div':
            div_count += 1
            in_div = True
        elif text[idx:idx+6] == '</div>':
            div_count -= 1
        
        idx += 1
        
        if in_div and div_count == 0:
            portal_end = idx + 5
            break
            
    if div_count == 0:
        portal_html = text[portal_start:portal_end]
        with open(os.path.join(components_dir, 'portal_transition.php'), 'w', encoding='utf-8') as f:
            f.write(portal_html)
        text = text.replace(portal_html, '<?php include \'components/portal_transition.php\'; ?>')

with open(out_file, 'w', encoding='utf-8') as f:
    f.write(text)

# We can remove the old file now
os.remove(file_path)

print("Extracted HTML components and created index.php")
