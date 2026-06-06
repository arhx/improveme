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
        $path = __DIR__.'/../../../resources/js/improveme.js';
        $js = is_file($path) ? (string) file_get_contents($path) : '/* improveme: widget asset missing */';

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
