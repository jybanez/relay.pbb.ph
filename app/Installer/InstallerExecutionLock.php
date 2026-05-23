<?php

namespace App\Installer;

use RuntimeException;

class InstallerExecutionLock
{
    public function withLock(callable $callback): mixed
    {
        $path = (string) config('installer.execution_lock_path');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Installer execution lock directory [$directory] could not be created.");
        }

        $handle = fopen($path, 'c+');
        if (! is_resource($handle)) {
            throw new RuntimeException('Installer execution lock could not be opened.');
        }

        try {
            if (! flock($handle, LOCK_EX)) {
                throw new RuntimeException('Installer execution lock could not be acquired.');
            }

            fwrite($handle, (string) getmypid());
            fflush($handle);

            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
