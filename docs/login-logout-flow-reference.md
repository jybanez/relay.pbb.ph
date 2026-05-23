# Login / Logout Flow Reference

## Purpose

This document describes how login and logout currently work in `PBB - HQ` so the same browser-side session flow can be implemented consistently in other PBB projects.

This is specifically for:

- browser-based users
- Laravel session authentication
- frontend applications that use the shared request helper pattern

It does not describe:

- hub bearer tokens
- machine-to-machine API authentication

## Current Model

This project uses:

- Laravel session authentication for users
- CSRF protection for non-`GET` browser requests
- a frontend bootstrap object for initial auth/session/security state

So the browser-side user model is:

1. user logs in with email and password
2. Laravel authenticates the user
3. Laravel regenerates the session
4. frontend receives:
   - current account
   - fresh CSRF token
5. frontend updates its runtime auth state

Logout is the inverse:

1. frontend sends logout request
2. Laravel logs the user out
3. Laravel invalidates the session
4. Laravel regenerates the CSRF token
5. frontend clears local browser cache/state and returns to `/`

## Initial Bootstrap

On initial page load, the application receives user/session startup state through:

- `window.__PBB_BOOTSTRAP__`

Current shape:

```js
window.__PBB_BOOTSTRAP__ = {
  app: {
    name: "PBB - HQ",
    page: "home"
  },
  auth: {
    authenticated: true,
    account: {
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
    country: "PH",
    excludedPoiClasses: []
  }
};
```

This is emitted from:

- [app.blade.php](/c:/wamp64/www/pbb/hub.ph/resources/views/app.blade.php)

The frontend uses this bootstrap to initialize:

- app identity
- current authenticated account
- current CSRF token
- startup settings

## Backend Endpoints

### Login

- `POST /api/login`

Request:

```json
{
  "email": "user@example.com",
  "password": "secret"
}
```

Success response:

```json
{
  "status": true,
  "data": {
    "account": {
      "id": 1,
      "name": "Jane Doe",
      "email": "jane@example.com",
      "role": "admin"
    },
    "csrf_token": "..."
  },
  "meta": null,
  "error": null
}
```

Behavior:

- validates credentials
- authenticates with `Auth::attempt(...)`
- regenerates the session
- returns account + fresh CSRF token

Implementation:

- [AuthController.php](/c:/wamp64/www/pbb/hub.ph/app/Http/Controllers/Api/AuthController.php)

### Current User

- `GET /api/user`

Returns:

- current account
- current session CSRF token

Used to confirm user state if needed.

### Logout

- `POST /api/logout`

Success response:

```json
{
  "status": true,
  "data": {
    "csrf_token": "..."
  },
  "meta": null,
  "error": null
}
```

Behavior:

- logs out the current user
- invalidates the session
- regenerates the CSRF token
- returns the new CSRF token

Implementation:

- [AuthController.php](/c:/wamp64/www/pbb/hub.ph/app/Http/Controllers/Api/AuthController.php)

## Frontend Runtime State

The app keeps user/session state in:

- `state.account`
- current mutable `csrfToken`

The initial values come from:

- `window.__PBB_BOOTSTRAP__`

Relevant implementation:

- [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)

Important helpers:

- `hydrateBootstrapState()`
- `setCsrfToken(...)`
- `applySessionPayload(...)`

## Login Flow

### 1. User opens login modal

The UI uses the helper action modal:

- `openLoginModal()`

### 2. User enters email and password

Validation rule:

- both email and password are required

### 3. Frontend submits login

Frontend request:

- `POST /api/login`

The shared request helper includes:

- `credentials: "same-origin"`
- `X-Requested-With: XMLHttpRequest`
- `X-CSRF-TOKEN` for non-`GET` requests

### 4. On success

Frontend calls:

- `applySessionPayload(res.data)`

This updates:

- `state.account`
- current CSRF token
- `window.__PBB_BOOTSTRAP__.auth`
- `window.__PBB_BOOTSTRAP__.security.csrfToken`

Then the app:

- rerenders the header
- rerenders the current page
- updates command palette commands

### 5. On failure

- modal stays open
- error message is shown
- no redirect occurs

## Logout Flow

### 1. User triggers logout

Usually from:

- account menu
- command palette

### 2. Frontend submits logout

Request:

- `POST /api/logout`

### 3. On success

Frontend:

- updates CSRF token from response
- clears local client cache
- redirects to `/`

Current implementation:

- `logout()` in [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)

## CSRF Handling

This project treats CSRF as session-coupled.

### Initial source

The initial frontend source is:

- bootstrap object `security.csrfToken`

The app also keeps the document meta tag in sync:

```html
<meta name="csrf-token" content="...">
```

### Runtime rule

Whenever the backend returns a new session CSRF token:

- frontend must call `setCsrfToken(...)`

That updates:

- in-memory runtime token
- meta tag
- bootstrap object

### Why this matters

If the session changes but the frontend keeps using an old CSRF token, protected requests may start failing with CSRF/session-related errors.

## Session Expiry Re-Login

This project now includes session-expiry handling for user-side API requests.

When the shared request helper sees:

- `401`
- `419`

for an authenticated user request, it opens a `Session Expired` modal.

Behavior:

- message explains that the session expired
- user enters password
- `Cancel` reloads the page
- `Login` posts to `/api/login`
- on success:
  - session is renewed
  - CSRF is refreshed
  - modal closes
- on failure:
  - error is shown
  - modal remains open

Relevant implementation:

- `handleExpiredSession()` in [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)

Related proposal:

- [pbb-user-session-handling-proposal.md](/c:/wamp64/www/pbb/hub.ph/docs/pbb-user-session-handling-proposal.md)

## Shared Request Helper Behavior

The project uses a shared `api(...)` helper in:

- [app.js](/c:/wamp64/www/pbb/hub.ph/resources/js/app.js)

Important behaviors:

- sends same-origin browser credentials
- sends CSRF token for non-`GET`
- parses JSON responses
- shows error toasts unless silenced
- detects expired session for authenticated users
- opens re-login modal when appropriate

This is an important part of keeping login/logout/session behavior consistent across the app.

## Recommended Standard For Other Teams

If another PBB project wants the same behavior, it should implement:

1. session-based login endpoint
2. logout endpoint that invalidates the session and regenerates CSRF
3. bootstrap object with:
   - app info
   - current account
   - CSRF token
   - startup settings
4. shared request helper
5. mutable CSRF token handling
6. session-expiry modal re-login flow

## Summary

Current PBB user-side browser auth behavior in this project is:

- bootstrap initial auth/session/security state
- login returns account + refreshed CSRF
- logout invalidates session and refreshes CSRF
- frontend keeps CSRF mutable and synchronized
- expired session opens a re-login modal instead of abruptly redirecting

This is the reference flow to share with teams implementing the same user-side session behavior in other PBB projects.
