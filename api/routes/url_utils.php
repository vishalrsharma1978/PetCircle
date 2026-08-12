<?php
/**
 * Shared URL/storage-path helpers for anything that resolves a public Supabase
 * Storage URL or proxies an external file (currently: care_guides.php).
 * Ported from community_proj/api/routes/url_utils.php with no behavior changes.
 */

function stripTrackingQuery($url)
{
    return preg_replace('/\?utm_source=chatgpt\.com$/', '', (string) $url);
}

function isAbsoluteHttpUrl($url)
{
    return is_string($url) && preg_match('/^https?:\/\//i', trim($url));
}

function isPrivateOrReservedIp($ip)
{
    if (!filter_var($ip, FILTER_VALIDATE_IP))
        return true;
    $flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
    return filter_var($ip, FILTER_VALIDATE_IP, $flags) === false;
}

function isSafeExternalFetchUrl($url)
{
    $url = trim((string) $url);
    if (!isAbsoluteHttpUrl($url))
        return false;
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '')
        return false;
    if ($host === 'localhost' || str_ends_with($host, '.localhost'))
        return false;
    if (filter_var($host, FILTER_VALIDATE_IP))
        return !isPrivateOrReservedIp($host);

    $records = @dns_get_record($host, DNS_A + DNS_AAAA);
    if (empty($records))
        return false;
    foreach ($records as $record) {
        $ip = $record['ip'] ?? $record['ipv6'] ?? '';
        if ($ip !== '' && isPrivateOrReservedIp($ip)) {
            return false;
        }
    }
    return true;
}

function encodeStoragePath($path)
{
    $path = trim((string) $path, '/');
    if ($path === '')
        return '';
    $parts = array_map('rawurlencode', explode('/', $path));
    return implode('/', $parts);
}

function buildPublicStorageUrl($bucketId, $objectPath, $intent = 'read', $filename = '')
{
    $objectPath = trim((string) $objectPath);
    if ($objectPath === '')
        return '';

    // Allow emergency rows where a full URL is stored in pdf_path/epub_path.
    if (isAbsoluteHttpUrl($objectPath)) {
        return stripTrackingQuery($objectPath);
    }

    $supabaseUrl = rtrim(envValue('SUPABASE_URL'), '/');
    if ($supabaseUrl === '')
        return '';

    $bucketId = trim((string) ($bucketId ?: 'pet-care-guides'));
    $url = $supabaseUrl
        . '/storage/v1/object/public/'
        . rawurlencode($bucketId)
        . '/'
        . encodeStoragePath($objectPath);

    if ($intent === 'download') {
        $downloadName = $filename ?: basename($objectPath);
        if ($downloadName) {
            $url .= '?download=' . rawurlencode($downloadName);
        }
    }

    return $url;
}

function resolveCareGuideUrl($guide, $format = 'pdf', $intent = 'read')
{
    $format = strtolower((string) $format) === 'epub' ? 'epub' : 'pdf';

    $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
    $pathKey = $format === 'epub' ? 'epub_path' : 'pdf_path';

    $externalUrl = trim((string) ($guide[$externalKey] ?? ''));
    if ($externalUrl !== '') {
        // External URLs always win for their own format only, so a guide can use
        // an external PDF while still using a bucket-hosted EPUB independently.
        $externalUrl = stripTrackingQuery($externalUrl);
        return isSafeExternalFetchUrl($externalUrl) ? $externalUrl : '';
    }

    $objectPath = trim((string) ($guide[$pathKey] ?? ''));
    if ($objectPath === '')
        return '';

    return buildPublicStorageUrl(
        $guide['bucket_id'] ?? 'pet-care-guides',
        $objectPath,
        $intent,
        ''
    );
}
