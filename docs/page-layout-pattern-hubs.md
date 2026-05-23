# Hubs Page Layout Pattern

## Purpose

This note explains the page-shell pattern used by the `Hubs` page so other pages can reuse the same layout behavior without guessing from the current CSS.

The important principle is:

- the page owns the layout
- the data widget only owns the scrollable body inside the final content row

This avoids:

- overlapping search/toolbars and data widgets
- the whole page becoming the scroll container
- disappearing headers when content gets long
- double-framed wrappers

## Layout Model

The layout is made of three layers:

1. App shell
- top navigation/header
- page content area below it

2. Page shell
- intro/header row
- toolbar/actions row
- content row that fills the remaining height

3. Data surface
- one main panel/surface for the widget
- only the widget body scrolls

## Recommended Structure

Use this pattern for pages similar to `Hubs`:

```html
<div class="feature-page">
  <section class="feature-shell">
    <header class="feature-head">
      <!-- eyebrow, title, description, summary/action -->
    </header>

    <div class="feature-toolbar">
      <!-- search, action buttons, counters -->
    </div>

    <section class="feature-content">
      <!-- ui.tree.grid / ui.grid / board / list -->
    </section>
  </section>
</div>
```

For the current Hubs page, the concrete structure is:

- `.page`
- `.hubs-page`
- `.hubs-shell`
- `.hubs-head`
- `.hubs-toolbar`
- `.hubs-tree`

## Recommended CSS Pattern

The key rule is to keep `min-height: 0` on every parent that needs to allow an inner scroll container.

```css
.feature-page {
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.feature-shell {
  min-height: 0;
  flex: 1;
  display: grid;
  grid-template-rows: auto auto minmax(0, 1fr);
  gap: 14px;
}

.feature-content {
  min-height: 0;
  overflow: hidden;
}
```

### Why This Matters

- `auto`
  - first row sizes to page intro content
- `auto`
  - second row sizes to toolbar/search/actions
- `minmax(0, 1fr)`
  - third row fills the remaining space and can host a real scrollable child

Without `min-height: 0`, the widget often forces the whole page to grow and the browser scrolls the full page instead of the intended internal body.

## Widget Hosting Rules

The data widget should not define the page layout.

Instead:

- the page shell defines the rows
- the widget fills the final row
- the widget's internal body becomes the scroll container

For helper components like `ui.grid` or `ui.tree.grid`:

- let the host surface fill the available height
- let the helper component fill the host
- let the helper table/body wrapper handle scrolling

## Hubs-Specific Interpretation

For the Hubs page:

- row 1: page intro
  - eyebrow
  - title
  - description
  - top-right action/count

- row 2: toolbar
  - search
  - clear search
  - expand all
  - collapse all
  - count badge

- row 3: tree panel
  - single visible surface
  - `ui.tree.grid` fills it
  - only the tree/grid body scrolls

## Common Mistakes To Avoid

- letting the data widget define page height
- using `height: 100%` without `min-height: 0` on parent containers
- putting search and the grid in the same overlapping visual box unless the component is designed for it
- adding extra wrapper panels that create double borders/chrome
- making the whole page the scroll container

## Design Guidance

For pages similar to `Hubs`, keep the page layout pattern consistent:

- one intro row
- one toolbar row
- one content row
- one visible surface for the main widget

This gives the page a predictable structure and makes helper widgets easier to swap without rewriting the layout every time.

## Recommendation

When building new admin/data pages with grids, trees, lists, or boards:

- start from the Hubs page-shell pattern
- do not start from the widget markup alone
- treat the page shell as the reusable layout contract

That will make layouts more uniform across the project and reduce CSS debugging when pages grow more complex.
