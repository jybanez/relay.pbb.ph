<?php

namespace App\Installer;

use RuntimeException;
use ZipArchive;

class InstallerReleasePackageService
{
    public function extractIfConfigured(): ?array
    {
        $packagePath = trim((string) config('installer.release_package_path', ''));

        if ($packagePath === '') {
            return null;
        }

        if (! is_file($packagePath)) {
            throw new RuntimeException("Release package [$packagePath] was not found.");
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('ZipArchive is required to extract the Relay release package.');
        }

        $extractRoot = (string) config('installer.release_extract_root');

        if ($extractRoot === '') {
            throw new RuntimeException('Installer release extract root is not configured.');
        }

        $this->resetDirectory($extractRoot);

        $archive = new ZipArchive();
        $opened = $archive->open($packagePath);

        if ($opened !== true) {
            throw new RuntimeException("Release package [$packagePath] could not be opened.");
        }

        $archive->extractTo($extractRoot);
        $archive->close();

        $missing = [];

        foreach ((array) config('installer.release_expected_paths', []) as $expectedPath) {
            if (! file_exists($extractRoot.DIRECTORY_SEPARATOR.$expectedPath)) {
                $missing[] = $expectedPath;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('Release package is missing expected paths: '.implode(', ', $missing));
        }

        return [
            'package_path' => $packagePath,
            'extract_root' => $extractRoot,
            'expected_paths' => (array) config('installer.release_expected_paths', []),
        ];
    }

    private function resetDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            $this->deleteDirectory($directory);
        }

        mkdir($directory, 0777, true);
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

            $path = $directory.DIRECTORY_SEPARATOR.$item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } elseif (file_exists($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
