# UI Workspace Bridge Proposal

## Purpose

Define a narrow helper-owned bridge so iframe-hosted PBB apps can request top-level workspace UI surfaces from the parent shell instead of rendering those surfaces inside the iframe.

This proposal exists to support future `PBB Workspace` composition over:

- `ui.window`
- `ui.iframe.host`

without forcing each embedded app to invent its own cross-frame UI contract.

## Problem

When a PBB app is rendered inside an iframe:

- toasts are clipped to the iframe box
- dialogs and modals cannot rise above the workspace window stack
- overlay behavior becomes visually inconsistent with the parent workspace

The workspace shell should own the true top-level overlay surfaces. Embedded apps should be able to request those surfaces explicitly and safely.

## Recommendation

Add a narrow `ui.workspace.bridge` helper that provides:

- a parent-side bridge host
- a child-side request helper
- local fallback when no trusted parent bridge is available

This should be explicit and trusted. It should not depend on silent iframe auto-hijacking.

## V1 Scope

V1 should support:

- top-level toast delegation
- top-level alert / confirm / prompt dialog delegation
- explicit action-modal delegation for simple serializable modal payloads
- local fallback when bridge is unavailable

V1 should not support:

- generic DOM-node modal mirroring
- form-modal delegation
- cross-frame shared state
- auth brokering
- arbitrary DOM portals into the parent

## Architecture

### Parent side

Add a bridge host that lives in the top-level workspace page.

Responsibilities:

- install a trusted `postMessage` listener
- validate message origin
- render delegated UI using the existing helper library
- return results to the child frame

### Child side

Add a request helper that lives inside iframe-hosted apps.

Responsibilities:

- detect potential iframe embedding
- probe for a trusted parent bridge
- send explicit UI requests
- fall back to local helper rendering when bridge is unavailable

## Recommended API

### Parent host

```js
const host = installWorkspaceUiBridgeHost({
  trustedOrigins: [window.location.origin, "null"],
});
```

### Child access

```js
const bridge = getWorkspaceUiBridge();
const ok = await bridge.confirm({
  title: "Delete record",
  message: "This cannot be undone.",
});
```

### Explicit simple modal

```js
const result = await showWorkspaceActionModal({
  title: "Session notice",
  message: "Your workspace token is about to expire.",
  actions: [
    { id: "dismiss", label: "Dismiss" },
    { id: "reauth", label: "Re-authenticate", variant: "primary" },
  ],
});
```

## Delegation Rules

### Auto-delegate in V1

- `createToastStack(...)` toast delivery
- `uiAlert(...)`
- `uiConfirm(...)`
- `uiPrompt(...)`

### Explicit-only in V1

- simple action modal requests through `showWorkspaceActionModal(...)`

### Local-only in V1

- `createModal(...)`
- `createFormModal(...)`
- DOM-node modal content
- select menus, popovers, tooltips, and geometry-bound surfaces

## Trust Model

The parent host must validate requests using explicit trusted origins.

Recommended defaults:

- `window.location.origin`
- `"null"` for local browser regression harnesses

The child helper should not assume any parent is safe. It should probe and use the bridge only when the host answers the handshake.

## Demo Expectations

Add a dedicated demo showing:

- a workspace page installing the parent bridge host
- an iframe-hosted child app rendered through `ui.window` + `ui.iframe.host`
- child buttons that trigger:
  - toast
  - alert
  - confirm
  - prompt
  - simple action modal

The visual proof must show those surfaces rendering in the parent workspace, not inside the iframe.

## Regression Expectations

Add browser coverage for:

- successful bridge handshake between parent and iframe child
- delegated toast rendering in the parent
- delegated dialog result round-trip
- delegated action modal result round-trip
- local fallback when no host is installed

## Rollout Order

1. `ui.workspace.bridge` proposal
2. V1 spec
3. implementation checklist
4. runtime implementation
5. demo + regression coverage
6. `PBB Workspace` proposal/spec over:
   - `ui.window`
   - `ui.iframe.host`
   - `ui.workspace.bridge`
