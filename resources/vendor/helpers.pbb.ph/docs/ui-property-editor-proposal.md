# UI Property Editor Proposal

## Summary

Add a shared `ui.property.editor` helper for inspector-style editing surfaces used in:

- IDE-like settings panes
- CAD/property inspectors
- design/configuration tools
- admin/operator configuration consoles
- schema-driven object editors

The component should provide a reusable property-inspector surface for structured name/value editing without turning the helper library into a full domain-specific modeler.

## Why This Is Needed

The helper library already has strong building blocks for:

- forms
- grouped field sections
- modal editing
- table/grid presentation

But it does not yet provide a shared inspector-style surface for:

- compact property name/value rows
- grouped property sections
- mixed read-only and editable values
- mixed-selection state such as "multiple values"
- narrow inline editors by property type

Without a shared property editor, apps that need object/config inspectors will drift into app-local implementations with inconsistent:

- row layout
- label/value alignment
- typed editor affordances
- validation presentation
- section grouping
- read-only treatment

This gap becomes more visible in:

- visual builder/admin tools
- Workspace-style management panels
- CAD/diagram-like tools
- advanced settings/property panes

## Recommended Component

Introduce:

- `ui.property.editor`

Factory direction:

```js
const editor = createPropertyEditor(container, data, options);
```

## Recommended Scope

`ui.property.editor` should own:

- inspector layout
- section grouping
- property row rendering
- typed editor selection for supported property kinds
- hosted shared controls where the helper already provides a stronger surface, such as `ui.select`
- read-only presentation
- mixed-value presentation
- field/form-style validation presentation where practical
- change events for edited properties

It should not own:

- persistence
- transport
- domain-specific object models
- undo/redo engine
- schema authoring workflows
- arbitrary plugin execution

## Recommended Data Model

The editor should work from explicit sections and property descriptors.

Example direction:

```js
const editor = createPropertyEditor(host, {
  selectionLabel: 'Radial Dimension (1)',
  sections: [
    {
      id: 'general',
      title: 'General Properties',
      properties: [
        {
          id: 'layer',
          label: 'Layer',
          kind: 'select',
          value: 'dimensions',
          options: [
            { value: 'dimensions', label: 'dimensions' },
            { value: 'annotations', label: 'annotations' }
          ]
        },
        {
          id: 'color',
          label: 'Color',
          kind: 'color-select',
          value: '#ffffff'
        },
        {
          id: 'lineweight',
          label: 'Lineweight',
          kind: 'text',
          value: 'By Layer'
        },
        {
          id: 'handle',
          label: 'Handle',
          kind: 'display',
          value: '0x3d0',
          readOnly: true
        }
      ]
    }
  ]
}, {
  onPropertyChange(change) {
    console.log(change.propertyId, change.value);
  }
});
```

## Supported V1 Property Kinds

Recommended first-wave kinds:

- `display`
- `text`
- `textarea`
- `number`
- `checkbox`
- `toggle`
- `select`
- `color`
- `color-select`

Optional if straightforward in implementation:

- `button`
- `action`
- `divider`

## Important V1 Concepts

### 1. Sections

The editor should support grouped sections with:

- title
- optional description
- optional collapsed state later, but not required for V1

### 2. Property Rows

Each row should have a stable shape:

- label cell
- value/editor cell
- optional trailing action slot

### 3. Read-Only Properties

Some properties should display values but not expose editing controls.

### 4. Mixed Values

For multi-selection workflows, some properties may represent mixed values.

The helper should support an explicit mixed state, for example:

- `mixed: true`
- display placeholder such as `Mixed`
- preserve editing behavior if the app chooses to overwrite the mixed state with a new value

### 5. Validation

The editor should support narrow field/form error presentation similar to other helper form surfaces, but it should not become a full submit engine.

## Recommended Interaction Model

The helper should emit narrow structured change events instead of owning save behavior.

Recommended callback direction:

- `onPropertyChange(change, meta)`
- `onAction(property, action, meta)`
- `onSelectionChange(selection, meta)` only if a selection header control exists

Apps should still own:

- debouncing
- autosave
- server sync
- undo/redo
- cross-property business rules

## Relationship To Existing Helpers

### `ui.fieldset`

`ui.fieldset` is good for grouped forms.

`ui.property.editor` is different because it needs:

- compact inspector layout
- property-style label/value alignment
- mixed-value semantics
- read-only inspector rows
- property-specific narrow editors

### `ui.form.modal`

`ui.form.modal` remains the correct helper for submit-oriented workflows.

`ui.property.editor` is better for continuous inspection/editing surfaces.

## Recommended V1 Use Cases

- object property inspector in a design/build tool
- app settings side panel
- admin property pane for selected records
- Workspace-style configuration inspector
- asset/property sidebars

## Non-Goals

V1 should not attempt to solve:

- full schema designer behavior
- property dependency graph execution
- plugin-defined custom editors
- full tree inspector with nested expansion for every property kind
- multi-column spreadsheet editing

## Implementation Recommendation

Build V1 as a narrow reusable editor with:

- stable section/property data model
- small supported editor set
- strong read-only/mixed-state handling
- app-owned persistence and business rules

That is the right quality bar for a first shared inspector component.
