<?php

function supabaseRequest($method, $path, $query = [], $body = null, $extraHeaders = [])
{
    static $ch = null;

    $url = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
    $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');

    if (empty($url) || empty($secretKey)) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Supabase API keys are missing from .env"]);
        exit();
    }

    $endpoint = $url . $path;
    if (!empty($query)) {
        $endpoint .= '?' . http_build_query($query);
    }

    if ($ch === null) {
        $ch = curl_init();
    } else {
        curl_reset($ch);
    }

    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if (function_exists('applyCurlTlsOptions')) {
        applyCurlTlsOptions($ch);
    }

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
