# Login Form Reference

## Purpose

This document describes the current login form used in `PBB - HQ` so other PBB teams can implement the same browser-side login experience and behavior in their projects.

This is a user-side session-auth login form reference.

It does not describe:

- hub bearer token authentication
- machine-to-machine authentication

## Current Login Form Type

The project uses a modal-based login form.

The login form is not a separate full-page login screen.

It is opened from:

- main navigation `Login`
- command palette `Open Login`
- auth-required empty states

Implementation:

- [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)
  - `openLoginModal()`

## Modal Title

- `Login`

## Form Layout

The form is a simple two-field vertical stack inside the shared helper action modal.

Structure:

1. `Email address`
2. `Password`

Rendered in a standard `.modal-form` layout with `.field` rows.

## Fields

### 1. Email address

- label: `Email address`
- input type: `email`
- placeholder: `name@agency.gov.ph`
- autocomplete: `username`

### 2. Password

- label: `Password`
- input type: `password`
- placeholder: `Enter your password`
- autocomplete: `current-password`

## Actions

The modal uses two actions:

### 1. Cancel

- label: `Cancel`
- variant: `ghost`
- closes the modal

### 2. Login

- label: `Login`
- variant: `primary`
- auto-focused action
- uses helper modal busy-state during submit

Busy label:

- `Signing in...`

## Validation Rules

Before submit:

- email is required
- password is required

If either is empty:

- show error message:
  - `Email and password are required.`
- keep modal open

## Submit Behavior

On submit, the frontend sends:

- `POST /api/login`

Payload:

```json
{
  "email": "user@example.com",
  "password": "secret"
}
```

## Success Behavior

If login succeeds:

- session is established or refreshed
- account payload is returned
- CSRF token is refreshed
- frontend updates session state
- modal closes
- header is rerendered
- current page is rerendered
- command palette is refreshed

## Failure Behavior

If login fails:

- show error message from the backend
- modal stays open
- user can correct the input and retry

## Busy-State Behavior

During the login request:

- modal shows helper busy overlay
- password/email fields are locked
- duplicate clicks are prevented

This uses the helper modal busy-state support, not custom app-side overlays.

## Accessibility / Usability Notes

- email uses `autocomplete="username"`
- password uses `autocomplete="current-password"`
- labels are visible above the inputs
- field order is email first, password second
- login is a modal action, not a raw submit button inside the form body

## Current Visual Pattern

The login form follows the shared modal form styling used throughout the app:

- `.modal-form`
- `.field`
- `.field-label`
- `.ui-input`

This keeps it visually consistent with:

- account form
- setup form
- users add/edit
- hubs add/edit

## Recommended Standard For Other Teams

If another PBB project wants the same login experience, use:

- modal-based login
- title: `Login`
- email field
- password field
- `Cancel` + `Login` actions
- busy-state on submit
- modal stays open on failure
- modal closes only on success

## Related References

- [login-logout-flow-reference.md](/c:/wamp64/www/pbb/hub.ph/docs/login-logout-flow-reference.md)
- [pbb-user-session-handling-proposal.md](/c:/wamp64/www/pbb/hub.ph/docs/pbb-user-session-handling-proposal.md)

## Summary

The current login form in `PBB - HQ` is:

- modal-based
- two-field
- session-authenticated
- helper-modal-driven
- busy-state protected
- closes only on successful login

This is the reference behavior to mirror across other user-side PBB projects.
