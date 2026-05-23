# UI Workspace Cross-Origin Form Bridge Proposal

## Purpose

Define a narrow cross-origin message contract so iframe-hosted PBB apps can request helper-owned form-modal rendering in the parent Workspace shell without trying to mirror arbitrary child DOM into the parent.

This proposal exists because the current automatic overlay routing only applies to trusted same-origin Workspace hosts. Real Workspace deployments such as `workspace.pbb.ph` hosting `hub.pbb.ph` are cross-origin and therefore need an explicit serializable bridge contract.

## Problem

For cross-origin iframe apps:

- child apps cannot mount modal DOM directly into the parent Workspace document
- generic `createFormModal(...)` instances still render inside the iframe
- login, re-auth, and other structured form-modal flows remain visually trapped inside the windowed child app

The right cross-origin fix is not DOM teleportation. It is a typed `postMessage` contract with JSON-safe payloads and results.

## Recommendation

Add a follow-on bridge layer with protocol-style method names and serializable payloads.

Recommended starting method:

- `modal.form.open`

The payload should describe the modal shell and form schema as data only. The child app sends the request, the parent Workspace renders the helper-owned modal, and the parent returns a typed result such as submitted values or close reason.

The payload should also include an explicit discriminator so the parent does not infer purpose from the modal title alone.

Recommended field:

- `intent`

## Naming Rule

Bridge methods should name protocol actions, not helper implementation functions.

Use:

- `modal.form.open`

Do not use:

- `createFormModal.open`

Reason:

- protocol names are easier for all teams to understand
- helper internals can change without forcing a contract rename

## Proposed Message Shape

### Request envelope

```json
{
  "namespace": "pbb.workspace.ui.bridge.v2",
  "phase": "request",
  "id": "msg_form_001",
  "method": "modal.form.open",
  "payload": {}
}
```

### Response envelope

```json
{
  "namespace": "pbb.workspace.ui.bridge.v2",
  "phase": "response",
  "id": "msg_form_001",
  "ok": true,
  "result": {},
  "error": null
}
```

## Payload Direction

The payload should stay close to the existing `createFormModal(...)` mental model:

- shell options such as `title`, `size`, `submitLabel`, `cancelLabel`, `busyMessage`
- explicit `intent` such as:
  - `login`
  - `reauth`
  - `generic-form` later
- optional `mode`
- optional `context`
- `initialValues`
- serializable `rows[]`

This keeps the contract familiar to teams already using helper-owned form modals while avoiding raw DOM transport.

## Example Direction

```js
{
  title: "Add Hub (Geodata)",
  size: "md",
  submitLabel: "Create Hub",
  cancelLabel: "Cancel",
  busyMessage: "Creating hub...",
  mode: "create",
  context: {
    badge: "CITY",
    summary: "Philippines / Region VII / Cebu / Cebu City / Lahug"
  },
  initialValues: {
    name: "LAHUG, CEBU CITY",
    deployment: "city",
    reg_code: "07",
    status: "planned"
  },
  rows: [
    [{ "type": "display", "name": "name", "label": "Generated Hub Name", "span": 2 }],
    [{ "type": "hidden", "name": "name" }],
    [{ "type": "hidden", "name": "deployment" }],
    [{ "type": "hidden", "name": "reg_code" }],
    [{
      "type": "ui.select",
      "name": "status",
      "label": "Status",
      "items": [
        { "label": "Planned", "value": "planned" },
        { "label": "Online", "value": "online" }
      ]
    }]
  ]
}
```

## JSON-Safe Rule

Anything sent through the bridge must survive `JSON.stringify(...)`.

Allowed:

- strings
- numbers
- booleans
- null
- arrays
- plain objects

Not allowed:

- DOM nodes
- functions
- class instances
- closures
- helper-owned instances

## Child / Parent Responsibility Split

### Parent Workspace owns

- rendering the helper-owned form modal in the parent shell
- returning close / cancel / submit results

### Child app owns

- business logic
- API submission
- auth/session logic
- follow-up state changes
- field-error mapping unless and until a later update contract is defined

This keeps Workspace as UI host, not app business-logic owner.

## Suggested Initial Scope

Strong candidates for first adoption:

- login
- re-auth

Possible next wave:

- account
- change-password
- schema-driven admin forms

V1 runtime implementation should stay limited to:

- login
- re-auth

Schema-style admin forms should remain future-capable in the contract, but not part of the first runtime slice.

## Non-Goals

This proposal does not introduce:

- arbitrary DOM mirroring
- generic cross-origin `createModal(...)` transport for custom DOM content
- remote execution of child callbacks in the parent
- Workspace-owned child API submission

## Rollout

1. draft proposal
2. draft V1 spec
3. draft implementation checklist
4. align with Workspace team on payload/result shape
5. implement a narrow bridge method for `modal.form.open`
6. test against a real cross-origin Workspace + child app pair
