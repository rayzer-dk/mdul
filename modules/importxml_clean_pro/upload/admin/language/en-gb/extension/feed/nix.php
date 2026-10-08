<?php

/**
 * @category   OpenCart
 * @package    ImportXML Clean PRO v1.4.3
 * @copyright  CodeCart PRO, https://codecartpro.com
 */

// Header
$_['heading_title'] = '<span style="color:#0057b7;font-weight:700;">ImportXML Clean</span> <span style="display:inline-block;background:#ffd700;color:#0057b7;border-radius:12px;padding:2px 10px;font-size:12px;line-height:1.2;vertical-align:middle;font-weight:700;margin-left:6px;">PRO</span> <span style="display:inline-block;background:#eef3f8;color:#445;border-radius:10px;padding:2px 8px;font-size:11px;line-height:1.2;vertical-align:middle;margin-left:5px;">v1.4.3</span>';

// Text
$_['text_success']	 = 'Module settings updated!';
$_['text_edit']			 = 'Module settings';
$_['btn_save']			 = 'Save';
$_['btn_cancel']		 = 'Back';

$_['text_copyright'] = '<p>Version: <b>%s</b></p>'
	. '<p>Author: <a href="https://codecartpro.com" target="_blank" rel="noopener">CodeCart PRO</a></p>';

// Error
$_['error_permission'] = 'You do not have permission to manage this module!';


// Settings
$_['text_part_settings'] = 'Suppliers and settings';

$_['text_enabled'] = 'Enabled';
$_['text_disabled'] = 'Disabled';
$_['help_status'] = 'When disabled, settings can be edited, but import actions are blocked.';
$_['error_module_disabled'] = 'Module is disabled. Enable it in module settings and save before starting import.';
$_['entry_status']			 = 'Status';
$_['btn_save_settings']	 = 'Save';
$_['btn_add_supplier']	 = 'Add Profile';

// Suppliers list
$_['entry_supplier_list'] = 'Supplier profiles';


// Delete
$_['msg_supplier_delete_success'] = 'Profile deleted';



// supplier form
$_['supplier_modal_title']			 = 'Profile settings';
$_['supplier_fieldset_settings'] = 'XML document settings';
$_['entry_supplier_name']				 = 'Profile name';
$_['entry_supplier_link_price']	 = 'Price Update XML Link';
$_['entry_supplier_link']				 = 'Link to an XML document on the web';
$_['entry_supplier_markup']			 = 'Markup (%)';

$_['supplier_fieldset_attributes']				 = 'Tag attributes in XML document';
$_['entry_attribute_parent_id']						 = 'Attributes to designate the category\'s parent_id';
$_['supplier_fieldset_tags']							 = 'XML document tags';
$_['entry_tag_product_name']							 = 'Title tag';
$_['entry_tag_product_description']				 = 'Description tag';
$_['entry_tag_product_model']							 = 'Model tag';
$_['entry_tag_product_sku']								 = 'SKU tag';
$_['entry_tag_product_price_purchasing']	 = 'Purchasing price tag';
$_['entry_tag_product_price_rrp']					 = 'RRP tag';
$_['entry_tag_product_quantity']					 = 'Quantity tag';
$_['entry_tag_product_images']						 = 'Image tags';
$_['entry_tag_product_category']					 = 'Product category tag';
$_['entry_tag_product_manufacturer_name']	 = 'Manufacturer tag';
$_['entry_tag_product_attributes']				 = 'Attribute tags';
$_['btn_supplier_modal_save']							 = 'Save Profile';
$_['msg_supplier_success']								 = 'Profile saved';
$_['msg_supplier_error']									 = 'Error! Check all form fields!';
$_['error_supplier_name_empty']						 = 'Please enter a profile name!';
$_['error_supplier_markup_empty']					 = 'Enter markup!';

$_['error_tag_empty']				 = '%s is required!';
$_['error_attribute_empty']	 = '%s is required!';



// Import
$_['text_select_option'] = '-- Select --';

$_['text_part_import'] = 'Manual import';
//$_['entry_primary_language'] = ' - select primary language for this import';
$_['entry_language']						 = ' - select primary language for this import';
$_['entry_file'] = 'XML file for manual import';
$_['help_file'] = 'Normal import works by uploading an XML file from your computer. Supplier links are used separately in the supplier profile only for cron updates.';
$_['error_file_main_not_saved']	 = 'The XML file for the main language was not saved correctly on the site';
$_['entry_xmllink']							 = 'Link to an XML file on the web';

$_['btn_file']							 = 'Select file from computer';
$_['file_not_choosen']			 = 'No file selected';
$_['xor']										 = 'OR';
$_['entry_copy_description'] = 'Copy product and category descriptions to languages that do not have an XML file specified';
$_['entry_copy_attributes']	 = 'Copy product attributes to languages that do not have an XML file specified';
$_['help_copy_attributes']	 = 'Attributes can only be copied if descriptions are copied';
$_['entry_supplier']				 = 'Supplier';
$_['error_supplier']				 = 'Select a Supplier';
$_['btn_import'] = 'Run manual import';
$_['text_import_options']		 = 'Import options';
$_['entry_delete_all']			 = 'Clear catalog';
$_['help_delete_all']				 = 'All products, categories, attributes and manufacturers will be deleted from the database before the import starts';
$_['entry_update_if_exist']	 = 'Overwrite all data for existing products';
$_['help_update_if_exist']	 = 'This option will override any changes you may have made to product names and descriptions. Without this checkbox, products that are already in the database will not be overwritten.';

$_['error_warning']				 = 'Error submitting form! Examine all fields for errors!';
$_['error_import_fatal']	 = 'The import file contains serious errors';
$_['error_import_no_tags'] = 'The import file does not contain the required tags';


// Import Processing
$_['status_started'] = '<p>Import has started. DO NOT close this page until the import is complete!!</p>';
$_['statistics']		 = '<p>Items processed: <b>%d</b></p>';

$_['statistics_console'] = '<p>Items processed in the current background Request: <b>%1$d</b></p>'
	. '<p>Items processed during the current import <b>%2$d</b></p>';

$_['success_import']	 = 'Import completed successfully';
$_['continued_import'] = 'Import continues...';

$_['import_placeholder_name'] = 'No name';

// CodeCart PRO additions
$_['error_language'] = 'Select the primary import language';
$_['error_model_or_sku_required'] = 'Specify the model tag or the SKU tag. One of these fields is enough.';
$_['error_price_tag_required'] = 'Specify the purchase price tag or the RRP/supplier price tag. One of these fields is enough.';
$_['text_diagnostics'] = 'Diagnostics';
$_['btn_check_update'] = 'Update module';
$_['btn_clear_logs'] = 'Clear logs';
$_['text_update_info'] = 'Current version: %s. New version and license checks are handled through the author site: %s';
$_['text_logs_cleared'] = 'Deleted log and temporary XML files: %d';
$_['text_about_module'] = 'About module';
$_['text_author'] = 'Author';
$_['text_version'] = 'Version';

$_['error_simplexml_missing'] = 'The PHP SimpleXML extension is disabled on the server. XML import cannot work without it.';

$_['entry_delete_data_on_uninstall'] = 'Delete module data on uninstall';
$_['help_delete_data_on_uninstall'] = 'Disabled by default. If enabled, uninstall will remove supplier profiles and NIX supplier columns from products/categories. Leave disabled if you may need audit data or rollback.';
$_['text_confirm_supplier_delete'] = 'Delete this supplier profile? The action is applied immediately and cannot be undone.';
$_['text_confirm_clear_logs'] = 'Delete NIX log files and temporary XML files?';
$_['error_request_method'] = 'Invalid request method. Repeat the action from the module interface.';
$_['error_supplier_delete_id'] = 'Supplier profile ID was not received. Refresh the page and try again.';
$_['error_ajax_failed'] = 'AJAX request failed. Check user_token, permissions and PHP error log.';
$_['placeholder_supplier_name'] = 'Example: Main supplier XML';
$_['help_supplier_profile_examples'] = 'Example: profile name “Main supplier XML”, markup “15”. Markup is applied to the purchase price if the purchase price tag is configured.';
$_['help_supplier_tags_examples'] = 'Examples: name, model, vendorCode, price, optPrice, quantity, picture, categoryId, vendor, description, param.';
$_['text_edit_import'] = 'Manual XML file import';

$_['text_confirm_delete_all_import'] = 'The “Clear catalog” option is enabled. All imported supplier products/categories can be deleted before import. Continue?';

// Product card fields and sortable tables
$_['supplier_fieldset_product_card_fields'] = 'Additional product card fields';
$_['help_supplier_product_card_fields'] = 'These fields are optional. Fill in only XML tags that really exist in the supplier feed. Values are saved into standard OpenCart/ocStore product fields.';
$_['entry_tag_product_meta_h1'] = 'HTML H1 tag';
$_['entry_tag_product_meta_title'] = 'Meta Title tag';
$_['entry_tag_product_meta_description'] = 'Meta Description tag';
$_['entry_tag_product_meta_keyword'] = 'Meta Keywords tag';
$_['entry_tag_product_tag'] = 'Product tags tag';
$_['entry_tag_product_currency'] = 'Currency tag';
$_['entry_tag_product_upc'] = 'UPC tag';
$_['entry_tag_product_ean'] = 'EAN tag';
$_['entry_tag_product_jan'] = 'JAN tag';
$_['entry_tag_product_isbn'] = 'ISBN tag';
$_['entry_tag_product_mpn'] = 'MPN tag';
$_['entry_tag_product_location'] = 'Location tag';
$_['entry_tag_product_minimum'] = 'Minimum quantity tag';
$_['entry_tag_product_subtract'] = 'Subtract stock tag';
$_['entry_tag_product_stock_status_id'] = 'Stock status tag';
$_['entry_tag_product_shipping'] = 'Shipping tag';
$_['entry_tag_product_tax_class_id'] = 'Tax class tag';
$_['entry_tag_product_length'] = 'Length tag';
$_['entry_tag_product_width'] = 'Width tag';
$_['entry_tag_product_height'] = 'Height tag';
$_['entry_tag_product_length_class_id'] = 'Length class tag';
$_['entry_tag_product_weight'] = 'Weight tag';
$_['entry_tag_product_weight_class_id'] = 'Weight class tag';
$_['entry_tag_product_status'] = 'Product status tag';
$_['entry_tag_product_sort_order'] = 'Sort order tag';
$_['entry_tag_product_google_product_category_id'] = 'Google Product Category ID tag';
$_['column_supplier_id'] = 'ID';
$_['column_supplier_name'] = 'Supplier';
$_['column_supplier_markup'] = 'Markup, %';
$_['text_no_suppliers'] = 'No supplier profiles yet';
$_['entry_tag_product_date_available'] = 'Date available tag';
$_['entry_tag_product_points'] = 'Points tag';

// Cron
$_['text_extension'] = 'Marketing channels';
$_['text_cron_settings'] = 'Additional: cron update';
$_['entry_cron_status'] = 'Enable cron';
$_['entry_cron_token'] = 'Secret token';
$_['entry_cron_supplier'] = 'Cron supplier';
$_['entry_cron_language'] = 'Cron main language';
$_['entry_cron_command'] = 'Cron command';
$_['entry_cron_url'] = 'Manual test URL';
$_['entry_cron_options'] = 'Cron import options';
$_['entry_cron_update_if_exist'] = 'Update existing products';
$_['entry_cron_copy_description'] = 'Copy descriptions to languages without separate XML';
$_['entry_cron_copy_attributes'] = 'Copy attributes to languages without separate XML';
$_['entry_cron_delete_all'] = 'Clear catalog before cron import';
$_['help_cron_status'] = 'Cron runs an update from the supplier XML link field. Do not run it more often than once every 5 minutes. For large XML files, use nighttime execution.';
$_['help_cron_token'] = 'The secret token protects cron from external execution. Update your hosting cron command after changing it.';
$_['help_cron_command'] = 'Use this command in the hosting cron command field. Set minutes and hours separately in your hosting panel.';
$_['help_cron_delete_all'] = 'Dangerous option. It is used only in full cron import mode. For regular price and stock updates it should stay disabled.';
$_['error_cron_token'] = 'Invalid or empty cron token.';
$_['error_cron_disabled'] = 'Cron is disabled or the extension is disabled.';
$_['error_cron_empty_link'] = 'The selected supplier has no XML link for cron.';
$_['error_cron_download'] = 'Failed to download XML: %s';
$_['error_cron_write_file'] = 'Failed to write temporary XML file: %s';
$_['text_cron_multilang_hint'] = 'For multiple XML files, enter links line by line: language_id=XML_URL. A single link is used for the main language.';
$_['text_cron_performance_warning'] = 'Warning: cron can be a heavy operation. The default safe mode updates prices, availability, quantity/stock, stock status and product status for existing products. Full cron import can download XML, create categories, update images, descriptions and SEO, so do not run it during peak hours.';

$_['placeholder_supplier_link_price'] = 'XML URL or language_id=XML_URL';

$_['help_supplier_link_price'] = 'For cron: one XML URL or a separate language_id=XML_URL line for each language.';

$_['text_quick_xml_tags'] = 'Quick XML tag examples';

$_['help_quick_xml_tags'] = 'Click a tag after focusing an input field to paste the example into that field.';

$_['btn_generate_token'] = 'Generate token';

$_['btn_copy_cron_command'] = 'Copy command';

$_['btn_copy_cron_url'] = 'Copy URL';

$_['text_copied'] = 'Copied.';

$_['placeholder_cron_token'] = 'Example: 48 random hexadecimal characters';

$_['error_ajax_failed_detail'] = 'AJAX request failed. HTTP status: %s. Server response: %s';
// CodeCart PRO production additions v1.4.3
$_['btn_export_settings'] = 'Export settings';
$_['btn_reset_settings'] = 'Reset settings';
$_['help_service_actions'] = 'Export saves settings and supplier profiles to JSON. Reset returns system settings to safe defaults, but does not remove supplier profiles.';
$_['text_confirm_reset_settings'] = 'Reset module settings to safe defaults? Supplier profiles will remain in the database.';
$_['text_settings_reset'] = 'Module settings were reset. Module disabled, cron disabled, new token generated.';
$_['diag_module_enabled'] = 'Module is enabled.';
$_['diag_module_disabled'] = 'Module is disabled: import is blocked until manual enable and settings save.';
$_['diag_table_suppliers'] = 'Supplier profile table nix_suppliers.';
$_['diag_product_supplier_id'] = 'product.nix_supplier_id field for linking product to supplier.';
$_['diag_product_supplier_product_id'] = 'product.nix_supplier_product_id field for supplier external product ID.';
$_['diag_simplexml'] = 'PHP SimpleXML extension for reading XML files.';
$_['diag_cache_writable'] = 'Cache directory is writable for temporary XML files.';
$_['diag_logs_writable'] = 'Logs directory is writable.';
$_['diag_main_category_ptc'] = 'Main category through product_to_category.main_category.';
$_['diag_main_category_product'] = 'Main category through product.main_category_id.';
$_['diag_google_product_category'] = 'Google Product Category ID through category.google_product_category_id or googleshopping_category.';
$_['entry_mirohost_minutes'] = 'Mirohost: minutes';
$_['entry_mirohost_hours'] = 'Mirohost: hours';
$_['entry_mirohost_days'] = 'Mirohost: days of month';
$_['entry_mirohost_months'] = 'Mirohost: months';
$_['entry_mirohost_weekdays'] = 'Mirohost: weekdays';
$_['help_mirohost_schedule'] = 'Recommended safe Mirohost example: minutes */30, hours *, days of month *, months *, weekdays *. Do not schedule more often than once every 5 minutes.';
// CodeCart PRO import/export additions v1.4.3
$_['btn_import_settings'] = 'Import settings';
$_['help_import_settings'] = 'Choose a JSON file created by Export settings. Import replaces system settings and supplier profiles. Make a backup before importing.';
$_['error_import_settings_failed'] = 'Settings import failed. Check file format and permissions.';
$_['error_import_settings_file'] = 'Settings file is not selected. Choose an exported JSON file.';
$_['error_import_settings_size'] = 'Settings file is too large. Maximum is 1 MB.';
$_['error_import_settings_json'] = 'Settings file has invalid JSON structure.';
$_['text_import_settings_success'] = 'Settings imported. Supplier profiles: %d.';
$_['text_defaults_restored'] = 'Module settings restored to defaults. Module disabled, cron disabled, new token generated.';
$_['diag_field_product_supplier_id'] = 'product.nix_supplier_id field for linking product to supplier.';
$_['diag_field_product_supplier_product_id'] = 'product.nix_supplier_product_id field for supplier external product ID.';
$_['diag_google_category'] = 'Google Product Category ID through category.google_product_category_id or googleshopping_category.';

// CodeCart PRO v1.4.3 UI and warning additions
$_['warning_offer_without_id'] = 'Warning: XML contains an offer without the id attribute. The item was skipped.';
$_['warning_offer_missing_tag'] = 'Warning: offer ID %s was skipped because required tag %s is missing.';
$_['warning_offer_missing_required_tags'] = 'Warning: offer ID %s was skipped because required tags for full import are missing.';
// CodeCart PRO service UI additions v1.4.3
$_['text_service_tools'] = 'Service tools';
$_['btn_apply_import_settings'] = 'Apply import';

$_['text_default'] = 'Default store';

$_['text_manual_import_primary'] = 'The main workflow is normal manual XML import through the “Manual import” button. Cron is only an additional function for automatic price, availability, quantity/stock, stock status and product status updates from the supplier XML link.';

$_['entry_cron_mode'] = 'Cron mode';

$_['text_cron_mode_price_stock'] = 'Prices, availability, stock and status of existing products';

$_['text_cron_mode_full'] = 'Full import from supplier link';

$_['help_cron_mode'] = 'The recommended cron mode updates prices, available attribute, quantity/stock, stock_status_id and product status for existing products. It does not create new products and does not overwrite descriptions, images, categories or SEO. Use full cron import only deliberately, preferably at night.';

$_['help_cron_secondary_function'] = 'Cron does not replace normal import. First run a manual XML import, check products, categories and mapped fields, then use cron later for regular price and availability updates.';

// CodeCart PRO v1.4.3 supplier UI corrections
$_['text_settings_main'] = 'Main settings';
$_['text_supplier_settings'] = 'Supplier profiles';
$_['text_cron_settings_short'] = 'Cron update';
$_['help_supplier_settings'] = 'Create and edit supplier profiles here: XML link for cron, markup and XML tag mapping to product edit fields. Manual import and cron will not work without a supplier profile.';
$_['help_supplier_list'] = 'Click “Add profile” to configure supplier XML tags. To edit an existing profile, click the supplier name in the table.';
$_['btn_manage_suppliers'] = 'Configure suppliers';
$_['help_import_supplier_select'] = 'If the required supplier is missing, create a supplier profile in settings first. The profile defines XML tags for product fields, prices, availability, categories, SEO and the cron link.';


// CodeCart PRO v1.4.3 safe supplier import additions
$_['btn_preview_import'] = 'Check changes without writing';
$_['btn_apply_safe_import'] = 'Apply import after preview';
$_['help_safe_import_buttons'] = 'Run the check first. The module will show what will be updated, created, skipped and which supplier products are missing. Apply the import only after reviewing the report.';
$_['help_file_formats'] = 'Supported formats: XML, YML/YAML, CSV/TXT and XLSX. For CSV/XLSX, the first row must contain field names: id, name, model, sku/vendorCode, price, optPrice, special_price, quantity, categoryId.';
$_['text_confirm_apply_after_preview'] = 'Apply import to the database? It is recommended to run the no-write preview first.';
$_['text_preview_ready'] = 'Preview complete. No database writes were performed.';
$_['text_preview_statistics'] = 'Total rows: %d. Will be updated: %d. New products: %d. Not found in store: %d. Missing from supplier: %d. Price changed: %d. Quantity changed: %d. Status changed: %d. Supplier purchase price changed: %d. Special price changed: %d.';
$_['text_preview_changed_products'] = 'Products with changes';
$_['text_preview_new_products'] = 'New supplier positions';
$_['text_preview_not_found_products'] = 'Not found in store for update';
$_['text_preview_missing_products'] = 'Products missing from supplier file';
$_['text_preview_no_rows'] = 'No rows to display.';
$_['text_preview_action_update'] = 'Will be updated';
$_['text_preview_action_create'] = 'Will be created';
$_['text_preview_action_skip_not_found'] = 'Skip: cron/price_stock does not create products';
$_['text_preview_action_skip_update_disabled'] = 'Skip: updating existing products is disabled';
$_['text_preview_action_missing'] = 'Exists in store, missing from supplier file';
$_['help_preview_apply_warning'] = 'The report displays the first 200 rows of each section. Counters are calculated for the whole file. In cron/price_stock mode, the module updates only price, quantity, stock_status_id, status and supplier purchase price for existing products.';
$_['entry_tag_product_special_price'] = 'Special price tag';
$_['help_tag_product_special_price'] = 'For example special_price, special, sale_price, discount_price or oldprice. If the tag exists and the price is greater than 0, the module writes product_special. If the tag exists and the value is 0 or empty, specials are cleared during full update.';
$_['error_ziparchive_missing'] = 'The PHP ZipArchive extension is not installed. XLSX cannot be read.';
$_['field_product_supplier_price'] = 'Field product.nix_supplier_price for supplier purchase price';
$_['text_run_preview_first'] = 'Run the no-write preview first. After a successful report, the apply button will become available.';

$_['error_preview_token'] = 'Safe import error: run the preview without writing first and do not change the file or settings before applying.';

// CodeCart PRO production fixes v1.4.3
$_['error_file'] = 'Select an XML, YML/YAML, CSV/TXT or XLSX import file.';
$_['error_file_upload_code'] = 'The file was not uploaded. PHP upload error code: %s. Check file size and upload_max_filesize/post_max_size settings.';
$_['error_file_extension'] = 'Invalid file format. Allowed formats: %s.';
$_['error_file_size'] = 'The file is too large. Maximum manual import size: %s.';
$_['text_about_module_description'] = 'ImportXML Clean imports products, categories, images, manufacturers and attributes from supplier XML/YML, CSV and XLSX files. Before applying changes, it can run a safe preview without writing to the database.';
$_['text_about_module_benefit'] = 'Module value: initial catalog import, safe old/new comparison before applying changes, updates for price, stock, status, supplier purchase price and special prices. Cron can be used as an additional function to update only existing products without overwriting descriptions, images, categories or SEO.';
$_['diag_ziparchive'] = 'PHP ZipArchive extension for XLSX import.';
$_['column_offer_id'] = 'Supplier ID';
$_['column_product_id'] = 'Product ID';
$_['column_name'] = 'Name';
$_['column_model'] = 'Model';
$_['column_sku'] = 'SKU';
$_['column_price_old'] = 'Price old';
$_['column_price_new'] = 'Price new';
$_['column_quantity_old'] = 'Qty old';
$_['column_quantity_new'] = 'Qty new';
$_['column_status_old'] = 'Status old';
$_['column_status_new'] = 'Status new';
$_['column_action'] = 'Action';

$_['error_user_token'] = 'The administrator session has expired or user_token is invalid. Refresh the module page and try again.';

$_['error_preview_expired'] = 'The safe preview has expired. Run the no-write change preview again before applying the import.';

$_['error_file_write'] = 'Cannot write temporary import file: %s. Check permissions for system/storage/cache.';
$_['diag_php_version'] = 'PHP %s; supported range: 7.4–8.5';
