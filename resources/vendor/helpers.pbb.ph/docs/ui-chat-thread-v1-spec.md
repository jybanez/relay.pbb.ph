# UI Chat Thread V1 Spec

## Summary

`ui.chat.thread` is the shared helper for rendering a conversation thread.

It should provide a reusable thread surface for:

- incoming and outgoing messages
- sender labels
- timestamps
- delivery/read state
- grouped message runs
- attachments
- empty state

It should remain presentation-focused.

It should not own:

- realtime transport
- backend pagination logic
- unread-count business rules
- delivery polling

## Goals

- give PBB apps a shared conversation-thread UI
- avoid app-local message bubble drift
- support chat plus photo/file sharing
- keep composition compatible with existing media helpers

## Non-Goals

- no socket/WebSocket logic
- no typing-indicator transport
- no message send API
- no conversation directory/list
- no forced virtualization by default

## Related Addenda

- `docs/ui-chat-thread-message-actions-addendum.md`
- `docs/ui-chat-thread-virtualization-v1-spec.md`

## Factory

```js
const thread = createChatThread(container, data, options);
```

## Signature

```ts
createChatThread(
  container: HTMLElement,
  data?: {
    messages?: ChatMessage[];
  },
  options?: ChatThreadOptions
): ChatThreadInstance
```

## Data Shape

### `ChatMessage`

```ts
type ChatMessage = {
  id: string;
  direction: "incoming" | "outgoing" | "system";
  senderName?: string;
  senderSubtitle?: string;
  text?: string;
  timestamp?: string;
  state?: "sending" | "sent" | "delivered" | "read" | "failed";
  attachments?: ChatAttachment[];
  meta?: {
    emphasized?: boolean;
    muted?: boolean;
  };
};
```

### `ChatAttachment`

```ts
type ChatAttachment = {
  id: string;
  kind: "image" | "video" | "audio" | "file";
  name?: string;
  url?: string;
  previewUrl?: string;
  posterUrl?: string;
  sizeLabel?: string;
  mimeType?: string;
};
```

## Options

```ts
type ChatThreadOptions = {
  className?: string;
  emptyTitle?: string;
  emptyText?: string;
  showSenderNames?: boolean;
  showTimestamps?: boolean;
  showStates?: boolean;
  groupAdjacentMessages?: boolean;
  alignOutgoingRight?: boolean;
  showMessageMenuTrigger?: boolean;
  maxMediaThumbsPerMessage?: number;
  mediaStripOptions?: Record<string, unknown>;
  getMessageMenuItems?: (message: ChatMessage, state: { messages: ChatMessage[] }) => MenuItem[];
  messageMenuOptions?: Record<string, unknown>;
  onAttachmentOpen?: (message: ChatMessage, attachment: ChatAttachment) => void;
  onAttachmentDownload?: (message: ChatMessage, attachment: ChatAttachment) => void;
  onMessageAction?: (message: ChatMessage, actionId: string) => void;
  onMessageMenuSelect?: (message: ChatMessage, item: MenuItem, meta: { source: string; index: number }) => void;
};
```

## Defaults

```js
{
  className: "",
  emptyTitle: "No messages yet",
  emptyText: "Start the conversation from the composer below.",
  showSenderNames: true,
  showTimestamps: true,
  showStates: true,
  groupAdjacentMessages: true,
  alignOutgoingRight: true,
  showMessageMenuTrigger: true,
  maxMediaThumbsPerMessage: 4,
  mediaStripOptions: {},
  getMessageMenuItems: null,
  messageMenuOptions: {},
  onAttachmentOpen: null,
  onAttachmentDownload: null,
  onMessageAction: null,
  onMessageMenuSelect: null,
}
```

## Rendering Rules

### Message Directions

- `incoming`
  - left-aligned by default
- `outgoing`
  - right-aligned by default
- `system`
  - centered or full-width muted system row

### Sender Name

- show sender name for incoming messages by default
- outgoing messages may omit repeated self-labels unless app data explicitly includes them

### Timestamp

- render in a compact muted form
- V1 does not own timestamp formatting beyond simple display-safe output
- apps may pre-format timestamps before passing data

### State

For outgoing messages:

- `sending`
- `sent`
- `delivered`
- `read`
- `failed`

System and incoming messages do not need delivery-state chrome by default.

### Adjacent Grouping

When `groupAdjacentMessages` is `true`:

- consecutive messages with the same `direction` and compatible sender context may visually compress
- timestamp and sender labeling may become lighter for the later messages in the group

This is a visual rule only.

### Message actions

- `ui.chat.thread` may render a helper-owned per-message action trigger
- visible only when:
  - `showMessageMenuTrigger` is `true`
  - and `getMessageMenuItems(...)` returns at least one item
- menu content remains app-defined
- selection routes through `onMessageMenuSelect(...)`
- V1 should use the shared menu layer instead of inventing a chat-only menu implementation

## Attachments

V1 attachment support should be narrow.

### Image and video attachments

- group them into one `ui.media.strip` per message
- clicking a strip item opens the shared `ui.media.viewer`
- `onAttachmentOpen(...)` still fires after the built-in open path so apps can log or augment the interaction

### Audio and file attachments

- render as file rows/chips
- support:
  - open
  - download
- actions delegate through the provided callbacks

## Empty State

If there are no messages:

- render helper-owned empty state text
- do not render fake placeholder bubbles

## Methods

### Required instance shape

```ts
type ChatThreadInstance = {
  update(nextData?: { messages?: ChatMessage[] }, nextOptions?: Partial<ChatThreadOptions>): void;
  destroy(): void;
  setMessages(messages: ChatMessage[]): void;
  getMessages(): ChatMessage[];
  getState(): {
    messages: ChatMessage[];
    options: ChatThreadOptions;
  };
};
```

## Accessibility

- message content must remain readable in DOM order
- attachment buttons/links must be keyboard reachable
- system messages should still be semantically readable, not visual-only decorations

## Integration Guidance

Use `ui.chat.thread` for:

- message display
- attachment previews
- conversation history rendering

Do not use it for:

- transport logic
- send pipeline
- upload orchestration

Those stay in app code or adjacent helpers.

## Recommended Composition

Typical composition:

- `ui.chat.thread`
- `ui.chat.composer`
- `ui.file.uploader`
- `ui.media.viewer`

## Demo Expectations

The eventual demo should show:

- incoming/outgoing messages
- grouped runs
- system messages
- image attachments
- file attachments
- failed outgoing message state
- empty thread state
