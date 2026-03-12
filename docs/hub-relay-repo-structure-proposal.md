# Hub Relay Repository Structure Proposal

## Purpose

Provide a folder-ready starting structure for the future `Hub Relay Server` repository so the team can begin implementation with a clear layout.

This proposal assumes:

- `Hub Relay` will be built as a server/service
- it will expose APIs for local applications and hub-to-hub delivery
- it will likely use Laravel/PHP for the first implementation
- shared monitoring is part of the relay core

## Recommended Repository Name

- `pbb-hub-relay-server`

## Recommended Top-Level Structure

```text
pbb-hub-relay-server/
├─ app/
├─ bootstrap/
├─ config/
├─ database/
├─ docs/
├─ public/
├─ resources/
├─ routes/
├─ storage/
├─ tests/
├─ composer.json
├─ README.md
└─ artisan
```

This assumes a Laravel-based service repository.

## Recommended Internal Structure

### `app/`

Suggested internal layout:

```text
app/
├─ Console/
│  └─ Commands/
├─ Contracts/
├─ DTO/
├─ Enums/
├─ Events/
├─ Exceptions/
├─ Http/
│  ├─ Controllers/
│  │  └─ Api/
│  ├─ Middleware/
│  ├─ Requests/
│  └─ Resources/
├─ Jobs/
├─ Models/
├─ Policies/
├─ Providers/
├─ Relay/
│  ├─ Auth/
│  ├─ Delivery/
│  ├─ Diagnostics/
│  ├─ Envelope/
│  ├─ Handlers/
│  ├─ Inbound/
│  ├─ Monitoring/
│  ├─ Outbound/
│  ├─ Storage/
│  ├─ Transport/
│  ├─ Uploads/
│  └─ Versioning/
└─ Support/
```

## Recommended Responsibilities By Folder

### `app/Relay/Envelope/`

Owns:

- envelope validation
- message metadata normalization
- content hash helpers
- payload-type metadata rules

Suggested classes:

- `RelayEnvelopeFactory`
- `RelayEnvelopeValidator`
- `RelayIdGenerator`
- `ContentHashService`

### `app/Relay/Outbound/`

Owns:

- local app submissions
- outbound queue orchestration
- target expansion
- message creation

Suggested classes:

- `RelaySubmissionService`
- `RelayTargetResolver`
- `RelayMessageBuilder`

### `app/Relay/Delivery/`

Owns:

- delivery records
- retry state
- delivery state transitions
- dead-letter handling

Suggested classes:

- `RelayDeliveryService`
- `RelayRetryPolicy`
- `RelayDeadLetterService`
- `RelayDeliveryStateMachine`

### `app/Relay/Inbound/`

Owns:

- hub-to-hub receive path
- receipt creation
- idempotency checks
- handoff to handlers

Suggested classes:

- `RelayReceiveService`
- `RelayReceiptService`
- `RelayIdempotencyService`
- `RelayInboundProcessor`

### `app/Relay/Transport/`

Owns:

- outbound HTTP clients
- request signing
- transport retries where appropriate
- upstream compatibility checks

Suggested classes:

- `RelayHttpSender`
- `RelayRequestSigner`
- `RelayCompatibilityClient`

### `app/Relay/Uploads/`

Owns:

- upload session lifecycle
- chunk receive/send
- reassembly
- upload progress tracking

Suggested classes:

- `RelayUploadSessionService`
- `RelayChunkWriter`
- `RelayChunkAssembler`
- `RelayUploadProgressService`

### `app/Relay/Monitoring/`

Owns:

- queue summaries
- delivery summaries
- upload summaries
- link health summaries

Suggested classes:

- `RelayDashboardService`
- `RelayDeliveryMonitor`
- `RelayUploadMonitor`
- `RelayLinkHealthService`

### `app/Relay/Diagnostics/`

Owns:

- health checks
- package version reporting
- protocol compatibility reporting
- service diagnostics endpoints

Suggested classes:

- `RelayDiagnosticsService`
- `RelayVersionService`
- `RelayProtocolCompatibilityService`

### `app/Relay/Handlers/`

Owns:

- payload handler registry
- role-aware or deployment-aware handler dispatch

Suggested classes:

- `RelayHandlerRegistry`
- `SitrepRelayHandler`
- `IncidentRelayHandler`

## Recommended API Route Structure

### `routes/api.php`

Suggested grouping:

```php
Route::prefix('api/hub-relay')->group(function () {
    // Local application API
    Route::post('/messages', ...);
    Route::get('/messages', ...);
    Route::get('/messages/{message}', ...);

    Route::get('/deliveries', ...);
    Route::get('/deliveries/{delivery}', ...);
    Route::post('/deliveries/{delivery}/retry', ...);
    Route::post('/deliveries/{delivery}/cancel', ...);

    Route::post('/messages/{message}/attachments/init', ...);
    Route::post('/uploads/{session}/chunk', ...);
    Route::post('/uploads/{session}/complete', ...);
    Route::get('/uploads/{session}', ...);

    // Hub-to-hub API
    Route::post('/receive', ...);
    Route::post('/receive-batch', ...);
    Route::post('/upload/init', ...);
    Route::post('/upload/chunk', ...);
    Route::post('/upload/complete', ...);
    Route::get('/upload/{session}/status', ...);

    // Monitoring and diagnostics
    Route::get('/dashboard', ...);
    Route::get('/outbox', ...);
    Route::get('/inbox', ...);
    Route::get('/diagnostics', ...);
    Route::get('/compatibility', ...);
});
```

## Recommended Models

### Core Relay Models

- `HubRelayMessage`
- `HubRelayDelivery`
- `HubRelayAttachment`
- `HubRelayReceipt`
- `HubRelayAttachmentTransfer`

### Service/Integration Models

- `HubRelayClient`
- `HubRelaySubscription`
- `HubRelayLocalReceiver`

## Recommended Database Structure

```text
database/
├─ factories/
├─ migrations/
├─ seeders/
└─ schema/
```

Suggested migration order:

1. relay messages
2. relay deliveries
3. relay attachments
4. relay receipts
5. attachment transfers
6. relay clients
7. relay subscriptions
8. relay local receivers

## Recommended Config Files

```text
config/
├─ app.php
├─ auth.php
├─ queue.php
└─ hub-relay.php
```

### `config/hub-relay.php`

Suggested sections:

- local relay identity
- protocol version
- retry policy
- upload/chunk settings
- local client auth settings
- remote hub auth settings
- diagnostics settings
- monitoring retention

## Recommended Console / Worker Structure

### `app/Console/Commands/`

Suggested commands:

- `hub-relay:work`
- `hub-relay:retry`
- `hub-relay:resume-uploads`
- `hub-relay:cleanup`
- `hub-relay:diagnostics`

These may later move to queue workers/supervisor-managed processes.

## Recommended Tests Structure

```text
tests/
├─ Feature/
│  ├─ Api/
│  ├─ Diagnostics/
│  ├─ Monitoring/
│  └─ Uploads/
├─ Integration/
│  ├─ Delivery/
│  ├─ Inbound/
│  └─ Retry/
└─ Unit/
   ├─ Envelope/
   ├─ Monitoring/
   ├─ Retry/
   └─ Uploads/
```

Minimum early test targets:

- envelope validation
- idempotent receive
- retry transitions
- chunk upload assembly
- monitoring summary generation
- diagnostics and compatibility endpoints

## Recommended Docs Structure

```text
docs/
├─ architecture/
├─ api/
├─ operations/
├─ protocol/
└─ rollout/
```

Suggested initial docs:

- architecture overview
- local application API
- hub-to-hub API
- relay envelope spec
- outage/reconnect behavior
- monitoring and diagnostics
- upgrade and rollout guide

## Recommended README Structure

Top-level `README.md` should include:

1. what the relay server is
2. local app integration model
3. hub-to-hub delivery model
4. how to run locally
5. how to run workers
6. environment variables
7. links to detailed docs

## Suggested First Implementation Slice

If the team wants a narrow phase 1, start with:

- JSON-only message receive/send
- one-to-many deliveries
- queue and retry state
- receipts and idempotency
- diagnostics endpoint
- dashboard summary endpoint
- no file uploads yet

That gives a small but valid relay service base.

## Recommendation

Use this repository structure as the initial scaffold for `pbb-hub-relay-server`.

It keeps:

- relay core behavior
- monitoring
- diagnostics
- transport
- uploads

clearly separated, while still fitting a practical Laravel service implementation.
