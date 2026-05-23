<?php

namespace App\Installer;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

class InstallerDatabaseService
{
    public const CONNECTION = 'installer_runtime';

    /**
     * @param  array<string, mixed>  $settings
     */
    public function verify(array $settings): void
    {
        $driver = (string) ($settings['database_driver'] ?? 'sqlite');

        if ($driver === 'sqlite') {
            $this->verifySqlite((string) ($settings['sqlite_path'] ?? ''));

            return;
        }

        $this->verifyMysql(
            (string) ($settings['database_host'] ?? ''),
            (int) ($settings['database_port'] ?? 3306),
            (string) ($settings['database_name'] ?? ''),
            (string) ($settings['database_username'] ?? ''),
            (string) ($settings['database_password'] ?? ''),
        );
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function migrate(): void
    {
        $exitCode = Artisan::call('migrate', [
            '--database' => self::CONNECTION,
            '--force' => true,
        ]);

        if ($exitCode !== 0) {
            throw new RuntimeException('Installer migration failed: '.Artisan::output());
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function runAgainstConnection(array $settings, callable $callback): mixed
    {
        $originalDefault = config('database.default');
        $originalConnections = config('database.connections');

        try {
            config([
                'database.connections.'.self::CONNECTION => $this->laravelConnectionConfig($settings),
                'database.default' => self::CONNECTION,
            ]);

            DB::purge(self::CONNECTION);
            DB::reconnect(self::CONNECTION);
            return $callback(self::CONNECTION);
        } finally {
            DB::purge(self::CONNECTION);
            config([
                'database.default' => $originalDefault,
                'database.connections' => $originalConnections,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function laravelConnectionConfig(array $settings): array
    {
        $driver = (string) ($settings['database_driver'] ?? 'sqlite');

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => (string) ($settings['sqlite_path'] ?? ''),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => (string) ($settings['database_host'] ?? ''),
            'port' => (string) ($settings['database_port'] ?? 3306),
            'database' => (string) ($settings['database_name'] ?? ''),
            'username' => (string) ($settings['database_username'] ?? ''),
            'password' => (string) ($settings['database_password'] ?? ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ];
    }

    private function verifySqlite(string $path): void
    {
        if ($path === '') {
            throw new RuntimeException('SQLite path is required.');
        }

        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("SQLite directory [$directory] could not be created.");
        }

        if (! file_exists($path) && file_put_contents($path, '') === false) {
            throw new RuntimeException("SQLite database file [$path] could not be created.");
        }

        new PDO('sqlite:'.$path);
    }

    private function verifyMysql(string $host, int $port, string $database, string $username, string $password): void
    {
        if ($host === '' || $database === '' || $username === '') {
            throw new RuntimeException('MySQL host, database, and username are required.');
        }

        new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database),
            $username,
            $password,
        );
    }
}
