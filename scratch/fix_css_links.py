import re

file_path = 'd:/pet_proj/PetCircle/components/head.php'
with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

# Remove ALL `<link rel="stylesheet" href="css/...`
text = re.sub(r'<link rel="stylesheet" href="css/[a-zA-Z0-9_-]+\.css">\n?', '', text)

# Now, right before `</head>` (which actually is not in head.php because head.php is the *contents* of head)
# Wait, head.php doesn't have `</head>`.
# I'll just append the correct CSS links to the very end of head.php!

css_links = """
  <link rel="stylesheet" href="css/style_0.css">
  <link rel="stylesheet" href="css/main.css">
  <link rel="stylesheet" href="css/portal.css">
  <link rel="stylesheet" href="css/style_3.css">
  <link rel="stylesheet" href="css/admin-theme.css">
"""

text += css_links

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(text)

print("Fixed CSS links in head.php")
