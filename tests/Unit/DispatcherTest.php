<?php

namespace Arhx\Improveme\Tests\Unit;

use Arhx\Improveme\Reporting\Channels\GitHubIssueChannel;
use Arhx\Improveme\Reporting\Channels\LogChannel;
use Arhx\Improveme\Reporting\Channels\TelegramChannel;
use Arhx\Improveme\Reporting\ReportDispatcher;
use Arhx\Improveme\Tests\TestCase;

class DispatcherTest extends TestCase
{
    public function test_it_builds_log_telegram_and_github_channels(): void
    {
        $channels = (new ReportDispatcher(['channels' => []]))->channels();

        $this->assertCount(3, $channels);
        $this->assertInstanceOf(LogChannel::class, $channels[0]);
        $this->assertInstanceOf(TelegramChannel::class, $channels[1]);
        $this->assertInstanceOf(GitHubIssueChannel::class, $channels[2]);
    }

    public function test_telegram_and_github_are_disabled_without_credentials(): void
    {
        $config = [
            'channels' => [
                'log' => ['enabled' => true, 'path' => storage_path('logs/x.log')],
                'telegram' => ['enabled' => true], // no token/chat_id
                'github' => ['enabled' => true],   // no token/repo
            ],
        ];

        [$log, $telegram, $github] = (new ReportDispatcher($config))->channels();

        $this->assertTrue($log->enabled());
        $this->assertFalse($telegram->enabled());
        $this->assertFalse($github->enabled());
    }
}
