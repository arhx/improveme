<?php

namespace Arhx\Improveme\Reporting\Channels;

use Arhx\Improveme\Reporting\ReportData;
use Illuminate\Support\Facades\Log;

/**
 * Always-on fallback channel: appends a structured line to a dedicated log
 * file (storage/logs/improveme.log by default). Builds its own single-file
 * logger so the host app does not need to register a logging channel.
 */
class LogChannel implements Channel
{
    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    public function send(ReportData $report): void
    {
        $logger = Log::build([
            'driver' => 'single',
            'path' => $this->config['path'],
            'level' => $this->config['level'] ?? 'info',
        ]);

        $logger->info('improveme report', [
            'type' => $report->type,
            'message' => $report->message,
            'url' => $report->url,
            'title' => $report->title,
            'selector' => $report->selector,
            'element' => $report->elementTag,
            'viewport' => $report->viewport,
            'user' => $report->user,
            'user_agent' => $report->userAgent,
            'screenshot' => $report->hasScreenshot() ? $report->screenshotPath : null,
            'html' => $report->html,
        ]);
    }
}
