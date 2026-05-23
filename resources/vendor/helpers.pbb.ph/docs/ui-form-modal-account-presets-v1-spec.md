# UI Form Modal Account Presets V1 Spec

## Purpose

Add two narrow account-related presets over `createFormModal(...)`:

- `createAccountFormModal(options)`
- `createChangePasswordFormModal(options)`

These should standardize the common account/profile and password-change modal flows used across PBB browser applications without replacing app-owned submit behavior.

## Factories

```js
import {
  createAccountFormModal,
  createChangePasswordFormModal,
} from "./js/ui/ui.form.modal.presets.js";
```

## `createAccountFormModal(options)`

### Helper-owned defaults

- `title`: `Account`
- `size`: `sm`
- `busyMessage`: `Saving account...`
- `submitLabel`: `Save`
- baseline rows:
  - `Name`
  - `Email`

### App-owned options

- inherited safe `createFormModal(...)` options
- `initialValues`
- `fields`
- `extraRows`
- `extraActions`
- `extraActionsPlacement`
- `onSubmit`

### Field defaults

Default field map:

```js
{
  name: "name",
  email: "email",
}
```

### `extraRows`

Type:

- `Array<Array<FormItem>>`

Default:

- `[]`

Behavior:

- appended after the baseline `Name` / `Email` rows
- supports all normal `createFormModal(...)` row content

## `createChangePasswordFormModal(options)`

### Helper-owned defaults

- `title`: `Change Password`
- `size`: `sm`
- `busyMessage`: `Updating password...`
- `submitLabel`: `Update Password`
- baseline rows:
  - `Current Password`
  - `New Password`
  - `Confirm Password`

### App-owned options

- inherited safe `createFormModal(...)` options
- `initialValues`
- `fields`
- `message`
- `onSubmit`

### Field defaults

Default field map:

```js
{
  currentPassword: "current_password",
  newPassword: "new_password",
  confirmPassword: "confirm_password",
}
```

## Shared Rules

- both presets remain wrappers over `createFormModal(...)`
- helper owns structure, labels, and password-field behavior
- app owns backend submission logic
- truthy submit result closes by default through the base form-modal contract

## Acceptance Targets

### Account preset

- default modal with:
  - `Name`
  - `Email`
- additive warning/instruction row via `extraRows`
- additive field row via `extraRows`
- additive footer action:
  - `Change Password`
- footer split example using:
  - `extraActionsPlacement: "start"`

### Change-password preset

- `Current Password`
- `New Password`
- `Confirm Password`
- shared password toggle behavior
- helper-owned `Cancel` / `Update Password`

## Non-Goals

V1 does not include:

- account-photo upload
- password strength meter
- server-owned password-policy UI
- account field validation beyond base helper behavior
- automatic account/change-password orchestration between the two presets
