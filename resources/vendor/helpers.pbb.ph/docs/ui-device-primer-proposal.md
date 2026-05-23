# UI Device Primer Proposal

## Summary

Add a shared `ui.device.primer` helper for project-configurable startup checks used to verify:

- permission-dependent browser capabilities
- hardware/device readiness
- browser feature support
- user-gesture-gated media readiness

The component should help PBB apps present a consistent preflight experience before the main page flow depends on microphone, camera, speech, location, notifications, or other browser services.

This helper should also support a modal preset wrapper so apps can launch it on page load when a workflow requires upfront readiness verification.

## Why This Is Needed

Different PBB apps will increasingly need the same browser/device checks:

- realtime/call surfaces need microphone and camera readiness
- speech-driven tools need microphone plus speech APIs
- field tools need location
- notification-heavy tools need browser notification permission
- media/call tools need audio playback readiness and media-device availability

Without a shared helper, each app will build its own:

- browser permission primer
- readiness status UI
- retry logic
- mixed required/optional check treatment
- startup gating flow

That creates drift in:

- terminology
- visual treatment
- error handling
- retry affordances
- readiness expectations

## Recommended Component

Introduce:

- `ui.device.primer`

Factory direction:

```js
const primer = createDevicePrimer(container, data, options);
```

And a modal preset wrapper:

- `createDevicePrimerModal(...)`

This keeps the core helper reusable in:

- full pages
- drawers
- side panels
- modal-on-load startup gating flows

## Recommended Scope

`ui.device.primer` should own:

- check list rendering
- status presentation
- helper-owned retry actions
- running supported built-in checks
- aggregate completion state
- narrow per-check detail text

It should not own:

- app routing decisions
- business-specific fallback behavior
- long-lived diagnostics history
- project-specific onboarding copy beyond what the app passes in
- actual call/session orchestration

## Recommended Check Model

Projects should choose which checks they want.

The helper should not force a fixed checklist.

Each app should declare a set of supported checks, for example:

- `microphone`
- `camera`
- `geolocation`
- `speechSynthesis`
- `speechRecognition`
- `notifications`
- `screenCapture`
- `audioPlayback`
- `mediaDevices`

This is stronger than a device-only model because some preflight needs are:

- permissions
- capabilities
- readiness state

not only hardware presence.

## Recommended Status Model

Each check should render a clear state:

- `pending`
- `checking`
- `ready`
- `failed`
- `blocked`
- `unsupported`

Where helpful, the helper should show:

- summary text
- short reason/detail
- retry button

## Required Vs Optional Checks

Projects should be able to mark checks as:

- required
- optional

The helper should expose aggregate results so app code can decide whether to:

- block entry
- continue with reduced capability
- reopen the primer later

## Retry Behavior

If a check fails, the row should show a retry action.

This is especially important for:

- microphone
- camera
- geolocation
- notifications
- screen capture

The helper should own the retry UI, but the project should still be able to observe retry events.

## Recommended V1 Checks

Start with:

- `microphone`
- `camera`
- `geolocation`
- `speechSynthesis`
- `speechRecognition`
- `notifications`
- `audioPlayback`
- `mediaDevices`

Optional for later:

- `screenCapture`
- `wakeLock`
- `fullscreen`
- `clipboard`

## Modal Wrapper Recommendation

The modal preset should exist because startup gating is a likely use case:

- page load preflight
- first-use setup
- call/session readiness before join

The default expectation should be auto-run.

A startup primer that sits in `pending` until the user manually starts it is the wrong baseline for most projects using this helper.

But the core helper should remain separate from the modal shell.

That keeps the component reusable outside modal flows.

## Relationship To Existing Helpers

### `ui.audio.audiograph`

Useful for live microphone visualization when a project wants a richer microphone readiness row later, but not required for V1.

### `ui.form.modal`

The modal preset can compose the core primer through the existing modal/form-modal stack, but the primer itself should not be treated as just another form.

### `ui.fieldset`

`ui.fieldset` is good for grouped form rows.

`ui.device.primer` is a readiness surface with status rows and retry behavior, not a generic data-entry section.

## Non-Goals

V1 should not attempt to solve:

- full call setup workflow
- device-selection routing for every media source
- persistent diagnostic history
- OS-level troubleshooting
- project-specific business copy beyond app-provided text

## Implementation Recommendation

Build V1 as:

- a reusable check-runner + status UI helper
- a narrow modal preset wrapper
- project-configurable check selection

That gives the helper library a shared startup-readiness surface without turning it into a full media/session orchestration framework.
