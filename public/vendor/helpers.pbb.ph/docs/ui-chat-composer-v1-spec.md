# UI Chat Composer V1 Spec

## Summary

`ui.chat.composer` is the shared helper for composing a message before send.

It should provide:

- text entry
- send trigger
- native file-picker trigger
- disabled/busy state
- optional helper text

It should not own:

- actual message sending
- upload queue rendering
- upload transport
- retry logic
- draft persistence

## Goals

- give PBB apps a shared message-composer UI
- keep send/attachment controls visually consistent
- own the basic native file-pick interaction for message attachments
- compose cleanly with `ui.chat.thread`

## Non-Goals

- no transport logic
- no upload implementation
- no visual upload queue in this helper
- no emoji picker in V1
- no mentions/autocomplete in V1
- no voice-note recorder in V1

## Factory

```js
const composer = createChatComposer(container, data, options);
```

## Signature

```ts
createChatComposer(
  container: HTMLElement,
  data?: {
    value?: string;
  },
  options?: ChatComposerOptions
): ChatComposerInstance
```

## Options

```ts
type ChatComposerOptions = {
  className?: string;
  placeholder?: string;
  helperText?: string;
  disabled?: boolean;
  busy?: boolean;
  sendLabel?: string;
  attachmentLabel?: string;
  showAttachmentButton?: boolean;
  accept?: string;
  multiple?: boolean;
  capture?: string | null;
  maxLength?: number | null;
  multiline?: boolean;
  submitOnEnter?: boolean;
  onChange?: (value: string) => void;
  onSend?: (payload: { text: string }) => void | Promise<void>;
  onFilesSelected?: (files: File[], meta: { source: "picker" }) => void;
};
```

## Defaults

```js
{
  className: "",
  placeholder: "Type a message...",
  helperText: "",
  disabled: false,
  busy: false,
  sendLabel: "Send",
  attachmentLabel: "Attach",
  showAttachmentButton: true,
  accept: "",
  multiple: true,
  capture: null,
  maxLength: null,
  multiline: true,
  submitOnEnter: true,
  onChange: null,
  onSend: null,
  onFilesSelected: null,
}
```

## Rendering Rules

### Input

- use a text area by default in V1
- keep the surface compact enough for operational shells
- if `multiline` is `false`, a single-line input is acceptable

### Send action

- send button should be visible and explicit
- if `busy` is `true`, send action should be disabled
- if the composer is empty/whitespace-only, send should not trigger

### Attachment action

- only render when `showAttachmentButton` is `true`
- clicking opens a hidden native `<input type="file">`
- selected files return through `onFilesSelected(files, meta)`
- V1 does not own upload UI or queue rendering by itself

### Helper text

- optional muted line below the composer controls
- good for:
  - upload hints
  - keyboard hints
  - policy reminders

## Input Behavior

### Change

- every value change should call `onChange(value)` when provided

### Submit

If `submitOnEnter` is `true`:

- `Enter` sends
- `Shift+Enter` inserts newline

If `submitOnEnter` is `false`:

- `Enter` should behave as normal text entry
- send only through the explicit button

### Busy state

When `busy` is `true`:

- input remains readable
- send button is disabled
- attachment button is disabled
- helper may optionally apply a shared busy visual treatment

### File picker

- the hidden file input is helper-owned
- the helper should support:
  - `accept`
  - `multiple`
  - optional `capture`
- the helper should return selected files as plain `File[]`
- the helper should clear the native input value after handling selection so the same file can be re-picked later if needed

## Methods

```ts
type ChatComposerInstance = {
  update(nextData?: { value?: string }, nextOptions?: Partial<ChatComposerOptions>): void;
  destroy(): void;
  setValue(value: string): void;
  getValue(): string;
  clear(): void;
  focus(): void;
  setBusy(busy: boolean): void;
  getState(): {
    value: string;
    options: ChatComposerOptions;
  };
};
```

## Send Contract

The helper should emit plain data only.

```ts
onSend?.({
  text: string,
});
```

V1 does not include attachment payloads in `onSend`.

Attachment selection is helper-owned, but queue/orchestration should stay app-owned or use adjacent helpers such as a future `ui.chat.upload.queue`.

## Accessibility

- composer input must be keyboard reachable
- send and attachment controls must be keyboard reachable
- disabled and busy states must remain visually and semantically clear

## Integration Guidance

Typical app-owned send flow:

1. read the outgoing text from `onSend`
2. set composer busy
3. send through backend or realtime transport
4. clear composer on success
5. unset busy

The helper should not try to own this lifecycle automatically.

## Recommended Composition

Use together with:

- `ui.chat.thread`
- `ui.chat.upload.queue` later
- `ui.file.uploader`
- `ui.media.viewer`

## Demo Expectations

The eventual demo should show:

- simple send
- empty-message guard
- busy state
- native file picker trigger
- multiline behavior
- enter vs shift+enter behavior
