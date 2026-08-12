<?php
/**
 * Pet Care & Training Guides library.
 *
 * Ported from community_proj/api/routes/holy_books.php, which is a generic
 * ebook reader/streaming-proxy over a Supabase-Storage-backed table — the
 * proxy mechanism (streamRemoteEbookFile, ebook_file/ebook_redirect routing,
 * range-request passthrough) is unchanged. Everything that was keyed on
 * religion ("tradition") is now keyed on `service_type`, the pet-appropriate
 * category column already present on the live `care_guides` table (renamed
 * from `pet_services`; see migration rename_pet_services_to_care_guides).
 * There is no scripture/religion content anywhere in this file.
 */

function slugifyCareGuide($value)
{
    $slug = strtolower(trim((string) $value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

if (!function_exists('isTruthyDbValue')) {
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
}

function careGuideFilename($guide, $format)
{
    $slug = $guide['slug'] ?? slugifyCareGuide(($guide['service_type'] ?? '') . '-' . ($guide['title'] ?? 'care-guide'));
    return $slug . '.' . $format;
}

function careGuideSourceType($guide, $format = 'pdf')
{
    $format = strtolower((string) $format) === 'epub' ? 'epub' : 'pdf';
    $externalKey = $format === 'epub' ? 'external_epub_url' : 'external_pdf_url';
    $pathKey = $format === 'epub' ? 'epub_path' : 'pdf_path';

    if (!empty($guide[$externalKey]))
        return 'external';
    if (!empty($guide[$pathKey]))
        return 'bucket';
    return 'none';
}

function buildCareGuideRoute($guideId, $format = 'pdf', $intent = 'read', $mode = 'scroll')
{
    return 'api/index.php?action=guide_redirect'
        . '&guide_id=' . rawurlencode($guideId)
        . '&format=' . rawurlencode($format)
        . '&intent=' . rawurlencode($intent)
        . '&mode=' . rawurlencode($mode);
}

function normalizeCareGuideCategoryKey($category)
{
    $category = strtolower(trim((string) $category));
    $aliases = [
        'training' => 'training',
        'obedience' => 'training',
        'health' => 'health',
        'medical' => 'health',
        'nutrition' => 'nutrition',
        'diet' => 'nutrition',
        'feeding' => 'nutrition',
        'behavior' => 'behavior',
        'behaviour' => 'behavior',
        'first-aid' => 'first-aid',
        'first_aid' => 'first-aid',
        'firstaid' => 'first-aid',
        'emergency' => 'first-aid',
        'grooming' => 'grooming',
    ];
    return $aliases[$category] ?? ($category ?: 'training');
}

function careGuideCategoryMeta($category)
{
    $key = normalizeCareGuideCategoryKey($category);
    $meta = [
        'training' => ['title' => 'Training Guides', 'subtitle' => 'Obedience, tricks & everyday manners'],
        'health' => ['title' => 'Health Guides', 'subtitle' => 'Vet-reviewed medical basics'],
        'nutrition' => ['title' => 'Nutrition Guides', 'subtitle' => 'Feeding, diet & weight care'],
        'behavior' => ['title' => 'Behavior Guides', 'subtitle' => 'Anxiety, socialization & problem behaviors'],
        'first-aid' => ['title' => 'First-Aid Guides', 'subtitle' => 'Emergency care basics'],
        'grooming' => ['title' => 'Grooming Guides', 'subtitle' => 'Coat, nail & hygiene care'],
    ];
    return $meta[$key] ?? [
        'title' => ucfirst($key) . ' Guides',
        'subtitle' => 'Pet care resources',
    ];
}

function careGuideUiMeta($guide)
{
    $category = normalizeCareGuideCategoryKey($guide['service_type'] ?? '');
    $byCategory = [
        'training' => ['icon' => 'graduation-cap', 'bg' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'],
        'health' => ['icon' => 'heart-pulse', 'bg' => 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'],
        'nutrition' => ['icon' => 'utensils', 'bg' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400'],
        'behavior' => ['icon' => 'brain', 'bg' => 'bg-purple-50 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400'],
        'first-aid' => ['icon' => 'siren', 'bg' => 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400'],
        'grooming' => ['icon' => 'scissors', 'bg' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'],
    ];
    return $byCategory[$category] ?? ['icon' => 'book-open', 'bg' => 'bg-brand-50 text-brand-600 dark:bg-brand-900/30 dark:text-brand-400'];
}

function getCareGuideRows()
{
    $res = supabaseRequest('GET', '/rest/v1/care_guides', [
        'select' => 'id,slug,service_type,title,subtitle,description,language,source_label,source_url,bucket_id,pdf_path,epub_path,external_pdf_url,external_epub_url,default_read_mode,scroll_enabled,page_enabled,pdf_download_enabled,epub_download_enabled,sort_order,is_active',
        'is_active' => 'eq.true',
        'order' => 'service_type.asc,sort_order.asc,title.asc',
    ]);

    if (supabaseFailed($res) || !is_array($res['data'])) {
        $message = is_array($res['data'])
            ? ($res['data']['message'] ?? json_encode($res['data']))
            : 'Could not read care_guides from Supabase.';
        return [
            'ok' => false,
            'message' => 'Failed to load care guides: ' . $message . ' (HTTP ' . ($res['code'] ?? 'unknown') . ')',
            'data' => [],
        ];
    }

    return ['ok' => true, 'message' => '', 'data' => $res['data']];
}

function normaliseCareGuideRowForFrontend($row)
{
    $slug = trim((string) ($row['slug'] ?? ''));
    if ($slug === '') {
        $slug = slugifyCareGuide(($row['service_type'] ?? '') . '-' . ($row['title'] ?? 'care-guide'));
    }

    $row['slug'] = $slug;
    $category = normalizeCareGuideCategoryKey($row['service_type'] ?? '');
    $ui = careGuideUiMeta($row);

    $pdfUrl = resolveCareGuideUrl($row, 'pdf', 'read');
    $epubUrl = resolveCareGuideUrl($row, 'epub', 'download');

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
        $desc = 'Pet care guide';

    $pdfSourceType = careGuideSourceType($row, 'pdf');
    $epubSourceType = careGuideSourceType($row, 'epub');

    $pdfReadRoute = function ($mode) use ($slug) {
        return buildCareGuideRoute($slug, 'pdf', 'read', $mode);
    };

    return [
        'id' => $slug,
        'slug' => $slug,
        'category' => $category,
        'title' => $row['title'] ?? 'Care Guide',
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
                'url' => $pdfDownloadEnabled ? buildCareGuideRoute($slug, 'pdf', 'download', 'page') : '',
                'source_type' => $pdfSourceType,
            ],
            'epub' => [
                'label' => 'EPUB',
                'available' => $epubDownloadEnabled,
                'url' => $epubDownloadEnabled ? buildCareGuideRoute($slug, 'epub', 'download', 'page') : '',
                'source_type' => $epubSourceType,
            ],
        ],
    ];
}

function findCareGuideById($guideId)
{
    $guideId = trim((string) $guideId);
    if ($guideId === '')
        return null;

    $rows = getCareGuideRows();
    if (!$rows['ok'])
        return null;

    $wanted = slugifyCareGuide($guideId);
    foreach ($rows['data'] as $row) {
        $candidates = array_filter([
            trim((string) ($row['slug'] ?? '')),
            trim((string) ($row['id'] ?? '')),
            slugifyCareGuide(($row['service_type'] ?? '') . '-' . ($row['title'] ?? '')),
            slugifyCareGuide($row['title'] ?? ''),
        ]);

        foreach ($candidates as $candidate) {
            if ($guideId === $candidate || $wanted === slugifyCareGuide($candidate)) {
                return $row;
            }
        }
    }

    return null;
}

function handleGetCareGuides($data)
{
    $rows = getCareGuideRows();
    if (!$rows['ok']) {
        jsonError($rows['message'], 500);
        return;
    }

    $requestedCategory = $data['category'] ?? ($_GET['category'] ?? '');
    $categoryKey = $requestedCategory !== '' ? normalizeCareGuideCategoryKey($requestedCategory) : '';
    $guides = [];

    foreach ($rows['data'] as $row) {
        if ($categoryKey !== '' && normalizeCareGuideCategoryKey($row['service_type'] ?? '') !== $categoryKey) {
            continue;
        }
        $guides[] = normaliseCareGuideRowForFrontend($row);
    }

    usort($guides, function ($a, $b) {
        $orderA = intval($a['sort_order'] ?? 100);
        $orderB = intval($b['sort_order'] ?? 100);
        if ($orderA === $orderB) {
            return strcmp(strtolower($a['title'] ?? ''), strtolower($b['title'] ?? ''));
        }
        return $orderA <=> $orderB;
    });

    $categories = [];
    foreach (['training', 'health', 'nutrition', 'behavior', 'first-aid', 'grooming'] as $key) {
        $meta = careGuideCategoryMeta($key);
        $meta['key'] = $key;
        $meta['count'] = count(array_filter($rows['data'], function ($row) use ($key) {
            return normalizeCareGuideCategoryKey($row['service_type'] ?? '') === $key;
        }));
        $categories[] = $meta;
    }

    jsonSuccess([
        'category' => $categoryKey,
        'categories' => $categories,
        'guides' => $guides,
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

function careGuideRequestContext($data)
{
    $format = strtolower($_GET['format'] ?? ($data['format'] ?? 'pdf'));
    $intent = strtolower($_GET['intent'] ?? ($data['intent'] ?? 'read'));
    $mode = strtolower($_GET['mode'] ?? ($data['mode'] ?? 'page'));

    if (!in_array($format, ['pdf', 'epub'], true)) {
        return ['ok' => false, 'message' => 'Unsupported guide format.', 'code' => 400];
    }
    if (!in_array($intent, ['read', 'download'], true))
        $intent = 'read';
    if (!in_array($mode, ['scroll', 'page'], true))
        $mode = 'page';
    if ($format === 'epub' && $intent === 'read')
        $intent = 'download';

    $guideId = $_GET['guide_id'] ?? ($data['guide_id'] ?? '');
    $guide = findCareGuideById($guideId);
    if (!$guide) {
        return ['ok' => false, 'message' => 'Unknown guide requested.', 'code' => 404];
    }

    if ($format === 'pdf' && $intent === 'read') {
        if ($mode === 'page' && !isTruthyDbValue($guide['page_enabled'] ?? true, true)) {
            return ['ok' => false, 'message' => 'Page reading is not enabled for this guide.', 'code' => 404];
        }
        if ($mode === 'scroll' && !isTruthyDbValue($guide['scroll_enabled'] ?? true, true)) {
            return ['ok' => false, 'message' => 'Scroll reading is not enabled for this guide.', 'code' => 404];
        }
    }

    if ($format === 'pdf' && $intent === 'download' && !isTruthyDbValue($guide['pdf_download_enabled'] ?? true, true)) {
        return ['ok' => false, 'message' => 'PDF download is not enabled for this guide.', 'code' => 404];
    }

    if ($format === 'epub' && !isTruthyDbValue($guide['epub_download_enabled'] ?? false, false)) {
        return ['ok' => false, 'message' => 'EPUB download is not enabled for this guide.', 'code' => 404];
    }

    $url = resolveCareGuideUrl($guide, $format, $intent);
    if (empty($url)) {
        return ['ok' => false, 'message' => 'No ' . strtoupper($format) . ' source is configured for this guide.', 'code' => 404];
    }

    return [
        'ok' => true,
        'guide' => $guide,
        'url' => $url,
        'format' => $format,
        'intent' => $intent,
        'mode' => $mode,
    ];
}

function streamRemoteGuideFile($url, $format, $filename, $intent)
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
    header('X-PawCircle-Guide-Proxy: ' . PAWCIRCLE_BACKEND_BUILD);
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
    curl_setopt($ch, CURLOPT_USERAGENT, 'PawCircle Guide Proxy');
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
                echo json_encode(['success' => false, 'message' => 'The guide file could not be reached at its configured storage URL. HTTP ' . $statusCode]);
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
        jsonError($err ?: ('The guide file could not be reached at its configured storage URL. HTTP ' . $code), 502);
        return;
    }
    exit();
}

function handleGuideFile($data)
{
    $ctx = careGuideRequestContext($data);
    if (!$ctx['ok']) {
        jsonError($ctx['message'], $ctx['code']);
        return;
    }

    $guide = $ctx['guide'];
    $format = $ctx['format'];
    $intent = $ctx['intent'];
    $url = $ctx['url'];
    $filename = careGuideFilename($guide, $format);

    // Do not proxy intentionally external files (e.g. a large vendor-hosted PDF);
    // those remain direct redirects/new-tab reads.
    if (careGuideSourceType($guide, $format) === 'external') {
        header_remove('Content-Type');
        header('Cache-Control: no-store');
        header('Location: ' . $url, true, 302);
        exit();
    }

    streamRemoteGuideFile($url, $format, $filename, $intent);
}

function handleGuideRedirect($data)
{
    $ctx = careGuideRequestContext($data);
    if (!$ctx['ok']) {
        jsonError($ctx['message'], $ctx['code']);
        return;
    }

    $url = $ctx['url'];

    // If it's a bucket URL (not external), verify it exists before redirecting
    // so we don't dump the user on a raw Supabase 404 XML page.
    if (careGuideSourceType($ctx['guide'], $ctx['format']) !== 'external') {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code == 404) {
            header_remove('Content-Type');
            header('Content-Type: text/html; charset=utf-8');
            http_response_code(404);
            echo '<div style="font-family:sans-serif;text-align:center;padding:50px;color:#333;">';
            echo '<h2 style="margin-bottom:10px;">File Not Uploaded Yet</h2>';
            echo '<p>This guide is listed in the library but the actual file has not been uploaded yet.</p>';
            echo '<p>Please check back later.</p>';
            echo '</div>';
            exit();
        }
    }

    if ($ctx['intent'] === 'read' && $ctx['format'] === 'pdf') {
        $url = withPdfViewerFragment($url, $ctx['mode']);
    }

    header_remove('Content-Type');
    header('Cache-Control: no-store');
    header('Location: ' . $url, true, 302);
    exit();
}
