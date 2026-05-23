# Helpers Schema Form Modal Proposal

## Purpose

Propose a reusable helpers-library component for schema-driven modal forms so PBB projects can define form UI declaratively instead of hand-building modal DOM and field markup for every workflow.

This is intended for:

- browser-side form modals
- helpers-based UI projects
- repeated create/edit/login/re-auth workflows
- teams that want to focus on data and behavior instead of modal/form assembly

It is not intended to replace:

- highly custom full-page forms
- advanced workflow builders
- domain-specific validation rules owned by the application

## Problem

Across PBB projects, the same pattern keeps repeating:

- open a modal
- render one or more text blocks
- render a few fields
- show inline validation or an error message
- provide cancel/submit actions
- collect values
- submit through `fetch`

Today, this usually means:

- manually creating DOM nodes
- manually styling field stacks
- manually wiring busy states
- manually syncing values into the submit payload
- manually repeating the same modal-form structure in multiple apps

This causes:

- duplicate implementation effort
- inconsistent spacing and field rendering
- custom wrappers that should probably live in helpers
- slower feature work in app repos

## Proposal Summary

Add a schema-driven modal form utility to the helpers library.

Suggested names:

- `ui.form.modal`
- `ui.action.form`
- `ui.modal.form`

Recommended direction:

- build it on top of `ui.action.modal`
- let the app describe the body as structured lines/blocks
- let the helper own rendering, value collection, and standard form behavior

## Core Idea

The component accepts a declarative schema.

The schema is made of:

- lines
- each line contains one or more renderable items
- each item has a `type`
- the `type` determines how it is rendered

Example mental model:

1. text line
2. grouped fields line
3. standalone input line
4. select line

So instead of hand-building DOM:

```js
const modal = createFormModal({
  title: "Create Person",
  lines: [
    [
      { type: "text", content: "Please enter the required information below." },
    ],
    [
      {
        type: "grid",
        cols: 2,
        content: [
          {
            type: "input",
            input: "text",
            label: "First Name",
            name: "firstname",
            placeholder: "Enter Firstname",
            value: data.firstname,
            required: true,
          },
          {
            type: "input",
            input: "text",
            label: "Last Name",
            name: "lastname",
            placeholder: "Enter Lastname",
            value: data.lastname,
            required: true,
          },
        ],
      },
    ],
    [
      {
        type: "input",
        input: "number",
        label: "Age",
        name: "age",
        value: data.age,
        min: 16,
        max: 88,
        required: true,
      },
    ],
    [
      {
        type: "select",
        label: "Year of Birth",
        name: "yearofbirth",
        value: data.yearofbirth,
        required: true,
        options: yearOptions,
      },
    ],
  ],
  actions: [
    { id: "cancel", label: "Cancel", variant: "ghost" },
    { id: "submit", label: "Save", variant: "primary", submit: true },
  ],
  onSubmit(values) {
    return savePerson(values);
  },
});
```

## Recommended V1 Scope

Keep version 1 intentionally narrow.

Recommended supported item types:

- `text`
- `input`
- `textarea`
- `select`
- `checkbox`
- `divider`
- `grid`
- `alert`

Recommended supported input types:

- `text`
- `email`
- `password`
- `number`
- `date`
- `url`
- `search`

Recommended supported field properties:

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
- `width`

Recommended supported layout properties:

- `rows`
- `cols`
- `gap`
- `span`
- `align`

## Recommended Schema Shape

Two acceptable approaches exist.

### Option A: `lines` is an array of arrays

This is closest to the user’s mental model.

```js
lines: [
  [{ type: "text", content: "..." }],
  [{ type: "input", name: "email", label: "Email address" }],
]
```

### Option B: `lines` is an array of line objects

This is more extensible if line-level metadata is needed later.

```js
lines: [
  {
    items: [{ type: "text", content: "..." }],
  },
  {
    items: [{ type: "input", name: "email", label: "Email address" }],
  },
]
```

Recommendation:

- start with Option A for simplicity
- normalize internally to a line-object model if needed

## Recommended Runtime API

The component should expose modal-like controls plus form-specific helpers.

Example:

```js
const formModal = createFormModal({
  title: "Login",
  lines: [...],
  actions: [...],
  onSubmit(values, ctx) {
    return apiLogin(values);
  },
});

formModal.open();
formModal.close();
formModal.setValues({ email: "user@example.com" });
formModal.setErrors({ password: "Invalid password." });
formModal.getValues();
formModal.setBusy(true);
formModal.destroy();
```

## Recommended Behavior

### Rendering

The helper should:

- render the modal body from schema
- render consistent field layout and spacing
- own label/input/help/error rendering
- use helpers primitives and styling internally

### Value Collection

The helper should:

- maintain current form state
- collect values by `name`
- expose normalized values during submit

### Validation

The helper should support:

- browser-level required validation
- simple field error injection
- application-supplied validation feedback

Recommended methods:

- `setErrors({ fieldName: "message" })`
- `clearErrors()`

### Submit Lifecycle

For submit actions:

- gather values
- run validation
- call `onSubmit(values, ctx)`
- show busy state through modal action handling
- keep modal open if submit fails
- close if submit succeeds and `closeOnSuccess !== false`

## Recommended Error Handling

The helper should support both:

- field-specific errors
- form-level errors

Examples:

- `password: "Invalid password."`
- form-level: `"Unable to save record."`

Recommended internal contract:

```js
{
  fieldErrors: {
    password: "Invalid password."
  },
  formError: "Unable to continue."
}
```

## Recommended Layout Rules

The helper should support:

- one-field-per-line simple forms
- two-column grouped fields
- mixed informational lines with form lines

Important rule:

- layout should be declarative, but not arbitrary CSS

So instead of raw `style` objects, prefer limited layout tokens like:

- `cols: 2`
- `span: 2`
- `width: "full"`

This keeps the API stable and design-system friendly.

## Recommended Use Cases

This would immediately help with:

- login modal
- session-expired re-login modal
- create client modal
- edit handler modal
- create user modal
- simple admin create/edit dialogs

It is especially strong for:

- short forms
- modal-bound workflows
- repeated CRUD operations

## Suggested Example Types

### Text

```js
{ type: "text", content: "Please enter the required information below." }
```

### Input

```js
{
  type: "input",
  input: "email",
  label: "Email address",
  name: "email",
  placeholder: "name@agency.gov.ph",
  autocomplete: "username",
  required: true,
}
```

### Select

```js
{
  type: "select",
  label: "Role",
  name: "role",
  value: "operator",
  options: [
    { label: "Operator", value: "operator" },
    { label: "Admin", value: "admin" },
  ],
}
```

### Grid

```js
{
  type: "grid",
  cols: 2,
  content: [
    { type: "input", input: "text", name: "firstname", label: "First Name" },
    { type: "input", input: "text", name: "lastname", label: "Last Name" },
  ],
}
```

### Alert

```js
{
  type: "alert",
  tone: "danger",
  content: "Unable to save this record.",
}
```

## Why This Should Live In Helpers

This is a cross-project concern, not a relay-specific one.

Reasons:

- the pattern repeats across multiple PBB projects
- the helpers library already owns modal/action behavior
- this reduces custom DOM-building in app repos
- it improves visual and behavioral consistency
- it aligns with the goal of using helpers for UI so app code focuses on functionality

## Risks

### 1. Over-designing the schema

If the first version tries to solve every form case, it will become too broad.

Mitigation:

- keep V1 small
- support common modal forms only

### 2. Becoming a generic form builder

This should not turn into a full no-code engine.

Mitigation:

- optimize for modal CRUD/login forms
- leave complex flows to app code

### 3. Excessive styling flexibility

If raw CSS-level control is exposed, consistency will suffer.

Mitigation:

- prefer helper-owned layout primitives
- expose limited layout tokens instead of arbitrary style config

## Recommended Next Step

If the helpers team agrees, implement a V1 prototype with:

1. `ui.action.modal` as the shell
2. `text`, `input`, `select`, `grid`, `alert` support
3. value collection
4. field error rendering
5. async submit lifecycle

Then migrate one or two real PBB forms first:

- login modal
- create client modal

That will validate whether the API is strong enough before broader adoption.

## Summary

This proposal adds a schema-driven modal form utility to helpers so PBB apps can define modal forms declaratively and stop rebuilding the same modal+form wiring in every project.

The right target is:

- not a giant form-builder platform
- not relay-specific glue
- but a focused helpers component for repeated modal form workflows

This would let projects describe:

- what fields exist
- how they are grouped
- what actions happen on submit

without repeatedly hand-building the same UI structure.

## Comparison Note

A narrower counter-proposal is available at:

- `docs/helpers-schema-form-modal-counter-proposal.md`

The current implementation-facing V1 spec is:

- `docs/helpers-schema-form-modal-v1-spec.md`

The wrapper/preset layer built on top of the V1 helper is:

- `docs/helpers-schema-form-modal-v1-presets-spec.md`

Main differences in the counter-proposal:

- composes explicitly over `createActionModal(...)`
- recommends `createFormModal(...)` as the factory shape
- uses a stricter `rows` model instead of broader `lines + grid + content` nesting
- keeps V1 field/layout support intentionally narrow
- adds explicit `formError` handling to the runtime API
- avoids turning V1 into a general-purpose form builder too early

This proposal remains useful as the broader problem statement and feature vision.
The counter-proposal is the tighter implementation target for a first release.
