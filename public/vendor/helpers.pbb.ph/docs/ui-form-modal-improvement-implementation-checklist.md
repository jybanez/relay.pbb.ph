# UI Form Modal Improvement Implementation Checklist

## Purpose

Turn the approved improvement direction for `ui.form.modal` into an implementation checklist that is:

- narrow
- acceptance-target-driven
- aligned with existing helper architecture

This checklist uses the following documents as working basis:

- `docs/ui-form-modal-improvement-response-memo.md`
- `docs/helpers-schema-form-modal-v1-spec.md`
- `docs/helpers-schema-form-modal-v1-presets-spec.md`

Acceptance-proof result:

- `docs/ui-form-modal-acceptance-proof.md`

Current status:

- implementation items complete for the approved scope
- both proof targets are now represented by the helper

## Acceptance Targets

The next `ui.form.modal` iteration must be validated against:

1. `Hub Add/Edit`
2. `Add Uplink`

These are not optional examples. They are the proof that the improvement stage is strong enough.

## Implementation Scope

This stage should add only the following shared capabilities:

1. `mode`
2. `hidden`
3. `display`
4. declarative field rules
5. small layout extension
6. `ui.select` integration
7. helper API error mapping

Deferred:

- function-based conditional rules
- large grouping/layout systems
- modal-only replacement controls for helper-owned components
- general-purpose form-builder behavior

## Checklist

### 1. Modal-Level Mode Support

- add `mode` to `createFormModal(options)`
- expose current `mode` through form context/state
- ensure field evaluation can reference modal mode
- document supported first-pass semantics:
  - `create`
  - `edit`
  - other app-owned strings allowed

Acceptance relevance:

- `Hub Add/Edit`
- `Account`

### 2. New Item Types: `hidden` And `display`

- add `hidden` item type
- add `display` item type
- ensure `hidden` participates in values/payload output
- ensure `display` is visual-only unless explicitly configured otherwise
- document how these differ from disabled/read-only inputs

Acceptance relevance:

- `Hub Add/Edit`
- `Hub Details`

### 3. Declarative Field Rules

Implement first-pass declarative rules only:

- `requiredOn`
- `hiddenOn`
- `readonlyOn`

Optional if still narrow enough:

- `disabledOn`

Do not add function-based rules in this pass unless blocked.

Requirements:

- rules evaluate against modal `mode`
- rules are deterministic and documented
- field visibility and validation stay synchronized
- hidden fields do not show validation errors

Acceptance relevance:

- `Hub Add/Edit`
- `Account`

### 4. Small Layout Extension

- add `span: 1 | 2`
- preserve existing row model
- do not add arbitrary grid nesting
- keep row warning behavior for unsupported layouts
- optionally add `rowClassName` only if clearly needed during migration

Acceptance relevance:

- `Hub Add/Edit`
- other medium-complexity admin forms

### 5. `ui.select` Integration

- add form item support for `type: "ui.select"`
- integrate existing helper-owned `ui.select`
- do not reimplement select logic inside `ui.form.modal`
- support value read/write through the same form-modal value contract
- support validation/error presentation parity with native fields
- support app-supplied options and async loading where the helper `ui.select` contract already allows it

Acceptance relevance:

- `Add Uplink`

### 6. API Error Mapping Helper

- add a helper context method such as:
  - `ctx.applyApiErrors(response)`
- support one or two documented common response shapes only
- map into:
  - field errors
  - form error
- preserve `setErrors(...)` and `setFormError(...)` for manual control
- do not overgeneralize into backend-specific assumptions

Acceptance relevance:

- `Hub Add/Edit`
- `Add Uplink`
- other CRUD forms

### 7. Context/Header Support

Only add this if still needed after the earlier items are implemented.

Preferred direction:

- a narrow top-level `context` option

Do not add a large new item family unless the narrower option proves insufficient.

Acceptance relevance:

- `Hub Add/Edit`
- `Hub Details`

## Validation Checklist By Acceptance Target

### A. `Hub Add/Edit`

The helper iteration is acceptable only if this flow can be represented without falling back to custom modal body markup for the core field logic.

Must support:

- create/edit `mode`
- deployment-driven readonly/hidden behavior
- generated hidden submitted values
- visible display-only context
- modest layout control
- helper busy submit lifecycle
- helper error handling

### B. `Add Uplink`

The helper iteration is acceptable only if this flow can be represented without reverting to native-select limitations.

Must support:

- helper-owned rich select integration
- larger/searchable option sets
- app-owned async submit
- helper busy state
- helper field/form errors

## Demo Requirements

Add or update demo coverage for:

1. `mode`-driven create/edit example
2. `hidden` + `display` example
3. declarative rule example
4. `span: 2` example
5. `ui.select`-integrated form example
6. API error mapping example

Recommended:

- keep these on `demos/demo.form.modal.html`
- add one example explicitly labeled:
  - `Hub Add/Edit style`
- add one example explicitly labeled:
  - `Add Uplink style`

## Documentation Checklist

- update `README.md`
- update `docs/helpers-schema-form-modal-v1-spec.md` if the base contract changes materially
- update `docs/helpers-schema-form-modal-v1-presets-spec.md` if preset behavior changes materially
- add a short comparison/link note from:
  - `docs/ui-form-modal-improvement-proposal.md`
  - `docs/ui-form-modal-improvement-response-memo.md`

For each new contract addition, document:

- option/item name
- type
- default
- behavior rules
- effect on values/errors/validation

## Regression Checklist

Extend browser regression coverage for:

- `mode`-driven rule evaluation
- hidden-field payload behavior
- display-field rendering behavior
- `span` rendering
- `ui.select` value/error integration
- API error mapping behavior

Keep coverage separated where useful:

- base `form.modal` behavior
- preset-wrapper behavior
- improvement-stage feature behavior

## Completion Gate

This stage should be considered complete only when:

1. the new helper capabilities are implemented
2. the docs are updated
3. regression coverage exists
4. demo coverage exists
5. `Hub Add/Edit` is representable with the helper
6. `Add Uplink` is representable with the helper

If those two acceptance targets still require substantial custom modal body markup, this improvement stage is not done.
