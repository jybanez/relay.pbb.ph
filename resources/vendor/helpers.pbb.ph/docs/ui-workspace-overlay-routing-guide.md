# UI Workspace Overlay Routing Guide

## Purpose

This guide is the plain operational rule set for teams running helper-based apps inside PBB Workspace.

Use it when deciding whether a helper-owned overlay should:

- render locally inside the child app
- render in the parent Workspace surface automatically
- render in the parent Workspace surface through the explicit cross-origin bridge

## Core Rule

Use helper APIs normally first.

The helper library owns the routing decision.

That decision currently works like this:

1. standalone app
- render locally

2. Workspace-hosted same-origin iframe app
- eligible helper overlays may parent-render automatically

3. Workspace-hosted cross-origin iframe app
- only supported bridge-aware overlays render in the parent Workspace surface
- unsupported overlays stay local

## What Teams Should Assume

Do not assume that every modal, dialog, toast, and helper surface will automatically bridge just because the app is running inside Workspace.

The correct assumption is:

- helper-owned overlays should render in the Workspace parent surface where the helper contract explicitly supports it
- if the child app opens a plain local cross-origin modal path that is not bridge-capable, it will still render inside the iframe

## Current Helper-Owned Routing Matrix

### Auto or Explicitly Parent-Owned In Workspace

- `createToastStack(...)`
- `uiAlert(...)`
- `uiConfirm(...)`
- `uiPrompt(...)`
- same-origin:
  - `createModal(...)`
  - `createActionModal(...)`
  - `createFormModal(...)`
  - preset wrappers built over `createFormModal(...)`
- cross-origin:
  - `intent: "login"`
  - `intent: "reauth"`
  - `intent: "account"`
  - `intent: "change-password"`
  - `intent: "generic-form"`

### Stays Local By Design

- dropdowns
- popovers
- tooltips
- context menus
- arbitrary custom DOM modal content that is not covered by the explicit cross-origin bridge contract

## Same-Origin Rule

When the child iframe and Workspace are same-origin:

- helper-owned modal-family overlays can mount into the parent Workspace surface automatically
- teams do not need to write explicit bridge requests for the normal modal-family helpers

## Cross-Origin Rule

When the child iframe and Workspace are on different origins:

- automatic parent DOM mounting is not available
- only explicit serializable bridge contracts can render in the parent Workspace surface

That is why cross-origin admin forms such as:

- `Add User`
- `Edit User`
- `Add App`
- `Edit App`

must use the helper-owned cross-origin form bridge if parent-owned Workspace rendering is required.

## Required Cross-Origin Path For Admin Forms

Use:

- `intent: "generic-form"`
- `modal.form.open` for one-shot open and result flows
- `modal.form.session.open`
- `modal.form.update`
- `modal.form.close`

Use the session-style path whenever the child app needs the parent modal to stay open while the child runs async submit logic and pushes:

- busy state
- busy message
- field errors
- form-level errors

## Practical Guidance For Teams

### If You Want Workspace Parent Rendering

Use helper-owned bridge-capable flows for:

- login
- reauth
- account
- change-password
- schema-style admin forms through `generic-form`

### If You Keep Using Plain Local Cross-Origin Modal Calls

Expect the modal to render inside the child iframe.

That is not a Workspace failure.
It means the child flow has not moved onto the bridge-capable helper path yet.

## Ownership Context

Parent-owned bridged modals should preserve visible ownership context.

Current helper behavior:

- bridged modal shells can render `ownerTitle`
- preset-based bridged flows default `ownerTitle` from the child `document.title` if the app does not pass one explicitly

If the subtitle is missing:

- first verify the child app sets a meaningful `document.title`

## Local Validation Before Team Rollout

Before asking Workspace, HQ, or any other downstream repo to validate a cross-origin bridge change:

1. run the local two-origin harness
2. run the automated cross-origin regression

References:

- `docs/ui-workspace-cross-origin-demo-harness.md`
- `node scripts/run-workspace-bridge-cross-origin-demo.mjs`
- `node tests/workspace.bridge.cross.origin.regression.mjs`

## Recommended Team Instruction

For apps that may run inside Workspace:

- treat helper-owned overlay routing as a shared helper contract
- do not invent app-local parent/child overlay rules
- do not assume local cross-origin `createFormModal(...)` calls will parent-render automatically
- move admin forms that need Workspace parent rendering onto the helper-owned `generic-form` bridge/session path
