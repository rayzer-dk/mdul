<?php
class ModelExtensionModuleSupplierSyncParserPro extends Model {
    public function install($schema_version = '1.6.4') {
        $charset = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_supplier` (
            `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `status` tinyint(1) NOT NULL DEFAULT '0',
            `base_url` varchar(512) NOT NULL DEFAULT '',
            `list_urls` mediumtext NOT NULL,
            `product_url_xpath` TEXT NOT NULL,
            `list_category_xpath` TEXT NOT NULL,
            `product_url_attr` varchar(64) NOT NULL DEFAULT 'href',
            `next_page_xpath` TEXT NOT NULL,
            `sku_xpath` TEXT NOT NULL,
            `model_xpath` TEXT NOT NULL,
            `name_xpath` TEXT NOT NULL,
            `price_xpath` TEXT NOT NULL,
            `stock_xpath` TEXT NOT NULL,
            `category_xpath` TEXT NOT NULL,
            `description_xpath` TEXT NOT NULL,
            `manufacturer_xpath` TEXT NOT NULL,
            `image_xpath` TEXT NOT NULL,
            `additional_images_xpath` TEXT NOT NULL,
            `image_attr` varchar(64) NOT NULL DEFAULT 'src',
            `currency_code` varchar(16) NOT NULL DEFAULT '',
            `source_language_code` varchar(16) NOT NULL DEFAULT '',
            `target_language_id` int(11) NOT NULL DEFAULT '0',
            `discount_percent` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `markup_percent` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `max_price_change_percent` decimal(15,4) NOT NULL DEFAULT '50.0000',
            `rounding_mode` varchar(32) NOT NULL DEFAULT 'two',
            `default_category_id` int(11) NOT NULL DEFAULT '0',
            `force_new_category_id` int(11) NOT NULL DEFAULT '0',
            `default_stock_status_id` int(11) NOT NULL DEFAULT '0',
            `create_new` tinyint(1) NOT NULL DEFAULT '0',
            `create_new_status` tinyint(1) NOT NULL DEFAULT '0',
            `auto_apply_existing` tinyint(1) NOT NULL DEFAULT '0',
            `auto_create_new` tinyint(1) NOT NULL DEFAULT '0',
            `update_price` tinyint(1) NOT NULL DEFAULT '1',
            `update_stock` tinyint(1) NOT NULL DEFAULT '1',
            `request_delay_ms` int(11) NOT NULL DEFAULT '500',
            `user_agent` varchar(255) NOT NULL DEFAULT '',
            `settings` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`supplier_id`),
            KEY `status` (`status`)
        ) " . $charset);

        $this->normalizeSupplierTextColumns();

        $this->addColumnIfMissing('ccp_ssp_supplier', 'list_category_xpath', "TEXT NOT NULL AFTER `product_url_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'category_xpath', "TEXT NOT NULL AFTER `stock_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'max_price_change_percent', "decimal(15,4) NOT NULL DEFAULT '50.0000' AFTER `markup_percent`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'auto_apply_existing', "tinyint(1) NOT NULL DEFAULT '0' AFTER `create_new_status`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'auto_create_new', "tinyint(1) NOT NULL DEFAULT '0' AFTER `auto_apply_existing`");

        $this->addColumnIfMissing('ccp_ssp_supplier', 'model_xpath', "TEXT NOT NULL AFTER `sku_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'ean_xpath', "TEXT NOT NULL AFTER `model_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'upc_xpath', "TEXT NOT NULL AFTER `ean_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'mpn_xpath', "TEXT NOT NULL AFTER `upc_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'attribute_row_xpath', "TEXT NOT NULL AFTER `additional_images_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'attribute_name_xpath', "TEXT NOT NULL AFTER `attribute_row_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'attribute_value_xpath', "TEXT NOT NULL AFTER `attribute_name_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'option_row_xpath', "TEXT NOT NULL AFTER `attribute_value_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'option_name_xpath', "TEXT NOT NULL AFTER `option_row_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'option_value_xpath', "TEXT NOT NULL AFTER `option_name_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'meta_description_xpath', "TEXT NOT NULL AFTER `description_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'meta_keyword_xpath', "TEXT NOT NULL AFTER `meta_description_xpath`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'source_language_code', "varchar(16) NOT NULL DEFAULT '' AFTER `currency_code`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'target_language_id', "int(11) NOT NULL DEFAULT '0' AFTER `source_language_code`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'price_formula_mode', "varchar(32) NOT NULL DEFAULT 'retail_discount_markup' AFTER `target_language_id`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'min_margin_percent', "decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER `markup_percent`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'missing_policy', "varchar(32) NOT NULL DEFAULT 'report' AFTER `update_stock`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'existing_description_mode', "varchar(32) NOT NULL DEFAULT 'keep' AFTER `missing_policy`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'import_attributes', "tinyint(1) NOT NULL DEFAULT '0' AFTER `existing_description_mode`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'import_options', "tinyint(1) NOT NULL DEFAULT '0' AFTER `import_attributes`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'fill_all_languages', "tinyint(1) NOT NULL DEFAULT '0' AFTER `import_options`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'require_test_success', "tinyint(1) NOT NULL DEFAULT '0' AFTER `fill_all_languages`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'last_test_ok', "tinyint(1) NOT NULL DEFAULT '0' AFTER `require_test_success`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'last_test_date', "datetime DEFAULT NULL AFTER `last_test_ok`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'force_new_category_id', "int(11) NOT NULL DEFAULT '0' AFTER `default_category_id`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'new_tax_class_id', "int(11) NOT NULL DEFAULT '0' AFTER `create_new_status`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'new_minimum', "int(11) NOT NULL DEFAULT '1' AFTER `new_tax_class_id`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'new_subtract', "tinyint(1) NOT NULL DEFAULT '1' AFTER `new_minimum`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'new_shipping', "tinyint(1) NOT NULL DEFAULT '1' AFTER `new_subtract`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'new_sort_order', "int(11) NOT NULL DEFAULT '0' AFTER `new_shipping`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'new_store_id', "int(11) NOT NULL DEFAULT '0' AFTER `new_sort_order`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'seo_url_mode', "varchar(32) NOT NULL DEFAULT 'all_languages' AFTER `new_store_id`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'min_new_price', "decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER `seo_url_mode`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'max_new_price', "decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER `min_new_price`");

        $this->addColumnIfMissing('ccp_ssp_supplier', 'source_type', "varchar(32) NOT NULL DEFAULT 'html' AFTER `base_url`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_url', "varchar(1024) NOT NULL DEFAULT '' AFTER `list_urls`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_file', "varchar(512) NOT NULL DEFAULT '' AFTER `feed_url`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_format', "varchar(16) NOT NULL DEFAULT 'auto' AFTER `feed_file`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_item_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_format`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_url_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_item_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_sku_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_url_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_name_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_sku_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_price_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_name_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_stock_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_price_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_quantity_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_stock_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_category_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_quantity_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_description_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_category_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_manufacturer_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_description_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_image_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_manufacturer_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_ean_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_image_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_upc_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_ean_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'feed_mpn_path', "varchar(255) NOT NULL DEFAULT '' AFTER `feed_upc_path`");
        $this->addColumnIfMissing('ccp_ssp_supplier', 'import_additional_images', "tinyint(1) NOT NULL DEFAULT '0' AFTER `image_attr`");
        if ($this->tableExists('ccp_ssp_product_link')) {
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_product_link` SET supplier_sku = '' WHERE LOWER(TRIM(supplier_sku)) IN ('грн','грн.','uah','₴','usd','eur')");
        }


        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_product_link` (
            `link_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL,
            `product_id` int(11) NOT NULL DEFAULT '0',
            `supplier_product_url` varchar(1024) NOT NULL DEFAULT '',
            `supplier_sku` varchar(255) NOT NULL DEFAULT '',
            `supplier_name` varchar(512) NOT NULL DEFAULT '',
            `last_supplier_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `last_purchase_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `last_sale_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `last_stock_text` varchar(255) NOT NULL DEFAULT '',
            `last_quantity` int(11) NOT NULL DEFAULT '0',
            `last_status` varchar(64) NOT NULL DEFAULT '',
            `last_error` mediumtext NOT NULL,
            `last_checked` datetime DEFAULT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`link_id`),
            KEY `supplier_product` (`supplier_id`,`product_id`),
            KEY `supplier_sku` (`supplier_id`,`supplier_sku`(120)),
            KEY `supplier_url` (`supplier_id`,`supplier_product_url`(120))
        ) " . $charset);

        $this->addColumnIfMissing('ccp_ssp_product_link', 'last_purchase_price', "decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER `last_supplier_price`");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_queue` (
            `queue_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL,
            `action` varchar(64) NOT NULL,
            `supplier_product_url` varchar(1024) NOT NULL DEFAULT '',
            `payload` mediumtext NOT NULL,
            `status` varchar(32) NOT NULL DEFAULT 'pending',
            `attempts` int(11) NOT NULL DEFAULT '0',
            `last_error` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`queue_id`),
            KEY `status_supplier` (`status`,`supplier_id`),
            KEY `supplier_action_url` (`supplier_id`,`action`,`supplier_product_url`(120))
        ) " . $charset);

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_new_product` (
            `new_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL,
            `supplier_product_url` varchar(1024) NOT NULL DEFAULT '',
            `supplier_sku` varchar(255) NOT NULL DEFAULT '',
            `supplier_name` varchar(512) NOT NULL DEFAULT '',
            `parsed_data` mediumtext NOT NULL,
            `status` varchar(64) NOT NULL DEFAULT 'pending',
            `product_id` int(11) NOT NULL DEFAULT '0',
            `last_error` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`new_id`),
            KEY `supplier_status` (`supplier_id`,`status`),
            KEY `supplier_url` (`supplier_id`,`supplier_product_url`(120))
        ) " . $charset);

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_review` (
            `review_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL DEFAULT '0',
            `product_id` int(11) NOT NULL DEFAULT '0',
            `supplier_product_url` varchar(1024) NOT NULL DEFAULT '',
            `supplier_sku` varchar(255) NOT NULL DEFAULT '',
            `supplier_name` varchar(512) NOT NULL DEFAULT '',
            `supplier_category` varchar(512) NOT NULL DEFAULT '',
            `supplier_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `sale_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `supplier_stock_text` varchar(255) NOT NULL DEFAULT '',
            `supplier_quantity` int(11) NOT NULL DEFAULT '0',
            `supplier_stock_status_id` int(11) NOT NULL DEFAULT '0',
            `local_name` varchar(512) NOT NULL DEFAULT '',
            `local_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `local_quantity` int(11) NOT NULL DEFAULT '0',
            `local_stock_status_id` int(11) NOT NULL DEFAULT '0',
            `price_delta_abs` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `price_delta_percent` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `match_source` varchar(64) NOT NULL DEFAULT '',
            `review_type` varchar(32) NOT NULL DEFAULT 'new',
            `parsed_data` mediumtext NOT NULL,
            `status` varchar(64) NOT NULL DEFAULT 'pending',
            `last_error` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`review_id`),
            KEY `supplier_status` (`supplier_id`,`status`),
            KEY `supplier_type` (`supplier_id`,`review_type`),
            KEY `supplier_url` (`supplier_id`,`supplier_product_url`(120)),
            KEY `product_id` (`product_id`)
        ) " . $charset);

        $this->addColumnIfMissing('ccp_ssp_review', 'price_delta_abs', "decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER `local_stock_status_id`");
        $this->addColumnIfMissing('ccp_ssp_review', 'price_delta_percent', "decimal(15,4) NOT NULL DEFAULT '0.0000' AFTER `price_delta_abs`");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_log` (
            `log_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL DEFAULT '0',
            `level` varchar(32) NOT NULL DEFAULT 'info',
            `title` varchar(255) NOT NULL DEFAULT '',
            `message` mediumtext NOT NULL,
            `related_url` varchar(1024) NOT NULL DEFAULT '',
            `date_added` datetime NOT NULL,
            PRIMARY KEY (`log_id`),
            KEY `supplier_level` (`supplier_id`,`level`),
            KEY `date_added` (`date_added`)
        ) " . $charset);

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_history` (
            `history_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL DEFAULT '0',
            `product_id` int(11) NOT NULL DEFAULT '0',
            `supplier_product_url` varchar(1024) NOT NULL DEFAULT '',
            `action` varchar(64) NOT NULL DEFAULT '',
            `field_changed` varchar(64) NOT NULL DEFAULT '',
            `old_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `new_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `old_quantity` int(11) NOT NULL DEFAULT '0',
            `new_quantity` int(11) NOT NULL DEFAULT '0',
            `old_stock_status_id` int(11) NOT NULL DEFAULT '0',
            `new_stock_status_id` int(11) NOT NULL DEFAULT '0',
            `supplier_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `sale_price` decimal(15,4) NOT NULL DEFAULT '0.0000',
            `note` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            PRIMARY KEY (`history_id`),
            KEY `supplier_product` (`supplier_id`,`product_id`),
            KEY `date_added` (`date_added`),
            KEY `action` (`action`)
        ) " . $charset);


        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_image` (
            `image_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL DEFAULT '0',
            `source_url` varchar(1024) NOT NULL DEFAULT '',
            `image_path` varchar(512) NOT NULL DEFAULT '',
            `hash` varchar(64) NOT NULL DEFAULT '',
            `mime` varchar(64) NOT NULL DEFAULT '',
            `size_bytes` int(11) NOT NULL DEFAULT '0',
            `date_added` datetime NOT NULL,
            PRIMARY KEY (`image_id`),
            KEY `supplier_url` (`supplier_id`,`source_url`(120)),
            KEY `hash` (`hash`)
        ) " . $charset);

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_exclusion` (
            `exclusion_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL DEFAULT '0',
            `product_id` int(11) NOT NULL DEFAULT '0',
            `supplier_product_url` varchar(1024) NOT NULL DEFAULT '',
            `supplier_sku` varchar(255) NOT NULL DEFAULT '',
            `type` varchar(32) NOT NULL DEFAULT 'product',
            `reason` varchar(255) NOT NULL DEFAULT '',
            `date_added` datetime NOT NULL,
            PRIMARY KEY (`exclusion_id`),
            KEY `supplier_product` (`supplier_id`,`product_id`),
            KEY `supplier_sku` (`supplier_id`,`supplier_sku`(120)),
            KEY `supplier_url` (`supplier_id`,`supplier_product_url`(120))
        ) " . $charset);

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_run` (
            `run_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL DEFAULT '0',
            `source` varchar(32) NOT NULL DEFAULT 'manual',
            `total_found` int(11) NOT NULL DEFAULT '0',
            `checked` int(11) NOT NULL DEFAULT '0',
            `price_updated` int(11) NOT NULL DEFAULT '0',
            `stock_updated` int(11) NOT NULL DEFAULT '0',
            `created` int(11) NOT NULL DEFAULT '0',
            `skipped` int(11) NOT NULL DEFAULT '0',
            `warnings` int(11) NOT NULL DEFAULT '0',
            `duplicates` int(11) NOT NULL DEFAULT '0',
            `excluded` int(11) NOT NULL DEFAULT '0',
            `no_change` int(11) NOT NULL DEFAULT '0',
            `errors` int(11) NOT NULL DEFAULT '0',
            `message` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            PRIMARY KEY (`run_id`),
            KEY `supplier_date` (`supplier_id`,`date_added`)
        ) " . $charset);
        $this->addColumnIfMissing('ccp_ssp_run', 'warnings', "int(11) NOT NULL DEFAULT '0' AFTER `skipped`");
        $this->addColumnIfMissing('ccp_ssp_run', 'duplicates', "int(11) NOT NULL DEFAULT '0' AFTER `warnings`");
        $this->addColumnIfMissing('ccp_ssp_run', 'excluded', "int(11) NOT NULL DEFAULT '0' AFTER `duplicates`");
        $this->addColumnIfMissing('ccp_ssp_run', 'no_change', "int(11) NOT NULL DEFAULT '0' AFTER `excluded`");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_category_map` (
            `map_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL DEFAULT '0',
            `supplier_category` varchar(512) NOT NULL DEFAULT '',
            `category_id` int(11) NOT NULL DEFAULT '0',
            `allow_import` tinyint(1) NOT NULL DEFAULT '1',
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`map_id`),
            KEY `supplier_category` (`supplier_id`,`supplier_category`(120))
        ) " . $charset);


        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "ccp_ssp_runtime_lock` (
            `lock_name` varchar(64) NOT NULL,
            `owner` varchar(64) NOT NULL DEFAULT '',
            `expires_at` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`lock_name`),
            KEY `expires_at` (`expires_at`)
        ) " . $charset);

        $this->addIndexIfMissing('ccp_ssp_queue', 'supplier_status_modified', "`supplier_id`,`status`,`date_modified`");
        $this->addIndexIfMissing('ccp_ssp_queue', 'status_modified', "`status`,`date_modified`");
        $this->addIndexIfMissing('ccp_ssp_review', 'supplier_status_modified', "`supplier_id`,`status`,`date_modified`");
        $this->addIndexIfMissing('ccp_ssp_review', 'supplier_delta', "`supplier_id`,`price_delta_abs`,`date_modified`");
        $this->addIndexIfMissing('ccp_ssp_review', 'supplier_modified', "`supplier_id`,`date_modified`");
        $this->addIndexIfMissing('ccp_ssp_new_product', 'supplier_status_modified', "`supplier_id`,`status`,`date_modified`");
        $this->addIndexIfMissing('ccp_ssp_history', 'supplier_action_date', "`supplier_id`,`action`,`date_added`");
        $this->addIndexIfMissing('ccp_ssp_log', 'supplier_date', "`supplier_id`,`date_added`");

        $this->load->model('setting/setting');
        $current = $this->model_setting_setting->getSetting('module_supplier_sync_parser_pro');
        $defaults = array(
            'module_supplier_sync_parser_pro_status' => 0,
            'module_supplier_sync_parser_pro_cron_token' => $this->generateToken(),
            'module_supplier_sync_parser_pro_batch_limit' => 20,
            'module_supplier_sync_parser_pro_stop_queue' => 0,
            'module_supplier_sync_parser_pro_review_retention_days' => 14,
            'module_supplier_sync_parser_pro_log_retention_days' => 60,
            'module_supplier_sync_parser_pro_history_retention_days' => 180,
            'module_supplier_sync_parser_pro_schema_version' => $schema_version
        );
        $settings = array_merge($defaults, is_array($current) ? $current : array());
        if (empty($settings['module_supplier_sync_parser_pro_cron_token'])) {
            $settings['module_supplier_sync_parser_pro_cron_token'] = $this->generateToken();
        }
        $settings['module_supplier_sync_parser_pro_schema_version'] = $schema_version;
        $this->model_setting_setting->editSetting('module_supplier_sync_parser_pro', $settings);
        $this->grantCurrentAdminPermissions();
    }

    private function grantCurrentAdminPermissions() {
        if (!isset($this->user) || !method_exists($this->user, 'getGroupId')) {
            return;
        }
        $group_id = (int)$this->user->getGroupId();
        if ($group_id <= 0) {
            return;
        }
        $this->load->model('user/user_group');
        $route = 'extension/module/supplier_sync_parser_pro';
        $this->model_user_user_group->addPermission($group_id, 'access', $route);
        $this->model_user_user_group->addPermission($group_id, 'modify', $route);
    }

    public function uninstall() {

        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_supplier_sync_parser_pro', array(
            'module_supplier_sync_parser_pro_status' => 0,
            'module_supplier_sync_parser_pro_cron_token' => $this->config->get('module_supplier_sync_parser_pro_cron_token'),
            'module_supplier_sync_parser_pro_batch_limit' => (int)$this->config->get('module_supplier_sync_parser_pro_batch_limit'),
            'module_supplier_sync_parser_pro_stop_queue' => (int)$this->config->get('module_supplier_sync_parser_pro_stop_queue'),
            'module_supplier_sync_parser_pro_review_retention_days' => (int)$this->config->get('module_supplier_sync_parser_pro_review_retention_days'),
            'module_supplier_sync_parser_pro_log_retention_days' => (int)$this->config->get('module_supplier_sync_parser_pro_log_retention_days'),
            'module_supplier_sync_parser_pro_history_retention_days' => (int)$this->config->get('module_supplier_sync_parser_pro_history_retention_days'),
            'module_supplier_sync_parser_pro_schema_version' => $this->config->get('module_supplier_sync_parser_pro_schema_version') ?: '1.6.4'
        ));
    }

    public function saveSettings($data) {

        $this->load->model('setting/setting');
        $settings = array(
            'module_supplier_sync_parser_pro_status' => !empty($data['module_supplier_sync_parser_pro_status']) ? 1 : 0,
            'module_supplier_sync_parser_pro_cron_token' => !empty($data['module_supplier_sync_parser_pro_cron_token']) ? $data['module_supplier_sync_parser_pro_cron_token'] : $this->generateToken(),
            'module_supplier_sync_parser_pro_batch_limit' => max(1, min(100, (int)$data['module_supplier_sync_parser_pro_batch_limit'])),
            'module_supplier_sync_parser_pro_stop_queue' => !empty($data['module_supplier_sync_parser_pro_stop_queue']) ? 1 : 0,
            'module_supplier_sync_parser_pro_review_retention_days' => max(1, min(365, (int)(isset($data['module_supplier_sync_parser_pro_review_retention_days']) ? $data['module_supplier_sync_parser_pro_review_retention_days'] : 14))),
            'module_supplier_sync_parser_pro_log_retention_days' => max(1, min(365, (int)(isset($data['module_supplier_sync_parser_pro_log_retention_days']) ? $data['module_supplier_sync_parser_pro_log_retention_days'] : 60))),
            'module_supplier_sync_parser_pro_history_retention_days' => max(1, min(1095, (int)(isset($data['module_supplier_sync_parser_pro_history_retention_days']) ? $data['module_supplier_sync_parser_pro_history_retention_days'] : 180))),
            'module_supplier_sync_parser_pro_schema_version' => $this->config->get('module_supplier_sync_parser_pro_schema_version') ?: '1.6.4'
        );
        $this->model_setting_setting->editSetting('module_supplier_sync_parser_pro', $settings);
    }

    public function getSuppliers() {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_supplier` ORDER BY name ASC, supplier_id ASC");
        return $q->rows;
    }

    public function getSupplier($supplier_id) {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_supplier` WHERE supplier_id = '" . (int)$supplier_id . "'");
        return $q->num_rows ? $q->row : array();
    }

    public function saveSupplier($data) {
        $supplier_id = isset($data['supplier_id']) ? (int)$data['supplier_id'] : 0;
        $defaults = array(
            'name' => '', 'status' => 0, 'base_url' => '', 'source_type' => 'html', 'list_urls' => '',
            'feed_url' => '', 'feed_file' => '', 'feed_format' => 'auto', 'feed_item_path' => '', 'feed_url_path' => '', 'feed_sku_path' => '', 'feed_name_path' => '', 'feed_price_path' => '', 'feed_stock_path' => '', 'feed_quantity_path' => '', 'feed_category_path' => '', 'feed_description_path' => '', 'feed_manufacturer_path' => '', 'feed_image_path' => '', 'feed_ean_path' => '', 'feed_upc_path' => '', 'feed_mpn_path' => '',
            'product_url_xpath' => '', 'list_category_xpath' => '', 'product_url_attr' => 'href', 'next_page_xpath' => '',
            'sku_xpath' => '', 'model_xpath' => '', 'ean_xpath' => '', 'upc_xpath' => '', 'mpn_xpath' => '', 'name_xpath' => '', 'price_xpath' => '', 'stock_xpath' => '', 'category_xpath' => '',
            'description_xpath' => '', 'meta_description_xpath' => '', 'meta_keyword_xpath' => '', 'manufacturer_xpath' => '', 'image_xpath' => '', 'additional_images_xpath' => '',
            'attribute_row_xpath' => '', 'attribute_name_xpath' => '', 'attribute_value_xpath' => '', 'option_row_xpath' => '', 'option_name_xpath' => '', 'option_value_xpath' => '', 'image_attr' => 'src', 'import_additional_images' => 0,
            'currency_code' => '', 'source_language_code' => '', 'target_language_id' => 0, 'price_formula_mode' => 'retail_discount_markup', 'discount_percent' => 0, 'markup_percent' => 0, 'min_margin_percent' => 0, 'max_price_change_percent' => 50, 'rounding_mode' => 'two',
            'default_category_id' => 0, 'force_new_category_id' => 0, 'default_stock_status_id' => 0, 'create_new' => 0, 'create_new_status' => 0,
            'new_tax_class_id' => 0, 'new_minimum' => 1, 'new_subtract' => 1, 'new_shipping' => 1, 'new_sort_order' => 0, 'new_store_id' => 0, 'seo_url_mode' => 'all_languages', 'min_new_price' => 0, 'max_new_price' => 0,
            'auto_apply_existing' => 0, 'auto_create_new' => 0, 'update_price' => 1, 'update_stock' => 1,
            'missing_policy' => 'report', 'existing_description_mode' => 'keep', 'import_attributes' => 0, 'import_options' => 0, 'fill_all_languages' => 0, 'require_test_success' => 0,
            'request_delay_ms' => 500, 'user_agent' => '', 'domain_policy' => 'strict_host', 'allowed_hosts_text' => '', 'stock_map_text' => '', 'category_map_text' => '', 'excluded_skus_text' => '', 'excluded_urls_text' => '', 'excluded_categories_text' => ''
        );
        $data = array_merge($defaults, is_array($data) ? $data : array());
        $data['currency_code'] = $this->normalizeCurrencyCode($data['currency_code']);
        $data['source_language_code'] = $this->normalizeLanguageCode($data['source_language_code']);
        $data['target_language_id'] = $this->normalizeTargetLanguageId($data['target_language_id']);
        $allowed_source_types = array('html', 'feed');
        $data['source_type'] = in_array((string)$data['source_type'], $allowed_source_types, true) ? (string)$data['source_type'] : 'html';
        $allowed_feed_formats = array('auto', 'xml', 'yml', 'csv');
        $data['feed_format'] = in_array((string)$data['feed_format'], $allowed_feed_formats, true) ? (string)$data['feed_format'] : 'auto';
        $stock_map = array();
        if (!empty($data['stock_map_text'])) {
            $lines = preg_split('/\r\n|\r|\n/', $data['stock_map_text']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 2) {
                    $stock_map[$parts[0]] = array('quantity' => (int)$parts[1], 'stock_status_id' => isset($parts[2]) ? (int)$parts[2] : (int)$data['default_stock_status_id']);
                }
            }
        }

        $category_map = array();
        if (!empty($data['category_map_text'])) {
            $lines = preg_split('/\r\n|\r|\n/', $data['category_map_text']);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = array_map('trim', explode('|', $line));
                if (count($parts) >= 2) {
                    $category_map[] = array(
                        'needle' => $parts[0],
                        'category_id' => (int)$parts[1],
                        'allow' => isset($parts[2]) ? (int)$parts[2] : 1
                    );
                }
            }
        }

        $excluded_skus = array();
        foreach (preg_split('/\r\n|\r|\n/', (string)$data['excluded_skus_text']) as $line) {
            $line = trim($line);
            if ($line !== '') { $excluded_skus[] = $line; }
        }
        $excluded_urls = array();
        foreach (preg_split('/\r\n|\r|\n/', (string)$data['excluded_urls_text']) as $line) {
            $line = trim($line);
            if ($line !== '') { $excluded_urls[] = $line; }
        }
        $excluded_categories = array();
        foreach (preg_split('/\r\n|\r|\n/', (string)$data['excluded_categories_text']) as $line) {
            $line = trim($line);
            if ($line !== '') { $excluded_categories[] = $line; }
        }

        $allowed_hosts = array();
        foreach (preg_split('/\r\n|\r|\n/', (string)$data['allowed_hosts_text']) as $line) {
            $line = trim(strtolower((string)$line));
            if ($line === '') {
                continue;
            }
            $host = parse_url((stripos($line, 'http://') === 0 || stripos($line, 'https://') === 0) ? $line : ('https://' . $line), PHP_URL_HOST);
            $host = trim((string)$host);
            if ($host !== '' && preg_match('/^[a-z0-9.-]+$/i', $host)) {
                $allowed_hosts[] = $host;
            }
        }
        $allowed_hosts = array_values(array_unique($allowed_hosts));
        $domain_policy = in_array((string)$data['domain_policy'], array('strict_host', 'allow_subdomains', 'allow_extra_hosts'), true) ? (string)$data['domain_policy'] : 'strict_host';

        $settings = array('stock_map' => $stock_map, 'category_map' => $category_map, 'excluded_skus' => $excluded_skus, 'excluded_urls' => $excluded_urls, 'excluded_categories' => $excluded_categories, 'domain_policy' => $domain_policy, 'allowed_hosts' => $allowed_hosts);
        $settings['in_stock_quantity'] = isset($data['in_stock_quantity']) ? max(0, min(2147483647, (int)$data['in_stock_quantity'])) : 100;
        $settings['unknown_stock_policy'] = isset($data['unknown_stock_policy']) && $data['unknown_stock_policy'] === 'zero' ? 'zero' : 'keep';
        $settings['update_stock_status'] = isset($data['update_stock_status']) ? (int)!empty($data['update_stock_status']) : 1;
        $settings['new_category_name'] = isset($data['new_category_name']) ? mb_substr(trim(strip_tags((string)$data['new_category_name'])), 0, 255, 'UTF-8') : '';
        $identifier_fields = array('sku','model','upc','ean','jan','isbn','mpn');
        $settings['match_source'] = isset($data['match_source']) && in_array($data['match_source'], array_merge(array('auto'), $identifier_fields), true) ? $data['match_source'] : 'auto';
        $settings['match_target'] = isset($data['match_target']) && in_array($data['match_target'], $identifier_fields, true) ? $data['match_target'] : 'sku';
        foreach (array('jan_xpath','isbn_xpath') as $field) { $settings[$field] = isset($data[$field]) ? trim((string)$data[$field]) : ''; }
        $settings['cron_enabled'] = !empty($data['cron_enabled']) ? 1 : 0;
        $settings['cron_interval_minutes'] = isset($data['cron_interval_minutes']) ? max(5, min(10080, (int)$data['cron_interval_minutes'])) : 60;
        // Keep the adapter selected by the preset; ordinary edits must not erase it.
        $previous_settings = array();
        if ($supplier_id > 0) {
            $previous = $this->getSupplier($supplier_id);
            $previous_settings = json_decode(isset($previous['settings']) ? $previous['settings'] : '', true);
        } elseif (isset($data['source_adapter']) && $data['source_adapter'] === 'prom') {
            $previous_settings = array('source_adapter'=>'prom');
        }
        if (is_array($previous_settings) && isset($previous_settings['source_adapter']) && $previous_settings['source_adapter'] === 'prom') {
            $settings['source_adapter'] = 'prom';
        }

        $fields = array(
            "name = '" . $this->db->escape($data['name']) . "'",
            "status = '" . (!empty($data['status']) ? 1 : 0) . "'",
            "base_url = '" . $this->db->escape($data['base_url']) . "'",
            "source_type = '" . $this->db->escape($data['source_type']) . "'",
            "list_urls = '" . $this->db->escape($data['list_urls']) . "'",
            "feed_url = '" . $this->db->escape($data['feed_url']) . "'",
            "feed_file = '" . $this->db->escape($data['feed_file']) . "'",
            "feed_format = '" . $this->db->escape($data['feed_format']) . "'",
            "feed_item_path = '" . $this->db->escape($data['feed_item_path']) . "'",
            "feed_url_path = '" . $this->db->escape($data['feed_url_path']) . "'",
            "feed_sku_path = '" . $this->db->escape($data['feed_sku_path']) . "'",
            "feed_name_path = '" . $this->db->escape($data['feed_name_path']) . "'",
            "feed_price_path = '" . $this->db->escape($data['feed_price_path']) . "'",
            "feed_stock_path = '" . $this->db->escape($data['feed_stock_path']) . "'",
            "feed_quantity_path = '" . $this->db->escape($data['feed_quantity_path']) . "'",
            "feed_category_path = '" . $this->db->escape($data['feed_category_path']) . "'",
            "feed_description_path = '" . $this->db->escape($data['feed_description_path']) . "'",
            "feed_manufacturer_path = '" . $this->db->escape($data['feed_manufacturer_path']) . "'",
            "feed_image_path = '" . $this->db->escape($data['feed_image_path']) . "'",
            "feed_ean_path = '" . $this->db->escape($data['feed_ean_path']) . "'",
            "feed_upc_path = '" . $this->db->escape($data['feed_upc_path']) . "'",
            "feed_mpn_path = '" . $this->db->escape($data['feed_mpn_path']) . "'",
            "product_url_xpath = '" . $this->db->escape($data['product_url_xpath']) . "'",
            "list_category_xpath = '" . $this->db->escape($data['list_category_xpath']) . "'",
            "product_url_attr = '" . $this->db->escape($data['product_url_attr']) . "'",
            "next_page_xpath = '" . $this->db->escape($data['next_page_xpath']) . "'",
            "sku_xpath = '" . $this->db->escape($data['sku_xpath']) . "'",
            "model_xpath = '" . $this->db->escape($data['model_xpath']) . "'",
            "ean_xpath = '" . $this->db->escape($data['ean_xpath']) . "'",
            "upc_xpath = '" . $this->db->escape($data['upc_xpath']) . "'",
            "mpn_xpath = '" . $this->db->escape($data['mpn_xpath']) . "'",
            "name_xpath = '" . $this->db->escape($data['name_xpath']) . "'",
            "price_xpath = '" . $this->db->escape($data['price_xpath']) . "'",
            "stock_xpath = '" . $this->db->escape($data['stock_xpath']) . "'",
            "category_xpath = '" . $this->db->escape($data['category_xpath']) . "'",
            "description_xpath = '" . $this->db->escape($data['description_xpath']) . "'",
            "meta_description_xpath = '" . $this->db->escape($data['meta_description_xpath']) . "'",
            "meta_keyword_xpath = '" . $this->db->escape($data['meta_keyword_xpath']) . "'",
            "manufacturer_xpath = '" . $this->db->escape($data['manufacturer_xpath']) . "'",
            "image_xpath = '" . $this->db->escape($data['image_xpath']) . "'",
            "additional_images_xpath = '" . $this->db->escape($data['additional_images_xpath']) . "'",
            "attribute_row_xpath = '" . $this->db->escape($data['attribute_row_xpath']) . "'",
            "attribute_name_xpath = '" . $this->db->escape($data['attribute_name_xpath']) . "'",
            "attribute_value_xpath = '" . $this->db->escape($data['attribute_value_xpath']) . "'",
            "option_row_xpath = '" . $this->db->escape($data['option_row_xpath']) . "'",
            "option_name_xpath = '" . $this->db->escape($data['option_name_xpath']) . "'",
            "option_value_xpath = '" . $this->db->escape($data['option_value_xpath']) . "'",
            "image_attr = '" . $this->db->escape($data['image_attr']) . "'",
            "import_additional_images = '" . (!empty($data['import_additional_images']) ? 1 : 0) . "'",
            "currency_code = '" . $this->db->escape($data['currency_code']) . "'",
            "source_language_code = '" . $this->db->escape($data['source_language_code']) . "'",
            "target_language_id = '" . (int)$data['target_language_id'] . "'",
            "price_formula_mode = '" . $this->db->escape($data['price_formula_mode']) . "'",
            "discount_percent = '" . (float)$data['discount_percent'] . "'",
            "markup_percent = '" . (float)$data['markup_percent'] . "'",
            "min_margin_percent = '" . (float)$data['min_margin_percent'] . "'",
            "max_price_change_percent = '" . max(0, (float)$data['max_price_change_percent']) . "'",
            "rounding_mode = '" . $this->db->escape($data['rounding_mode']) . "'",
            "default_category_id = '" . (int)$data['default_category_id'] . "'",
            "force_new_category_id = '" . (int)$data['force_new_category_id'] . "'",
            "default_stock_status_id = '" . (int)$data['default_stock_status_id'] . "'",
            "create_new = '" . (!empty($data['create_new']) ? 1 : 0) . "'",
            "create_new_status = '" . (!empty($data['create_new_status']) ? 1 : 0) . "'",
            "new_tax_class_id = '" . (int)$data['new_tax_class_id'] . "'",
            "new_minimum = '" . max(1, (int)$data['new_minimum']) . "'",
            "new_subtract = '" . (!empty($data['new_subtract']) ? 1 : 0) . "'",
            "new_shipping = '" . (!empty($data['new_shipping']) ? 1 : 0) . "'",
            "new_sort_order = '" . (int)$data['new_sort_order'] . "'",
            "new_store_id = '" . max(0, (int)$data['new_store_id']) . "'",
            "seo_url_mode = '" . $this->db->escape($data['seo_url_mode']) . "'",
            "min_new_price = '" . max(0, (float)$data['min_new_price']) . "'",
            "max_new_price = '" . max(0, (float)$data['max_new_price']) . "'",
            "auto_apply_existing = '" . (!empty($data['auto_apply_existing']) ? 1 : 0) . "'",
            "auto_create_new = '" . (!empty($data['auto_create_new']) ? 1 : 0) . "'",
            "update_price = '" . (!empty($data['update_price']) ? 1 : 0) . "'",
            "update_stock = '" . (!empty($data['update_stock']) ? 1 : 0) . "'",
            "missing_policy = '" . $this->db->escape($data['missing_policy']) . "'",
            "existing_description_mode = '" . $this->db->escape($data['existing_description_mode']) . "'",
            "import_attributes = '" . (!empty($data['import_attributes']) ? 1 : 0) . "'",
            "import_options = '" . (!empty($data['import_options']) ? 1 : 0) . "'",
            "fill_all_languages = '" . (!empty($data['fill_all_languages']) ? 1 : 0) . "'",
            "require_test_success = '" . (!empty($data['require_test_success']) ? 1 : 0) . "'",
            "request_delay_ms = '" . max(0, min(10000, (int)$data['request_delay_ms'])) . "'",
            "user_agent = '" . $this->db->escape($data['user_agent']) . "'",
            "settings = '" . $this->db->escape(json_encode($settings)) . "'",
            "date_modified = NOW()"
        );

        if ($supplier_id > 0) {
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_supplier` SET " . implode(', ', $fields) . " WHERE supplier_id = '" . (int)$supplier_id . "'");
            $this->syncCategoryMapRows($supplier_id, $category_map);
            return $supplier_id;
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_supplier` SET " . implode(', ', $fields) . ", date_added = NOW()");
        $supplier_id = (int)$this->db->getLastId();
        $this->syncCategoryMapRows($supplier_id, $category_map);
        return $supplier_id;
    }



    public function setSupplierStatus($supplier_id, $status) {
        $supplier_id = (int)$supplier_id;
        $status = !empty($status) ? 1 : 0;
        if ($supplier_id <= 0) {
            return false;
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_supplier` SET status = '" . (int)$status . "', date_modified = NOW() WHERE supplier_id = '" . (int)$supplier_id . "'");
        return true;
    }

    public function setSupplierNewFlags($supplier_id, $create_new, $create_new_status) {
        $supplier_id = (int)$supplier_id;
        if ($supplier_id <= 0) {
            return false;
        }
        $create_new = !empty($create_new) ? 1 : 0;
        $create_new_status = !empty($create_new_status) ? 1 : 0;
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_supplier` SET create_new = '" . (int)$create_new . "', create_new_status = '" . (int)$create_new_status . "', date_modified = NOW() WHERE supplier_id = '" . (int)$supplier_id . "'");
        return true;
    }


    public function deleteSupplier($supplier_id) {
        $supplier_id = (int)$supplier_id;
        if ($supplier_id <= 0) {
            return false;
        }
        $tables = array(
            'ccp_ssp_supplier',
            'ccp_ssp_product_link',
            'ccp_ssp_queue',
            'ccp_ssp_review',
            'ccp_ssp_new_product',
            'ccp_ssp_history',
            'ccp_ssp_log',
            'ccp_ssp_image',
            'ccp_ssp_run',
            'ccp_ssp_exclusion',
            'ccp_ssp_category_map'
        );
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . $table . "` WHERE supplier_id = '" . (int)$supplier_id . "'");
            }
        }
        return true;
    }

    public function updateDetectedRules($supplier_id, $rules) {
        $supplier_id = (int)$supplier_id;
        $allowed = array(
            'product_url_xpath','list_category_xpath','product_url_attr','next_page_xpath','sku_xpath','model_xpath','ean_xpath','upc_xpath','mpn_xpath','name_xpath','price_xpath','stock_xpath','category_xpath','description_xpath','meta_description_xpath','meta_keyword_xpath','manufacturer_xpath','image_xpath','additional_images_xpath','image_attr','attribute_row_xpath','attribute_name_xpath','attribute_value_xpath','option_row_xpath','option_name_xpath','option_value_xpath'
        );
        $fields = array();
        foreach ($allowed as $field) {
            if (array_key_exists($field, $rules)) {
                $value = trim((string)$rules[$field]);
                $fields[] = "`" . $field . "` = '" . $this->db->escape($value) . "'";
            }
        }
        if (!$supplier_id || !$fields) {
            return 0;
        }
        $fields[] = "last_test_ok = '0'";
        $fields[] = "date_modified = NOW()";
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_supplier` SET " . implode(', ', $fields) . " WHERE supplier_id = '" . $supplier_id . "'");
        return count($fields) - 2;
    }

    public function updateSupplierListUrls($supplier_id, $list_url, $append = false) {
        $supplier_id = (int)$supplier_id;
        $list_url = trim((string)$list_url);
        if ($supplier_id <= 0 || $list_url === '') {
            return false;
        }
        $current = '';
        if ($append) {
            $q = $this->db->query("SELECT list_urls FROM `" . DB_PREFIX . "ccp_ssp_supplier` WHERE supplier_id = '" . $supplier_id . "' LIMIT 1");
            $current = $q->num_rows ? (string)$q->row['list_urls'] : '';
        }
        $lines = array();
        foreach (preg_split('/\r\n|\r|\n/', $current) as $line) {
            $line = trim($line);
            if ($line !== '') { $lines[$line] = $line; }
        }
        $lines[$list_url] = $list_url;
        $value = implode("\n", array_values($lines));
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_supplier` SET list_urls = '" . $this->db->escape($value) . "', date_modified = NOW() WHERE supplier_id = '" . $supplier_id . "'");
        return true;
    }

    public function getCategories($parent_id = 0) {
        $language_id = $this->getLanguageId();
        $q = $this->db->query("SELECT c.category_id, cd.name FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id = '" . $language_id . "') ORDER BY cd.name ASC");
        return $q->rows;
    }

    public function getStockStatuses() {
        $language_id = $this->getLanguageId();
        $q = $this->db->query("SELECT stock_status_id, name FROM `" . DB_PREFIX . "stock_status` WHERE language_id = '" . $language_id . "' ORDER BY name ASC");
        return $q->rows;
    }

    public function getLinks($limit = 50, $supplier_id = 0) {
        $where = (int)$supplier_id > 0 ? " WHERE l.supplier_id = '" . (int)$supplier_id . "'" : '';
        $q = $this->db->query("SELECT l.*, s.name AS provider_name FROM `" . DB_PREFIX . "ccp_ssp_product_link` l LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (l.supplier_id = s.supplier_id)" . $where . " ORDER BY l.last_checked DESC LIMIT " . (int)$limit);
        $rows = array();
        foreach ($q->rows as $row) {
            $row['last_stock_text'] = $this->cleanStockTextForDisplay(isset($row['last_stock_text']) ? $row['last_stock_text'] : '');
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $rows[] = $row;
        }
        return $rows;
    }

    public function getReviews($limit = 200, $supplier_id = 0) {
        $where = array();
        if ((int)$supplier_id > 0) {
            $where[] = "r.supplier_id = '" . (int)$supplier_id . "'";
        }
        $sql = "SELECT r.*, s.name AS provider_name, s.currency_code AS provider_currency_code FROM `" . DB_PREFIX . "ccp_ssp_review` r LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (r.supplier_id = s.supplier_id)";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY FIELD(r.status, 'pending','price_warning','excluded','error','applied','created','skipped'), r.date_modified DESC LIMIT " . (int)$limit;
        $q = $this->db->query($sql);
        $rows = array();
        foreach ($q->rows as $row) {
            $row['supplier_stock_text'] = $this->cleanStockTextForDisplay(isset($row['supplier_stock_text']) ? $row['supplier_stock_text'] : '');
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $row['supplier_category_clean'] = $this->cleanCategoryTextForDisplay(isset($row['supplier_category']) ? $row['supplier_category'] : '');
            if ($this->categoryLooksLikeProductName($row['supplier_category_clean'], isset($row['supplier_name']) ? $row['supplier_name'] : '', isset($row['supplier_sku']) ? $row['supplier_sku'] : '')) { $row['supplier_category_clean'] = ''; }
            $rows[] = $row;
        }
        return $rows;
    }

    public function getNewProducts($limit = 50) {
        $q = $this->db->query("SELECT n.*, s.name AS provider_name, s.currency_code AS provider_currency_code FROM `" . DB_PREFIX . "ccp_ssp_new_product` n LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (n.supplier_id = s.supplier_id) ORDER BY FIELD(n.status, 'pending','duplicate','excluded','error','created','skipped'), n.date_modified DESC LIMIT " . (int)$limit);
        $rows = array();
        foreach ($q->rows as $row) {
            $parsed = json_decode($row['parsed_data'], true);
            if (!is_array($parsed)) {
                $parsed = array();
            }
            $row['category_text'] = isset($parsed['category_text']) ? $parsed['category_text'] : '';
            $row['category_text_clean'] = $this->cleanCategoryTextForDisplay($row['category_text']);
            if ($this->categoryLooksLikeProductName($row['category_text_clean'], isset($row['supplier_name']) ? $row['supplier_name'] : '', isset($row['supplier_sku']) ? $row['supplier_sku'] : '')) { $row['category_text_clean'] = ''; }
            $row['target_category_id'] = isset($parsed['target_category_id']) ? (int)$parsed['target_category_id'] : 0;
            $row['supplier_raw_price'] = isset($parsed['supplier_raw_price']) ? (float)$parsed['supplier_raw_price'] : 0;
            $row['supplier_currency'] = isset($parsed['supplier_currency']) ? (string)$parsed['supplier_currency'] : '';
            $row['supplier_price'] = isset($parsed['supplier_price']) ? $parsed['supplier_price'] : 0;
            $row['sale_price'] = isset($parsed['sale_price']) ? $parsed['sale_price'] : 0;
            $row['stock_text'] = $this->cleanStockTextForDisplay(isset($parsed['stock_text']) ? $parsed['stock_text'] : '');
            $row['quantity'] = isset($parsed['quantity']) ? $parsed['quantity'] : 0;
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $rows[] = $row;
        }
        return $rows;
    }

    public function getQueue($limit = 50) {
        $q = $this->db->query("SELECT q.*, s.name AS provider_name FROM `" . DB_PREFIX . "ccp_ssp_queue` q LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (q.supplier_id = s.supplier_id) ORDER BY q.queue_id DESC LIMIT " . (int)$limit);
        $rows = array();
        foreach ($q->rows as $row) {
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $rows[] = $row;
        }
        return $rows;
    }

    public function getLogs($limit = 100) {
        $q = $this->db->query("SELECT l.*, s.name AS provider_name FROM `" . DB_PREFIX . "ccp_ssp_log` l LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (l.supplier_id = s.supplier_id) ORDER BY l.log_id DESC LIMIT " . (int)$limit);
        $rows = array();
        foreach ($q->rows as $row) {
            $row['related_url_label'] = $this->shortenUrlForDisplay(isset($row['related_url']) ? $row['related_url'] : '');
            $rows[] = $row;
        }
        return $rows;
    }

    public function getHistory($limit = 150, $supplier_id = 0) {
        $where = array();
        if ((int)$supplier_id > 0) {
            $where[] = "h.supplier_id = '" . (int)$supplier_id . "'";
        }
        $sql = "SELECT h.*, s.name AS provider_name, pd.name AS product_name FROM `" . DB_PREFIX . "ccp_ssp_history` h LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (h.supplier_id = s.supplier_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (h.product_id = pd.product_id AND pd.language_id = '" . (int)$this->getLanguageId() . "')";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY h.history_id DESC LIMIT " . (int)$limit;
        $q = $this->db->query($sql);
        $rows = array();
        foreach ($q->rows as $row) {
            $row['old_price'] = $this->formatPriceValue(isset($row['old_price']) ? $row['old_price'] : 0);
            $row['new_price'] = $this->formatPriceValue(isset($row['new_price']) ? $row['new_price'] : 0);
            $row['supplier_price'] = $this->formatPriceValue(isset($row['supplier_price']) ? $row['supplier_price'] : 0);
            $row['sale_price'] = $this->formatPriceValue(isset($row['sale_price']) ? $row['sale_price'] : 0);
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $rows[] = $row;
        }
        return $rows;
    }

    public function clearHistory($supplier_id = 0) {
        $has_history = $this->tableExists('ccp_ssp_history');
        $has_run = $this->tableExists('ccp_ssp_run');
        if ((int)$supplier_id > 0) {
            if ($has_history) { $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_history` WHERE supplier_id = '" . (int)$supplier_id . "'"); }
            if ($has_run) { $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_run` WHERE supplier_id = '" . (int)$supplier_id . "'"); }
        } else {
            if ($has_history) { $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "ccp_ssp_history`"); }
            if ($has_run) { $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "ccp_ssp_run`"); }
        }
    }

    public function searchProducts($query, $limit = 20) {
        $language_id = $this->getLanguageId();
        $limit = max(1, min(50, (int)$limit));
        $query = trim((string)$query);
        if ($query === '') {
            return array();
        }

        $where = array();
        if (ctype_digit($query)) {
            $where[] = "p.product_id = '" . (int)$query . "'";
        }

        $query_identifier = $this->normalizeSearchIdentifier($query);
        if ($query_identifier !== '') {
            $id = $this->db->escape($query_identifier);
            $where[] = "TRIM(p.model) = '" . $id . "'";
            $where[] = "TRIM(p.sku) = '" . $id . "'";
            $where[] = "TRIM(p.ean) = '" . $id . "'";
            $where[] = "TRIM(p.upc) = '" . $id . "'";
            $where[] = "TRIM(p.mpn) = '" . $id . "'";
            $where[] = "TRIM(p.jan) = '" . $id . "'";
            $where[] = "TRIM(p.isbn) = '" . $id . "'";
        }

        $exact_matches = $where;
        $tokens = $this->extractSearchTokens($query, 8);
        if ($tokens) {
            foreach ($tokens as $token) {
                $like = $this->db->escape($token);
                $where[] = "pd.name LIKE '%" . $like . "%'";
                $where[] = "p.model LIKE '%" . $like . "%'";
                $where[] = "p.sku LIKE '%" . $like . "%'";
            }
        } else {
            $like = $this->db->escape($query);
            $where[] = "pd.name LIKE '%" . $like . "%'";
            $where[] = "p.model LIKE '%" . $like . "%'";
            $where[] = "p.sku LIKE '%" . $like . "%'";
        }

        $order = $exact_matches ? "CASE WHEN (" . implode(" OR ", $exact_matches) . ") THEN 0 ELSE 1 END, " : "";
        $sql = "SELECT p.product_id, p.model, p.sku, p.ean, p.upc, p.jan, p.isbn, p.mpn, p.price, p.quantity, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE (" . implode(' OR ', $where) . ") ORDER BY " . $order . "p.product_id DESC LIMIT 150";
        $q = $this->db->query($sql);
        $normalized_query = $this->normalizeSearchComparableName($query);
        $rows = array();
        $seen = array();
        foreach ($q->rows as $row) {
            $product_id = (int)$row['product_id'];
            if (isset($seen[$product_id])) { continue; }
            $seen[$product_id] = true;
            $similarity = $this->calculateSearchSimilarity($normalized_query, $this->normalizeSearchComparableName(isset($row['name']) ? $row['name'] : ''));
            $identifier_match = false;
            foreach (array('model','sku','ean','upc','jan','isbn','mpn') as $field) {
                if ($query_identifier !== '' && isset($row[$field]) && trim((string)$row[$field]) === $query_identifier) {
                    $identifier_match = true;
                    $similarity = 100;
                    break;
                }
            }
            $row['similarity'] = (int)$similarity;
            $row['match_reason'] = $identifier_match ? 'identifier' : ($similarity >= 90 ? 'name_similarity_90' : 'text_search');
            $rows[] = $row;
        }
        usort($rows, function($a, $b) {
            $sa = isset($a['similarity']) ? (int)$a['similarity'] : 0;
            $sb = isset($b['similarity']) ? (int)$b['similarity'] : 0;
            if ($sa === $sb) { return ((int)$a['product_id'] < (int)$b['product_id']) ? -1 : 1; }
            return ($sa > $sb) ? -1 : 1;
        });
        return array_slice($rows, 0, $limit);
    }

    private function normalizeSearchIdentifier($value) {
        $value = trim((string)$value, " \t\n\r\0\x0B.:;,#№");
        if ($value === '') { return ''; }
        if (!preg_match('/[a-zа-яієїґ0-9]/iu', $value)) { return ''; }
        return substr($value, 0, 80);
    }

    private function normalizeSearchComparableName($name) {
        $name = function_exists('mb_strtolower') ? mb_strtolower((string)$name, 'UTF-8') : strtolower((string)$name);
        $name = preg_replace('/\b(купити|купить|ціна|цена|доставка|доставкою|uah|грн|грн\.|price|buy|delivery|sale|акція|акция)\b/iu', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    private function extractSearchTokens($text, $limit = 8) {
        $text = $this->normalizeSearchComparableName($text);
        $parts = preg_split('/\s+/u', $text);
        $tokens = array();
        foreach ($parts as $token) {
            $token = trim($token);
            $len = function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token);
            if ($len < 3) { continue; }
            $tokens[$token] = $len;
        }
        arsort($tokens);
        return array_slice(array_keys($tokens), 0, max(1, (int)$limit));
    }

    private function calculateSearchSimilarity($a, $b) {
        $a = $this->toSearchAsciiComparable($a);
        $b = $this->toSearchAsciiComparable($b);
        if ($a === '' || $b === '') { return 0; }
        if ($a === $b) { return 100; }
        $p1 = 0; $p2 = 0;
        similar_text($a, $b, $p1);
        similar_text($b, $a, $p2);
        $max = max(strlen($a), strlen($b));
        $lev = 0;
        if ($max > 0) {
            $distance = levenshtein(substr($a, 0, 255), substr($b, 0, 255));
            $distance = min($distance, $max);
            $lev = (1 - ($distance / $max)) * 100;
        }
        return (int)round(max($p1, $p2, $lev));
    }

    private function toSearchAsciiComparable($text) {
        $text = function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : strtolower((string)$text);
        $map = array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ь'=>'','ы'=>'y','ъ'=>'','э'=>'e','ю'=>'yu','я'=>'ya','ø'=>'o','æ'=>'ae','å'=>'a','ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'ss','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ó'=>'o','ò'=>'o','í'=>'i','ì'=>'i','ñ'=>'n');
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    public function clearQueue($supplier_id = 0, $status = '') {
        $where = array();
        if ((int)$supplier_id > 0) {
            $where[] = "supplier_id = '" . (int)$supplier_id . "'";
        }
        if ($status !== '') {
            $where[] = "status = '" . $this->db->escape($status) . "'";
        }
        $sql = "DELETE FROM `" . DB_PREFIX . "ccp_ssp_queue`";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $this->db->query($sql);
    }

    public function clearReviews($supplier_id = 0, $status = '') {
        $where = array();
        if ((int)$supplier_id > 0) {
            $where[] = "supplier_id = '" . (int)$supplier_id . "'";
        }
        if ($status !== '') {
            $where[] = "status = '" . $this->db->escape($status) . "'";
        }
        $sql = "DELETE FROM `" . DB_PREFIX . "ccp_ssp_review`";
        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $this->db->query($sql);
    }

    public function clearLogs($supplier_id = 0) {
        if ((int)$supplier_id > 0) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_log` WHERE supplier_id = '" . (int)$supplier_id . "'");
        } else {
            $this->db->query("TRUNCATE TABLE `" . DB_PREFIX . "ccp_ssp_log`");
        }
    }

    public function resetProcessingJobs($supplier_id = 0) {
        $where = "status = 'processing'";
        if ((int)$supplier_id > 0) {
            $where .= " AND supplier_id = '" . (int)$supplier_id . "'";
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_queue` SET status = 'pending', date_modified = NOW() WHERE " . $where);
        $this->clearProcessLocks($supplier_id);
    }

    public function clearProcessLocks($supplier_id = 0) {
        if ((int)$supplier_id > 0) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name IN ('queue_" . (int)$supplier_id . "','queue_0')");
        } else {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name LIKE 'queue\_%'");
        }
    }

    public function retrySelectedQueueJobs($queue_ids, $supplier_id = 0) {
        $ids = $this->sanitizeIdList($queue_ids);
        if (!$ids) {
            return 0;
        }
        $where = "queue_id IN (" . implode(',', $ids) . ")";
        if ((int)$supplier_id > 0) {
            $where .= " AND supplier_id = '" . (int)$supplier_id . "'";
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_queue` SET status = 'pending', attempts = '0', last_error = '', date_modified = NOW() WHERE " . $where);
        return (int)$this->db->countAffected();
    }

    public function deleteSelectedQueueJobs($queue_ids, $supplier_id = 0) {
        $ids = $this->sanitizeIdList($queue_ids);
        if (!$ids) {
            return 0;
        }
        $where = "queue_id IN (" . implode(',', $ids) . ")";
        if ((int)$supplier_id > 0) {
            $where .= " AND supplier_id = '" . (int)$supplier_id . "'";
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_queue` WHERE " . $where);
        return (int)$this->db->countAffected();
    }

    public function deleteSelectedReviewRows($review_ids, $supplier_id = 0) {
        $ids = $this->sanitizeIdList($review_ids);
        if (!$ids) {
            return 0;
        }
        $where = "review_id IN (" . implode(',', $ids) . ")";
        if ((int)$supplier_id > 0) {
            $where .= " AND supplier_id = '" . (int)$supplier_id . "'";
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_review` WHERE " . $where);
        return (int)$this->db->countAffected();
    }

    public function deleteSelectedNewProductRows($new_ids, $supplier_id = 0) {
        $ids = $this->sanitizeIdList($new_ids);
        if (!$ids) {
            return 0;
        }
        $where = "new_id IN (" . implode(',', $ids) . ")";
        if ((int)$supplier_id > 0) {
            $where .= " AND supplier_id = '" . (int)$supplier_id . "'";
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_new_product` WHERE " . $where);
        return (int)$this->db->countAffected();
    }

    public function setSelectedNewProductsStatus($new_ids, $status, $supplier_id = 0) {
        $ids = $this->sanitizeIdList($new_ids);
        $allowed = array('pending', 'skipped', 'excluded');
        if (!$ids || !in_array($status, $allowed, true)) {
            return 0;
        }
        $where = "new_id IN (" . implode(',', $ids) . ")";
        if ((int)$supplier_id > 0) {
            $where .= " AND supplier_id = '" . (int)$supplier_id . "'";
        }
        $message = '';
        if ($status === 'skipped') {
            $message = 'Skipped manually';
        } elseif ($status === 'pending') {
            $message = 'Returned to pending';
        } elseif ($status === 'excluded') {
            $message = 'Excluded manually';
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape($message) . "', date_modified = NOW() WHERE " . $where);
        return (int)$this->db->countAffected();
    }

    private function sanitizeIdList($ids) {
        $out = array();
        foreach ((array)$ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $out[$id] = $id;
            }
        }
        return array_values($out);
    }


    public function getRuns($limit = 50, $supplier_id = 0) {
        $where = $supplier_id ? " WHERE supplier_id = '" . (int)$supplier_id . "'" : '';
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_run`" . $where . " ORDER BY run_id DESC LIMIT " . (int)$limit);
        return $q->rows;
    }

    public function getExclusions($supplier_id = 0, $limit = 100) {
        $where = $supplier_id ? " WHERE supplier_id = '" . (int)$supplier_id . "'" : '';
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_exclusion`" . $where . " ORDER BY exclusion_id DESC LIMIT " . (int)$limit);
        return $q->rows;
    }

    public function saveExclusion($data) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_exclusion` SET supplier_id = '" . (int)$data['supplier_id'] . "', product_id = '" . (int)$data['product_id'] . "', supplier_product_url = '" . $this->db->escape(isset($data['supplier_product_url']) ? $data['supplier_product_url'] : '') . "', supplier_sku = '" . $this->db->escape(isset($data['supplier_sku']) ? $data['supplier_sku'] : '') . "', type = '" . $this->db->escape(isset($data['type']) ? $data['type'] : 'product') . "', reason = '" . $this->db->escape(isset($data['reason']) ? $data['reason'] : '') . "', date_added = NOW()");
    }


    public function setQueueStopFlag($enabled) {
        $value = $enabled ? 1 : 0;
        $key = 'module_supplier_sync_parser_pro_stop_queue';
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'module_supplier_sync_parser_pro' AND `key` = '" . $this->db->escape($key) . "'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', code = 'module_supplier_sync_parser_pro', `key` = '" . $this->db->escape($key) . "', `value` = '" . (int)$value . "', serialized = '0'");
        if (is_object($this->config) && method_exists($this->config, 'set')) {
            $this->config->set($key, $value);
        }
    }

    public function getQueueStopFlag() {
        $key = 'module_supplier_sync_parser_pro_stop_queue';
        $q = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `key` = '" . $this->db->escape($key) . "' LIMIT 1");
        if ($q->num_rows) {
            return (int)$q->row['value'];
        }
        return (int)$this->config->get($key);
    }

    public function saveSelectedReviewExclusions($review_ids, $reason = 'Manual exclusion', $supplier_id = 0) {
        $ids = array();
        foreach ((array)$review_ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return array('excluded' => 0);
        }
        $reason = trim((string)$reason);
        if ($reason === '') {
            $reason = 'Manual exclusion';
        }
        $where_supplier = ((int)$supplier_id > 0) ? " AND supplier_id = '" . (int)$supplier_id . "'" : '';
        $q = $this->db->query("SELECT review_id, supplier_id, product_id, supplier_product_url, supplier_sku FROM `" . DB_PREFIX . "ccp_ssp_review` WHERE review_id IN (" . implode(',', $ids) . ")" . $where_supplier);

        $count = 0;
        foreach ($q->rows as $row) {
            $exists = $this->db->query("SELECT exclusion_id FROM `" . DB_PREFIX . "ccp_ssp_exclusion` WHERE supplier_id = '" . (int)$row['supplier_id'] . "' AND ((product_id > 0 AND product_id = '" . (int)$row['product_id'] . "') OR (supplier_product_url <> '' AND supplier_product_url = '" . $this->db->escape($row['supplier_product_url']) . "') OR (supplier_sku <> '' AND supplier_sku = '" . $this->db->escape($row['supplier_sku']) . "')) LIMIT 1");
            if (!$exists->num_rows) {
                $this->saveExclusion(array(
                    'supplier_id' => (int)$row['supplier_id'],
                    'product_id' => (int)$row['product_id'],
                    'supplier_product_url' => $row['supplier_product_url'],
                    'supplier_sku' => $row['supplier_sku'],
                    'type' => 'manual',
                    'reason' => $reason
                ));
            }
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_review` SET status = 'excluded', last_error = '" . $this->db->escape($reason) . "', date_modified = NOW() WHERE review_id = '" . (int)$row['review_id'] . "'");
            $count++;
        }
        return array('excluded' => $count);
    }

    public function recoverErrorJobs($supplier_id = 0) {
        $where = "status = 'error'";
        if ($supplier_id) { $where .= " AND supplier_id = '" . (int)$supplier_id . "'"; }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_queue` SET status = 'pending', last_error = '', date_modified = NOW() WHERE " . $where);
    }


    public function getReviewsPaged($filters = array()) {
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        $limit = max(10, min(500, $limit));
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $page = max(1, $page);
        $start = ($page - 1) * $limit;
        $where = array();
        if (!empty($filters['supplier_id'])) {
            $where[] = "r.supplier_id = '" . (int)$filters['supplier_id'] . "'";
        }
        if (!empty($filters['status'])) {
            $where[] = "r.status = '" . $this->db->escape($filters['status']) . "'";
        }
        if (!empty($filters['type'])) {
            $where[] = "r.review_type = '" . $this->db->escape($filters['type']) . "'";
        }
        if (!empty($filters['change'])) {
            $price_changed = "ABS(r.price_delta_abs) > 0.0001";
            $stock_changed = "(r.supplier_quantity <> r.local_quantity OR r.supplier_stock_status_id <> r.local_stock_status_id)";
            if ($filters['change'] === 'changed') {
                $where[] = "(" . $price_changed . " OR " . $stock_changed . ")";
            } elseif ($filters['change'] === 'price') {
                $where[] = "(" . $price_changed . " AND NOT " . $stock_changed . ")";
            } elseif ($filters['change'] === 'stock') {
                $where[] = "(" . $stock_changed . " AND NOT " . $price_changed . ")";
            } elseif ($filters['change'] === 'price_stock') {
                $where[] = "(" . $price_changed . " AND " . $stock_changed . ")";
            } elseif ($filters['change'] === 'same') {
                $where[] = "(NOT " . $price_changed . " AND NOT " . $stock_changed . ")";
            }
        }
        if (!empty($filters['text'])) {
            $text = $this->db->escape($filters['text']);
            $where[] = "(r.supplier_name LIKE '%" . $text . "%' OR r.supplier_sku LIKE '%" . $text . "%' OR r.local_name LIKE '%" . $text . "%' OR r.supplier_product_url LIKE '%" . $text . "%')";
        }
        $sql_where = $where ? " WHERE " . implode(' AND ', $where) : '';
        $count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ccp_ssp_review` r" . $sql_where);
        $sort_map = array(
            'review_id' => 'r.review_id', 'supplier' => 's.name', 'supplier_name' => 'r.supplier_name', 'sku' => 'r.supplier_sku', 'status' => 'r.status', 'type' => 'r.review_type',
            'supplier_price' => 'r.supplier_price', 'sale_price' => 'r.sale_price', 'local_price' => 'r.local_price', 'price_delta_abs' => 'r.price_delta_abs',
            'quantity' => 'r.supplier_quantity', 'local_quantity' => 'r.local_quantity', 'date' => 'r.date_modified'
        );
        $sort_key = isset($filters['sort']) ? $filters['sort'] : '';
        $order = (isset($filters['order']) && strtolower($filters['order']) === 'asc') ? 'ASC' : 'DESC';
        $order_sql = isset($sort_map[$sort_key]) ? ($sort_map[$sort_key] . ' ' . $order . ', r.review_id DESC') : "FIELD(r.status, 'pending','price_warning','duplicate','excluded','error','applied','created','skipped'), r.date_modified DESC";
        $sql = "SELECT r.*, s.name AS provider_name, s.currency_code AS provider_currency_code FROM `" . DB_PREFIX . "ccp_ssp_review` r LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (r.supplier_id = s.supplier_id)" . $sql_where . " ORDER BY " . $order_sql . " LIMIT " . (int)$start . "," . (int)$limit;
        $q = $this->db->query($sql);
        $rows = array();
        foreach ($q->rows as $row) {
            $parsed = json_decode(isset($row['parsed_data']) ? $row['parsed_data'] : '', true);
            if (!is_array($parsed)) { $parsed = array(); }
            $display_price = $this->buildSupplierPriceDisplay($row, $parsed);
            $row['supplier_raw_price'] = $this->formatPriceValue($display_price['supplier_raw_price']);
            $row['supplier_currency'] = $display_price['supplier_currency'];
            $row['supplier_price_base'] = $this->formatPriceValue($display_price['supplier_price_base']);
            $row['supplier_price'] = $this->formatPriceValue($display_price['supplier_price_base']);
            $row['sale_price'] = $this->formatPriceValue(isset($row['sale_price']) ? $row['sale_price'] : 0);
            $row['local_price'] = $this->formatPriceValue(isset($row['local_price']) ? $row['local_price'] : 0);
            $row['price_delta_abs'] = $this->formatPriceValue(isset($row['price_delta_abs']) ? $row['price_delta_abs'] : 0);
            $row['price_delta_percent'] = $this->formatPriceValue(isset($row['price_delta_percent']) ? $row['price_delta_percent'] : 0);
            $row['supplier_category_clean'] = $this->cleanCategoryTextForDisplay(isset($row['supplier_category']) ? $row['supplier_category'] : '');
            if ($this->categoryLooksLikeProductName($row['supplier_category_clean'], isset($row['supplier_name']) ? $row['supplier_name'] : '', isset($row['supplier_sku']) ? $row['supplier_sku'] : '')) { $row['supplier_category_clean'] = ''; }
            $row['supplier_stock_text'] = $this->cleanStockTextForDisplay(isset($row['supplier_stock_text']) ? $row['supplier_stock_text'] : '');
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $row['match_candidates'] = array();
            if (!empty($parsed['match_candidates']) && is_array($parsed['match_candidates'])) {
                $row['match_candidates'] = array_slice($parsed['match_candidates'], 0, 5);
            }
            $rows[] = $row;
        }
        return array('rows' => $rows, 'total' => (int)$count->row['total'], 'page' => $page, 'limit' => $limit);
    }

    private function categoryLooksLikeProductName($category, $product_name, $sku = '') {
        $category = trim(preg_replace('/\s+/u', ' ', (string)$category));
        $product_name = trim(preg_replace('/\s+/u', ' ', (string)$product_name));
        if ($category === '' || $product_name === '') { return false; }
        $cat_key = function_exists('mb_strtolower') ? mb_strtolower($category, 'UTF-8') : strtolower($category);
        $prod_key = function_exists('mb_strtolower') ? mb_strtolower($product_name, 'UTF-8') : strtolower($product_name);
        $cat_key = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $cat_key);
        $prod_key = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $prod_key);
        $cat_key = trim(preg_replace('/\s+/u', ' ', $cat_key));
        $prod_key = trim(preg_replace('/\s+/u', ' ', $prod_key));
        if ($cat_key === $prod_key || strpos($cat_key, $prod_key) !== false || strpos($prod_key, $cat_key) !== false) { return true; }
        $sku = trim((string)$sku);
        if ($sku !== '' && stripos($category, $sku) !== false) { return true; }
        return false;
    }

    private function cleanStockTextForDisplay($text) {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if ($text === '') { return ''; }
        $text = preg_replace('/^\s*(наявність|наличие|availability|stock|in stock|available)\s*[:\-–—]?\s*/iu', '', $text);
        $text = preg_replace('/\s*\b(наявність|наличие|availability|stock)\b\s*[:\-–—]?\s*/iu', ' ', $text);
        $text = preg_replace('/\s+\/\s+/u', ' / ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function shortenUrlForDisplay($url) {
        $url = trim((string)$url);
        if ($url === '') { return ''; }
        if (strpos($url, 'feed://') === 0) { return $url; }
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return (strlen($url) > 42) ? (substr($url, 0, 24) . '…' . substr($url, -14)) : $url;
        }
        $host = strtolower($parts['host']);
        $path = isset($parts['path']) ? trim((string)$parts['path'], '/') : '';
        $segments = $path !== '' ? explode('/', $path) : array();
        $last = $segments ? end($segments) : '';
        $label = $host . ($last !== '' ? '/' . $last : '/');
        if (strlen($label) > 54) {
            $label = substr($host, 0, 24) . '…/' . substr($last, -24);
        }
        return $label;
    }

    private function formatPriceValue($amount) {
        $amount = round((float)$amount, 2);
        if (abs($amount - round($amount)) < 0.00001) {
            return (string)(int)round($amount);
        }
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }

    private function cleanCategoryTextForDisplay($text) {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if ($text === '') { return ''; }

        $parts = preg_split('/\s*(?:\/|>|»|›|\||→|\n)\s*/u', $text);
        $out = array();
        $seen = array();
        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', (string)$part));
            if ($part === '') { continue; }
            $part = $this->collapseRepeatedWords($part);
            $key = function_exists('mb_strtolower') ? mb_strtolower($part, 'UTF-8') : strtolower($part);
            if (!isset($seen[$key])) { $seen[$key] = true; $out[] = $part; }
        }
        if (!$out) { $out[] = $text; }
        return $this->collapseRepeatedWords(implode(' / ', $out));
    }

    private function collapseRepeatedWords($text) {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if ($text === '') { return ''; }
        $words = preg_split('/\s+/u', $text);
        $max = (int)floor(count($words) / 2);
        for ($size = $max; $size >= 1; $size--) {
            for ($i = 0; $i + 2 * $size <= count($words); $i++) {
                $a = array_slice($words, $i, $size);
                $b = array_slice($words, $i + $size, $size);
                $a_key = function_exists('mb_strtolower') ? mb_strtolower(implode(' ', $a), 'UTF-8') : strtolower(implode(' ', $a));
                $b_key = function_exists('mb_strtolower') ? mb_strtolower(implode(' ', $b), 'UTF-8') : strtolower(implode(' ', $b));
                if ($a_key === $b_key) {
                    array_splice($words, $i + $size, $size);
                    $i = max(-1, $i - 1);
                }
            }
        }
        return trim(implode(' ', $words));
    }

    public function getReviewStatsDb($supplier_id = 0) {
        $where = $supplier_id ? " WHERE supplier_id = '" . (int)$supplier_id . "'" : '';
        $q = $this->db->query("SELECT COUNT(*) AS total, SUM(CASE WHEN ABS(price_delta_abs) > 0.0001 THEN 1 ELSE 0 END) AS price_changed, SUM(CASE WHEN supplier_quantity <> local_quantity OR supplier_stock_status_id <> local_stock_status_id THEN 1 ELSE 0 END) AS stock_changed, SUM(CASE WHEN ABS(price_delta_abs) > 0.0001 AND (supplier_quantity <> local_quantity OR supplier_stock_status_id <> local_stock_status_id) THEN 1 ELSE 0 END) AS price_stock_changed, SUM(CASE WHEN review_type = 'new' THEN 1 ELSE 0 END) AS new_rows, SUM(CASE WHEN status = 'price_warning' THEN 1 ELSE 0 END) AS warnings, SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) AS duplicates, SUM(CASE WHEN status = 'excluded' THEN 1 ELSE 0 END) AS excluded, SUM(CASE WHEN ABS(price_delta_abs) <= 0.0001 AND supplier_quantity = local_quantity AND supplier_stock_status_id = local_stock_status_id THEN 1 ELSE 0 END) AS same FROM `" . DB_PREFIX . "ccp_ssp_review`" . $where);
        $row = $q->row;
        foreach (array('total','price_changed','stock_changed','price_stock_changed','new_rows','warnings','duplicates','excluded','same') as $key) {
            $row[$key] = isset($row[$key]) ? (int)$row[$key] : 0;
        }
        return $row;
    }

    public function getNewProductsPaged($filters = array()) {
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
        $limit = max(10, min(500, $limit));
        $page = isset($filters['page']) ? (int)$filters['page'] : 1;
        $page = max(1, $page);
        $start = ($page - 1) * $limit;
        $where = array();
        if (!empty($filters['supplier_id'])) { $where[] = "n.supplier_id = '" . (int)$filters['supplier_id'] . "'"; }
        if (!empty($filters['status'])) { $where[] = "n.status = '" . $this->db->escape($filters['status']) . "'"; }
        if (!empty($filters['text'])) {
            $text = $this->db->escape($filters['text']);
            $where[] = "(n.supplier_name LIKE '%" . $text . "%' OR n.supplier_sku LIKE '%" . $text . "%' OR n.supplier_product_url LIKE '%" . $text . "%')";
        }
        $sql_where = $where ? " WHERE " . implode(' AND ', $where) : '';
        $count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ccp_ssp_new_product` n" . $sql_where);
        $sort_map = array('new_id' => 'n.new_id', 'supplier' => 's.name', 'supplier_name' => 'n.supplier_name', 'sku' => 'n.supplier_sku', 'status' => 'n.status', 'date' => 'n.date_modified');
        $sort_key = isset($filters['sort']) ? $filters['sort'] : '';
        $order = (isset($filters['order']) && strtolower($filters['order']) === 'asc') ? 'ASC' : 'DESC';
        $order_sql = isset($sort_map[$sort_key]) ? ($sort_map[$sort_key] . ' ' . $order . ', n.new_id DESC') : "FIELD(n.status, 'pending','duplicate','excluded','error','created','skipped'), n.date_modified DESC";
        $q = $this->db->query("SELECT n.*, s.name AS provider_name, s.currency_code AS provider_currency_code FROM `" . DB_PREFIX . "ccp_ssp_new_product` n LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (n.supplier_id = s.supplier_id)" . $sql_where . " ORDER BY " . $order_sql . " LIMIT " . (int)$start . "," . (int)$limit);
        $rows = array();
        foreach ($q->rows as $row) {
            $parsed = json_decode($row['parsed_data'], true);
            if (!is_array($parsed)) { $parsed = array(); }
            $row['category_text'] = isset($parsed['category_text']) ? $parsed['category_text'] : '';
            $row['category_text_clean'] = $this->cleanCategoryTextForDisplay($row['category_text']);
            if ($this->categoryLooksLikeProductName($row['category_text_clean'], isset($row['supplier_name']) ? $row['supplier_name'] : '', isset($row['supplier_sku']) ? $row['supplier_sku'] : '')) { $row['category_text_clean'] = ''; }
            $row['target_category_id'] = isset($parsed['target_category_id']) ? (int)$parsed['target_category_id'] : 0;
            $row['supplier_price'] = isset($parsed['supplier_price']) ? $parsed['supplier_price'] : (isset($row['supplier_price']) ? $row['supplier_price'] : 0);
            $display_price = $this->buildSupplierPriceDisplay($row, $parsed);
            $row['supplier_raw_price'] = $this->formatPriceValue($display_price['supplier_raw_price']);
            $row['supplier_currency'] = $display_price['supplier_currency'];
            $row['supplier_price_base'] = $this->formatPriceValue($display_price['supplier_price_base']);
            $row['supplier_price'] = $this->formatPriceValue($display_price['supplier_price_base']);
            $row['purchase_price'] = $this->formatPriceValue(isset($parsed['purchase_price']) ? $parsed['purchase_price'] : 0);
            $row['sale_price'] = $this->formatPriceValue(isset($parsed['sale_price']) ? $parsed['sale_price'] : (isset($row['sale_price']) ? $row['sale_price'] : 0));
            $row['stock_text'] = $this->cleanStockTextForDisplay(isset($parsed['stock_text']) ? $parsed['stock_text'] : '');
            $row['quantity'] = isset($parsed['quantity']) ? $parsed['quantity'] : 0;
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $row['image'] = isset($parsed['main_image_url']) ? $parsed['main_image_url'] : '';
            $rows[] = $row;
        }
        return array('rows' => $rows, 'total' => (int)$count->row['total'], 'page' => $page, 'limit' => $limit);
    }

    public function getNewStatsDb($supplier_id = 0) {
        $where = $supplier_id ? " WHERE supplier_id = '" . (int)$supplier_id . "'" : '';
        $q = $this->db->query("SELECT status, COUNT(*) AS total FROM `" . DB_PREFIX . "ccp_ssp_new_product`" . $where . " GROUP BY status");
        $stats = array('total' => 0, 'pending' => 0, 'duplicate' => 0, 'excluded' => 0, 'created' => 0, 'error' => 0, 'skipped' => 0);
        foreach ($q->rows as $row) {
            $status = $row['status'];
            $stats['total'] += (int)$row['total'];
            if (isset($stats[$status])) { $stats[$status] = (int)$row['total']; }
        }
        return $stats;
    }

    public function getQueuePaged($filters = array()) {
        $limit = max(10, min(500, isset($filters['limit']) ? (int)$filters['limit'] : 100));
        $page = max(1, isset($filters['page']) ? (int)$filters['page'] : 1);
        $start = ($page - 1) * $limit;
        $where = array();
        if (!empty($filters['supplier_id'])) { $where[] = "q.supplier_id = '" . (int)$filters['supplier_id'] . "'"; }
        if (!empty($filters['status'])) { $where[] = "q.status = '" . $this->db->escape($filters['status']) . "'"; }
        if (!empty($filters['text'])) { $where[] = "(q.supplier_product_url LIKE '%" . $this->db->escape($filters['text']) . "%' OR q.last_error LIKE '%" . $this->db->escape($filters['text']) . "%')"; }
        $sql_where = $where ? " WHERE " . implode(' AND ', $where) : '';
        $count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ccp_ssp_queue` q" . $sql_where);
        $sort_map = array('queue_id' => 'q.queue_id', 'supplier' => 's.name', 'action' => 'q.action', 'status' => 'q.status', 'attempts' => 'q.attempts', 'date' => 'q.date_modified');
        $sort_key = isset($filters['sort']) ? $filters['sort'] : '';
        $order = (isset($filters['order']) && strtolower($filters['order']) === 'asc') ? 'ASC' : 'DESC';
        $order_sql = isset($sort_map[$sort_key]) ? ($sort_map[$sort_key] . ' ' . $order . ', q.queue_id DESC') : 'q.queue_id DESC';
        $q = $this->db->query("SELECT q.*, s.name AS provider_name FROM `" . DB_PREFIX . "ccp_ssp_queue` q LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (q.supplier_id = s.supplier_id)" . $sql_where . " ORDER BY " . $order_sql . " LIMIT " . (int)$start . "," . (int)$limit);
        $rows = $q->rows;
        foreach ($rows as &$row) {
            $row['payload_title'] = '';
            $row['payload_mode'] = '';
            $payload = json_decode(isset($row['payload']) ? (string)$row['payload'] : '', true);
            if (is_array($payload)) {
                $row['payload_title'] = isset($payload['title']) ? (string)$payload['title'] : '';
                $row['payload_mode'] = isset($payload['mode']) ? (string)$payload['mode'] : '';
            }
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
        }
        unset($row);
        return array('rows' => $rows, 'total' => (int)$count->row['total'], 'page' => $page, 'limit' => $limit);
    }

    public function getLogsPaged($filters = array()) {
        $limit = max(10, min(500, isset($filters['limit']) ? (int)$filters['limit'] : 100));
        $page = max(1, isset($filters['page']) ? (int)$filters['page'] : 1);
        $start = ($page - 1) * $limit;
        $where = array();
        if (!empty($filters['supplier_id'])) { $where[] = "l.supplier_id = '" . (int)$filters['supplier_id'] . "'"; }
        if (!empty($filters['level'])) { $where[] = "l.level = '" . $this->db->escape($filters['level']) . "'"; }
        if (!empty($filters['text'])) { $where[] = "(l.title LIKE '%" . $this->db->escape($filters['text']) . "%' OR l.message LIKE '%" . $this->db->escape($filters['text']) . "%' OR l.related_url LIKE '%" . $this->db->escape($filters['text']) . "%')"; }
        $sql_where = $where ? " WHERE " . implode(' AND ', $where) : '';
        $count = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "ccp_ssp_log` l" . $sql_where);
        $sort_map = array('log_id' => 'l.log_id', 'supplier' => 's.name', 'level' => 'l.level', 'title' => 'l.title', 'date' => 'l.date_added');
        $sort_key = isset($filters['sort']) ? $filters['sort'] : '';
        $order = (isset($filters['order']) && strtolower($filters['order']) === 'asc') ? 'ASC' : 'DESC';
        $order_sql = isset($sort_map[$sort_key]) ? ($sort_map[$sort_key] . ' ' . $order . ', l.log_id DESC') : 'l.log_id DESC';
        $q = $this->db->query("SELECT l.*, s.name AS provider_name FROM `" . DB_PREFIX . "ccp_ssp_log` l LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (l.supplier_id = s.supplier_id)" . $sql_where . " ORDER BY " . $order_sql . " LIMIT " . (int)$start . "," . (int)$limit);
        $rows = array();
        foreach ($q->rows as $row) {
            $row['related_url_label'] = $this->shortenUrlForDisplay(isset($row['related_url']) ? $row['related_url'] : '');
            $rows[] = $row;
        }
        return array('rows' => $rows, 'total' => (int)$count->row['total'], 'page' => $page, 'limit' => $limit);
    }

    public function getHistoryPaged($filters = array()) {
        $limit = max(10, min(500, isset($filters['limit']) ? (int)$filters['limit'] : 100));
        $page = max(1, isset($filters['page']) ? (int)$filters['page'] : 1);
        $start = ($page - 1) * $limit;
        $where = array();
        if (!empty($filters['supplier_id'])) { $where[] = "h.supplier_id = '" . (int)$filters['supplier_id'] . "'"; }
        if (!empty($filters['action'])) { $where[] = "h.action = '" . $this->db->escape($filters['action']) . "'"; }
        if (!empty($filters['text'])) { $where[] = "(h.supplier_product_url LIKE '%" . $this->db->escape($filters['text']) . "%' OR h.note LIKE '%" . $this->db->escape($filters['text']) . "%' OR pd.name LIKE '%" . $this->db->escape($filters['text']) . "%')"; }
        $sql_where = $where ? " WHERE " . implode(' AND ', $where) : '';
        $join = " FROM `" . DB_PREFIX . "ccp_ssp_history` h LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (h.supplier_id = s.supplier_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (h.product_id = pd.product_id AND pd.language_id = '" . (int)$this->getLanguageId() . "')";
        $count = $this->db->query("SELECT COUNT(*) AS total" . $join . $sql_where);
        $sort_map = array('history_id' => 'h.history_id', 'supplier' => 's.name', 'action' => 'h.action', 'field' => 'h.field_changed', 'product' => 'pd.name', 'date' => 'h.date_added');
        $sort_key = isset($filters['sort']) ? $filters['sort'] : '';
        $order = (isset($filters['order']) && strtolower($filters['order']) === 'asc') ? 'ASC' : 'DESC';
        $order_sql = isset($sort_map[$sort_key]) ? ($sort_map[$sort_key] . ' ' . $order . ', h.history_id DESC') : 'h.history_id DESC';
        $q = $this->db->query("SELECT h.*, s.name AS provider_name, pd.name AS product_name" . $join . $sql_where . " ORDER BY " . $order_sql . " LIMIT " . (int)$start . "," . (int)$limit);
        $rows = array();
        foreach ($q->rows as $row) {
            $row['old_price'] = $this->formatPriceValue(isset($row['old_price']) ? $row['old_price'] : 0);
            $row['new_price'] = $this->formatPriceValue(isset($row['new_price']) ? $row['new_price'] : 0);
            $row['supplier_price'] = $this->formatPriceValue(isset($row['supplier_price']) ? $row['supplier_price'] : 0);
            $row['sale_price'] = $this->formatPriceValue(isset($row['sale_price']) ? $row['sale_price'] : 0);
            $row['supplier_url_label'] = $this->shortenUrlForDisplay(isset($row['supplier_product_url']) ? $row['supplier_product_url'] : '');
            $rows[] = $row;
        }
        return array('rows' => $rows, 'total' => (int)$count->row['total'], 'page' => $page, 'limit' => $limit);
    }

    public function syncCategoryMapRows($supplier_id, $category_map) {
        $supplier_id = (int)$supplier_id;
        if ($supplier_id <= 0 || !is_array($category_map)) { return; }
        foreach ($category_map as $row) {
            $needle = isset($row['needle']) ? trim((string)$row['needle']) : '';
            if ($needle === '') { continue; }
            $category_id = isset($row['category_id']) ? (int)$row['category_id'] : 0;
            $allow = !empty($row['allow']) ? 1 : 0;
            $exists = $this->db->query("SELECT map_id FROM `" . DB_PREFIX . "ccp_ssp_category_map` WHERE supplier_id = '" . $supplier_id . "' AND supplier_category = '" . $this->db->escape($needle) . "' LIMIT 1");
            if ($exists->num_rows) {
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_category_map` SET category_id = '" . $category_id . "', allow_import = '" . $allow . "', date_modified = NOW() WHERE map_id = '" . (int)$exists->row['map_id'] . "'");
            } else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_category_map` SET supplier_id = '" . $supplier_id . "', supplier_category = '" . $this->db->escape($needle) . "', category_id = '" . $category_id . "', allow_import = '" . $allow . "', date_added = NOW(), date_modified = NOW()");
            }
        }
    }

    public function getCategoryMapRows($supplier_id = 0, $limit = 300) {
        $supplier_id = (int)$supplier_id;
        if ($supplier_id > 0) {
            $where = " WHERE cm.supplier_id = '" . $supplier_id . "'";
        } else {
            $where = '';
        }
        $q = $this->db->query("SELECT cm.*, s.name AS provider_name, cd.name AS category_name FROM `" . DB_PREFIX . "ccp_ssp_category_map` cm LEFT JOIN `" . DB_PREFIX . "ccp_ssp_supplier` s ON (cm.supplier_id = s.supplier_id) LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (cm.category_id = cd.category_id AND cd.language_id = '" . (int)$this->getLanguageId() . "')" . $where . " ORDER BY cm.supplier_category ASC LIMIT " . (int)$limit);
        return $q->rows;
    }

    public function cleanupOldData() {
        $review_days = (int)$this->config->get('module_supplier_sync_parser_pro_review_retention_days') ?: 14;
        $log_days = (int)$this->config->get('module_supplier_sync_parser_pro_log_retention_days') ?: 60;
        $history_days = (int)$this->config->get('module_supplier_sync_parser_pro_history_retention_days') ?: 180;
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_review` WHERE status IN ('applied','created','skipped','excluded','error') AND date_modified < DATE_SUB(NOW(), INTERVAL " . (int)$review_days . " DAY)");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_queue` WHERE status IN ('done','skipped','error') AND date_modified < DATE_SUB(NOW(), INTERVAL " . (int)$review_days . " DAY)");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_log` WHERE date_added < DATE_SUB(NOW(), INTERVAL " . (int)$log_days . " DAY)");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_history` WHERE date_added < DATE_SUB(NOW(), INTERVAL " . (int)$history_days . " DAY)");
    }



    private function normalizeSupplierTextColumns() {
        $columns = array(
            'product_url_xpath' => 'product_url_attr',
            'list_category_xpath' => 'product_url_xpath',
            'next_page_xpath' => 'product_url_attr',
            'sku_xpath' => 'next_page_xpath',
            'ean_xpath' => 'sku_xpath',
            'upc_xpath' => 'ean_xpath',
            'mpn_xpath' => 'upc_xpath',
            'name_xpath' => 'mpn_xpath',
            'price_xpath' => 'name_xpath',
            'stock_xpath' => 'price_xpath',
            'category_xpath' => 'stock_xpath',
            'description_xpath' => 'category_xpath',
            'meta_description_xpath' => 'description_xpath',
            'meta_keyword_xpath' => 'meta_description_xpath',
            'manufacturer_xpath' => 'meta_keyword_xpath',
            'image_xpath' => 'manufacturer_xpath',
            'additional_images_xpath' => 'image_xpath',
            'attribute_row_xpath' => 'additional_images_xpath',
            'attribute_name_xpath' => 'attribute_row_xpath',
            'attribute_value_xpath' => 'attribute_name_xpath',
            'option_row_xpath' => 'attribute_value_xpath',
            'option_name_xpath' => 'option_row_xpath',
            'option_value_xpath' => 'option_name_xpath'
        );

        foreach ($columns as $column => $after) {
            $this->modifySupplierColumnToText($column, $after);
        }
    }

    private function modifySupplierColumnToText($column, $after = '') {
        $table_name = DB_PREFIX . 'ccp_ssp_supplier';
        $column_name = preg_replace('/[^a-z0-9_]/i', '', $column);
        $after_name = preg_replace('/[^a-z0-9_]/i', '', $after);

        $q = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table_name) . "` LIKE '" . $this->db->escape($column_name) . "'");
        if (!$q->num_rows) {
            return;
        }

        $type = strtolower((string)$q->row['Type']);
        if (strpos($type, 'text') !== false || strpos($type, 'blob') !== false) {
            return;
        }

        $sql = "ALTER TABLE `" . $this->db->escape($table_name) . "` MODIFY `" . $this->db->escape($column_name) . "` TEXT NOT NULL";
        if ($after_name !== '') {
            $after_q = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table_name) . "` LIKE '" . $this->db->escape($after_name) . "'");
            if ($after_q->num_rows) {
                $sql .= " AFTER `" . $this->db->escape($after_name) . "`";
            }
        }
        $this->db->query($sql);
    }

    private function tableExists($table) {
        $table_name = DB_PREFIX . preg_replace('/[^a-z0-9_]/i', '', $table);
        $q = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($table_name) . "'");
        return (bool)$q->num_rows;
    }

    private function addIndexIfMissing($table, $index, $columns) {
        $table_name = DB_PREFIX . preg_replace('/[^a-z0-9_]/i', '', $table);
        $index_name = preg_replace('/[^a-z0-9_]/i', '', $index);
        $q = $this->db->query("SHOW INDEX FROM `" . $this->db->escape($table_name) . "` WHERE Key_name = '" . $this->db->escape($index_name) . "'");
        if (!$q->num_rows) {
            $this->db->query("ALTER TABLE `" . $this->db->escape($table_name) . "` ADD KEY `" . $this->db->escape($index_name) . "` (" . $columns . ")");
        }
    }

    private function addColumnIfMissing($table, $column, $definition) {
        $table_name = DB_PREFIX . preg_replace('/[^a-z0-9_]/i', '', $table);
        $column_name = preg_replace('/[^a-z0-9_]/i', '', $column);
        $q = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($table_name) . "` LIKE '" . $this->db->escape($column_name) . "'");
        if (!$q->num_rows) {
            $this->db->query("ALTER TABLE `" . $this->db->escape($table_name) . "` ADD `" . $this->db->escape($column_name) . "` " . $definition);
        }
    }


    public function getCurrencies() {
        $q = $this->db->query("SELECT currency_id, title, code, symbol_left, symbol_right, value, status FROM `" . DB_PREFIX . "currency` WHERE status = '1' ORDER BY code ASC");
        return $q->rows;
    }

    public function getLanguages() {
        $q = $this->db->query("SELECT language_id, name, code, sort_order, status FROM `" . DB_PREFIX . "language` WHERE status = '1' ORDER BY sort_order ASC, name ASC");
        return $q->rows;
    }

    private function buildSupplierPriceDisplay($row, $parsed) {
        $base_currency = strtoupper((string)$this->config->get('config_currency'));
        $supplier_currency = '';
        if (isset($parsed['supplier_currency']) && trim((string)$parsed['supplier_currency']) !== '') {
            $supplier_currency = strtoupper(trim((string)$parsed['supplier_currency']));
        } elseif (isset($row['provider_currency_code']) && trim((string)$row['provider_currency_code']) !== '') {
            $supplier_currency = strtoupper(trim((string)$row['provider_currency_code']));
        } else {
            $supplier_currency = $base_currency;
        }

        $aliases = array('UAN' => 'UAH', 'GRN' => 'UAH', 'ГРН' => 'UAH', 'ГРН.' => 'UAH');
        if (isset($aliases[$supplier_currency])) {
            $supplier_currency = $aliases[$supplier_currency];
        }

        $supplier_price_base = 0.0;
        if (isset($row['supplier_price']) && (float)$row['supplier_price'] > 0) {
            $supplier_price_base = (float)$row['supplier_price'];
        } elseif (isset($parsed['supplier_price']) && (float)$parsed['supplier_price'] > 0) {
            $supplier_price_base = (float)$parsed['supplier_price'];
        } elseif (isset($parsed['supplier_price_base']) && (float)$parsed['supplier_price_base'] > 0) {
            $supplier_price_base = (float)$parsed['supplier_price_base'];
        }

        $supplier_raw_price = 0.0;
        if (isset($parsed['supplier_raw_price']) && (float)$parsed['supplier_raw_price'] > 0) {
            $supplier_raw_price = (float)$parsed['supplier_raw_price'];
        }

        if ($supplier_raw_price <= 0 && $supplier_price_base > 0) {
            if ($supplier_currency !== '' && $supplier_currency !== $base_currency) {
                $supplier_raw_price = $this->convertBaseCurrencyToSupplier($supplier_price_base, $supplier_currency);
            } else {
                $supplier_raw_price = $supplier_price_base;
            }
        }

        return array(
            'supplier_raw_price' => round((float)$supplier_raw_price, 2),
            'supplier_currency' => $supplier_currency,
            'supplier_price_base' => round((float)$supplier_price_base, 2)
        );
    }

    private function convertBaseCurrencyToSupplier($amount, $supplier_currency) {
        $amount = (float)$amount;
        $supplier_currency = strtoupper(trim((string)$supplier_currency));
        $base_currency = strtoupper((string)$this->config->get('config_currency'));
        if ($amount <= 0 || $supplier_currency === '' || $supplier_currency === $base_currency) {
            return $amount;
        }

        $source_value = $this->getCurrencyValue($supplier_currency);
        $base_value = $this->getCurrencyValue($base_currency);
        if ($source_value > 0 && $base_value > 0) {
            return ($amount / $base_value) * $source_value;
        }

        return $amount;
    }

    private function getCurrencyValue($code) {
        $code = strtoupper(trim((string)$code));
        if ($code === '') {
            return 0.0;
        }
        $q = $this->db->query("SELECT value FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($code) . "' AND status = '1' LIMIT 1");
        if ($q->num_rows && (float)$q->row['value'] > 0) {
            return (float)$q->row['value'];
        }
        if ($code === strtoupper((string)$this->config->get('config_currency'))) {
            return 1.0;
        }
        return 0.0;
    }

    private function normalizeCurrencyCode($code) {
        $code = strtoupper(trim((string)$code));
        $aliases = array('UAN' => 'UAH', 'GRN' => 'UAH', 'ГРН' => 'UAH', 'ГРН.' => 'UAH');
        if (isset($aliases[$code])) {
            $code = $aliases[$code];
        }
        if ($code === '') {
            $code = strtoupper((string)$this->config->get('config_currency'));
        }
        $q = $this->db->query("SELECT code FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($code) . "' AND status = '1' LIMIT 1");
        if ($q->num_rows) {
            return $q->row['code'];
        }
        return strtoupper((string)$this->config->get('config_currency'));
    }

    private function normalizeLanguageCode($code) {
        $code = strtolower(trim((string)$code));
        if ($code === '') {
            return '';
        }
        $q = $this->db->query("SELECT code FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape($code) . "' AND status = '1' LIMIT 1");
        return $q->num_rows ? $q->row['code'] : '';
    }

    private function normalizeTargetLanguageId($language_id) {
        $language_id = (int)$language_id;
        if ($language_id > 0) {
            $q = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE language_id = '" . (int)$language_id . "' AND status = '1' LIMIT 1");
            if ($q->num_rows) {
                return (int)$q->row['language_id'];
            }
        }
        return $this->getLanguageId();
    }

    private function getLanguageId() {
        $language_id = (int)$this->config->get('config_language_id');
        if ($language_id > 0) {
            return $language_id;
        }
        $code = $this->config->get('config_language');
        if ($code) {
            $q = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE code = '" . $this->db->escape($code) . "' LIMIT 1");
            if ($q->num_rows) {
                return (int)$q->row['language_id'];
            }
        }
        $q = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` ORDER BY language_id ASC LIMIT 1");
        return $q->num_rows ? (int)$q->row['language_id'] : 1;
    }

    private function generateToken() {
        return sha1(uniqid('supplier_sync_parser_pro', true) . mt_rand());
    }
}
