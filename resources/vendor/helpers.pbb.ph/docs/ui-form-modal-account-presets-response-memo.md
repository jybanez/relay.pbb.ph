# UI Form Modal Account Presets Response Memo

## Summary

`PBB HQ` proposed adding shared account-related presets over `createFormModal(...)`:

- `createAccountFormModal(...)`
- `createChangePasswordFormModal(...)`

The proposal is directionally correct.

The important refinement from HQ is also correct:

- the account preset should not be extensible through extra fields only
- the extensibility point should be additive `extraRows`

That matches how `createFormModal(...)` already works and avoids inventing a narrower field-only side contract that would immediately fail on text, alert, divider, display, or context rows.

## Decision

Proceed with a narrow preset proposal, but keep the presets lightweight wrappers.

Recommended order:

1. `createChangePasswordFormModal(...)`
2. `createAccountFormModal(...)`

Why this order:

- change-password is narrower
- it has fewer extension concerns
- it reuses the already-landed `ui.password` behavior directly
- it gives a cleaner first acceptance target before introducing account-row composition

## Recommended V1 Contract

### `createAccountFormModal(options)`

Helper-owned baseline:

- title: `Account`
- submit label: `Save`
- rows:
  - `Name`
  - `Email`

App-owned extension points:

- `initialValues`
- `extraRows`
- `extraActions`
- `extraActionsPlacement`
- `onSubmit`

### `createChangePasswordFormModal(options)`

Helper-owned baseline:

- title: `Change Password`
- submit label: `Update Password`
- rows:
  - `Current Password`
  - `New Password`
  - `Confirm Password`

App-owned pieces:

- `initialValues`
- field remapping if needed
- `onSubmit`

## Recommendation Against Overreach

Do not turn account presets into app frameworks.

The helper should not own:

- backend endpoints
- success toasts
- submit-side payload mapping
- cross-project account-policy rules
- project-specific profile schemas

The helper should only own:

- stable shared row structure
- shared password-field behavior
- helper-owned modal/busy/validation behavior
- additive row/footer extension points

## Priority Decision

This proposal is more urgent than `ui.icons`.

Why:

- it comes from a real HQ flow
- it is built directly on top of the already-active `ui.form.modal` work
- it addresses likely-repeated account/profile behavior across PBB browser apps

So the recommended queue is:

1. account/change-password preset proposal
2. account/change-password preset implementation
3. return to `ui.icons`
