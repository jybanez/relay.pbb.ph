# Hub Relay Explicit Target Routing Implementation Checklist

## Goal

Replace the current `target_hub_ids + target_system` model with canonical `targets[]` routing entries.

## Phase 1: Design And Contract

- [x] Freeze the routing direction around `targets[]`
- [x] Use `target_hq_hub_id` instead of Relay hub ID in each target
- [x] Use `target_system` mapped to client `system_code`
- [x] Treat forwarding as a new message from a forwarding application

## Phase 2: Message Storage

- [x] Add canonical `targets` JSON to `hub_relay_messages`
- [x] Add derived `target_hq_hub_ids` JSON to `hub_relay_messages`
- [x] Add derived `target_systems` JSON to `hub_relay_messages`
- [x] Remove reliance on single `target_system` for routing decisions
- [x] Remove legacy `target_hub_ids + target_system` API compatibility handling

## Phase 3: Delivery Storage

- [x] Add `target_hq_hub_id` to `hub_relay_deliveries`
- [x] Add `target_system` to `hub_relay_deliveries`
- [x] Make one delivery record represent one explicit target pair
- [x] Stop treating deliveries as per-hub-only records

## Phase 4: Envelope And Validation

- [x] Update `RelayEnvelopeDTO` to model `targets[]`
- [x] Validate each target entry for:
  - `target_hq_hub_id`
  - `target_system`
- [x] Reject empty target sets
- [x] Reject or normalize duplicate target pairs
- [x] Update controller validation for outbound submit and inbound receive

## Phase 5: Outbound Submission And Delivery

- [x] Store canonical `targets[]` on outbound message create
- [x] Create one delivery per unique target entry
- [x] Send per-delivery payloads containing only the relevant target entry
- [x] Resolve outbound relay peer by HQ numeric hub ID

## Phase 6: Inbound Receive And Local Routing

- [x] Resolve local relay identity by HQ numeric hub ID
- [x] Select only target entries intended for the local relay
- [x] Reject inbound messages with no local target entries
- [x] Accept messages with local targets but unknown local `target_system` values and mark them `undeliverable`
- [x] Route local visibility by explicit target entry, not handler fanout inference
- [x] Queue handlers only for the explicitly targeted client system

## Phase 7: Inbox And Handler Behavior

- [x] Scope inbox list/detail by explicit `targets[]` membership for the authenticated client
- [x] Ensure non-targeted clients cannot see inbound messages
- [x] Ensure handlers run only inside the targeted client boundary
- [x] Preserve “no handler matched” inbox visibility for the targeted client

## Phase 8: Admin And Diagnostics

- [x] Update admin list/detail displays to show `targets[]` cleanly
- [ ] Update diagnostics or troubleshooting views to display explicit target pairs

## Phase 9: Documentation

- [x] Update README examples to use `targets[]`
- [x] Update API manual to use `targets[]`
- [x] Update API reference to use `targets[]`
- [x] Update client-handler testing proposal/checklists to describe explicit target pairs
- [x] Update OpenAPI schema and examples

## Phase 10: Tests

- [x] Update envelope validator unit tests for `targets[]`
- [x] Update message submission tests for multi-target pairs
- [x] Update delivery processing tests for one-delivery-per-target-entry
- [x] Update inbound receive tests for local target filtering
- [x] Update inbox tests for explicit target visibility
- [x] Update handler routing tests so matching happens only within targeted client systems
- [x] Add test for duplicate target-pair rejection or normalization
- [x] Add test for unknown target system on receiving relay
- [x] Add test for no local target entries on receiving relay

## Completion Rule

This refactor is complete when:

- `targets[]` is the canonical routing model
- deliveries are per `(target_hq_hub_id, target_system)` pair
- inbox visibility follows explicit target entries
- handlers run only inside explicitly targeted client systems
- docs and tests describe the same routing model
