# UI Chat Thread Message Actions Addendum

## Summary

`ui.chat.thread` should support helper-owned per-message action menus.

This addendum keeps the shared library responsible for:

- rendering the action trigger
- mounting the shared menu surface
- routing the selected action back to app code

It keeps app code responsible for:

- deciding which actions are valid for a given message
- handling the selected action

## Why

Without a shared message-action surface, apps will drift into their own:

- kebab buttons
- hover action rows
- right-click menus

That is exactly the kind of repeated chat UI variation the helper library should absorb.

## Recommended Contract

Add narrow `ui.chat.thread` options:

```ts
type ChatThreadOptions = {
  showMessageMenuTrigger?: boolean;
  getMessageMenuItems?: (message: ChatMessage, state: { messages: ChatMessage[] }) => MenuItem[];
  messageMenuOptions?: Record<string, unknown>;
  onMessageMenuSelect?: (message: ChatMessage, item: MenuItem, meta: { source: string; index: number }) => void;
};
```

## Behavioral Rules

- no trigger when:
  - `showMessageMenuTrigger` is `false`
  - or `getMessageMenuItems(...)` returns no items
- the helper should use the shared menu layer
- the helper should not hardcode business actions such as:
  - delete
  - retry
  - forward
  - pin

Those remain app-owned.

## V1 Scope

V1 should support:

- explicit trigger button per eligible message
- app-defined menu items
- callback on selection

V1 should not yet support:

- right-click custom context interception
- nested submenu trees
- message hover-only action chrome

## Example

```js
const thread = createChatThread(host, { messages }, {
  getMessageMenuItems(message) {
    if (message.direction === "system") {
      return [];
    }
    return [
      { id: "copy", label: "Copy text" },
      { id: "reply", label: "Reply" },
      { id: "delete", label: "Delete", danger: true },
    ];
  },
  onMessageMenuSelect(message, item) {
    console.log("message.menu", message.id, item.id);
  },
});
```
