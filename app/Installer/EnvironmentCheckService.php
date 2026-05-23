<?php

namespace App\Installer;

class EnvironmentCheckService
{
    public function run(): array
    {
        $checks = array_merge(
            $this->runtimeChecks(),
            $this->extensionChecks(),
            $this->archiveChecks(),
            $this->databaseChecks(),
            $this->filesystemChecks(),
        );

        $grouped = [];
        $blockingFailureCount = 0;
        $warningCount = 0;

        foreach ($checks as $check) {
            $group = $check['group'];
            $grouped[$group][] = $check;

            if (($check['status'] ?? null) === 'fail' && ($check['blocking'] ?? true)) {
                $blockingFailureCount++;
            }

            if (($check['status'] ?? null) === 'warning') {
                $warningCount++;
            }
        }

        return [
            'status' => $blockingFailureCount === 0 ? 'ready' : 'blocked',
            'can_continue' => $blockingFailureCount === 0,
            'summary' => [
                'total_checks' => count($checks),
                'blocking_failures' => $blockingFailureCount,
                'warnings' => $warningCount,
                'groups' => array_keys($grouped),
            ],
            'groups' => $grouped,
        ];
    }

    private function runtimeChecks(): array
    {
        $minimum = (string) config('installer.requirements.php_min', '8.2.0');
        $current = PHP_VERSION;
        $passes = version_compare($current, $minimum, '>=');

        return [[
            'key' => 'php_version',
            'group' => 'runtime',
            'label' => 'PHP Version',
            'status' => $passes ? 'pass' : 'fail',
            'message' => "PHP $current detected.",
            'hint' => $passes ? null : "Upgrade PHP to at least $minimum.",
            'blocking' => true,
        ]];
    }

    private function extensionChecks(): array
    {
        $checks = [];

        foreach ((array) config('installer.requirements.extensions', []) as $extension) {
            $loaded = extension_loaded($extension);

            $checks[] = [
                'key' => 'extension_'.$extension,
                'group' => 'extensions',
                'label' => strtoupper($extension).' Extension',
                'status' => $loaded ? 'pass' : 'fail',
                'message' => $loaded ? "Extension [$extension] is loaded." : "Extension [$extension] is not loaded.",
                'hint' => $loaded ? null : "Enable the PHP [$extension] extension.",
                'blocking' => true,
            ];
        }

        return $checks;
    }

    private function archiveChecks(): array
    {
        $hasZipArchive = class_exists(\ZipArchive::class);

        return [[
            'key' => 'zip_archive',
            'group' => 'archive',
            'label' => 'ZIP Archive Support',
            'status' => $hasZipArchive ? 'pass' : 'fail',
            'message' => $hasZipArchive ? 'ZipArchive is available.' : 'ZipArchive is not available.',
            'hint' => $hasZipArchive ? null : 'Enable the PHP zip extension with ZipArchive support.',
            'blocking' => true,
        ]];
    }

    private function databaseChecks(): array
    {
        return [
            [
                'key' => 'pdo_mysql',
                'group' => 'database',
                'label' => 'MySQL Driver',
                'status' => extension_loaded('pdo_mysql') ? 'pass' : 'warning',
                'message' => extension_loaded('pdo_mysql') ? 'PDO MySQL is available.' : 'PDO MySQL is not available.',
                'hint' => extension_loaded('pdo_mysql') ? null : 'Enable PDO MySQL if this host will use MySQL.',
                'blocking' => false,
            ],
            [
                'key' => 'sqlite_driver',
                'group' => 'database',
                'label' => 'SQLite Driver',
                'status' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? 'pass' : 'warning',
                'message' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? 'SQLite support is available.' : 'SQLite support is not available.',
                'hint' => (extension_loaded('sqlite3') || extension_loaded('pdo_sqlite')) ? null : 'Enable SQLite if this host will use SQLite.',
                'blocking' => false,
            ],
        ];
    }

    private function filesystemChecks(): array
    {
        return [
            $this->pathCheck('install_root', 'filesystem', 'Install Root', base_path()),
            $this->pathCheck('installer_root', 'filesystem', 'Installer Working Root', (string) config('installer.bootstrap_root')),
            $this->pathCheck('env_target', 'filesystem', 'Environment File Target', base_path('.env')),
            $this->pathCheck('storage_path', 'filesystem', 'Storage Path', storage_path()),
            $this->pathCheck('bootstrap_cache', 'filesystem', 'Bootstrap Cache', base_path('bootstrap/cache')),
        ];
    }

    private function pathCheck(string $key, string $group, string $label, string $path): array
    {
        $result = $this->pathWritableOrCreatable($path);

        return [
            'key' => $key,
            'group' => $group,
            'label' => $label,
            'status' => $result['ok'] ? 'pass' : 'fail',
            'message' => $result['message'],
            'hint' => $result['hint'],
            'blocking' => true,
        ];
    }

    private function pathWritableOrCreatable(string $path): array
    {
        if (is_dir($path)) {
            return [
                'ok' => is_writable($path),
                'message' => is_writable($path) ? "Directory [$path] is writable." : "Directory [$path] is not writable.",
                'hint' => is_writable($path) ? null : "Grant write permission to [$path].",
            ];
        }

        if (is_file($path)) {
            return [
                'ok' => is_writable($path),
                'message' => is_writable($path) ? "File [$path] is writable." : "File [$path] is not writable.",
                'hint' => is_writable($path) ? null : "Grant write permission to [$path].",
            ];
        }

        $parent = $this->nearestExistingParent($path);
        $parentWritable = $parent !== null && is_writable($parent);

        return [
            'ok' => $parentWritable,
            'message' => $parentWritable ? "Path [$path] can be created." : "Path [$path] cannot be created.",
            'hint' => $parentWritable ? null : 'Ensure a writable parent directory exists for ['.$path.'].',
        ];
    }

    private function nearestExistingParent(string $path): ?string
    {
        $current = dirname($path);

        while ($current !== '' && $current !== '.' && ! is_dir($current)) {
            $next = dirname($current);

            if ($next === $current) {
                break;
            }

            $current = $next;
        }

        return is_dir($current) ? $current : null;
    }
}
