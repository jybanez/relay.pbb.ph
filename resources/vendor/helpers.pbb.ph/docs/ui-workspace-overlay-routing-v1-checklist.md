# UI Workspace Overlay Routing V1 Checklist

## Docs

- [x] Draft proposal in `docs/ui-workspace-overlay-routing-proposal.md`
- [x] Draft V1 spec in `docs/ui-workspace-overlay-routing-v1-spec.md`
- [x] Draft implementation checklist in `docs/ui-workspace-overlay-routing-v1-checklist.md`
- [x] Update `README.md` with:
  - automatic parent overlay routing behavior
  - `renderTarget` option
  - same-origin portal boundary
- [x] Update `CHANGELOG.md`
- [x] Update `docs/pbb-refactor-playbook.md`

## Runtime

- [x] Extend `ui.workspace.bridge` host to expose a same-origin overlay parent
- [x] Add helper-owned resolution for trusted same-origin Workspace overlay parent
- [x] Integrate `createModal(...)` with automatic parent overlay routing
- [x] Ensure modal document/focus/body-lock behavior follows the mounted document
- [x] Let `createActionModal(...)` inherit the new routing automatically
- [x] Let `createFormModal(...)` and presets inherit the new routing automatically
- [x] Keep local fallback when no same-origin parent portal is available

## Demo Surface

- [x] Extend `samples/iframe/workspace-ui-bridge.fixture.html` with plain-helper modal examples
- [x] Update `demos/demo.workspace.bridge.html` to describe the new automatic-routing behavior

## Regression

- [x] Extend `tests/workspace.bridge.regression.html`
- [x] Verify:
  - local fallback for plain helper modals when no host is installed
  - parent rendering for plain `createActionModal(...)`
  - parent rendering for plain `createFormModal(...)`
  - no duplicate child modal copy when parent rendering is active

## Cross-Project Follow-Through

- [x] Announce the new automatic overlay-routing behavior in `C:\\wamp64\\www\\pbb\\chat_log.md`
- [x] Include the helper-refresh reminder pointing teams to:
  - `https://github.com/jybanez/helpers.pbb.ph.git`
- [x] Announce the cache-busting follow-up for Workspace-hosted child apps that still reused stale modal/bridge modules
