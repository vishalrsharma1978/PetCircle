import re

# Fix frontend
file_path = 'd:/pet_proj/PetCircle/pawcircle_frontend.html'
with open(file_path, 'r', encoding='utf-8') as f:
    text = f.read()

text = text.replace('"pawcircle_api.php', '"api/index.php')
text = text.replace("'pawcircle_api.php", "'api/index.php")

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(text)

# Fix auth
auth_path = 'd:/pet_proj/PetCircle/api/routes/auth.php'
with open(auth_path, 'r', encoding='utf-8') as f:
    auth_text = f.read()

# Instead of complex regex, just replace the block manually
old_block = """        $passwordCorrect = false;
        if (!empty($user['password_hash'])) {
            $passwordCorrect = password_verify($password, $user['password_hash']);
        } else {
            $authRes = supabaseRequest('POST', '/auth/v1/token?grant_type=password', [], [
                'email' => $user['email'],
                'password' => $password,
            ]);
            $passwordCorrect = (($authRes['code'] ?? 500) === 200);
        }

        if (!$passwordCorrect) {"""

new_block = """        if (empty($user['password_hash']) || !password_verify($password, (string) $user['password_hash'])) {"""

auth_text = auth_text.replace(old_block, new_block)

with open(auth_path, 'w', encoding='utf-8') as f:
    f.write(auth_text)

print("Fixed frontend and auth.")
