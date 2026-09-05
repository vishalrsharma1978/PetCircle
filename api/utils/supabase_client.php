<?php

function envValue($key, $default = '')
{
    return getenv($key) ?: ($_ENV[$key] ?? $default);
}

function supabaseRequest($method, $path, $query = [], $body = null, $extraHeaders = [])
{
    $url = rtrim(envValue('SUPABASE_URL'), '/');
    $secretKey = envValue('SUPABASE_SECRET_KEY');

    if (empty($url) || empty($secretKey)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Supabase API keys are missing from .env"]);
        exit();
    }

    $endpoint = $url . $path;
    if (!empty($query)) {
        $endpoint .= '?' . http_build_query($query);
    }

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $defaultHeaders = [
        "apikey: {$secretKey}",
        "Authorization: Bearer {$secretKey}",
        "Content-Type: application/json",
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($defaultHeaders, $extraHeaders));

    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    return [
        'code' => $httpCode,
        'data' => json_decode($response, true),
    ];
}

function sendSupabaseError($message, $res, $code = 500, $extra = [])
{
    error_log(sprintf(
        "[pawcircle][%s] %s | http=%s | response=%s",
        requestId(),
        $message,
        $res['code'] ?? 'n/a',
        json_encode($res['data'] ?? null)
    ));

    if (defined('PAWCIRCLE_DEBUG') && PAWCIRCLE_DEBUG) {
        jsonError($message, $code, array_merge($extra, [
            "supabase_http_code" => $res['code'] ?? null,
            "supabase_response" => $res['data'] ?? null,
        ]));
    } else {
        jsonError("Could not complete that request.", $code, $extra);
    }
}

function cleanPlainValue($value, $maxLength = 255)
{
    $text = trim((string) ($value ?? ''));
    $text = strip_tags($text);
    if ($maxLength > 0 && strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }
    return $text;
}

/**
 * cleanPlainValue() for a value that gets interpolated into a PostgREST
 * filter *expression* (an or=/and= clause), rather than passed as a plain
 * eq. operand.
 *
 * PostgREST parses , ( ) . * as structure, so a value carrying them can close
 * one logic group and open another — rewriting the boolean tree rather than
 * just the value. That used to be merely a widened match; since handleGetPosts
 * now expresses group-membership visibility as a nested and=(or(),or()), the
 * same injection is a visibility bypass. Strip them outright: no legitimate
 * pet_type or breed contains any of these.
 */
function cleanFilterValue($value, $maxLength = 255)
{
    return str_replace(
        ['(', ')', ',', '.', '*', '"', '\\'],
        '',
        cleanPlainValue($value, $maxLength)
    );
}
