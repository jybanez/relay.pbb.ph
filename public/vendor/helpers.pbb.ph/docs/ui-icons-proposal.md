# `ui.icons` Proposal

## Summary

Introduce a shared SVG icon system for `helpers.pbb.ph` so PBB projects use one categorized icon library instead of repeating inline SVG or mixing unrelated icon sources per repo.

V1 should stay narrow:

- one shared icon registry
- one visual style
- plain DOM/SVG output
- namespaced icon ids
- dedicated demo/catalog page

## Problem

Current helper and app work already uses SVG in:

- dialogs
- modal/window controls
- action buttons
- navigation
- status surfaces
- media/tooling controls

Without a shared icon layer, teams drift into:

- app-local icon copies
- inconsistent sizes and stroke styles
- repeated inline SVG
- weak naming discipline
- poor demo/documentation discoverability

## Goals

V1 should provide:

1. a helper-owned icon registry
2. stable namespaced icon ids
3. direct DOM SVG creation
4. category-based discoverability
5. `currentColor` inheritance
6. predictable sizing for buttons, labels, and status UI
7. one dedicated demo page
8. regression coverage for render and registry integrity

## Non-Goals

V1 should not include:

- multiple icon styles mixed together
- third-party CDN/runtime icon loading
- app-defined raw SVG injection as part of the shared contract
- animation systems
- icon fonts
- illustration assets

## Recommended Architecture

### Files

- `js/ui/ui.icons.js`
- `js/ui/ui.icons.catalog.js`
- `css/ui/ui.icons.css`
- `demos/demo.icons.html`
- `tests/icons.regression.html`
- `tests/icons.regression.mjs`

### Registry API

Recommended exports:

- `createIcon(name, options)`
- `getIconDefinition(name)`
- `listIcons()`
- `listIconCategories()`

### Render Rule

`createIcon(...)` should return an `SVGElement`, not an HTML string.

Example:

```js
const icon = createIcon("actions.close", {
  size: 16,
  title: "Close",
  className: "my-icon",
});
```

Recommended options:

- `size`
- `title`
- `className`
- `strokeWidth`
- `decorative`
- `ariaLabel`

## Naming Rules

Use stable namespaced ids:

- `actions.close`
- `actions.edit`
- `actions.delete`
- `actions.search`
- `actions.refresh`
- `status.success`
- `status.warning`
- `status.error`
- `status.info`
- `nav.chevron-left`
- `nav.chevron-right`
- `nav.chevron-up`
- `nav.chevron-down`
- `media.play`
- `media.pause`
- `data.filter`
- `data.sort`
- `data.grid`
- `data.tree`
- `people.user`
- `people.team`
- `comms.radio`
- `comms.signal`

Rules:

- lowercase only
- category prefix required
- kebab-case suffix
- no vague ids like `misc-star` or `icon-1`

## Visual Direction

Recommended V1 direction:

- outline icons
- rounded caps and joins
- stroke-based rendering
- `currentColor` by default

That is the easiest way to keep dialogs, windows, buttons, and tooling visually coherent.

## Initial V1 Categories

Start with:

### Actions

- close
- add
- edit
- delete
- search
- refresh
- save
- upload
- download
- copy
- check

### Status

- success
- warning
- error
- info
- pending
- offline
- online

### Navigation

- chevron-left
- chevron-right
- chevron-up
- chevron-down
- menu
- more
- home
- back

### Media

- play
- pause
- stop
- image
- video
- audio
- zoom-in
- zoom-out

### Data / Structure

- filter
- sort
- grid
- list
- tree
- map

### People / Communication

- user
- users
- team
- radio
- signal
- alert

This is enough for V1.

## Integration Targets

The icon system should be suitable for later adoption in:

- `ui.dialog`
- `ui.modal`
- `ui.window`
- `ui.toast`
- `ui.password`
- `ui.media.viewer`
- `ui.media.strip`
- `ui.nav*`
- `ui.progress`

V1 does not need to migrate all of them immediately.

## Accessibility Expectations

The helper should support:

- decorative icons hidden from assistive tech by default
- accessible labels when the icon itself is meaningful
- title support for plain SVG usage

Rule:

- icon-only buttons must still receive button-level accessible labeling

## Demo Expectations

`demos/demo.icons.html` should show:

1. category catalog
2. icon names
3. size examples
4. button integration examples
5. status examples
6. standalone API examples

## Regression Expectations

V1 should include:

- no duplicate icon names
- valid categories
- renderable SVG output
- title/aria behavior checks
- size option check

## Rollout Recommendation

Phase 1:

- add registry
- add CSS
- add demo page
- add regression coverage

Phase 2:

- migrate repeated shared surfaces:
  - dialog semantic icons
  - window controls
  - password toggle
  - media viewer controls

Phase 3:

- expand only after real cross-project adoption feedback

## Recommendation

Approve a narrow `ui.icons` V1 with:

- one outline SVG style
- namespaced icon ids
- DOM-first rendering
- dedicated demo/catalog page
- regression coverage

That is the correct scale for a first shared icon system in `helpers.pbb.ph`.
