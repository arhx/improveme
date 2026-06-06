<?php

namespace Arhx\Improveme\Reporting\Channels;

use Arhx\Improveme\Reporting\ReportData;

interface Channel
{
    /** Whether this channel is configured and should run. */
    public function enabled(): bool;

    /** Deliver the report. Implementations must not throw on transport errors. */
    public function send(ReportData $report): void;
}
