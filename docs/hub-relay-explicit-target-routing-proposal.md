# Hub Relay Explicit Target Routing Proposal

## Purpose

Replace the current `target_hub_ids + target_system` routing model with explicit per-recipient target entries.

This proposal exists because Relay now needs to support one outbound message being sent to:

- multiple destination hubs
- multiple destination systems
- forwarding applications that intentionally generate a new downstream relay message

without relying on ambiguous parallel arrays or implicit local fanout.

## Proposed Envelope Model

Relay messages should use explicit `targets[]` objects.

```json
{
  "source_hq_hub_id": 2,
  "source_system": "emergency-hotline",
  "message_type": "sitrep.current",
  "payload": {},
  "targets": [
    { "target_hq_hub_id": 10, "target_system": "city-eoc.app" },
    { "target_hq_hub_id": 10, "target_system": "provincial-forwarder.app" },
    { "target_hq_hub_id": 456, "target_system": "rafi-foundation.app" }
  ]
}
```

Forwarding is a new message from a forwarding application, not hidden relay transport behavior.

```json
{
  "source_hq_hub_id": 10,
  "source_system": "provincial-forwarder.app",
  "message_type": "sitrep.current",
  "payload": {},
  "targets": [
    { "target_hq_hub_id": 123, "target_system": "provincial-eoc.app" }
  ]
}
```

## Why This Replaces The Current Model

The current shape:

- `target_hub_ids`
- single `target_system`

does not model one message targeting different systems on different hubs.

The previously discussed alternative of:

- `target_hub_ids[]`
- `target_systems[]`

is still weaker than `targets[]` because it depends on positional matching between separate arrays.

`targets[]` is preferred because it:

- keeps each destination pair explicit
- validates cleanly
- avoids positional ambiguity
- makes delivery records naturally per-recipient
- gives receiving relays an exact authorization key:
  - `target_hq_hub_id`
  - `target_system`

## Identity Rules

### Source Hub Identity

Use HQ numeric hub ID as the canonical transport identity in the envelope.

Reason:

- HQ numeric hub ID is system-generated
- Relay hub ID is manually entered and more error-prone

### Target Hub Identity

Each target entry should use:

- `target_hq_hub_id`

not Relay hub ID.

### Target System Identity

Each target entry should use:

- `target_system`

where `target_system` maps directly to the receiving relay client's `system_code`.

## Delivery Model

One outbound message can produce many deliveries.

One delivery should map to one explicit target entry:

- one `target_hq_hub_id`
- one `target_system`

That means delivery records should stop being “per hub only” and become “per target pair”.

## Receiving Relay Behavior

When a relay receives a message:

1. validate the envelope and `targets[]`
2. determine which target entries are intended for the local relay's HQ hub ID
3. expose the message only to the client whose `system_code` matches the matched local target entry
4. queue local handlers only for that explicitly targeted client

Relay should not infer destination client visibility through broad handler matching.

Handler matching should become a secondary step that happens only inside the explicitly targeted client boundary.

## Forwarding Behavior

Relay transport should not implement hidden multi-hop business forwarding.

If a local application wants to pass a message onward, that application should submit a new outbound relay message using its own:

- `source_hq_hub_id`
- `source_system`
- `targets[]`

This keeps:

- transport generic
- forwarding auditable
- message lineage clearer through `correlation_id` and `reference_id`

## Validation Rules

Relay should enforce:

- `targets` must be a non-empty array
- each target must contain:
  - `target_hq_hub_id`
  - `target_system`
- duplicate target pairs should be rejected or normalized
- each outbound delivery should be created from one unique target pair

Receiving relay should also enforce:

- only target entries for the current local HQ hub should be considered local deliveries
- if no target entry matches the local HQ hub, the inbound message should be rejected
- if a local target entry references an unknown `target_system`, the message should be recorded and marked `undeliverable` for local client delivery

## Storage Direction

Relay should store:

- canonical `targets` JSON on the message
- derived query-friendly fields where useful

Recommended derived fields:

- `target_hq_hub_ids`
- `target_systems`

These are derived from `targets[]` and exist only to simplify querying, filtering, and admin display.

## Contract Direction

The system should use `targets[]` as the only documented and accepted API contract for explicit destination routing.

That means:

- outbound API requires `targets[]`
- inbound relay delivery requires `targets[]`
- storage is canonicalized to `targets[]`

## Expected Benefits

- explicit security boundary per recipient
- cleaner message-to-delivery mapping
- clearer separation between delivery and forwarding
- less configuration ambiguity
- better fit for realistic multi-recipient incident routing

## Recommendation

Adopt `targets[]` with:

- `source_hq_hub_id`
- `source_system`
- `targets[].target_hq_hub_id`
- `targets[].target_system`

and treat forwarding as a new outbound relay message created by a forwarding application, not as hidden relay-core transport logic.
