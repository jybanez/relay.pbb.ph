# Relay HQ Heartbeat And Public Hub Snapshot Proposal

## Purpose

Relay needs a runtime heartbeat to Hub HQ so HQ can monitor each installed Relay node and return the current authoritative public hub details. Relay will use the returned public hub payload to keep `public/hub.json` fresh.

This builds on the install-time `hub.json` snapshot. Kit can seed the first snapshot during install, but Hub HQ should become the long-running authority after the node is online and Data Prep has provisioned HQ connectivity.

## Goals

- Let Hub HQ monitor Relay node liveness and basic health.
- Let Relay refresh its public hub identity snapshot from HQ without rerunning Kit.
- Keep `/hub.json` publicly accessible and safe for clients/operators to inspect.
- Include freshness metadata so stale hub snapshots are obvious.
- Avoid exposing tokens, private registry records, or install secrets in public output.
- Keep Relay functional with the last good snapshot if HQ is temporarily unavailable.
- Keep install/service ownership aligned with Kit Setup's `runtime_services` contract.

## Non-Goals

- Replacing Maestro worker telemetry.
- Making `/hub.json` an authenticated endpoint.
- Letting HQ remotely execute commands on Relay.
- Returning operational directives from HQ in V1.
- Publishing secret-bearing HQ registry fields.
- Using queue-worker process count as the HQ node heartbeat count.

## Proposed Flow

1. Kit installs Relay and passes initial resolved hub details.
2. Relay writes the initial sanitized snapshot to `public/hub.json`.
3. Kit completes Data Prep and writes HQ token/hub settings.
4. Kit starts or restarts the dedicated Relay HQ heartbeat service.
5. Relay periodically sends an authenticated heartbeat to Hub HQ.
6. Hub HQ records liveness/health and returns the current public hub payload.
7. Relay validates and sanitizes the returned payload.
8. Relay atomically rewrites `public/hub.json` only when the public snapshot changes or freshness metadata needs updating.
9. Relay records heartbeat success/failure in local diagnostics.

## Runtime Service

Relay should declare a dedicated Kit-managed runtime service for the install-level HQ heartbeat:

```json
{
  "id": "pbb-relay-hq-heartbeat",
  "name": "PBB Relay HQ Heartbeat",
  "type": "background_process",
  "required": true,
  "required_for_smoke": false,
  "manager": "kit",
  "working_directory": "{app.install_path}",
  "command": "{runtime.php_binary}",
  "args": ["artisan", "relay:hq-heartbeat"],
  "health_check": {
    "type": "process",
    "timeout_seconds": 3
  },
  "logs": {
    "stdout": "storage/logs/pbb-relay-hq-heartbeat.out.log",
    "stderr": "storage/logs/pbb-relay-hq-heartbeat.err.log"
  },
  "notes": "Kit starts this after Relay Data Prep applies HQ hub/token settings. It sends install-level HQ heartbeats and hydrates public/hub.json."
}
```

Rationale:

- `pbb-relay-worker` may be scaled to multiple queue-worker processes.
- HQ heartbeat is node/install-level, not per queue worker.
- A dedicated heartbeat service avoids duplicate HQ heartbeats when queue workers are scaled.
- `required_for_smoke` remains `false` because the service depends on HQ token/hub settings provisioned after installer smoke.

## Proposed Relay Request

Endpoint:

```text
POST /api/hubs/heartbeat
```

Auth:

- Use the hub-owned HQ token already provisioned into Relay.
- Send it as bearer auth.
- The bearer token should identify the authoritative HQ hub record.
- Request `hub_id` and `relay_hub_id` are cross-check fields, not the primary authentication mechanism.

Header example:

```text
Authorization: Bearer <RELAY_HQ_API_TOKEN>
```

Payload:

```json
{
  "schema_version": 1,
  "app": "pbb-relay",
  "version": "1.1.0",
  "build_id": "pbb-relay-m1-1.1.0-20260525.015238",
  "relay_hub_id": "072217029",
  "hub_id": 12,
  "app_url": "https://relay.pbb.ph",
  "heartbeat_at": "2026-05-25T02:15:00+08:00",
  "services": {
    "queue_worker": {
      "service_id": "pbb-relay-worker",
      "status": "running",
      "last_maestro_heartbeat_at": "2026-05-25T02:14:45+08:00"
    },
    "hq_heartbeat": {
      "service_id": "pbb-relay-hq-heartbeat",
      "status": "running"
    }
  },
  "health": {
    "status": "healthy",
    "queued_deliveries": 0,
    "failed_deliveries": 0,
    "dead_deliveries": 0,
    "queued_handler_dispatches": 0,
    "failed_handler_dispatches": 0
  },
  "current_snapshot": {
    "hash": "sha256-of-current-public-hub-json-without-hydrated-at",
    "hydrated_at": "2026-05-25T02:10:00+08:00",
    "snapshot_version": "optional-hq-version"
  }
}
```

Identity rules:

- `relay_hub_id` remains the canonical Relay-facing hub identity.
- Numeric `hub_id` remains the HQ record reference.
- HQ should reject requests when the bearer token does not match the authoritative hub record.
- HQ may reject or warn when `hub_id` / `relay_hub_id` cross-checks do not match the token-bound hub.

## Proposed Hub HQ Response

HQ should use the standard response wrapper:

```json
{
  "status": true,
  "data": {
    "hub": {
      "base_url": "https://hub.pbb.ph",
      "hub_id": 12,
      "relay_hub_id": "072217029",
      "name": "Guadalupe, CEBU CITY, CEBU",
      "code": null,
      "deployment": "barangay",
      "domain": "guadalupe-cebu-cebu.pbb.ph",
      "status": "active",
      "country_code": "PH",
      "reg_code": "07",
      "prov_code": "0722",
      "citymun_code": "072217",
      "brgy_code": "072217029",
      "uplinks": [
        {
          "id": 29,
          "uplink_hub_id": 11,
          "uplink_type": "hierarchy",
          "uplink_domain": "cebu-cebu.pbb.ph",
          "priority": 1,
          "is_primary": true,
          "hub": {
            "id": 11,
            "name": "CEBU CITY, CEBU",
            "code": "cebu-cebu",
            "deployment": "city",
            "domain": "cebu-cebu.pbb.ph",
            "status": "active"
          }
        }
      ],
      "sources": []
    },
    "snapshot_version": "hub-12:2026-05-25T02:15:00+08:00",
    "snapshot_hash": "optional-hq-public-snapshot-hash"
  },
  "meta": {
    "heartbeat_interval_seconds": 60,
    "stale_after_seconds": 180
  },
  "error": null
}
```

Response rules:

- `data.hub` must be a heartbeat-specific public hub snapshot.
- HQ must not return token/private fields in `data.hub`.
- HQ should not reuse a broader hub registry payload unless token/private metadata is removed.
- `data.snapshot_version` should change whenever the public snapshot meaningfully changes.
- `data.snapshot_hash` is optional; Relay will still compute its own sanitized public hash.

## Public `hub.json` Shape

Relay writes the sanitized HQ response to:

```text
public/hub.json
```

Public URL:

```text
/hub.json
```

Expected output:

```json
{
  "base_url": "https://hub.pbb.ph",
  "hub_id": 12,
  "relay_hub_id": "072217029",
  "name": "Guadalupe, CEBU CITY, CEBU",
  "code": null,
  "deployment": "barangay",
  "domain": "guadalupe-cebu-cebu.pbb.ph",
  "status": "active",
  "country_code": "PH",
  "reg_code": "07",
  "prov_code": "0722",
  "citymun_code": "072217",
  "brgy_code": "072217029",
  "hydrated_at": "2026-05-25T02:15:00+08:00",
  "hydrated_from": "hq_heartbeat",
  "snapshot_version": "hub-12:2026-05-25T02:15:00+08:00",
  "snapshot_hash": "sha256-of-sanitized-public-snapshot-without-hydrated-at",
  "hq_snapshot_hash": "optional-hq-public-snapshot-hash",
  "uplinks": [],
  "sources": []
}
```

Install-time snapshots should use:

```json
{
  "hydrated_from": "install"
}
```

Heartbeat snapshots should use:

```json
{
  "hydrated_from": "hq_heartbeat"
}
```

## Relay Sanitization Rules

Relay must strip these fields at every level before writing `/hub.json`:

- `token`
- `api_token`
- `secret`
- `shared_secret`
- `password`
- `raw_hub`
- `raw_payload_json`
- any private operator or installer-only metadata

Relay should preserve these public fields:

- `base_url`
- `hub_id`
- `relay_hub_id`
- `name`
- `code`
- `deployment`
- `domain`
- `status`
- `country_code`
- `reg_code`
- `prov_code`
- `citymun_code`
- `brgy_code`
- `uplinks`
- `sources`

## Runtime Behavior

Recommended Relay environment settings:

```text
RELAY_HQ_HEARTBEAT_ENABLED=true
RELAY_HQ_HEARTBEAT_INTERVAL_SECONDS=60
RELAY_HQ_HEARTBEAT_PATH=/api/hubs/heartbeat
```

Recommended behavior:

- Run HQ heartbeat from `php artisan relay:hq-heartbeat`.
- Send a best-effort heartbeat every 60 seconds.
- Use short HTTP connect/response timeouts so HQ connectivity problems do not stall the process.
- If HQ returns the same `snapshot_version` or same sanitized hash, Relay may only update `hydrated_at`.
- If HQ is unreachable, unauthorized, malformed, or returns no `data.hub`, keep the last good `hub.json`.
- Record heartbeat failures in local diagnostics, but do not blank the public snapshot.
- If HQ returns a retired/inactive status, write the status to `hub.json` and let Relay policy decide whether to restrict routing.
- Keep V1 limited to monitoring and hub snapshot hydration; ignore/forbid operational directives in the HQ response.

## Kit Setup Expectations

Kit should:

- install the `pbb-relay-hq-heartbeat` runtime service from Relay `runtime_services`
- start it only after `.env`, database setup, admin provisioning, and Relay Data Prep Apply Settings are complete
- restart it after Relay HQ token/hub settings change
- capture stdout/stderr to the declared service logs
- keep it running after machine reboot
- include the service in post-apply service verification, not pre-Data Prep smoke

## Failure Semantics

Relay should keep the heartbeat process alive when:

- HQ is temporarily unreachable
- HQ returns 401/403 because token provisioning is incomplete or stale
- HQ returns 5xx
- HQ returns malformed JSON
- HQ returns a wrapper without `data.hub`

The public snapshot should remain the last valid snapshot. Operators should be able to inspect the heartbeat log and local diagnostics for the latest failure reason.

## Resolved HQ Contract Points

- Endpoint: `POST /api/hubs/heartbeat`
- Auth: bearer token using the hub-owned HQ token
- Canonical Relay identity: `relay_hub_id`
- HQ record reference: numeric `hub_id`
- Response wrapper: `{ status, data, meta, error }`
- Public snapshot location in response: `data.hub`
- Snapshot metadata: `data.snapshot_version`, optional `data.snapshot_hash`
- V1 scope: monitoring plus public hub snapshot hydration only
- Interval: 60 seconds
- Stale threshold: roughly 3 missed heartbeats, about 180 seconds

## Remaining Open Questions

1. Should HQ define a formal validation error code for token/hub cross-check mismatches?
2. Should HQ include the accepted next heartbeat interval in every response or only when it differs from 60 seconds?
3. Should Relay expose a local admin diagnostic endpoint for last HQ heartbeat status, or is log/report visibility enough for V1?

## Suggested First Implementation

1. Hub HQ adds `POST /api/hubs/heartbeat` and returns the heartbeat-specific public hub snapshot.
2. Relay adds `php artisan relay:hq-heartbeat`.
3. Relay declares `pbb-relay-hq-heartbeat` in `release.json` and installer-generated manifests.
4. Relay writes `/hub.json` atomically with `hydrated_at`, `hydrated_from`, `snapshot_version`, `snapshot_hash`, and optional `hq_snapshot_hash`.
5. Relay tests cover request shape, bearer auth, token stripping, stale HQ fallback, malformed response handling, and unchanged snapshot handling.
6. Kit starts/restarts and verifies `pbb-relay-hq-heartbeat` after Relay Data Prep Apply Settings.
