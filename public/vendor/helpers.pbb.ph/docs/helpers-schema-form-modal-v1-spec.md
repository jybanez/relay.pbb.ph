# Helpers Schema Form Modal V1 Spec

## Purpose

Define the first implementation contract for a schema-driven modal-form helper in `helpers.pbb.ph`.

This spec consolidates:

- the original proposal
- the helpers-team counter-proposal
- the response memo

into one implementation-facing document.

The goal is to ship a useful V1 without turning the feature into a generic form-builder system.

## Status

This is the recommended V1 implementation spec.

Use this as the working basis for:

- API definition
- helper implementation
- demo scope
- initial migrations

## Document Index

- Original proposal:
  - `docs/helpers-schema-form-modal-proposal.md`
- Counter-proposal:
  - `docs/helpers-schema-form-modal-counter-proposal.md`
- Response memo:
  - `docs/helpers-schema-form-modal-response-memo.md`
- Implementation checklist:
  - `docs/helpers-schema-form-modal-v1-checklist.md`
- Preset wrappers:
  - `docs/helpers-schema-form-modal-v1-presets-spec.md`

## Problem

Across PBB projects, short modal-bound forms are repeatedly rebuilt by hand:

1. open a modal
2. render text/instructions
3. render a few inputs
4. collect values
5. show field or form errors
6. submit asynchronously
7. lock the modal while busy
8. close or remain open based on submit outcome

This is a repeated shared UI concern and belongs in the helpers layer.

## V1 Goal

Provide a schema-driven modal-form helper for:

- login
- re-auth
- short CRUD forms
- repeated create/edit flows

while preserving:

- helper-owned modal behavior
- helper-owned busy-state behavior
- app-owned domain validation

## Non-Goals

V1 is not:

- a full form-builder platform
- a page-form engine
- a layout framework
- a workflow designer
- a replacement for highly custom form UIs

If the form requires:

- complex conditional sections
- highly custom body markup
- advanced multi-step flow logic
- arbitrary layout behavior

then app code should continue using:

- `createModal(...)`
- `createActionModal(...)`

directly.

## Naming

Recommended public factory:

- `createFormModal(...)`

Recommended loader key:

- `ui.form.modal`

Rationale:

- matches existing helper factory naming
- still supports loader-based access through a namespaced key
- avoids inventing a parallel modal naming scheme

## Architectural Rule

The component must compose over:

- `createActionModal(...)`

It must reuse helper-owned behavior for:

- modal shell
- busy state
- close rules
- focus handling
- footer action behavior
- shared form feedback styling

It must not:

- duplicate modal shell logic
- fork a second action-modal implementation
- bypass existing helper busy-state rules

## Public API

### Factory

```js
const formModal = createFormModal(options);
```

### Example

```js
const formModal = createFormModal({
  title: "Login",
  rows: [
    [
      {
        type: "text",
        content: "Please sign in to continue.",
      },
    ],
    [
      {
        type: "input",
        input: "email",
        name: "email",
        label: "Email address",
        placeholder: "name@agency.gov.ph",
        autocomplete: "username",
        required: true,
      },
    ],
    [
      {
        type: "input",
        input: "password",
        name: "password",
        label: "Password",
        autocomplete: "current-password",
        required: true,
      },
    ],
  ],
  submitLabel: "Sign In",
  cancelLabel: "Cancel",
  onSubmit(values, ctx) {
    return apiLogin(values);
  },
});

formModal.open();
```

## Supported V1 Options

### Modal shell options

All safe applicable `createActionModal(...)` options, including:

- `title`
- `size`
- `className`
- `showCloseButton`
- `closeOnBackdrop`
- `closeOnEscape`
- `busyMessage`
- close-while-busy policy options already supported by `ui.modal`

The form helper must not widen or change the base modal contract.

### Form-specific options

- `rows`
- `submitLabel`
- `cancelLabel`
- `submitVariant`
- `submitIcon`
- `cancelIcon`
- `initialValues`
- `closeOnSuccess`
- `onSubmit(values, ctx)`
- `onChange(values, ctx)` optional

Optional but acceptable:

- `ariaLabel`

## Schema Model

### Top-level structure

Use:

- `rows`

Each row is an array of items.

Example:

```js
rows: [
  [{ type: "text", content: "Please enter the required information below." }],
  [
    { type: "input", input: "text", name: "firstname", label: "First Name" },
    { type: "input", input: "text", name: "lastname", label: "Last Name" }
  ],
  [{ type: "select", name: "role", label: "Role", options: [...] }]
]
```

## Row Layout Contract

V1 row behavior is strict.

### One item in a row

- render full width

### Two items in a row

- render equal-width two-column layout

### More than two items in a row

Recommended behavior:

- reject with a clear developer-facing warning

Acceptable fallback:

- normalize conservatively to stacked layout

But this should not silently create arbitrary multi-column layouts.

## Supported V1 Item Types

### Supported

- `text`
- `alert`
- `divider`
- `input`
- `textarea`
- `select`
- `checkbox`

### Deferred

- `grid`
- `custom`
- `radio-group`
- `multi-select`
- `date-range`
- nested container items
- arbitrary render hooks

## Item Contracts

### `text`

```js
{
  type: "text",
  content: "Please enter the required information below."
}
```

Use for:

- instructions
- helper guidance
- short read-only context

### `alert`

```js
{
  type: "alert",
  tone: "danger",
  content: "Unable to save this record."
}
```

Use for:

- inline helper-owned notices
- static warning/information blocks

### `divider`

```js
{
  type: "divider"
}
```

Use for:

- separating sections in short forms

### `input`

```js
{
  type: "input",
  input: "email",
  name: "email",
  label: "Email address",
  value: "",
  placeholder: "name@agency.gov.ph",
  autocomplete: "username",
  required: true
}
```

Supported `input` values:

- `text`
- `email`
- `password`
- `number`
- `date`
- `url`
- `search`

### `textarea`

```js
{
  type: "textarea",
  name: "notes",
  label: "Notes",
  value: "",
  placeholder: "Add remarks"
}
```

### `select`

```js
{
  type: "select",
  name: "role",
  label: "Role",
  value: "operator",
  options: [
    { label: "Operator", value: "operator" },
    { label: "Admin", value: "admin" }
  ]
}
```

### `checkbox`

```js
{
  type: "checkbox",
  name: "notify",
  label: "Notify assigned teams immediately",
  value: true
}
```

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

Optional but acceptable:

- `inputmode`
- `pattern`

Not supported in V1:

- raw `style`
- arbitrary per-item `className`
- arbitrary width tokens
- arbitrary layout objects

## Value Model

The helper owns current form state.

Values are keyed by field `name`.

Example:

```js
{
  email: "user@example.com",
  password: "secret",
  notify: true
}
```

## Runtime Instance API

Minimum required methods:

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

### Option

```js
onSubmit(values, ctx)
```

### `ctx`

Recommended:

- `modal`
- `setErrors`
- `clearErrors`
- `setFormError`
- `clearFormError`
- `setBusy`
- `isBusy`

### Submit behavior

1. gather values
2. clear old helper-side validation errors
3. run helper-side required/basic validation
4. if invalid:
   - keep modal open
   - show errors
   - focus first invalid field
5. if valid:
   - enter busy state
   - call `onSubmit(values, ctx)`
6. if submit resolves truthy:
   - close unless `closeOnSuccess === false`
7. if submit resolves falsy:
   - keep modal open
8. if submit rejects:
   - keep modal open

The helper must not assume how domain/server errors are shaped beyond the explicit API provided by app code.

## Validation Contract

V1 validation must remain narrow.

### Helper-owned

- required validation
- basic native/browser validity where practical

### App-owned

- business rules
- server validation
- domain-specific constraints

This boundary must stay explicit in docs and implementation.

## Error Contract

The helper supports:

- field-specific errors
- form-level error

### Recommended field error API

```js
formModal.setErrors({
  password: "Invalid password."
});
```

### Recommended form error API

```js
formModal.setFormError("Unable to continue.");
```

### Rendering rules

- field error renders under the field
- form error renders in a stable shared form-error area

Use shared styling such as:

- `.ui-form-error`

## Busy-State Contract

The form helper must reuse the existing modal busy-state contract.

When busy:

- inputs are not interactive
- duplicate submits are blocked
- submit is disabled
- cancel is disabled or follows the modal close-while-busy rules
- the helper relies on helper-owned modal busy presentation

Do not implement a second busy overlay system inside the form helper.

## Actions Contract

V1 keeps actions narrow.

The helper owns standard form actions:

- cancel
- submit

Recommended options:

- `cancelLabel`
- `submitLabel`
- `submitVariant`
- `submitIcon`
- `cancelIcon`

Do not expose a full arbitrary `actions[]` schema in V1 unless a real migration proves it is needed.

Reason:

- the majority of target modal forms are standard cancel/submit flows
- early action flexibility expands the surface too quickly

## Accessibility Contract

The helper should:

- render native form controls
- preserve label/input association
- render errors as readable text
- keep focus behavior consistent with `ui.modal`
- focus the first invalid field on helper validation failure

Do not overstate semantics.
Use standard HTML form controls where possible.

## Styling Contract

The helper should prefer existing shared styling first.

Reuse:

- shared input styling
- shared button variants
- shared modal spacing
- `.ui-form-error`

If repeated wrappers are needed later, extract them into shared primitives rather than exposing raw style controls in the schema.

## Demo Requirements

V1 should ship with at least one dedicated demo covering:

1. login modal
2. failed submit with field error
3. failed submit with form-level error
4. successful async submit
5. two-column row example

The demo must show:

- value collection
- helper validation
- busy state
- close-on-success behavior

## First Migration Targets

Recommended first real migrations:

1. login modal
2. session-expired re-login modal
3. one simple CRUD modal

This is enough to validate:

- row layout
- values contract
- error handling
- busy submit lifecycle
- app integration fit

before broadening the API.

## Explicit Deferred Items

Do not include these in V1:

- nested layout trees
- grid/content container schema
- arbitrary column counts
- fully custom footer action arrays
- dynamic visibility rules
- render hooks for arbitrary HTML blocks
- advanced validation DSL

These can be considered after real migrations expose a concrete need.

## Implementation Guidance

The safest implementation order is:

1. create body renderer for supported V1 item types
2. add row layout renderer
3. add value collection and setters
4. add helper validation
5. add field/form error rendering
6. connect to `createActionModal(...)`
7. connect busy submit lifecycle
8. build demo
9. migrate login modal
10. migrate one CRUD form

## Recommendation

Proceed with V1 using this spec.

This gives the helpers library:

- a useful new shared primitive
- a clear boundary
- a smaller contract to defend
- a safer path to expansion later

## Final Summary

`createFormModal(...)` should ship as a narrow schema-driven modal-form helper built on `createActionModal(...)`.

V1 should focus on:

- row-based layout
- a small item set
- helper-owned submit/busy/error behavior
- short modal forms only

This is the right balance between reuse and discipline.
