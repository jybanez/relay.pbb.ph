# UI Device Primer Checklist

## Proposal And Scope

- [x] Draft device-primer proposal.
- [x] Draft V1 device-primer spec.
- [x] Keep the component project-configurable rather than enforcing a fixed startup checklist.
- [x] Separate the core helper from the modal preset wrapper.

## Runtime

- [x] Add `ui.device.primer` runtime helper.
- [x] Add check list rendering with:
  - label
  - description
  - required/optional marker
  - status
  - detail text
- [x] Add supported V1 check kinds:
  - `microphone`
  - `camera`
  - `geolocation`
  - `speechSynthesis`
  - `speechRecognition`
  - `notifications`
  - `audioPlayback`
  - `mediaDevices`
- [x] Add aggregate readiness state.
- [x] Add helper-owned retry actions.
- [x] Add instance methods:
  - `update(...)`
  - `runAll()`
  - `runCheck(id)`
  - `retryCheck(id)`
  - `getState()`
  - `destroy()`

## Modal Preset

- [x] Add `createDevicePrimerModal(...)`.
- [x] Support modal-on-load startup use.
- [x] Keep blocking/close policy app-owned.

## Demo

- [x] Add dedicated `demo.device.primer.html`.
- [x] Show mixed required and optional checks.
- [x] Show at least one failed check with retry.
- [x] Show page-load modal preset example.

## Regression

- [x] Add browser regression coverage for:
  - initial pending state
  - auto-run
  - retry flow
  - aggregate readiness state
  - unsupported capability handling

## Docs

- [x] Update `README.md`.
- [x] Update `CHANGELOG.md`.
- [x] Update `docs/pbb-refactor-playbook.md`.
- [x] Update demo/catalog navigation.
