<?php
/**
 * Deploy build identifier.
 *
 * Returns a token that changes whenever the shipped front-end code changes, so a
 * long-lived client can detect a new deploy and refresh itself. Derived from the
 * modification time of the core bundles, the same signal assetVer() uses.
 */
if (!function_exists('appBuildId')) {
    function appBuildId()
    {
        $roots = [];
        if (!empty($_SERVER['DOCUMENT_ROOT'])) {
            $roots[] = rtrim($_SERVER['DOCUMENT_ROOT'], "/\\") . '/';
        }
        $roots[] = dirname(__DIR__, 2) . '/public/';
        $roots[] = getcwd() . '/public/';

        $assets = ['js/core.js', 'js/api.js', 'css/main.css'];
        $latest = 0;
        foreach ($assets as $rel) {
            foreach ($roots as $root) {
                $path = $root . $rel;
                clearstatcache(true, $path);
                $mtime = @filemtime($path);
                if ($mtime) {
                    if ($mtime > $latest) {
                        $latest = $mtime;
                    }
                    break;
                }
            }
        }
        if (!$latest) {
            $latest = (int) @filemtime(__FILE__);
        }
        return $latest ? (string) $latest : '1';
    }
}
