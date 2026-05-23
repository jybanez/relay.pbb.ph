# Hub Relay HQ Registry Integration Proposal

## Purpose

Define how each `Hub Relay` installation should derive its identity and relay topology from the canonical `PBB HQ` hub registry instead of relying on hand-maintained local peer definitions as the long-term source of truth.

Reference contract:

- `https://hub.pbb.ph/api/hubs`
- `c:\wamp64\www\pbb\hub.ph\public\openapi\hubs.yaml`
- `c:\wamp64\www\pbb\hub.ph\docs\hubs-spec.md`

The HQ hubs API is the authoritative registry for:

- canonical hub identity
- deployment type
- domain/base address
- hub status
- uplink/source topology
- hub token lifecycle metadata

## Implementation Status

Baseline implementation now exists in this repo.

Implemented:

- HQ API client and sync command
- local HQ cache tables for hubs, links, and node settings
- local node identity resolution from HQ-backed data
- outbound peer resolution from HQ cache plus local overrides
- inbound sender lookup against HQ-known hubs plus local credentials

Current command:

- `php artisan relay:hq-sync`

Current boundary:

- `relay_hub_id` is the canonical Relay identity key
- numeric HQ `id` is stored only as `hq_id` reference data
- manual peer config still remains as a compatibility fallback
- scheduled sync and admin sync visibility are not implemented yet

## Current Relay State

Today this repo supports both:

- manual environment/config identity and peer definitions
- HQ-backed cached identity/topology resolution

Manual/bootstrap values still include:

- `RELAY_LOCAL_HUB_ID`
- `RELAY_TARGETS`
- `RELAY_HUBS`

That is enough for early testing, but it creates drift risk because:

- hub IDs can diverge from HQ
- target URLs can become stale
- inbound trusted-hub config can drift from the master registry
- topology changes require manual edits on every relay node

This remains acceptable for bootstrap, local loopback testing, and credential overrides, but it is not the intended long-term source of truth for peer identity/topology.

## Canonical Source Of Truth

`PBB HQ` must be treated as the source of truth for relay installation identity and hub topology.

Relevant HQ hub fields from the current HQ contract:

- `id`
- `relay_hub_id`
- `name`
- `code`
- `deployment`
- `domain`
- `status`
- `last_seen_at`
- `last_response_ms`
- `token.has_token`
- `token.is_active`
- `uplinks[]`
- `sources[]`

## Architectural Rule

Relay should not invent a parallel long-lived registry of hubs.

Instead:

- HQ owns hub identity and topology
- Relay keeps a local runtime projection/cache of HQ hub data
- local env/config only bootstraps HQ access and selects the local HQ hub record

## Recommended Identity Model

Each relay installation should be anchored to one HQ hub record.

Recommended local identity fields:

- `relay_hub_id`
- `hq_id`
- `hq_hub_code`
- `hq_hub_name`
- `deployment`
- `domain`
- `status`

Recommended rule:

- `relay_hub_id` is the canonical stable identity used for relay trust and topology
- `hq_id` remains the HQ record reference key
- `code` remains the human-friendly operator identifier
- `domain` becomes the default relay base URL source for upstream peers

## Recommended Relay Mapping

### 1. Local Relay Identity

Map the local relay installation to one HQ hub record.

Recommended bootstrap env:

- `RELAY_HQ_API_BASE_URL`
- `RELAY_HQ_API_TOKEN`
- `RELAY_HQ_LOCAL_RELAY_HUB_ID`
- `RELAY_HQ_LOCAL_HQ_ID`

Meaning:

- `RELAY_HQ_API_BASE_URL`
  - HQ API base for registry lookups
- `RELAY_HQ_API_TOKEN`
  - bearer token for registry access
- `RELAY_HQ_LOCAL_RELAY_HUB_ID`
  - the canonical Relay-facing hub identity this relay instance represents
- `RELAY_HQ_LOCAL_HQ_ID`
  - optional HQ numeric record reference for this relay instance

### 2. Outbound Targets

Outbound relay targets should be derived from HQ `uplinks`.

Recommended mapping:

- each HQ uplink becomes one relay target candidate
- target hub canonical identity = uplink hub `hub.relay_hub_id`
- target HQ reference = uplink hub `hub.id`
- target display code = uplink hub `code`
- target base URL = derived from uplink hub `domain`

Recommended default relay URL derivation:

- if HQ hub domain is `city.hub.pbb.ph`
- relay base URL defaults to `https://city.hub.pbb.ph`
- relay receive path remains `/api/v1/receive` unless explicitly overridden

If some installations host relay on a different subpath or host than the HQ hub domain, Relay should support an override field later, but the default should come from HQ.

### 3. Inbound Trust

Inbound sender validation should match the remote sender to an HQ hub record.

Recommended rule:

- if sender `source_hub_id` does not match a cached HQ `relay_hub_id`, reject the request
- if sender hub status is not acceptable for relay traffic, reject or soft-fail based on policy
- if sender hub is not part of an allowed topology relationship, reject or flag based on policy

Allowed topology policy should be configurable, but initial recommendation is:

- accept from known HQ hubs
- optionally tighten later to:
  - only configured `sources`
  - or only explicitly allowed peers

### 4. Token/Auth Material

The current HQ API exposes token lifecycle metadata, not raw peer secrets.

So Relay should separate:

- HQ registry identity/topology sync
- relay transport credential material

Recommended rule:

- HQ remains the source of hub identity and topology
- relay transport secrets remain locally provisioned unless HQ later exposes a dedicated secure relay-credential distribution workflow

That means `RELAY_HUBS` and `RELAY_TARGETS` should evolve, not disappear immediately:

- identity, code, domain, and topology should come from HQ
- transport secrets may still be stored locally for now

## Recommended Local Data Model

Add a local HQ projection table instead of treating `.env` JSON as the real registry.

### `hub_registry_hubs`

Suggested columns:

- `hq_id`
- `relay_hub_id`
- `code`
- `name`
- `deployment`
- `domain`
- `status`
- `country_code`
- `reg_code`
- `prov_code`
- `citymun_code`
- `brgy_code`
- `last_seen_at`
- `last_response_ms`
- `deployed_at`
- `has_token`
- `token_is_active`
- `token_last_used_at`
- `token_revoked_at`
- `token_issued_at`
- `raw_payload_json`
- `synced_at`

### `hub_registry_links`

Suggested columns:

- `id`
- `hub_relay_hub_id`
- `linked_relay_hub_id`
- `hub_hq_id`
- `linked_hq_id`
- `relationship_type`
  - `uplink`
  - `source`
- `uplink_type`
- `priority`
- `is_primary`
- `linked_domain`
- `raw_payload_json`
- `synced_at`

### `relay_node_settings`

Suggested columns:

- `id`
- `local_relay_hub_id`
- `local_hq_id`
- `hq_sync_enabled`
- `hq_last_sync_at`
- `hq_last_sync_status`
- `hq_last_sync_error`
- `outbound_topology_mode`
  - `manual`
  - `hq_uplinks`
  - `hq_uplinks_with_overrides`
- `inbound_trust_mode`
  - `manual`
  - `known_hq_hubs`
  - `hq_sources_only`

## Recommended Runtime Resolution

### Outbound Resolution

When sending to target hub `X`:

1. resolve target from local HQ cache
2. derive base URL from HQ hub domain unless locally overridden
3. resolve transport secret/cert config from local secure config
4. send to that hub's relay receive endpoint

### Inbound Resolution

When receiving a message:

1. identify sender hub from `source_hub_id` or `X-Relay-Hub-Id`
2. verify sender exists in local HQ cache
3. apply trust policy using cached HQ relationship data
4. verify transport credential according to configured auth mode
5. accept and process idempotently

## Transition Strategy

Do not replace the current `.env`-driven model in one step.

Recommended phases:

### Phase 1. Bootstrap HQ Awareness

Add support for:

- HQ API config
- local HQ hub selection
- registry sync service
- cached hub and uplink/source records

Keep existing `RELAY_TARGETS` and `RELAY_HUBS` working.

### Phase 2. Use HQ Data For Defaults

Change runtime resolution so:

- outbound targets default from HQ uplinks
- local node identity defaults from HQ local hub record
- inbound sender identity must exist in HQ cache

Keep manual overrides for secrets and exceptional routing.

### Phase 3. Tighten Trust Policy

Add policy controls so operators can choose:

- accept from all known HQ hubs
- accept only from HQ `sources`
- allow explicit overrides for exceptional peers

### Phase 4. Reduce Manual Peer Drift

Make `RELAY_TARGETS` and `RELAY_HUBS` primarily credential/override stores rather than identity registries.

Target end state:

- HQ owns identity and topology
- Relay owns transport execution and secret usage

## Recommended Config Shape

Suggested long-term config additions:

```dotenv
RELAY_HQ_API_ENABLED=true
RELAY_HQ_API_BASE_URL=https://hub.pbb.ph
RELAY_HQ_API_TOKEN=
RELAY_HQ_LOCAL_RELAY_HUB_ID=city-hub-01
RELAY_HQ_LOCAL_HQ_ID=14
RELAY_HQ_SYNC_ENABLED=true
RELAY_HQ_SYNC_INTERVAL_SECONDS=300
RELAY_HQ_OUTBOUND_TOPOLOGY_MODE=hq_uplinks
RELAY_HQ_INBOUND_TRUST_MODE=known_hq_hubs
```

Suggested override model:

```dotenv
RELAY_TARGET_OVERRIDES={"6":{"base_url":"https://cebu.hub.pbb.ph"}}
RELAY_HUB_CREDENTIALS={"6":{"token":"shared-city-key"}}
```

Key idea:

- `relay_hub_id` becomes the stable Relay peer key
- numeric HQ `id` remains the HQ record reference
- identity/topology are HQ-derived
- secrets and exceptional transport overrides stay local

## Required New Relay Services

Recommended new services:

- `HqHubRegistryClient`
  - calls HQ hubs API
- `HqHubRegistrySyncService`
  - syncs HQ hub and topology data into local tables
- `RelayNodeIdentityService`
  - resolves the local node identity from HQ cache
- `RelayPeerResolver`
  - resolves outbound/inbound peers from HQ cache plus local overrides
- `RelayTrustPolicy`
  - decides whether inbound sender is acceptable

## API/UX Implications

Relay admin/config UI should eventually show:

- local Relay hub binding
- last HQ sync time
- sync health
- cached uplinks
- cached sources
- per-peer credential/override state

Operators should be able to understand:

- who this relay instance is in HQ
- which uplinks were derived from HQ
- which peers are accepted inbound
- which peers have local credential overrides

## Implementation Mapping In This Repo

Current implementation files:

- `app/Relay/Registry/HqHubRegistryClient.php`
- `app/Relay/Registry/HqHubRegistrySyncService.php`
- `app/Relay/Registry/RelayNodeIdentityService.php`
- `app/Relay/Registry/RelayPeerResolver.php`
- `app/Console/Commands/RelayHqSyncCommand.php`
- `app/Models/HubRegistryHub.php`
- `app/Models/HubRegistryLink.php`
- `app/Models/RelayNodeSetting.php`

Current runtime integration points:

- outbound target resolution in `app/Relay/Transport/RelayTargetResolver.php`
- inbound hub authentication in `app/Http/Middleware/AuthenticateRelayHub.php`
- diagnostics/local identity reporting in `app/Relay/Diagnostics/RelayDiagnosticsService.php`

## Short Recommendation

Adopt `PBB HQ` as the canonical identity and topology registry for Relay installations.

Keep Relay responsible for:

- transport behavior
- queueing
- retry logic
- local credential usage
- inbound/outbound execution

But move hub identity and topology authority to HQ, with Relay using a local synced projection rather than hand-maintained peer JSON as the long-term source of truth.
