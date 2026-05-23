# `ui.iframe.host` Proposal

## Summary

Introduce a narrow shared iframe-hosting helper for `helpers.pbb.ph` so projects can embed external or sibling PBB applications inside helper-owned surfaces, especially `ui.window`, without repeating raw iframe setup and lifecycle handling per app.

Recommended direction:

- keep `ui.window` generic
- add a separate iframe-host helper
- let future `PBB Workspace` compose:
  - `createWindowManager(...)`
  - `createIframeHost(...)`

This proposal intentionally stops short of SSO, Workspace routing, or cross-app orchestration. It only covers the iframe-hosting surface.

## Problem

If PBB projects start embedding apps inside helper-owned windows without a shared iframe abstraction, each project will re-decide:

- iframe attributes
- loading state
- sizing and overflow behavior
- error fallback behavior
- reload/reset behavior
- cleanup behavior on close/destroy
- security defaults

That will quickly drift and make a future Workspace shell harder to standardize.

## Goals

V1 should provide:

1. helper-owned iframe creation and lifecycle
2. a consistent loading shell
3. a consistent load-error shell
4. a reusable DOM host surface for:
   - `ui.window`
   - modal/drawer demos if ever needed
5. narrow imperative controls:
   - `setSrc(...)`
   - `reload()`
   - `destroy()`
6. safe defaults for common iframe attributes

## Non-Goals

V1 should not include:

- cross-frame message protocols
- automatic title sync from embedded documents
- Workspace app discovery
- authentication brokering
- deep link management
- multi-app launcher logic
- shell-to-app state sharing

## Recommended Architecture

### New helper

- `js/ui/ui.iframe.host.js`
- `css/ui/ui.iframe.host.css`

### Demo

- `demos/demo.iframe.host.html`

### Regression

- `tests/iframe.host.regression.html`
- `tests/iframe.host.regression.mjs`

## API

```js
import { createIframeHost } from "./js/ui/ui.iframe.host.js";
```

### Factory

```js
const host = createIframeHost(options);
```

### Recommended options

- `src`
- `title`
- `loadingText`
- `errorTitle`
- `errorMessage`
- `sandbox`
- `referrerPolicy`
- `allow`
- `allowFullscreen`
- `className`

### Returned API

```js
host.root
host.iframe
host.getSrc()
host.setSrc(url)
host.reload()
host.getState()
host.destroy()
```

### Suggested state shape

```js
{
  src: "...",
  loading: true,
  loaded: false,
  error: null,
}
```

## Composition With `ui.window`

The preferred composition is:

```js
const iframeHost = createIframeHost({
  src: "/pbb/hq/",
  title: "PBB HQ",
});

const win = manager.createWindow({
  title: "PBB HQ",
  content: iframeHost.root,
});
```

Reason:

- `ui.window` remains generic
- iframe lifecycle stays separate
- Workspace shell can compose both without turning `ui.window` into an app-launch subsystem

## Default Behavior

### Loading

Before iframe load completes:

- show helper-owned loading state
- keep host dimensions stable

### Success

After `load`:

- hide loading state
- show iframe

### Error

If the iframe fails to load or is reset to an invalid URL:

- show helper-owned error surface
- keep the root stable

Note:

- browser iframe failure reporting is limited
- V1 should focus on deterministic helper-owned fallback states, not magical detection

## Security Defaults

V1 should encourage explicit configuration for:

- `sandbox`
- `referrerpolicy`
- `allow`

Recommended baseline:

- no unsafe defaults that widen capability silently
- make teams choose when embedded apps need more capability

## Demo Expectations

The demo page should show:

1. simple embedded local page
2. loading state
3. reload action
4. URL swap via `setSrc(...)`
5. composition inside `ui.window`

## Regression Expectations

Need baseline coverage for:

- root renders
- iframe element renders
- `setSrc(...)` updates iframe source
- `reload()` keeps host stable
- `destroy()` clears the host

## Why This Should Happen Before Workspace

`PBB Workspace` depends on a clean embedded-app surface.

Without `ui.iframe.host`, Workspace would be forced to own:

- iframe DOM setup
- loading state
- fallback state
- security defaults
- destroy/reset rules

That would make Workspace too heavy too early.

The correct dependency order is:

1. `ui.window`
2. `ui.iframe.host`
3. `PBB Workspace`
4. Workspace auth / app-launch contracts

## Recommendation

Proceed with `ui.iframe.host` as a narrow helper-owned iframe surface.

Do not fold iframe hosting directly into `ui.window` V1.

Keep the abstractions separate so Workspace can compose them cleanly later.
