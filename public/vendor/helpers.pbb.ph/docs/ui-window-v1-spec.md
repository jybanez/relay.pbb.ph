# UI Window V1 Spec

## Purpose

Define the implementation-facing V1 contract for a shared window system in `helpers.pbb.ph`.

This spec narrows the broader proposal in `docs/ui-window-proposal.md` into a concrete helper contract.

## Scope

V1 supports:

- multiple open windows
- manager-owned stacking and active window tracking
- drag by title bar
- resize by edges and corners
- focus / bring-to-front
- maximize / restore
- minimize / restore
- close
- manager-owned taskbar for minimized windows
- viewport bounds clamping

V1 does not support:

- docking
- snapping
- tiling
- saved layouts
- nested managers
- iframe application hosting

## Public API

### `createWindowManager(options)`

Factory for a manager that owns the shared window layer and taskbar.

```js
const manager = createWindowManager(options);
```

### Manager Options

| Option | Type | Default | Required | Description |
|---|---|---|---|---|
| `container` | `HTMLElement` | `document.body` | no | Host element for the shared window layer. |
| `bounds` | `"viewport"` | `"viewport"` | no | Window movement and resize bounds. V1 supports viewport-only behavior. |
| `showTaskbar` | `boolean` | `true` | no | Renders the minimized-window recovery surface. |
| `className` | `string` | `""` | no | Extra class applied to the manager root. |
| `onWindowOpen` | `function` | `null` | no | Fires when a window opens. |
| `onWindowClose` | `function` | `null` | no | Fires when a window closes. |
| `onActiveChange` | `function` | `null` | no | Fires when the active window changes. |

### Manager Methods

| Method | Arguments | Returns | Description |
|---|---|---|---|
| `createWindow` | `options` | `WindowInstance` | Creates a managed window instance. |
| `getWindows` | none | `WindowInstance[]` | Returns managed windows. |
| `focusWindow` | `id` | `boolean` | Brings a matching window to front. |
| `closeWindow` | `id, meta?` | `Promise<boolean>` | Closes a matching window. |
| `closeAll` | `meta?` | `Promise<void>` | Closes all open windows. |
| `destroy` | none | `void` | Removes the manager layer and destroys windows. |

### `manager.createWindow(options)`

Creates a managed window.

```js
const win = manager.createWindow(options);
```

### Window Options

| Option | Type | Default | Required | Description |
|---|---|---|---|---|
| `id` | `string` | generated | no | Stable identifier for focus/minimize lookup. |
| `title` | `string` | `"Window"` | no | Title-bar label. |
| `content` | `Node \| string \| function` | `null` | no | Window body content. Function receives the window instance and should return a node or string. |
| `width` | `number` | `640` | no | Initial width in pixels. |
| `height` | `number` | `420` | no | Initial height in pixels. |
| `x` | `number` | centered | no | Initial x position in pixels. |
| `y` | `number` | centered | no | Initial y position in pixels. |
| `minWidth` | `number` | `320` | no | Minimum width in pixels. |
| `minHeight` | `number` | `220` | no | Minimum height in pixels. |
| `draggable` | `boolean` | `true` | no | Enables title-bar drag. |
| `resizable` | `boolean` | `true` | no | Enables edge/corner resize handles. |
| `minimizable` | `boolean` | `true` | no | Enables minimize control and taskbar recovery. |
| `maximizable` | `boolean` | `true` | no | Enables maximize/restore control. |
| `closable` | `boolean` | `true` | no | Enables close control. |
| `initialState` | `"normal" \| "maximized" \| "minimized"` | `"normal"` | no | Initial window state. |
| `className` | `string` | `""` | no | Extra class applied to the window root. |
| `headerActions` | `Action[]` | `[]` | no | Optional header button actions. |
| `onOpen` | `function` | `null` | no | Fires when the window opens. |
| `onClose` | `function` | `null` | no | Fires when the window closes. |
| `onFocus` | `function` | `null` | no | Fires when the window becomes active. |
| `onMove` | `function` | `null` | no | Fires after drag movement commits. |
| `onResize` | `function` | `null` | no | Fires after resize updates. |
| `onStateChange` | `function` | `null` | no | Fires on minimize/maximize/restore. |

### Window Methods

| Method | Arguments | Returns | Description |
|---|---|---|---|
| `open` | none | `boolean` | Opens the window and focuses it. |
| `close` | `meta?` | `Promise<boolean>` | Closes the window. |
| `focus` | none | `boolean` | Brings the window to front. |
| `minimize` | none | `boolean` | Minimizes the window to the taskbar. |
| `maximize` | none | `boolean` | Maximizes the window to manager bounds. |
| `restore` | none | `boolean` | Restores from minimized/maximized state. |
| `setTitle` | `title` | `void` | Updates the title bar label. |
| `setContent` | `content` | `void` | Replaces the body content. |
| `setPosition` | `{ x, y }` | `void` | Updates the window position. |
| `setSize` | `{ width, height }` | `void` | Updates the window size. |
| `getState` | none | `WindowState` | Returns the current window state. |
| `destroy` | none | `void` | Removes the window instance from the manager. |

## State Shape

`getState()` returns:

```js
{
  id,
  open,
  active,
  minimized,
  maximized,
  x,
  y,
  width,
  height,
  zIndex,
}
```

## Behavior Rules

### Focus / Stack

- only one window is active at a time
- focusing a window brings it to the highest z-index
- clicking anywhere inside a window focuses it

### Drag

- title bar only
- drag clamps to viewport bounds
- maximized windows cannot be dragged until restored

### Resize

- edge and corner resize handles
- enforce `minWidth` and `minHeight`
- clamp to manager bounds

### Minimize

- minimized windows are hidden from the main layer
- minimized windows render a taskbar item when `showTaskbar !== false`
- clicking a taskbar item restores and focuses the window

### Maximize

- fills the manager viewport
- preserves prior position and size for restore

### Close

- closes the window from the active layer
- minimized windows can also be closed from the taskbar surface
- `destroy()` removes all DOM and manager references

## Accessibility

V1 expectations:

- visible title labels
- focusable active window shell
- labeled minimize / maximize / restore / close controls
- keyboard focus moves into the active window on open/focus

## Demo Scope

Dedicated pages:

- `demos/demo.window.html`
- `demos/demo.window.manager.html`

The manager/workspace demo must prove:

- open multiple windows
- drag and resize
- focus / stacking
- minimize and restore from taskbar
- maximize and restore
- dynamic content updates

## Regression Scope

Browser harness:

- `tests/window.regression.html`
- `tests/window.regression.mjs`

Minimum checks:

- manager creates multiple windows
- focus changes z-index ordering
- minimize creates a taskbar item and hides the window
- restore reopens the window
- maximize preserves restore state
- resize enforces minimum bounds
- close removes window DOM
- destroy removes manager DOM

## Acceptance Targets

V1 is acceptable if it cleanly supports:

- detached inspector beside another active surface
- detached media preview that remains open during other interactions
- at least three concurrent windows with correct stack and restore behavior
