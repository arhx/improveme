<?php

namespace Arhx\Improveme\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Serves the self-contained widget script. The script is fully static — all
 * per-request configuration (endpoint, csrf token, colours, labels) is read
 * from `window.__IMPROVEME__`, which the blade snippet renders inline — so the
 * file can be cached aggressively.
 */
class WidgetController
{
    public function script(): Response
    {
        $path = self::assetPath();
        $js = is_file($path) ? (string) file_get_contents($path) : '/* improveme: widget asset missing */';

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => '"'.self::assetVersion().'"',
        ]);
    }

    /**
     * Absolute path to the bundled widget script.
     */
    public static function assetPath(): string
    {
        return __DIR__.'/../../../resources/js/improveme.js';
    }

    /**
     * Short content hash used to cache-bust the script URL. Because the widget
     * is served from a version-less route with a long max-age, browsers would
     * otherwise keep a stale copy for up to a day after an upgrade; appending
     * ?v={hash} makes each release fetch fresh while staying cacheable.
     */
    public static function assetVersion(): string
    {
        $path = self::assetPath();

        return is_file($path) ? substr(md5_file($path), 0, 10) : 'dev';
    }
}
