# UI Window Taskbar Improvement Proposal

## Purpose

This proposal defines the next narrow expansion stage for `ui.window` after the V1 manager and window shell.

The focus is the manager-owned taskbar only.

It does **not** propose:

- docking
- snapping
- tiling
- workspace persistence
- tabbed windows

Those should remain separate proposal stages.

## Why This Needs A Proposal

V1 minimize behavior is functional, but the taskbar is still intentionally basic:

- one minimized item per window
- restore by clicking the taskbar item
- close from the taskbar item when the window is closable

That is enough for V1 correctness, but not enough for heavier operational use where many tool windows can remain minimized for longer sessions.

If the taskbar is going to grow, it needs a deliberate contract instead of incremental one-off changes.

## Current V1 Baseline

Current behavior already supports:

- manager-owned taskbar container
- minimized window recovery
- close button per taskbar item when allowed
- active/focused window tracking at the manager level

## Problems To Solve

### 1. Many minimized windows do not scale well

If several windows are minimized, the taskbar becomes crowded and loses scanability.

### 2. Window state is not summarized strongly enough

The taskbar currently restores windows, but it does not communicate enough state at a glance:

- active
- minimized
- attention-needed
- dirty/unsaved

### 3. Recovery controls are still minimal

V1 allows per-item restore and optional close, but not broader manager-level recovery actions.

## Recommended Scope

### In Scope

1. stronger taskbar item states
2. optional overflow handling for many minimized windows
3. optional badges / state markers
4. manager-level restore helpers
5. manager-level minimize/restore convenience methods if needed by the taskbar

### Out Of Scope

1. docking and snapped layout recovery
2. pinned windows or app launchers
3. drag-reorder taskbar items
4. multiple taskbars
5. saved window layouts

## Proposed Contract Additions

### Manager Options

Possible narrow additions:

| Option | Type | Default | Purpose |
|---|---|---:|---|
| `taskbarOverflow` | `"scroll" \| "menu"` | `"scroll"` | Controls how many minimized items are handled. |
| `showTaskbarCounts` | `boolean` | `false` | Shows minimized/total counts in the taskbar shell. |
| `taskbarLabelMode` | `"title" \| "compact"` | `"title"` | Controls taskbar item label treatment. |

### Window Options

Possible narrow additions:

| Option | Type | Default | Purpose |
|---|---|---:|---|
| `taskbarLabel` | `string` | `title` | Explicit compact label for the taskbar item. |
| `attention` | `boolean` | `false` | Marks a window as requiring user attention. |
| `dirty` | `boolean` | `false` | Marks unsaved/modified state in taskbar presentation. |

These should also be mutable through methods rather than direct property reads.

### Manager Methods

Possible narrow additions:

| Method | Purpose |
|---|---|
| `restoreAll()` | Restores every minimized window. |
| `minimizeAll()` | Minimizes every open window that allows minimize. |
| `getMinimizedWindows()` | Returns minimized window instances in taskbar order. |

### Window Methods

Possible narrow additions:

| Method | Purpose |
|---|---|
| `setAttention(flag)` | Updates attention state for taskbar presentation. |
| `setDirty(flag)` | Updates dirty/unsaved state for taskbar presentation. |
| `setTaskbarLabel(label)` | Updates compact taskbar label. |

## Interaction Rules

### Taskbar Item States

Each item should support a clear visual state model:

- normal minimized window
- active window
- attention-needed window
- dirty window

These should stay additive and visually restrained.

### Overflow

If taskbar items exceed available width, support one of two narrow strategies:

1. horizontal scrolling taskbar
2. overflow menu after N items

For the next stage, scrolling is the simpler and safer default.

### Restore Behavior

Clicking a minimized taskbar item should:

1. restore the window
2. focus the window
3. bring it to front

That should remain stable.

## Accessibility

Taskbar changes should preserve:

- clear button labels
- active item indication
- keyboard focus order
- restore/close actions reachable without pointer-only behavior

If taskbar overflow introduces a menu, that menu requires its own accessibility review and regression coverage.

## Demo Scope

If this proposal is accepted, add:

1. one taskbar-overflow demo
2. one attention/dirty-state demo

These should extend `demos/demo.window.manager.html` rather than creating a second unrelated window surface.

## Regression Scope

Required coverage for any accepted taskbar expansion:

1. restore-all behavior if added
2. minimized ordering stability
3. active-state taskbar sync
4. dirty/attention marker updates
5. overflow behavior when many windows are minimized

## Recommendation

If the team wants the next improvement stage for `ui.window`, it should be:

1. taskbar improvements
2. only after that, if still needed, evaluate docking/snapping as a separate proposal

That keeps the subsystem disciplined:

- V1 = correct base windowing
- next stage = stronger taskbar usability
- later stages = only if truly justified

## Follow-On Spec

The current implementation-facing follow-on doc for the next bounded stage is:

- `docs/ui-window-taskbar-v2-spec.md`

That V2 spec narrows this proposal into:

- always-on taskbar support for workspace-style usage
- all-open-window taskbar listing
- active/minimized item state
- taskbar-based focus switching
