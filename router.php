<?php
// router.php
// Used by the PHP built-in web server to serve static files with security headers
// and to route everything else to public/index.php or public/api/index.php.
// Mirrors community_proj/router.php's structure. Zoom Meeting SDK calling is
// wired up (api/routes/zoom.php, public/js/zoom.js) — CSP allowances for
// Zoom live in api/utils/security_headers.php.

$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if (empty($path)) {
    $path = '/';
}
$ext = pathinfo($path, PATHINFO_EXTENSION);
$static_exts = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'webp', 'woff', 'woff2', 'ttf', 'txt', 'xml'];

if (in_array(strtolower($ext), $static_exts)) {
    $file = __DIR__ . '/public' . $path;
    if (file_exists($file) && is_file($file)) {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("Permissions-Policy: camera=(self), microphone=(self), geolocation=(self), payment=(), usb=()");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src * data: blob:; media-src 'self' blob: https://*.supabase.co https://*.amazonaws.com; connect-src 'self' https://*.supabase.co wss://*.supabase.co https://unpkg.com https://cdn.jsdelivr.net https://*.amazonaws.com; frame-src 'self'; worker-src 'self' blob:; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none';");
        header_remove('X-Powered-By');
        header('Cache-Control: public,max-age=31536000,immutable');

        $mimeTypes = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf'
        ];

        if (isset($mimeTypes[strtolower($ext)])) {
            header("Content-Type: " . $mimeTypes[strtolower($ext)]);
        }

        readfile($file);
        return true;
    }

    http_response_code(404);
    echo "404 Not Found";
    return true;
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/public/index.php';
    return true;
}

if (strpos($path, '/api/') === 0 || $path === '/api') {
    require __DIR__ . '/public/api/index.php';
    return true;
}

http_response_code(404);
echo "404 Not Found";
return true;
