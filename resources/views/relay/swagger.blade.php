<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} | API Docs</title>
    @include('relay.partials.bootstrap', ['pageName' => 'relay-api-docs'])
    <link rel="stylesheet" href="{{ asset('relay-ui/dashboard.css') }}">
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <link rel="stylesheet" href="{{ asset('vendor/dark-swagger/dark-swagger-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('relay-ui/admin.css') }}">
</head>
<body class="relay-dashboard">
    <div class="relay-dashboard-shell relay-admin-shell">
        @include('relay.partials.operator-nav', ['activeNav' => 'api'])

        <section class="ui-panel relay-swagger-panel">
            <div class="ui-shell-header">
                <div>
                    <p class="ui-eyebrow">Swagger</p>
                    <h1 class="ui-title">Relay API Documentation</h1>
                    <p class="relay-section-note">OpenAPI-powered documentation for local application and hub-to-hub integration.</p>
                </div>
                <div class="relay-hero-actions">
                    <a class="ui-button ui-button-ghost" href="/relay">Dashboard</a>
                    <a class="ui-button ui-button-ghost" href="{{ asset('relay-ui/openapi.json') }}" target="_blank" rel="noreferrer">OpenAPI JSON</a>
                    <a class="ui-button ui-button-ghost" href="/api/v1/compatibility" target="_blank" rel="noreferrer">Compatibility JSON</a>
                </div>
            </div>
            <div id="swagger-ui"></div>
        </section>
    </div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
        window.ui = SwaggerUIBundle({
            url: @json(asset('relay-ui/openapi.json')),
            dom_id: '#swagger-ui',
            deepLinking: true,
            docExpansion: 'list',
            defaultModelsExpandDepth: 2,
            defaultModelExpandDepth: 2,
            displayRequestDuration: true,
            tryItOutEnabled: true,
            persistAuthorization: true
        });
    </script>
</body>
</html>
