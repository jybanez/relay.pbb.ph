# UI Form Modal Improvement Proposal

## Purpose

Capture concrete improvement opportunities for `ui.form.modal` in `helpers.pbb.ph` based on real usage in `PBB - HQ`.

This project has already moved these flows onto the helper form modal system:

- Login
- Session Re-authentication
- Setup
- GeoData Coordinate Editor
- Users Add/Edit

That refactor work confirms that `ui.form.modal` is already useful for small and medium forms. It also makes the remaining gaps clear.

## Why This Proposal Exists

The current helper form modal is strong for:

- simple field-driven forms
- standard validation
- helper-owned busy state
- modal consistency
- compact CRUD forms

But some important project flows still remain custom because the current API is not expressive enough yet.

Current custom holdouts in this project:

- Account
- Hub Add/Edit
- Add Uplink
- Hub Details

The main reason is not styling. It is form expressiveness.

## Current Strengths

The current `ui.form.modal` already provides meaningful value:

- helper-owned modal shell
- helper-owned submit/busy handling
- inline field validation
- form-level error support
- small schema-driven configuration
- preset wrappers such as:
  - `ui.form.modal.login`
  - `ui.form.modal.reauth`

This is a solid base and should continue to be the preferred direction.

## Current Limitations Observed In This Project

### 1. No First-Class Conditional Field Behavior

There is currently no clean helper contract for:

- required only in create mode
- optional in edit mode
- hidden when another field has a certain value
- readonly when another field has a certain value
- label/help/placeholder changes based on mode

This is the main reason `Hub Add/Edit` is still custom.

Real example:

- `deployment = other`
  - `name` should be editable
- `deployment = region/province/city/barangay`
  - `name` should not be editable
  - visible value should be display-only
  - submitted value should still exist as a hidden field

That pattern is awkward to express in the current form modal API.

### 2. Missing Hidden And Read-Only Display Field Types

The current field set covers:

- input
- textarea
- select
- checkbox

But practical forms also need:

- `hidden`
- `display`
- `badge`
- `summary`

Real examples:

- hidden generated `name` in geodata hub forms
- readonly context values that should look like information, not inputs
- contextual deployment/location summary above a form

Right now these require custom markup outside the form modal schema.

### 3. Native Select Is Too Limited For Relationship Flows

The current built-in `select` is fine for short static lists such as `role`.

It is not sufficient for:

- searchable option lists
- larger option sets
- async option loading
- richer option rendering

This is one reason `Add Uplink` still remains custom.

The helper already has stronger selection tools elsewhere. Form modal should be able to use them cleanly.

### 4. Layout Model Is Too Narrow For More Expressive Forms

The current modal warns when a row has more than 2 items and only renders the first 2.

This is fine for small forms, but eventually limiting.

Needed capabilities:

- explicit column span
- full-width vs half-width fields
- section grouping
- fieldset/subsection headings
- compact context rows

This would reduce the need for app-local content wrappers.

### 5. No First-Class API Validation Error Mapping

The current context already supports:

- `setErrors(...)`
- `setFormError(...)`

That is useful, but app code still has to translate backend validation responses manually.

Recommended improvement:

- helper-supported mapping from common API validation error shapes into field/form errors

This would reduce repeated app-side error plumbing.

### 6. Limited Mode Semantics

Many forms naturally have modes:

- create
- edit
- rotate
- confirm-with-reason

The current API can support this indirectly, but only through ad hoc app logic.

Recommended improvement:

- a first-class `mode`
- field rules that can depend on mode

Example:

- `requiredOn: ["create"]`
- `hiddenOn: ["edit"]`
- `readonlyOn: ["edit"]`

### 7. Context Blocks Need Better Support

Some forms need contextual information above the editable fields.

Examples:

- deployment badge
- location summary
- explanatory status block
- warning block tied to the form

Current `text` and `alert` items help, but they are not expressive enough for richer context strips used in real forms.

## Recommended Additions

### 1. Conditional Field Rules

Support field-level rules such as:

```js
{
  type: "input",
  name: "password",
  label: "Password",
  requiredWhen: ({ mode }) => mode === "create",
  helpWhen: ({ mode }) =>
    mode === "create"
      ? "Required when creating a user."
      : "Leave blank to keep the current password.",
}
```

Or simpler declarative variants:

```js
{
  name: "password",
  requiredOn: ["create"],
  optionalOn: ["edit"],
}
```

### 2. Better Helper Component Integration

Recommended direction:

- `hidden`
- `display`
- `context`
- `group`
- `ui.select`

Recommended principle:

- use existing helper component names when the library already has a component for the job
- only add new form-modal-native item types when there is no existing helper component to integrate

So the request here is not to invent a separate select system inside `ui.form.modal`.

It is to let `ui.form.modal` host existing helper-owned components more directly.

Example:

```js
{
  type: "display",
  name: "location_label",
  label: "Location",
  value: "CEBU CITY, CEBU",
}
```

```js
{
  type: "hidden",
  name: "name",
  value: "CEBU CITY, CEBU",
}
```

### 3. Rich Select Integration

Allow `ui.form.modal` to use the existing helper `ui.select` capabilities directly as a field type.

Example direction:

```js
{
  type: "ui.select",
  name: "uplink_hub_ids",
  label: "Uplinks",
  multiple: true,
  searchable: true,
  options: [...],
}
```

Or:

```js
{
  type: "ui.select",
  name: "uplink_hub_id",
  searchable: true,
  loadOptions: async (query) => [...],
}
```

This keeps the form-modal contract aligned with the helper library vocabulary and ownership model.

### 4. Better Layout Controls

Suggested additions:

- `span: 1 | 2`
- `rowClassName`
- `section`

Example:

```js
{
  type: "input",
  name: "domain",
  label: "Domain Name",
  span: 2,
}
```

### 5. Backend Error Mapping Helper

Recommended helper context addition:

```js
ctx.applyApiErrors(response);
```

This should map common backend responses into:

- field errors
- form error

without repeating boilerplate in every app.

### 6. Explicit Mode Support

Recommended modal-level option:

```js
createFormModal({
  mode: "edit",
  rows: [...],
});
```

Then field definitions can depend on that mode.

### 7. Context/Header Strip Support

Recommended helper-level support for compact context above the form body.

Example:

```js
createFormModal({
  title: "Edit Hub",
  context: {
    kind: "badge-summary",
    badge: "CITY",
    summary: "CEBU CITY, CEBU",
  },
  rows: [...],
});
```

This would remove a lot of app-local custom markup for structured form context.

If the helper library already introduces a reusable context/header component later, the form modal should reference that helper-owned component or preset by name instead of creating a parallel modal-only concept.

## Concrete Modals These Improvements Would Unlock

### 1. Hub Add/Edit

Would likely become helper-form-modal compatible if the helper supports:

- hidden fields
- display fields
- context strip
- conditional readonly/hidden/required rules

This is the highest-value unlock.

### 2. Add Uplink

Would likely move over if the helper supports:

- `ui.select` integration
- async option handling

### 3. Account

Would become cleaner with:

- explicit mode semantics
- create/edit style conditional password rules

### 4. Hub Token Actions

Smaller token-oriented actions could eventually use:

- status/reason/context presets
- richer readonly/display fields

## Recommended Design Principle

`ui.form.modal` should remain schema-driven and compact, but it needs enough expressive power to cover real admin forms without forcing apps back into custom modal markup.

Recommended principle:

- keep simple forms simple
- add progressive capability for real-world conditional forms
- prefer helper-owned component names and integrations over app-local embedded controls
- only introduce new modal-native item types when the helper library does not already own an equivalent component

## Recommended Rollout

1. Add `mode` support
2. Add `hidden` and `display` field types
3. Add conditional rules (`requiredWhen`, `hiddenWhen`, `readonlyWhen`)
4. Add `span` and simple layout extensions
5. Add richer select integration
6. Add API error mapping helper
7. Add context/header strip support

## Recommendation

Continue pushing projects toward `ui.form.modal`, but improve it so more medium-complexity admin forms can move onto the helper instead of remaining custom.

For `PBB - HQ`, the most valuable unlock would be making `Hub Add/Edit` and `Add Uplink` eligible for refactor into the shared helper form-modal system.

## Related Follow-Up Documents

- Response memo:
  - `docs/ui-form-modal-improvement-response-memo.md`
- Implementation checklist:
  - `docs/ui-form-modal-improvement-implementation-checklist.md`
