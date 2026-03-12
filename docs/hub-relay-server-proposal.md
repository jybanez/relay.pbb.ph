# Hub Relay Server Proposal

## Purpose

Define `Hub Relay` as a local shared service that runs in each deployment location and exposes APIs for local applications and upstream hub-to-hub communication.

This proposal reframes `Hub Relay` from an embedded code module into a location-level service.

That means:

- applications in the same location send payloads to the local relay service
- the local relay service handles queueing, retries, routing, attachments, and upstream delivery
- the receiving hub’s local relay service accepts the payload and exposes it to local applications

## Recommendation

`Hub Relay` should be built primarily as a `server/service`, not as code that every application must embed.

Recommended architecture:

- one local `Hub Relay Server` per deployment location
- stable APIs for local applications
- stable APIs for upstream hub-to-hub delivery
- optional SDKs only as convenience clients, not as the primary integration model

## Why This Is Better

Compared with distributing relay code into every application, a relay server gives you:

- one relay implementation per location
- one upgrade point per location
- easier integration for multiple local systems
- easier integration for partner software
- technology-stack neutrality
- clearer operational ownership

This is especially useful if a location may run:

- core PBB systems
- partner systems
- future third-party or role-specific systems

All of them can piggy-back on the same relay service without carrying their own copy of relay logic.

## Core Idea

Each location runs a `Hub Relay Server`.

Local systems do not talk directly to upstream hubs. Instead:

1. a local application sends a payload to the local relay server
2. the relay server validates and queues the payload
3. the relay server routes it to one or more upstream hubs
4. the remote hub’s relay server receives and stores it
5. local applications at the receiving hub consume it through the receiving relay server

This makes relay an infrastructure service rather than an application library.

## Deployment Model

Per location:

- one `Hub Relay Server`
- many local applications may use it

Examples:

- barangay location:
  - barangay operations app
  - local reporting app
  - partner NGO/foundation app
- city location:
  - city operations app
  - SITREP review app
  - analytics app

All of those local apps may submit or receive data through the same relay service.

## Shared Across All Hubs

Every `Hub Relay Server` should implement the same:

- message envelope contract
- attachment transfer contract
- delivery queue model
- retry policy model
- acknowledgment model
- monitoring and diagnostics model
- authentication model
- protocol versioning rules

This keeps hub-to-hub behavior consistent across the network.

## What Varies By Deployment

What should remain deployment-specific:

- which local applications are installed
- which payload types are produced or consumed
- how payloads are processed after receipt
- how received information is presented in UI
- what permissions and operator workflows exist locally

So the relay server is shared infrastructure, while business behavior remains application-specific.

## Relay Layer vs Application Layer

### 1. Relay Server Layer

Owned by the relay service:

- local app API
- outbound queue
- delivery attempts
- upstream routing
- file and image transfer
- receipts
- idempotency
- diagnostics and monitoring

### 2. Application Layer

Owned by local software using the relay:

- payload creation
- business validation before submit
- post-receipt workflows
- role-specific UI
- local dashboards and actions

## Local Integration Model

The local relay service should expose APIs that local systems can use.

### Example local use cases

- a barangay SITREP app submits a SITREP to the local relay
- a local document system submits a file package
- a partner system submits a foundation-specific report
- a local monitoring app queries relay delivery status

This removes the requirement for every local system to embed the relay core.

## Upstream Hub-to-Hub Model

Hub-to-hub traffic is relay-server to relay-server.

That means:

- sender app -> local relay server
- local relay server -> upstream relay server
- upstream relay server -> local receiver apps

This is the core network transport path.

## Suggested Naming

Primary name:

- `Hub Relay Server`

Suggested technical names:

- service: `hub-relay-server`
- local API namespace: `/api/hub-relay`
- upstream receive API namespace: `/api/hub-relay`

Optional convenience clients:

- `Hub Relay PHP SDK`
- `Hub Relay JS Client`

## Recommended API Surfaces

There should be two logical API surfaces, even if they share the same service.

### 1. Local Application API

Used by applications in the same location.

Recommended responsibilities:

- submit payloads for relay
- attach files/images
- query outbox/inbox/delivery state
- query upload progress
- retry/cancel when allowed

Suggested endpoints:

- `POST /api/hub-relay/messages`
- `POST /api/hub-relay/messages/{message}/attachments/init`
- `POST /api/hub-relay/uploads/{session}/chunk`
- `POST /api/hub-relay/uploads/{session}/complete`
- `GET /api/hub-relay/messages`
- `GET /api/hub-relay/messages/{message}`
- `GET /api/hub-relay/deliveries`
- `GET /api/hub-relay/uploads/{session}`
- `POST /api/hub-relay/deliveries/{delivery}/retry`
- `POST /api/hub-relay/deliveries/{delivery}/cancel`

### 2. Hub-to-Hub Relay API

Used only between relay servers.

Recommended responsibilities:

- receive relayed messages from downstream hubs
- initialize/receive chunked attachments
- acknowledge receipt
- expose compatibility and diagnostics

Suggested endpoints:

- `POST /api/hub-relay/receive`
- `POST /api/hub-relay/receive-batch`
- `POST /api/hub-relay/upload/init`
- `POST /api/hub-relay/upload/chunk`
- `POST /api/hub-relay/upload/complete`
- `GET /api/hub-relay/upload/{session}/status`
- `GET /api/hub-relay/diagnostics`
- `GET /api/hub-relay/compatibility`

## Recommended Envelope Contract

The relay server should continue using a common envelope regardless of payload type.

Suggested fields:

- `relay_id`
- `source_hub_id`
- `source_system`
- `target_hub_ids`
- `message_type`
- `payload_format`
- `payload_version`
- `created_at`
- `occurred_at`
- `priority`
- `content_hash`
- `attachments_count`
- `correlation_id`

The `source_system` field is especially useful in the server model because multiple local applications may use the same relay.

Examples:

- `sitrep.app`
- `incident.app`
- `foundation.reporting`

## Receiving Model For Local Applications

There are two reasonable receiving models:

### Option A. Pull Model

Local apps query the local relay server for received messages.

Examples:

- `GET /api/hub-relay/inbox`
- `GET /api/hub-relay/messages/{message}`

Pros:

- simple
- easy for many kinds of apps
- decoupled

### Option B. Registered Handler / Webhook Model

Local apps register a handler or local callback endpoint that the relay server invokes after receipt.

Pros:

- more automatic
- faster local processing

Recommendation for phase 1:

- support pull model first
- add local handler/webhook registration later if needed

## Monitoring Model

Relay monitoring should also be common across all deployments.

The relay server should own:

- queue depth
- delivery states
- retry states
- upload progress
- dead-letter states
- link health
- package version
- protocol version

Different deployments may present that monitoring differently, but the underlying monitoring data should come from the relay server itself.

## Recommended Views

Because the relay server is shared infrastructure, the same monitoring concepts can back many UIs.

Recommended relay-server monitoring views:

- Relay Dashboard
- Outbox
- Inbox
- Deliveries
- Uploads
- Message Details
- Delivery Details
- Hub Link Status
- Relay Configuration
- Dead Letters / Failed Items

These may be:

- hosted directly by the relay server
- or consumed by another admin UI through relay APIs

## Authentication Model

There are now two trust boundaries.

### 1. Local App -> Local Relay Server

Recommended options:

- API key per local system
- client credentials
- signed service account token

### 2. Relay Server -> Upstream Relay Server

Recommended options:

- per-hub credentials
- mutual TLS later if needed
- HMAC request signing or signed bearer tokens

These two boundaries should be managed separately.

## Large File Handling

Large files and images should use chunked upload with byte-based progress tracking.

That should remain owned by the relay server, not by individual apps.

This gives:

- resumable transfer
- visible progress percentage
- retry at chunk level
- one common implementation across systems

## Data Model Recommendation

The server model still fits the earlier relay tables:

- `hub_relay_messages`
- `hub_relay_deliveries`
- `hub_relay_attachments`
- `hub_relay_receipts`
- `hub_relay_attachment_transfers`

Additional useful tables in the server model:

- `hub_relay_clients`
  - local systems allowed to use the relay
- `hub_relay_subscriptions`
  - optional future mapping of local apps to message types
- `hub_relay_local_receivers`
  - optional future webhook/handler registrations

## Role of SDKs

SDKs should still exist, but only as convenience clients.

Recommended examples:

- PHP SDK
- JS client
- CLI client

These should:

- wrap the relay server API
- simplify authentication and request formatting
- not replace the relay server architecture

In other words:

- `API first`
- `SDK second`

## Step-by-Step Implementation Plan

### Phase 1. Define The Service Contract

1. define the relay server responsibilities clearly
2. define local application API
3. define hub-to-hub API
4. define common envelope contract
5. define monitoring and diagnostics contract

### Phase 2. Build The Relay Server

6. create dedicated relay server repository, for example:
   - `pbb-hub-relay-server`
7. implement:
   - message storage
   - delivery queue
   - upstream sender
   - inbound receiver
   - attachment transfer
   - diagnostics and monitoring APIs
8. implement authentication for:
   - local clients
   - remote hubs

### Phase 3. Integrate One Local App

9. pick one PBB local application, such as a SITREP app
10. remove embedded relay logic from that app
11. submit payloads through local relay API instead
12. validate receipt and delivery flow end-to-end

### Phase 4. Add Remote Receive Consumption

13. choose one receiving application at the upstream hub
14. let it consume received messages via relay inbox APIs
15. validate that local business presentation can differ while transport stays common

### Phase 5. Add Monitoring and Admin Views

16. expose shared relay monitoring endpoints
17. add common relay monitoring UI or shared admin screens
18. verify that different hub deployments can still present monitoring differently

### Phase 6. Add SDKs

19. add PHP SDK as a convenience wrapper
20. optionally add JS client for local web apps
21. document that SDKs are optional and API remains canonical

## Recommended Migration From Earlier Package-Only Direction

If you previously considered `Hub Relay` as only a shared package, the better reframing is:

- keep shared code for the relay server implementation itself
- but integrate local applications through API, not by copying relay behavior into every app

So the package idea does not disappear. It shifts role:

- package = implementation unit of the relay server
- API = integration contract for other applications

## Recommendation

Make `Hub Relay` a local shared service per hub location.

Use:

- API-first integration for local applications
- relay-server to relay-server APIs for upstream delivery
- shared monitoring and diagnostics at the relay layer
- optional SDKs only as convenience clients

This is the better architecture if the PBB ecosystem will include multiple systems, partner software, and different deployment roles sharing one common relay capability.
