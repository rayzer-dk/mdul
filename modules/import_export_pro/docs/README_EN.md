# Import Export Pro 3.7.0

Import/export and supplier synchronization module for CodeCart 3.0.5.2 / ocStore 3.0.4.1 and OpenCart 3.x, PHP 7.4–8.5.

Before installation, upgrade or import, create a full backup of the website files and database. The module stays disabled after installation or migration. Run every new profile on a staging copy and use the mandatory dry-run/review workflow.

Key safety features: strict single match key without fallback, duplicate-match blocking, per-field policies, persistent InnoDB queue, row review and selection, transactional apply, worker lease/heartbeat, safe supplier missing-product synchronization, multilingual/multistore SEO preservation, correct OpenCart options, SSRF-safe image downloading, streamed CSV export and formula-injection protection.

Administrative full imports require dry-run review. Cron is intentionally restricted to price and/or stock updates for existing products.

See the Russian README.md in the archive for the complete documentation and data formats.
