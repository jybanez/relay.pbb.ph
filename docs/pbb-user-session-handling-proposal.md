# PBB User Session Handling Proposal

## Purpose

Standardize how PBB-related projects handle expiring user sessions for browser-based, session-authenticated users.

This proposal is specifically for:

- user-facing browser sessions
- Laravel session authentication
- projects where user authorization depends on the active session

It does not apply to:

- hub bearer tokens
- machine-to-machine API access
- long-lived service authentication

## Problem

In session-based PBB projects, when the session expires:

- the authenticated user is lost
- user authorization also stops working
- the next protected request may fail unexpectedly
- the user may lose context or unsaved work

Without a standard pattern, projects will handle this inconsistently:

- silent redirects
- abrupt page reloads
- unexplained login failures
- duplicate custom re-login implementations

## Proposed User Experience

When the application detects that the user session has expired or is no longer valid, it should open a modal that informs the user and allows re-authentication without leaving the current page.

### Modal message

Recommended message:

`Your session has expired. To continue, please enter your password again.`

### Modal content

- informational message
- readonly email/username context if desired
- password field

### Modal actions

- `Cancel`
  - reloads the page
- `Login`
  - sends a `POST` request with the credentials
  - if successful:
    - modal closes
    - session is refreshed
    - user continues working
  - if unsuccessful:
    - show an alert or error message
    - modal stays open

## Why This Is A Good Pattern

This approach is strong for PBB projects because:

- it makes the session-expiry state explicit
- it preserves the user’s current working context
- it avoids forcing a full redirect to the login page
- it keeps re-authentication contained to a predictable modal flow

This is especially useful in:

- admin pages
- data-entry pages
- multi-step workflows
- pages with unsaved context

## Recommended Trigger Conditions

The modal should appear when any of these happen:

1. proactive session timeout warning
- the frontend knows the session is about to expire or has expired

2. protected API response indicates expired session
- example:
  - `401 Unauthorized`
  - `419 Page Expired`
  - framework-specific expired-session response

3. explicit backend signal
- a standard response contract could indicate:
  - `session_expired: true`

## Recommended Flow

### 1. Session expires or protected request fails

The application detects that the session is no longer valid.

### 2. Re-login modal opens

The modal explains that the session has expired and asks for the user’s password.

### 3. User chooses:

#### Cancel

- page reloads
- application returns to standard unauthenticated flow

#### Login

- frontend sends login request
- request should include:
  - user identifier if needed
  - password

### 4. Result handling

#### Success

- session is restored
- modal closes
- user continues

#### Failure

- alert or inline error is shown
- modal remains open
- no redirect occurs

## Recommended API Behavior

The re-login action should use a standard session-auth login endpoint.

Example:

- `POST /api/login`

Request body:

```json
{
  "email": "user@example.com",
  "password": "secret"
}
```

Success:

- server refreshes or recreates session
- returns success payload

Failure:

- returns validation/authentication error
- frontend keeps modal open

## Recommended UI Contract

### Modal title

- `Session Expired`

### Body

- explanatory message
- password field

Optional:

- small note that re-authentication is required to continue

### Actions

- `Cancel`
- `Login`

## Interaction Rules

### Cancel

- action should not silently dismiss only
- it should reload the page as requested

Reason:

- if the user does not want to re-authenticate, the application should reset into a clean unauthenticated state

### Login

- modal must not close unless login is successful
- failed login should keep the modal open
- failed login should show a clear error message

### Busy state

This should use the helper modal busy-state pattern:

- while login is in flight:
  - modal shows busy overlay
  - buttons and input are disabled
  - duplicate submissions are prevented

## Accessibility Expectations

- password field must receive focus when modal opens
- error message must be perceivable to assistive tech
- modal must remain keyboard-usable
- login button should clearly indicate busy state while request is in progress

## Security Notes

- password must not be stored in client state longer than necessary
- do not log password values
- if possible, reuse the existing login endpoint and session-hardening rules already used by the app
- failed re-login attempts should follow the same backend protections as normal login

## CSRF Handling Standard

Because these projects use Laravel session authentication, the re-login flow should also standardize how CSRF is refreshed after the session is renewed.

### Expected behavior

When re-login succeeds:

- the session is refreshed or regenerated
- the CSRF context is also refreshed
- the frontend must refresh its active CSRF value before continuing normal protected requests

### Recommended source of truth

PBB projects should use the HTML document meta tag as the standard frontend source of truth:

```html
<meta name="csrf-token" content="...">
```

### Recommended project behavior

After successful re-login:

1. server returns a valid renewed session
2. frontend refreshes the CSRF token source
3. frontend updates any in-memory request helper that caches the CSRF token
4. only then should protected follow-up requests continue

### Why this matters

Without a standard CSRF refresh step:

- the session may already be valid again
- but the frontend may still send an old CSRF token
- resulting in confusing `419 Page Expired` or similar CSRF failures immediately after re-login

### Recommended implementation rule

PBB projects should standardize one of these two approaches:

#### Preferred

- after successful re-login, reload the current page state or refresh the page shell data source that regenerates the meta CSRF token

#### Acceptable

- after successful re-login, explicitly re-read the current CSRF token from the document and update the shared request helper before sending any more protected requests

### PBB standard recommendation

For consistency across PBB projects:

- the CSRF token should be treated as session-coupled
- the document meta tag should be the canonical frontend source
- successful re-login must include a CSRF refresh step before continuing protected interactions

## Suggested Helper-Library Direction

This pattern is likely common across multiple PBB projects.

The modal implementation can be app-specific at first, but long-term it may be worth standardizing:

- session-expiry detection contract
- reusable session-expired modal helper
- consistent error and busy-state handling

Possible future helper concept:

- `uiSessionRelogin(...)`

But this should only happen if multiple projects need the exact same flow.

## Recommended Implementation Boundaries

### App responsibility

- detect session expiry
- know current logged-in user identity if needed
- decide when to open modal
- call login endpoint
- reload page on cancel

### Helper-library responsibility

- modal rendering
- busy-state handling
- consistent action behavior

## Recommended PBB Standard

For session-authenticated PBB projects:

- when the session expires, show a re-login modal
- require password to continue
- `Cancel` reloads the page
- `Login` re-authenticates in place
- failed login keeps the modal open
- successful login closes the modal and restores continuity

## Bootstrap Standard For User-Side Security State

To keep behavior consistent across PBB browser applications, initial user-session state should be exposed through a standard frontend bootstrap object instead of being read from scattered DOM locations.

### Recommended contents

The bootstrap object should include:

- project/app identity
- current authenticated-user state
- current user payload
- CSRF token
- project settings needed at startup

Example:

```html
<script>
window.__PBB_BOOTSTRAP__ = {
  app: {
    name: "PBB - HQ"
  },
  auth: {
    authenticated: true,
    user: {
      id: 1,
      name: "Jane Doe",
      email: "jane@example.com",
      role: "admin"
    }
  },
  security: {
    csrfToken: "..."
  },
  settings: {
    country: "PH"
  }
};
</script>
```

### Why this is recommended

- one consistent frontend startup source
- less DOM scraping across projects
- easier session-expiry and re-login handling
- easier reuse of shared frontend patterns
- easier debugging and maintenance

### Relationship to CSRF handling

The bootstrap object should provide the initial CSRF token for the current page load.

After successful re-login:

- the session is renewed
- the CSRF context is renewed
- the frontend must refresh its active CSRF value again before continuing protected requests

So the bootstrap object is the initial source, but re-login must still include a CSRF refresh step.

### PBB standard recommendation

For user-side browser projects:

- expose initial auth/session state in a shared bootstrap object
- expose the initial CSRF token there
- expose project settings there
- keep this focused on user/browser state only

This proposal does not recommend placing machine-to-machine hub bearer tokens in the frontend bootstrap object.

## Advantages

- consistent session-expiry behavior across projects
- less user confusion
- less lost context
- less duplicated custom handling
- better fit for admin/data-entry systems than a forced redirect

## Open Questions

These should be decided per project or as a PBB-wide standard:

1. Should the modal appear only after expiry, or also shortly before expiry?
2. Should the user identifier be hidden, shown as readonly, or omitted entirely?
3. Should failed attempts be shown as inline errors, alerts, or both?
4. Should there be an automatic retry of the original failed request after re-login?
5. Should unsaved form state be preserved automatically after re-login?

## Recommendation

Adopt this modal-based re-login flow as the standard session-expiry handling pattern for PBB projects that use user session authorization.

It is clearer and less disruptive than redirecting users away from the current page, while still preserving the security model of session-based authentication.
