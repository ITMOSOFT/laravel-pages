# Changelog

## [0.1.1] - 2026-11-26

- Fix model defaults so omitted status, type, template, and navigation fields are populated before the model saving hook validates them.
- Add a regression test for model defaults.
- Correct store-isolation test setup to publish pages before asserting public resolution.

## [0.1.0] - 2026-11-26

- Initial package implementation for store-scoped static pages and content blocks.
- Added draft/published/archived states, scheduled visibility, navigation locations, metadata and soft deletion.
- Added revision history, revision restoration and optimistic stale-update checks.
- Added public JSON page and navigation endpoints.
- Added tests, CI workflow, documentation and release instructions.
