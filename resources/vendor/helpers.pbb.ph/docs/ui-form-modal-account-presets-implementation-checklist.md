# UI Form Modal Account Presets Implementation Checklist

## Contract

- [x] Review HQ proposal and confirm `extraRows` is the correct account extension point
- [x] Keep both presets as wrappers over `createFormModal(...)`
- [x] Preserve app-owned submit behavior
- [x] Keep `extraActions` / `extraActionsPlacement` compatible with the recently landed footer-action contract

## Runtime

- [x] Add `createAccountFormModal(options)` to `js/ui/ui.form.modal.presets.js`
- [x] Add `createChangePasswordFormModal(options)` to `js/ui/ui.form.modal.presets.js`
- [x] Add field-map support for both presets
- [x] Add additive `extraRows` support to the account preset
- [x] Reuse `input: "password"` rows so `ui.password` behavior comes through automatically

## Demos

- [x] Add dedicated account preset demo page
- [x] Add dedicated change-password preset demo page
- [x] Show HQ-style account footer flow:
  - `Change Password`
  - `Cancel`
  - `Save`
- [x] Show an `extraRows` example with a warning row and one extra field

## Docs

- [x] Add helper-side response memo
- [x] Add V1 spec
- [x] Update `README.md`
- [x] Update `CHANGELOG.md`
- [x] Update `docs/pbb-refactor-playbook.md`

## Regression

- [x] Add browser regression for account preset baseline rows
- [x] Add browser regression for `extraRows`
- [x] Add browser regression for change-password preset rows
- [x] Add browser regression for shared password toggle behavior in change-password preset

## Cross-Project Communication

- [x] Add helper-side review note to `C:\wamp64\www\pbb\chat_log.md`
- [x] State the helper-side priority decision relative to `ui.icons`
