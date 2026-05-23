<?php

namespace App\Installer;

use RuntimeException;

class InstallerCleanupService
{
    /**
     * @param  list<string>  $delete
     * @param  list<string>  $preserve
     * @return array<string, mixed>
     */
    public function createManifest(array $delete, array $preserve = []): array
    {
        $manifest = [
            'delete' => array_values(array_unique(array_filter($delete, fn ($path) => is_string($path) && trim($path) !== ''))),
            'preserve' => array_values(array_unique(array_filter($preserve, fn ($path) => is_string($path) && trim($path) !== ''))),
        ];

        $path = $this->manifestPath();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $manifest;
    }

    public function hasManifest(): bool
    {
        return is_file($this->manifestPath());
    }

    public function cleanup(): array
    {
        $path = $this->manifestPath();

        if (! is_file($path)) {
            throw new RuntimeException('Cleanup manifest was not found.');
        }

        $manifest = json_decode((string) file_get_contents($path), true);

        if (! is_array($manifest)) {
            throw new RuntimeException('Cleanup manifest is invalid.');
        }

        $deleted = [];
        $preserve = array_values(array_filter(
            (array) ($manifest['preserve'] ?? []),
            fn ($target) => is_string($target) && trim($target) !== '',
        ));

        foreach ((array) ($manifest['delete'] ?? []) as $target) {
            if (in_array($target, $preserve, true)) {
                continue;
            }

            $resolved = $this->resolveWithinCleanupRoot((string) $target);

            if ($resolved === null || ! file_exists($resolved)) {
                continue;
            }

            if (is_dir($resolved)) {
                $this->deleteDirectory($resolved);
            } else {
                unlink($resolved);
            }

            $deleted[] = $target;
        }

        unlink($path);

        return [
            'deleted' => $deleted,
        ];
    }

    private function resolveWithinCleanupRoot(string $path): ?string
    {
        $root = rtrim((string) config('installer.cleanup_root'), DIRECTORY_SEPARATOR);

        if ($root === '') {
            throw new RuntimeException('Installer cleanup root is not configured.');
        }

        $candidate = $this->isAbsolutePath($path)
            ? $path
            : $root.DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);

        $normalizedRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root), DIRECTORY_SEPARATOR);
        $normalizedCandidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);

        if (! $this->isWithinRoot($normalizedRoot, $normalizedCandidate)) {
            throw new RuntimeException("Cleanup target [$path] is outside the allowed cleanup root.");
        }

        return $normalizedCandidate;
    }

    private function manifestPath(): string
    {
        return (string) config('installer.cleanup_manifest_path');
    }

    private function isAbsolutePath(string $path): bool
    {
        return preg_match('/^[A-Za-z]:\\\\|^\//', $path) === 1;
    }

    private function isWithinRoot(string $root, string $candidate): bool
    {
        return $candidate === $root || str_starts_with($candidate, $root.DIRECTORY_SEPARATOR);
    }

    private function deleteDirectory(string $directory): void
    {
        $items = scandir($directory);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($child)) {
                $this->deleteDirectory($child);
            } elseif (file_exists($child)) {
                unlink($child);
            }
        }

        rmdir($directory);
    }
}
