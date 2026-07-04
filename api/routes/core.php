<?php

function mb_substr($string, $start, $length = null, $encoding = null)
    {
        if ($length === null) {
            return substr($string, $start);
        }
        return substr($string, $start, $length);
    }

function resolveCaBundlePath()
{
    $fromEnv = trim((string) (getenv('CA_BUNDLE_PATH') ?: ($_ENV['CA_BUNDLE_PATH'] ?? '')));
    if ($fromEnv !== '') {
        if (is_file($fromEnv)) {
            return $fromEnv;
        }

        error_log('[PAWCIRCLE][TLS][' . requestId() . '] CA_BUNDLE_PATH is set but file was not found: ' . $fromEnv);
    }

    $localBundle = __DIR__ . '/cacert.pem';
    if (is_file($localBundle)) {
        return $localBundle;
    }

    $iniBundle = trim((string) ini_get('curl.cainfo'));
    if ($iniBundle !== '' && is_file($iniBundle)) {
        return $iniBundle;
    }

    $iniOpenSsl = trim((string) ini_get('openssl.cafile'));
    if ($iniOpenSsl !== '' && is_file($iniOpenSsl)) {
        return $iniOpenSsl;
    }

    return '';
}

function applyCurlTlsOptions($ch)
{
    // Always enforce certificate and host verification for outbound HTTPS calls.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $caPath = resolveCaBundlePath();
    if ($caPath !== '') {
        curl_setopt($ch, CURLOPT_CAINFO, $caPath);
        return;
    }

    // On Windows, missing CA bundles are a common cause of
    // "unable to get local issuer certificate" TLS failures.
    if (PHP_OS_FAMILY === 'Windows') {
        static $warned = false;
        if (!$warned) {
            $warned = true;
            error_log('[PAWCIRCLE][TLS][' . requestId() . '] No usable CA bundle found. Set CA_BUNDLE_PATH in .env to a valid cacert.pem file.');
        }
    }
}

function requestId()
{
    static $id = null;
    if ($id === null) {
        try {
            $id = bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $id = substr(md5(uniqid('', true)), 0, 16);
        }
    }
    return $id;
}

function envValue($key, $default = '')
{
    return getenv($key) ?: ($_ENV[$key] ?? $default);
}

function supabaseAnonKey()
{
    return envValue('SUPABASE_ANON_KEY')
        ?: envValue('SUPABASE_PUBLIC_ANON_KEY')
        ?: envValue('SUPABASE_PUBLISHABLE_KEY')
        ?: envValue('SUPABASE_PUBLIC_KEY');
}

function split_part_fallback($value, $delimiter, $index, $fallback)
{
    $parts = explode($delimiter, (string) $value);
    $candidate = trim((string) ($parts[$index] ?? ''));
    return $candidate !== '' ? $candidate : $fallback;
}

function getMigrationStatusForAppUser($appUserId)
{
    if (!isValidUuid($appUserId)) {
        return '';
    }
    $res = supabaseRequest('GET', '/rest/v1/user_migration_review', [
        'user_id' => 'eq.' . strtolower($appUserId),
        'select' => 'migration_status',
        'limit' => '1',
    ]);
    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        return '';
    }
    return (string) ($res['data'][0]['migration_status'] ?? '');
}

function findAppUsersByEmail($email)
{
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [];
    }

    $res = supabaseRequest('GET', '/rest/v1/users', [
        'email' => 'ilike.' . $email,
        'select' => 'id,email,auth_user_id',
        'limit' => '20',
    ]);

    if (($res['code'] ?? 500) >= 400 || empty($res['data'])) {
        return [];
    }

    return $res['data'];
}

function finishResponseEarly()
{
    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    }
}

function isHttpsRequest()
{
    return (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function getBearerToken()
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    
    // Apache often strips the Authorization header by default
    if (empty($header) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    if (preg_match('/Bearer\s+(.+)/i', $header, $m)) {
        return trim($m[1]);
    }
    return '';
}

function getCsrfTokenHeader()
{
    return trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
}

function userHasOwnerCapability($userId)
{
    foreach (fetchAdminCapabilities($userId) as $cap) {
        if (($cap['role'] ?? '') === 'owner')
            return true;
    }
    return false;
}

function requireOwnerCapability($userId)
{
    if (!userHasOwnerCapability($userId)) {
        jsonError("Owner admin access is required for that action.", 403);
        exit();
    }
}

function privacyHash($value)
{
    $value = trim((string) $value);
    if ($value === '')
        return null;
    $salt = envValue('APP_AUDIT_SALT', envValue('SUPABASE_SECRET_KEY', 'pawcircle'));
    return hash('sha256', $salt . '|' . strtolower($value));
}

function getClientIpAddress()
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
        $_SERVER['HTTP_X_REAL_IP'] ?? '',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        $_SERVER['REMOTE_ADDR'] ?? '',
    ];

    foreach ($candidates as $candidate) {
        if (!$candidate)
            continue;
        $first = trim(explode(',', $candidate)[0]);
        if (filter_var($first, FILTER_VALIDATE_IP)) {
            return $first;
        }
    }
    return '';
}

function isLocalDevRequest()
{
    $ip = getClientIpAddress();
    return in_array($ip, ['127.0.0.1', '::1'], true)
        || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), 'localhost:')
        || str_starts_with((string) ($_SERVER['HTTP_HOST'] ?? ''), '127.0.0.1:');
}

function testUsersEnabled()
{
    return PAWCIRCLE_DEBUG
        && isLocalDevRequest()
        && in_array(strtolower((string) envValue('PAWCIRCLE_ENABLE_TEST_USERS', '')), ['1', 'true', 'yes', 'on'], true);
}

function testUserMap()
{
    return [
        'user' => ['name' => 'Test User', 'email' => 'test-user@pawcircle.local', 'pet_type' => 'Dog', 'breed' => 'General'],
        'userh' => ['name' => 'Test Hindu User', 'email' => 'test-user-hindu@pawcircle.local', 'pet_type' => 'Dog', 'breed' => 'Caste No Bar'],
        'userm' => ['name' => 'Test Muslim User', 'email' => 'test-user-muslim@pawcircle.local', 'pet_type' => 'Cat', 'breed' => 'Caste No Bar'],
        'userc' => ['name' => 'Test Christian User', 'email' => 'test-user-christian@pawcircle.local', 'pet_type' => 'Rabbit', 'breed' => 'Caste No Bar'],
        'userb' => ['name' => 'Test Buddhist User', 'email' => 'test-user-buddhist@pawcircle.local', 'pet_type' => 'Reptile', 'breed' => 'Caste No Bar'],
        'userp' => ['name' => 'Test Parsi User', 'email' => 'test-user-parsi@pawcircle.local', 'pet_type' => 'Small Pet', 'breed' => 'Caste No Bar'],
        'users' => ['name' => 'Test Sikh User', 'email' => 'test-user-sikh@pawcircle.local', 'pet_type' => 'Bird', 'breed' => 'Caste No Bar'],
        'userj' => ['name' => 'Test Jain User', 'email' => 'test-user-jain@pawcircle.local', 'pet_type' => 'Fish', 'breed' => 'Caste No Bar'],
        'usero' => ['name' => 'Test Other User', 'email' => 'test-user-other@pawcircle.local', 'pet_type' => 'Other', 'breed' => 'Other'],
    ];
}

function getClientIpHash()
{
    return privacyHash(getClientIpAddress());
}

function getClientUserAgent()
{
    $ua = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '')
        return null;
    return substr($ua, 0, 500);
}

function safePatchUserTrackingFields($userId, $fields, $context = 'user tracking')
{
    if (!isValidUuid($userId) || empty($fields))
        return false;

    $res = supabaseRequest('PATCH', '/rest/v1/users', [
        'id' => 'eq.' . strtolower($userId),
    ], $fields, ['Prefer: return=minimal']);

    if (($res['code'] ?? 500) >= 400) {
        error_log(sprintf(
            "[pawcircle][%s] %s update failed | user=%s | http=%s | response=%s",
            requestId(),
            $context,
            $userId,
            $res['code'] ?? 'n/a',
            json_encode($res['data'] ?? null)
        ));
        return false;
    }

    return true;
}

function rateLimitKey($scope, $value)
{
    return substr($scope . ':' . privacyHash($value), 0, 90);
}

function loadRateLimitBucket($rateKey)
{
    $res = supabaseRequest('GET', '/rest/v1/auth_rate_limits', [
        'rate_key' => 'eq.' . $rateKey,
        'select' => 'rate_key,attempts,window_start,blocked_until',
        'limit' => '1',
    ]);

    if (($res['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] auth rate limit load failed | http=" . ($res['code'] ?? 'n/a') . " | response=" . json_encode($res['data'] ?? null));
        jsonError("Sign-in protection is not configured. Please contact support.", 500);
        exit();
    }

    return $res['data'][0] ?? null;
}

function saveRateLimitBucket($rateKey, $attempts, $windowStart, $blockedUntil = null)
{
    $res = supabaseRequest('POST', '/rest/v1/auth_rate_limits', [
        'on_conflict' => 'rate_key',
    ], [
        'rate_key' => $rateKey,
        'attempts' => $attempts,
        'window_start' => $windowStart,
        'blocked_until' => $blockedUntil,
        'updated_at' => nowIsoUtc(),
    ], ['Prefer: resolution=merge-duplicates,return=minimal']);

    if (($res['code'] ?? 500) >= 400) {
        error_log("[pawcircle][" . requestId() . "] auth rate limit save failed | http=" . ($res['code'] ?? 'n/a') . " | response=" . json_encode($res['data'] ?? null));
        jsonError("Sign-in protection is not configured. Please contact support.", 500);
        exit();
    }
}

function markUserActive($userId, $source = 'activity')
{
    $now = nowIsoUtc();

    $ok = safePatchUserTrackingFields($userId, [
        'last_active_at' => $now,
    ], 'last_active_at');

    return [$ok, $now];
}

function handleTrackActivity($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');

    $source = strtolower(trim((string) ($data['source'] ?? 'activity')));
    $source = preg_replace('/[^a-z0-9_:\-]/', '', $source);
    if ($source === '')
        $source = 'activity';
    $source = substr($source, 0, 50);

    [$ok, $now] = markUserActive($userId, $source);

    jsonSuccess([
        'last_active_at' => $now,
        'activity_recorded' => $ok,
        'source' => $source,
    ]);
}

function slugifyPetService($value)
{
    $slug = strtolower(trim((string) $value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

function stripTrackingQuery($url)
{
    return preg_replace('/\?utm_source=chatgpt\.com$/', '', (string) $url);
}

function isTruthyDbValue($value, $default = false)
{
    if ($value === null || $value === '')
        return $default;
    if (is_bool($value))
        return $value;
    if (is_int($value))
        return $value === 1;
    $normalised = strtolower(trim((string) $value));
    if (in_array($normalised, ['1', 'true', 't', 'yes', 'y', 'on'], true))
        return true;
    if (in_array($normalised, ['0', 'false', 'f', 'no', 'n', 'off'], true))
        return false;
    return $default;
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

    $bucketId = trim((string) ($bucketId ?: 'holy-books'));
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

function holyBookFilename($book, $format)
{
    $slug = $book['slug'] ?? slugifyPetService(($book['service_type'] ?? '') . '-' . ($book['title'] ?? 'holy-book'));
    return $slug . '.' . $format;
}

function resolvePetServiceUrl($book, $format = 'pdf', $intent = 'read')
{
    $format = strtolower((string) $format) === 'epub' ? 'epub' : 'pdf';

    $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
    $pathKey = $format === 'epub' ? 'epub_path' : 'pdf_path';

    $externalUrl = trim((string) ($book[$externalKey] ?? ''));
    if ($externalUrl !== '') {
        // External URLs always win for their own format only. This lets Quran
        // use an external PDF while still using a bucket EPUB independently.
        $externalUrl = stripTrackingQuery($externalUrl);
        return isSafeExternalFetchUrl($externalUrl) ? $externalUrl : '';
    }

    $objectPath = trim((string) ($book[$pathKey] ?? ''));
    if ($objectPath === '')
        return '';

    return buildPublicStorageUrl(
        $book['bucket_id'] ?? 'holy-books',
        $objectPath,
        $intent,
        holyBookFilename($book, $format)
    );
}

function holyBookSourceType($book, $format = 'pdf')
{
    $format = strtolower((string) $format) === 'epub' ? 'epub' : 'pdf';
    $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
    $pathKey = $format === 'epub' ? 'epub_path' : 'pdf_path';

    if (!empty($book[$externalKey]))
        return 'external';
    if (!empty($book[$pathKey]))
        return 'bucket';
    return 'none';
}

function buildEbookRoute($bookId, $format = 'pdf', $intent = 'read', $mode = 'scroll')
{
    return 'backend_api.php?action=ebook_redirect'
        . '&book_id=' . rawurlencode($bookId)
        . '&format=' . rawurlencode($format)
        . '&intent=' . rawurlencode($intent)
        . '&mode=' . rawurlencode($mode);
}

function buildEbookFileRoute($bookId, $format = 'pdf', $intent = 'read', $mode = 'page')
{
    return 'backend_api.php?action=ebook_file'
        . '&book_id=' . rawurlencode($bookId)
        . '&format=' . rawurlencode($format)
        . '&intent=' . rawurlencode($intent)
        . '&mode=' . rawurlencode($mode);
}

function holyBookSectionMeta($pet_type)
{
    $key = normalizePetTypeKey($pet_type);
    $meta = [
        'Dog' => [
            'title' => 'Dharmic Granth',
            'subtitle' => 'धार्मिक ग्रंथ',
            'type' => 'pet service',
        ],
        'Cat' => [
            'title' => 'Islamic Texts',
            'subtitle' => 'النصوص الإسلامية',
            'type' => 'pet service',
        ],
        'Bird' => [
            'title' => 'Sikh Scripture',
            'subtitle' => 'ਸਿੱਖ ਧਰਮ ਗ੍ਰੰਥ',
            'type' => 'pet service',
        ],
        'Rabbit' => [
            'title' => 'Christian Texts',
            'subtitle' => 'Holy Bible',
            'type' => 'pet service',
        ],
        'Fish' => [
            'title' => 'Jain Agamas',
            'subtitle' => 'જૈન આગમ',
            'type' => 'pet service',
        ],
        'Reptile' => [
            'title' => 'Buddhist Texts',
            'subtitle' => 'बौद्ध ग्रंथ',
            'type' => 'pet service',
        ],
        'Small Pet' => [
            'title' => 'Zoroastrian Texts',
            'subtitle' => 'Zend Avesta',
            'type' => 'pet service',
        ],
    ];
    return $meta[$key] ?? [
        'title' => $key ? ($key . ' Texts') : 'Pet Services',
        'subtitle' => 'Ebooks',
        'type' => 'pet service',
    ];
}

function stringContains($haystack, $needle)
{
    return strpos((string) $haystack, (string) $needle) !== false;
}

function holyBookUiMeta($book)
{
    $pet_type = normalizePetTypeKey($book['service_type'] ?? '');
    $title = strtolower((string) ($book['title'] ?? ''));

    $byPetType = [
        'Dog' => ['icon' => 'book-open', 'bg' => 'bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400'],
        'Cat' => ['icon' => 'book-open', 'bg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'],
        'Bird' => ['icon' => 'book-open', 'bg' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'],
        'Rabbit' => ['icon' => 'book-open', 'bg' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400'],
        'Fish' => ['icon' => 'book-open', 'bg' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
        'Reptile' => ['icon' => 'book-open', 'bg' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'],
        'Small Pet' => ['icon' => 'book-open', 'bg' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
    ];

    $ui = $byPetType[$pet_type] ?? ['icon' => 'book-open', 'bg' => 'bg-amber-50 text-amber-700'];

    if (stringContains($title, 'hadith') || stringContains($title, 'sahih') || stringContains($title, 'bible') || stringContains($title, 'ramayana')) {
        $ui['icon'] = 'book';
    } elseif (stringContains($title, 'tafsir') || stringContains($title, 'sutra') || stringContains($title, 'zend')) {
        $ui['icon'] = 'scroll-text';
    } elseif (stringContains($title, 'veda') || stringContains($title, 'tripitaka') || stringContains($title, 'gathas')) {
        $ui['icon'] = 'library';
    } elseif (stringContains($title, 'purana') || stringContains($title, 'progress') || stringContains($title, 'sirah')) {
        $ui['icon'] = 'book-marked';
    }

    return $ui;
}

function normalizePetTypeKey($pet_type)
{
    $pet_type = trim((string) $pet_type);
    $aliases = [
        'Parsi / Zoroastrian' => 'Small Pet',
        'Zoroastrian' => 'Small Pet',
        'Zoroastrianism' => 'Small Pet',
        'Cat' => 'Cat',
        'Islam' => 'Cat',
        'Islamic' => 'Cat',
        'Christianity' => 'Rabbit',
        'Buddhism' => 'Reptile',
        'Jainism' => 'Fish',
        'Sikhism' => 'Bird',
        'Hinduism' => 'Dog',
    ];
    return $aliases[$pet_type] ?? ($pet_type ?: 'Dog');
}

function getPetServiceRows()
{
    $res = supabaseRequest('GET', '/rest/v1/pet_services', [
        'select' => 'id,slug,service_type,title,subtitle,description,language,source_label,source_url,bucket_id,pdf_path,epub_path,external_pdf_url,external_epub_url,default_read_mode,scroll_enabled,page_enabled,pdf_download_enabled,epub_download_enabled,sort_order,is_active',
        'is_active' => 'eq.true',
        'order' => 'service_type.asc,sort_order.asc,title.asc',
    ]);

    if (($res['code'] ?? 500) >= 400 || !is_array($res['data'])) {
        $message = is_array($res['data'])
            ? ($res['data']['message'] ?? json_encode($res['data']))
            : 'Could not read pet_services from Supabase.';
        return [
            'ok' => false,
            'message' => 'Failed to load pet services: ' . $message . ' (HTTP ' . ($res['code'] ?? 'unknown') . ')',
            'data' => [],
        ];
    }

    return ['ok' => true, 'message' => '', 'data' => $res['data']];
}

function normalisePetServiceRowForFrontend($row)
{
    $slug = trim((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugifyPetService(($row['service_type'] ?? '') . '-' . ($row['title'] ?? 'holy-book'));
    }

    $row['slug'] = $slug;
    $pet_type = normalizePetTypeKey($row['service_type'] ?? '');
    $ui = holyBookUiMeta($row);

    $pdfUrl = resolvePetServiceUrl($row, 'pdf', 'read');
    $epubUrl = resolvePetServiceUrl($row, 'epub', 'download');

    $pdfAvailable = $pdfUrl !== '';
    $epubAvailable = $epubUrl !== '';

    $scrollEnabled = $pdfAvailable && isTruthyDbValue($row['scroll_enabled'] ?? true, true);
    $pageEnabled = $pdfAvailable && isTruthyDbValue($row['page_enabled'] ?? true, true);
    $pdfDownloadEnabled = $pdfAvailable && isTruthyDbValue($row['pdf_download_enabled'] ?? true, true);
    $epubDownloadEnabled = $epubAvailable && isTruthyDbValue($row['epub_download_enabled'] ?? false, false);

    $desc = trim((string) ($row['subtitle'] ?? ''));
    if ($desc === '')
        $desc = trim((string) ($row['description'] ?? ''));
    if ($desc === '')
        $desc = trim((string) ($row['language'] ?? ''));
    if ($desc === '')
        $desc = 'Sacred text';

    $pdfSourceType = holyBookSourceType($row, 'pdf');
    $epubSourceType = holyBookSourceType($row, 'epub');

    $pdfReadAction = 'redirect';
    $epubDownloadAction = 'redirect';
    $pdfDownloadAction = 'redirect';

    $pdfReadRoute = function ($mode) use ($slug, $pdfReadAction) {
        return $pdfReadAction === 'file'
            ? buildEbookFileRoute($slug, 'pdf', 'read', $mode)
            : buildEbookRoute($slug, 'pdf', 'read', $mode);
    };

    return [
        'id' => $slug,
        'slug' => $slug,
        'pet_type' => $pet_type,
        'title' => $row['title'] ?? 'Pet Service',
        'desc' => $desc,
        'description' => $row['description'] ?? '',
        'language' => $row['language'] ?? '',
        'source_label' => $row['source_label'] ?? '',
        'source_url' => $row['source_url'] ?? '',
        'sort_order' => intval($row['sort_order'] ?? 100),
        'icon' => $ui['icon'],
        'bg' => $ui['bg'],
        'read_target' => $pdfSourceType === 'external' ? 'new_tab' : 'modal',
        'source_types' => [
            'pdf' => $pdfSourceType,
            'epub' => $epubSourceType,
        ],
        'read' => [
            'default' => ($row['default_read_mode'] ?? 'page') === 'scroll' ? 'scroll' : 'page',
            'target' => $pdfSourceType === 'external' ? 'new_tab' : 'modal',
            'scroll' => $scrollEnabled ? $pdfReadRoute('scroll') : '',
            'page' => $pageEnabled ? $pdfReadRoute('page') : '',
        ],
        'downloads' => [
            'pdf' => [
                'label' => 'PDF',
                'available' => $pdfDownloadEnabled,
                'url' => $pdfDownloadEnabled ? ($pdfDownloadAction === 'file' ? buildEbookFileRoute($slug, 'pdf', 'download', 'page') : buildEbookRoute($slug, 'pdf', 'download', 'page')) : '',
                'source_type' => $pdfSourceType,
            ],
            'epub' => [
                'label' => 'EPUB',
                'available' => $epubDownloadEnabled,
                'url' => $epubDownloadEnabled ? ($epubDownloadAction === 'file' ? buildEbookFileRoute($slug, 'epub', 'download', 'page') : buildEbookRoute($slug, 'epub', 'download', 'page')) : '',
                'source_type' => $epubSourceType,
            ],
        ],
    ];
}

function findPetServiceById($bookId)
{
    $bookId = trim((string) $bookId);
    if ($bookId === '')
        return null;

    $rows = getPetServiceRows();
    if (!$rows['ok'])
        return null;

    $wanted = slugifyPetService($bookId);
    foreach ($rows['data'] as $row) {
        $candidates = array_filter([
            trim((string) ($row['slug'] ?? '')),
            trim((string) ($row['id'] ?? '')),
            slugifyPetService(($row['service_type'] ?? '') . '-' . ($row['title'] ?? '')),
            slugifyPetService($row['title'] ?? ''),
        ]);

        foreach ($candidates as $candidate) {
            if ($bookId === $candidate || $wanted === slugifyPetService($candidate)) {
                return $row;
            }
        }
    }

    return null;
}

function handleGetPetServices($data)
{
    $rows = getPetServiceRows();
    if (!$rows['ok']) {
        jsonError($rows['message'], 500);
        return;
    }

    $pet_typeKey = normalizePetTypeKey($data['pet_type'] ?? ($_GET['pet_type'] ?? 'Dog'));
    $books = [];

    foreach ($rows['data'] as $row) {
        if (normalizePetTypeKey($row['service_type'] ?? '') !== $pet_typeKey) {
            continue;
        }
        $books[] = normalisePetServiceRowForFrontend($row);
    }

    usort($books, function ($a, $b) {
        $orderA = intval($a['sort_order'] ?? 100);
        $orderB = intval($b['sort_order'] ?? 100);
        if ($orderA === $orderB) {
            return strcmp(strtolower($a['title'] ?? ''), strtolower($b['title'] ?? ''));
        }
        return $orderA <=> $orderB;
    });

    $section = holyBookSectionMeta($pet_typeKey);
    $section['books'] = $books;

    jsonSuccess([
        'backend_build' => PAWCIRCLE_BACKEND_BUILD,
        'backend_source' => 'supabase_pet_services_table',
        'pet_type' => $pet_typeKey,
        'section' => $section,
        'read_options' => [
            'default' => 'scroll',
            'options' => ['scroll', 'page'],
        ],
        'download_options' => ['pdf', 'epub'],
    ]);
}

function withPdfViewerFragment($url, $mode)
{
    if (preg_match('/\.(pdf)(\?|$)/i', $url)) {
        $fragment = $mode === 'page' ? '#page=1&view=Fit&toolbar=1' : '#view=FitH&toolbar=1';
        return preg_replace('/#.*$/', '', $url) . $fragment;
    }
    return $url;
}

function holyBookRequestContext($data)
{
    $format = strtolower($_GET['format'] ?? ($data['format'] ?? 'pdf'));
    $intent = strtolower($_GET['intent'] ?? ($data['intent'] ?? 'read'));
    $mode = strtolower($_GET['mode'] ?? ($data['mode'] ?? 'page'));

    if (!in_array($format, ['pdf', 'epub'], true)) {
        return ['ok' => false, 'message' => 'Unsupported ebook format.', 'code' => 400];
    }
    if (!in_array($intent, ['read', 'download'], true))
        $intent = 'read';
    if (!in_array($mode, ['scroll', 'page'], true))
        $mode = 'page';
    if ($format === 'epub' && $intent === 'read')
        $intent = 'download';

    $bookId = $_GET['book_id'] ?? ($data['book_id'] ?? '');
    $book = findPetServiceById($bookId);
    if (!$book) {
        return ['ok' => false, 'message' => 'Unknown ebook requested.', 'code' => 404];
    }

    if ($format === 'pdf' && $intent === 'read') {
        if ($mode === 'page' && !isTruthyDbValue($book['page_enabled'] ?? true, true)) {
            return ['ok' => false, 'message' => 'Page reading is not enabled for this ebook.', 'code' => 404];
        }
        if ($mode === 'scroll' && !isTruthyDbValue($book['scroll_enabled'] ?? true, true)) {
            return ['ok' => false, 'message' => 'Scroll reading is not enabled for this ebook.', 'code' => 404];
        }
    }

    if ($format === 'pdf' && $intent === 'download' && !isTruthyDbValue($book['pdf_download_enabled'] ?? true, true)) {
        return ['ok' => false, 'message' => 'PDF download is not enabled for this ebook.', 'code' => 404];
    }

    if ($format === 'epub' && !isTruthyDbValue($book['epub_download_enabled'] ?? false, false)) {
        return ['ok' => false, 'message' => 'EPUB download is not enabled for this ebook.', 'code' => 404];
    }

    $url = resolvePetServiceUrl($book, $format, $intent);
    if (empty($url)) {
        return ['ok' => false, 'message' => 'No ' . strtoupper($format) . ' source is configured for this ebook.', 'code' => 404];
    }

    return [
        'ok' => true,
        'book' => $book,
        'url' => $url,
        'format' => $format,
        'intent' => $intent,
        'mode' => $mode,
    ];
}

function validateRemoteEbookForProxy($url, $format)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PawCircle Ebook Proxy');
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = strtolower((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
    $err = curl_error($ch);

    // Some object/CDN hosts do not implement HEAD correctly. Only block clear failures.
    if ($code >= 400) {
        return ['ok' => false, 'message' => 'The ebook file could not be reached at its configured storage URL. Check the bucket path and public read policy. HTTP ' . $code];
    }
    if ($err) {
        return ['ok' => false, 'message' => 'The ebook file could not be reached: ' . $err];
    }
    if ($contentType && strpos($contentType, 'text/html') !== false) {
        return ['ok' => false, 'message' => 'The configured ebook URL returned an HTML page, not a ' . strtoupper($format) . ' file. Check the path/URL.'];
    }
    return ['ok' => true];
}

function streamRemoteEbookFile($url, $format, $filename, $intent)
{
    ignore_user_abort(true);
    @set_time_limit(0);

    $range = trim((string) ($_SERVER['HTTP_RANGE'] ?? ''));
    $statusCode = 200;
    $responseHeaders = [];
    $sentHeaders = false;
    $bytesWritten = 0;

    header_remove('Content-Type');
    http_response_code(200);
    header('X-PawCircle-Ebook-Proxy: ' . PAWCIRCLE_BACKEND_BUILD);
    header('Cache-Control: private, max-age=600');
    header('Content-Type: ' . ($format === 'epub' ? 'application/epub+zip' : 'application/pdf'));
    $disposition = $intent === 'download' ? 'attachment' : 'inline';
    header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
    header('Accept-Ranges: bytes');
    header('X-Content-Type-Options: nosniff');

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
    curl_setopt($ch, CURLOPT_USERAGENT, 'PawCircle Ebook Proxy');
    if ($range !== '' && preg_match('/^bytes=\d*-\d*$/', $range)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Range: ' . $range]);
    }
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$statusCode, &$responseHeaders) {
        $line = trim($header);
        if ($line === '')
            return strlen($header);
        if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $line, $m)) {
            $statusCode = intval($m[1]);
            $responseHeaders = [];
            return strlen($header);
        }
        $parts = explode(':', $line, 2);
        if (count($parts) === 2) {
            $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
        }
        return strlen($header);
    });
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$sentHeaders, &$statusCode, &$responseHeaders, &$bytesWritten) {
        if (!$sentHeaders) {
            if ($statusCode >= 400) {
                http_response_code($statusCode);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'The ebook file could not be reached at its configured storage URL. HTTP ' . $statusCode]);
                $sentHeaders = true;
                return 0;
            }
            http_response_code($statusCode === 206 ? 206 : 200);
            foreach (['content-length', 'content-range', 'content-encoding'] as $name) {
                if (!empty($responseHeaders[$name])) {
                    header($name . ': ' . $responseHeaders[$name]);
                }
            }
            $sentHeaders = true;
        }
        echo $chunk;
        $bytesWritten += strlen($chunk);
        flush();
        return strlen($chunk);
    });
    curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (!$sentHeaders && ($err || $code >= 400)) {
        jsonError($err ?: ('The ebook file could not be reached at its configured storage URL. HTTP ' . $code), 502);
        return;
    }
    exit();
}

function handleEbookFile($data)
{
    $ctx = holyBookRequestContext($data);
    if (!$ctx['ok']) {
        jsonError($ctx['message'], $ctx['code']);
        return;
    }

    $book = $ctx['book'];
    $format = $ctx['format'];
    $intent = $ctx['intent'];
    $url = $ctx['url'];
    $filename = holyBookFilename($book, $format);

    // Do not proxy intentionally external PDFs such as the oversized Quran PDF.
    // Those remain direct redirects/new-tab reads.
    if (holyBookSourceType($book, $format) === 'external') {
        header_remove('Content-Type');
        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit();
    }

    streamRemoteEbookFile($url, $format, $filename, $intent);
}

function handleEbookRedirect($data)
{
    $ctx = holyBookRequestContext($data);
    if (!$ctx['ok']) {
        jsonError($ctx['message'], $ctx['code']);
        return;
    }

    $url = $ctx['url'];
    if ($ctx['intent'] === 'read' && $ctx['format'] === 'pdf') {
        $url = withPdfViewerFragment($url, $ctx['mode']);
    }

    header_remove('Content-Type');
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 302);
    exit();
}

function resolveTaxonomyValues($data) {
        $breed = $data['pet_breed'] ?? $data['breed'] ?? $data['breed_group'] ?? $data['interest_circle'] ?? null;
        $pet_type = $data['pet_type'] ?? $data['animal_type'] ?? $data['pet_type'] ?? null;

        return [
            'breed' => $breed,
            'pet_type' => $pet_type,
        ];
    }

function buildUserPayload($user, $loginAt = null)
{
    $profile = normaliseProfileEmbed($user['profiles'] ?? []);
    $customTags = profileCustomTags($profile);
    $adminCaps = fetchAdminCapabilities($user['id'] ?? '');
    $systemTags = adminCapabilityTags($adminCaps);
    $displayTags = profileDisplayTags($customTags, $systemTags);
    $interests = implode(', ', $customTags);

    return [
        "id" => $user['id'],
        "name" => $profile['pet_name'] ?? ($user['email'] ?? 'Member'),
        "pet_name" => $profile['pet_name'] ?? '',
        "parent_name" => $profile['parent_name'] ?? '',
        "email" => $user['email'] ?? '',
        "role" => $user['role'] ?? 'member',
        "pet_type" => $profile['pet_type'] ?? 'Dog',
        "breed" => $profile['breed'] ?? '',
        "breed" => $profile['breed'] ?? '',
        "pet_type" => $profile['pet_type'] ?? '',
        "membership_applied" => $profile['membership_applied'] ?? false,
        "membership_status" => $profile['status'] ?? 'none',
        "profile_photo_url" => $profile['profile_photo_url'] ?? null,
        "cover_photo_url" => $profile['cover_photo_url'] ?? null,
        "mobile_number" => $profile['mobile_number'] ?? null,
        "gender" => $profile['gender'] ?? null,
        "bio" => $profile['bio'] ?? '',
        "current_city" => $profile['current_city'] ?? null,
        "last_login_at" => $loginAt ?? ($user['last_login_at'] ?? null),
        "last_active_at" => $user['last_active_at'] ?? null,
        // "primary_interests" => $customTags,
        "custom_tags" => $customTags,
        "system_tags" => $systemTags,
        "tags" => $displayTags,
        "socialProfile" => [
            "name" => $profile['pet_name'] ?? ($user['email'] ?? 'Member'),
            "pet_name" => $profile['pet_name'] ?? '',
            "parent_name" => $profile['parent_name'] ?? '',
            "pet_type" => $profile['pet_type'] ?? '',
            "breed" => $profile['breed'] ?? '',
            "gender" => $profile['gender'] ?? null,
            "bio" => $profile['bio'] ?? '',
            "currentCity" => $profile['current_city'] ?? null,
            "contactNo" => $profile['mobile_number'] ?? null,
            "shareContact" => true,
            "tags" => $customTags,
            "customTags" => $customTags,
            "systemTags" => $systemTags,
            "displayTags" => $displayTags,
        ],
        "personalization" => [
            "interests" => $interests,
            "tags" => $customTags,
        ],
        "admin_capabilities" => $adminCaps,
        "admin_mode_active" => false,
        "admin_mode_until" => null,
    ];
}

function countRowsApprox($table, $filters = [])
{
    $query = array_merge(['select' => 'id', 'limit' => '1000'], $filters);
    $res = supabaseRequest('GET', '/rest/v1/' . $table, $query);
    if (($res['code'] ?? 500) >= 400)
        return 0;
    return count($res['data'] ?? []);
}

function recentRows($table, $select, $limit = 100, $filters = [])
{
    $query = array_merge([
        'select' => $select,
        'order' => 'created_at.desc',
        'limit' => (string) max(1, min($limit, 500)),
    ], $filters);
    $res = supabaseRequest('GET', '/rest/v1/' . $table, $query);
    return (($res['code'] ?? 500) >= 400) ? [] : ($res['data'] ?? []);
}

function bucketCounts($rows, $key)
{
    $counts = [];
    foreach (($rows ?? []) as $row) {
        $value = trim((string) ($row[$key] ?? ''));
        if ($value === '')
            $value = 'Unspecified';
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    arsort($counts);
    $out = [];
    foreach (array_slice($counts, 0, 12, true) as $name => $count) {
        $out[] = ['label' => $name, 'count' => $count];
    }
    return $out;
}

function handleLogout($data)
{
    $sessionId = $data['auth_session_id'] ?? '';
    if (isValidUuid($sessionId)) {
        supabaseRequest('PATCH', '/rest/v1/user_sessions', [
            'id' => 'eq.' . strtolower($sessionId),
        ], ['revoked_at' => nowIsoUtc()], ['Prefer: return=minimal']);
    } else {
        $rawToken = getRawSessionToken();
        if ($rawToken !== '') {
            supabaseRequest('PATCH', '/rest/v1/user_sessions', [
                'token_hash' => 'eq.' . hashSessionSecret($rawToken),
                'revoked_at' => 'is.null',
            ], ['revoked_at' => nowIsoUtc()], ['Prefer: return=minimal']);
        }
    }

    clearSessionCookies();
    jsonSuccess(["message" => "Signed out."]);
}

function handleSignOutOtherDevices($data)
{
    $userId = requireUuid($data['auth_user_id'] ?? '', 'user_id');
    $sessionId = requireUuid($data['auth_session_id'] ?? '', 'session_id');

    $res = supabaseRequest('PATCH', '/rest/v1/user_sessions', [
        'user_id' => 'eq.' . $userId,
        'id' => 'neq.' . $sessionId,
        'revoked_at' => 'is.null',
    ], [
        'revoked_at' => nowIsoUtc(),
        'admin_mode_until' => null,
    ], ['Prefer: return=representation']);

    if (($res['code'] ?? 500) >= 400) {
        sendSupabaseError("Failed to sign out other devices.", $res);
        return;
    }

    jsonSuccess([
        'message' => 'Other devices have been signed out.',
        'revoked_count' => count($res['data'] ?? []),
    ]);
}

function handlePhotoUpload()
{
    $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] = '00000000-0000-0000-0000-000000000000';
    $supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
    $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');
    $postMediaMaxBytes = 50 * 1024 * 1024;

    if (!isset($_FILES['photo'])) {
        $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;
        if ($contentLength > $postMediaMaxBytes) {
            http_response_code(413);
            echo json_encode([
                "status" => "error",
                "message" => "The upload is too large. Post media must be 50MB or smaller on the current Supabase plan and within your PHP post_max_size/upload_max_filesize settings."
            ]);
            return;
        }
        http_response_code(400);
        $postKeys = array_keys($_POST);
        $fileKeys = array_keys($_FILES);
        echo json_encode(["status" => "error", "message" => "No photo field in request.", "post_keys" => $postKeys, "file_keys" => $fileKeys]);
        return;
    }

    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini. Increase upload_max_filesize/post_max_size or choose a smaller file.',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload',
        ];
        $errCode = $_FILES['photo']['error'];
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => $uploadErrors[$errCode] ?? "Upload error code: {$errCode}"]);
        return;
    }

    $file = $_FILES['photo'];

    $bucketName = isset($_POST['bucket']) && trim($_POST['bucket']) !== ''
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['bucket'])
        : 'profile-photos';

    $allowedBuckets = ['profile-photos', 'cover-photos', 'post-media', 'gallery-media', 'profiles', 'posts', 'gallery'];
    if (!in_array($bucketName, $allowedBuckets, true)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid storage bucket."]);
        return;
    }

    $bucketMap = [
        'profile-photos' => 'profiles',
        'cover-photos'   => 'profiles',
        'post-media'     => 'posts',
        'gallery-media'  => 'gallery'
    ];
    $actualBucketName = $bucketMap[$bucketName] ?? $bucketName;

    $imageTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $postMediaTypes = array_merge($imageTypes, ['image/gif', 'video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'application/pdf']);

    $allowedTypes = $bucketName === 'post-media' ? $postMediaTypes : $imageTypes;
    $maxBytes = $bucketName === 'post-media' ? $postMediaMaxBytes : 2097152; // 50MB for post-media, 2MB for profile/cover

    // Do NOT trust the browser-supplied MIME. Detect the real type from the file bytes.
    $detectedType = $file['type'];
    if (class_exists('finfo') && is_uploaded_file($file['tmp_name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $sniffed = $finfo->file($file['tmp_name']);
        if ($sniffed) {
            $detectedType = $sniffed;
        }
    }

    if (!in_array($detectedType, $allowedTypes, true)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => $bucketName === 'post-media'
                ? "Only JPG, PNG, WebP, GIF, MP4, WebM, MOV, M4V, and PDF files are allowed."
                : "Only JPG, PNG, and WebP files are allowed."
        ]);
        return;
    }
    // From here on, use the detected (trusted) type for storage.
    $file['type'] = $detectedType;

    if ($file['size'] > $maxBytes) {
        http_response_code(413);
        echo json_encode([
            "status" => "error",
            "message" => $bucketName === 'post-media'
                ? "Post media must be 50MB or smaller on the current Supabase plan."
                : "File exceeds the " . ($maxBytes / 1048576) . "MB limit."
        ]);
        return;
    }

    $mimeExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'video/quicktime' => 'mov',
        'video/x-m4v' => 'm4v',
        'application/pdf' => 'pdf',
    ];
    $extension = $mimeExtensions[$file['type']] ?? 'bin';
    $prefix = isset($_POST['prefix']) && trim($_POST['prefix']) !== ''
        ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['prefix'])
        : ($bucketName === 'post-media' ? 'media' : 'profile');

    $filename = uniqid($prefix . '_') . '.' . $extension;
    $fileData = file_get_contents($file['tmp_name']);

    // Store files under the authenticated user's folder. Do not trust a
    // multipart form user_id because it is editable in the browser.
    $authUserId = $GLOBALS['PAWCIRCLE_AUTH_CONTEXT']['user_id'] ?? '';
    $userId = isValidUuid($authUserId) ? strtolower($authUserId) : null;

    $objectPath = $userId ? ($userId . '/' . $filename) : $filename;
    $uploadUrl = "{$supabaseUrl}/storage/v1/object/{$actualBucketName}/{$objectPath}";

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$secretKey}",
        "apikey: {$secretKey}",
        "Content-Type: {$file['type']}",
        "x-upsert: true",
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $fileData);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($httpCode === 200 || $httpCode === 201) {
        $publicUrl = "{$supabaseUrl}/storage/v1/object/public/{$actualBucketName}/{$objectPath}";
        echo json_encode([
            "status" => "success",
            "photo_url" => $publicUrl,
            "url" => $publicUrl,
            "bucket" => $bucketName,
            "path" => $objectPath,
            "mime_type" => $file['type'],
        ]);
    } else {
        $err = json_decode($response, true);
        $tooLarge = $httpCode === 413 || stripos((string) $response, 'too large') !== false || stripos((string) $response, 'size') !== false;
        error_log("[pawcircle] upload failed! httpCode=$httpCode, url=$uploadUrl, response=$response");
        http_response_code($tooLarge ? 413 : 500);
        echo json_encode([
            "status" => "error",
            "message" => $tooLarge
                ? "The upload is too large for Supabase Storage. Choose a file 50MB or smaller."
                : "Storage upload failed (HTTP {$httpCode}): " . ($err['message'] ?? $response),
            "curl_err" => $curlError,
            "url" => $uploadUrl,
        ]);
    }
}

function parsePublicStorageUrl($url)
{
    if (empty($url))
        return null;
    $marker = '/storage/v1/object/public/';
    $pos = strpos($url, $marker);
    if ($pos === false)
        return null;
    $sub = substr($url, $pos + strlen($marker));
    $parts = explode('/', $sub, 2);
    if (count($parts) < 2)
        return null;
    return ['bucket' => $parts[0], 'path' => $parts[1]];
}

function supabaseStorageDelete($bucket, $path)
{
    $supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
    $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');
    if (!$bucket || !$path)
        return false;
    $deleteUrl = "{$supabaseUrl}/storage/v1/object/{$bucket}/{$path}";
    $ch = curl_init($deleteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer {$secretKey}",
        "apikey: {$secretKey}",
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ($httpCode === 200 || $httpCode === 204);
}

function cleanNullableText($value, $maxLength = 500)
{
    if ($value === null || $value === '')
        return null;
    $clean = trim(strip_tags((string) $value));
    if ($clean === '')
        return null;
    return substr($clean, 0, $maxLength);
}

function cleanDateValue($value)
{
    $value = trim((string) ($value ?? ''));
    if ($value === '')
        return null;
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
}

function cleanTimeValue($value)
{
    $value = trim((string) ($value ?? ''));
    if ($value === '')
        return null;
    return preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value) ? substr($value, 0, 5) : null;
}

function cleanTextValue($value, $maxLength = 5000)
{
    $text = trim((string) ($value ?? ''));
    $text = strip_tags($text);
    $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    if ($maxLength > 0 && strlen($text) > $maxLength) {
        $text = substr($text, 0, $maxLength);
    }
    return $text;
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

function requireFields($data, $fields)
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            jsonError(implode(', ', $fields) . " required.", 400, ["missing" => $field]);
            return false;
        }
    }
    return true;
}

function normalizeUuidList($ids)
{
    $out = [];
    foreach ($ids as $id) {
        $id = trim((string) $id);
        if ($id !== '' && preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
            $out[] = strtolower($id);
        }
    }
    return array_values(array_unique($out));
}

function ageFromDateOfBirth($dob)
{
    if (empty($dob))
        return null;
    try {
        return (int) (new DateTimeImmutable((string) $dob))->diff(new DateTimeImmutable('today'))->y;
    } catch (Exception $e) {
        return null;
    }
}

function captureJsonHandler($callback)
{
    ob_start();

    $oldCode = http_response_code();

    try {
        $callback();
        $raw = ob_get_clean();
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            return [
                "status" => "error",
                "message" => "Handler did not return valid JSON",
                "raw" => $raw
            ];
        }

        return $decoded;
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        return [
            "status" => "error",
            "message" => $e->getMessage()
        ];
    } finally {
        http_response_code($oldCode ?: 200);
    }
}

function handleSocialBootstrap($data)
{
    $userId = $data['user_id'] ?? '';
    $breed = $data['breed'] ?? '';
    $pet_type = $data['pet_type'] ?? '';

    if (!$userId) {
        jsonError("user_id is required.", 400);
        return;
    }

    $postsData = captureJsonHandler(function () use ($userId, $breed, $pet_type) {
        handleGetPosts([
            "user_id" => $userId,
            "breed" => $breed,
            "pet_type" => $pet_type
        ]);
    });

    $friendsData = captureJsonHandler(function () use ($userId) {
        handleGetFriends([
            "user_id" => $userId
        ]);
    });

    $groupsData = captureJsonHandler(function () use ($userId, $breed, $pet_type) {
        handleGetGroups([
            "user_id" => $userId,
            "breed" => $breed,
            "pet_type" => $pet_type
        ]);
    });

    $eventsData = captureJsonHandler(function () use ($breed, $pet_type) {
        handleGetEvents([
            "breed" => $breed,
            "pet_type" => $pet_type
        ]);
    });

    jsonSuccess([
        "posts" => $postsData["posts"] ?? [],
        "friends" => $friendsData["friends"] ?? [],
        "requests" => $friendsData["requests"] ?? [],
        "groups" => $groupsData["groups"] ?? [],
        "events" => $eventsData["events"] ?? [],

        "debug" => [
            "posts_status" => $postsData["status"] ?? "unknown",
            "friends_status" => $friendsData["status"] ?? "unknown",
            "groups_status" => $groupsData["status"] ?? "unknown",
            "events_status" => $eventsData["status"] ?? "unknown"
        ]
    ]);
}

function countRowsByKey($rows, $key)
{
    $counts = [];
    foreach (($rows ?? []) as $row) {
        if (!empty($row[$key])) {
            $id = $row[$key];
            $counts[$id] = ($counts[$id] ?? 0) + 1;
        }
    }
    return $counts;
}

function normalizeVisibility($value)
{
    $value = strtolower(trim((string) $value));
    $allowed = ['public', 'breed', 'pet_type', 'private'];
    return in_array($value, $allowed, true) ? $value : 'public';
}

function normalizeOnlineStatus($value)
{
    $value = strtolower(trim((string) $value));
    $allowed = ['online', 'away', 'busy', 'offline'];
    return in_array($value, $allowed, true) ? $value : 'online';
}

function handleGetAccountSettings($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,role,created_at,deactivated_at,is_verified,verified_at,auth_user_id,password_hash',
        'limit' => '1',
    ]);

    if (supabaseFailed($userRes)) {
        $userRes = supabaseRequest('GET', '/rest/v1/users', [
            'id' => 'eq.' . $userId,
            'select' => 'id,email,role,created_at',
            'limit' => '1',
        ]);
    }

    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        sendSupabaseError("Failed to load account.", $userRes, 404);
        return;
    }

    $userRow = $userRes['data'][0];

    /* Check for a pending verification request */
    $verifPendingRes = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'user_id' => 'eq.' . $userId,
        'status' => 'eq.pending',
        'select' => 'id,status,created_at',
        'limit' => '1',
    ]);
    $verificationPending = !empty($verifPendingRes['data']);

    $profile = getAccountProfile($userId);
    $visibility = normalizeVisibility($profile['visibility'] ?? (!empty($profile['is_public']) ? 'public' : 'private'));

    /* Decode privacy_settings from profile JSONB */
    $rawPrivacy = $profile['privacy_settings'] ?? null;
    $privacySettings = [];
    if (is_string($rawPrivacy)) {
        $privacySettings = json_decode($rawPrivacy, true) ?? [];
    } elseif (is_array($rawPrivacy)) {
        $privacySettings = $rawPrivacy;
    }

    jsonSuccess([
        "account" => [
            "id" => $userRow['id'],
            "email" => $userRow['email'] ?? '',
            "role" => $userRow['role'] ?? 'member',
            "auth_user_id" => $userRow['auth_user_id'] ?? null,
            "has_password" => !empty($userRow['password_hash'] ?? ''),
            "password_login_enabled" => !empty($userRow['password_hash'] ?? ''),
            "third_party_sign_in_enabled" => !empty($userRow['auth_user_id'] ?? ''),
            "created_at" => $userRow['created_at'] ?? null,
            "deactivated_at" => $userRow['deactivated_at'] ?? null,
            "is_verified" => (bool) ($userRow['is_verified'] ?? false),
            "verified_at" => $userRow['verified_at'] ?? null,
            "verification_pending" => $verificationPending,
        ],
        "profile" => array_merge($profile, [
            "visibility" => $visibility,
            "online_status" => normalizeOnlineStatus($profile['online_status'] ?? 'online'),
            "social_links" => is_array($profile['social_links'] ?? null) ? $profile['social_links'] : [],
            "privacy_settings" => $privacySettings,
        ]),
    ]);
}

function handleUpdateAccountSettings($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $profileUpdate = [];
    $fieldMap = [
        'name' => 'full_name',
        'full_name' => 'full_name',
        'bio' => 'bio',
        'mobile_number' => 'mobile_number',
        'current_city' => 'current_city',
        'occupation' => 'occupation',
        'gender' => 'gender',
        'date_of_birth' => 'date_of_birth',
    ];

    foreach ($fieldMap as $inputKey => $profileKey) {
        if (array_key_exists($inputKey, $data)) {
            $profileUpdate[$profileKey] = cleanNullableText($data[$inputKey], $profileKey === 'bio' ? 800 : 240);
        }
    }

    if (array_key_exists('visibility', $data)) {
        $visibility = normalizeVisibility($data['visibility']);
        $profileUpdate['visibility'] = $visibility;
        $profileUpdate['is_public'] = $visibility !== 'private';
    }

    if (array_key_exists('online_status', $data)) {
        $profileUpdate['online_status'] = normalizeOnlineStatus($data['online_status']);
    }

    if (array_key_exists('social_links', $data) && is_array($data['social_links'])) {
        $links = [];
        foreach (['facebook', 'instagram', 'twitter'] as $key) {
            $links[$key] = cleanNullableText($data['social_links'][$key] ?? '', 300);
        }
        $profileUpdate['social_links'] = $links;
    }

    /* Privacy settings: hide_playdate, private_tree, whatsapp_notifications */
    if (array_key_exists('privacy_settings', $data) && is_array($data['privacy_settings'])) {
        $ps = $data['privacy_settings'];
        $profileUpdate['privacy_settings'] = json_encode([
            'hidePlaydate' => (bool) ($ps['hidePlaydate'] ?? false),
            'privateTree' => (bool) ($ps['privateTree'] ?? false),
            'whatsappNotifications' => (bool) ($ps['whatsappNotifications'] ?? $ps['whatsapp'] ?? false),
        ]);
    }

    if (empty($profileUpdate)) {
        jsonSuccess(["message" => "Nothing to update."]);
        return;
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
    ], $profileUpdate, ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to update account settings.", $res);
        return;
    }

    jsonSuccess(["profile" => $res['data'][0] ?? getAccountProfile($userId)]);
}

function handleChangeAccountCredentials($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $currentPassword = (string) ($data['current_password'] ?? '');
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,password_hash,auth_user_id',
        'limit' => '1',
    ]);

    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        sendSupabaseError("Account not found.", $userRes, 404);
        return;
    }

    $user = $userRes['data'][0];
    $hasPassword = !empty($user['password_hash'] ?? '');

    $userUpdate = [];
    $profileUpdate = [];

    $newEmail = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    if ($newEmail && $newEmail !== ($user['email'] ?? '')) {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            jsonError("Invalid email format.");
            return;
        }
        $dupe = supabaseRequest('GET', '/rest/v1/users', [
            'email' => 'eq.' . $newEmail,
            'id' => 'neq.' . $userId,
            'select' => 'id',
            'limit' => '1',
        ]);
        if (!empty($dupe['data'])) {
            jsonError("That email is already in use.", 409);
            return;
        }
        $userUpdate['email'] = $newEmail;
    }

    $newPassword = (string) ($data['new_password'] ?? '');

    if ($hasPassword) {
        if ($currentPassword === '') {
            jsonError("Current password is required.");
            return;
        }
        if (!password_verify($currentPassword, $user['password_hash'] ?? '')) {
            jsonError("Current password is incorrect.", 401);
            return;
        }
    } elseif (!empty($newEmail) && $newEmail !== ($user['email'] ?? '')) {
        jsonError("Set an PawCircle password before changing your email.");
        return;
    } elseif ($newPassword === '' && ($currentPassword !== '' || isset($data['current_password']))) {
        jsonError("This account does not have an PawCircle password yet.");
        return;
    }

    if ($newPassword !== '') {
        if (strlen($newPassword) < 10) {
            jsonError("Password must be at least 10 characters.");
            return;
        }
        $userUpdate['password_hash'] = password_hash($newPassword, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    if (isset($data['name'])) {
        $name = cleanNullableText($data['name'], 180);
        if (!$name) {
            jsonError("Name cannot be empty.");
            return;
        }
        $profileUpdate['full_name'] = $name;
    }

    if (!empty($userUpdate)) {
        $res = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], $userUpdate, ['Prefer: return=representation']);
        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to update account credentials.", $res);
            return;
        }
    }

    if (!empty($profileUpdate)) {
        $res = supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], $profileUpdate, ['Prefer: return=representation']);
        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to update account name.", $res);
            return;
        }
    }

    jsonSuccess(["message" => "Account credentials updated."]);
}

function handleChangePetTypeBreed($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $pet_type = cleanNullableText($data['pet_type'] ?? '', 80);
    $breed = cleanNullableText($data['breed'] ?? '', 140);
    if (!$userId || !$pet_type || !$breed) {
        jsonError("user_id, pet_type and breed are required.");
        return;
    }

    $oldProfile = getAccountProfile($userId);
    $membershipRes = supabaseRequest('GET', '/rest/v1/group_members', [
        'user_id' => 'eq.' . $userId,
        'select' => 'group_id',
    ]);
    $removedGroupIds = normalizeUuidList(array_column($membershipRes['data'] ?? [], 'group_id'));

    if (!empty($removedGroupIds)) {
        supabaseRequest('DELETE', '/rest/v1/group_members', [
            'user_id' => 'eq.' . $userId,
        ]);
    }

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
    ], [
        'pet_type' => $pet_type,
        'breed' => $breed,
    ], ['Prefer: return=representation']);

    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to change pet_type/breed.", $res);
        return;
    }

    jsonSuccess([
        "profile" => $res['data'][0] ?? getAccountProfile($userId),
        "removed_group_count" => count($removedGroupIds),
        "previous" => [
            "pet_type" => $oldProfile['pet_type'] ?? null,
            "breed" => $oldProfile['breed'] ?? null,
        ],
    ]);
}

function deleteStorageUrls($urls)
{
    foreach ($urls as $url) {
        $parsed = parsePublicStorageUrl($url);
        if ($parsed)
            supabaseStorageDelete($parsed['bucket'], $parsed['path']);
    }
}

function handleDeactivateAccount($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $password = (string) ($data['password'] ?? '');
    if (!$userId) {
        jsonError("user_id is required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,password_hash',
        'limit' => '1',
    ]);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("Account not found.", 404);
        return;
    }

    // Third-party (e.g. Google) accounts have no PawCircle password, so skip the
    // password check for them; password accounts must still confirm with it.
    $storedHash = (string) ($userRes['data'][0]['password_hash'] ?? '');
    
    $passwordCorrect = false;
    if ($storedHash !== '') {
        $passwordCorrect = password_verify($password, $storedHash);
    } else {
        $authRes = supabaseRequest('POST', '/auth/v1/token?grant_type=password', [], [
            'email' => $userRes['data'][0]['email'] ?? '',
            'password' => $password,
        ]);
        $passwordCorrect = (($authRes['code'] ?? 500) === 200);
    }

    if ($password === '' || !$passwordCorrect) {
        jsonError("Password is incorrect.", 401);
        return;
    }

    $now = gmdate('c');
    $userPatch = supabaseRequest('PATCH', '/rest/v1/users', ['id' => 'eq.' . $userId], ['deactivated_at' => $now], ['Prefer: return=representation']);
    if (supabaseFailed($userPatch)) {
        sendSupabaseError("Failed to deactivate account. Run the required SQL if deactivated_at does not exist.", $userPatch);
        return;
    }
    supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], ['online_status' => 'offline']);
    jsonSuccess(["message" => "Account deactivated.", "deactivated_at" => $now]);
}

function handleDeleteAccountPermanently($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    $password = (string) ($data['password'] ?? '');
    $confirm = strtoupper(trim((string) ($data['confirm'] ?? '')));
    if (!$userId || $confirm !== 'DELETE') {
        jsonError("user_id and confirm=DELETE are required.");
        return;
    }

    $userRes = supabaseRequest('GET', '/rest/v1/users', [
        'id' => 'eq.' . $userId,
        'select' => 'id,email,password_hash',
        'limit' => '1',
    ]);
    if (supabaseFailed($userRes) || empty($userRes['data'])) {
        jsonError("Account not found.", 404);
        return;
    }

    // Accounts with an PawCircle password must confirm with it. Accounts that only
    // signed up through a third-party provider (e.g. Google) have no password
    // to enter, so the typed DELETE confirmation is sufficient.
    $storedHash = (string) ($userRes['data'][0]['password_hash'] ?? '');
    
    $passwordCorrect = false;
    if ($storedHash !== '') {
        $passwordCorrect = password_verify($password, $storedHash);
    } else {
        $authRes = supabaseRequest('POST', '/auth/v1/token?grant_type=password', [], [
            'email' => $userRes['data'][0]['email'] ?? '',
            'password' => $password,
        ]);
        $passwordCorrect = (($authRes['code'] ?? 500) === 200);
    }

    if ($password === '' || !$passwordCorrect) {
        jsonError("Password is incorrect.", 401);
        return;
    }

    $profile = getAccountProfile($userId);
    $postsRes = supabaseRequest('GET', '/rest/v1/posts', [
        'user_id' => 'eq.' . $userId,
        'select' => 'id,media_url',
        'limit' => '1000',
    ]);
    $groupMessagesRes = supabaseRequest('GET', '/rest/v1/group_messages', [
        'sender_id' => 'eq.' . $userId,
        'select' => 'media_url',
        'limit' => '1000',
    ]);
    $directMessagesRes = supabaseRequest('GET', '/rest/v1/direct_messages', [
        'or' => '(sender_id.eq.' . $userId . ',recipient_id.eq.' . $userId . ')',
        'select' => 'media_url',
        'limit' => '1000',
    ]);
    $postMediaUrls = array_column($postsRes['data'] ?? [], 'media_url');
    $messageMediaUrls = array_merge(
        array_column($groupMessagesRes['data'] ?? [], 'media_url'),
        array_column($directMessagesRes['data'] ?? [], 'media_url')
    );
    deleteStorageUrls(array_filter(array_merge([
        $profile['profile_photo_url'] ?? null,
        $profile['cover_photo_url'] ?? null,
    ], $postMediaUrls, $messageMediaUrls)));

    supabaseRequest('DELETE', '/rest/v1/group_members', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/friendships', ['or' => '(requester.eq.' . $userId . ',addressee.eq.' . $userId . ')']);
    supabaseRequest('DELETE', '/rest/v1/notifications', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/post_likes', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/direct_messages', ['or' => '(sender_id.eq.' . $userId . ',recipient_id.eq.' . $userId . ')']);
    supabaseRequest('DELETE', '/rest/v1/group_messages', ['sender_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/call_participants', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/call_sessions', ['created_by' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/events', ['created_by' => 'eq.' . $userId]);
    supabaseRequest('PATCH', '/rest/v1/post_comments', ['user_id' => 'eq.' . $userId], ['is_deleted' => true]);
    supabaseRequest('PATCH', '/rest/v1/posts', ['user_id' => 'eq.' . $userId], ['is_deleted' => true, 'updated_at' => gmdate('c')]);
    supabaseRequest('DELETE', '/rest/v1/family_members', ['owner_user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/user_horoscope_profiles', ['user_id' => 'eq.' . $userId]);
    supabaseRequest('DELETE', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId]);

    $deleteUser = supabaseRequest('DELETE', '/rest/v1/users', ['id' => 'eq.' . $userId]);
    if (supabaseFailed($deleteUser)) {
        sendSupabaseError("Failed to delete user row after cleanup.", $deleteUser);
        return;
    }

    jsonSuccess(["message" => "Account permanently deleted."]);
}

function handleGetGalleries($data)
{
    $userId = cleanNullableText($data['user_id'] ?? '', 80);
    if (!$userId) {
        jsonError("user_id is required.", 400);
        return;
    }

    $query = [
        'owner_user_id' => 'eq.' . $userId,
        'select' => 'id,owner_user_id,event_id,title,description,visibility,created_at,updated_at',
        'order' => 'created_at.desc',
        'limit' => isset($data['limit']) ? (string) max(1, min((int) $data['limit'], 200)) : '100',
    ];

    if (!empty($data['gallery_id']))
        $query['id'] = 'eq.' . cleanPlainValue($data['gallery_id'], 80);
    if (!empty($data['event_id']))
        $query['event_id'] = 'eq.' . cleanPlainValue($data['event_id'], 80);
    if (!empty($data['independent']))
        $query['event_id'] = 'is.null';

    $res = supabaseRequest('GET', '/rest/v1/gallery_collections', $query);
    if (supabaseFailed($res)) {
        sendSupabaseError("Failed to fetch galleries.", $res);
        return;
    }

    jsonSuccess(["galleries" => attachGalleryItems($res['data'] ?? [])]);
}

function handleJoinPack($data)
{
    if (!requireFields($data, ['user_id', 'pack_key']))
        return;

    $userId = $data['user_id'];
    $mandalKey = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($data['pack_key'] ?? ''));
    if ($mandalKey === '') {
        jsonError("Invalid mandal.", 400);
        return;
    }

    $name = cleanTextValue($data['name'] ?? '', 120) ?: 'Mandal';
    $description = cleanTextValue($data['description'] ?? $data['desc'] ?? '', 1000) ?: null;

    // Find the existing shared mandal group by its stable key.
    $group = findMandalGroup($mandalKey);

    if (!$group) {
        $body = [
            'name' => $name,
            'description' => $description,
            'avatar_url' => null,
            'breed' => null,   // global scope: visible/joinable to everyone
            'pet_type' => null,
            'created_by' => $userId,
            'is_private' => false,
            'pack_key' => $mandalKey,
        ];
        $res = supabaseRequest('POST', '/rest/v1/groups', [], $body, ['Prefer: return=representation']);

        if (supabaseFailed($res) || empty($res['data'])) {
            // Likely a race where another request created it first — re-fetch by key.
            $group = findMandalGroup($mandalKey);
            if (!$group) {
                sendSupabaseError("Failed to join mandal.", $res);
                return;
            }
        } else {
            $group = $res['data'][0];
        }
    }

    // Add membership, treating an existing membership as success.
    $memberRes = supabaseRequest('POST', '/rest/v1/group_members', [], [
        'group_id' => $group['id'],
        'user_id' => $userId,
        'role' => 'member',
    ], ['Prefer: return=representation']);

    if (supabaseFailed($memberRes)) {
        $code = is_array($memberRes['data'] ?? null) ? ($memberRes['data']['code'] ?? '') : '';
        $msg = is_array($memberRes['data'] ?? null) ? ($memberRes['data']['message'] ?? '') : '';
        if ($code !== '23505' && stripos($msg, 'duplicate') === false) {
            sendSupabaseError("Failed to join mandal.", $memberRes);
            return;
        }
    }

    $group = enrichGroups([$group], $userId)[0] ?? $group;
    jsonSuccess(["group" => $group]);
}

function base64UrlEncodeRaw($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function uniqueUserIds($ids)
{
    $clean = [];

    foreach ((array) $ids as $id) {
        $id = trim((string) $id);
        if ($id !== '' && !in_array($id, $clean, true)) {
            $clean[] = $id;
        }
    }

    return $clean;
}

function resolveCallParticipants($data)
{
    $callerId = $data['user_id'] ?? null;
    $targetType = $data['target_type'] ?? null;

    if (!$callerId || !$targetType) {
        jsonError("user_id and target_type are required.");
        exit();
    }

    if ($targetType === 'group') {
        if (empty($data['group_id'])) {
            jsonError("group_id is required for group calls.");
            exit();
        }

        if (!str_starts_with($data['group_id'], 'event_group_') && !isGroupMember($callerId, $data['group_id'])) {
            jsonError("You are not a member of this group.", 403);
            exit();
        }

        $memberIds = str_starts_with($data['group_id'], 'event_group_') ? [] : getGroupMemberIds($data['group_id']);

        return uniqueUserIds(array_merge([$callerId], $memberIds));
    }

    $participantIds = uniqueUserIds($data['participant_ids'] ?? []);

    if (empty($participantIds)) {
        jsonError("participant_ids is required for direct or selected user calls.");
        exit();
    }

    foreach ($participantIds as $participantId) {
        if ($participantId === $callerId) {
            continue;
        }

        if (!areFriends($callerId, $participantId)) {
            jsonError("You can only call accepted friends.", 403, [
                "blocked_user_id" => $participantId
            ]);
            exit();
        }
    }

    return uniqueUserIds(array_merge([$callerId], $participantIds));
}

function insertCallParticipants($callId, $callerId, $participantIds)
{
    $rows = [];

    foreach ($participantIds as $uid) {
        $rows[] = [
            'call_id' => $callId,
            'user_id' => $uid,
            'role' => $uid === $callerId ? 'host' : 'participant',
            'status' => $uid === $callerId ? 'joined' : 'invited',
            'joined_at' => $uid === $callerId ? gmdate('c') : null
        ];
    }

    $res = supabaseRequest(
        'POST',
        '/rest/v1/call_participants',
        [],
        $rows,
        ['Prefer: return=representation']
    );

    if ($res['code'] >= 400) {
        jsonError("Failed to save call participants.", 500, [
            "supabase_response" => $res['data']
        ]);
        exit();
    }

    return $res['data'] ?? [];
}

function notifyCallParticipants($call, $callerId, $participantIds)
{
    $recipientIds = [];
    foreach (($participantIds ?? []) as $participantId) {
        if ($participantId && $participantId !== $callerId) {
            $recipientIds[] = $participantId;
        }
    }

    $recipientIds = normalizeUuidList($recipientIds);
    if (empty($recipientIds)) {
        return ["created" => 0, "attempted" => 0];
    }

    $profiles = fetchProfilesMap([$callerId]);
    $callerName = $profiles[$callerId]['full_name'] ?? 'A member';
    $callType = $call['call_type'] ?? 'voice';
    $typeLabel = $callType === 'video' ? 'video call' : 'voice call';
    $targetType = $call['target_type'] ?? null;
    $groupName = null;

    if ($targetType === 'group' && !empty($call['group_id'])) {
        $groupRes = supabaseRequest('GET', '/rest/v1/groups', [
            'id' => 'eq.' . $call['group_id'],
            'select' => 'name',
            'limit' => '1'
        ]);

        if (!supabaseFailed($groupRes) && !empty($groupRes['data'][0]['name'])) {
            $groupName = $groupRes['data'][0]['name'];
        }
    }

    $title = $targetType === 'group'
        ? 'Call from ' . ($groupName ?: 'your group')
        : $callerName . ' is calling';
    $body = $targetType === 'group'
        ? $callerName . ' started a ' . $typeLabel . ' in ' . ($groupName ?: 'your group') . '.'
        : $callerName . ' started a ' . $typeLabel . '.';
    $created = 0;

    foreach ($recipientIds as $recipientId) {
        $notification = createNotification(
            $recipientId,
            'call_invite',
            $title,
            $body,
            [
                'call_id' => $call['id'] ?? null,
                'caller_id' => $callerId,
                'call_type' => $callType,
                'target_type' => $targetType,
                'group_id' => $call['group_id'] ?? null,
                'group_name' => $groupName,
            ]
        );

        if (!supabaseFailed($notification)) {
            $created++;
        }
    }

    return ["created" => $created, "attempted" => count($recipientIds)];
}

function maybeEndCallIfNobodyJoined($callId)
{
    $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
        'id' => 'eq.' . $callId,
        'status' => 'in.(ringing,active)',
        'select' => 'id,zoom_meeting_id,status,ended_at',
        'limit' => '1'
    ]);

    if (empty($callRes['data'])) {
        return ["ended" => false];
    }

    $joinedRes = supabaseRequest('GET', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'status' => 'eq.joined',
        'select' => 'id',
        'limit' => '1'
    ]);

    if (!empty($joinedRes['data'])) {
        return ["ended" => false];
    }

    $endedAt = gmdate('c');
    $meetingId = $callRes['data'][0]['zoom_meeting_id'] ?? null;

    supabaseRequest('PATCH', '/rest/v1/call_sessions', ['id' => 'eq.' . $callId], [
        'status' => 'ended',
        'ended_at' => $endedAt
    ]);

    // Anyone who never joined becomes missed, while already-left/declined users keep their status.
    supabaseRequest('PATCH', '/rest/v1/call_participants', [
        'call_id' => 'eq.' . $callId,
        'status' => 'in.(invited,ringing)'
    ], [
        'status' => 'missed',
        'left_at' => $endedAt
    ]);

    zoomEndMeetingIfPossible($meetingId);

    return ["ended" => true, "ended_at" => $endedAt];
}

function computeMatchKundaliPHP($dob, $time, $gender)
{
    if (!$dob || trim($dob) === '')
        $dob = '2000-01-01';
    $dobParts = explode('-', $dob);
    $year = (int) ($dobParts[0] ?? 2000);
    $month = (int) ($dobParts[1] ?? 1);
    $day = (int) ($dobParts[2] ?? 1);

    $timeParts = explode(':', $time ?: '12:00');
    $hour = (int) ($timeParts[0] ?? 12);
    $minute = (int) ($timeParts[1] ?? 0);

    // Create DateTime for UTC
    try {
        $date = new DateTime(sprintf("%04d-%02d-%02d %02d:%02d:00", $year, $month, $day, $hour, $minute), new DateTimeZone('UTC'));
    } catch (Exception $e) {
        $date = new DateTime("2000-01-01 12:00:00", new DateTimeZone('UTC'));
    }
    // subtract 5 hours 30 mins to simulate the JS behavior (JS did hour - 5, minute - 30)
    $date->modify('-5 hours -30 minutes');

    $Y = (int) $date->format('Y');
    $M = (int) $date->format('n');
    $d = (int) $date->format('j');
    $h = (int) $date->format('G') + (int) $date->format('i') / 60;

    if ($M <= 2) {
        $Y--;
        $M += 12;
    }
    $A = floor($Y / 100);
    $B = 2 - $A + floor($A / 4);
    $JD = floor(365.25 * ($Y + 4716)) + floor(30.6001 * ($M + 1)) + $d + $h / 24 + $B - 1524.5;
    $dDays = $JD - 2451545.0;

    $gSun = 357.529 + 0.98560028 * $dDays;
    $qSun = 280.459 + 0.98564736 * $dDays;
    $lSun = $qSun + 1.915 * sin($gSun * pi() / 180) + 0.020 * sin(2 * $gSun * pi() / 180);

    $lMoonMean = 218.316 + 13.176396 * $dDays;
    $gMoon = 134.963 + 13.064993 * $dDays;
    $lMoon = $lMoonMean + 6.289 * sin($gMoon * pi() / 180);

    $ayanamsa = 23.85 + ($dDays / 365.25) * (50.29 / 3600);

    $siderealSun = fmod(fmod($lSun - $ayanamsa, 360) + 360, 360);
    $siderealMoon = fmod(fmod($lMoon - $ayanamsa, 360) + 360, 360);

    $nakshatras = ['Ashwini', 'Bharani', 'Krittika', 'Rohini', 'Mrigashira', 'Ardra', 'Punarvasu', 'Pushya', 'Ashlesha', 'Magha', 'Purva Phalguni', 'Uttara Phalguni', 'Hasta', 'Chitra', 'Swati', 'Vishakha', 'Anuradha', 'Jyeshtha', 'Mula', 'Purva Ashadha', 'Uttara Ashadha', 'Shravana', 'Dhanishta', 'Shatabhisha', 'Purva Bhadrapada', 'Uttara Bhadrapada', 'Revati'];
    $nakshatraIndex = floor($siderealMoon / 13.333333);
    $rasiIndex = floor($siderealMoon / 30);
    $rasis = ['Mesha', 'Vrishabha', 'Mithuna', 'Karka', 'Simha', 'Kanya', 'Tula', 'Vrischika', 'Dhanu', 'Makara', 'Kumbha', 'Meena'];
    $planets = ['Mars', 'Venus', 'Mercury', 'Moon', 'Sun', 'Mercury', 'Venus', 'Mars', 'Jupiter', 'Saturn', 'Saturn', 'Jupiter'];
    $yoniAnimals = ['Horse', 'Elephant', 'Sheep', 'Serpent', 'Dog', 'Cat', 'Rat', 'Cow', 'Buffalo', 'Tiger', 'Deer', 'Monkey', 'Mongoose', 'Lion'];

    $sunRasi = floor($siderealSun / 30);
    $hoursSinceSunrise = fmod($hour + $minute / 60 - 6 + 24, 24);
    $ascendantIndex = floor($sunRasi + $hoursSinceSunrise / 2) % 12;

    return [
        'nakshatra' => $nakshatras[$nakshatraIndex] ?? 'Ashwini',
        'nakshatraIndex' => $nakshatraIndex,
        'rasi' => $rasis[$rasiIndex] ?? 'Mesha',
        'rasiIndex' => $rasiIndex,
        'rasiLord' => $planets[$rasiIndex] ?? 'Mars',
        'ganam' => ['Deva', 'Manushya', 'Rakshasa'][$nakshatraIndex % 3],
        'yoni' => $yoniAnimals[$nakshatraIndex % 14],
        'vashya' => $rasiIndex,
        'nadi' => $nakshatraIndex % 3,
        'varna' => floor($rasiIndex / 3),
        'ascendant' => $rasis[$ascendantIndex],
        'gender' => $gender ?: 'Male',
    ];
}

function calculateGunaScorePHP($profileA, $profileB)
{
    $kA = computeMatchKundaliPHP($profileA['dob'] ?? '', $profileA['birthTime'] ?? '', $profileA['gender'] ?? '');
    $kB = computeMatchKundaliPHP($profileB['dob'] ?? '', $profileB['birthTime'] ?? '', $profileB['gender'] ?? '');
    if (!$kA || !$kB)
        return ['total' => 0];

    // 1. Varna (1 pt)
    $varnaScore = $kA['varna'] >= $kB['varna'] ? 1 : 0;

    // 2. Vashya (2 pts)
    $vashyaCompat = [[2, 0, 1, 1, 0, 0, 1, 0, 0, 1, 0, 1], [0, 2, 0, 1, 1, 0, 0, 1, 0, 0, 1, 0], [1, 0, 2, 0, 1, 1, 0, 0, 1, 0, 0, 1], [1, 1, 0, 2, 0, 1, 1, 0, 0, 1, 0, 0], [0, 1, 1, 0, 2, 0, 1, 1, 0, 0, 1, 0], [0, 0, 1, 1, 0, 2, 0, 1, 1, 0, 0, 1], [1, 0, 0, 1, 1, 0, 2, 0, 1, 1, 0, 0], [0, 1, 0, 0, 1, 1, 0, 2, 0, 1, 1, 0], [0, 0, 1, 0, 0, 1, 1, 0, 2, 0, 1, 1], [1, 0, 0, 1, 0, 0, 1, 1, 0, 2, 0, 1], [0, 1, 0, 0, 1, 0, 0, 1, 1, 0, 2, 0], [1, 0, 1, 0, 0, 1, 0, 0, 1, 1, 0, 2]];
    $vashyaScore = $vashyaCompat[$kA['rasiIndex']][$kB['rasiIndex']] ?? 0;

    // 3. Tara (3 pts)
    $taraDiff = ($kB['nakshatraIndex'] - $kA['nakshatraIndex'] + 27) % 9;
    $taraGood = [0, 1, 3, 5, 7];
    $taraScore = in_array($taraDiff, $taraGood) ? 3 : ($taraDiff % 2 === 0 ? 1.5 : 0);

    // 4. Yoni (4 pts)
    $yoniScore = 0;
    if ($kA['yoni'] === $kB['yoni']) {
        $yoniScore = 4;
    } else {
        $yEn = ['Horse', 'Elephant', 'Sheep', 'Serpent', 'Dog', 'Cat', 'Rat', 'Cow', 'Buffalo', 'Tiger', 'Deer', 'Monkey', 'Mongoose', 'Lion'];
        $yI = array_search($kA['yoni'], $yEn);
        $yJ = array_search($kB['yoni'], $yEn);
        $diff = abs($yI - $yJ);
        $yoniScore = $diff <= 3 ? 3 : ($diff <= 7 ? 2 : 1);
    }

    // 5. Graha Maitri (5 pts)
    $friendlyPairs = ['Mars' => ['Sun', 'Moon', 'Jupiter'], 'Venus' => ['Mercury', 'Saturn'], 'Mercury' => ['Sun', 'Venus'], 'Moon' => ['Sun', 'Mercury'], 'Sun' => ['Moon', 'Mars', 'Jupiter'], 'Jupiter' => ['Sun', 'Moon', 'Mars'], 'Saturn' => ['Mercury', 'Venus']];
    $lA = $kA['rasiLord'];
    $lB = $kB['rasiLord'];
    if ($lA === $lB) {
        $gmScore = 5;
    } elseif (in_array($lB, $friendlyPairs[$lA] ?? []) && in_array($lA, $friendlyPairs[$lB] ?? [])) {
        $gmScore = 5;
    } elseif (in_array($lB, $friendlyPairs[$lA] ?? []) || in_array($lA, $friendlyPairs[$lB] ?? [])) {
        $gmScore = 3;
    } else {
        $gmScore = 1;
    }

    // 6. Gana (6 pts)
    if ($kA['ganam'] === $kB['ganam']) {
        $ganaScore = 6;
    } elseif (($kA['ganam'] === 'Deva' && $kB['ganam'] === 'Manushya') || ($kA['ganam'] === 'Manushya' && $kB['ganam'] === 'Deva')) {
        $ganaScore = 5;
    } elseif (($kA['ganam'] === 'Manushya' && $kB['ganam'] === 'Rakshasa') || ($kA['ganam'] === 'Rakshasa' && $kB['ganam'] === 'Manushya')) {
        $ganaScore = 1;
    } else {
        $ganaScore = 0;
    }

    // 7. Bhakoot (7 pts)
    $rasiDiff = ($kB['rasiIndex'] - $kA['rasiIndex'] + 12) % 12;
    $badBhakoot = [5, 6, 7, 8, 11];
    $bhakootScore = in_array($rasiDiff, $badBhakoot) ? 0 : 7;

    // 8. Nadi (8 pts)
    $nadiScore = $kA['nadi'] !== $kB['nadi'] ? 8 : 0;

    return ['total' => $varnaScore + $vashyaScore + $taraScore + $yoniScore + $gmScore + $ganaScore + $bhakootScore + $nadiScore];
}

function handleSubmitAdvertisingEnquiry($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $message = substr(trim((string) ($data['message'] ?? '')), 0, 500);

    // Resolve the enquirer's display name for context.
    $enquirerName = 'A breed member';
    $pm = fetchProfilesMap([$userId]);
    if (!empty($pm[$userId]['full_name'])) {
        $enquirerName = $pm[$userId]['full_name'];
    }

    // Find every active owner (canonical 'owner' plus its raw aliases).
    $res = supabaseRequest('GET', '/rest/v1/admin_roles', [
        'revoked_at' => 'is.null',
        'role' => 'in.(owner,super_admin,supreme_overlord_admin)',
        'select' => 'user_id',
    ]);

    $ownerIds = [];
    foreach (($res['data'] ?? []) as $row) {
        $uid = strtolower((string) ($row['user_id'] ?? ''));
        if ($uid !== '') {
            $ownerIds[$uid] = true; // dedupe (a user may hold multiple owner rows)
        }
    }
    $ownerIds = array_keys($ownerIds);

    $title = 'New advertising enquiry';
    $body = $enquirerName . ' is interested in advertising on PawCircle.'
        . ($message !== '' ? ' Message: "' . $message . '"' : ' Reach out to discuss sponsorship options.');

    $sent = 0;
    foreach ($ownerIds as $oid) {
        $r = createNotification($oid, 'advertising_enquiry', $title, $body, [
            'from_user_id' => $userId,
            'from_name' => $enquirerName,
            'message' => $message,
        ]);
        if (($r['code'] ?? 500) < 300) {
            $sent++;
        }
    }

    jsonSuccess(['notified' => $sent]);
}

function handleSubmitVerificationRequest($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $idType = trim((string) ($data['id_type'] ?? ''));
    $idNumber = trim((string) ($data['id_number'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));

    $allowedIdTypes = ['aadhaar', 'pan', 'passport', 'voter', 'driving_licence'];
    if ($fullName === '') {
        jsonError('full_name is required.', 400);
        return;
    }
    if (!in_array(strtolower($idType), $allowedIdTypes, true)) {
        jsonError('Invalid id_type.', 400);
        return;
    }
    if ($idNumber === '') {
        jsonError('id_number is required.', 400);
        return;
    }

    // Check for an existing pending request
    $existing = supabaseRequest('GET', '/rest/v1/verification_requests', [
        'user_id' => 'eq.' . $userId,
        'status' => 'eq.pending',
        'select' => 'id',
    ]);
    if (!empty($existing['data'])) {
        jsonError('A verification request is already pending for this account.', 409);
        return;
    }

    $res = supabaseRequest('POST', '/rest/v1/verification_requests', [], [
        'user_id' => $userId,
        'full_name' => $fullName,
        'id_type' => strtolower($idType),
        'id_number' => $idNumber,
        'reason' => $reason,
        'status' => 'pending',
        'created_at' => nowIsoUtc(),
    ], ['Prefer: return=representation']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to submit verification request. Please try again later.', 502);
        return;
    }
    jsonSuccess(['submitted' => true, 'request' => $res['data'][0] ?? null]);
}

function handleSavePrivacySettings($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $ps = $data['privacy_settings'] ?? [];
    if (!is_array($ps)) {
        jsonError('privacy_settings must be an object.', 400);
        return;
    }

    // Preserve any server-only WhatsApp fields the client doesn't send back
    // (verified flag + the verified number) so toggling other settings can't wipe them.
    $existing = readPrivacySettings($userId);

    $payload = [
        'hidePlaydate' => (bool) ($ps['hidePlaydate'] ?? false),
        'privateTree' => (bool) ($ps['privateTree'] ?? false),
        'whatsappNotifications' => (bool) ($ps['whatsappNotifications'] ?? $ps['whatsapp'] ?? false),
        'whatsappNumber' => trim((string) ($ps['whatsappNumber'] ?? $existing['whatsappNumber'] ?? '')),
        'whatsappVerified' => (bool) ($ps['whatsappVerified'] ?? $existing['whatsappVerified'] ?? false),
        'hideOnlineStatus' => (bool) ($ps['hideOnlineStatus'] ?? false),
        'hidePhone' => (bool) ($ps['hidePhone'] ?? false),
        'hideEmail' => (bool) ($ps['hideEmail'] ?? false),
    ];

    $res = supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], [
        'privacy_settings' => json_encode($payload),
    ], ['Prefer: return=minimal']);

    if (($res['status'] ?? 500) >= 300) {
        jsonError('Failed to save privacy settings.', 502);
        return;
    }
    jsonSuccess(['saved' => true, 'privacy_settings' => $payload]);
}

function handleGetPrivacySettings($data)
{
    $userId = requireUuid($data['user_id'] ?? '', 'user_id');
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'privacy_settings',
    ]);
    if (($res['status'] ?? 500) >= 300 || empty($res['data'])) {
        jsonSuccess(['privacy_settings' => (object) []]);
        return;
    }
    $raw = $res['data'][0]['privacy_settings'] ?? '{}';
    $ps = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
    // Never expose the transient WhatsApp OTP material to the client.
    unset($ps['whatsappOtpHash'], $ps['whatsappOtpExpires'], $ps['whatsappOtpNumber']);
    jsonSuccess(['privacy_settings' => $ps]);
}

function readPrivacySettings($userId)
{
    $res = supabaseRequest('GET', '/rest/v1/profiles', [
        'user_id' => 'eq.' . $userId,
        'select' => 'privacy_settings',
        'limit' => '1',
    ]);
    if (($res['code'] ?? 500) >= 300 || empty($res['data'])) {
        return [];
    }
    $raw = $res['data'][0]['privacy_settings'] ?? '{}';
    return is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
}

function writePrivacySettings($userId, array $patch)
{
    $ps = array_merge(readPrivacySettings($userId), $patch);
    return supabaseRequest('PATCH', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId], [
        'privacy_settings' => json_encode($ps),
    ], ['Prefer: return=minimal']);
}

function emailEnabled()
{
    return trim((string) envValue('SENDPULSE_CLIENT_ID', '')) !== ''
        && trim((string) envValue('SENDPULSE_CLIENT_SECRET', '')) !== '';
}

function sendpulseAccessToken($clientId, $clientSecret)
{
    $ch = curl_init('https://api.sendpulse.com/oauth/access_token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode([
            'grant_type' => 'client_credentials',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]),
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);

    $body = json_decode($response, true);
    if ($curlErr || $httpCode >= 300 || empty($body['access_token'])) {
        $detail = is_array($body) ? json_encode($body) : substr((string) $response, 0, 500);
        error_log("[pawcircle][" . requestId() . "] [SendPulse auth ERROR] http=$httpCode err=$curlErr detail=$detail");
        return null;
    }
    return (string) $body['access_token'];
}