# improveme

A drop-in **feedback & bug-report widget** for Laravel. It adds a floating 🐞
icon to your site; clicking it lets a user write a bug report or a suggestion,
visually **pick the related block on the page** (with a drill-down picker that
lets you "see through" to the element behind), and ships the report — its
text, a CSS selector, the element's HTML and a **screenshot of the area** — to
**Telegram** and/or a **log file**.

- Zero front-end build step — the widget is plain JS served by a route.
- Works out of the box: with no Telegram config it just logs to a file.
- Auto-injects into your pages, or place it manually with `@improveme`.
- Fully publishable config; rename the env vars; override the route/controller.

## Requirements

- PHP 8.2+
- Laravel 10, 11, 12 or 13

## Install

```bash
composer require arhx/improveme
```

That's it. The service provider is auto-discovered and, by default, the widget
**auto-injects** before `</body>` on every HTML page. Open any page and you'll
see the floating icon in the bottom-right corner.

> Reports are written to `storage/logs/improveme.log` immediately, even before
> you configure Telegram.

## Configure Telegram (optional)

Add to your `.env`:

```dotenv
IMPROVEME_TELEGRAM_TOKEN=123456:ABC-your-bot-token
IMPROVEME_TELEGRAM_CHAT_ID=-1001234567890
```

Now every report is sent to that chat (screenshot as a photo with the report
as caption). Leave them unset and only the log channel runs.

To send into a forum topic: `IMPROVEME_TELEGRAM_THREAD_ID=42`.

## Manual placement

Prefer to control exactly where the snippet renders? Turn off auto-injection
and drop the directive in your layout:

```dotenv
IMPROVEME_INJECT=false
```

```blade
<body>
  ...
  @improveme
</body>
```

## Common env switches

| Env | Default | Meaning |
| --- | --- | --- |
| `IMPROVEME_ENABLED` | `true` | Master on/off switch. |
| `IMPROVEME_INJECT` | `true` | Auto-inject before `</body>`. |
| `IMPROVEME_AUDIENCE` | `all` | `all` \| `auth` \| `guest`. |
| `IMPROVEME_POSITION` | `bottom-right` | Icon corner. |
| `IMPROVEME_ACCENT` | `#ff5a36` | Icon / primary button colour. |
| `IMPROVEME_HOVER_COLOR` | `#3b82f6` | Element hover outline. |
| `IMPROVEME_SELECTED_COLOR` | `#22c55e` | Picked element outline. |
| `IMPROVEME_SCREENSHOT_PADDING` | `15` | Px captured around the selection. |
| `IMPROVEME_TELEGRAM_TOKEN` | — | Bot token (enables Telegram). |
| `IMPROVEME_TELEGRAM_CHAT_ID` | — | Target chat id. |

## Publish & customise

Publish the config to tweak anything (and to rename the env vars — just edit the
`env('…')` calls in the published file):

```bash
php artisan vendor:publish --tag=improveme-config
```

Publish the Blade snippet (e.g. to restyle the bootstrap):

```bash
php artisan vendor:publish --tag=improveme-views
```

Self-host the JS asset instead of serving it from a route:

```bash
php artisan vendor:publish --tag=improveme-assets
```

### Override the route or controller

Point the package at your own controller (extend the bundled one for the
easiest path):

```php
// config/improveme.php
'controller' => \App\Http\Controllers\MyReportController::class,
```

```php
use Arhx\Improveme\Http\Controllers\ReportController;

class MyReportController extends ReportController
{
    protected function rules(array $config): array
    {
        return array_merge(parent::rules($config), [
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);
    }
}
```

Or take over routing entirely:

```php
// config/improveme.php
'register_routes' => false,
```

```php
// routes/web.php
Route::post('feedback', \App\Http\Controllers\MyReportController::class);
```

## How the picker works

1. Click 🎯 **Pick an element** — the page enters picking mode.
2. Hovering outlines the block under the cursor (hover colour).
3. Click to select it (selected colour). The selection stays highlighted.
4. Hovering the selected block now **sees through it** — the outline previews
   the block *behind* it; clicking again drills to that containing block.
5. Press **Esc** (or **Done**) to finish. A screenshot of the selection
   (padded by 15px) is captured client-side via `html2canvas`.

The report payload includes a unique CSS selector, the element's `outerHTML`
(truncated), its bounding rect, the page URL/title, viewport, user agent, the
authenticated user (if any) and the screenshot.

## What gets sent

```jsonc
{
  "type": "bug",                  // or "idea"
  "message": "…",
  "page":   { "url": "…", "title": "…", "referrer": "…" },
  "viewport": { "w": 1440, "h": 900, "dpr": 2 },
  "element": { "selector": "…", "tag": "div", "html": "…", "rect": {…} },
  "screenshot": "data:image/png;base64,…"
}
```

## License

MIT © arhx
