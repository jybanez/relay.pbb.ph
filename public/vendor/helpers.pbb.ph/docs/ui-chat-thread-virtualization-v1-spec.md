# UI Chat Thread Virtualization V1 Spec

## Purpose

Define the first supported virtualization contract for `ui.chat.thread`.

## Scope

This spec covers:

- opt-in measured-list virtualization for long chat threads
- append and prepend scroll behavior
- narrow performance tuning options

This spec does not cover:

- unread marker product logic
- advanced jump navigation
- animation semantics
- app-specific history loading workflows

## Core Rule

Virtualization must not change the message data contract.

Teams should be able to use the existing message structure with or without virtualization.

## V1 Options

### `enableVirtualization`

- type: `boolean`
- default: `false`

When `true`, the helper may render only the currently visible message window plus overscan.

### `virtualThreshold`

- type: `number`
- default: `120`

Minimum message count before virtualization becomes active.

### `virtualOverscan`

- type: `number`
- default: `10`

Number of extra messages rendered above and below the visible window.

### `bottomAnchorThreshold`

- type: `number`
- default: `48`

Distance in pixels from the bottom used to decide whether the thread is considered pinned to the latest message.

## Required Behavior

### 1. Below Threshold

If message count is below `virtualThreshold`:

- render normally without virtualization

### 2. Append While Near Bottom

If the user is within `bottomAnchorThreshold` of the bottom when new messages append:

- keep the thread pinned to the bottom

### 3. Append While Reading Older Messages

If the user is not near the bottom when new messages append:

- do not auto-jump to the newest message

### 4. Prepend Older History

If messages are inserted above the current viewport:

- preserve the currently visible reading position as closely as possible

### 5. Message Interactions

When a message is rendered in the visible window, all normal thread interactions should still work:

- message action menus
- image/video media viewer open
- file/audio open/download

## Implementation Model

Recommended V1 implementation model:

- top spacer
- rendered visible message slice
- bottom spacer
- measured message heights
- viewport-driven window recalculation

Fixed-height row assumptions are not sufficient.

## State Expectations

Recommended internal state concepts:

- measured row heights by message id
- visible window start/end indexes
- top spacer height
- bottom spacer height
- pinned-to-bottom boolean

These are implementation details, but the helper needs them to satisfy the scroll rules above.

## Media And Reflow

Late-loading media may change measured message height.

V1 should:

- tolerate this reasonably
- update measurements as needed
- avoid extreme visible jumps where practical

Perfect no-shift behavior for every media timing case is not required in V1.

## Recommended Instance Behavior

Virtualization should not remove existing methods.

Current thread instance methods should keep working.

If the helper later adds virtualization-specific instance methods, they should be additive.

## Acceptance Criteria

The V1 thread virtualization contract is acceptable when:

- virtualization is opt-in
- small threads still render normally
- long threads render a reduced visible window
- append behavior preserves bottom anchoring correctly
- prepend behavior preserves reading position reasonably
- existing message interactions continue to work
- the message data contract remains unchanged
