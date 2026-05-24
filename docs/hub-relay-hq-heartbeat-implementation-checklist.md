# Relay HQ Heartbeat Implementation Checklist

This checklist tracks the cross-project implementation for Relay's HQ heartbeat and public `hub.json` hydration.

## Contract And Coordination

- [x] Draft Relay proposal for HQ heartbeat and public hub snapshot.
- [x] Incorporate HQ feedback on endpoint, auth, identity, wrapper shape, snapshot metadata, interval, and V1 scope.
- [ ] HQ confirms any remaining validation/error-code details.
- [ ] Kit confirms post-Data Prep handling for the new Relay heartbeat runtime service.

## Hub HQ Work

- [ ] Add `POST /api/hubs/heartbeat`.
- [ ] Authenticate using the hub-owned bearer token.
- [ ] Bind token to the authoritative HQ hub record.
- [ ] Accept `hub_id` and `relay_hub_id` as cross-check fields.
- [ ] Reject or warn on token/hub identity mismatch.
- [ ] Record Relay liveness and basic health from heartbeat payloads.
- [ ] Return standard `{ status, data, meta, error }` wrapper.
- [ ] Return `data.hub` as a heartbeat-specific public snapshot.
- [ ] Exclude token/private/operator-only fields from `data.hub`.
- [ ] Return `data.snapshot_version`.
- [ ] Optionally return `data.snapshot_hash`.
- [ ] Keep V1 limited to monitoring and snapshot hydration, without operational directives.
- [ ] Treat about three missed 60-second heartbeats as stale.
- [ ] Add HQ-side tests for auth, identity mismatch, public snapshot filtering, wrapper shape, and stale detection.

## Relay Work

- [x] Install-time `public/hub.json` snapshot writer exists.
- [ ] Add Relay HQ heartbeat configuration keys.
- [ ] Add `php artisan relay:hq-heartbeat`.
- [ ] Add Relay HQ heartbeat HTTP client.
- [ ] Send `POST /api/hubs/heartbeat` with bearer auth.
- [ ] Include `relay_hub_id`, optional numeric `hub_id`, Relay version/build, app URL, current snapshot metadata, and health summary.
- [ ] Report queue-worker status from the heartbeat payload so HQ can distinguish heartbeat-service health from delivery-worker health.
- [ ] Parse HQ standard response wrapper.
- [ ] Validate `data.hub` before writing public snapshot.
- [ ] Strip private/token fields defensively at every level.
- [ ] Write `public/hub.json` atomically.
- [ ] Add `hydrated_at`, `hydrated_from`, `snapshot_version`, `snapshot_hash`, and optional `hq_snapshot_hash`.
- [ ] Keep the last valid `hub.json` if HQ is unavailable, unauthorized, malformed, or missing `data.hub`.
- [ ] Log heartbeat failures somewhere inspectable.
- [ ] Declare `pbb-relay-hq-heartbeat` in `release.json` `services` and `runtime_services`.
- [ ] Ensure installer-generated manifest/report includes the new runtime service.
- [ ] Add tests for request shape, auth header, wrapper parsing, sanitization, atomic snapshot writing, unchanged snapshot behavior, and failure fallback.
- [ ] Rebuild and publish the Relay bundle for Kit after implementation.

## Kit Setup Work

- [ ] Read the new `pbb-relay-hq-heartbeat` service from Relay `runtime_services`.
- [ ] Register it as a persistent background process.
- [ ] Do not require it during pre-Data Prep smoke checks.
- [ ] Start it after Relay `.env`, database setup, admin provisioning, and Data Prep Apply Settings are complete.
- [ ] Restart it after Relay HQ token/hub settings change.
- [ ] Capture stdout/stderr using the declared log paths.
- [ ] Keep it running after machine reboot.
- [ ] Include it in post-apply service verification.
- [ ] Surface failure logs/report entries if it exits after startup.

## Verification

- [ ] Local Relay tests pass.
- [ ] Relay installer package build passes.
- [ ] Bundle checksum is copied into Kit's bundled package manifest.
- [ ] Kit service-plan report shows both Relay runtime services.
- [ ] Installed Relay has initial `public/hub.json`.
- [ ] Installed Relay starts `pbb-relay-worker`.
- [ ] Installed Relay starts `pbb-relay-hq-heartbeat` after Data Prep.
- [ ] HQ receives fresh heartbeat within 60 seconds after service start.
- [ ] HQ returns public snapshot and Relay hydrates `public/hub.json`.
- [ ] `public/hub.json` remains valid after HQ temporary failure.
- [ ] No token/private fields appear in `public/hub.json`.
