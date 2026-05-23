<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $appName }} | {{ $sectionTitle }}</title>
    @include('relay.partials.bootstrap', ['pageName' => 'relay-section-'.$sectionKey])
    <link rel="stylesheet" href="{{ asset('relay-ui/dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('relay-ui/admin.css') }}">
</head>
<body class="relay-dashboard">
    <div class="relay-dashboard-shell relay-admin-shell">
        @include('relay.partials.operator-nav', ['activeNav' => $sectionKey])

        @if (session('status') || session('generated_api_key'))
            <section class="ui-panel relay-admin-toolbar">
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
            </section>
        @endif

        <section class="ui-panel relay-admin-main">
            <div class="relay-admin-page-head ui-shell-header">
                <div>
                    <p class="ui-eyebrow">Operations View</p>
                    <h2 class="ui-title">{{ $sectionTitle }}</h2>
                    <p class="relay-section-note">{{ $sectionDescription }}</p>
                </div>
            </div>
            <div class="relay-admin-page-toolbar">
                <input class="ui-input relay-admin-search-input" id="relay-admin-search" type="search" placeholder="Search {{ strtolower($sectionTitle) }}">
                <div class="relay-admin-toolbar-actions">
                    @if ($sectionKey === 'clients')
                        <button type="button" class="ui-button ui-button-primary" id="relay-admin-new-client">New Client</button>
                    @endif
                    @if ($sectionKey === 'users' && auth()->user()?->isRelayAdmin())
                        <button type="button" class="ui-button ui-button-primary" id="relay-admin-new-user">New User</button>
                    @endif
                    <span class="ui-badge" id="relay-admin-count">Loading…</span>
                </div>
            </div>
            <div class="relay-admin-page-content">
                <div id="relay-admin-grid"></div>
            </div>
        </section>
    </div>
    <script id="relay-admin-data" type="application/json">{!! json_encode([
        'sectionKey' => $sectionKey,
        'sectionTitle' => $sectionTitle,
        'dataUrl' => $dataUrl,
        'openClientModal' => $sectionKey === 'clients' && $errors->any(),
        'oldInput' => $sectionKey === 'clients' ? [
            'name' => old('name'),
            'system_code' => old('system_code'),
            'description' => old('description'),
        ] : null,
        'validationError' => $sectionKey === 'clients' ? $errors->first() : null,
        'openUserModal' => $sectionKey === 'users' && $errors->any(),
        'userOldInput' => $sectionKey === 'users' ? [
            'name' => old('name'),
            'email' => old('email'),
            'role' => old('role', 'operator'),
        ] : null,
        'userValidationError' => $sectionKey === 'users' ? $errors->first() : null,
        'isRelayAdmin' => auth()->user()?->isRelayAdmin(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="module" src="{{ asset('relay-ui/admin.js') }}"></script>
</body>
</html>
