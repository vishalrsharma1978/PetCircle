<?php
// Cache-busting version for local static assets. router.php serves css/js with a
// long, immutable Cache-Control, so without this a browser never re-fetches an
// updated file. Appending the file's modification time as a query string changes
// the URL whenever the file content changes on deploy, forcing a fresh fetch.
if (!function_exists('assetVer')) {
  function assetVer($relPath)
  {
    $rel = ltrim($relPath, '/');
    $candidates = [];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
      $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $rel;
    }
    $candidates[] = dirname(__DIR__) . '/public/' . $rel;
    $candidates[] = getcwd() . '/public/' . $rel; // cwd is chdir'd to repo root
    foreach ($candidates as $path) {
      clearstatcache(true, $path);
      $mtime = @filemtime($path);
      if ($mtime) {
        return (string) $mtime;
      }
    }
    $self = @filemtime(__FILE__);
    return $self ? (string) $self : '1';
  }
}
require_once dirname(__DIR__) . '/api/utils/build_id.php';
?>

<?php
$ogTitle = 'PawCircle';
$ogDescription = 'Find your pack on PawCircle.';
$ogImage = '';

if (!empty($_GET['post'])) {
    $postId = $_GET['post'];
    require_once dirname(__DIR__) . '/api/utils/supabase_client.php';
    
    $envFile = dirname(__DIR__) . '/.env';
    if (file_exists($envFile)) {
        $parsed = @parse_ini_file($envFile);
        if ($parsed) {
            foreach ($parsed as $key => $value) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
    
    $res = supabaseRequest('GET', '/rest/v1/posts', [
        'id' => 'eq.' . $postId,
        'select' => 'content,media_url,users(name)'
    ]);
    
    if (!empty($res['data'][0])) {
        $post = $res['data'][0];
        $authorName = $post['users']['name'] ?? 'Member';
        $ogTitle = 'Post by ' . $authorName;
        if (!empty($post['content'])) {
            $ogDescription = mb_substr(strip_tags($post['content']), 0, 160);
        } else {
            $ogDescription = 'View this post on PawCircle.';
        }
        if (!empty($post['media_url'])) {
            $ogImage = $post['media_url'];
        }
    }
}
?>
<head>
  <meta charset="UTF-8" />
  <script>window.__PAWCIRCLE_BUILD__ = "<?= appBuildId() ?>";</script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PawCircle</title>
  <meta name="description" content="PawCircle is a social platform for pet parents to connect, coordinate playdates, find rescue and volunteer opportunities, and build their pet's pack." />
  <meta property="og:title" content="<?= htmlspecialchars($ogTitle, ENT_QUOTES) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($ogDescription, ENT_QUOTES) ?>" />
  <meta property="og:type" content="website" />
  <?php
    $ogScheme = (($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
    $ogUrl = $ogScheme . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
  ?>
  <meta property="og:url" content="<?= htmlspecialchars($ogUrl, ENT_QUOTES) ?>" />
  <?php if ($ogImage): ?>
  <meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>" />
  <?php endif; ?>
  <link rel="stylesheet" href="css/fonts.css?v=<?= assetVer('css/fonts.css') ?>">
  <link rel="stylesheet" href="css/tailwind.css?v=<?= assetVer('css/tailwind.css') ?>">
  <link rel="stylesheet" href="css/style_0.css?v=<?= assetVer('css/style_0.css') ?>">
  <link rel="stylesheet" href="css/portal.css?v=<?= assetVer('css/portal.css') ?>">
  <link rel="stylesheet" href="css/style_3.css?v=<?= assetVer('css/style_3.css') ?>">
  <script src="https://unpkg.com/lucide@1.22.0/dist/umd/lucide.min.js" defer integrity="sha384-TnesABAZXAtQhqlVENNq1yFPN48F7YMII/Ksrk9CdlFRnT1mQnD+o5BbHXh1P4ne" crossorigin="anonymous"></script>
  <!-- Feature CDN libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" defer integrity="sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer integrity="sha384-3zSEDfvllQohrq0PHL1fOXJuC/jSOO34H46t6UQfobFOmxE5BpjjaIJY5F2/bMnU" crossorigin="anonymous"></script>
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2" defer crossorigin="anonymous" referrerpolicy="strict-origin-when-cross-origin"></script>
  <link rel="stylesheet" href="css/main.css?v=<?= assetVer('css/main.css') ?>">
  <link rel="stylesheet" href="css/admin-theme.css?v=<?= assetVer('css/admin-theme.css') ?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet" integrity="sha384-oMy41mb/qJnpJlpXOF57hSu2KGi47l/UV9+tPNrBOs7/ap5Vubj/3phrCtjutHMQ" crossorigin="anonymous">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js" defer integrity="sha384-r+ljwOAhwY4/kdyzMnuBg7MEVoWpTMp5EYUDntB/E9qzNwL9dAEcNrb2XaV+mJc2" crossorigin="anonymous"></script>
</head>
