Vendored from: https://github.com/jybanez/helpers.pbb.ph
Upstream snapshot: C:\wamp64\www\hotline-helpers
Latest documented upstream release: v0.21.83

Included in this project:
- `css/*`
- `js/*`
- `dist/*`
- `docs/*`
- `boot.*.json`
- `README.upstream.md`
- `CHANGELOG.upstream.md`

Notes:
- Refreshed from the official local Helper working tree in `C:\wamp64\www\hotline-helpers`.
- The upstream source does not expose Git metadata in this checkout, so Relay pins the vendored copy by source path plus the package version in upstream package.json.
- Relay still imports Helper through `/vendor/helpers.pbb.ph/js/ui/*` and `/vendor/helpers.pbb.ph/css/ui/*`; `/dist` is included locally for future migration and audit, but release packaging trims non-runtime docs/demo/test material.
