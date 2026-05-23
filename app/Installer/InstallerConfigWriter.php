<?php

namespace App\Installer;

class InstallerConfigWriter
{
    /**
     * @param  array<string, scalar|null>  $values
     */
    public function write(array $values): string
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        if (! file_exists($path)) {
            $example = base_path('.env.example');
            $content = file_exists($example) ? (string) file_get_contents($example) : '';
            file_put_contents($path, $content);
        }

        $content = (string) file_get_contents($path);

        foreach ($values as $key => $value) {
            $content = $this->setEnvValue($content, (string) $key, $this->stringifyValue($value));
        }

        file_put_contents($path, $content);

        return $path;
    }

    private function setEnvValue(string $content, string $key, string $value): string
    {
        $escapedKey = preg_quote($key, '/');
        $line = $key.'='.$value;

        if (preg_match("/^{$escapedKey}=.*$/m", $content) === 1) {
            return (string) preg_replace("/^{$escapedKey}=.*$/m", $line, $content);
        }

        if ($content !== '' && ! str_ends_with($content, "\n")) {
            $content .= PHP_EOL;
        }

        return $content.$line.PHP_EOL;
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $string = (string) $value;

        if ($string === '' || preg_match('/\s/', $string) === 1) {
            return '"'.str_replace('"', '\"', $string).'"';
        }

        return $string;
    }

    private function path(): string
    {
        return (string) config('installer.env_path');
    }
}
