# PBB Workspace App Discovery Counter-Proposal

## Summary

This counter-proposal keeps the good part of Workspace's current direction:

- assisted app registration
- backend-only probing
- admin review before save

But it changes the discovery contract to a simpler and safer V1:

1. prefer a static manifest file over a custom API endpoint
2. treat discovery as optional assisted registration, not a Workspace-readiness test by itself
3. do not auto-classify a missing manifest as an embeddable static site
4. let Workspace derive the canonical base URL from the probed target instead of trusting the app to report it

## Why Change The Current Proposal

The current proposal is sound in intent, but still looser than necessary for V1.

Main issues:

- multiple acceptable discovery endpoints create avoidable contract drift
- `base_url` reported by the app is easy to misstate behind subpaths, proxies, or staging aliases
- automatic assumptions based on a missing discovery contract are too risky
- a custom endpoint requires backend implementation in every downstream app even when the metadata is mostly static

For V1, a static manifest is the narrower and more predictable approach.

## Recommended V1 Discovery Contract

Preferred discovery path:

```text
GET /.well-known/pbb.json
```

Reason:

- explicit and easy to recognize
- stable across app types
- product-neutral and cacheable
- does not require app teams to implement a discovery controller just to participate

Workspace should perform discovery on the backend, not in the browser.

## Discovery Model

Discovery is for assisted registration only.

Expected flow:

1. admin enters a target app URL
2. Workspace backend probes `/.well-known/pbb.json`
3. if valid, Workspace normalizes the manifest and prefills the registration form
4. admin reviews and saves
5. if the manifest is missing or malformed, Workspace falls back to manual registration

Important rule:

- discovery must never auto-save an app
- discovery must never silently mark an unknown target as Workspace-ready

## Counter-Proposed Manifest Shape

Recommended minimum manifest:

```json
{
  "contract_version": "1",
  "app_code": "relay",
  "name": "PBB Relay",
  "app_kind": "laravel-browser-app",
  "launch_path": "/",
  "health_url": "/up",
  "access_check_url": "/api/workspace/user-access",
  "embeddable": true,
  "icon": "app-window",
  "category": "Operations",
  "service_auth_type": "bearer",
  "preferred_window": {
    "width": 1280,
    "height": 820
  },
  "workspace_notes": "Relay requires service auth for access checks."
}
```

## Recommended Fields

Required:

- `contract_version`
- `app_code`
- `name`
- `app_kind`
- `launch_path`
- `embeddable`

Strongly recommended:

- `health_url`
- `access_check_url`
- `icon`
- `category`
- `service_auth_type`

Optional:

- `preferred_window.width`
- `preferred_window.height`
- `workspace_notes`
- `service_auth_config_hint`

## Field Semantics

- `contract_version`
  Discovery manifest version. Start with `"1"`.

- `app_code`
  Stable unique code used by Workspace registry and diagnostics.

- `name`
  User-facing launcher/window label.

- `app_kind`
  High-level app type so Workspace can reason about fallback behavior without guessing.

  Recommended initial values:

  - `laravel-browser-app`
  - `static-site`
  - `api-only`
  - `other`

- `launch_path`
  Preferred relative launch path inside the app.

- `health_url`
  Relative or absolute advisory readiness endpoint. Usually `/up`.

- `access_check_url`
  Relative or absolute server-to-server Workspace access-check endpoint.

- `embeddable`
  Whether the app is intended to run inside Workspace iframe windows.

- `icon`
  Stable icon token or app icon identifier used by Workspace for launcher display.

- `category`
  Launcher grouping hint such as `Operations`, `Admin`, or `Public`.

- `service_auth_type`
  High-level machine-to-machine auth hint used for access-check and related Workspace probes.

- `preferred_window`
  Suggested opening dimensions for non-maximized launches.

## URL Rules

Manifest URLs should be relative where practical.

Preferred examples:

```json
{
  "launch_path": "/",
  "health_url": "/up",
  "access_check_url": "/api/workspace/user-access"
}
```

Workspace should resolve relative values against the probed target URL.

Why:

- avoids stale hardcoded origins
- works better across staging, subpaths, and mirrored environments
- lets Workspace derive the canonical base URL itself

## Canonical Base URL Rule

Workspace should derive canonical `base_url` from the probe target, not require the manifest to supply it.

Reason:

- Workspace already knows what it probed
- reported `base_url` can drift behind reverse proxies, aliases, or install paths
- using the actual probe target is safer for V1 normalization

If Workspace wants to store a normalized `base_url` in its own registry, it should derive and save that value during registration.

## Missing Manifest Behavior

This is the most important counter-proposal rule.

If `/.well-known/pbb.json` is missing:

- Workspace must not assume the target is an embeddable static app
- Workspace must not silently register the app
- Workspace should treat the target as unverified for discovery
- admin may still proceed with manual registration

Safe fallback behavior:

- manifest found and valid:
  - assisted prefill
- manifest missing:
  - manual registration only
- manifest malformed:
  - show discovery error, allow manual registration

This keeps Workspace honest and avoids guessing:

- embeddability
- app type
- icon/category
- access-check support
- service auth behavior

## Static Sites

Static sites should still be able to participate.

Example static manifest:

```json
{
  "contract_version": "1",
  "app_code": "website",
  "name": "PBB Website",
  "app_kind": "static-site",
  "launch_path": "/",
  "health_url": null,
  "access_check_url": null,
  "embeddable": true,
  "icon": "app-window",
  "category": "Public",
  "preferred_window": {
    "width": 1280,
    "height": 820
  }
}
```

That is a better model than inferring staticness from a missing manifest.

## Service Auth Guidance

`service_auth_type` should stay advisory in V1.

Workspace may use it to prefill the registration form, but it should not treat the field alone as enough to configure machine-to-machine trust automatically.

Actual service-auth details should still be reviewable and editable by the admin in Workspace setup.

## Relationship To Existing Workspace Contracts

This manifest should complement, not replace:

- Workspace app registry
- per-app access-check contract

Recommended relationship:

- discovery manifest helps prefill registration
- access-check endpoint remains the actual user-visibility trust contract
- registry remains the Workspace-owned saved source of truth after admin review

## Why This Is Better For V1

Benefits:

- simpler for downstream app teams to adopt
- no required discovery controller/backend endpoint
- easier to cache and inspect
- safer fallback behavior
- less ambiguity around canonical origin
- easier for static sites and simpler browser apps to participate

## Recommendation

Adopt the following as the preferred V1 discovery model:

- discovery path: `GET /.well-known/pbb.json`
- relative URLs where practical
- explicit `app_kind`
- Workspace-derived canonical base URL
- manual-registration fallback when manifest is missing or malformed
- no automatic static-site or embeddable classification from absence alone

That gives Workspace a narrow, stable assisted-registration contract without over-guessing downstream app capabilities.
