<?php

namespace App\Relay\Auth;

use Illuminate\Http\Request;

class RelayHubTransportAuth
{
    public function mode(): string
    {
        return (string) config('relay.hub_auth.mode', 'shared_key');
    }

    public function usesHmac(): bool
    {
        return in_array($this->mode(), ['hmac', 'mtls_hmac'], true);
    }

    public function requiresClientCertificate(): bool
    {
        return in_array($this->mode(), ['mtls', 'mtls_hmac'], true);
    }

    public function resolvePresentedFingerprint(Request $request): ?string
    {
        $configuredHeader = config('relay.hub_auth.client_certificate_fingerprint_header');
        $candidates = array_filter([
            is_string($configuredHeader) && $configuredHeader !== '' ? $configuredHeader : null,
            'X-Relay-Client-Cert-Fingerprint',
            'X-SSL-Client-SHA256',
            'X-Client-Cert-SHA256',
        ]);

        foreach ($candidates as $header) {
            $value = $request->header($header);

            if (is_string($value) && $value !== '') {
                return $this->normalizeFingerprint($value);
            }
        }

        $serverCandidates = [
            'HTTP_X_RELAY_CLIENT_CERT_FINGERPRINT',
            'HTTP_X_SSL_CLIENT_SHA256',
            'HTTP_X_CLIENT_CERT_SHA256',
            'SSL_CLIENT_FINGERPRINT',
            'SSL_CLIENT_CERT_SHA256',
        ];

        foreach ($serverCandidates as $key) {
            $value = $request->server($key);

            if (is_string($value) && $value !== '') {
                return $this->normalizeFingerprint($value);
            }
        }

        return null;
    }

    public function expectedFingerprint(array $hubConfig): ?string
    {
        $value = $hubConfig['tls_client_certificate_fingerprint']
            ?? $hubConfig['client_certificate_fingerprint']
            ?? null;

        return is_string($value) && $value !== ''
            ? $this->normalizeFingerprint($value)
            : null;
    }

    public function normalizeFingerprint(string $fingerprint): string
    {
        return strtolower(str_replace(':', '', trim($fingerprint)));
    }
}
