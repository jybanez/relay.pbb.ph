# UI Communication Proposal

## Summary

Add a shared communication UI subsystem for:

- chat and messaging
- photo and file sharing
- audio-call shell controls
- video-call shell surfaces

This should not begin as one large all-in-one communication widget.

The better direction is a small set of reusable helper-owned primitives that can be composed into:

- operator chat
- support/admin messaging
- Workspace-hosted communication panels
- incident/call-side communication tools

## Why This Is Needed

The current helper library already has useful media and audio building blocks:

- `ui.file.uploader`
- `ui.media.viewer`
- `ui.media.strip`
- `ui.audio.player`
- `ui.audio.audiograph`
- `ui.audio.callSession`

But it does not yet provide shared communication UI for:

- message thread rendering
- message composition
- attachment presentation in a messaging flow
- reusable call controls
- video participant layout

Without shared primitives, each app will start inventing:

- message bubbles
- chat composers
- attachment rows
- call controls
- participant tiles

That would create avoidable UI drift.

## Design Direction

Build communication as a subsystem of focused primitives.

Do not start with:

- one giant communication panel helper
- one opinionated chat app shell
- one hard-coded call console

Start with reusable pieces that can be composed by apps.

## Proposed Component Groups

### 1. Messaging

#### `ui.chat.thread`

Shared message thread surface for:

- incoming/outgoing messages
- timestamps
- sender labels
- delivery/read states
- message grouping
- attachments

#### `ui.chat.composer`

Shared message composer surface for:

- text entry
- send action
- attachment picker trigger
- optional disabled/busy state

#### `ui.chat.message`

Optional lower-level message renderer if thread-level composition becomes too rigid.

This does not need to be first-wave unless the thread helper needs a smaller reusable unit.

### 2. Attachments And Media

Prefer composition over reinvention.

Use existing helpers as the base:

- `ui.file.uploader`
- `ui.media.viewer`
- `ui.media.strip`

New communication-specific layer may add:

#### `ui.chat.attachments`

For:

- attachment chips
- photo thumbnail rows
- download/open actions
- upload-pending and failed states

This should compose with the existing uploader/viewer/media-strip helpers instead of replacing them.

#### `ui.chat.upload.queue`

For:

- pending draft attachments selected before send
- media-thumb grouping for image/video
- listed rows for audio/file items
- remove-before-send behavior

This should stay separate from `ui.chat.composer` so the composer owns file picking while the queue owns visual draft attachment state.

### 3. Call Shell

#### `ui.call.controls`

Shared call action surface for:

- mute/unmute
- end call
- hold/resume
- camera toggle
- screen share trigger

This should be reusable across:

- audio-only
- video
- Workspace-hosted call windows

#### `ui.call.status`

Shared call/session status strip for:

- connecting
- connected
- reconnecting
- muted
- recording
- duration

### 4. Video

#### `ui.video.tile`

Single participant tile surface for:

- local/remote video
- avatar fallback
- muted/video-off indicators
- participant label

#### `ui.video.grid`

Shared layout surface for:

- 1-up
- 2-up
- small participant grids
- active-speaker emphasis later if needed

## Recommended First Wave

Start with messaging and photo support first.

### First-wave priority

1. `ui.chat.thread`
2. `ui.chat.composer`
3. `ui.chat.upload.queue`
4. attachment/media composition over:
   - `ui.file.uploader`
   - `ui.media.viewer`
   - `ui.media.strip`

### Why

- chat + photos are broadly reusable
- lower runtime complexity than call/video surfaces
- immediate value across multiple PBB apps

## Recommended Second Wave

### Call shell

1. `ui.call.controls`
2. `ui.call.status`

### Then video

1. `ui.video.tile`
2. `ui.video.grid`

## What Should Stay Out Of Scope Initially

Do not put these into first-wave component scope:

- realtime transport logic
- WebRTC negotiation logic
- socket/message delivery logic
- conversation persistence
- unread-count business rules
- typing-indicator transport rules
- contact directory logic

The helper library should own presentation and interaction contracts.

Applications should still own:

- backend APIs
- realtime transport
- auth/session logic
- conversation state orchestration

## Initial Usage Model

Apps should be able to compose communication UIs like:

- thread view + composer + attachment strip
- Workspace window containing chat thread + media viewer
- video grid + call controls + status strip

That gives flexibility without forcing one app-level layout.

## Styling Direction

Communication UI should feel native to the existing helper system:

- same shell tokens
- same surface hierarchy
- same shared button/input patterns
- same media-viewer integration for photo attachments

Avoid introducing a disconnected “consumer chat app” look.

These should still feel like operational PBB interfaces.

## Proposed Follow-On Docs

After this proposal, the clean next documents are:

1. `ui-chat-thread-v1-spec.md`
2. `ui-chat-composer-v1-spec.md`
3. `ui-communication-roadmap.md`

## Recommendation

Proceed in this order:

1. approve the communication subsystem split
2. implement messaging first
3. reuse existing uploader/media/viewer primitives for attachments
4. add call/video surfaces after the messaging contract is stable
