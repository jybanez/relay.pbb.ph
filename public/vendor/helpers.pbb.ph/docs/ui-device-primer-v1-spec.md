# UI Device Primer V1 Spec

## Summary

`ui.device.primer` is the shared helper for rendering and running project-configurable startup checks for browser permissions, hardware readiness, and capability availability.

It should provide:

- a list of configured checks
- helper-owned status rendering
- helper-owned retry affordances
- aggregate readiness state

It should remain presentation- and preflight-focused.

It should not own:

- app navigation
- project-specific workflow branching
- call/session orchestration
- backend persistence

## Goals

- give PBB apps a shared device/capability preflight surface
- avoid app-local startup-primer drift
- support required and optional checks
- support modal-on-load composition through a preset wrapper

## Non-Goals

- no full device manager in V1
- no persistent diagnostics history
- no automatic post-check routing
- no project-owned media call state in the helper

## Components

### Core helper

```js
const primer = createDevicePrimer(container, data, options);
```

### Modal preset

```js
const modal = createDevicePrimerModal(data, options);
```

The core helper and modal wrapper should be separate.

## Signature

```ts
createDevicePrimer(
  container: HTMLElement,
  data?: {
    title?: string;
    message?: string;
    checks?: DevicePrimerCheck[];
  },
  options?: DevicePrimerOptions
): DevicePrimerInstance
```

## Data Model

### `DevicePrimerCheck`

```ts
type DevicePrimerCheck = {
  id: string;
  kind:
    | "microphone"
    | "camera"
    | "geolocation"
    | "speechSynthesis"
    | "speechRecognition"
    | "notifications"
    | "audioPlayback"
    | "mediaDevices";
  label?: string;
  description?: string;
  required?: boolean;
  autoRun?: boolean;
};
```

### `DevicePrimerState`

```ts
type DevicePrimerState = {
  title: string;
  message: string;
  checks: DevicePrimerCheckState[];
  allComplete: boolean;
  allRequiredReady: boolean;
  hasFailures: boolean;
};
```

### `DevicePrimerCheckState`

```ts
type DevicePrimerCheckState = DevicePrimerCheck & {
  status:
    | "pending"
    | "checking"
    | "ready"
    | "failed"
    | "blocked"
    | "unsupported";
  detailText: string;
  canRetry: boolean;
  updatedAt: number | null;
};
```

## Options

```ts
type DevicePrimerOptions = {
  className?: string;
  autoRun?: boolean;
  allowRetry?: boolean;
  showSummary?: boolean;
  onCheckStart?: (check, state) => void;
  onCheckComplete?: (result, state) => void;
  onRetry?: (check, state) => void;
  onComplete?: (state) => void;
};
```

## Supported V1 Checks

### `microphone`

Intent:

- verify microphone permission/readiness

Expected V1 behavior:

- uses `getUserMedia({ audio: true })`
- marks:
  - `ready` on success
  - `failed` or `blocked` on rejection

### `camera`

Intent:

- verify camera permission/readiness

Expected V1 behavior:

- uses `getUserMedia({ video: true })`
- marks:
  - `ready` on success
  - `failed` or `blocked` on rejection

### `geolocation`

Intent:

- verify location access/readiness

Expected V1 behavior:

- uses `navigator.geolocation.getCurrentPosition(...)`

### `speechSynthesis`

Intent:

- verify speech synthesis availability

Expected V1 behavior:

- checks `window.speechSynthesis`
- no forced voice playback required in V1

### `speechRecognition`

Intent:

- verify speech-recognition API availability

Expected V1 behavior:

- checks browser support only in V1

### `notifications`

Intent:

- verify notification permission/readiness

Expected V1 behavior:

- checks support
- may request permission on run/retry

### `audioPlayback`

Intent:

- verify gesture-gated audio readiness

Expected V1 behavior:

- exercises `AudioContext.resume()` or equivalent helper-owned playback readiness path

### `mediaDevices`

Intent:

- verify device enumeration support/availability

Expected V1 behavior:

- checks `navigator.mediaDevices?.enumerateDevices`
- marks `ready` when enumeration succeeds and returns usable devices

## UI Structure

Recommended rendering:

- optional title
- optional explanatory message
- summary block when enabled
- list of check rows

Each check row should show:

- label
- optional description
- required/optional marker
- current status
- short detail text
- retry button when allowed and applicable

## Status Rules

### `pending`

- check has not run yet

### `checking`

- helper is currently running the check

### `ready`

- check completed successfully

### `failed`

- check ran but did not pass

### `blocked`

- check was denied or browser policy prevents success

### `unsupported`

- browser/platform does not expose the required capability

## Auto-Run Rules

- `options.autoRun` defaults to `true`
- `options.autoRun: true` runs all checks on init unless a check explicitly sets `autoRun: false`
- `options.autoRun: false` leaves checks in `pending` until app code calls `runAll()` or `runCheck(id)`

## Retry Rules

- if `allowRetry` is `true`, failed/blocked checks may show retry
- retry reruns the same check
- helper emits `onRetry(...)` before rerun

## Aggregate State Rules

`getState()` should expose:

- `allComplete`
- `allRequiredReady`
- `hasFailures`

This lets app code decide whether to:

- continue
- block
- reopen later

## Instance Methods

```ts
type DevicePrimerInstance = {
  update(nextData?: object, nextOptions?: object): void;
  runAll(): Promise<DevicePrimerState>;
  runCheck(id: string): Promise<DevicePrimerCheckState | null>;
  retryCheck(id: string): Promise<DevicePrimerCheckState | null>;
  getState(): DevicePrimerState;
  destroy(): void;
};
```

## Modal Preset Direction

The modal preset should:

- render the same core helper inside a helper-owned modal
- support startup gating use on page load
- allow project code to decide:
  - blocking vs non-blocking
  - close conditions
  - follow-up navigation

## Accessibility

V1 expectations:

- clear status text, not color-only state
- retry buttons as native buttons
- labelled region/container
- required/optional text should be visible in text, not only decoration

## Example

```js
const primer = createDevicePrimer(host, {
  title: "Startup Device Check",
  message: "This page needs microphone, camera, and audio playback readiness before you continue.",
  checks: [
    { id: "mic", kind: "microphone", label: "Microphone", required: true },
    { id: "cam", kind: "camera", label: "Camera", required: true },
    { id: "audio", kind: "audioPlayback", label: "Audio Playback", required: true },
    { id: "speech", kind: "speechRecognition", label: "Speech Recognition", required: false }
  ]
}, {
  autoRun: true,
  onComplete(state) {
    console.log(state.allRequiredReady);
  }
});
```
