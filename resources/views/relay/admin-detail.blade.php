<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} | {{ $title }}</title>
    @include('relay.partials.bootstrap', ['pageName' => 'relay-detail-'.($detailMode ?? 'generic')])
    <link rel="stylesheet" href="{{ asset('relay-ui/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('relay-ui/admin.css') }}">
</head>
<body class="relay-dashboard">
    <div class="relay-dashboard-shell relay-admin-shell">
        @include('relay.partials.operator-nav', ['activeNav' => $activeNav])

        @if (($detailMode ?? 'generic') === 'client')
            <section class="ui-panel relay-admin-main relay-client-detail-page">
                <div class="relay-client-header">
                    <div class="relay-client-header-main">
                        <p class="ui-eyebrow">Client Detail</p>
                        <h2 class="ui-title" id="relay-detail-summary-title">{{ $title }}</h2>
                        <p class="relay-section-note" id="relay-detail-subtitle">{{ $subtitle }}</p>
                    </div>
                    <div class="relay-client-header-actions" id="relay-client-header-actions"></div>
                </div>

                <div class="relay-client-toolbar-summary" id="relay-client-summary-inline"></div>

                <div class="relay-admin-page-toolbar relay-client-grid-toolbar">
                    <div class="relay-client-toolbar-loading" id="relay-client-toolbar-search-loading"></div>
                    <input class="ui-input relay-admin-search-input" id="relay-client-handlers-search" type="search" placeholder="Search handlers" hidden>
                    <div class="relay-admin-toolbar-actions">
                        <div class="relay-client-toolbar-loading" id="relay-client-toolbar-actions-loading"></div>
                        <span class="ui-badge" id="relay-client-handlers-count" hidden>0 handler(s)</span>
                        <button class="ui-button ui-button-primary" id="relay-client-handlers-new" type="button" hidden>New Handler</button>
                    </div>
                </div>

                <div class="relay-admin-page-content relay-client-detail-content">
                    <div id="relay-client-handlers-grid"></div>
                </div>
            </section>
        @else
            <div class="relay-admin-detail-shell">
                <section class="relay-admin-detail-grid">
                    <section class="ui-panel">
                        <div class="ui-shell-header">
                            <div>
                                <p class="ui-eyebrow">Summary</p>
                                <h2 class="ui-title" id="relay-detail-summary-title">{{ $title }}</h2>
                                <p class="relay-section-note" id="relay-detail-subtitle">{{ $subtitle }}</p>
                            </div>
                            <div class="relay-hero-actions">
                                <a class="ui-button ui-button-ghost" href="{{ $backUrl }}" id="relay-detail-back">{{ $backLabel }}</a>
                                <a class="ui-button ui-button-ghost" href="/relay">Dashboard</a>
                            </div>
                        </div>

                        @if (session('status'))
                            <div class="relay-admin-notice ui-surface">
                                <strong>Status</strong>
                                <span>{{ session('status') }}</span>
                            </div>
                        @endif

                        @if (session('generated_api_key'))
                            <div class="relay-admin-secret ui-surface">
                                <p class="ui-eyebrow">Generated API Key</p>
                                <code>{{ session('generated_api_key') }}</code>
                            </div>
                        @endif

                        <div class="relay-detail-summary" id="relay-detail-summary"></div>
                        <div class="relay-detail-actions" id="relay-detail-actions"></div>
                        <div class="relay-related-groups" id="relay-detail-related"></div>
                    </section>

                    <section class="ui-panel">
                        <div class="ui-shell-header">
                            <div>
                                <p class="ui-eyebrow">Inspector</p>
                                <h2 class="ui-title">Raw Detail</h2>
                            </div>
                        </div>
                        <div id="relay-admin-inspector"></div>
                    </section>
                </section>
            </div>
        @endif
    </div>

    <script id="relay-admin-detail-config" type="application/json">{!! json_encode([
        'dataUrl' => $dataUrl,
        'detailMode' => $detailMode ?? 'generic',
        'isRelayAdmin' => auth()->user()?->isRelayAdmin(),
        'initialStatusMessage' => session('status'),
        'initialGeneratedApiKey' => session('generated_api_key'),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="module" src="{{ asset('relay-ui/admin-detail.js') }}"></script>
</body>
</html>
