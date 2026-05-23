# PBB Workspace App Registry V1 Spec

## Purpose

Define the V1 contract for how `PBB Workspace` stores and normalizes the list of installed or available PBB applications.

This spec is intentionally explicit.

V1 should not rely on filesystem guessing or implicit app discovery.

This registry spec should be read together with the HQ baseline docs:

- `c:\wamp64\www\pbb\hub.ph\docs\pbb-laravel-application-baseline.md`
- `c:\wamp64\www\pbb\hub.ph\docs\pbb-api-documentation-baseline.md`

Those baselines define the minimum application and API discipline expected from new PBB browser apps before Workspace-specific integration contracts are layered on top.

## Design Rule

Workspace app discovery is registry-driven.

That means:

- Workspace owns a server-side app registry
- each app must be explicitly registered
- frontend launcher state is derived from the normalized registry

## V1 Storage Direction

Workspace should keep an explicit registry record per app.

Persistence can be:

- database table
- JSON-backed config storage

Database storage is recommended if Workspace already has setup/admin UI.

## Registry Record Shape

Each app record should contain:

| Field | Type | Required | Description |
|---|---|---|---|
| `app_code` | `string` | yes | Stable unique identifier such as `hq`, `relay`, `maestro`, `website`. |
| `name` | `string` | yes | Display name shown in launcher surfaces. |
| `base_url` | `string` | yes | Base URL of the app. |
| `launch_path` | `string` | no | Path appended to `base_url` when launching. Default `/`. |
| `description` | `string` | no | Short launcher description. |
| `icon` | `string` | no | Stable app icon identifier used by the Workspace shell. |
| `category` | `string` | no | Launcher grouping/category label. |
| `embeddable` | `boolean` | no | Whether the app is expected to run inside `ui.iframe.host`. Default `true`. |
| `health_url` | `string` | no | Optional health/status endpoint used by Workspace. |
| `access_check_url` | `string` | yes | URL used by Workspace backend to check whether the current user can access the app. |
| `navbar_order` | `number` | no | Optional stable ordering hint for the navbar `Apps` menu. |
| `preferred_window_width` | `number` | no | Suggested window width for launch. |
| `preferred_window_height` | `number` | no | Suggested window height for launch. |
| `is_active` | `boolean` | yes | Whether the app should participate in launcher evaluation. |

## Normalized App Shape

Workspace frontend should not receive raw registry rows.

Workspace backend should normalize each accessible app to a launcher shape like:

```json
{
  "appCode": "hq",
  "name": "PBB HQ",
  "baseUrl": "https://hq.pbb.ph",
  "launchUrl": "https://hq.pbb.ph/",
  "description": "Registry, topology, sessions, and admin flows.",
  "icon": "data.registry",
  "category": "operations",
  "embeddable": true,
  "navbarOrder": 10,
  "healthUrl": "https://hq.pbb.ph/up",
  "preferredWindow": {
    "width": 1280,
    "height": 820
  }
}
```

The same normalized app payload should drive both:

- launcher/catalog surfaces
- navbar `Apps` menu surfaces

Icon implementation note:

- Workspace may map `icon` to helper icon ids, app-provided icon identifiers, or another shell-owned icon registry
- the registry contract should not require the new team to couple the field to one specific helper implementation

## Registry Validation Rules

V1 validation should enforce:

- `app_code` unique
- `name` non-empty
- `base_url` valid absolute URL or valid deployment-relative URL depending on Workspace hosting model
- `access_check_url` non-empty
- inactive apps excluded from user launcher results

## Launch URL Resolution

Workspace should resolve launch URL as:

- `launch_url = base_url + launch_path`

Rules:

- if `launch_path` omitted, use `/`
- if `launch_path` is already absolute, backend should reject it in V1
- Workspace should normalize duplicated slashes

## Embedding Rules

`embeddable` controls launch behavior.

If `embeddable === true`:

- Workspace may launch the app inside `ui.window` + `ui.iframe.host`

If `embeddable === false`:

- Workspace should use an external launch path or a future fallback UX

V1 does not need multiple launch strategies beyond that flag.

## Health Rules

If `health_url` is configured:

- Workspace may probe it server-side
- health should remain advisory only in V1

Health should not override access decisions.

## App Management UI Expectations

Admin-only `App Management` should support:

- create app entry
- edit app entry
- activate/deactivate app entry

V1 does not require:

- filesystem auto-discovery
- manifest auto-import
- remote metadata synchronization

Recommended admin note:

- registry admission should prefer apps that already meet the HQ Laravel/API baseline expectations or have a documented exception

## Frontend Launcher Input

Workspace frontend should receive a filtered list of normalized apps from its own backend, not from raw registry storage.

That backend response should already exclude:

- inactive apps
- inaccessible apps
- malformed apps rejected during normalization

This filtered list should be reusable across:

- launcher page rendering
- navbar `Apps` menu rendering
- app-switching interactions

## Out Of Scope

V1 does not define:

- app manifest auto-fetching
- health-based launch blocking
- app install/uninstall orchestration
- pinned apps
- multi-tenant registry partitioning

## Acceptance Targets

V1 registry is acceptable if:

- admins can manage app records explicitly
- raw registry records validate deterministically
- launcher receives normalized app payloads
- navbar `Apps` menu receives the same filtered normalized app payloads
- only active apps with valid access-check configuration are considered for user visibility
