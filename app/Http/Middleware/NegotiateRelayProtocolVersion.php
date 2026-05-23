<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NegotiateRelayProtocolVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestedVersion = $this->resolveRequestedVersion($request);
        $minimumVersion = (string) config('relay.version.minimum_supported_protocol', '1.0');
        $currentVersion = (string) config('relay.version.protocol', '1.0');
        $supportedVersions = array_values(array_filter(array_map('strval', (array) config('relay.version.supported_protocols', [$currentVersion]))));

        if ($requestedVersion !== null && version_compare($requestedVersion, $minimumVersion, '<')) {
            return response()->json([
                'success' => false,
                'error' => 'Unsupported relay protocol version',
                'requested_protocol_version' => $requestedVersion,
                'minimum_supported_protocol_version' => $minimumVersion,
                'relay_protocol_version' => $currentVersion,
            ], 426);
        }

        if ($requestedVersion !== null && !in_array($requestedVersion, $supportedVersions, true)) {
            return response()->json([
                'success' => false,
                'error' => 'Relay protocol version is not supported by this node',
                'requested_protocol_version' => $requestedVersion,
                'supported_protocol_versions' => $supportedVersions,
                'relay_protocol_version' => $currentVersion,
            ], 406);
        }

        $response = $next($request);

        $response->headers->set('X-Relay-Protocol-Version', $currentVersion);
        $response->headers->set('X-Relay-Minimum-Supported-Protocol-Version', $minimumVersion);
        $response->headers->set('X-Relay-Supported-Protocol-Versions', implode(',', $supportedVersions));
        $response->headers->set('X-Relay-Protocol-Capabilities', implode(',', (array) config('relay.version.capabilities', [])));
        $response->headers->set('X-Relay-Package-Version', (string) config('relay.version.package', '1.1.0'));
        $response->headers->set('Vary', trim($response->headers->get('Vary').' ,X-Relay-Protocol-Version,Accept', ' ,'));

        if ($requestedVersion !== null && $requestedVersion !== $currentVersion) {
            $response->headers->set('X-Relay-Requested-Protocol-Version', $requestedVersion);
            $response->headers->set('X-Relay-Protocol-Compatibility-Mode', 'legacy');
        }

        return $response;
    }

    private function resolveRequestedVersion(Request $request): ?string
    {
        $header = $request->header('X-Relay-Protocol-Version');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        $accept = $request->header('Accept');

        if (!is_string($accept) || $accept === '') {
            return null;
        }

        if (preg_match('/application\/vnd\.pbb-hub-relay\.v(?P<major>\d+)(?:\.(?P<minor>\d+))?\+json/i', $accept, $matches) === 1) {
            return isset($matches['minor']) && $matches['minor'] !== ''
                ? $matches['major'].'.'.$matches['minor']
                : $matches['major'].'.0';
        }

        return null;
    }
}
