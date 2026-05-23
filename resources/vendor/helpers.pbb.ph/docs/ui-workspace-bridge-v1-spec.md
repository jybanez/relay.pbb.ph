# UI Workspace Bridge V1 Spec

## Purpose

Define the implementation-facing V1 contract for delegated workspace UI surfaces between a parent shell and iframe-hosted child apps.

This helper provides explicit delegation for:

- toasts
- promise dialogs
- simple serializable action modals

while preserving local fallback behavior when no trusted bridge is available.

## Scope

V1 supports:

- parent-side bridge host installation
- child-side bridge probing and request helpers
- delegated toast delivery
- delegated alert / confirm / prompt dialogs
- delegated simple action-modal requests
- local fallback for standalone or non-bridged execution

V1 does not support:

- generic modal DOM mirroring
- `createFormModal(...)` delegation
- selection/popover/menu delegation
- auth/session brokering
- arbitrary cross-frame method invocation

## Public API

### `installWorkspaceUiBridgeHost(options)`

Installs the parent-side bridge host in the top-level workspace page.

```js
const host = installWorkspaceUiBridgeHost(options);
```

### Host options

| Option | Type | Default | Description |
|---|---|---:|---|
| `trustedOrigins` | `string[]` | `[window.location.origin, "null"]` | Allowed child origins. |
| `toastOptions` | `object` | `{}` | Passed to the parent-owned toast stack. |
| `parent` | `HTMLElement` | `document.body` | Parent surface for top-level modals/dialogs. |

### Host return value

| Property / Method | Returns | Description |
|---|---|---|
| `destroy()` | `void` | Removes listeners and helper-owned state. |
| `getState()` | `object` | Returns host install state and trusted origins. |

### `getWorkspaceUiBridge(options)`

Returns a child-side bridge helper.

```js
const bridge = getWorkspaceUiBridge(options);
```

### Child options

| Option | Type | Default | Description |
|---|---|---:|---|
| `timeoutMs` | `number` | `900` | Handshake / request timeout. |
| `targetOrigin` | `string` | `"*"` | `postMessage` target origin. |

### Child bridge methods

| Method | Arguments | Returns | Description |
|---|---|---|---|
| `isAvailable()` | none | `Promise<boolean>` | Resolves whether a trusted parent bridge responded to handshake. |
| `showToast(payload)` | `object` | `Promise<string \| null>` | Delegated toast request. |
| `dismissToast(id)` | `string` | `Promise<boolean>` | Delegated toast dismiss. |
| `clearToasts()` | none | `Promise<boolean>` | Delegated toast clear. |
| `alert(payload)` | `object` | `Promise<boolean>` | Delegated alert dialog. |
| `confirm(payload)` | `object` | `Promise<boolean>` | Delegated confirm dialog. |
| `prompt(payload)` | `object` | `Promise<string \| null>` | Delegated prompt dialog. |
| `showActionModal(payload)` | `object` | `Promise<object>` | Delegated simple action modal. |

### `showWorkspaceActionModal(payload, options)`

Convenience export that delegates a simple action modal through the bridge.

## Message Contract

Namespace:

- `pbb.workspace.ui.bridge.v1`

Request shape:

```js
{
  namespace,
  phase: "request",
  id,
  method,
  payload,
}
```

Response shape:

```js
{
  namespace,
  phase: "response",
  id,
  ok,
  result,
  error,
}
```

## Delegated Methods

### `bridge.ping`

Used for capability probing.

Result:

```js
{
  methods: [
    "toast.show",
    "toast.dismiss",
    "toast.clear",
    "dialog.alert",
    "dialog.confirm",
    "dialog.prompt",
    "modal.action",
  ],
}
```

### `toast.show`

Payload:

```js
{
  message,
  options,
}
```

Result:

- toast id or `null`

### `dialog.alert`

Payload:

```js
{
  message,
  options,
}
```

Result:

- `true`

### `dialog.confirm`

Payload:

```js
{
  message,
  options,
}
```

Result:

- `true` or `false`

### `dialog.prompt`

Payload:

```js
{
  message,
  options,
}
```

Result:

- entered string or `null`

### `modal.action`

Payload:

```js
{
  title,
  message,
  description,
  html,
  size,
  closeOnBackdrop,
  closeOnEscape,
  actions: [
    {
      id,
      label,
      variant,
      autoFocus,
    },
  ],
}
```

Result:

```js
{
  reason,      // "action" | "close"
  actionId,    // optional
  actionLabel, // optional
}
```

## Helper Integration Rules

### Automatic in V1

- `ui.toast`
  - should attempt bridge delivery when running in an iframe and bridge mode is not disabled
  - must fall back locally when bridge is unavailable

- `ui.dialog`
  - `uiAlert(...)`
  - `uiConfirm(...)`
  - `uiPrompt(...)`
  - should attempt bridge delivery first when bridge mode is not disabled
  - must fall back locally when bridge is unavailable

### Explicit in V1

- `showWorkspaceActionModal(...)`
  - promise-based simple action modal request

### Local only in V1

- `createModal(...)`
- `createFormModal(...)`

## Fallback Rules

When any of these are true:

- not running in an iframe
- no parent bridge host answers handshake
- request times out
- request errors

then:

- toasts render locally
- dialogs render locally
- explicit action-modal requests reject or should be handled by the caller

## Security

- parent host only accepts messages from trusted origins
- child helper must never assume the parent is trusted without a successful handshake
- no arbitrary method execution beyond the documented bridge methods

## Demo Scope

Dedicated page:

- `demos/demo.workspace.bridge.html`

Required proof:

- parent bridge host installed in workspace shell
- iframe child loaded inside `ui.window` + `ui.iframe.host`
- child requests render parent-owned:
  - toast
  - alert
  - confirm
  - prompt
  - simple action modal

## Regression Scope

Browser harness:

- `tests/workspace.bridge.regression.html`
- `tests/workspace.bridge.regression.mjs`

Minimum checks:

- bridge handshake succeeds
- delegated toast request reaches parent
- delegated confirm request round-trips result
- delegated action-modal request round-trips action result
- local fallback path works when no host is installed
