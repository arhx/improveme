<?php

namespace Arhx\Improveme\Tests\Feature;

use Arhx\Improveme\Tests\TestCase;

class ReportControllerTest extends TestCase
{
    public function test_it_accepts_a_valid_report_and_responds_with_json(): void
    {
        $res = $this->postJson(route('improveme.report'), [
            'type' => 'bug',
            'message' => 'Something is broken on this page',
            'page' => ['url' => 'https://app.test/dashboard', 'title' => 'Dashboard'],
        ]);

        $res->assertOk()->assertExactJson(['ok' => true]);
    }

    public function test_it_returns_422_json_on_validation_failure(): void
    {
        // No message — the only required field.
        $res = $this->postJson(route('improveme.report'), ['type' => 'bug']);

        $res->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonStructure(['ok', 'errors' => ['message']]);
    }

    public function test_it_returns_404_when_disabled(): void
    {
        config()->set('improveme.enabled', false);

        $res = $this->postJson(route('improveme.report'), ['message' => 'hello there']);

        $res->assertStatus(404)->assertJsonPath('ok', false);
    }

    public function test_report_endpoint_never_redirects(): void
    {
        // Inertia/SPA clients fetch() the endpoint and expect JSON, never a 302.
        $res = $this->postJson(route('improveme.report'), ['message' => 'plain feedback']);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));
    }
}
