    <?php
    /**
     * eSamaj Secure Backend API - Supabase REST API Edition
     * Communicates entirely via the Supabase REST API using the Secret Key.
     * No direct database connection required.
     */

    // Show only fatal errors in output — warnings (e.g. curl_close deprecation)
    // must not bleed into JSON responses and corrupt them
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);

    define('ESAMAJ_BACKEND_BUILD', 'holybooks-db-file-proxy-v4-patched-from-16-2026-06-06');

    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }

    // Load .env using parse_ini_file (handles inline comments and quotes correctly)
    $envFile = __DIR__ . '/.env';
    if (file_exists($envFile)) {
        $parsed = parse_ini_file($envFile);
        if ($parsed) {
            foreach ($parsed as $key => $value) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }

    /**
     * Send a request to Supabase PostgREST REST API
     */
    function supabaseRequest($method, $path, $query = [], $body = null, $extraHeaders = []) {
        $url       = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
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

    // Read action
    // Do NOT read php://input for multipart requests (file uploads) —
    // it conflicts with PHP's internal $_FILES parsing and causes a fatal error.
    $isMultipart = isset($_SERVER['CONTENT_TYPE']) && 
                strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false;

    $inputData = $isMultipart ? [] : (json_decode(file_get_contents("php://input"), true) ?? []);
    $action    = $_GET['action'] ?? $inputData['action'] ?? '';

    if ($action === 'ping') {
        echo json_encode(["status" => "pong", "message" => "PHP Backend is active and connected to Supabase REST API.", "backend_build" => ESAMAJ_BACKEND_BUILD]);
        exit();
    }

    if ($action === 'check_tables') {
        $tables = ['users', 'profiles'];
        $results = [];
        foreach ($tables as $t) {
            $r = supabaseRequest('GET', '/rest/v1/' . $t, ['limit' => '1']);
            $results[$t] = $r['code'] === 200 ? 'OK' : 'ERROR (HTTP ' . $r['code'] . '): ' . json_encode($r['data']);
        }
        echo json_encode($results);
        exit();
    }

    switch ($action) {
        case 'public_signup':
        case 'signup':
            handleSignup($inputData);
            break;
        case 'public_login':
            handleLogin($inputData, 'member');
            break;
        case 'admin_login':
            handleAdminLogin($inputData);
            break;
        case 'get_stats':
            handleGetStats();
            break;
        case 'update_profile':
            handleUpdateProfile($inputData);
            break;
        case 'upload_photo':
            handlePhotoUpload();
            break;

        // Holy books / ebook library
        case 'get_holy_books':
            handleGetHolyBooks($inputData);
            break;
        case 'ebook_redirect':
            handleEbookRedirect($inputData);
            break;
        case 'ebook_file':
            handleEbookFile($inputData);
            break;

        case 'social_bootstrap':
            handleSocialBootstrap($inputData);
            break;

        // Posts
        case 'create_post':      handleCreatePost($inputData);      break;
        case 'get_posts':        handleGetPosts($inputData);        break;

        // Likes
        case 'toggle_like':      handleToggleLike($inputData);      break;

        // Comments
        case 'submit_comment':   handleSubmitComment($inputData);   break;
        case 'get_comments':     handleGetComments($inputData);     break;

        // Events
        case 'save_event':       handleSaveEvent($inputData);       break;
        case 'delete_event':     handleDeleteEvent($inputData);     break;
        case 'get_events':       handleGetEvents($inputData);       break;

        // Groups
        case 'create_group':          handleCreateGroup($inputData);       break;
        case 'join_group':            handleJoinGroup($inputData);         break;
        case 'send_group_message':    handleSendGroupMessage($inputData);  break;
        case 'get_group_messages':    handleGetGroupMessages($inputData);  break;
        case 'get_groups':            handleGetGroups($inputData);         break;

        // Friends
        case 'send_friend_request':   handleSendFriendRequest($inputData);    break;
        case 'respond_friend_request':handleRespondFriendRequest($inputData); break;
        case 'get_friends':           handleGetFriends($inputData);           break;

        // Zoom Calls
        case 'zoom_test':             handleZoomTest($inputData);           break;
        case 'zoom_start_call':       handleZoomStartCall($inputData);      break;
        case 'zoom_join_call':        handleZoomJoinCall($inputData);       break;
        case 'zoom_end_call':         handleZoomEndCall($inputData);        break;
        case 'zoom_get_active_calls': handleZoomGetActiveCalls($inputData); break;
        case 'zoom_get_group_calls':  handleZoomGetGroupCalls($inputData);  break;
        case 'zoom_mark_participant': handleZoomMarkParticipant($inputData);break;

        default:
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid endpoint request."]);
            break;
    }

    // ---------------------------------------------------------------------------
    // ZOOM TEST
    // ---------------------------------------------------------------------------
    function envValue($key, $default = '') {
        return getenv($key) ?: ($_ENV[$key] ?? $default);
    }

    function jsonSuccess($data = []) {
        echo json_encode(array_merge(["status" => "success"], $data));
    }

    function jsonError($message, $code = 400, $extra = []) {
        http_response_code($code);
        echo json_encode(array_merge([
            "status" => "error",
            "message" => $message
        ], $extra));
    }

    // ---------------------------------------------------------------------------
    // HOLY BOOKS / EBOOK LIBRARY
    // ---------------------------------------------------------------------------
    function slugifyHolyBook($value) {
        $slug = strtolower(trim((string)$value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    function stripTrackingQuery($url) {
        return preg_replace('/\?utm_source=chatgpt\.com$/', '', (string)$url);
    }

    function isTruthyDbValue($value, $default = false) {
        if ($value === null || $value === '') return $default;
        if (is_bool($value)) return $value;
        if (is_int($value)) return $value === 1;
        $normalised = strtolower(trim((string)$value));
        if (in_array($normalised, ['1', 'true', 't', 'yes', 'y', 'on'], true)) return true;
        if (in_array($normalised, ['0', 'false', 'f', 'no', 'n', 'off'], true)) return false;
        return $default;
    }

    function isAbsoluteHttpUrl($url) {
        return is_string($url) && preg_match('/^https?:\/\//i', trim($url));
    }

    function encodeStoragePath($path) {
        $path = trim((string)$path, '/');
        if ($path === '') return '';
        $parts = array_map('rawurlencode', explode('/', $path));
        return implode('/', $parts);
    }

    function buildPublicStorageUrl($bucketId, $objectPath, $intent = 'read', $filename = '') {
        $objectPath = trim((string)$objectPath);
        if ($objectPath === '') return '';

        // Allow emergency rows where a full URL is stored in pdf_path/epub_path.
        if (isAbsoluteHttpUrl($objectPath)) {
            return stripTrackingQuery($objectPath);
        }

        $supabaseUrl = rtrim(envValue('SUPABASE_URL'), '/');
        if ($supabaseUrl === '') return '';

        $bucketId = trim((string)($bucketId ?: 'holy-books'));
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

    function holyBookFilename($book, $format) {
        $slug = $book['slug'] ?? slugifyHolyBook(($book['tradition'] ?? '') . '-' . ($book['title'] ?? 'holy-book'));
        return $slug . '.' . $format;
    }

    function resolveHolyBookUrl($book, $format = 'pdf', $intent = 'read') {
        $format = strtolower((string)$format) === 'epub' ? 'epub' : 'pdf';

        $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
        $pathKey     = $format === 'epub' ? 'epub_path'        : 'pdf_path';

        $externalUrl = trim((string)($book[$externalKey] ?? ''));
        if ($externalUrl !== '') {
            // External URLs always win for their own format only. This lets Quran
            // use an external PDF while still using a bucket EPUB independently.
            return stripTrackingQuery($externalUrl);
        }

        $objectPath = trim((string)($book[$pathKey] ?? ''));
        if ($objectPath === '') return '';

        return buildPublicStorageUrl(
            $book['bucket_id'] ?? 'holy-books',
            $objectPath,
            $intent,
            holyBookFilename($book, $format)
        );
    }

    function holyBookSourceType($book, $format = 'pdf') {
        $format = strtolower((string)$format) === 'epub' ? 'epub' : 'pdf';
        $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
        $pathKey     = $format === 'epub' ? 'epub_path'        : 'pdf_path';

        if (!empty($book[$externalKey])) return 'external';
        if (!empty($book[$pathKey])) return 'bucket';
        return 'none';
    }

    function buildEbookRoute($bookId, $format = 'pdf', $intent = 'read', $mode = 'scroll') {
        return 'pawcircle_api.php?action=ebook_redirect'
            . '&book_id=' . rawurlencode($bookId)
            . '&format=' . rawurlencode($format)
            . '&intent=' . rawurlencode($intent)
            . '&mode=' . rawurlencode($mode);
    }

    function buildEbookFileRoute($bookId, $format = 'pdf', $intent = 'read', $mode = 'page') {
        return 'pawcircle_api.php?action=ebook_file'
            . '&book_id=' . rawurlencode($bookId)
            . '&format=' . rawurlencode($format)
            . '&intent=' . rawurlencode($intent)
            . '&mode=' . rawurlencode($mode);
    }

    function holyBookSectionMeta($religion) {
        $key = normalizeReligionKey($religion);
        $meta = [
            'Hindu' => [
                'title' => 'Dharmic Granth',
                'subtitle' => 'धार्मिक ग्रंथ',
                'type' => 'holy book',
            ],
            'Muslim' => [
                'title' => 'Islamic Texts',
                'subtitle' => 'النصوص الإسلامية',
                'type' => 'holy book',
            ],
            'Sikh' => [
                'title' => 'Sikh Scripture',
                'subtitle' => 'ਸਿੱਖ ਧਰਮ ਗ੍ਰੰਥ',
                'type' => 'holy book',
            ],
            'Christian' => [
                'title' => 'Christian Texts',
                'subtitle' => 'Holy Bible',
                'type' => 'holy book',
            ],
            'Jain' => [
                'title' => 'Jain Agamas',
                'subtitle' => 'જૈન આગમ',
                'type' => 'holy book',
            ],
            'Buddhist' => [
                'title' => 'Buddhist Texts',
                'subtitle' => 'बौद्ध ग्रंथ',
                'type' => 'holy book',
            ],
            'Parsi' => [
                'title' => 'Zoroastrian Texts',
                'subtitle' => 'Zend Avesta',
                'type' => 'holy book',
            ],
        ];
        return $meta[$key] ?? [
            'title' => $key ? ($key . ' Texts') : 'Holy Books',
            'subtitle' => 'Ebooks',
            'type' => 'holy book',
        ];
    }

    function stringContains($haystack, $needle) {
        return strpos((string)$haystack, (string)$needle) !== false;
    }

    function holyBookUiMeta($book) {
        $religion = normalizeReligionKey($book['tradition'] ?? '');
        $title = strtolower((string)($book['title'] ?? ''));

        $byReligion = [
            'Hindu' => ['icon' => 'book-open', 'bg' => 'bg-orange-50 text-orange-600 dark:bg-orange-900/30 dark:text-orange-400'],
            'Muslim' => ['icon' => 'book-open', 'bg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'],
            'Sikh' => ['icon' => 'book-open', 'bg' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'],
            'Christian' => ['icon' => 'book-open', 'bg' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400'],
            'Jain' => ['icon' => 'book-open', 'bg' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
            'Buddhist' => ['icon' => 'book-open', 'bg' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'],
            'Parsi' => ['icon' => 'book-open', 'bg' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
        ];

        $ui = $byReligion[$religion] ?? ['icon' => 'book-open', 'bg' => 'bg-amber-50 text-amber-700'];

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

    function normalizeReligionKey($religion) {
        $religion = trim((string)$religion);
        $aliases = [
            'Parsi / Zoroastrian' => 'Parsi',
            'Zoroastrian' => 'Parsi',
            'Zoroastrianism' => 'Parsi',
            'Muslim' => 'Muslim',
            'Islam' => 'Muslim',
            'Islamic' => 'Muslim',
            'Christianity' => 'Christian',
            'Buddhism' => 'Buddhist',
            'Jainism' => 'Jain',
            'Sikhism' => 'Sikh',
            'Hinduism' => 'Hindu',
        ];
        return $aliases[$religion] ?? ($religion ?: 'Hindu');
    }

    function getHolyBookRows() {
        $res = supabaseRequest('GET', '/rest/v1/holy_books', [
            'select' => 'id,slug,tradition,title,subtitle,description,language,source_label,source_url,bucket_id,pdf_path,epub_path,external_pdf_url,external_epub_url,default_read_mode,scroll_enabled,page_enabled,pdf_download_enabled,epub_download_enabled,sort_order,is_active',
            'is_active' => 'eq.true',
            'order' => 'tradition.asc,sort_order.asc,title.asc',
        ]);

        if (($res['code'] ?? 500) >= 400 || !is_array($res['data'])) {
            $message = is_array($res['data'])
                ? ($res['data']['message'] ?? json_encode($res['data']))
                : 'Could not read holy_books from Supabase.';
            return [
                'ok' => false,
                'message' => 'Failed to load holy books: ' . $message . ' (HTTP ' . ($res['code'] ?? 'unknown') . ')',
                'data' => [],
            ];
        }

        return ['ok' => true, 'message' => '', 'data' => $res['data']];
    }

    function normaliseHolyBookRowForFrontend($row) {
        $slug = trim((string)($row['slug'] ?? ''));
        if ($slug === '') {
            $slug = slugifyHolyBook(($row['tradition'] ?? '') . '-' . ($row['title'] ?? 'holy-book'));
        }

        $row['slug'] = $slug;
        $religion = normalizeReligionKey($row['tradition'] ?? '');
        $ui = holyBookUiMeta($row);

        $pdfUrl = resolveHolyBookUrl($row, 'pdf', 'read');
        $epubUrl = resolveHolyBookUrl($row, 'epub', 'download');

        $pdfAvailable = $pdfUrl !== '';
        $epubAvailable = $epubUrl !== '';

        $scrollEnabled = $pdfAvailable && isTruthyDbValue($row['scroll_enabled'] ?? true, true);
        $pageEnabled   = $pdfAvailable && isTruthyDbValue($row['page_enabled'] ?? true, true);
        $pdfDownloadEnabled  = $pdfAvailable && isTruthyDbValue($row['pdf_download_enabled'] ?? true, true);
        $epubDownloadEnabled = $epubAvailable && isTruthyDbValue($row['epub_download_enabled'] ?? false, false);

        $desc = trim((string)($row['subtitle'] ?? ''));
        if ($desc === '') $desc = trim((string)($row['description'] ?? ''));
        if ($desc === '') $desc = trim((string)($row['language'] ?? ''));
        if ($desc === '') $desc = 'Sacred text';

        $pdfSourceType = holyBookSourceType($row, 'pdf');
        $epubSourceType = holyBookSourceType($row, 'epub');

        $pdfReadAction = $pdfSourceType === 'bucket' ? 'file' : 'redirect';
        $epubDownloadAction = $epubSourceType === 'bucket' ? 'file' : 'redirect';
        $pdfDownloadAction = $pdfSourceType === 'bucket' ? 'file' : 'redirect';

        $pdfReadRoute = function ($mode) use ($slug, $pdfReadAction) {
            return $pdfReadAction === 'file'
                ? buildEbookFileRoute($slug, 'pdf', 'read', $mode)
                : buildEbookRoute($slug, 'pdf', 'read', $mode);
        };

        return [
            'id' => $slug,
            'slug' => $slug,
            'religion' => $religion,
            'title' => $row['title'] ?? 'Holy Book',
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

    function findHolyBookById($bookId) {
        $bookId = trim((string)$bookId);
        if ($bookId === '') return null;

        $rows = getHolyBookRows();
        if (!$rows['ok']) return null;

        $wanted = slugifyHolyBook($bookId);
        foreach ($rows['data'] as $row) {
            $candidates = array_filter([
                trim((string)($row['slug'] ?? '')),
                trim((string)($row['id'] ?? '')),
                slugifyHolyBook(($row['tradition'] ?? '') . '-' . ($row['title'] ?? '')),
                slugifyHolyBook($row['title'] ?? ''),
            ]);

            foreach ($candidates as $candidate) {
                if ($bookId === $candidate || $wanted === slugifyHolyBook($candidate)) {
                    return $row;
                }
            }
        }

        return null;
    }

    function handleGetHolyBooks($data) {
        $rows = getHolyBookRows();
        if (!$rows['ok']) {
            jsonError($rows['message'], 500);
            return;
        }

        $religionKey = normalizeReligionKey($data['religion'] ?? ($_GET['religion'] ?? 'Hindu'));
        $books = [];

        foreach ($rows['data'] as $row) {
            if (normalizeReligionKey($row['tradition'] ?? '') !== $religionKey) {
                continue;
            }
            $books[] = normaliseHolyBookRowForFrontend($row);
        }

        usort($books, function ($a, $b) {
            $orderA = intval($a['sort_order'] ?? 100);
            $orderB = intval($b['sort_order'] ?? 100);
            if ($orderA === $orderB) {
                return strcmp(strtolower($a['title'] ?? ''), strtolower($b['title'] ?? ''));
            }
            return $orderA <=> $orderB;
        });

        $section = holyBookSectionMeta($religionKey);
        $section['books'] = $books;

        jsonSuccess([
            'backend_build' => ESAMAJ_BACKEND_BUILD,
            'backend_source' => 'supabase_holy_books_table',
            'religion' => $religionKey,
            'section' => $section,
            'read_options' => [
                'default' => 'scroll',
                'options' => ['scroll', 'page'],
            ],
            'download_options' => ['pdf', 'epub'],
        ]);
    }

    function withPdfViewerFragment($url, $mode) {
        if (preg_match('/\.(pdf)(\?|$)/i', $url)) {
            $fragment = $mode === 'page' ? '#page=1&view=Fit&toolbar=1' : '#view=FitH&toolbar=1';
            return preg_replace('/#.*$/', '', $url) . $fragment;
        }
        return $url;
    }

    function holyBookRequestContext($data) {
        $format = strtolower($_GET['format'] ?? ($data['format'] ?? 'pdf'));
        $intent = strtolower($_GET['intent'] ?? ($data['intent'] ?? 'read'));
        $mode = strtolower($_GET['mode'] ?? ($data['mode'] ?? 'page'));

        if (!in_array($format, ['pdf', 'epub'], true)) {
            return ['ok' => false, 'message' => 'Unsupported ebook format.', 'code' => 400];
        }
        if (!in_array($intent, ['read', 'download'], true)) $intent = 'read';
        if (!in_array($mode, ['scroll', 'page'], true)) $mode = 'page';
        if ($format === 'epub' && $intent === 'read') $intent = 'download';

        $bookId = $_GET['book_id'] ?? ($data['book_id'] ?? '');
        $book = findHolyBookById($bookId);
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

        $url = resolveHolyBookUrl($book, $format, $intent);
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

    function validateRemoteEbookForProxy($url, $format) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'eSamaj Ebook Proxy');
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = strtolower((string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE));
        $err = curl_error($ch);
        curl_close($ch);

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

    function streamRemoteEbookFile($url, $format, $filename, $intent) {
        $validation = validateRemoteEbookForProxy($url, $format);
        if (!$validation['ok']) {
            jsonError($validation['message'], 502, ['url_hint' => preg_replace('/\?.*$/', '', $url)]);
            return;
        }

        ignore_user_abort(true);
        @set_time_limit(0);

        header_remove('Content-Type');
        http_response_code(200);
        header('X-eSamaj-Ebook-Proxy: ' . ESAMAJ_BACKEND_BUILD);
        header('Cache-Control: private, max-age=300');
        header('Content-Type: ' . ($format === 'epub' ? 'application/epub+zip' : 'application/pdf'));
        $disposition = $intent === 'download' ? 'attachment' : 'inline';
        header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($filename) . '"');
        header('X-Content-Type-Options: nosniff');

        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
        curl_setopt($ch, CURLOPT_USERAGENT, 'eSamaj Ebook Proxy');
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) {
            echo $chunk;
            flush();
            return strlen($chunk);
        });
        curl_exec($ch);
        curl_close($ch);
        exit();
    }

    function handleEbookFile($data) {
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

    function handleEbookRedirect($data) {
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



    // ---------------------------------------------------------------------------
    // SIGNUP
    // ---------------------------------------------------------------------------
    function resolveTaxonomyValues($data) {
        $community = $data['pet_community'] ?? $data['community'] ?? $data['breed_group'] ?? $data['interest_circle'] ?? null;
        $religion = $data['pet_type'] ?? $data['animal_type'] ?? $data['religion'] ?? null;

        return [
            'community' => $community,
            'religion' => $religion,
        ];
    }

    function appendPetAliases($row) {
        if (!is_array($row)) return $row;
        $row['pet_community'] = $row['community'] ?? null;
        $row['pet_type'] = $row['religion'] ?? null;
        return $row;
    }

    function handleSignup($data) {
        if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Name, email, and password are required."]);
            return;
        }

        $name      = htmlspecialchars(strip_tags($data['name']));
        $email     = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password  = $data['password'];
        $taxonomy  = resolveTaxonomyValues($data);
        $community = isset($taxonomy['community']) ? htmlspecialchars(strip_tags((string)$taxonomy['community'])) : 'Not Specified';
        $religion  = isset($taxonomy['religion'])  ? htmlspecialchars(strip_tags((string)$taxonomy['religion']))  : '';

        $interestsRaw = isset($data['interests']) ? htmlspecialchars(strip_tags($data['interests'])) : '';
        $skillsRaw    = isset($data['skills'])    ? htmlspecialchars(strip_tags($data['skills']))    : '';
        $ageGroup     = isset($data['age'])       ? htmlspecialchars(strip_tags($data['age']))       :
                    (isset($data['ageGroup'])  ? htmlspecialchars(strip_tags($data['ageGroup'])) : '');
        $phone        = isset($data['mobile_number']) ? htmlspecialchars(strip_tags($data['mobile_number'])) : '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid email format."]);
            return;
        }

        if (strlen($password) < 6) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Password must be at least 6 characters."]);
            return;
        }

        // Check for duplicate email
        $checkRes = supabaseRequest('GET', '/rest/v1/users', ['email' => 'eq.' . $email, 'select' => 'id']);
        if (!empty($checkRes['data'])) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "An account with this email already exists."]);
            return;
        }

        // Insert user
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        $userRes = supabaseRequest('POST', '/rest/v1/users', [], [
            'email'         => $email,
            'password_hash' => $passwordHash,
            'role'          => 'member',
        ], ['Prefer: return=representation']);

        if ($userRes['code'] >= 400 || empty($userRes['data'])) {
            http_response_code(500);
            $errDetail = is_array($userRes['data']) ? ($userRes['data']['message'] ?? json_encode($userRes['data'])) : 'No response from Supabase';
            echo json_encode(["status" => "error", "message" => "Failed to create user account: " . $errDetail . " (HTTP " . $userRes['code'] . ")"]);
            return;
        }

        $userId = $userRes['data'][0]['id'];

        $interestsArr = array_values(array_filter(array_map('trim', explode(',', $interestsRaw))));
        $skillsArr    = array_values(array_filter(array_map('trim', explode(',', $skillsRaw))));

        // Insert profile
        $profileRes = supabaseRequest('POST', '/rest/v1/profiles', [], [
            'user_id'           => $userId,
            'full_name'         => $name,
            'community'         => $community,
            'religion'          => $religion,
            'primary_interests' => empty($interestsArr) ? null : $interestsArr,
            'skills'            => empty($skillsArr)    ? null : $skillsArr,
            'age_group'         => $ageGroup,
            'mobile_number'     => $phone,
        ], ['Prefer: return=representation']);

        if ($profileRes['code'] >= 400) {
            // Roll back user row if profile insert fails
            supabaseRequest('DELETE', '/rest/v1/users', ['id' => 'eq.' . $userId]);
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to save profile. Please try again."]);
            return;
        }

        echo json_encode([
            "status"  => "success",
            "message" => "Account created successfully.",
            "user"    => appendPetAliases([
                "id"        => $userId,
                "name"      => $name,
                "email"     => $email,
                "role"      => "member",
                "community" => $community,
                "religion"  => $religion,
                "personalization" => [
                    "interests" => implode(', ', $interestsArr),
                    "skills"    => implode(', ', $skillsArr),
                    "ageGroup"  => $ageGroup,
                ],
            ]),
        ]);
    }

    // ---------------------------------------------------------------------------
    // MEMBER LOGIN
    // ---------------------------------------------------------------------------
    function handleLogin($data, $expectedRole) {
        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Credentials cannot be empty."]);
            return;
        }

        $email    = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];

        $res = supabaseRequest('GET', '/rest/v1/users', [
            'email'  => 'eq.' . $email,
            'role'   => 'eq.' . $expectedRole,
            'select' => 'id,email,role,password_hash,profiles(full_name,community,religion,primary_interests,skills,age_group,membership_applied,status,profile_photo_url,cover_photo_url,mobile_number)',
        ]);

        if ($res['code'] === 200 && !empty($res['data'])) {
            $user = $res['data'][0];

            if (!password_verify($password, $user['password_hash'])) {
                http_response_code(401);
                echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
                return;
            }

            // PostgREST returns 1-to-1 embed as object {}; normalise both shapes
            $raw = $user['profiles'] ?? null;
            if (is_array($raw) && isset($raw[0])) {
                $profile = $raw[0];
            } elseif (is_array($raw) && !empty($raw) && !isset($raw[0])) {
                $profile = $raw;
            } else {
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Account profile is incomplete. Please contact support."]);
                return;
            }

            $interests = (isset($profile['primary_interests']) && is_array($profile['primary_interests']))
                ? implode(', ', $profile['primary_interests']) : '';
            $skills    = (isset($profile['skills']) && is_array($profile['skills']))
                ? implode(', ', $profile['skills']) : '';

            echo json_encode([
                "status"  => "success",
                "message" => "Authentication successful.",
                "user"    => appendPetAliases([
                    "id"                => $user['id'],
                    "name"              => $profile['full_name']       ?? 'Member',
                    "email"             => $user['email'],
                    "role"              => $user['role'],
                    "community"         => $profile['community']       ?? 'Not Specified',
                    "religion"          => $profile['religion']        ?? '',
                    "membership_applied"=> $profile['membership_applied'] ?? false,
                    "membership_status" => $profile['status']          ?? 'none',
                    "profile_photo_url" => $profile['profile_photo_url'] ?? null,
                    "cover_photo_url"   => $profile['cover_photo_url']   ?? null,
                    "mobile_number"     => $profile['mobile_number']   ?? null,
                    "socialProfile"     => [
                        "name"          => $profile['full_name']       ?? 'Member',
                        "community"     => $profile['community']       ?? 'Not Specified',
                        "religion"      => $profile['religion']        ?? '',
                        "pet_community" => $profile['community']       ?? 'Not Specified',
                        "pet_type"      => $profile['religion']        ?? '',
                        "age"           => $profile['age_group']       ?? '',
                        "contactNo"     => $profile['mobile_number']   ?? null,
                        "shareContact"  => true,
                    ],
                    "personalization"   => [
                        "interests" => $interests,
                        "skills"    => $skills,
                        "ageGroup"  => $profile['age_group'] ?? '',
                    ],
                ]),
            ]);
            return;
        }

        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
    }

    // ---------------------------------------------------------------------------
    // ADMIN LOGIN — returns user + stats in one response
    // ---------------------------------------------------------------------------
    function handleAdminLogin($data) {
        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Credentials cannot be empty."]);
            return;
        }

        $email    = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];

        $res = supabaseRequest('GET', '/rest/v1/users', [
            'email'  => 'eq.' . $email,
            'role'   => 'eq.admin',
            'select' => 'id,email,role,password_hash',
        ]);

        if ($res['code'] !== 200 || empty($res['data'])) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid admin credentials."]);
            return;
        }

        $user = $res['data'][0];

        if (!password_verify($password, $user['password_hash'])) {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid admin credentials."]);
            return;
        }

        $statsData = fetchStats();

        echo json_encode([
            "status"  => "success",
            "message" => "Admin authentication successful.",
            "user"    => [
                "id"    => $user['id'],
                "email" => $user['email'],
                "role"  => $user['role'],
            ],
            "stats"   => $statsData,
        ]);
    }

    // ---------------------------------------------------------------------------
    // STATS (standalone endpoint + shared helper)
    // Uses HEAD + Prefer: count=exact to avoid fetching all rows
    // ---------------------------------------------------------------------------
    function handleGetStats() {
        echo json_encode(["status" => "success", "stats" => fetchStats()]);
    }

    function fetchStats() {
        $url       = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
        $secretKey = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');

        // Get total member count from Content-Range header (no row data transferred)
        $ch = curl_init($url . '/rest/v1/users?role=eq.member&select=id');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: {$secretKey}",
            "Authorization: Bearer {$secretKey}",
            "Prefer: count=exact",
        ]);
        $headerStr = curl_exec($ch);

        $totalUsers = 0;
        if (preg_match('/Content-Range:\s*\d+-\d+\/(\d+)/i', $headerStr, $m)) {
            $totalUsers = (int) $m[1];
        }

        // Community distribution (member profiles only via inner join)
        $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
            'select'      => 'community,users!inner(role)',
            'users.role'  => 'eq.member',
        ]);

        $commCount = [];
        foreach (($profilesRes['data'] ?? []) as $p) {
            $c = $p['community'] ?? 'Not Specified';
            $commCount[$c] = ($commCount[$c] ?? 0) + 1;
        }

        $communities = [];
        foreach ($commCount as $name => $count) {
            $communities[] = ['community' => $name, 'count' => $count];
        }
        usort($communities, fn($a, $b) => $b['count'] - $a['count']);

        return ['totalUsers' => $totalUsers, 'communities' => $communities];
    }

    // ---------------------------------------------------------------------------
    // PHOTO UPLOAD
    // Uses PUT (not POST) — Supabase Storage REST requires PUT for uploads
    // ---------------------------------------------------------------------------
    function handlePhotoUpload() {
        $supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
        $secretKey   = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');

        if (!isset($_FILES['photo'])) {
            http_response_code(400);
            $postKeys = array_keys($_POST);
            $fileKeys = array_keys($_FILES);
            echo json_encode(["status" => "error", "message" => "No photo field in request.", "post_keys" => $postKeys, "file_keys" => $fileKeys]);
            return;
        }

        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize in php.ini',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form',
                UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload',
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

        $allowedBuckets = ['profile-photos', 'cover-photos', 'post-media'];
        if (!in_array($bucketName, $allowedBuckets, true)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Invalid storage bucket."]);
            return;
        }

        $imageTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $postMediaTypes = array_merge($imageTypes, ['image/gif', 'video/mp4', 'video/webm', 'application/pdf']);

        $allowedTypes = $bucketName === 'post-media' ? $postMediaTypes : $imageTypes;
        $maxBytes = $bucketName === 'post-media' ? 10485760 : 2097152; // 10MB for post-media, 2MB for profile/cover

        if (!in_array($file['type'], $allowedTypes, true)) {
            http_response_code(400);
            echo json_encode([
                "status" => "error",
                "message" => $bucketName === 'post-media'
                    ? "Only JPG, PNG, WebP, GIF, MP4, WebM, and PDF files are allowed."
                    : "Only JPG, PNG, and WebP files are allowed."
            ]);
            return;
        }

        if ($file['size'] > $maxBytes) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "File exceeds the " . ($maxBytes / 1048576) . "MB limit."]);
            return;
        }

        $extension  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $prefix = isset($_POST['prefix']) && trim($_POST['prefix']) !== ''
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['prefix'])
            : ($bucketName === 'post-media' ? 'media' : 'profile');

        $filename   = uniqid($prefix . '_') . '.' . $extension;
        $fileData   = file_get_contents($file['tmp_name']);

        // Optional user_id to store files under a user-specific folder
        $userId = isset($_POST['user_id']) && trim($_POST['user_id']) !== ''
            ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['user_id'])
            : null;

        $objectPath = $userId ? ($userId . '/' . $filename) : $filename;
        $uploadUrl = "{$supabaseUrl}/storage/v1/object/{$bucketName}/{$objectPath}";

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
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($httpCode === 200 || $httpCode === 201) {
            $publicUrl = "{$supabaseUrl}/storage/v1/object/public/{$bucketName}/{$objectPath}";
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
            http_response_code(500);
            echo json_encode([
                "status"   => "error",
                "message"  => "Storage upload failed (HTTP {$httpCode}): " . ($err['message'] ?? $response),
                "curl_err" => $curlError,
                "url"      => $uploadUrl,
            ]);
        }
    }

    // Helper: parse a Supabase public storage URL into bucket/path
    function parsePublicStorageUrl($url) {
        if (empty($url)) return null;
        $marker = '/storage/v1/object/public/';
        $pos = strpos($url, $marker);
        if ($pos === false) return null;
        $sub = substr($url, $pos + strlen($marker));
        $parts = explode('/', $sub, 2);
        if (count($parts) < 2) return null;
        return ['bucket' => $parts[0], 'path' => $parts[1]];
    }

    // Helper: delete an object from Supabase Storage
    function supabaseStorageDelete($bucket, $path) {
        $supabaseUrl = rtrim(getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''), '/');
        $secretKey   = getenv('SUPABASE_SECRET_KEY') ?: ($_ENV['SUPABASE_SECRET_KEY'] ?? '');
        if (!$bucket || !$path) return false;
        $deleteUrl = "{$supabaseUrl}/storage/v1/object/{$bucket}/{$path}";
        $ch = curl_init($deleteUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$secretKey}",
            "apikey: {$secretKey}",
        ]);
        $response = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return ($httpCode === 200 || $httpCode === 204);
    }

    // ---------------------------------------------------------------------------
    // UPDATE PROFILE — called by submitMembership() and saveProfile()
    // Updates the profiles row for the given user_id
    // ---------------------------------------------------------------------------
    function handleUpdateProfile($data) {
        if (empty($data['user_id'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "user_id is required."]);
            return;
        }

        $userId = $data['user_id'];

        // Build update payload from whatever fields are present
        $allowed = [
            'full_name', 'date_of_birth', 'gender', 'mobile_number',
            'religion', 'community', 'mother_tongue', 'gotra',
            'native_village', 'current_city', 'occupation',
            'highest_education', 'organization', 'linkedin_url',
            'age_group', 'source', 'profile_photo_url', 'cover_photo_url',
            'membership_applied', 'terms_accepted', 'privacy_accepted',
            'accuracy_certified', 'is_public', 'bio',
        ];

        $update = [];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $update[$field] = is_string($data[$field])
                    ? htmlspecialchars(strip_tags($data[$field]))
                    : $data[$field];
            }
        }

        // Fetch existing profile values so we can cleanup old storage objects
        $oldProfilePhoto = null;
        $oldCoverPhoto = null;
        $existing = supabaseRequest('GET', '/rest/v1/profiles', ['user_id' => 'eq.' . $userId, 'select' => 'profile_photo_url,cover_photo_url']);
        if (isset($existing['code']) && $existing['code'] === 200 && !empty($existing['data'])) {
            $ex = $existing['data'][0];
            $oldProfilePhoto = $ex['profile_photo_url'] ?? null;
            $oldCoverPhoto = $ex['cover_photo_url'] ?? null;
        }

        // Array fields (text[])
        foreach (['skills', 'primary_interests'] as $arr) {
            if (isset($data[$arr])) {
                $update[$arr] = is_array($data[$arr]) ? $data[$arr]
                    : array_values(array_filter(array_map('trim', explode(',', $data[$arr]))));
            }
        }

        if (empty($update)) {
            echo json_encode(["status" => "success", "message" => "Nothing to update."]);
            return;
        }

        $res = supabaseRequest(
            'PATCH',
            '/rest/v1/profiles',
            ['user_id' => 'eq.' . $userId],
            $update,
            ['Prefer: return=representation']
        );

        if ($res['code'] >= 400) {
            $msg = is_array($res['data']) ? ($res['data']['message'] ?? json_encode($res['data'])) : 'Unknown error';
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Profile update failed: " . $msg]);
            return;
        }

        echo json_encode(["status" => "success", "message" => "Profile updated."]);

        // Remove previous profile/cover files from storage if they were replaced
        try {
            if (isset($update['profile_photo_url']) && $oldProfilePhoto && $oldProfilePhoto !== $update['profile_photo_url']) {
                $parsed = parsePublicStorageUrl($oldProfilePhoto);
                if ($parsed) supabaseStorageDelete($parsed['bucket'], $parsed['path']);
            }
            if (isset($update['cover_photo_url']) && $oldCoverPhoto && $oldCoverPhoto !== $update['cover_photo_url']) {
                $parsed = parsePublicStorageUrl($oldCoverPhoto);
                if ($parsed) supabaseStorageDelete($parsed['bucket'], $parsed['path']);
            }
        } catch (Exception $e) {
            // ignore deletion errors
        }
        return;
    }

    // ============================================================
    // POSTS
    // ============================================================

    // ============================================================
    // SOCIAL DATA HELPERS
    // ============================================================

    function cleanTextValue($value, $maxLength = 5000) {
        $text = trim((string)($value ?? ''));
        $text = strip_tags($text);
        $text = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        if ($maxLength > 0 && strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
        }
        return $text;
    }

    function requireFields($data, $fields) {
        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                jsonError(implode(', ', $fields) . " required.", 400, ["missing" => $field]);
                return false;
            }
        }
        return true;
    }

    function supabaseFailed($res) {
        return !is_array($res) || ($res['code'] ?? 500) >= 400;
    }

    function sendSupabaseError($message, $res, $code = 500, $extra = []) {
        jsonError($message, $code, array_merge($extra, [
            "supabase_http_code" => $res['code'] ?? null,
            "supabase_response" => $res['data'] ?? null,
        ]));
    }

    function normalizeUuidList($ids) {
        $out = [];
        foreach ($ids as $id) {
            $id = trim((string)$id);
            if ($id !== '' && preg_match('/^[0-9a-fA-F-]{36}$/', $id)) {
                $out[] = strtolower($id);
            }
        }
        return array_values(array_unique($out));
    }

    function fetchProfilesMap($userIds) {
        $userIds = normalizeUuidList($userIds);
        if (empty($userIds)) return [];

        $res = supabaseRequest('GET', '/rest/v1/profiles', [
            'user_id' => 'in.(' . implode(',', $userIds) . ')',
            'select'  => 'user_id,full_name,profile_photo_url,community,religion,current_city,mobile_number'
        ]);

        if (supabaseFailed($res)) return [];

        $map = [];
        foreach (($res['data'] ?? []) as $profile) {
            if (!empty($profile['user_id'])) {
                $map[$profile['user_id']] = $profile;
            }
        }
        return $map;
    }

    function captureJsonHandler($callback) {
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

    // SOCIAL BOOTSTRAP
    function handleSocialBootstrap($data) {
        $userId = $data['user_id'] ?? '';
        $taxonomy = resolveTaxonomyValues($data);
        $community = $taxonomy['community'] ?? '';
        $religion = $taxonomy['religion'] ?? '';

        if (!$userId) {
            jsonError("user_id is required.", 400);
            return;
        }

        $postsData = captureJsonHandler(function () use ($userId, $community, $religion) {
            handleGetPosts([
                "user_id" => $userId,
                "community" => $community,
                "religion" => $religion
            ]);
        });

        $friendsData = captureJsonHandler(function () use ($userId) {
            handleGetFriends([
                "user_id" => $userId
            ]);
        });

        $groupsData = captureJsonHandler(function () use ($userId, $community, $religion) {
            handleGetGroups([
                "user_id" => $userId,
                "community" => $community,
                "religion" => $religion
            ]);
        });

        $eventsData = captureJsonHandler(function () use ($community, $religion) {
            handleGetEvents([
                "community" => $community,
                "religion" => $religion
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

    function profileSummary($profile, $fallbackName = 'Member') {
        $name = $profile['full_name'] ?? $fallbackName;
        return [
            'full_name' => $name,
            'name' => $name,
            'profile_photo_url' => $profile['profile_photo_url'] ?? null,
            'community' => $profile['community'] ?? null,
            'religion' => $profile['religion'] ?? null,
            'current_city' => $profile['current_city'] ?? null,
            'mobile_number' => $profile['mobile_number'] ?? null,
        ];
    }

    function countRowsByKey($rows, $key) {
        $counts = [];
        foreach (($rows ?? []) as $row) {
            if (!empty($row[$key])) {
                $id = $row[$key];
                $counts[$id] = ($counts[$id] ?? 0) + 1;
            }
        }
        return $counts;
    }

    function getPostLikeRows($postIds) {
        $postIds = normalizeUuidList($postIds);
        if (empty($postIds)) return [];

        $res = supabaseRequest('GET', '/rest/v1/post_likes', [
            'post_id' => 'in.(' . implode(',', $postIds) . ')',
            'select' => 'post_id,user_id,created_at'
        ]);

        return supabaseFailed($res) ? [] : ($res['data'] ?? []);
    }

    function getPostCommentRows($postIds) {
        $postIds = normalizeUuidList($postIds);
        if (empty($postIds)) return [];

        $res = supabaseRequest('GET', '/rest/v1/post_comments', [
            'post_id'    => 'in.(' . implode(',', $postIds) . ')',
            'is_deleted' => 'eq.false',
            'select'     => 'post_id'
        ]);

        return supabaseFailed($res) ? [] : ($res['data'] ?? []);
    }

    function enrichPosts($posts, $currentUserId = null) {
        $posts = $posts ?? [];
        if (empty($posts)) return [];

        $userIds = [];
        $postIds = [];

        foreach ($posts as $post) {
            if (!empty($post['user_id'])) $userIds[] = $post['user_id'];
            if (!empty($post['id'])) $postIds[] = $post['id'];
        }

        $profileMap = fetchProfilesMap($userIds);
        $likeRows = getPostLikeRows($postIds);
        $commentRows = getPostCommentRows($postIds);

        $likeCounts = countRowsByKey($likeRows, 'post_id');
        $commentCounts = countRowsByKey($commentRows, 'post_id');

        $likedByCurrent = [];
        if ($currentUserId) {
            foreach ($likeRows as $like) {
                if (($like['user_id'] ?? null) === $currentUserId) {
                    $likedByCurrent[$like['post_id']] = true;
                }
            }
        }

        foreach ($posts as &$post) {
            $profile = $profileMap[$post['user_id'] ?? ''] ?? [];
            $summary = profileSummary($profile);

            $post['profiles'] = $summary;
            $post['author'] = $summary['full_name'];
            $post['profile_photo_url'] = $summary['profile_photo_url'];
            $post['like_count'] = $likeCounts[$post['id']] ?? 0;
            $post['comment_count'] = $commentCounts[$post['id']] ?? 0;
            $post['is_liked'] = !empty($likedByCurrent[$post['id']]);

            // Frontend compatibility keys
            $post['likes'] = $post['like_count'];
            $post['comments'] = $post['comment_count'];
            $post['isLiked'] = $post['is_liked'];
        }
        unset($post);

        return $posts;
    }

    function enrichComments($comments) {
        $comments = $comments ?? [];
        if (empty($comments)) return [];

        $userIds = [];
        foreach ($comments as $comment) {
            if (!empty($comment['user_id'])) $userIds[] = $comment['user_id'];
        }

        $profileMap = fetchProfilesMap($userIds);

        foreach ($comments as &$comment) {
            $profile = $profileMap[$comment['user_id'] ?? ''] ?? [];
            $summary = profileSummary($profile);

            $comment['profiles'] = $summary;
            $comment['author'] = $summary['full_name'];
            $comment['profile_photo_url'] = $summary['profile_photo_url'];
        }
        unset($comment);

        return $comments;
    }

    function enrichGroupMessages($messages) {
        $messages = $messages ?? [];
        if (empty($messages)) return [];

        $userIds = [];
        foreach ($messages as $msg) {
            if (!empty($msg['sender_id'])) $userIds[] = $msg['sender_id'];
        }

        $profileMap = fetchProfilesMap($userIds);

        foreach ($messages as &$msg) {
            $profile = $profileMap[$msg['sender_id'] ?? ''] ?? [];
            $summary = profileSummary($profile);

            $msg['profiles'] = $summary;
            $msg['sender_name'] = $summary['full_name'];
            $msg['sender_photo'] = $summary['profile_photo_url'];
            $msg['text'] = $msg['content'] ?? '';
            $msg['media'] = $msg['media_url'] ?? null;
        }
        unset($msg);

        return $messages;
    }

    function getMemberRowsForGroups($groupIds) {
        $groupIds = normalizeUuidList($groupIds);
        if (empty($groupIds)) return [];

        $res = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'in.(' . implode(',', $groupIds) . ')',
            'select'   => 'group_id,user_id,role,joined_at'
        ]);

        return supabaseFailed($res) ? [] : ($res['data'] ?? []);
    }

    function enrichGroups($groups, $currentUserId = null, $includeMembers = true) {
        $groups = $groups ?? [];
        if (empty($groups)) return [];

        $groupIds = [];
        foreach ($groups as $group) {
            if (!empty($group['id'])) $groupIds[] = $group['id'];
        }

        $memberRows = getMemberRowsForGroups($groupIds);
        $membersByGroup = [];
        $memberUserIds = [];

        foreach ($memberRows as $row) {
            $gid = $row['group_id'] ?? null;
            if (!$gid) continue;
            $membersByGroup[$gid][] = $row;
            if (!empty($row['user_id'])) $memberUserIds[] = $row['user_id'];
        }

        $profileMap = $includeMembers ? fetchProfilesMap($memberUserIds) : [];

        foreach ($groups as &$group) {
            $gid = $group['id'];
            $rows = $membersByGroup[$gid] ?? [];
            $group['member_count'] = count($rows);
            $group['is_member'] = false;

            $members = [];
            foreach ($rows as $row) {
                if ($currentUserId && ($row['user_id'] ?? null) === $currentUserId) {
                    $group['is_member'] = true;
                }

                if ($includeMembers && !empty($row['user_id'])) {
                    $profile = $profileMap[$row['user_id']] ?? [];
                    $members[] = [
                        'user_id' => $row['user_id'],
                        'role' => $row['role'] ?? 'member',
                        'joined_at' => $row['joined_at'] ?? null,
                        'name' => $profile['full_name'] ?? 'Member',
                        'profile_photo_url' => $profile['profile_photo_url'] ?? null,
                    ];
                }
            }

            $group['members'] = $members;
        }
        unset($group);

        return $groups;
    }

    function userIsGroupMember($groupId, $userId) {
        if (!$groupId || !$userId) return false;

        $res = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'eq.' . $groupId,
            'user_id'  => 'eq.' . $userId,
            'select'   => 'group_id',
            'limit'    => '1'
        ]);

        return !supabaseFailed($res) && !empty($res['data']);
    }

    // ============================================================
    // POSTS
    // ============================================================

    function handleCreatePost($data) {
        if (empty($data['user_id'])) {
            jsonError("user_id required.", 400);
            return;
        }

        $content = cleanTextValue($data['content'] ?? '', 5000);
        $mediaUrl = trim((string)($data['media_url'] ?? ''));

        if ($content === '' && $mediaUrl === '') {
            jsonError("Post content or media_url required.", 400);
            return;
        }

        $postType = $data['post_type'] ?? ($mediaUrl ? 'image' : 'text');
        $allowedTypes = ['text', 'image', 'video', 'link', 'poll'];
        if (!in_array($postType, $allowedTypes, true)) {
            $postType = $mediaUrl ? 'image' : 'text';
        }

        $taxonomy = resolveTaxonomyValues($data);

        $body = [
            'user_id'   => $data['user_id'],
            'content'   => $content === '' ? null : $content,
            'media_url' => $mediaUrl === '' ? null : $mediaUrl,
            'post_type' => $postType,
            'community' => $taxonomy['community'] ?? null,
            'religion'  => $taxonomy['religion'] ?? null,
        ];

        $res = supabaseRequest('POST', '/rest/v1/posts', [], $body, ['Prefer: return=representation']);

        if (supabaseFailed($res) || empty($res['data'])) {
            sendSupabaseError("Failed to create post.", $res);
            return;
        }

        $post = enrichPosts([$res['data'][0]], $data['user_id'])[0];

        jsonSuccess(["post" => $post]);
    }

    function handleGetPosts($data) {
        $limit = isset($data['limit']) ? max(1, min((int)$data['limit'], 100)) : 30;

        $query = [
            'select'     => 'id,user_id,content,media_url,post_type,community,religion,is_deleted,created_at,updated_at',
            'is_deleted' => 'eq.false',
            'order'      => 'created_at.desc',
            'limit'      => (string)$limit,
        ];

        if (!empty($data['post_id'])) {
            $query['id'] = 'eq.' . $data['post_id'];
        }

        $taxonomy = resolveTaxonomyValues($data);

        if (!empty($taxonomy['community'])) {
            $query['community'] = 'eq.' . $taxonomy['community'];
        }

        if (!empty($taxonomy['religion'])) {
            $query['religion'] = 'eq.' . $taxonomy['religion'];
        }

        $res = supabaseRequest('GET', '/rest/v1/posts', $query);

        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to fetch posts.", $res);
            return;
        }

        $posts = enrichPosts($res['data'] ?? [], $data['user_id'] ?? null);

        jsonSuccess(["posts" => $posts]);
    }

    function handleToggleLike($data) {
        if (!requireFields($data, ['user_id', 'post_id'])) return;

        $uid = $data['user_id'];
        $pid = $data['post_id'];

        $check = supabaseRequest('GET', '/rest/v1/post_likes', [
            'user_id' => 'eq.' . $uid,
            'post_id' => 'eq.' . $pid,
            'select'  => 'post_id,user_id',
            'limit'   => '1'
        ]);

        if (supabaseFailed($check)) {
            sendSupabaseError("Failed to check like status.", $check);
            return;
        }

        if (!empty($check['data'])) {
            $del = supabaseRequest('DELETE', '/rest/v1/post_likes', [
                'user_id' => 'eq.' . $uid,
                'post_id' => 'eq.' . $pid
            ]);

            if (supabaseFailed($del)) {
                sendSupabaseError("Failed to unlike post.", $del);
                return;
            }

            $action = 'unliked';
            $isLiked = false;
        } else {
            $ins = supabaseRequest('POST', '/rest/v1/post_likes', [], [
                'user_id' => $uid,
                'post_id' => $pid,
            ]);

            if (supabaseFailed($ins)) {
                // Duplicate insert is harmless: treat as liked.
                $msg = is_array($ins['data'] ?? null) ? ($ins['data']['code'] ?? $ins['data']['message'] ?? '') : '';
                if ($msg !== '23505') {
                    sendSupabaseError("Failed to like post.", $ins);
                    return;
                }
            }

            $action = 'liked';
            $isLiked = true;
        }

        $likes = supabaseRequest('GET', '/rest/v1/post_likes', [
            'post_id' => 'eq.' . $pid,
            'select'  => 'user_id'
        ]);

        $likeCount = supabaseFailed($likes) ? null : count($likes['data'] ?? []);

        jsonSuccess([
            "action" => $action,
            "post_id" => $pid,
            "is_liked" => $isLiked,
            "isLiked" => $isLiked,
            "like_count" => $likeCount,
            "likes" => $likeCount,
        ]);
    }

    // ============================================================
    // COMMENTS
    // ============================================================

    function handleSubmitComment($data) {
        if (!requireFields($data, ['user_id', 'post_id', 'content'])) return;

        $content = cleanTextValue($data['content'], 2000);
        if ($content === '') {
            jsonError("Comment cannot be empty.", 400);
            return;
        }

        $res = supabaseRequest('POST', '/rest/v1/post_comments', [], [
            'user_id' => $data['user_id'],
            'post_id' => $data['post_id'],
            'content' => $content,
        ], ['Prefer: return=representation']);

        if (supabaseFailed($res) || empty($res['data'])) {
            sendSupabaseError("Failed to submit comment.", $res);
            return;
        }

        $comment = enrichComments([$res['data'][0]])[0];

        $commentCountRes = supabaseRequest('GET', '/rest/v1/post_comments', [
            'post_id'    => 'eq.' . $data['post_id'],
            'is_deleted' => 'eq.false',
            'select'     => 'id'
        ]);

        jsonSuccess([
            "comment" => $comment,
            "comment_count" => supabaseFailed($commentCountRes) ? null : count($commentCountRes['data'] ?? []),
        ]);
    }

    function handleGetComments($data) {
        if (empty($data['post_id'])) {
            jsonError("post_id required.", 400);
            return;
        }

        $res = supabaseRequest('GET', '/rest/v1/post_comments', [
            'post_id'    => 'eq.' . $data['post_id'],
            'is_deleted' => 'eq.false',
            'select'     => 'id,post_id,user_id,content,is_deleted,created_at',
            'order'      => 'created_at.asc',
            'limit'      => '100',
        ]);

        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to fetch comments.", $res);
            return;
        }

        jsonSuccess(["comments" => enrichComments($res['data'] ?? [])]);
    }

    // ============================================================
    // EVENTS
    // ============================================================

    function normalizeEventPayload($data) {
        $title = cleanTextValue($data['title'] ?? '', 180);
        $description = cleanTextValue($data['description'] ?? $data['desc'] ?? '', 3000);
        $location = cleanTextValue($data['location'] ?? '', 300);

        return [
            'title'       => $title,
            'description' => $description === '' ? null : $description,
            'event_date'  => $data['event_date'] ?? $data['date'] ?? null,
            'event_time'  => $data['event_time'] ?? $data['time'] ?? null,
            'location'    => $location === '' ? null : $location,
            'is_online'   => isset($data['is_online']) ? (bool)$data['is_online'] : !empty($data['meeting_url']) || !empty($data['link']),
            'meeting_url' => trim((string)($data['meeting_url'] ?? $data['link'] ?? '')) ?: null,
            'religion'    => ($taxonomy = resolveTaxonomyValues($data))['religion'] ?? null,
            'community'   => $taxonomy['community'] ?? null,
            'banner_url'  => trim((string)($data['banner_url'] ?? '')) ?: null,
        ];
    }

    function handleSaveEvent($data) {
        if (empty($data['user_id']) || empty($data['title'])) {
            jsonError("user_id and title required.", 400);
            return;
        }

        $body = normalizeEventPayload($data);
        if ($body['title'] === '') {
            jsonError("Event title cannot be empty.", 400);
            return;
        }

        if (!empty($data['event_id'])) {
            $res = supabaseRequest('PATCH', '/rest/v1/events', [
                'id' => 'eq.' . $data['event_id'],
                'created_by' => 'eq.' . $data['user_id'],
            ], $body, ['Prefer: return=representation']);
        } else {
            $body['created_by'] = $data['user_id'];
            $res = supabaseRequest('POST', '/rest/v1/events', [], $body, ['Prefer: return=representation']);
        }

        if (supabaseFailed($res) || empty($res['data'])) {
            sendSupabaseError("Failed to save event.", $res);
            return;
        }

        jsonSuccess(["event" => $res['data'][0]]);
    }

    function handleDeleteEvent($data) {
        if (empty($data['event_id'])) {
            jsonError("event_id required.", 400);
            return;
        }

        $query = ['id' => 'eq.' . $data['event_id']];
        if (!empty($data['user_id'])) {
            $query['created_by'] = 'eq.' . $data['user_id'];
        }

        $res = supabaseRequest('DELETE', '/rest/v1/events', $query);

        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to delete event.", $res);
            return;
        }

        jsonSuccess(["event_id" => $data['event_id']]);
    }

    function handleGetEvents($data) {
        $query = [
            'select' => 'id,title,description,event_date,event_time,location,is_online,meeting_url,religion,community,banner_url,created_by,created_at,updated_at',
            'order'  => 'event_date.asc,event_time.asc',
            'limit'  => isset($data['limit']) ? (string)max(1, min((int)$data['limit'], 100)) : '100',
        ];

        $taxonomy = resolveTaxonomyValues($data);
        if (!empty($taxonomy['community'])) $query['community'] = 'eq.' . $taxonomy['community'];
        if (!empty($taxonomy['religion'])) $query['religion'] = 'eq.' . $taxonomy['religion'];
        if (!empty($data['created_by'])) $query['created_by'] = 'eq.' . $data['created_by'];
        if (!empty($data['from_date'])) $query['event_date'] = 'gte.' . $data['from_date'];

        $res = supabaseRequest('GET', '/rest/v1/events', $query);

        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to fetch events.", $res);
            return;
        }

        $events = $res['data'] ?? [];
        $profileMap = fetchProfilesMap(array_column($events, 'created_by'));

        foreach ($events as &$event) {
            $profile = $profileMap[$event['created_by'] ?? ''] ?? [];
            $event['creator'] = profileSummary($profile);
        }
        unset($event);

        jsonSuccess(["events" => $events]);
    }

    // ============================================================
    // GROUPS
    // ============================================================

    function handleCreateGroup($data) {
        if (empty($data['user_id']) || empty($data['name'])) {
            jsonError("user_id and name required.", 400);
            return;
        }

        $name = cleanTextValue($data['name'], 120);
        if ($name === '') {
            jsonError("Group name cannot be empty.", 400);
            return;
        }

        $taxonomy = resolveTaxonomyValues($data);

        $body = [
            'name'        => $name,
            'description' => cleanTextValue($data['description'] ?? $data['desc'] ?? '', 1000) ?: null,
            'avatar_url'  => trim((string)($data['avatar_url'] ?? '')) ?: null,
            'community'   => $taxonomy['community'] ?? null,
            'religion'    => $taxonomy['religion'] ?? null,
            'created_by'  => $data['user_id'],
            'is_private'  => isset($data['is_private']) ? (bool)$data['is_private'] : false,
        ];

        $res = supabaseRequest('POST', '/rest/v1/groups', [], $body, ['Prefer: return=representation']);

        if (supabaseFailed($res) || empty($res['data'])) {
            sendSupabaseError("Failed to create group.", $res);
            return;
        }

        $group = $res['data'][0];

        $memberRes = supabaseRequest('POST', '/rest/v1/group_members', [], [
            'group_id' => $group['id'],
            'user_id'  => $data['user_id'],
            'role'     => 'admin',
        ]);

        if (supabaseFailed($memberRes)) {
            sendSupabaseError("Group was created, but failed to add creator as member.", $memberRes, 500, ["group" => $group]);
            return;
        }

        $group = enrichGroups([$group], $data['user_id'])[0];

        jsonSuccess(["group" => $group]);
    }

    function handleJoinGroup($data) {
        if (!requireFields($data, ['user_id', 'group_id'])) return;

        $res = supabaseRequest('POST', '/rest/v1/group_members', [], [
            'group_id' => $data['group_id'],
            'user_id'  => $data['user_id'],
            'role'     => 'member',
        ], ['Prefer: return=representation']);

        if (supabaseFailed($res)) {
            $code = is_array($res['data'] ?? null) ? ($res['data']['code'] ?? '') : '';
            $msg = is_array($res['data'] ?? null) ? ($res['data']['message'] ?? '') : '';

            if ($code === '23505' || stripos($msg, 'duplicate') !== false) {
                jsonSuccess(["message" => "Already a member."]);
                return;
            }

            sendSupabaseError("Failed to join group.", $res);
            return;
        }

        jsonSuccess(["membership" => $res['data'][0] ?? null]);
    }

    function handleSendGroupMessage($data) {
        if (empty($data['user_id']) || empty($data['group_id'])) {
            jsonError("user_id and group_id required.", 400);
            return;
        }

        $content = cleanTextValue($data['content'] ?? $data['text'] ?? '', 3000);
        $mediaUrl = trim((string)($data['media_url'] ?? ''));

        if ($content === '' && $mediaUrl === '') {
            jsonError("Message content or media_url required.", 400);
            return;
        }

        if (!userIsGroupMember($data['group_id'], $data['user_id'])) {
            jsonError("Only group members can send messages.", 403);
            return;
        }

        $res = supabaseRequest('POST', '/rest/v1/group_messages', [], [
            'group_id'  => $data['group_id'],
            'sender_id' => $data['user_id'],
            'content'   => $content === '' ? null : $content,
            'media_url' => $mediaUrl === '' ? null : $mediaUrl,
        ], ['Prefer: return=representation']);

        if (supabaseFailed($res) || empty($res['data'])) {
            sendSupabaseError("Failed to send message.", $res);
            return;
        }

        $message = enrichGroupMessages([$res['data'][0]])[0];

        jsonSuccess(["message" => $message]);
    }

    function handleGetGroupMessages($data) {
        $groupId = $data['group_id'] ?? null;
        $userId  = $data['user_id'] ?? null;
        $limit   = isset($data['limit']) ? max(1, min((int)$data['limit'], 100)) : 30;

        if (!$groupId || !$userId) {
            jsonError("group_id and user_id are required.", 400);
            return;
        }

        // Because this backend uses the Supabase service key, protect group chats manually.
        // Only members should be able to read group messages.
        $memberRes = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'eq.' . $groupId,
            'user_id'  => 'eq.' . $userId,
            'select'   => 'group_id,user_id',
            'limit'    => '1',
        ]);

        if (supabaseFailed($memberRes)) {
            sendSupabaseError("Failed to verify group membership.", $memberRes);
            return;
        }

        if (empty($memberRes['data'])) {
            jsonError("You are not a member of this group.", 403);
            return;
        }

        // Fetch newest N messages first so Supabase can use the (group_id, created_at desc) index.
        $messagesRes = supabaseRequest('GET', '/rest/v1/group_messages', [
            'group_id'   => 'eq.' . $groupId,
            'is_deleted' => 'eq.false',
            'select'     => 'id,group_id,sender_id,content,media_url,created_at',
            'order'      => 'created_at.desc',
            'limit'      => (string)$limit,
        ]);

        if (supabaseFailed($messagesRes)) {
            sendSupabaseError("Failed to load group messages.", $messagesRes);
            return;
        }

        $messages = $messagesRes['data'] ?? [];

        // Fetch sender profiles separately instead of relying on fragile embedded joins.
        $senderIds = [];
        foreach ($messages as $m) {
            if (!empty($m['sender_id'])) {
                $senderIds[] = $m['sender_id'];
            }
        }

        $senderIds = normalizeUuidList(array_values(array_unique($senderIds)));
        $profilesById = [];

        if (!empty($senderIds)) {
            $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
                'user_id' => 'in.(' . implode(',', $senderIds) . ')',
                'select'  => 'user_id,full_name,profile_photo_url',
            ]);

            if (supabaseFailed($profilesRes)) {
                sendSupabaseError("Failed to load message sender profiles.", $profilesRes);
                return;
            }

            foreach (($profilesRes['data'] ?? []) as $profile) {
                $profilesById[$profile['user_id']] = $profile;
            }
        }

        foreach ($messages as &$m) {
            $profile = $profilesById[$m['sender_id'] ?? ''] ?? null;

            $m['sender_name'] = $profile['full_name'] ?? 'Member';
            $m['sender_avatar_url'] = $profile['profile_photo_url'] ?? null;
        }
        unset($m);

        // Reverse so the frontend displays oldest -> newest.
        $messages = array_reverse($messages);

        jsonSuccess([
            "messages" => $messages
        ]);
    }

    function handleGetGroups($data) {
        $userId = $data['user_id'] ?? null;
        $groupsById = [];

        $publicQuery = [
            'select' => 'id,name,description,avatar_url,community,religion,created_by,is_private,created_at,updated_at',
            'is_private' => 'eq.false',
            'order' => 'created_at.desc',
            'limit' => isset($data['limit']) ? (string)max(1, min((int)$data['limit'], 100)) : '100',
        ];

        $taxonomy = resolveTaxonomyValues($data);
        if (!empty($taxonomy['community'])) $publicQuery['community'] = 'eq.' . $taxonomy['community'];
        if (!empty($taxonomy['religion'])) $publicQuery['religion'] = 'eq.' . $taxonomy['religion'];

        $publicRes = supabaseRequest('GET', '/rest/v1/groups', $publicQuery);

        if (supabaseFailed($publicRes)) {
            sendSupabaseError("Failed to fetch groups.", $publicRes);
            return;
        }

        foreach (($publicRes['data'] ?? []) as $g) {
            $groupsById[$g['id']] = $g;
        }

        if ($userId) {
            $membershipRes = supabaseRequest('GET', '/rest/v1/group_members', [
                'user_id' => 'eq.' . $userId,
                'select' => 'group_id'
            ]);

            if (supabaseFailed($membershipRes)) {
                sendSupabaseError("Failed to fetch group memberships.", $membershipRes);
                return;
            }

            $joinedIds = normalizeUuidList(array_column($membershipRes['data'] ?? [], 'group_id'));
            if (!empty($joinedIds)) {
                $joinedRes = supabaseRequest('GET', '/rest/v1/groups', [
                    'id' => 'in.(' . implode(',', $joinedIds) . ')',
                    'select' => 'id,name,description,avatar_url,community,religion,created_by,is_private,created_at,updated_at',
                    'order' => 'created_at.desc',
                ]);

                if (supabaseFailed($joinedRes)) {
                    sendSupabaseError("Failed to fetch joined groups.", $joinedRes);
                    return;
                }

                foreach (($joinedRes['data'] ?? []) as $g) {
                    $groupsById[$g['id']] = $g;
                }
            }
        }

        $groups = array_values($groupsById);
        usort($groups, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        $groups = enrichGroups($groups, $userId);

        jsonSuccess(["groups" => $groups]);
    }

    // ============================================================
    // FRIENDS
    // ============================================================

    function handleSendFriendRequest($data) {
        $requester = $data['requester'] ?? $data['user_id'] ?? null;
        $addressee = $data['addressee'] ?? $data['addressee_id'] ?? $data['friend_id'] ?? null;

        if (!$requester || !$addressee) {
            jsonError("requester/user_id and addressee required.", 400);
            return;
        }

        if ($requester === $addressee) {
            jsonError("You cannot send a friend request to yourself.", 400);
            return;
        }

        $existing = supabaseRequest('GET', '/rest/v1/friendships', [
            'or' => '(and(requester.eq.' . $requester . ',addressee.eq.' . $addressee . '),and(requester.eq.' . $addressee . ',addressee.eq.' . $requester . '))',
            'select' => 'id,requester,addressee,status',
            'limit' => '1'
        ]);

        if (!supabaseFailed($existing) && !empty($existing['data'])) {
            jsonSuccess([
                "message" => "Friendship or request already exists.",
                "friendship" => $existing['data'][0],
            ]);
            return;
        }

        $res = supabaseRequest('POST', '/rest/v1/friendships', [], [
            'requester' => $requester,
            'addressee' => $addressee,
            'status'    => 'pending',
        ], ['Prefer: return=representation']);

        if (supabaseFailed($res)) {
            $code = is_array($res['data'] ?? null) ? ($res['data']['code'] ?? '') : '';
            if ($code === '23505') {
                jsonSuccess(["message" => "Request already sent."]);
                return;
            }

            sendSupabaseError("Failed to send friend request.", $res);
            return;
        }

        jsonSuccess(["friendship" => $res['data'][0] ?? null]);
    }

    function handleRespondFriendRequest($data) {
        if (empty($data['friendship_id']) || empty($data['action'])) {
            jsonError("friendship_id and action required.", 400);
            return;
        }

        $action = $data['action'];

        if ($action === 'accept' || $action === 'accepted') {
            $query = ['id' => 'eq.' . $data['friendship_id']];
            if (!empty($data['user_id'])) $query['addressee'] = 'eq.' . $data['user_id'];

            $res = supabaseRequest('PATCH', '/rest/v1/friendships', $query, [
                'status' => 'accepted'
            ], ['Prefer: return=representation']);

            if (supabaseFailed($res)) {
                sendSupabaseError("Failed to accept friend request.", $res);
                return;
            }

            jsonSuccess(["action" => "accepted", "friendship" => $res['data'][0] ?? null]);
            return;
        }

        $query = ['id' => 'eq.' . $data['friendship_id']];
        if (!empty($data['user_id'])) $query['addressee'] = 'eq.' . $data['user_id'];

        $res = supabaseRequest('DELETE', '/rest/v1/friendships', $query);

        if (supabaseFailed($res)) {
            sendSupabaseError("Failed to decline friend request.", $res);
            return;
        }

        jsonSuccess(["action" => "declined", "friendship_id" => $data['friendship_id']]);
    }

    function handleGetFriends($data) {
        if (empty($data['user_id'])) {
            jsonError("user_id required.", 400);
            return;
        }

        $uid = $data['user_id'];

        $sentAccepted = supabaseRequest('GET', '/rest/v1/friendships', [
            'requester' => 'eq.' . $uid,
            'status'    => 'eq.accepted',
            'select'    => 'id,requester,addressee,status,created_at,updated_at'
        ]);

        $receivedAccepted = supabaseRequest('GET', '/rest/v1/friendships', [
            'addressee' => 'eq.' . $uid,
            'status'    => 'eq.accepted',
            'select'    => 'id,requester,addressee,status,created_at,updated_at'
        ]);

        $incomingPending = supabaseRequest('GET', '/rest/v1/friendships', [
            'addressee' => 'eq.' . $uid,
            'status'    => 'eq.pending',
            'select'    => 'id,requester,addressee,status,created_at,updated_at'
        ]);

        $outgoingPending = supabaseRequest('GET', '/rest/v1/friendships', [
            'requester' => 'eq.' . $uid,
            'status'    => 'eq.pending',
            'select'    => 'id,requester,addressee,status,created_at,updated_at'
        ]);

        foreach ([
            'sentAccepted' => $sentAccepted,
            'receivedAccepted' => $receivedAccepted,
            'incomingPending' => $incomingPending,
            'outgoingPending' => $outgoingPending
        ] as $label => $res) {
            if (supabaseFailed($res)) {
                sendSupabaseError("Failed to fetch friendships.", $res, 500, ["source" => $label]);
                return;
            }
        }

        $rows = [];
        $profileIds = [];

        $addRow = function($friendship, $otherUserId, $type) use (&$rows, &$profileIds) {
            if (!$otherUserId) return;
            $rows[] = [
                'friendship_id' => $friendship['id'],
                'user_id' => $otherUserId,
                'type' => $type,
                'status' => $friendship['status'] ?? null,
                'created_at' => $friendship['created_at'] ?? null,
            ];
            $profileIds[] = $otherUserId;
        };

        foreach (($sentAccepted['data'] ?? []) as $f) {
            $addRow($f, $f['addressee'] ?? null, 'friend');
        }

        foreach (($receivedAccepted['data'] ?? []) as $f) {
            $addRow($f, $f['requester'] ?? null, 'friend');
        }

        foreach (($incomingPending['data'] ?? []) as $f) {
            $addRow($f, $f['requester'] ?? null, 'request');
        }

        foreach (($outgoingPending['data'] ?? []) as $f) {
            $addRow($f, $f['addressee'] ?? null, 'sent_request');
        }

        $profileMap = fetchProfilesMap($profileIds);

        $format = function($row) use ($profileMap) {
            $profile = $profileMap[$row['user_id']] ?? [];
            return [
                'friendship_id' => $row['friendship_id'],
                'user_id' => $row['user_id'],
                'name' => $profile['full_name'] ?? 'Member',
                'photo' => $profile['profile_photo_url'] ?? null,
                'community' => $profile['community'] ?? null,
                'religion' => $profile['religion'] ?? null,
                'type' => $row['type'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
            ];
        };

        $friends = [];
        $requests = [];
        $sentRequests = [];

        foreach ($rows as $row) {
            if ($row['type'] === 'friend') $friends[] = $format($row);
            if ($row['type'] === 'request') $requests[] = $format($row);
            if ($row['type'] === 'sent_request') $sentRequests[] = $format($row);
        }

        jsonSuccess([
            "friends" => $friends,
            "requests" => $requests,
            "sent_requests" => $sentRequests
        ]);
    }

    // ============================================================
    // ZOOM CALLS
    // ============================================================

    function handleZoomTest($data) {
        $required = [
            'ZOOM_S2S_ACCOUNT_ID',
            'ZOOM_S2S_CLIENT_ID',
            'ZOOM_S2S_CLIENT_SECRET',
            'ZOOM_MEETING_SDK_CLIENT_ID',
            'ZOOM_MEETING_SDK_CLIENT_SECRET',
            'ZOOM_HOST_USER_ID',
            'APP_BASE_URL'
        ];

        $missing = [];

        foreach ($required as $key) {
            if (!envValue($key)) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            jsonError("Missing Zoom .env values.", 500, [
                "missing" => $missing
            ]);
            return;
        }

        $token = zoomGetAccessToken();

        jsonSuccess([
            "message" => "Zoom Server-to-Server OAuth is working.",
            "host_user_id" => envValue('ZOOM_HOST_USER_ID'),
            "app_base_url" => envValue('APP_BASE_URL'),
            "token_preview" => substr($token, 0, 12) . "..."
        ]);
    }
    function base64UrlEncodeRaw($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    function generateZoomMeetingSdkJwt($meetingNumber, $role = 0) {
        $clientId = envValue('ZOOM_MEETING_SDK_CLIENT_ID');
        $clientSecret = envValue('ZOOM_MEETING_SDK_CLIENT_SECRET');

        if (!$clientId || !$clientSecret) {
            jsonError("Zoom Meeting SDK credentials are missing from .env", 500);
            exit();
        }

        $iat = time() - 30;
        $exp = $iat + 60 * 60 * 2;

        $header = [
            "alg" => "HS256",
            "typ" => "JWT"
        ];

        $payload = [
            "appKey" => $clientId,
            "sdkKey" => $clientId,
            "mn" => (string) $meetingNumber,
            "role" => (int) $role,
            "iat" => $iat,
            "exp" => $exp,
            "tokenExp" => $exp,
            "video_webrtc_mode" => 1
        ];

        $segments = [
            base64UrlEncodeRaw(json_encode($header)),
            base64UrlEncodeRaw(json_encode($payload))
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $clientSecret, true);

        $segments[] = base64UrlEncodeRaw($signature);

        return implode('.', $segments);
    }

    function zoomGetAccessToken() {
        $accountId = envValue('ZOOM_S2S_ACCOUNT_ID');
        $clientId = envValue('ZOOM_S2S_CLIENT_ID');
        $clientSecret = envValue('ZOOM_S2S_CLIENT_SECRET');

        if (!$accountId || !$clientId || !$clientSecret) {
            jsonError("Zoom Server-to-Server OAuth credentials are missing from .env", 500);
            exit();
        }

        $url = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . urlencode($accountId);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $data = json_decode($response, true);

        if ($httpCode >= 400 || empty($data['access_token'])) {
            jsonError("Failed to get Zoom access token.", 500, [
                "zoom_http_code" => $httpCode,
                "zoom_response" => $data
            ]);
            exit();
        }

        return $data['access_token'];
    }

    function zoomApiRequest($method, $path, $body = null) {
        $token = zoomGetAccessToken();

        $ch = curl_init('https://api.zoom.us/v2' . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [
            "code" => $httpCode,
            "data" => json_decode($response, true)
        ];
    }

    function createZoomMeetingForCall($callType, $topic) {
        $hostUserId = envValue('ZOOM_HOST_USER_ID');

        if (!$hostUserId) {
            jsonError("ZOOM_HOST_USER_ID is missing from .env", 500);
            exit();
        }

        $isVideo = $callType === 'video';

        $password = substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ23456789'), 0, 8);

        $payload = [
            "topic" => $topic,
            "type" => 2,
            "start_time" => gmdate('Y-m-d\TH:i:s\Z', time() + 60),
            "duration" => 60,
            "timezone" => "UTC",
            "password" => $password,
            "settings" => [
                "join_before_host" => true,
                "waiting_room" => false,
                "host_video" => $isVideo,
                "participant_video" => $isVideo,
                "mute_upon_entry" => false,
                "approval_type" => 2,
                "audio" => "both"
            ]
        ];

        $res = zoomApiRequest(
            'POST',
            '/users/' . rawurlencode($hostUserId) . '/meetings',
            $payload
        );

        if ($res['code'] >= 400 || empty($res['data']['id'])) {
            jsonError("Failed to create Zoom meeting.", 500, [
                "zoom_http_code" => $res['code'],
                "zoom_response" => $res['data']
            ]);
            exit();
        }

        return $res['data'];
    }

    function uniqueUserIds($ids) {
        $clean = [];

        foreach ((array) $ids as $id) {
            $id = trim((string) $id);
            if ($id !== '' && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    function getGroupMemberIds($groupId) {
        $res = supabaseRequest('GET', '/rest/v1/group_members', [
            'group_id' => 'eq.' . $groupId,
            'select' => 'user_id'
        ]);

        if ($res['code'] >= 400) {
            jsonError("Failed to load group members.", 500);
            exit();
        }

        return array_values(array_unique(array_column($res['data'] ?? [], 'user_id')));
    }

    function isGroupMember($userId, $groupId) {
        $res = supabaseRequest('GET', '/rest/v1/group_members', [
            'user_id' => 'eq.' . $userId,
            'group_id' => 'eq.' . $groupId,
            'select' => 'id',
            'limit' => '1'
        ]);

        return !empty($res['data']);
    }

    function areFriends($userA, $userB) {
        $orFilter = sprintf(
            '(and(requester.eq.%s,addressee.eq.%s),and(requester.eq.%s,addressee.eq.%s))',
            $userA,
            $userB,
            $userB,
            $userA
        );

        $res = supabaseRequest('GET', '/rest/v1/friendships', [
            'status' => 'eq.accepted',
            'or' => $orFilter,
            'select' => 'id',
            'limit' => '1'
        ]);

        return !empty($res['data']);
    }

    function resolveCallParticipants($data) {
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

            if (!isGroupMember($callerId, $data['group_id'])) {
                jsonError("You are not a member of this group.", 403);
                exit();
            }

            $memberIds = getGroupMemberIds($data['group_id']);

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

    function insertCallParticipants($callId, $callerId, $participantIds) {
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

    function handleZoomStartCall($data) {

        cleanupStaleCallSessions();
        if (empty($data['user_id']) || empty($data['call_type']) || empty($data['target_type'])) {
            jsonError("user_id, call_type and target_type are required.");
            return;
        }

        $callerId = $data['user_id'];
        $callType = $data['call_type'];

        if (!in_array($callType, ['voice', 'video'], true)) {
            jsonError("call_type must be voice or video.");
            return;
        }

        if (!in_array($data['target_type'], ['direct', 'selected_users', 'group'], true)) {
            jsonError("target_type must be direct, selected_users or group.");
            return;
        }

        $participantIds = resolveCallParticipants($data);

        $topic = $callType === 'video'
            ? 'eSamaj Video Call'
            : 'eSamaj Voice Call';

        $zoomMeeting = createZoomMeetingForCall($callType, $topic);

        $meetingId = (string) $zoomMeeting['id'];
        $password = $zoomMeeting['password'] ?? '';
        $joinUrl = $zoomMeeting['join_url'] ?? null;

        $callRes = supabaseRequest(
            'POST',
            '/rest/v1/call_sessions',
            [],
            [
                'created_by' => $callerId,
                'call_type' => $callType,
                'target_type' => $data['target_type'],
                'group_id' => $data['target_type'] === 'group' ? ($data['group_id'] ?? null) : null,
                'provider' => 'zoom',
                'zoom_meeting_id' => $meetingId,
                'zoom_password' => $password,
                'zoom_join_url' => $joinUrl,
                'status' => 'active',
                'started_at' => gmdate('c')
            ],
            ['Prefer: return=representation']
        );

        if ($callRes['code'] >= 400 || empty($callRes['data'][0]['id'])) {
            jsonError("Failed to save call session.", 500, [
                "supabase_response" => $callRes['data']
            ]);
            return;
        }

        $call = $callRes['data'][0];
        insertCallParticipants($call['id'], $callerId, $participantIds);

        $signature = generateZoomMeetingSdkJwt($meetingId, 0);

        jsonSuccess([
            "call" => $call,
            "zoom" => [
                "sdkKey" => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
                "meetingNumber" => $meetingId,
                "password" => $password,
                "signature" => $signature,
                "role" => 0,
                "joinUrl" => $joinUrl
            ]
        ]);
    }

    function handleZoomJoinCall($data) {
        if (empty($data['user_id']) || empty($data['call_id'])) {
            jsonError("user_id and call_id are required.");
            return;
        }

        $userId = $data['user_id'];
        $callId = $data['call_id'];

        $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
            'call_id' => 'eq.' . $callId,
            'user_id' => 'eq.' . $userId,
            'select' => 'id,status',
            'limit' => '1'
        ]);

        if (empty($participantRes['data'])) {
            jsonError("You are not invited to this call.", 403);
            return;
        }

        $callRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
            'id' => 'eq.' . $callId,
            'status' => 'in.(ringing,active)',
            'select' => 'id,created_by,call_type,target_type,group_id,zoom_meeting_id,zoom_password,zoom_join_url,status,started_at,ended_at,created_at',
            'limit' => '1'
        ]);

        if (empty($callRes['data'])) {
            jsonError("Call not found or already ended.", 404);
            return;
        }

        $call = $callRes['data'][0];

        supabaseRequest(
            'PATCH',
            '/rest/v1/call_participants',
            [
                'call_id' => 'eq.' . $callId,
                'user_id' => 'eq.' . $userId
            ],
            [
                'status' => 'joined',
                'joined_at' => gmdate('c')
            ]
        );

        supabaseRequest(
            'PATCH',
            '/rest/v1/call_sessions',
            ['id' => 'eq.' . $callId],
            ['status' => 'active']
        );

        $meetingId = $call['zoom_meeting_id'];
        $signature = generateZoomMeetingSdkJwt($meetingId, 0);

        jsonSuccess([
            "call" => $call,
            "zoom" => [
                "sdkKey" => envValue('ZOOM_MEETING_SDK_CLIENT_ID'),
                "meetingNumber" => $meetingId,
                "password" => $call['zoom_password'] ?? '',
                "signature" => $signature,
                "role" => 0,
                "joinUrl" => $call['zoom_join_url'] ?? null
            ]
        ]);
    }

    function zoomEndMeetingIfPossible($meetingId) {
        if (!$meetingId) {
            return null;
        }

        // Best-effort cleanup. Zoom returns an error if the meeting is already ended/not live;
        // we should not fail the app's own DB cleanup because of that.
        return zoomApiRequest('PUT', '/meetings/' . rawurlencode((string)$meetingId) . '/status', [
            'action' => 'end'
        ]);
    }

    function handleZoomEndCall($data) {
        if (empty($data['user_id']) || empty($data['call_id'])) {
            jsonError("user_id and call_id are required.");
            return;
        }

        $userId = $data['user_id'];
        $callId = $data['call_id'];

        $ownerRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
            'id' => 'eq.' . $callId,
            'created_by' => 'eq.' . $userId,
            'select' => 'id,zoom_meeting_id',
            'limit' => '1'
        ]);

        if (empty($ownerRes['data'])) {
            jsonError("Only the call creator can end this call.", 403);
            return;
        }

        $meetingId = $ownerRes['data'][0]['zoom_meeting_id'] ?? null;
        $endedAt = gmdate('c');

        supabaseRequest(
            'PATCH',
            '/rest/v1/call_sessions',
            ['id' => 'eq.' . $callId],
            [
                'status' => 'ended',
                'ended_at' => $endedAt
            ]
        );

        supabaseRequest(
            'PATCH',
            '/rest/v1/call_participants',
            [
                'call_id' => 'eq.' . $callId,
                'status' => 'in.(invited,ringing,joined)'
            ],
            [
                'status' => 'left',
                'left_at' => $endedAt
            ]
        );

        zoomEndMeetingIfPossible($meetingId);

        jsonSuccess(["message" => "Call ended.", "call_ended" => true, "ended_at" => $endedAt]);
    }

    function maybeEndCallIfNobodyJoined($callId) {
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

    function handleZoomGetActiveCalls($data) {
        if (empty($data['user_id'])) {
            jsonError("user_id is required.");
            return;
        }

        $res = supabaseRequest('GET', '/rest/v1/call_participants', [
            'user_id' => 'eq.' . $data['user_id'],
            'status' => 'in.(invited,ringing,joined)',
            'select' => 'id,status,call_sessions(id,created_by,call_type,target_type,group_id,status,created_at,started_at)',
            'order' => 'created_at.desc'
        ]);

        jsonSuccess([
            "calls" => $res['data'] ?? []
        ]);
    }

    function handleZoomGetGroupCalls($data) {
        cleanupStaleCallSessions();

        if (empty($data['user_id']) || empty($data['group_id'])) {
            jsonError("user_id and group_id are required.");
            return;
        }

        $userId = $data['user_id'];
        $groupId = $data['group_id'];
        $limit = isset($data['limit']) ? max(1, min((int)$data['limit'], 50)) : 20;

        if (!isGroupMember($userId, $groupId)) {
            jsonError("You are not a member of this group.", 403);
            return;
        }

        $callsRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
            'group_id' => 'eq.' . $groupId,
            'target_type' => 'eq.group',
            'provider' => 'eq.zoom',
            'select' => 'id,created_by,call_type,target_type,group_id,status,created_at,started_at,ended_at',
            'order' => 'created_at.desc',
            'limit' => (string)$limit
        ]);

        if ($callsRes['code'] >= 400) {
            jsonError("Failed to load group calls.", 500, ["supabase_response" => $callsRes['data']]);
            return;
        }

        $calls = $callsRes['data'] ?? [];
        $creatorIds = [];
        $callIds = [];

        foreach ($calls as $call) {
            if (!empty($call['created_by'])) $creatorIds[] = $call['created_by'];
            if (!empty($call['id'])) $callIds[] = $call['id'];
        }

        $creatorIds = normalizeUuidList(array_values(array_unique($creatorIds)));
        $callIds = normalizeUuidList(array_values(array_unique($callIds)));

        $profilesByUserId = [];
        if (!empty($creatorIds)) {
            $profilesRes = supabaseRequest('GET', '/rest/v1/profiles', [
                'user_id' => 'in.(' . implode(',', $creatorIds) . ')',
                'select' => 'user_id,full_name,profile_photo_url'
            ]);

            if ($profilesRes['code'] < 400) {
                foreach (($profilesRes['data'] ?? []) as $profile) {
                    $profilesByUserId[$profile['user_id']] = $profile;
                }
            }
        }

        $participantByCallId = [];
        if (!empty($callIds)) {
            $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
                'call_id' => 'in.(' . implode(',', $callIds) . ')',
                'user_id' => 'eq.' . $userId,
                'select' => 'call_id,status,joined_at,left_at'
            ]);

            if ($participantRes['code'] < 400) {
                foreach (($participantRes['data'] ?? []) as $row) {
                    $participantByCallId[$row['call_id']] = $row;
                }
            }
        }

        foreach ($calls as &$call) {
            $profile = $profilesByUserId[$call['created_by'] ?? ''] ?? null;
            $participant = $participantByCallId[$call['id'] ?? ''] ?? null;

            $call['created_by_name'] = $profile['full_name'] ?? 'Member';
            $call['created_by_avatar_url'] = $profile['profile_photo_url'] ?? null;
            $call['participant_status'] = $participant['status'] ?? null;
            $call['participant_joined_at'] = $participant['joined_at'] ?? null;
            $call['participant_left_at'] = $participant['left_at'] ?? null;
        }
        unset($call);

        jsonSuccess(["calls" => $calls]);
    }

    function cleanupStaleCallSessions() {
        // Calls can get stuck as active/ringing if a browser tab is closed,
        // the Zoom SDK fails to report leave, or local dev is refreshed.
        // Treat any call older than this as stale and close it in our DB.
        $staleAfterSeconds = 2 * 60 * 60; // 2 hours
        $threshold = gmdate('c', time() - $staleAfterSeconds);
        $now = gmdate('c');

        $staleFilters = [
            'status' => 'in.(ringing,active,live)',
            'ended_at' => 'is.null',
        ];

        // Some rows may have created_at but no started_at, or vice versa.
        // Patch both cases so old "LIVE NOW" cards do not stay live forever.
        supabaseRequest('PATCH', '/rest/v1/call_sessions', array_merge($staleFilters, [
            'created_at' => 'lt.' . $threshold,
        ]), [
            'status' => 'ended',
            'ended_at' => $now,
        ]);

        supabaseRequest('PATCH', '/rest/v1/call_sessions', array_merge($staleFilters, [
            'started_at' => 'lt.' . $threshold,
        ]), [
            'status' => 'ended',
            'ended_at' => $now,
        ]);

        // Mark any unfinished participants on those old calls as missed/left.
        // This is best-effort cleanup for display consistency; call_sessions is the source of truth.
        $staleCallsRes = supabaseRequest('GET', '/rest/v1/call_sessions', [
            'status' => 'eq.ended',
            'ended_at' => 'gte.' . gmdate('c', time() - 60),
            'select' => 'id,ended_at',
            'limit' => '100',
        ]);

        $callIds = normalizeUuidList(array_column($staleCallsRes['data'] ?? [], 'id'));
        if (!empty($callIds)) {
            supabaseRequest('PATCH', '/rest/v1/call_participants', [
                'call_id' => 'in.(' . implode(',', $callIds) . ')',
                'status' => 'in.(invited,ringing)'
            ], [
                'status' => 'missed',
                'left_at' => $now,
            ]);

            supabaseRequest('PATCH', '/rest/v1/call_participants', [
                'call_id' => 'in.(' . implode(',', $callIds) . ')',
                'status' => 'eq.joined'
            ], [
                'status' => 'left',
                'left_at' => $now,
            ]);
        }
    }
    function handleZoomMarkParticipant($data) {
        if (empty($data['user_id']) || empty($data['call_id']) || empty($data['participant_status'])) {
            jsonError("user_id, call_id and participant_status are required.");
            return;
        }

        $allowed = ['declined', 'left', 'missed'];

        if (!in_array($data['participant_status'], $allowed, true)) {
            jsonError("Invalid participant_status.");
            return;
        }

        $userId = $data['user_id'];
        $callId = $data['call_id'];

        $participantRes = supabaseRequest('GET', '/rest/v1/call_participants', [
            'call_id' => 'eq.' . $callId,
            'user_id' => 'eq.' . $userId,
            'select' => 'id,status',
            'limit' => '1'
        ]);

        if (empty($participantRes['data'])) {
            jsonError("You are not a participant in this call.", 403);
            return;
        }

        $now = gmdate('c');
        $patch = [
            'status' => $data['participant_status']
        ];

        if ($data['participant_status'] === 'left') {
            $patch['left_at'] = $now;
        }

        supabaseRequest(
            'PATCH',
            '/rest/v1/call_participants',
            [
                'call_id' => 'eq.' . $callId,
                'user_id' => 'eq.' . $userId
            ],
            $patch
        );

        $endCheck = ["ended" => false];
        if ($data['participant_status'] === 'left') {
            $endCheck = maybeEndCallIfNobodyJoined($callId);
        }

        jsonSuccess([
            "call_ended" => !empty($endCheck['ended']),
            "ended_at" => $endCheck['ended_at'] ?? null
        ]);
    }