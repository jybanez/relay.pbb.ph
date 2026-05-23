# UI Iframe Host V1 Spec

## Purpose

Define the implementation-facing V1 contract for a shared iframe-host helper in `helpers.pbb.ph`.

This spec narrows the broader proposal in `docs/ui-iframe-host-proposal.md` into a concrete helper contract that can be composed with `ui.window` and later with `PBB Workspace`.

## Scope

V1 supports:

- helper-owned iframe creation
- helper-owned loading state
- helper-owned deterministic error state when the source is invalid or reset to empty
- stable host sizing for reuse inside plain pages or `ui.window`
- full-bleed composition inside `ui.window` so iframe-hosted app surfaces can occupy the full window body
- narrow imperative controls for source updates and reload
- safe default iframe attributes with explicit overrides
- optional `srcdoc` support for local demo/test usage

V1 does not support:

- cross-frame messaging
- automatic title sync from embedded documents
- Workspace discovery or launcher logic
- auth brokering / SSO
- shell-to-app shared state
- browser-history integration

## Public API

### `createIframeHost(options)`

Factory for a helper-owned iframe surface.

```js
const host = createIframeHost(options);
```

### Options

| Option | Type | Default | Required | Description |
|---|---|---:|---|---|
| `src` | `string` | `""` | no | URL loaded into the iframe. |
| `srcdoc` | `string` | `""` | no | Inline document markup used instead of `src` when provided. |
| `title` | `string` | `"Embedded content"` | no | Accessible iframe title. |
| `loadingText` | `string` | `"Loading embedded page..."` | no | Helper-owned loading message. |
| `errorTitle` | `string` | `"Unable to load embedded page"` | no | Helper-owned error heading. |
| `errorMessage` | `string` | `"Check the requested URL or embedded app availability."` | no | Helper-owned error message body. |
| `sandbox` | `string` | `"allow-scripts allow-same-origin allow-forms allow-popups allow-downloads"` | no | Sandbox attribute applied to the iframe. |
| `referrerPolicy` | `string` | `"strict-origin-when-cross-origin"` | no | Referrer policy applied to the iframe. |
| `allow` | `string` | `""` | no | Raw iframe `allow` attribute. |
| `allowFullscreen` | `boolean` | `false` | no | Adds `allowfullscreen` to the iframe. |
| `className` | `string` | `""` | no | Extra class names applied to the host root. |
| `onLoad` | `function` | `null` | no | Fires after a successful iframe load. |
| `onError` | `function` | `null` | no | Fires when the helper enters an error state. |

## Window Composition Behavior

When the returned host root is used as `ui.window` content, the helper marks itself as full-fill window content. `ui.window` should then allow the iframe host to occupy the full window body instead of wrapping it in generic body padding.

### Returned API

| Property / Method | Arguments | Returns | Description |
|---|---|---|---|
| `root` | - | `HTMLElement` | Root host surface. |
| `iframe` | - | `HTMLIFrameElement` | Managed iframe element. |
| `getSrc` | none | `string` | Current `src` value. |
| `setSrc` | `url` | `void` | Replaces the current iframe URL and clears `srcdoc`. |
| `reload` | none | `void` | Reloads the current `src` or re-applies the current `srcdoc`. |
| `update` | `options` | `void` | Applies partial option updates. |
| `getState` | none | `IframeHostState` | Returns current host state. |
| `destroy` | none | `void` | Removes DOM and listeners owned by the helper. |

## State Shape

`getState()` returns:

```js
{
  src,
  srcdoc,
  title,
  status,
  loading,
  loaded,
  error,
}
```

Where:

- `status` is one of:
  - `"idle"`
  - `"loading"`
  - `"ready"`
  - `"error"`
- `error` is either `null` or:

```js
{
  code,
  message,
}
```

## Behavior Rules

### Source selection

- if `srcdoc` is non-empty, V1 loads `srcdoc`
- otherwise V1 loads `src`
- if both are empty, V1 enters the helper-owned error state

### Loading

- a non-empty `src` or `srcdoc` transition sets:
  - `status = "loading"`
  - `loading = true`
  - `loaded = false`
- loading surface remains visible until the iframe `load` event fires

### Ready state

- iframe `load` sets:
  - `status = "ready"`
  - `loading = false`
  - `loaded = true`
  - `error = null`
- iframe becomes the primary visible surface

### Error state

V1 must enter a helper-owned error state when:

- source is reset to empty
- source update is not a usable string

Error state must keep the root stable and visible rather than leaving a collapsed/blank host.

### Reload

- `reload()` with `src` reassigns the same `src`
- `reload()` with `srcdoc` re-applies the same `srcdoc`
- `reload()` with no valid source re-enters the error state

## Composition With `ui.window`

Preferred usage:

```js
const iframeHost = createIframeHost({
  src: "/pbb/hq/",
  title: "PBB HQ",
});

const win = manager.createWindow({
  title: "PBB HQ",
  content: iframeHost.root,
});
```

Rules:

- `ui.window` stays generic
- iframe lifecycle stays owned by `ui.iframe.host`
- iframe-host content should be allowed to occupy the full window body
- window destroy/close should not require `ui.window` to understand iframe-specific behavior

## Accessibility

V1 expectations:

- iframe always has a `title`
- loading and error surfaces expose readable text
- error state should remain visible to keyboard/screen-reader users
- host root should not trap focus on its own

## Demo Scope

Dedicated page:

- `demos/demo.iframe.host.html`

The demo must prove:

- standalone iframe host
- helper-owned loading state
- deterministic error state
- `setSrc(...)`
- `reload()`
- composition inside `ui.window`
- deterministic browser fixture loading through a same-origin HTML file
- full-bleed composition inside `ui.window`

## Regression Scope

Browser harness:

- `tests/iframe.host.regression.html`
- `tests/iframe.host.regression.mjs`

Minimum checks:

- root renders
- iframe renders
- `srcdoc` load transitions to ready
- empty source transitions to error
- `setSrc(validUrl)` updates the iframe to a valid non-error source
- `reload()` keeps the host stable
- `destroy()` removes the host DOM
- ready iframe content is not visually overridden by stale loading/error surfaces

## Acceptance Targets

V1 is acceptable if it cleanly supports:

- a local embedded utility page in a normal helper demo
- a helper-owned iframe inside `ui.window`
- a helper-owned iframe occupying the full `ui.window` body when used as embedded app content
- future Workspace composition without moving iframe logic into `ui.window`
