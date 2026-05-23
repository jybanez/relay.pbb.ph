# UI Modal Improvement Proposal

## Summary

Improve the shared `ui.modal` shell with two small but high-value UX refinements:

1. header-drag support so modal panels can be moved out of the way while inspecting content behind them
2. optional owner context text below the modal title so Workspace-bridged modals clearly show which window requested them

## Why

Current modal behavior is correct for blocking interaction, but Workspace-style usage exposes two friction points:

- parent-owned bridged modals can hide important content behind them with no way to temporarily move the panel
- once a modal is bridged into the Workspace parent surface, users lose some ownership context unless the shell itself tells them which window initiated it

These are shell concerns, so they belong in `ui.modal`, not in app-local modal clones.

## Proposed V1

### Header Drag

- add shared `draggable` modal support
- drag handle is the modal header only
- dragging clamps the panel within the current viewport
- drag state resets when the modal closes and reopens
- interactive controls in the header must not accidentally start a drag

Recommended default:

- `draggable: true`

Reason:

- Workspace-style UIs benefit immediately
- local apps that do not want drag can still disable it explicitly

### Owner Context

- add optional `ownerTitle`
- render it as a small secondary line below the modal title
- intended primary use:
  - Workspace-bridged parent-owned modals

Example:

- title: `Login`
- owner title: `PBB HQ`

## Proposed Option Shape

```js
createModal({
  title: "Login",
  ownerTitle: "PBB HQ",
  draggable: true,
});
```

## Non-Goals

This proposal does not add:

- resize handles for modals
- modal docking/snapping
- modal persistence across reopen
- arbitrary drag handles outside the header
- window-manager semantics inside `ui.modal`

## Integration Notes

- `createActionModal(...)` and `createFormModal(...)` should inherit the modal-shell behavior automatically
- Workspace bridge form modals should be able to pass `ownerTitle` through the normalized payload
- dialogs can keep the default drag behavior unless later UX review says otherwise

## Validation Targets

- modal can be dragged by the header
- close button and header actions still work normally
- panel stays within viewport bounds
- owner title renders when provided
- owner title stays absent when not provided
