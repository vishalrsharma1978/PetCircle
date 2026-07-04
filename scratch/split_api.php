<?php
$inputFile = "d:/pet_proj/PetCircle/pawcircle_api.php";
$outDir = "d:/pet_proj/PetCircle/api/routes";

$code = file_get_contents($inputFile);
$tokens = token_get_all($code);

$functions = [];
$intervalsToRemove = [];

$i = 0;
while ($i < count($tokens)) {
    if (is_array($tokens[$i]) && $tokens[$i][0] === T_FUNCTION) {
        $funcStart = $i;
        
        // Try to find preceding docblock
        $docStart = $i;
        $j = $i - 1;
        while ($j >= 0 && (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_DOC_COMMENT, T_COMMENT]))) {
            if ($tokens[$j][0] === T_DOC_COMMENT) {
                $docStart = $j;
            }
            $j--;
        }

        // Find function name
        $funcName = null;
        $j = $i + 1;
        while ($j < count($tokens)) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                $funcName = $tokens[$j][1];
                break;
            }
            $j++;
        }

        if (!$funcName) {
            $i++;
            continue;
        }

        // Find opening brace
        $j++;
        while ($j < count($tokens) && $tokens[$j] !== '{' && (!is_array($tokens[$j]) || $tokens[$j][0] !== T_CURLY_OPEN)) {
            $j++;
        }

        if ($j >= count($tokens)) {
            $i++;
            continue;
        }

        // Match braces
        $braceCount = 1;
        $j++;
        while ($j < count($tokens) && $braceCount > 0) {
            $t = $tokens[$j];
            if ($t === '{' || (is_array($t) && $t[0] === T_CURLY_OPEN)) {
                $braceCount++;
            } elseif ($t === '}') {
                $braceCount--;
            }
            $j++;
        }

        $funcEnd = $j - 1;

        // Get string for this function
        $funcCode = "";
        for ($k = $docStart; $k <= $funcEnd; $k++) {
            $funcCode .= is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
        }

        $functions[$funcName] = $funcCode;
        
        // Find line numbers for substr later (token_get_all gives line numbers but string positions are tricky)
        // Actually, just store the tokens and reconstruct the file by omitting these tokens.
        $intervalsToRemove[] = [$docStart, $funcEnd];

        $i = $funcEnd;
    }
    $i++;
}

// Reconstruct leftover code
$leftover = "";
$removeIdx = 0;
for ($i = 0; $i < count($tokens); $i++) {
    $skip = false;
    foreach ($intervalsToRemove as $interval) {
        if ($i >= $interval[0] && $i <= $interval[1]) {
            $skip = true;
            break;
        }
    }
    if (!$skip) {
        $leftover .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
    }
}

// Map functions to routes
$routesMap = [
    "auth" => ["auth", "login", "session", "signup", "cookie", "profile", "punishment", "rate_limit", "verify"],
    "admin" => ["admin", "stats", "server"],
    "social" => ["post", "comment", "like", "gallery", "friend", "group", "message", "member"],
    "rescue" => ["rescue"],
    "integrations" => ["zoom", "whatsapp", "playdate"],
    "notifications" => ["notification", "event"]
];

$routeFiles = [
    "auth" => [], "admin" => [], "social" => [], "rescue" => [],
    "integrations" => [], "notifications" => [], "core" => []
];

foreach ($functions as $name => $body) {
    $lowerName = strtolower($name);
    $route = "core";
    foreach ($routesMap as $r => $keywords) {
        foreach ($keywords as $kw) {
            if (strpos($lowerName, $kw) !== false) {
                $route = $r;
                break 2;
            }
        }
    }
    $routeFiles[$route][] = $body;
}

if (!is_dir($outDir)) {
    mkdir($outDir, 0777, true);
}

foreach ($routeFiles as $route => $funcs) {
    if (empty($funcs)) continue;
    $content = "<?php\n\n" . implode("\n\n", $funcs);
    file_put_contents("$outDir/$route.php", $content);
    echo "Wrote " . count($funcs) . " functions to $route.php\n";
}

file_put_contents("d:/pet_proj/PetCircle/api/index.php", $leftover);
echo "Done.\n";
