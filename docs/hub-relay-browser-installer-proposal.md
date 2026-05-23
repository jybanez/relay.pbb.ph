# Hub Relay Browser Installer Proposal

## Purpose

Define a browser-based first-install experience for `Hub Relay` that can bootstrap a fresh relay server from a minimal deployment footprint, validate hub identity directly against `PBB HQ`, and remove installer artifacts after successful installation.

This proposal replaces the idea of a long-lived CLI installer with a short-lived browser installer intended only for fresh deployments.

## Primary Goals

- make first installation simple for operators
- align relay identity directly with `PBB HQ`
- reduce manual configuration drift
- surface environment requirements before setup begins
- keep installer UX aligned with other PBB projects through the shared Helper library
- remove installer code and payloads after successful install

## Deployment Assumption

Initial deployment to a fresh host is intentionally minimal.

Expected starting files:

- `index.php`
- `installer.zip`

Expected starting condition:

- the domain already points to an empty web host or document root
- PHP is available through the web server
- no relay application is installed yet

## Recommended Bootstrap Model

### 1. Minimal Entry Point

`index.php` should be a very small bootstrap entry point.

Responsibilities:

- detect whether Relay is already installed
- if not installed, check whether `installer.zip` is present
- extract the installer payload into a temporary installer directory
- route the browser into the installer UI
- if already installed, hand off to the real Relay application front controller

`index.php` should not contain business logic for the installer itself. It should only bootstrap or hand off.

### 2. Installer Payload

`installer.zip` should contain the temporary installer runtime.

Recommended contents:

- installer routes/controllers/views/assets
- environment-check logic
- HQ validation logic
- setup execution logic
- cleanup/self-removal logic
- packaged Relay application payload or release bundle

Recommended rule:

- avoid requiring a second network download for the Relay application package during installation

That makes installation more reliable and easier to reason about.

## Concrete File Layout

Recommended fresh-host layout before install:

```text
webroot/
├─ index.php
├─ installer.zip
└─ .installer/
   └─ extracted/          (created on first installer bootstrap)
```

Recommended layout during install:

```text
webroot/
├─ index.php
├─ installer.zip
├─ .installer/
│  ├─ extracted/
│  │  ├─ public/
│  │  ├─ views/
│  │  ├─ bootstrap/
│  │  ├─ app/
│  │  └─ assets/
│  ├─ state.json
│  ├─ cleanup.json
│  └─ logs/
└─ relay-release.zip      (optional, if separated from installer runtime)
```

Recommended layout after successful install:

```text
webroot/
├─ index.php              (handoff bootstrap or real app front controller)
├─ public/                (installed Relay public assets/app entry)
├─ app/
├─ bootstrap/
├─ config/
├─ database/
├─ resources/
├─ routes/
├─ storage/
├─ vendor/
└─ .relay-installed.lock
```

Recommended rule:

- installer working files should live under a dedicated hidden directory such as `.installer/`
- successful cleanup should remove `.installer/` and `installer.zip`
- final installed state should not depend on installer directories remaining present

## Fresh-Install Detection

The installer must only be available on a fresh host.

Recommended detection signals:

- no valid installed lock file
- no completed application bootstrap
- no existing successful Relay install state in the target environment

Recommended installed marker:

- a dedicated install lock file outside the web root when possible
- plus sanity checks such as:
  - valid `.env`
  - valid `APP_KEY`
  - successful DB connectivity
  - expected migrated tables

The installer must not remain reachable once installation is complete.

## Installer State Machine

The installer should behave like a small state machine rather than a loose collection of pages.

Recommended states:

- `fresh`
- `environment_checked`
- `hq_validated`
- `settings_collected`
- `installing`
- `cleanup_pending`
- `installed`
- `failed`

Recommended rules:

- state should be persisted in a small installer state file
- users should not be able to skip forward into later steps without satisfying earlier ones
- refresh or reconnect should restore the current valid step rather than restarting blindly
- failure during install execution should preserve enough state for recovery or a clean retry

Example state file:

```json
{
  "status": "hq_validated",
  "created_at": "2026-03-21T09:00:00+08:00",
  "updated_at": "2026-03-21T09:03:12+08:00",
  "hq_hub_id": 10,
  "relay_hub_id": "072217043",
  "app_url": "https://lusaran.cebu.cebu.relay.pbb.ph"
}
```

## Installer Flow

### Step 1. Environment Check

This should be the first visible installer screen.

Purpose:

- verify that the host is capable of running the installer and the Relay application
- prevent partial setup on an unsupported environment
- give operators a clear UI for requirement failures

Recommended checks:

- PHP version
- required PHP extensions:
  - `pdo`
  - `mbstring`
  - `openssl`
  - `json`
  - `fileinfo`
  - `zip`
  - target database driver extension such as `pdo_mysql` or `sqlite3`
- filesystem writeability for:
  - installer extraction target
  - Relay app target
  - storage paths
  - bootstrap/cache
  - env file location
- ability to extract ZIP archives through `ZipArchive`
- required PHP functions if setup execution depends on them
- hostname and protocol sanity where relevant

Recommended UX:

- one clearly readable status list
- each requirement shown as:
  - `pass`
  - `warning`
  - `fail`
- do not allow install continuation while blocking failures remain

Recommended check groups:

- runtime
- PHP extensions
- filesystem
- archive/extraction
- database drivers
- optional network reachability to HQ

Recommended UI behavior:

- environment checks should run immediately when the installer first loads
- each check row should show:
  - label
  - current result
  - short reason
  - fix hint when failed
- the screen should feel like a system readiness console, not a generic form page

## Step 2. HQ Validation

After the environment is confirmed, the installer should collect and validate the local hub identity directly against `PBB HQ`.

Fields to collect:

- HQ Hub ID
- assigned HQ token
- admin name
- admin email

Recommended validation call:

- `GET /api/hubs/{id}` on the HQ API
- header: `Authorization: Bearer <token>`

Recommended verification rules:

- require HTTP `200`
- require returned `hub.id` to match the submitted HQ Hub ID
- require a non-empty `relay_hub_id`
- require a usable domain or explicitly flag that app URL must be reviewed
- require token metadata to indicate `has_token=true` and `is_active=true`
- reject clearly retired or unusable hub states

Validation expectations:

- the request succeeds with a valid hub record
- the returned hub token is active
- the hub record is usable for Relay bootstrap

If validation succeeds, the installer should derive:

- `RELAY_HQ_LOCAL_HQ_ID` from HQ `id`
- `RELAY_HQ_LOCAL_RELAY_HUB_ID` from HQ `relay_hub_id`
- `RELAY_LOCAL_HUB_ID` from HQ `relay_hub_id`
- `APP_URL` from HQ `domain`
- local display context such as:
  - hub name
  - deployment
  - domain
  - hub status
  - uplink summary

Recommended rule:

- the installer should trust HQ as the source of identity
- operators should not type `relay_hub_id` or domain manually during normal install

## Step 3. Installation Settings

After HQ validation, the installer should collect any remaining local runtime settings.

Recommended fields:

- database driver
- database host
- database port
- database name
- database username
- database password
- optional app storage settings only if needed

Recommended behavior:

- prefill what can be inferred
- keep the form short
- provide a review screen before execution

Recommended installation review summary:

- HQ hub name
- HQ Hub ID
- `relay_hub_id`
- deployment
- domain / derived `APP_URL`
- primary uplink summary
- selected DB driver and target database
- admin name
- admin email

## Step 4. Install Execution

Once the operator confirms the settings, the installer should execute setup in a controlled sequence.

Recommended sequence:

1. extract the packaged Relay application
2. write `.env`
3. generate `APP_KEY`
4. verify DB connectivity
5. run migrations
6. enable HQ registry bootstrap settings
7. generate a strong admin password
8. create the initial admin user
9. write the installed lock marker
10. prepare cleanup of installer artifacts

Recommended execution model:

- use a server-side install service, not a controller with inline logic
- persist progress step-by-step so the UI can reflect actual execution state
- stop immediately on hard failure
- show the current step and last successful step in the UI
- drive execution through a blocking modal that remains visible while install work is running, completed, or failed

### Execution Modal

The install execution phase should not feel like a normal form submit.

Recommended UX:

- clicking `Execute Installation` opens a blocking modal
- the modal stays open while installation is running
- the underlying page should not invite further interaction during execution
- while running, the modal should not be casually dismissible
- success should keep the modal open and transition it into the success summary
- failure should keep the modal open and show the failed step, error detail, and whether retry is possible

Recommended modal states:

- `running`
- `completed`
- `failed`

Recommended modal content:

- installer title and current overall status
- a visible warning such as `Do not close this page while installation is running`
- a step list with per-step statuses:
  - `pending`
  - `running`
  - `completed`
  - `failed`
- detail text for the active or failed step
- final action area that changes by state

Recommended actions by state:

- while `running`
  - no close action
  - no secondary installer navigation
- when `completed`
  - `Go To Relay`
  - `Run Cleanup` only if cleanup is still pending
- when `failed`
  - `Retry` only if the failed step is resumable
  - `Close` plus corrective guidance if the failure is terminal

Recommended rule:

- the execution modal must be driven from persisted installer execution state, not only from in-memory JavaScript state
- if the page refreshes, the installer should restore the active execution modal and its last known progress

### Recommended Execution Steps

Recommended visible execution phases:

1. prepare install workspace
2. extract Relay release package
3. write environment configuration
4. verify database connectivity
5. run database migrations
6. create initial admin account
7. write install lock
8. prepare cleanup
9. finalize installation state

Recommended rule:

- these steps should be meaningful operational phases, not archive-internal file counts
- operators care more about understandable install milestones than low-level extraction details

Recommended install service slices:

- `EnvironmentCheckService`
- `HqInstallerValidationService`
- `InstallerConfigWriter`
- `InstallerDatabaseService`
- `InstallerAdminProvisioner`
- `InstallerCleanupService`
- `InstallerStateStore`

Recommended execution-tracking slices:

- `InstallerExecutionStateStore`
- `InstallerExecutionRunner`
- `InstallerExecutionStepResult`

Admin password rule:

- admin password should be system-generated
- it should be displayed once on the success screen
- it should not be logged in plaintext

## Step 5. Success And Cleanup

After successful setup, the installer should transition into cleanup and handoff.

Recommended success output:

- installation complete
- local hub name
- `relay_hub_id`
- login URL
- generated admin password shown once

Recommended final actions:

1. write install lock
2. swap bootstrap from installer handoff to application handoff
3. execute cleanup manifest
4. invalidate installer session/state
5. redirect to Relay home or login

Cleanup expectations:

- remove `installer.zip`
- remove extracted installer runtime files
- remove temporary extraction directories
- remove any installer-only routes/views/assets
- leave only the installed Relay application

Important implementation note:

- self-removal should happen as a staged cleanup after installation completes
- do not rely on deleting the currently executing file in the middle of the same request

Recommended pattern:

- final install step writes a cleanup manifest
- a dedicated post-install cleanup handler performs file removal
- browser is redirected to the installed application after cleanup completes

Cleanup manifest example:

```json
{
  "delete": [
    "installer.zip",
    ".installer/extracted",
    ".installer/state.json",
    ".installer/cleanup.json"
  ],
  "preserve": [
    ".relay-installed.lock",
    ".env",
    "storage",
    "vendor",
    "public"
  ]
}
```

## Route And Request Model

Recommended installer route surface while in fresh-install mode:

- `GET /`
  - bootstrap into installer shell or installed app
- `GET /install`
  - installer shell entry
- `GET /install/api/environment`
  - return environment checks
- `POST /install/api/hq/validate`
  - validate HQ Hub ID + token
- `POST /install/api/settings`
  - save DB and install settings
- `POST /install/api/execute`
  - start installation and open execution modal
- `GET /install/api/progress`
  - return current execution-modal state and step progress
- `POST /install/api/cleanup`
  - run staged cleanup if needed

Recommended rule:

- none of these routes should exist after installation is complete

## Recommended Installer Modules

Suggested temporary installer structure:

```text
.installer/extracted/
├─ app/
│  ├─ Installer/
│  │  ├─ Http/
│  │  ├─ Services/
│  │  ├─ Support/
│  │  └─ DTO/
├─ resources/
│  ├─ views/installer/
│  └─ js/installer/
├─ public/
│  ├─ installer/
│  └─ vendor/helpers.pbb.ph/
└─ routes/
   └─ installer.php
```

Recommended front-end modules:

- `installer.app.js`
- `installer.environment.js`
- `installer.hq-identity.js`
- `installer.settings.js`
- `installer.execute.js`
- `installer.success.js`
- `installer.execution-modal.js`

## Helper UI Direction

Recommended shell pattern:

- full-height app shell
- fixed header / step tracker
- scrollable content panel
- sticky action footer when needed

Recommended visual sections:

- left or top step tracker
- main working surface
- status summary panel

Recommended status tokens:

- `pending`
- `running`
- `pass`
- `warning`
- `fail`

Recommended dark-scheme tone:

- charcoal and slate base surfaces
- sharp high-contrast text
- restrained accent use
- clear semantic colors for state rows

The goal should feel like a deployment console, not a consumer onboarding wizard.

## UX/UI Direction

The installer should use the shared `helpers.pbb.ph` library as its primary UX/UI layer, consistent with other PBB projects.

Recommended visual direction:

- dark scheme by default
- strong contrast for status readability
- clear step-based layout
- deliberate operational tone rather than marketing UI

Recommended Helper-driven UI areas:

- form rendering and validation
- modal or confirmation flows where useful
- shared button/input styling
- progress and status presentation patterns

Recommended installer screens:

- environment check
- HQ validation
- install settings
- review and execute
- success and cleanup

## Security Expectations

This installer is high-risk by nature and must be treated accordingly.

Required safeguards:

- only usable before install completes
- never reachable after install success
- HQ token input must not be echoed back in logs or debug output
- generated admin password must only be shown once
- install state must be locked before cleanup completes
- no reinstall route should remain exposed

Recommended additional safeguards:

- require fresh-install mode only
- fail closed if install state is ambiguous
- clearly separate installer storage from app runtime storage where practical

Recommended hardening rules:

- disable verbose exception rendering in installer responses unless explicitly in local debug mode
- never store the HQ token in plaintext after validation unless the final app config explicitly requires it
- if the HQ token must be written into `.env`, avoid echoing it back anywhere in success views
- protect against repeated POST execution by using installer state transitions and execution locks
- ensure cleanup cannot delete outside the intended webroot/install root

## Packaging Recommendation

Preferred packaging model:

- `index.php`
- `installer.zip`

Where `installer.zip` contains:

- installer runtime
- Relay application package
- cleanup logic

This avoids requiring network downloads during installation and keeps the deployment artifact set small.

## Open Questions

These should be decided before implementation begins:

- should the Relay release package be embedded inside `installer.zip` or shipped as a sibling archive
- should `index.php` remain a tiny permanent bootstrap or be replaced entirely by the installed app front controller
- should database creation itself be supported, or only connection to a pre-created database
- should HQ reachability be a hard blocker during initial install or a retryable validation step
- should the installer support resume after partial application extraction
- where should the installed lock file live on Linux vs Windows hosts

## Suggested Implementation Phases

### Phase 1. Installer Bootstrap Prototype

- minimal `index.php`
- installer extraction
- environment checks
- helper-based shell UI

### Phase 2. HQ Identity Binding

- HQ Hub ID + token validation
- HQ-derived install review screen
- derived env/config mapping

### Phase 3. Install Execution

- env writing
- DB verification
- migrations
- admin generation
- install progress UI

### Phase 4. Cleanup And Lockdown

- install lock
- self-removal
- handoff to installed app
- regression testing for fresh-install-only access

## Operational Benefits

Compared with a CLI-first setup, this browser installer model gives:

- fewer operator mistakes
- direct HQ-backed identity alignment
- clearer environment diagnostics
- better onboarding on Windows and Linux hosts
- a familiar PBB UX/UI layer
- less chance of curious operators exploring unsupported install commands later

## Recommendation

Build the primary Relay installer as a short-lived browser-based installer for fresh deployments.

The installer should:

- start with environment checks
- validate hub identity against `PBB HQ`
- derive relay identity from HQ data
- collect only the remaining local runtime settings
- generate the first admin password automatically
- use the shared Helper library with a dark installer scheme
- remove installer artifacts after successful installation

This gives Relay a cleaner and safer deployment story while keeping identity, UX, and operational setup aligned with the wider PBB platform.
