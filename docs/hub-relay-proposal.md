# Hub Relay Proposal

## Purpose

Define a shared `Hub Relay` system for Project Bantay Bayan hubs so that any hub can send data upstream to one or more parent hubs using the same protocol and processing model.

This is intended for cases such as:

- a barangay hub sending `SITREP` data to its city/municipal hub
- the same barangay hub also sending that data to a foundation hub
- a city hub forwarding operational data to a provincial hub
- a provincial hub forwarding to a regional or national hub

All hubs run the same system. The direction of transfer for this proposal is upstream only.

## Core Idea

`Hub Relay` is an outbound-to-upstream delivery system with a matching inbound receiver on every hub.

Each hub can:

- create relay-ready outbound messages
- queue them for one or more upstream targets
- transmit them reliably
- receive inbound relay messages from downstream hubs
- validate, persist, and acknowledge them

Because every hub runs the same system, any hub can act as:

- a sender to its upstream hubs
- a receiver for its downstream hubs

## Scope

The relay must support different payload classes, including:

- structured database records
- files
- images
- future binary or document payloads

Examples:

- `sitrep.record`
- `incident.record`
- `attachment.file`
- `attachment.image`

## Recommended Naming

Primary system name:

- `Hub Relay`

Suggested technical names:

- module: `hub_relay`
- outbound queue table: `hub_relay_outbox`
- inbound receipt table: `hub_relay_inbox`
- attempt log table: `hub_relay_attempts`
- attachment table: `hub_relay_attachments`

## Design Goals

- upstream-only delivery for the initial version
- support one-to-many upstream delivery
- durable queueing and retries
- idempotent receive behavior
- payload-type neutrality
- transport security and sender verification
- identical implementation on all hubs

## Shared Relay Across Role-Specific Deployments

The `Hub Relay` must be treated as a common subsystem inside a larger multi-deployment platform.

That larger platform will be deployed in different locations such as:

- provincial capitol
- city hall
- barangay hall
- foundation site

These deployments will not necessarily have the same business responsibilities, workflows, or UI.

What they share is:

- the same hub-to-hub relay contract
- the same transport and delivery behavior
- the same receipt and retry model
- the same relay monitoring and diagnostics model

What may differ by deployment:

- which modules are enabled
- which payloads are relevant
- how received information is stored
- how received information is presented in the UI
- which operators are allowed to send, receive, approve, or act on relayed data
- how shared relay monitoring is surfaced in the local application UI

This means `Hub Relay` should be designed as:

- a common interoperability layer
- not a single-purpose SITREP screen
- not a single shared UI across all hub deployments

## Shared Relay, Different Application Behavior

The same relayed payload may be processed differently depending on the receiving hub’s role.

Example:

- a barangay hub sends a `SITREP`
- a city hub may show it in an operations queue
- a provincial hub may aggregate it into summary dashboards
- a foundation hub may present it as program monitoring input

So while the relay envelope, delivery tracking, receipts, and transport are shared, the application behavior above the relay may differ by deployment.

Recommended architectural split:

### 1. Relay Layer

Shared across all hub deployments:

- envelope validation
- queueing
- upstream target delivery
- retries
- acknowledgments
- idempotency
- attachment transfer
- delivery monitoring
- relay diagnostics
- operational monitoring data production

### 2. Application Handler Layer

May vary by deployment role:

- payload interpretation
- record creation/update rules
- business workflows after receipt
- UI presentation
- access control

This separation is important because it allows:

- one common network protocol
- one common reliability model
- different local business behavior per hub type

## Shared Monitoring, Different Presentation

Relay monitoring should also be treated as a shared cross-hub capability.

All hubs should expose the same core monitoring concepts:

- queue depth
- delivery states
- retry state
- upload progress
- dead-letter state
- target-hub health
- relay package and protocol version

What may vary by deployment is the presentation of that monitoring:

- HQ may expose a network-wide operational dashboard
- a city hub may expose only local outbox/inbox views
- a foundation hub may embed relay monitoring in a partner-facing operations screen

So the monitoring model should be common, while the surrounding UI and workflow may differ by deployment.

## Role-Aware Processing Recommendation

The receiver should route each payload through:

- common relay validation first
- then a role-aware or deployment-aware application handler

Recommended model:

- relay transport accepts the message
- relay receiver stores the receipt
- handler registry selects the payload handler
- handler behavior may branch based on:
  - deployment type
  - hub role
  - enabled modules
  - payload type

The same principle should apply to monitoring:

- relay core produces common monitoring data
- each hub application decides how to present that data to its operators

This keeps the relay generic while still allowing deployment-specific outcomes.

## Non-Goals

- downstream push from parent to child
- real-time bidirectional streaming
- full distributed database replication
- general remote file browsing

This proposal is for controlled message relay, not full node replication.

## High-Level Model

### 1. Outbound

When a hub needs to send data upstream:

- create a relay envelope
- attach payload metadata
- store the payload or attachment reference
- enqueue one outbound delivery per upstream target

### 2. Transport

A relay worker sends queued messages to each configured upstream hub.

Each attempt should:

- authenticate the sender
- upload metadata and payload
- receive an acknowledgment
- mark the delivery as delivered or retryable

### 3. Inbound

The receiving hub:

- authenticates the sending hub
- validates the envelope
- checks idempotency
- stores the received message
- stores attachments if present
- hands off payload processing to the application layer
- returns an acknowledgment

## Recommended Envelope Structure

Every relayed item should use a common envelope regardless of payload type.

Suggested envelope fields:

- `relay_id`
- `source_hub_id`
- `source_hub_code`
- `target_hub_id`
- `target_hub_code`
- `message_type`
- `payload_format`
- `payload_version`
- `created_at`
- `occurred_at`
- `priority`
- `content_hash`
- `attachments_count`

Optional fields:

- `correlation_id`
- `reference_type`
- `reference_id`
- `tags`

### Example Message Types

- `sitrep.record`
- `sitrep.attachment`
- `incident.record`
- `incident.image`
- `hub.metric`

### Example Payload Formats

- `json`
- `file`
- `image`
- `binary`

## Delivery Model

Because one hub may uplink to multiple upstream hubs, deliveries should be target-specific.

That means:

- one logical relay message may produce multiple outbound deliveries
- delivery state must be tracked per target hub

Example:

- barangay hub creates one `SITREP`
- it is queued for:
  - city hub
  - foundation hub

Those are two separate deliveries sharing one common source payload.

## Recommended Data Model

### `hub_relay_messages`

Stores the logical message envelope.

Suggested columns:

- `id`
- `relay_id`
- `source_hub_id`
- `message_type`
- `payload_format`
- `payload_version`
- `reference_type`
- `reference_id`
- `content_hash`
- `payload_json`
- `created_at`
- `occurred_at`

### `hub_relay_deliveries`

Stores one outbound delivery per upstream target.

Suggested columns:

- `id`
- `hub_relay_message_id`
- `target_hub_id`
- `status`
- `attempt_count`
- `last_attempt_at`
- `delivered_at`
- `last_error`
- `next_retry_at`
- `created_at`
- `updated_at`

Suggested statuses:

- `queued`
- `sending`
- `delivered`
- `failed`
- `dead`

### `hub_relay_attachments`

Stores metadata for files/images attached to a message.

Suggested columns:

- `id`
- `hub_relay_message_id`
- `attachment_type`
- `name`
- `mime_type`
- `size_bytes`
- `storage_disk`
- `storage_path`
- `checksum`
- `created_at`

### `hub_relay_receipts`

Stores inbound receipts for idempotency and auditing.

Suggested columns:

- `id`
- `relay_id`
- `source_hub_id`
- `received_at`
- `processed_at`
- `status`
- `message_type`
- `content_hash`
- `created_at`

Suggested statuses:

- `received`
- `processed`
- `duplicate`
- `rejected`

## Payload Handling Strategy

Use a common envelope, but allow payload handling by type.

Recommended split:

- envelope and transport are generic
- application handlers are type-specific

Examples:

- `sitrep.record`
  - save as application data
- `incident.image`
  - save file, link to source record
- `attachment.file`
  - store in file storage, link in metadata

This avoids hardcoding SITREP-only behavior into the relay transport itself.

## Transport Recommendation

Initial transport recommendation:

- HTTPS API between hubs

Suggested pattern:

- sender posts to `/api/hub-relay/receive`
- receiver returns JSON acknowledgment

Why:

- simple to deploy
- easy to secure
- works for metadata and multipart attachments
- compatible with current web-stack architecture

## Authentication and Trust

Every relay request must verify the sending hub.

Recommended options:

- shared relay token per hub pair
- or per-hub signed API credentials
- or HMAC request signing

Minimum expectations:

- sender identity must be verifiable
- receiver must reject unknown hubs
- transport must use TLS

## Idempotency

This is mandatory.

The receiver must be able to safely accept retries without duplicating records.

Recommended rule:

- `relay_id` must be globally unique per message
- receiver stores each received `relay_id`
- duplicate receive returns success acknowledgment without reprocessing

## Retry Behavior

Outbound delivery must be retryable.

Recommended behavior:

- queue failed deliveries
- exponential backoff
- max attempt threshold
- dead-letter or failed state after repeated failure

Suggested backoff:

- 1 min
- 5 min
- 15 min
- 1 hr
- 6 hr

## Network Outage and Reconnectivity

The relay must assume that some hub-to-hub links will be unstable, intermittent, or fully offline for periods of time.

This is especially important for:

- barangay hubs with weak connectivity
- remote or disaster-affected locations
- locations using unreliable last-mile links

So outage and reconnect handling should be part of the base relay design, not treated as an edge case.

### Recommended Link States

Each target hub link should be trackable in a small operational state model:

- `healthy`
- `degraded`
- `offline`
- `recovering`

Suggested meaning:

- `healthy`
  - recent deliveries are succeeding normally
- `degraded`
  - deliveries still succeed, but latency or failure rate is elevated
- `offline`
  - repeated delivery attempts fail or the target cannot be reached
- `recovering`
  - target has reconnected and queued work is being resumed

### Outage Detection

Recommended triggers for marking a link degraded or offline:

- repeated connection failures
- repeated timeout failures
- repeated TLS or transport failures
- upload sessions that stall beyond threshold
- no successful delivery for a configured duration while queue backlog exists

Suggested operational signals:

- consecutive failure count
- last successful delivery timestamp
- oldest queued delivery age
- stalled upload count

### Behavior During Outage

When a target hub is offline:

- continue accepting local submissions into the relay queue
- do not discard queued deliveries automatically
- continue retry scheduling with backoff
- persist enough state to survive service restarts
- preserve idempotency identifiers for eventual resend

For large-file transfers:

- preserve upload session state
- preserve chunk progress
- allow resume after reconnect where possible

### Reconnect Recovery

When connectivity returns:

- transition the link to `recovering`
- resume queued deliveries
- resume incomplete chunked uploads if resumable
- continue using idempotent receive rules to avoid duplicate processing

Recommended reconnect behavior:

- do not flood the target immediately with unlimited parallel sends
- use controlled recovery concurrency
- prioritize oldest queued items first unless priority rules override

### Queue Policy During Outage

The queue should remain usable during long outages.

Recommended policy:

- accept new messages while storage thresholds allow
- track:
  - queue depth
  - oldest unsent message age
  - per-target backlog
- support optional priority handling for urgent payloads

If the outage is prolonged, operators should be able to see:

- how many items are pending
- how old the oldest pending item is
- whether storage pressure is rising

### Storage and Disk Pressure Contingencies

Long outages may cause local queue growth.

Recommended safeguards:

- configurable storage thresholds
- warnings before disk pressure becomes critical
- optional admission control for lower-priority payloads
- operator visibility into queue growth by payload type and target hub

The relay should not silently fail once disk pressure rises.

### Duplicate Protection After Reconnect

Reconnect often causes replay and retry bursts.

The receiver must continue to rely on idempotency:

- duplicate `relay_id` must not create duplicate records
- resumed uploads must not create duplicate attachments
- already-acknowledged deliveries must be safely ignored or confirmed

### Operator Visibility During Outage

Operators should be able to see:

- target hub offline since when
- queue backlog size
- oldest unsent delivery age
- active stalled uploads
- last successful delivery time
- current retry/backoff state

This should be visible in:

- dashboard summaries
- delivery views
- hub link status views

### Manual Recovery Tools

Recommended operator actions:

- retry now
- pause delivery to target
- resume delivery to target
- cancel failed upload
- requeue dead-letter item if policy allows

These should be constrained by permissions and audit logging.

### Recommended Recovery Principles

- durable queue first
- resume rather than restart when possible
- idempotent receiver always
- bounded retry behavior
- observable link state and backlog at all times

These principles should apply across all hubs consistently.

## Attachment Handling

For files and images:

- store attachment metadata in the envelope
- send attachment content via multipart upload or staged upload
- validate checksum after receipt

Recommended attachment metadata:

- `name`
- `mime_type`
- `size_bytes`
- `checksum`
- `attachment_type`

## Large File Transfer and Progress Tracking

For large files, the relay should support progress-aware transfer.

This is possible when the sender tracks total bytes and uploaded bytes during transfer.

Recommended approach:

- use chunked upload for large files
- track progress by bytes, not only by chunk count
- allow resume/retry at the chunk level

Why chunked upload is preferred:

- supports visible progress percentage
- avoids restarting an entire large upload on failure
- works better across unstable hub-to-hub links
- allows resumable transfer

### Recommended Progress Fields

For delivery tracking:

- `transfer_size_bytes`
- `transferred_bytes`
- `transfer_progress_percent`
- `transfer_status`
- `current_chunk_index`
- `total_chunks`

Suggested transfer statuses:

- `queued`
- `initializing`
- `uploading`
- `assembling`
- `delivered`
- `failed`

### Progress Calculation

Preferred formula:

- `transfer_progress_percent = transferred_bytes / transfer_size_bytes * 100`

Fallback formula for chunk-based state:

- `current_chunk_index / total_chunks * 100`

Byte-based progress is better because chunk sizes may vary.

### Recommended Storage

Large-file progress should be tracked per delivery target, not only per logical message.

Reason:

- the same file may be sent to multiple upstream hubs
- one target may be completed while another is still uploading

So progress belongs on the delivery record or a delivery-attachment child record.

### Recommended Additional Table

If attachments are large and progress must be tracked precisely, add:

### `hub_relay_attachment_transfers`

Suggested columns:

- `id`
- `hub_relay_delivery_id`
- `hub_relay_attachment_id`
- `transfer_status`
- `transfer_size_bytes`
- `transferred_bytes`
- `transfer_progress_percent`
- `current_chunk_index`
- `total_chunks`
- `upload_session_id`
- `last_activity_at`
- `completed_at`
- `last_error`
- `created_at`
- `updated_at`

This keeps relay-message metadata separate from actual transport state.

### UX Implication

For monitoring screens, large-file deliveries can show:

- `Uploading 42%`
- `Uploading 612 MB / 1.4 GB`
- `Resuming upload`
- `Assembling file`

That is useful for operational awareness and troubleshooting slow links.

## SITREP Example

Barangay hub creates a SITREP.

### Outbound

- create `hub_relay_message`
- message type: `sitrep.record`
- payload format: `json`
- create deliveries for:
  - city hub
  - foundation hub

### Receiver

- validate sender
- check `relay_id`
- store SITREP
- attach receipt
- return acknowledgment

### Result

The same SITREP reaches multiple upstream hubs through identical relay behavior.

## Recommended API Surface

### Sender-Side

- `relay.enqueue(messageType, payload, options)`
- `relay.enqueueForTargets(targetHubIds, messageType, payload, options)`
- `relay.attach(messageId, fileMeta, storageRef)`

### Receiver-Side

Core message endpoints:

- `POST /api/hub-relay/receive`
  - receive a single relay message with JSON payload
- `POST /api/hub-relay/receive-batch`
  - optional batch receive for smaller structured messages

Attachment and large-file endpoints:

- `POST /api/hub-relay/upload/init`
  - initialize attachment upload session
- `POST /api/hub-relay/upload/chunk`
  - upload one chunk for a target delivery/session
- `POST /api/hub-relay/upload/complete`
  - finalize and assemble uploaded chunks
- `GET /api/hub-relay/upload/{session}/status`
  - return current upload progress and resumable state
- `POST /api/hub-relay/receive-attachment`
  - optional non-chunked attachment receive for smaller files

### Proposed Endpoint Contract

#### 1. Structured Message Receive

- `POST /api/hub-relay/receive`

Purpose:

- receive one structured relay envelope and payload

Body:

- envelope metadata
- payload JSON
- optional attachment references

Response:

- `relay_id`
- `receipt_status`
- `received_at`

#### 2. Upload Session Initialize

- `POST /api/hub-relay/upload/init`

Purpose:

- create an upload session for a large attachment bound to a relay delivery

Suggested request fields:

- `relay_id`
- `target_hub_id`
- `attachment_name`
- `mime_type`
- `size_bytes`
- `checksum`
- `chunk_size_bytes`

Suggested response fields:

- `upload_session_id`
- `accepted`
- `chunk_size_bytes`
- `next_chunk_index`

#### 3. Upload Chunk

- `POST /api/hub-relay/upload/chunk`

Purpose:

- send one chunk of a large attachment

Suggested request fields:

- `upload_session_id`
- `chunk_index`
- `total_chunks`
- `chunk_checksum`
- binary chunk content

Suggested response fields:

- `accepted`
- `received_chunk_index`
- `transferred_bytes`
- `transfer_progress_percent`
- `next_chunk_index`

#### 4. Upload Complete

- `POST /api/hub-relay/upload/complete`

Purpose:

- signal that all chunks are uploaded and request final assembly/validation

Suggested request fields:

- `upload_session_id`
- `total_chunks`
- `final_checksum`

Suggested response fields:

- `assembled`
- `verified`
- `attachment_id`
- `receipt_status`

#### 5. Upload Status

- `GET /api/hub-relay/upload/{session}/status`

Purpose:

- allow sender or monitoring UI to query current progress

Suggested response fields:

- `upload_session_id`
- `transfer_status`
- `transfer_size_bytes`
- `transferred_bytes`
- `transfer_progress_percent`
- `current_chunk_index`
- `total_chunks`
- `last_activity_at`

### Admin/Monitoring

- `GET /api/hub-relay/outbox`
- `GET /api/hub-relay/inbox`
- `GET /api/hub-relay/deliveries`
- `GET /api/hub-relay/deliveries/{delivery}`
- `GET /api/hub-relay/uploads/{session}`
- `POST /api/hub-relay/{delivery}/retry`
- `POST /api/hub-relay/{delivery}/cancel`

## Processing Architecture

Recommended separation:

- `HubRelayService`
  - enqueue and delivery orchestration
- `HubRelaySender`
  - HTTP transmission
- `HubRelayReceiver`
  - inbound validation and receipt handling
- `HubRelayHandlerRegistry`
  - payload-type handlers

Example handlers:

- `SitrepRelayHandler`
- `IncidentRelayHandler`
- `AttachmentRelayHandler`

## Monitoring and UX

Eventually, the HQ Hub and other hubs should expose relay monitoring such as:

- queued deliveries
- failed deliveries
- last delivery time
- target hub status
- retry counts
- dead-letter entries

Useful UI states:

- `Queued`
- `Sending`
- `Delivered`
- `Failed`
- `Dead`
- `Offline`
- `Recovering`

## Proposed Management and Monitoring Views

The relay system should separate operational monitoring from relay configuration.

Recommended split:

- Operations views
- Detail views
- Configuration views

### 1. Relay Dashboard

Purpose:

- top-level health and activity summary for the local hub relay state

Recommended content:

- queued deliveries count
- sending deliveries count
- delivered count
- failed count
- dead count
- active upload count
- last successful delivery time
- failing target hubs
- slowest target hubs by response time

This should be the default monitoring entry point.

### 2. Outbox

Purpose:

- show messages created locally and being sent upstream

Recommended filters:

- target hub
- message type
- status
- date range

Recommended columns:

- relay id
- message type
- target hub
- status
- progress
- attempts
- created at
- last attempt at

Recommended actions:

- view details
- retry
- cancel if allowed

### 3. Inbox

Purpose:

- show relay messages received from downstream hubs

Recommended filters:

- source hub
- message type
- receipt status
- date range

Recommended columns:

- relay id
- source hub
- message type
- received at
- processed at
- receipt status

Recommended actions:

- view details
- inspect payload
- inspect attachments

### 4. Deliveries

Purpose:

- show one row per target delivery

This should be the primary troubleshooting view because one logical message may have multiple upstream targets.

Recommended columns:

- relay id
- source hub
- target hub
- delivery status
- transfer progress
- attempts
- next retry at
- last error
- delivered at

Recommended actions:

- view delivery
- retry
- cancel

### 5. Uploads

Purpose:

- monitor large file and image transfers

Recommended columns:

- upload session id
- attachment name
- relay id
- target hub
- size
- transferred bytes
- progress percent
- transfer status
- last activity at

Recommended actions:

- view upload
- retry
- resume
- cancel

### 6. Message Details

Purpose:

- inspect one relay message end-to-end

Recommended sections:

- envelope metadata
- payload preview
- attachment list
- target deliveries
- attempt timeline
- processing receipts

This is the main investigation view for a single relay item.

### 7. Delivery Details

Purpose:

- inspect one delivery target path in detail

Recommended sections:

- source hub
- target hub
- current status
- transfer progress
- retry history
- response/error timeline

This is useful when one target hub is failing while others succeeded.

### 8. Hub Link Status

Purpose:

- monitor relay behavior by upstream target hub

Recommended columns:

- target hub
- current connectivity
- queue depth
- failed deliveries
- last delivered at
- average response time

This view helps identify network issues between hubs.

### 9. Relay Configuration

Purpose:

- manage local relay behavior and trust settings

Recommended sections:

- local relay identity
- upstream targets
- relay credentials/tokens
- retry policy
- chunk size defaults
- payload-type enablement

### 10. Dead Letters / Failed Items

Purpose:

- operator-facing queue for messages that exceeded retry policy or were rejected

Recommended actions:

- inspect
- retry manually
- mark resolved
- archive

## Recommended Initial Information Architecture

Suggested navigation:

- `Relay`
  - `Dashboard`
  - `Outbox`
  - `Inbox`
  - `Deliveries`
  - `Uploads`
  - `Configuration`

## Recommended Minimum Phase-1 Views

If phase 1 should stay tight, the strongest initial set is:

- `Relay Dashboard`
- `Outbox`
- `Inbox`
- `Message Details`
- `Delivery Details`

That is enough for operational visibility before adding richer upload and configuration management views.

## HQ Hub Recommendation

For the HQ Hub specifically, the most valuable first views are:

- `Relay Dashboard`
- `Deliveries`
- `Message Details`
- `Hub Link Status`

Reason:

- HQ is likely to care more about network-wide relay health and upstream/downstream delivery behavior than only one local queue.

## Recommended Phase 1

Initial implementation should support:

- upstream-only relay
- JSON payloads
- one-to-many upstream deliveries
- durable queueing
- retries
- idempotent receiver
- basic monitoring

Payload focus for phase 1:

- `SITREP`

## Recommended Phase 2

- file and image attachments
- relay monitoring UI
- replay/retry controls
- manual target routing controls
- richer handler registry

## Recommended Phase 3

- batching
- partial compression
- priority queues
- selective forwarding policies
- downstream or peer relay if ever needed later

## Recommendation

Build `Hub Relay` as a shared, upstream-only message relay subsystem that runs identically on every hub.

It should use:

- a common envelope
- per-target delivery tracking
- idempotent receipt handling
- queue-based retries
- payload-type handlers above a generic transport layer

This gives PBB a consistent way to move SITREPs and future records/files/images upstream without tightly coupling transport logic to one payload type or one specific hub level.
