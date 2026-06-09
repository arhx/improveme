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
        'html2canvasUrl' => $w['html2canvas_url'] ?? '',
        'labels' => $w['labels'] ?? [],
    ];
@endphp
<script>window.__IMPROVEME__ = @json($boot);</script>
<script src="{{ route('improveme.widget') }}?v={{ \Arhx\Improveme\Http\Controllers\WidgetController::assetVersion() }}" defer></script>
