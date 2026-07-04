import re

with open('d:/pet_proj/PetCircle/index.php', 'r', encoding='utf-8') as f:
    text = f.read()

scripts = re.findall(r'<script src="js/(.*?)"></script>', text)
print(scripts)
