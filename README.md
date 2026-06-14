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

## File GitHub issues (optional)

Turn each report into a GitHub issue in your repo's tracker — handy when you
want feedback to land somewhere an AI coding agent (or a human) can read and act
on directly:

```dotenv
IMPROVEME_GITHUB_ENABLED=true
IMPROVEME_GITHUB_TOKEN=ghp_your_token        # PAT with issues:write
IMPROVEME_GITHUB_REPO=owner/repo
```

Use a classic PAT with the `repo` scope, or a fine-grained PAT scoped to
**Issues: Read and write** on the target repo. Each issue gets the report's
message as title, the page URL, picked selector/HTML, browser console errors and
a screenshot reference in the body, labelled `improveme` + `bug`/`enhancement`
(missing labels are created automatically). Channels stack — Telegram and GitHub
can both be on at once. Configure labels/endpoint in the published config.

## Inertia (Vue / React) projects

improveme works out of the box in Laravel + Inertia apps — no Inertia dependency
is added to your project. A few notes on how it fits an SPA:

- **One injection, survives navigation.** The snippet is injected once into the
  initial full-page HTML (your root template, e.g. `app.blade.php`, which contains
  `</body>`) and attaches to `<html>` — outside Inertia's app root — so the widget
  stays put across client-side visits. Inertia's partial XHR responses (carrying
  the `X-Inertia` header) and any AJAX/JSON responses are never touched.
- **Fresh CSRF token.** In an SPA the page never reloads, so a token captured at
  first paint can go stale once the server-side session regenerates (a login via
  Fortify/Sanctum, say). The widget therefore reads Laravel's `XSRF-TOKEN` cookie
  at send-time — the same approach axios uses — and only falls back to the inline
  token when no cookie is present. This avoids spurious `419 TokenMismatch` errors
  on long-lived sessions. Override the cookie name with `IMPROVEME_XSRF_COOKIE` if
  you have customised it.
- **JSON in, JSON out.** The report endpoint always responds with JSON (a `200
  {ok:true}`, or `422 {ok:false, errors}` on validation failure) — never a redirect
  or a flash message — so it composes cleanly with `HandleInertiaRequests` and your
  shared props without interfering with them.

Nothing extra to configure: `composer require arhx/improveme` and the widget shows
up. Restrict it to staging/authenticated users with the env switches below.

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
| `IMPROVEME_XSRF_COOKIE` | `XSRF-TOKEN` | Cookie read for a fresh CSRF token (SPA/Inertia). |
| `IMPROVEME_TELEGRAM_TOKEN` | — | Bot token (enables Telegram). |
| `IMPROVEME_TELEGRAM_CHAT_ID` | — | Target chat id. |
| `IMPROVEME_GITHUB_ENABLED` | `false` | File a GitHub issue per report. |
| `IMPROVEME_GITHUB_TOKEN` | — | PAT with `issues:write`. |
| `IMPROVEME_GITHUB_REPO` | — | Target repo as `owner/repo`. |

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
   (padded by 15px) is captured client-side via
   [html2canvas-pro](https://github.com/yorickshan/html2canvas-pro) — a
   maintained html2canvas fork that understands Tailwind 4's `oklch()` colours
   (lazy-loaded from a CDN; URL configurable via `IMPROVEME_HTML2CANVAS_URL`).

The report payload includes a unique CSS selector, the element's `outerHTML`
(truncated), its bounding rect, the page URL/title, viewport, user agent, the
request IP, the authenticated user id (just `Auth::id()` — no name/email), any
buffered browser **console errors**, and the screenshot.

Console/JS errors are captured from the moment the widget snippet is parsed:
`console.error(...)`, uncaught `error` events (including failed resource loads)
and `unhandledrejection` are buffered (last 50) and attached automatically — so
a bug report comes with the relevant console output already in it.

## What gets sent

```jsonc
{
  "type": "bug",                  // or "idea"
  "message": "…",
  "page":   { "url": "…", "title": "…", "referrer": "…" },
  "viewport": { "w": 1440, "h": 900, "dpr": 2 },
  "userAgent": "…",
  "element": { "selector": "…", "tag": "div", "html": "…", "rect": {…} },
  "consoleErrors": [ { "level": "error", "text": "TypeError: …" } ],
  "screenshot": "data:image/png;base64,…"
}
```

The server additionally records the request **IP** and the authenticated
**user id** (resolved from `Auth::id()`) — these are taken server-side, not from
the payload.

## License

MIT © arhx
