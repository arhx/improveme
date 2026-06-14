<?php

namespace Arhx\Improveme\Tests\Feature;

use Arhx\Improveme\Tests\TestCase;

class WidgetRouteTest extends TestCase
{
    public function test_it_serves_the_widget_javascript(): void
    {
        $res = $this->get(route('improveme.widget'));

        $res->assertOk();
        $this->assertStringContainsString('application/javascript', (string) $res->headers->get('Content-Type'));
        $this->assertStringContainsString('__IMPROVEME__', $res->getContent());
    }
}
