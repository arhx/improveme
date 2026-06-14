# Changelog

All notable changes to `arhx/improveme` are documented here. This project adheres
to [Semantic Versioning](https://semver.org/).

## [1.3.0] - 2026-06-15

### Added
- **Inertia (Vue/React) compatibility.** The widget now reads Laravel's
  `XSRF-TOKEN` cookie at send-time (the axios way, as `X-XSRF-TOKEN`) and falls
  back to the inline token only when no cookie is present. This keeps the CSRF
  token fresh across long-lived SPA sessions and prevents `419 TokenMismatch`
  errors after the session regenerates (e.g. a Fortify/Sanctum login). Configurable
  via `IMPROVEME_XSRF_COOKIE` (`improveme.widget.xsrf_cookie`).
- The auto-inject middleware now skips Inertia visits explicitly (`X-Inertia`
  header) in addition to the existing AJAX/JSON guards.
- First test suite (Orchestra Testbench + PHPUnit): middleware injection rules,
  report controller responses/validation, widget route, `ReportData` mapping,
  dispatcher channel wiring and screenshot storage. Added `composer test`/`lint`
  scripts.
- README section on using improveme in Laravel + Inertia projects.

### Notes
- No breaking changes. Existing non-Inertia (Blade) installs behave identically:
  the cookie path is used by every standard Laravel `web` response, with the inline
  token retained as a fallback.

## [1.2.0] - 2026-06-10

### Added
- Optional **GitHub Issues** channel — files one issue per report (title, page URL,
  selector/HTML, console errors, screenshot reference, labelled `improveme` +
  `bug`/`enhancement`). Off by default; enable with `IMPROVEME_GITHUB_*`.

## [1.1.0]

### Added
- Reports now attach the request **IP**, **user-agent**, authenticated **`Auth::id()`**
  (no name/email) and buffered browser **console errors**.

## [1.0.x]

- Initial release: floating feedback/bug-report widget with drill-down element
  picker, client-side screenshot (html2canvas-pro), and Telegram + log channels.
  Auto-injection middleware, `@improveme` Blade directive, publishable
  config/views/assets, overridable route/controller.

[1.3.0]: https://github.com/arhx/improveme/releases/tag/v1.3.0
[1.2.0]: https://github.com/arhx/improveme/releases/tag/v1.2.0
[1.1.0]: https://github.com/arhx/improveme/releases/tag/v1.1.0
