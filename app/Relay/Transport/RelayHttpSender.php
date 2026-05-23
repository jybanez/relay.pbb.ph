<?php

namespace App\Relay\Transport;

use App\Relay\Auth\RelayHubSignature;
use App\Relay\Auth\RelayHubTransportAuth;
use App\Relay\Registry\RelayNodeIdentityService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

class RelayHttpSender
{
    public function __construct(
        private HttpFactory $http,
        private RelayTargetResolver $resolver,
        private RelayHubSignature $signature,
        private RelayHubTransportAuth $transportAuth,
        private RelayNodeIdentityService $nodeIdentity,
    ) {}

    public function send(string $targetHubId, array $payload): Response
    {
        $target = $this->resolver->resolve($targetHubId);
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $request = $this->http
            ->acceptJson()
            ->timeout((int) config('relay.delivery.timeout_seconds', 10));

        $request = $request->withOptions($this->tlsOptions($target));

        if ($target['token'] !== null && in_array($this->transportAuth->mode(), ['shared_key', 'hmac', 'mtls_hmac'], true)) {
            $request = $request->withHeaders([
                'X-Relay-Hub-Key' => $target['token'],
            ]);
        }

        $localHubId = $this->nodeIdentity->localHubId();

        if (is_string($localHubId) && $localHubId !== '') {
            $request = $request->withHeaders([
                'X-Relay-Hub-Id' => $localHubId,
            ]);
        }

        if ($this->transportAuth->usesHmac() && $target['token'] !== null) {
            $timestamp = now()->toIso8601String();

            $request = $request->withHeaders([
                'X-Relay-Timestamp' => $timestamp,
                'X-Relay-Signature' => $this->signature->sign($timestamp, $body, $target['token']),
            ]);
        }

        return $request
            ->withBody($body, 'application/json')
            ->post($target['base_url'] . $target['receive_path']);
    }

    private function tlsOptions(array $target): array
    {
        $options = [
            'verify' => $target['verify_peer'],
        ];

        if ($target['client_certificate_path'] !== null) {
            $options['cert'] = $target['client_certificate_path'];
        }

        if ($target['client_private_key_path'] !== null) {
            $options['ssl_key'] = $target['client_private_key_passphrase'] !== null
                ? [$target['client_private_key_path'], $target['client_private_key_passphrase']]
                : $target['client_private_key_path'];
        }

        return $options;
    }
}
