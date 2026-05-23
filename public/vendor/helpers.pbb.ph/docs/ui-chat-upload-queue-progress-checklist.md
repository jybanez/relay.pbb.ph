# UI Chat Upload Queue Progress Checklist

## Contract

- [x] draft upload-queue progress addendum
- [x] extend `docs/ui-chat-upload-queue-v1-spec.md`

## Runtime

- [x] support queue item visual states:
  - `queued`
  - `uploading`
  - `uploaded`
  - `failed`
- [x] support queue item fields:
  - `progress`
  - `progressLabel`
  - `errorText`

## Styling

- [x] add progress and status styling for:
  - media groups
  - file/audio rows

## Demo

- [x] update `demos/demo.chat.upload.queue.html` with uploading and failed examples

## Regression

- [x] add browser regression coverage for:
  - progress rendering
  - failed-state error text

## Docs

- [x] update:
  - `README.md`
  - `CHANGELOG.md`
  - `docs/pbb-refactor-playbook.md`
