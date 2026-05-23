# Workspace Bridge Cross-Origin Demo Harness

## Purpose

Provide a local two-origin browser harness inside the helper repo so cross-origin Workspace bridge changes can be tested here before downstream app teams spend time validating them in live repos.

## Why This Exists

The original bridge demo and regression only proved:

- same-origin parent overlay routing
- explicit bridge mechanics inside one origin

That was not enough to catch deployment-shaped issues such as:

- cross-origin owner-title gaps
- duplicate child fallback modals
- lost bridged account footer actions

## Local Origins

The harness uses two local origins by port:

- parent Workspace host:
  - `http://127.0.0.1:4173`
- child iframe app:
  - `http://127.0.0.1:4174`

Different ports are different browser origins, so this reproduces the same cross-origin boundary that exists between products such as Workspace and HQ.

## Manual Demo

Start the local harness:

```bash
node scripts/run-workspace-bridge-cross-origin-demo.mjs
```

Then open the printed parent URL, for example:

```text
http://127.0.0.1:4173/demos/demo.workspace.bridge.cross.origin.html?childOrigin=http%3A%2F%2F127.0.0.1%3A4174
```

Files:

- parent demo:
  - `demos/demo.workspace.bridge.cross.origin.html`
- child demo:
  - `samples/iframe/workspace-ui-bridge.cross-origin.child.html`

## Automated Regression

Run:

```bash
node tests/workspace.bridge.cross.origin.regression.mjs
```

This starts both local origins automatically, opens the parent regression page in a headless browser, and verifies the real cross-origin bridge path.

Files:

- parent regression:
  - `tests/workspace.bridge.cross.origin.regression.html`
- node launcher:
  - `tests/workspace.bridge.cross.origin.regression.mjs`
- local static server utility:
  - `tests/_support/static-server.mjs`

## Current Assertions

The automated cross-origin regression currently proves:

- child readiness across origins
- bridged login renders in the parent document
- owner-title subtitle is inferred from the child document title
- no local child login modal remains visible
- bridged account renders in the parent document
- bridged account preserves serialized `Change Password` footer action
- account extra-action round-trip returns the correct `actionId`
- no local child account modal remains visible

## Practical Rule

Before rolling out a cross-origin bridge change to Workspace, HQ, or any other app team:

1. validate it in this harness first
2. run the automated cross-origin regression
3. only then ask downstream repos to refresh vendored helper copies
