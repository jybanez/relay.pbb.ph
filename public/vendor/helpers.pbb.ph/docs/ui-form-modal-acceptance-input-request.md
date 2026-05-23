# UI Form Modal Acceptance Input Request

## Purpose

This document requests the exact inputs needed from the `PBB - HQ` team to complete acceptance-proof validation for the next `ui.form.modal` iteration.

The helper now has the new capability set implemented in principle. The remaining step is to prove that it is actually sufficient for the two agreed acceptance targets:

1. `Hub Add/Edit`
2. `Add Uplink`

This proof should be based on the real project contracts, not approximated demo guesses.

## Why We Need This

The current improvement stage added the following helper capabilities:

- `mode`
- `hidden`
- `display`
- declarative rules
- small layout extension
- hosted `ui.select`
- API error mapping helper

That is feature completion.

What remains is acceptance proof:

- confirm that the real `Hub Add/Edit` flow can be represented with the helper
- confirm that the real `Add Uplink` flow can be represented with the helper

To do that accurately, we need the real field, rule, payload, and error contracts from the project team.

## Requested Inputs

Please provide the following for each target flow.

---

## A. `Hub Add/Edit`

### 1. Current field inventory

Please provide the real field list for:

- `Hub Add`
- `Hub Edit`

For each field, include:

- field name
- visible label
- input type
- required or optional
- default value behavior
- whether it is editable, readonly, hidden, or display-only

### 2. Mode differences

Please describe exactly what changes between:

- create mode
- edit mode

Examples of the kind of detail needed:

- which fields are required only on create
- which fields become readonly on edit
- which fields are hidden on edit
- which labels/help text change by mode

### 3. Conditional rules

Please provide the real conditional logic for the form.

Especially important:

- deployment-driven behavior
- location-driven behavior
- generated-name behavior
- hidden submitted values that must still remain in payloads

Example format:

```text
If deployment = other:
- name is editable

If deployment = region/province/city/barangay:
- name is readonly
- location summary is display-only
- generated name must still be submitted as hidden value
```

### 4. Payload contract

Please provide the expected outgoing payload for:

- add
- edit

Include:

- exact payload keys
- hidden/generated values that must still be included
- any field-name mappings between UI and backend payload

### 5. Validation / error response examples

Please provide one or more real backend validation failure responses for this flow.

We need the actual shape used by the project so we can confirm whether:

- `ctx.applyApiErrors(...)`

is sufficient as implemented or needs adjustment.

### 6. Current implementation reference

Best case:

- file path(s) in the app where `Hub Add/Edit` is currently implemented

Acceptable fallback:

- screenshots
- copied form structure
- pseudocode of current form rendering and submit behavior

---

## B. `Add Uplink`

### 1. Current field inventory

Please provide the actual field list for the current `Add Uplink` flow.

For each field, include:

- field name
- visible label
- input type
- required or optional
- default value behavior

### 2. Uplink selection behavior

This is the most important part for this acceptance target.

Please specify:

- single-select or multi-select
- searchable or not
- static options or async-loaded options
- expected option label shape
- expected option value shape
- whether server-side query/filtering is required

### 3. Payload contract

Please provide the actual outgoing payload shape for `Add Uplink`.

Include:

- exact key names
- selected uplink value format
- any additional fields or notes required by the backend

### 4. Validation / error response examples

Please provide one or more real backend validation failure responses for this flow.

Especially useful:

- invalid/missing uplink selection
- duplicate/conflict response
- general form-level error response

### 5. Current implementation reference

Best case:

- file path(s) in the app where `Add Uplink` is currently implemented

Acceptable fallback:

- screenshots
- copied form structure
- pseudocode

---

## Preferred Response Format

The cleanest response is one section per target flow with:

1. field list
2. conditional rules
3. payload shape
4. example backend error response
5. current implementation reference

Example skeleton:

```text
Hub Add/Edit
- fields:
  - ...
- mode differences:
  - ...
- payload:
  - ...
- error response:
  - ...
- implementation files:
  - ...

Add Uplink
- fields:
  - ...
- select behavior:
  - ...
- payload:
  - ...
- error response:
  - ...
- implementation files:
  - ...
```

## Outcome

Once these inputs are provided, we can do the actual acceptance-proof step:

1. model the real `Hub Add/Edit` flow with `ui.form.modal`
2. model the real `Add Uplink` flow with `ui.form.modal`
3. determine whether the current improvement stage is truly sufficient

If those real flows still require substantial custom markup after this exercise, then the helper stage is not complete yet.
