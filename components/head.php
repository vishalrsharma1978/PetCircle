<?php
if (!function_exists('assetVer')) {
  function assetVer($relPath)
  {
    $rel = ltrim($relPath, '/');
    $candidates = [];
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
      $candidates[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/' . $rel;
    }
    $candidates[] = dirname(__DIR__) . '/' . $rel;
    $candidates[] = getcwd() . '/' . $rel; 
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
?>

  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>PawCircle Portal</title>
  <link href="https://fonts.googleapis.com/css2?pack=Inter:wght@400;500;600&pack=Poppins:wght@600;700&display=swap"
    rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <!-- Feature CDN libraries -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
  <!-- <link rel="stylesheet" href="https://source.zoom.us/6.1.0/css/bootstrap.css" />
  <link rel="stylesheet" href="https://source.zoom.us/6.1.0/css/react-select.css" /> -->

  <link rel="stylesheet" href="css/style_0.css?v=<?= assetVer('public/css/style_0.css') ?>">
  <link rel="stylesheet" href="css/main.css?v=<?= assetVer('public/css/main.css') ?>">
  <link rel="stylesheet" href="css/portal.css?v=<?= assetVer('public/css/portal.css') ?>">
  <link rel="stylesheet" href="css/style_3.css?v=<?= assetVer('public/css/style_3.css') ?>">
  <link rel="stylesheet" href="css/admin-theme.css?v=<?= assetVer('public/css/admin-theme.css') ?>">

  <script>
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            brand: {
              50: "var(--brand-50, #fff5f5)",
              100: "var(--brand-100, #ffd45a)",
              200: "var(--brand-200, #ffa95a)",
              300: "var(--brand-300, #ff8b5a)",
              400: "var(--brand-400, #ff5a5a)",
              500: "var(--brand-500, #e04848)",
              900: "var(--brand-900, #7a2222)",
            },
          },
          fontPack: { sans: ["Inter", "sans-serif"], serif: ["Poppins", "sans-serif"], heading: ["Poppins", "sans-serif"] },
        },
      },
    };
  </script>
