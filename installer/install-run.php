<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/RelayKitInstaller.php';

function relay_installer_usage(): void
{
    fwrite(STDERR, "Usage: php installer/install-run.php --config <path> --report <path> [--mode fresh|upgrade|repair|preflight] [--dry-run] [--no-service-register] [--verbose]\n");
}

$startedAt = date(DATE_ATOM);

try {
    $args = RelayKitInstaller::parseArgs($argv);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    relay_installer_usage();
    exit(3);
}

if (($args['help'] ?? false) === true) {
    relay_installer_usage();
    exit(0);
}

if (! is_string($args['config']) || $args['config'] === '' || ! is_file($args['config'])) {
    fwrite(STDERR, "Config file is required and must exist.\n");
    relay_installer_usage();
    exit(2);
}

if (! is_string($args['report']) || $args['report'] === '') {
    fwrite(STDERR, "Report path is required.\n");
    relay_installer_usage();
    exit(2);
}

$config = [];

try {
    $config = RelayKitInstaller::normalizeConfig(
        RelayKitInstaller::readJsonFile($args['config']),
        is_string($args['mode']) ? $args['mode'] : null
    );

    if ((bool) $args['no_service_register']) {
        $config['services']['registration_mode'] = 'generate';
    }

    $errors = RelayKitInstaller::validateConfig($config);
    if ($errors !== []) {
        $report = RelayKitInstaller::report($config, 'failed', [
            RelayKitInstaller::step('config', 'failed', 'Installer configuration is incomplete.'),
        ], [
            'started_at' => $startedAt,
            'summary' => 'Relay installer configuration failed validation.',
            'errors' => array_map(
                static fn (string $id, string $message): array => ['id' => $id, 'message' => $message],
                array_keys($errors),
                $errors
            ),
        ]);
        RelayKitInstaller::writeJsonFile($args['report'], $report);
        exit(2);
    }

    $preflight = RelayKitInstaller::preflight($config);
    $preflightFailed = ($preflight['status'] ?? 'failed') !== 'passed';
    $mode = (string) $config['mode'];

    if ($mode === 'preflight' || (bool) $args['dry_run']) {
        $report = RelayKitInstaller::report($config, $preflightFailed ? 'failed' : 'success', [
            RelayKitInstaller::step('preflight', $preflightFailed ? 'failed' : 'success', $preflightFailed ? 'Blocking preflight checks failed.' : 'Preflight checks passed.'),
            RelayKitInstaller::step('dry-run', (bool) $args['dry_run'] ? 'success' : 'skipped', (bool) $args['dry_run'] ? 'Dry run completed without mutation.' : 'No install mutation requested.'),
        ], [
            'started_at' => $startedAt,
            'summary' => $preflightFailed ? 'Preflight failed.' : 'Preflight passed.',
            'errors' => $preflightFailed ? $preflight['checks'] : [],
        ]);
        $report['preflight'] = $preflight;
        RelayKitInstaller::writeJsonFile($args['report'], $report);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        exit($preflightFailed ? 1 : 0);
    }

    if ($preflightFailed) {
        $report = RelayKitInstaller::report($config, 'failed', [
            RelayKitInstaller::step('preflight', 'failed', 'Blocking preflight checks failed.'),
        ], [
            'started_at' => $startedAt,
            'summary' => 'Install stopped before mutation because preflight failed.',
            'errors' => $preflight['checks'],
        ]);
        $report['preflight'] = $preflight;
        RelayKitInstaller::writeJsonFile($args['report'], $report);
        exit(1);
    }

    $result = RelayKitInstaller::install($config, $mode);
    $successSummary = match ($mode) {
        'upgrade' => 'PBB Relay upgrade completed successfully.',
        'repair' => 'PBB Relay repair completed successfully.',
        default => 'PBB Relay installed successfully.',
    };

    $report = RelayKitInstaller::report($config, 'success', array_merge([
        RelayKitInstaller::step('preflight', 'success', 'Blocking preflight checks passed.'),
    ], $result['steps']), [
        'started_at' => $startedAt,
        'summary' => $successSummary,
        'service_artifact' => $result['service_artifact'],
    ]);
    $report['manifest'] = $result['manifest'];
    $report['runtime_services'] = $result['manifest']['runtime_services'] ?? [];
    $report['preflight'] = $preflight;
    $report['database'] = $result['database'] ?? null;
    $report['database_setup'] = $result['manifest']['database_setup'] ?? null;
    $report['filesystem'] = $result['filesystem'] ?? null;
    $report['web_server'] = $result['manifest']['web_server'] ?? null;

    $installReportPath = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $config['app']['install_path']), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR . 'storage/app/installer/install-report.json';
    RelayKitInstaller::writeJsonFile($installReportPath, $report);
    RelayKitInstaller::writeJsonFile($args['report'], $report);

    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    $fallback = $config !== [] ? $config : RelayKitInstaller::normalizeConfig(['mode' => 'fresh']);
    $report = RelayKitInstaller::report($fallback, 'failed', [
        RelayKitInstaller::step('install', 'failed', $e->getMessage()),
    ], [
        'started_at' => $startedAt,
        'summary' => 'Relay installer failed.',
        'errors' => [['id' => 'install.exception', 'message' => $e->getMessage()]],
    ]);
    RelayKitInstaller::writeJsonFile((string) $args['report'], $report);
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
