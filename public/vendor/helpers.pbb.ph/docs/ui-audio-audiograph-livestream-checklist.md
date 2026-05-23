# UI Audio Audiograph Livestream Checklist

## Proposal And Scope

- [x] Review Realtime's livestream proposal.
- [x] Accept source-first livestream support as a real helper gap.
- [x] Keep backward compatibility for `attachAudio(...)`.

## Runtime

- [x] Add `attachMediaStream(stream)`.
- [x] Add `attachAudioNode(node)`.
- [x] Add `resume()`.
- [x] Keep `unlockAudioContext()` as a compatibility alias.
- [x] Support one active attached source at a time.
- [x] Cleanly replace attached sources without destroy/recreate.
- [x] Expose `sourceType` in `getState()`.
- [x] Preserve the existing media-element playback path.

## Demo

- [x] Add a dedicated stream-oriented audiograph demo.
- [x] Include a synthetic stream path that works without microphone permissions.
- [x] Include a microphone path for manual testing.
- [x] Document the new livestream methods in the demo reference panel.

## Regression

- [x] Add regression coverage for `attachMediaStream(...)`.
- [x] Add regression coverage for source replacement.
- [x] Add regression coverage for `attachAudioNode(...)`.

## Docs

- [x] Add livestream addendum.
- [x] Update `README.md`.
- [x] Update `CHANGELOG.md`.
- [x] Update demo/catalog navigation.
