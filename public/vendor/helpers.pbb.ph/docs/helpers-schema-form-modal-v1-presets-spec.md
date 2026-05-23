# Helpers Schema Form Modal Presets Spec

## Purpose

Define the first preset-wrapper layer on top of `createFormModal(...)`.

These presets exist to standardize repeated modal-form workflows across PBB projects without forcing every project to rebuild the same schema and submit lifecycle manually.

This spec assumes:

- `createFormModal(...)` already exists
- presets are thin wrappers over that helper
- structure is helper-owned
- field names and option sets can be caller-provided where needed

## Position

Preset form modals are a good fit for helpers when:

- the flow is repeated across projects
- the field structure is stable
- consistency is valuable
- the wrapper stays narrower than the base form-modal helper

Preset wrappers must not become:

- mini form builders
- alternate schema engines
- domain-specific app glue

## Architectural Rule

All presets must compose over:

- `createFormModal(...)`

They must not:

- bypass the base helper
- fork the row/item contract
- duplicate modal busy/error/submit behavior

## Design Rule

Preset wrappers should allow engineers to provide:

- field names
- labels/placeholders where appropriate
- option lists where appropriate

Preset wrappers should continue to own:

- structure
- field order
- default validation expectations
- default busy labels
- default button labels
- visual consistency

This is the correct balance between:

- reuse
- integration flexibility

## Shared Preset Principles

### 1. Field-name remapping is allowed

Projects cannot be assumed to share the same field names.

So wrappers should accept a `fields` mapping object.

Example:

```js
fields: {
  email: "user_email",
  password: "user_password"
}
```

### 2. Structure remains fixed

The wrapper should define:

- which fields exist
- their order
- which are required
- which are optional

Callers should not be able to arbitrarily reorder or replace the form structure through the preset.

### 3. App still owns submit behavior

Each preset should still accept:

- `onSubmit(values, ctx)`

The wrapper should not own API calls.

### 4. App can still fall back to `createFormModal(...)`

If a project needs a more custom version of a flow, it should use the base helper directly.

## Recommended Presets

### Included in this spec

1. `createLoginFormModal(...)`
2. `createReauthFormModal(...)`
3. `createStatusUpdateFormModal(...)`
4. `createReasonFormModal(...)`

## 1. `createLoginFormModal(...)`

### Purpose

Standard login flow for operator/admin sign-in.

### Structure

1. intro text
2. email/username field
3. password field

### Required fields

- identifier field
- password field

### Recommended options

- `title`
- `message`
- `submitLabel`
- `busyMessage`
- `identifierLabel`
- `identifierPlaceholder`
- `identifierAutocomplete`
- `passwordLabel`
- `passwordPlaceholder`
- `fields`
- `initialValues`
- `onSubmit(values, ctx)`

### Recommended field mapping

```js
fields: {
  identifier: "email",
  password: "password"
}
```

### Default mapping

```js
{
  identifier: "email",
  password: "password"
}
```

### Recommended default layout

```js
rows: [
  [{ type: "text", content: "Please sign in to continue." }],
  [{ type: "input", input: "email", name: "email", label: "Email address", required: true }],
  [{ type: "input", input: "password", name: "password", label: "Password", required: true }]
]
```

### Notes

- the wrapper may support either email-style or username-style identifier labeling
- the field name should remain configurable through `fields.identifier`

## 2. `createReauthFormModal(...)`

### Purpose

Standard re-authentication flow for session expiration or privileged action confirmation.

### Structure

1. explanatory text
2. read-only identifier field or text
3. password field

### Required fields

- password field

### Optional fixed context

- email/username displayed read-only

### Recommended options

- `title`
- `message`
- `submitLabel`
- `busyMessage`
- `identifierValue`
- `identifierLabel`
- `passwordLabel`
- `passwordPlaceholder`
- `fields`
- `onSubmit(values, ctx)`

### Recommended field mapping

```js
fields: {
  identifier: "email",
  password: "password"
}
```

### Default mapping

```js
{
  identifier: "email",
  password: "password"
}
```

### Recommended default layout

```js
rows: [
  [{ type: "text", content: "Your session has expired. Please re-enter your password to continue." }],
  [{ type: "input", input: "email", name: "email", label: "Email address", value: currentUser.email, readonly: true, disabled: true }],
  [{ type: "input", input: "password", name: "password", label: "Password", required: true }]
]
```

### Notes

- the identifier field can be rendered as disabled/read-only input for consistency
- the wrapper still returns values using caller-provided field names

## 3. `createStatusUpdateFormModal(...)`

### Purpose

Standard operational form for changing a record status with optional remarks and notification behavior.

### Structure

1. context text
2. status select
3. remarks textarea
4. optional notify checkbox

### Required fields

- status select

### Optional fields

- remarks
- notify

### Recommended options

- `title`
- `message`
- `submitLabel`
- `busyMessage`
- `statusOptions`
- `defaultNotify`
- `remarksPlaceholder`
- `showNotify`
- `notifyLabel`
- `fields`
- `initialValues`
- `onSubmit(values, ctx)`

### Recommended field mapping

```js
fields: {
  status: "status",
  remarks: "remarks",
  notify: "notify"
}
```

### Default mapping

```js
{
  status: "status",
  remarks: "remarks",
  notify: "notify"
}
```

### Recommended default layout

```js
rows: [
  [{ type: "text", content: "Update the current operational status for this record." }],
  [{ type: "select", name: "status", label: "Status", required: true, options: statusOptions }],
  [{ type: "textarea", name: "remarks", label: "Remarks", placeholder: "Add status notes" }],
  [{ type: "checkbox", name: "notify", label: "Notify affected teams", value: true }]
]
```

### Notes

- `statusOptions` must be app-supplied
- the wrapper should not assume a universal status vocabulary

## 4. `createReasonFormModal(...)`

### Purpose

Standard guarded-action form for cancellations, rejections, overrides, suspensions, or other actions that require structured justification.

### Structure

1. warning/danger alert
2. reason category select
3. reason details textarea
4. optional confirmation phrase input
5. optional notify checkbox

### Required fields

- reason category select
- reason details textarea

### Optional fields

- confirmation phrase input
- notify checkbox

### Recommended options

- `title`
- `message`
- `submitLabel`
- `busyMessage`
- `reasonOptions`
- `detailsLabel`
- `detailsPlaceholder`
- `reasonLabel`
- `confirmPhrase`
- `confirmLabel`
- `showNotify`
- `notifyLabel`
- `fields`
- `initialValues`
- `onSubmit(values, ctx)`

### Recommended field mapping

```js
fields: {
  reasonCode: "reasonCode",
  reasonDetails: "reasonDetails",
  confirmText: "confirmText",
  notify: "notify"
}
```

### Default mapping

```js
{
  reasonCode: "reasonCode",
  reasonDetails: "reasonDetails",
  confirmText: "confirmText",
  notify: "notify"
}
```

### Recommended default layout

```js
rows: [
  [{ type: "alert", tone: "danger", content: "This action may affect downstream operations. Please provide a reason before continuing." }],
  [{ type: "select", name: "reasonCode", label: "Reason Category", required: true, options: reasonOptions }],
  [{ type: "textarea", name: "reasonDetails", label: "Details", required: true, placeholder: "Explain why this action is necessary." }],
  [{ type: "input", input: "text", name: "confirmText", label: "Type CANCEL to confirm", required: true }],
  [{ type: "checkbox", name: "notify", label: "Notify affected users", value: false }]
]
```

### Notes

- `reasonOptions` must be app-supplied for monitoring and reporting purposes
- the select field is important because free-text-only reasons are weak for metrics
- `confirmText` should only be included when the flow is destructive enough to justify it

## Common Preset API Shape

Each preset should return the same instance surface as `createFormModal(...)`.

Example:

```js
const modal = createStatusUpdateFormModal({
  fields: {
    status: "incident_status",
    remarks: "notes",
    notify: "notify_teams"
  },
  statusOptions: [...],
  onSubmit(values, ctx) {
    return apiUpdate(values);
  },
});

modal.open();
modal.setErrors({ incident_status: "Status is required." });
modal.getValues();
```

## Validation Rules

Preset wrappers should preserve the same validation boundary as `createFormModal(...)`.

### Helper-owned

- required validation
- base field rendering
- busy submit lifecycle

### App-owned

- business rules
- domain validation
- server error interpretation

The preset must not hide this boundary.

## What Presets Must Not Do

Presets must not:

- become alternate schema systems
- allow arbitrary structural overrides
- assume project-wide backend field names
- hardcode shared monitoring vocabularies like reason codes or statuses

Those must remain configurable through:

- `fields`
- `statusOptions`
- `reasonOptions`
- label/placeholder overrides where appropriate

## Rollout Recommendation

Recommended order:

1. `createLoginFormModal(...)`
2. `createReauthFormModal(...)`
3. `createStatusUpdateFormModal(...)`
4. `createReasonFormModal(...)`

Reason:

- auth wrappers are easiest to validate first
- status update is highly reusable in operational systems
- categorized reason form is the right next guardrail preset

## Demo Recommendation

Add dedicated examples for:

1. login
2. re-auth
3. status update
4. reason required with app-supplied reason options

Each demo should show:

- default layout
- field-name remapping
- async submit
- error handling

## Final Summary

Prebuilt form-modal presets are a good fit for helpers if they stay:

- thin
- opinionated
- reusable
- layered on top of `createFormModal(...)`

The right wrapper design is:

- helper-owned structure
- engineer-provided field names
- engineer-provided option lists where needed

That preserves both:

- cross-project UX consistency
- practical integration flexibility
