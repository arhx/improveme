<?php

namespace Arhx\Improveme\Reporting\Channels;

use Arhx\Improveme\Reporting\ReportData;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\FileUpload\InputFile;

/**
 * Sends the report to a Telegram chat via irazasyed/telegram-bot-sdk's bare
 * Api client (no Laravel service provider / no extra infrastructure).
 *
 * A screenshot, when present, is uploaded as a photo with the report as its
 * caption; otherwise a plain text message is sent. Disabled automatically
 * when no bot token + chat id are configured.
 */
class TelegramChannel implements Channel
{
    // Telegram limits: 1024 chars for a photo caption, 4096 for a message.
    private const CAPTION_LIMIT = 1000;

    private const MESSAGE_LIMIT = 4000;

    public function __construct(private array $config) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false)
            && ! empty($this->config['token'])
            && ! empty($this->config['chat_id']);
    }

    public function send(ReportData $report): void
    {
        try {
            $api = new Api($this->config['token']);
            $chatId = $this->config['chat_id'];
            $parseMode = $this->config['parse_mode'] ?? 'HTML';

            $base = [
                'chat_id' => $chatId,
                'parse_mode' => $parseMode,
            ];
            if (! empty($this->config['message_thread_id'])) {
                $base['message_thread_id'] = $this->config['message_thread_id'];
            }

            if ($report->hasScreenshot()) {
                $api->sendPhoto($base + [
                    'photo' => InputFile::create($report->screenshotPath, 'screenshot.png'),
                    'caption' => $this->text($report, self::CAPTION_LIMIT),
                ]);
            } else {
                $api->sendMessage($base + [
                    'text' => $this->text($report, self::MESSAGE_LIMIT),
                    'disable_web_page_preview' => true,
                ]);
            }
        } catch (\Throwable $e) {
            // Never let a transport failure bubble up to the user-facing request.
            Log::warning('improveme: telegram delivery failed', ['error' => $e->getMessage()]);
        }
    }

    private function text(ReportData $report, int $limit): string
    {
        $lines = [];
        $lines[] = '<b>'.$report->typeLabel().'</b>';
        $lines[] = $this->escape($report->message);

        $meta = [];
        if ($report->url) {
            $meta[] = '🔗 <a href="'.$this->escape($report->url).'">'.$this->escape($report->title ?: $report->url).'</a>';
        }
        if ($report->selector) {
            $meta[] = '🎯 <code>'.$this->escape($report->selector).'</code>';
        }
        if ($report->user) {
            $meta[] = '👤 #'.$this->escape((string) ($report->user['id'] ?? '?'));
        }
        if ($report->ip) {
            $meta[] = '🌐 <code>'.$this->escape($report->ip).'</code>';
        }
        if ($report->viewport) {
            $vp = $report->viewport;
            $meta[] = '🖥 '.($vp['w'] ?? '?').'×'.($vp['h'] ?? '?');
        }
        if ($report->userAgent) {
            $meta[] = '🧭 <code>'.$this->escape($report->userAgent).'</code>';
        }
        if ($meta) {
            $lines[] = '';
            $lines[] = implode("\n", $meta);
        }

        $text = implode("\n", $lines);

        // The console-errors block is the most likely to overflow, and naive
        // truncation could slice a <pre> tag in half and break Telegram's HTML
        // parser. So size it to whatever room is left and always close the tag.
        if ($report->consoleErrors) {
            $header = "\n\n⚠️ <b>Console errors</b> (".count($report->consoleErrors)."):\n<pre>";
            $footer = '</pre>';
            $room = $limit - mb_strlen($text) - mb_strlen($header) - mb_strlen($footer);
            if ($room > 0) {
                $body = $this->escape(implode("\n", $report->consoleErrors));
                if (mb_strlen($body) > $room) {
                    $body = mb_substr($body, 0, $room - 1).'…';
                }
                $text .= $header.$body.$footer;
            }
        }

        if (mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit - 1).'…';
        }

        return $text;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
