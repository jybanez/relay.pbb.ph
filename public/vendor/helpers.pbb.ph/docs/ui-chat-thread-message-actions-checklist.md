# UI Chat Thread Message Actions Checklist

## Contract

- [x] document the message-action addendum
- [x] update `docs/ui-chat-thread-v1-spec.md`

## Runtime

- [x] add helper-owned per-message action trigger support to `ui.chat.thread`
- [x] keep menu items app-defined through `getMessageMenuItems(...)`
- [x] route selections through `onMessageMenuSelect(...)`
- [x] keep system messages out of the trigger path unless apps explicitly return actions

## Styling

- [x] add shared message action-trigger styling in `css/ui/ui.chat.thread.css`

## Demo

- [x] update `demos/demo.chat.thread.html` to show message actions

## Regression

- [x] add browser regression coverage for:
  - trigger rendering
  - menu open
  - callback on select

## Docs

- [x] update:
  - `README.md`
  - `CHANGELOG.md`
  - `docs/pbb-refactor-playbook.md`
