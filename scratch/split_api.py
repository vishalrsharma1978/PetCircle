import os
import re

file_path = "d:/pet_proj/PetCircle/pawcircle_api.php"
out_dir = "d:/pet_proj/PetCircle/api/routes"

with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

routes_map = {
    "auth": ["auth", "login", "session", "signup", "cookie", "profile", "punishment", "rate_limit", "verify"],
    "admin": ["admin", "stats", "server"],
    "social": ["post", "comment", "like", "gallery", "friend", "group", "message", "member"],
    "rescue": ["rescue"],
    "integrations": ["zoom", "whatsapp", "playdate"],
    "notifications": ["notification", "event"]
}

def get_route(func_name):
    lower_name = func_name.lower()
    for route, keywords in routes_map.items():
        if any(kw in lower_name for kw in keywords):
            return route
    return "core"

def extract_functions(text):
    funcs = {}
    intervals_to_remove = []
    
    # Find all function declarations
    matches = list(re.finditer(r"^[ \t]*function\s+([A-Za-z0-9_]+)\s*\(", text, re.MULTILINE))
    
    for match in matches:
        start_idx = match.start()
        func_name = match.group(1)
        
        doc_start = start_idx
        before_func = text[:start_idx].rstrip()
        if before_func.endswith("*/"):
            doc_open = before_func.rfind("/**")
            if doc_open != -1:
                doc_start = doc_open
        
        brace_start = text.find('{', start_idx)
        if brace_start == -1:
            continue
            
        brace_count = 0
        end_idx = -1
        in_string = False
        string_char = ''
        escape = False
        
        for i in range(brace_start, len(text)):
            c = text[i]
            if escape:
                escape = False
                continue
            if c == '\\':
                escape = True
                continue
            if c in ["'", '"']:
                if not in_string:
                    in_string = True
                    string_char = c
                elif string_char == c:
                    in_string = False
                continue
                
            if not in_string:
                if c == '{':
                    brace_count += 1
                elif c == '}':
                    brace_count -= 1
                    if brace_count == 0:
                        end_idx = i
                        break
                        
        if end_idx != -1:
            func_body = text[doc_start:end_idx+1]
            funcs[func_name] = func_body
            intervals_to_remove.append((doc_start, end_idx+1))
            
    # Reconstruct text without functions
    remaining_text = []
    last_end = 0
    for start, end in intervals_to_remove:
        remaining_text.append(text[last_end:start])
        last_end = end
    remaining_text.append(text[last_end:])
    
    return funcs, "".join(remaining_text)

print("Extracting functions...")
extracted_funcs, leftover_text = extract_functions(content)
print(f"Extracted {len(extracted_funcs)} functions.")

route_files = {
    "auth": [], "admin": [], "social": [], "rescue": [],
    "integrations": [], "notifications": [], "core": []
}

for name, body in extracted_funcs.items():
    route = get_route(name)
    route_files[route].append(body)

for route, funcs_list in route_files.items():
    if not funcs_list:
        continue
    file_path = os.path.join(out_dir, f"{route}.php")
    with open(file_path, "w", encoding="utf-8") as f:
        f.write("<?php\n\n")
        f.write("\n\n".join(funcs_list))
    print(f"Wrote {len(funcs_list)} functions to {route}.php")

with open("d:/pet_proj/PetCircle/api/index.php", "w", encoding="utf-8") as f:
    f.write(leftover_text)

print("Done.")
