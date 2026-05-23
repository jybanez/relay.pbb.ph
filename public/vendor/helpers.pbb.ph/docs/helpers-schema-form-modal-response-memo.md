# Helpers Schema Form Modal Response Memo

## Purpose

This memo responds to the `helpers` team’s counter-proposal for a schema-driven modal-form utility.

It is intended to be forwardable as a short implementation-position document.

## Position

I agree with the counter-proposal’s direction.

The original proposal identified the correct shared problem:

- PBB projects repeatedly rebuild the same modal + form + submit + error + busy-state workflow

The counter-proposal improves this by narrowing the first implementation into a V1 that is:

- realistic to ship
- aligned with the current `helpers.pbb.ph` architecture
- easier to keep stable
- less likely to turn into a general form-builder too early

## What I Support In The Counter-Proposal

### 1. `createFormModal(...)` as the public API

This is a better public shape than inventing a new modal naming pattern.

It is:

- clear
- direct
- consistent with current factory naming in the helpers library

### 2. Building on `createActionModal(...)`

This is the correct architectural rule.

The new form helper should reuse helper-owned behavior for:

- modal shell
- footer actions
- busy state
- focus management
- close rules

It should not create a second modal system.

### 3. Strict `rows` model for V1

The counter-proposal is right to replace the broader `lines + nested grid/content` direction with a simpler row-based model.

That is a better first contract because it is:

- easier to read
- easier to document
- easier to validate
- much less likely to drift into a layout engine

### 4. Narrow V1 scope

The counter-proposal correctly keeps V1 focused on:

- login modal
- re-auth modal
- simple create/edit modals
- short CRUD flows

That is the right target.

### 5. Clear validation boundary

The split is correct:

- helper owns required/basic form validation
- app owns domain/business validation

That boundary needs to stay explicit.

### 6. Limiting footer action flexibility in V1

The proposal is correct to avoid opening arbitrary footer/action configuration too early.

For most modal forms, V1 only needs:

- `Cancel`
- `Submit`

That keeps the contract smaller and easier to prove.

## Why I Think This Is The Right V1

This version solves a real repeated problem without overreaching.

It gives the helpers team a reusable primitive for a pattern that already exists across projects, while avoiding:

- generic form-builder expectations
- arbitrary layout DSL complexity
- premature API surface growth

This is exactly the kind of component that belongs in the shared helpers library.

## Minor Suggestions

These are small refinements, not objections.

### 1. Keep these item types in V1

I support keeping these in the first version:

- `text`
- `alert`
- `divider`
- `input`
- `textarea`
- `select`
- `checkbox`

That feels like the right minimal set.

### 2. Make row behavior explicit

The row behavior should be written clearly in the contract:

- one item in a row = full width
- two items in a row = equal columns
- more than two items = reject, warn, or normalize conservatively

This will prevent ambiguity early.

### 3. Consider `focusField(name)` later

Not required for V1, but worth keeping in mind for a later iteration.

That would help with:

- validation flows
- login/re-auth
- edit modals

## Recommendation

Use the counter-proposal as the implementation basis.

Treat the original proposal as:

- the motivation
- the problem statement
- the broader future direction

But treat the counter-proposal as:

- the actual V1 contract
- the safer implementation boundary

## Recommended First Migrations

The best first migrations are:

1. login modal
2. session-expired re-login modal
3. one simple CRUD modal

That is enough to validate:

- row layout
- value collection
- error handling
- busy submit lifecycle
- real app integration

before broadening the component further.

## Final Summary

The original proposal identified the right shared UI problem.

The counter-proposal defines the better first implementation.

So my recommendation is:

- proceed with the helpers team’s counter-proposal
- keep the original proposal as supporting context
- validate the V1 through real migrations before expanding the API surface

## Follow-Up

For the consolidated implementation-facing contract, see:

- `docs/helpers-schema-form-modal-v1-spec.md`

For the preset-wrapper layer built on top of the base helper, see:

- `docs/helpers-schema-form-modal-v1-presets-spec.md`
