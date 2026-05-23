<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }}</title>
    @include('relay.partials.bootstrap', ['pageName' => 'public-home'])
    <link rel="stylesheet" href="{{ asset('relay-ui/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('relay-ui/admin.css') }}">
</head>
<body
    class="relay-dashboard"
    data-relay-login-open="{{ request()->boolean('login') || $errors->any() ? 'true' : 'false' }}"
>
    <div class="relay-dashboard-shell relay-public-shell">
        <section class="ui-panel relay-hero">
            <div class="ui-shell-header">
                <div class="relay-hero-copy">
                    <p class="ui-eyebrow">Public Relay Status</p>
                    <h1 class="ui-title relay-title">{{ $appName }}</h1>
                    <p class="relay-lead">
                        Shared service status page for relay health, delivery posture, and integration availability.
                        Service URL:
                        <a href="{{ $appUrl }}" target="_blank" rel="noreferrer">{{ $appUrl }}</a>.
                    </p>
                </div>
                <span class="ui-badge relay-health relay-health-{{ $health['status'] ?? 'healthy' }}">
                    {{ strtoupper($health['status'] ?? 'healthy') }}
                </span>
            </div>

            <div class="relay-hero-actions">
                <a class="ui-button ui-button-ghost" href="/relay/login" data-open-relay-login>Operator Login</a>
                <a class="ui-button ui-button-ghost" href="/relay/api/docs">Swagger UI</a>
                <a class="ui-button ui-button-ghost" href="/api/v1/diagnostics" target="_blank" rel="noreferrer">Diagnostics JSON</a>
                <a class="ui-button ui-button-ghost" href="/api/v1/compatibility" target="_blank" rel="noreferrer">Compatibility JSON</a>
            </div>
        </section>

        <section class="relay-metrics" aria-label="Public relay metrics">
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Messages</p>
                <h2 class="ui-title relay-metric-title">{{ number_format((int) ($diagnostics['queue_status']['total_messages'] ?? 0)) }}</h2>
                <p class="relay-metric-note">Total relay messages tracked by this node.</p>
            </article>
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Deliveries</p>
                <h2 class="ui-title relay-metric-title">{{ number_format((int) ($diagnostics['queue_status']['total_deliveries'] ?? 0)) }}</h2>
                <p class="relay-metric-note">Outbound delivery records across all targets.</p>
            </article>
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Receipts</p>
                <h2 class="ui-title relay-metric-title">{{ number_format($totalReceiptsCount) }}</h2>
                <p class="relay-metric-note">Inbound receipts accepted by this relay.</p>
            </article>
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Dead Letters</p>
                <h2 class="ui-title relay-metric-title">{{ number_format($deadDeliveriesCount) }}</h2>
                <p class="relay-metric-note">Dead deliveries currently needing operator attention.</p>
            </article>
        </section>

        <section class="relay-dashboard-grid">
            <div class="relay-dashboard-main">
                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Shared Service</p>
                            <h2 class="ui-title">Integration Entry Points</h2>
                            <p class="relay-section-note">
                                Public-facing entry points for local systems, upstream hubs, and engineering diagnostics.
                            </p>
                        </div>
                    </div>
                    <div class="relay-public-links">
                        <article class="relay-public-link-card">
                            <p class="ui-eyebrow">Local App Submission</p>
                            <strong>Messages API</strong>
                            <code>/api/v1/messages</code>
                            <p class="relay-section-note">Submit outbound messages from local systems into the relay.</p>
                        </article>
                        <article class="relay-public-link-card">
                            <p class="ui-eyebrow">Local App Consumption</p>
                            <strong>Inbox API</strong>
                            <code>/api/v1/inbox</code>
                            <p class="relay-section-note">Read inbound relay messages accepted by this node.</p>
                        </article>
                        <article class="relay-public-link-card">
                            <p class="ui-eyebrow">Outbound Tracking</p>
                            <strong>Deliveries API</strong>
                            <code>/api/v1/deliveries</code>
                            <p class="relay-section-note">Inspect delivery state and retry posture for submitted work.</p>
                        </article>
                        <article class="relay-public-link-card">
                            <p class="ui-eyebrow">Hub-To-Hub Receive</p>
                            <strong>Receive API</strong>
                            <code>/api/v1/receive</code>
                            <p class="relay-section-note">Inbound endpoint for upstream or downstream hub delivery.</p>
                        </article>
                        <article class="relay-public-link-card">
                            <p class="ui-eyebrow">Diagnostics</p>
                            <strong>Compatibility API</strong>
                            <code>/api/v1/compatibility</code>
                            <p class="relay-section-note">Expose protocol compatibility, package version, and capabilities.</p>
                        </article>
                        <article class="relay-public-link-card">
                            <p class="ui-eyebrow">Developer Docs</p>
                            <strong>Swagger UI</strong>
                            <code>/relay/api/docs</code>
                            <p class="relay-section-note">Interactive OpenAPI surface for engineers and integrators.</p>
                        </article>
                    </div>
                </section>
            </div>

            <div class="relay-dashboard-side">
                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Service Snapshot</p>
                            <h2 class="ui-title">Current Usage</h2>
                            <p class="relay-section-note">
                                Updated {{ $health['last_check'] ?? ($diagnostics['timestamp'] ?? now()->toIso8601String()) }}.
                            </p>
                        </div>
                    </div>
                    <dl class="relay-public-facts">
                        <div class="relay-public-fact">
                            <dt>Active Clients</dt>
                            <dd>{{ number_format($activeClientsCount) }}</dd>
                        </div>
                        <div class="relay-public-fact">
                            <dt>Active Handlers</dt>
                            <dd>{{ number_format($activeHandlersCount) }}</dd>
                        </div>
                        <div class="relay-public-fact">
                            <dt>Queued Deliveries</dt>
                            <dd>{{ number_format((int) ($diagnostics['queue_status']['total_queued'] ?? 0)) }}</dd>
                        </div>
                        <div class="relay-public-fact">
                            <dt>Failed Deliveries</dt>
                            <dd>{{ number_format((int) ($diagnostics['queue_status']['failed_deliveries'] ?? 0)) }}</dd>
                        </div>
                        <div class="relay-public-fact">
                            <dt>Protocol Version</dt>
                            <dd>{{ $diagnostics['version']['relay_protocol_version'] ?? '1.1' }}</dd>
                        </div>
                        <div class="relay-public-fact">
                            <dt>Package Version</dt>
                            <dd>{{ $diagnostics['version']['relay_package_version'] ?? '1.1.0' }}</dd>
                        </div>
                    </dl>
                </section>
            </div>
        </section>
    </div>

    <script id="relay-public-home-config" type="application/json">{!! json_encode([
        'oldEmail' => old('email'),
        'loginError' => $errors->first(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="module" src="{{ asset('relay-ui/public-home.js') }}"></script>
</body>
</html>
