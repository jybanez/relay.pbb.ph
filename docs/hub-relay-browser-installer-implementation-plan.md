# Hub Relay Browser Installer Implementation Plan

## Purpose

Define the first buildable implementation plan for the `Hub Relay` browser installer.

This plan turns the installer proposal and bootstrap package spec into a practical execution sequence for the first development slice.

Companion docs:

- `docs/hub-relay-browser-installer-proposal.md`
- `docs/hub-relay-installer-bootstrap-package-spec.md`

## Scope Of This Plan

This plan covers only the initial implementation slice.

Included:

- bootstrap `index.php`
- temporary installer runtime skeleton
- environment-check backend
- Helper-based dark installer shell
- installer state storage

Not included yet:

- HQ validation flow
- DB configuration flow
- full install execution
- admin creation
- final cleanup/self-removal

Those should come in later phases after the bootstrap slice is stable.

## Phase Goal

At the end of this first slice, a fresh host should be able to:

1. load the minimal bootstrap `index.php`
2. extract the installer runtime from `installer.zip`
3. render a browser installer shell
4. run environment checks
5. show pass/warn/fail status with clear UX
6. persist installer state as `environment_checked` when requirements pass

That gives the team a real installer foundation without yet taking on HQ binding or destructive install steps.

## Deliverables

### 1. Bootstrap Entry Point

Add a minimal permanent bootstrap `index.php` that:

- detects installed state
- extracts installer runtime if needed
- hands off to installer runtime
- fails with a simple bootstrap error page if extraction cannot proceed

This file should remain intentionally small and auditable.

### 2. Installer Runtime Skeleton

Create the temporary installer runtime structure described in the packaging spec.

Minimum runtime pieces:

- installer entrypoint
- routes
- controllers
- state storage helper
- environment check service
- helper-driven UI shell

### 3. Environment Check API

Add an installer API endpoint that returns grouped environment readiness checks.

Minimum check groups:

- runtime
- PHP extensions
- filesystem
- archive support
- DB driver availability

### 4. Installer Shell UI

Add the first browser installer page using the shared Helper library.

Minimum UI features:

- dark scheme shell
- step tracker
- grouped environment checks
- pass/warn/fail status rendering
- continue button disabled while blocking failures exist

### 5. Installer State Store

Persist lightweight installer state outside the app DB.

Minimum state fields:

- `status`
- `created_at`
- `updated_at`
- current completed step
- current check summary

## Recommended Folder Plan

Suggested initial installer runtime structure:

```text
.installer/runtime/
├─ public/
│  ├─ index.php
│  ├─ assets/
│  └─ vendor/helpers.pbb.ph/
├─ app/
│  └─ Installer/
│     ├─ Http/
│     │  ├─ Controllers/
│     │  └─ Responses/
│     ├─ Services/
│     ├─ Support/
│     └─ DTO/
├─ resources/
│  ├─ views/installer/
│  └─ js/installer/
├─ routes/
│  └─ installer.php
└─ bootstrap/
```

For the first slice, only the minimum pieces need to exist.

## Suggested Backend Components

### `InstallerBootstrap`

Purpose:

- central helper for locating installer paths
- resolving installed state
- runtime extraction checks

### `InstallerStateStore`

Purpose:

- read/write installer state JSON
- enforce state transitions

### `EnvironmentCheckService`

Purpose:

- run all environment checks
- return grouped normalized results

Suggested normalized check shape:

```json
{
  "key": "php_version",
  "group": "runtime",
  "label": "PHP Version",
  "status": "pass",
  "message": "PHP 8.2.29 detected.",
  "hint": null
}
```

### `InstallerController`

Purpose:

- serve installer shell
- expose environment readiness endpoint
- accept “continue” action once environment checks pass

## Suggested Front-End Components

### `installer.app.js`

Purpose:

- shell bootstrap
- load Helper components
- coordinate step UI

### `installer.environment.js`

Purpose:

- fetch environment check results
- render grouped status rows
- derive blocking vs non-blocking conditions

### `installer.shell.css` or Helper-host styling layer

Purpose:

- define installer-specific dark theme variables
- define shell layout
- keep visual treatment aligned with the shared PBB Helper direction

## Environment Checks To Implement First

### Runtime

- PHP version meets minimum
- `json` extension loaded
- `openssl` extension loaded
- `mbstring` extension loaded
- `fileinfo` extension loaded

### Archive Support

- `zip` extension loaded
- `ZipArchive` class available

### Database Drivers

- `pdo` extension loaded
- `pdo_mysql` availability
- `sqlite3` or SQLite PDO availability

### Filesystem

- install root writable
- `.installer/` writable or creatable
- env target writable or creatable
- `storage/` writable or creatable
- `bootstrap/cache/` writable or creatable

### Optional Warnings

- HQ hostname not reachable
- host appears to be plain HTTP instead of HTTPS

These should be warnings, not hard blockers, unless the team later decides otherwise.

## Step Flow For This Slice

### Step A. Bootstrap

- request hits root `index.php`
- bootstrap checks installed lock
- if not installed, bootstrap extracts installer runtime if needed
- bootstrap hands off to installer runtime

### Step B. Installer Shell

- installer shell loads with dark Helper-based frame
- environment check request is issued immediately

### Step C. Environment Check Results

- grouped checks are rendered
- failures block continuation
- warnings are visible but non-blocking

### Step D. Continue

- when all blocking checks pass, operator can continue
- installer state becomes `environment_checked`
- next unimplemented steps can temporarily show a placeholder “next phase” screen during early development

## Suggested Route Surface For This Phase

- `GET /install`
  - installer shell
- `GET /install/api/environment`
  - grouped environment checks
- `POST /install/api/environment/continue`
  - persist state when environment is acceptable

For the first slice, these are enough.

## State Rules For This Phase

Initial state:

- `fresh`

When environment checks pass and operator continues:

- `environment_checked`

If environment checks fail:

- remain at `fresh`

Recommended rule:

- do not allow manual transition to later states yet

## UX/UI Direction For This Phase

Use `helpers.pbb.ph` as the primary installer UI layer.

Minimum visual requirements:

- dark scheme
- full-height shell
- clear step indicator with current step highlighted
- grouped requirement panels
- readable semantic status colors
- fixed footer action area

Recommended tone:

- operational
- quiet
- exact
- not playful or promotional

## Error Handling

Bootstrap errors should be separate from installer UI errors.

### Bootstrap Errors

Examples:

- `installer.zip` missing
- `ZipArchive` unavailable before runtime extraction
- runtime entrypoint missing after extraction

Handling:

- render a minimal plain fallback page
- no dependency on Helper UI

### Installer UI Errors

Examples:

- environment endpoint failure
- state file write failure

Handling:

- render inside installer shell
- provide retry action
- show short technical reason

## Testing Plan

### Manual

- fresh host with valid ZIP extraction support
- host missing `zip`
- host missing write permission
- host with PHP version below minimum

### Automated

Initial automated coverage targets:

- bootstrap installed/not-installed path selection
- environment check normalization
- filesystem check classification
- blocking vs warning derivation
- state transition from `fresh` to `environment_checked`

## Deferred To Later Phases

These should not be mixed into the first slice:

- HQ API integration
- database credentials collection
- `.env` writing
- application extraction into final root
- migrations
- admin generation
- cleanup manifest execution

Keeping the first slice narrow is important. If bootstrap, extraction, and environment checks are not solid, later install steps will be harder to trust.

## Next Execution Slice

After the current installer baseline, the next recommended slice should focus on execution UX and execution-state mechanics before deeper packaging changes.

Recommended goals:

1. replace the current fire-and-forget execution button behavior with a blocking execution modal
2. persist execution progress step-by-step outside the app DB
3. restore the modal after refresh using persisted execution state
4. prevent duplicate execution starts through an execution lock
5. separate `running`, `completed`, and `failed` modal behavior cleanly

Recommended backend scope:

- `InstallerExecutionStateStore`
- `InstallerExecutionLock`
- `InstallerExecutionRunner`
- `GET /install/api/progress`
- optional `POST /install/api/execute/retry`

Recommended front-end scope:

- `installer.execution-modal.js`
- modal shell using Helper dialog/action-modal patterns
- step list with `pending`, `running`, `completed`, and `failed` states
- polling or resumable fetch flow against persisted execution state

Recommended visible execution phases:

1. prepare workspace
2. extract release
3. write environment
4. verify database
5. run migrations
6. create admin
7. write install lock
8. prepare cleanup
9. finalize installed state

Recommended rule:

- do not split the embedded release package only to create perceived progress
- progress should be based on meaningful install phases, not archive fragmentation

## Implementation Sequence

Recommended order:

1. add permanent bootstrap `index.php`
2. add installer runtime extraction support
3. add installer runtime shell route
4. add `InstallerStateStore`
5. add `EnvironmentCheckService`
6. add environment API response shape
7. add Helper-based dark installer shell
8. add continue action and state transition
9. add initial tests

## Definition Of Done For Phase 1

Phase 1 is complete when:

- a fresh-host bootstrap path works
- installer runtime extraction works
- environment checks render in a usable dark-shell UI
- blocking failures prevent continuation
- passing checks allow transition to `environment_checked`
- no install execution or cleanup behavior has been partially mixed in yet

## Recommendation

Implement the installer in narrow, trustworthy slices.

Start with the bootstrap path and environment-check experience first. That gives the project a safe, demonstrable foundation for the browser installer before adding HQ validation, database setup, release extraction, and self-removal behavior.
