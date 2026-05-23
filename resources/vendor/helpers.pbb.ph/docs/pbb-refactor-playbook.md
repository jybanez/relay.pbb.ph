# PBB Refactor Playbook

This guide is for engineers integrating this library into `*.pbb.ph` projects without breaking behavior.

## 1) Source Of Truth

- API and behavior contracts live in:
  - `README.md` (public contract)
  - `CHANGELOG.md` (release history)
  - `js/incident/*.js` (runtime contract)
  - `js/ui/*.js` (shared UI contract)
- If docs and implementation differ, implementation currently wins. Update docs in the same change.

## 2) Non-Negotiable Helper Contract

Every helper must keep:

- Signature: `(container, data, options)`
- Stable return shape:
  - `destroy()`
  - `update(nextData, nextOptions?)`
- List helpers additionally expose:
  - `setList(items[])`
  - `getData()`
  - `getState()`

Do not remove or rename these methods in refactors.

## 3) Error Handling Rules

Required option missing behavior must remain:

- `console.error(...)`
- render nothing for that instance
- still return stable API object

Validation is per-instance, never global.

## 4) Refactor Order (Safe Sequence)

1. Move repeated UI behavior into `js/ui/*` first.
2. Apply utilities to one incident helper.
3. Validate demo pages.
4. Apply to second helper.
5. Update `README.md` + this playbook in same commit.

Avoid simultaneous behavior changes and structural refactors in one PR.

## 4.1) Validation Depth

- Syntax checks and registry checks are baseline only.
- If a change touches render-path behavior in a DOM-heavy component, add or update a targeted browser-rendered regression harness when practical.
- Current explicit example:
  - `tests/tree.grid.regression.mjs`
  - this protects `ui.tree.grid` initial render, search filtering, empty-search state, and recovery path
- Current explicit example:
  - `tests/modal.busy.regression.mjs`
  - this protects `ui.modal` busy-state transitions, runtime open-state class preservation, and hidden close-button behavior during `setBusy(...)`

## 5) Integration Pattern In pbb.ph Projects

Use adapter functions between helper callbacks and backend APIs.

- Keep helpers UI-focused.
- Keep API payload mapping outside helpers.
- App integrations should use `uiLoader` by registry key.
- Direct path imports from `js/ui/*` and `js/incident/*` are internal-library usage, not app integration usage.
- Before building app-local UI, check whether the helper library already provides the needed surface as:
  - a shared component
  - a preset wrapper
  - a shared styling primitive
  - a shared layout/shell primitive
- Prefer helper-owned workflows before hand-built project flows for repeated operational UI such as:
  - `createFormModal(...)`
  - `createLoginFormModal(...)`
- `createReauthFormModal(...)`
- `createAccountFormModal(...)`
- `createChangePasswordFormModal(...)`
- `createPasswordField(...)`
  - `createDevicePrimer(...)`
  - `createFieldset(...)`
  - `createChatThread(...)`
  - `createChatComposer(...)`
  - `createChatUploadQueue(...)`
  - `createStatusUpdateFormModal(...)`
  - `createReasonFormModal(...)`
  - `uiAlert(...)`, `uiConfirm(...)`, `uiPrompt(...)`
  - `createToastStack(...)`
  - `createMediaViewer(...)`
  - `createHierarchyMap(...)`
  - `createTreeGrid(...)`
- Before adding project-local UI overrides, check whether the shared primitive layer already exposes a suitable contract:
  - button variants in `ui.components.css`
  - shell/layout primitives such as `ui-panel`, `ui-surface`, `ui-field`, `ui-label`, `ui-badge`, `ui-eyebrow`, `ui-shell-header`, `ui-shell-search`
- Prefer shared variants first. Only add project-local overrides when the library contract is genuinely insufficient for the use case.
- If a project needs the same override more than once, raise it back into the shared library instead of duplicating CSS across `*.pbb.ph` apps.

Session and re-auth rule:

- `createReauthFormModal(...)` owns the re-auth UI only.
- Session-expiry detection remains app-owned.
- Detect expiry through app contracts such as:
  - `401` / `419` responses
  - an app-owned idle timer
  - an explicit backend session-expired response shape
- Keep one reusable re-auth modal instance near the authenticated app shell instead of creating separate instances per page or action.
- When expiry is detected:
  - block or defer the protected action
  - open the shared re-auth modal
  - on successful re-auth, retry or resume the blocked action if the project flow supports it
- Do not embed timeout polling or session-watch logic inside the helper wrapper itself.
- For password entry outside those wrappers, prefer the shared `createPasswordField(...)` primitive so teams reuse the same show/hide behavior instead of building project-local toggles.
- When a form modal needs a third footer action such as `Change Password`, prefer the helper-owned `extraActions` option on `createFormModal(...)` instead of trying to replace the full footer action set from app code.

## 5.1) Helper-First And Proposal-First Rules

- Engineers integrating `*.pbb.ph` projects should maximize use of documented helper components, wrappers, and shared primitives before introducing app-local UI.
- If the helper library is close but missing a repeated capability, do not directly extend the shared helper from project work as an ad hoc fix.
- Instead, submit a proposal or spec update first. The proposal should state:
  - the repeated use case
  - why the current helper contract is insufficient
  - the narrowest shared API or styling change that solves it
  - expected demo and regression coverage
- Repeated project-local workarounds are a signal to submit a helper proposal, not a signal to silently fork shared behavior inside an app.
- App teams may compose existing helper APIs, map payloads, and configure options. They should not redefine shared helper contracts unilaterally from application code.
- Helper-library changes should remain proposal-driven so maintainers can review:
  - cross-project reuse
  - naming consistency
  - contract boundaries
  - regression/test impact

Example adapter boundary:

- Helper emits: `onStatusNext(assignmentId, toStatus)`
- App adapter transforms to API payload and calls endpoint.
- API response normalized and fed back through `setList(...)`/`update(...)`.

## 6) Data Normalization Rule

Normalize API data before passing to helpers.

- Team assignments: ensure `team`, `team.category`, `team.resources` shape.
- Incident types: ensure `fields`, `detail_entries`, `resources`, `resources_needed`.
- Incident custom field groups: keep the field definition as `type: "group"` or `input_type: "group"` with child `fields[]`; store submitted non-repeatable group values as JSON object strings and repeatable group values as JSON array strings in `detail_entries[].field_value`.
- Non-incident grouped forms: use `createFieldGroup(...)` directly or `createFieldset(...)` rows with `type: "group"`; keep values as objects for non-repeatable groups and arrays of objects for repeatable groups.
- Common grouped schemas: prefer `fieldGroupPresets.person()`, `address()`, `missingPerson()`, or `evacuee()` before hand-authoring repeated field lists.

Never add backend-specific quirks inside helper rendering logic.

## 7) Current Known Pitfalls

1. Event cleanup regressions:
   - Always use `createEventBag()` for listeners in list/drawer flows.
2. Drawer regressions:
   - Keep `onOpenDrawer` / `onCloseDrawer` firing behavior.
3. Resource rendering confusion:
   - Incident type resource section is shown from `resources[]`, quantity resolved via `resources_needed[]` fallback `0`.
4. Focus/scroll jumps:
   - Keep keyed reconciliation and anchor restoration in list helpers.
5. Audio autoplay/context warnings:
   - Keep audio context unlock logic tied to user gesture (`Play`) path.
6. Audio timeline mismatches:
   - Prefer `incident.call_duration_seconds` for total session duration when present.
   - Keep per-segment timestamp positioning from `recording_role`.
7. Virtualized grid jank:
   - Row-level listeners must be cleaned between virtual window renders.
   - Avoid full DOM rebuild when visible start/end window did not change.
8. Menu alignment regressions:
   - Use centralized alignment/placement resolution in menu core (`ui.menu`), not only wrapper helpers.
9. Sidebar animation loss:
   - Avoid full rerender on collapse toggle; prefer in-place class/state updates to preserve transitions.
10. Modal async safety regressions:
   - Preserve helper-owned busy-state handling in `createModal(...)` / `createActionModal(...)`.
- When updating modal options while open, preserve runtime root state classes (`is-mounted`, `is-open`, `is-closing`). Option updates must not visually dismiss an open modal unless `close()` is actually invoked.
- Hidden modal controls such as the close button must be hidden by shared CSS as well as DOM attributes; do not rely on `[hidden]` alone when author styles set `display` explicitly.
   - Do not reintroduce app-local duplicate-submit guards when the shared modal contract can own:
     - overlay lock
     - control disabling
     - close suppression while busy
11. Hosted select clipping regressions:
   - `ui.select` menus used inside modals, drawers, or other overflow containers must render in a floating layer instead of the local clipped container.
   - Do not solve hosted-select clipping with per-app z-index or overflow overrides. Fix it in the shared helper so placement works across shells.
12. Hidden-field layout regressions in `ui.form.modal`:
   - Hidden fields are payload concerns, not layout concerns.
   - Rows with no visible renderable items must not produce visible spacing.
   - Hidden-only fields should render through a non-visual container while still participating in helper values and submit payloads.
13. Hierarchy/demo data drift:
  - When a demo is backed by real dataset extracts, keep the extractor script beside the sample payload.
  - For `ui.hierarchy.map`, `samples/samplehierarchy_cebu.json` should be regenerated via `scripts/generate.hierarchy.sample.ps1` instead of being hand-edited when the data contract changes.

## 8) UI Utility Ownership

Shared UI layer:

- `js/ui/ui.dom.js`
- `js/ui/ui.events.js`
- `js/ui/ui.drawer.js`
- `js/ui/ui.search.js`
- `js/ui/ui.modal.js`
- `js/ui/ui.dialog.js`
- `js/ui/ui.toast.js`
- `js/ui/ui.workspace.bridge.js`
- `js/ui/ui.fieldset.js`
- `js/ui/ui.property.editor.js`
- `js/ui/ui.chat.thread.js`
- `js/ui/ui.chat.composer.js`
- `js/ui/ui.chat.upload.queue.js`
- `docs/ui-chat-thread-virtualization-v1-spec.md`
- `js/ui/ui.select.js`
- `js/ui/ui.toggle.button.js`
- `js/ui/ui.toggle.group.js`
- `js/ui/ui.datepicker.js`
- `js/ui/ui.timeline.js`
- `js/ui/ui.timeline.scrubber.js`
- `js/ui/ui.command.palette.js`
- `js/ui/ui.tree.js`
- `js/ui/ui.kanban.js`
- `js/ui/ui.file.uploader.js`
- `js/ui/ui.tabs.js`
- `js/ui/ui.strips.js`
- `js/ui/ui.media.strip.js`
- `js/ui/ui.media.viewer.js`
- `js/ui/ui.grid.js`
- `js/ui/ui.tree.grid.js`
- `js/ui/ui.hierarchy.map.js`
- `js/ui/ui.progress.js`
- `js/ui/ui.virtual.list.js`
- `js/ui/ui.scheduler.js`

## 8.1) Shared Styling First

- Treat `css/ui/ui.components.css` as the first styling surface for shared app chrome.
- Existing button variants should be considered before custom button CSS:
  - `.ui-button-primary`
  - `.ui-button-ghost`
  - `.ui-button-borderless`
  - `.ui-button-quiet`
  - `.ui-button-link`
  - `.ui-button-icon`
- Dense grid/tree-grid/list action cells should prefer:
  - `.ui-cell-actions`
  - `.ui-cell-action`
  - combined with shared button classes such as `.ui-button`, `.ui-button-icon`, `.ui-button-borderless`, `.ui-button-danger`
- Inline validation/auth errors should prefer `.ui-form-error` before introducing local error text styles in forms or modal workflows
- The goal is to keep visual behavior consistent across `*.pbb.ph` projects and avoid local style drift.
- If a project cannot use an existing variant cleanly, document the gap and promote a new shared variant instead of normalizing on ad hoc overrides.
- `js/ui/ui.menu.js`
- `js/ui/ui.dropdown.js`
- `js/ui/ui.dropup.js`
- `js/ui/ui.navbar.js`
- `js/ui/ui.sidebar.js`
- `js/ui/ui.breadcrumbs.js`
- `js/ui/ui.audio.player.js`
- `js/ui/ui.audio.audiograph.js`
- `js/ui/ui.audio.callSession.js`

Shared CSS:

- `css/ui/ui.tokens.css`
- `css/ui/ui.components.css`
- `css/ui/ui.modal.css`
- `css/ui/ui.dialog.css`
- `css/ui/ui.toast.css`
- `css/ui/ui.select.css`
- `css/ui/ui.toggle.css`
- `css/ui/ui.datepicker.css`
- `css/ui/ui.timeline.css`
- `css/ui/ui.timeline.scrubber.css`
- `css/ui/ui.command.palette.css`
- `css/ui/ui.tree.css`
- `css/ui/ui.kanban.css`
- `css/ui/ui.file.uploader.css`
- `css/ui/ui.tabs.css`
- `css/ui/ui.strips.css`
- `css/ui/ui.media.strip.css`
- `css/ui/ui.media.viewer.css`
- `css/ui/ui.grid.css`
- `css/ui/ui.tree.grid.css`
- `css/ui/ui.progress.css`
- `css/ui/ui.virtual.list.css`
- `css/ui/ui.scheduler.css`
- `css/ui/ui.nav.css`
- `css/ui/ui.audio.css`

Rule: add generic behavior to `ui/*`; keep incident domain logic in `incident/*`.

## 9) Before Merging Any Refactor

Run this checklist:

1. All demo pages load and interact correctly:
   - `demos/index.html`
   - `demos/demo.team.assignments.html`
   - `demos/demo.incident.types.html`
   - `demos/demo.grid.html`
   - `demos/demo.tree.grid.html`
   - `demos/demo.hierarchy.map.html`
   - `demos/demo.progress.html`
   - `demos/demo.virtual.list.html`
   - `demos/demo.scheduler.html`
   - `demos/demo.timeline.html`
   - `demos/demo.ui.html`
   - `demos/demo.toast.html`
   - `demos/demo.select.html`
   - `demos/demo.fieldset.html`
   - `demos/demo.toggle.button.html`
   - `demos/demo.toggle.group.html`
   - `demos/demo.buttons.html`
   - `demos/demo.audio.html`
   - `demos/demo.media.viewer.html`
   - `demos/demo.nav.html`
   - `demos/demo.navbar.html`
   - `demos/demo.sidebar.html`
   - `demos/demo.breadcrumbs.html`
   - `demos/demo.dropdown.html`
   - `demos/demo.dropup.html`
   - `demos/demo.stepper.html`
   - `demos/demo.splitter.html`
   - `demos/demo.inspector.html`
   - `demos/demo.empty.state.html`
   - `demos/demo.skeleton.html`
   - `demos/demo.form.modal.html`
2. No console errors in normal demo flow.
3. Required-option behavior still matches contract.
4. `getData()` output shape unchanged for touched helpers.
5. README + this playbook updated if API/behavior changed.

## 10) Versioning Guidance

- Patch (`x.y.Z`): CSS/internal cleanup, no contract changes.
- Minor (`x.Y.z`): new optional APIs/utilities/components.
- Major (`X.y.z`): contract or behavior-breaking changes.
- Keep `README.md` release notes as the canonical component-version history; avoid per-component ad-hoc version labels.
- If a change reshapes app integration expectations, treat it as contract-sensitive even if it is additive.

If changing callback signatures or removing methods, plan a major version.

## 11) New Utilities (Recent Additions)

### 11.0 Registry Loader (`ui.loader`)

- `js/ui/ui.loader.js` is now the preferred integration entry point for browser projects that want CSS + JS loading managed together.
- Preferred usage:
  - `await uiLoader.load("ui.modal")` to ensure styles are present
  - `const createModal = await uiLoader.get("ui.modal")` to resolve the exported factory
  - `const modal = await uiLoader.create("ui.modal", options)` when direct instantiation is preferred
- Keep registry keys stable once documented because app integrations may reference them directly.
- Loader behavior to preserve:
  - deduplicated stylesheet injection
  - dynamic import by registry key
  - export-aware resolution by registry key
  - grouped loading support
  - failure diagnostics for CSS/module loading
  - support for both `ui.*` and `incident.*` component namespaces
- Wrapper-style components may expose `chrome: false` to disable library-owned border/background/padding without changing behavior.
- Only add `chrome: false` when the component actually owns a distinct outer shell. Do not add no-op chrome flags to components whose visuals are entirely internal.
- Tree-style components may expose lazy child loading via `lazyLoadChildren(...)` plus explicit `loadChildren(...)` / `refreshChildren(...)` instance methods; treat these as part of the component contract once used by apps.
- Demo pages should prefer `uiLoader` as well, so working demos remain the reference implementation for project integrations.
- Registry changes should ship with a contract test update in `tests/registry.contract.mjs`.

### 11.0a Accessibility Baseline

- Wrapper/data primitives should expose `ariaLabel` when they render a user-visible region, panel, list, or inspector shell.
- Prefer low-risk semantic improvements first:
  - `role="region"` with `aria-label` for framed shells
  - `role="list"` / `role="listitem"` for simple virtualized list primitives
- For interactive overlays and menus, preserve and test:
  - title/label wiring (`aria-labelledby` or `aria-label`)
  - trigger state attributes (`aria-expanded`, `aria-controls`, `aria-haspopup`) where applicable
  - active descendant wiring for composite widgets where the trigger/input owns the active option state
  - focus restore after close
  - `Escape` close behavior where the component already behaves like an overlay/menu surface
- For navigation/tab primitives, preserve and test:
  - landmark labels on `nav` / navigation-like containers
  - `aria-current="page"` on active navigation/breadcrumb items
  - tab-to-panel linkage via `aria-controls` / `aria-labelledby`
- Do not add ARIA roles that conflict with the component's actual interaction model.
- Accessibility improvements that reshape keyboard behavior or focus handling should be documented as contract-sensitive changes.

### 11.1 Modal Foundation (`ui.modal`)

- `createModal(options)` is now the base shell for overlay/dialog rendering.
- `createModal(...)` now defaults to header-only dragging (`draggable: true`); keep drag initiation restricted to the header and do not let close buttons or header actions accidentally start movement.
- `ownerTitle` is now the shared way to restore ownership context in parent-owned bridged modals; prefer it over app-local subtitle markup when the shell needs to identify the requesting window/app.
- Cross-origin bridged preset modals now default `ownerTitle` from the child document title when `options.ownerTitle` is not explicitly provided. If the subtitle is missing in Workspace, verify the child app sets a meaningful `document.title`.
- `ui.dialog` helpers (`uiAlert`, `uiConfirm`, `uiPrompt`) are expected to compose over `ui.modal`, not duplicate modal/backdrop/focus logic.
- `createModal(...)` may expose header-level actions through `headerActions`; keep these as a slot-level contract, not a second footer-action API.
- `createActionModal(...)` may expose declarative `headerActions[]`; keep the header/footer action object contract identical when extending that helper.
- If action buttons support icons, preserve the shared icon contract (`icon`, `iconPosition`, `iconOnly`, `ariaLabel`) across both header and footer actions.
- `ui.dialog` helpers (`uiAlert`, `uiConfirm`, `uiPrompt`) must pass through the same header-action and icon-action options instead of inventing a separate action schema.
- If `ui.dialog` exposes semantic variants (`success`, `info`, `warning`, `error`), keep them as dialog-level presentation and default-action-emphasis contracts layered on top of `ui.modal`; do not fork a second modal implementation to support status styling.
- Default semantic status icons should also remain in `ui.dialog`, with explicit opt-out/override (`showVariantIcon`, `variantIcon`) rather than migrating status presentation into the neutral `ui.modal` shell.
- `ui.toast` should use the same semantic status icon language as `ui.dialog`; if the icon set changes, update both layers together instead of letting status cues drift.
- Shared semantic status icons should live in a single helper module when both `ui.dialog` and `ui.toast` consume them; do not let each component carry its own copy of the SVG map.
- Secondary helper-managed dialog guidance should use the shared `description` option before apps switch to custom modal body markup.
- Dialog speech should remain opt-in at the `ui.dialog` layer (`speak`, `speakText`, `voiceName`, `speakRate`, `speakPitch`, `speakVolume`); keep speech behavior out of the neutral `ui.modal` shell.
- `createFormModal(...)` should compose over `createActionModal(...)`; do not fork a second modal-form shell to support schema-driven forms.
- `createFormModal(...)` V1 should keep a strict row model:
  - one item => full width
  - two items => equal columns
  - more than two items => reject or normalize conservatively
- Modal-form validation should stay split:
  - helper owns required/basic validation
  - app owns domain/business validation
- Preset wrappers over `createFormModal(...)` should keep helper-owned structure and ordering while allowing engineer-provided field-name mappings where cross-project backend names differ.
- Preset wrappers that depend on business vocabularies should require app-supplied option lists instead of hardcoding shared operational categories:
  - status values
  - reason categories
- When base `createFormModal(...)` behavior and preset-wrapper behavior both need browser regression coverage, keep them in separate harnesses so failures identify the correct layer immediately.
- DOM-heavy `createFormModal(...)` changes should get a targeted browser regression harness when behavior depends on validation, busy submit lifecycle, or modal-close outcomes.
- Required behaviors to preserve:
  - escape/backdrop close controls
  - focus trap and focus restore
  - body scroll lock while open

### 11.2 Progress UI (`ui.progress`)

- `createProgress(container, data, options)` provides generic progress renderers:
  - `linear`, `striped`, `gradient`, `segmented`, `steps`, `radial`, `ring`, `indeterminate`
- Keep this component domain-agnostic; incident/business state mapping remains in caller adapters.

### 11.3 Window Foundation (`ui.window`)

- `createWindowManager(options)` is the shared direction for desktop-like floating tools:
  - stacked non-modal windows
  - drag / resize
  - minimize / maximize / restore
  - manager-owned taskbar recovery
  - workspace-style all-open-window taskbar when `taskbarMode: "always"` or contained `taskbarMode: "auto"` is used
- Use `ui.window` instead of app-local draggable/resizable panels when the interaction is actually window-like.
- Prefer:
  - `taskbarMode: "always"` for contained workspace shells
  - `taskbarMode: "minimized-only"` for conservative body-level usage
- Do not implement app-local window lists or ad hoc desktop bars when the shared taskbar contract already fits the workflow.
- Keep V1 narrow:
  - no docking
  - no snapping
  - no tiled layout manager
  - no saved workspace persistence
- If repeated product work needs those behaviors, submit a proposal first instead of widening `ui.window` ad hoc from app code.

### 11.3.1 Iframe Host (`ui.iframe.host`)

- `createIframeHost(options)` is the shared direction for embedded iframe surfaces, especially when a project wants to host another PBB app inside `ui.window`.
- Use `ui.iframe.host` instead of ad hoc iframe wiring when the integration needs helper-owned:
  - loading state
  - deterministic error state
  - narrow reload/source controls
- Keep V1 narrow:
  - no cross-frame messaging
  - no auth brokering
  - no Workspace launcher logic
  - no automatic embedded title sync
- Compose it with `ui.window`; do not widen `ui.window` itself with iframe-specific lifecycle behavior.

### 11.3.2 Workspace Bridge (`ui.workspace.bridge`)

- `ui.workspace.bridge` is the shared direction when iframe-hosted apps need helper-owned overlays to render in the parent workspace shell instead of inside the iframe.
- Use it for:
  - delegated toasts
  - delegated alert / confirm / prompt dialogs
  - explicit simple action-modal requests
- same-origin Workspace hosts may now also act as the automatic overlay parent for modal-family helpers such as:
  - `createModal(...)`
  - `createActionModal(...)`
  - `createFormModal(...)`
  - modal presets
- Keep the contract explicit:
  - parent installs `installWorkspaceUiBridgeHost(...)`
  - child uses `getWorkspaceUiBridge(...)`
- Do not treat this as generic cross-origin DOM mirroring. Automatic modal-family routing is same-origin only; cross-origin cases still rely on explicit bridge-aware surfaces or local fallback.
- For cross-origin iframe apps that need helper-owned auth modals in the Workspace parent, use the explicit form bridge:
  - `showWorkspaceFormModal(...)`
  - `namespace: "pbb.workspace.ui.bridge.v2"`
  - `method: "modal.form.open"`
  - current shipped `intent` values:
  - `login`
  - `reauth`
  - `account`
  - `change-password`
  - `generic-form`
- For cross-origin preset submits that need visible busy/error state in the parent-owned modal while child-owned async work runs, use the session-style bridge path:
  - `getWorkspaceUiBridge(...).openFormSession(payload)`
  - `method: "modal.form.session.open"`
  - `method: "modal.form.update"`
  - `method: "modal.form.close"`
  - keep API/business/session logic in the child app; Workspace should only render and update the parent modal surface
- For cross-origin admin forms that should render in the Workspace parent, do not assume a plain local `createFormModal(...)` call will bridge automatically.
  - Typical examples:
    - `Add User`
    - `Edit User`
    - `Add App`
    - `Edit App`
  - those flows should move onto the helper-owned `generic-form` bridge/session path when parent-owned Workspace rendering is required
- Use the plain operational routing guide when briefing downstream teams:
  - `docs/ui-workspace-overlay-routing-guide.md`
- Before asking downstream teams to validate a new cross-origin bridge change in real repos, use the local harness first:
  - `docs/ui-workspace-cross-origin-demo-harness.md`
  - `node scripts/run-workspace-bridge-cross-origin-demo.mjs`
  - `node tests/workspace.bridge.cross.origin.regression.mjs`
- Keep timeout semantics straight:
  - `timeoutMs` is for bridge availability / transport only
  - once the parent accepts and renders the interactive login or re-auth modal, the request should remain pending until the user responds
  - do not re-open a local modal just because the user took longer than the old transport timeout
- After helper-side routing changes land, downstream apps should refresh vendored helper assets and hard-refresh the browser so stale cached ES modules do not keep older local-rendering behavior alive.
- Keep V1 narrow:
  - no generic parent RPC
  - no auth/session brokering
  - no automatic cross-origin `createFormModal(...)` mirroring outside the explicit bridge-supported form intents
  - no arbitrary modal mirroring from child DOM into parent DOM
- Child apps still own:
  - API submission
  - CSRF refresh sequencing
  - auth/session state changes
  - retry/error mapping
- Prefer `trustedOrigins` over permissive cross-frame behavior. Workspace-owned surfaces should only be delegated to a known parent shell.

## 11.4 Communication Helpers

- `createChatThread(...)` is the shared thread surface for conversation history, grouped message runs, and attachment presentation.
- In `createChatThread(...)`:
  - image/video attachments should group through `ui.media.strip`
  - audio/file attachments should remain in the listed file group
  - per-message actions should prefer the helper-owned action trigger plus shared menu contract instead of app-local kebab menus
- `createChatComposer(...)` now owns the helper-side attach picker:
  - hidden native `<input type="file">`
  - `accept`
  - `multiple`
  - optional `capture`
  - `onFilesSelected(files, meta)`
- `createChatUploadQueue(...)` is the shared pending-draft attachment surface that should sit between `createChatComposer(...)` and app-owned send logic.
- In `createChatUploadQueue(...)`:
  - image/video attachments should group through `ui.media.strip`
  - audio/file attachments should remain in the listed file group
  - remove actions should update app-owned draft state, not mutate backend uploads
  - upload state is visual-only:
    - `status`
    - `progress`
    - `progressLabel`
    - `errorText`
  - actual upload transport and retry logic still remain app-owned
- Do not build separate app-local image thumbnail strips for chat messages when `ui.media.strip` already fits the message media set.
- Do not build separate app-local per-message kebab/context menus when `createChatThread(...)` already provides the shared message-action trigger contract.
- Keep attachment transport, upload orchestration, and realtime delivery logic in app code.
- Compose the thread with:
  - `createChatComposer(...)`
  - `createChatUploadQueue(...)`
  - `createFileUploader(...)`
  - `createMediaStrip(...)`
  - `createMediaViewer(...)`

### 11.4 Grid Virtualization (`ui.grid`)

- Virtualization is optional and controlled by options:
  - `enableVirtualization`
  - `virtualRowHeight`
  - `virtualOverscan`
  - `virtualThreshold`
- Preserve compatibility with:
  - selection modes
  - row click callbacks
  - column resizing
  - remote mode query events

### 11.4 Timeline + Scrubber (`ui.timeline`, `ui.timeline.scrubber`)

- `createTimeline(container, items, options)` supports vertical/horizontal orientation and grouped rendering.
- `createTimelineScrubber(container, options)` supports seek, range handles, and zoom levels.
- preserve labeling support (`ariaLabel`, `valueLabel`, `rangeStartLabel`, `rangeEndLabel`)
- preserve keyboard item activation on clickable timeline cards
- preserve keyboard adjustment behavior on scrubber range handles
- In demo/reference integrations:
  - scrubber range may filter visible timeline items
  - seek should keep active item highlight behavior stable
  - keyboard focus must remain visible on timeline cards and scrubber controls

### 11.5 Command Palette / Tree / Kanban

- `ui.command.palette`:
  - global quick actions with shortcut handling
  - preserve keyboard navigation and command filtering behavior
  - preserve async provider flow (`providers[]`) and loading behavior
  - preserve pinned/recent grouping and history hooks (`onHistoryChange`)
  - keep `historyStorageKey` behavior optional (no hard dependency on storage)
- `ui.tree`:
  - expandable/selectable/checkable hierarchy
  - preserve stable selection/check callbacks
  - preserve lazy-loading contract (`lazyLoadChildren`, `onLoadChildren`)
  - preserve virtualization settings behavior for large trees
- `ui.kanban`:
  - lane/card rendering with drag-drop card moves
  - preserve board labeling support (`ariaLabel`) and keyboard click activation
  - preserve lane-wide drop behavior and pointer-position insertion logic
  - preserve move callback payload shape (`card`, `fromLaneId`, `toLaneId`, `fromIndex`, `toIndex`, `lanes`)
- `ui.file.uploader`:
  - preserve region/dropzone labeling support (`ariaLabel`, `dropzoneAriaLabel`)
  - preserve legacy `onUpload(item, controls)` behavior
  - preserve chunk/resume hook contracts when `useChunkUpload` is enabled
  - keep state fields stable for progress + chunk metadata (`uploadedBytes`, `chunkIndex`, `totalChunks`)
- `ui.audio.player` / `ui.audio.callSession` / `ui.audio.audiograph`:
  - preserve transport/session labeling support (`ariaLabel`, `seekLabel`)
  - preserve mute semantics and per-role control behavior

### 11.6 Media Viewer

- `ui.media.viewer` is the dedicated standalone image/video lightbox layer.
- Keep it separate from `ui.media.strip`:
  - `ui.media.strip` owns thumbnail launch/browsing and should compose over the viewer instead of maintaining a second embedded lightbox implementation
  - `ui.media.viewer` owns modal viewing, zoom/pan, and gallery navigation
- Preserve:
  - stable viewer shell sizing across item changes
  - transform-based zoom/pan behavior
  - keyboard support (`Escape`, `Left/Right`, `+/-`, `0`, `Home/End`)
  - optional video audiograph composition via `ui.audio.audiograph`
  - item normalization compatibility with strip-style media payloads (`path/src`, `thumb`, `poster`, `photo|image|video`)

## 12) Demo Ownership Split

- `demos/demo.ui.html` is now a lightweight utilities overview/router, not a catch-all contract page.
- Toast, select, toggle, and button references belong on their dedicated pages:
  - `demos/demo.toast.html`
  - `demos/demo.select.html`
  - `demos/demo.fieldset.html`
  - `demos/demo.toggle.button.html`
  - `demos/demo.toggle.group.html`
  - `demos/demo.buttons.html`
- `demos/demo.fieldset.html` is the grouped-form reference when a page needs semantic sections and form-modal-style rows outside modal lifecycle.
- `createFieldset(...)` should share practical row semantics with `createFormModal(...)` for common row types such as:
  - `input`
  - `textarea`
  - `select`
  - `checkbox`
  - `ui.select`
  - `hidden`
  - `text`
  - `alert`
  - `divider`
  - `display`
- richer grouped page-content rows such as `image` and `custom` / `content` are documented through `createFieldset(...)` first; do not assume every fieldset-oriented content row automatically belongs in modal flows without an explicit shared contract decision.
- Grid-focused behavior belongs in `demos/demo.grid.html`:
  - local
  - remote
  - large virtualized fixed-height list
- Timeline-focused behavior belongs in `demos/demo.timeline.html`:
  - vertical/horizontal timeline
  - scrubber interaction (seek/range/zoom)
- Media-viewer-focused behavior belongs in `demos/demo.media.viewer.html`.
- Navigation-focused behavior belongs in the dedicated navigation demos:
  - `demos/demo.navbar.html`
  - `demos/demo.sidebar.html`
  - `demos/demo.breadcrumbs.html`
  - `demos/demo.dropdown.html`
  - `demos/demo.dropup.html`
- Virtual-list-focused behavior belongs in `demos/demo.virtual.list.html`.
- Scheduler/calendar-focused behavior belongs in `demos/demo.scheduler.html`.
- Stepper behavior belongs in `demos/demo.stepper.html`.
- Splitter behavior belongs in `demos/demo.splitter.html`.
- Data inspector behavior belongs in `demos/demo.inspector.html`.
- Empty-state behavior belongs in `demos/demo.empty.state.html`.
- Skeleton behavior belongs in `demos/demo.skeleton.html`.

When introducing a substantial UI module, prefer a dedicated demo page and link it from `demos/index.html`.
