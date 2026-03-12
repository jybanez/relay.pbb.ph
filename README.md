# relay.pbb.ph

`relay.pbb.ph` is the proposed home of the Project Bantay Bayan `Hub Relay` server.

This repository is being prepared as the canonical implementation workspace for:

- the `Hub Relay Server` architecture
- hub-to-hub relay transport
- local application integration APIs
- shared monitoring and diagnostics
- future relay workers, uploads, retries, and receipts

## Start Here

Read the proposal set in:

- [docs/hub-relay-proposals-readme.md](docs/hub-relay-proposals-readme.md)

Recommended reading order:

1. `docs/hub-relay-server-proposal.md`
2. `docs/hub-relay-proposal.md`
3. `docs/hub-relay-shared-package-proposal.md`
4. `docs/hub-relay-repo-structure-proposal.md`

## Current Status

This repository is currently a prepared implementation baseline.

What is already here:

- proposal documents
- initial repository scaffold

What should happen next:

1. confirm the server-first architecture
2. scaffold the Laravel service application
3. define the first API contract
4. implement the first narrow slice:
   - JSON-only messages
   - queueing
   - delivery tracking
   - receipts and idempotency
   - diagnostics endpoint

## Notes

The current recommended direction is:

- `Hub Relay` as a local shared service per hub location
- API-first integration for local systems
- relay-server to relay-server transport across hubs
- shared monitoring owned by the relay layer

The shared-package proposal remains relevant as the codebase packaging and release strategy for the relay server itself.
