# Maestro First-Pass Test Checklist

Use this checklist for the first manual verification pass against `https://maestro.pbb.ph`.

## Access

- Open `https://maestro.pbb.ph/`
- Confirm the public shell loads cleanly
- Open `https://maestro.pbb.ph/up`
- Confirm the branded Maestro health page loads and shows a healthy state
- Confirm unauthenticated protected API calls return `401`

## Login

- Open the login modal
- Submit valid credentials
- Confirm the modal closes correctly on success
- Confirm the authenticated operator shell loads after login
- Submit invalid credentials
- Confirm the modal stays open and shows an error
- Press `Enter` in the password field
- Confirm native submit behaves the same as clicking `Login`

## Session

- Confirm `GET /api/user` reflects the authenticated account after login
- Logout from the authenticated shell
- Confirm the session clears and the public shell returns
- Let the session expire if possible
- Trigger an authenticated action
- Confirm the re-login modal appears
- Confirm re-login restores the session without breaking the current page flow

## Shell

- Confirm the top navigation renders correctly
- Confirm post-login pages are reachable:
  - `Dashboard`
  - `Workers`
  - `Worker Events`
  - `Applications`
  - `Queues`
- Confirm the theme matches the intended helpers-dark styling
- Confirm helper components render correctly with no obvious raw or unskinned UI

## Workers

- Open `Workers`
- Confirm the grid renders
- Confirm the empty state is clean if there is no data
- Confirm populated rows render at the top correctly if data exists
- Confirm row count, search, and filter behavior works if present
- Confirm timestamps and status badges are readable

## Worker Events

- Open `Worker Events`
- Confirm the grid renders
- Confirm event ordering looks correct
- Confirm the empty state works
- Confirm event detail text is readable and not truncated incorrectly

## Applications

- Open `Applications`
- Confirm the grid renders
- Confirm application identity fields are clear
- Confirm the empty state works
- Confirm any application detail or drill-in action works if implemented

## Queues

- Open `Queues`
- Confirm queue metrics surface loads
- Confirm empty-state and zero-state rendering are correct
- Confirm backlog and failure counts display sensibly

## Helpers

- Confirm helper modals close automatically on successful truthy submit
- Confirm dialogs and buttons inherit the site theme correctly
- Confirm no stale helper behavior remains from older vendored copies

## Offline-Readiness Basics

- Confirm main UI assets are local paths, not public CDNs
- Confirm helper assets are served from `/vendor/helpers.pbb.ph/...`
- Confirm `/up` does not depend on internet-hosted fonts or scripts

## API Boundary

- While logged out, confirm protected APIs reject access
- While logged in, confirm authenticated APIs return data
- Confirm CSRF and session behavior is stable across login and logout

## Visual And UX

- Confirm there are no overflowing containers without internal scroll
- Confirm grids use available height properly
- Confirm empty and loading states are clean
- Confirm there are no broken icons, unreadable text, or raw fallback styling

## Suggested First-Pass Outcome

At the end of the first pass, capture:

- what worked cleanly
- what blocked testing
- any auth/session issues
- any helper rendering regressions
- any offline-readiness gaps
- any pages that are not yet ready for broader operator testing
