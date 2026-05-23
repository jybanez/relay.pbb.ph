# UI Chat Thread Virtualization Checklist

## Proposal And Scope

- [x] Draft virtualization proposal for `ui.chat.thread`.
- [x] Draft V1 virtualization spec with explicit scroll rules.
- [x] Confirm the proposed option names and defaults are acceptable.

## Runtime

- [x] Add `enableVirtualization` support to `ui.chat.thread`.
- [x] Add `virtualThreshold` support.
- [x] Add `virtualOverscan` support.
- [x] Add `bottomAnchorThreshold` support.
- [x] Implement measured visible-window rendering.
- [x] Implement top/bottom spacers.
- [x] Preserve append bottom-anchor behavior.
- [x] Preserve prepend reading-position behavior.
- [ ] Re-measure on late-loading media where needed.

## Demo

- [x] Add a dedicated large-thread virtualization demo scenario.
- [x] Add a visible explanation of append vs prepend behavior.
- [x] Document the new virtualization options in the demo reference panel.

## Regression

- [x] Add browser regression coverage for virtualized long-thread rendering.
- [x] Add regression for append while pinned near bottom.
- [x] Add regression for append while reading older messages.
- [x] Add regression for prepend preserving visible position.
- [x] Add regression proving message menus and media opening still work under virtualization.

## Docs

- [x] Update `docs/ui-chat-thread-v1-spec.md`.
- [x] Update `README.md`.
- [x] Update `CHANGELOG.md`.
- [x] Update `docs/pbb-refactor-playbook.md`.
