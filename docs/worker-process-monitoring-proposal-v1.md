# PBB Relay Worker-Process Monitoring Proposal

## Proposal Stage

This document defines the recommended `V1` implementation for worker-process monitoring in `PBB - Hub Relay Server`.

## Current Status

This document should now be treated as background context and a superseded relay-side direction.

The preferred product direction is to move worker-process monitoring into `PBB Maestro` as a separate platform project.

This proposal is still useful as:

- source context for why worker visibility is needed
- relay-specific operational motivation
- supporting reference for Maestro integration requirements

It should not be treated as the active primary implementation direction for relay itself.

## Purpose

Add `V1` worker-process monitoring to `PBB - Hub Relay Server` so operators can visually understand what the relay background workers are doing behind the scenes.

This proposal is specifically for:

- queue workers that process relay deliveries
- queue workers that process local handler dispatches
- operational visibility inside the relay admin UI
- worker telemetry that can be rendered in grids and dashboards

It is not intended to replace:

- OS-level process supervision
- service managers such as Supervisor, NSSM, or Windows Task Scheduler
- infrastructure monitoring such as CPU, RAM, disk, and network telemetry

## Problem

The relay currently exposes queue and delivery outcomes, but not worker-process state.

Today, operators can see:

- queued deliveries
- failed or dead deliveries
- recent handler dispatches
- recent uploads and receipts

But operators cannot directly see:

- whether any worker is running
- how many workers are running
- which queues each worker is consuming
- whether a worker is alive but idle
- whether a worker is stale or hung
- which worker last processed a job
- whether relay backlog is caused by no workers, slow workers, or failing jobs

This creates an operational blind spot.

## Goals

- visually show active worker processes inside the relay UI
- persist worker telemetry in application storage so it can be queried and rendered
- support multiple worker processes, not only a single worker
- show current state, recent activity, and stale/offline worker conditions
- make the data easy to render with `ui.grid`
- support both `relay-deliveries` and `relay-handlers`
- minimize coupling to a specific infrastructure tool

## Non-Goals

- replacing system-level service supervision
- replacing full infrastructure observability platforms
- implementing Laravel Horizon as part of this proposal
- auto-scaling workers
- remote process control such as restart/kill from the relay UI

## Core Idea

Each queue worker should self-report lightweight telemetry to the relay application on a fixed heartbeat interval.

That telemetry should be stored in relay tables and rendered in the operator UI as:

- a live `Workers` grid
- a `Worker Events` or `Worker Activity` grid
- summary cards on the dashboard

This gives operators visibility into the worker layer without requiring direct access to the host machine.

## Proposed Operator Experience

The relay admin UI should expose a new section:

- `/relay/workers`

The section should use the same page-shell pattern as other relay admin screens:

- page header
- toolbar
- content row

The primary content should be a `ui.grid`.

### Workers Grid

Each row represents one worker process instance.

Suggested columns:

- `Worker ID`
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

Suggested row meaning:

- one row = one running `php artisan queue:work ...` process

### Worker Activity Grid

Optional secondary grid for recent events such as:

- worker started
- worker heartbeat
- job started
- job completed
- job failed
- worker became stale
- worker stopped

Suggested columns:

- `Timestamp`
- `Worker ID`
- `Queue`
- `Event`
- `Job Type`
- `Job ID`
- `Outcome`
- `Notes`

## Suggested Status Model

Worker status should be derived from heartbeat freshness and current execution state.

Suggested statuses:

- `starting`
- `idle`
- `busy`
- `stale`
- `stopped`
- `errored`

Recommended interpretation:

- `starting`
  - worker registered recently but has not yet completed a normal heartbeat cycle
- `idle`
  - worker is alive and not currently processing a job
- `busy`
  - worker is alive and currently processing a job
- `stale`
  - worker has not heartbeat-reported within the allowed freshness window
- `stopped`
  - worker shut down gracefully and reported exit
- `errored`
  - worker encountered a fatal reporting issue or unrecoverable process state

## Proposed Data Model

### 1. `hub_relay_workers`

Represents the latest known state for each worker process.

Suggested fields:

- `id`
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
- `meta_json`
- `stopped_at`
- timestamps

Recommended uniqueness:

- unique `worker_id`

### 2. `hub_relay_worker_events`

Append-only activity log for worker lifecycle and job lifecycle events.

Suggested fields:

- `id`
- `hub_relay_worker_id`
- `worker_id`
- `queue_name`
- `event_type`
- `job_type`
- `job_id`
- `outcome`
- `notes`
- `payload`
- `occurred_at`
- timestamps

## Worker Identity

Each worker process should generate a stable runtime identifier when it starts.

Suggested format:

- `host-name:pid:start-timestamp:random-suffix`

Example:

- `relay-node-01:18492:2026-03-17T10:15:00Z:ab12`

This avoids collisions when processes restart and lets the UI distinguish current vs historical workers.

## Telemetry Collection Strategy

The simplest implementation is application-level heartbeat reporting from the worker process itself.

### On worker boot

The worker should:

- generate `worker_id`
- detect queue names it is consuming
- capture host and pid
- create or upsert the `hub_relay_workers` row
- log a `worker.started` event

### On heartbeat interval

Every fixed interval, such as every 15 to 30 seconds, the worker should:

- update `last_heartbeat_at`
- update current memory usage
- update derived status

### On job start

Before processing a job:

- mark worker as `busy`
- record `current_job_type`
- record `current_job_id`
- log `job.started`

### On job completion

After successful processing:

- clear `current_job_*`
- increment `processed_count`
- mark worker as `idle`
- log `job.completed`

### On job failure

After failure:

- clear `current_job_*`
- increment `failed_count`
- mark worker as `idle` or `errored` depending on severity
- log `job.failed`

### On graceful shutdown

If possible:

- set `status = stopped`
- set `stopped_at`
- log `worker.stopped`

## Freshness and Staleness Rules

Recommended defaults:

- heartbeat interval: `15s`
- stale threshold: `45s`
- offline/stopped detection should prefer explicit shutdown if available

Interpretation:

- if `now - last_heartbeat_at <= 45s`, worker is considered alive
- if greater than threshold and not explicitly stopped, worker becomes `stale`

## Laravel Integration Strategy

This can be implemented without changing the queue backend from `database`.

Suggested components:

- `RelayWorkerMonitorService`
- `RelayWorkerTelemetry`
- queue event listeners for:
  - `JobProcessing`
  - `JobProcessed`
  - `JobFailed`
- worker bootstrap command wrapper or custom artisan command such as:
  - `relay:work`

### Recommended command shape

Instead of asking operators to run raw `queue:work`, prefer a relay-aware wrapper:

```powershell
C:/wamp64/bin/php/php8.2.29/php.exe artisan relay:work --queue=relay-deliveries,relay-handlers
```

The wrapper can:

- register worker identity
- start heartbeat loop
- attach queue listeners
- delegate actual work to Laravel queue processing

This makes monitoring consistent and reduces setup mistakes.

## Proposed Relay UI Changes

### New Admin Section

Add:

- `/relay/workers`

This should appear in the top operator navigation as:

- `Workers`

### Dashboard Additions

Add worker summary cards such as:

- `Active Workers`
- `Busy Workers`
- `Stale Workers`
- `Failed Jobs`

Add a small recent-workers or worker-health panel.

### Grids

Primary grid:

- `Workers`

Secondary grid:

- `Worker Events`

Both are well-suited for `ui.grid`.

## Suggested API/Data Endpoints

Internal web-data endpoints:

- `GET /relay/data/sections/workers`
- `GET /relay/data/workers/events`

Optional public/admin API later:

- `GET /api/v1/admin/workers`
- `GET /api/v1/admin/worker-events`

These should remain protected operator endpoints unless there is a later need for external machine access.

## Security Considerations

Worker monitoring should avoid exposing sensitive infrastructure details publicly.

Recommendations:

- keep worker views operator-authenticated
- do not expose absolute filesystem paths
- do not expose raw environment variables
- avoid showing private keys, tokens, or secrets in worker metadata
- be careful with hostnames if production topology is sensitive

## Operational Benefits

This proposal would let operators answer questions such as:

- are any workers running right now?
- are both relay queues being consumed?
- is the backlog caused by zero workers?
- is a worker alive but stuck?
- which worker is failing jobs?
- did workers stop after deployment or reboot?

That is the missing behind-the-scenes visibility the relay currently does not provide.

## Why This Fits The Relay

The relay depends on background processing for:

- outbound delivery
- retry scheduling
- local handler dispatches

Those are core relay functions, not optional extras.

Because of that, worker-process visibility is operationally relevant to the relay itself and belongs in the relay operator surface.

## Recommended V1 Scope

V1 should stay focused.

Implement:

- `hub_relay_workers` table
- heartbeat tracking
- queue event listeners
- `/relay/workers` grid
- dashboard worker summary cards
- stale worker detection

Defer:

- remote restart controls
- deep OS metrics
- cluster topology maps
- Horizon-style charts
- per-second throughput graphs

## Future Enhancements

Potential later additions:

- worker detail modal
- worker-event detail inspector
- throughput charts by queue
- alerting when no workers are alive
- alerting when stale workers exceed threshold
- deployment-aware worker grouping
- host-level health summaries

## Recommendation

Implement `V1` worker-process monitoring as a relay-native operational feature.

The relay already tracks message, delivery, handler, and upload activity. The next useful operational layer is to track the worker processes that make those activities happen.

This will give `PBB - Hub Relay Server` a clearer operator story:

- not only what happened to messages
- but also which worker processes were alive, busy, stale, or failing while it happened
