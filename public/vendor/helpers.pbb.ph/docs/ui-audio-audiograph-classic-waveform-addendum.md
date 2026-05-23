# UI Audio Audiograph Classic Waveform Addendum

## Summary

`ui.audio.audiograph` now supports a new visual style:

- `style: "classic-waveform"`

This is a live waveform-inspired renderer for compact audio surfaces.

It is intentionally:

- live
- compact
- non-timeline

It does **not** turn `ui.audio.audiograph` into a full history/timeline waveform component.

## Intended Use

Use `classic-waveform` when you want:

- a centered mirrored waveform look
- darker monitor-style analysis visuals
- a more studio-inspired live graph treatment

Use it for:

- local microphone activity
- remote stream activity
- call/session cards
- compact operator audio surfaces

## Boundary

`classic-waveform` remains part of `ui.audio.audiograph`.

It does **not** add:

- timeline history
- playback scrubber behavior
- waveform editing
- clip review semantics

If the library later needs a true waveform timeline helper, that should remain a separate component.

## Example

```js
const graph = createAudioGraph(host, {
  role: "local-audio",
  roleLabel: "Local audio",
  isPlaying: true,
  isLive: true,
  isActive: true,
}, {
  style: "classic-waveform",
  overlayHeader: false,
  transparentBackground: true,
  showMute: false,
});

await graph.resume();
graph.attachMediaStream(localStream);
```
