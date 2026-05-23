# UI Icons V1 Implementation Checklist

## Runtime

- [x] Add `js/ui/ui.icons.catalog.js`
- [x] Add `js/ui/ui.icons.js`
- [x] Add `css/ui/ui.icons.css`
- [x] Support:
  - [x] `createIcon(name, options)`
  - [x] `getIconDefinition(name)`
  - [x] `listIcons()`
  - [x] `listIconCategories()`

## Loader

- [x] Register `ui.icons` in `js/ui/ui.loader.js`

## Demo

- [x] Add `demos/demo.icons.html`
- [x] Add shared navigation entry under `Utilities`
- [x] Add home catalog card on `demos/index.html`

## Docs

- [x] Update `README.md`
- [x] Update `CHANGELOG.md`
- [x] Keep `docs/ui-icons-proposal.md` as the forward proposal

## Regression

- [x] Add `tests/icons.regression.html`
- [x] Add `tests/icons.regression.mjs`
- [x] Verify registry integrity and SVG creation
