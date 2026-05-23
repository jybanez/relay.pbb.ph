# UI Window Taskbar V2 Implementation Checklist

## Docs

- [x] Keep `docs/ui-window-taskbar-improvement-proposal.md` as the bounded rationale doc
- [x] Draft `docs/ui-window-taskbar-v2-spec.md`
- [x] Draft `docs/ui-window-taskbar-v2-checklist.md`
- [x] Update `README.md` window/taskbar section after implementation
- [x] Update `CHANGELOG.md` after implementation
- [x] Update `docs/pbb-refactor-playbook.md` after implementation

## Runtime

- [x] Add manager option:
  - `taskbarMode`
- [x] Add manager option:
  - `showTaskbarClose`
- [x] Add manager option:
  - `taskbarItemOrder`
- [x] Add manager method:
  - `getTaskbarWindows()`
- [x] Update taskbar sync logic so `"always"` mode lists all open windows
- [x] Preserve V1 behavior for `"minimized-only"`
- [x] Implement `"auto"` behavior with a clear contained/body rule
- [x] Add active-window taskbar state
- [x] Add minimized-window taskbar state
- [x] Keep maximized windows reserving taskbar height whenever taskbar is visible
- [x] Keep hidden taskbar fully non-rendering
- [x] Keep overflowing taskbar items horizontally scrollable without shrinking the pills into unreadable widths

## Demo Surface

- [x] Extend `demos/demo.window.manager.html`
- [x] Add always-on taskbar example
- [x] Add open-window switching example
- [x] Add minimized-state example

## Regression

- [x] Extend `tests/window.regression.html`
- [x] Extend `tests/window.regression.mjs`
- [x] Verify:
  - taskbar hidden in `"minimized-only"` mode when nothing is minimized
  - taskbar visible in `"always"` mode
  - all open windows render taskbar items in `"always"` mode
  - clicking open taskbar item focuses the window
  - clicking minimized taskbar item restores and focuses
  - maximized windows reserve taskbar height when taskbar is visible
  - hidden taskbar does not still render visually

## Cross-Project Follow-Through

- [x] Inform other teams through `C:\\wamp64\\www\\pbb\\chat_log.md` after implementation
- [x] Include the helper-refresh reminder pointing teams to:
  - `https://github.com/jybanez/helpers.pbb.ph.git`
