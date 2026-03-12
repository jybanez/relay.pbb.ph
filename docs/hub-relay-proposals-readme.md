# Hub Relay Proposal Set

## Purpose

This proposal set is the starting point for designing and implementing the `Hub Relay` capability for Project Bantay Bayan.

These documents should be read together, but they do not have the same role.

## Reading Order

### 1. Start Here

- `hub-relay-server-proposal.md`

This is the primary architecture proposal.

It defines the recommended direction:

- `Hub Relay` as a local shared service
- API-first integration for local applications
- relay-server to relay-server delivery between hubs
- optional SDKs as convenience clients only

This document answers:

- what `Hub Relay` should be architecturally
- how local systems should integrate with it
- why the server model is preferred over embedding relay code in every app

### 2. Functional and Operational Spec

- `hub-relay-proposal.md`

This is the system behavior proposal.

It defines:

- envelope and message model
- delivery model
- retries
- attachment transfer
- monitoring
- outage and reconnectivity handling
- proposed views
- proposed endpoints

This document answers:

- how the relay should behave
- what features and operational states it should support
- what the transport and monitoring model should look like

### 3. Implementation and Distribution Strategy

- `hub-relay-shared-package-proposal.md`

This document explains how the relay codebase itself should be managed.

With the newer server-first direction, this proposal should be treated as:

- the packaging and release strategy for building and maintaining the relay server
- not the primary integration model for local business applications

This document answers:

- how the relay server should be versioned and released
- how fixes and improvements should be propagated consistently
- how shared code and deployment-specific behavior should be separated

### 4. Repository Scaffold

- `hub-relay-repo-structure-proposal.md`

This document gives the team a concrete starting layout for the future relay server repository.

It covers:

- suggested repository name
- Laravel-oriented folder structure
- internal domain/service boundaries
- route, config, model, migration, and test layout
- suggested first implementation slice

## Recommended Team Workflow

1. Confirm agreement on `hub-relay-server-proposal.md`
2. Use `hub-relay-proposal.md` to define the first implementation scope
3. Use `hub-relay-shared-package-proposal.md` to decide repository, versioning, and rollout strategy
4. Use `hub-relay-repo-structure-proposal.md` to scaffold the repository consistently

## Suggested First Deliverables

Once the team aligns on the architecture, the next useful implementation outputs are:

- service repository structure
- initial API contract
- initial database schema
- local client authentication model
- hub-to-hub authentication model
- phase-1 message flow for `SITREP`

## Short Summary

Use these documents with the following mental model:

- `hub-relay-server-proposal.md` = target architecture
- `hub-relay-proposal.md` = functional and operational behavior
- `hub-relay-shared-package-proposal.md` = codebase packaging and release strategy
- `hub-relay-repo-structure-proposal.md` = starting repository and code layout
