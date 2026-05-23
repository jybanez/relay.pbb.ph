# UI Workspace Overlay Routing Proposal

## Purpose

Define the next helper-side layer above `ui.workspace.bridge` so apps using shared helper overlays can adapt automatically when running inside a Workspace-hosted iframe.

This proposal exists because the current bridge is explicit and primitive-focused:

- toast delegation
- alert / confirm / prompt delegation
- explicit simple action-modal delegation

That is not enough for the broader goal of letting helper-based apps render top-level overlays in the parent Workspace surface without rewriting each app around manual bridge calls.

## Problem

When a helper-based app is embedded inside a Workspace iframe:

- plain `createActionModal(...)` still renders inside the iframe
- plain `createFormModal(...)` and higher-level presets still render inside the iframe
- dialog and toast delegation only happens where the child uses the already bridge-aware helper entrypoints

So the current parent host is not sufficient on its own.

## Recommendation

Add a helper-owned overlay-routing layer with this V1 behavior:

1. if a trusted same-origin Workspace host is installed, helper modal-family overlays should render into the parent host automatically
2. if that same-origin parent portal is not available, existing bridge-aware primitives keep their current behavior
3. if neither portal nor bridge path is available, helpers render locally as they do today

This keeps the behavior automatic where it is technically safe, while preserving the existing cross-frame bridge for primitives that are already serializable.

## V1 Scope

Automatic routing in V1:

- `createModal(...)`
- `createActionModal(...)`
- `createFormModal(...)`
- presets built over `createFormModal(...)`

Existing explicit bridge behavior remains:

- toast
- alert / confirm / prompt
- explicit simple `showWorkspaceActionModal(...)`

## V1 Boundary

V1 automatic routing should prefer:

- same-origin parent portal only

It should not pretend to support arbitrary cross-origin DOM teleportation for form or custom modal content.

Cross-origin iframe cases should still rely on:

- the existing explicit bridge where supported
- local fallback otherwise

## Why Same-Origin Portal First

Arbitrary modal and form content is not safely serializable:

- DOM nodes
- event handlers
- submit callbacks
- helper-owned busy lifecycle
- field refs and focus behavior

Those all work naturally through a same-origin parent overlay parent, but they do not map cleanly onto a generic cross-origin `postMessage` RPC contract.

So the correct first automation layer is:

- same-origin parent portal

not:

- automatic cross-origin modal mirroring

## Proposed Behavior

### Standalone app

- render overlays locally

### Iframe app + trusted same-origin Workspace host

- render modal-family overlays in the parent Workspace surface automatically
- keep toast/dialog primitives working as before

### Iframe app + no same-origin parent portal

- keep existing explicit bridge behavior for supported primitives
- render unsupported overlays locally

## Configuration

Overlay-producing helpers should accept a shared narrow override:

- `renderTarget: "auto" | "local" | "parent"`

Recommended default:

- `"auto"`

Meaning:

- `"auto"`: prefer parent portal when available, otherwise use the current helper behavior
- `"local"`: always render inside the current document
- `"parent"`: attempt parent portal first, then fall back according to the helper’s current behavior

## Non-Goals

This proposal does not introduce:

- arbitrary parent DOM execution
- cross-origin form-modal mirroring
- auth/session brokering
- layout routing for dropdowns, tooltips, or geometry-bound surfaces

## Rollout

1. draft proposal
2. draft V1 spec
3. draft implementation checklist
4. implement same-origin parent overlay routing for modal-family helpers
5. extend demo and regression coverage
6. update public docs

## Operational Follow-Through

Because the overlay-routing behavior depends on the live ES-module import graph, downstream apps should refresh vendored helper copies and hard-refresh the browser after rollout so stale cached modal/bridge modules do not mask the new behavior.
