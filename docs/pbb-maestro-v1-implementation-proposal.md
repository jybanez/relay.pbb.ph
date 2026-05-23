# PBB Maestro V1 Implementation Proposal

## Purpose

Define the implementation-oriented `V1` plan for `PBB Maestro`.

This document translates the broader project proposal into a concrete first release that a delivery team can estimate, design, and build.

## Relationship To The Main Project Proposal

This document is the implementation companion to:

- `pbb-maestro-project-proposal.md`

The main proposal defines the product direction.

This document defines:

- the `V1` scope
- the first modules
- the first data model
- the first telemetry contract
- the initial rollout path

## V1 Objective

`PBB Maestro V1` should make worker processes visible.

Its first job is not to manage or scale workers directly.

Its first job is to let operators answer:

- what workers exist?
- what queues are they consuming?
- are they alive?
- are they busy?
- are they stale?
- what did they process recently?
- is queue backlog likely caused by worker issues?

## V1 Principles

- monitoring first
- orchestration later
- application-agnostic design
- low-friction integration for the first app, which is relay
- grid-first operator UI
- environment-neutral control model
- helpers-first UI implementation
- standardized PBB browser-session behavior

## Shared UI And Auth References

### UI Library Reference

`PBB Maestro V1` should use the official shared UI library:

- `helpers.pbb.ph`
- official repository: `https://github.com/jybanez/helpers.pbb.ph.git`

That should be the default path for:

- page shell and navigation
- `ui.grid`
- dialogs
- action modals
- form modals
- skeleton loading states

This keeps V1 aligned with the broader PBB frontend direction and reduces custom UI work.

For V1, this should be treated as a vendored or locally stored dependency, not a CDN dependency.

### Session Handling Reference

Browser session behavior should follow:

- `pbb-user-session-handling-proposal.md`

Recommended V1 behavior:

- authenticated operator UI
- protected fetch calls for data grids
- expired-session detection
- modal-based re-authentication without page-loss where appropriate

### Login / Logout Reference

Browser login/logout flow should follow:

- `login-logout-flow-reference.md`
- `login-form-reference.md`

Recommended V1 auth endpoints:

- `POST /api/login`
- `GET /api/user`
- `POST /api/logout`

## Offline-Ready Requirement

`PBB Maestro V1` should assume that some deployments may operate without internet access.

Because of that, V1 should be built so that runtime operation does not depend on fetching required assets or libraries from the public internet.

V1 expectations:

- vendor `helpers.pbb.ph` locally or make it available from a trusted internal local package source
- store required frontend assets locally
- avoid CDN dependencies for core UI behavior
- ensure Swagger/OpenAPI assets, if adopted, are also available locally
- ensure runtime-critical packages can be installed and deployed in environments with restricted connectivity

Recommended V1 vendoring rule for `helpers.pbb.ph`:

- vendor the library directly into the Maestro repository
- pin it to a specific upstream commit or tagged version
- record that pinned version in a local vendoring note such as `VENDORED.md`
- load Maestro UI assets from the local vendored copy, not from the public internet

Simple rule:

- if Maestro needs it to operate, it should be available locally

## Shared Reference Consumption

If Maestro is developed in its own repository, the shared auth/session reference documents should be imported into that repository's docs set or rewritten into Maestro-owned standards documents.

V1 should not assume live filesystem links back to the relay repository.

The intended reuse is:

- shared UX/auth standard
- not hard runtime dependency on relay documentation files

## Suggested Baseline Environment

To keep startup friction low, `PBB Maestro V1` should strongly consider using the same current baseline as relay unless the new team has a clear reason to diverge.

Recommended baseline:

- Laravel `12.54.1`
- PHP `8.2.29`
- PHP CLI path: `C:/wamp64/bin/php/php8.2.29/php.exe`
- MySQL

Prepared local development database:

- host: `localhost`
- database: `pbb_maestro`
- username: `root`
- password: blank

This gives the team a concrete and aligned starting environment for V1 implementation.

## V1 Scope

### Included In V1

- application registry
- worker registry
- heartbeat ingestion
- worker event ingestion
- worker status derivation
- worker grids and dashboards
- app-level and queue-level worker visibility
- relay integration as the first producer

### Explicitly Excluded From V1

- process spawning
- process killing
- scaling workers automatically
- restart orchestration
- environment adapters for systemd, Supervisor, Kubernetes, etc.
- advanced alert automation
- CPU and host infrastructure monitoring

## V1 Target Outcome

At the end of V1, an operator should be able to open Maestro and see:

- registered applications
- current worker processes
- queue assignments
- worker status
- stale workers
- recent worker/job lifecycle events

And, for relay specifically:

- whether relay delivery workers are alive
- whether relay handler workers are alive
- whether worker freshness explains relay backlog or delivery failures

## V1 Boundary

`PBB Maestro V1` is strictly a monitoring and visibility release.

It must not drift into worker orchestration.

### V1 Allowed Responsibilities

- telemetry ingestion
- worker registration
- heartbeat tracking
- stale detection
- worker-event recording
- worker/app/queue dashboards and grids

### V1 Disallowed Responsibilities

- starting workers
- stopping workers
- restarting workers
- killing hung workers
- scaling worker count
- executing host-level process commands
- directly integrating with `systemd`, `Supervisor`, Docker, Kubernetes, or Windows service control

### Practical Rule

If a feature requires Maestro to issue a process-control command to the host environment, it is outside `V1`.

## Suggested Core Modules

### 1. Applications Module

Purpose:

- track applications integrated with Maestro

V1 responsibilities:

- create and store application identity
- expose application metadata for UI and telemetry validation

Suggested fields:

- `app_code`
- `display_name`
- `environment`
- `base_url`
- `is_active`

Examples:

- `relay`
- `hq`
- `sitrep`

### 2. Workers Module

Purpose:

- track latest known state of each worker process

V1 responsibilities:

- register worker instances
- update heartbeat state
- derive worker status
- expose current state to UI

### 3. Worker Events Module

Purpose:

- preserve lifecycle and job activity history

V1 responsibilities:

- record worker lifecycle events
- record job lifecycle events
- support event grid rendering

### 4. Dashboard Module

Purpose:

- show high-level worker health

V1 responsibilities:

- active worker counts
- stale worker counts
- busy vs idle counts
- recent failures
- per-application worker distribution

## Proposed Data Model

## 1. `maestro_applications`

Represents applications integrated with Maestro.

Suggested fields:

- `id`
- `app_code`
- `display_name`
- `environment`
- `base_url`
- `is_active`
- `meta_json`
- timestamps

Constraints:

- unique `app_code`

## 2. `maestro_workers`

Represents the current known state of a worker process.

Suggested fields:

- `id`
- `maestro_application_id`
- `worker_id`
- `host_name`
- `queue_name`
- `process_id`
- `status`
- `started_at`
- `last_heartbeat_at`
- `last_job_started_at`
- `last_job_finished_at`
- `current_job_type`
- `current_job_id`
- `processed_count`
- `failed_count`
- `memory_mb`
- `stopped_at`
- `meta_json`
- timestamps

Constraints:

- unique `worker_id`

V1 note:

- this table intentionally combines worker identity and mutable runtime state for implementation speed
- if the project later needs clearer separation, it can split into worker-instance records plus live-status snapshots

## 3. `maestro_worker_events`

Append-only event log for worker and job activity.

Suggested fields:

- `id`
- `maestro_worker_id`
- `worker_id`
- `event_type`
- `queue_name`
- `job_type`
- `job_id`
- `outcome`
- `notes`
- `payload_json`
- `occurred_at`
- timestamps

Suggested indexes:

- `worker_id`
- `event_type`
- `occurred_at`
- `queue_name`

## Worker Identity Contract

Each worker process should generate a unique runtime identity on boot.

Suggested structure:

- `host:pid:started_at:random_suffix`

Example:

- `relay-node-01:18244:2026-03-17T14:30:00Z:9f3a`

Requirements:

- unique per runtime instance
- stable for the life of the process
- not reused after restart

## V1 Telemetry Contract

Telemetry should be sent from integrated applications to Maestro over HTTP.

### V1 Telemetry Decisions

To keep implementation concrete, V1 should make these explicit decisions:

- authentication:
  - use an application-level telemetry token issued per registered application
- idempotency:
  - heartbeats should upsert by `worker_id`
  - worker events should require a producer-generated `event_id` with a unique constraint
- duplicate handling:
  - duplicate `event_id` submissions should be accepted as idempotent no-ops
- clock skew:
  - Maestro server time is the canonical ingest time
  - producer-supplied timestamps should still be stored as reported times
  - if reported time is outside an allowed skew threshold, Maestro should flag the event or worker record rather than reject it immediately
- missing `worker.stopped`:
  - if a worker dies without emitting `worker.stopped`, Maestro should mark it `stale` after heartbeat expiry
  - V1 should not automatically convert `stale` into `stopped` without stronger evidence

Suggested V1 skew threshold:

- `60s`

### 1. Worker Registration / Heartbeat

Suggested endpoint:

- `POST /api/v1/telemetry/workers/heartbeat`

Suggested payload:

```json
{
  "app_code": "relay",
  "worker_id": "relay-node-01:18244:2026-03-17T14:30:00Z:9f3a",
  "host_name": "relay-node-01",
  "queue_name": "relay-deliveries",
  "process_id": 18244,
  "status": "idle",
  "started_at": "2026-03-17T14:30:00Z",
  "last_heartbeat_at": "2026-03-17T14:31:15Z",
  "current_job_type": null,
  "current_job_id": null,
  "processed_count": 128,
  "failed_count": 3,
  "memory_mb": 54.7,
  "meta": {
    "php_version": "8.2.29",
    "queue_connection": "database"
  }
}
```

### 2. Worker Event Ingestion

Suggested endpoint:

- `POST /api/v1/telemetry/worker-events`

Suggested payload:

```json
{
  "event_id": "a6f447b0-79c1-4fe7-8a86-5d312d9b7f48",
  "app_code": "relay",
  "worker_id": "relay-node-01:18244:2026-03-17T14:30:00Z:9f3a",
  "event_type": "job.completed",
  "queue_name": "relay-deliveries",
  "job_type": "ProcessRelayDelivery",
  "job_id": "f6ebc8c9-9a22-4f1f-a88b-98eb3f77d812",
  "outcome": "success",
  "notes": "Delivery job completed successfully.",
  "occurred_at": "2026-03-17T14:31:19Z",
  "payload": {
    "target_hub_id": "city-hub"
  }
}
```

## Recommended Status Derivation Rules

V1 suggested statuses:

- `starting`
- `idle`
- `busy`
- `stale`
- `stopped`

Suggested derivation:

- `starting`
  - worker registered very recently
- `busy`
  - last heartbeat is fresh and a current job is present
- `idle`
  - last heartbeat is fresh and no current job is present
- `stale`
  - last heartbeat exceeded freshness threshold
- `stopped`
  - explicit stop event received

Recommended V1 thresholds:

- heartbeat interval target: `15s`
- stale threshold: `45s`

## Stale Evaluation Strategy

V1 should make stale evaluation explicit.

Recommended approach:

- evaluate freshness on read for UI and API responses
- also run a lightweight scheduled reconciliation job that marks obviously stale workers in storage

This gives V1 two useful behaviors:

- current UI reads always reflect the latest known freshness
- persisted worker rows can still be updated for reporting, filtering, and summaries

Recommended implementation shape:

- read-time derivation is the primary truth for operator views
- scheduled reconciliation is a secondary consistency pass, not the only stale detector

This avoids a design where stale state depends entirely on a scheduler firing on time.

Recommended interpretation for worker death without explicit stop:

- heartbeat freshness failure moves worker to `stale`
- operator UI should surface `stale duration`
- later phases may introduce stronger offline inference rules, but V1 should keep this conservative

## Relay Integration Plan

Relay should be the first integrated application.

### Relay V1 Integration Responsibilities

- generate worker identity on boot
- send heartbeat updates
- emit worker lifecycle events
- emit job lifecycle events for:
  - delivery jobs
  - local handler dispatch jobs

### Relay Integration Events

Recommended relay event types:

- `worker.started`
- `worker.heartbeat`
- `worker.stopped`
- `job.started`
- `job.completed`
- `job.failed`

### Relay Metadata To Include

- queue name
- worker role
- current job type
- process id
- memory usage
- app version

## Suggested Maestro UI

The operator UI should be implemented using `helpers.pbb.ph` wherever possible.

## 1. Dashboard

High-level cards:

- `Applications`
- `Active Workers`
- `Busy Workers`
- `Stale Workers`
- `Recent Failures`

Panels:

- recent worker events
- workers by application
- workers by queue

## 2. Workers Section

Primary grid.

Suggested columns:

- `Worker ID`
- `Application`
- `Queue`
- `Host`
- `PID`
- `Status`
- `Started`
- `Last Heartbeat`
- `Current Job`
- `Processed`
- `Failed`
- `Memory MB`
- `Uptime`

## 3. Worker Events Section

Secondary grid.

Suggested columns:

- `Time`
- `Application`
- `Worker`
- `Queue`
- `Event`
- `Job Type`
- `Outcome`
- `Notes`

## 4. Applications Section

Suggested columns:

- `Application`
- `Environment`
- `Workers`
- `Busy`
- `Stale`
- `Last Seen`

## Authenticated Pages Needed In V1

After operator login, `PBB Maestro V1` should have a defined authenticated page set.

Minimum V1 pages:

- `Dashboard`
  - landing page after login
  - summary cards and recent event panels
- `Workers`
  - primary worker grid
- `Worker Events`
  - event log grid
- `Applications`
  - registered app grid with worker summaries
- `Queues`
  - queue summary grid or queue-oriented monitoring page

Supporting authenticated pages:

- `Login`
  - modal or dedicated entry flow following the shared PBB auth references
- `Session / Current User`
  - current operator context and logout flow

Deferred but recommended next pages:

- `Worker Detail`
- `Application Detail`
- `Queue Detail`

Even if V1 starts with list/grid pages only, the page map should be explicit from the start so navigation and auth/session flow are designed coherently.

## Suggested Internal API Surface

Operator-facing read endpoints:

- `GET /api/v1/applications`
- `GET /api/v1/workers`
- `GET /api/v1/worker-events`

Telemetry ingestion endpoints:

- `POST /api/v1/telemetry/workers/heartbeat`
- `POST /api/v1/telemetry/worker-events`

Browser auth/session endpoints:

- `POST /api/login`
- `GET /api/user`
- `POST /api/logout`

## Security Requirements

Telemetry ingestion must be authenticated.

Final V1 rule:

- use an application-level telemetry token issued per registered application

Recommended contract:

- each integrated application has one or more Maestro-issued telemetry tokens
- telemetry ingestion endpoints require that token on every request
- trusted internal network alone is not sufficient authentication for V1
- signed request schemes may be explored later, but are not required for V1

Operator UI must be authenticated and role-protected.

V1 requirements:

- no public worker visibility
- no public host/process details
- audit log sensitive actions if any are added later

Operator authentication should follow the shared browser-session flow and re-authentication pattern already documented in the PBB references above.

## Suggested Delivery Plan

### Milestone 1: Foundation

- create project skeleton
- create core database tables
- create application registry
- create telemetry auth model
- vendor required frontend/runtime dependencies for offline-ready deployment

### Milestone 2: Telemetry Ingestion

- heartbeat endpoint
- worker-events endpoint
- worker status derivation
- stale detection

### Milestone 3: Operator UI

- dashboard
- workers grid
- worker events grid
- application grid
- login/logout/session flow using the shared PBB reference pattern
- helpers-based UI shell and component usage

### Milestone 4: Relay Integration

- relay worker heartbeat reporting
- relay worker event reporting
- validation of real worker data under queue load

## Explicit V2 Follow-On

After `V1` monitoring is stable, `V2` may introduce environment adapters for controlled orchestration.

That later phase can cover:

- start/stop/restart actions
- desired worker counts
- scaling workflows
- environment-specific execution integration

## Suggested Team Questions Before Build

- should Maestro be Laravel-based for faster alignment with relay?
- what telemetry auth model is preferred?
- should worker telemetry be pull-based, push-based, or hybrid?
- what deployment environments must V1 support first?
- should Maestro own alerting in V1 or only visibility?

## Recommended V1 Success Criteria

`PBB Maestro V1` is successful if:

- relay can report worker telemetry into Maestro
- operators can see current workers in a grid
- operators can detect stale workers
- operators can correlate worker state with relay queue behavior
- the system works without owning process control yet

## Recommendation

Build `PBB Maestro V1` as a monitoring-first platform service with relay as the first integrated application.

Keep the first release intentionally narrow:

- ingest worker telemetry
- derive worker health
- show worker state clearly

That will provide immediate operational value while keeping the architecture clean and extensible for future orchestration features.
