<?php

namespace Arhx\Improveme\Tests\Unit;

use Arhx\Improveme\Reporting\ReportData;
use Arhx\Improveme\Tests\TestCase;
use Illuminate\Http\Request;

class ReportDataTest extends TestCase
{
    public function test_it_maps_request_fields(): void
    {
        $request = Request::create('/x', 'POST', [
            'type' => 'bug',
            'message' => '  hello world  ',
            'page' => ['url' => 'https://x.test/p', 'title' => 'Title'],
            'element' => ['selector' => '.foo', 'tag' => 'div', 'html' => '<div></div>'],
            'viewport' => ['w' => 100, 'h' => 200, 'dpr' => 2],
        ]);

        $data = ReportData::fromRequest($request);

        $this->assertSame('bug', $data->type);
        $this->assertSame('hello world', $data->message); // trimmed
        $this->assertSame('https://x.test/p', $data->url);
        $this->assertSame('Title', $data->title);
        $this->assertSame('.foo', $data->selector);
        $this->assertSame('div', $data->elementTag);
        $this->assertSame(['w' => 100, 'h' => 200, 'dpr' => 2], $data->viewport);
    }

    public function test_unknown_type_falls_back_to_idea(): void
    {
        $request = Request::create('/x', 'POST', ['type' => 'whatever', 'message' => 'm']);

        $this->assertSame('idea', ReportData::fromRequest($request)->type);
    }

    public function test_it_normalises_and_caps_console_errors(): void
    {
        $request = Request::create('/x', 'POST', [
            'message' => 'm',
            'consoleErrors' => [
                ['level' => 'error', 'text' => 'boom'],
                ['text' => ''],          // dropped — blank
                'plain string error',     // accepted — bare string
            ],
        ]);

        $data = ReportData::fromRequest($request);

        $this->assertSame(['boom', 'plain string error'], $data->consoleErrors);
    }

    public function test_console_errors_null_when_empty(): void
    {
        $request = Request::create('/x', 'POST', ['message' => 'm']);

        $this->assertNull(ReportData::fromRequest($request)->consoleErrors);
    }
}
