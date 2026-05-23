# UI Form Modal Improvement Implementation Plan

## Purpose

Translate the approved improvement checklist into a concrete build sequence for `ui.form.modal`.

This plan is intentionally narrower than the broader proposal. It is designed to ship the highest-value unlocks first while keeping the helper aligned with the existing modal architecture.

## Working Basis

- `docs/ui-form-modal-improvement-response-memo.md`
- `docs/ui-form-modal-improvement-implementation-checklist.md`
- `docs/helpers-schema-form-modal-v1-spec.md`
- `docs/helpers-schema-form-modal-v1-presets-spec.md`

## Acceptance Targets

The implementation must be evaluated against:

1. `Hub Add/Edit`
2. `Add Uplink`

These flows define whether the helper has become expressive enough.

## Phase 1

Deliver the smallest set of changes that unlock medium-complexity conditional forms without reopening form-builder drift.

Included in this phase:

1. modal-level `mode`
2. item types:
   - `hidden`
   - `display`
3. declarative rules:
   - `requiredOn`
   - `hiddenOn`
   - `readonlyOn`
4. small layout extension:
   - `span: 1 | 2`
5. helper API error mapping:
   - `ctx.applyApiErrors(response)`

Deferred from this phase:

- `ui.select` integration
- richer context/header strip
- function-based conditional rules
- grouping/layout containers

## Why Phase 1 First

This set gives the best unlock-per-risk ratio.

It should be enough to prove:

- create/edit semantics
- hidden submitted values
- display-only context values
- deployment-driven visibility/readonly logic
- modest form layout control

That makes it the right first proof step for `Hub Add/Edit`.

## Code Changes

### 1. `js/ui/ui.form.modal.js`

Add:

- `mode` to normalized options
- first-pass rule evaluation helper
- support for:
  - `hidden`
  - `display`
- `span`-aware row/item rendering
- hidden-field value participation in `getValues()`
- display-item rendering that does not participate in payload unless explicitly configured later
- `ctx.applyApiErrors(response)`

Constraints:

- do not fork modal behavior
- preserve current V1 row model
- keep current field types working unchanged

### 2. `css/ui/ui.form.modal.css`

Add or update styles for:

- hidden field wrappers if needed
- display field layout
- span-based width behavior
- rule-driven hidden state behavior

Constraint:

- keep styling modest and aligned with existing helper tokens/components

### 3. `README.md`

Update the `createFormModal(...)` section to document:

- `mode`
- `hidden`
- `display`
- declarative rules
- `span`
- `ctx.applyApiErrors(response)`

### 4. Demos

Update:

- `demos/demo.form.modal.html`

Add examples for:

- create/edit mode behavior
- hidden + display values
- declarative rule behavior
- span usage
- API error mapping

One example should be labeled as:

- `Hub Add/Edit style`

## Regression Coverage

Extend browser regression coverage for:

- `mode`-driven rule evaluation
- hidden-field payload behavior
- display rendering
- span rendering class behavior
- API error mapping to field/form errors

Recommended file:

- extend `tests/form.modal.regression.*`

Keep preset-wrapper coverage separate unless a preset is changed.

## Phase 2

Only start after Phase 1 is validated against `Hub Add/Edit`.

Included:

1. `ui.select` integration
2. `Add Uplink` demo/proof flow

This phase should:

- host helper-owned select behavior
- not reimplement select logic inside form modal
- preserve form-modal value/error contract

## Delivery Sequence

1. implement Phase 1 runtime changes
2. add demo coverage
3. add regression coverage
4. update docs
5. validate against `Hub Add/Edit`
6. start Phase 2 for `ui.select` and `Add Uplink`

## Completion Criteria For This Plan

Phase 1 is done when:

1. runtime support exists for:
   - `mode`
   - `hidden`
   - `display`
   - `requiredOn`
   - `hiddenOn`
   - `readonlyOn`
   - `span`
   - `applyApiErrors(...)`
2. demo coverage exists
3. regression coverage exists
4. README is updated
5. `Hub Add/Edit` is representable without custom body markup for the core field logic

Phase 2 is done when:

1. `ui.select` integration exists
2. demo coverage exists
3. regression coverage exists
4. `Add Uplink` is representable without falling back to native-select limitations
