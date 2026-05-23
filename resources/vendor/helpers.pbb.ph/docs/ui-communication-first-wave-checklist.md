# UI Communication First Wave Checklist

## Scope

- [x] Approve `docs/ui-communication-proposal.md`
- [x] Approve `docs/ui-chat-thread-v1-spec.md`
- [x] Approve `docs/ui-chat-composer-v1-spec.md`
- [x] Limit first-wave runtime to:
  - `ui.chat.thread`
  - `ui.chat.composer`
- [x] Keep realtime transport, upload transport, and call/video runtime out of first-wave implementation

## Runtime

- [x] Add `js/ui/ui.chat.thread.js`
- [x] Add `js/ui/ui.chat.composer.js`
- [x] Add `css/ui/ui.chat.thread.css`
- [x] Add `css/ui/ui.chat.composer.css`
- [x] Keep helper instance shapes stable and explicit
- [x] Support chat-thread empty state
- [x] Support message directions:
  - `incoming`
  - `outgoing`
  - `system`
- [x] Support attachment rendering for:
  - `image`
  - `video`
  - `audio`
  - `file`
- [x] Group image/video attachments through `ui.media.strip`
- [x] Keep audio/file attachments in the listed file group
- [x] Support outgoing state badges:
  - `sending`
  - `sent`
  - `delivered`
  - `read`
  - `failed`
- [x] Support composer send guard for empty/whitespace-only payloads
- [x] Support composer busy/disabled handling
- [x] Support composer `Enter` submit and `Shift+Enter` newline behavior

## Loader And Registry

- [x] Register `ui.chat.thread` in `js/ui/ui.loader.js`
- [x] Register `ui.chat.composer` in `js/ui/ui.loader.js`
- [x] Include both helpers in the public registry groupings

## Demo Surface

- [x] Add `demos/demo.chat.thread.html`
- [x] Add `demos/demo.chat.composer.html`
- [x] Add demo navigation entries in `js/demo/demo.shell.js`
- [x] Add demo index entries in `demos/index.html`
- [x] Show realistic examples for:
  - incoming/outgoing/system messages
  - image/file attachments
  - failed outgoing message
  - empty thread
  - composer busy state
  - attachment trigger

## Docs

- [x] Update `README.md`
- [x] Update `CHANGELOG.md`
- [x] Update `docs/pbb-refactor-playbook.md`

## Regression

- [x] Add `tests/chat.thread.regression.html`
- [x] Add `tests/chat.thread.regression.mjs`
- [x] Verify:
  - thread empty state
  - incoming/outgoing/system rendering
  - attachment rendering
  - outgoing state rendering
  - composer send callback
  - empty-send guard
  - busy-state disabling
  - enter vs shift+enter behavior
  - stable instance methods

## Completion Gate

- [x] Thread and composer runtime are implemented
- [x] Demo pages exist and reflect the approved specs
- [x] Docs match the shipped contract
- [x] Regression coverage passes locally
