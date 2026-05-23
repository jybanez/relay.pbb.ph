# PBB - Hub Relay Server

`PBB - Hub Relay Server` is the canonical implementation workspace for Project Bantay Bayan's shared relay service.

This is a **local shared service** — not embedded relay code in every application. Local systems integrate through **APIs**; hub-to-hub communication happens **server-to-server**.

## Quick Links

- **Architecture:** [docs/hub-relay-server-proposal.md](docs/hub-relay-server-proposal.md)
- **Phase 1 Implementation:** [PHASE1_IMPLEMENTATION.md](PHASE1_IMPLEMENTATION.md)
- **Phase 1 SITREP:** [PHASE1_SITREP.md](PHASE1_SITREP.md)
- **Public Status Page:** `/`
- **Swagger UI:** `/relay/api/docs`
- **OpenAPI Spec:** [public/relay-ui/openapi.json](public/relay-ui/openapi.json)
- **API Manual:** [docs/hub-relay-api-manual.md](docs/hub-relay-api-manual.md)
- **API Reference:** [docs/hub-relay-api-reference.md](docs/hub-relay-api-reference.md)
- **Client/Handler Testing Proposal:** [docs/hub-relay-client-handler-testing-proposal.md](docs/hub-relay-client-handler-testing-proposal.md)
- **Client/Handler Stage 1 Checklist:** [docs/hub-relay-client-handler-stage1-checklist.md](docs/hub-relay-client-handler-stage1-checklist.md)
- **Client/Handler Stage 2 Checklist:** [docs/hub-relay-client-handler-stage2-checklist.md](docs/hub-relay-client-handler-stage2-checklist.md)
- **Two-Node Dummy Client Test Plan:** [docs/hub-relay-two-node-dummy-client-test-plan.md](docs/hub-relay-two-node-dummy-client-test-plan.md)
- **Two-Node Dummy Client Live Checklist:** [docs/hub-relay-two-node-dummy-client-live-checklist.md](docs/hub-relay-two-node-dummy-client-live-checklist.md)
- **Hop Forwarding Refactor Checklist:** [docs/hub-relay-hop-forwarding-refactor-checklist.md](docs/hub-relay-hop-forwarding-refactor-checklist.md)
- **HQ Registry Integration:** [docs/hub-relay-hq-registry-integration-proposal.md](docs/hub-relay-hq-registry-integration-proposal.md)
- **Browser Installer Proposal:** [docs/hub-relay-browser-installer-proposal.md](docs/hub-relay-browser-installer-proposal.md)
- **Installer Bootstrap Spec:** [docs/hub-relay-installer-bootstrap-package-spec.md](docs/hub-relay-installer-bootstrap-package-spec.md)
- **Installer Implementation Plan:** [docs/hub-relay-browser-installer-implementation-plan.md](docs/hub-relay-browser-installer-implementation-plan.md)
- **Standalone Installer Runtime:** [docs/hub-relay-standalone-installer-runtime-proposal.md](docs/hub-relay-standalone-installer-runtime-proposal.md)
- **API Endpoints:** See [routes/api.php](routes/api.php)
- **Vendored UI Library:** [public/vendor/helpers.pbb.ph/VENDORED.md](public/vendor/helpers.pbb.ph/VENDORED.md)
- **Future Worker Monitoring Platform:** [docs/pbb-maestro-project-proposal.md](docs/pbb-maestro-project-proposal.md)

## What This Is

PBB - Hub Relay Server is a Laravel application that:

- **Accepts messages** from local applications via API
- **Queues deliveries** to upstream hubs
- **Receives messages** from downstream hubs with idempotent handling
- **Tracks delivery state** separately per next-hop relay
- **Tracks outbound ownership** separately per authenticated local client
- **Provides diagnostics** for monitoring hub network health
- **Shared infrastructure** that works with any local application

It is not intended to become the long-term home of worker-process monitoring or worker lifecycle management. That concern is split toward `PBB Maestro` and Kit Setup: Relay exposes app-level queue and delivery state, emits worker telemetry when configured, and declares the required worker process contract; Kit owns service registration, start/restart, and reboot persistence in bundled installs.

## Current Status

**Phase 1: ✅ COMPLETE**
**Phase 2: ✅ COMPLETE (baseline)**

All phase-1 deliverables are ready:
- ✅ Runnable Laravel service
- ✅ Initial migrations and models
- ✅ API route definitions
- ✅ Envelope contract
- ✅ Local client authentication setup
- ✅ JSON message submission and receipt
- ✅ Idempotent inbound handling
- ✅ Diagnostics and monitoring

Phase 2 work already implemented:
- ✅ Outbound delivery worker job dispatch
- ✅ Retry scheduling with exponential backoff
- ✅ Config-driven relay target delivery
- ✅ Shared-key hub-to-hub authentication middleware
- ✅ Optional HMAC signing and verification for hub-to-hub requests
- ✅ Certificate-bound transport auth modes (`mtls`, `mtls_hmac`) with outbound client certificate support
- ✅ Attachment upload sessions and chunked upload flow
- ✅ Local inbox API and handler/webhook registration
- ✅ Handler dispatch tracking and manual retry controls
- ✅ Public shared-service home page at `/`
- ✅ Initial monitoring/admin dashboard at `/relay`
- ✅ Admin operations screens for outbox, inbox, deliveries, uploads, dead letters, clients, and users
- ✅ API token management section and lifecycle controls
- ✅ User management and operator login baseline
- ✅ API integration manual and route reference documentation
- ✅ Swagger/OpenAPI documentation baseline
- ✅ Shared shell/session baseline endpoints (`/api/bootstrap`, `/api/csrf-token`, `/api/session/ping`)
- ✅ Protocol compatibility support for `1.0` and `1.1`, including capability headers
- ✅ SDK packaging and release baseline under `sdk/`
- ✅ Operator retry/cancel workflows in the admin detail screens
- ✅ Full-screen admin layouts with container scroll and virtualized record lists
- ✅ Kit Setup installer bundle contract for unattended installs
- ✅ Required Relay worker service declaration for `pbb-relay-worker`
- ✅ Data Prep apply/verify tools for Maestro telemetry settings
- ✅ Maestro telemetry CA bundle handoff for Windows/S5 TLS trust

See [PHASE1_SITREP.md](PHASE1_SITREP.md) for full details.

## Phase 2 Tracker

### Completed

- ✅ Outbound delivery workers
- ✅ Retry scheduling and backoff policy
- ✅ Config-driven relay target delivery
- ✅ Shared-key hub authentication
- ✅ HMAC hub request signing and verification
- ✅ Certificate-bound transport auth (`mtls`, `mtls_hmac`)
- ✅ Attachment upload sessions
- ✅ Chunked upload flow
- ✅ Local inbox API
- ✅ Local handler/webhook registration
- ✅ Handler dispatch tracking and retry controls
- ✅ Dashboard and admin operations screens
- ✅ API token management section and lifecycle controls
- ✅ User management and operator login baseline
- ✅ API integration manual and route reference documentation
- ✅ Swagger/OpenAPI documentation baseline
- ✅ Shared shell/session baseline endpoints (`/api/bootstrap`, `/api/csrf-token`, `/api/session/ping`)
- ✅ Protocol compatibility support for `1.0` and `1.1`
- ✅ SDK packaging and release baseline
- ✅ Admin/operator workflow baseline
- ✅ UI hardening baseline for full-screen container-based operations

### Remaining After Phase 2 Baseline

- [ ] UI polish and additional workflow depth from real operator testing
- [ ] Granular user permissions and audit trails
- [ ] Automated SDK publish/release pipeline
- [ ] Scheduled HQ registry sync and admin visibility
- [ ] Continue Maestro integration hardening from S5/production retests

### UI Requirements For Remaining Admin Work

- Maximize use of available screen space on desktop and large displays
- Prefer fixed page shells with independently scrollable panels/containers instead of full-page scrolling
- Prefer infinite scrolling with virtual rendering for large tables and lists instead of pagination
- Keep helpers.pbb.ph as the primary UI/UX layer, with app code focused on relay behavior and data

### Functional Gaps To Close

- **API token management:** Baseline client/token management now exists at `/relay/clients`, including create, rotate, activate, and deactivate flows. Audit history and multi-token-per-client workflows are still not implemented.
- **API manual and references:** Baseline written docs exist in `docs/hub-relay-api-manual.md` and `docs/hub-relay-api-reference.md`, and Swagger/OpenAPI is now available publicly at `/relay/api/docs` backed by `public/relay-ui/openapi.json`. Deeper third-party onboarding examples are still not implemented.
- **Shell/session baseline:** Relay now exposes `/api/bootstrap`, `/api/csrf-token`, and authenticated `/api/session/ping`, and the browser session client uses the bootstrap + near-expiry keepalive path in addition to re-auth fallback.
- **User management:** Baseline relay operator login and user management now exists through the public home login modal plus `/relay/users`, with `admin` and `operator` roles plus active/inactive status. Granular permissions, audit history, and password-reset workflows are still not implemented.
- **Operator workflows:** Delivery retry/cancel and handler-dispatch retry are now exposed in the admin detail screens. Bulk operations and deeper dead-letter tooling are still future work.
- **Worker monitoring:** Relay intentionally stops short of full worker-process monitoring. Worker lifecycle visibility belongs to `PBB Maestro`; service lifecycle belongs to Kit Setup or an equivalent runtime manager. Relay retains queue, delivery, and handler diagnostics plus outbound Maestro worker telemetry when configured.

## Maestro Integration Readiness

Relay includes a disabled-by-default Maestro telemetry client. Bundled Kit installs enable it during Data Prep after Kit receives the Relay telemetry token from Maestro.

What relay owns:

- queue and business-processing diagnostics
- delivery, inbox, upload, and handler state
- stable queue naming
- worker identity generation and queue lifecycle telemetry hooks
- Data Prep apply/verify tools that persist and report Maestro telemetry settings

What Maestro will own:

- worker visibility
- worker heartbeat monitoring
- stale-worker detection
- cross-application worker dashboards
- later orchestration through environment adapters

What Kit Setup or another runtime manager owns:

- registering `pbb-relay-worker`
- starting the worker only after `.env`, database setup, and admin provisioning are complete
- restarting the worker after Relay `.env` changes, especially telemetry token or CA bundle changes
- keeping the worker running after machine reboot
- collecting stdout/stderr or equivalent service logs

Current relay behavior:

- Maestro telemetry is off by default
- queue lifecycle hooks are registered only for worker-style commands
- telemetry uses a no-op implementation unless Maestro config is enabled and has a base URL, app code, and token
- TLS verification remains enabled by default; Kit can pass a trusted CA bundle path for Windows/S5 installs

## Maestro Telemetry Config

Relay exposes the following integration keys in `.env` / `.env.example`:

```dotenv
RELAY_MAESTRO_ENABLED=false
RELAY_MAESTRO_APP_CODE=relay
RELAY_MAESTRO_BASE_URL=
RELAY_MAESTRO_TELEMETRY_TOKEN=
RELAY_MAESTRO_TLS_VERIFY=true
RELAY_MAESTRO_CA_BUNDLE=
RELAY_MAESTRO_HEARTBEAT_INTERVAL_SECONDS=15
RELAY_MAESTRO_CONNECT_TIMEOUT_SECONDS=3
RELAY_MAESTRO_TIMEOUT_SECONDS=5
RELAY_MAESTRO_HEARTBEAT_PATH=/api/v1/telemetry/workers/heartbeat
RELAY_MAESTRO_EVENTS_PATH=/api/v1/telemetry/worker-events
```

The intended event surface is:

- `worker.started`
- `job.started`
- `job.completed`
- `job.failed`
- heartbeat payload updates for the current worker instance

This seam is intentionally conservative:

- no relay-side worker management
- no relay-side scaling logic
- no relay-side worker monitoring UI expansion
- only outbound worker telemetry reporting

## HQ Registry Baseline

Relay now includes a baseline `PBB HQ` registry integration for node identity and peer discovery.

Current implementation:

- HQ hub data can be synced locally with `php artisan relay:hq-sync`
- cached HQ hub records are stored in `hub_registry_hubs`
- cached HQ topology links are stored in `hub_registry_links`
- local sync/runtime settings are stored in `relay_node_settings`
- outbound target resolution can use HQ `uplinks`
- inbound hub validation can recognize HQ-known peers

Canonical identity rule:

- `relay_hub_id` is the Relay-facing stable node identity
- HQ numeric `id` is retained only as `hq_id` reference data
- local credential and transport overrides can still be provided outside HQ

Current boundary:

- manual `RELAY_TARGETS` and `RELAY_HUBS` still work
- HQ sync is implemented, but scheduled sync and admin sync visibility are still pending
- HQ does not currently distribute raw relay transport secrets, so credentials remain locally provisioned

## Browser Installer Direction

Relay is moving toward a browser-based first-install experience for fresh deployments instead of a long-lived CLI installer.

Current implementation status:

- installer mode can take over `/` and `/install` when enabled and not yet locked as installed
- current runtime now covers:
  - bootstrap routing and installer-mode gating
  - environment checks
  - HQ Hub ID + token validation
  - installer state storage
  - install-settings capture
  - install execution for the current app runtime:
    - `.env` writing
    - target DB verification
    - migrations
    - admin password generation
    - install lock finalization
    - optional release package extraction
    - cleanup manifest generation
    - manual or automatic installer cleanup execution
  - Helper-based dark installer shell
  - deployable installer package build output via `php artisan relay:installer:build`
  - split builder behavior with a cached embedded release artifact so installer UI/runtime changes can rebuild without repackaging the full Relay release
  - package exclusions for installer/runtime and release payloads, keeping `docs`, `test*`, `.git*`, environment examples, PHPUnit cache, and other installer-only/source-only artifacts out of the installed runtime
  - production pruning that keeps `composer.json` available because Laravel queue workers still need Composer package metadata at runtime
  - package-builder refactor toward a standalone PHP `installer-runtime/` plus full Laravel `relay-release.zip`
  - Kit-compatible unattended installer entry point at `installer/install-run.php`
  - compact Kit bundle shape with top-level `index.php`, `installer.zip`, `release.json`, baseline schema, and installer contract files
  - service artifact generation for the required `pbb-relay-worker`
  - Data Prep apply/verify tools for Maestro telemetry settings
  - documented next-step direction for a blocking execution modal with persisted per-step progress state
- still pending:
  - production handoff testing of the generated root bootstrap `index.php` + `installer.zip` pair on a truly empty webroot
  - actual self-removal sequencing for the production bootstrap package after successful install

See:

- [docs/hub-relay-browser-installer-proposal.md](docs/hub-relay-browser-installer-proposal.md)
- [docs/hub-relay-installer-bootstrap-package-spec.md](docs/hub-relay-installer-bootstrap-package-spec.md)
- [docs/hub-relay-browser-installer-implementation-plan.md](docs/hub-relay-browser-installer-implementation-plan.md)
- [docs/hub-relay-standalone-installer-runtime-proposal.md](docs/hub-relay-standalone-installer-runtime-proposal.md)

Maintainer build commands:

```bash
# Default: rebuild runtime and reuse the cached release artifact when the release fingerprint is unchanged
C:/wamp64/bin/php/php8.2.29/php.exe artisan relay:installer:build

# Runtime/UI-only rebuild using the cached release artifact
C:/wamp64/bin/php/php8.2.29/php.exe artisan relay:installer:build --runtime-only

# Force a full embedded release rebuild
C:/wamp64/bin/php/php8.2.29/php.exe artisan relay:installer:build --force-release
```

## Kit Setup Bundle Contract

The official Relay bundle for Kit Setup is built from the installer output and registered in Kit's bundled package manifest:

```text
C:\wamp64\www\pbb\kit-setup\packages\bundled\pbb-relay-1.1.0.zip
```

Current bundle SHA256 after the Maestro CA bundle fix:

```text
483bea49f7f856230ad5fe00e7378e79306cfded29a34d9a50daddcf0dfb3951
```

Relay's `release.json` declares the required background process:

```text
pbb-relay-worker
PBB Relay Worker
php artisan queue:work --queue=relay-deliveries,relay-handlers
```

The worker is required. Without it, Relay APIs can accept work, but outbound hub deliveries and local handler callbacks remain queued. Relay generates service artifacts and install reports; Kit registers the persistent service, starts it after install/Data Prep are complete, restarts it after `.env` changes, and verifies it during smoke checks.

Data Prep settings precedence is intentionally specific-last:

1. generic `maestro`
2. `dependencies.maestro`
3. `data_prep.apply_settings.maestro`
4. `relay.data_prep.maestro`
5. `relay.data_prep.apply_settings.maestro`

That order prevents stale generic dependency values from overriding Relay-specific Maestro settings. Relay also forces the default app code back to `relay` unless a Relay-specific override is supplied.

For Maestro telemetry TLS trust, Kit may pass any of these keys:

```json
{
  "relay": {
    "data_prep": {
      "apply_settings": {
        "maestro": {
          "base_url": "https://maestro.pbb.ph",
          "app_code": "relay",
          "telemetry_token": "<relay telemetry token>",
          "tls_verify": true,
          "ca_bundle": "C:\\wamp64\\www\\pbb\\kit-setup\\assets\\certs\\cacert.pem",
          "curl_ca_bundle": "C:\\wamp64\\www\\pbb\\kit-setup\\assets\\certs\\cacert.pem"
        }
      }
    }
  }
}
```

`tools/data-prep/apply-settings.php` writes `RELAY_MAESTRO_CA_BUNDLE` and clears `bootstrap/cache/config.php` so a restarted worker reads the new values. `tools/data-prep/verify.php` reports `verify_tls`, `ca_bundle_configured`, and `ca_bundle_exists`.

## Proposal Reading Order

If you're new, read the proposals in this order:

1. [docs/hub-relay-server-proposal.md](docs/hub-relay-server-proposal.md) — Target architecture
2. [docs/hub-relay-proposal.md](docs/hub-relay-proposal.md) — System behavior
3. [docs/hub-relay-shared-package-proposal.md](docs/hub-relay-shared-package-proposal.md) — Distribution strategy
4. [docs/hub-relay-repo-structure-proposal.md](docs/hub-relay-repo-structure-proposal.md) — Repository layout
5. [docs/hub-relay-proposals-readme.md](docs/hub-relay-proposals-readme.md) — Guide to the proposals

## Quick Start

Production URL: `https://relay.pbb.ph`

### Required PHP

Use this PHP binary for all project commands on this workstation:

```bash
C:/wamp64/bin/php/php8.2.29/php.exe
```

Examples below use that exact executable so setup is unambiguous.

## Vendored UI Library

This project vendors the official shared UI/UX library from `https://github.com/jybanez/helpers.pbb.ph`.

- Upstream snapshot: `C:\wamp64\www\hotline-helpers`
- Upstream package version: `0.21.83`
- Local path: `public/vendor/helpers.pbb.ph`
- Vendor manifest: [public/vendor/helpers.pbb.ph/VENDORED.md](public/vendor/helpers.pbb.ph/VENDORED.md)

Included vendored source:

- `public/vendor/helpers.pbb.ph/js/ui/*`
- `public/vendor/helpers.pbb.ph/css/ui/*`
- `public/vendor/helpers.pbb.ph/js/vendor/*`
- `public/vendor/helpers.pbb.ph/dist/*`
- `public/vendor/helpers.pbb.ph/boot.*.json`

Included upstream references:

- [public/vendor/helpers.pbb.ph/README.upstream.md](public/vendor/helpers.pbb.ph/README.upstream.md)
- [public/vendor/helpers.pbb.ph/CHANGELOG.upstream.md](public/vendor/helpers.pbb.ph/CHANGELOG.upstream.md)
- [public/vendor/helpers.pbb.ph/docs/pbb-refactor-playbook.md](public/vendor/helpers.pbb.ph/docs/pbb-refactor-playbook.md)
- [public/vendor/helpers.pbb.ph/docs/helpers-schema-form-modal-counter-proposal.md](public/vendor/helpers.pbb.ph/docs/helpers-schema-form-modal-counter-proposal.md)

Preferred integration pattern:

```js
import { uiLoader } from "../vendor/helpers.pbb.ph/js/ui/ui.loader.js";

await uiLoader.load("ui.modal");
const createModal = await uiLoader.get("ui.modal");
```

The vendored folder keeps the upstream `js/ui` and `css/ui` relative layout intact so `uiLoader` can resolve and load component styles without local path rewrites. The installer package trims this local copy down to the production runtime paths Relay uses.

To refresh the vendored copy later, refresh from the official Helper source, replace the contents under `public/vendor/helpers.pbb.ph` and `resources/vendor/helpers.pbb.ph`, and update the upstream package version in both `VENDORED.md` and this README.

### Setup

```bash
# Install dependencies
composer install

# Environment
cp .env.example .env
C:/wamp64/bin/php/php8.2.29/php.exe artisan key:generate

# Database (.env should point to MySQL database pbb_relay on localhost)
C:/wamp64/bin/php/php8.2.29/php.exe artisan migrate

# Create initial relay admin user
C:/wamp64/bin/php/php8.2.29/php.exe artisan relay:user:create "Relay Admin" "admin@relay.local" "change-me-now" --role=admin

# Or seed default test users
C:/wamp64/bin/php/php8.2.29/php.exe artisan db:seed

# Run server
C:/wamp64/bin/php/php8.2.29/php.exe artisan serve
```

Seeded relay test users:

```text
admin@relay.local / relay-admin-123
operator@relay.local / relay-operator-123
```

Expected `.env` values:

```dotenv
APP_NAME="PBB - Hub Relay Server"
APP_URL=https://relay.pbb.ph
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=pbb_relay
DB_USERNAME=root
DB_PASSWORD=
RELAY_DELIVERY_MAX_ATTEMPTS=5
RELAY_DELIVERY_BACKOFF_MINUTES=1,5,15,60,360
RELAY_DELIVERY_TIMEOUT_SECONDS=10
RELAY_DELIVERY_QUEUE=relay-deliveries
RELAY_LOCAL_HANDLER_QUEUE=relay-handlers
RELAY_LOCAL_HANDLER_TIMEOUT_SECONDS=10
RELAY_LOCAL_HANDLER_MAX_ATTEMPTS=3
RELAY_LOCAL_HANDLER_BACKOFF_SECONDS=30,120,600
RELAY_HUB_AUTH_MODE=shared_key
RELAY_HUB_AUTH_TIMESTAMP_TOLERANCE_SECONDS=300
RELAY_HUB_AUTH_CLIENT_CERTIFICATE_FINGERPRINT_HEADER=X-Relay-Client-Cert-Fingerprint
RELAY_PROTOCOL_VERSION=1.1
RELAY_MINIMUM_SUPPORTED_PROTOCOL_VERSION=1.0
RELAY_SUPPORTED_PROTOCOL_VERSIONS=1.0,1.1
RELAY_PROTOCOL_CAPABILITIES=chunked_uploads,local_handlers,tracked_handler_dispatches,certificate_bound_auth,admin_operator_auth
RELAY_UPLOAD_DISK=local
RELAY_UPLOAD_CHUNK_SIZE_BYTES=1048576
```

Configure upstream targets in `.env` when outbound delivery is enabled:

```dotenv
RELAY_TARGETS={"city-hub":{"base_url":"https://city.example"}}
```

Configure trusted inbound hubs in `.env` for hub-to-hub receive:

```dotenv
RELAY_HUBS={"city-hub":{"token":"shared-city-key"}}
```

HQ registry bootstrap config for the implemented canonical hub identity/topology baseline:

```dotenv
RELAY_HQ_API_ENABLED=true
RELAY_HQ_API_BASE_URL=https://hub.pbb.ph
RELAY_HQ_API_TOKEN=
RELAY_HQ_LOCAL_RELAY_HUB_ID=
RELAY_HQ_LOCAL_HQ_ID=
RELAY_HQ_SYNC_ENABLED=true
RELAY_HQ_SYNC_INTERVAL_SECONDS=300
RELAY_HQ_OUTBOUND_TOPOLOGY_MODE=manual
RELAY_HQ_INBOUND_TRUST_MODE=manual
```

Command:

```bash
C:/wamp64/bin/php/php8.2.29/php.exe artisan relay:hq-sync
```

See [docs/hub-relay-hq-registry-integration-proposal.md](docs/hub-relay-hq-registry-integration-proposal.md) for the current HQ-backed identity/topology model and the remaining transition work from manual peer config to HQ-derived defaults.

Use HMAC mode when you want signed hub-to-hub requests instead of plain shared-key comparison:

```dotenv
RELAY_HUB_AUTH_MODE=hmac
RELAY_HUB_AUTH_TIMESTAMP_TOLERANCE_SECONDS=300
RELAY_LOCAL_HUB_ID=barangay-hub
```

Use certificate-bound transport when your web server or proxy forwards a trusted client certificate fingerprint:

```dotenv
RELAY_HUB_AUTH_MODE=mtls
RELAY_HUB_AUTH_CLIENT_CERTIFICATE_FINGERPRINT_HEADER=X-Relay-Client-Cert-Fingerprint
RELAY_HUBS={"city-hub":{"tls_client_certificate_fingerprint":"ab12cd34ef56"}}
```

Use `mtls_hmac` when you want both client certificate validation and HMAC request signing:

```dotenv
RELAY_HUB_AUTH_MODE=mtls_hmac
RELAY_HUBS={"city-hub":{"token":"shared-city-key","tls_client_certificate_fingerprint":"ab12cd34ef56"}}
RELAY_TARGETS={"city-hub":{"base_url":"https://city.example","token":"shared-city-key","client_certificate_path":"C:/certs/relay-client.pem","client_private_key_path":"C:/certs/relay-client.key","ca_certificate_path":"C:/certs/relay-ca.pem"}}
```

### Test It

```bash
# Run test suite
C:/wamp64/bin/php/php8.2.29/php.exe artisan test

# Or individually
C:/wamp64/bin/php/php8.2.29/php.exe artisan test tests/Feature/Relay/Api/MessageSubmissionTest.php
```

### Browser Session Auth

Browser-based relay operator authentication follows the shared PBB session pattern described in:

- [docs/login-logout-flow-reference.md](docs/login-logout-flow-reference.md)
- [docs/pbb-user-session-handling-proposal.md](docs/pbb-user-session-handling-proposal.md)

Current browser session endpoints:

- `GET /api/bootstrap`
- `GET /api/csrf-token`
- `GET /api/session/ping`
- `POST /api/login`
- `GET /api/user`
- `POST /api/user`
- `POST /api/user/password`
- `POST /api/logout`

Behavior:

- the public home `Operator Login` action opens the shared Helper login preset and authenticates via `POST /api/login`
- successful login returns the current account plus a refreshed CSRF token
- the browser shell treats `GET /api/bootstrap` as the primary startup contract for account, CSRF, page, and session metadata
- the embedded `window.__PBB_BOOTSTRAP__` payload is now only a lightweight page/settings seed for early startup and fallback
- protected fetch requests use the shared browser session helper with mutable CSRF handling
- `GET /api/session/ping` is only sent near expiry when the user is actively using the page
- expired sessions on authenticated `/relay` requests open a `Session Expired` re-login modal instead of redirecting away
- logout is executed via `POST /api/logout`, which invalidates the session and refreshes the CSRF token before returning the browser to `/`

### Submit a Message

```bash
curl -X POST http://localhost:8000/api/v1/messages \
  -H "Content-Type: application/json" \
  -H "X-Relay-Key: test-key-123" \
  -d '{
    "source_system": "sitrep.app",
    "target_systems": ["city-eoc.app"],
    "message_type": "sitrep.record",
    "payload": {"incident_id": 123, "description": "Test"}
  }'
```

### Check Health

```bash
curl http://localhost:8000/api/v1/diagnostics | jq
```

## Architecture Overview

### Two API Surfaces

**Local Application API** (`/api/v1/`)
- Local systems submit messages, query their own outbound message and delivery state, inspect shared inbox items, and register local handlers
- Authentication: API key (per registered local client)

**Hub-to-Hub API** (`/api/v1/receive`)
- Remote hubs deliver messages to this relay
- Authentication: shared-key, HMAC, certificate-bound (`mtls`), or certificate-plus-HMAC (`mtls_hmac`) transport modes
- Protocol negotiation: `X-Relay-Protocol-Version` header with compatibility response headers on all relay API responses

### Database Schema

- **hub_relay_messages** — Message envelopes
- **hub_relay_deliveries** — Delivery tracking (per next-hop relay)
- **hub_relay_receipts** — Inbound receipts + idempotency keys
- **hub_relay_attachments** — File metadata (phase 2)
- **hub_relay_clients** — Local app registration + API keys
- **hub_relay_messages.hub_relay_client_id** — Outbound message ownership by local client
- **hub_relay_handlers** — Local webhook registrations and handoff status
- **hub_relay_handler_dispatches** — Local webhook attempt state and retry visibility

### Core Services

- `RelaySubmissionService` — Local app message submission
- `RelayReceiveService` — Inbound message handling
- `LocalHandlerDispatchService` — Matches inbound messages to local webhook handlers
- `DispatchRelayToLocalHandler` — Executes tracked local webhook dispatch attempts
- `RelayEnvelopeValidator` — Message validation
- `RelayIdempotencyService` — Duplicate detection
- `RelayDiagnosticsService` — Health and queue status

## Phase 1 Features

✅ **Message Submission**
- Local apps POST `/api/v1/messages`
- Creates message + next-hop delivery queue from HQ-managed topology
- Records the authenticated local client as the outbound message owner

✅ **Outbound Queueing**
- Messages stored with status (queued, sending, delivered, failed, dead)
- One delivery record per target hub per message

✅ **Inbound Receipt**
- Remote hubs POST `/api/v1/receive`
- Idempotent handling (same relay_id = no reprocessing)

✅ **Local Consumption**
- Local apps GET `/api/v1/inbox` for received-message pull access
- Local apps POST `/api/v1/handlers` to register callback endpoints
- Inbound receives can queue local webhook handoff jobs for matching handlers
- Local apps can inspect `/api/v1/handler-dispatches` and manually retry failed webhook dispatches

Current ownership boundary:
- `GET /api/v1/messages` and `GET /api/v1/messages/{message}` are scoped to the authenticated local client
- `GET /api/v1/deliveries` plus delivery detail/retry/cancel routes are scoped through the owning outbound message
- local client submission uses client-owned `target_systems[]`
- hub-to-hub transport uses relay-owned next-hop `target_hq_hub_id`
- inbound messages that reach the local HQ hub but match no registered local `target_systems[]` are accepted and marked `undeliverable`
- `GET /api/v1/inbox` and `GET /api/v1/inbox/{message}` only expose inbound messages whose `target_systems[]` include the authenticated client's `system_code`
- local handler dispatch only evaluates handlers owned by client systems present in `target_systems[]`

✅ **Diagnostics**
- GET `/api/v1/diagnostics` → Queue + delivery + inbox summaries
- GET `/api/v1/compatibility` → Version info for hub negotiation

✅ **Monitoring UI**
- `/` provides a public shared-service status page with basic relay metrics and integration links
- `Operator Login` on `/` provides relay operator access through a modal-backed browser session flow
- `/relay` renders an operator-facing dashboard for queue, inbox, upload, client, and handler status
- `/relay/outbox`, `/relay/inbox`, `/relay/deliveries`, `/relay/uploads`, `/relay/dead-letters`, `/relay/clients`, and `/relay/users` provide broader admin operations views
- `/relay/api/docs` provides a public Swagger UI for the current OpenAPI spec
- `/relay/clients` provides baseline API token management for local client registrations
- `/relay/users` provides baseline relay operator management

✅ **Envelope Contract**
- Common format for all messages across all hubs
- Local client submit fields: source_system, target_systems, message_type, payload, etc.
- Hub transport fields: relay_id, origin_hq_hub_id, source_hub_id, source_system, target_hq_hub_id, target_systems, hop_trace, etc.
- ULID format for globally unique, sortable IDs

## Deferred to Phase 2+

- ✅ Delivery workers (background jobs)
- ✅ Retry logic with backoff
- ✅ Basic hub authentication (shared keys + optional HMAC)
- ✅ Stronger hub authentication (certificate-bound `mtls` / `mtls_hmac`)
- ✅ File/image attachments + chunked uploads
- ✅ Local handler registration (webhooks)
- ✅ Handler dispatch observability and retry controls
- ✅ Admin monitoring UI
- ✅ SDKs (PHP, JS clients)

Still open after Phase 2 baseline:
- UI polish from operator testing
- OpenAPI/schema generation
- granular permissions and audit trails
- automated SDK release automation
- scheduled HQ sync and admin sync visibility

## Key Design Principles

1. **Server-First** — Shared service, not copied code
2. **API-First** — Local apps use REST APIs
3. **Hub-to-Hub** — Server-to-server relay, not direct app communication
4. **Shared Infrastructure** — One implementation per location
5. **Deployment-Neutral** — Business logic stays in applications, relay is generic

## File Structure

```
app/
├─ Relay/
│  ├─ Envelope/     (Validation)
│  ├─ Outbound/     (Submission)
│  ├─ Inbound/      (Receive)
│  ├─ Diagnostics/  (Monitoring)
│  ├─ ... (Auth, Delivery, Storage, Transport for phase 2+)
├─ Http/Controllers/Api/Relay/
│  ├─ Inbound/
│  ├─ Messages/
│  ├─ Attachments/
│  ├─ Uploads/
│  └─ DiagnosticsController.php
├─ Models/
│  ├─ HubRelayMessage.php
│  ├─ HubRelayDelivery.php
│  ├─ HubRelayReceipt.php
│  └─ ...
└─ DTO/
   └─ RelayEnvelopeDTO.php

database/
├─ migrations/ (hub_relay_*)
├─ factories/  (HubRelayMessageFactory)
└─ seeders/

resources/
└─ vendor/
   └─ helpers.pbb.ph/
      ├─ css/ui/
      ├─ js/ui/
      └─ VENDORED.md

sdk/
├─ php/
│  ├─ src/PbbHubRelayClient.php
│  └─ README.md
└─ js/
   ├─ pbb-hub-relay-client.mjs
   └─ README.md

tests/
├─ Unit/Relay/
└─ Feature/Relay/Api/

routes/
├─ api.php     (Relay API routes)
└─ web.php
```

## Next Steps

**For Development Team:**
1. Review [PHASE1_SITREP.md](PHASE1_SITREP.md) for completion details
2. Run tests: `C:/wamp64/bin/php/php8.2.29/php.exe artisan test`
3. Test API endpoints manually (see Quick Start)
4. Initial feedback on architecture alignment

**After Phase 2:**
1. Execute operator testing against the completed Phase 2 baseline
2. Capture polish/follow-up work as post-phase-2 hardening

## Deployment Notes

- **PHP:** 8.2+ (configured in composer.json)
- **Project PHP CLI:** `C:/wamp64/bin/php/php8.2.29/php.exe`
- **Database:** MySQL for local development in this workspace (`pbb_relay` on `localhost`)
- **Queue:** Database driver (switch to Redis later)
- **Web Server:** Any Laravel-compatible server (Apache, Nginx, PHP built-in)

## Testing

All core functionality is tested:

```bash
# Full test suite
C:/wamp64/bin/php/php8.2.29/php.exe artisan test

# Specific test class
C:/wamp64/bin/php/php8.2.29/php.exe artisan test --filter=MessageSubmissionTest

# With coverage (requires pcov/xdebug)
C:/wamp64/bin/php/php8.2.29/php.exe artisan test --coverage
```

## Contributing

- Proposals: Update docs/ folder
- Implementation: Follow folder structure in app/
- Tests: Add feature or unit tests for changes
- Backwards Compatibility: Phase 1 API is stable

## Architecture Decision: Server-First

Why not embed relay code in each app?

- ✅ **One implementation** to maintain
- ✅ **One upgrade path** per hub location
- ✅ **Neutral protocol** between different systems
- ✅ **Easy for partner systems** to integrate
- ✅ **Clear separation** of concerns
- ✅ **Better monitoring** from one place

This design enables PBB to support multiple local systems (SITREP, incident management, foundation reporting, etc.) all using the same relay without code duplication.

---

**Questions?** See [PHASE1_IMPLEMENTATION.md](PHASE1_IMPLEMENTATION.md) or [PHASE1_SITREP.md](PHASE1_SITREP.md) for detailed information.

- `Hub Relay` as a local shared service per hub location
- API-first integration for local systems
- relay-server to relay-server transport across hubs
- shared monitoring owned by the relay layer

The shared-package proposal remains relevant as the codebase packaging and release strategy for the relay server itself.
