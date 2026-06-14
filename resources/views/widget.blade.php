@php
    $cfg = config('improveme');
    if (! ($cfg['enabled'] ?? true)) {
        return;
    }

    $audience = $cfg['audience'] ?? 'all';
    if (($audience === 'auth' && ! auth()->check()) || ($audience === 'guest' && auth()->check())) {
        return;
    }

    $w = $cfg['widget'] ?? [];
    $boot = [
        'endpoint' => route('improveme.report'),
        'token' => csrf_token(),
        'position' => $w['position'] ?? 'bottom-right',
        'accent' => $w['accent'] ?? '#ff5a36',
        'hoverColor' => $w['hover_color'] ?? '#3b82f6',
        'selectedColor' => $w['selected_color'] ?? '#22c55e',
        'zIndex' => (int) ($w['z_index'] ?? 2147483000),
        'screenshotPadding' => (int) ($w['screenshot_padding'] ?? 15),
        'maxHtml' => (int) ($w['max_html'] ?? 20000),
        'xsrfCookie' => $w['xsrf_cookie'] ?? 'XSRF-TOKEN',
        'html2canvasUrl' => $w['html2canvas_url'] ?? '',
        'labels' => $w['labels'] ?? [],
    ];
@endphp
<script>
/* improveme: start capturing console/JS errors as early as possible so they are
   already buffered by the time a report is sent. Runs inline (not deferred). */
(function () {
  if (window.__IMPROVEME_ERRLOG__) return;
  var buf = window.__IMPROVEME_ERRLOG__ = [];
  var MAX = 50;
  function push(level, text) {
    text = String(text == null ? '' : text);
    if (!text) return;
    buf.push({ level: level, text: text.slice(0, 2000) });
    if (buf.length > MAX) buf.shift();
  }
  function fmt(a) {
    if (a instanceof Error) return a.stack || (a.name + ': ' + a.message);
    if (a && typeof a === 'object') { try { return JSON.stringify(a); } catch (e) { return Object.prototype.toString.call(a); } }
    return String(a);
  }
  var orig = console.error;
  console.error = function () {
    try { push('error', Array.prototype.map.call(arguments, fmt).join(' ')); } catch (e) {}
    return orig.apply(console, arguments);
  };
  window.addEventListener('error', function (e) {
    if (e && e.message) push('error', e.message + (e.filename ? ' @ ' + e.filename + ':' + (e.lineno || 0) + ':' + (e.colno || 0) : ''));
    else if (e && e.target && (e.target.src || e.target.href)) push('error', 'Resource failed to load: ' + (e.target.src || e.target.href));
  }, true);
  window.addEventListener('unhandledrejection', function (e) {
    var r = e && e.reason;
    push('error', 'Unhandled promise rejection: ' + (r instanceof Error ? (r.stack || r.message) : fmt(r)));
  });
})();
window.__IMPROVEME__ = @json($boot);
</script>
<script src="{{ route('improveme.widget') }}?v={{ \Arhx\Improveme\Http\Controllers\WidgetController::assetVersion() }}" defer></script>
