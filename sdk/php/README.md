# PBB - Hub Relay Server PHP SDK

Minimal convenience client for the local relay API.

```php
<?php

require __DIR__.'/src/PbbHubRelayClient.php';

use Pbb\HubRelaySdk\PbbHubRelayClient;

$client = new PbbHubRelayClient('https://relay.pbb.ph', 'test-relay-key', '1.1');

$diagnostics = $client->diagnostics();
$messages = $client->listMessages();
$client->ensureCompatibility();
```

Supported methods:

- `diagnostics()`
- `compatibility()`
- `submitMessage(array $payload)`
- `listMessages(array $query = [])`
- `getMessage(string $messageId)`
- `listDeliveries(array $query = [])`
- `retryDelivery(string $deliveryId)`
- `cancelDelivery(string $deliveryId)`
- `listInbox(array $query = [])`
- `listHandlers()`
- `createHandler(array $payload)`
- `listHandlerDispatches(array $query = [])`
- `retryHandlerDispatch(string $dispatchId)`
- `ensureCompatibility()`
- `supportsCapability(string $capability)`

Package metadata:

- package name: `pbb/hub-relay-sdk`
- current version: `0.2.0`
