# Hub Relay Client And Handler Stage 1 Checklist

This checklist translates the approved Stage 1 client/handler testing proposal into the concrete behaviors Relay should verify now.

Use this checklist for:

- feature-test coverage
- manual QA of the `Clients` section
- acceptance checks before deeper client-ownership work starts

Status markers used below:

- `[x]` implemented and covered now
- `[ ]` still pending manual QA, additional coverage, or a later decision

Current implementation note:

- outbound message ownership by client is now implemented
- outbound delivery inspection/retry/cancel is now client-scoped
- inbound inbox visibility is now explicit through `targets[] -> client.system_code`

## Client Lifecycle

- [x] Admin can create a local relay client
- [x] Generated API key works immediately
- [x] API key rotation invalidates the old key
- [x] Client can be deactivated
- [x] Deactivated client key is rejected by local application routes
- [x] Client can be reactivated

## Client Authentication Boundary

- [x] Missing `X-Relay-Key` is rejected
- [x] Invalid `X-Relay-Key` is rejected
- [x] Inactive client key is rejected
- [x] Valid active client key is accepted

Recommended route coverage:

- [x] `POST /api/v1/messages`
- [x] `GET /api/v1/handlers`
- [x] `GET /api/v1/handler-dispatches`

## Handler Ownership

- [x] Client can register its own handler
- [x] Client can update its own handler
- [x] Client can deactivate its own handler
- [x] Client cannot update another client's handler
- [x] Client cannot deactivate another client's handler

## Handler Matching

- [x] Matching active handler is queued
- [x] Inactive handler is skipped
- [x] Handler with non-matching `message_type_pattern` is skipped
- [x] Handler with non-matching `source_system` is skipped
- [x] Handler with non-matching `source_hub_id` is skipped
- [x] Handlers from non-target clients are skipped even if their match rules would otherwise match

## Handler Dispatch Isolation

- [x] Client can list its own handler dispatches
- [x] Client can view its own handler dispatch detail
- [x] Client can retry its own failed/dead dispatch
- [x] Client cannot view another client's dispatch detail
- [x] Client cannot retry another client's dispatch
- [x] Client dispatch list does not include another client's dispatches

## Outbound Ownership

- [x] Submitted outbound messages store the authenticated client's ownership
- [x] Client can list only its own outbound messages
- [x] Client can view only its own outbound message detail
- [x] Client can list only its own delivery records
- [x] Client can view only its own delivery detail
- [x] Client cannot retry another client's delivery
- [x] Client cannot cancel another client's delivery

## Suggested Stage 1 Pack

- [x] Create client A
- [x] Create client B
- [x] Create one matching handler for client A
- [x] Create one matching handler for client B
- [x] Add inactive and mismatching handlers as controls
- [x] Submit or receive a message that should match only the intended handlers
- [x] Verify dispatch rows created only for matching active handlers
- [x] Verify dispatch isolation per client key

## Deliberate Out Of Scope For Stage 1

Do not treat these as failures in Stage 1 yet:

- [ ] inbound message attribution/subscription model per client
- [ ] pull-only inbound subscriptions independent of explicit target pairs

These belong to the later inbound subscription and consume-model phase beyond the current explicit `targets[]` routing rule.

## Current Evidence

Current automated coverage exists in:

- `tests/Feature/Relay/AdminScreensTest.php`
- `tests/Feature/Relay/Api/ClientHandlerStage1Test.php`
- `tests/Feature/Relay/Api/HandlerRegistrationTest.php`
- `tests/Feature/Relay/Api/HandlerDispatchControlTest.php`
- `tests/Feature/Relay/Api/InboxAndHandlerDispatchTest.php`
- `tests/Feature/Relay/Api/MessageSubmissionTest.php`
