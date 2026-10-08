# Changelog

## 2.3.1

- Certified the module code for PHP 7.4-8.5.
- Avoided the PHP 8.5 `curl_close()` deprecation while preserving resource cleanup on PHP 7.4.
- Updated compatibility metadata to CodeCart 3.0.5.2, OpenCart 3.x, ocStore 3.0.4.1 and PHP 7.4-8.5.
- Import, queue, dry-run and supplier-sync behaviour is unchanged.

## 2.3.0

- Added explicit per-field UI policies: do not change, overwrite, fill empty, add/merge, replace and clear.
- Added dedicated quick buttons for prices-only, stock-only, new-only and full imports.
- Removed fallback matching from the processing path; the selected key is mandatory in every row.
- Added supplier-scoped missing-product synchronization with mandatory dry run, manual row confirmation and cross-supplier protection.
- Added a persistent supplier registry baseline, stable-key validation, name-key prohibition and current-supplier-only unlinking when another supplier remains active.
- Blocked unsafe clearing of required names, product model/date and the selected matching key.
- Added strict matching and safe import profiles.
- Added supplier-column mapping, including case-insensitive Russian and Ukrainian headers, and per-field update rules.
- Added dry-run review, row selection, result pagination, filters, reports and error retry.
- Made queue preparation transactional and idempotent.
- Added worker leases, renewal before each row and expired-processing recovery.
- Fixed empty queues and interrupted batch continuation.
- Fixed ocStore 3.0.4.1 automatic category and manufacturer creation.
- Added discounts, specials, rewards and recurring-profile import/export.
- Added batch-level validation for required key/profile columns and duplicate mapped targets.
- Added automatic retention cleanup and pipe-delimited export.
- Extended documentation and CSV examples.

## 2.1.1

- Added ocStore 3.0.4.1-specific data preservation.

## 2.1.0

- Reworked the original importer around safe standard OpenCart models and persistent queues.
