# UI Form Modal Footer Actions Implementation Checklist

## Contract

- [x] Confirm HQ acceptance target and preferred API direction
- [x] Keep helper-owned cancel/submit contract intact
- [x] Additive option is `extraActions`, not full footer replacement
- [x] Reserve `cancel` and `submit` IDs
- [x] Add `extraActionsPlacement` with `"start"` / `"end"` behavior

## Runtime

- [x] Add `extraActions` to `createFormModal(...)` options normalization
- [x] Merge `extraActions` before helper-owned cancel/submit actions
- [x] Provide helper-shaped callback context to extra actions
- [x] Default extra actions to non-closing behavior
- [x] Keep busy state helper-owned and disabling the full footer

## Demo

- [x] Add an HQ-style `Account Footer Actions` example to `demos/demo.form.modal.html`
- [x] Show `Change Password`, `Cancel`, `Save` ordering
- [x] Demonstrate nested password-modal launch without closing the account modal

## Docs

- [x] Add helper-side response memo
- [x] Add V1 spec
- [x] Update `README.md`
- [x] Update `CHANGELOG.md`
- [x] Keep playbook guidance aligned with the narrow additive contract

## Regression

- [x] Add browser regression for footer order
- [x] Add browser regression for extra-action callback execution
- [x] Add browser regression for default non-closing behavior
- [x] Add browser regression for busy-state disable behavior

## Cross-Project Communication

- [x] Add helper-side resolution note to `C:\wamp64\www\pbb\chat_log.md`
- [x] Remind teams to refresh local helper copies from the official repo
