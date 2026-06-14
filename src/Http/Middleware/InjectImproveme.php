<?php

namespace Arhx\Improveme\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appends the widget snippet right before </body> on HTML responses, giving a
 * zero-template-edit integration. Skips non-HTML, redirects, AJAX/JSON, Inertia
 * visits and the package's own endpoints. Disable with IMPROVEME_INJECT=false to
 * place the Blade directive manually instead.
 */
class InjectImproveme
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->shouldInject($request, $response)) {
            return $response;
        }

        $snippet = view('improveme::widget')->render();
        $content = $response->getContent();

        if ($content === false || ! str_contains($content, '</body>')) {
            return $response;
        }

        // Inject before the LAST </body> so nested templates don't break.
        $pos = strripos($content, '</body>');
        $content = substr($content, 0, $pos).$snippet.substr($content, $pos);

        $response->setContent($content);
        // Length changed — drop any stale Content-Length header.
        $response->headers->remove('Content-Length');

        return $response;
    }

    private function shouldInject(Request $request, Response $response): bool
    {
        if (! config('improveme.enabled', true) || ! config('improveme.inject', true)) {
            return false;
        }

        // Skip XHR/JSON, and Inertia visits explicitly: an Inertia navigation is
        // an XHR carrying an X-Inertia header whose response is a JSON page
        // object, not an HTML document. The widget is injected once into the
        // initial full-page HTML and survives client-side navigation, so it must
        // never be appended to these partial responses.
        if ($request->ajax() || $request->wantsJson() || $request->isJson() || $request->hasHeader('X-Inertia')) {
            return false;
        }

        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            return false;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
            return false;
        }

        if (! $this->audienceAllows($request)) {
            return false;
        }

        // Don't inject into the widget's own responses.
        $prefix = trim((string) config('improveme.prefix', 'improveme'), '/');

        return ! $request->is($prefix.'/*');
    }

    private function audienceAllows(Request $request): bool
    {
        return match (config('improveme.audience', 'all')) {
            'auth' => (bool) $request->user(),
            'guest' => ! $request->user(),
            default => true,
        };
    }
}
