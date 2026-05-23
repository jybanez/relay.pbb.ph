# UI Device Selector Implementation Checklist

## Proposal And Scope

- [x] Draft `ui.device.selector` proposal.
- [x] Confirm registry naming as `ui.device.selector`.
- [x] Confirm V1 should include built-in browser-media adapters only.
- [x] Confirm WebUSB, Web Bluetooth, printer, and local-agent integrations are V2+ adapter work.
- [x] Decide V1 should use core component composition, without a dedicated modal wrapper.
- [x] Decide selected-device persistence remains app-owned through callbacks.
- [x] Decide speaker selection should show a clear system-default/fallback row when direct output routing is unavailable.

## V1 Contract

- [x] Define normalized device model:
  - `id`
  - `label`
  - `kind`
  - `groupId`
  - `transport`
  - `status`
  - `capabilities`
  - `raw`
- [x] Define normalized state model:
  - `idle`
  - `loading`
  - `ready`
  - `unsupported`
  - `permission-required`
  - `permission-denied`
  - `no-devices`
  - `needs-user-action`
  - `testing`
  - `test-failed`
  - `error`
- [x] Define adapter contract:
  - `isSupported(context)`
  - `listDevices(context)`
  - `requestPermission(context)`
  - `requestDevice(context)`
  - `selectDevice(device, context)`
  - `testDevice(device, context)`
  - `connect(device, context)`
  - `disconnect(device, context)`
  - `subscribe(listener, context)`
- [x] Define instance API:
  - `update(data, options?)`
  - `refresh()`
  - `requestPermission()`
  - `requestDevice()`
  - `selectDevice(deviceId)`
  - `testSelectedDevice()`
  - `getState()`
  - `destroy()`

## Runtime

- [x] Add `js/ui/ui.device.selector.js`.
- [x] Add `css/ui/ui.device.selector.css`.
- [x] Register `ui.device.selector` in the loader/registry.
- [x] Add regular layout for setup panels.
- [x] Add compact layout for navbars/status regions.
- [x] Add device list rendering.
- [x] Add selected-device rendering.
- [x] Add loading, empty, permission, unsupported, and error states.
- [x] Add refresh action.
- [x] Add request-permission action.
- [x] Add request-device action for adapters that need browser prompts.
- [x] Add test action for adapters that support testing.
- [x] Add structured callbacks:
  - `onRefresh`
  - `onPermissionRequest`
  - `onRequestDevice`
  - `onSelectionChange`
  - `onTestStart`
  - `onTestComplete`
  - `onError`
- [x] Add cleanup for event listeners, adapter subscriptions, and hosted controls.

## Browser Media Adapter

- [x] Add browser-media adapter factory.
- [x] Support `audioinput` as `microphone`.
- [x] Support `videoinput` as `camera`.
- [x] Support `audiooutput` as `speaker` when available.
- [x] Use `navigator.mediaDevices.enumerateDevices()` for list refresh.
- [x] Request microphone/camera permission through app/user-triggered flow.
- [x] Handle hidden labels before permission grant.
- [x] Handle `devicechange` events where supported.
- [x] Support speaker output fallback when `selectAudioOutput()` or `setSinkId()` is unavailable.
- [x] Provide test hooks:
  - microphone test can confirm stream acquisition
  - camera test can confirm stream acquisition
  - speaker test can play app-provided sample audio where supported
- [x] Stop any temporary media streams after tests.

## Demo

- [x] Add `demos/demo.device.selector.html`.
- [x] Show camera selector.
- [x] Show microphone selector.
- [x] Show speaker selector with fallback messaging.
- [x] Show compact selector example suitable for navbar/status placement.
- [x] Show adapter-driven mock device example for future USB/Bluetooth/local-agent use.
- [x] Show permission-needed and unsupported states.
- [x] Add demo catalog/sidebar navigation.

## Regression

- [x] Add `tests/device.selector.regression.html`.
- [x] Add `tests/device.selector.regression.mjs`.
- [x] Cover initial render.
- [x] Cover loading and empty states.
- [x] Cover permission-required state.
- [x] Cover unsupported adapter state.
- [x] Cover device selection callback.
- [x] Cover refresh callback.
- [x] Cover test action success/failure states.
- [x] Cover compact layout stability.
- [x] Cover `update(...)`, `getState()`, and `destroy()` cleanup.

## Docs

- [x] Add README section for `ui.device.selector`.
- [x] Document normalized device model.
- [x] Document adapter contract.
- [x] Document browser-media adapter usage.
- [x] Document browser limitations for media, speaker output, USB, Bluetooth, and printing.
- [x] Update `CHANGELOG.md`.
- [ ] Update `docs/pbb-refactor-playbook.md` if the component becomes part of the recommended browser-app baseline.

## Bundle And Verification

- [x] Run syntax checks for new JS.
- [x] Run device selector regression test.
- [x] Run existing `ui.device.primer` regression test.
- [x] Run registry contract test.
- [x] Run UI bundle contract test.
- [x] Run `npm run build:ui-bundle`.
- [x] Verify rebuilt `dist/helpers.ui.bundle.min.js`.
- [x] Verify rebuilt `dist/helpers.ui.bundle.min.css`.
- [x] Browser-check `demos/demo.device.selector.html`.
- [x] Browser-check `demos/index.html` catalog link.

## Cross-Project Follow-Up

- [ ] Ask Hotline Beta whether V1 media selection covers its citizen/operator setup flows.
- [ ] Ask Realtime whether SDK docs should reference selector integration for media-device choice.
- [ ] Ask Workspace whether hosted iframe apps need a parent-owned device selector or only app-local selectors.
- [ ] Draft a separate PBB Local Device Agent proposal before attempting reliable OS-level USB/Bluetooth/printer enumeration.
