# Catalog Import PRO 2.3.1

Author: CodeCart PRO
Website: https://codecartpro.com
Compatibility: CodeCart 3.0.5.2, OpenCart 3.x, ocStore 3.0.4.1, PHP 7.4-8.5, MySQL/MariaDB, InnoDB and utf8mb4.

Create a complete backup of the site files and database before installation, update, removal or the first real import.

The module remains OFF after installation and schema migration. Import, queue preparation, processing, recovery, export and remote-image downloading are blocked until it is enabled manually.

Version 2.3.1 adds PHP 7.4-8.5 compatibility target and avoids the PHP 8.5 curl_close() deprecation while preserving PHP 7.4 cURL resource cleanup. Import behaviour is unchanged.

Version 2.3.0 provides:

- mandatory strict selected-key matching with no fallback search path;
- separate quick buttons for prices only, stock only, new products only and full import;
- safe profiles for prices, stock, prices plus stock, new records only, existing records only and full upsert;
- supplier-column mapping with case-insensitive Russian and Ukrainian header matching;
- individual UI policy selectors for every supported mutable field: preserve, overwrite, fill_empty, merge, replace and clear, with unsafe clearing blocked for required fields and the selected key;
- supplier-scoped missing-product synchronization with disable, zero-stock or delete actions;
- dry-run review with per-row selection before real application;
- status filters, search, server-side pagination, full CSV reports and error retry;
- transactional and idempotent CSV queue preparation;
- unique batch/CSV-row protection;
- preflight validation of the selected key, profile-required columns and duplicate mapped targets;
- worker-token leases, renewal before each row and expired-processing recovery;
- catalogue mutation and queue completion in one database transaction;
- safe ocStore 3.0.4.1 category and manufacturer creation;
- import/export of product discounts, specials, rewards and recurring profiles;
- streaming export with CSV Formula Injection protection;
- automatic retention cleanup when the module page is opened.

Unqualified text columns update only the selected language. Existing values and relations are preserved when their CSV columns are absent. ocStore-specific noindex values, product/article relations, category and manufacturer related entities, manufacturer multilingual metadata and layouts are retained.

Run the first import in dry-run mode. Successful rows enter review status. Select the exact rows to apply, then start the real import.

Missing-product synchronization is available only with a permanent lower-case supplier code, a stable strict SKU/model/EAN/ID key, mandatory dry run and manual row confirmation. Name-based missing synchronization is prohibited. The first confirmed supplier import creates the baseline registry; later files are compared only with products previously registered under the same supplier code and key field. In new-only mode, an already existing product can be linked to the supplier after review without changing the product. Missing rows are unselected by default, and an empty file cannot trigger mass actions. If the same product is still assigned to another active supplier, disable, zero-stock and delete actions are blocked; after confirmation only the current supplier link is deactivated.

Third-party module tables and unknown custom fields require a dedicated adapter. CSV export is not a substitute for a full database backup.

See the Russian README in the archive for the complete field reference, compact commercial-data formats and security model.
