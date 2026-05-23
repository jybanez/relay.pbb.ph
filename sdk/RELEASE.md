# SDK Release Notes

This repository currently ships baseline convenience clients in:

- `sdk/php`
- `sdk/js`

## Current Package Baseline

- protocol-aware local relay clients
- delivery and inbox convenience methods
- handler and handler-dispatch convenience methods
- compatibility and capability inspection helpers

## Release Checklist

1. Update package version in `sdk/php/composer.json`
2. Update package version in `sdk/js/package.json`
3. Update `CHANGELOG.md` in both SDK folders
4. Verify protocol compatibility against `GET /api/v1/compatibility`
5. Verify example snippets in both SDK READMEs
6. Run the repository test suite

## Current Gaps

- no automated publish pipeline yet
- no generated API schema bindings
- no dedicated TypeScript declaration package yet
