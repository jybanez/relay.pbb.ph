# ui.fieldset Validation Support Response Memo

## Summary

`PBB Workspace` raised a real helper gap after adopting `ui.fieldset` for the grouped `Add App` admin form.

The current helper contract is strong enough for grouped structure and value retrieval, but it is missing practical validation/error helpers that page-sized forms need once they become real save flows.

This memo agrees with the direction of the Workspace proposal and recommends a narrow parity layer with `ui.form.modal`, without turning `ui.fieldset` into a second form engine.

## Workspace Proposal

Workspace proposal reference:

- `c:\wamp64\www\pbb\workspace\docs\ui-fieldset-validation-proposal.md`

Requested direction:

- `setErrors(fieldErrors)`
- `clearErrors()`
- `setFormError(message)`
- `clearFormError()`
- `applyApiErrors(response)`

## Assessment

The proposal is valid.

Reasons:

- `ui.fieldset` is the correct helper for long grouped admin/page forms
- once those forms are backed by real save endpoints, field-level validation is no longer optional
- top-level coarse error banners are not enough when server validation targets named fields
- the proposed API is narrow and already familiar because it mirrors practical `ui.form.modal` error helpers

This is a helper usability gap, not only a Workspace-specific preference.

## Recommended Direction

Add a narrow validation/error surface to `ui.fieldset`.

Recommended V1 methods:

- `setErrors(fieldErrors)`
- `clearErrors()`
- `setFormError(message)`
- `clearFormError()`
- `applyApiErrors(response)`
- `getErrors()`
- `getFormError()`

`getErrors()` and `getFormError()` are recommended additions because once the helper owns error state, read access is useful for debugging, tests, and app-level orchestration.

## Required Behavior

### Field errors

- render beside the matching field
- set `aria-invalid="true"` on invalid controls
- connect visible error text through `aria-describedby` where practical
- support server-style `errors` objects where values may be arrays or strings

### Form-level error

- render one fieldset-level error strip for non-field or general failures
- keep it visually distinct from field-level error text

### Clearing errors

- remove visual error state
- remove `aria-invalid`
- remove any helper-owned described-by references added for errors

### Name matching

Field matching should stay practical and predictable.

Recommended rule:

- exact field name match first
- support dotted or nested server keys only when they match the actual field name directly

Do not invent aggressive aliasing or schema transforms inside `ui.fieldset` V1.

If an app needs remapping, app code can normalize server errors before calling `setErrors(...)`.

## Scope Boundary

`ui.fieldset` must still not become:

- a submit engine
- a validator
- a schema form framework
- a modal-form replacement

It should only gain:

- error state rendering
- error state clearing
- API-response-to-error mapping convenience
- accessibility state parity for invalid fields

That is the right boundary.

## Relationship To `ui.form.modal`

This should be treated as practical parity, not identity.

`ui.form.modal` still owns:

- modal shell lifecycle
- submit/busy lifecycle
- close behavior
- helper-owned submit orchestration

`ui.fieldset` should only own:

- grouped structure
- values
- grouped validation presentation

The shared part is the error mapping ergonomics, not the full form runtime.

## Recommendation

Proceed with a narrow validation parity implementation for `ui.fieldset`.

Suggested implementation order:

1. add field-level error rendering and clearing
2. add fieldset-level form error rendering and clearing
3. add `applyApiErrors(response)` convenience over the same response shape used by `ui.form.modal`
4. add browser regression coverage for:
   - field error rendering
   - form error rendering
   - aria-invalid state
   - error clearing
   - mixed grouped rows with validation attached to named fields

## Conclusion

Workspace's proposal identifies a real helper gap.

The correct response is:

- yes to narrow validation support on `ui.fieldset`
- no to broadening it into a second form engine

This has now moved forward as a shared helper improvement because grouped page/admin forms are now a real cross-project pattern.

## Implementation Status

The narrow validation parity described above is now implemented in the helper repo.

Current shipped `createFieldset(...)` validation methods:

- `getErrors()`
- `getFormError()`
- `setErrors(fieldErrors)`
- `clearErrors()`
- `setFormError(message)`
- `clearFormError()`
- `applyApiErrors(response)`

Current shipped behavior:

- field-level error text renders inline under the matching field
- one fieldset-level form error renders above the row grid
- invalid controls receive `aria-invalid="true"`
- helper-managed `aria-describedby` references are attached for visible field errors
- dotted API keys such as `base_url.origin` resolve back to the base field where practical
- grouped two-column rows keep alignment stable even when only one side is showing an error

Related references:

- `js/ui/ui.fieldset.js`
- `css/ui/ui.fieldset.css`
- `demos/demo.fieldset.html`
- `tests/fieldset.regression.html`
- `tests/fieldset.regression.mjs`
