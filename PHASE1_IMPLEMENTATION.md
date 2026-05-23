# Phase 1 Implementation Summary

## Overview

This is the initial implementation of the PBB - Hub Relay Server according to the server-first architecture proposal. Phase 1 focuses on:

- Local application API for message submission
- Hub-to-hub inbound message receipt
- Outbound message queuing
- Receipt and idempotency tracking
- Diagnostics and health monitoring
- JSON message transport plus phase-2 attachment, inbox, and local handler support

## Architecture

### Two API Surfaces

#### 1. Local Application API
Routes: `/api/hub-relay/*`
Authentication: API key per registered local client
Purpose: Local applications submit messages and query delivery status

#### 2. Hub-to-Hub Relay API
Routes: `/api/hub-relay/receive`, `/api/hub-relay/receive-batch`
Authentication: Shared-key hub credentials via `X-Relay-Hub-Key`, with optional HMAC signing
Purpose: Remote hubs deliver messages to this relay service

## Database Schema

### hub_relay_messages
- Stores logical message envelopes
- Indexed by source_system, message_type, priority
- Contains complete payload as JSON

### hub_relay_deliveries
- One record per target hub per message
- Status tracking: queued → sending → delivered/failed/dead
- Retry tracking and timestamps

### hub_relay_receipts
- One record per unique relay_id received
- Enables idempotency (prevents duplicate processing)
- Status: received → processed/duplicate/rejected

### hub_relay_attachments
- Metadata for attached files
- Used by the chunked upload flow

### hub_relay_upload_sessions
- Tracks local and hub-to-hub upload session state
- Stores chunk progress, transfer status, and assembled file location

### hub_relay_clients
- Local application registration
- API key management
- Activity tracking

### hub_relay_handlers
- Local webhook registration per authenticated client
- Message-type and source filters for inbound handoff
- Last dispatch success/failure timestamps for operator visibility

### hub_relay_handler_dispatches
- Persistent record of each local webhook dispatch attempt
- Retryable state for failed and dead local handoff work
- Operator-facing visibility into attempts, errors, and next retry timing

## Envelope Contract

All messages use a common envelope:

```json
{
  "relay_id": "ulid-format-globally-unique",
  "source_hub_id": "sending-hub-id",
  "source_system": "sitrep.app",
  "target_hub_ids": ["hub-id-1", "hub-id-2"],
  "message_type": "sitrep.record",
  "payload_format": "json",
  "payload_version": "1.0",
  "created_at": "2026-03-13T12:00:00Z",
  "occurred_at": "2026-03-13T11:50:00Z",
  "priority": "normal",
  "content_hash": "sha256-hash",
  "attachments_count": 0,
  "correlation_id": "optional-correlation-id",
  "tags": ["tag1", "tag2"],
  "payload": { ... }
}
```

## API Endpoints

### Local Application API (POST /api/hub-relay/messages)

**Request:**
```json
{
  "source_hub_id": "barangay-hub",
  "source_system": "sitrep.app",
  "target_hub_ids": ["city-hub", "foundation-hub"],
  "message_type": "sitrep.record",
  "payload": { "content": "..." },
  "priority": "normal"
}
```

**Response (201):**
```json
{
  "success": true,
  "relay_id": "...",
  "message_id": "...",
  "status": "queued",
  "deliveries_count": 2,
  "deliveries": [
    {"id": "...", "target_hub_id": "city-hub", "status": "queued"},
    {"id": "...", "target_hub_id": "foundation-hub", "status": "queued"}
  ]
}
```

### Hub-to-Hub Receive (POST /api/hub-relay/receive)

**Request:**
```json
{
  "relay_id": "...",
  "source_hub_id": "barangay-hub",
  "source_system": "sitrep.app",
  "target_hub_ids": ["this-hub"],
  "message_type": "sitrep.record",
  "payload": { ... }
}
```

**Response (201 or 200 for duplicate):**
```json
{
  "success": true,
  "status": "received",
  "relay_id": "...",
  "message": "Message received and acknowledged"
}
```

### Diagnostics (GET /api/hub-relay/diagnostics)

**Response:**
```json
{
  "version": {
    "relay_package_version": "1.1.0",
    "relay_protocol_version": "1.1",
    "minimum_supported_protocol_version": "1.0"
  },
  "queue_status": {
    "total_queued": 42,
    "total_messages": 1234,
    "total_deliveries": 3456,
    "failed_deliveries": 5,
    "dead_letter_deliveries": 2
  },
  "delivery_summary": { ... },
  "inbox_summary": { ... },
  "timestamp": "2026-03-13T12:00:00Z"
}
```

## Local Client Authentication

**Phase 1 Model:**
- API keys stored in `hub_relay_clients`
- Middleware validates `X-Relay-Key` header
- Status tracking of last usage

**Future (remaining Phase 2 work):**
- Richer operator controls around stronger transport auth rollout
- TLS mutual authentication deployment hardening at the web-server layer
- Deeper detail-screen workflows and packaged SDK distribution
- API token management UI/workflows for local clients
- Integration manual and API reference documentation
- User management and admin authorization model
- UI hardening around wide-screen layouts, container scrolling, and virtualized lists

## Idempotency

- Enforced by unique `relay_id`
- Duplicate receives return 200 OK without reprocessing
- Tests confirm duplicate handling

## Deployment Notes

### Prerequisites
- PHP 8.2+
- Laravel 12
- Database (configured in .env)

### Setup Steps

```bash
# 1. Install dependencies
composer install

# 2. Copy environment file
cp .env.example .env

# 3. Generate app key
C:/wamp64/bin/php/php8.2.29/php.exe artisan key:generate

# 4. Run migrations
C:/wamp64/bin/php/php8.2.29/php.exe artisan migrate

# 5. Register a test local client
C:/wamp64/bin/php/php8.2.29/php.exe artisan tinker
# In tinker:
# App\Models\HubRelayClient::create(['name' => 'Test App', 'system_code' => 'test.app', 'api_key' => 'test-key-123'])
```

### Testing the API

```bash
# Submit a message
curl -X POST http://localhost:8000/api/hub-relay/messages \
  -H "Content-Type: application/json" \
  -H "X-Relay-Key: test-key-123" \
  -d '{
    "source_hub_id": "test-hub",
    "source_system": "test.app",
    "target_hub_ids": ["remote-hub"],
    "message_type": "test.message",
    "payload": {"test": "data"}
  }'

# Get diagnostics
curl http://localhost:8000/api/hub-relay/diagnostics

# Receive a message
curl -X POST http://localhost:8000/api/hub-relay/receive \
  -H "Content-Type: application/json" \
  -d '{
    "relay_id": "...",
    "source_hub_id": "remote-hub",
    "source_system": "test.app",
    "target_hub_ids": ["this-hub"],
    "message_type": "test.message",
    "payload": {}'
  }'
```

## Phase 1 Deliverables

✅ Runnable Laravel service baseline
✅ Initial migrations (messages, deliveries, receipts, clients, attachments)
✅ Initial API route definitions
✅ Envelope contract definition
✅ Local client authentication model
✅ Phase-1 narrow slice implementation:
  - ✅ JSON-only messages
  - ✅ Outbound queue
  - ✅ Inbound receive
  - ✅ Receipts and idempotency
  - ✅ Diagnostics endpoint
  - ✅ Basic monitoring summary

## Next Steps (Phase 2)

- [x] Basic hub-to-hub authentication (shared-key credentials)
- [x] Optional HMAC request signing
- [x] Attachment/file transfer with chunking
- [x] Delivery worker (background jobs)
- [x] Retry logic and backoff policy
- [x] Upload session management
- [x] Stronger transport auth (`mtls` / `mtls_hmac`)
- [x] Local app webhook/handler registration
- [x] Inbox pull API for received messages
- [x] Initial monitoring dashboard
- [x] Richer handler dispatch visibility and admin actions
- [x] API token management section and lifecycle controls
- [x] User management and relay operator login baseline
- [x] API user manual and reference documentation baseline
- [x] API versioning and compatibility baseline
- [x] SDK implementation (PHP, JS)
- [x] Detail-screen and workflow expansion baseline
- [x] UI hardening for max-screen layouts, container scrolling, and virtualized infinite lists
- [x] Protocol/version evolution beyond `1.0`
- [x] SDK packaging/release hardening baseline

## Service Architecture

```
Local Application → LocalApplicationAPI → RelaySubmissionService
                                           └─→ RelayEnvelopeValidator
                                           └─→ Create Message+Deliveries

Remote Hub → HubToHubAPI → RelayReceiveService → RelayEnvelopeValidator
                                                 └─→ RelayIdempotencyService
                                                 └─→ Store Receipt
                                                 └─→ LocalHandlerDispatchService

Monitoring → DiagnosticsAPI → RelayDiagnosticsService
                               └─→ Queue Summary
                               └─→ Delivery Summary
                               └─→ Inbox Summary
                               └─→ Health Status

Local App Webhook → Handler API → HubRelayHandler registry
                                  └─→ DispatchRelayToLocalHandler jobs
                                  └─→ HubRelayHandlerDispatch tracking
```

## Testing

```bash
C:/wamp64/bin/php/php8.2.29/php.exe artisan test
```

Includes tests for:
- Envelope validation
- Idempotent receive
- Message submission
- Delivery creation
- Diagnostics data aggregation
