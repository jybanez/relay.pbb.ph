# PBB Workspace App Access Check V1 Spec

## Purpose

Define the V1 contract between `PBB Workspace` and each registered PBB app for determining whether a Workspace user should see and launch that app.

This access-check contract should be read together with the HQ baseline docs:

- `c:\wamp64\www\pbb\hub.ph\docs\pbb-laravel-application-baseline.md`
- `c:\wamp64\www\pbb\hub.ph\docs\pbb-api-documentation-baseline.md`

Those baselines matter because Workspace depends on apps having consistent authenticated browser behavior and documented API contracts before cross-project visibility checks become reliable.

## Design Rule

App visibility in Workspace is determined by app-owned access confirmation.

That means:

- Workspace does not hardcode per-app user lists
- Workspace asks each app whether the current user has access
- each app remains the source of truth for downstream authorization

## Architecture Rule

Workspace backend performs access checks.

The browser must not directly call each app's access endpoint.

Reasons:

- avoids CORS issues
- centralizes timeouts and error handling
- keeps trust rules out of the frontend
- lets Workspace return one normalized launcher payload
- lets Workspace populate both the launcher and the navbar `Apps` menu from the same filtered result

## Required Request Contract

Each registered app should expose a Workspace access-check endpoint.

Each app team owns implementing and documenting this endpoint in that app's own repository.

Recommended shape:

- `POST /api/workspace/user-access`

Request body:

```json
{
  "email": "user1@pbb.ph"
}
```

V1 keeps the request narrow on purpose.

The access-check endpoint itself should be part of the app's documented API surface, not an undocumented integration-only seam.

Required request field:

| Field | Type | Required | Description |
|---|---|---|---|
| `email` | `string` | yes | Workspace user email used for app-local user lookup. |

## Required Response Contract

Successful app response:

```json
{
  "app_code": "hq",
  "has_access": true,
  "display_name": "PBB HQ"
}
```

Recommended richer response:

```json
{
  "app_code": "hq",
  "has_access": true,
  "display_name": "PBB HQ",
  "roles": ["operator"],
  "embeddable": true,
  "launch_path": "/"
}
```

Required response fields:

| Field | Type | Required | Description |
|---|---|---|---|
| `app_code` | `string` | yes | Must match the app registry record. |
| `has_access` | `boolean` | yes | Whether the user should see and be allowed to launch the app. |

Optional response fields:

| Field | Type | Description |
|---|---|---|
| `display_name` | `string` | App-provided display label if needed. |
| `roles` | `string[]` | Informational app-local roles. |
| `embeddable` | `boolean` | App-specific override for iframe launch support. |
| `launch_path` | `string` | App-specific launch-path override. |

## Workspace Backend Behavior

For each active registered app:

1. resolve the app's `access_check_url`
2. send the current Workspace user's email
3. interpret the response
4. include the app in launcher results only if `has_access === true`
5. use the same filtered app result for the navbar `Apps` menu

If the app does not respond successfully:

- Workspace should treat access as unavailable
- V1 should fail closed for visibility

That means:

- timeout
- invalid response
- connection failure
- explicit app error

all result in:

- app hidden from that user in Workspace

## Security Expectations

V1 access checks should be trusted server-to-server requests.

At minimum, each app should require one of:

- shared internal token
- server allowlist
- other project-approved service authentication

This spec does not define the final service-auth mechanism, but Workspace should not call access-check endpoints anonymously in production.

## Why Email Is Sufficient In V1

The user proposed email-based matching like:

- `user1@pbb.ph`

That is acceptable for V1 if:

- apps already use email as a stable user identifier
- Workspace user records also store email

Later versions can move to stronger identity claims if needed.

## Failure Handling

Workspace should not expose partial raw app failures to normal users.

Normal user behavior:

- inaccessible or failed apps simply do not appear

Admin behavior may include diagnostics in setup surfaces, for example:

- app access check timeout
- invalid response shape
- app code mismatch

## Normalized Launcher Result

After successful access filtering, Workspace frontend should receive a final launcher payload such as:

```json
[
  {
    "appCode": "hq",
    "name": "PBB HQ",
    "launchUrl": "https://hq.pbb.ph/",
    "embeddable": true
  },
  {
    "appCode": "relay",
    "name": "PBB Relay",
    "launchUrl": "https://relay.pbb.ph/",
    "embeddable": true
  }
]
```

If `user1@pbb.ph` is not recognized by Maestro, then Maestro should simply not appear.

That filtered payload should be the shared source for:

- launcher/catalog rendering
- navbar `Apps` menu rendering

## Out Of Scope

This V1 spec does not define:

- shared SSO
- token handoff for app-local session creation
- role synchronization between Workspace and apps
- automatic user provisioning into apps
- browser-direct cross-origin access checks

## Acceptance Targets

The V1 access-check contract is acceptable if:

- Workspace backend can filter apps by user email
- apps remain the source of truth for whether the user has access
- hidden apps do not appear in the launcher
- hidden apps do not appear in the navbar `Apps` menu
- failed checks default to hidden rather than overexposed visibility
- the access-check endpoint is documented in the app's API docs baseline
