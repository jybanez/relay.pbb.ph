# UI Window V1 Checklist

## Scope Lock

This checklist implements only the V1 contract in:

- `docs/ui-window-proposal.md`
- `docs/ui-window-v1-spec.md`

## Runtime

- [x] add `js/ui/ui.window.js`
- [x] add `css/ui/ui.window.css`
- [x] implement `createWindowManager(options)`
- [x] implement `manager.createWindow(options)`
- [x] implement window open / close / focus
- [x] implement z-index stack management
- [x] implement title-bar drag
- [x] implement edge / corner resize
- [x] implement minimize / restore
- [x] implement maximize / restore
- [x] implement taskbar for minimized windows
- [x] clamp movement and resize to viewport bounds
- [x] implement `setTitle`, `setContent`, `setPosition`, `setSize`, `getState`, `destroy`

## Loader

- [x] register `ui.window` in `js/ui/ui.loader.js`
- [x] add `ui.window` to an appropriate loader group

## Demo

- [x] add `demos/demo.window.manager.html`
- [x] prove multiple windows
- [x] prove focus / stacking
- [x] prove drag and resize
- [x] prove minimize / restore via taskbar
- [x] prove maximize / restore
- [x] add demo metadata for right-column manual rendering
- [x] add nav/catalog links

## Docs

- [x] document `createWindowManager(options)` in `README.md`
- [x] document manager and window option tables in `README.md`
- [x] document returned APIs in `README.md`
- [x] update project structure in `README.md`
- [x] update `CHANGELOG.md`
- [x] update `docs/pbb-refactor-playbook.md` with window-system ownership rules

## Regression

- [x] add `tests/window.regression.html`
- [x] add `tests/window.regression.mjs`
- [x] cover stack order
- [x] cover minimize / restore
- [x] cover maximize / restore
- [x] cover minimum resize bounds
- [x] cover destroy cleanup

## Completion Gate

V1 is complete when:

- runtime API matches `docs/ui-window-v1-spec.md`
- demo exists and proves the core interactions
- regression harness passes
- loader/docs/changelog/playbook are updated
