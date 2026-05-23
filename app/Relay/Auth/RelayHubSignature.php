<?php

namespace App\Relay\Auth;

class RelayHubSignature
{
    public function sign(string $timestamp, string $body, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . "\n" . $body, $secret);
    }

    public function verify(string $timestamp, string $body, string $secret, string $signature): bool
    {
        return hash_equals($this->sign($timestamp, $body, $secret), $signature);
    }
}
