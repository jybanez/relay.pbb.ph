<?php

namespace Pbb\HubRelaySdk;

use RuntimeException;

class PbbHubRelayClient
{
    private string $protocolVersion;
    private ?array $lastCompatibility = null;

    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        string $protocolVersion = '1.1',
    ) {
        $this->protocolVersion = $protocolVersion;
    }

    public function diagnostics(): array
    {
        return $this->request('GET', '/api/v1/diagnostics');
    }

    public function compatibility(): array
    {
        return $this->request('GET', '/api/v1/compatibility');
    }

    public function submitMessage(array $payload): array
    {
        return $this->request('POST', '/api/v1/messages', $payload);
    }

    public function listMessages(array $query = []): array
    {
        return $this->request('GET', '/api/v1/messages', null, $query);
    }

    public function getMessage(string $messageId): array
    {
        return $this->request('GET', '/api/v1/messages/'.$messageId);
    }

    public function listDeliveries(array $query = []): array
    {
        return $this->request('GET', '/api/v1/deliveries', null, $query);
    }

    public function retryDelivery(string $deliveryId): array
    {
        return $this->request('POST', '/api/v1/deliveries/'.$deliveryId.'/retry');
    }

    public function cancelDelivery(string $deliveryId): array
    {
        return $this->request('POST', '/api/v1/deliveries/'.$deliveryId.'/cancel');
    }

    public function listInbox(array $query = []): array
    {
        return $this->request('GET', '/api/v1/inbox', null, $query);
    }

    public function listHandlers(): array
    {
        return $this->request('GET', '/api/v1/handlers');
    }

    public function createHandler(array $payload): array
    {
        return $this->request('POST', '/api/v1/handlers', $payload);
    }

    public function listHandlerDispatches(array $query = []): array
    {
        return $this->request('GET', '/api/v1/handler-dispatches', null, $query);
    }

    public function retryHandlerDispatch(string $dispatchId): array
    {
        return $this->request('POST', '/api/v1/handler-dispatches/'.$dispatchId.'/retry');
    }

    public function ensureCompatibility(): array
    {
        $compatibility = $this->compatibility();
        $supported = $compatibility['supported_protocol_versions'] ?? [$compatibility['version']['relay_protocol_version'] ?? '1.0'];

        if (!in_array($this->protocolVersion, $supported, true)) {
            throw new RuntimeException('Configured protocol version ['.$this->protocolVersion.'] is not supported by the relay server.');
        }

        $this->lastCompatibility = $compatibility;

        return $compatibility;
    }

    public function supportsCapability(string $capability): bool
    {
        $compatibility = $this->lastCompatibility ?? $this->ensureCompatibility();

        return in_array($capability, $compatibility['relay_protocol_capabilities'] ?? [], true);
    }

    private function request(string $method, string $path, ?array $payload = null, array $query = []): array
    {
        $url = rtrim($this->baseUrl, '/').$path;

        if ($query !== []) {
            $url .= '?'.http_build_query($query);
        }

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL.');
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'X-Relay-Key: '.$this->apiKey,
            'X-Relay-Protocol-Version: '.$this->protocolVersion,
        ];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        $raw = curl_exec($ch);

        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Relay request failed: '.$error);
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            throw new RuntimeException('Relay response was not valid JSON.');
        }

        if ($status >= 400) {
            throw new RuntimeException('Relay request failed with HTTP '.$status.': '.($decoded['error'] ?? 'Unknown error'));
        }

        return $decoded;
    }
}
