<?php

namespace Arhx\Improveme\Reporting\Channels;

use Arhx\Improveme\Reporting\ReportData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Opens a GitHub issue for each report via the REST API. Designed so an AI
 * coding agent (or a human) can read incoming feedback straight from the repo's
 * issue tracker — title, page URL, picked selector/HTML, browser console errors
 * and screenshot reference all land in the issue body, labelled bug/idea.
 *
 * Needs a token with `issues:write` on the target repo (a classic PAT with the
 * `repo` scope, or a fine-grained PAT scoped to Issues: read & write). Disabled
 * automatically when no token + repo are configured. Never throws — a transport
 * failure is logged and swallowed so the user-facing request still succeeds.
 */
class GitHubIssueChannel implements Channel
{
    // GitHub issue titles are capped at 256 chars; keep ours short and readable.
    private const TITLE_LIMIT = 120;

    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false)
            && ! empty($this->config['token'])
            && ! empty($this->config['repo']);
    }

    public function send(ReportData $report): void
    {
        try {
            $api = rtrim($this->config['api_url'] ?? 'https://api.github.com', '/');
            $repo = trim((string) $this->config['repo'], '/');

            $response = Http::withToken($this->config['token'])
                ->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(15)
                ->post("{$api}/repos/{$repo}/issues", [
                    'title' => $this->title($report),
                    'body' => $this->body($report),
                    'labels' => $this->labels($report),
                ]);

            if ($response->failed()) {
                Log::warning('improveme: github issue creation failed', [
                    'status' => $response->status(),
                    'body' => $response->json('message') ?? $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('improveme: github delivery failed', ['error' => $e->getMessage()]);
        }
    }

    private function title(ReportData $report): string
    {
        $prefix = $report->type === 'bug' ? '🐞 ' : '💡 ';
        // First line of the message keeps the title scannable in the issue list.
        $first = trim(strtok($report->message, "\n") ?: $report->message);
        $body = mb_strlen($first) > self::TITLE_LIMIT
            ? mb_substr($first, 0, self::TITLE_LIMIT - 1).'…'
            : $first;

        return $prefix.($body !== '' ? $body : 'Feedback');
    }

    /** @return string[] */
    private function labels(ReportData $report): array
    {
        $labels = (array) ($this->config['labels'] ?? []);

        $typeLabel = $report->type === 'bug'
            ? ($this->config['label_bug'] ?? null)
            : ($this->config['label_idea'] ?? null);
        if ($typeLabel) {
            $labels[] = $typeLabel;
        }

        return array_values(array_unique(array_filter(array_map('strval', $labels))));
    }

    private function body(ReportData $report): string
    {
        $type = $report->type === 'bug' ? '🐞 Bug' : '💡 Idea';
        $lines = [];
        $lines[] = '**Type:** '.$type;
        $lines[] = '';
        $lines[] = $report->message !== '' ? $report->message : '_(no description)_';
        $lines[] = '';
        $lines[] = '---';

        $meta = [];
        if ($report->url) {
            $title = $report->title ?: $report->url;
            $meta[] = '- **Page:** ['.$this->mdInline($title).']('.$report->url.')';
        }
        if ($report->selector) {
            $meta[] = '- **Selector:** `'.$this->mdCode($report->selector).'`';
        }
        if ($report->elementTag) {
            $meta[] = '- **Element:** `<'.$this->mdCode($report->elementTag).'>`';
        }
        if ($report->viewport) {
            $vp = $report->viewport;
            $meta[] = '- **Viewport:** '.($vp['w'] ?? '?').'×'.($vp['h'] ?? '?');
        }
        if ($report->user) {
            $meta[] = '- **User:** #'.($report->user['id'] ?? '?');
        }
        if ($report->ip) {
            $meta[] = '- **IP:** `'.$this->mdCode($report->ip).'`';
        }
        if ($report->userAgent) {
            $meta[] = '- **User-Agent:** `'.$this->mdCode($report->userAgent).'`';
        }
        if ($meta) {
            $lines[] = implode("\n", $meta);
        }

        if ($report->consoleErrors) {
            $lines[] = '';
            $lines[] = '<details><summary>⚠️ Console errors ('.count($report->consoleErrors).')</summary>';
            $lines[] = '';
            $lines[] = '```text';
            $lines[] = $this->fence(implode("\n", $report->consoleErrors));
            $lines[] = '```';
            $lines[] = '</details>';
        }

        if ($report->html) {
            $lines[] = '';
            $lines[] = '<details><summary>🧩 Element HTML</summary>';
            $lines[] = '';
            $lines[] = '```html';
            $lines[] = $this->fence($report->html);
            $lines[] = '```';
            $lines[] = '</details>';
        }

        if ($report->hasScreenshot()) {
            $lines[] = '';
            $lines[] = '> 📸 Screenshot stored on the server: `'.$report->screenshotPath.'`';
        }

        $lines[] = '';
        $lines[] = '<sub>filed automatically by improveme</sub>';

        return implode("\n", $lines);
    }

    /** Neutralise markdown control chars in inline link text. */
    private function mdInline(string $v): string
    {
        return str_replace([']', '['], [')', '('], $v);
    }

    /** Keep backticked spans from breaking out of their code span. */
    private function mdCode(string $v): string
    {
        return str_replace('`', '', $v);
    }

    /** Stop a payload from prematurely closing its fenced code block. */
    private function fence(string $v): string
    {
        return str_replace('```', '`‌`‌`', $v);
    }
}
