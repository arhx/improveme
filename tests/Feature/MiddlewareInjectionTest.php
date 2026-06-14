<?php

namespace Arhx\Improveme\Tests\Feature;

use Arhx\Improveme\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class MiddlewareInjectionTest extends TestCase
{
    private function htmlRoute(string $uri = '/_im/page'): void
    {
        Route::middleware('web')->get($uri, fn () => response(
            '<html><head></head><body><h1>Hi</h1></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ));
    }

    public function test_it_injects_the_widget_before_body_on_html_responses(): void
    {
        $this->htmlRoute();

        $res = $this->get('/_im/page');

        $res->assertOk();
        $content = $res->getContent();
        $this->assertStringContainsString('window.__IMPROVEME__', $content);
        // Snippet must land before the closing body tag.
        $this->assertLessThan(strripos($content, '</body>'), strpos($content, 'window.__IMPROVEME__'));
    }

    public function test_injected_boot_payload_carries_endpoint_and_xsrf_cookie(): void
    {
        $this->htmlRoute();

        $content = $this->get('/_im/page')->getContent();

        $this->assertStringContainsString('"endpoint"', $content);
        $this->assertStringContainsString('"xsrfCookie"', $content);
    }

    public function test_it_does_not_inject_into_json_responses(): void
    {
        Route::middleware('web')->get('/_im/json', fn () => response()->json(['a' => 1]));

        $content = $this->get('/_im/json')->getContent();

        $this->assertStringNotContainsString('__IMPROVEME__', $content);
    }

    public function test_it_does_not_inject_into_inertia_visits(): void
    {
        $this->htmlRoute('/_im/inertia');

        // An Inertia navigation carries the X-Inertia header.
        $content = $this->get('/_im/inertia', ['X-Inertia' => 'true'])->getContent();

        $this->assertStringNotContainsString('__IMPROVEME__', $content);
    }

    public function test_it_does_not_inject_into_ajax_requests(): void
    {
        $this->htmlRoute('/_im/ajax');

        $content = $this->get('/_im/ajax', ['X-Requested-With' => 'XMLHttpRequest'])->getContent();

        $this->assertStringNotContainsString('__IMPROVEME__', $content);
    }

    public function test_it_skips_injection_when_disabled_at_runtime(): void
    {
        config()->set('improveme.enabled', false);
        $this->htmlRoute('/_im/disabled');

        $content = $this->get('/_im/disabled')->getContent();

        $this->assertStringNotContainsString('__IMPROVEME__', $content);
    }

    public function test_audience_auth_hides_widget_from_guests(): void
    {
        config()->set('improveme.audience', 'auth');
        $this->htmlRoute('/_im/auth-only');

        $content = $this->get('/_im/auth-only')->getContent();

        $this->assertStringNotContainsString('__IMPROVEME__', $content);
    }
}
