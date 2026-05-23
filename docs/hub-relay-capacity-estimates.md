# Hub Relay Capacity Estimates

## Purpose

This document provides presentation-ready capacity estimates for `PBB - Hub Relay Server` based on the current Relay design and implementation in this repository.

These figures are intended for planning and executive presentation use. They are architecture-based estimates, not formal load-test results.

Date prepared: `2026-04-08`

## Executive Summary

Relay should currently be presented as a service that can:

- move **hundreds of thousands of JSON records per day per uplink** in its default reliability-first setup
- move **low millions of JSON records per day per uplink** in a tuned deployment with multiple workers
- transfer large attachments separately using **1 MB chunked uploads**

For presentation purposes, the safest top-line statement is:

> Relay can realistically support **0.17 million to 0.86 million JSON records per day per uplink** in its current default-style setup, and **0.69 million to 3.46 million records per day per uplink** once the worker layer is scaled.

## What The Current Architecture Actually Does

The estimates below are grounded in the current implementation:

- outbound delivery is **one queued delivery job per target hub**
- outbound transport is **one HTTP POST per message per uplink**
- inbound catch-up supports `receive-batch`, but outbound sending is still **single-message-per-request**
- attachments are transferred separately from the JSON envelope
- attachments use **chunked upload**, with a default chunk size of **1 MB**
- the default queue backend is Laravel's **database queue**
- the local development runner uses a single `queue:listen` process

In practice, this means Relay throughput is currently driven more by:

- worker count
- queue backend choice
- per-request latency
- attachment size

and less by raw line speed alone.

## Safe Planning Assumptions

For presentation use, these assumptions are reasonable and defensible:

- a typical JSON relay envelope is about **1 KB to 3 KB**
- a lean record is about **782 bytes**
- a moderate SITREP-style record is about **2,195 bytes**
- the default deployment profile is a **database-backed queue** with conservative worker capacity
- scaled deployment means **multiple workers** and queue/process tuning

## Proposed Presentation Table

Use this table directly in slides or planning decks.

| Scenario | Deployment Profile | Estimated Records / Second / Uplink | Estimated Records / Hour / Uplink | Estimated Records / Day / Uplink | Approx Data / Day at 2 KB per Record |
| --- | --- | ---: | ---: | ---: | ---: |
| Conservative | Default-style setup, database queue, minimal worker concurrency | 2 | 7,200 | 172,800 | 346 MB |
| Expected | Default-style setup under healthy connectivity | 5 | 18,000 | 432,000 | 864 MB |
| Upper Default Range | Default-style setup under favorable latency and stable uplink | 10 | 36,000 | 864,000 | 1.73 GB |
| Scaled | Multiple workers with tuned processing | 8 | 28,800 | 691,200 | 1.38 GB |
| Strong Scaled | Multiple workers with stronger queue throughput | 20 | 72,000 | 1,728,000 | 3.46 GB |
| Aggressive Scaled | Multiple workers with optimized queue and stable network path | 40 | 144,000 | 3,456,000 | 6.91 GB |

## Recommended Slide Language

If the audience is mixed technical and executive, this wording is safe:

- **Current baseline:** `0.17M to 0.86M records/day/uplink`
- **Scaled deployment:** `0.69M to 3.46M records/day/uplink`
- **Typical message size:** `~1 KB to 3 KB JSON envelope`
- **Attachment handling:** `separate 1 MB chunked transfer path`

Short verbal version:

> Relay is designed to move high volumes of structured incident and coordination records efficiently. In practical terms, the current implementation supports hundreds of thousands of records per day per uplink today, and low millions per day when worker capacity is scaled.

## Record Size Estimates

Representative envelope sizing from the current contract:

| Record Type | Approx Size |
| --- | ---: |
| Lean JSON relay envelope | 782 bytes |
| Moderate SITREP-style envelope | 2,195 bytes |
| Safe planning range | 1 KB to 3 KB |

Useful file-size conversion for presentations:

| Data Volume | Approx Record Count at 1 KB | Approx Record Count at 2 KB | Approx Record Count at 3 KB | Approx Record Count at 5 KB |
| --- | ---: | ---: | ---: | ---: |
| 100 MB | 100,000 | 50,000 | 33,000 | 20,000 |
| 500 MB | 500,000 | 250,000 | 166,000 | 100,000 |
| 1 GB | 1,000,000 | 500,000 | 333,000 | 200,000 |
| 5 GB | 5,000,000 | 2,500,000 | 1,666,000 | 1,000,000 |

## Attachment Transfer Estimates

Relay handles attachments outside the message envelope. The default upload chunk size is **1 MB**, so chunk counts are straightforward:

| File Size | Chunks at 1 MB |
| --- | ---: |
| 10 MB | 10 |
| 25 MB | 25 |
| 50 MB | 50 |
| 100 MB | 100 |
| 250 MB | 250 |
| 500 MB | 500 |

Presentation-friendly transfer times by uplink speed:

| Link Speed | 10 MB File | 100 MB File | 500 MB File |
| --- | ---: | ---: | ---: |
| 1 Mbps | 1.5 to 2 min | 14 to 18 min | 70 to 90 min |
| 5 Mbps | 20 to 25 sec | 3 to 4 min | 15 to 18 min |
| 10 Mbps | 8 to 12 sec | 1.5 to 2 min | 7 to 9 min |

## What Limits Throughput Today

The main current limiting factors are:

1. **One outbound request per message**
   Relay does not yet batch outbound transmissions. Each outbound delivery is sent as its own HTTP request.

2. **Queue backend**
   The current default queue is the database driver, which is reliable and simple but not ideal for high-volume worker throughput.

3. **Worker concurrency**
   The baseline developer runner uses a single `queue:listen` path. Actual production throughput depends heavily on how many workers are running.

4. **Network latency**
   Since each outbound message is its own request, per-request round-trip time matters.

5. **Attachment size**
   Large files are practical, but they consume uplink time in direct proportion to file size.

## How To Present This Honestly

Recommended framing:

- These are **architecture-based estimates**
- These are **not yet formal benchmark numbers**
- The current implementation is optimized first for **reliability, traceability, and compatibility**
- Higher throughput is possible with **worker scaling** and a faster queue backend

If you need a one-sentence disclaimer:

> These figures are engineering estimates derived from the current Relay implementation and operating model; formal throughput benchmarks should still be run before publishing contractual performance claims.

## Suggested One-Slide Version

If you want a very compact slide, use:

| Metric | Presentation Value |
| --- | --- |
| Default throughput | `0.17M to 0.86M JSON records/day/uplink` |
| Scaled throughput | `0.69M to 3.46M JSON records/day/uplink` |
| Typical record size | `~1 KB to 3 KB` |
| Transfer model | `1 outbound HTTP request per message` |
| Attachment model | `separate chunked transfer` |
| Default chunk size | `1 MB` |
| Example attachment capacity | `100 MB file in ~1.5 to 18 min depending on link speed` |

## Code References

These estimates were derived from the current implementation, especially:

- outbound delivery job dispatch: [app/Relay/Outbound/RelaySubmissionService.php](C:/wamp64/www/pbb/relay/app/Relay/Outbound/RelaySubmissionService.php)
- outbound HTTP send path: [app/Relay/Transport/RelayHttpSender.php](C:/wamp64/www/pbb/relay/app/Relay/Transport/RelayHttpSender.php)
- inbound batch receive path: [app/Http/Controllers/Api/Relay/Inbound/ReceiveController.php](C:/wamp64/www/pbb/relay/app/Http/Controllers/Api/Relay/Inbound/ReceiveController.php)
- delivery queue config: [config/relay.php](C:/wamp64/www/pbb/relay/config/relay.php)
- default queue backend: [config/queue.php](C:/wamp64/www/pbb/relay/config/queue.php)
- attachment upload flow: [app/Relay/Uploads/RelayUploadService.php](C:/wamp64/www/pbb/relay/app/Relay/Uploads/RelayUploadService.php)
- current environment queue selection: [.env](C:/wamp64/www/pbb/relay/.env)
- deployment note that queue uses database driver: [README.md](C:/wamp64/www/pbb/relay/README.md)
- development runner using `queue:listen`: [composer.json](C:/wamp64/www/pbb/relay/composer.json)

## Final Recommendation

For now, present Relay as:

- a **high-reliability structured record relay**
- capable of **hundreds of thousands to low millions of records per day per uplink**
- with **separate chunked support for large files and evidence attachments**

That framing matches the system's current design without overstating benchmark certainty.
