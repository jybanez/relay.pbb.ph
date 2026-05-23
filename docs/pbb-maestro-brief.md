# PBB Maestro Brief

## What It Is

`PBB Maestro` is a proposed new PBB platform project for monitoring and eventually orchestrating background worker processes across PBB applications.

Its first integration target is:

- `PBB - Hub Relay Server`

## Why It Is Needed

PBB applications increasingly depend on background workers for:

- queue processing
- delivery jobs
- handler dispatches
- scheduled or asynchronous tasks

Today, applications such as relay can show queue outcomes, but they do not own worker lifecycle management.

That means operators can often see the symptoms:

- queued jobs
- failed deliveries
- dead letters

But not the actual process state:

- how many workers are running
- whether they are alive
- whether they are stale
- which queues they are consuming
- which jobs they are handling

`PBB Maestro` is intended to fill that gap.

## What V1 Should Do

`PBB Maestro V1` should be monitoring-first.

It should:

- register worker instances
- ingest worker heartbeats
- ingest worker events
- show worker status in an operator UI
- detect stale workers
- show queue-to-worker visibility

It should not yet:

- spawn workers
- kill workers
- auto-scale workers
- replace platform process managers

Simple boundary:

- `V1` shows worker state
- `V1` does not control worker processes
- process control belongs to a later adapter-based phase

## Shared UI And Session Standards

Maestro should follow the same shared PBB browser-app standards used elsewhere:

- UI library: `helpers.pbb.ph`
- official repository: `https://github.com/jybanez/helpers.pbb.ph.git`
- session handling reference: `pbb-user-session-handling-proposal.md`
- login/logout flow reference: `login-logout-flow-reference.md`
- login form reference: `login-form-reference.md`

Practical meaning:

- helpers-first operator UI
- modal/dialog-based interactions where appropriate
- standardized browser session handling
- shared login / logout / current-user API flow

If Maestro lives in its own repository, those reference docs should be copied or restated there instead of relying on relay-repo filesystem links.

## Offline-Ready Assumption

There is a strong possibility that Maestro will be deployed without reliable internet access.

Because of that:

- required libraries and assets should be vendored or stored locally
- Maestro should not rely on public CDNs for required runtime behavior
- `helpers.pbb.ph` and similar dependencies should be available locally
- any third-party runtime dependency needed in production should be installable and usable without depending on live internet during normal operation

## Suggested Baseline Stack

To reduce unnecessary divergence, the new team should strongly consider starting Maestro with the same baseline stack currently used in relay.

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

This does not mean Maestro must be permanently locked to this stack forever, but it gives the team a known-good starting point that aligns with the current PBB relay environment.

## What Operators Should Be Able To See

At minimum:

- current workers
- application ownership
- queue assignments
- worker status
- last heartbeat
- current or last job
- processed and failed counts
- recent worker events

And they should access that UI through the same PBB session-authenticated browser pattern used by other internal tools.

## What The Relay’s Role Is

`PBB - Hub Relay Server` should:

- keep doing relay business processing
- emit worker telemetry to Maestro
- expose queue and delivery context

It should not become the system that manages worker lifecycle.

## What Maestro’s Role Is

`PBB Maestro` should:

- own worker visibility
- track worker health
- detect stale or missing workers
- provide a central monitoring UI
- later evolve toward controlled orchestration

## Suggested V1 Modules

- Applications
- Workers
- Worker Events
- Dashboard

## Pages After Login

Once an operator is logged in, the expected Maestro pages should be:

- `Dashboard`
- `Workers`
- `Worker Events`
- `Applications`
- `Queues`

And at minimum there should also be:

- a clear current-user/session surface
- a logout path

## Recommended Build Strategy

Phase 1:

- define telemetry contract
- create worker and event storage
- build monitoring UI

Phase 2:

- integrate relay workers
- validate stale detection and worker visibility

Phase 3:

- expand to more PBB applications

## Suggested Positioning

`PBB Maestro` should be treated as a platform service, not a relay feature.

That gives PBB a cleaner architecture:

- business apps focus on domain behavior
- Maestro focuses on worker visibility and orchestration concerns

## Companion Documents

For fuller detail, see:

- `pbb-maestro-project-proposal.md`
- `pbb-maestro-v1-implementation-proposal.md`
