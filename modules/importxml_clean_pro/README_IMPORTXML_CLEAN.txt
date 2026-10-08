ImportXML Clean PRO v1.4.2
Author: CodeCart PRO
Author site: https://codecartpro.com
Compatibility: CodeCart 3.0.5.2 / ocStore 3.0.4.1 / OpenCart 3.x / PHP 7.4–8.5

Purpose:
ImportXML Clean is an OpenCart/ocStore 3.x feed extension for importing supplier XML/YML, CSV/TXT and XLSX catalogs into the store catalog.
The main workflow is safe manual import from the admin panel: upload the file, choose supplier profile, choose language, run the preview without database writes, review the report, then apply the import.
Cron is an additional function only. It is intended mainly for scheduled price, stock and product status updates from the supplier XML link after the initial manual import has already been checked.

What the module can do:
- Import products from supplier XML/YML, CSV/TXT and XLSX files.
- Show a safe preview before writing to the database: products to update, new products, not found products, missing supplier products and old/new values for price, quantity, status, supplier purchase price and special price.
- Import and update categories.
- Import images, manufacturers and attributes when the XML contains them.
- Fill standard OpenCart/ocStore product card fields such as model, SKU, UPC, EAN, JAN, ISBN, MPN, location, quantity, stock status, price, weight, dimensions, sort order, status and SEO fields.
- Work with several languages.
- Convert prices through the standard OpenCart currency system when a currency tag is configured.
- Detect the main product category safely: first product_to_category.main_category = 1, then product.main_category_id, then fallback through product_to_category.
- Save Google Product Category ID when the store has a supported category field or compatible googleshopping_category table.
- Run cron as an additional update mechanism.

Recommended workflow:
1. Install the module.
2. Enable the module manually and save settings.
3. Create and check supplier profile.
4. Run safe preview with a small test XML/YML, CSV or XLSX file.
5. Apply import only after checking the preview report.
6. Check created products, categories, SEO fields, prices and images.
7. Only after that enable cron if scheduled updates are needed.
8. Keep cron mode as “Prices, availability, stock and status of existing products” for regular updates.

Why cron is separated from manual import:
Manual import is the main catalog import scenario. It is controlled by the administrator and is suitable for creating or fully updating products.
Cron is secondary because automatic full imports can be heavy and risky on large catalogs. The safe default cron mode updates prices, availability, quantity/stock, stock status and product status of already imported products.

Advantages:
- Safer than direct core edits.
- Installed as an extension/feed item in Promotion Channels / Feeds.
- Disabled by default after installation.
- Does not connect frontend CSS or JS.
- Does not globally inject admin CSS/JS through common/header.twig.
- Uses isolated admin styles.
- Supports UTF-8 without BOM, InnoDB and utf8mb4.
- Includes diagnostics, service tools, settings export/import, reset defaults and log cleanup.

v1.4.2 compatibility and hardening notes:
- Added explicit server-side user_token checks for admin AJAX/service actions.
- Added 30-minute lifetime for the safe import preview token.
- Added write-failure handling for temporary import files.
- Improved CSV/TXT/XLSX text normalization for UTF-8 and Windows-1251 supplier files.
- Full update of existing products now preserves images, attributes, options, related products, rewards, layouts, downloads and filters unless those sections are explicitly supplied by the import data. This prevents accidental data loss when supplier files contain only a partial product dataset.

- Removed PHP 8.5 deprecated curl_close() from cron XML download.
- Added explicit fgetcsv() escape arguments for PHP 8.4+ without changing the previous CSV parsing mode.
- Declared StdE registry properties to remove PHP 8.2+ dynamic-property deprecations.
- Added whitelist validation for dynamic table/column/index identifiers used by schema diagnostics and migration helpers.
- Removed duplicate language keys in en-gb, ru-ru and uk-ua.
- Normalized the invalid legacy 1.3.17 version to the valid 1.4.2 release scheme.
