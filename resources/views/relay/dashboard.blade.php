<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} | Relay Dashboard</title>
    @include('relay.partials.bootstrap', ['pageName' => 'relay-dashboard'])
    <link rel="stylesheet" href="{{ asset('relay-ui/dashboard.css') }}">
</head>
<body class="relay-dashboard">
    <div class="relay-dashboard-shell">
        @include('relay.partials.operator-nav', ['activeNav' => 'home'])

        <section class="relay-metrics" aria-label="Key relay metrics">
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Queue</p>
                <h2 class="ui-title relay-metric-title">Queued Deliveries</h2>
                <p class="relay-metric-note">Items waiting for the next worker attempt.</p>
                <div class="relay-metric-progress" data-metric-key="queuedDeliveries"></div>
            </article>
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Retry</p>
                <h2 class="ui-title relay-metric-title">Failed Deliveries</h2>
                <p class="relay-metric-note">Retryable failures that still have runway.</p>
                <div class="relay-metric-progress" data-metric-key="failedDeliveries"></div>
            </article>
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Dead Letter</p>
                <h2 class="ui-title relay-metric-title">Dead Deliveries</h2>
                <p class="relay-metric-note">Operator attention required.</p>
                <div class="relay-metric-progress" data-metric-key="deadDeliveries"></div>
            </article>
            <article class="ui-panel relay-metric-card">
                <p class="ui-eyebrow">Inbox</p>
                <h2 class="ui-title relay-metric-title">Inbound Receipts</h2>
                <p class="relay-metric-note">Accepted relay messages across all source hubs.</p>
                <div class="relay-metric-progress" data-metric-key="inboundReceipts"></div>
            </article>
        </section>

        <section class="relay-dashboard-grid">
            <div class="relay-dashboard-main">
                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Network</p>
                            <h2 class="ui-title">Hub Link Status</h2>
                            <p class="relay-section-note">Current per-target delivery pressure and most recent successful completion.</p>
                        </div>
                    </div>
                    <div id="hub-status-grid"></div>
                </section>

                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Outbound</p>
                            <h2 class="ui-title">Recent Deliveries</h2>
                            <p class="relay-section-note">Latest delivery state transitions across all upstream targets.</p>
                        </div>
                    </div>
                    <div id="recent-deliveries-grid"></div>
                </section>

                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Transfers</p>
                            <h2 class="ui-title">Recent Upload Sessions</h2>
                            <p class="relay-section-note">Chunked attachment sessions across local and hub-to-hub flows.</p>
                        </div>
                    </div>
                    <div id="recent-uploads-grid"></div>
                </section>
            </div>

            <div class="relay-dashboard-side">
                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Inbound</p>
                            <h2 class="ui-title">Recent Messages</h2>
                            <p class="relay-section-note">Latest messages accepted into the relay.</p>
                        </div>
                    </div>
                    <div id="recent-messages-grid"></div>
                </section>

                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Receipts</p>
                            <h2 class="ui-title">Inbound Receipts</h2>
                            <p class="relay-section-note">Most recent relay receipts seen by this hub.</p>
                        </div>
                    </div>
                    <div id="recent-receipts-grid"></div>
                </section>

                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Clients</p>
                            <h2 class="ui-title">Active Local Clients</h2>
                            <p class="relay-section-note">Registered systems using the local relay API.</p>
                        </div>
                    </div>
                    <div id="clients-grid"></div>
                </section>

                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Handlers</p>
                            <h2 class="ui-title">Local Handlers</h2>
                            <p class="relay-section-note">Registered webhook endpoints for local application handoff.</p>
                        </div>
                    </div>
                    <div id="handlers-grid"></div>
                </section>

                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Dispatches</p>
                            <h2 class="ui-title">Handler Dispatches</h2>
                            <p class="relay-section-note">Recent local webhook dispatch attempts, including failed and retryable work.</p>
                        </div>
                    </div>
                    <div id="handler-dispatches-grid"></div>
                </section>

                <section class="ui-panel">
                    <div class="ui-shell-header">
                        <div>
                            <p class="ui-eyebrow">Routes</p>
                            <h2 class="ui-title">API Shortcuts</h2>
                            <p class="relay-section-note">Direct entry points for operators and engineers.</p>
                        </div>
                        <div class="relay-hero-actions">
                            <span class="ui-badge relay-health relay-health-healthy" id="relay-dashboard-health">LOADING</span>
                            <span class="ui-badge" id="relay-dashboard-timestamp">Timestamp loading</span>
                        </div>
                    </div>
                    <div class="relay-shortcuts">
                        <a class="ui-button ui-button-ghost" href="/api/v1/diagnostics" target="_blank" rel="noreferrer">Diagnostics JSON</a>
                        <a class="ui-button ui-button-ghost" href="/api/v1/compatibility" target="_blank" rel="noreferrer">Compatibility JSON</a>
                        <a class="ui-button ui-button-ghost" href="/relay/api/docs">Swagger UI</a>
                        <span class="ui-badge">Messages API: /api/v1/messages</span>
                        <span class="ui-badge">Inbox API: /api/v1/inbox</span>
                        <span class="ui-badge">Deliveries API: /api/v1/deliveries</span>
                        <span class="ui-badge">Handlers API: /api/v1/handlers</span>
                        <span class="ui-badge">Handler Dispatches API: /api/v1/handler-dispatches</span>
                        <span class="ui-badge">Uploads API: /api/v1/upload/*</span>
                    </div>
                </section>
            </div>
        </section>
    </div>

    <script id="relay-dashboard-config" type="application/json">{!! json_encode([
        'dataUrl' => $dataUrl,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="module" src="{{ asset('relay-ui/dashboard.js') }}"></script>
</body>
</html>
