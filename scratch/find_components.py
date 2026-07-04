import re

file_path = 'd:/pet_proj/PetCircle/index.php'
with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# Find all divs with id containing 'modal'
modals = set(re.findall(r'<div[^>]*id=\"([^\"]*modal[^\"]*)\"[^>]*>', text, re.IGNORECASE))
print('Modals found:', modals)

# Find major views
# Typically PetCircle might have an app container or dashboard containers
views = set(re.findall(r'<div[^>]*id=\"(admin-dashboard|app-container|social-feed|view-[^\"]*|page-[^\"]*)\"[^>]*>', text, re.IGNORECASE))
print('Views found:', views)

# Let's also find all script tags
scripts = set(re.findall(r'<script.*?>', text, re.IGNORECASE))
print('Script tags found:', len(scripts))

# Specifically find large script blocks
script_blocks = re.findall(r'<script[^>]*>(.*?)</script>', text, re.DOTALL | re.IGNORECASE)
for i, b in enumerate(script_blocks):
    if len(b) > 1000:
        print(f"Large script block {i} size: {len(b)}")

