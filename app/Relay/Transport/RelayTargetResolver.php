<?php

namespace App\Relay\Transport;

use App\Relay\Registry\RelayPeerResolver;

class RelayTargetResolver
{
    public function __construct(
        private RelayPeerResolver $peerResolver,
    ) {}

    /**
     * @return array{base_url: string, receive_path: string, token: ?string, client_certificate_path: ?string, client_private_key_path: ?string, client_private_key_passphrase: ?string, verify_peer: bool|string}
     */
    public function resolve(string $targetHubId): array
    {
        $target = $this->peerResolver->resolveOutbound($targetHubId);

        if (! is_array($target) || empty($target['base_url'])) {
            throw new RelayTargetConfigException("No relay target configuration found for hub [{$targetHubId}].");
        }

        return [
            'base_url' => rtrim((string) $target['base_url'], '/'),
            'receive_path' => '/' . ltrim((string) ($target['receive_path'] ?? '/api/v1/receive'), '/'),
            'token' => isset($target['token']) && $target['token'] !== '' ? (string) $target['token'] : null,
            'client_certificate_path' => isset($target['client_certificate_path']) && $target['client_certificate_path'] !== '' ? (string) $target['client_certificate_path'] : null,
            'client_private_key_path' => isset($target['client_private_key_path']) && $target['client_private_key_path'] !== '' ? (string) $target['client_private_key_path'] : null,
            'client_private_key_passphrase' => isset($target['client_private_key_passphrase']) && $target['client_private_key_passphrase'] !== '' ? (string) $target['client_private_key_passphrase'] : null,
            'verify_peer' => $target['verify_peer'] ?? true,
        ];
    }
}
