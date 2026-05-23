# UI Workspace Bridge V1 Implementation Checklist

## Docs

- [x] Draft proposal in `docs/ui-workspace-bridge-proposal.md`
- [x] Draft V1 spec in `docs/ui-workspace-bridge-v1-spec.md`
- [x] Draft implementation checklist in `docs/ui-workspace-bridge-v1-checklist.md`
- [x] Update `README.md` with:
  - project-structure entries
  - helper summary entries
  - dedicated bridge section
- [x] Update `CHANGELOG.md` with workspace-bridge release notes
- [x] Update `docs/pbb-refactor-playbook.md` with iframe/workspace bridge guidance

## Runtime

- [x] Add `js/ui/ui.workspace.bridge.js`
- [x] Implement parent-side host:
  - trusted origin validation
  - message request/response handling
  - delegated toast/dialog/action-modal handlers
- [x] Implement child-side request helper:
  - handshake probing
  - request timeout handling
  - explicit action-modal request helper
- [x] Integrate `ui.toast` with automatic delegated delivery + local fallback
- [x] Integrate `ui.dialog` (`uiAlert`, `uiConfirm`, `uiPrompt`) with automatic delegated delivery + local fallback
- [x] Keep `createModal(...)` and `createFormModal(...)` local in V1

## Loader / Demo Surface

- [x] Register bridge exports in `js/ui/ui.loader.js`
- [x] Add dedicated demo page:
  - `demos/demo.workspace.bridge.html`
- [x] Add same-origin iframe fixture page for the child app:
  - `samples/iframe/workspace-ui-bridge.fixture.html`
- [x] Add shared-nav entry under `Utilities`
- [x] Add home-catalog card in `demos/index.html`

## Regression

- [x] Add browser harness:
  - `tests/workspace.bridge.regression.html`
  - `tests/workspace.bridge.regression.mjs`
- [x] Verify:
  - bridge handshake
  - delegated toast round-trip
  - local fallback when no host is installed
- [ ] Add stable browser assertions for:
  - delegated dialog round-trip
  - delegated action-modal round-trip

## Cross-Project Follow-Through

- [x] Inform other teams through `C:\\wamp64\\www\\pbb\\chat_log.md`
- [x] Include the helper-refresh reminder pointing teams to:
  - `https://github.com/jybanez/helpers.pbb.ph.git`
