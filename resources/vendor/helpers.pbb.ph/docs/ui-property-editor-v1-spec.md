# UI Property Editor V1 Spec

## Summary

`ui.property.editor` is the shared helper for rendering and editing inspector-style property panels.

It should provide a reusable property-editor surface for:

- grouped property sections
- compact property name/value rows
- typed editors for common property kinds
- read-only property display
- mixed-value display for multi-selection
- narrow validation/error presentation

It should remain presentation-focused.

It should not own:

- persistence
- transport
- undo/redo
- domain object modeling
- schema authoring

## Goals

- give PBB apps a shared property-inspector UI
- avoid app-local inspector drift
- support continuous editing surfaces without forcing modal form semantics
- support typed property editing while keeping app logic external

## Non-Goals

- no autosave engine
- no backend sync logic
- no arbitrary custom runtime plugins in V1
- no full tree/grid hybrid editor in V1
- no forced collapsible sections in V1

## Factory

```js
const editor = createPropertyEditor(container, data, options);
```

## Signature

```ts
createPropertyEditor(
  container: HTMLElement,
  data?: {
    selectionLabel?: string;
    sections?: PropertyEditorSection[];
  },
  options?: PropertyEditorOptions
): PropertyEditorInstance
```

## Data Model

### `PropertyEditorSection`

```ts
type PropertyEditorSection = {
  id: string;
  title: string;
  description?: string;
  properties?: PropertyEditorProperty[];
};
```

### `PropertyEditorProperty`

```ts
type PropertyEditorProperty = {
  id: string;
  label: string;
  kind:
    | 'display'
    | 'text'
    | 'textarea'
    | 'number'
    | 'checkbox'
    | 'toggle'
    | 'select'
    | 'ui.select'
    | 'color'
    | 'color-select'
    | 'action'
    | 'divider';
  value?: unknown;
  placeholder?: string;
  help?: string;
  readOnly?: boolean;
  disabled?: boolean;
  mixed?: boolean;
  required?: boolean;
  options?: Array<{ value: string | number; label: string }>;
  min?: number;
  max?: number;
  step?: number;
  actions?: Array<{ id: string; label: string; danger?: boolean }>;
  errorText?: string;
};
```

## V1 Options

### `className`

- type: `string`
- default: `""`

Extra root class for host-specific layout adjustments.

### `showSelectionLabel`

- type: `boolean`
- default: `true`

Shows the selection header row when `data.selectionLabel` is present.

### `selectionLabelPlaceholder`

- type: `string`
- default: `"No selection"`

Fallback selection text when the selection header is enabled but no selection label is available.

### `labelWidth`

- type: `string | number | null`
- default: `null`

Optional fixed width for the property label column.

### `dense`

- type: `boolean`
- default: `false`

Uses a tighter row density for inspector-heavy surfaces.

### `showSectionDescriptions`

- type: `boolean`
- default: `true`

Shows section descriptions when provided.

### `showPropertyHelp`

- type: `boolean`
- default: `true`

Shows property help text when provided.

### `mixedLabel`

- type: `string`
- default: `"Mixed"`

Display label used when a property has `mixed: true`.

### `readOnlyValueFallback`

- type: `string`
- default: `"—"`

Fallback display for empty read-only values.

### `onPropertyChange(change, meta)`

Structured change callback for edited properties.

### `onAction(property, action, meta)`

Structured action callback for property-level actions.

## Required V1 Behavior

### 1. Section Rendering

The editor should render sections in order.

Each section may include:

- title
- description
- property rows

Empty sections may render with title only, but should not throw.

### 2. Property Row Layout

Each property row should use a stable inspector shape:

- label cell
- value/editor cell
- optional trailing action cell

### 3. Typed Editor Rendering

Supported V1 kinds should map to narrow built-in controls:

- `display`: text-only value presentation
- `text`: single-line text input
- `textarea`: multi-line text input
- `number`: numeric input
- `checkbox`: checkbox control
- `toggle`: toggle-button control
- `select`: native single-value select
- `ui.select`: hosted shared select with searchable and multi-value support
- `color`: simple color display or color input
- `color-select`: color swatch plus choice affordance
- `action`: button-like action row
- `divider`: non-editable visual separator

### 4. Read-Only Properties

If `readOnly: true`:

- render a non-editable value display
- do not expose editing controls
- preserve help and error text if provided

### 5. Mixed Values

If `mixed: true`:

- show the mixed state clearly in the editor cell
- use `mixedLabel` by default
- allow overwrite when the property kind is editable and not read-only

### 6. Validation

The editor should support narrow property-level error rendering:

- show `errorText` under the property row when provided
- set `aria-invalid="true"` on invalid editable controls where practical

V1 may defer form-level aggregate errors.

## Change Event Contract

Recommended V1 shape:

```ts
type PropertyChange = {
  sectionId: string;
  propertyId: string;
  kind: PropertyEditorProperty['kind'];
  value: unknown;
};
```

`onPropertyChange(change, meta)` should fire when a supported editable property changes.

`meta` may include:

- source element
- previous value
- mixed-to-explicit transition indicator

## Action Event Contract

Recommended V1 shape:

```ts
type PropertyActionSelection = {
  id: string;
  label: string;
  danger?: boolean;
};
```

`onAction(property, action, meta)` should fire for:

- `action` rows
- trailing property actions if supported in the row

## Instance Methods

### `update(data?, options?)`

Updates editor data and/or options.

### `setSections(sections)`

Replaces the current section set.

### `setSelectionLabel(label)`

Updates the header selection label.

### `getState()`

Returns current normalized data and options.

### `setErrors(errors)`

Optional but recommended narrow parity with other helper form surfaces.

Expected input shape:

```ts
Record<string, string>
```

Where keys map to `property.id`.

### `clearErrors()`

Clears property-level errors.

### `destroy()`

Destroys the editor instance and DOM content.

## Accessibility Expectations

V1 should:

- connect labels to inputs where practical
- expose disabled/read-only state clearly
- expose invalid state via `aria-invalid` where practical
- keep property labels readable even in dense mode

## Acceptance Criteria

The V1 property editor contract is acceptable when:

- sections and property rows render consistently
- supported property kinds have narrow built-in editors
- read-only and mixed-value states are clearly handled
- apps can listen to structured property changes
- apps keep persistence and business logic outside the helper
- the component is reusable across inspector-style surfaces
