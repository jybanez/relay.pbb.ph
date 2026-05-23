<?php

declare(strict_types=1);

$rootPath = dirname(__DIR__, 2);
$startedAt = gmdate('c');
$mode = optionValue($argv, '--mode') ?? 'initial';
$configPath = optionValue($argv, '--config');
$reportPath = optionValue($argv, '--report');
$dryRun = in_array('--dry-run', $argv, true);

$warnings = [];
$errors = [];
$results = [];
$outputs = [];

$configResult = loadConfig($configPath);
if ($configResult['status'] === 'failed') {
    $errors[] = $configResult['message'];
    $config = [];
} else {
    $config = $configResult['config'];
}

if ($errors === []) {
    try {
        $settings = resolveMaestroSettings($config);
        $envPath = resolveEnvPath($config, $rootPath);
        $tokenSupplied = trim((string) ($settings['telemetry_token'] ?? '')) !== '';
        $baseUrlSupplied = trim((string) ($settings['base_url'] ?? '')) !== '';

        if (! $baseUrlSupplied) {
            $errors[] = 'Relay Data Prep apply settings requires a Maestro base URL.';
        }

        if (! $tokenSupplied) {
            $errors[] = 'Relay Data Prep apply settings requires a Maestro telemetry token.';
        }

        if ($errors === []) {
            $changes = [
                'RELAY_MAESTRO_ENABLED' => 'true',
                'RELAY_MAESTRO_BASE_URL' => (string) $settings['base_url'],
                'RELAY_MAESTRO_APP_CODE' => (string) ($settings['app_code'] ?? 'relay'),
                'RELAY_MAESTRO_TELEMETRY_TOKEN' => (string) $settings['telemetry_token'],
                'RELAY_MAESTRO_TLS_VERIFY' => boolString($settings['tls_verify'] ?? true),
            ];

            if (stringValue($settings['ca_bundle'] ?? null) !== null) {
                $changes['RELAY_MAESTRO_CA_BUNDLE'] = stringValue($settings['ca_bundle']);
            }

            $existing = readEnvFile($envPath);
            $changedKeys = [];
            foreach ($changes as $key => $value) {
                if (($existing[$key] ?? null) !== $value) {
                    $changedKeys[] = $key;
                }
            }

            if (! $dryRun) {
                writeEnvFile($envPath, array_merge($existing, $changes));
            }

            $configCacheCleared = false;
            if (! $dryRun) {
                $configCacheCleared = clearLaravelConfigCache($rootPath);
            }

            $results[] = [
                'id' => 'relay_maestro_settings',
                'type' => 'app_settings',
                'action' => $changedKeys === [] ? 'noop' : 'upsert',
                'status' => 'success',
                'changed_keys' => $changedKeys,
                'changed' => count($changedKeys),
                'token_supplied' => $tokenSupplied,
                'tls_verify' => boolString($settings['tls_verify'] ?? true) === 'true',
                'ca_bundle_configured' => stringValue($settings['ca_bundle'] ?? null) !== null,
                'config_cache_cleared' => $configCacheCleared,
            ];

            $outputs[] = [
                'id' => 'relay_maestro_telemetry',
                'kind' => 'client_settings',
                'target_app' => 'pbb-relay',
                'secret_ref' => 'runtime.maestro.relay_telemetry_token',
                'status' => $dryRun ? 'planned' : 'applied',
                'token_supplied' => $tokenSupplied,
                'ca_bundle_configured' => stringValue($settings['ca_bundle'] ?? null) !== null,
            ];
        }
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

$status = $errors === [] ? 'success' : 'failed';

$report = [
    'schema_version' => 1,
    'app' => 'pbb-relay',
    'tool' => 'data_prep_apply_settings',
    'mode' => $mode,
    'dry_run' => $dryRun,
    'status' => $status,
    'summary' => $status === 'success'
        ? ($dryRun ? 'Relay Maestro settings planned.' : 'Relay Maestro settings applied.')
        : 'Relay Maestro settings failed.',
    'started_at' => $startedAt,
    'finished_at' => gmdate('c'),
    'sources' => [
        [
            'id' => 'runtime_maestro_settings',
            'kind' => 'runtime_config',
        ],
    ],
    'results' => $results,
    'outputs' => $outputs,
    'warnings' => $warnings,
    'errors' => $errors,
];

emitJson($report, $reportPath);

exit($status === 'success' ? 0 : 1);

function optionValue(array $argv, string $name): ?string
{
    foreach ($argv as $index => $arg) {
        if ($arg === $name) {
            return isset($argv[$index + 1]) ? (string) $argv[$index + 1] : null;
        }

        if (str_starts_with($arg, $name.'=')) {
            return substr($arg, strlen($name) + 1);
        }
    }

    return null;
}

function loadConfig(?string $path): array
{
    if ($path === null || trim($path) === '') {
        return [
            'status' => 'failed',
            'message' => 'Config file is required for Relay Data Prep apply settings.',
            'config' => [],
        ];
    }

    if (! is_file($path)) {
        return [
            'status' => 'failed',
            'message' => 'Config file was not found: '.$path,
            'config' => [],
        ];
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return [
            'status' => 'failed',
            'message' => 'Config file could not be read: '.$path,
            'config' => [],
        ];
    }

    $config = json_decode($json, true);
    if (! is_array($config)) {
        return [
            'status' => 'failed',
            'message' => 'Config file is not valid JSON: '.json_last_error_msg(),
            'config' => [],
        ];
    }

    return [
        'status' => 'passed',
        'message' => 'Config file loaded.',
        'config' => $config,
    ];
}

function resolveMaestroSettings(array $config): array
{
    $candidates = [
        dataGet($config, 'maestro'),
        dataGet($config, 'dependencies.maestro'),
        dataGet($config, 'data_prep.apply_settings.maestro'),
        dataGet($config, 'relay.data_prep.maestro'),
        dataGet($config, 'relay.data_prep.apply_settings.maestro'),
    ];

    $settings = [];
    foreach ($candidates as $candidate) {
        if (is_array($candidate)) {
            $settings = array_merge($settings, $candidate);
        }
    }

    $tokenCandidates = [
        dataGet($config, 'relay.data_prep.apply_settings.telemetry_token'),
        dataGet($config, 'relay.data_prep.apply_settings.maestro.telemetry_token'),
        dataGet($config, 'maestro.telemetry_token'),
        dataGet($config, 'dependencies.maestro.telemetry_token'),
        dataGet($config, 'maestro.populate.generated_telemetry_tokens.relay'),
        dataGet($config, 'generated_telemetry_tokens.relay'),
        dataGet($config, 'runtime_telemetry_tokens.relay'),
        dataGet($config, 'application_tokens.relay.Primary'),
    ];

    foreach ($tokenCandidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            $settings['telemetry_token'] = $candidate;
            break;
        }
    }

    $relayAppCodeCandidates = [
        dataGet($config, 'relay.data_prep.apply_settings.app_code'),
        dataGet($config, 'relay.data_prep.apply_settings.maestro.app_code'),
        dataGet($config, 'relay.data_prep.maestro.app_code'),
        dataGet($config, 'relay.maestro.app_code'),
    ];

    $settings['app_code'] = 'relay';
    foreach ($relayAppCodeCandidates as $candidate) {
        if (is_string($candidate) && trim($candidate) !== '') {
            $settings['app_code'] = trim($candidate);
            break;
        }
    }

    $caBundleCandidates = [
        dataGet($config, 'relay.data_prep.apply_settings.maestro.ca_bundle'),
        dataGet($config, 'relay.data_prep.apply_settings.maestro.curl_ca_bundle'),
        dataGet($config, 'relay.data_prep.apply_settings.maestro.ssl_cert_file'),
        dataGet($config, 'relay.data_prep.maestro.ca_bundle'),
        dataGet($config, 'relay.data_prep.maestro.curl_ca_bundle'),
        dataGet($config, 'relay.maestro.ca_bundle'),
        dataGet($config, 'relay.maestro.curl_ca_bundle'),
        dataGet($config, 'data_prep.apply_settings.maestro.ca_bundle'),
        dataGet($config, 'data_prep.apply_settings.maestro.curl_ca_bundle'),
        dataGet($config, 'maestro.ca_bundle'),
        dataGet($config, 'maestro.curl_ca_bundle'),
        dataGet($config, 'dependencies.maestro.ca_bundle'),
        dataGet($config, 'dependencies.maestro.curl_ca_bundle'),
    ];

    foreach ($caBundleCandidates as $candidate) {
        if (stringValue($candidate) !== null) {
            $settings['ca_bundle'] = stringValue($candidate);
            break;
        }
    }

    return $settings;
}

function clearLaravelConfigCache(string $rootPath): bool
{
    $cachePath = $rootPath.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php';
    if (! is_file($cachePath)) {
        return false;
    }

    return @unlink($cachePath);
}

function resolveEnvPath(array $config, string $rootPath): string
{
    $configured = dataGet($config, 'relay.data_prep.apply_settings.env_path')
        ?? dataGet($config, 'app.env_path')
        ?? dataGet($config, 'env_path');

    if (is_string($configured) && trim($configured) !== '') {
        return $configured;
    }

    return $rootPath.DIRECTORY_SEPARATOR.'.env';
}

function readEnvFile(string $path): array
{
    $values = [];
    if (! is_file($path)) {
        return $values;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || ! str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $values[$key] = trim($value, " \t\n\r\0\x0B\"'");
    }

    return $values;
}

function writeEnvFile(string $path, array $values): void
{
    $directory = dirname($path);
    if (! is_dir($directory)) {
        mkdir($directory, 0777, true);
    }

    $lines = [];
    foreach ($values as $key => $value) {
        $lines[] = $key.'='.envValue((string) $value);
    }

    file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);
}

function envValue(string $value): string
{
    if ($value === '') {
        return '';
    }

    if (preg_match('/^[A-Za-z0-9_.,:\/@+\-]+$/', $value) === 1) {
        return $value;
    }

    return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
}

function boolString(mixed $value): string
{
    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === false ? 'false' : 'true';
}

function stringValue(mixed $value): ?string
{
    if (! is_string($value)) {
        return null;
    }

    $value = trim($value);

    return $value !== '' ? $value : null;
}

function dataGet(array $data, string $path): mixed
{
    $current = $data;
    foreach (explode('.', $path) as $segment) {
        if (! is_array($current) || ! array_key_exists($segment, $current)) {
            return null;
        }
        $current = $current[$segment];
    }

    return $current;
}

function emitJson(array $payload, ?string $path): void
{
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

    if ($path !== null && $path !== '') {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $json);
    }

    echo $json;
}
