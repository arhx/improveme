<?php

namespace Arhx\Improveme\Reporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Normalised, transport-agnostic representation of one feedback report.
 *
 * Built from the incoming request once, then handed to every channel so a
 * channel never has to re-parse raw input.
 */
class ReportData
{
    public function __construct(
        public string $type,            // 'bug' | 'idea'
        public string $message,
        public ?string $url = null,
        public ?string $title = null,
        public ?string $selector = null,
        public ?string $elementTag = null,
        public ?string $html = null,
        public ?array $viewport = null,  // ['w'=>, 'h'=>, 'dpr'=>]
        public ?string $userAgent = null,
        public ?string $ip = null,       // request IP address
        public ?array $user = null,      // ['id'=>] — only Auth::id(), no PII
        public ?array $consoleErrors = null, // browser console / JS errors, strings
        public ?string $screenshotPath = null, // absolute fs path once stored
        public ?array $raw = null,       // original payload, for custom channels
    ) {}

    public static function fromRequest(Request $request): self
    {
        $type = $request->input('type') === 'bug' ? 'bug' : 'idea';
        $el = (array) $request->input('element', []);
        $vp = (array) $request->input('viewport', []);

        // Only the bare authenticated id — no name/email, to keep reports PII-light.
        $id = Auth::id();
        $user = $id !== null ? ['id' => $id] : null;

        return new self(
            type: $type,
            message: trim((string) $request->input('message', '')),
            url: $request->input('page.url') ?: $request->input('url'),
            title: $request->input('page.title'),
            selector: $el['selector'] ?? null,
            elementTag: $el['tag'] ?? null,
            html: $el['html'] ?? null,
            viewport: $vp ?: null,
            userAgent: $request->input('userAgent') ?: $request->userAgent(),
            ip: $request->ip(),
            user: $user,
            consoleErrors: self::consoleErrors($request),
            raw: $request->all(),
        );
    }

    /**
     * Normalise the client-collected console/JS errors into a capped list of
     * trimmed strings, ignoring anything blank or malformed.
     */
    private static function consoleErrors(Request $request): ?array
    {
        $out = [];
        foreach ((array) $request->input('consoleErrors', []) as $e) {
            $text = is_array($e) ? (string) ($e['text'] ?? '') : (string) $e;
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $out[] = mb_substr($text, 0, 2000);
            if (count($out) >= 50) {
                break;
            }
        }

        return $out ?: null;
    }

    public function typeLabel(): string
    {
        return $this->type === 'bug' ? '🐞 Bug' : '💡 Idea';
    }

    public function hasScreenshot(): bool
    {
        return $this->screenshotPath !== null && is_file($this->screenshotPath);
    }
}
