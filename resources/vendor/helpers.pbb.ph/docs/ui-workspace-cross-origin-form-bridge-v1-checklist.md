# UI Workspace Cross-Origin Form Bridge V1 Checklist

## Docs

- [x] Draft proposal in `docs/ui-workspace-cross-origin-form-bridge-proposal.md`
- [x] Draft V1 spec in `docs/ui-workspace-cross-origin-form-bridge-v1-spec.md`
- [x] Draft implementation checklist in `docs/ui-workspace-cross-origin-form-bridge-v1-checklist.md`
- [x] Add concrete sample request/response messages for:
  - login
  - re-auth
  - schema-style admin form modal
- [x] Update `README.md` if the contract moves from proposal into implementation
- [x] Update `CHANGELOG.md` if runtime work ships

## Contract Alignment

- [x] Confirm protocol-style method naming with Workspace team:
  - `modal.form.open`
- [x] Confirm `namespace` versioning for the new cross-origin contract
- [x] Confirm explicit payload discriminator:
  - `intent`
- [x] Confirm supported V1 row types for JSON-safe transport
- [x] Confirm allowed row-item properties per supported row type
- [x] Confirm explicit `ui.select.items` schema
- [x] Confirm V1 result shape:
  - `{ reason, values }`
  - where `reason` is `submit | cancel | dismiss`
- [x] Confirm V1 error shape:
  - `{ code, message }`

## Runtime

- [x] Extend `ui.workspace.bridge` with a new child-side helper for `modal.form.open`
- [x] Extend the parent Workspace host handling for `modal.form.open`
- [x] Reuse helper-owned form modal rendering on the parent side
- [x] Keep child-side business logic out of the parent host
- [x] Fail closed on malformed or unsupported payloads
- [x] Keep the first shipped runtime limited to:
  - `intent: "login"`
  - `intent: "reauth"`

## Next Wave Scope

- [x] Confirm the next bridged preset intents with Workspace/HQ before widening runtime:
  - `intent: "account"`
  - `intent: "change-password"`
- [x] Confirm whether `account` remains a preset-only contract or needs additive `extraRows` support in the bridge payload
- [x] Confirm the value/result shape for `change-password`:
  - `current_password`
  - `new_password`
  - `confirm_password`
- [x] Confirm whether parent-owned retry/error reopening is still acceptable for these presets or whether an explicit update/error-round-trip is needed first
- [x] Keep schema-style `generic-form` out of the next runtime slice unless Workspace/HQ produce a stronger shared need and a narrower supported-row subset

## Next Batch Scope

- [x] Confirm `generic-form` should move from future-capable contract-only status into shipped runtime
- [x] Keep `generic-form` under the existing JSON-safe row contract instead of introducing arbitrary DOM transport
- [x] Confirm `generic-form` continues to use child-owned API/business logic with parent-owned rendering only

## Next Batch Runtime

- [x] Extend `modal.form.open` runtime to support:
  - `intent: "generic-form"`
- [x] Keep `generic-form` on the shared `createFormModal(...)` renderer in the parent host
- [x] Preserve `fieldErrors` / `formError` rendering for bridged generic forms
- [x] Preserve `ui.select.items` transport for bridged generic forms

## Next Batch Demo Surface

- [x] Add explicit bridge demo coverage for `generic-form`
- [x] Add cross-origin harness coverage for `generic-form`

## Next Batch Regression

- [x] Add browser coverage for accepted bridged `generic-form` requests in the same-origin fixture harness
- [x] Add browser coverage for accepted bridged `generic-form` requests in the cross-origin harness
- [x] Verify bridged `generic-form` preserves owner-title subtitle, form-level error, field-level error, and no-child-fallback behavior

## Session Update Scope

- [x] Confirm the next contract slice should support parent-modal updates during child-owned async submit flows instead of forcing close-and-reopen retries
- [x] Keep the session update scope narrow to:
  - bridged preset submits
  - busy state
  - busy message
  - field errors
  - form error
- [x] Keep Workspace parent ownership limited to rendering and state updates only
- [x] Keep child ownership of:
  - API submission
  - auth/session rules
  - CSRF sequencing
  - business validation mapping

## Session Update Runtime

- [x] Add a session-style child bridge entrypoint:
  - `modal.form.session.open`
- [x] Add parent-side update handling:
  - `modal.form.update`
- [x] Add parent-side explicit close handling:
  - `modal.form.close`
- [x] Add event streaming back to the child through the same bridge namespace:
  - `phase: "event"`
- [x] Keep serialized preset footer actions working under the session contract
- [x] Preserve child fallback when the session bridge cannot be opened

## Session Update Demo Surface

- [x] Add an explicit busy-submit demo trigger in the cross-origin child harness
- [x] Add a matching parent-side cross-origin harness trigger for the async busy submit path

## Session Update Regression

- [x] Verify a bridged async login submit activates the parent modal busy state
- [x] Verify the parent modal stays open while the child async submit is in flight
- [x] Verify child-side form errors flow back into the already-open parent modal after busy clears
- [x] Verify cancel still closes the parent modal cleanly after an async bridged retry path

## Next Wave Runtime

- [x] Extend `modal.form.open` runtime to support:
  - `intent: "account"`
  - `intent: "change-password"`
- [x] Reuse existing helper-owned preset rendering on the parent side instead of introducing a second preset bridge renderer
- [x] Preserve child-app ownership of:
  - API submission
  - password/account business rules
  - session and CSRF handling
- [x] Fail closed on unsupported preset payloads or row/options that exceed the agreed preset contract

## Next Wave Demo Surface

- [x] Add bridge demo coverage for:
  - account preset
  - change-password preset
- [x] Show returned `{ reason, values }` examples for both new intents

## Next Wave Regression

- [x] Add browser coverage for accepted bridged `account` requests
- [x] Add browser coverage for accepted bridged `change-password` requests
- [x] Verify cancel/result round-trip for both new intents
- [x] Verify malformed/unsupported preset payload rejection for both new intents
- [x] Verify bridged account preset preserves serialized `extraActions` such as `Change Password`
- [x] Verify bridged footer action round-trip returns `reason: "action"` with the selected `actionId`

## Demo Surface

- [x] Add a bridge demo showing cross-origin-style request messages for:
  - login
  - re-auth
  - schema-style admin form
- [x] Show the returned `{ reason, values }` result shape clearly

## Regression

- [x] Add browser coverage for accepted `modal.form.open` requests
- [x] Verify successful result round-trip for a serializable form payload
- [x] Verify cancel/close result round-trip
- [x] Verify malformed payload rejection
- [x] Verify unsupported row-type rejection

## Cross-Project Follow-Through

- [x] Review with Workspace team before runtime implementation
- [x] Announce the final agreed contract in `C:\wamp64\www\pbb\chat_log.md` once aligned
