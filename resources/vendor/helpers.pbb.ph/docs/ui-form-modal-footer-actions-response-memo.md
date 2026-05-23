# UI Form Modal Footer Actions Response Memo

## Summary

`PBB HQ` identified a real helper gap in `createFormModal(...)`:

- the helper owns the footer
- but the footer was effectively fixed to:
  - `Cancel`
  - `Submit`
- app-side attempts to add a third footer action through `modal.update({ actions: [...] })` were overwritten by the helper's own footer rebuild

The gap is valid and should be fixed in the helper layer.

## Decision

Adopt the additive `extraActions` direction proposed by HQ.

Why:

- preserves the current helper-owned cancel/submit contract
- avoids opening a full footer-replacement API
- solves the actual HQ `Account` modal case cleanly
- is lower risk than treating `actions` as a fully app-owned surface

## V1 Contract

- `createFormModal(options)` accepts:
  - `extraActions: FormModalExtraAction[]`
  - `extraActionsPlacement: "start" | "end"`
- footer order is:
  1. `extraActions` in provided order
  2. helper-owned `Cancel`
  3. helper-owned `Submit`
- placement default is:
  - `"end"`
- `"start"` is available for acceptance cases such as:
  - `Change Password | Cancel | Save`
- reserved IDs:
  - `cancel`
  - `submit`
- extra-action callbacks receive:
  - current `values`
  - helper-owned `ctx`
  - current `actionId`
- default extra-action behavior is:
  - does not close the form modal unless explicitly opted in
- busy state remains helper-owned:
  - extra actions disable together with the rest of the footer

## Acceptance Target

Use the `PBB HQ` `Account` modal as the first acceptance case:

- footer renders:
  - `Change Password`
  - `Cancel`
  - `Save`
- `Change Password` can open a second modal without closing the account form
- `Save` still uses the standard helper-owned submit path

## Non-Goals

This change does not add:

- full footer replacement
- app-owned `cancel` / `submit` action rewriting
- busy-safe extra actions
- footer grouping or left/right footer regions
