# UI Form Modal Footer Actions V1 Spec

## Purpose

Allow narrow additive footer actions in `createFormModal(...)` without replacing the helper-owned cancel/submit contract.

## API

```js
const formModal = createFormModal({
  title: "Account",
  submitLabel: "Save",
  extraActions: [
    {
      id: "change-password",
      label: "Change Password",
      variant: "ghost",
      onClick(values, ctx) {
        openChangePasswordModal(values.email);
        return false;
      },
    },
  ],
});
```

## Option Shape

### `extraActions`

Type:

- `Array<FormModalExtraAction>`

Default:

- `[]`

### `extraActionsPlacement`

Type:

- `"start" | "end"`

Default:

- `"end"`

### `FormModalExtraAction`

Supported properties:

- `id`
- `label`
- `variant`
- `className`
- `icon`
- `iconPosition`
- `iconOnly`
- `ariaLabel`
- `busyMessage`
- `disabled`
- `autoFocus`
- `closeOnClick`
- `onClick(values, ctx)`

Reserved IDs:

- `cancel`
- `submit`

Reserved IDs are ignored with a helper warning.

## Footer Order

Footer actions render in this order:

1. `extraActions` in provided order
2. helper-owned `Cancel`
3. helper-owned `Submit`

Placement behavior:

- `"end"`
  - keeps all footer actions in the same end-side cluster
- `"start"`
  - visually splits the last extra action away from helper-owned `Cancel` and `Submit`
  - intended for cases such as `Change Password | Cancel | Save`

## Callback Contract

`extraActions[].onClick(values, ctx)` receives:

- `values`
  - current form values
- `ctx.modal`
- `ctx.mode`
- `ctx.action`
- `ctx.actionId`
- `ctx.event`
- `ctx.getValues()`
- `ctx.setValues(values)`
- `ctx.getState()`
- existing helper utilities:
  - `ctx.setErrors(...)`
  - `ctx.clearErrors()`
  - `ctx.setFormError(...)`
  - `ctx.clearFormError()`
  - `ctx.applyApiErrors(...)`
  - `ctx.setBusy(...)`
  - `ctx.isBusy()`

## Close Rules

- helper-owned submit:
  - unchanged
- helper-owned cancel:
  - unchanged
- extra actions:
  - default to `closeOnClick: false`
  - only close when `closeOnClick: true` is explicitly provided and the callback does not return `false`

## Busy Rules

- busy state remains helper-owned
- all footer actions, including `extraActions`, become non-interactive during busy state
- V1 does not support busy-safe footer extras

## Backward Compatibility

- existing form-modal usage without `extraActions` remains unchanged
- `actions` is still not a public app-owned replacement contract for `createFormModal(...)`

## Demo Target

- `demos/demo.form.modal.html`
  - account footer-actions acceptance example

## Regression Target

- `tests/form.modal.regression.html`
  - order
  - callback execution
  - non-closing default
  - busy-state disable behavior
