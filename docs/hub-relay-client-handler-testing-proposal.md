# Hub Relay Client And Handler Testing Proposal

## Purpose

Define a practical testing plan for the `Clients` section of `PBB - Hub Relay Server` and for the real behavior of client-owned handlers.

This proposal exists because Relay already has:

- client API-key authentication
- client lifecycle management in `/relay/clients`
- handler registration per client
- handler dispatch tracking and retry

Relay now also stores outbound message ownership by client, and inbound routing now uses explicit `targets[]` entries where:

- `target_hq_hub_id` identifies the destination HQ hub
- `target_system` maps to the destination client's `system_code`

That means message submission, delivery inspection, inbound inbox visibility, and handler routing can now all be tested under explicit client-ownership rules.

## Current Implementation Reality

### What Relay Already Models

- each local application client has:
  - `name`
  - `system_code`
  - `api_key`
  - active/inactive state
- each handler belongs to exactly one client
- local application API routes require `X-Relay-Key`
- handler dispatch list/detail/retry routes are already scoped to the authenticated client
- handler matching can filter on:
  - `message_type_pattern`
  - `source_system`
  - `source_hub_id`
- inbound routing first narrows handlers to the client systems explicitly listed in local `targets[]`

### What Relay Does Not Yet Model Clearly

- Relay still treats `source_system` as message metadata, not as authoritative client ownership
- Relay still does not implement multi-client local fanout within one receiving hub
- Relay still does not implement per-client consume markers or advanced subscription grants beyond explicit target pairs

### What Relay Now Models Explicitly

- outbound messages store `hub_relay_client_id`
- local message list/detail is scoped to the authenticated client
- local delivery list/detail/retry/cancel is scoped through the owning outbound message's client
- inbound messages carry explicit `targets[]`
- inbound inbox list/detail is scoped through `targets[] -> client.system_code`
- handler ownership and handler-dispatch ownership remain client-scoped

## Main Testing Boundary

Testing should be split into two phases.

### Phase A: Test What The Current Implementation Can Already Prove

These tests are meaningful now:

- client lifecycle
- client credential enforcement
- handler ownership and isolation
- handler dispatch behavior per client
- client usability through the `/relay/clients` admin area

### Phase B: Test Explicit Client Routing

These tests should happen only after Relay explicitly decides and implements:

- how an explicitly targeted but unknown `target_system` should behave when no local client exists
- whether Relay should later add inbox grants or pull-only subscriptions beyond explicit target pairs

Relay already implements client-owned outbound messages, client-owned delivery inspection, and explicit-target inbound visibility. The remaining ambiguity is how much flexibility to add beyond that explicit route.

## Recommended Testing Goals

### 1. Client Lifecycle

Verify from the admin/operator side that a client can be:

- created
- issued an API key
- rotated to a new API key
- activated/deactivated
- documented for a real local application team

Expected outcomes:

- new client is immediately usable with the generated key
- old key stops working after rotation
- inactive client cannot call local application APIs
- admin detail page shows the right handler inventory and activity summary

### 2. Client Authentication Enforcement

Verify the API boundary for local applications.

Required tests:

- missing `X-Relay-Key` returns `401`
- invalid key returns `401`
- inactive client key returns `401`
- valid active key succeeds

This should be tested against:

- `POST /api/v1/messages`
- `GET /api/v1/messages`
- `GET /api/v1/inbox`
- `GET /api/v1/deliveries`
- handler routes
- handler-dispatch routes

### 3. Handler Ownership And Isolation

Verify that handlers remain owned by one client only.

Required tests:

- client A can create handlers for itself
- client A can update/toggle only its own handlers
- client B cannot read or mutate client A handler state through client-scoped APIs
- handler-dispatch list/detail/retry routes only expose dispatches for handlers owned by the authenticated client

Expected outcome:

- handler scope is enforced by `hub_relay_client_id`, not by UI convention alone

### 4. Handler Matching Behavior

Verify that handler execution rules are correct when messages arrive.

Recommended cases:

- handler with only `message_type_pattern`
- handler with matching `source_system`
- handler with non-matching `source_system`
- handler with matching `source_hub_id`
- handler with non-matching `source_hub_id`
- inactive handler should not dispatch

Expected outcomes:

- matching handlers create dispatch rows
- non-matching handlers do not create dispatch rows
- inactive handlers are skipped

### 5. Handler Dispatch Lifecycle

Verify the end-to-end local handoff path once a message reaches Relay.

Recommended cases:

- successful dispatch to local webhook
- failed dispatch due to remote error or timeout
- retryable failed dispatch
- manual retry from API
- manual retry from admin/operator UI

Expected outcomes:

- dispatch status changes are visible
- attempt counts increment correctly
- retry preserves ownership and dispatch identity
- dead/failed dispatches remain inspectable by the correct client

### 6. Real Client Simulation

Test Relay using two realistic local clients, for example:

- `sitrep.app`
- `case-mgmt.app`

Each client should have:

- its own API key
- its own handlers
- distinct message types and source-system patterns

This gives a more realistic basis for checking:

- credential boundary
- handler matching
- dispatch isolation
- admin/operator visibility

## Recommended Test Matrix

### Matrix A: Current Valid Scope

#### Client A

- can submit outbound messages with its API key
- can list only its own submitted messages while authenticated by its API key
- can inspect only its own deliveries while authenticated by its API key
- can list only inbound messages whose local `targets[]` include that client system
- can register handlers
- can list only its own handler dispatches

#### Client B

- same checks as Client A

#### Cross-Client Checks

- client B cannot see client A messages
- client B cannot inspect, retry, or cancel client A deliveries
- client B cannot see client A inbox messages because inbound routing now targets exactly one client system
- client B cannot see or retry client A handler dispatches
- client B cannot mutate client A handlers
- inactive client key for either client is rejected

## Important Product Question Before Further Testing

Relay now has an explicit ownership rule for local outbound records and for inbound inbox visibility.

### Option 1: Shared Local Relay Inbox

Direction:

- any valid local client can read the same local inbox
- client keys authenticate trusted local systems, while outbound ownership remains client-scoped

Implication:

- testing focuses on client authentication, outbound ownership, and handler ownership
- no expectation that client A and client B see different inboxes

### Option 2: Client-Owned Messages And Inbox

Direction:

- submitted messages already store `hub_relay_client_id`
- inbound messages carry explicit `targets[]`
- inbox views are client-scoped through explicit target entries
- a client can only inspect and consume its own data unless explicitly granted broader access

Implication:

- Relay should keep separate future decisions open for subscription grants and consume markers
- Relay can already test “client A cannot see client B inbox receipts” under the current explicit target model

## Recommendation

Use a staged approach.

### Stage 1: Immediate Testing

Start now with:

- client lifecycle
- credential enforcement
- handler ownership
- handler matching
- handler dispatch lifecycle

This validates the current implementation honestly without pretending Relay already has client-owned message isolation.

### Stage 2: Ownership Decision

Choose and implement the inbound ownership model. Relay has now chosen:

- client-owned outbound messages and deliveries
- explicit inbound routing through `targets[]`
- inbox visibility through `targets[] -> client.system_code`

The remaining future decisions are whether to add pull-only subscriptions, consume markers, or broader local fanout beyond one targeted client system.

### Stage 3: Ownership-Aware Tests

With explicit target-pair routing now implemented, the remaining future work is:

- optional pull-only subscription rules
- optional explicit inbox grants beyond explicit target pairs
- any needed per-client consume or acknowledge semantics

Then add:

- client A cannot see client B inbox receipts
- client A cannot consume client B inbound work
- handler dispatch still remains client-owned and consistent with message ownership

## Suggested First Concrete Test Pack

The first practical test pack for Relay should be:

1. create two clients
2. create one handler per client
3. submit messages using each client key
4. verify API-key enforcement on local application routes
5. verify `targets[]` routing constrains handler matching to the correct client-owned handlers
6. verify handler-dispatch list/detail/retry are isolated per client
7. verify client deactivation blocks message submission and handler-dispatch access

## Expected Deliverables

If this proposal is followed, the next deliverables should be:

- a documented test checklist for client and handler behavior
- feature tests covering the valid current client/handler scope
- a separate design decision on advanced inbound subscription/fanout rules
- follow-up implementation only after that routing/ownership rule is explicit

## Bottom Line

Relay should not jump straight into “client send/receive isolation” testing as if the product already supports it.

The correct near-term move is:

- test clients as authenticated local application identities
- test outbound messages and deliveries as client-owned records
- test inbox visibility as explicit target-owned access
- test handlers as client-owned local handoff rules
- test handler dispatches as client-isolated operational records

Then, as a separate design step, decide whether Relay should add subscription/fanout behavior beyond the current explicit `targets[]` routing rule.
