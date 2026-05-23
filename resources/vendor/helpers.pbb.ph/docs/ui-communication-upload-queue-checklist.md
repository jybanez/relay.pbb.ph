# UI Communication Upload Queue Checklist

## Scope

- [x] Extend `ui.chat.composer` with helper-owned native file picking
- [x] Add separate `ui.chat.upload.queue` helper for pending draft attachments
- [x] Keep upload transport and backend submission out of scope

## Composer Runtime

- [x] Add hidden native `<input type="file">`
- [x] Support:
  - `accept`
  - `multiple`
  - `capture`
- [x] Replace `onAttachmentClick` with:
  - `onFilesSelected(files, meta)`
- [x] Clear the native file input value after file selection so the same file can be reselected later
- [x] Keep attachment trigger disabled during composer busy/disabled state

## Upload Queue Runtime

- [x] Add `js/ui/ui.chat.upload.queue.js`
- [x] Add `css/ui/ui.chat.upload.queue.css`
- [x] Support queue items for:
  - `image`
  - `video`
  - `audio`
  - `file`
- [x] Group image/video through `ui.media.strip`
- [x] Keep audio/file in listed rows
- [x] Support remove action per queue item
- [x] Support empty-hidden default

## Loader And Registry

- [x] Register `ui.chat.upload.queue` in `js/ui/ui.loader.js`
- [x] Include it in the `communication` group

## Demo Surface

- [x] Update `demos/demo.chat.composer.html` to prove native file picking behavior
- [x] Add `demos/demo.chat.upload.queue.html`
- [x] Add demo-shell and index entries
- [x] Show queue items with:
  - image/video media strip
  - audio/file rows
  - remove action

## Docs

- [x] Update `README.md`
- [x] Update `CHANGELOG.md`
- [x] Update `docs/pbb-refactor-playbook.md`

## Regression

- [x] Extend chat regression to verify:
  - composer exposes file input
  - selected files reach `onFilesSelected`
  - queue renders image/video media strip
  - queue renders audio/file rows
  - remove action fires

## Completion Gate

- [x] Native file picking is helper-owned in the composer
- [x] Pending draft attachments have a separate shared queue helper
- [x] Regression passes locally
