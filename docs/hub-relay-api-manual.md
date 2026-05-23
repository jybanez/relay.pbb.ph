# PBB - Hub Relay Server API Manual

This manual is for engineers integrating local applications or remote hubs with `PBB - Hub Relay Server`.

For the exact endpoint catalog, see [hub-relay-api-reference.md](./hub-relay-api-reference.md).

## Who This Is For

- Local application engineers sending messages into the relay
- Local application engineers consuming received messages from the relay
- Engineers configuring webhook handlers for local systems
- Engineers connecting one relay instance to another relay instance

## Base URL

Production:

```text
https://relay.pbb.ph
```

Local development:

```text
http://localhost:8000
```

All relay endpoints in this document are under:

```text
/api/v1
```

The shared lightweight hub heartbeat endpoint is:

```text
/api/status
```

The minimal probe endpoint is:

```text
/api/health
```

## Two API Surfaces

### 1. Local Application API

Use this when a local system such as `sitrep.app` or another partner system needs to:

- submit outbound relay messages
- check its own outbound delivery status
- read received inbound messages from the inbox
- register webhook handlers for local handoff
- manage attachment uploads attached to locally submitted messages

Important routing field:

- local clients submit `target_systems[]`
- Relay owns hub topology and next-hop routing
- `target_systems[]` maps to destination client `system_code` values

Authentication:

- `X-Relay-Key: <local-client-api-key>`

How to get a local client API key:

1. A relay admin logs into `/relay/login`
2. Go to `/relay/clients`
3. Create or rotate a client token
4. Save the generated token immediately

## Public Heartbeat Endpoint

Use this when another PBB system only needs a lightweight availability and backlog snapshot from Relay.

Endpoint:

```http
GET /api/status
```

Auth:

- none

Response includes:

- overall status
- local hub id
- relay version info
- queue counts
- inbox receipt count

Minimal probe endpoint:

```http
GET /api/health
```

Response includes:

- `status: ok`

## Browser Shell Session API

Relay also exposes a small session-authenticated browser shell API for operator login, account updates, re-auth, and keepalive behavior.

Endpoints:

- `GET /api/bootstrap`
- `GET /api/csrf-token`
- `POST /api/login`
- `GET /api/user`
- `POST /api/user`
- `POST /api/user/password`
- `POST /api/logout`
- `GET /api/session/ping`

What these are for:

- `GET /api/bootstrap` is the primary startup contract for the Relay browser shell
- `POST /api/login` is used by the public home login modal
- `GET /api/user`, `POST /api/user`, and `POST /api/user/password` support the shared account/user-menu flow
- `GET /api/session/ping` supports activity-aware near-expiry keepalive
- `POST /api/logout` closes the session and refreshes CSRF state

These endpoints are not part of the local client relay protocol or the hub-to-hub relay protocol. They exist for the authenticated Relay operator UI.

## 2. Hub-to-Hub API

Use this when one relay server is delivering to another relay server.

Authentication modes supported:

- `shared_key`
- `hmac`
- `mtls`
- `mtls_hmac`

The active inbound mode depends on the receiving relay configuration.

## Protocol Headers

Relay API routes are protected by protocol negotiation middleware.

Recommended request header:

```text
X-Relay-Protocol-Version: 1.0
```

If the request version is unsupported, the relay rejects it. Use:

```http
GET /api/v1/compatibility
```

to discover supported protocol versions and auth modes.

## Typical Local Application Flow

### 1. Submit a message

Send a `POST /api/v1/messages` request with:

- `source_system`
- `target_systems`
- `message_type`
- `payload`

Optional fields:

- `payload_format`
- `payload_version`
- `reference_type`
- `reference_id`
- `tags`
- `priority`
- `correlation_id`
- `attachments_count`
- `occurred_at`

Example:

```bash
curl -X POST http://localhost:8000/api/v1/messages \
  -H "Content-Type: application/json" \
  -H "X-Relay-Key: relay_xxx" \
  -H "X-Relay-Protocol-Version: 1.0" \
  -d '{
    "source_system": "sitrep.app",
    "target_systems": [
      "city-eoc.app"
    ],
    "message_type": "sitrep.record",
    "payload": {
      "incident_id": 123,
      "description": "Structure fire on Main St"
    },
    "priority": "high"
  }'
```

Successful response:

```json
{
  "success": true,
  "relay_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "message_id": "01ARZ3NDEKTSV4RRFFQ69G5FBW",
  "status": "queued",
  "deliveries_count": 1,
  "deliveries": [
    {
      "id": "01ARZ3NDEKTSV4RRFFQ69G5FCX",
      "target_hq_hub_id": "10",
      "status": "queued"
    }
  ]
}
```

### 2. Track delivery status

Use:

- `GET /api/v1/messages`
- `GET /api/v1/messages/{message}`
- `GET /api/v1/deliveries`
- `GET /api/v1/deliveries/{delivery}`

Ownership rule:

- outbound messages are stored under the authenticated local client
- message and delivery list/detail endpoints only return records owned by that client

For failed or dead deliveries:

- `POST /api/v1/deliveries/{delivery}/retry`
- `POST /api/v1/deliveries/{delivery}/cancel`

### 3. Attach files if needed

For local attachments:

1. `POST /api/v1/messages/{message}/attachments/init`
2. `POST /api/v1/uploads/{session}/chunk`
3. `POST /api/v1/uploads/{session}/complete`
4. `POST /api/v1/attachments/{attachment}/complete`

The relay currently accepts raw chunk bytes as the request body during chunk upload.

### 4. Consume inbound messages

If your local system wants pull-based consumption:

- `GET /api/v1/inbox`
- `GET /api/v1/inbox/{message}`

Current inbound boundary:

- inbox reads are `target_systems[]`-based
- a client can read an inbound message only when `target_systems[]` contains that client's `system_code`
- handler dispatch is also constrained to client systems present in `target_systems[]`

If your local system wants push-based consumption:

- register a handler with `POST /api/v1/handlers`
- inspect tracked dispatch attempts with `GET /api/v1/handler-dispatches`
- retry failed local handoff attempts with `POST /api/v1/handler-dispatches/{dispatch}/retry`

## Typical Hub-to-Hub Flow

### 1. Check compatibility

Before connecting relays, the sender should inspect:

```http
GET /api/v1/compatibility
```

This returns:

- relay package version
- relay protocol version
- minimum supported protocol version
- supported auth modes
- supported protocol versions

### 2. Deliver a message

Send:

```http
POST /api/v1/receive
```

with a hop envelope and the required auth headers.

Duplicate `relay_id` values are handled idempotently. The first receive returns `201`; duplicates return `200`.
If the message reaches the local HQ hub but none of its `target_systems[]` entries match a registered local client and there are no more eligible next hops, Relay accepts it and marks the receipt `undeliverable`.

### 3. Deliver multiple messages for catch-up

Use:

```http
POST /api/v1/receive-batch
```

This is useful after outages or during catch-up synchronization.

### 4. Send attachments hub-to-hub

Use:

1. `POST /api/v1/upload/init`
2. `POST /api/v1/upload/chunk`
3. `POST /api/v1/upload/complete`
4. `GET /api/v1/upload/{session}/status`

## Authentication Details

### Local Application API

Header:

```text
X-Relay-Key: <client-api-key>
```

If the header is missing or invalid, the relay returns `401`.

### Shared Key Hub Auth

Header:

```text
X-Relay-Hub-Key: <shared-hub-key>
```

### HMAC Hub Auth

Headers:

```text
X-Relay-Hub-Key: <shared-hub-key>
X-Relay-Timestamp: <unix-timestamp-or-compatible-string>
X-Relay-Signature: <computed-signature>
```

### Certificate-Bound Hub Auth

The relay expects a trusted client certificate fingerprint to be forwarded by the web server or proxy header configured in:

- `RELAY_HUB_AUTH_CLIENT_CERTIFICATE_FINGERPRINT_HEADER`

Modes:

- `mtls`
- `mtls_hmac`

## Error Handling Expectations

Common response classes:

- `200 OK` for successful reads and idempotent duplicates
- `201 Created` for successful create/receive/init operations
- `401 Unauthorized` for invalid local client or hub credentials
- `422 Unprocessable Entity` for validation or business-rule failures
- `426 Upgrade Required` for unsupported relay protocol versions
- `500 Internal Server Error` for unexpected server failures

Validation failures generally return:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": [
      "Validation message"
    ]
  }
}
```

Business-rule failures generally return:

```json
{
  "success": false,
  "error": "Human-readable error"
}
```

## Operational Recommendations

- Always send `X-Relay-Protocol-Version: 1.0`
- Treat `relay_id` as the idempotency key for hub-to-hub delivery
- Store `message_id`, `delivery.id`, and `upload_session_id` from relay responses
- Prefer webhook handlers for near-real-time local processing
- Fall back to inbox polling for resilient local pull-based consumption
- For attachments, preserve chunk order and send the exact raw bytes in the request body

## Current Documentation Scope

This manual documents the current implemented API surface in this repository as of March 27, 2026.

Still not covered by the product at a mature level:

- full third-party onboarding guides by vertical/use-case
- audit logging guide for token/user lifecycle events
- granular role/permission matrix beyond `admin` and `operator`
