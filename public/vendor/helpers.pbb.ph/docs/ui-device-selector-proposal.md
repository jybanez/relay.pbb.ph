# UI Device Selector Proposal

## Summary

Add a shared `ui.device.selector` helper for selecting and testing connected user devices from PBB browser apps.

The helper should provide a consistent device-selection surface for cameras, microphones, speakers, and future device classes without pretending the browser can universally enumerate or control every USB, Bluetooth, printer, scanner, or workstation peripheral.

V1 should focus on a practical browser-native media-device adapter and a clean adapter contract that can later support WebUSB, Web Bluetooth, printers, or a trusted PBB Local Device Agent.

## Why This Is Needed

PBB browser projects are starting to rely on workstation-attached devices:

- Hotline citizen/operator surfaces may need camera, microphone, speaker, headset, or audio-output selection.
- Realtime/media flows need predictable device labels, permission prompts, and test actions.
- Workspace-hosted apps may need a common way to request, choose, and remember devices without each app building its own controls.
- Future barangay desk workflows may need printers, scanners, radios, POS-style peripherals, or Bluetooth devices.

Without a shared helper, each project will likely drift in:

- permission language
- device-list loading states
- selected-device persistence behavior
- test/playback UX
- unsupported-browser handling
- retry and refresh affordances
- compact status placement in navbars or setup panels

## Recommended Component

Introduce:

- `ui.device.selector`

Factory direction:

```js
const selector = createDeviceSelector(container, data, options);
```

The component should be adapter-driven:

```js
const selector = createDeviceSelector(container, {
  kind: 'camera',
  selectedDeviceId: savedCameraId,
  devices: []
}, {
  adapter: createMediaDeviceAdapter({ kind: 'videoinput' }),
  onSelectionChange(selection) {
    saveSelection(selection);
  }
});
```

The helper should also expose built-in adapter factories for common browser media devices:

- `createMediaDeviceAdapter({ kind: 'audioinput' })`
- `createMediaDeviceAdapter({ kind: 'videoinput' })`
- `createMediaDeviceAdapter({ kind: 'audiooutput' })`

## Recommended Scope

`ui.device.selector` should own:

- selection/list UI
- loading, empty, permission-needed, unsupported, and error states
- refresh/retry/request-permission actions
- selected-device presentation
- helper-owned test action placement
- compact and regular layouts
- structured events for selection, refresh, permission request, test, connect, and disconnect
- accessibility labels and stable sizing for navbar/status placement
- adapter normalization so different device sources can render consistently

It should not own:

- app-specific media session orchestration
- Realtime call setup
- camera capture pipelines
- audio graph routing beyond invoking adapter hooks
- printer queue ownership
- USB/Bluetooth device protocol implementations
- silent device access that bypasses user permission
- OS-level enumeration unless a trusted local agent provides it
- storage policy beyond optional app-provided persistence hooks

## Relationship To `ui.device.primer`

`ui.device.primer` checks whether required capabilities are ready.

`ui.device.selector` chooses which device should be used.

They should compose, but stay separate:

- Primer can say "microphone is available and permission is granted."
- Selector can let the user choose "USB Headset Microphone" and run a test.
- Apps decide whether selection is required before entering a workflow.

The device primer may later render selector rows as custom check content, but the selector should not become a startup-gating component by itself.

## Browser Reality And Platform Limits

The component must be designed around current browser constraints:

- Camera and microphone enumeration depends on secure context and user permission.
- Device labels may be hidden until permission is granted.
- Audio output selection is browser-dependent and may require `selectAudioOutput()` and `setSinkId()`.
- WebUSB and Web Bluetooth require a user gesture and explicit permission, and they expose only devices selected through the browser prompt.
- WebUSB/Web Bluetooth require device-specific filters and protocols; they are not generic "show all attached devices" APIs.
- Browser printing generally opens the OS/browser print dialog; installed printer enumeration and silent routing are not reliable in normal browser apps.

This means Helper should provide honest states such as:

- `unsupported`
- `permission-required`
- `permission-denied`
- `no-devices`
- `needs-user-action`
- `ready`
- `testing`
- `test-failed`

## Recommended Device Model

Adapters should normalize devices into a common shape:

```js
{
  id: 'device-id-or-stable-agent-id',
  label: 'USB Camera',
  kind: 'camera',
  groupId: 'optional-group-id',
  transport: 'browser-media',
  status: 'available',
  capabilities: {
    canTest: true,
    canConnect: false,
    canDisconnect: false,
    canSetDefault: true
  },
  raw: null
}
```

Recommended `kind` values:

- `camera`
- `microphone`
- `speaker`
- `printer`
- `scanner`
- `usb`
- `bluetooth`
- `serial`
- `hid`
- `custom`

Recommended `transport` values:

- `browser-media`
- `webusb`
- `webbluetooth`
- `webserial`
- `webhid`
- `local-agent`
- `server`
- `manual`

## Recommended Adapter Contract

Adapters should be plain JavaScript objects with optional capabilities:

```js
const adapter = {
  kind: 'camera',
  async isSupported() {},
  async listDevices(context) {},
  async requestPermission(context) {},
  async requestDevice(context) {},
  async selectDevice(device, context) {},
  async testDevice(device, context) {},
  async connect(device, context) {},
  async disconnect(device, context) {},
  subscribe?(listener, context) {}
};
```

The selector should call only the hooks present on the adapter and render unsupported actions as unavailable.

## Recommended Instance API

V1 should expose:

- `update(data, options?)`
- `refresh()`
- `requestPermission()`
- `requestDevice()`
- `selectDevice(deviceId)`
- `testSelectedDevice()`
- `getState()`
- `destroy()`

`getState()` should include:

- current device kind
- normalized devices
- selected device
- permission/status state
- last error
- test result
- adapter support state

## Recommended V1 Scope

Start with browser media devices:

- camera selection from `videoinput`
- microphone selection from `audioinput`
- speaker/output selection from `audiooutput` where supported
- refresh devices
- request permission
- selected-device callback
- optional test action
- compact layout for navbar/status regions
- regular layout for setup panels/modals

Recommended V1 non-goals:

- direct WebUSB implementation
- direct Web Bluetooth implementation
- printer enumeration
- native local-agent protocol
- media recording
- call/session join flow
- cross-origin iframe permission forwarding beyond documented host-app requirements

## V2 And Later Direction

Later work can add adapter packages or examples for:

- WebUSB device request flows with app-provided filters.
- Web Bluetooth device request flows with app-provided service filters.
- WebSerial/WebHID where project requirements justify them.
- Printer launcher UI that delegates to `window.print()` or app/backend print routes.
- PBB Local Device Agent integration for reliable OS-level enumeration and approved device control.

The PBB Local Device Agent direction should be treated as a separate proposal because it introduces:

- desktop/runtime installation
- localhost trust
- authentication between browser app and local agent
- device allowlists
- audit logging
- update/operations policy
- security review

## Security And Privacy Requirements

The selector should preserve browser security expectations:

- never auto-request powerful permissions on page load without app intent
- make user-triggered request actions explicit
- avoid exposing raw device details in visible UI unless needed
- allow apps to pass custom labels when browser labels are unavailable
- keep selected-device persistence app-owned
- avoid logging raw hardware identifiers by default
- expose enough event context for apps to audit selection changes when required

## Accessibility And UX Requirements

V1 should support:

- clear label and description
- keyboard-operable refresh, request, test, and select controls
- `aria-live` status updates where appropriate
- stable width in compact mode
- no layout jump when labels appear after permission grant
- concise failure text with retry action
- disabled action explanation where an adapter does not support an operation

## Open Questions

- Resolved: use `ui.device.selector` rather than `ui.device.picker`.
- Resolved: keep built-in media adapters in the same V1 module unless implementation size later justifies a split.
- Resolved: show speaker selection with a clear system-default/fallback row when direct output routing is unavailable.
- Resolved: keep selected-device persistence app-owned through callbacks for V1.
- Resolved: skip a dedicated modal wrapper in V1; apps can compose the selector inside existing modal helpers.

## Recommendation

Proceed with `ui.device.selector` V1 as a transport-agnostic selector plus built-in browser-media adapters.

Keep the first implementation small and useful for Hotline/Realtime media workflows, while establishing the adapter contract that can support USB, Bluetooth, printer, and local-agent integrations later without rewriting the UI.
