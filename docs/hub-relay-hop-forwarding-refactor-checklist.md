# Hub Relay Hop-Forwarding Refactor Checklist

## Goal

Refactor Relay from exact recipient-pair routing to a split ownership model:

- client owns:
  - `source_system`
  - `message_type`
  - `payload`
  - `target_systems[]`
- relay owns:
  - `relay_id`
  - `origin_hq_hub_id`
  - `source_hub_id` per hop
  - `target_hq_hub_id` per hop
  - `hop_trace`

## Phase 1: Contract Direction

- [x] Change local client submit contract to accept `target_systems[]`
- [x] Remove `relay_id` from client-facing submit input
- [x] Remove `source_hub_id` from client-facing submit input
- [x] Remove `targets[]` from client-facing submit input
- [ ] Define relay transport envelope as:
  - `relay_id`
  - `origin_hq_hub_id`
  - `source_hub_id`
  - `source_system`
  - `target_hq_hub_id`
  - `target_systems[]`
  - `hop_trace`
  - existing business payload fields

## Phase 2: Message Storage

- [x] Add `origin_hq_hub_id` to relay messages
- [x] Add `hop_trace` JSON to relay messages
- [x] Keep `target_systems` as the client-owned logical audience
- [x] Treat `target_hub_ids` as derived next-hop tracking, not client input
- [x] Stop relying on exact `targets[]` pairs for message ownership and inbox visibility

## Phase 3: Delivery Storage

- [x] Treat each delivery as one next-hop transport attempt
- [x] Keep `target_hq_hub_id` as the next-hop hub identity
- [x] Stop requiring `target_system` on deliveries for routing semantics
- [x] Preserve delivery filtering and worker efficiency under the new next-hop model

## Phase 4: Node Identity

- [x] Add a canonical local HQ hub ID accessor in relay node identity services
- [x] Ensure local submission stamps:
  - `origin_hq_hub_id = local_hq_id`
  - `source_hub_id = local_hq_id`
- [x] Ensure forwarded deliveries stamp:
  - `origin_hq_hub_id` stays unchanged
  - `source_hub_id = current local_hq_id`

## Phase 5: Topology And Forwarding

- [x] Add next-hop resolution from HQ-managed adjacency
- [x] Resolve candidate next hops from registered upstream/downstream links
- [x] Exclude already visited hubs from forwarding candidates
- [x] Exclude the current relay itself from forwarding candidates
- [x] Exclude the immediate previous hop when needed
- [x] Create outbound deliveries per eligible next hop

## Phase 6: Hop Trace And Loop Control

- [x] Add append-only `hop_trace`
- [x] Initialize trace on local submit
- [x] Append current relay on inbound receive
- [x] Detect loops by checking whether the current relay already exists in trace
- [x] Reject or stop processing looped messages safely

## Phase 7: Inbound Local Delivery

- [x] Consider a message locally deliverable when:
  - `target_hq_hub_id` equals local HQ hub ID
  - at least one local client `system_code` matches `target_systems[]`
- [x] Scope inbox visibility by `target_systems[] -> client.system_code`
- [x] Scope handler execution by `target_systems[] -> client.system_code`
- [x] Preserve local visibility even when no handler matches

## Phase 8: Inbound Forwarding Outcomes

- [x] Forward a received message to eligible unvisited adjacent relays
- [x] If local systems match, process local inbox/handlers and still forward if topology requires
- [x] If no local systems match but next hops exist, forward without marking terminal failure
- [x] Mark as `undeliverable` only when:
  - no local client matches `target_systems[]`
  - and no next hops remain

## Phase 9: API And UI Surface

- [x] Update `/api/v1/messages` docs and validation to the new client-facing contract
- [x] Update `/api/v1/receive` docs and validation to the new relay transport contract
- [x] Update inbox/delivery/admin views to reflect:
  - `target_systems[]`
  - next-hop `target_hq_hub_id`
  - `hop_trace`
- [ ] Update diagnostics to show hop trace and forwarding outcomes

## Phase 10: Tests

- [x] Rewrite message submission tests for client-owned `target_systems[]`
- [x] Rewrite inbound receive tests for hop-owned `target_hq_hub_id`
- [x] Add loop-prevention tests
- [x] Add forwarding-to-adjacent-peer tests
- [x] Add local-delivery-plus-forwarding tests
- [x] Add terminal-undeliverable tests
- [x] Update installer tests if embedded release schema/behavior changes

## Phase 11: Packaging

- [x] Refresh installer build after the refactor lands
- [x] Ensure fresh installs get the new forwarding contract

## Completion Rule

This refactor is complete when:

- clients submit only `target_systems[]`
- relays own per-hop targeting and trace metadata
- forwarding follows registered HQ topology
- loops are prevented through `hop_trace`
- inbox and handlers remain scoped by `target_systems[]`
- terminal undeliverable state is explicit
