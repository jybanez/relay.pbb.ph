# UI Window System Proposal

## Purpose

Introduce a shared windowing system for `helpers.pbb.ph` that supports:

- draggable windows
- resizable windows
- minimize / maximize / restore
- z-index stacking
- multiple concurrent open windows

The goal is to support tool-like operational workflows that do not fit modal-only interaction.

## Why This Proposal Exists

The current helper library is strong for:

- modal workflows
- dialog workflows
- structured form workflows
- dedicated full-page demos

But some operational interfaces need non-modal, parallel, desktop-like interaction.

Examples:

- media viewer detached beside an incident details panel
- inspector panel kept open while working in a hierarchy view
- side-by-side operational tools for comparison or verification
- floating utilities launched from a command palette
- persistent helper tools that should not block the rest of the screen

These are not good fits for `createModal(...)` because modal behavior intentionally blocks background interaction.

## Problem Statement

Today, if a project needs desktop-style windows, the likely outcomes are:

- app-local draggable panel implementations
- ad hoc z-index handling
- inconsistent minimize / maximize behavior
- duplicated resize / focus logic across projects
- inconsistent accessibility and keyboard support

That should be owned by the shared helper layer instead.

## Goals

V1 should provide a narrow but real window system.

Supported in V1:

- multiple open windows
- drag by title bar
- resize by edges / corners
- focus / bring-to-front behavior
- minimize
- maximize
- restore
- close
- viewport bounds clamping
- helper-owned visual shell and controls

V1 should be suitable for:

- inspectors
- media tools
- support utilities
- side-by-side operational panels

## Non-Goals

V1 should not try to become a full desktop framework.

Explicitly deferred:

- docking
- snapping
- tabbed windows
- saved layouts
- tiling strategies
- virtual desktops
- cross-window message bus
- iframe application hosting
- arbitrary nested window managers

Those can be proposed later if repeated real use cases justify them.

## Recommended Architecture

The system should have two layers.

### 1. `createWindowManager(options)`

Shared responsibilities:

- host container ownership
- active window tracking
- z-index ordering
- viewport bounds
- minimize tray / taskbar ownership
- manager-level events

### 2. `createWindow(options)`

Per-window responsibilities:

- shell rendering
- title bar
- content host
- drag interactions
- resize interactions
- minimize / maximize / restore state
- focus state
- close lifecycle

This separation matters. A window system is not just a draggable modal.

## Proposed V1 API

### Manager

```js
const manager = createWindowManager({
  container: document.body,
  bounds: "viewport",
  showTaskbar: true,
});
```

### Window Creation

```js
const win = manager.createWindow({
  id: "hub-inspector",
  title: "Hub Inspector",
  width: 720,
  height: 480,
  x: 120,
  y: 80,
  minWidth: 320,
  minHeight: 220,
  resizable: true,
  minimizable: true,
  maximizable: true,
  closable: true,
  content: hostNode,
});
```

### Returned Window API

```js
win.open();
win.close();
win.focus();
win.minimize();
win.maximize();
win.restore();
win.setTitle("Updated Title");
win.setContent(nextNode);
win.setPosition({ x: 160, y: 120 });
win.setSize({ width: 800, height: 540 });
win.getState();
win.destroy();
```

### Returned Manager API

```js
manager.createWindow(options);
manager.getWindows();
manager.focusWindow(id);
manager.closeWindow(id);
manager.closeAll();
manager.destroy();
```

## Recommended Options Contract

### `createWindowManager(options)`

Proposed V1 options:

- `container`
- `bounds`
- `showTaskbar`
- `className`
- `onWindowOpen`
- `onWindowClose`
- `onActiveChange`

### `createWindow(options)`

Proposed V1 options:

- `id`
- `title`
- `content`
- `width`
- `height`
- `x`
- `y`
- `minWidth`
- `minHeight`
- `resizable`
- `draggable`
- `minimizable`
- `maximizable`
- `closable`
- `initialState`
- `className`
- `headerActions`
- `onOpen`
- `onClose`
- `onFocus`
- `onMove`
- `onResize`
- `onStateChange`

## Recommended State Model

Each window should have an explicit state object.

```js
{
  id: "hub-inspector",
  open: true,
  active: true,
  minimized: false,
  maximized: false,
  x: 120,
  y: 80,
  width: 720,
  height: 480,
  zIndex: 1200,
}
```

This should be exposed through `getState()` and event payloads.

## Interaction Rules

### Focus / Stack

- clicking a window focuses it
- focused window moves to top z-index
- only one window is active at a time

### Drag

- drag handle should be the title bar only
- dragging should clamp to the manager bounds
- dragging a maximized window should not silently break state unless explicitly supported

### Resize

- resize from edges and corners
- enforce `minWidth` and `minHeight`
- clamp to manager bounds where reasonable

### Maximize

- fill the manager viewport
- preserve prior size / position for restore

### Minimize

- minimized windows should move to a shared taskbar or tray owned by the manager
- minimize without a recovery surface should not ship in V1

### Close

- close removes the window from active interaction
- `destroy()` should remove the instance completely

## Accessibility Expectations

A shared window system must still maintain a real accessibility contract.

V1 expectations:

- visible title bar labeling
- focusable window shell
- keyboard focus management when a window becomes active
- button labels for minimize / maximize / restore / close
- manager and window roles documented clearly

If full ARIA desktop semantics are too heavy for V1, that should be stated explicitly in the docs rather than implied.

## Visual Direction

The window system should feel like a tool surface, not a repurposed modal.

Recommended visual traits:

- distinct title bar
- active / inactive styling
- resize handles visible enough to discover
- controlled shadow and border system
- optional status badge or header actions later

It should still match the helper library tokens and existing shell language.

## Demo Scope

A dedicated demo page should exist:

- `demos/demo.window.html`
- `demos/demo.window.manager.html`

The demo should prove:

- open multiple windows
- drag and resize
- bring to front
- minimize and restore from taskbar
- maximize and restore
- replace content dynamically

Suggested demo windows:

- Inspector
- Media Preview
- Incident Notes

That gives a realistic operational feel without introducing app-specific logic.

## Regression Scope

Window behavior is interaction-heavy and should get targeted browser regression coverage.

Suggested browser harness:

- `tests/window.regression.html`
- `tests/window.regression.mjs`

Minimum regression checks:

- multiple windows can open
- focus changes z-index ordering
- minimize removes the window from the viewport and adds a taskbar item
- restore reopens the same window state
- maximize preserves restore state
- resize respects minimum bounds
- drag updates position
- destroy removes DOM cleanly

## Recommended Rollout

### Phase 1

Build the narrow V1 system:

- manager
- window shell
- drag
- resize
- stack
- maximize / restore
- close

### Phase 2

Add minimize plus manager-owned taskbar.

### Phase 3

Only after repeated real use:

- snapping
- saved layouts
- docking or tiling

## Risks

### 1. Scope drift

The biggest risk is turning this into a desktop framework too early.

### 2. Modal confusion

If the implementation reuses modal semantics too directly, projects will get a hybrid that does neither job cleanly.

### 3. Interaction bugs

Drag / resize / focus systems are easy to get almost right and still feel unstable.

### 4. Accessibility debt

Window systems can become inaccessible quickly if focus ownership is not designed upfront.

## Acceptance Targets

The first implementation should prove itself against real helper use cases.

Recommended acceptance targets:

- Detached inspector beside a hierarchy or grid demo
- Standalone media preview window that can remain open while another tool is active
- At least three concurrent windows with correct stacking and restore behavior

If those feel stable, V1 is sufficient.

## Recommendation

Proceed with a narrow shared `ui.window` system.

Use a manager + window split, keep V1 focused, and treat minimize as incomplete unless paired with a manager-owned recovery surface.

This should be built as a helper subsystem, not as app-local draggable modal variants.

## Follow-On Proposal

If the next iteration needs a stronger taskbar, use:

- `docs/ui-window-taskbar-improvement-proposal.md`

before discussing docking or snapping.
