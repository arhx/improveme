<?php

namespace Arhx\Improveme\Tests\Unit;

use Arhx\Improveme\Reporting\ScreenshotStore;
use Arhx\Improveme\Tests\TestCase;
use Illuminate\Support\Facades\Storage;

class ScreenshotStoreTest extends TestCase
{
    // 1×1 transparent PNG.
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    public function test_it_stores_a_data_url_and_returns_a_real_path(): void
    {
        Storage::fake('local');

        $store = new ScreenshotStore(['disk' => 'local', 'dir' => 'improveme']);
        $path = $store->store('data:image/png;base64,'.self::PNG);

        $this->assertNotNull($path);
        $this->assertFileExists($path);
    }

    public function test_it_returns_null_for_missing_or_invalid_input(): void
    {
        $store = new ScreenshotStore([]);

        $this->assertNull($store->store(null));
        $this->assertNull($store->store(''));
        $this->assertNull($store->store('not-a-data-url'));
    }
}
