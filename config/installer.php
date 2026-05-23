<?php

$normalizePath = static function (string $path): string {
    return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
};

$basePath = base_path();
$embeddedRuntimeBasePath = $normalizePath($basePath);
$embeddedRuntimeSuffix = $normalizePath('.installer/runtime/installer-runtime');
$embeddedRuntime = str_ends_with($embeddedRuntimeBasePath, $embeddedRuntimeSuffix);

$installRoot = $embeddedRuntime
    ? dirname(dirname(dirname($basePath)))
    : $basePath;

$bootstrapRoot = env('INSTALLER_BOOTSTRAP_ROOT', $installRoot.DIRECTORY_SEPARATOR.'.installer');
$installedAppRoot = env('INSTALLER_INSTALLED_APP_ROOT', $embeddedRuntime
    ? $installRoot.DIRECTORY_SEPARATOR.'.relay'.DIRECTORY_SEPARATOR.'app'
    : $basePath);
$releasePackagePath = env('INSTALLER_RELEASE_PACKAGE_PATH', $embeddedRuntime
    ? $bootstrapRoot.DIRECTORY_SEPARATOR.'runtime'.DIRECTORY_SEPARATOR.'relay-release'.DIRECTORY_SEPARATOR.'relay-release.zip'
    : '');
$releaseExtractRoot = env('INSTALLER_RELEASE_EXTRACT_ROOT', $embeddedRuntime
    ? $installedAppRoot
    : storage_path('app/installer/release'));

return [
    'enabled' => filter_var(env('INSTALLER_ENABLED', false), FILTER_VALIDATE_BOOL),
    'embedded_runtime' => $embeddedRuntime,
    'install_root' => env('INSTALLER_INSTALL_ROOT', $installRoot),
    'lock_path' => env('INSTALLER_LOCK_PATH', $installRoot.DIRECTORY_SEPARATOR.'.relay-installed.lock'),
    'state_path' => env('INSTALLER_STATE_PATH', $bootstrapRoot.DIRECTORY_SEPARATOR.'state.json'),
    'execution_state_path' => env('INSTALLER_EXECUTION_STATE_PATH', $bootstrapRoot.DIRECTORY_SEPARATOR.'execution-state.json'),
    'execution_lock_path' => env('INSTALLER_EXECUTION_LOCK_PATH', $bootstrapRoot.DIRECTORY_SEPARATOR.'locks'.DIRECTORY_SEPARATOR.'execution.lock'),
    'bootstrap_root' => $bootstrapRoot,
    'installed_app_root' => $installedAppRoot,
    'env_path' => env('INSTALLER_ENV_PATH', $embeddedRuntime
        ? $installedAppRoot.DIRECTORY_SEPARATOR.'.env'
        : base_path('.env')),
    'hq_api_base_url' => env('INSTALLER_HQ_API_BASE_URL', env('RELAY_HQ_API_BASE_URL', 'https://hub.pbb.ph')),
    'release_package_path' => $releasePackagePath,
    'release_extract_root' => $releaseExtractRoot,
    'release_expected_paths' => [
        'app',
        'bootstrap',
        'config',
        'database',
        'public',
        'resources',
        'routes',
        'storage',
        'vendor',
    ],
    'cleanup_manifest_path' => env('INSTALLER_CLEANUP_MANIFEST_PATH', $bootstrapRoot.DIRECTORY_SEPARATOR.'cleanup.json'),
    'cleanup_root' => env('INSTALLER_CLEANUP_ROOT', $installRoot),
    'cleanup_auto_run' => filter_var(env('INSTALLER_CLEANUP_AUTO_RUN', false), FILTER_VALIDATE_BOOL),
    'requirements' => [
        'php_min' => '8.2.0',
        'extensions' => [
            'json',
            'openssl',
            'mbstring',
            'fileinfo',
            'zip',
            'pdo',
        ],
    ],
];
