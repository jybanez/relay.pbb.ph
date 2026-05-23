# Demo And Docs Reorganization Checklist

This checklist turns the demo/manual reorganization plan into an implementation sequence.

## 1) Scope

- Move demo pages out of the repository root into `demos/`
- Preserve a working root `index.html`
- Improve demo discoverability through a dedicated catalog page
- Introduce structured API reference formatting in `README.md`

## 2) File Structure

- [x] Create `demos/`
- [x] Move current demo pages into `demos/`
- [x] Move current root `index.html` to `demos/index.html`
- [x] Keep root `index.html` as a small landing page or redirect into `demos/index.html`

## 3) Path And Navigation Migration

- [x] Update demo-page relative paths for:
  - [x] `css/*`
  - [x] `js/*`
  - [x] `samples/*`
  - [x] `README.md`
  - [x] `CHANGELOG.md`
  - [x] `docs/*`
- [x] Update `js/demo/demo.shell.js` links to point at `demos/*`
- [x] Ensure the shared demo shell still highlights the active page correctly after the move
- [x] Verify any manual “Back to Home” links point to `./index.html` inside `demos/`

## 4) Demo Catalog

- [x] Rework `demos/index.html` into a clearer catalog page
- [x] Group demos by category
- [x] Keep links to:
  - [x] `README.md`
  - [x] `CHANGELOG.md`
  - [x] `docs/pbb-refactor-playbook.md`
- [x] Add short “use this when” summaries per demo card

## 5) Demo Surface Improvements

- [ ] Keep component demos separate from future pattern/cookbook pages
- [ ] Preserve the shared sticky demo navigation
- [ ] Avoid adding new workflow examples into an overloaded `demo.ui.html` if a dedicated page is a better fit
- [ ] Plan future pages:
  - [ ] `demos/cookbook.html`
  - [ ] `demos/guide.which-helper.html`

## 6) API Reference Structure

- [x] Define a standard API reference format for component sections in `README.md`
- [ ] Apply the format first to:
  - [x] `createModal(...)`
  - [x] `createActionModal(...)`
  - [x] `uiAlert(...)`, `uiConfirm(...)`, `uiPrompt(...)`
  - [x] `createFormModal(...)`
  - [x] form-modal preset wrappers

## 7) API Table Standard

- [ ] Constructor / Factory section
- [ ] Options table
- [ ] Events / Callbacks table
- [ ] Returned API / Methods table
- [ ] Behavior notes
- [ ] Minimal example
- [ ] Related demos

Recommended table shapes:

### Options
| Option | Type | Default | Required | Description |
|---|---|---:|---|---|

### Events / Callbacks
| Callback | Payload | Returns | Description |
|---|---|---|---|

### Methods / Returned API
| Method | Arguments | Returns | Description |
|---|---|---|---|

## 8) Validation

- [ ] Load all demo pages after the move
- [ ] Verify shared demo navigation
- [ ] Verify docs links from demo pages
- [ ] Verify sample JSON fetches still work
- [ ] Verify local WAMP paths
- [ ] Verify GitHub Pages-relative paths
- [ ] Run:
  - [ ] `node tests/registry.contract.mjs`
  - [ ] `node tests/tree.grid.regression.mjs`
  - [ ] `node tests/modal.busy.regression.mjs`
  - [ ] `node tests/form.modal.regression.mjs`
  - [ ] `node tests/form.modal.presets.regression.mjs`

## 9) Rules During Migration

- [ ] Do not change loader keys as part of the demo move
- [ ] Do not mix component contract changes into the reorganization work
- [ ] Keep root entry working throughout the migration
- [ ] Update docs when navigation or integration expectations change
- [ ] If a demo path move breaks a sample fetch or asset import, fix the path instead of introducing duplicate files
