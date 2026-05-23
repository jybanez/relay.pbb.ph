# Helpers Schema Form Modal Counter-Proposal

## Purpose

Refine the original schema-form modal proposal into a tighter V1 that fits the current `helpers.pbb.ph` architecture and avoids turning the first release into a generic form-builder platform.

This counter-proposal keeps the core goal:

- reduce repeated modal + form assembly work across PBB projects
- standardize busy/error/submit behavior
- let app code focus on data and endpoint behavior

But it narrows the contract so V1 is implementable, defensible, and easier to keep stable.

## Position

The original proposal is directionally correct.

The part that needs tightening is not the motivation.
It is the scope and contract shape.

The right V1 should be:

- modal-form focused
- schema-driven
- built on top of `createActionModal(...)`
- strict about supported field and layout patterns

It should not try to become:

- a general visual form builder
- a layout engine
- a replacement for app-owned domain validation

## Recommended Name

Recommended public factory:

- `createFormModal(...)`

Recommended loader key:

- `ui.form.modal`

Reason:

- aligns with current factory naming like `createModal(...)`, `createActionModal(...)`
- still allows a namespaced loader entry
- avoids inventing a new `ui.action.modal` concept that does not exist in the current library

## Architectural Rule

This utility must compose over:

- `createActionModal(...)`

It must reuse existing helper contracts for:

- modal shell
- busy state
- footer/header actions
- close behavior
- focus handling
- shared form feedback styling

It must not:

- duplicate modal shell logic
- fork a second action-button contract
- bypass helper-owned busy-state behavior

## Problem Statement

Across PBB projects, the same repeated workflow appears:

1. open a modal
2. render one or more explanatory text blocks
3. render a few inputs
4. capture values
5. show field or form errors
6. submit asynchronously
7. lock the modal while busy
8. close on success or remain open on failure

This is a shared pattern and belongs in the helper library.

## Counter-Proposal Summary

Add a focused schema-driven modal-form helper that supports:

- a small set of body item types
- predictable row-based layout
- helper-owned value collection
- field errors
- form-level error
- helper-owned submit busy lifecycle

Defer:

- arbitrary nested layout trees
- raw CSS-like layout control
- advanced validation engines
- dynamic condition builders

## V1 Design Principle

If a form requires:

- highly custom body markup
- advanced conditional sections
- complex dependent layout behavior
- large workflow orchestration

Then the app should still use `createModal(...)` or `createActionModal(...)` directly.

This helper should target:

- login modals
- re-auth dialogs
- simple create/edit forms
- short CRUD flows

## Recommended V1 Schema

Use `rows`, not deeply nested `lines + grid + content` trees.

Recommended shape:

```js
const formModal = createFormModal({
  title: "Create Person",
  rows: [
    [
      {
        type: "text",
        content: "Please enter the required information below.",
      },
    ],
    [
      {
        type: "input",
        input: "text",
        name: "firstname",
        label: "First Name",
        placeholder: "Enter first name",
        value: data.firstname,
        required: true,
      },
      {
        type: "input",
        input: "text",
        name: "lastname",
        label: "Last Name",
        placeholder: "Enter last name",
        value: data.lastname,
        required: true,
      },
    ],
    [
      {
        type: "input",
        input: "number",
        name: "age",
        label: "Age",
        value: data.age,
        min: 16,
        max: 88,
        required: true,
      },
    ],
    [
      {
        type: "select",
        name: "yearofbirth",
        label: "Year of Birth",
        value: data.yearofbirth,
        required: true,
        options: yearOptions,
      },
    ],
  ],
  submitLabel: "Save",
  cancelLabel: "Cancel",
  onSubmit(values, ctx) {
    return savePerson(values);
  },
});
```

## Why `rows`

This is the right V1 compromise:

- easy to read
- enough for one-column and two-column modal forms
- no need for a nested layout DSL yet

Each row is:

- an array of items

The helper renders the row width split evenly unless a small token like `span` is introduced later.

## Supported V1 Item Types

Recommended V1 types:

- `text`
- `alert`
- `divider`
- `input`
- `textarea`
- `select`
- `checkbox`

Defer from V1:

- `grid`
- `custom`
- `radio-group`
- `multi-select`
- `date-range`
- arbitrary nested item trees

## Supported V1 Input Types

Recommended:

- `text`
- `email`
- `password`
- `number`
- `date`
- `url`
- `search`

## Supported V1 Field Properties

Recommended:

- `name`
- `label`
- `value`
- `placeholder`
- `required`
- `disabled`
- `readonly`
- `autocomplete`
- `min`
- `max`
- `step`
- `options`
- `help`

Optional, but still acceptable in V1:

- `inputmode`
- `pattern`

Do not add in V1:

- raw `style`
- arbitrary `className` per field
- layout-style freeform width strings

## Recommended Layout Contract

Keep the layout model minimal.

V1 should support:

- one field in a row
- two fields in a row
- mixed informational rows and field rows

If a row has:

- one item: render full width
- two items: render two equal columns
- more than two items: reject or normalize conservatively

That restriction is useful.
It prevents the schema from turning into layout soup.

## Runtime API

Recommended API:

```js
const formModal = createFormModal({
  title: "Login",
  rows: [...],
  onSubmit(values, ctx) {
    return apiLogin(values);
  },
});

formModal.open();
formModal.close();
formModal.destroy();

formModal.getValues();
formModal.setValues({ email: "user@example.com" });

formModal.setErrors({ password: "Invalid password." });
formModal.clearErrors();

formModal.setFormError("Unable to continue.");
formModal.clearFormError();

formModal.setBusy(true, { message: "Signing in..." });
formModal.isBusy();
```

## Recommended Returned Surface

Minimum useful instance methods:

- `open()`
- `close(meta?)`
- `destroy()`
- `getValues()`
- `setValues(values)`
- `setErrors(fieldErrors)`
- `clearErrors()`
- `setFormError(message)`
- `clearFormError()`
- `setBusy(isBusy, options?)`
- `isBusy()`

Optional later:

- `focusField(name)`
- `getField(name)`

## Submit Contract

Recommended:

```js
onSubmit(values, ctx)
```

Where `ctx` includes:

- `modal`
- `setErrors`
- `setFormError`
- `clearErrors`
- `clearFormError`

Behavior:

1. gather values
2. run helper-side required validation
3. if invalid:
   - do not submit
   - render field errors
4. if valid:
   - enter busy state
   - call `onSubmit(values, ctx)`
5. if submit resolves truthy:
   - close unless `closeOnSuccess === false`
6. if submit resolves falsy or rejects:
   - keep modal open
   - leave error rendering to app or helper result contract

## Validation Contract

V1 validation should stay narrow.

Helper-owned:

- required fields
- basic native/browser validity where practical

App-owned:

- domain validation
- server response validation
- business rules

That split is important.

## Error Contract

The helper should support:

- field errors
- form-level error

Recommended shape:

```js
{
  fieldErrors: {
    password: "Invalid password."
  },
  formError: "Unable to continue."
}
```

Recommended API support:

- `setErrors({ fieldName: "message" })`
- `setFormError("...")`

The helper should render:

- field error under the field
- form error near the submit area or top of the form body

It should prefer shared styling like:

- `.ui-form-error`

## Actions Contract

Do not expose a fully custom `actions[]` schema in V1 unless there is a strong need.

V1 is cleaner if the helper owns the standard form actions:

- cancel
- submit

Recommended options:

- `cancelLabel`
- `submitLabel`
- `submitVariant`
- `submitIcon`
- `cancelIcon`

Reason:

- nearly every schema-form modal is a cancel/submit flow
- opening full footer action customization immediately increases contract surface too early

If advanced action customization is needed later, it can be added in V2.

## Busy-State Behavior

This helper must rely on the existing modal busy contract.

When busy:

- inputs are not interactive
- submit/cancel are disabled as appropriate
- duplicate submits are blocked
- the modal follows the existing `createModal(...)` busy/close rules

This is already a solved shared problem in the library.
The form helper should reuse it, not reinvent it.

## Accessibility Expectations

The helper should:

- render native form controls
- preserve label/input association
- expose field errors in readable text
- keep focus behavior consistent with `ui.modal`
- move focus to the first invalid field when helper validation fails

Do not over-claim with fake form semantics.
Use real form controls.

## Styling Guidance

The helper should reuse shared styling primitives before introducing new ones.

Recommended:

- shared input classes
- shared button variants
- `.ui-form-error`
- shared spacing and panel conventions

If repeated form layout wrappers appear, then add small shared primitives.
Do not start by inventing a full schema-layout style system.

## Immediate Use Cases

Best first migrations:

1. login modal
2. session re-auth modal
3. simple create user modal
4. simple edit record modal

These are small, repeated, and easy to validate.

## Explicit Non-Goals

V1 is not:

- a page form engine
- a dynamic condition builder
- a drag-and-drop form designer
- an arbitrary layout DSL
- a replacement for app-specific workflow UIs

## Risks In The Original Proposal

### 1. Too much layout too early

The original `lines + grid + content + rows + cols + gap + span + align + width` direction is already close to a general layout engine.

That is not the right V1.

### 2. Too much action customization too early

If every form modal also exposes arbitrary action arrays immediately, the component surface gets large before the core form contract is proven.

### 3. Validation ambiguity

The original proposal gestures at helper validation and app validation, but the boundary needs to be explicit.

## Recommended V1 Rollout

1. implement `createFormModal(...)`
2. compose over `createActionModal(...)`
3. support:
   - `text`
   - `alert`
   - `divider`
   - `input`
   - `textarea`
   - `select`
   - `checkbox`
4. support:
   - helper-required validation
   - field errors
   - form error
   - busy submit lifecycle
5. migrate:
   - login modal
   - one simple CRUD modal
6. evaluate what layout or action flexibility is actually still missing

## Recommendation

Proceed with a schema-driven modal-form helper, but narrow the first release.

The right target is:

- form-modal utility
- schema-driven body
- helper-owned submit and busy lifecycle
- limited row-based layout

not:

- general-purpose form builder
- layout framework
- second modal system

## Final Summary

The original proposal identifies a real shared problem and should move forward.

The safer implementation path is:

- build on `createActionModal(...)`
- keep the schema small
- keep layout rules strict
- reuse helper-owned busy/error/modal behavior
- prove the contract with login and one CRUD modal before broadening it

That gives the team a realistic V1 with strong reuse value and lower design risk.
