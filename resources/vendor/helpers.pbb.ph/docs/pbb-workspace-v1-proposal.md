# PBB Workspace V1 Proposal

## Purpose

Define the product and architecture direction for `PBB Workspace` as a launcher-oriented shell for installed PBB applications.

Workspace planning should also treat these HQ-owned baseline docs as required upstream references:

- `c:\wamp64\www\pbb\hub.ph\docs\pbb-laravel-application-baseline.md`
- `c:\wamp64\www\pbb\hub.ph\docs\pbb-api-documentation-baseline.md`

Those docs define the expected baseline for new PBB Laravel browser apps and the API documentation discipline that makes Workspace integration practical.

## Product Direction

`PBB Workspace` should be a browser-based operational shell that:

- authenticates Workspace users
- shows only the PBB apps available to the current user
- launches those apps inside managed windows
- keeps window/taskbar behavior consistent across the shell
- reserves setup/admin capability for Workspace administrators only

It should not try to replace the internal business logic of the apps it hosts.

For practical embedding/integration, Workspace should assume hosted PBB apps are moving toward the HQ Laravel/API baselines above. Baseline alignment should be preferred for predictable Workspace integration, and deviations should be documented explicitly.

## User Model

V1 keeps the user model intentionally narrow:

- `Administrator`
- `User`

Both can use Workspace as a launcher shell.

Only `Administrator` can access Workspace setup.

## Workspace Shell

Workspace should use the standard PBB navbar.

Navbar behavior should include:

- a separate `Apps` menu that shows only the current user's accessible PBB apps
- a separate `User` menu that follows the standard PBB user-menu contract
- an admin-only `Setup` menu that is separate from the `User` menu
- app switching from the navbar without requiring a return to the launcher page

The navbar `Apps` entry should be driven by the same backend-filtered app list used by the launcher.

V1 should prefer an `Apps` dropdown/menu rather than rendering every app as a top-level navbar item.

Recommended trigger presentation:

- `Apps`: text button or compact button with text label
- `Setup`: gear-icon trigger for administrators only
- `User`: avatar, user icon, or single-letter trigger per HQ user-menu guidance

Dropdown items themselves should keep visible text labels.

Examples:

- `Setup`
  - `App Management`
  - `User Management`
- `User`
  - `Account`
  - `Logout`

The `User` menu should follow HQ's standard user-menu direction in:

- `c:\wamp64\www\pbb\hub.ph\docs\pbb-user-menu-spec.md`

### Default user menu

For normal users:

- `Account`
- `Logout`

### Default admin navbar actions

For administrators, the navbar action set should include:

- `Apps`
- `Setup`
- `User`

### Default admin setup menu

For administrators:

- `App Management`
- `User Management`

### Default administrator user menu

For administrators:

- `Account`
- `Logout`

## Setup Scope

Workspace setup is admin-only.

V1 setup contains:

- `App Management`
- `User Management`

### App Management

Owns:

- the registry of installed/known PBB apps
- app metadata used by the launcher
- embeddable / launch settings
- health/status endpoint configuration when needed

### User Management

Owns:

- Workspace-local user records
- role assignment (`Administrator` / `User`)
- activation/deactivation

It does not own downstream app-local permissions.

## App Visibility Rule

Workspace should not show every registered app to every user.

Instead:

- Workspace backend checks each registered app through a standard app-access API
- only apps that confirm access for the current user are shown in the launcher
- the same filtered app list is used to populate the navbar `Apps` menu

Example:

- user email: `user1@pbb.ph`
- HQ says user exists and has access
- Relay says user exists and has access
- Maestro says user does not exist or has no access

Workspace result:

- show `HQ`
- show `Relay`
- hide `Maestro`

## Responsibility Split

### Workspace owns

- Workspace login/logout/session
- admin-vs-user shell role
- setup surfaces
- installed-app registry
- app visibility filtering
- navbar app-switching surface
- app launch UI
- workspace window/taskbar behavior

### Each PBB app owns

- its own business logic
- its own user authorization rules
- whether a specific user has access
- optional iframe compatibility details

Workspace is a shell and launcher, not a replacement for each app's internal authz model.

## Architecture

### Frontend composition

Workspace should use a windowed shell with embedded app surfaces and a parent/child UI bridge for top-level overlays.

Recommended composition includes:

- `ui.window`
- `ui.iframe.host`
- `ui.workspace.bridge`

Launch model:

- app card clicked
- Workspace opens a `ui.window`
- the window body hosts a `ui.iframe.host`
- iframe app uses a trusted parent/child UI bridge for top-level toasts/dialogs when needed

### Backend composition

Workspace backend should own:

- Workspace-local users and sessions
- app registry persistence
- app-access checks
- normalized launcher payload returned to the frontend

The browser should not directly query every app for access checks.

## Required V1 Contracts

V1 needs these additional documents/contracts:

1. app registry spec
2. app access-check spec

Those are the minimal seams needed before implementation begins.

## V1 Identity Model

V1 should use Workspace-local users rather than trying to introduce full cross-app SSO immediately.

Reasons:

- lower rollout risk
- simpler shell authorization
- cleaner setup ownership
- no immediate dependency on HQ or cross-app token exchange

Later, Workspace can adopt trusted launch/session exchange if the platform matures in that direction.

## V1 Scope

In scope:

- Workspace-local login/logout
- 2-role model (`Administrator`, `User`)
- admin-only setup
- app registry
- access-filtered launcher
- iframe-in-window launch
- standard PBB navbar
- taskbar/window-based shell UX

Out of scope:

- cross-app SSO
- automatic downstream user provisioning
- automatic app installation/discovery from filesystem scanning
- pinned apps / desktop persistence
- workspace-wide auth token brokering

## Success Criteria

V1 is successful if:

- an administrator can configure registered apps
- an administrator can manage Workspace users
- a normal user sees only the apps that confirm access
- the navbar `Apps` menu shows only the apps that confirm access
- apps open inside managed workspace windows
- workspace-level shell UX is consistent
- embedded apps can delegate top-level helper overlays to the parent shell

## Follow-On Specs

V1 implementation should be driven by:

- `docs/pbb-workspace-app-registry-v1-spec.md`
- `docs/pbb-workspace-app-access-check-v1-spec.md`
