<?php

/**
 * AWS S3 storage helper.
 *
 * Uploads user media (profile/cover photos, post media, gallery media) to an
 * S3 bucket using SigV4-signed raw cURL — no aws-sdk-php dependency, matching
 * this repo's raw-cURL convention (see supabaseRequest / handlePhotoUpload).
 *
 * Objects are stored under a single bucket, keyed by their logical bucket name
 * as a prefix (e.g. "post-media/<user>/<file>"), and served as permanent public
 * URLs — mirroring the previous Supabase Storage behavior so DB rows can keep
 * storing a stable, non-expiring address. The bucket must grant public
 * s3:GetObject via a bucket policy (see docs/aws-s3-setup.md).
 *
 * When S3 is not configured, callers fall back to Supabase Storage, so existing
 * deployments keep working until the AWS_* env vars are set.
 */

function s3Config()
{
    return [
        'bucket' => envValue('AWS_S3_BUCKET'),
        'region' => envValue('AWS_S3_REGION'),
        'key' => envValue('AWS_ACCESS_KEY_ID'),
        'secret' => envValue('AWS_SECRET_ACCESS_KEY'),
        // Optional CDN / custom-domain base for public URLs, no trailing slash
        // (e.g. https://cdn.example.com). Falls back to the virtual-hosted S3 URL.
        'publicBase' => rtrim(envValue('AWS_S3_PUBLIC_BASE_URL'), '/'),
        // Optional S3 API endpoint override for S3-compatible providers, no
        // trailing slash (e.g. https://<bucket>.r2.cloudflarestorage.com).
        'endpoint' => rtrim(envValue('AWS_S3_ENDPOINT'), '/'),
    ];
}

function s3IsConfigured()
{
    $c = s3Config();
    return $c['bucket'] !== '' && $c['region'] !== '' && $c['key'] !== '' && $c['secret'] !== '';
}

/** Base URL (scheme + host) used for signed S3 API requests. */
function s3EndpointBase($c)
{
    if ($c['endpoint'] !== '') {
        return $c['endpoint'];
    }
    return "https://{$c['bucket']}.s3.{$c['region']}.amazonaws.com";
}

/** URI-encode each path segment of an object key, preserving the "/" separators. */
function s3EncodeKey($key)
{
    $key = ltrim((string) $key, '/');
    // Enforce petcircle/ prefix so uploads do not interlink with community_proj
    if (strpos($key, 'petcircle/') !== 0) {
        $key = 'petcircle/' . $key;
    }
    $parts = array_map('rawurlencode', explode('/', $key));
    return implode('/', $parts);
}

/** Permanent public URL for a stored object key. */
function s3PublicUrl($key)
{
    $c = s3Config();
    $encoded = s3EncodeKey($key);
    $base = $c['publicBase'] !== '' ? $c['publicBase'] : s3EndpointBase($c);
    return $base . '/' . $encoded;
}

/**
 * Perform a SigV4-signed S3 request. Returns
 * ['code' => int, 'body' => string, 'error' => string].
 */
function s3Request($method, $key, $body = '', $contentType = null)
{
    $c = s3Config();
    if (!s3IsConfigured()) {
        return ['code' => 0, 'body' => '', 'error' => 'S3 not configured'];
    }

    $base = s3EndpointBase($c);
    $parts = parse_url($base);
    $host = ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');
    $encodedKey = s3EncodeKey($key);
    $canonicalUri = '/' . $encodedKey;
    $url = $base . '/' . $encodedKey;

    $body = $body === null ? '' : $body;
    $amzDate = gmdate('Ymd\THis\Z');
    $shortDate = gmdate('Ymd');

    $isStream = is_resource($body);
    $payloadHash = $isStream ? 'UNSIGNED-PAYLOAD' : hash('sha256', (string) $body);

    // Signed headers, sorted lowercase by name.
    $headers = [
        'host' => $host,
        'x-amz-content-sha256' => $payloadHash,
        'x-amz-date' => $amzDate,
    ];
    if ($contentType) {
        $headers['content-type'] = $contentType;
    }
    if ($method === 'PUT') {
        $headers['cache-control'] = 'max-age=31536000, public';
    }
    ksort($headers);

    $canonicalHeaders = '';
    foreach ($headers as $name => $value) {
        $canonicalHeaders .= $name . ':' . trim((string) $value) . "\n";
    }
    $signedHeaders = implode(';', array_keys($headers));

    $canonicalRequest = $method . "\n"
        . $canonicalUri . "\n"
        . "" . "\n" // empty canonical query string
        . $canonicalHeaders . "\n"
        . $signedHeaders . "\n"
        . $payloadHash;

    $scope = $shortDate . '/' . $c['region'] . '/s3/aws4_request';
    $stringToSign = "AWS4-HMAC-SHA256\n"
        . $amzDate . "\n"
        . $scope . "\n"
        . hash('sha256', $canonicalRequest);

    $kDate = hash_hmac('sha256', $shortDate, 'AWS4' . $c['secret'], true);
    $kRegion = hash_hmac('sha256', $c['region'], $kDate, true);
    $kService = hash_hmac('sha256', 's3', $kRegion, true);
    $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
    $signature = hash_hmac('sha256', $stringToSign, $kSigning);

    $authorization = "AWS4-HMAC-SHA256 "
        . "Credential={$c['key']}/{$scope}, "
        . "SignedHeaders={$signedHeaders}, "
        . "Signature={$signature}";

    $curlHeaders = [
        "Authorization: {$authorization}",
        "x-amz-content-sha256: {$payloadHash}",
        "x-amz-date: {$amzDate}",
        "Expect:", // disable 100-continue; keeps the request single round-trip
    ];
    if (isset($headers['content-type'])) {
        $curlHeaders[] = "Content-Type: {$headers['content-type']}";
    }
    if (isset($headers['cache-control'])) {
        $curlHeaders[] = "Cache-Control: {$headers['cache-control']}";
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
    if ($method === 'PUT' || $method === 'POST') {
        if ($isStream) {
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $body);
            $fstat = fstat($body);
            curl_setopt($ch, CURLOPT_INFILESIZE, $fstat['size']);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }
    $response = curl_exec($ch);
    if ($isStream) {
        fclose($body);
    }
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlError = curl_error($ch);

    return ['code' => $httpCode, 'body' => (string) $response, 'error' => $curlError];
}

/** Upload an object. Returns the full response array. */
function s3PutObject($key, $body, $contentType)
{
    return s3Request('PUT', $key, $body, $contentType);
}

/** Delete an object by key. Returns true on success (or if already gone). */
function s3DeleteObject($key)
{
    $res = s3Request('DELETE', $key, '', null);
    return $res['code'] === 200 || $res['code'] === 204;
}

/**
 * If $url points at our S3 bucket (direct S3, path-style, or the configured
 * public/CDN base), return ['key' => objectKey]; otherwise null.
 */
function parseS3Url($url)
{
    if (!is_string($url) || $url === '') {
        return null;
    }
    $c = s3Config();

    $bases = [];
    if ($c['publicBase'] !== '') {
        $bases[] = $c['publicBase'];
    }
    if ($c['endpoint'] !== '') {
        $bases[] = $c['endpoint'];
    }
    if ($c['bucket'] !== '' && $c['region'] !== '') {
        $bases[] = "https://{$c['bucket']}.s3.{$c['region']}.amazonaws.com";
        $bases[] = "https://s3.{$c['region']}.amazonaws.com/{$c['bucket']}";
    }

    foreach ($bases as $base) {
        $prefix = rtrim($base, '/') . '/';
        if (strncmp($url, $prefix, strlen($prefix)) === 0) {
            $key = rawurldecode(substr($url, strlen($prefix)));
            return $key !== '' ? ['key' => $key] : null;
        }
    }
    return null;
}
