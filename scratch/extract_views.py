import os
import re

file_path = 'd:/pet_proj/PetCircle/index.php'
components_dir = 'd:/pet_proj/PetCircle/components'
views_dir = 'd:/pet_proj/PetCircle/views'
modals_dir = 'd:/pet_proj/PetCircle/modals'

for d in [components_dir, views_dir, modals_dir]:
    if not os.path.exists(d):
        os.makedirs(d)

with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# We want to extract specific blocks. We can find `<div id="something-modal"` or `<div id="view-something"`
def extract_div_by_id_pattern(pattern, directory):
    global text
    # pattern is a regex for the id, like r'view-[a-zA-Z0-9_-]+'
    # we need to find all <div ... id="PATTERN" ...>
    
    search_idx = 0
    extracted_count = 0
    while True:
        # Find next div that matches
        match = re.search(r'<div[^>]*id=\"(' + pattern + r')\"[^>]*>', text[search_idx:], re.IGNORECASE)
        if not match:
            break
            
        div_start = search_idx + match.start()
        div_id = match.group(1)
        
        # Now count divs
        div_count = 0
        in_div = False
        idx = div_start
        end_idx = -1
        
        # simple tag counter (doesn't handle <div /> or divs in strings, but good enough for this)
        while idx < len(text):
            if text[idx:idx+4].lower() == '<div':
                # ensure it's a tag start
                # simple check if there's a space or > after
                if text[idx+4] in [' ', '>', '\n', '\t']:
                    div_count += 1
                    in_div = True
            elif text[idx:idx+6].lower() == '</div>':
                div_count -= 1
                if in_div and div_count == 0:
                    end_idx = idx + 6
                    break
            
            idx += 1
            
        if end_idx != -1:
            html = text[div_start:end_idx]
            filename = div_id + '.php'
            
            # Write to file
            with open(os.path.join(directory, filename), 'w', encoding='utf-8') as out_f:
                out_f.write(html)
                
            # Replace in text
            include_stmt = f"<?php include '{os.path.basename(directory)}/{filename}'; ?>"
            text = text[:div_start] + include_stmt + text[end_idx:]
            extracted_count += 1
            # Adjust search_idx
            search_idx = div_start + len(include_stmt)
        else:
            # Malformed HTML or something
            search_idx = div_start + 4
            
    return extracted_count

print("Extracting modals...")
num_modals = extract_div_by_id_pattern(r'[a-zA-Z0-9_-]*modal[a-zA-Z0-9_-]*', modals_dir)
print(f"Extracted {num_modals} modals.")

print("Extracting views...")
num_views = extract_div_by_id_pattern(r'view-[a-zA-Z0-9_-]+', views_dir)
print(f"Extracted {num_views} views.")

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(text)
print("Updated index.php")
