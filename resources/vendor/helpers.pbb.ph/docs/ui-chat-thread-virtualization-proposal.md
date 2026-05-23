# UI Chat Thread Virtualization Proposal

## Purpose

Define the recommended direction for adding virtualization support to `ui.chat.thread`.

This proposal exists because chat threads can grow large enough that rendering every message node at once becomes wasteful or visibly slow.

The goal is to improve performance for long-running or history-heavy threads without changing the current message, attachment, or menu contracts.

## Why This Needs Its Own Proposal

Chat-thread virtualization is materially harder than grid virtualization.

A chat thread has additional constraints:

- message heights are variable
- grouped message runs affect spacing and visual grouping
- image/video content can load later and change height
- many threads are visually anchored near the bottom
- history may be prepended above the current viewport
- system messages and separators live in the same vertical flow

So this should not be treated as a casual rendering optimization.

It needs an explicit UX and scroll-behavior contract.

## Recommended Direction

Virtualization should be:

- opt-in
- narrow in first scope
- backward-compatible with the current thread contract

Recommended V1 option:

- `enableVirtualization: true`

This should default to:

- `false`

## Initial Use Case

The first real target is:

- long text-heavy operational threads
- operator consoles that stay open for long periods
- large history views where message count grows into the hundreds or thousands

This is the right first slice because it delivers real value without forcing the helper to solve every chat UX edge case immediately.

## V1 Behavioral Goals

The first version should aim to preserve these behaviors:

- stable message rendering contract
- stable message action menu behavior
- stable media attachment behavior
- stable scroll-to-bottom behavior for live append flows
- stable visible position when older history is prepended

## V1 Should Not Try To Solve Everything

The first version should not promise:

- perfect no-shift behavior for every late-loading media case
- full unread-marker product logic
- full bidirectional history virtualization with complex jump controls
- animation-heavy insertion behavior
- every future chat-specific viewport feature

That would overload the first implementation.

## Recommended V1 Constraints

### 1. Vertical List Virtualization Only

The helper should virtualize the message list as a vertical sequence.

It should not introduce special virtualization behavior for:

- attachments inside a message
- menus
- media viewer
- per-message action surfaces

Those should continue to work on the rendered message nodes that are currently mounted.

### 2. Keep The Existing Message Data Contract

Virtualization should not require a different message shape.

Existing thread message objects should remain valid.

### 3. Keep Scroll Anchoring Explicit

The hardest part is not DOM trimming.

It is scroll behavior.

The first version should explicitly define:

- what happens when new messages append while the user is already near the bottom
- what happens when older messages prepend above the current viewport
- when the helper should auto-scroll
- when it should preserve the user's current reading position

### 4. Media Reflow Must Be Tolerated

Late-loading media may change message height.

The V1 implementation should tolerate this reasonably, even if it does not promise perfect zero-shift behavior in every case.

## Recommended V1 Option Surface

Suggested options:

- `enableVirtualization`
- `virtualOverscan`
- `virtualThreshold`
- `bottomAnchorThreshold`

Example:

```js
const thread = createChatThread(host, { messages }, {
  enableVirtualization: true,
  virtualThreshold: 120,
  virtualOverscan: 10,
  bottomAnchorThreshold: 48,
});
```

## Scroll Behavior Model

### Append Behavior

If the user is already near the bottom when a new message arrives:

- keep the thread pinned to the bottom

If the user is reading older messages away from the bottom:

- do not forcibly jump to the latest message

### Prepend Behavior

If older history is inserted above the current viewport:

- preserve the currently visible reading position as closely as possible

This is one of the most important reasons virtualization needs an explicit contract.

## Message Menus And Attachments

Virtualization should not change how these work.

Expected behavior:

- message action menus remain per-message and helper-owned
- image/video attachments still open the shared media viewer
- file/audio attachments still use current open/download behavior

## Recommended Implementation Strategy

The first version should likely use:

- a fixed viewport host
- top spacer
- rendered visible message window
- bottom spacer
- scroll-window recomputation on scroll

Because message heights are variable, the implementation will need measured row heights instead of assuming a constant row size.

That makes this a measured-list virtualization problem, not a simple fixed-row table problem.

## Risks

Main risks include:

- poor scroll anchoring when prepending history
- visible jump during image/video load
- over-complex first implementation
- breaking message grouping or system-message spacing

Those risks are manageable if the first version stays narrow.

## Recommendation

Proceed, but only as:

- opt-in
- long-thread optimization
- measured-list virtualization
- with explicit append/prepend scroll rules

## Suggested Next Docs

If this direction is accepted, the next documents should be:

1. `ui-chat-thread-virtualization-v1-spec.md`
2. `ui-chat-thread-virtualization-checklist.md`

## Bottom Line

`ui.chat.thread` should gain virtualization support.

But it should be introduced as a dedicated, opt-in, scroll-contract-aware feature rather than a generic rendering tweak.
