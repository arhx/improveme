<?php

namespace Arhx\Improveme\Reporting;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Decodes an incoming `data:` screenshot URL and persists it to the configured
 * filesystem disk, returning the absolute path (so Telegram can upload it and
 * the log can reference it).
 */
class ScreenshotStore
{
    public function __construct(private array $config) {}

    /** @return string|null absolute filesystem path, or null if nothing stored */
    public function store(?string $dataUrl): ?string
    {
        if (! $dataUrl || ! str_starts_with($dataUrl, 'data:image/')) {
            return null;
        }

        [$meta, $encoded] = array_pad(explode(',', $dataUrl, 2), 2, null);
        if ($encoded === null) {
            return null;
        }

        $binary = base64_decode($encoded, true);
        if ($binary === false || $binary === '') {
            return null;
        }

        $ext = str_contains($meta, 'image/jpeg') ? 'jpg' : 'png';
        $disk = Storage::disk($this->config['disk'] ?? 'local');
        $dir = trim($this->config['dir'] ?? 'improveme', '/');
        $path = $dir.'/'.now()->format('Y/m/d').'/'.Str::uuid()->toString().'.'.$ext;

        $disk->put($path, $binary);

        // Channels need a real filesystem path (Telegram upload, log reference).
        return method_exists($disk, 'path') ? $disk->path($path) : null;
    }
}
