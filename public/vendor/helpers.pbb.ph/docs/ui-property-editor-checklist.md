# UI Property Editor Checklist

## Proposal And Scope

- [x] Draft property-editor proposal.
- [x] Draft V1 property-editor spec.
- [x] Confirm boolean property support should include both `checkbox` and `toggle`.

## Runtime

- [x] Add `ui.property.editor` runtime helper.
- [x] Add section rendering.
- [x] Add selection header rendering.
- [x] Add property-row layout with label/editor/action cells.
- [x] Support V1 property kinds:
  - `display`
  - `text`
  - `textarea`
  - `number`
  - `checkbox`
  - `toggle`
  - `select`
  - `ui.select`
  - `color`
  - `color-select`
  - `action`
  - `divider`
- [x] Add read-only handling.
- [x] Add mixed-value handling.
- [x] Add property-level error rendering.
- [x] Add structured `onPropertyChange(...)` callbacks.
- [x] Add structured `onAction(...)` callbacks.
- [x] Add instance methods:
  - `update(...)`
  - `setSections(...)`
  - `setSelectionLabel(...)`
  - `getState()`
  - `setErrors(...)`
  - `clearErrors()`
  - `destroy()`

## Demo

- [x] Add dedicated `demo.property.editor.html`.
- [x] Document the V1 options and methods in the reference panel.
- [x] Show mixed, read-only, toggle, select, color, and action rows in one realistic inspector.

## Regression

- [x] Add browser regression coverage for section and row rendering.
- [x] Add regression for `checkbox` and `toggle` changes.
- [x] Add regression for read-only and mixed-value presentation.
- [x] Add regression for property-level errors.
- [x] Add regression for `onAction(...)` callbacks.

## Docs

- [x] Update `README.md`.
- [x] Update `CHANGELOG.md`.
- [x] Update `docs/pbb-refactor-playbook.md`.
- [x] Update demo/catalog navigation.
