<?php

use Arhx\Improveme\Http\Controllers\ReportController;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    | When false the widget is never injected and the routes do nothing.
    | Handy to keep it on in staging and off in production (or vice versa).
    */
    'enabled' => env('IMPROVEME_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Auto-injection
    |--------------------------------------------------------------------------
    | When true a middleware appends the widget snippet right before </body>
    | of every HTML response — zero template edits required. Set it to false
    | and place @improveme (or <x-improveme />) in your layout yourself.
    */
    'inject' => env('IMPROVEME_INJECT', true),

    /*
    |--------------------------------------------------------------------------
    | Who may see the widget
    |--------------------------------------------------------------------------
    | A closure-style gate is overkill for a config file, so this is a simple
    | switch: 'all' shows it to everyone, 'auth' only to logged-in users,
    | 'guest' only to guests. Override the controller/route for finer control.
    */
    'audience' => env('IMPROVEME_AUDIENCE', 'all'),

    /*
    |--------------------------------------------------------------------------
    | Routing
    |--------------------------------------------------------------------------
    | The package registers two routes under this prefix:
    |   GET  {prefix}/widget.js   — the (cached) widget script
    |   POST {prefix}/report      — receives a report
    | Set register_routes=false and define your own to fully take over.
    | Point `controller` at your own class to customise handling only.
    */
    'register_routes' => true,
    'prefix' => env('IMPROVEME_PREFIX', 'improveme'),
    'middleware' => ['web'],
    'controller' => ReportController::class,

    /*
    |--------------------------------------------------------------------------
    | Widget appearance & behaviour (passed through to the JS)
    |--------------------------------------------------------------------------
    */
    'widget' => [
        // bottom-right | bottom-left | top-right | top-left
        'position' => env('IMPROVEME_POSITION', 'bottom-right'),
        'accent' => env('IMPROVEME_ACCENT', '#ff5a36'),     // icon / primary button
        'hover_color' => env('IMPROVEME_HOVER_COLOR', '#3b82f6'),   // element hover outline
        'selected_color' => env('IMPROVEME_SELECTED_COLOR', '#22c55e'), // picked element outline
        'z_index' => (int) env('IMPROVEME_Z_INDEX', 2147483000),
        'screenshot_padding' => (int) env('IMPROVEME_SCREENSHOT_PADDING', 15), // px around selection
        'max_html' => (int) env('IMPROVEME_MAX_HTML', 20000), // truncate captured outerHTML
        // CDN for the screenshot engine (lazy-loaded only when a screenshot is
        // taken). Defaults to html2canvas-pro — a maintained html2canvas fork
        // that supports modern CSS colour functions (oklch/lab/color()), which
        // Tailwind 4 emits for every colour. It exposes the same `html2canvas`
        // global, so the original library works too if you point this back at it.
        'html2canvas_url' => env(
            'IMPROVEME_HTML2CANVAS_URL',
            'https://cdn.jsdelivr.net/npm/html2canvas-pro@1.5.11/dist/html2canvas-pro.min.js'
        ),
        'labels' => [
            'title' => 'Send feedback',
            'placeholder' => 'Describe the bug or your idea…',
            'type_bug' => 'Bug',
            'type_idea' => 'Idea',
            'pick' => 'Pick an element',
            'picking' => 'Click a block · scroll-click to go behind · Esc to finish',
            'send' => 'Send',
            'sent' => 'Thanks! Your feedback was sent.',
            'error' => 'Could not send — please try again.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Channels — where reports go
    |--------------------------------------------------------------------------
    | Every report is always written to the `log` channel. Telegram fires too
    | when a bot token + chat id are configured; if not, it is silently
    | skipped, so a fresh install works out of the box (log only).
    */
    'channels' => [

        'log' => [
            'enabled' => env('IMPROVEME_LOG_ENABLED', true),
            // Stored under storage/logs/ by default.
            'path' => storage_path('logs/improveme.log'),
            'level' => env('IMPROVEME_LOG_LEVEL', 'info'),
        ],

        'telegram' => [
            'enabled' => env('IMPROVEME_TELEGRAM_ENABLED', true),
            'token' => env('IMPROVEME_TELEGRAM_TOKEN'),
            'chat_id' => env('IMPROVEME_TELEGRAM_CHAT_ID'),
            // Optional: send into a forum topic.
            'message_thread_id' => env('IMPROVEME_TELEGRAM_THREAD_ID'),
            // 'HTML' or 'MarkdownV2'
            'parse_mode' => 'HTML',
        ],

        // Files a GitHub issue per report so the feedback lands right in the
        // repo's tracker — ideal for an AI agent (or a human) to read and act
        // on. Off by default; needs a token with issues:write + the repo slug.
        'github' => [
            'enabled' => env('IMPROVEME_GITHUB_ENABLED', false),
            // Classic PAT with `repo`, or a fine-grained PAT with Issues: R/W.
            'token' => env('IMPROVEME_GITHUB_TOKEN'),
            // Target repository as "owner/repo".
            'repo' => env('IMPROVEME_GITHUB_REPO'),
            // Labels added to every issue (missing ones are created by GitHub).
            'labels' => ['improveme'],
            // Per-type labels appended on top of the shared ones.
            'label_bug' => env('IMPROVEME_GITHUB_LABEL_BUG', 'bug'),
            'label_idea' => env('IMPROVEME_GITHUB_LABEL_IDEA', 'enhancement'),
            // Override for GitHub Enterprise (e.g. https://ghe.example.com/api/v3).
            'api_url' => env('IMPROVEME_GITHUB_API_URL', 'https://api.github.com'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Screenshot storage
    |--------------------------------------------------------------------------
    | Incoming screenshots are decoded and stored here so the log entry can
    | reference a file and Telegram can upload it. Uses Laravel's filesystem.
    */
    'storage' => [
        'disk' => env('IMPROVEME_DISK', 'local'),
        'dir' => env('IMPROVEME_DIR', 'improveme'),
        'keep_screenshots' => env('IMPROVEME_KEEP_SCREENSHOTS', true),
    ],
];
