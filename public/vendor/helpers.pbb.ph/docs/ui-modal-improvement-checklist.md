# UI Modal Improvement Checklist

## Docs

- [x] Draft proposal in `docs/ui-modal-improvement-proposal.md`
- [x] Update `README.md`
- [x] Update `CHANGELOG.md`
- [x] Update `docs/pbb-refactor-playbook.md`

## Runtime

- [x] Add `draggable` to `createModal(...)`
- [x] Make header drag the only drag handle
- [x] Keep drag disabled for header button interactions
- [x] Clamp drag movement to viewport bounds
- [x] Reset drag position on close/reopen
- [x] Add optional `ownerTitle`
- [x] Render `ownerTitle` below the main modal title
- [x] Let `createActionModal(...)` inherit the shell behavior
- [x] Let `createFormModal(...)` inherit the shell behavior
- [x] Pass `ownerTitle` through Workspace-bridged form modal rendering

## Demo

- [x] Update `demos/demo.modal.html` to show draggable behavior
- [x] Show owner-title example in the modal demo

## Regression

- [x] Add browser coverage for header drag affordance and reset behavior
- [x] Verify drag does not start from the close button
- [x] Verify owner title rendering
- [x] Verify drag reset after close/reopen
