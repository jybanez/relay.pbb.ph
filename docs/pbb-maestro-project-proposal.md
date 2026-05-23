# PBB Maestro Project Proposal

## Purpose

Define `PBB Maestro` as a new Project Bantay Bayan platform component responsible for orchestrating, supervising, and monitoring background worker processes across PBB applications.

The immediate motivation is the need to manage relay workers outside of `PBB - Hub Relay Server`, while still giving operators and administrators visibility into worker lifecycle and processing health.

## Proposed Name

Primary project name:

- `PBB Maestro`

Rationale:

- a maestro coordinates many moving parts
- the name implies orchestration and control
- it is broader than a single worker dashboard
- it leaves room for future cross-application process management

## Shared UI And Session Standards

`PBB Maestro` should align with existing PBB frontend and browser-session standards from the beginning.

## Offline-First Deployment Constraint

There is a strong possibility that `PBB Maestro` will be deployed in environments without reliable internet access, or with no internet access at all.

Because of that, Maestro should be designed with an offline-first deployment assumption.

Practical implication:

- any required frontend library, asset, stylesheet, script, icon set, or third-party dependency needed at runtime should be vendored or stored locally
- Maestro should not depend on public CDNs for required UI or JS functionality
- runtime-critical documentation or reference assets that the project depends on should be available locally within the repository or deployment package
- third-party binaries, packages, or support assets required for operation should be installable and runnable without depending on live internet access during normal operation

This is especially important for:

- `helpers.pbb.ph`
- frontend assets
- Swagger/OpenAPI UI assets if used
- icon packs or fonts if required
- other third-party resources used by the operator UI or runtime

The default design assumption should be:

- if Maestro needs it to function in production, Maestro should be able to serve or access it locally

### UI Library

The project should use the official shared UI library:

- `helpers.pbb.ph`
- official repository: `https://github.com/jybanez/helpers.pbb.ph.git`

This should be treated as the default UI implementation path for:

- layout primitives
- navigation
- grids
- dialogs
- action modals
- form modals
- skeleton loading states

The goal is to keep Maestro focused on worker-monitoring functionality, not custom UI reinvention.

For offline-ready deployments, the helpers library should be vendored into the Maestro project or otherwise stored in a locally accessible internal package source.

### Session Handling

Browser session handling should follow the existing PBB standard described in:

- `pbb-user-session-handling-proposal.md`

That means Maestro should support:

- session-authenticated operator access
- expired-session detection on protected requests
- modal-based re-authentication without losing page context
- consistent session-expiry signaling for frontend consumers

### Login / Logout Flow

Browser authentication flow should follow the reference described in:

- `login-logout-flow-reference.md`
- `login-form-reference.md`

This should include:

- `POST /api/login`
- `GET /api/user`
- `POST /api/logout`

And the same shared browser-session pattern used across PBB applications.

## Shared Reference Dependency

The session and login references above currently originate from the relay-side design set.

If `PBB Maestro` is created in a separate repository, those references should be handled explicitly in one of these ways:

- copy the approved reference documents into Maestro's own `docs/` or `docs/references/` folder
- vendor them as design references from a shared internal docs source
- restate the approved standards directly in Maestro docs and treat relay as the historical source

Maestro should not depend on broken cross-repo filesystem links.

The intended dependency is conceptual and standards-based:

- Maestro should reuse the same approved browser-session and login patterns
- Maestro should not depend on the relay repository continuing to host those references forever

## Proposal Stage

This document defines the recommended initial project direction for `PBB Maestro`.

It is a platform proposal, not a relay-only feature proposal.

## Problem

PBB applications increasingly depend on background processes such as:

- queue workers
- delivery workers
- handler dispatch workers
- scheduled jobs
- asynchronous processors

These processes are essential to correct operation, but they are currently managed outside the application itself by environment-specific tools.

That creates several recurring problems:

- worker lifecycle management is inconsistent across deployments
- worker monitoring is fragmented or missing
- applications can expose queue symptoms but not process reality
- stale, hung, missing, or overloaded workers are difficult to diagnose centrally
- each PBB project would otherwise build its own worker-management approach

For relay specifically, the application can show delivery backlog and failures, but it does not own worker instantiation, restart, scaling, or service supervision.

This is the correct architectural separation, but it leaves a platform gap:

- PBB needs a dedicated system for managing worker processes across applications

## Core Idea

`PBB Maestro` should be a separate platform project that manages the lifecycle and telemetry of background worker processes used by PBB systems.

At minimum, Maestro should be able to:

- register worker processes
- track worker heartbeat and health
- detect stale or missing workers
- show queue and worker activity in an operator UI
- coordinate restart and scale policies through environment-appropriate adapters

The relay and other PBB applications should expose telemetry and integrate with Maestro, but should not be responsible for full worker orchestration themselves.

## Scope

### In Scope

- worker registration and identity
- worker heartbeat monitoring
- worker lifecycle state tracking
- worker activity/event logging
- queue-to-worker visibility
- cross-application worker dashboards
- operator-facing monitoring and diagnostics
- integration with host/platform process managers

### Out of Scope

- replacing application queue logic
- replacing Laravel, queue backends, or app-specific job processing
- embedding all orchestration logic inside every application
- becoming a full infrastructure monitoring suite
- replacing external CPU/RAM/disk/network observability tools

## Why A Separate Project Is Needed

Worker management belongs to the runtime/platform layer, not inside a single business application.

That means:

- `PBB - Hub Relay Server` should expose worker-related telemetry and worker-facing integration hooks
- `PBB Maestro` should own worker supervision, orchestration, and cross-project visibility

This separation is important because:

- worker management is reusable across PBB applications
- it avoids duplicating process-control logic across projects
- it keeps applications focused on their business domain
- it supports multiple deployment environments more cleanly

## Target Use Cases

### 1. Relay Worker Monitoring

Monitor:

- relay delivery workers
- local handler dispatch workers
- retry/backoff processing activity

### 2. Multi-Project Background Process Monitoring

Track workers for:

- relay
- data ingestion services
- scheduled report generators
- ETL or sync jobs
- alerting jobs

### 3. Worker Lifecycle Control

Allow controlled actions such as:

- restart worker
- scale worker count
- mark stale worker
- recommend remediation steps

These should be introduced gradually and safely.

## Design Goals

- externalize worker management from individual PBB apps
- support multiple environments and deployment styles
- provide a unified operational UI
- standardize worker identity, heartbeat, and status tracking
- support multiple applications, not only relay
- allow progressive adoption starting with monitoring
- avoid assuming a single OS or process manager

## Non-Goals

- directly embedding process spawning into every application
- coupling worker management to one deployment stack
- tying the project only to relay
- requiring Redis or Horizon as the only possible backend model

## Recommended Architecture

`PBB Maestro` should be treated as a platform service with three layers:

### 1. Control Plane

Responsible for:

- worker definitions
- desired instance counts
- health policies
- restart policies
- scale recommendations

### 2. Telemetry Plane

Responsible for:

- worker registration
- heartbeat ingestion
- worker event logs
- worker status derivation
- queue-to-worker metrics

### 3. Operator UI

Responsible for:

- worker grids
- worker event logs
- health dashboards
- stale/hung detection views
- queue backlog visibility
- remediation actions

## Suggested Product Responsibilities

`PBB Maestro` should eventually own:

- worker inventory
- worker status and history
- worker-event timeline
- app-to-worker mapping
- queue assignment visibility
- stale/hung detection
- orchestration adapters

It should not own:

- application business rules
- application job payload semantics
- per-project domain UIs

## Suggested V1

The first version of Maestro should be monitoring-first.

### V1 Capabilities

- register worker instances
- capture worker heartbeats
- show active, busy, stale, and stopped workers
- show worker events
- show worker-to-queue mapping
- support app registration such as `relay`
- provide operator UI with `ui.grid`
- expose APIs for telemetry ingestion and worker-state reads

### V1 Explicitly Does Not Need

- full auto-scaling
- process spawning on all environments
- kill/restart controls from day one
- deep infrastructure monitoring
- workflow automation beyond basic monitoring

This keeps Maestro safe and achievable for an initial release.

## Suggested V2 Direction

Once V1 monitoring is proven, Maestro can expand into controlled orchestration.

Potential V2 capabilities:

- restart recommendations
- controlled restart actions
- host adapters for supported environments
- desired worker count configuration
- scaling actions
- deployment-aware worker grouping

## V1 Boundary Definition

To keep the project focused, `PBB Maestro V1` should be treated as a monitoring platform, not a worker-control platform.

### V1 Must Do

- accept worker telemetry from integrated applications
- store worker and worker-event records
- derive worker health and freshness state
- expose worker visibility in the operator UI
- show application-to-worker ownership clearly
- help operators understand worker-related causes of queue backlog or failures

### V1 Must Not Do

- spawn new worker processes
- stop worker processes
- kill hung worker processes
- restart workers directly
- scale worker counts automatically
- enforce desired worker counts in the runtime environment
- integrate deeply with OS-specific service managers

### V1 Decision Rule

If a feature requires Maestro to directly control an operating-system process or container runtime, it is not a `V1` feature.

That work belongs to a later orchestration phase.

## V2 Boundary Definition

`PBB Maestro V2` may begin controlled orchestration through explicit environment adapters.

### V2 May Include

- environment adapters
- desired worker-count management
- restart workflows
- controlled stop/start actions
- stale-worker remediation actions
- platform-aware scaling logic

### V2 Still Should Not Become

- a replacement for infrastructure monitoring platforms
- a replacement for full container orchestration systems
- a generic shell-command executor without boundaries

## Environment Adapter Boundary

If Maestro evolves into orchestration, environment-specific execution must be isolated behind adapters.

Examples:

- `systemd` adapter
- `Supervisor` adapter
- Docker adapter
- Kubernetes adapter
- Windows service or `NSSM` adapter

This is important because Maestro’s core should remain environment-neutral.

The core should express intent such as:

- `desired workers = 3`
- `restart relay delivery workers`

The adapter should translate that intent into the correct platform-specific action.

That keeps the architecture clean:

- Maestro core = policy, visibility, coordination
- adapter = environment-specific execution
- alerting and escalation rules

## Universal Deployment Model

Maestro should assume that worker processes are actually managed by an external runtime layer.

Examples:

- `systemd`
- `Supervisor`
- `NSSM`
- Windows Service wrappers
- Docker Compose
- Kubernetes
- platform worker services

Maestro should integrate with these realities rather than pretending one universal process manager already exists.

That means:

- Maestro owns observation first
- Maestro may later own orchestration through adapters
- Maestro should not hardcode a single platform-specific control model

## Relay Relationship

For `PBB - Hub Relay Server`, the intended relationship is:

- relay publishes worker-related telemetry
- relay exposes queue and workload state
- Maestro monitors relay workers
- Maestro may later coordinate relay worker lifecycle through environment adapters

Relay remains responsible for:

- queueing jobs
- processing relay business logic
- exposing application-level delivery and handler state

Maestro becomes responsible for:

- monitoring whether the worker layer is healthy
- correlating worker state with queue and backlog behavior
- helping operators understand if background processing infrastructure is the problem

## Suggested Initial Modules

### 1. App Registry

Track applications integrated with Maestro.

Example:

- `relay`
- `sitrep`
- `hq`

### 2. Worker Registry

Track worker identity, app ownership, queue assignment, host, pid, and lifecycle status.

### 3. Worker Events

Append-only event stream for:

- worker started
- heartbeat
- job started
- job completed
- job failed
- worker stale
- worker stopped

### 4. Dashboard

Provide:

- worker counts
- stale worker counts
- recent failures
- queue mapping visibility

### 5. Workers View

Grid of current worker state.

### 6. Events View

Grid of worker lifecycle and job lifecycle events.

## Suggested UI Surface

The operator UI should be grid-first and monitoring-oriented.

Core views:

- `Dashboard`
- `Workers`
- `Worker Events`
- `Applications`
- `Queues`

Potential detail views later:

- worker detail
- application detail
- queue detail

## Authenticated Operator Pages

Once a user is logged in, `PBB Maestro` should expose a clear authenticated operator surface.

Recommended initial pages:

- `Dashboard`
  - high-level worker health, stale counts, recent failures, and application summaries
- `Workers`
  - primary live worker grid
- `Worker Events`
  - append-only lifecycle and job event grid
- `Applications`
  - registered applications and their worker health summary
- `Queues`
  - queue-oriented worker/activity view across applications
- `Profile` or `Session`
  - current operator context, logout entry point, and session-related actions

Recommended near-term detail pages:

- `Worker Detail`
  - one worker instance, current state, last heartbeat, current job, recent events
- `Application Detail`
  - one application, its workers, queue distribution, recent failures
- `Queue Detail`
  - one queue across one or more applications, active workers, stale workers, recent job events

These pages should be treated as the authenticated operating surface after login, even if some detail pages ship after the first dashboard/grid release.

## Security Model

Because Maestro may evolve toward process-control capabilities, its authorization model should be strict from the start.

Recommendations:

- authenticated operator access
- strong role separation
- audit logging for sensitive actions
- no public access to operational internals
- protect host/process metadata appropriately

## Recommended Technology Direction

Maestro should be designed as a platform tool, not a relay extension.

It should support:

- HTTP-based telemetry ingestion
- background event recording
- operator dashboards
- adapters for supported environments later

The exact implementation stack can be chosen by the new team, but the architecture should preserve:

- separation from business apps
- cross-project applicability
- environment-agnostic control concepts

If Maestro includes a browser-based operator UI, it should also preserve:

- helpers-first UI composition
- modal-based operator login
- standardized session-expiry re-login behavior

## Recommended Delivery Strategy

### Phase 1

- define worker telemetry contract
- define app registration contract
- build monitoring-only UI
- integrate first with relay

### Phase 2

- add worker status derivation and alert rules
- add remediation recommendations
- add queue-level and host-level summaries

### Phase 3

- add controlled orchestration adapters
- add safe restart workflows
- add scale recommendations or desired worker counts

## Expected Benefits

`PBB Maestro` would provide:

- one place to see worker health across PBB systems
- safer separation of platform concerns from business apps
- clearer troubleshooting when queues back up
- reusable worker management instead of per-project reinvention
- a future foundation for orchestration and process control

## Recommendation

Create `PBB Maestro` as a separate platform project focused on worker orchestration and monitoring across PBB applications.

Its first release should be monitoring-first, with relay as the first integration target.

That gives the PBB platform a clean architecture:

- applications such as relay focus on business processing
- Maestro focuses on background worker visibility and lifecycle coordination
