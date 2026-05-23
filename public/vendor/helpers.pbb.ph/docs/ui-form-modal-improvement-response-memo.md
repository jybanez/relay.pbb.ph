# UI Form Modal Improvement Response Memo

## Purpose

This memo responds to `docs/ui-form-modal-improvement-proposal.md`.

It is intended to be forwardable as a short implementation-position document for the next stage of `ui.form.modal`.

## Position

I agree with the proposal's direction.

The proposal identifies real form-modal gaps that are now visible after actual project migrations, not speculative API design. The problem statement is credible because it is based on concrete remaining holdouts in `PBB - HQ`:

- Account
- Hub Add/Edit
- Add Uplink
- Hub Details

The core issue is also framed correctly:

- the blocker is not styling
- the blocker is form expressiveness

That is the right reason to evolve `ui.form.modal`.

## What I Agree With

### 1. Continue Using `ui.form.modal` As The Shared Direction

The proposal is correct not to retreat back to app-local modal markup.

`ui.form.modal` already provides meaningful shared value:

- helper-owned modal shell
- helper-owned busy handling
- helper-owned validation and error presentation
- consistent submit lifecycle
- preset wrappers for repeated auth flows

The next step should be to improve this shared helper so more medium-complexity forms can move onto it.

### 2. Add Explicit `mode`

This is the highest-value improvement.

Many repeated forms naturally have modes:

- create
- edit
- rotate
- confirm-with-reason

First-class `mode` support would remove a large amount of app-local branching.

### 3. Add `hidden` And `display` First

These are practical, low-risk additions that solve real current problems.

They directly support:

- submitted-but-not-editable values
- generated values that should remain in payloads
- readonly context that should look like information, not inputs

This is especially relevant for geodata and hub forms.

### 4. Add Conditional Rules

The proposal is correct that conditional behavior is one of the biggest missing pieces.

This is the clearest unlock for:

- conditional password rules
- deployment-driven field behavior
- create/edit differences
- readonly/hidden state transitions

### 5. Improve Select Integration

The proposal is also correct that the built-in native `select` is no longer sufficient for some relationship flows.

This matters most for:

- searchable option lists
- larger option sets
- async option loading
- richer option rendering

`Add Uplink` is a strong example of where this limitation is real.

### 6. Add API Error Mapping

This is a valid shared improvement.

`setErrors(...)` and `setFormError(...)` are already useful, but common response mapping is still app-local boilerplate today.

Reducing repeated response-to-error plumbing is an appropriate helper concern.

## What I Would Tighten

These are refinements, not objections.

### 1. Prefer Declarative Rules Before Function-Based Rules

The proposal shows both:

- `requiredWhen: ({ mode }) => ...`
- `requiredOn: ["create"]`

I would start with the declarative form first:

- `requiredOn`
- `hiddenOn`
- `readonlyOn`

Reason:

- easier to document
- easier to test
- easier to reason about
- less risk of turning schema into embedded app logic

Function-based rules can come later if there is proven need.

### 2. Keep New Item Types Narrow

I agree with:

- `hidden`
- `display`

I would be cautious with:

- `badge`
- `summary`
- `context`
- `group`

Those may be useful, but they expand the surface quickly and can overlap with each other.

Recommendation:

- add `hidden`
- add `display`
- treat richer context/header support as a top-level form-modal option or separate helper-owned concept, not a large new family of row item types immediately

### 3. `ui.select` Should Be Integrated, Not Reimplemented

This is an important architectural rule.

The right direction is:

- `type: "ui.select"`

with the form modal hosting the existing helper component cleanly.

The wrong direction would be:

- creating a second, modal-only select system with overlapping features

The proposal is already leaning in the right direction here. That should stay explicit.

### 4. Keep Layout Extensions Small

I agree with:

- `span: 1 | 2`
- maybe `rowClassName`

I would defer:

- arbitrary grid behavior
- deep grouping containers
- layout-engine-like nesting

The goal should be:

- make real forms possible
- do not reopen form-builder drift

## Recommended Implementation Order

I would slightly tighten the rollout order to maximize unlocks while containing risk:

1. add `mode`
2. add `hidden` and `display`
3. add declarative conditional rules:
   - `requiredOn`
   - `hiddenOn`
   - `readonlyOn`
4. add `span: 1 | 2`
5. add `ui.select` integration
6. add helper API error mapping
7. add richer context/header strip support

This ordering gets the highest-value unlocks first.

## Highest-Value Unlocks

### 1. Hub Add/Edit

This looks like the best acceptance target for the next stage.

It would likely move onto `ui.form.modal` once the helper supports:

- `mode`
- `hidden`
- `display`
- conditional rules

### 2. Add Uplink

This is the strongest justification for richer select integration.

If `ui.form.modal` can host helper-owned rich selection cleanly, this becomes a much better candidate for migration.

### 3. Account

This becomes materially cleaner once conditional mode semantics exist, especially for password behavior across create/edit flows.

## Recommended Design Rule

`ui.form.modal` should remain:

- schema-driven
- compact
- helper-owned

But it now needs enough expressive power to cover real admin forms that are still being forced into custom markup.

The right principle is:

- keep simple forms simple
- add progressive capability for real-world conditional forms
- reuse helper-owned component vocabulary where possible
- avoid inventing modal-only replacements for existing shared components

## Recommendation

Proceed with the proposal in principle.

But tighten the first implementation boundary around:

- `mode`
- `hidden`
- `display`
- declarative conditional rules
- narrow layout extension
- helper-owned select integration

That is the safest path to increasing the usefulness of `ui.form.modal` without turning it into a general-purpose form builder prematurely.

## Final Summary

This proposal is valid and timely.

It reflects real migration pressure and identifies the correct next-stage improvements for `ui.form.modal`.

My recommendation is:

- accept the direction
- keep the first pass narrow and declarative
- validate it through real migrations, especially:
  - `Hub Add/Edit`
  - `Add Uplink`

Those two flows should be treated as the real acceptance tests for this next improvement stage.

## Follow-Up

Proposal under review:

- `docs/ui-form-modal-improvement-proposal.md`

Existing base form-modal contract:

- `docs/helpers-schema-form-modal-v1-spec.md`

Existing preset-wrapper contract:

- `docs/helpers-schema-form-modal-v1-presets-spec.md`

Implementation checklist:

- `docs/ui-form-modal-improvement-implementation-checklist.md`
