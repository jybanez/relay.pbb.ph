# Hub Relay Client And Handler Stage 2 Checklist

This checklist defines the next ownership pass after Stage 1.

Stage 2 focuses on explicit inbound routing and inbox visibility.

Decision implemented in this stage:

- inbound routing is now **explicit target-pair based**
- each target entry contains:
  - `target_hq_hub_id`
  - `target_system`
- `target_system` refers to the destination client's `system_code`
- inbox visibility follows explicit local target entries, not broad cross-client handler matching
- handlers only run inside the targeted client system

Status markers used below:

- `[x]` implemented and covered now
- `[ ]` still pending follow-up work or a later product decision

## Inbound Ownership Rule

- [x] Inbox visibility is no longer shared across all valid clients
- [x] Inbox visibility is derived from explicit local target entries
- [x] Client A cannot list inbound messages visible only to client B
- [x] Client A cannot view inbound message detail visible only to client B
- [x] Inbound messages can still be visible to the targeted client even when no handler matched

## Receive And Dispatch Alignment

- [x] A matched inbound receive only evaluates handlers owned by the targeted client system
- [x] Inbox list uses the same explicit target boundary as handler routing
- [x] Inbox detail uses the same explicit target boundary as handler routing
- [x] One inbound message no longer fans out across multiple clients on the same hub

## Deliberate Out Of Scope For Stage 2

- [ ] per-client receipt acknowledgements or consume markers
- [ ] explicit inbox grant records independent of explicit target pairs
- [ ] post-receive reassignment of existing inbound messages to newly added clients
- [ ] multi-recipient local delivery within one receiving hub

## Current Evidence

Current automated coverage exists in:

- `tests/Feature/Relay/Api/InboxAndHandlerDispatchTest.php`
- `tests/Feature/Relay/Api/ClientHandlerStage1Test.php`
