# UI Icons V1 Spec

## Purpose

Add a shared SVG icon registry for `helpers.pbb.ph` so PBB projects can use one categorized icon set with stable namespaced ids instead of scattering inline SVG across repos.

## Runtime Files

- `js/ui/ui.icons.catalog.js`
- `js/ui/ui.icons.js`
- `css/ui/ui.icons.css`

## Demo And Regression

- `demos/demo.icons.html`
- `tests/icons.regression.html`
- `tests/icons.regression.mjs`

## API

```js
import {
  createIcon,
  getIconDefinition,
  listIcons,
  listIconCategories,
} from "./js/ui/ui.icons.js";
```

### `createIcon(name, options)`

Returns:

- `SVGElement`

Supported options:

- `size`
- `title`
- `className`
- `strokeWidth`
- `decorative`
- `ariaLabel`

### `getIconDefinition(name)`

Returns:

- icon definition object
- `null` when the name is unknown

### `listIcons()`

Returns:

- sorted icon ids

### `listIconCategories()`

Returns:

- sorted category names

## Naming Rules

Stable namespaced ids:

- `actions.close`
- `actions.edit`
- `actions.search`
- `navigation.chevron-left`
- `status.success`
- `media.play`

## Categories In V1

- `actions`
- `navigation`
- `status`
- `media`
- `data`
- `people`
- `workflow`
- `places`
- `time`
- `comms`
- `assets`

## Render Rules

- one outline visual language
- `currentColor` inheritance
- root SVG element gets `.ui-icon`
- unknown icon names throw an error in `createIcon(...)`

## Accessibility

- decorative icons default to `aria-hidden="true"`
- non-decorative icons should expose:
  - `aria-label`
  - or `<title>`

## Initial Curated Set

### Actions

- `actions.add`
- `actions.check`
- `actions.close`
- `actions.copy`
- `actions.delete`
- `actions.download`
- `actions.edit`
- `actions.options`
- `actions.hide`
- `actions.more-horizontal`
- `actions.attach`
- `actions.export`
- `actions.refresh`
- `actions.save`
- `actions.search`
- `actions.settings`
- `actions.sort`
- `actions.view`

### Navigation

- `navigation.chevron-left`
- `navigation.chevron-right`
- `navigation.chevron-up`
- `navigation.chevron-down`
- `navigation.arrow-left`
- `navigation.arrow-right`
- `navigation.home`
- `navigation.menu`

### Status

- `status.success`
- `status.warning`
- `status.error`
- `status.info`

### Media

- `media.play`
- `media.pause`
- `media.image`
- `media.video`
- `media.microphone`
- `media.audio`

### Data

- `data.grid`
- `data.list`
- `data.tree`
- `data.upload`
- `data.filter`

### People

- `people.account`
- `people.profile`
- `people.user`
- `people.users`

### Workflow

- `workflow.assigned`
- `workflow.requested`
- `workflow.accepted`
- `workflow.en-route`
- `workflow.on-scene`
- `workflow.completed`
- `workflow.cancelled`

### Places

- `places.pin`
- `places.route`
- `places.map`
- `places.home-base`

### Time

- `time.clock`
- `time.history`
- `time.calendar`
- `time.timer`

### Communication

- `comms.phone`
- `comms.radio`
- `comms.message`
- `comms.notification`
- `comms.signal`

### Assets

- `assets.vehicle`
- `assets.document`
- `assets.camera`
- `assets.clipboard`

## Non-Goals

V1 does not include:

- icon animation
- multiple icon styles
- external icon packs
- project-owned icon registration
