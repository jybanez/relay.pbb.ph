# UI Audio Audiograph Livestream Addendum

## Summary

`ui.audio.audiograph` now supports live audio-source attachment in addition to its existing media-element playback path.

This addendum exists because playback-oriented `attachAudio(HTMLMediaElement)` was not enough for:

- local microphone monitoring
- remote WebRTC audio visualization
- call/session UIs
- source replacement during renegotiation

## New Runtime Surface

`ui.audio.audiograph` now supports:

- `attachMediaStream(stream)`
- `attachAudioNode(node)`
- `resume()`

Existing compatibility surface remains:

- `attachAudio(mediaElement)`
- `unlockAudioContext()`

`unlockAudioContext()` remains as a compatibility alias to `resume()`.

## Visual Styles

The component also supports a live waveform-inspired visual style:

- `style: "classic-waveform"`

This style is still:

- live
- compact
- non-timeline

It exists to provide a more classic centered waveform look without changing the component into a history-based waveform timeline.

## Source Model

Only one active source is attached at a time:

- `media-element`
- `media-stream`
- `audio-node`

Attaching a new source replaces the previous source cleanly without requiring destroy/recreate.

## Intended Usage

### Media element

Use when the graph follows helper-owned playback surfaces such as:

- `ui.audio.callSession`
- `ui.media.viewer`

### Media stream

Use when the graph should follow live browser/WebRTC streams such as:

- local microphone tracks
- remote participant audio

### Audio node

Use when the app already owns a Web Audio graph and wants the helper to visualize an existing node.

## Live-Stream State

For live sources, apps may set:

- `isLive: true`
- `isActive: true`

The graph no longer needs file-duration semantics to behave meaningfully for live audio.

## Mute Boundary

`setMuted(...)` remains a helper/UI state toggle.

For media-element sources, the helper still updates the bound element's `muted` flag.

For media-stream and audio-node sources, the helper does not mutate track enablement or call mute policy. App code still owns the actual microphone/call mute contract.

## Debug State

`getState()` now includes:

- `sourceType`

Where `sourceType` is one of:

- `"none"`
- `"media-element"`
- `"media-stream"`
- `"audio-node"`
