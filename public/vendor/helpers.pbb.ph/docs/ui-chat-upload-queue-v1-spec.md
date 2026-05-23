# UI Chat Upload Queue V1 Spec

## Summary

`ui.chat.upload.queue` is the shared helper for showing files selected for a pending chat/message draft before send.

It should provide:

- visual list of selected attachments
- media thumbnails for image/video
- listed rows for audio/file items
- remove action per item

It should remain draft-focused.

It should not own:

- upload transport
- background upload
- chunking/retry logic
- backend submission

## Goals

- give apps a shared pending-attachment queue for chat/message drafts
- avoid app-local upload-chip/file-preview drift
- pair cleanly with `ui.chat.composer`

## Non-Goals

- no actual upload engine
- no transport progress in V1
- no retry/cancel state machine in V1
- no drag-reorder in V1

## Factory

```js
const queue = createChatUploadQueue(container, data, options);
```

## Signature

```ts
createChatUploadQueue(
  container: HTMLElement,
  data?: {
    items?: ChatUploadQueueItem[];
  },
  options?: ChatUploadQueueOptions
): ChatUploadQueueInstance
```

## Data Shape

```ts
type ChatUploadQueueItem = {
  id: string;
  kind: "image" | "video" | "audio" | "file";
  name: string;
  sizeLabel?: string;
  previewUrl?: string;
  status?: "queued" | "uploading" | "uploaded" | "failed";
  progress?: number;
  progressLabel?: string;
  errorText?: string;
  file?: File | null;
};
```

## Options

```ts
type ChatUploadQueueOptions = {
  className?: string;
  emptyHidden?: boolean;
  maxMediaThumbs?: number;
  onRemove?: (item: ChatUploadQueueItem) => void;
  onOpen?: (item: ChatUploadQueueItem) => void;
};
```

## Defaults

```js
{
  className: "",
  emptyHidden: true,
  maxMediaThumbs: 8,
  onRemove: null,
  onOpen: null,
}
```

## Rendering Rules

### Image / video

- group through `ui.media.strip`
- clicking an item may open the shared media viewer
- render compact upload state below the media group when items carry progress/status

### Audio / file

- render as listed rows
- each row may show:
  - name
  - size label
  - progress
  - error text
  - remove action

## Methods

```ts
type ChatUploadQueueInstance = {
  update(nextData?: { items?: ChatUploadQueueItem[] }, nextOptions?: Partial<ChatUploadQueueOptions>): void;
  destroy(): void;
  setItems(items: ChatUploadQueueItem[]): void;
  getItems(): ChatUploadQueueItem[];
  getState(): {
    items: ChatUploadQueueItem[];
    options: ChatUploadQueueOptions;
  };
};
```

## Integration Guidance

Recommended draft flow:

1. `ui.chat.composer` opens native file picker
2. app receives selected files
3. app normalizes them into queue items
4. `ui.chat.upload.queue` renders them
5. app sends:
   - composer text
   - queue items/files

## Demo Expectations

The eventual demo should show:

- image/video queue previews
- audio/file queued rows
- remove action
- empty hidden/default state
