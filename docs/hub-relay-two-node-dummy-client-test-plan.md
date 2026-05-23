# Hub Relay Two-Node Dummy Client Test Plan

## Purpose

Define a practical end-to-end manual test for relay-to-relay delivery using two installed Relay nodes and dummy local clients on both sides.

This plan is intended to prove:

- outbound message creation from a local client
- hop-based delivery from one relay to its adjacent peer
- remote relay inbox visibility by `target_systems[]`
- remote handler dispatch inside the targeted client-system boundary
- undeliverable behavior when the target system does not exist on the receiving relay

## Test Shape

Use two Relay servers:

- `Relay A`
- `Relay B`

Use dummy local clients with clear `system_code` values so routing is obvious.

## Suggested Dummy Topology

### Relay A

Hub identity:

- `local_hq_id`: `10`
- example role: sender

Dummy clients:

- `alpha.sender.app`
- `alpha.forwarder.app`

### Relay B

Hub identity:

- `local_hq_id`: `11`
- example role: receiver

Dummy clients:

- `bravo.receiver.app`
- `bravo.audit.app`

## Required Setup

### 1. Install Both Relays

Install two separate Relay instances using the latest installer build.

Each instance must have:

- its own database
- its own domain or local base URL
- its own HQ hub identity

### 2. Configure Relay-To-Relay Peer Delivery

Ensure Relay A can deliver to Relay B and Relay B can deliver to Relay A.

Minimum requirement:

- each relay knows how to resolve the other relay as an outbound peer

### 3. Create Dummy Clients

On Relay A, create:

- `alpha.sender.app`
- `alpha.forwarder.app`

On Relay B, create:

- `bravo.receiver.app`
- `bravo.audit.app`

Record each client's API key.

### 4. Register Dummy Handlers

On Relay B:

- register one active handler for `bravo.receiver.app`
- register one active handler for `bravo.audit.app`

Recommended handler patterns:

- `message_type_pattern = sitrep.*`
- `source_system = alpha.sender.app`

This keeps the first pass simple and easy to explain.

## Canonical Client Submit Payload

Use client-owned `target_systems[]`:

```json
{
  "source_system": "alpha.sender.app",
  "message_type": "sitrep.current",
  "payload": {
    "incident_id": "TEST-0001",
    "summary": "Dummy inter-relay SITREP",
    "severity": "high"
  },
  "target_systems": [
    "bravo.receiver.app"
  ]
}
```

## Core Test Matrix

### Case 1: Happy Path To One Remote Target System

Action:

- submit the canonical test payload to Relay A using the API key for `alpha.sender.app`

Expected on Relay A:

- outbound message is created
- message ownership is `alpha.sender.app`
- one delivery row is created for the next-hop relay:
  - `target_hq_hub_id = 11`
- delivery moves to `delivered`

Expected on Relay B:

- receipt is created
- inbound message is stored with local `target_systems[]`
- message is visible in inbox only for `bravo.receiver.app`
- message is not visible for `bravo.audit.app`
- one handler dispatch is queued only for `bravo.receiver.app`

### Case 2: Wrong Remote Target System

Action:

- submit a payload from Relay A to Relay B using:

```json
{
  "target_systems": [
    "missing.receiver.app"
  ]
}
```

Expected on Relay A:

- outbound delivery still reaches Relay B successfully at the transport layer

Expected on Relay B:

- receipt is created
- receipt status becomes `undeliverable`
- no client inbox exposes the message
- no handler dispatch rows are created
- operators can inspect the receipt and see the unknown target-system reason

### Case 3: Two Remote Target Systems On The Same Receiving Relay

Action:

- submit from Relay A:

```json
{
  "source_system": "alpha.sender.app",
  "message_type": "sitrep.current",
  "payload": {
    "incident_id": "TEST-0002"
  },
  "target_systems": [
    "bravo.receiver.app",
    "bravo.audit.app"
  ]
}
```

Expected on Relay A:

- one outbound message
- one delivery row for the next-hop relay

Expected on Relay B:

- same relay message is stored once
- both target systems are retained in local `target_systems[]`
- `bravo.receiver.app` sees the message in inbox
- `bravo.audit.app` sees the same message in inbox
- each targeted client only gets its own handler dispatches

### Case 4: Non-Targeted Client Isolation

Setup:

- create a third client on Relay B such as `charlie.other.app`

Action:

- use the same message from Case 1 or Case 3

Expected:

- `charlie.other.app` cannot see the inbound message in inbox
- `charlie.other.app` has no handler dispatches for that message

### Case 5: Reverse Direction

Action:

- submit a similar message from Relay B to Relay A using `bravo.receiver.app`

Expected:

- the same ownership and target-routing rules apply in reverse

## Recommended Manual Checks

For each case, inspect:

- `/relay/outbox`
- `/relay/deliveries`
- `/relay/inbox`
- `/relay/clients`
- `/relay` detail views for the message and delivery records

Also verify via client APIs when possible:

- `GET /api/v1/messages`
- `GET /api/v1/deliveries`
- `GET /api/v1/inbox`
- `GET /api/v1/handler-dispatches`

## Pass Criteria

This two-node test is successful when:

- hop-based relay forwarding routes correctly between Relay A and Relay B
- only the targeted remote client systems gain inbox visibility
- handler dispatch stays inside the targeted client boundary
- missing remote target systems become `undeliverable`
- no non-targeted client can read or process the message

## Follow-Up After This Test

If this test passes, the next useful expansion is:

- add a forwarding scenario where a receiver on Relay B creates a new outbound message to Relay A or to a third relay
- verify lineage using `correlation_id` or `reference_id`
