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

$configResult = loadConfig($configPath);
if ($configResult['status'] === 'failed') {
    $errors[] = $configResult['message'];
    $config = [];
} else {
    $config = $configResult['config'];
}

if ($errors === []) {
    try {
        $envPath = resolveEnvPath($config, $rootPath);
        $env = readEnvFile($envPath);

        $checks = [
            'maestro_enabled' => [
                'passed' => filter_var($env['RELAY_MAESTRO_ENABLED'] ?? false, FILTER_VALIDATE_BOOL) === true,
                'message' => 'RELAY_MAESTRO_ENABLED is true.',
            ],
            'maestro_base_url' => [
                'passed' => trim((string) ($env['RELAY_MAESTRO_BASE_URL'] ?? '')) !== '',
                'message' => 'RELAY_MAESTRO_BASE_URL is configured.',
            ],
            'maestro_app_code' => [
                'passed' => trim((string) ($env['RELAY_MAESTRO_APP_CODE'] ?? '')) !== '',
                'message' => 'RELAY_MAESTRO_APP_CODE is configured.',
            ],
            'maestro_telemetry_token' => [
                'passed' => trim((string) ($env['RELAY_MAESTRO_TELEMETRY_TOKEN'] ?? '')) !== '',
                'message' => 'RELAY_MAESTRO_TELEMETRY_TOKEN is configured.',
            ],
            'maestro_tls_verify' => [
                'passed' => true,
                'message' => 'RELAY_MAESTRO_TLS_VERIFY is readable.',
                'verify_tls' => filter_var($env['RELAY_MAESTRO_TLS_VERIFY'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== false,
            ],
            'maestro_ca_bundle' => [
                'passed' => true,
                'message' => 'RELAY_MAESTRO_CA_BUNDLE status was checked.',
                'ca_bundle_configured' => stringValue($env['RELAY_MAESTRO_CA_BUNDLE'] ?? null) !== null,
                'ca_bundle_exists' => stringValue($env['RELAY_MAESTRO_CA_BUNDLE'] ?? null) !== null
                    && is_file((string) stringValue($env['RELAY_MAESTRO_CA_BUNDLE'] ?? null)),
            ],
        ];

        foreach ($checks as $id => $check) {
            if (! $check['passed']) {
                $errors[] = $check['message'].' Check failed.';
            }

            $results[] = [
                'id' => $id,
                'type' => 'app_setting',
                'status' => $check['passed'] ? 'success' : 'failed',
                'message' => $check['message'],
                'token_supplied' => $id === 'maestro_telemetry_token' ? $check['passed'] : null,
                'verify_tls' => $check['verify_tls'] ?? null,
                'ca_bundle_configured' => $check['ca_bundle_configured'] ?? null,
                'ca_bundle_exists' => $check['ca_bundle_exists'] ?? null,
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
    'tool' => 'data_prep_verify',
    'mode' => $mode,
    'dry_run' => $dryRun,
    'status' => $status,
    'summary' => $status === 'success'
        ? 'Relay Maestro settings verification passed.'
        : 'Relay Maestro settings verification failed.',
    'started_at' => $startedAt,
    'finished_at' => gmdate('c'),
    'sources' => [
        [
            'id' => 'relay_environment',
            'path' => '.env',
        ],
    ],
    'results' => $results,
    'outputs' => [],
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
            'message' => 'Config file is required for Relay Data Prep verification.',
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

function resolveEnvPath(array $config, string $rootPath): string
{
    $configured = dataGet($config, 'relay.data_prep.verify.env_path')
        ?? dataGet($config, 'relay.data_prep.apply_settings.env_path')
        ?? dataGet($config, 'app.env_path')
        ?? dataGet($config, 'env_path');

    if (is_string($configured) && trim($configured) !== '') {
        return $configured;
    }

    return $rootPath.DIRECTORY_SEPARATOR.'.env';
}

function readEnvFile(string $path): array
{
    if (! is_file($path)) {
        throw new RuntimeException('Relay environment file was not found: '.$path);
    }

    $values = [];
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
