# Hub Relay Installer Bootstrap Package Spec

## Purpose

Define the concrete packaging and bootstrap mechanics for the browser-based `Hub Relay` installer.

This document is the technical companion to:

- `docs/hub-relay-browser-installer-proposal.md`

Where the installer proposal describes the product and UX direction, this document specifies:

- what files are shipped initially
- what `index.php` actually does
- what `installer.zip` contains
- how the Relay application package is unpacked
- how the installer hands off to the installed app
- how cleanup and self-removal should work safely

## Scope

This spec covers the bootstrap package only.

It does not attempt to define:

- final Relay business logic
- Relay API behavior
- HQ registry semantics beyond installer validation
- the full UI implementation details

## Bootstrap Artifact Model

Recommended initial deployment artifacts:

- `index.php`
- `installer.zip`

Recommended rule:

- no other files should be required for a fresh install
- in Kit Setup bundled-package form, the outer bundle may contain a compact unattended installer contract rather than a fully expanded Laravel app tree

Optional future variant:

- `relay-release.zip`

But the preferred baseline remains:

- a single installer archive that already contains the Relay release payload

## Artifact Responsibilities

### `index.php`

`index.php` is the permanent bootstrap entry point.

Responsibilities:

- determine whether Relay is already installed
- determine whether installer bootstrap is required
- extract `installer.zip` if necessary
- delegate request handling to the extracted installer runtime
- after installation, delegate request handling to the installed Relay application

Non-goals:

- no installer business logic
- no HQ validation logic
- no environment validation logic
- no installation orchestration logic

Recommended size:

- small enough to audit quickly
- stable enough that it rarely changes between releases

### `installer.zip`

`installer.zip` is a self-contained installer bootstrap archive.

Responsibilities:

- provide the temporary installer runtime
- include the shared Helper UI assets needed by the installer
- include the Relay release payload
- include cleanup and handoff logic

## Recommended Package Layout

Recommended archive layout inside `installer.zip`:

```text
installer.zip
├─ installer-runtime/
│  ├─ public/
│  │  ├─ index.php
│  │  ├─ assets/
│  │  └─ vendor/helpers.pbb.ph/
│  ├─ app/
│  │  └─ Installer/
│  ├─ routes/
│  │  └─ installer.php
│  ├─ resources/
│  │  ├─ views/installer/
│  │  └─ js/installer/
│  ├─ bootstrap/
│  └─ manifest.json
└─ relay-release/
   ├─ relay-release.zip
   └─ release-manifest.json
```

Recommended rule:

- installer runtime and application release should be distinct payloads inside the archive
- that separation makes cleanup and version handling easier
- maintainer builds should treat `installer-runtime` and `relay-release.zip` as separate sub-builds so installer UI changes do not force a full release repack every time

Recommended maintainer build behavior:

- cache the built `relay-release.zip` artifact outside the shipped archive assembly path
- compute a release fingerprint from the deployable app tree
- reuse the cached release artifact when the fingerprint is unchanged
- allow explicit runtime-only and force-release build modes
- exclude non-runtime packaging noise such as `docs/`, `test*/`, and `.git*` artifacts from both payloads
- prune source-only/runtime-sensitive files from the installed app, including environment examples and PHPUnit cache
- keep `composer.json` in the installed app because Laravel queue workers and package discovery can still read Composer package metadata at runtime even when `vendor/` is bundled

## Required Manifests

### Installer Manifest

Recommended file:

- `installer-runtime/manifest.json`

Suggested fields:

```json
{
  "installer_version": "1.0.0",
  "relay_release_version": "1.1.0",
  "minimum_php_version": "8.2.0",
  "requires_extensions": [
    "pdo",
    "mbstring",
    "openssl",
    "json",
    "fileinfo",
    "zip"
  ],
  "has_embedded_relay_release": true
}
```

Purpose:

- allow bootstrap sanity checks before full installer execution
- expose version information to the environment check UI

### Relay Release Manifest

Recommended file:

- `relay-release/release-manifest.json`

Suggested fields:

```json
{
  "release_version": "1.1.0",
  "app_package": "relay-release.zip",
  "app_entrypoint": "public/index.php",
  "expected_paths": [
    "app",
    "bootstrap",
    "config",
    "database",
    "public",
    "resources",
    "routes",
    "storage",
    "vendor"
  ]
}
```

Purpose:

- define what the installer expects to extract
- support post-extraction integrity checks

## Bootstrap Directory Layout On Disk

Recommended extracted working layout:

```text
webroot/
├─ index.php
├─ installer.zip
└─ .installer/
   ├─ runtime/
   ├─ release/
   ├─ state.json
   ├─ cleanup.json
   ├─ locks/
   └─ logs/
```

Recommended meanings:

- `.installer/runtime/`
  - extracted installer runtime
- `.installer/release/`
  - extracted Relay release or temporary release files
- `.installer/state.json`
  - persisted installer state machine
- `.installer/cleanup.json`
  - staged cleanup manifest
- `.installer/locks/`
  - execution/cleanup locks
- `.installer/logs/`
  - installer-local logs if needed

## `index.php` Bootstrap Algorithm

Recommended `index.php` decision flow:

1. define install root and working paths
2. check for installed lock marker
3. if installed lock exists and installed app entrypoint exists:
   - require installed app entrypoint
4. if not installed:
   - confirm `installer.zip` exists
   - confirm `ZipArchive` exists
   - extract installer runtime into `.installer/runtime/` if missing or invalid
   - require installer runtime entrypoint

Recommended pseudocode:

```php
if (isInstalled() && installedAppEntrypointExists()) {
    require installedAppEntrypoint();
    exit;
}

if (! file_exists($installerZip)) {
    renderBootstrapError('Installer package not found.');
    exit;
}

extractInstallerRuntimeIfNeeded();
require $installerRuntimeEntrypoint;
```

Recommended hard rule:

- bootstrap must fail closed
- if install state is ambiguous, do not guess

## Installed App Handoff

There are two viable handoff patterns.

### Option A. Permanent Bootstrap `index.php`

`index.php` remains a permanent tiny dispatcher.

Behavior:

- before install: hand off to installer runtime
- after install: hand off to installed app front controller

Advantages:

- simpler fresh-install bootstrap
- easier to reason about recovery
- avoids replacing the executing front file

Recommended status:

- preferred

### Option B. Replace `index.php` Entirely

The installer overwrites the bootstrap `index.php` with the app’s real front controller.

Advantages:

- slightly simpler steady-state runtime

Disadvantages:

- riskier cleanup/handoff
- harder rollback if install ends in a partial state

Recommended status:

- avoid unless there is a strong deployment reason

## Relay Release Extraction Rules

During install execution, the installer should extract the real Relay application payload into the target install root.

Recommended target:

- current web root or deployment root

Recommended rules:

- extract into a temporary directory first
- verify manifest and expected paths
- move into final position only after validation
- do not overwrite an already-installed app without a dedicated update flow

Recommended sequence:

1. extract `relay-release.zip` into `.installer/release/unpacked/`
2. verify required paths from release manifest
3. write final env/config values
4. move or copy release paths into final install root
5. run Laravel bootstrap tasks

## Environment File Strategy

Recommended install target:

- final `.env` in the install root

Recommended behavior:

- start from embedded `.env.example`
- write installer-derived values only
- keep keys ordered consistently where practical
- avoid storing transient installer-only values

Required installer-derived fields:

- `APP_NAME`
- `APP_URL`
- `APP_KEY`
- DB settings
- `RELAY_LOCAL_HUB_ID`
- `RELAY_HQ_API_ENABLED=true`
- `RELAY_HQ_API_BASE_URL`
- `RELAY_HQ_API_TOKEN`
- `RELAY_HQ_LOCAL_RELAY_HUB_ID`
- `RELAY_HQ_LOCAL_HQ_ID`

The installer also writes a public hub identity snapshot:

- `public/hub.json`
- public URL: `/hub.json`

For Kit installs, Kit should pass the resolved HQ hub payload under `relay.hub` together with `relay.hub_id`, `relay.hq_hub_id`, `relay.hq_api_base_url`, and `relay.hq_api_token`. Relay sanitizes that payload before writing it. The snapshot includes public fields such as `base_url`, `hub_id`, `relay_hub_id`, `name`, `code`, deployment/location codes, `domain`, `status`, `uplinks`, and `sources`; it must not include token material, installer secrets, or raw private registry payloads.

Data Prep may later update these Maestro telemetry fields after Maestro provisions the Relay telemetry token:

- `RELAY_MAESTRO_ENABLED`
- `RELAY_MAESTRO_BASE_URL`
- `RELAY_MAESTRO_APP_CODE`
- `RELAY_MAESTRO_TELEMETRY_TOKEN`
- `RELAY_MAESTRO_TLS_VERIFY`
- `RELAY_MAESTRO_CA_BUNDLE`

Recommended rule:

- after Data Prep changes any Maestro telemetry field, clear `bootstrap/cache/config.php` and restart the Relay worker so it reads the new `.env`

## Install Lock Strategy

Recommended final lock file:

- `.relay-installed.lock`

Suggested fields:

```json
{
  "installed_at": "2026-03-21T12:30:00+08:00",
  "relay_release_version": "1.1.0",
  "hq_hub_id": 10,
  "relay_hub_id": "072217043",
  "app_url": "https://lusaran.cebu.cebu.relay.pbb.ph"
}
```

Recommended rule:

- lock should be written only after the application is genuinely usable
- lock presence alone should not be the only installed check; bootstrap should also verify app entrypoint existence

## Cleanup Strategy

Cleanup should be staged, explicit, and constrained.

Recommended deletions after successful install:

- `installer.zip`
- `.installer/runtime/`
- `.installer/release/`
- `.installer/state.json`
- `.installer/cleanup.json`
- `.installer/logs/` if not needed
- any installer-only public assets

Recommended preservation list:

- installed application files
- `.env`
- `.relay-installed.lock`
- runtime storage
- Composer vendor directory

Recommended hardening:

- cleanup logic must reject path traversal
- cleanup logic must only delete within the install root
- cleanup must be idempotent

## Failure Recovery Model

Installer failures should be classified into two groups:

- pre-install failures
- partial-install failures

### Pre-Install Failures

Examples:

- missing PHP extension
- invalid HQ token
- invalid DB credentials

Recommended handling:

- no app extraction into final root
- keep installer available for correction and retry

### Partial-Install Failures

Examples:

- release extracted but migration fails
- env written but admin provisioning fails
- cleanup manifest written but cleanup not completed

Recommended handling:

- preserve installer state
- preserve last successful step
- offer safe retry only from the correct stage
- do not write final installed lock unless app is usable

## Execution State Contract

Install execution should expose a persisted execution-state contract separate from the earlier form-state flow.

Recommended execution state file shape:

```json
{
  "status": "running",
  "started_at": "2026-03-21T14:02:00+08:00",
  "updated_at": "2026-03-21T14:02:14+08:00",
  "current_step": "verify_database",
  "last_completed_step": "write_environment",
  "steps": [
    { "key": "prepare_workspace", "status": "completed", "message": "Install workspace prepared." },
    { "key": "extract_release", "status": "completed", "message": "Embedded Relay release extracted." },
    { "key": "write_environment", "status": "completed", "message": "Environment configuration written." },
    { "key": "verify_database", "status": "running", "message": "Verifying target database connectivity." },
    { "key": "run_migrations", "status": "pending", "message": null }
  ],
  "failure": null,
  "retry_allowed": false,
  "cleanup_pending": false
}
```

Recommended rules:

- execution state should be persisted outside the application DB
- execution state should survive page refreshes
- execution state should support modal restoration after reconnect
- execution state should be updated after every completed or failed step

Recommended step status values:

- `pending`
- `running`
- `completed`
- `failed`

Recommended top-level execution statuses:

- `idle`
- `running`
- `completed`
- `failed`

## Execution API Contract

Recommended execution API behavior:

- `POST /install/api/execute`
  - initialize execution state
  - acquire an execution lock
  - begin or advance the staged installer execution
- `GET /install/api/progress`
  - return the current persisted execution state for the modal
- `POST /install/api/execute/retry`
  - retry only when execution state explicitly allows it

Recommended response shape for `GET /install/api/progress`:

```json
{
  "status": "running",
  "current_step": "run_migrations",
  "last_completed_step": "verify_database",
  "steps": [
    { "key": "prepare_workspace", "status": "completed", "message": "Install workspace prepared." },
    { "key": "extract_release", "status": "completed", "message": "Embedded Relay release extracted." },
    { "key": "write_environment", "status": "completed", "message": "Environment configuration written." },
    { "key": "verify_database", "status": "completed", "message": "Target database connection verified." },
    { "key": "run_migrations", "status": "running", "message": "Applying database migrations." }
  ],
  "failure": null,
  "retry_allowed": false
}
```

Recommended rule:

- the browser modal should render from this contract directly
- do not infer modal state only from button clicks or local JavaScript assumptions

## Execution Locking

The installer should prevent duplicate execution starts.

Recommended behavior:

- create an execution lock before starting work
- reject repeated execute requests while the lock is active
- expose the already-running execution state instead of starting a second install
- release the lock only after completion or terminal failure bookkeeping is finished

Recommended rule:

- duplicate clicks should never launch multiple overlapping installs

## Kit Setup Runtime Service Contract

Relay requires a background queue worker in production installs:

```json
{
  "id": "pbb-relay-worker",
  "name": "PBB Relay Worker",
  "type": "background_process",
  "required": true,
  "required_for_smoke": true,
  "manager": "kit",
  "working_directory": "{app.install_path}",
  "command": "{runtime.php_binary}",
  "args": ["artisan", "queue:work", "--queue=relay-deliveries,relay-handlers"],
  "health_check": {
    "type": "process",
    "timeout_seconds": 3
  },
  "logs": {
    "stdout": "storage/logs/pbb-relay-worker.out.log",
    "stderr": "storage/logs/pbb-relay-worker.err.log"
  }
}
```

Relay installer behavior:

- write `storage/app/installer/generated/pbb-relay-worker.*` service artifacts
- include the worker declaration in `install-manifest.json` and `install-report.json`
- leave actual service registration and process lifecycle to Kit or another runtime manager

Kit/runtime-manager behavior:

- register a persistent service named `pbb-relay-worker` / `PBB Relay Worker`
- run it with PHP 8.2+ from the installed Relay app root where `artisan` and `.env` live
- start it only after `.env`, database setup, and admin provisioning are complete
- restart it after Data Prep apply-settings changes Maestro telemetry values
- configure it to survive machine reboot
- capture stdout/stderr or equivalent service logs
- treat missing or stopped worker service as smoke-test failure

Operational reason:

- Relay API requests can enqueue outbound deliveries and local handler dispatches without the worker, but those jobs will not be delivered until `pbb-relay-worker` is running.

## Data Prep Maestro Settings Contract

Relay's Data Prep apply-settings tool accepts Maestro settings from increasingly specific config sections. Specific Relay settings win over generic dependency settings:

1. `maestro`
2. `dependencies.maestro`
3. `data_prep.apply_settings.maestro`
4. `relay.data_prep.maestro`
5. `relay.data_prep.apply_settings.maestro`

Accepted Relay-specific Maestro fields:

- `enabled`
- `base_url`
- `app_code`
- `telemetry_token`
- `tls_verify`
- `ca_bundle`
- `curl_ca_bundle`
- `ssl_cert_file`

`ca_bundle`, `curl_ca_bundle`, and `ssl_cert_file` are aliases for the same trusted CA bundle path. When TLS verification is enabled and a valid CA bundle path is configured, Relay's Maestro HTTP client passes that path to Guzzle's `verify` option. Relay does not require Kit to disable TLS verification for Windows/S5 installs.

Apply-settings behavior:

- writes `RELAY_MAESTRO_ENABLED=true`
- writes `RELAY_MAESTRO_BASE_URL`
- writes `RELAY_MAESTRO_APP_CODE`, defaulting to `relay` unless a Relay-specific override is supplied
- writes `RELAY_MAESTRO_TELEMETRY_TOKEN`
- writes `RELAY_MAESTRO_TLS_VERIFY`
- writes `RELAY_MAESTRO_CA_BUNDLE` when a CA bundle path is supplied
- clears `bootstrap/cache/config.php`

Verify behavior:

- verifies required Maestro env values are present
- reports whether the telemetry token is configured without exposing it
- reports `verify_tls`
- reports `ca_bundle_configured`
- reports `ca_bundle_exists`

Recommended Kit config shape:

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

## Security Rules

Recommended bootstrap-package security rules:

- installer runtime must never execute after install lock is valid
- the HQ token must never be written into visible HTML after validation
- the generated admin password must never be written to installer logs
- release manifests should be validated before extraction use
- runtime extraction path should be fixed, not user-controlled
- do not allow arbitrary archive upload as part of install

## Versioning Recommendation

The installer runtime and Relay release should be versioned separately.

Recommended model:

- installer runtime version
- embedded Relay release version

Reason:

- installer fixes may be needed without changing the Relay release
- Relay release may change while bootstrap behavior stays stable

## Suggested Build Outputs

Recommended future build artifacts:

- `relay-installer-bootstrap.zip`
  - contains browser installer runtime plus embedded release
- `relay-release.zip`
  - optional standalone release artifact for later update tooling

Recommended future internal pipeline:

1. build Relay release artifact
2. build installer runtime artifact
3. embed release artifact into installer bootstrap archive
4. emit top-level deployment files:
   - `index.php`
   - `installer.zip`

## Recommendation

Use a permanent tiny `index.php` bootstrap plus a self-contained `installer.zip` that carries both the temporary installer runtime and the packaged Relay release.

The installer should extract into a hidden working directory, validate HQ-backed identity, unpack the Relay release into the final install root, write a final installed lock, and then remove all installer artifacts except the permanent bootstrap handoff file.
