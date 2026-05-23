# PBB - Hub Relay Server JS Client

Minimal convenience wrapper around the local relay API.

```js
import { PbbHubRelayClient } from "./pbb-hub-relay-client.mjs";

const client = new PbbHubRelayClient({
  baseUrl: "https://relay.pbb.ph",
  apiKey: "test-relay-key",
  protocolVersion: "1.1",
});

const diagnostics = await client.diagnostics();
const messages = await client.listMessages();
await client.ensureCompatibility();
```

Supported methods:

- `diagnostics()`
- `compatibility()`
- `submitMessage(payload)`
- `listMessages(query)`
- `getMessage(messageId)`
- `listDeliveries(query)`
- `retryDelivery(deliveryId)`
- `cancelDelivery(deliveryId)`
- `listInbox(query)`
- `listHandlers()`
- `createHandler(payload)`
- `listHandlerDispatches(query)`
- `retryHandlerDispatch(dispatchId)`
- `ensureCompatibility()`
- `supportsCapability(capability)`

Package metadata:

- package name: `@pbb/hub-relay-client`
- current version: `0.2.0`
