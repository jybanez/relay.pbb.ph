@php
    $relayBootstrap = [
        'app' => [
            'name' => $appName ?? config('app.name'),
            'page' => $pageName ?? 'relay',
        ],
        'settings' => [
            'bootstrapUrl' => '/api/bootstrap',
            'csrfTokenUrl' => '/api/csrf-token',
            'sessionPingUrl' => '/api/session/ping',
        ],
    ];
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
window.__PBB_BOOTSTRAP__ = {!! json_encode($relayBootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!};
</script>
