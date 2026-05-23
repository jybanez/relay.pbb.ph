# UI Iframe Host V1 Implementation Checklist

## Docs

- [x] Draft proposal in `docs/ui-iframe-host-proposal.md`
- [x] Draft V1 spec in `docs/ui-iframe-host-v1-spec.md`
- [x] Draft implementation checklist in `docs/ui-iframe-host-v1-checklist.md`
- [x] Update `README.md` with:
  - project-structure entries
  - helper summary entry
  - dedicated `createIframeHost(...)` section
- [x] Update `CHANGELOG.md` with the iframe-host release note
- [x] Update `docs/pbb-refactor-playbook.md` with helper-first guidance for iframe hosting

## Runtime

- [x] Add `js/ui/ui.iframe.host.js`
- [x] Add `css/ui/ui.iframe.host.css`
- [x] Implement helper-owned DOM structure:
  - root host
  - loading surface
  - error surface
  - iframe surface
- [x] Implement source normalization for `src` / `srcdoc`
- [x] Implement deterministic helper-owned error state for empty/invalid source
- [x] Implement public API:
  - `root`
  - `iframe`
  - `getSrc()`
  - `setSrc(url)`
  - `reload()`
  - `update(options)`
  - `getState()`
  - `destroy()`
- [x] Keep `ui.window` generic; do not add iframe-specific logic there
- [x] Ensure authored iframe-host CSS respects helper-owned hidden-state toggles for:
  - loading surface
  - error surface
  - iframe surface
- [x] Support full-bleed composition inside `ui.window` without pushing iframe-specific lifecycle logic into the window manager

## Loader / Demo Surface

- [x] Register `ui.iframe.host` in `js/ui/ui.loader.js`
- [x] Add a dedicated demo page:
  - `demos/demo.iframe.host.html`
- [x] Use a same-origin fixture file in the dedicated demo for deterministic browser behavior:
  - `samples/iframe/iframe-host.fixture.html`
- [x] Add shared-nav entry under `Utilities`
- [x] Add home-catalog card in `demos/index.html`

## Regression

- [x] Add browser harness:
  - `tests/iframe.host.regression.html`
  - `tests/iframe.host.regression.mjs`
- [x] Verify:
  - root/iframe render
  - `srcdoc` ready state
  - empty-source error state
  - `setSrc(validUrl)`
  - `reload()` stability
  - `destroy()` cleanup
  - hidden loading/error surfaces do not visually override ready iframe content

## Cross-Project Follow-Through

- [x] Inform other teams through `C:\wamp64\www\pbb\chat_log.md`
- [x] Include the helper-refresh reminder pointing teams to:
  - `https://github.com/jybanez/helpers.pbb.ph.git`
