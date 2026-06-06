<?php

namespace Arhx\Improveme\Reporting;

use Arhx\Improveme\Reporting\Channels\Channel;
use Arhx\Improveme\Reporting\Channels\LogChannel;
use Arhx\Improveme\Reporting\Channels\TelegramChannel;

/**
 * Builds the configured channels and fans a report out to every enabled one.
 * The log channel is always present; Telegram joins only when configured.
 */
class ReportDispatcher
{
    public function __construct(private array $config) {}

    public function dispatch(ReportData $report): void
    {
        foreach ($this->channels() as $channel) {
            if ($channel->enabled()) {
                $channel->send($report);
            }
        }
    }

    /** @return Channel[] */
    public function channels(): array
    {
        $channels = $this->config['channels'] ?? [];

        return [
            new LogChannel($channels['log'] ?? []),
            new TelegramChannel($channels['telegram'] ?? []),
        ];
    }
}
