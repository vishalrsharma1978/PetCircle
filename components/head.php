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

// Share-link OG tags. This block runs with NO session and the Supabase secret
// key, so it can never establish group membership — see the group_id branch
// below. The uuid guard also stops $_GET['post'] being interpolated straight
// into a PostgREST filter.
if (!empty($_GET['post']) && preg_match('/^[0-9a-fA-F-]{36}$/', $_GET['post'])) {
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
        'select' => 'content,media_url,group_id,is_deleted,is_archived,users(name)'
    ]);

    if (!empty($res['data'][0])) {
        $post = $res['data'][0];
        // Group posts are members-only, and this runs unauthenticated — so it
        // must emit nothing post-specific, or a leaked/guessed id would put a
        // members-only post's text and media URL into public page meta.
        // Deleted and archived posts get the same treatment (previously they
        // were unfiltered here too).
        $isShareable = empty($post['group_id']) && empty($post['is_deleted']) && empty($post['is_archived']);
        if ($isShareable) {
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
  <!-- Baloo 2 was declared as --font-display in portal.css from the start but
       never actually loaded anywhere, so it silently fell back to the default
       sans this whole time. Loading it for real here (CSP already allowlists
       fonts.googleapis.com/fonts.gstatic.com) fixes that for the existing
       storybook signup page too, not just the new playful views. -->
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&display=swap">
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
  <link rel="stylesheet" href="css/auth_v2.css?v=<?= assetVer('css/auth_v2.css') ?>">
  <link rel="stylesheet" href="css/auth_mascots.css?v=<?= assetVer('css/auth_mascots.css') ?>">
  <link rel="stylesheet" href="css/auth_scene.css?v=<?= assetVer('css/auth_scene.css') ?>">
  <!-- Sign-up's night-den variant. Must follow auth_scene.css, whose park
       defaults it overrides. -->
  <link rel="stylesheet" href="css/auth_den.css?v=<?= assetVer('css/auth_den.css') ?>">
  <!-- Motion for the logged-in app (see public/js/motion.js). Loaded after
       main.css so its tab-underline and pack-tree rules win on specificity
       ties with the component classes they replace. -->
  <link rel="stylesheet" href="css/motion.css?v=<?= assetVer('css/motion.css') ?>">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" rel="stylesheet" integrity="sha384-oMy41mb/qJnpJlpXOF57hSu2KGi47l/UV9+tPNrBOs7/ap5Vubj/3phrCtjutHMQ" crossorigin="anonymous">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js" defer integrity="sha384-r+ljwOAhwY4/kdyzMnuBg7MEVoWpTMp5EYUDntB/E9qzNwL9dAEcNrb2XaV+mJc2" crossorigin="anonymous"></script>
</head>
