# UI Form Modal Acceptance Proof

## Purpose

Record the real acceptance-proof outcome for the approved `ui.form.modal` improvement stage.

This document validates the helper against the agreed proof targets:

1. `Hub Add/Edit`
2. `Add Uplink`

Working basis:

- `docs/ui-form-modal-improvement-response-memo.md`
- `docs/ui-form-modal-improvement-implementation-checklist.md`
- `docs/ui-form-modal-acceptance-input-response.md`

Reference implementation sources reviewed:

- `c:\wamp64\www\pbb\hub.ph\resources\js\app.js`
- `c:\wamp64\www\pbb\hub.ph\app\Http\Controllers\Api\HubController.php`

## Result

The current `ui.form.modal` implementation is sufficient for the accepted proof targets.

The only remaining gap identified during review was a narrow top-level context strip for geodata-driven hub flows. That support is now present through the `context` option.

No broader layout system or general-purpose form-builder expansion was required.

## Acceptance Target: `Hub Add/Edit`

### Real flow findings from PBB HQ

The real app does not have one generic hub form. It has two meaningful variants:

1. geodata-driven flow
2. `other` deployment flow

The critical branching axis is the flow shape, not only `create` vs `edit`.

### Geodata-driven hub flow

Observed requirements:

- top context strip:
  - deployment badge
  - location summary
- hidden generated values:
  - `name`
  - `deployment`
  - `country_code`
  - `reg_code`
  - `prov_code`
  - `citymun_code`
  - `brgy_code`
- visible editable fields:
  - `status`
  - `code`
  - `domain`

Current helper coverage:

- `context`
  - covers the top strip without adding a larger field family
- `hidden`
  - covers generated/submitted location values
- `display`
  - can expose generated name or other read-only helper-owned context inside the form body
- `ui.select`
  - covers hosted status selection
- `span: 2`
  - covers simple full-width rows where needed

### `Other` deployment hub flow

Observed requirements:

- editable:
  - `name`
  - `status`
  - `code`
  - `domain`
- hidden fixed values:
  - `deployment = other`
  - `country_code`
  - empty geo location codes

Current helper coverage:

- standard input rows cover the editable fields
- `hidden` covers fixed submitted values
- hosted `ui.select` covers status parity with the shared helper vocabulary

### Conclusion for `Hub Add/Edit`

Accepted.

`ui.form.modal` can now represent both real hub editor variants without falling back to custom modal body markup for the core field logic.

## Acceptance Target: `Add Uplink`

### Real flow findings from PBB HQ

Observed requirements:

- hosted multi-select relationship field
- helper-owned searchable selection
- static options from loaded hub state
- submitted values originate as string ids in the UI
- payload merges those values back into a full hub update payload as integer ids
- Laravel validation may return dotted field keys such as:
  - `uplink_hub_ids.0`

### Current helper coverage

- `type: "ui.select"`
  - hosts the existing shared select helper
- `multiple`
  - covers multi-uplink selection
- `searchable`
  - covers the real usability requirement
- `getValues()` / `setValues(...)`
  - preserve the selected array contract
- app-owned submit logic
  - can convert string ids to integers before submit
- `applyApiErrors(...)`
  - now maps dotted backend keys back to the base field when possible

### Conclusion for `Add Uplink`

Accepted.

`ui.form.modal` can now represent the real Add Uplink flow cleanly enough to count as proof coverage for this stage.

## What Changed To Reach Acceptance

The improvement stage is accepted because the helper now includes:

- `mode`
- `hidden`
- `display`
- declarative rules:
  - `requiredOn`
  - `hiddenOn`
  - `readonlyOn`
- `span: 1 | 2`
- hosted `ui.select`
- `applyApiErrors(...)`
- narrow top-level `context`

These additions were enough to satisfy the proof targets without reopening the earlier rejected form-builder drift.

## What Was Explicitly Not Needed

The proof review did not require:

- arbitrary nested layout engines
- function-based conditional rules
- form-modal-specific select reimplementation
- large context/header field families
- general-purpose grouping containers

That is an important result. The narrower implementation boundary held.

## Demo Proof Surface

Current proof-oriented demo coverage lives in:

- `demos/demo.form.modal.html`

Relevant examples:

- `Open Hub Add Style`
- `Open Hub Edit Style`
- `Open Add Uplink Style`

These examples are now aligned to the real PBB HQ flow shapes instead of only representative helper feature demos.

## Regression Coverage

Current regression coverage lives in:

- `tests/form.modal.regression.html`
- `tests/form.modal.regression.mjs`

The regression suite now covers:

- mode-driven rules
- hidden/display fields
- span behavior
- narrow context strip rendering
- hosted `ui.select`
- dotted backend error mapping to base fields

## Final Judgment

The improvement checklist can be treated as complete for the approved scope.

The helper is now strong enough for:

1. `Hub Add/Edit`
2. `Add Uplink`

Any future work should be treated as a new proposal stage rather than as unfinished acceptance work from this one.
