# UI Workspace Overlay Routing V1 Spec

## Summary

`ui.workspace.bridge` V1 introduced explicit bridge primitives.

This follow-on V1 adds helper-owned automatic overlay routing for modal-family helpers when a trusted same-origin Workspace host is installed.

## Goals

- make helper modal-family overlays automatically render in the parent Workspace surface when safe
- keep local rendering as the fallback
- keep cross-origin behavior honest
- avoid widening the bridge into a generic DOM RPC system

## Supported Automatic Routing

The following helpers are in scope:

- `createModal(options)`
- `createActionModal(options)`
- `createFormModal(options)`
- form-modal presets that compose over `createFormModal(...)`

## Existing Explicit Bridge Surfaces

These continue to use the explicit bridge/runtime already shipped:

- `createToastStack(...)`
- `uiAlert(...)`
- `uiConfirm(...)`
- `uiPrompt(...)`
- `showWorkspaceActionModal(...)`

## Shared Option

Overlay-producing helpers should support:

- `renderTarget: "auto" | "local" | "parent"`

Default:

- `"auto"`

## Routing Rules

### `renderTarget: "local"`

- always render inside the current document
- do not use parent portal
- do not use automatic bridge routing

### `renderTarget: "auto"`

Resolution order:

1. explicit `parent` option, if supplied
2. trusted same-origin Workspace overlay parent, if available
3. helper’s existing behavior

### `renderTarget: "parent"`

Resolution order:

1. explicit `parent` option, if supplied
2. trusted same-origin Workspace overlay parent, if available
3. helper’s existing fallback behavior

`"parent"` is a preference, not a hard crash-on-failure mode.

## Same-Origin Parent Portal Contract

Automatic modal-family routing depends on a trusted same-origin parent host.

The installed Workspace host must expose:

- `window.__uiWorkspaceBridgeHost`
- `getOverlayParent()`

That parent element becomes the mount target for helper modal-family overlays.

## Document/Focus Rules

When a modal is mounted into the parent Workspace document:

- focus trapping must bind to the mounted document
- body-lock behavior must apply to the mounted document body
- focus restore should use the mounted document’s prior active element

## Fallback Rules

If a trusted same-origin Workspace overlay parent is not available:

- modal-family helpers render locally
- explicit bridge-aware primitives continue to behave as they do today

## Accessibility Requirements

- modal semantics remain unchanged
- focus trap still applies
- `aria-modal`, `aria-labelledby`, and busy-state behavior remain intact
- parent-routed modal-family overlays must remain keyboard operable

## Regression Requirements

Add browser coverage for:

- local modal rendering when no Workspace host is installed
- automatic parent rendering for plain `createActionModal(...)`
- automatic parent rendering for plain `createFormModal(...)`
- no duplicate child copy when parent rendering is used

## Operational Note

Because this feature changed the live modal/bridge ES-module chain, downstream apps may need both:

- a refreshed vendored helper copy
- a hard browser refresh

to ensure stale cached modules do not keep older local-rendering behavior alive after rollout.

## Non-Goals

This V1 does not add automatic cross-origin routing for arbitrary form or modal DOM.

That remains outside the helper contract.
