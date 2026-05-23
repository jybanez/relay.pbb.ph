<?php

namespace App\Installer;

class InstallerMode
{
    public function enabled(): bool
    {
        return (bool) config('installer.enabled', false);
    }

    public function installed(): bool
    {
        $lockPath = (string) config('installer.lock_path', '');

        return $lockPath !== '' && is_file($lockPath);
    }

    public function shouldServeInstaller(): bool
    {
        return $this->enabled() && ! $this->installed();
    }
}
