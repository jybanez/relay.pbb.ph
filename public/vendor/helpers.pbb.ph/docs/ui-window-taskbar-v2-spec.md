# UI Window Taskbar V2 Spec

## Purpose

Define the implementation-facing V2 contract for the `ui.window` taskbar.

This spec upgrades the V1 minimize-only recovery strip into a real workspace taskbar that can represent all open windows.

It remains intentionally narrow.

## Scope

V2 adds:

- always-on taskbar support for workspace-style usage
- taskbar items for all open windows, not only minimized windows
- active-window highlighting
- minimized-state indication
- click-to-focus for open windows
- click-to-restore for minimized windows
- optional close affordance per item

V2 does not add:

- pinned apps
- launchers/start menu
- drag-reorder
- grouped windows
- taskbar overflow menu
- workspace persistence
- docking/snapping

## Design Rule

The taskbar should represent manager state, not only minimized recovery state.

That means:

- every open window gets a taskbar item
- minimized windows stay in the same list with a minimized visual state
- active window is clearly identifiable

## Public API Additions

### Manager Options

| Option | Type | Default | Required | Description |
|---|---|---:|---|---|
| `taskbarMode` | `"auto" \| "always" \| "minimized-only"` | `"auto"` | no | Controls when taskbar items are shown. |
| `showTaskbarClose` | `boolean` | `true` | no | Shows item-level close affordance for closable windows. |
| `taskbarItemOrder` | `"open-order" \| "z-order"` | `"open-order"` | no | Controls item ordering. |

### Manager Methods

| Method | Arguments | Returns | Description |
|---|---|---|---|
| `getTaskbarWindows` | none | `WindowInstance[]` | Returns windows currently represented in the taskbar in rendered order. |

### Window Methods

No required new methods for V2.

Existing methods remain the interaction surface:

- `focus()`
- `restore()`
- `minimize()`
- `close()`

## Taskbar Visibility Rules

### `taskbarMode: "minimized-only"`

V1-compatible mode:

- taskbar shows only when at least one window is minimized
- only minimized windows appear in the taskbar

### `taskbarMode: "always"`

Workspace mode:

- taskbar appears as soon as the manager has at least one open window
- all open windows appear in the taskbar
- minimized windows appear with a minimized state marker

### `taskbarMode: "auto"`

Reserved compatibility mode:

- equivalent to `"always"` for contained managers
- equivalent to `"minimized-only"` for body-level managers

This keeps existing standalone behavior conservative while allowing workspace containers to opt into desktop-style behavior without extra configuration.

## Taskbar Item States

Each item may render these states:

- normal
- active
- minimized
- closable

V2 should not introduce attention/dirty markers yet. Those belong to a later stage.

## Interaction Rules

### Open, non-minimized item

Clicking the taskbar item should:

1. focus the window
2. bring it to front

It should not minimize-toggle the window in V2.

Reason:

- click-to-focus is the clearer default
- minimize remains an explicit window-control action

### Minimized item

Clicking the taskbar item should:

1. restore the window
2. focus the window
3. bring it to front

### Close affordance

If the window is closable and `showTaskbarClose !== false`:

- taskbar item may render an inline close affordance
- closing from taskbar should behave the same as `window.close({ reason: "taskbar-close" })`

## Layout Rules

- maximized windows must reserve taskbar height whenever the taskbar is visible
- hidden taskbar must not still occupy visual space
- taskbar items must remain single-line and ellipsized
- taskbar must not overlap the manager layer content area in a way that visually hides maximized window body content
- if taskbar items exceed available width, the default overflow behavior is horizontal scrolling

## Ordering Rules

### `taskbarItemOrder: "open-order"`

- stable by window creation order
- recommended default

### `taskbarItemOrder: "z-order"`

- highest z-index window appears last
- useful if workspace wants the taskbar to reflect active stacking more aggressively

## Accessibility

V2 expectations:

- each taskbar item remains keyboard reachable
- active item has clear semantic or visual indication
- close affordance has explicit label
- minimized/open state changes remain visually and behaviorally clear

## Demo Scope

Extend:

- `demos/demo.window.manager.html`

Add:

1. always-on taskbar example
2. all-open-window listing example
3. active-window highlight example
4. minimized-state example

## Regression Scope

Extend:

- `tests/window.regression.html`
- `tests/window.regression.mjs`

Required checks:

1. taskbar starts hidden in `minimized-only` mode
2. taskbar becomes visible in `always` mode once at least one window is open
3. all open windows render taskbar items in `always` mode
4. clicking an open taskbar item focuses without minimizing
5. clicking a minimized taskbar item restores and focuses
6. maximized windows reserve taskbar height in `always` mode
7. hidden taskbar does not still render visually

## Acceptance Targets

V2 is acceptable if it cleanly supports:

- a workspace manager with several simultaneously open windows
- taskbar-based switching without minimizing first
- minimized restore without losing stable taskbar order
- maximized windows that never spill behind a visible taskbar
