# PBB - Hub Relay Server API Reference

This reference is the implementation-facing catalog of the current relay API.

For workflow guidance, see [hub-relay-api-manual.md](./hub-relay-api-manual.md).

## Base Path

```text
/api/v1
```

## Common Headers

### Local Application API

```text
X-Relay-Key: <client-api-key>
X-Relay-Protocol-Version: 1.0
Content-Type: application/json
```

### Hub-to-Hub API

At minimum:

```text
X-Relay-Protocol-Version: 1.0
Content-Type: application/json
```

Additional headers depend on auth mode:

- `X-Relay-Hub-Key`
- `X-Relay-Timestamp`
- `X-Relay-Signature`
- forwarded certificate fingerprint header, depending on deployment

## Public Endpoints

### `GET /api/status`

Purpose:

- return lightweight hub heartbeat/status data for cross-project polling

Auth:

- none

Response keys:

- `status`
- `hub_id`
- `timestamp`
- `version`
- `health`
- `queue`
- `inbox`

### `GET /api/health`

Purpose:

- return a minimal load-balancer or uptime probe response

Auth:

- none

Response keys:

- `status`

### `GET /api/v1/diagnostics`

Purpose:

- return health, queue, delivery, and inbox summaries

Auth:

- none

Response:

- diagnostics payload from `RelayDiagnosticsService`

### `GET /api/v1/compatibility`

Purpose:

- return protocol and auth compatibility metadata

Auth:

- none

Response keys:

- `version`
- `health`
- `supported_auth_modes`
- `supported_protocol_versions`
- `api_endpoints`

## Browser Shell And Session API

These endpoints support the authenticated Relay operator shell at `/relay`.

### `GET /api/bootstrap`

Purpose:

- return the canonical browser bootstrap payload for the current page

Auth:

- none

Query parameters:

- `page`

Response keys:

- `app.name`
- `app.page`
- `auth.authenticated`
- `auth.account`
- `security.csrfToken`
- `security.sessionLifetimeMinutes`
- `security.keepaliveThresholdSeconds`
- `settings.bootstrapUrl`
- `settings.csrfTokenUrl`
- `settings.sessionPingUrl`

### `GET /api/csrf-token`

Purpose:

- regenerate and return the current browser CSRF token

Auth:

- none

Response keys:

- `csrfToken`

### `POST /api/login`

Purpose:

- create a relay operator browser session

Auth:

- none

Request fields:

- `email`
- `password`

Success:

- `200 OK`

Response keys:

- `status`
- `data.account`
- `data.csrf_token`

Validation/auth failure:

- `422 Unprocessable Entity`

### `GET /api/user`

Purpose:

- return current browser session/account state

Auth:

- none

Response keys:

- `status`
- `data.authenticated`
- `data.account`
- `data.csrf_token`

### `POST /api/user`

Purpose:

- update the currently authenticated relay operator profile

Auth:

- session-authenticated relay operator

Request fields:

- `name`
- `email`

### `POST /api/user/password`

Purpose:

- update the currently authenticated relay operator password

Auth:

- session-authenticated relay operator

Request fields:

- `current_password`
- `password`
- `password_confirmation`

### `POST /api/logout`

Purpose:

- invalidate the current relay operator browser session

Auth:

- session-authenticated relay operator

Response keys:

- `status`
- `data.csrf_token`

### `GET /api/session/ping`

Purpose:

- keep an authenticated browser session alive when the user is active and the session is near expiry

Auth:

- session-authenticated relay operator

Response keys:

- `status`
- `data.csrf_token`
- `data.touched_at`
- `data.session_lifetime_minutes`

## Local Application API

### `POST /api/v1/messages`

Purpose:

- submit a new outbound relay message
- create an outbound message owned by the authenticated local client

Required JSON fields:

- `source_system`
- `target_systems`
- `message_type`
- `payload`

Optional JSON fields:

- `payload_format` with allowed values `json`, `file`, `image`, `binary`
- `payload_version`
- `reference_type`
- `reference_id`
- `tags`
- `priority` with allowed values `low`, `normal`, `high`, `urgent`
- `correlation_id`
- `attachments_count`
- `occurred_at`

Success:

- `201 Created`

Response keys:

- `success`
- `relay_id`
- `message_id`
- `status`
- `deliveries_count`
- `deliveries[]`

### `GET /api/v1/messages`

Purpose:

- list locally submitted messages
- return only messages owned by the authenticated local client

Query parameters:

- `source_system`
- `message_type`
- `priority`
- `limit`

Success:

- `200 OK`

Response keys:

- `data`
- `pagination.total`
- `pagination.per_page`
- `pagination.current_page`
- `pagination.last_page`

### `GET /api/v1/messages/{message}`

Purpose:

- show a specific local message with deliveries and attachments

Notes:

- only outbound messages owned by the authenticated local client are visible

Success:

- `200 OK`

Response keys:

- `message`
- `deliveries`
- `attachments`

### `GET /api/v1/inbox`

Purpose:

- list messages received from remote hubs

Notes:

- inbox reads are scoped to inbound messages whose `target_systems[]` include the authenticated local client's `system_code`
- handlers are also constrained to that same client-owned target-system audience

Query parameters:

- `message_type`
- `source_system`
- `source_hub_id`
- `status`
- `limit`

Success:

- `200 OK`

Response keys:

- `data`
- `pagination.*`

### `GET /api/v1/inbox/{message}`

Purpose:

- show one received inbound message

Notes:

- only messages with a receipt are valid inbox records
- the message must include the authenticated local client's `system_code` in `target_systems[]`

Success:

- `200 OK`

Response keys:

- `message`
- `receipt`
- `attachments`

### `GET /api/v1/deliveries`

Purpose:

- list outbound delivery records
- return only delivery records for messages owned by the authenticated local client

Query parameters:

- `status`
- `target_hq_hub_id`
- `limit`

Success:

- `200 OK`

Response keys:

- `data`
- `pagination.*`

### `GET /api/v1/deliveries/{delivery}`

Purpose:

- show one delivery record and its parent message summary

Notes:

- only deliveries for messages owned by the authenticated local client are visible

Success:

- `200 OK`

Response keys:

- `delivery`
- `message`

### `POST /api/v1/deliveries/{delivery}/retry`

Purpose:

- reset a failed or dead delivery and requeue processing

Rules:

- only `failed` or `dead` deliveries can be retried
- delivery must belong to a message owned by the authenticated local client

Success:

- `200 OK`

Response keys:

- `success`
- `delivery`

Failure:

- `422` with `Can only retry failed or dead deliveries`

### `POST /api/v1/deliveries/{delivery}/cancel`

Purpose:

- cancel a queued or failed delivery by marking it dead

Rules:

- delivered messages cannot be cancelled
- delivery must belong to a message owned by the authenticated local client

Success:

- `200 OK`

Response keys:

- `success`
- `delivery`

### `POST /api/v1/messages/{message}/attachments/init`

Purpose:

- initialize a local attachment upload session

Required JSON fields:

- `attachment_type` with allowed values `file`, `image`, `binary`
- `attachment_name`
- `mime_type`
- `size_bytes`

Optional JSON fields:

- `checksum`
- `chunk_size_bytes`
- `target_hub_id`

Success:

- `201 Created`

Response:

- upload session and attachment initialization payload from `RelayUploadService`

### `POST /api/v1/uploads/{session}/chunk`

Purpose:

- append one chunk to a local upload session

Required JSON/query fields:

- `chunk_index`
- `total_chunks`

Optional JSON/query fields:

- `chunk_checksum`

Request body:

- raw bytes of the chunk

Success:

- `200 OK`

Response:

- upload progress payload from `RelayUploadService`

### `POST /api/v1/uploads/{session}/complete`

Purpose:

- complete a local upload session

Required JSON fields:

- `total_chunks`

Optional JSON fields:

- `final_checksum`

Success:

- `200 OK`

Response:

- upload completion payload from `RelayUploadService`

### `GET /api/v1/uploads/{session}`

Purpose:

- get status for a local upload session

Success:

- `200 OK`

Response keys:

- `success`
- upload status payload from `RelayUploadService`

### `POST /api/v1/attachments/{attachment}/complete`

Purpose:

- finalize an attachment after its upload session completes

Required JSON fields:

- `total_chunks`

Optional JSON fields:

- `final_checksum`

Success:

- `200 OK`

Response:

- attachment completion payload from `RelayUploadService`

### `GET /api/v1/handlers`

Purpose:

- list local webhook handlers owned by the authenticated client

Success:

- `200 OK`

Response keys:

- `data`

### `POST /api/v1/handlers`

Purpose:

- register a local webhook handler

Required JSON fields:

- `name`
- `endpoint_url`

Optional JSON fields:

- `message_type_pattern`
- `source_system`
- `source_hub_id`
- `auth_token`
- `is_active`

Defaults:

- `message_type_pattern` defaults to `*`
- `is_active` defaults to `true`

Success:

- `201 Created`

Response keys:

- `success`
- `handler`

### `PATCH /api/v1/handlers/{handler}`

Purpose:

- update a local webhook handler owned by the authenticated client

Allowed JSON fields:

- `name`
- `endpoint_url`
- `message_type_pattern`
- `source_system`
- `source_hub_id`
- `auth_token`
- `is_active`

Success:

- `200 OK`

Response keys:

- `success`
- `handler`

### `DELETE /api/v1/handlers/{handler}`

Purpose:

- deactivate a local webhook handler owned by the authenticated client

Success:

- `200 OK`

Response keys:

- `success`
- `handler`

Notes:

- the current implementation performs a soft deactivation by setting `is_active = false`

### `GET /api/v1/handler-dispatches`

Purpose:

- list tracked local webhook dispatch attempts for the authenticated client

Query parameters:

- `status`
- `handler_id`
- `limit`

Success:

- `200 OK`

Response keys:

- `data`
- `pagination.*`

### `GET /api/v1/handler-dispatches/{dispatch}`

Purpose:

- show one tracked handler dispatch attempt

Success:

- `200 OK`

Response keys:

- `dispatch`

### `POST /api/v1/handler-dispatches/{dispatch}/retry`

Purpose:

- reset and requeue a failed or dead local webhook dispatch

Rules:

- only `failed` or `dead` dispatches can be retried

Success:

- `200 OK`

Response keys:

- `success`
- `dispatch`

Failure:

- `422` with `Can only retry failed or dead handler dispatches`

## Hub-to-Hub API

### `POST /api/v1/receive`

Purpose:

- receive one message from a remote relay

Required JSON fields:

- `relay_id`
- `origin_hq_hub_id`
- `source_hub_id`
- `source_system`
- `target_hq_hub_id`
- `target_systems`
- `message_type`
- `payload`

Optional JSON fields:

- `hop_trace`
- `payload_format`
- `payload_version`
- `content_hash`
- `attachments_count`
- `occurred_at`
- `created_at`
- `correlation_id`
- `tags`
- `priority`

Success:

- `201 Created` on first receive
- `200 OK` on duplicate receive

Receive status values:

- `received` when the local relay accepts the hop envelope and processes local delivery and/or forwarding
- `duplicate` when the relay already recorded the same `relay_id`
- `undeliverable` when the message reaches the local HQ hub, no local `target_systems[]` entry matches a registered local client, and there are no more eligible next hops

Response keys:

- `success`
- `status`
- `relay_id`
- `message`

### `POST /api/v1/receive-batch`

Purpose:

- receive multiple messages in one request

Required JSON fields:

- `messages`
- each message must include `relay_id`, `origin_hq_hub_id`, `source_hub_id`, `source_system`, `target_hq_hub_id`, `target_systems`, `message_type`, `payload`

Success:

- `201 Created`

Response keys:

- `success`
- `results`
- `received_count`

### `POST /api/v1/upload/init`

Purpose:

- initialize an inbound hub-to-hub upload session

Required JSON fields:

- `relay_id`
- `source_hub_id`
- `attachment_type` with allowed values `file`, `image`, `binary`
- `attachment_name`
- `mime_type`
- `size_bytes`

Optional JSON fields:

- `target_hub_id`
- `checksum`
- `chunk_size_bytes`

Success:

- `201 Created`

Response:

- upload session initialization payload from `RelayUploadService`

### `POST /api/v1/upload/chunk`

Purpose:

- append a chunk to an inbound hub-to-hub upload session

Required fields:

- `upload_session_id`
- `chunk_index`
- `total_chunks`

Optional fields:

- `chunk_checksum`

Request body:

- raw bytes of the chunk

Success:

- `200 OK`

### `POST /api/v1/upload/complete`

Purpose:

- finalize an inbound hub-to-hub upload session

Required fields:

- `upload_session_id`
- `total_chunks`

Optional fields:

- `final_checksum`

Success:

- `200 OK`

### `GET /api/v1/upload/{session}/status`

Purpose:

- return inbound hub-to-hub upload status

Success:

- `200 OK`

## Compatibility Notes

Current implemented protocol baseline:

- `1.0`

Current supported compatibility discovery endpoint:

- `GET /api/v1/compatibility`

Current machine-readable schema status:

- OpenAPI JSON exists at `public/relay-ui/openapi.json`
- Swagger UI is available at `/relay/api/docs`

Current pagination behavior:

- message, delivery, inbox, and handler-dispatch list endpoints currently return paginated responses
- the admin UI hardening pass may shift front-end consumption patterns later, but these API responses are the current contract
