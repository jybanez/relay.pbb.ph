# Hub Relay Two-Node Dummy Client Live Checklist

## Goal

Run a real two-node Relay-to-Relay test using dummy local clients, client-owned `target_systems[]`, and hop-based relay forwarding.

Use this after both relays are installed from the latest installer build.

## Preflight

- [ ] Relay A is installed and reachable
- [ ] Relay B is installed and reachable
- [ ] Relay A and Relay B have different databases
- [ ] Relay A and Relay B have different HQ hub identities
- [ ] Relay A can resolve Relay B as an outbound relay peer
- [ ] Relay B can resolve Relay A as an outbound relay peer
- [ ] Both relays are running queue workers

## Dummy Client Setup

### Relay A

- [ ] Create client `alpha.sender.app`
- [ ] Record the API key for `alpha.sender.app`
- [ ] Create client `alpha.forwarder.app`
- [ ] Record the API key for `alpha.forwarder.app`

### Relay B

- [ ] Create client `bravo.receiver.app`
- [ ] Record the API key for `bravo.receiver.app`
- [ ] Create client `bravo.audit.app`
- [ ] Record the API key for `bravo.audit.app`
- [ ] Optionally create client `charlie.other.app`
- [ ] Record the API key for `charlie.other.app`

## Dummy Handler Setup

### Relay B

- [ ] Register one active handler for `bravo.receiver.app`
- [ ] Set `message_type_pattern` to `sitrep.*`
- [ ] Set `source_system` to `alpha.sender.app`
- [ ] Register one active handler for `bravo.audit.app`
- [ ] Set `message_type_pattern` to `sitrep.*`
- [ ] Set `source_system` to `alpha.sender.app`

## Case 1: Happy Path To One Remote Target System

Submit from Relay A as `alpha.sender.app`:

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

### Expected On Relay A

- [ ] Outbound message is created
- [ ] Message is owned by `alpha.sender.app`
- [ ] One delivery row is created
- [ ] Delivery next hop is Relay B (`11`)
- [ ] Delivery reaches `delivered`

### Expected On Relay B

- [ ] Receipt is created
- [ ] Receipt status is `processed` or equivalent successful receive state
- [ ] Inbound message appears in `bravo.receiver.app` inbox
- [ ] Inbound message does not appear in `bravo.audit.app` inbox
- [ ] One handler dispatch is created for `bravo.receiver.app`
- [ ] No handler dispatch is created for `bravo.audit.app`

## Case 2: Unknown Remote Target System

Submit from Relay A:

```json
{
  "source_system": "alpha.sender.app",
  "message_type": "sitrep.current",
  "payload": {
    "incident_id": "TEST-0002"
  },
  "target_systems": [
    "missing.receiver.app"
  ]
}
```

### Expected On Relay A

- [ ] Outbound message is created
- [ ] Delivery reaches Relay B at transport level

### Expected On Relay B

- [ ] Receipt is created
- [ ] Receipt status is `undeliverable`
- [ ] No client inbox exposes the message
- [ ] No handler dispatch is created
- [ ] Operator views show the unknown target-system reason

## Case 3: Two Target Systems On Relay B

Submit from Relay A:

```json
{
  "source_system": "alpha.sender.app",
  "message_type": "sitrep.current",
  "payload": {
    "incident_id": "TEST-0003"
  },
  "target_systems": [
    "bravo.receiver.app",
    "bravo.audit.app"
  ]
}
```

### Expected On Relay A

- [ ] One outbound message is created
- [ ] One delivery row is created for Relay B as the next hop

### Expected On Relay B

- [ ] One inbound message record is stored
- [ ] Local `target_systems[]` contains both target systems
- [ ] `bravo.receiver.app` sees the message in inbox
- [ ] `bravo.audit.app` sees the message in inbox
- [ ] `bravo.receiver.app` sees only its own handler dispatches
- [ ] `bravo.audit.app` sees only its own handler dispatches

## Case 4: Non-Targeted Client Isolation

Use the message from Case 1 or Case 3.

### Expected On Relay B

- [ ] `charlie.other.app` cannot see the message in inbox
- [ ] `charlie.other.app` has no handler dispatch for the message

## Case 5: Reverse Direction

Submit from Relay B to Relay A using `bravo.receiver.app`.

### Expected

- [ ] Outbound message is owned by the Relay B client that submitted it
- [ ] Relay A receives it only for the explicitly targeted system
- [ ] Non-targeted Relay A clients do not see it
- [ ] Handler dispatch stays inside the targeted Relay A client boundary

## Operator Screens To Check

### Relay A

- [ ] `/relay/outbox`
- [ ] `/relay/deliveries`
- [ ] detail view for the sent message
- [ ] detail view for the delivery

### Relay B

- [ ] `/relay/inbox`
- [ ] `/relay/deliveries` if any reverse test is run
- [ ] handler-dispatch detail screen
- [ ] client detail screen for targeted clients

## Client API Checks

### On Relay A

- [ ] `GET /api/v1/messages` with `alpha.sender.app` key
- [ ] `GET /api/v1/deliveries` with `alpha.sender.app` key

### On Relay B

- [ ] `GET /api/v1/inbox` with `bravo.receiver.app` key
- [ ] `GET /api/v1/inbox` with `bravo.audit.app` key
- [ ] `GET /api/v1/inbox` with `charlie.other.app` key
- [ ] `GET /api/v1/handler-dispatches` with each targeted client key

## Pass Rule

- [ ] Hop-based relay forwarding routes correctly between Relay A and Relay B
- [ ] Only targeted systems gain inbox visibility
- [ ] Handler dispatch remains inside the targeted client boundary
- [ ] Unknown target systems become `undeliverable`
- [ ] Non-targeted clients cannot see or process the message

## Follow-Up

After this checklist passes:

- [ ] test forwarding using `alpha.forwarder.app` or a Relay B forwarder client
- [ ] verify message lineage using `correlation_id` and/or `reference_id`
