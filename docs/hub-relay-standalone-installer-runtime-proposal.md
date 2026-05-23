# Hub Relay Standalone Installer Runtime Proposal

## Purpose

Define the intended refactor of the browser installer runtime from a full Laravel payload into a smaller standalone PHP application.

This proposal exists because the first packaged installer build exposed unnecessary duplication:

- `installer-runtime/` currently carried nearly the same app tree as the real Relay release
- `relay-release/relay-release.zip` also carried the real Relay app tree
- that makes the deployable installer larger, slower to extract, and harder to reason about

## Core Direction

The installer runtime should be a temporary standalone PHP application, not another Laravel app.

Recommended split:

- root `index.php`
  - permanent bootstrap and handoff
- `installer-runtime/`
  - lightweight standalone PHP installer
  - temporary only
- `relay-release/relay-release.zip`
  - the actual Laravel Relay application to be installed

## Why Standalone PHP Is Better

- smaller `installer.zip`
- less duplicated code between installer and installed app
- faster first extraction on weak hosts
- fewer framework dependencies before installation is complete
- clearer cleanup and self-removal boundary
- easier to audit as a temporary setup runtime

## Runtime Responsibilities

The standalone installer runtime still needs to cover the full install flow:

- environment readiness checks
- HQ Hub ID + token validation
- install settings capture
- release extraction into the final installed app root
- `.env` writing
- DB verification
- Relay migrations
- first admin provisioning
- install lock creation
- cleanup manifest creation and execution

## Recommended Runtime Layout

```text
installer-runtime/
├─ public/
│  ├─ index.php
│  ├─ relay-installer/
│  └─ vendor/helpers.pbb.ph/
├─ src/
│  └─ RelayInstallerRuntime.php
├─ views/
│  └─ shell.php
└─ manifest.json
```

## Routing Model

The standalone runtime should own these routes while install mode is active:

- `GET /`
- `GET /install`
- `GET /install/api/environment`
- `POST /install/api/environment/continue`
- `POST /install/api/hq/validate`
- `POST /install/api/settings`
- `POST /install/api/execute`
- `POST /install/api/cleanup`

Static asset paths should also be handled without vhost changes:

- `/relay-installer/*`
- `/vendor/helpers.pbb.ph/*`

## Bootstrap And Domain Behavior

The target deployment requirement remains:

- same domain for installer and installed Relay app
- no extra server/vhost changes after initial webroot setup

Recommended mechanism:

- root `.htaccess` rewrites non-file requests to root `index.php`
- root `index.php` serves as permanent dispatcher
- before install, requests are handed to the standalone installer runtime
- after install, requests are handed to `.relay/app/public/index.php`
- static files for both modes are served through the same root bootstrap decision path

## Current Refactor Status

This proposal is now partially implemented in the package builder direction:

- builder output is being shifted toward a standalone `installer-runtime/`
- root package now also needs a permanent `.htaccess`
- the standalone runtime should remain API-compatible with the current installer front-end contract where practical

## Immediate Refactor Goal

The next correct implementation baseline is:

1. package a standalone PHP `installer-runtime/`
2. keep `relay-release.zip` as the only full Laravel application payload
3. keep the current installer front-end contract stable enough that the existing UI can still drive the flow
4. keep local repo Laravel-based installer code available for development/testing until the standalone runtime fully replaces it
