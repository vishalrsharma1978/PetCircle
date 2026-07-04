<?php

function jsonSuccess($data = [])
{
    echo json_encode(array_merge(["status" => "success", "request_id" => function_exists('requestId') ? requestId() : ''], $data));
}

function jsonError($message, $code = 400, $extra = [])
{
    http_response_code($code);
    echo json_encode(array_merge([
        "status" => "error",
        "message" => $message,
        "request_id" => function_exists('requestId') ? requestId() : '',
    ], $extra));
}

function supabaseFailed($res)
{
    return !is_array($res) || ($res['code'] ?? 500) >= 400;
}

function sendSupabaseError($message, $res, $code = 500, $extra = [])
{
    // Always log the full detail server-side, correlated by request id.
    error_log(sprintf(
        "[pawcircle][%s] %s | http=%s | response=%s",
        function_exists('requestId') ? requestId() : '',
        $message,
        $res['code'] ?? 'n/a',
        json_encode($res['data'] ?? null)
    ));

    $debug = defined('PAWCIRCLE_DEBUG') ? PAWCIRCLE_DEBUG : false;

    if ($debug) {
        jsonError($message, $code, array_merge($extra, [
            "supabase_http_code" => $res['code'] ?? null,
            "supabase_response" => $res['data'] ?? null,
        ]));
    } else {
        // Production: generic message, no internal details leaked.
        jsonError("Could not complete that request.", $code, $extra);
    }
}

// ── ID validation helpers ──
// Validate a UUID before using it in a Supabase filter. Returns the lowercased
// UUID, or emits a 400 and exits when invalid.
function requireUuid($value, $fieldName = 'id')
{
    $value = strtolower(trim((string) $value));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
        jsonError("Invalid {$fieldName}.", 400);
        exit();
    }
    return $value;
}

// Non-fatal UUID check (returns bool) for optional fields / soft validation.
function isValidUuid($value)
{
    return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', trim((string) $value));
}

function requireIntId($value, $fieldName = 'id')
{
    if (!ctype_digit((string) $value)) {
        jsonError("Invalid {$fieldName}.", 400);
        exit();
    }
    return (int) $value;
}


// ── Login / activity tracking helpers ──
// These helpers are deliberately "safe": if the recommended schema has not
// been run yet, the app still logs the failure server-side and continues.
function nowIsoUtc()
{
    return gmdate('c');
}
