# Helpers Schema Form Modal V1 Checklist

## Purpose

Turn `docs/helpers-schema-form-modal-v1-spec.md` into an execution checklist for implementation and validation.

This document is intentionally operational.

Use it to track build order, acceptance criteria, and migration readiness.

## Reference Spec

Primary reference:

- `docs/helpers-schema-form-modal-v1-spec.md`

Supporting context:

- `docs/helpers-schema-form-modal-proposal.md`
- `docs/helpers-schema-form-modal-counter-proposal.md`
- `docs/helpers-schema-form-modal-response-memo.md`

## 1. API Surface

### Required

- [ ] Add `createFormModal(...)` factory
- [ ] Add loader key `ui.form.modal`
- [ ] Keep architecture composed over `createActionModal(...)`
- [ ] Do not fork modal shell logic

### Acceptance criteria

- [ ] The public factory name is `createFormModal(...)`
- [ ] The helper can be loaded via `uiLoader.get("ui.form.modal")`
- [ ] The implementation reuses existing modal busy/close/focus behavior

## 2. Body Schema

### Required

- [ ] Accept `rows`
- [ ] Each row is an array of items
- [ ] Support only V1 item types:
  - [ ] `text`
  - [ ] `alert`
  - [ ] `divider`
  - [ ] `input`
  - [ ] `textarea`
  - [ ] `select`
  - [ ] `checkbox`

### Acceptance criteria

- [ ] One-item rows render full width
- [ ] Two-item rows render equal columns
- [ ] More-than-two-item rows are rejected or normalized conservatively
- [ ] Unsupported types fail clearly for the developer

## 3. Field Rendering

### Required

- [ ] Render native form controls where applicable
- [ ] Render label + control association correctly
- [ ] Render help text when provided
- [ ] Render field-level error area
- [ ] Render form-level error area

### Acceptance criteria

- [ ] Inputs can render:
  - [ ] `text`
  - [ ] `email`
  - [ ] `password`
  - [ ] `number`
  - [ ] `date`
  - [ ] `url`
  - [ ] `search`
- [ ] Select options render from `options`
- [ ] Checkbox value is tracked correctly
- [ ] Error text uses shared styling such as `.ui-form-error`

## 4. Value Model

### Required

- [ ] Maintain current form state internally
- [ ] Key values by `name`
- [ ] Support initial values
- [ ] Support programmatic value updates

### Required API

- [ ] `getValues()`
- [ ] `setValues(values)`

### Acceptance criteria

- [ ] Returned values match current form inputs
- [ ] `setValues(...)` updates controls and internal state
- [ ] Missing field names are handled safely

## 5. Error Model

### Required API

- [ ] `setErrors(fieldErrors)`
- [ ] `clearErrors()`
- [ ] `setFormError(message)`
- [ ] `clearFormError()`

### Acceptance criteria

- [ ] Field errors render under matching fields
- [ ] Form error renders in a stable shared location
- [ ] Clearing errors removes visible feedback cleanly

## 6. Validation

### Required

- [ ] Implement helper-owned required validation
- [ ] Use native/browser validity where practical
- [ ] Do not embed app-specific domain rules

### Acceptance criteria

- [ ] Required empty fields block submit
- [ ] First invalid field receives focus
- [ ] Validation errors do not close the modal

## 7. Submit Lifecycle

### Required

- [ ] Support `onSubmit(values, ctx)`
- [ ] Gather values before submit
- [ ] Clear helper-side errors before submit
- [ ] Enter busy state during async submit
- [ ] Keep modal open on falsy or rejected submit
- [ ] Close on truthy submit unless `closeOnSuccess === false`

### `ctx` should include

- [ ] `modal`
- [ ] `setErrors`
- [ ] `clearErrors`
- [ ] `setFormError`
- [ ] `clearFormError`
- [ ] `setBusy`
- [ ] `isBusy`

### Acceptance criteria

- [ ] Duplicate submit is blocked while busy
- [ ] Failed submit leaves the form usable
- [ ] Successful submit closes normally by default

## 8. Busy-State Integration

### Required

- [ ] Reuse existing modal busy contract
- [ ] Do not create a second busy overlay system
- [ ] Disable submit while busy
- [ ] Respect close-while-busy policy inherited from modal

### Required API

- [ ] `setBusy(isBusy, options?)`
- [ ] `isBusy()`

### Acceptance criteria

- [ ] Busy visual state is owned by the modal shell
- [ ] Form controls are non-interactive while busy
- [ ] Busy state does not break modal open-state classes or close behavior

## 9. Actions

### V1 scope

- [ ] Keep public action contract narrow
- [ ] Support standard:
  - [ ] cancel
  - [ ] submit

### Supported options

- [ ] `cancelLabel`
- [ ] `submitLabel`
- [ ] `submitVariant`
- [ ] `cancelIcon`
- [ ] `submitIcon`

### Acceptance criteria

- [ ] No arbitrary public `actions[]` schema in V1 unless explicitly approved
- [ ] Cancel and submit behavior remain predictable

## 10. Accessibility

### Required

- [ ] Use native form controls
- [ ] Preserve label/input association
- [ ] Keep focus behavior consistent with `ui.modal`
- [ ] Focus first invalid field on validation failure

### Acceptance criteria

- [ ] Keyboard-only use remains viable
- [ ] Error text is visible and readable
- [ ] Focus restore on close still works

## 11. Documentation

### Required

- [ ] Document `createFormModal(...)` in `README.md`
- [ ] Document loader key in `README.md`
- [ ] Add release note in `CHANGELOG.md`
- [ ] Add maintenance rules in `docs/pbb-refactor-playbook.md`

### Acceptance criteria

- [ ] Docs clearly separate helper validation from app validation
- [ ] Docs clearly state V1 row limits
- [ ] Docs clearly state supported V1 item types

## 12. Demo

### Required

- [ ] Add a dedicated demo or a clear demo section
- [ ] Include:
  - [ ] login modal
  - [ ] failed submit with field error
  - [ ] failed submit with form-level error
  - [ ] successful async submit
  - [ ] two-column row example

### Acceptance criteria

- [ ] Demo is understandable without reading source first
- [ ] Demo shows busy-state behavior explicitly
- [ ] Demo shows error handling explicitly

## 13. First Migrations

### Recommended

- [ ] login modal
- [ ] session-expired re-login modal
- [ ] one simple CRUD modal

### Acceptance criteria

- [ ] At least one real app flow validates the contract before V2 expansion
- [ ] Migration findings are fed back into docs before widening the API

## 14. Explicit Deferred Items

Do not pull these into V1 without a deliberate contract update:

- [ ] nested layout trees
- [ ] `grid` container schema
- [ ] arbitrary column counts
- [ ] arbitrary public footer action arrays
- [ ] dynamic visibility rules
- [ ] advanced validation DSL
- [ ] custom render hooks

## 15. Completion Gate

V1 is ready only when all of the following are true:

- [ ] implementation exists
- [ ] docs are updated
- [ ] demo exists
- [ ] busy/error/value behavior works
- [ ] at least one real migration has been validated

If those are not true, the feature is not done.
