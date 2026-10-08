ВАЖНО: перед установкой или обновлением сделайте полную резервную копию файлов сайта и базы данных.

Supplier Sync Parser PRO v1.6.4
Author: CodeCart PRO
Site: https://codecartpro.com
Compatibility: CodeCart 3.0.5.2 / OpenCart 3.x / ocStore 3.0.4.1, PHP 7.4–8.5

IMPORTANT BACKUP WARNING
Before installation, update or first mass synchronization, create a full backup of site files and database.
This module can update prices, stock and create products, therefore testing on a small supplier set is mandatory before production use.

VERSION 1.6.4 CHANGES
- Expanded the supported PHP target to PHP 7.4–8.5 and removed PHP 8.5-deprecated curl_close().
- Declared parser service properties explicitly to avoid dynamic-property deprecations on PHP 8.2+.
- Cron authentication now prefers the X-CCP-Cron-Key header so the secret is not exposed in URLs; the old ?token= form remains a compatibility fallback.
- Supplier images are validated in a temporary file and atomically renamed into place to avoid partial image files.
- Added explicit user_token validation for administrative write/AJAX actions and synchronized version/schema metadata.

PURPOSE
Supplier Sync Parser PRO is designed for stores that need to take prices, stock, product data and new products from supplier websites when the supplier does not provide XML, CSV or API.
The module uses HTML parsing and XPath rules, but the normal user workflow is built around a simple assistant: scan one product page, check detected values, save rules, create a queue and apply reviewed changes.

MAIN BENEFITS
1. Price and stock monitoring from supplier websites.
2. Safe preview before applying changes to OpenCart products.
3. Existing product matching by SKU, EAN, UPC, MPN, supplier URL and title.
4. New product capture with manual category selection before creation.
5. OpenCart currency support and supplier price normalization.
6. Language targeting for stores with multiple languages.
7. Queue-based batch processing for large catalogs.
8. AJAX actions, progress, filters, sorting, pagination and selectable table limits.
9. Logs, history and run summaries for auditing changes.
10. Disabled-state guard: if the module is disabled, it does not run queue, cron, parsing, updates or product creation.

SAFE WORKFLOW
1. Install the module and leave it disabled until settings are checked.
2. Enable the module only after backup and configuration.
3. Create a supplier profile: name, base URL, currency, language and category/direct product URLs.
4. Open Parsing Rules and paste one real supplier product URL.
5. Scan the product page, check detected name, price, stock, SKU, image and description.
6. Save selected rules.
7. If using supplier category pages, scan one category page to detect product links.
8. Build a queue from category pages, direct product URLs or already linked products.
9. Process one small batch first.
10. Review results in Preview and New Products.
11. Manually apply selected updates or create selected new products.
12. After successful testing, use larger batches or cron.

LARGE CATALOG WORKFLOW
For thousands of products, use batch processing instead of one large run.
Recommended batch limit: 20-50 products.
Recommended request delay: 500-1500 ms.
Recommended cron interval: not more often than once every 5 minutes.
Use filters, sorting, pagination and row limits 50/100/200/500 to review data safely.
For regular updates after initial matching, prefer the linked-products mode instead of scanning all supplier categories every time.

QUEUE MODES
Scan categories: use when supplier list URLs are category/list pages. Requires product link XPath from the category page.
Direct product URLs: use when supplier list URLs are already product page URLs, one URL per line. Product link XPath is not required.
Check linked products: use after products are already matched with supplier URLs.
Scan new only: use when you want to discover new supplier products and skip existing matches.

PRICE HANDLING
Supplier prices are normalized before calculation. The module removes currency text, symbols, spaces, non-breaking spaces and supports comma/dot price formats.
The final OpenCart price is saved as a decimal value with a dot.
Currency must be selected from active OpenCart currencies.
The module supports supplier discount, store markup, minimum margin, maximum price change warning and rounding modes.

LANGUAGE HANDLING
Each supplier has source language and target OpenCart language.
Name, description, meta data, attributes and options are written to the selected OpenCart language.
Other languages are not overwritten unless the fill-all-languages setting is enabled.
The module does not automatically translate supplier content; translation should be a separate controlled AI/translation process.

NEW PRODUCTS
New products are not created silently by default in the safe workflow.
They appear in the New Products tab where the administrator can select rows and choose a target category.
A supplier-level forced category can be configured for all new products.
New products can be created disabled for manual checking before publication.

DATA TABLES
The module creates its own InnoDB utf8mb4 tables for suppliers, product links, queue, preview, new products, logs, history, images, exclusions, run reports, category map and runtime locks.

SECURITY AND PERFORMANCE
The archive is readable and not obfuscated.
No eval, shell execution or encoded payloads are used.
SQL values are cast or escaped through OpenCart database methods.
AJAX actions use standard admin route, user_token and permission checks.
Heavy work is moved into queue batches and cron to avoid long admin page loads.

CRON EXAMPLE
Endpoint:
https://YOUR-DOMAIN/index.php?route=extension/module/supplier_sync_parser_pro/cron
Authentication header:
X-CCP-Cron-Key: YOUR_TOKEN
Optional parameters:
&supplier_id=1
&limit=20
The legacy ?token=YOUR_TOKEN query parameter remains supported only for existing scheduled jobs.

MIROHOST NOTE
Do not run cron more often than once per 5 minutes.
Use separate cron fields: minutes */5, hours *, days of month *, months *, days of week *.
Command example:
curl -fsS --max-time 120 -H 'X-CCP-Cron-Key: YOUR_TOKEN' 'https://YOUR-DOMAIN/index.php?route=extension/module/supplier_sync_parser_pro/cron&limit=20' >/dev/null

VERSION 1.6.0 CHANGES
Improved full workflow from supplier creation to new product creation and price/stock update.
Added clearer six-step visual workflow.
Added supplier readiness indicators.
Improved Parsing Rules tab so manual XPath fields are secondary and the scan assistant is primary.
Shortened auto-detection dropdown labels so long XPath values do not overload the interface.
Added queue control groups: create queue, process queue and maintenance.
Added AJAX table refresh for server-side filter, pagination and sort navigation without full admin page reload.
Expanded About module tab with benefits, use cases and workflow explanation.
Kept previous fixes: Row size too large, PHP 7.4–8.5 urlencode warning, OpenCart currency selection, price normalization, supplier status switch, delete supplier icon, direct URL queue mode, category product-link detection and disabled-state guard.

LICENSE
License Server PRO integration is intentionally not included in this version by request.

VERSION 1.6.0 CHANGES
- All operational tabs are scoped to the selected supplier. Each supplier has independent rules, queue, preview, new products, mapping, history and logs.
- Added active supplier selector at the top of the module. Operational tabs are locked until a supplier is selected or saved.
- Added domain guard. By default the parser crawls only the base supplier domain and its www alias. It does not jump to unrelated domains or subdomains unless explicitly allowed.
- Added optional domain policy per supplier: strict base domain, base domain plus subdomains, or base domain plus manually allowed extra hosts.
- External CDN images may be used as image sources, but external HTML pages are not queued as product pages unless their host is explicitly allowed.

VERSION 1.6.0 CHANGES
- Reordered the main workflow tabs: Suppliers, Parsing Rules, Queue, Preview, New Products, Mapping, History, Logs, Diagnostics, Settings, About.
- Added per-row action selection in Preview so each found product can be updated, skipped, excluded, forced, or created individually.
- Renamed the queue stop flag to a clear queue processing pause and added help text.
- Fixed price guard logic: 0.0000 now really means no minimum/maximum price limit.
- Added clearer help for price limits and safety guards.

VERSION 1.6.0 CHANGES
- Added bulk queue actions for selected rows: return selected jobs to pending and delete selected jobs.
- Added bulk actions for selected new products: skip selected and return selected to pending.
- Increased queue creation limits for large supplier catalogs: sitemap can queue up to 5000 product URLs; category/site scans can create larger queues safely.
- Clarified large-catalog logic in the interface: tables are paginated, one visible page can show 50/100/200/500 rows, and product processing is done by AJAX batches.
- Kept batch processing safe: one processing request is capped to 100 queue jobs to reduce timeout and hosting overload risk.
- Added clearer preview explanation: supplier product name and OpenCart product name are shown side by side before applying price/stock changes.
- Fixed duplicate column declaration in the category mapping install SQL.

VERSION 1.6.0 CHANGES
- Safer price extraction: the parser now prefers real monetary values near currency symbols or price labels and ignores service numbers, phones, models and unrelated long numeric fragments.
- Invalid identifiers such as "грн.", "UAH", currency signs or plain prices are no longer used as SKU/model keys. Old bad supplier links with currency as SKU are cleared during installation/update.
- New products are filled only in the selected target language by default. The module no longer copies the same supplier text into all languages during product creation.
- Additional product images are disabled by default and filtered more strictly. This prevents importing pictures from related products, articles, banners, logos and service blocks. Enable extra images only after checking the exact image XPath.
- Existing queues with non-catalog URLs are skipped during processing instead of being parsed as products.
- Bulk actions now collect selected rows only from the current table and uncheck rows after the action to reduce accidental mass updates.

VERSION 1.6.0 CHANGES
- Selected queue processing buttons now process exactly selected queue rows, not the next general batch.
- Manual product linking is clearer: if the search field is empty, the module uses supplier SKU or supplier product name and suggests matching OpenCart products.
- After manual linking, the preview row refreshes immediately: linked product, price, quantity, price difference, status and match source are updated without page reload.
- New product SEO URL behavior is explained in the New Products tab. URLs are generated from the cleaned product name, transliterated, lowercased and made unique in seo_url.
- Manual link price guard now checks both price increase and price decrease against the supplier maximum price change limit.

VERSION 1.6.0 CHANGES
- Fixed runtime error in diagnostics: normalizeComparableName() was missing from parser library.
- Product-rule auto-detection now rejects category/list URLs and asks for a real product card URL.
- XML/YML Google Merchant feeds with g: namespace are now supported for field auto-detection and parsing.
- Recommended Google feed mapping: //item, g:title, g:description, g:link, g:image_link, g:additional_image_link, g:mpn, g:brand, g:product_type, g:availability, g:price.
- Image import now keeps AVIF extension when supplier feed contains AVIF images.
