<?php

namespace Arhx\Improveme\Tests;

use Arhx\Improveme\ImprovemeServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ImprovemeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Keep the always-on log channel out of the real storage tree.
        $app['config']->set('improveme.channels.log.path', storage_path('logs/improveme-test.log'));

        // No external creds in tests — Telegram/GitHub stay disabled by default.
        $app['config']->set('improveme.channels.telegram.enabled', false);
        $app['config']->set('improveme.channels.github.enabled', false);
    }
}
