<?php
/**
 * Centralized Security Headers
 * Applied to all dynamic responses (frontend and API)
 */
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("X-Frame-Options: SAMEORIGIN");
header("Permissions-Policy: camera=(self), microphone=(self), geolocation=(self), payment=(), usb=()");
// script-src/frame-src include https://source.zoom.us and connect-src
// includes https://*.zoom.us + wss://*.zoom.us for the Zoom Meeting SDK for
// Web (vendored bundle still loads its WASM/AV libs from Zoom's own CDN at
// runtime, matching Zoom's own documented requirement).
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://unpkg.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://esm.sh https://source.zoom.us; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src * data: blob:; media-src 'self' blob: https://*.supabase.co https://*.amazonaws.com; connect-src 'self' https://*.supabase.co wss://*.supabase.co https://unpkg.com https://cdn.jsdelivr.net https://*.amazonaws.com https://esm.sh https://*.zoom.us wss://*.zoom.us; frame-src 'self' https://source.zoom.us; worker-src 'self' blob:; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none';");
header_remove('X-Powered-By');

// Prevent browser from caching dynamic PHP responses (like main.php)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
}
