# UI Chat Upload Queue Progress Addendum

## Summary

`ui.chat.upload.queue` should support visual per-item upload state.

This keeps the queue helper responsible for:

- rendering upload progress
- rendering visual status
- rendering upload error text

It keeps app code responsible for:

- actual upload transport
- retries
- cancel behavior
- backend mapping

## Why

Once attachments are selected, apps need a shared way to show:

- queued
- uploading
- uploaded
- failed

Without this, each app will invent its own:

- progress rows
- status chips
- error lines

## Recommended Data Extension

Extend queue items with:

```ts
type ChatUploadQueueItem = {
  status?: "queued" | "uploading" | "uploaded" | "failed";
  progress?: number;
  progressLabel?: string;
  errorText?: string;
};
```

## Rendering Rules

### Media items

- keep image/video grouped through `ui.media.strip`
- render status/progress below the media strip item group as compact rows

### Audio / file items

- keep file-row presentation
- render progress bar and error text inside the main file block

## Boundary

This addendum is **visual state only**.

It does not make `ui.chat.upload.queue` responsible for:

- starting uploads
- retry orchestration
- upload session state machines

## Example

```js
queue.setItems([
  {
    id: "file1",
    kind: "file",
    name: "route-plan.pdf",
    sizeLabel: "1.2 MB",
    status: "uploading",
    progress: 64,
    progressLabel: "64%",
  },
  {
    id: "file2",
    kind: "audio",
    name: "radio-note.mp3",
    sizeLabel: "220 KB",
    status: "failed",
    errorText: "Upload failed. Retry from the app flow.",
  },
]);
```
