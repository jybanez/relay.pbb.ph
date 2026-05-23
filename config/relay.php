<?php

$targets = json_decode((string) env('RELAY_TARGETS', '{}'), true);
$hubs = json_decode((string) env('RELAY_HUBS', '{}'), true);
$targetOverrides = json_decode((string) env('RELAY_TARGET_OVERRIDES', '{}'), true);
$hubCredentials = json_decode((string) env('RELAY_HUB_CREDENTIALS', '{}'), true);

return [
    'local_hub_id' => env('RELAY_LOCAL_HUB_ID'),
    'hq_registry' => [
        'enabled' => filter_var(env('RELAY_HQ_API_ENABLED', false), FILTER_VALIDATE_BOOL),
        'base_url' => env('RELAY_HQ_API_BASE_URL'),
        'token' => env('RELAY_HQ_API_TOKEN'),
        'local_relay_hub_id' => env('RELAY_HQ_LOCAL_RELAY_HUB_ID'),
        'local_hq_id' => env('RELAY_HQ_LOCAL_HQ_ID'),
        'sync_enabled' => filter_var(env('RELAY_HQ_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL),
        'sync_interval_seconds' => (int) env('RELAY_HQ_SYNC_INTERVAL_SECONDS', 300),
        'outbound_topology_mode' => env('RELAY_HQ_OUTBOUND_TOPOLOGY_MODE', 'manual'),
        'inbound_trust_mode' => env('RELAY_HQ_INBOUND_TRUST_MODE', 'manual'),
    ],
    'hub_auth' => [
        'mode' => env('RELAY_HUB_AUTH_MODE', 'shared_key'),
        'timestamp_tolerance_seconds' => (int) env('RELAY_HUB_AUTH_TIMESTAMP_TOLERANCE_SECONDS', 300),
        'client_certificate_fingerprint_header' => env('RELAY_HUB_AUTH_CLIENT_CERTIFICATE_FINGERPRINT_HEADER', 'X-Relay-Client-Cert-Fingerprint'),
    ],

    'version' => [
        'package' => env('RELAY_PACKAGE_VERSION', '1.1.0'),
        'protocol' => env('RELAY_PROTOCOL_VERSION', '1.1'),
        'minimum_supported_protocol' => env('RELAY_MINIMUM_SUPPORTED_PROTOCOL_VERSION', '1.0'),
        'supported_protocols' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('RELAY_SUPPORTED_PROTOCOL_VERSIONS', '1.0,1.1'))
        ), static fn (string $value): bool => $value !== '')),
        'capabilities' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('RELAY_PROTOCOL_CAPABILITIES', 'chunked_uploads,local_handlers,tracked_handler_dispatches,certificate_bound_auth,admin_operator_auth'))
        ), static fn (string $value): bool => $value !== '')),
    ],

    'delivery' => [
        'max_attempts' => (int) env('RELAY_DELIVERY_MAX_ATTEMPTS', 5),
        'backoff_minutes' => array_values(array_filter(array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', (string) env('RELAY_DELIVERY_BACKOFF_MINUTES', '1,5,15,60,360'))
        ), static fn (int $value): bool => $value > 0)),
        'timeout_seconds' => (int) env('RELAY_DELIVERY_TIMEOUT_SECONDS', 10),
        'queue' => env('RELAY_DELIVERY_QUEUE', 'relay-deliveries'),
    ],

    'local_handlers' => [
        'queue' => env('RELAY_LOCAL_HANDLER_QUEUE', 'relay-handlers'),
        'timeout_seconds' => (int) env('RELAY_LOCAL_HANDLER_TIMEOUT_SECONDS', 10),
        'max_attempts' => (int) env('RELAY_LOCAL_HANDLER_MAX_ATTEMPTS', 3),
        'backoff_seconds' => array_values(array_filter(array_map(
            static fn (string $value): int => (int) trim($value),
            explode(',', (string) env('RELAY_LOCAL_HANDLER_BACKOFF_SECONDS', '30,120,600'))
        ), static fn (int $value): bool => $value > 0)),
    ],

    'uploads' => [
        'disk' => env('RELAY_UPLOAD_DISK', 'local'),
        'chunk_size_bytes' => (int) env('RELAY_UPLOAD_CHUNK_SIZE_BYTES', 1048576),
        'temp_prefix' => env('RELAY_UPLOAD_TEMP_PREFIX', 'relay_uploads/tmp'),
        'attachment_prefix' => env('RELAY_ATTACHMENT_PREFIX', 'relay_attachments'),
    ],

    'maestro' => [
        'enabled' => filter_var(env('RELAY_MAESTRO_ENABLED', false), FILTER_VALIDATE_BOOL),
        'app_code' => env('RELAY_MAESTRO_APP_CODE', 'relay'),
        'base_url' => env('RELAY_MAESTRO_BASE_URL'),
        'telemetry_token' => env('RELAY_MAESTRO_TELEMETRY_TOKEN'),
        'tls_verify' => filter_var(env('RELAY_MAESTRO_TLS_VERIFY', true), FILTER_VALIDATE_BOOL),
        'ca_bundle' => env('RELAY_MAESTRO_CA_BUNDLE'),
        'heartbeat_interval_seconds' => (int) env('RELAY_MAESTRO_HEARTBEAT_INTERVAL_SECONDS', 15),
        'connect_timeout_seconds' => (int) env('RELAY_MAESTRO_CONNECT_TIMEOUT_SECONDS', 3),
        'timeout_seconds' => (int) env('RELAY_MAESTRO_TIMEOUT_SECONDS', 5),
        'heartbeat_path' => env('RELAY_MAESTRO_HEARTBEAT_PATH', '/api/v1/telemetry/workers/heartbeat'),
        'events_path' => env('RELAY_MAESTRO_EVENTS_PATH', '/api/v1/telemetry/worker-events'),
    ],

    'targets' => is_array($targets) ? $targets : [],
    'hubs' => is_array($hubs) ? $hubs : [],
    'target_overrides' => is_array($targetOverrides) ? $targetOverrides : [],
    'hub_credentials' => is_array($hubCredentials) ? $hubCredentials : [],
];
