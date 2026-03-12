# Hub Relay Shared Package Proposal

## Purpose

Define how `Hub Relay` should be managed so that:

- the relay is implemented once
- all hub deployments consume the same core code
- fixes and improvements can be rolled out consistently
- deployment-specific behavior can still vary above the shared relay layer

This proposal exists to avoid a fragile model where each hub deployment carries its own manually copied version of relay logic.

## Recommendation

`Hub Relay` should be built and maintained as a shared, versioned package, not as duplicated project code.

Recommended approach:

- one dedicated `Hub Relay` repository
- one versioned release line
- one install/update mechanism for all hubs
- extension points for deployment-specific behavior

## Why This Is Necessary

If each hub deployment maintains its own copy of the relay:

- fixes drift across deployments
- protocol behavior becomes inconsistent
- bugs get patched multiple times
- upgrades become manual and error-prone
- compatibility between hubs becomes harder to guarantee

If the relay is a shared package:

- one fix can be released once
- all hubs can update to the same version
- compatibility rules become explicit
- testing and rollout become manageable

## Recommended Repository Structure

Create a dedicated repository, for example:

- `pbb-hub-relay`

This repository should contain:

- relay domain models
- queue and delivery logic
- retry logic
- transport clients
- inbound receiver logic
- attachment transfer logic
- monitoring and diagnostics primitives
- configuration
- migrations
- tests
- protocol/version docs

Optional split later:

- backend package: `pbb-hub-relay`
- frontend admin package: `pbb-hub-relay-ui`

For phase 1, a backend-first shared package is enough.

## Recommended Packaging Model

Because your hubs are Laravel/PHP-based, the strongest recommendation is:

- private Composer package

That gives you:

- standard dependency installation
- version pinning
- clean upgrades
- compatibility with Laravel service providers and migrations

Optional frontend assets can stay:

- inside each hub app initially
- or later move to a separate shared UI package if the relay monitoring UI also becomes common

## What Belongs In The Shared Package

The shared package should own:

- relay message envelope model
- outbound delivery queue model
- inbound receipt model
- attachment transfer model
- delivery worker logic
- retry/backoff policy engine
- authentication/signing helpers
- HTTP sender/receiver transport layer
- chunked upload support
- relay monitoring data contracts
- common diagnostics endpoints or services
- queue, delivery, and upload status aggregation
- relay package and protocol version reporting
- protocol versioning
- shared migrations
- admin/service APIs if common across all hubs

## What Should Stay Outside The Shared Package

Hub-specific apps should still own:

- local business modules
- SITREP-specific business screens
- role-specific dashboards
- permissions and UI presentation
- deployment-specific payload handlers if behavior differs

What this means for monitoring:

- the shared package should own the common monitoring model and data access
- individual hub apps may still build different dashboards and views on top of that shared data

This keeps the relay generic and shared while letting each hub stay role-aware.

## Recommended Extension Model

The package should expose extension points instead of encouraging forks.

Recommended contracts:

- `RelayPayloadHandlerInterface`
- `RelayTargetResolverInterface`
- `RelayAuthorizationInterface`
- `RelayStorageAdapterInterface`
- `RelayPresentationAdapterInterface` if UI later becomes shared

This allows:

- city hub behavior
- provincial hub behavior
- foundation hub behavior

without changing the shared relay core.

## Versioning Strategy

Use semantic versioning.

Examples:

- `1.0.0`
- `1.0.1`
- `1.1.0`

Recommended meaning:

- patch version:
  - bug fixes
  - no breaking API/protocol change
- minor version:
  - backward-compatible feature additions
- major version:
  - breaking change in package contract or relay protocol behavior

## Protocol Versioning

In addition to package versioning, define a relay protocol version.

Recommended fields:

- `relay_package_version`
- `relay_protocol_version`
- `minimum_supported_protocol_version`

Why:

- package version tells you what code a hub runs
- protocol version tells you whether hubs can talk to each other safely

Two hubs may run different package versions and still be compatible if protocol compatibility is preserved.

## Recommended Release Flow

1. develop changes in the shared package repo
2. run unit/integration tests there
3. tag a versioned release
4. update one staging hub to that version
5. validate relay compatibility
6. roll out to production hubs in a controlled order

This is much safer than editing each hub copy independently.

## Recommended Deployment Visibility

Each hub should expose:

- installed relay package version
- relay protocol version
- last upgrade date
- relay diagnostics and monitoring status

HQ should eventually be able to monitor:

- which hubs are running outdated relay versions
- which hubs are on incompatible protocol versions
- which hubs have unhealthy relay queues or repeated delivery failures
- which hubs have stalled uploads or persistent dead-letter entries

## Step-by-Step Implementation Plan

## Phase 1: Create The Shared Package

### Step 1. Create the dedicated repository

Create a new repository:

- `pbb-hub-relay`

Initial structure suggestion:

- `src/`
- `config/`
- `database/migrations/`
- `routes/`
- `tests/`
- `docs/`
- `composer.json`
- `README.md`

### Step 2. Define package boundaries

Before moving code, define what the package owns:

- relay models
- transport services
- queue/delivery logic
- retry rules
- protocol/version helpers

And what it does not own:

- deployment-specific UI
- role-specific business dashboards

### Step 3. Create the Composer package

Set up:

- package name, for example:
  - `pbb/hub-relay`
- PSR-4 autoloading
- Laravel service provider
- publishable config
- package migrations

### Step 4. Move relay core into the package

Move only the common relay code first:

- message models
- delivery models
- receipt models
- sender
- receiver
- queue/retry orchestration
- monitoring status services
- common monitoring endpoints or query services

Do not move project-specific UI or local business flows yet.

### Step 5. Add package configuration

Create config for:

- enabled transport
- authentication mode
- retry policy
- chunk size
- protocol version
- monitoring retention and diagnostics behavior

### Step 6. Add extension contracts

Define interfaces/hooks for:

- payload handlers
- authorization
- target resolution
- attachment storage

This prevents teams from editing package internals for deployment-specific behavior.

## Phase 2: Prepare Existing Hub Apps To Consume It

### Step 7. Add the private package source to each hub app

Configure Composer to install the private package from your Git source or private package registry.

Possible approaches:

- private VCS repository entry
- Satis/Packagist Private
- internal Composer registry

### Step 8. Install the package in one hub app first

Start with one controlled deployment:

- staging HQ hub
- or one non-critical staging node

Install:

- `composer require pbb/hub-relay`

### Step 9. Publish config and run migrations

For the first consuming app:

- publish package config if needed
- run package migrations
- verify tables and workers are present

### Step 10. Bind local handlers

In the consuming app, register:

- payload handlers
- any deployment-specific behavior
- local policies or presentation adapters

This is where city/province/foundation differences should live.

## Phase 3: Replace Duplicated Relay Logic

### Step 11. Remove copied relay code from the app

As each app adopts the package:

- remove local duplicated relay classes
- remove local tables only if replaced by package migrations
- keep only app-level integration code

### Step 12. Add version reporting

Expose package/protocol info in the app:

- relay package version
- relay protocol version

This can be shown in:

- an admin status page
- a hub diagnostics endpoint

### Step 13. Test hub-to-hub compatibility

Test:

- sender on older package to receiver on newer package
- sender on newer package to receiver on older package
- mismatched but supported protocol versions
- rejected incompatible versions

### Step 14. Roll out gradually

Suggested rollout order:

1. staging hub
2. HQ hub
3. regional/provincial hubs
4. city hubs
5. barangay hubs

Use controlled waves so protocol regressions are isolated quickly.

## Phase 4: Standardize Upgrade Process

### Step 15. Document the upgrade workflow

Define exactly how teams should update:

1. pull latest app code
2. update Composer dependency
3. run migrations
4. restart workers/services
5. verify diagnostics and relay status

### Step 16. Add automated tests in the package

At minimum:

- envelope validation tests
- idempotency tests
- delivery retry tests
- attachment transfer tests
- chunk assembly tests
- protocol compatibility tests
- monitoring data aggregation tests
- diagnostics endpoint tests

### Step 17. Add release notes discipline

Every package release should include:

- version number
- protocol version impact
- migration requirements
- breaking changes
- rollout notes

## Recommended Operational Rules

- never patch relay core independently in one hub app unless it is an emergency hotfix
- any relay-core fix should go back to the shared package first
- if a hub app needs different behavior, use extension points, not direct modifications

## Suggested Future Enhancements

Once the package is stable:

- add package-level admin monitoring endpoints
- add built-in health diagnostics
- add protocol compatibility endpoint
- add relay worker dashboard primitives

## Shared Monitoring Recommendation

Relay monitoring should be treated the same way as relay transport:

- implemented once in the shared package
- reused across all hub deployments
- versioned and tested centrally

Recommended shared monitoring responsibilities in the package:

- relay status summaries
- outbox, inbox, delivery, and upload query services
- health and diagnostics endpoints
- version and protocol reporting
- dead-letter and retry-state visibility

Recommended app-level responsibilities:

- role-specific dashboards
- operator workflows
- local page layout and permissions

This keeps monitoring behavior consistent across all hubs while still allowing each deployment to present the data in a way that fits its role.

## Recommendation

Manage `Hub Relay` as a dedicated shared Composer package with strict versioning, protocol compatibility rules, and extension points for deployment-specific behavior.

Do not manage it as duplicated copied code across hubs.

That is the only sustainable way to ensure that improvements, fixes, and protocol changes can be applied consistently across barangay, city, provincial, regional, foundation, and HQ deployments.
