<?php

namespace Arhx\Improveme\Tests\Unit;

use Arhx\Improveme\Reporting\Channels\GitHubIssueChannel;
use Arhx\Improveme\Reporting\Channels\TelegramChannel;
use Arhx\Improveme\Reporting\ReportData;
use Arhx\Improveme\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChannelsHttpTest extends TestCase
{
    private const TOKEN = '123:abc';

    private function telegram(array $extra = []): TelegramChannel
    {
        return new TelegramChannel($extra + [
            'enabled' => true,
            'token' => self::TOKEN,
            'chat_id' => -1001234567890,
            'parse_mode' => 'HTML',
        ]);
    }

    private function report(?string $screenshot = null): ReportData
    {
        return new ReportData(
            type: 'bug',
            message: 'Broken <button>',
            url: 'https://x.test/page',
            title: 'Page',
            selector: '.btn',
            screenshotPath: $screenshot,
        );
    }

    /** Multipart parts of a recorded request, keyed by part name. */
    private function parts(Request $request): array
    {
        $parts = [];
        foreach ($request->data() as $part) {
            $parts[$part['name']] = $part;
        }

        return $parts;
    }

    public function test_telegram_sends_message_without_screenshot(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $this->telegram(['message_thread_id' => 42])->send($this->report());

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'
                && $request->isForm()
                && $request['chat_id'] === '-1001234567890'
                && $request['message_thread_id'] === '42'
                && $request['parse_mode'] === 'HTML'
                && $request['disable_web_page_preview'] === 'true'
                && str_contains($request['text'], 'Broken &lt;button&gt;')
                && str_contains($request['text'], '<code>.btn</code>');
        });
    }

    public function test_telegram_sends_photo_with_caption_when_screenshot_exists(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []])]);

        $file = tempnam(sys_get_temp_dir(), 'imp');
        file_put_contents($file, 'PNGDATA');

        try {
            $this->telegram()->send($this->report($file));
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }

        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://api.telegram.org/bot'.self::TOKEN.'/sendPhoto' || ! $request->isMultipart()) {
                return false;
            }
            $parts = $this->parts($request);

            return $parts['photo']['contents'] === 'PNGDATA'
                && $parts['photo']['filename'] === 'screenshot.png'
                && $parts['chat_id']['contents'] === '-1001234567890'
                && ! isset($parts['message_thread_id'])
                && str_contains($parts['caption']['contents'], 'Broken &lt;button&gt;');
        });
    }

    public function test_telegram_api_error_is_logged_not_thrown(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request: chat not found'], 400)]);
        Log::spy();

        $this->telegram()->send($this->report());

        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => $msg === 'improveme: telegram delivery failed'
            && $ctx['status'] === 400
            && $ctx['error'] === 'Bad Request: chat not found')->once();
    }

    public function test_telegram_transport_error_does_not_leak_token(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 6 for https://api.telegram.org/bot'.self::TOKEN.'/sendMessage'));
        Log::spy();

        $this->telegram()->send($this->report());

        Log::shouldHaveReceived('warning')->withArgs(fn ($msg, $ctx) => ! str_contains($ctx['error'], self::TOKEN)
            && str_contains($ctx['error'], 'bot***/sendMessage'))->once();
    }

    public function test_telegram_custom_api_url(): void
    {
        Http::fake(['bot.local/*' => Http::response(['ok' => true])]);

        $this->telegram(['api_url' => 'http://bot.local/'])->send($this->report());

        Http::assertSent(fn (Request $r) => $r->url() === 'http://bot.local/bot'.self::TOKEN.'/sendMessage');
    }

    public function test_github_creates_issue_with_labels(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['number' => 1], 201)]);

        (new GitHubIssueChannel([
            'enabled' => true,
            'token' => 'ghp_x',
            'repo' => 'owner/repo',
            'labels' => ['improveme'],
            'label_bug' => 'bug',
        ]))->send($this->report());

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://api.github.com/repos/owner/repo/issues'
                && $request->hasHeader('Authorization', 'Bearer ghp_x')
                && $request['labels'] === ['improveme', 'bug']
                && str_starts_with($request['title'], '🐞 Broken <button>')
                && str_contains($request['body'], '`.btn`');
        });
    }
}
