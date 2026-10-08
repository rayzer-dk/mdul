<?php
class ModelExtensionModuleImportPro extends Model {
    private function lang($key, $fallback = '') {
        if (isset($this->language)) {
            $value = (string)$this->language->get($key);
            if ($value !== $key && $value !== '') {
                return $value;
            }
        }
        return $fallback !== '' ? $fallback : $key;
    }

    public function install() {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "import_pro_profile` (
            `profile_id` int(11) NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `format` varchar(32) NOT NULL,
            `source_type` varchar(32) NOT NULL DEFAULT 'file',
            `source_path` text NOT NULL,
            `match_field` varchar(64) NOT NULL DEFAULT 'model',
            `key_field` varchar(64) NOT NULL DEFAULT 'model',
            `default_language_id` int(11) NOT NULL DEFAULT 0,
            `settings_json` mediumtext NOT NULL,
            `field_map_json` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`profile_id`),
            KEY `idx_import_pro_profile_date_modified` (`date_modified`),
            KEY `idx_import_pro_profile_name` (`name`),
            KEY `idx_import_pro_profile_format` (`format`),
            KEY `idx_import_pro_profile_source_type` (`source_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "import_pro_run` (
            `run_id` int(11) NOT NULL AUTO_INCREMENT,
            `profile_id` int(11) NOT NULL,
            `is_dry_run` tinyint(1) NOT NULL DEFAULT 0,
            `run_mode` varchar(32) NOT NULL DEFAULT 'full',
            `created_count` int(11) NOT NULL DEFAULT 0,
            `updated_count` int(11) NOT NULL DEFAULT 0,
            `skipped_count` int(11) NOT NULL DEFAULT 0,
            `error_count` int(11) NOT NULL DEFAULT 0,
            `log_json` mediumtext NOT NULL,
            `date_added` datetime NOT NULL,
            PRIMARY KEY (`run_id`),
            KEY `idx_import_pro_run_profile_id` (`profile_id`),
            KEY `idx_import_pro_run_date_added` (`date_added`),
            KEY `idx_import_pro_run_profile_date` (`profile_id`, `date_added`),
            KEY `idx_import_pro_run_mode_date` (`run_mode`, `date_added`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "import_pro_supplier` (
            `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
            `profile_id` int(11) NOT NULL DEFAULT 0,
            `name` varchar(255) NOT NULL,
            `status` tinyint(1) NOT NULL DEFAULT 1,
            `markup_type` varchar(16) NOT NULL DEFAULT 'none',
            `markup_value` decimal(15,4) NOT NULL DEFAULT 0.0000,
            `fixed_markup` decimal(15,4) NOT NULL DEFAULT 0.0000,
            `price_rounding` decimal(15,4) NOT NULL DEFAULT 0.0000,
            `price_update_source_type` varchar(32) NOT NULL DEFAULT '',
            `price_update_source_path` text NOT NULL,
            `date_added` datetime NOT NULL,
            `date_modified` datetime NOT NULL,
            PRIMARY KEY (`supplier_id`),
            KEY `idx_import_pro_supplier_profile_id` (`profile_id`),
            KEY `idx_import_pro_supplier_name` (`name`),
            KEY `idx_import_pro_supplier_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "import_pro_supplier_product` (
            `supplier_product_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL,
            `product_id` int(11) NOT NULL,
            `external_product_id` varchar(255) NOT NULL,
            `supplier_sku` varchar(255) NOT NULL DEFAULT '',
            `last_price` decimal(15,4) NOT NULL DEFAULT 0.0000,
            `last_quantity` int(11) NOT NULL DEFAULT 0,
            `last_hash` char(40) NOT NULL DEFAULT '',
            `last_seen` datetime NOT NULL,
            PRIMARY KEY (`supplier_product_id`),
            UNIQUE KEY `uq_import_pro_supplier_external` (`supplier_id`, `external_product_id`),
            KEY `idx_import_pro_supplier_product_product_id` (`product_id`),
            KEY `idx_import_pro_supplier_product_sku` (`supplier_sku`),
            KEY `idx_import_pro_supplier_product_seen` (`last_seen`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "import_pro_supplier_category` (
            `supplier_category_id` int(11) NOT NULL AUTO_INCREMENT,
            `supplier_id` int(11) NOT NULL,
            `category_id` int(11) NOT NULL DEFAULT 0,
            `external_category_id` varchar(255) NOT NULL,
            `external_parent_category_id` varchar(255) NOT NULL DEFAULT '',
            `name` varchar(255) NOT NULL DEFAULT '',
            `last_seen` datetime NOT NULL,
            PRIMARY KEY (`supplier_category_id`),
            UNIQUE KEY `uq_import_pro_supplier_category` (`supplier_id`, `external_category_id`),
            KEY `idx_import_pro_supplier_category_category_id` (`category_id`),
            KEY `idx_import_pro_supplier_category_parent` (`external_parent_category_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->upgradeSchema();
        $this->load->model('extension/module/import_pro_queue');
        $this->model_extension_module_import_pro_queue->install();
        $this->installDefaultSettings();
    }

    private function installDefaultSettings() {
        if (!$this->canLoadModel('setting/setting')) {
            return;
        }

        $this->load->model('setting/setting');
        $current = $this->model_setting_setting->getSetting('module_import_pro');

        $defaults = array(
            'module_import_pro_status' => 0,
            'module_import_pro_default_match_field' => 'model',
            'module_import_pro_default_key_field' => 'model',
            'module_import_pro_cron_token' => bin2hex(random_bytes(16)),
            'module_import_pro_url_timeout' => 60,
            'module_import_pro_image_subdir' => 'catalog/import_pro/',
            'module_import_pro_download_images' => 1,
            'module_import_pro_image_limit' => 10,
            'module_import_pro_uninstall_delete_data' => 0,
            'module_import_pro_version' => '3.7.0'
        );

        $settings = $current;
        $previous_version = isset($current['module_import_pro_version']) ? (string)$current['module_import_pro_version'] : '';
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $settings) || $settings[$key] === '' || $settings[$key] === null) {
                $settings[$key] = $value;
            }
        }

        if ($previous_version !== '3.7.0') {
            $settings['module_import_pro_status'] = 0;
        }

        if (empty($settings['module_import_pro_cron_token']) || strlen((string)$settings['module_import_pro_cron_token']) < 16) {
            $settings['module_import_pro_cron_token'] = bin2hex(random_bytes(16));
        }

        $this->model_setting_setting->editSetting('module_import_pro', $settings);
    }

    private function canLoadModel($route) {
        $route = preg_replace('/[^a-zA-Z0-9_\/]/', '', (string)$route);
        if ($route === '') {
            return false;
        }

        $base = defined('DIR_APPLICATION') ? rtrim(DIR_APPLICATION, '/\\') . '/' : '';
        if ($base === '') {
            return false;
        }

        return is_file($base . 'model/' . $route . '.php');
    }

    private function upgradeSchema() {
        $this->normalizeTable('import_pro_profile');
        $this->normalizeTable('import_pro_run');
        $this->normalizeTable('import_pro_supplier');
        $this->normalizeTable('import_pro_supplier_product');
        $this->normalizeTable('import_pro_supplier_category');

        $this->ensureColumn('import_pro_run', 'run_mode', "`run_mode` varchar(32) NOT NULL DEFAULT 'full' AFTER `is_dry_run`");
        $this->ensureColumn('import_pro_supplier', 'profile_id', "`profile_id` int(11) NOT NULL DEFAULT 0 AFTER `supplier_id`");
        $this->ensureColumn('import_pro_supplier', 'markup_type', "`markup_type` varchar(16) NOT NULL DEFAULT 'none' AFTER `status`");
        $this->ensureColumn('import_pro_supplier', 'markup_value', "`markup_value` decimal(15,4) NOT NULL DEFAULT 0.0000 AFTER `markup_type`");
        $this->ensureColumn('import_pro_supplier', 'fixed_markup', "`fixed_markup` decimal(15,4) NOT NULL DEFAULT 0.0000 AFTER `markup_value`");
        $this->ensureColumn('import_pro_supplier', 'price_rounding', "`price_rounding` decimal(15,4) NOT NULL DEFAULT 0.0000 AFTER `fixed_markup`");
        $this->ensureColumn('import_pro_supplier', 'price_update_source_type', "`price_update_source_type` varchar(32) NOT NULL DEFAULT '' AFTER `price_rounding`");
        $this->ensureColumn('import_pro_supplier', 'price_update_source_path', "`price_update_source_path` text NOT NULL AFTER `price_update_source_type`");
        $this->ensureColumn('import_pro_supplier_product', 'supplier_sku', "`supplier_sku` varchar(255) NOT NULL DEFAULT '' AFTER `external_product_id`");
        $this->ensureColumn('import_pro_supplier_product', 'last_hash', "`last_hash` char(40) NOT NULL DEFAULT '' AFTER `last_quantity`");
        $this->ensureIndex('import_pro_profile', 'idx_import_pro_profile_date_modified', '`date_modified`');
        $this->ensureIndex('import_pro_profile', 'idx_import_pro_profile_name', '`name`');
        $this->ensureIndex('import_pro_profile', 'idx_import_pro_profile_format', '`format`');
        $this->ensureIndex('import_pro_profile', 'idx_import_pro_profile_source_type', '`source_type`');
        $this->ensureIndex('import_pro_run', 'idx_import_pro_run_profile_id', '`profile_id`');
        $this->ensureIndex('import_pro_run', 'idx_import_pro_run_date_added', '`date_added`');
        $this->ensureIndex('import_pro_run', 'idx_import_pro_run_profile_date', '`profile_id`, `date_added`');
        $this->ensureIndex('import_pro_run', 'idx_import_pro_run_mode_date', '`run_mode`, `date_added`');
        $this->ensureIndex('import_pro_supplier', 'idx_import_pro_supplier_profile_id', '`profile_id`');
        $this->ensureIndex('import_pro_supplier', 'idx_import_pro_supplier_name', '`name`');
        $this->ensureIndex('import_pro_supplier', 'idx_import_pro_supplier_status', '`status`');
        $this->ensureIndex('import_pro_supplier_product', 'idx_import_pro_supplier_product_product_id', '`product_id`');
        $this->ensureIndex('import_pro_supplier_product', 'idx_import_pro_supplier_product_sku', '`supplier_sku`');
        $this->ensureIndex('import_pro_supplier_product', 'idx_import_pro_supplier_product_seen', '`last_seen`');
        $this->ensureIndex('import_pro_supplier_category', 'idx_import_pro_supplier_category_category_id', '`category_id`');
        $this->ensureIndex('import_pro_supplier_category', 'idx_import_pro_supplier_category_parent', '`external_parent_category_id`');
        $this->ensureProductMainCategoryColumn();
    }

    private function ensureProductMainCategoryColumn() {
        if ($this->tableColumnExists('product_to_category', 'main_category')) {
            $this->ensureIndex('product_to_category', 'idx_import_pro_ptc_main_category', '`product_id`, `main_category`');
        }

        if ($this->tableColumnExists('product', 'main_category_id')) {
            $this->ensureIndex('product', 'idx_import_pro_main_category_id', '`main_category_id`');
        }
    }

    private function tableExists($table) {
        static $cache = array();

        $table = preg_replace('~[^a-zA-Z0-9_]+~', '', (string)$table);
        if ($table === '') {
            return false;
        }

        $full_table = DB_PREFIX . $table;
        if (!array_key_exists($full_table, $cache)) {
            $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape($full_table) . "'");
            if (!$query->num_rows) {
                $query = $this->db->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape($full_table) . "' LIMIT 1");
            }
            $cache[$full_table] = $query->num_rows ? true : false;
        }

        return $cache[$full_table];
    }

    private function normalizeTable($table) {
        if (!$this->tableExists($table)) {
            return;
        }

        $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ENGINE=InnoDB");
        $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function ensureColumn($table, $column, $definition) {
        if (!$this->tableExists($table) || $this->tableColumnExists($table, $column)) {
            return;
        }

        $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD " . $definition);
    }

    private function indexExists($table, $index) {
        $table = preg_replace('~[^a-zA-Z0-9_]+~', '', (string)$table);
        $index = preg_replace('~[^a-zA-Z0-9_]+~', '', (string)$index);
        if ($table === '' || $index === '' || !$this->tableExists($table)) {
            return false;
        }

        $query = $this->db->query("SHOW INDEX FROM `" . DB_PREFIX . $table . "` WHERE Key_name = '" . $this->db->escape($index) . "'");
        return $query->num_rows ? true : false;
    }

    private function ensureIndex($table, $index, $columns) {
        if (!$this->tableExists($table) || $this->indexExists($table, $index)) {
            return;
        }

        $this->db->query("ALTER TABLE `" . DB_PREFIX . $table . "` ADD INDEX `" . $this->db->escape($index) . "` (" . $columns . ")");
    }
    public function uninstall() {
        $delete_data = false;

        if ($this->canLoadModel('setting/setting')) {
            $this->load->model('setting/setting');
            $settings = $this->model_setting_setting->getSetting('module_import_pro');
            $delete_data = !empty($settings['module_import_pro_uninstall_delete_data']);
            $this->model_setting_setting->deleteSetting('module_import_pro');
        }

        // Remove only temporary source files owned by the module.
        // Imported product images are preserved because products may still reference them after uninstall.
        $this->deleteTree(DIR_DOWNLOAD . 'import_pro/');

        if ($delete_data) {
            if ($this->canLoadModel('extension/module/import_pro_queue')) {
                $this->load->model('extension/module/import_pro_queue');
                $this->model_extension_module_import_pro_queue->uninstall();
            }
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "import_pro_supplier_category`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "import_pro_supplier_product`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "import_pro_supplier`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "import_pro_run`");
            $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "import_pro_profile`");
        }
    }
    protected function deleteImageSubdir($subdir) {
        $subdir = trim((string)$subdir);
        if ($subdir === '') {
            return;
        }

        $subdir = str_replace('\\', '/', $subdir);
        $subdir = trim($subdir, '/');
        if ($subdir === '') {
            return;
        }

        $target = rtrim(str_replace('\\', '/', DIR_IMAGE), '/') . '/' . $subdir . '/';
        $base = rtrim(str_replace('\\', '/', DIR_IMAGE), '/') . '/';
        if (strpos($target, $base) !== 0) {
            return;
        }

        $this->deleteTree($target);
    }

    protected function deleteTree($path) {
        $path = str_replace('\\', '/', (string)$path);
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $item_path = rtrim($path, '/') . '/' . $item;
            if (is_dir($item_path)) {
                $this->deleteTree($item_path);
            } elseif (is_file($item_path)) {
                @unlink($item_path);
            }
        }

        @rmdir($path);
    }


    private function normalizeModuleSubdir($subdir) {
        $subdir = trim((string)$subdir);
        $subdir = str_replace('\\', '/', $subdir);
        $subdir = preg_replace('~\.\.+~', '', $subdir);
        $subdir = trim($subdir, '/');
        if ($subdir === '') {
            return 'catalog/import_pro/';
        }
        if (strpos($subdir, 'catalog/import_pro') !== 0) {
            $subdir = 'catalog/import_pro/' . ltrim($subdir, '/');
        }
        $subdir = preg_replace('~[^a-zA-Z0-9/_\-.]+~', '_', $subdir);
        $subdir = preg_replace('~/+~', '/', $subdir);
        return rtrim($subdir, '/') . '/';
    }

    private function isAllowedSourceExtension($filename) {
        $ext = strtolower(pathinfo((string)$filename, PATHINFO_EXTENSION));
        return in_array($ext, array('csv', 'xlsx', 'xml', 'yml', 'yaml', 'json', 'html', 'htm', 'txt'), true);
    }

    private function tableColumnExists($table, $column) {
        static $cache = array();

        $table = preg_replace('~[^a-zA-Z0-9_]+~', '', (string)$table);
        $column = preg_replace('~[^a-zA-Z0-9_]+~', '', (string)$column);
        if ($table === '' || $column === '' || !$this->tableExists($table)) {
            return false;
        }

        $full_table = DB_PREFIX . $table;
        $key = $full_table . '.' . $column;
        if (!array_key_exists($key, $cache)) {
            $query = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape($full_table) . "` LIKE '" . $this->db->escape($column) . "'");
            if (!$query->num_rows) {
                $query = $this->db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . $this->db->escape($full_table) . "' AND COLUMN_NAME = '" . $this->db->escape($column) . "' LIMIT 1");
            }
            $cache[$key] = $query->num_rows ? true : false;
        }

        return $cache[$key];
    }

    private function getMainCategoryStorageMode() {
        static $mode = null;

        if ($mode !== null) {
            return $mode;
        }

        $has_ptc_main = $this->tableColumnExists('product_to_category', 'main_category');
        $has_product_main = $this->tableColumnExists('product', 'main_category_id');

        if ($has_ptc_main && $has_product_main) {
            $mode = 'both';
        } elseif ($has_ptc_main) {
            $mode = 'product_to_category';
        } elseif ($has_product_main) {
            $mode = 'product';
        } else {
            $mode = 'fallback';
        }

        return $mode;
    }

    private function getMainCategoryExpression($product_alias = 'p') {
        $product_alias = preg_replace('~[^a-zA-Z0-9_]+~', '', (string)$product_alias);
        if ($product_alias === '') {
            $product_alias = 'p';
        }

        $parts = array();

        if ($this->tableColumnExists('product_to_category', 'main_category')) {
            $parts[] = "(SELECT p2cm.category_id FROM `" . DB_PREFIX . "product_to_category` p2cm WHERE p2cm.product_id = " . $product_alias . ".product_id AND p2cm.main_category='1' ORDER BY p2cm.category_id ASC LIMIT 1)";
        }

        if ($this->tableColumnExists('product', 'main_category_id')) {
            $parts[] = "NULLIF(" . $product_alias . ".main_category_id, 0)";
        }

        if ($this->tableExists('product_to_category')) {
            $parts[] = "(SELECT p2cf.category_id FROM `" . DB_PREFIX . "product_to_category` p2cf WHERE p2cf.product_id = " . $product_alias . ".product_id ORDER BY p2cf.category_id ASC LIMIT 1)";
        }

        $parts[] = "0";

        return "COALESCE(" . implode(', ', $parts) . ")";
    }

    private function normalizeGoogleProductCategoryValue($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }

        $digits = preg_replace('~[^0-9]+~', '', $value);
        if ($digits !== '') {
            return substr($digits, 0, 10);
        }

        return '';
    }

    private function getCategoryParentId($category_id) {
        $category_id = (int)$category_id;
        if (!$category_id || !$this->tableExists('category')) {
            return 0;
        }

        $query = $this->db->query("SELECT parent_id FROM `" . DB_PREFIX . "category` WHERE category_id='" . $category_id . "' LIMIT 1");
        return $query->num_rows ? (int)$query->row['parent_id'] : 0;
    }

    private function getGoogleProductCategoryByCategoryId($category_id, $store_id = 0) {
        $category_id = (int)$category_id;
        $store_id = (int)$store_id;
        if (!$category_id) {
            return '';
        }

        $visited = array();
        $current_id = $category_id;
        $depth = 0;

        while ($current_id && $depth < 30 && !isset($visited[$current_id])) {
            $visited[$current_id] = true;

            if ($this->tableExists('googleshopping_category') && $this->tableColumnExists('googleshopping_category', 'google_product_category') && $this->tableColumnExists('googleshopping_category', 'category_id') && $this->tableColumnExists('googleshopping_category', 'store_id')) {
                $query = $this->db->query("SELECT google_product_category FROM `" . DB_PREFIX . "googleshopping_category` WHERE category_id='" . (int)$current_id . "' AND store_id IN ('0','" . (int)$store_id . "') AND google_product_category <> '' ORDER BY (store_id='" . (int)$store_id . "') DESC LIMIT 1");
                if ($query->num_rows) {
                    $value = $this->normalizeGoogleProductCategoryValue($query->row['google_product_category']);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            if ($this->tableColumnExists('category', 'google_product_category_id')) {
                $query = $this->db->query("SELECT google_product_category_id FROM `" . DB_PREFIX . "category` WHERE category_id='" . (int)$current_id . "' AND google_product_category_id <> '' LIMIT 1");
                if ($query->num_rows) {
                    $value = $this->normalizeGoogleProductCategoryValue($query->row['google_product_category_id']);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            if ($this->tableColumnExists('category', 'google_product_category')) {
                $query = $this->db->query("SELECT google_product_category FROM `" . DB_PREFIX . "category` WHERE category_id='" . (int)$current_id . "' AND google_product_category <> '' LIMIT 1");
                if ($query->num_rows) {
                    $value = $this->normalizeGoogleProductCategoryValue($query->row['google_product_category']);
                    if ($value !== '') {
                        return $value;
                    }
                }
            }

            $current_id = $this->getCategoryParentId($current_id);
            $depth++;
        }

        return '';
    }

    private function saveGoogleProductCategory($category_id, $google_product_category, $store_id = 0) {
        $category_id = (int)$category_id;
        $store_id = (int)$store_id;
        $google_product_category = $this->normalizeGoogleProductCategoryValue($google_product_category);

        if (!$category_id || $google_product_category === '') {
            return;
        }

        if ($this->tableExists('googleshopping_category') && $this->tableColumnExists('googleshopping_category', 'google_product_category') && $this->tableColumnExists('googleshopping_category', 'category_id') && $this->tableColumnExists('googleshopping_category', 'store_id')) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "googleshopping_category` WHERE category_id='" . (int)$category_id . "' AND store_id='" . (int)$store_id . "'");
            $this->db->query("INSERT INTO `" . DB_PREFIX . "googleshopping_category` SET google_product_category='" . $this->db->escape($google_product_category) . "', store_id='" . (int)$store_id . "', category_id='" . (int)$category_id . "'");
        }

        if ($this->tableColumnExists('category', 'google_product_category_id')) {
            $this->db->query("UPDATE `" . DB_PREFIX . "category` SET google_product_category_id='" . $this->db->escape($google_product_category) . "' WHERE category_id='" . (int)$category_id . "'");
        }

        if ($this->tableColumnExists('category', 'google_product_category')) {
            $this->db->query("UPDATE `" . DB_PREFIX . "category` SET google_product_category='" . $this->db->escape($google_product_category) . "' WHERE category_id='" . (int)$category_id . "'");
        }
    }

    private function validateSourceReference($format, $source_type, $source_path) {
        $errors = array();

        $format = strtolower(trim((string)$format));
        if (!in_array($format, array('csv', 'xlsx', 'xml', 'yml', 'yaml', 'json', 'html'), true)) {
            $errors[] = 'Unsupported source format';
        }

        $source_type = trim((string)$source_type);
        if (!in_array($source_type, array('file', 'url'), true)) {
            $errors[] = 'Unsupported source type';
        }

        $source_path = trim((string)$source_path);
        if ($source_path === '') {
            $errors[] = 'Source path is required';
            return $errors;
        }

        if ($source_type === 'url') {
            if (!$this->isSafePublicUrl($source_path)) {
                $errors[] = 'Source URL must be a public HTTP/HTTPS address';
            }
            return $errors;
        }

        if (!$this->isAllowedSourceExtension($source_path)) {
            $errors[] = 'Unsupported file extension';
        }

        if (strpos($source_path, "\0") !== false || preg_match('~\.\./|\.\.\\\\~', $source_path)) {
            $errors[] = 'Source path must not contain traversal sequences';
        }

        $sandbox = rtrim(str_replace('\\', '/', DIR_DOWNLOAD), '/') . '/import_pro/';
        $normalized_path = str_replace('\\', '/', $source_path);
        $real_sandbox = realpath($sandbox);
        $real_path = realpath($normalized_path);
        $under_sandbox = false;

        if ($real_sandbox && $real_path) {
            $real_sandbox = rtrim(str_replace('\\', '/', $real_sandbox), '/') . '/';
            $real_path = str_replace('\\', '/', $real_path);
            $under_sandbox = (strpos($real_path . (is_dir($real_path) ? '/' : ''), $real_sandbox) === 0);
        } else {
            $under_sandbox = (strpos($normalized_path, $sandbox) === 0);
        }

        if (!$under_sandbox) {
            $errors[] = 'File source must live under DIR_DOWNLOAD/import_pro/. Use the Upload button or place the file there.';
        }

        return $errors;
    }

    private function isSafePublicUrl($url) {
        $url = trim((string)$url);
        if (!preg_match('~^https?://~i', $url)) {
            return false;
        }

        $parts = @parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host']) || !in_array(strtolower($parts['scheme']), array('http', 'https'), true)) {
            return false;
        }

        if (!empty($parts['user']) || !empty($parts['pass'])) {
            return false;
        }

        $host = trim($parts['host'], '[]');
        $host_lc = strtolower($host);
        if ($host_lc === 'localhost' || $host_lc === 'localhost.localdomain' || substr($host_lc, -6) === '.local') {
            return false;
        }

        $ips = array();
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            $ips[] = $host;
        } elseif (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (!empty($record['ip'])) {
                        $ips[] = $record['ip'];
                    }
                    if (!empty($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        if (!$ips && function_exists('gethostbynamel')) {
            $resolved = @gethostbynamel($host);
            if (is_array($resolved)) {
                $ips = array_merge($ips, $resolved);
            }
        }

        if (!$ips) {
            return false;
        }

        foreach (array_unique($ips) as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
    private function buildRedirectUrl($base_url, $location) {
        $location = trim((string)$location);
        if ($location === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $location)) {
            return $location;
        }

        $parts = @parse_url($base_url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        if (strpos($location, '//') === 0) {
            return $parts['scheme'] . ':' . $location;
        }

        $port = isset($parts['port']) ? ':' . (int)$parts['port'] : '';
        $base_path = isset($parts['path']) ? $parts['path'] : '/';
        $dir = preg_replace('~/[^/]*$~', '/', $base_path);

        if (strpos($location, '/') === 0) {
            $dir = '';
        }

        return $parts['scheme'] . '://' . $parts['host'] . $port . $dir . $location;
    }

    private function fetchRemoteBody($url, $settings = array(), $max_bytes = 104857600) {
        $url = trim((string)$url);
        if (!$this->isSafePublicUrl($url)) {
            throw new Exception('URL source must be a public HTTP/HTTPS address');
        }

        $timeout = !empty($settings['url_timeout']) ? (int)$settings['url_timeout'] : (int)$this->config->get('module_import_pro_url_timeout');
        $timeout = max(5, min(300, $timeout ? $timeout : 60));
        $auth_type = !empty($settings['auth_type']) ? $settings['auth_type'] : 'none';
        $username = isset($settings['auth_username']) ? (string)$settings['auth_username'] : '';
        $password = isset($settings['auth_password']) ? (string)$settings['auth_password'] : '';

        $current_url = $url;
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            if (!$this->isSafePublicUrl($current_url)) {
                throw new Exception('Redirect URL is not allowed');
            }

            $headers = array();

            if (function_exists('curl_init')) {
                $ch = curl_init($current_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                curl_setopt($ch, CURLOPT_USERAGENT, 'ImportPro/3.1');
                if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                    curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
                }
                if ($auth_type === 'basic' && $username !== '') {
                    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
                }

                $response = curl_exec($ch);
                $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $header_size = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $curl_error = curl_error($ch);
                unset($ch);

                if ($response === false) {
                    throw new Exception('Failed to download source from URL' . ($curl_error ? ': ' . $curl_error : ''));
                }

                $raw_headers = substr($response, 0, $header_size);
                $body = substr($response, $header_size);
                foreach (preg_split('/\r\n|\n|\r/', $raw_headers) as $line) {
                    if (strpos($line, ':') !== false) {
                        list($name, $value) = explode(':', $line, 2);
                        $headers[strtolower(trim($name))] = trim($value);
                    }
                }
            } else {
                $context_headers = array();
                if ($auth_type === 'basic' && $username !== '') {
                    $context_headers[] = 'Authorization: Basic ' . base64_encode($username . ':' . $password);
                }

                $context = stream_context_create(array(
                    'http' => array(
                        'timeout' => $timeout,
                        'follow_location' => 0,
                        'ignore_errors' => true,
                        'header' => implode("\r\n", $context_headers)
                    ),
                    'ssl' => array('verify_peer' => true, 'verify_peer_name' => true)
                ));

                $body = @file_get_contents($current_url, false, $context);
                $http_code = 0;
                $legacy_header_name = 'http_response_header';
                $response_headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : (isset($$legacy_header_name) ? $$legacy_header_name : array());
                if (is_array($response_headers)) {
                    foreach ($response_headers as $line) {
                        if (preg_match('~^HTTP/\S+\s+(\d+)~', $line, $m)) {
                            $http_code = (int)$m[1];
                        } elseif (strpos($line, ':') !== false) {
                            list($name, $value) = explode(':', $line, 2);
                            $headers[strtolower(trim($name))] = trim($value);
                        }
                    }
                }

                if ($body === false) {
                    throw new Exception('Failed to download source from URL');
                }
            }

            if (in_array($http_code, array(301, 302, 303, 307, 308), true) && !empty($headers['location'])) {
                $current_url = $this->buildRedirectUrl($current_url, $headers['location']);
                continue;
            }

            if ($http_code >= 400) {
                throw new Exception('Failed to download source from URL (HTTP ' . (int)$http_code . ')');
            }

            if ($body === '') {
                throw new Exception('Empty response from URL source');
            }

            if ($max_bytes > 0 && strlen($body) > $max_bytes) {
                throw new Exception('Remote file is too large. Maximum allowed size is ' . round($max_bytes / 1048576, 1) . ' MB');
            }

            return $body;
        }

        throw new Exception('Too many URL redirects');
    }

    private function validateProfileData($data, &$settings, &$field_map) {
        $errors = array();
        $name = isset($data['name']) ? trim((string)$data['name']) : '';
        if ($name === '') {
            $errors[] = 'Profile name is required';
        }

        $format = isset($data['format']) ? strtolower(trim((string)$data['format'])) : 'csv';
        $source_type = isset($data['source_type']) ? trim((string)$data['source_type']) : 'file';
        $source_path = isset($data['source_path']) ? trim((string)$data['source_path']) : '';

        $source_errors = $this->validateSourceReference($format, $source_type, $source_path);
        if ($source_errors) {
            $errors = array_merge($errors, $source_errors);
        }

        $match_field = isset($data['match_field']) ? trim((string)$data['match_field']) : 'model';
        if (!in_array($match_field, array('product_id', 'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'name'))) {
            $errors[] = 'Unsupported match field';
        }

        if (!is_array($field_map)) {
            $errors[] = 'Field map JSON is invalid';
        }

        $settings['image_subdir']  = $this->normalizeModuleSubdir(isset($settings['image_subdir']) ? $settings['image_subdir'] : 'catalog/import_pro/');
        $settings['url_timeout']   = max(5, min(300, isset($settings['url_timeout']) ? (int)$settings['url_timeout'] : 60));
        $settings['preview_limit'] = max(1, min(500, isset($settings['preview_limit']) ? (int)$settings['preview_limit'] : 20));
        $settings['run_limit']     = max(0, isset($settings['run_limit']) ? (int)$settings['run_limit'] : 0);
        $settings['image_limit']   = max(1, min(100, isset($settings['image_limit']) ? (int)$settings['image_limit'] : (int)$this->config->get('module_import_pro_image_limit')));
        if ($settings['image_limit'] < 1) { $settings['image_limit'] = 10; }
        $settings['auto_split_images'] = !empty($settings['auto_split_images']) ? 1 : 0;
        $settings['image_update_mode'] = (isset($settings['image_update_mode']) && in_array($settings['image_update_mode'], array('keep', 'main', 'replace', 'append'), true)) ? $settings['image_update_mode'] : 'replace';
        $settings['import_category_mode'] = (isset($settings['import_category_mode']) && in_array($settings['import_category_mode'], array('none', 'add', 'replace'), true)) ? $settings['import_category_mode'] : 'none';
        $settings['import_category_name'] = isset($settings['import_category_name']) ? trim((string)$settings['import_category_name']) : '';
        if ($settings['import_category_mode'] !== 'none' && $settings['import_category_name'] === '') { $settings['import_category_name'] = 'Imported products'; }
        $settings['import_product_status_mode'] = (isset($settings['import_product_status_mode']) && in_array($settings['import_product_status_mode'], array('file', 'enabled', 'disabled'), true)) ? $settings['import_product_status_mode'] : 'file';
        $settings['missing_product_action'] = (isset($settings['missing_product_action']) && in_array($settings['missing_product_action'], array('none', 'disable', 'zero', 'delete'), true)) ? $settings['missing_product_action'] : 'none';
        $settings['category_mode'] = (isset($settings['category_mode']) && in_array($settings['category_mode'], array('file', 'force', 'both'), true)) ? $settings['category_mode'] : 'file';
        $settings['auth_type']     = (isset($settings['auth_type']) && in_array($settings['auth_type'], array('none', 'basic'), true)) ? $settings['auth_type'] : 'none';
        $settings['supplier_markup_type'] = (isset($settings['supplier_markup_type']) && in_array($settings['supplier_markup_type'], array('none', 'percent', 'fixed', 'percent_fixed'), true)) ? $settings['supplier_markup_type'] : 'none';
        $settings['supplier_price_stock_source_type'] = (isset($settings['supplier_price_stock_source_type']) && in_array($settings['supplier_price_stock_source_type'], array('', 'file', 'url'), true)) ? $settings['supplier_price_stock_source_type'] : '';
        $settings['supplier_name'] = isset($settings['supplier_name']) ? trim((string)$settings['supplier_name']) : '';
        $settings['supplier_code'] = isset($settings['supplier_code']) ? strtolower(trim((string)$settings['supplier_code'])) : '';
        $settings['supplier_code'] = trim(preg_replace('/[^a-z0-9._-]+/', '_', $settings['supplier_code']), '._-');
        if (!empty($settings['supplier_mode']) && $settings['supplier_name'] === '') {
            $errors[] = 'Supplier name is required when supplier mode is enabled';
        }
        if (!empty($settings['supplier_mode']) && $settings['supplier_code'] !== '' && !preg_match('/^[a-z0-9._-]{2,64}$/', $settings['supplier_code'])) {
            $errors[] = 'Supplier code must contain 2-64 lowercase Latin letters, numbers, dots, underscores or hyphens';
        }
        if ($settings['missing_product_action'] !== 'none') {
            if (empty($settings['supplier_mode'])) {
                $errors[] = 'Missing-product synchronization requires supplier mode';
            }
            if (empty($settings['sync_missing_confirm'])) {
                $errors[] = 'Confirm the supplier scope before enabling missing-product synchronization';
            }
        }
        if (!empty($settings['supplier_price_stock_source_path'])) {
            $price_source_type = $settings['supplier_price_stock_source_type'] ? $settings['supplier_price_stock_source_type'] : $source_type;
            $price_source_errors = $this->validateSourceReference($format, $price_source_type, $settings['supplier_price_stock_source_path']);
            if ($price_source_errors) {
                foreach ($price_source_errors as $price_source_error) {
                    $errors[] = 'Price/stock source: ' . $price_source_error;
                }
            }
        }

        return $errors;
    }

    private function ensureProductStores($product_id) {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_to_store'");
        if ($query->num_rows) {
            $stores = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store`");
            $store_ids = array(0);
            foreach ($stores->rows as $store) {
                $store_ids[] = (int)$store['store_id'];
            }
            $store_ids = array_unique($store_ids);
            foreach ($store_ids as $store_id) {
                $this->db->query("REPLACE INTO `" . DB_PREFIX . "product_to_store` SET product_id='" . (int)$product_id . "', store_id='" . (int)$store_id . "'");
            }
        }
    }

    private function ensureManufacturerStores($manufacturer_id) {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "manufacturer_to_store'");
        if ($query->num_rows) {
            $stores = $this->db->query("SELECT store_id FROM `" . DB_PREFIX . "store`");
            $store_ids = array(0);
            foreach ($stores->rows as $store) {
                $store_ids[] = (int)$store['store_id'];
            }
            $store_ids = array_unique($store_ids);
            foreach ($store_ids as $store_id) {
                $this->db->query("REPLACE INTO `" . DB_PREFIX . "manufacturer_to_store` SET manufacturer_id='" . (int)$manufacturer_id . "', store_id='" . (int)$store_id . "'");
            }
        }
    }

    private function rebuildCategoryPath($category_id, $parent_id) {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "category_path'");
        if (!$query->num_rows) {
            return;
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "category_path` WHERE category_id='" . (int)$category_id . "'");
        $level = 0;
        if ($parent_id) {
            $paths = $this->db->query("SELECT path_id, level FROM `" . DB_PREFIX . "category_path` WHERE category_id='" . (int)$parent_id . "' ORDER BY level ASC");
            foreach ($paths->rows as $path) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id='" . (int)$category_id . "', path_id='" . (int)$path['path_id'] . "', level='" . (int)$level . "'");
                $level++;
            }
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id='" . (int)$category_id . "', path_id='" . (int)$category_id . "', level='" . (int)$level . "'");
    }

    private function isMappedTarget($product_data, $target) {
        return !empty($product_data['_mapped_targets']) && in_array($target, $product_data['_mapped_targets']);
    }

    private function normalizeDateValue($value) {
        $value = trim((string)$value);
        if ($value === '') {
            return '';
        }
        if (preg_match('~^\d{4}-\d{2}-\d{2}$~', $value)) {
            return $value;
        }
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return '';
        }
        return date('Y-m-d', $timestamp);
    }

    public function getProfiles() {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "import_pro_profile` ORDER BY date_modified DESC, profile_id DESC");
        $profiles = array();
        foreach ($query->rows as $row) {
            $row['settings'] = $row['settings_json'] ? json_decode($row['settings_json'], true) : array();
            if (is_array($row['settings'])) {
                $row['settings']['auth_password_set'] = !empty($row['settings']['auth_password']) ? 1 : 0;
                $row['settings']['auth_password'] = '';
            }
            $row['field_map'] = $row['field_map_json'] ? json_decode($row['field_map_json'], true) : array();
            $profiles[] = $row;
        }
        return $profiles;
    }

    public function getSuppliersForExport() {
        $query = $this->db->query("SELECT s.supplier_id, s.name, s.status, s.profile_id, p.name AS profile_name, COUNT(sp.supplier_product_id) AS product_count FROM `" . DB_PREFIX . "import_pro_supplier` s LEFT JOIN `" . DB_PREFIX . "import_pro_profile` p ON (s.profile_id = p.profile_id) LEFT JOIN `" . DB_PREFIX . "import_pro_supplier_product` sp ON (s.supplier_id = sp.supplier_id) GROUP BY s.supplier_id ORDER BY s.name ASC, s.supplier_id ASC");
        return $query->rows;
    }

    public function getProfile($profile_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "import_pro_profile` WHERE profile_id = '" . (int)$profile_id . "'");
        if (!$query->num_rows) {
            throw new Exception('Profile not found');
        }
        $row = $query->row;
        $row['settings'] = $row['settings_json'] ? json_decode($row['settings_json'], true) : array();
        $row['field_map'] = $row['field_map_json'] ? json_decode($row['field_map_json'], true) : array();
        return $row;
    }


    public function detectSourceFields($data) {
        $storedAuthPassword = '';
        $detectProfileId = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
        if ($detectProfileId > 0 && (!isset($data['auth_password']) || trim((string)$data['auth_password']) === '')) {
            $stored = $this->db->query("SELECT settings_json FROM `" . DB_PREFIX . "import_pro_profile` WHERE profile_id='" . (int)$detectProfileId . "' LIMIT 1");
            if ($stored->num_rows) {
                $storedSettings = json_decode($stored->row['settings_json'], true);
                if (is_array($storedSettings) && isset($storedSettings['auth_password'])) {
                    $storedAuthPassword = (string)$storedSettings['auth_password'];
                }
            }
        }
        $settings = array(
            'delimiter' => isset($data['delimiter']) ? html_entity_decode((string)$data['delimiter'], ENT_QUOTES, 'UTF-8') : ',',
            'enclosure' => isset($data['enclosure']) ? html_entity_decode((string)$data['enclosure'], ENT_QUOTES, 'UTF-8') : '"',
            'escape' => isset($data['escape']) ? html_entity_decode((string)$data['escape'], ENT_QUOTES, 'UTF-8') : '\\',
            'start_row' => isset($data['start_row']) ? (int)$data['start_row'] : 1,
            'sheet_name' => isset($data['sheet_name']) ? $data['sheet_name'] : '',
            'root_node' => isset($data['root_node']) ? $data['root_node'] : '',
            'item_node' => isset($data['item_node']) ? $data['item_node'] : '',
            'html_table_index' => isset($data['html_table_index']) ? (int)$data['html_table_index'] : 0,
            'html_header_row' => isset($data['html_header_row']) ? (int)$data['html_header_row'] : 1,
            'auth_type' => isset($data['auth_type']) ? $data['auth_type'] : 'none',
            'auth_username' => isset($data['auth_username']) ? $data['auth_username'] : '',
            'auth_password' => isset($data['auth_password']) && trim((string)$data['auth_password']) !== '' ? $data['auth_password'] : $storedAuthPassword
        );

        $profile = array(
            'format' => isset($data['format']) ? strtolower($data['format']) : 'csv',
            'source_type' => isset($data['source_type']) ? $data['source_type'] : 'file',
            'source_path' => isset($data['source_path']) ? $data['source_path'] : '',
            'settings' => $settings
        );

        $source_errors = $this->validateSourceReference($profile['format'], $profile['source_type'], $profile['source_path']);
        if ($source_errors) {
            throw new Exception(implode('; ', $source_errors));
        }

        $parsed = $this->parseSource($profile, 8);
        return array(
            'headers' => !empty($parsed['headers']) ? array_values($parsed['headers']) : array(),
            'rows' => !empty($parsed['rows']) ? $parsed['rows'] : array(),
            'sample_row' => !empty($parsed['rows'][0]) ? $parsed['rows'][0] : array()
        );
    }

    public function saveProfile($data) {
        $incoming_profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
        $stored_auth_password = '';
        if ($incoming_profile_id > 0) {
            $stored = $this->db->query("SELECT settings_json FROM `" . DB_PREFIX . "import_pro_profile` WHERE profile_id='" . (int)$incoming_profile_id . "' LIMIT 1");
            if ($stored->num_rows) {
                $stored_settings = json_decode($stored->row['settings_json'], true);
                if (is_array($stored_settings) && isset($stored_settings['auth_password'])) {
                    $stored_auth_password = (string)$stored_settings['auth_password'];
                }
            }
        }
        $settings = array(
            'delimiter' => isset($data['delimiter']) ? html_entity_decode((string)$data['delimiter'], ENT_QUOTES, 'UTF-8') : ',',
            'enclosure' => isset($data['enclosure']) ? html_entity_decode((string)$data['enclosure'], ENT_QUOTES, 'UTF-8') : '"',
            'escape' => isset($data['escape']) ? html_entity_decode((string)$data['escape'], ENT_QUOTES, 'UTF-8') : '\\',
            'start_row' => isset($data['start_row']) ? (int)$data['start_row'] : 1,
            'sheet_name' => isset($data['sheet_name']) ? $data['sheet_name'] : '',
            'root_node' => isset($data['root_node']) ? $data['root_node'] : '',
            'item_node' => isset($data['item_node']) ? $data['item_node'] : '',
            'default_category' => isset($data['default_category']) ? (int)$data['default_category'] : 0,
            'create_manufacturer' => !empty($data['create_manufacturer']) ? 1 : 0,
            'create_categories' => !empty($data['create_categories']) ? 1 : 0,
            'update_existing' => !empty($data['update_existing']) ? 1 : 0,
            'create_new' => !empty($data['create_new']) ? 1 : 0,
            'update_price' => !empty($data['update_price']) ? 1 : 0,
            'update_quantity' => !empty($data['update_quantity']) ? 1 : 0,
            'update_images' => !empty($data['update_images']) ? 1 : 0,
            'update_descriptions' => !empty($data['update_descriptions']) ? 1 : 0,
            'update_attributes' => !empty($data['update_attributes']) ? 1 : 0,
            'update_seo' => !empty($data['update_seo']) ? 1 : 0,
            'update_specials' => !empty($data['update_specials']) ? 1 : 0,
            'update_options' => !empty($data['update_options']) ? 1 : 0,
            'download_images' => !empty($data['download_images']) ? 1 : 0,
            'image_limit' => isset($data['image_limit']) ? max(1, min(100, (int)$data['image_limit'])) : 10,
            'auto_split_images' => !empty($data['auto_split_images']) ? 1 : 0,
            'image_update_mode' => isset($data['image_update_mode']) ? (string)$data['image_update_mode'] : 'replace',
            'image_subdir' => isset($data['image_subdir']) ? $this->normalizeModuleSubdir($data['image_subdir']) : 'catalog/import_pro/',
            'url_timeout' => isset($data['url_timeout']) ? (int)$data['url_timeout'] : 60,
            'auth_type' => isset($data['auth_type']) ? $data['auth_type'] : 'none',
            'auth_username' => isset($data['auth_username']) ? $data['auth_username'] : '',
            'auth_password' => (isset($data['auth_password']) && (string)$data['auth_password'] !== '') ? (string)$data['auth_password'] : $stored_auth_password,
            'preview_limit' => isset($data['preview_limit']) ? max(1, (int)$data['preview_limit']) : 20,
            'run_limit' => isset($data['run_limit']) ? max(0, (int)$data['run_limit']) : 0,
            'batch_size' => isset($data['batch_size']) ? max(1, (int)$data['batch_size']) : 100,
            'run_mode' => isset($data['run_mode']) ? $data['run_mode'] : 'full',
            'in_stock_quantity' => isset($data['in_stock_quantity']) ? max(0, min(2147483647, (int)$data['in_stock_quantity'])) : 100,
            'html_table_index' => isset($data['html_table_index']) ? (int)$data['html_table_index'] : 0,
            'html_header_row' => isset($data['html_header_row']) ? (int)$data['html_header_row'] : 1,
            'filter_in_stock_only' => !empty($data['filter_in_stock_only']) ? 1 : 0,
            'filter_status_value' => isset($data['filter_status_value']) ? (string)$data['filter_status_value'] : '',
            'filter_manufacturer_contains' => isset($data['filter_manufacturer_contains']) ? trim((string)$data['filter_manufacturer_contains']) : '',
            'filter_category_contains' => isset($data['filter_category_contains']) ? trim((string)$data['filter_category_contains']) : '',
            'clean_text_fields' => !empty($data['clean_text_fields']) ? 1 : 0,
            'clean_description_html' => !empty($data['clean_description_html']) ? 1 : 0,
            'decode_html_entities' => !empty($data['decode_html_entities']) ? 1 : 0,
            'strip_invisible_chars' => !empty($data['strip_invisible_chars']) ? 1 : 0,
            'strip_msword_markup' => !empty($data['strip_msword_markup']) ? 1 : 0,
            'auto_generate_seo_keyword' => !empty($data['auto_generate_seo_keyword']) ? 1 : 0,
            'category_path_mode' => !empty($data['category_path_mode']) ? 1 : 0,
            'fill_missing_languages' => !empty($data['fill_missing_languages']) ? 1 : 0,
            'category_mode' => isset($data['category_mode']) ? $data['category_mode'] : 'file',
            'force_category_id' => isset($data['force_category_id']) ? (int)$data['force_category_id'] : 0,
            'import_category_mode' => isset($data['import_category_mode']) ? (string)$data['import_category_mode'] : 'none',
            'import_category_name' => isset($data['import_category_name']) ? trim((string)$data['import_category_name']) : '',
            'import_product_status_mode' => isset($data['import_product_status_mode']) ? (string)$data['import_product_status_mode'] : 'file',
            'missing_product_action' => isset($data['missing_product_action']) ? (string)$data['missing_product_action'] : 'none',
            'sync_missing_confirm' => !empty($data['sync_missing_confirm']) ? 1 : 0,
            'supplier_code' => isset($data['supplier_code']) ? strtolower(trim((string)$data['supplier_code'])) : '',
            'ignore_empty_fields' => !isset($data['ignore_empty_fields']) || !empty($data['ignore_empty_fields']) ? 1 : 0,
            'field_rules' => $this->sanitizeSafeFieldRules(isset($data['field_rules']) ? $data['field_rules'] : array()),
            'supplier_mode' => !empty($data['supplier_mode']) ? 1 : 0,
            'supplier_name' => isset($data['supplier_name']) ? trim((string)$data['supplier_name']) : '',
            'supplier_markup_type' => isset($data['supplier_markup_type']) ? (string)$data['supplier_markup_type'] : 'none',
            'supplier_markup_value' => isset($data['supplier_markup_value']) ? (float)str_replace(',', '.', (string)$data['supplier_markup_value']) : 0,
            'supplier_fixed_markup' => isset($data['supplier_fixed_markup']) ? (float)str_replace(',', '.', (string)$data['supplier_fixed_markup']) : 0,
            'supplier_price_rounding' => isset($data['supplier_price_rounding']) ? (float)str_replace(',', '.', (string)$data['supplier_price_rounding']) : 0,
            'supplier_price_stock_source_type' => isset($data['supplier_price_stock_source_type']) ? (string)$data['supplier_price_stock_source_type'] : '',
            'supplier_price_stock_source_path' => isset($data['supplier_price_stock_source_path']) ? trim((string)$data['supplier_price_stock_source_path']) : '',
            'supplier_auto_xml_params' => !empty($data['supplier_auto_xml_params']) ? 1 : 0,
            'supplier_copy_attributes_to_languages' => !empty($data['supplier_copy_attributes_to_languages']) ? 1 : 0,
        );

        $field_map = array();
        if (!empty($data['field_map_json'])) {
            $decoded = json_decode(html_entity_decode($data['field_map_json'], ENT_QUOTES, 'UTF-8'), true);
            if (is_array($decoded)) {
                $field_map = $decoded;
            }
        }

        $validation_errors = $this->validateProfileData($data, $settings, $field_map);
        if ($validation_errors) {
            throw new Exception(implode('; ', $validation_errors));
        }

    $profile_id = isset($data['profile_id']) ? (int)$data['profile_id'] : 0;
    $profile_name = isset($data['name']) && trim((string)$data['name']) !== '' ? trim((string)$data['name']) : 'New profile';
    $format = isset($data['format']) && $data['format'] ? strtolower((string)$data['format']) : 'csv';
    if (!in_array($format, array('csv', 'xlsx', 'xml', 'yml', 'yaml', 'json', 'html'), true)) { $format = 'csv'; }
    $source_type = isset($data['source_type']) && $data['source_type'] ? (string)$data['source_type'] : 'file';
    if (!in_array($source_type, array('file', 'url'), true)) { $source_type = 'file'; }
    $source_path = isset($data['source_path']) ? (string)$data['source_path'] : '';
    $match_field = isset($data['match_field']) && $data['match_field'] ? (string)$data['match_field'] : 'model';
    if (!in_array($match_field, array('product_id', 'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'name'), true)) { $match_field = 'model'; }
    $key_field = isset($data['key_field']) && $data['key_field'] ? (string)$data['key_field'] : $match_field;
    if (!in_array($key_field, array('product_id', 'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'name'), true)) { $key_field = $match_field; }
    $default_language_id = isset($data['default_language_id']) ? (int)$data['default_language_id'] : (int)$this->config->get('config_language_id');

    $sql = array(
        "name = '" . $this->db->escape($profile_name) . "'",
        "format = '" . $this->db->escape($format) . "'",
        "source_type = '" . $this->db->escape($source_type) . "'",
        "source_path = '" . $this->db->escape($source_path) . "'",
        "match_field = '" . $this->db->escape($match_field) . "'",
        "key_field = '" . $this->db->escape($key_field) . "'",
        "default_language_id = '" . (int)$default_language_id . "'",
        "settings_json = '" . $this->db->escape(json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) . "'",
        "field_map_json = '" . $this->db->escape(json_encode($field_map, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) . "'",
        "date_modified = NOW()"
    );

    if ($profile_id) {
        $this->db->query("UPDATE `" . DB_PREFIX . "import_pro_profile` SET " . implode(', ', $sql) . " WHERE profile_id = '" . $profile_id . "'");
        if (!empty($settings['supplier_mode'])) {
            $this->upsertSupplierFromSettings($profile_id, $settings);
        }
        return $profile_id;
    }

    $this->db->query("INSERT INTO `" . DB_PREFIX . "import_pro_profile` SET " . implode(', ', $sql) . ", date_added = NOW()");
    $new_profile_id = (int)$this->db->getLastId();
    if (!empty($settings['supplier_mode'])) {
        $this->upsertSupplierFromSettings($new_profile_id, $settings);
    }
    return $new_profile_id;
}

    public function deleteProfile($profile_id) {
        $profile_id = (int)$profile_id;
        // Cascade: remove related runs so the runs table does not accumulate orphans
        // after a profile is deleted. Runs are append-only history, safe to drop here.
        $supplier_rows = $this->db->query("SELECT supplier_id FROM `" . DB_PREFIX . "import_pro_supplier` WHERE profile_id = '" . $profile_id . "'");
        foreach ($supplier_rows->rows as $supplier_row) {
            $supplier_id = (int)$supplier_row['supplier_id'];
            $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_supplier_product` WHERE supplier_id = '" . $supplier_id . "'");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_supplier_category` WHERE supplier_id = '" . $supplier_id . "'");
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_supplier` WHERE profile_id = '" . $profile_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_run` WHERE profile_id = '" . $profile_id . "'");
        $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_profile` WHERE profile_id = '" . $profile_id . "'");
    }

    public function clearRunLog($keep_days = 7) {
        // Trim run history. Keeps anything newer than $keep_days days, truncates older.
        // Returns number of deleted rows for the admin UI.
        $keep_days = max(0, (int)$keep_days);
        if ($keep_days > 0) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_run` WHERE date_added < DATE_SUB(NOW(), INTERVAL " . $keep_days . " DAY)");
        } else {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_run`");
        }
        $affected = 0;
        try {
            $row = $this->db->query("SELECT ROW_COUNT() AS c");
            if (!empty($row->row['c'])) {
                $affected = (int)$row->row['c'];
            }
        } catch (Throwable $e) {
            // Some MySQL configurations may not return ROW_COUNT() reliably here. Non-fatal.
        }
        return $affected;
    }

    public function getRecentRuns($limit = 20) {
        $limit = max(1, (int)$limit);
        $query = $this->db->query("SELECT r.*, p.name AS profile_name FROM `" . DB_PREFIX . "import_pro_run` r LEFT JOIN `" . DB_PREFIX . "import_pro_profile` p ON (p.profile_id = r.profile_id) ORDER BY r.run_id DESC LIMIT " . (int)$limit);
        $rows = array();
        foreach ($query->rows as $row) {
            $row['log'] = $row['log_json'] ? json_decode($row['log_json'], true) : array();
            $row['result_summary'] = 'Created: ' . (int)$row['created_count'] . ', updated: ' . (int)$row['updated_count'] . ', skipped: ' . (int)$row['skipped_count'] . ', errors: ' . (int)$row['error_count'];
            $rows[] = $row;
        }
        return $rows;
    }

    public function previewProfile($profile_id, $limit = 0) {
        $profile = $this->getProfile($profile_id);
        if (!$limit) {
            $limit = !empty($profile['settings']['preview_limit']) ? (int)$profile['settings']['preview_limit'] : 20;
        }
        $parsed = $this->parseSource($profile, $limit);

        return array(
            'headers' => $parsed['headers'],
            'rows' => $parsed['rows'],
            'analysis' => array(
                'total_preview_rows' => count($parsed['rows']),
                'mapped_fields' => array_keys($profile['field_map']),
                'match_field' => $profile['match_field'],
                'key_field' => $profile['key_field'],
                'preview_limit' => $limit
            )
        );
    }

    public function runImport($profile_id, $dry_run = false, $limit = 0, $offset = 0, $run_mode = '', $session_started_at = '', $finalize_missing = false, $source_total_hint = 0) {
        if (!$this->config->get('module_import_pro_status')) { throw new RuntimeException('Module is disabled'); }
        throw new RuntimeException('Legacy direct import is disabled. Use the protected dry-run queue workflow.');
        $profile_id = (int)$profile_id;
        $profile = $this->getProfile($profile_id);

        // Limit is controlled explicitly by admin AJAX or cron URL. Do not silently take
        // the saved profile limit here, otherwise cron may import only the first batch.
        $limit = max(0, (int)$limit);

        if ($run_mode === '') {
            $run_mode = !empty($profile['settings']['run_mode']) ? $profile['settings']['run_mode'] : 'full';
        }
        if ($run_mode === 'price_qty' && !empty($profile['settings']['supplier_price_stock_source_path'])) {
            $profile['source_path'] = (string)$profile['settings']['supplier_price_stock_source_path'];
            if (!empty($profile['settings']['supplier_price_stock_source_type'])) {
                $profile['source_type'] = (string)$profile['settings']['supplier_price_stock_source_type'];
            }
        }
        $offset = max(0, (int)$offset);
        $limit = max(0, (int)$limit);
        $dry_run = !empty($dry_run);
        $finalize_missing = !empty($finalize_missing);
        $source_total_hint = max(0, (int)$source_total_hint);
        $session_started_at = trim((string)$session_started_at);
        if ($session_started_at !== '' && preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$~', $session_started_at)) {
            $run_started_at = $session_started_at;
        } else {
            $run_started_at = date('Y-m-d H:i:s', time() - 1);
        }
        $supplier_id = 0;
        if (!empty($profile['settings']['supplier_mode']) && !empty($profile['settings']['supplier_name'])) {
            $supplier_id = $dry_run ? (int)$this->getSupplierIdFromSettings($profile_id, $profile['settings']) : (int)$this->upsertSupplierFromSettings($profile_id, $profile['settings']);
        }
        $lock_name = 'import_pro_profile_' . $profile_id;

        if (!$this->acquireImportLock($lock_name)) {
            throw new Exception('Another Import Pro run is already active for this profile. Wait until the current run finishes or check stuck cron jobs.');
        }

        try {
            $source_path = $this->prepareSource($profile);
            if (!is_file($source_path)) {
                throw new Exception('Source file not found: ' . $source_path);
            }

            if ($profile['format'] === 'csv') {
                $parsed = $this->parseSourcePath($profile, $source_path, $limit > 0 ? ($limit + 1) : 0, $offset);
                $csv_has_more = !empty($parsed['has_more']);
                $parsed_total = $source_total_hint > 0 ? $source_total_hint : $this->countCsvItems($source_path, $profile['settings']);
            } else {
                $parsed = $this->parseSourcePath($profile, $source_path, 0, 0);
                $csv_has_more = false;
                $parsed_total = count($parsed['rows']);
                if ($offset || $limit) {
                    $parsed['rows'] = array_slice($parsed['rows'], $offset, $limit ? $limit : null);
                }
            }

            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;
            $not_found = 0;
            $changed = 0;
            $unchanged = 0;
            $price_changed = 0;
            $quantity_changed = 0;
            $status_changed = 0;
            $supplier_price_changed = 0;
            $special_changed = 0;
            $cron_create_blocked = 0;
            $change_rows = array();
            $new_rows = array();
            $not_found_rows = array();
            $seen_supplier_external_ids = array();
            $log = array();
            $max_log_rows = 500;
            $max_preview_rows = 150;
            $append_log = function($entry) use (&$log, $max_log_rows) {
                if (count($log) < $max_log_rows) {
                    $log[] = $entry;
                }
            };

            $this->load->model('localisation/language');
            $languages = $this->model_localisation_language->getLanguages();
            $language_ids = array();

            foreach ($languages as $language) {
                $language_ids[$language['code']] = (int)$language['language_id'];
            }

            foreach ($parsed['rows'] as $index => $row) {
                $transaction_started = false;
                $row_number = $offset + $index + 1;

                try {
                    $product_data = $this->mapRowToProduct($row, $profile, $language_ids, $run_mode !== 'price_qty');

                    if (!$this->passesImportFilters($product_data, $profile['settings'])) {
                        $skipped++;
                        $append_log(array('row' => $row_number, 'status' => 'skipped', 'message' => $this->lang('log_filtered_by_rules', 'Filtered by import rules')));
                        continue;
                    }

                    $identifier = isset($product_data[$profile['match_field']]) ? trim((string)$product_data[$profile['match_field']]) : '';
                    if ($profile['match_field'] === 'name') {
                        $match_language_id = (int)$profile['default_language_id'] ?: (int)$this->config->get('config_language_id');
                        $identifier = isset($product_data['descriptions'][$match_language_id]['name']) ? trim((string)$product_data['descriptions'][$match_language_id]['name']) : '';
                    }
                    if ($identifier === '' && $supplier_id && !empty($product_data['external_product_id'])) {
                        $identifier = trim((string)$product_data['external_product_id']);
                    }
                    if ($identifier === '') {
                        $skipped++;
                        $append_log(array('row' => $row_number, 'status' => 'skipped', 'message' => $this->lang('log_empty_match_field', 'Empty match field and empty external_product_id')));
                        continue;
                    }

                    if ($supplier_id && !empty($product_data['external_product_id'])) {
                        $seen_supplier_external_ids[trim((string)$product_data['external_product_id'])] = true;
                    }

                    $existing = false;
                    if ($supplier_id && !empty($product_data['external_product_id'])) {
                        $existing = $this->findExistingSupplierProduct($supplier_id, $product_data['external_product_id']);
                    }
                    if (!$existing) {
                        $existing = $this->findExistingProduct($profile['match_field'], $identifier, (int)$profile['default_language_id']);
                    }

                    if ($existing) {
                        if (empty($profile['settings']['update_existing'])) {
                            $skipped++;
                            $append_log(array('row' => $row_number, 'status' => 'skipped', 'message' => $this->lang('log_existing_product_skipped', 'Existing product skipped'), 'identifier' => $identifier));
                            continue;
                        }

                        $change_detail = $this->buildImportChangeDetails((int)$existing['product_id'], $product_data, $profile, $run_mode, $supplier_id);
                        if (!empty($change_detail['changed'])) {
                            $changed++;
                            if (!empty($change_detail['has_price_change'])) $price_changed++;
                            if (!empty($change_detail['has_quantity_change'])) $quantity_changed++;
                            if (!empty($change_detail['has_status_change'])) $status_changed++;
                            if (!empty($change_detail['has_supplier_price_change'])) $supplier_price_changed++;
                            if (!empty($change_detail['has_special_change'])) $special_changed++;
                            if (count($change_rows) < $max_preview_rows) {
                                $change_rows[] = array_merge(array('row' => $row_number, 'identifier' => $identifier), $change_detail);
                            }
                        } else {
                            $unchanged++;
                        }

                        if (!$dry_run) {
                            $this->db->query("START TRANSACTION");
                            $transaction_started = true;
                            $this->updateProduct((int)$existing['product_id'], $product_data, $profile, $language_ids, $run_mode);
                            if ($supplier_id) {
                                $this->saveSupplierProductLink($supplier_id, (int)$existing['product_id'], $product_data);
                                if ($run_mode !== 'price_qty') {
                                    $this->saveSupplierCategoryLinks($supplier_id, (int)$existing['product_id'], $product_data, $profile['settings']);
                                }
                            }
                            $this->db->query("COMMIT");
                            $transaction_started = false;
                        }

                        $updated++;
                        $append_log(array('row' => $row_number, 'status' => !empty($change_detail['changed']) ? 'updated' : 'unchanged', 'product_id' => (int)$existing['product_id'], 'identifier' => $identifier, 'changes' => !empty($change_detail['changes']) ? $change_detail['changes'] : array()));
                    } else {
                        if ($run_mode === 'price_qty') {
                            $skipped++;
                            $not_found++;
                            $cron_create_blocked++;
                            if (count($not_found_rows) < $max_preview_rows) {
                                $not_found_rows[] = array('row' => $row_number, 'identifier' => $identifier, 'reason' => 'price_qty_existing_only');
                            }
                            $append_log(array('row' => $row_number, 'status' => 'not_found', 'message' => $this->lang('log_price_qty_create_blocked', 'Price/quantity mode never creates new products. New supplier row was skipped.'), 'identifier' => $identifier));
                            continue;
                        }

                        if (empty($profile['settings']['create_new'])) {
                            $skipped++;
                            $not_found++;
                            if (count($not_found_rows) < $max_preview_rows) {
                                $not_found_rows[] = array('row' => $row_number, 'identifier' => $identifier, 'reason' => 'create_new_disabled');
                            }
                            $append_log(array('row' => $row_number, 'status' => 'not_found', 'message' => $this->lang('log_new_creation_disabled', 'New product creation disabled'), 'identifier' => $identifier));
                            continue;
                        }

                        if (count($new_rows) < $max_preview_rows) {
                            $new_rows[] = array('row' => $row_number, 'identifier' => $identifier, 'name' => $this->getProductDataPreviewName($product_data), 'price' => isset($product_data['price']) ? (float)$product_data['price'] : 0, 'quantity' => isset($product_data['quantity']) ? (int)$product_data['quantity'] : 0);
                        }

                        $product_id = 0;

                        if (!$dry_run) {
                            $this->db->query("START TRANSACTION");
                            $transaction_started = true;
                            $product_id = $this->createProduct($product_data, $profile, $language_ids);
                            if ($supplier_id) {
                                $this->saveSupplierProductLink($supplier_id, (int)$product_id, $product_data);
                                $this->saveSupplierCategoryLinks($supplier_id, (int)$product_id, $product_data, $profile['settings']);
                            }
                            $this->db->query("COMMIT");
                            $transaction_started = false;
                        }

                        $created++;
                        $append_log(array('row' => $row_number, 'status' => 'created', 'product_id' => (int)$product_id, 'identifier' => $identifier));
                    }
                } catch (Throwable $e) {
                    if ($transaction_started) {
                        try {
                            $this->db->query("ROLLBACK");
                        } catch (Throwable $rollback_error) {
                            $this->log->write('Import Pro rollback error: ' . $rollback_error->getMessage());
                        }
                    }

                    $errors++;
                    $append_log(array('row' => $row_number, 'status' => 'error', 'message' => $e->getMessage()));
                }
            }

            $total_events = $created + $updated + $skipped + $errors;
            if ($total_events > $max_log_rows && count($log) >= $max_log_rows) {
                array_pop($log);
                $log[] = array('row' => 0, 'status' => 'info', 'message' => $this->lang('log_truncated', 'Run log was truncated to 500 entries to protect database size and admin performance.'));
            }

            $has_more = ($profile['format'] === 'csv') ? ($csv_has_more ? 1 : 0) : (($limit > 0 && ($offset + count($parsed['rows'])) < $parsed_total) ? 1 : 0);
            $next_offset = $has_more ? ($offset + count($parsed['rows'])) : 0;

            $missing_result = array('action' => 'none', 'affected' => 0, 'skipped' => 0);
            $should_finalize_missing = (!$dry_run && $supplier_id && $run_mode === 'full' && (($limit === 0 && $offset === 0) || (!$has_more && $finalize_missing)));
            if ($should_finalize_missing) {
                $missing_result = $this->processMissingSupplierProducts($supplier_id, $run_started_at, $profile['settings']);
                if (!empty($missing_result['affected'])) {
                    $append_log(array('row' => 0, 'status' => 'info', 'message' => $this->lang('log_missing_action', 'Missing products action') . ': ' . $missing_result['action'] . ', ' . $this->lang('log_affected', 'affected') . ': ' . (int)$missing_result['affected']));
                }
            }

            $missing_preview = array('count' => 0, 'rows' => array(), 'truncated' => 0);
            if ($supplier_id && $run_mode === 'full' && $limit === 0 && $offset === 0) {
                $missing_preview = $this->getMissingSupplierProductsBySeenExternalIds($supplier_id, array_keys($seen_supplier_external_ids), $max_preview_rows);
            }
            $missing_count = isset($missing_preview['count']) ? (int)$missing_preview['count'] : 0;

            $this->db->query("INSERT INTO `" . DB_PREFIX . "import_pro_run` SET profile_id = '" . (int)$profile_id . "', is_dry_run = '" . (int)$dry_run . "', run_mode = '" . $this->db->escape($run_mode) . "', created_count = '" . (int)$created . "', updated_count = '" . (int)$updated . "', skipped_count = '" . (int)$skipped . "', error_count = '" . (int)$errors . "', log_json = '" . $this->db->escape(json_encode($log, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) . "', date_added = NOW()");

            return array(
                'dry_run' => $dry_run,
                'run_mode' => $run_mode,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
                'not_found' => $not_found,
                'changed' => $changed,
                'unchanged' => $unchanged,
                'price_changed' => $price_changed,
                'quantity_changed' => $quantity_changed,
                'status_changed' => $status_changed,
                'supplier_price_changed' => $supplier_price_changed,
                'special_changed' => $special_changed,
                'cron_create_blocked' => $cron_create_blocked,
                'missing_count' => $missing_count,
                'processed' => count($parsed['rows']),
                'product_processed' => count($parsed['rows']),
                'source_total' => $parsed_total,
                'product_total' => $parsed_total,
                'success_total' => ($created + $updated),
                'applied_limit' => (int)$limit,
                'applied_offset' => (int)$offset,
                'has_more' => (int)$has_more,
                'next_offset' => (int)$next_offset,
                'missing_action' => $missing_result['action'],
                'missing_affected' => (int)$missing_result['affected'],
                'run_id' => $this->db->getLastId(),
                'session_started_at' => $run_started_at,
                'change_summary' => array(
                    'changed' => $changed,
                    'unchanged' => $unchanged,
                    'price_changed' => $price_changed,
                    'quantity_changed' => $quantity_changed,
                    'status_changed' => $status_changed,
                    'supplier_price_changed' => $supplier_price_changed,
                    'special_changed' => $special_changed,
                    'not_found' => $not_found,
                    'new_items' => $created,
                    'missing_from_supplier' => $missing_count,
                    'cron_create_blocked' => $cron_create_blocked
                ),
                'change_rows' => $change_rows,
                'new_rows' => $new_rows,
                'not_found_rows' => $not_found_rows,
                'missing_rows' => !empty($missing_preview['rows']) ? $missing_preview['rows'] : array(),
                'preview_rows_limit' => $max_preview_rows,
                'log' => array_slice($log, 0, 100)
            );
        } finally {
            $this->releaseImportLock($lock_name);
        }
    }

    private function buildImportChangeDetails($product_id, $product_data, $profile, $run_mode = 'full', $supplier_id = 0) {
        $product_id = (int)$product_id;
        $changes = array();
        $flags = array(
            'has_price_change' => false,
            'has_quantity_change' => false,
            'has_status_change' => false,
            'has_supplier_price_change' => false,
            'has_special_change' => false
        );

        $query = $this->db->query("SELECT product_id, model, sku, price, quantity, status FROM `" . DB_PREFIX . "product` WHERE product_id='" . (int)$product_id . "' LIMIT 1");
        if (!$query->num_rows) {
            return array_merge(array('product_id' => $product_id, 'model' => '', 'sku' => '', 'changed' => false, 'changes' => array()), $flags);
        }
        $current = $query->row;

        if (!empty($profile['settings']['update_price']) && $this->isMappedTarget($product_data, 'price')) {
            $old = (float)$current['price'];
            $new = isset($product_data['price']) ? (float)$product_data['price'] : 0.0;
            if (abs($old - $new) > 0.0001) {
                $changes[] = array('field' => 'price', 'old' => $this->formatPreviewNumber($old), 'new' => $this->formatPreviewNumber($new));
                $flags['has_price_change'] = true;
            }
        }

        if (!empty($profile['settings']['update_quantity']) && $this->isMappedTarget($product_data, 'quantity')) {
            $old = (int)$current['quantity'];
            $new = isset($product_data['quantity']) ? (int)$product_data['quantity'] : 0;
            if ($old !== $new) {
                $changes[] = array('field' => 'quantity', 'old' => $old, 'new' => $new);
                $flags['has_quantity_change'] = true;
            }
        }

        if ($run_mode !== 'price_qty' && $this->isMappedTarget($product_data, 'status')) {
            $old = (int)$current['status'];
            $new = isset($product_data['status']) ? (int)$product_data['status'] : 0;
            if ($old !== $new) {
                $changes[] = array('field' => 'status', 'old' => $old, 'new' => $new);
                $flags['has_status_change'] = true;
            }
        }

        if ($supplier_id && $this->isMappedTarget($product_data, 'supplier_price')) {
            $old_supplier = $this->getSupplierStoredPrice((int)$supplier_id, $product_id);
            $new_supplier = isset($product_data['supplier_price']) ? (float)$product_data['supplier_price'] : 0.0;
            if ($old_supplier !== null && abs((float)$old_supplier - $new_supplier) > 0.0001) {
                $changes[] = array('field' => 'supplier_price', 'old' => $this->formatPreviewNumber((float)$old_supplier), 'new' => $this->formatPreviewNumber($new_supplier));
                $flags['has_supplier_price_change'] = true;
            } elseif ($old_supplier === null && $new_supplier > 0) {
                $changes[] = array('field' => 'supplier_price', 'old' => '', 'new' => $this->formatPreviewNumber($new_supplier));
                $flags['has_supplier_price_change'] = true;
            }
        }

        if (!empty($profile['settings']['update_specials']) && $this->isMappedTarget($product_data, 'specials')) {
            $old_specials = $this->normalizeSpecialsForPreview($this->getProductSpecialsForPreview($product_id));
            $new_specials = $this->normalizeSpecialsForPreview(isset($product_data['specials']) ? $product_data['specials'] : array());
            if ($old_specials !== $new_specials) {
                $changes[] = array('field' => 'specials', 'old' => $this->specialsPreviewToString($old_specials), 'new' => $this->specialsPreviewToString($new_specials));
                $flags['has_special_change'] = true;
            }
        }

        return array_merge(array(
            'product_id' => $product_id,
            'model' => isset($current['model']) ? (string)$current['model'] : '',
            'sku' => isset($current['sku']) ? (string)$current['sku'] : '',
            'changed' => !empty($changes),
            'changes' => $changes
        ), $flags);
    }

    private function getSupplierStoredPrice($supplier_id, $product_id) {
        if (!$supplier_id || !$product_id || !$this->tableExists('import_pro_supplier_product')) {
            return null;
        }
        $query = $this->db->query("SELECT last_price FROM `" . DB_PREFIX . "import_pro_supplier_product` WHERE supplier_id='" . (int)$supplier_id . "' AND product_id='" . (int)$product_id . "' LIMIT 1");
        return $query->num_rows ? (float)$query->row['last_price'] : null;
    }

    private function getProductSpecialsForPreview($product_id) {
        if (!$this->tableExists('product_special')) {
            return array();
        }
        $query = $this->db->query("SELECT customer_group_id, price FROM `" . DB_PREFIX . "product_special` WHERE product_id='" . (int)$product_id . "' ORDER BY customer_group_id ASC, priority ASC, product_special_id ASC");
        return $query->rows;
    }

    private function normalizeSpecialsForPreview($specials) {
        $normalized = array();
        if (!is_array($specials)) {
            return $normalized;
        }
        foreach ($specials as $special) {
            if (!isset($special['price']) || $special['price'] === '') {
                continue;
            }
            $group_id = !empty($special['customer_group_id']) ? (int)$special['customer_group_id'] : 1;
            $normalized[] = $group_id . ':' . $this->formatPreviewNumber((float)$special['price']);
        }
        sort($normalized, SORT_STRING);
        return $normalized;
    }

    private function specialsPreviewToString($specials) {
        return $specials ? implode('; ', $specials) : '';
    }

    private function formatPreviewNumber($value) {
        $value = (float)$value;
        $formatted = number_format($value, 4, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    private function getProductDataPreviewName($product_data) {
        if (!empty($product_data['descriptions']) && is_array($product_data['descriptions'])) {
            foreach ($product_data['descriptions'] as $description) {
                if (!empty($description['name'])) {
                    return (string)$description['name'];
                }
            }
        }
        return '';
    }

    private function getMissingSupplierProductsBySeenExternalIds($supplier_id, $seen_external_ids, $limit = 150) {
        $supplier_id = (int)$supplier_id;
        $limit = max(1, (int)$limit);
        if (!$supplier_id || !$this->tableExists('import_pro_supplier_product')) {
            return array('count' => 0, 'rows' => array(), 'truncated' => 0);
        }

        $seen = array();
        if (is_array($seen_external_ids)) {
            foreach ($seen_external_ids as $external_id) {
                $external_id = trim((string)$external_id);
                if ($external_id !== '') {
                    $seen[$external_id] = true;
                }
            }
        }

        $rows = array();
        $count = 0;
        $query = $this->db->query("SELECT sp.product_id, sp.external_product_id, sp.supplier_sku, sp.last_price, sp.last_quantity, p.model, p.sku, p.status FROM `" . DB_PREFIX . "import_pro_supplier_product` sp LEFT JOIN `" . DB_PREFIX . "product` p ON (p.product_id = sp.product_id) WHERE sp.supplier_id='" . (int)$supplier_id . "' ORDER BY sp.last_seen ASC, sp.supplier_product_id ASC LIMIT 10000");
        foreach ($query->rows as $row) {
            $external_id = isset($row['external_product_id']) ? trim((string)$row['external_product_id']) : '';
            if ($external_id !== '' && isset($seen[$external_id])) {
                continue;
            }
            $count++;
            if (count($rows) < $limit) {
                $rows[] = array(
                    'product_id' => (int)$row['product_id'],
                    'external_product_id' => $external_id,
                    'model' => isset($row['model']) ? (string)$row['model'] : '',
                    'sku' => isset($row['sku']) ? (string)$row['sku'] : '',
                    'supplier_sku' => isset($row['supplier_sku']) ? (string)$row['supplier_sku'] : '',
                    'last_price' => isset($row['last_price']) ? $this->formatPreviewNumber((float)$row['last_price']) : '0',
                    'last_quantity' => isset($row['last_quantity']) ? (int)$row['last_quantity'] : 0,
                    'status' => isset($row['status']) ? (int)$row['status'] : 0
                );
            }
        }

        return array('count' => $count, 'rows' => $rows, 'truncated' => max(0, $count - count($rows)));
    }

    private function processMissingSupplierProducts($supplier_id, $run_started_at, $settings) {
        $supplier_id = (int)$supplier_id;
        $action = isset($settings['missing_product_action']) ? (string)$settings['missing_product_action'] : 'none';
        if (!$supplier_id || !in_array($action, array('disable', 'delete'), true)) {
            return array('action' => 'none', 'affected' => 0, 'skipped' => 0);
        }

        $run_started_at = $this->db->escape((string)$run_started_at);
        if ($action === 'disable') {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "import_pro_supplier_product` sp ON (sp.product_id = p.product_id) SET p.status='0', p.date_modified=NOW() WHERE sp.supplier_id='" . (int)$supplier_id . "' AND sp.last_seen < '" . $run_started_at . "'");
            return array('action' => 'disable', 'affected' => (int)$this->db->countAffected(), 'skipped' => 0);
        }

        $query = $this->db->query("SELECT sp.product_id FROM `" . DB_PREFIX . "import_pro_supplier_product` sp LEFT JOIN `" . DB_PREFIX . "import_pro_supplier_product` sp_other ON (sp_other.product_id = sp.product_id AND sp_other.supplier_id <> sp.supplier_id) WHERE sp.supplier_id='" . (int)$supplier_id . "' AND sp.last_seen < '" . $run_started_at . "' AND sp_other.supplier_product_id IS NULL LIMIT 5000");
        $deleted = 0;
        foreach ($query->rows as $row) {
            $product_id = (int)$row['product_id'];
            if ($product_id > 0) {
                $this->deleteImportedProduct($product_id);
                $deleted++;
            }
        }

        return array('action' => 'delete', 'affected' => $deleted, 'skipped' => 0);
    }

    private function deleteImportedProduct($product_id) {
        $product_id = (int)$product_id;
        if (!$product_id) {
            return;
        }

        $tables = array(
            'product_attribute', 'product_description', 'product_discount', 'product_filter', 'product_image',
            'product_option', 'product_option_value', 'product_recurring', 'product_reward', 'product_special',
            'product_to_category', 'product_to_download', 'product_to_layout', 'product_to_store', 'review', 'coupon_product'
        );
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . $table . "` WHERE product_id='" . $product_id . "'");
            }
        }
        if ($this->tableExists('product_related')) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_related` WHERE product_id='" . $product_id . "' OR related_id='" . $product_id . "'");
        }
        if ($this->tableExists('seo_url')) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query='product_id=" . $product_id . "'");
        }
        if ($this->tableExists('import_pro_supplier_product')) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "import_pro_supplier_product` WHERE product_id='" . $product_id . "'");
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "product` WHERE product_id='" . $product_id . "'");
    }

    private function acquireImportLock($lock_name) {
        $lock_name = preg_replace('~[^a-zA-Z0-9_\-:]+~', '_', (string)$lock_name);
        if ($lock_name === '') {
            $lock_name = 'import_pro_global';
        }

        try {
            $query = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($lock_name) . "', 0) AS locked");
            return !empty($query->row['locked']);
        } catch (Throwable $e) {
            $this->log->write('Import Pro lock check unavailable, continuing without DB lock: ' . $e->getMessage());
            return true;
        }
    }

    private function releaseImportLock($lock_name) {
        $lock_name = preg_replace('~[^a-zA-Z0-9_\-:]+~', '_', (string)$lock_name);
        if ($lock_name === '') {
            $lock_name = 'import_pro_global';
        }

        try {
            $this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($lock_name) . "')");
        } catch (Throwable $e) {
            $this->log->write('Import Pro lock release error: ' . $e->getMessage());
        }
    }

    private function parseSource($profile, $limit = 0, $offset = 0) {
        $path = $this->prepareSource($profile);
        if (!is_file($path)) {
            throw new Exception('Source file not found: ' . $path);
        }
        return $this->parseSourcePath($profile, $path, $limit, $offset);
    }

    private function parseSourcePath($profile, $path, $limit = 0, $offset = 0) {
        $format = strtolower($profile['format']);
        switch ($format) {
            case 'csv': return $this->parseCsv($path, $profile['settings'], $limit, $offset);
            case 'xlsx': return $this->parseXlsx($path, $profile['settings'], $limit);
            case 'xml':
            case 'yml':
            case 'yaml': return $this->parseXml($path, $profile['settings'], $limit);
            case 'json': return $this->parseJson($path, $limit);
            case 'html': return $this->parseHtml($path, $profile['settings'], $limit);
            default: throw new Exception('Unsupported format: ' . $format);
        }
    }

    private function prepareSource($profile) {
        if ($profile['source_type'] === 'url') {
            return $this->downloadRemoteSource($profile['source_path'], $profile['settings']);
        }
        return $profile['source_path'];
    }

    private function downloadRemoteSource($url, $settings) {
        if (!$this->isSafePublicUrl($url)) {
            throw new Exception('URL source must be a public HTTP/HTTPS address');
        }

        $body = $this->fetchRemoteBody($url, $settings, 104857600);

        $dir = DIR_DOWNLOAD . 'import_pro/url/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        $ext = $ext ? strtolower(preg_replace('~[^a-z0-9]+~i', '', $ext)) : '';
        if ($ext && !$this->isAllowedSourceExtension('source.' . $ext)) {
            throw new Exception('Unsupported remote source extension');
        }

        $ext = $ext ? '.' . $ext : '';
        $hash = md5($url . '|' . (!empty($settings['auth_type']) ? $settings['auth_type'] : 'none') . '|' . (isset($settings['auth_username']) ? $settings['auth_username'] : ''));
        $target = $dir . 'src_' . $hash . $ext;
        if (@file_put_contents($target, $body) === false) {
            throw new Exception('Cannot save downloaded source file');
        }

        return $target;
    }

    private function countCsvItems($path, $settings) {
        $delimiter = isset($settings['delimiter']) && $settings['delimiter'] !== '' ? $settings['delimiter'] : ',';
        $enclosure = isset($settings['enclosure']) && $settings['enclosure'] !== '' ? $settings['enclosure'] : '"';
        $escape = isset($settings['escape']) && $settings['escape'] !== '' ? $settings['escape'] : '\\';
        $start_row = isset($settings['start_row']) ? max(1, (int)$settings['start_row']) : 1;
        $handle = fopen($path, 'r');
        if (!$handle) {
            throw new Exception('Cannot open CSV file');
        }
        $line = 0;
        $headers_seen = false;
        $count = 0;
        while (($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
            $line++;
            if ($line < $start_row) {
                continue;
            }
            if (!$headers_seen) {
                $headers_seen = true;
                continue;
            }
            $count++;
        }
        fclose($handle);
        return $count;
    }

    private function parseCsv($path, $settings, $limit, $offset = 0) {
        $delimiter = isset($settings['delimiter']) && $settings['delimiter'] !== '' ? $settings['delimiter'] : ',';
        $enclosure = isset($settings['enclosure']) && $settings['enclosure'] !== '' ? $settings['enclosure'] : '"';
        $escape = isset($settings['escape']) && $settings['escape'] !== '' ? $settings['escape'] : '\\';
        $start_row = isset($settings['start_row']) ? max(1, (int)$settings['start_row']) : 1;
        $handle = fopen($path, 'r');
        if (!$handle) throw new Exception('Cannot open CSV file');
        $headers = array(); $rows = array(); $line = 0; $offset = max(0, (int)$offset); $data_index = 0; $has_more = false;
        while (($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
            $line++;
            if ($line < $start_row) continue;
            if (!$headers) {
                $headers = array_map(function($value) use ($settings) { return $this->normalizeInputString($value, !empty($settings['decode_html_entities']), true); }, $data);
                continue;
            }
            if ($offset && $data_index < $offset) { $data_index++; continue; }
            $row = array(); foreach ($headers as $i => $header) { $row[$header] = isset($data[$i]) ? trim((string)$data[$i]) : ''; }
            $rows[] = $row; $data_index++;
            if ($limit && count($rows) > $limit) { $has_more = true; array_pop($rows); break; }
        }
        fclose($handle);
        return array('headers' => $headers, 'rows' => $rows, 'has_more' => $has_more);
    }

    private function parseJson($path, $limit) {
        if (filesize($path) > 104857600) { throw new Exception('JSON source is too large. Maximum allowed size is 100 MB.'); }
        $content = file_get_contents($path); $decoded = json_decode($content, true);
        if (!is_array($decoded)) throw new Exception('Invalid JSON');
        $items = array();
        if (isset($decoded[0]) && is_array($decoded[0])) $items = $decoded; else foreach ($decoded as $value) { if (is_array($value) && isset($value[0]) && is_array($value[0])) { $items = $value; break; } }
        if (empty($items)) throw new Exception('JSON must contain an array of objects');
        $headers = array_keys($items[0]); $rows = array();
        foreach ($items as $item) {
            $row = array(); foreach ($headers as $header) { $row[$header] = isset($item[$header]) ? (is_scalar($item[$header]) ? (string)$item[$header] : json_encode($item[$header])) : ''; }
            $rows[] = $row; if ($limit && count($rows) >= $limit) break;
        }
        return array('headers' => $headers, 'rows' => $rows);
    }

    private function parseXml($path, $settings, $limit) {
        if (!function_exists('simplexml_load_file')) {
            throw new Exception('SimpleXML PHP extension is required for XML import');
        }

        libxml_use_internal_errors(true); $xml = simplexml_load_file($path, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) throw new Exception('Invalid XML');
        $item_node = !empty($settings['item_node']) ? $settings['item_node'] : 'item'; $root = $xml;
        if (!empty($settings['root_node'])) { foreach (explode('/', trim($settings['root_node'], '/')) as $part) { if (isset($root->{$part})) $root = $root->{$part}; } }
        $items = $root->xpath('.//' . $item_node); if (!$items) throw new Exception('XML item node not found: ' . $item_node);
        $headers = array(); $rows = array();
        foreach ($items as $item) {
            $row = array();
            foreach ($item->attributes() as $attr_name => $attr_value) {
                $key = '@' . $attr_name;
                $row[$key] = trim((string)$attr_value);
                if (!isset($row[$attr_name])) {
                    $row[$attr_name] = trim((string)$attr_value);
                }
                if (!in_array($key, $headers, true)) $headers[] = $key;
                if (!in_array($attr_name, $headers, true)) $headers[] = $attr_name;
            }
            foreach ($item->children() as $child) {
                $name = $child->getName();
                $value = trim((string)$child);
                if (isset($row[$name]) && $row[$name] !== '') {
                    $row[$name] .= '|' . $value;
                } else {
                    $row[$name] = $value;
                }
                if (!in_array($name, $headers, true)) $headers[] = $name;
                foreach ($child->attributes() as $attr_name => $attr_value) {
                    $attr_value = trim((string)$attr_value);
                    $attr_key = $name . '@' . $attr_name;
                    $row[$attr_key] = $attr_value;
                    if (!in_array($attr_key, $headers, true)) $headers[] = $attr_key;
                    if ($name === 'param' && $attr_name === 'name' && $attr_value !== '') {
                        $param_key = 'param:' . $attr_value;
                        if (isset($row[$param_key]) && $row[$param_key] !== '') {
                            $row[$param_key] .= '| ' . $value;
                        } else {
                            $row[$param_key] = $value;
                        }
                        if (!in_array($param_key, $headers, true)) $headers[] = $param_key;
                    }
                }
            }
            $rows[] = $row; if ($limit && count($rows) >= $limit) break;
        }
        return array('headers' => $headers, 'rows' => $rows);
    }


    private function parseHtml($path, $settings, $limit) {
        if (!class_exists('DOMDocument')) throw new Exception('DOMDocument is required for HTML import');
        $html = file_get_contents($path);
        if ($html === false || trim($html) === '') throw new Exception('Empty HTML source');
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        $xpath = new DOMXPath($dom);
        $tables = $xpath->query('//table');
        if (!$tables || !$tables->length) throw new Exception('No HTML table found');
        $table_index = isset($settings['html_table_index']) ? max(0, (int)$settings['html_table_index']) : 0;
        if ($table_index >= $tables->length) $table_index = 0;
        $table = $tables->item($table_index);
        $header_row = isset($settings['html_header_row']) ? max(1, (int)$settings['html_header_row']) : 1;
        $rows_nodes = $xpath->query('.//tr', $table);
        $headers = array();
        $rows = array();
        $row_num = 0;
        // Two-phase parse: phase 1 — skip every <tr> before $header_row and capture headers
        // at row $header_row exactly. Phase 2 — everything after the header row is data.
        // The previous implementation accidentally auto-generated col_N headers from the
        // first <tr> when $header_row > 1 and then pushed the real header row as data.
        foreach ($rows_nodes as $tr) {
            $row_num++;
            $cells = $xpath->query('./th|./td', $tr);
            if (!$cells || !$cells->length) {
                continue;
            }
            $vals = array();
            foreach ($cells as $cell) {
                $vals[] = trim(preg_replace('~\s+~u', ' ', html_entity_decode($cell->textContent, ENT_QUOTES, 'UTF-8')));
            }

            if (!$headers) {
                if ($row_num < $header_row) {
                    // Pre-header noise (e.g. caption, repeated title, banner row). Drop it.
                    continue;
                }
                // We are at row $header_row (or, if the table is shorter, the first non-empty row).
                $headers = $vals;
                // If the row really is empty for some reason, fall back to col_N headers
                // so we still produce a usable result instead of throwing.
                if (!array_filter($headers, 'strlen')) {
                    $headers = array();
                    for ($i = 0; $i < count($vals); $i++) {
                        $headers[] = 'col_' . ($i + 1);
                    }
                }
                continue;
            }

            $row = array();
            foreach ($headers as $i => $header) {
                $row[$header] = isset($vals[$i]) ? $vals[$i] : '';
            }
            $rows[] = $row;
            if ($limit && count($rows) >= $limit) {
                break;
            }
        }
        return array('headers' => $headers, 'rows' => $rows);
    }

    public function getRun($run_id) {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "import_pro_run` WHERE run_id='" . (int)$run_id . "' LIMIT 1");
        if (!$query->num_rows) return false;
        $row = $query->row;
        $row['log'] = $row['log_json'] ? json_decode($row['log_json'], true) : array();
        return $row;
    }

    private function parseXlsx($path, $settings, $limit) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive is required for XLSX');
        }
        if (!function_exists('simplexml_load_string')) {
            throw new Exception('SimpleXML PHP extension is required for XLSX');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception('Cannot open XLSX');
        }

        $shared = array();
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml) {
            $sx = simplexml_load_string($sharedXml);
            if ($sx && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = (string)$si->t;
                    } else {
                        $text = '';
                        if (isset($si->r)) {
                            foreach ($si->r as $r) {
                                $text .= (string)$r->t;
                            }
                        }
                        $shared[] = $text;
                    }
                }
            }
        }

        $sheetName = !empty($settings['sheet_name']) ? $settings['sheet_name'] : '';
        $sheetPath = 'xl/worksheets/sheet1.xml';

        if ($sheetName) {
            $workbookXml = $zip->getFromName('xl/workbook.xml');
            $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
            $sheetTarget = '';
            if ($workbookXml && $relsXml) {
                $workbook = simplexml_load_string($workbookXml);
                $rels = simplexml_load_string($relsXml);
                $map = array();
                foreach ($rels->Relationship as $rel) {
                    $attrs = $rel->attributes();
                    $map[(string)$attrs['Id']] = (string)$attrs['Target'];
                }
                foreach ($workbook->sheets->sheet as $sheet) {
                    $attrs = $sheet->attributes('r', true);
                    if ((string)$sheet['name'] === $sheetName && isset($map[(string)$attrs['id']])) {
                        $target = str_replace('\\', '/', $map[(string)$attrs['id']]);
                        $sheetTarget = substr($target, 0, 1) === '/' ? ltrim($target, '/') : 'xl/' . $target;
                        if (strpos($sheetTarget, '..') !== false || strpos($sheetTarget, ':') !== false) {
                            $zip->close();
                            throw new Exception('Invalid worksheet path in XLSX');
                        }
                        break;
                    }
                }
            }
            if (!$sheetTarget) {
                $zip->close();
                throw new Exception('Selected worksheet not found in XLSX');
            }
            $sheetPath = $sheetTarget;
        }

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if (!$sheetXml) {
            throw new Exception('Worksheet not found in XLSX');
        }

        $start_row = isset($settings['start_row']) ? max(1, (int)$settings['start_row']) : 1;
        $sheet = simplexml_load_string($sheetXml);
        $headers = array();
        $rows = array();

        foreach ($sheet->sheetData->row as $rowNode) {
            $row_num = isset($rowNode['r']) ? (int)$rowNode['r'] : 0;
            if ($row_num && $row_num < $start_row) {
                continue;
            }

            $cells = array();
            foreach ($rowNode->c as $c) {
                preg_match('/([A-Z]+)/', (string)$c['r'], $m);
                $col = isset($m[1]) ? $this->xlsxColToIndex($m[1]) : count($cells) + 1;
                if ((string)$c['t'] === 'inlineStr') {
                    $value = isset($c->is->t) ? (string)$c->is->t : '';
                    if (isset($c->is->r)) {
                        foreach ($c->is->r as $run) { $value .= (string)$run->t; }
                    }
                } else {
                    $value = ((string)$c['t'] === 's') ? (isset($shared[(int)$c->v]) ? $shared[(int)$c->v] : '') : (isset($c->v) ? (string)$c->v : '');
                }
                $cells[$col] = trim($value);
            }

            if (!$headers) {
                ksort($cells);
                // Retain physical column indexes: an empty B column must not shift C.
                $headers = array_filter($cells, static function ($value) { return $value !== ''; });
                if (count($headers) !== count(array_unique($headers))) {
                    throw new Exception('Duplicate column headers in XLSX');
                }
                continue;
            }

            $row = array();
            foreach ($headers as $i => $header) {
                $index = $i;
                $row[$header] = isset($cells[$index]) ? $cells[$index] : '';
            }

            $rows[] = $row;
            if ($limit && count($rows) >= $limit) {
                break;
            }
        }

        return array('headers' => array_values($headers), 'rows' => $rows);
    }

    private function normalizeInputString($value, $decode_entities = false, $strip_invisible = true) {
        if ($value === null) {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        $value = (string)$value;
        $value = str_replace(array("\r\n", "\r"), "\n", $value);
        $value = str_replace(array("\xEF\xBB\xBF", "&nbsp;", "&#160;", "&#xA0;"), array('', ' ', ' ', ' '), $value);

        if ($decode_entities) {
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded !== '') {
                $value = $decoded;
            }
        }

        if ($strip_invisible) {
            $value = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{2060}\x{00AD}]+/u', '', $value);
            $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value);
        }

        return trim($value);
    }

    private function sanitizePlainText($value, $settings = array(), $single_line = true) {
        $decode_entities = !empty($settings['decode_html_entities']);
        $strip_invisible = !empty($settings['strip_invisible_chars']) || !isset($settings['strip_invisible_chars']);
        $value = $this->normalizeInputString($value, $decode_entities, $strip_invisible);
        $value = preg_replace('~<\s*(script|style|iframe|object|embed|noscript)[^>]*>.*?<\s*/\s*\1\s*>~isu', ' ', $value);
        $value = preg_replace('~<!--.*?-->~s', ' ', $value);
        if (!empty($settings['strip_msword_markup'])) {
            $value = preg_replace('~<\/?o:[^>]*>~i', ' ', $value);
            $value = preg_replace('~<\/?w:[^>]*>~i', ' ', $value);
            $value = preg_replace('~<\/?m:[^>]*>~i', ' ', $value);
            $value = preg_replace('~\[if [^\]]+\].*?<!\[endif\]~isu', ' ', $value);
        }
        $value = strip_tags($value);
        $value = preg_replace('/\s+/u', $single_line ? ' ' : "\n", $value);
        return trim($value);
    }

    private function sanitizeHtmlFragment($value, $settings = array()) {
        $decode_entities = !empty($settings['decode_html_entities']);
        $strip_invisible = !empty($settings['strip_invisible_chars']) || !isset($settings['strip_invisible_chars']);
        $value = $this->normalizeInputString($value, $decode_entities, $strip_invisible);
        $value = preg_replace('~<\s*(script|style|iframe|object|embed|form|input|button|textarea|select|noscript|svg|math|template|audio|video|canvas)[^>]*>.*?<\s*/\s*\1\s*>~isu', '', $value);
        $value = preg_replace('~<\s*(script|style|iframe|object|embed|form|input|button|textarea|select|noscript|svg|math|template|link|meta|base|source|track)[^>]*?/?>~isu', '', $value);
        $value = preg_replace('~<!--.*?-->~s', '', $value);
        if (!empty($settings['strip_msword_markup'])) {
            $value = preg_replace('~<\/?(o|w|m):[^>]*>~i', '', $value);
            $value = preg_replace('~\[if [^\]]+\].*?<!\[endif\]~isu', '', $value);
            $value = preg_replace('~\sclass="?Mso[^" >]*"?~iu', '', $value);
            $value = preg_replace('~\sstyle="[^"]*mso-[^"]*"~iu', '', $value);
        }

        $allowedTags = array(
            'p','br','strong','b','em','i','u','ul','ol','li','table','thead','tbody','tr','td','th',
            'h1','h2','h3','h4','h5','h6','div','span','a','img','blockquote','hr'
        );
        $allowedAttributes = array(
            '*' => array('class','title'),
            'a' => array('href','target','rel','class','title'),
            'img' => array('src','alt','title','width','height','class'),
            'td' => array('colspan','rowspan','class','title'),
            'th' => array('colspan','rowspan','class','title'),
            'ol' => array('start','type','class','title'),
            'ul' => array('type','class','title'),
            'li' => array('value','class','title')
        );

        if (class_exists('DOMDocument')) {
            $previous = libxml_use_internal_errors(true);
            $document = new DOMDocument('1.0', 'UTF-8');
            $flags = 0;
            if (defined('LIBXML_HTML_NOIMPLIED')) { $flags |= LIBXML_HTML_NOIMPLIED; }
            if (defined('LIBXML_HTML_NODEFDTD')) { $flags |= LIBXML_HTML_NODEFDTD; }
            $wrapper = '<?xml encoding="UTF-8"><div id="import-pro-sanitize-root">' . $value . '</div>';
            $loaded = $document->loadHTML($wrapper, $flags);
            if ($loaded) {
                $xpath = new DOMXPath($document);
                $nodes = $xpath->query('//*');
                $nodeList = array();
                foreach ($nodes as $node) { $nodeList[] = $node; }
                for ($i = count($nodeList) - 1; $i >= 0; $i--) {
                    $node = $nodeList[$i];
                    if (!$node instanceof DOMElement || $node->getAttribute('id') === 'import-pro-sanitize-root') { continue; }
                    $tag = strtolower($node->tagName);
                    if (!in_array($tag, $allowedTags, true)) {
                        $parent = $node->parentNode;
                        if ($parent) {
                            while ($node->firstChild) { $parent->insertBefore($node->firstChild, $node); }
                            $parent->removeChild($node);
                        }
                        continue;
                    }

                    $allowed = array_merge(isset($allowedAttributes['*']) ? $allowedAttributes['*'] : array(), isset($allowedAttributes[$tag]) ? $allowedAttributes[$tag] : array());
                    $remove = array();
                    foreach ($node->attributes as $attribute) {
                        $name = strtolower($attribute->name);
                        $attributeValue = trim((string)$attribute->value);
                        if (!in_array($name, $allowed, true) || strpos($name, 'on') === 0 || in_array($name, array('srcdoc','formaction','action','xlink:href','xmlns'), true)) {
                            $remove[] = $attribute->name;
                            continue;
                        }
                        if (in_array($name, array('href','src'), true) && !$this->isSafeImportedHtmlUrl($attributeValue, $name === 'href')) {
                            $remove[] = $attribute->name;
                        }
                    }
                    foreach ($remove as $attributeName) { $node->removeAttribute($attributeName); }
                    if ($tag === 'a' && strtolower($node->getAttribute('target')) === '_blank') {
                        $node->setAttribute('rel', 'noopener noreferrer');
                    }
                }

                $root = $document->getElementById('import-pro-sanitize-root');
                if (!$root) {
                    $rootQuery = $xpath->query('//*[@id="import-pro-sanitize-root"]');
                    $root = $rootQuery->length ? $rootQuery->item(0) : null;
                }
                if ($root) {
                    $clean = '';
                    foreach ($root->childNodes as $child) { $clean .= $document->saveHTML($child); }
                    $value = $clean;
                }
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        } else {
            $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><table><thead><tbody><tr><td><th><h1><h2><h3><h4><h5><h6><div><span><a><img><blockquote><hr>';
            $value = strip_tags($value, $allowed);
            $value = preg_replace_callback('~<([a-z0-9]+)\\b([^>]*)>~iu', function($match) use ($allowedTags, $allowedAttributes) {
                $tag = strtolower((string)$match[1]);
                if (!in_array($tag, $allowedTags, true)) { return ''; }
                $allowedForTag = array_merge(isset($allowedAttributes['*']) ? $allowedAttributes['*'] : array(), isset($allowedAttributes[$tag]) ? $allowedAttributes[$tag] : array());
                $attributes = array();
                if (preg_match_all("~([a-zA-Z0-9:_-]+)\\s*=\\s*(\"[^\"]*\"|'[^']*'|[^\\s>]+)~u", (string)$match[2], $attributeMatches, PREG_SET_ORDER)) {
                    foreach ($attributeMatches as $attributeMatch) {
                        $name = strtolower((string)$attributeMatch[1]);
                        $raw = (string)$attributeMatch[2];
                        $attributeValue = trim($raw, " \\t\\n\\r\\0\\x0B\\\"'");
                        if (!in_array($name, $allowedForTag, true) || strpos($name, 'on') === 0 || in_array($name, array('style','srcdoc','formaction','action','xlink:href','xmlns'), true)) { continue; }
                        if (in_array($name, array('href','src'), true) && !$this->isSafeImportedHtmlUrl($attributeValue, $name === 'href')) { continue; }
                        $attributes[$name] = $attributeValue;
                    }
                }
                if ($tag === 'a' && isset($attributes['target']) && strtolower($attributes['target']) === '_blank') {
                    $attributes['rel'] = 'noopener noreferrer';
                }
                $output = '<' . $tag;
                foreach ($attributes as $name => $attributeValue) {
                    $output .= ' ' . $name . '="' . htmlspecialchars($attributeValue, ENT_QUOTES, 'UTF-8') . '"';
                }
                return $output . '>';
            }, $value);
        }

        $value = preg_replace('~>\s+<~u', '> <', $value);
        $value = preg_replace('/\n{3,}/u', "\n\n", $value);
        return trim($value);
    }

    private function isSafeImportedHtmlUrl($url, $allowContactSchemes) {
        $url = html_entity_decode(trim((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '' || $url[0] === '#' || $url[0] === '/' || strpos($url, './') === 0 || strpos($url, '../') === 0) {
            return true;
        }
        if (strpos($url, '//') === 0) {
            return true;
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme === null || $scheme === false || $scheme === '') {
            return true;
        }
        $scheme = strtolower((string)$scheme);
        if (in_array($scheme, array('http','https'), true)) {
            return true;
        }
        return $allowContactSchemes && in_array($scheme, array('mailto','tel'), true);
    }

    private function toLower($value) {
        $value = (string)$value;
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    private function stringContains($haystack, $needle) {
        $haystack = (string)$haystack;
        $needle = (string)$needle;
        if ($needle === '') {
            return true;
        }
        return function_exists('mb_strpos') ? (mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) : (strpos($haystack, $needle) !== false);
    }

    private function slugify($value) {
        $value = $this->normalizeInputString($value, true, true);
        $map = array(
            'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Ґ'=>'g','Д'=>'d','Е'=>'e','Є'=>'ye','Ж'=>'zh','З'=>'z','И'=>'i','І'=>'i','Ї'=>'yi','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r','С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'kh','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'shch','Ь'=>'','Ю'=>'yu','Я'=>'ya',
            'а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh','з'=>'z','и'=>'i','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'kh','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'shch','ь'=>'','ю'=>'yu','я'=>'ya',
            'Ъ'=>'','Ы'=>'y','Э'=>'e','ъ'=>'','ы'=>'y','э'=>'e'
        );
        $value = strtr($value, $map);
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false && $converted !== '') {
                $value = $converted;
            }
        }
        $value = $this->toLower($value);
        $value = preg_replace('~[^a-z0-9]+~', '-', $value);
        $value = trim($value, '-');
        return $value;
    }

    private function makeUniqueSeoKeyword($keyword, $product_id = 0) {
        $base = $this->slugify($keyword);
        if (!$this->tableExists('seo_url')) {
            return $base;
        }
        if ($base === '') {
            return '';
        }
        $candidate = $base;
        $i = 2;
        while (true) {
            $sql = "SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url` WHERE keyword='" . $this->db->escape($candidate) . "'";
            if ($product_id) {
                $sql .= " AND query <> 'product_id=" . (int)$product_id . "'";
            }
            $sql .= " LIMIT 1";
            $query = $this->db->query($sql);
            if (!$query->num_rows) {
                return $candidate;
            }
            $candidate = $base . '-' . $i;
            $i++;
            if ($i > 5000) {
                return $base . '-' . time();
            }
        }
    }

    private function sanitizeImportedProductData($product, $settings) {
        if (empty($settings['clean_text_fields']) && empty($settings['clean_description_html']) && empty($settings['auto_generate_seo_keyword'])) {
            return $product;
        }

        $plain_fields = array('model','sku','upc','ean','jan','isbn','mpn','location','manufacturer');
        foreach ($plain_fields as $field) {
            if (isset($product[$field])) {
                $product[$field] = $this->sanitizePlainText($product[$field], $settings, true);
            }
        }

        if (!empty($product['category_names'])) {
            $clean_categories = array();
            foreach ($product['category_names'] as $category_name) {
                $category_name = $this->sanitizePlainText($category_name, $settings, true);
                if ($category_name !== '') {
                    $clean_categories[] = $category_name;
                }
            }
            $product['category_names'] = array_values(array_unique($clean_categories));
        }

        if (!empty($product['attributes'])) {
            $clean_attributes = array();
            foreach ($product['attributes'] as $attribute) {
                $name = isset($attribute['name']) ? $this->sanitizePlainText($attribute['name'], $settings, true) : '';
                $text = isset($attribute['text']) ? $this->sanitizePlainText($attribute['text'], $settings, true) : '';
                if ($name !== '' && $text !== '') {
                    $clean_attributes[] = array('name' => $name, 'text' => $text);
                }
            }
            $product['attributes'] = $clean_attributes;
        }

        if (!empty($product['options'])) {
            $clean_options = array();
            foreach ($product['options'] as $option) {
                $name = isset($option['name']) ? $this->sanitizePlainText($option['name'], $settings, true) : '';
                $value = isset($option['value']) ? $this->sanitizePlainText($option['value'], $settings, true) : '';
                if ($name !== '' && $value !== '') {
                    $option['name'] = $name;
                    $option['value'] = $value;
                    $clean_options[] = $option;
                }
            }
            $product['options'] = $clean_options;
        }

        foreach ($product['descriptions'] as $language_id => $description) {
            if (isset($description['name'])) {
                $product['descriptions'][$language_id]['name'] = $this->sanitizePlainText($description['name'], $settings, true);
            }
            if (isset($description['meta_title'])) {
                $product['descriptions'][$language_id]['meta_title'] = $this->sanitizePlainText($description['meta_title'], $settings, true);
            }
            if (isset($description['meta_description'])) {
                $product['descriptions'][$language_id]['meta_description'] = $this->sanitizePlainText($description['meta_description'], $settings, true);
            }
            if (isset($description['meta_h1'])) {
                $product['descriptions'][$language_id]['meta_h1'] = $this->sanitizePlainText($description['meta_h1'], $settings, true);
            }
            if (isset($description['meta_keyword'])) {
                $product['descriptions'][$language_id]['meta_keyword'] = $this->sanitizePlainText($description['meta_keyword'], $settings, true);
            }
            if (isset($description['description'])) {
                if (!empty($settings['clean_description_html'])) {
                    $product['descriptions'][$language_id]['description'] = $this->sanitizeHtmlFragment($description['description'], $settings);
                } elseif (!empty($settings['clean_text_fields'])) {
                    $product['descriptions'][$language_id]['description'] = $this->sanitizePlainText($description['description'], $settings, false);
                }
            }
        }

        if (!empty($settings['auto_generate_seo_keyword'])) {
            $default_language_id = 0;
            if (!empty($product['descriptions'])) {
                $keys = array_keys($product['descriptions']);
                $default_language_id = (int)reset($keys);
            }
            $source_name = !empty($product['descriptions'][$default_language_id]['name']) ? $product['descriptions'][$default_language_id]['name'] : '';
            if (empty($product['seo_keyword']) && $source_name !== '') {
                $product['seo_keyword'] = $this->slugify($source_name);
            } else {
                $product['seo_keyword'] = $this->slugify($product['seo_keyword']);
            }
        } elseif (!empty($product['seo_keyword'])) {
            $product['seo_keyword'] = $this->sanitizePlainText($product['seo_keyword'], $settings, true);
        }

        return $product;
    }

    private function passesImportFilters($product_data, $settings) {
        if (!empty($settings['filter_in_stock_only']) && (int)$product_data['quantity'] <= 0) {
            return false;
        }
        if (isset($settings['filter_status_value']) && $settings['filter_status_value'] !== '') {
            $expected = $this->normalizeBooleanLike($settings['filter_status_value']);
            $actual = $this->normalizeBooleanLike(isset($product_data['status']) ? $product_data['status'] : 1);
            if ((string)$expected !== '' && $expected !== $actual) {
                return false;
            }
        }
        if (!empty($settings['filter_manufacturer_contains'])) {
            $needles = preg_split('~\s*[|,;]+\s*~u', $this->toLower($settings['filter_manufacturer_contains']));
            $hay = $this->toLower(isset($product_data['manufacturer']) ? $product_data['manufacturer'] : '');
            $matched = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && $this->stringContains($hay, $needle)) { $matched = true; break; }
            }
            if (!$matched) return false;
        }
        if (!empty($settings['filter_category_contains'])) {
            $needles = preg_split('~\s*[|,;]+\s*~u', $this->toLower($settings['filter_category_contains']));
            $hay = $this->toLower(implode('|', isset($product_data['category_names']) ? $product_data['category_names'] : array()));
            $matched = false;
            foreach ($needles as $needle) {
                if ($needle !== '' && $this->stringContains($hay, $needle)) { $matched = true; break; }
            }
            if (!$matched) return false;
        }
        return true;
    }

    private function normalizeBooleanLike($value) {
        if (is_bool($value)) return $value ? 1 : 0;
        $value = trim($this->toLower((string)$value));
        if ($value === '') return '';
        if (in_array($value, array('1','yes','true','on','enabled','enable','y','так','да','є','available','in stock','instock'))) return 1;
        if (in_array($value, array('0','no','false','off','disabled','disable','n','ні','нет','out of stock','outofstock'))) return 0;
        return is_numeric($value) ? ((float)$value > 0 ? 1 : 0) : 1;
    }

    private function mapRowToProduct($row, $profile, $language_ids, $require_name = true) {
        $map = $profile['field_map'];
        $default_language_id = (int)$profile['default_language_id'] ? (int)$profile['default_language_id'] : (int)$this->config->get('config_language_id');
        $product = array('model'=>'','sku'=>'','upc'=>'','ean'=>'','jan'=>'','isbn'=>'','mpn'=>'','location'=>'','price'=>0,'quantity'=>0,'minimum'=>1,'subtract'=>1,'stock_status_id'=>(int)$this->config->get('config_stock_status_id'),'shipping'=>1,'status'=>1,'tax_class_id'=>0,'weight'=>0,'length'=>0,'width'=>0,'height'=>0,'sort_order'=>0,'date_available'=>'','weight_class_id'=>(int)$this->config->get('config_weight_class_id'),'length_class_id'=>(int)$this->config->get('config_length_class_id'),'manufacturer'=>'','manufacturer_id'=>0,'default_category'=>!empty($profile['settings']['default_category']) ? (int)$profile['settings']['default_category'] : 0,'category_names'=>array(),'image'=>'','additional_images'=>array(),'attributes'=>array(),'options'=>array(),'specials'=>array(),'seo_keyword'=>'','product_url'=>'','external_product_id'=>'','external_category_id'=>'','external_parent_category_id'=>'','supplier_sku'=>'','supplier_price'=>0,'google_product_category'=>'','descriptions'=>array(),'_mapped_targets'=>array());
        foreach ($map as $target => $source) {
            if (!isset($row[$source])) continue; $value = trim((string)$row[$source]); if ($value === '') continue;
            if ($target === 'quantity' || $target === 'supplier_quantity') {
                $value = $this->normalizeSupplierQuantity($value, $profile['settings']);
                if ($value === '') { continue; }
            }
            $product['_mapped_targets'][] = $target;
            if (in_array($target, array('name','description','meta_title','meta_description','meta_h1','meta_keywords'), true)) {
                if (!isset($product['descriptions'][$default_language_id])) $product['descriptions'][$default_language_id] = array();
                $field = ($target === 'meta_keywords') ? 'meta_keyword' : $target;
                $product['descriptions'][$default_language_id][$field] = $value;
            } elseif (strpos($target, 'name:') === 0) {
                $code = substr($target, 5); if (isset($language_ids[$code])) { $lang_id = $language_ids[$code]; if (!isset($product['descriptions'][$lang_id])) $product['descriptions'][$lang_id]=array(); $product['descriptions'][$lang_id]['name']=$value; }
            } elseif (strpos($target, 'description:') === 0) {
                $code = substr($target, 12); if (isset($language_ids[$code])) { $lang_id = $language_ids[$code]; if (!isset($product['descriptions'][$lang_id])) $product['descriptions'][$lang_id]=array(); $product['descriptions'][$lang_id]['description']=$value; }
            } elseif (strpos($target, 'meta_title:') === 0) {
                $code = substr($target, 11); if (isset($language_ids[$code])) { $lang_id = $language_ids[$code]; if (!isset($product['descriptions'][$lang_id])) $product['descriptions'][$lang_id]=array(); $product['descriptions'][$lang_id]['meta_title']=$value; }
            } elseif (strpos($target, 'meta_description:') === 0) {
                $code = substr($target, 17); if (isset($language_ids[$code])) { $lang_id = $language_ids[$code]; if (!isset($product['descriptions'][$lang_id])) $product['descriptions'][$lang_id]=array(); $product['descriptions'][$lang_id]['meta_description']=$value; }
            } elseif (strpos($target, 'meta_h1:') === 0) {
                $code = substr($target, 8); if (isset($language_ids[$code])) { $lang_id = $language_ids[$code]; if (!isset($product['descriptions'][$lang_id])) $product['descriptions'][$lang_id]=array(); $product['descriptions'][$lang_id]['meta_h1']=$value; }
            } elseif (strpos($target, 'meta_keywords:') === 0) {
                $code = substr($target, 14); if (isset($language_ids[$code])) { $lang_id = $language_ids[$code]; if (!isset($product['descriptions'][$lang_id])) $product['descriptions'][$lang_id]=array(); $product['descriptions'][$lang_id]['meta_keyword']=$value; }
            } elseif ($target === 'product_url' || $target === 'page_url' || $target === 'url') {
                $product['product_url'] = $value;
                $product['seo_keyword'] = $this->extractSeoKeywordFromUrl($value);
            } elseif ($target === 'categories') {
                $product['category_names'] = array_filter(preg_split('/\s*[|;]\s*/', $value));
            } elseif ($target === 'main_category_id') {
                $product['default_category'] = (int)$value;
            } elseif ($target === 'attributes') {
                foreach (preg_split('/\s*[|;]\s*/', $value) as $pair) { $chunks = preg_split('/\s*[:=]\s*/', $pair, 2); if (count($chunks) === 2) $product['attributes'][] = array('name'=>trim($chunks[0]),'text'=>trim($chunks[1])); }
            } elseif ($target === 'additional_images') {
                $product['additional_images'] = $this->splitImageList($value);
            } elseif ($target === 'specials') {
                foreach (preg_split('/\s*[|;]\s*/', $value) as $item) { if ($item==='') continue; $chunks=preg_split('/\s*[:=]\s*/',$item,2); if (count($chunks)===2) { $group=(int)trim($chunks[0]); if (!$group) $group=1; $product['specials'][]=array('customer_group_id'=>$group,'price'=>(float)str_replace(',','.',trim($chunks[1]))); } else { $product['specials'][]=array('customer_group_id'=>1,'price'=>(float)str_replace(',','.',trim($item))); } }
            } elseif ($target === 'options') {
                foreach (preg_split('/\s*[|;]\s*/', $value) as $item) {
                    $parts = explode(':', $item);
                    if (count($parts) >= 3) {
                        $product['options'][] = array('name'=>trim($parts[0]), 'value'=>trim($parts[1]), 'price'=>(float)str_replace(',','.',trim($parts[2])), 'price_prefix'=>isset($parts[3]) ? trim($parts[3]) : '+');
                    }
                }
            } elseif ($target === 'google_product_category') {
                $product['google_product_category'] = $this->normalizeGoogleProductCategoryValue($value);
            } elseif (in_array($target, array('external_product_id','external_category_id','external_parent_category_id','supplier_sku'), true)) {
                $product[$target] = $value;
            } elseif ($target === 'supplier_price') {
                $product['supplier_price'] = (float)str_replace(',', '.', $value);
            } else {
                if (in_array($target, array('price','weight','length','width','height'))) $product[$target]=(float)str_replace(',','.', $value); elseif (in_array($target, array('quantity','minimum','subtract','status','tax_class_id','stock_status_id','default_category','manufacturer_id','sort_order','shipping'))) $product[$target]=(int)$value; elseif ($target === 'date_available') $product[$target] = $this->normalizeDateValue($value); else $product[$target]=$value;
            }
        }
        if (!empty($profile['settings']['supplier_auto_xml_params'])) {
            foreach ($row as $source_key => $source_value) {
                if (strpos((string)$source_key, 'param:') !== 0) {
                    continue;
                }
                $attribute_name = trim(substr((string)$source_key, 6));
                $attribute_value = trim((string)$source_value);
                if ($attribute_name !== '' && $attribute_value !== '') {
                    $product['attributes'][] = array('name' => $attribute_name, 'text' => $attribute_value);
                    $product['_mapped_targets'][] = 'attributes';
                }
            }
        }
        if (!empty($profile['settings']['supplier_mode'])) {
            $product = $this->applySupplierPriceRules($product, $profile['settings']);
        }

        $status_mode = isset($profile['settings']['import_product_status_mode']) ? (string)$profile['settings']['import_product_status_mode'] : 'file';
        if ($status_mode === 'enabled' || $status_mode === 'disabled') {
            $product['status'] = ($status_mode === 'enabled') ? 1 : 0;
            $product['_mapped_targets'][] = 'status';
        }

        if (!empty($profile['settings']['fill_missing_languages']) && !empty($product['descriptions'])) {
            $default_description = isset($product['descriptions'][$default_language_id]) ? $product['descriptions'][$default_language_id] : array();
            if (!$default_description) {
                foreach ($product['descriptions'] as $description) {
                    if (!empty($description['name'])) {
                        $default_description = $description;
                        break;
                    }
                }
            }
            if ($default_description) {
                foreach ($language_ids as $code => $language_id) {
                    if (empty($product['descriptions'][$language_id])) {
                        $product['descriptions'][$language_id] = $default_description;
                    } else {
                        foreach (array('name','description','meta_title','meta_description','meta_h1','meta_keyword') as $field) {
                            if (empty($product['descriptions'][$language_id][$field]) && !empty($default_description[$field])) {
                                $product['descriptions'][$language_id][$field] = $default_description[$field];
                            }
                        }
                    }
                }
            }
        }

        $product = $this->prepareProductImages($product, $profile['settings']);
        if (empty($product['descriptions'][$default_language_id]['name'])) { foreach ($product['descriptions'] as $description) { if (!empty($description['name'])) { $product['descriptions'][$default_language_id]['name'] = $description['name']; break; } } }
        if ($require_name && empty($product['descriptions'][$default_language_id]['name'])) throw new Exception('Missing product name for default language');
        $product = $this->sanitizeImportedProductData($product, $profile['settings']);
        return $product;
    }

    private function extractSeoKeywordFromUrl($url) {
        $url = trim((string)$url);
        if ($url === '') return '';
        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        $parts = @parse_url($url);
        $path = isset($parts['path']) ? $parts['path'] : $url;
        $path = trim($path);
        $path = preg_replace('~^index\.php\?route=product/product&product_id=\d+$~i', '', $path);
        $path = trim($path, '/');
        if ($path === '') return '';
        $segments = explode('/', $path);
        $last = end($segments);
        $last = preg_replace('~\.(html?|php)$~i', '', (string)$last);
        $last = preg_replace('~[^a-zA-Z0-9_\-]+~u', '-', $last);
        $last = trim($last, '-');
        return $last;
    }

    private function splitImageList($value) {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/\s*[|;\r\n]+\s*/u', (string)$value);
        }

        $result = array();
        foreach ($items as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $result[] = $item;
            }
        }

        return $result;
    }

    private function prepareProductImages($product, $settings) {
        $limit = isset($settings['image_limit']) ? (int)$settings['image_limit'] : (int)$this->config->get('module_import_pro_image_limit');
        if ($limit < 1) {
            $limit = 10;
        }
        $limit = max(1, min(100, $limit));

        $auto_split = !empty($settings['auto_split_images']);
        $main_list = $this->splitImageList(isset($product['image']) ? $product['image'] : '');
        $additional_list = $this->splitImageList(isset($product['additional_images']) ? $product['additional_images'] : array());

        if ($auto_split) {
            if (!empty($main_list)) {
                $product['image'] = array_shift($main_list);
                $additional_list = array_merge($main_list, $additional_list);
                if (!empty($main_list)) {
                    $product['_mapped_targets'][] = 'additional_images';
                }
            } elseif (!empty($additional_list)) {
                $product['image'] = array_shift($additional_list);
                $product['_mapped_targets'][] = 'image';
            }
        } else {
            $product['image'] = !empty($main_list) ? reset($main_list) : '';
        }

        $ordered = array();
        if (!empty($product['image'])) {
            $ordered[] = $product['image'];
        }
        foreach ($additional_list as $image) {
            $ordered[] = $image;
        }

        $prepared = array();
        foreach ($ordered as $image) {
            $image = trim((string)$image);
            if ($image === '') {
                continue;
            }
            $image = $this->prepareImage($image, $settings);
            if ($image === '' || in_array($image, $prepared, true)) {
                continue;
            }
            $prepared[] = $image;
            if (count($prepared) >= $limit) {
                break;
            }
        }

        $product['image'] = !empty($prepared) ? array_shift($prepared) : '';
        $product['additional_images'] = array_values($prepared);

        if (!empty($product['image'])) {
            $product['_mapped_targets'][] = 'image';
        }
        if (!empty($product['additional_images'])) {
            $product['_mapped_targets'][] = 'additional_images';
        }
        $product['_mapped_targets'] = array_values(array_unique($product['_mapped_targets']));

        return $product;
    }

    private function prepareImage($image, $settings) {
        $image = trim((string)$image);
        if ($image === '') {
            return '';
        }

        $download = array_key_exists('download_images', (array)$settings) ? !empty($settings['download_images']) : (bool)$this->config->get('module_import_pro_download_images');
        if (!$download || !preg_match('~^https?://~i', $image)) {
            return $image;
        }

        if (!$this->isSafePublicUrl($image)) {
            return '';
        }

        $subdir = $this->normalizeModuleSubdir(!empty($settings['image_subdir']) ? $settings['image_subdir'] : $this->config->get('module_import_pro_image_subdir'));
        $image_dir = rtrim(DIR_IMAGE, '/') . '/';
        if (!is_dir($image_dir . $subdir)) {
            @mkdir($image_dir . $subdir, 0755, true);
        }

        $ext = strtolower(pathinfo(parse_url($image, PHP_URL_PATH), PATHINFO_EXTENSION));
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp', 'avif');
        if (!in_array($ext, $allowed_ext, true)) {
            $ext = 'jpg';
        }

        $filename = 'imp_' . md5($image) . '.' . $ext;
        $target = $image_dir . $subdir . $filename;

        if (!is_file($target)) {
            try {
                $body = $this->fetchRemoteBody($image, $settings, 12582912);
                $info = function_exists('getimagesizefromstring') ? @getimagesizefromstring($body) : false;
                if (!$info || empty($info[0]) || empty($info[1])) {
                    return '';
                }

                if (!empty($info['mime'])) {
                    $mime_map = array(
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/gif'  => 'gif',
                        'image/webp' => 'webp',
                        'image/avif' => 'avif'
                    );
                    if (isset($mime_map[$info['mime']]) && $mime_map[$info['mime']] !== $ext) {
                        $ext = $mime_map[$info['mime']];
                        $filename = 'imp_' . md5($image) . '.' . $ext;
                        $target = $image_dir . $subdir . $filename;
                    }
                }

                if (@file_put_contents($target, $body) === false) {
                    return '';
                }
            } catch (Throwable $e) {
                $this->log->write('Import Pro image download skipped: ' . $e->getMessage());
                return '';
            }
        }

        return is_file($target) ? $subdir . $filename : '';
    }

    private function upsertSupplierFromSettings($profile_id, $settings) {
        if (empty($settings['supplier_name'])) {
            return 0;
        }
        $profile_id = (int)$profile_id;
        $name = trim((string)$settings['supplier_name']);
        if ($name === '') {
            return 0;
        }
        $markup_type = isset($settings['supplier_markup_type']) ? (string)$settings['supplier_markup_type'] : 'none';
        if (!in_array($markup_type, array('none', 'percent', 'fixed', 'percent_fixed'), true)) {
            $markup_type = 'none';
        }
        $markup_value = isset($settings['supplier_markup_value']) ? (float)$settings['supplier_markup_value'] : 0;
        $fixed_markup = isset($settings['supplier_fixed_markup']) ? (float)$settings['supplier_fixed_markup'] : 0;
        $price_rounding = isset($settings['supplier_price_rounding']) ? (float)$settings['supplier_price_rounding'] : 0;
        $price_source_type = isset($settings['supplier_price_stock_source_type']) ? (string)$settings['supplier_price_stock_source_type'] : '';
        $price_source_path = isset($settings['supplier_price_stock_source_path']) ? (string)$settings['supplier_price_stock_source_path'] : '';
        $query = $this->db->query("SELECT supplier_id FROM `" . DB_PREFIX . "import_pro_supplier` WHERE profile_id='" . (int)$profile_id . "' AND name='" . $this->db->escape($name) . "' LIMIT 1");
        $fields = array(
            "profile_id='" . (int)$profile_id . "'",
            "name='" . $this->db->escape($name) . "'",
            "status='" . (!empty($settings['supplier_mode']) ? 1 : 0) . "'",
            "markup_type='" . $this->db->escape($markup_type) . "'",
            "markup_value='" . (float)$markup_value . "'",
            "fixed_markup='" . (float)$fixed_markup . "'",
            "price_rounding='" . (float)$price_rounding . "'",
            "price_update_source_type='" . $this->db->escape($price_source_type) . "'",
            "price_update_source_path='" . $this->db->escape($price_source_path) . "'",
            "date_modified=NOW()"
        );
        if ($query->num_rows) {
            $supplier_id = (int)$query->row['supplier_id'];
            $this->db->query("UPDATE `" . DB_PREFIX . "import_pro_supplier` SET " . implode(', ', $fields) . " WHERE supplier_id='" . (int)$supplier_id . "'");
            return $supplier_id;
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "import_pro_supplier` SET " . implode(', ', $fields) . ", date_added=NOW()");
        return (int)$this->db->getLastId();
    }

    private function getSupplierIdFromSettings($profile_id, $settings) {
        $profile_id = (int)$profile_id;
        $name = isset($settings['supplier_name']) ? trim((string)$settings['supplier_name']) : '';
        if ($name === '') {
            return 0;
        }
        $query = $this->db->query("SELECT supplier_id FROM `" . DB_PREFIX . "import_pro_supplier` WHERE profile_id='" . (int)$profile_id . "' AND name='" . $this->db->escape($name) . "' LIMIT 1");
        return $query->num_rows ? (int)$query->row['supplier_id'] : 0;
    }

    private function findExistingSupplierProduct($supplier_id, $external_product_id) {
        $supplier_id = (int)$supplier_id;
        $external_product_id = trim((string)$external_product_id);
        if (!$supplier_id || $external_product_id === '') {
            return false;
        }
        $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "import_pro_supplier_product` WHERE supplier_id='" . (int)$supplier_id . "' AND external_product_id='" . $this->db->escape($external_product_id) . "' LIMIT 1");
        return $query->num_rows ? array('product_id' => (int)$query->row['product_id']) : false;
    }

    private function applySupplierPriceRules($product, $settings) {
        $source_price = isset($product['supplier_price']) && (float)$product['supplier_price'] > 0 ? (float)$product['supplier_price'] : (float)$product['price'];
        $markup_type = isset($settings['supplier_markup_type']) ? (string)$settings['supplier_markup_type'] : 'none';
        $markup_value = isset($settings['supplier_markup_value']) ? (float)$settings['supplier_markup_value'] : 0;
        $fixed_markup = isset($settings['supplier_fixed_markup']) ? (float)$settings['supplier_fixed_markup'] : 0;
        $rounding = isset($settings['supplier_price_rounding']) ? (float)$settings['supplier_price_rounding'] : 0;
        if ($markup_type === 'percent' || $markup_type === 'percent_fixed') {
            $source_price = $source_price + ($source_price * $markup_value / 100);
        }
        if ($markup_type === 'fixed' || $markup_type === 'percent_fixed') {
            $source_price = $source_price + $fixed_markup;
        }
        if ($rounding > 0) {
            $source_price = ceil($source_price / $rounding) * $rounding;
        }
        if ($source_price < 0) {
            $source_price = 0;
        }
        $product['price'] = $source_price;
        $product['_mapped_targets'][] = 'price';
        return $product;
    }

    private function saveSupplierProductLink($supplier_id, $product_id, $product_data) {
        $supplier_id = (int)$supplier_id;
        $product_id = (int)$product_id;
        $external_product_id = isset($product_data['external_product_id']) ? trim((string)$product_data['external_product_id']) : '';
        if (!$supplier_id || !$product_id || $external_product_id === '') {
            return;
        }
        $supplier_sku = isset($product_data['supplier_sku']) && $product_data['supplier_sku'] !== '' ? (string)$product_data['supplier_sku'] : (isset($product_data['sku']) ? (string)$product_data['sku'] : '');
        $last_price = isset($product_data['supplier_price']) && (float)$product_data['supplier_price'] > 0 ? (float)$product_data['supplier_price'] : (float)$product_data['price'];
        $last_quantity = isset($product_data['quantity']) ? (int)$product_data['quantity'] : 0;
        $hash_source = json_encode(array('price' => $last_price, 'quantity' => $last_quantity, 'model' => isset($product_data['model']) ? $product_data['model'] : '', 'sku' => $supplier_sku), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $last_hash = sha1((string)$hash_source);
        $this->db->query("REPLACE INTO `" . DB_PREFIX . "import_pro_supplier_product` SET supplier_id='" . (int)$supplier_id . "', product_id='" . (int)$product_id . "', external_product_id='" . $this->db->escape($external_product_id) . "', supplier_sku='" . $this->db->escape($supplier_sku) . "', last_price='" . (float)$last_price . "', last_quantity='" . (int)$last_quantity . "', last_hash='" . $this->db->escape($last_hash) . "', last_seen=NOW()");
    }

    private function saveSupplierCategoryLinks($supplier_id, $product_id, $product_data, $settings) {
        $supplier_id = (int)$supplier_id;
        if (!$supplier_id || empty($product_data['external_category_id'])) {
            return;
        }
        $external_category_id = trim((string)$product_data['external_category_id']);
        if ($external_category_id === '') {
            return;
        }
        $external_parent_category_id = isset($product_data['external_parent_category_id']) ? trim((string)$product_data['external_parent_category_id']) : '';
        $category_name = '';
        if (!empty($product_data['category_names']) && is_array($product_data['category_names'])) {
            $category_name = trim((string)end($product_data['category_names']));
        }
        $category_id = 0;
        $category_ids = $this->resolveCategories(isset($product_data['category_names']) ? $product_data['category_names'] : array(), $settings);
        if (!empty($category_ids)) {
            $category_id = (int)reset($category_ids);
        }
        $this->db->query("REPLACE INTO `" . DB_PREFIX . "import_pro_supplier_category` SET supplier_id='" . (int)$supplier_id . "', category_id='" . (int)$category_id . "', external_category_id='" . $this->db->escape($external_category_id) . "', external_parent_category_id='" . $this->db->escape($external_parent_category_id) . "', name='" . $this->db->escape($category_name) . "', last_seen=NOW()");
    }

    private function findExistingProduct($field, $value, $language_id = 0) {
        $allowed = array('product_id', 'model', 'sku', 'upc', 'ean', 'jan', 'isbn', 'mpn', 'name');
        if (!in_array($field, $allowed, true)) { throw new Exception('Invalid product matching field'); }
        if (trim((string)$value) === '') { return false; }
        if ($field === 'name') {
            $language_id = $language_id > 0 ? (int)$language_id : (int)$this->config->get('config_language_id');
            $query = $this->db->query("SELECT p.product_id FROM `" . DB_PREFIX . "product` p INNER JOIN `" . DB_PREFIX . "product_description` pd ON pd.product_id = p.product_id WHERE pd.name = '" . $this->db->escape($value) . "' AND pd.language_id = '" . (int)$language_id . "' LIMIT 2");
        } else {
            $query = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product` WHERE `" . $field . "` = '" . $this->db->escape($value) . "' LIMIT 2");
        }
        if ($query->num_rows > 1) { throw new Exception('Ambiguous product match: manual linking required'); }
        return $query->num_rows ? $query->row : false;
    }

    private function createProduct($product_data, $profile, $language_ids) {
        $manufacturer_id = !empty($product_data['manufacturer_id']) ? (int)$product_data['manufacturer_id'] : $this->resolveManufacturer($product_data['manufacturer'], $profile['settings']);
        $category_ids = $this->resolveCategories($product_data['category_names'], $profile['settings']);
        $product_data['default_category'] = $this->resolveMainCategory($product_data['default_category'], $category_ids, $profile['settings']);
        $date_available = $this->normalizeDateValue($product_data['date_available']);
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET
            model='" . $this->db->escape($product_data['model']) . "', sku='" . $this->db->escape($product_data['sku']) . "', upc='" . $this->db->escape($product_data['upc']) . "', ean='" . $this->db->escape($product_data['ean']) . "', jan='" . $this->db->escape($product_data['jan']) . "', isbn='" . $this->db->escape($product_data['isbn']) . "', mpn='" . $this->db->escape($product_data['mpn']) . "', location='" . $this->db->escape($product_data['location']) . "', quantity='" . (int)$product_data['quantity'] . "', stock_status_id='" . (int)$product_data['stock_status_id'] . "', image='" . $this->db->escape($product_data['image']) . "', manufacturer_id='" . (int)$manufacturer_id . "', shipping='" . (int)$product_data['shipping'] . "', price='" . (float)$product_data['price'] . "', points='0', tax_class_id='" . (int)$product_data['tax_class_id'] . "', date_available='" . $this->db->escape($date_available ? $date_available : date('Y-m-d')) . "', weight='" . (float)$product_data['weight'] . "', weight_class_id='" . (int)$product_data['weight_class_id'] . "', length='" . (float)$product_data['length'] . "', width='" . (float)$product_data['width'] . "', height='" . (float)$product_data['height'] . "', length_class_id='" . (int)$product_data['length_class_id'] . "', subtract='" . (int)$product_data['subtract'] . "', minimum='" . (int)$product_data['minimum'] . "', sort_order='" . (int)$product_data['sort_order'] . "', status='" . (int)$product_data['status'] . "', viewed='0', date_added=NOW(), date_modified=NOW()");
        $product_id = $this->db->getLastId();
        $this->ensureProductStores($product_id);
        $this->saveDescriptions($product_id, $product_data, $language_ids);
        $this->saveCategories($product_id, $category_ids, $product_data['default_category']);
        if ($this->isMappedTarget($product_data, 'google_product_category')) {
            $this->saveGoogleProductCategory((int)$product_data['default_category'], $product_data['google_product_category']);
        }
        $this->saveAdditionalImages($product_id, $product_data['additional_images']);
        $this->saveAttributes($product_id, $product_data['attributes'], $language_ids);
        $this->saveSeoKeyword($product_id, $product_data);
        $this->saveSpecials($product_id, $product_data['specials']);
        $this->saveOptions($product_id, $product_data['options']);
        return $product_id;
    }

    private function updateProduct($product_id, $product_data, $profile, $language_ids, $run_mode = 'full') {
        $manufacturer_id = !empty($product_data['manufacturer_id']) ? (int)$product_data['manufacturer_id'] : $this->resolveManufacturer($product_data['manufacturer'], $profile['settings']);

        if ($run_mode === 'price_qty') {
            $fields = array();
            if (!empty($profile['settings']['update_price']) && $this->isMappedTarget($product_data, 'price')) $fields[] = "price='" . (float)$product_data['price'] . "'";
            if (!empty($profile['settings']['update_quantity']) && $this->isMappedTarget($product_data, 'quantity')) $fields[] = "quantity='" . (int)$product_data['quantity'] . "'";
            if (!empty($fields)) {
                $fields[] = "date_modified=NOW()";
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET " . implode(', ', $fields) . " WHERE product_id='" . (int)$product_id . "'");
            }
            if (!empty($profile['settings']['update_specials']) && $this->isMappedTarget($product_data, 'specials')) $this->saveSpecials($product_id, $product_data['specials'], true);
            return;
        }

        $fields = array("date_modified=NOW()");
        foreach (array('model','sku','upc','ean','jan','isbn','mpn','location') as $target) {
            if ($this->isMappedTarget($product_data, $target)) {
                $fields[] = "`" . $target . "`='" . $this->db->escape($product_data[$target]) . "'";
            }
        }
        if ($this->isMappedTarget($product_data, 'manufacturer') || $this->isMappedTarget($product_data, 'manufacturer_id')) $fields[] = "manufacturer_id='" . (int)$manufacturer_id . "'";
        if (!empty($profile['settings']['update_price']) && $this->isMappedTarget($product_data, 'price')) $fields[] = "price='" . (float)$product_data['price'] . "'";
        if (!empty($profile['settings']['update_quantity']) && $this->isMappedTarget($product_data, 'quantity')) $fields[] = "quantity='" . (int)$product_data['quantity'] . "'";
        foreach (array('minimum','subtract','status','tax_class_id','stock_status_id','shipping','sort_order') as $target) {
            if ($this->isMappedTarget($product_data, $target)) {
                $fields[] = "`" . $target . "`='" . (int)$product_data[$target] . "'";
            }
        }
        foreach (array('weight','length','width','height') as $target) {
            if ($this->isMappedTarget($product_data, $target)) {
                $fields[] = "`" . $target . "`='" . (float)$product_data[$target] . "'";
            }
        }
        if ($this->isMappedTarget($product_data, 'date_available')) {
            $date_available = $this->normalizeDateValue($product_data['date_available']);
            if ($date_available !== '') {
                $fields[] = "date_available='" . $this->db->escape($date_available) . "'";
            }
        }
        $image_update_mode = !empty($profile['settings']['image_update_mode']) ? (string)$profile['settings']['image_update_mode'] : 'replace';
        if (!empty($profile['settings']['update_images']) && $image_update_mode !== 'keep' && $product_data['image'] !== '' && $this->isMappedTarget($product_data, 'image')) $fields[] = "image='" . $this->db->escape($product_data['image']) . "'";
        $this->db->query("UPDATE `" . DB_PREFIX . "product` SET " . implode(', ', $fields) . " WHERE product_id='" . (int)$product_id . "'");
        $this->ensureProductStores($product_id);
        if (!empty($profile['settings']['update_descriptions'])) $this->saveDescriptions($product_id, $product_data, $language_ids, false);
        if (!empty($profile['settings']['update_attributes'])) $this->saveAttributes($product_id, $product_data['attributes'], $language_ids, true);
        $resolved_category_ids = $this->resolveCategories($product_data['category_names'], $profile['settings']);
        $product_data['default_category'] = $this->resolveMainCategory($product_data['default_category'], $resolved_category_ids, $profile['settings']);
        if (!empty($resolved_category_ids)) {
            $this->saveCategories($product_id, $resolved_category_ids, $product_data['default_category'], true);
        }
        if ($this->isMappedTarget($product_data, 'google_product_category')) {
            $this->saveGoogleProductCategory((int)$product_data['default_category'], $product_data['google_product_category']);
        }
        if (!empty($profile['settings']['update_images']) && $image_update_mode !== 'keep' && $image_update_mode !== 'main' && !empty($product_data['additional_images'])) {
            $this->saveAdditionalImages($product_id, $product_data['additional_images'], $image_update_mode === 'replace');
        }
        if (!empty($profile['settings']['update_seo'])) $this->saveSeoKeyword($product_id, $product_data);
        if (!empty($profile['settings']['update_specials']) && $this->isMappedTarget($product_data, 'specials')) $this->saveSpecials($product_id, $product_data['specials'], true);
        if (!empty($profile['settings']['update_options'])) $this->saveOptions($product_id, $product_data['options'], true);
    }

    private function saveDescriptions($product_id, $product_data, $language_ids, $replace = false) {
        $has_meta_h1 = $this->tableColumnExists('product_description', 'meta_h1');

        foreach ($language_ids as $code => $language_id) {
            $incoming = isset($product_data['descriptions'][$language_id]) && is_array($product_data['descriptions'][$language_id]) ? $product_data['descriptions'][$language_id] : array();
            if (empty($incoming)) {
                continue;
            }

            $existing = array();
            if (!$replace) {
                $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_description` WHERE product_id='" . (int)$product_id . "' AND language_id='" . (int)$language_id . "' LIMIT 1");
                if ($query->num_rows) {
                    $existing = $query->row;
                }
            }

            $name = array_key_exists('name', $incoming) ? (string)$incoming['name'] : (isset($existing['name']) ? (string)$existing['name'] : '');
            if ($name === '') {
                continue;
            }

            $description      = array_key_exists('description', $incoming) ? (string)$incoming['description'] : (isset($existing['description']) ? (string)$existing['description'] : '');
            $tag              = isset($existing['tag']) ? (string)$existing['tag'] : '';
            $meta_title       = array_key_exists('meta_title', $incoming) ? (string)$incoming['meta_title'] : (isset($existing['meta_title']) && $existing['meta_title'] !== '' ? (string)$existing['meta_title'] : $name);
            $meta_description = array_key_exists('meta_description', $incoming) ? (string)$incoming['meta_description'] : (isset($existing['meta_description']) ? (string)$existing['meta_description'] : '');
            $meta_keyword     = array_key_exists('meta_keyword', $incoming) ? (string)$incoming['meta_keyword'] : (isset($existing['meta_keyword']) ? (string)$existing['meta_keyword'] : '');
            $meta_h1          = array_key_exists('meta_h1', $incoming) ? (string)$incoming['meta_h1'] : (isset($existing['meta_h1']) && $existing['meta_h1'] !== '' ? (string)$existing['meta_h1'] : $name);

            $fields = array(
                "product_id='" . (int)$product_id . "'",
                "language_id='" . (int)$language_id . "'",
                "name='" . $this->db->escape($name) . "'",
                "description='" . $this->db->escape($description) . "'",
                "tag='" . $this->db->escape($tag) . "'",
                "meta_title='" . $this->db->escape($meta_title) . "'",
                "meta_description='" . $this->db->escape($meta_description) . "'",
                "meta_keyword='" . $this->db->escape($meta_keyword) . "'"
            );

            if ($has_meta_h1) {
                $fields[] = "meta_h1='" . $this->db->escape($meta_h1) . "'";
            }

            $this->db->query("REPLACE INTO `" . DB_PREFIX . "product_description` SET " . implode(', ', $fields));
        }
    }

    private function resolveManufacturer($name, $settings) {
        if (!$name) return 0; $query = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name='" . $this->db->escape($name) . "' LIMIT 1");
        if ($query->num_rows) return (int)$query->row['manufacturer_id'];
        if (!empty($settings['create_manufacturer'])) { $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name='" . $this->db->escape($name) . "', sort_order=0"); $manufacturer_id = $this->db->getLastId(); $this->ensureManufacturerStores($manufacturer_id); return $manufacturer_id; }
        return 0;
    }

    private function resolveCategories($names, $settings) {
        $ids = array();
        $mode = !empty($settings['category_mode']) ? $settings['category_mode'] : 'file';
        $force_category_id = !empty($settings['force_category_id']) ? (int)$settings['force_category_id'] : 0;
        $import_category_mode = !empty($settings['import_category_mode']) ? (string)$settings['import_category_mode'] : 'none';
        $import_category_id = ($import_category_mode !== 'none') ? $this->resolveImportCategory($settings) : 0;

        if ($import_category_mode === 'replace' && $import_category_id) {
            return array($import_category_id);
        }

        if ($mode === 'force') {
            $ids = $force_category_id ? array($force_category_id) : array();
            if ($import_category_mode === 'add' && $import_category_id) {
                array_unshift($ids, $import_category_id);
            }
            return array_values(array_unique(array_filter($ids)));
        }
        foreach ($names as $name) {
            $name = trim($name); if ($name === '') continue;

            if (!empty($settings['category_path_mode']) && preg_match('~(?:\s*>\s*|\s*/\s*)~u', $name)) {
                $parts = preg_split('~\s*>\s*|\s*/\s*~u', $name);
                $parent_id = 0;
                $last_id = 0;
                foreach ($parts as $part_name) {
                    $part_name = trim($part_name); if ($part_name === '') continue;
                    $query = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id) WHERE cd.name='" . $this->db->escape($part_name) . "' AND c.parent_id='" . (int)$parent_id . "' LIMIT 1");
                    if ($query->num_rows) {
                        $last_id = (int)$query->row['category_id'];
                        $parent_id = $last_id;
                        continue;
                    }
                    if (empty($settings['create_categories'])) {
                        $last_id = 0;
                        break;
                    }
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET parent_id='" . (int)$parent_id . "', top='0', `column`='1', sort_order='0', status='1', date_added=NOW(), date_modified=NOW()");
                    $category_id = $this->db->getLastId();
                    $languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`");
                    $has_category_meta_h1 = $this->tableColumnExists('category_description', 'meta_h1');
                    foreach ($languages->rows as $language) {
                        $category_fields = array(
                            "category_id='" . (int)$category_id . "'",
                            "language_id='" . (int)$language['language_id'] . "'",
                            "name='" . $this->db->escape($part_name) . "'",
                            "description=''",
                            "meta_title='" . $this->db->escape($part_name) . "'",
                            "meta_description=''",
                            "meta_keyword=''"
                        );
                        if ($has_category_meta_h1) {
                            $category_fields[] = "meta_h1='" . $this->db->escape($part_name) . "'";
                        }
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET " . implode(', ', $category_fields));
                    }
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET category_id='" . (int)$category_id . "', store_id='0'");
                    $this->rebuildCategoryPath($category_id, $parent_id);
                    $last_id = (int)$category_id;
                    $parent_id = $last_id;
                }
                if ($last_id) {
                    $ids[] = $last_id;
                }
                continue;
            }

            $query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category_description` WHERE name='" . $this->db->escape($name) . "' LIMIT 1");
            if ($query->num_rows) { $ids[] = (int)$query->row['category_id']; continue; }
            if (!empty($settings['create_categories'])) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET parent_id='0', top='0', `column`='1', sort_order='0', status='1', date_added=NOW(), date_modified=NOW()");
                $category_id = $this->db->getLastId();
                $languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`");
                $has_category_meta_h1 = $this->tableColumnExists('category_description', 'meta_h1');
                foreach ($languages->rows as $language) {
                    $category_fields = array(
                        "category_id='" . (int)$category_id . "'",
                        "language_id='" . (int)$language['language_id'] . "'",
                        "name='" . $this->db->escape($name) . "'",
                        "description=''",
                        "meta_title='" . $this->db->escape($name) . "'",
                        "meta_description=''",
                        "meta_keyword=''"
                    );
                    if ($has_category_meta_h1) {
                        $category_fields[] = "meta_h1='" . $this->db->escape($name) . "'";
                    }
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET " . implode(', ', $category_fields));
                }
                $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET category_id='" . (int)$category_id . "', store_id='0'");
                $this->rebuildCategoryPath($category_id, 0);
                $ids[] = (int)$category_id;
            }
        }
        $ids = array_values(array_unique(array_filter($ids)));
        if ($force_category_id && $mode === 'both') {
            array_unshift($ids, $force_category_id);
            $ids = array_values(array_unique(array_filter($ids)));
        }
        if ($import_category_mode === 'add' && $import_category_id) {
            array_unshift($ids, $import_category_id);
            $ids = array_values(array_unique(array_filter($ids)));
        }
        return $ids;
    }

    private function resolveImportCategory($settings) {
        $name = isset($settings['import_category_name']) ? trim((string)$settings['import_category_name']) : '';
        if ($name === '') {
            $name = 'Imported products';
        }

        $query = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "category_description` WHERE name='" . $this->db->escape($name) . "' LIMIT 1");
        if ($query->num_rows) {
            return (int)$query->row['category_id'];
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET parent_id='0', top='0', `column`='1', sort_order='0', status='1', date_added=NOW(), date_modified=NOW()");
        $category_id = (int)$this->db->getLastId();
        $languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`");
        $has_category_meta_h1 = $this->tableColumnExists('category_description', 'meta_h1');
        foreach ($languages->rows as $language) {
            $category_fields = array(
                "category_id='" . (int)$category_id . "'",
                "language_id='" . (int)$language['language_id'] . "'",
                "name='" . $this->db->escape($name) . "'",
                "description=''",
                "meta_title='" . $this->db->escape($name) . "'",
                "meta_description=''",
                "meta_keyword=''"
            );
            if ($has_category_meta_h1) {
                $category_fields[] = "meta_h1='" . $this->db->escape($name) . "'";
            }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET " . implode(', ', $category_fields));
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET category_id='" . (int)$category_id . "', store_id='0'");
        $this->rebuildCategoryPath($category_id, 0);

        return $category_id;
    }

    private function resolveMainCategory($current_main_category, $category_ids, $settings) {
        $mode = !empty($settings['category_mode']) ? $settings['category_mode'] : 'file';
        $force_category_id = !empty($settings['force_category_id']) ? (int)$settings['force_category_id'] : 0;
        if ($mode === 'force' && $force_category_id) return $force_category_id;
        if ($mode === 'both' && $force_category_id) return $force_category_id;
        if ($current_main_category) return (int)$current_main_category;
        if (!empty($category_ids)) return (int)reset($category_ids);
        return 0;
    }

    private function saveCategories($product_id, $category_ids, $main_category = 0, $replace = false) {
        $product_id = (int)$product_id;
        $main_category = (int)$main_category;
        $category_ids = array_values(array_unique(array_filter(array_map('intval', (array)$category_ids))));

        if (!$main_category && !empty($category_ids)) {
            $main_category = (int)reset($category_ids);
        }

        if ($main_category && !in_array($main_category, $category_ids, true)) {
            array_unshift($category_ids, $main_category);
            $category_ids = array_values(array_unique(array_filter($category_ids)));
        }

        if ($replace) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_to_category` WHERE product_id='" . $product_id . "'");
        }

        $has_ptc_main = $this->tableColumnExists('product_to_category', 'main_category');
        $has_product_main = $this->tableColumnExists('product', 'main_category_id');

        if ($has_ptc_main && $main_category) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product_to_category` SET main_category='0' WHERE product_id='" . $product_id . "'");
        }

        foreach ($category_ids as $category_id) {
            $category_id = (int)$category_id;
            if (!$category_id) {
                continue;
            }

            if ($has_ptc_main) {
                $main = ($main_category && $main_category === $category_id) ? 1 : 0;
                $this->db->query("REPLACE INTO `" . DB_PREFIX . "product_to_category` SET product_id='" . $product_id . "', category_id='" . $category_id . "', main_category='" . (int)$main . "'");
            } else {
                $this->db->query("REPLACE INTO `" . DB_PREFIX . "product_to_category` SET product_id='" . $product_id . "', category_id='" . $category_id . "'");
            }
        }

        if ($has_ptc_main && $main_category) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product_to_category` SET main_category='1' WHERE product_id='" . $product_id . "' AND category_id='" . (int)$main_category . "'");
        }

        if ($has_product_main) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET main_category_id='" . $main_category . "' WHERE product_id='" . $product_id . "'");
        }
    }

    private function saveAdditionalImages($product_id, $images, $replace = false) {
        $product_id = (int)$product_id;
        if ($replace) {
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_image` WHERE product_id='" . $product_id . "'");
        }

        $existing = array();
        $sort_order = 0;
        $query = $this->db->query("SELECT image, sort_order FROM `" . DB_PREFIX . "product_image` WHERE product_id='" . $product_id . "' ORDER BY sort_order ASC, product_image_id ASC");
        foreach ($query->rows as $row) {
            $existing[] = (string)$row['image'];
            $sort_order = max($sort_order, (int)$row['sort_order'] + 1);
        }

        foreach ((array)$images as $image) {
            $image = trim((string)$image);
            if ($image === '' || in_array($image, $existing, true)) {
                continue;
            }
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET product_id='" . $product_id . "', image='" . $this->db->escape($image) . "', sort_order='" . (int)$sort_order . "'");
            $existing[] = $image;
            $sort_order++;
        }
    }

    private function saveAttributes($product_id, $attributes, $language_ids, $replace = false) {
        if ($replace) $this->db->query("DELETE FROM `" . DB_PREFIX . "product_attribute` WHERE product_id='" . (int)$product_id . "'");
        foreach ($attributes as $attribute) {
            $attribute_id = $this->resolveAttribute($attribute['name']); if (!$attribute_id) continue;
            foreach ($language_ids as $code => $language_id) $this->db->query("REPLACE INTO `" . DB_PREFIX . "product_attribute` SET product_id='" . (int)$product_id . "', attribute_id='" . (int)$attribute_id . "', language_id='" . (int)$language_id . "', text='" . $this->db->escape($attribute['text']) . "'");
        }
    }

    private function resolveAttribute($name) {
        if (!$name) return 0; $query = $this->db->query("SELECT attribute_id FROM `" . DB_PREFIX . "attribute_description` WHERE name='" . $this->db->escape($name) . "' LIMIT 1");
        if ($query->num_rows) return (int)$query->row['attribute_id'];
        $group_id = 1; $group_query = $this->db->query("SELECT attribute_group_id FROM `" . DB_PREFIX . "attribute_group` ORDER BY attribute_group_id ASC LIMIT 1"); if ($group_query->num_rows) $group_id = (int)$group_query->row['attribute_group_id'];
        $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute` SET attribute_group_id='" . (int)$group_id . "', sort_order=0"); $attribute_id = $this->db->getLastId();
        $languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`"); foreach ($languages->rows as $language) $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_description` SET attribute_id='" . (int)$attribute_id . "', language_id='" . (int)$language['language_id'] . "', name='" . $this->db->escape($name) . "'");
        return $attribute_id;
    }

    private function saveSeoKeyword($product_id, $product_data) {
        if (empty($product_data['seo_keyword'])) return; $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "seo_url'"); if (!$query->num_rows) return;
        $keyword = $this->makeUniqueSeoKeyword($product_data['seo_keyword'], $product_id);
        if ($keyword === '') return;
        $this->db->query("DELETE FROM `" . DB_PREFIX . "seo_url` WHERE query='product_id=" . (int)$product_id . "'"); $languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`");
        foreach ($languages->rows as $language) $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id=0, language_id='" . (int)$language['language_id'] . "', query='product_id=" . (int)$product_id . "', keyword='" . $this->db->escape($keyword) . "'");
    }

    private function saveSpecials($product_id, $specials, $replace = false) {
        if ($replace) $this->db->query("DELETE FROM `" . DB_PREFIX . "product_special` WHERE product_id='" . (int)$product_id . "'");
        foreach ($specials as $special) {
            if (!isset($special['price']) || $special['price'] === '') continue;
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_special` SET product_id='" . (int)$product_id . "', customer_group_id='" . (int)$special['customer_group_id'] . "', priority='1', price='" . (float)$special['price'] . "', date_start='0000-00-00', date_end='0000-00-00'");
        }
    }

    private function saveOptions($product_id, $options, $replace = false) {
        if ($replace) {
            $product_options = $this->db->query("SELECT product_option_id FROM `" . DB_PREFIX . "product_option` WHERE product_id='" . (int)$product_id . "'");
            foreach ($product_options->rows as $row) $this->db->query("DELETE FROM `" . DB_PREFIX . "product_option_value` WHERE product_option_id='" . (int)$row['product_option_id'] . "'");
            $this->db->query("DELETE FROM `" . DB_PREFIX . "product_option` WHERE product_id='" . (int)$product_id . "'");
        }
        if (!$options) return;
        foreach ($options as $option) {
            $ids = $this->resolveOptionAndValue($option['name'], $option['value']);
            if (!$ids) continue;
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_option` SET product_id='" . (int)$product_id . "', option_id='" . (int)$ids['option_id'] . "', `value`='', required='0'");
            $product_option_id = $this->db->getLastId();
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_option_value` SET product_option_id='" . (int)$product_option_id . "', product_id='" . (int)$product_id . "', option_id='" . (int)$ids['option_id'] . "', option_value_id='" . (int)$ids['option_value_id'] . "', quantity='999', subtract='0', price='" . (float)$option['price'] . "', price_prefix='" . $this->db->escape(in_array($option['price_prefix'], array('+','-','=')) ? $option['price_prefix'] : '+') . "', points='0', points_prefix='+', weight='0', weight_prefix='+'"
            );
        }
    }

    private function resolveOptionAndValue($option_name, $value_name) {
        if (!$option_name || !$value_name) return false;
        $query = $this->db->query("SELECT o.option_id FROM `" . DB_PREFIX . "option_description` od LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id = od.option_id) WHERE od.name='" . $this->db->escape($option_name) . "' LIMIT 1");
        if ($query->num_rows) {
            $option_id = (int)$query->row['option_id'];
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "option` SET type='select', sort_order=0");
            $option_id = $this->db->getLastId();
            $languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`");
            foreach ($languages->rows as $language) $this->db->query("INSERT INTO `" . DB_PREFIX . "option_description` SET option_id='" . (int)$option_id . "', language_id='" . (int)$language['language_id'] . "', name='" . $this->db->escape($option_name) . "'");
        }
        $value_query = $this->db->query("SELECT ov.option_value_id FROM `" . DB_PREFIX . "option_value_description` ovd LEFT JOIN `" . DB_PREFIX . "option_value` ov ON (ov.option_value_id = ovd.option_value_id) WHERE ov.option_id='" . (int)$option_id . "' AND ovd.name='" . $this->db->escape($value_name) . "' LIMIT 1");
        if ($value_query->num_rows) {
            $option_value_id = (int)$value_query->row['option_value_id'];
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "option_value` SET option_id='" . (int)$option_id . "', image='', sort_order=0");
            $option_value_id = $this->db->getLastId();
            $languages = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language`");
            foreach ($languages->rows as $language) $this->db->query("INSERT INTO `" . DB_PREFIX . "option_value_description` SET option_value_id='" . (int)$option_value_id . "', language_id='" . (int)$language['language_id'] . "', option_id='" . (int)$option_id . "', name='" . $this->db->escape($value_name) . "'");
        }
        return array('option_id' => $option_id, 'option_value_id' => $option_value_id);
    }



    private function langText($key, $fallback = '') {
        $value = $this->language->get($key);
        if ($value === $key || $value === '') {
            return $fallback !== '' ? $fallback : $key;
        }
        return $value;
    }

    private function langReplace($key, $fallback, array $replace = array()) {
        $text = $this->langText($key, $fallback);
        foreach ($replace as $search => $value) {
            $text = str_replace($search, (string)$value, $text);
        }
        return $text;
    }

    public function getDiagnostics() {
        $items = array();
        $add = function($status, $check, $message) use (&$items) {
            $items[] = array(
                'status' => $status,
                'check' => $check,
                'message' => $message
            );
        };

        $curl_ok = function_exists('curl_init');
        $zip_ok = class_exists('ZipArchive');
        $xml_ok = class_exists('SimpleXMLElement');
        $dom_ok = class_exists('DOMDocument');
        $mb_ok = function_exists('mb_strtolower');

        $add((version_compare(PHP_VERSION, '7.4.0', '>=') && version_compare(PHP_VERSION, '8.6.0', '<')) ? 'ok' : 'warn', $this->langText('diag_php_version', 'PHP version'), $this->langReplace('diag_msg_php_version', 'Current: {version}. Supported range: PHP 7.4–8.5.', array('{version}' => PHP_VERSION)));
        $add($curl_ok ? 'ok' : 'warn', $this->langText('diag_curl', 'cURL'), $curl_ok ? $this->langText('diag_msg_curl_ok', 'Available for stable URL imports and image downloads.') : $this->langText('diag_msg_curl_warn', 'Not available. URL imports can fall back to streams, but redirects/auth/SSL diagnostics are weaker.'));
        $add($zip_ok ? 'ok' : 'warn', $this->langText('diag_ziparchive', 'ZipArchive'), $zip_ok ? $this->langText('diag_msg_zip_ok', 'Available. XLSX export/import can work.') : $this->langText('diag_msg_zip_warn', 'Missing. XLSX export/import will not work. Install PHP zip extension.'));
        $add($xml_ok ? 'ok' : 'warn', $this->langText('diag_simplexml', 'SimpleXML'), $xml_ok ? $this->langText('diag_msg_simplexml_ok', 'Available. XML imports can work.') : $this->langText('diag_msg_simplexml_warn', 'Missing. XML imports will not work.'));
        $add($dom_ok ? 'ok' : 'warn', $this->langText('diag_domdocument', 'DOMDocument'), $dom_ok ? $this->langText('diag_msg_dom_ok', 'Available. HTML table import can work.') : $this->langText('diag_msg_dom_warn', 'Missing. HTML table import will not work.'));
        $add($mb_ok ? 'ok' : 'warn', $this->langText('diag_mbstring', 'mbstring'), $mb_ok ? $this->langText('diag_msg_mbstring_ok', 'Available. Multilingual text normalization is safe.') : $this->langText('diag_msg_mbstring_warn', 'Missing. Ukrainian/Russian string handling can be limited.'));

        $download_dir = DIR_DOWNLOAD . 'import_pro/';
        if (!is_dir($download_dir)) {
            @mkdir($download_dir, 0755, true);
        }
        $download_ok = is_dir($download_dir) && is_writable($download_dir);
        $add($download_ok ? 'ok' : 'error', $this->langText('diag_download_dir', 'DIR_DOWNLOAD/import_pro/'), $download_ok ? $this->langText('diag_msg_download_ok', 'Writable. Source file uploads can work.') : $this->langText('diag_msg_download_error', 'Not writable. Upload button and URL cache cannot save files. Check folder permissions.'));

        $image_subdir = $this->normalizeModuleSubdir($this->config->get('module_import_pro_image_subdir') ? $this->config->get('module_import_pro_image_subdir') : 'catalog/import_pro/');
        $image_dir = rtrim(DIR_IMAGE, '/') . '/' . $image_subdir;
        if (!is_dir($image_dir)) {
            @mkdir($image_dir, 0755, true);
        }
        $image_ok = is_dir($image_dir) && is_writable($image_dir);
        $add($image_ok ? 'ok' : 'warn', $this->langText('diag_image_folder', 'Image import folder'), $image_ok ? $this->langReplace('diag_msg_image_ok', 'Writable: {path}', array('{path}' => $image_subdir)) : $this->langReplace('diag_msg_image_warn', 'Not writable: {path}. Image downloading will be skipped or fail.', array('{path}' => $image_subdir)));

        foreach (array('import_pro_profile', 'import_pro_run', 'import_pro_supplier', 'import_pro_supplier_product', 'import_pro_supplier_category', 'import_pro_batch', 'import_pro_queue', 'import_pro_queue_log', 'import_pro_supplier_registry') as $table) {
            $query = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . $table) . "'");
            $add($query->num_rows ? 'ok' : 'error', $this->langReplace('diag_db_table', 'DB table {table}', array('{table}' => DB_PREFIX . $table)), $query->num_rows ? $this->langText('diag_msg_table_ok', 'Exists.') : $this->langText('diag_msg_table_missing', 'Missing. Reinstall/repair the module tables.'));
        }

        $meta_h1_ok = $this->tableColumnExists('product_description', 'meta_h1');
        $main_category_storage = $this->getMainCategoryStorageMode();
        $add($meta_h1_ok ? 'ok' : 'warn', $this->langText('diag_meta_h1', 'product_description.meta_h1'), $meta_h1_ok ? $this->langText('diag_msg_meta_h1_ok', 'Available. meta_h1 import/export is enabled.') : $this->langText('diag_msg_meta_h1_warn', 'Not found. The module will safely skip meta_h1 on standard OpenCart.'));
        if ($main_category_storage === 'both') {
            $add('ok', $this->langText('diag_main_category', 'Main category storage'), $this->langText('diag_msg_main_category_both', 'Available: product_to_category.main_category and product.main_category_id. product_to_category.main_category is used as primary, product.main_category_id is synchronized.'));
        } elseif ($main_category_storage === 'product_to_category') {
            $add('ok', $this->langText('diag_main_category', 'Main category storage'), $this->langText('diag_msg_main_category_ok', 'Available: product_to_category.main_category. Main category import/export is enabled.'));
        } elseif ($main_category_storage === 'product') {
            $add('ok', $this->langText('diag_main_category', 'Main category storage'), $this->langText('diag_msg_main_category_legacy', 'Available: product.main_category_id. It is supported as fallback storage.'));
        } else {
            $add('warn', $this->langText('diag_main_category', 'Main category storage'), $this->langText('diag_msg_main_category_warn', 'Dedicated main category field was not found. The module will use the first product_to_category link as safe fallback.'));
        }

        $google_category_ok = ($this->tableExists('googleshopping_category') && $this->tableColumnExists('googleshopping_category', 'google_product_category') && $this->tableColumnExists('googleshopping_category', 'category_id') && $this->tableColumnExists('googleshopping_category', 'store_id')) || $this->tableColumnExists('category', 'google_product_category_id') || $this->tableColumnExists('category', 'google_product_category');
        $add($google_category_ok ? 'ok' : 'warn', $this->langText('diag_google_product_category', 'Google Product Category ID'), $google_category_ok ? $this->langText('diag_msg_google_product_category_ok', 'Available. Import/export can read and save Google Product Category ID through category mapping.') : $this->langText('diag_msg_google_product_category_warn', 'Not found. Google Product Category ID import/export will be skipped safely.'));

        $cron_token = (string)$this->config->get('module_import_pro_cron_token');
        $add(strlen($cron_token) >= 32 ? 'ok' : 'warn', $this->langText('diag_cron_token', 'Cron token length'), strlen($cron_token) >= 32 ? $this->langText('diag_msg_cron_ok', 'Token length is good.') : $this->langText('diag_msg_cron_warn', 'Token is shorter than recommended. Save module settings to regenerate a 32-character token.'));
        $add($this->config->get('module_import_pro_status') ? 'ok' : 'warn', $this->langText('diag_module_status', 'Module status'), $this->config->get('module_import_pro_status') ? $this->langText('diag_msg_module_enabled', 'Enabled.') : $this->langText('diag_msg_module_disabled', 'Disabled. Import and cron are blocked until the module is enabled and settings are saved.'));

        return $items;
    }

    public function exportProducts($format, $filters = array()) {
        $format = strtolower(trim($format));
        $marketplace_formats = array('rozetka_yml', 'epicentr_xml', 'hotline_xml', 'prom_yml', 'price_ua_yml', 'facebook_csv', 'pinterest_csv', 'tiktok_csv');
        if (in_array($format, $marketplace_formats, true)) {
            return $this->exportMarketplaceFeed($format, $filters);
        }
        if (!in_array($format, array('csv','xlsx','xml','json'), true)) {
            throw new Exception('Unsupported export format');
        }

        $language_id = !empty($filters['language_id']) ? (int)$filters['language_id'] : (int)$this->config->get('config_language_id');
        $status_sql = '';
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status_sql = " AND p.status = '" . (int)$filters['status'] . "'";
        }
        $category_join = '';
        $category_sql = '';
        if (!empty($filters['category_id'])) {
            $category_join = " LEFT JOIN `" . DB_PREFIX . "product_to_category` p2cf ON (p.product_id = p2cf.product_id) ";
            $category_sql = " AND p2cf.category_id = '" . (int)$filters['category_id'] . "'";
        }
        $manufacturer_sql = '';
        if (!empty($filters['manufacturer_id'])) {
            $manufacturer_sql = " AND p.manufacturer_id = '" . (int)$filters['manufacturer_id'] . "'";
        }
        $supplier_id = !empty($filters['supplier_id']) ? (int)$filters['supplier_id'] : 0;
        $supplier_exists_sql = '';
        $supplier_sub_sql = '';
        if ($supplier_id > 0) {
            $supplier_exists_sql = " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp_filter WHERE ipsp_filter.product_id = p.product_id AND ipsp_filter.supplier_id = '" . (int)$supplier_id . "')";
            $supplier_sub_sql = " AND ipsp.supplier_id = '" . (int)$supplier_id . "'";
        }
        $stock_sql = '';
        if (!empty($filters['in_stock_only'])) {
            $stock_sql = " AND p.quantity > 0 ";
        }
        $limit_sql = '';
        $limit = isset($filters['limit']) ? max(0, (int)$filters['limit']) : 0;
        $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
        if ($limit > 0) {
            $limit_sql = " LIMIT " . $offset . "," . $limit;
        }
        $main_category_expr = $this->getMainCategoryExpression('p');
        $main_category_select = $main_category_expr . " AS main_category_id";
        $catalog_url = '';
        if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
            $catalog_url = HTTPS_CATALOG;
        } elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
            $catalog_url = HTTP_CATALOG;
        }
        $catalog_url = rtrim($catalog_url, '/') . '/';
        $export_image_format = isset($filters['image_format']) ? (string)$filters['image_format'] : 'relative';
        if (!in_array($export_image_format, array('relative', 'url'), true)) {
            $export_image_format = 'relative';
        }

        $has_product_meta_h1 = $this->tableColumnExists('product_description', 'meta_h1');
        $meta_h1_select = $has_product_meta_h1 ? "pd.meta_h1 AS meta_h1" : "'' AS meta_h1";

        $field_map = array(
            'product_id' => "p.product_id AS product_id",
            'model' => "p.model AS model",
            'sku' => "p.sku AS sku",
            'upc' => "p.upc AS upc",
            'ean' => "p.ean AS ean",
            'jan' => "p.jan AS jan",
            'isbn' => "p.isbn AS isbn",
            'mpn' => "p.mpn AS mpn",
            'location' => "p.location AS location",
            'quantity' => "p.quantity AS quantity",
            'minimum' => "p.minimum AS minimum",
            'subtract' => "p.subtract AS subtract",
            'stock_status_id' => "p.stock_status_id AS stock_status_id",
            'image' => "p.image AS image",
            'manufacturer' => "m.name AS manufacturer",
            'manufacturer_id' => "p.manufacturer_id AS manufacturer_id",
            'main_category_id' => $main_category_select,
            'google_product_category' => "'' AS google_product_category",
            'price' => "p.price AS price",
            'points' => "p.points AS points",
            'status' => "p.status AS status",
            'sort_order' => "p.sort_order AS sort_order",
            'date_available' => "p.date_available AS date_available",
            'shipping' => "p.shipping AS shipping",
            'tax_class_id' => "p.tax_class_id AS tax_class_id",
            'weight' => "p.weight AS weight",
            'weight_class_id' => "p.weight_class_id AS weight_class_id",
            'length' => "p.length AS length",
            'width' => "p.width AS width",
            'height' => "p.height AS height",
            'length_class_id' => "p.length_class_id AS length_class_id",
            'name' => "pd.name AS name",
            'description' => "pd.description AS description",
            'tag' => "pd.tag AS tag",
            'meta_title' => "pd.meta_title AS meta_title",
            'meta_description' => "pd.meta_description AS meta_description",
            'meta_h1' => $meta_h1_select,
            'meta_keywords' => "pd.meta_keyword AS meta_keywords",
            'seo_keyword' => "(SELECT su.keyword FROM `" . DB_PREFIX . "seo_url` su WHERE su.store_id='0' AND su.language_id='" . (int)$language_id . "' AND su.query = CONCAT('product_id=', p.product_id) LIMIT 1) AS seo_keyword",
            'product_url' => "COALESCE((SELECT IF(su.keyword IS NULL OR su.keyword = '', CONCAT('" . $this->db->escape($catalog_url) . "index.php?route=product/product&product_id=', p.product_id), CONCAT('" . $this->db->escape($catalog_url) . "', su.keyword)) FROM `" . DB_PREFIX . "seo_url` su WHERE su.store_id='0' AND su.language_id='" . (int)$language_id . "' AND su.query = CONCAT('product_id=', p.product_id) LIMIT 1), CONCAT('" . $this->db->escape($catalog_url) . "index.php?route=product/product&product_id=', p.product_id)) AS product_url",
            'categories' => "(SELECT GROUP_CONCAT(DISTINCT cd.name ORDER BY cd.name SEPARATOR '|') FROM `" . DB_PREFIX . "product_to_category` p2c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (p2c.category_id = cd.category_id AND cd.language_id='" . (int)$language_id . "') WHERE p2c.product_id = p.product_id) AS categories",
            'attributes' => "(SELECT GROUP_CONCAT(DISTINCT CONCAT(ad.name, ':', pav.text) ORDER BY ad.name SEPARATOR '|') FROM `" . DB_PREFIX . "product_attribute` pav LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON (pav.attribute_id = ad.attribute_id AND ad.language_id='" . (int)$language_id . "') WHERE pav.product_id = p.product_id AND pav.language_id='" . (int)$language_id . "') AS attributes",
            'additional_images' => "(SELECT GROUP_CONCAT(DISTINCT image ORDER BY product_image_id SEPARATOR '|') FROM `" . DB_PREFIX . "product_image` pi WHERE pi.product_id = p.product_id) AS additional_images",
            'category_ids' => "(SELECT GROUP_CONCAT(DISTINCT p2ci.category_id ORDER BY p2ci.category_id SEPARATOR '|') FROM `" . DB_PREFIX . "product_to_category` p2ci WHERE p2ci.product_id=p.product_id) AS category_ids",
            'store_ids' => "'' AS store_ids",
            'filter_ids' => "'' AS filter_ids",
            'download_ids' => "'' AS download_ids",
            'related_product_ids' => "'' AS related_product_ids",
            'related_article_ids' => "'' AS related_article_ids",
            'layouts' => "'' AS layouts",
            'discounts' => "'' AS discounts",
            'specials' => "'' AS specials",
            'rewards' => "'' AS rewards",
            'recurring' => "'' AS recurring",
            'options' => "'' AS options",
            'noindex' => "" . ($this->tableColumnExists('product', 'noindex') ? "p.noindex AS noindex" : "0 AS noindex") . "",
            'supplier_id' => "(SELECT GROUP_CONCAT(DISTINCT ipsp.supplier_id ORDER BY ipsp.supplier_id SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS supplier_id",
            'supplier_name' => "(SELECT GROUP_CONCAT(DISTINCT ips.name ORDER BY ips.name SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp LEFT JOIN `" . DB_PREFIX . "import_pro_supplier` ips ON (ipsp.supplier_id = ips.supplier_id) WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS supplier_name",
            'external_product_id' => "(SELECT GROUP_CONCAT(DISTINCT ipsp.external_product_id ORDER BY ipsp.external_product_id SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS external_product_id",
            'supplier_sku' => "(SELECT GROUP_CONCAT(DISTINCT ipsp.supplier_sku ORDER BY ipsp.supplier_sku SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS supplier_sku",
            'supplier_price' => "(SELECT GROUP_CONCAT(DISTINCT ipsp.last_price ORDER BY ipsp.supplier_id SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS supplier_price",
            'supplier_quantity' => "(SELECT GROUP_CONCAT(DISTINCT ipsp.last_quantity ORDER BY ipsp.supplier_id SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS supplier_quantity",
            'supplier_last_seen' => "(SELECT GROUP_CONCAT(DISTINCT ipsp.last_seen ORDER BY ipsp.last_seen DESC SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS supplier_last_seen",
            'supplier_hash' => "(SELECT GROUP_CONCAT(DISTINCT ipsp.last_hash ORDER BY ipsp.supplier_id SEPARATOR '|') FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp WHERE ipsp.product_id = p.product_id" . $supplier_sub_sql . ") AS supplier_hash",
            'external_category_id' => "(SELECT GROUP_CONCAT(DISTINCT ipsc.external_category_id ORDER BY ipsc.external_category_id SEPARATOR '|') FROM `" . DB_PREFIX . "product_to_category` p2cx LEFT JOIN `" . DB_PREFIX . "import_pro_supplier_category` ipsc ON (p2cx.category_id = ipsc.category_id) WHERE p2cx.product_id = p.product_id" . ($supplier_id > 0 ? " AND ipsc.supplier_id = '" . (int)$supplier_id . "'" : "") . " AND ipsc.external_category_id IS NOT NULL AND ipsc.external_category_id <> '') AS external_category_id",
            'external_parent_category_id' => "(SELECT GROUP_CONCAT(DISTINCT ipsc.external_parent_category_id ORDER BY ipsc.external_parent_category_id SEPARATOR '|') FROM `" . DB_PREFIX . "product_to_category` p2cx LEFT JOIN `" . DB_PREFIX . "import_pro_supplier_category` ipsc ON (p2cx.category_id = ipsc.category_id) WHERE p2cx.product_id = p.product_id" . ($supplier_id > 0 ? " AND ipsc.supplier_id = '" . (int)$supplier_id . "'" : "") . " AND ipsc.external_parent_category_id IS NOT NULL AND ipsc.external_parent_category_id <> '') AS external_parent_category_id"
        );

        $default_headers = array('product_id','model','sku','upc','ean','jan','isbn','mpn','location','quantity','minimum','subtract','stock_status_id','image','manufacturer','manufacturer_id','main_category_id','google_product_category','price','points','status','noindex','sort_order','date_available','shipping','tax_class_id','weight','weight_class_id','length','width','height','length_class_id','name','description','tag','meta_title','meta_description','meta_h1','meta_keywords','seo_keyword','product_url','categories','category_ids','attributes','additional_images','store_ids','filter_ids','download_ids','related_product_ids','related_article_ids','layouts','discounts','specials','rewards','recurring','options','supplier_id','supplier_name','external_product_id','supplier_sku','supplier_price','supplier_quantity','supplier_last_seen','external_category_id','external_parent_category_id');
        $headers = !empty($filters['fields']) && is_array($filters['fields']) ? array_values(array_intersect(array_keys($field_map), $filters['fields'])) : $default_headers;
        if (!$headers) {
            $headers = $default_headers;
        }

        $needs_google_product_category = in_array('google_product_category', $headers, true);
        $needs_hidden_main_category = $needs_google_product_category && !in_array('main_category_id', $headers, true);
        $advanced_headers = array('store_ids','filter_ids','download_ids','related_product_ids','related_article_ids','layouts','discounts','specials','rewards','recurring','options');
        $needs_advanced_export = (bool)array_intersect($advanced_headers, $headers);
        $needs_hidden_product_id = $needs_advanced_export && !in_array('product_id', $headers, true);

        $selects = array();
        foreach ($headers as $header) {
            if (isset($field_map[$header])) {
                $selects[] = $field_map[$header];
            }
        }
        if ($needs_hidden_main_category) {
            $selects[] = $main_category_expr . " AS __import_pro_main_category_id";
        }
        if ($needs_hidden_product_id) {
            $selects[] = "p.product_id AS __import_pro_product_id";
        }

        $sql = "SELECT " . implode(', ', $selects) . "
                FROM `" . DB_PREFIX . "product` p
                LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id='" . (int)$language_id . "')
                LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
                " . $category_join . "
                WHERE 1 " . $status_sql . $category_sql . $manufacturer_sql . $supplier_exists_sql . $stock_sql . "
                GROUP BY p.product_id
                ORDER BY p.product_id ASC" . $limit_sql;
        $rows = $this->db->query($sql)->rows;
        if ($needs_advanced_export) {
            $this->populateAdvancedProductExportFields($rows, $headers, $language_id);
        }
        if ($needs_google_product_category) {
            $store_id = (int)$this->config->get('config_store_id');
            foreach ($rows as &$row) {
                $category_id = isset($row['main_category_id']) ? (int)$row['main_category_id'] : (isset($row['__import_pro_main_category_id']) ? (int)$row['__import_pro_main_category_id'] : 0);
                $row['google_product_category'] = $this->getGoogleProductCategoryByCategoryId($category_id, $store_id);
                if (isset($row['__import_pro_main_category_id'])) {
                    unset($row['__import_pro_main_category_id']);
                }
            }
            unset($row);
        }
        if ($needs_hidden_product_id) {
            foreach ($rows as &$row) { unset($row['__import_pro_product_id']); }
            unset($row);
        }
        if ($export_image_format === 'url') {
            foreach ($rows as &$row) {
                if (isset($row['image'])) {
                    $row['image'] = $this->formatExportImageValue($row['image'], $catalog_url);
                }
                if (isset($row['additional_images'])) {
                    $parts = $this->splitImageList($row['additional_images']);
                    foreach ($parts as &$part) {
                        $part = $this->formatExportImageValue($part, $catalog_url);
                    }
                    unset($part);
                    $row['additional_images'] = implode('|', $parts);
                }
            }
            unset($row);
        }

        $filename = 'export_products_' . date('Ymd_His');
        if ($format === 'json') {
            return array('filename' => $filename . '.json', 'mime' => 'application/json', 'content' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
        if ($format === 'xml') {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "
<products>
";
            foreach ($rows as $row) {
                $xml .= "  <product>
";
                foreach ($headers as $header) {
                    $value = isset($row[$header]) ? $row[$header] : '';
                    $xml .= '    <' . $header . '><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$value) . ']]></' . $header . ">
";
                }
                $xml .= "  </product>
";
            }
            $xml .= "</products>
";
            return array('filename' => $filename . '.xml', 'mime' => 'application/xml', 'content' => $xml);
        }
        if ($format === 'csv') {
            $stream = fopen('php://temp', 'w+');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ',', '"', '\\');
            foreach ($rows as $row) {
                $line = array();
                foreach ($headers as $header) {
                    $value = isset($row[$header]) ? html_entity_decode(strip_tags((string)$row[$header]), ENT_QUOTES, 'UTF-8') : '';
                    $line[] = $this->escapeCsvFormulaValue($value);
                }
                fputcsv($stream, $line, ',', '"', '\\');
            }
            rewind($stream);
            $content = stream_get_contents($stream);
            fclose($stream);
            return array('filename' => $filename . '.csv', 'mime' => 'text/csv', 'content' => $content, 'row_count' => count($rows));
        }
        return $this->buildXlsxExport($filename, $headers, $rows);
    }

    private function populateAdvancedProductExportFields(&$rows, $headers, $languageId) {
        if (!$rows) { return; }
        $ids = array();
        $rowIndex = array();
        foreach ($rows as $index => $row) {
            $productId = isset($row['product_id']) ? (int)$row['product_id'] : (isset($row['__import_pro_product_id']) ? (int)$row['__import_pro_product_id'] : 0);
            if ($productId > 0) { $ids[$productId] = $productId; $rowIndex[$productId] = $index; }
        }
        if (!$ids) { return; }
        $in = implode(',', array_map('intval', array_values($ids)));

        $setList = function($field, $table, $valueColumn, $orderColumn = '') use (&$rows, $rowIndex, $in, $headers) {
            if (!in_array($field, $headers, true) || !$this->tableExists($table)) { return; }
            foreach ($rows as &$row) { $row[$field] = ''; } unset($row);
            $order = $orderColumn !== '' ? " ORDER BY product_id, `" . $orderColumn . "`" : " ORDER BY product_id, `" . $valueColumn . "`";
            $query = $this->db->query("SELECT product_id, `" . $valueColumn . "` AS value FROM `" . DB_PREFIX . $table . "` WHERE product_id IN (" . $in . ")" . $order);
            $lists = array();
            foreach ($query->rows as $item) { $lists[(int)$item['product_id']][] = (string)$item['value']; }
            foreach ($lists as $productId => $values) { if (isset($rowIndex[$productId])) { $rows[$rowIndex[$productId]][$field] = implode('|', array_values(array_unique($values))); } }
        };

        $setList('store_ids', 'product_to_store', 'store_id');
        $setList('filter_ids', 'product_filter', 'filter_id');
        $setList('download_ids', 'product_to_download', 'download_id');
        $setList('related_product_ids', 'product_related', 'related_id');
        if ($this->tableExists('product_related_article')) { $setList('related_article_ids', 'product_related_article', 'article_id'); }

        if (in_array('layouts', $headers, true) && $this->tableExists('product_to_layout')) {
            foreach ($rows as &$row) { $row['layouts'] = ''; } unset($row);
            $maps = array();
            foreach ($this->db->query("SELECT product_id, store_id, layout_id FROM `" . DB_PREFIX . "product_to_layout` WHERE product_id IN (" . $in . ") ORDER BY product_id, store_id")->rows as $item) {
                $maps[(int)$item['product_id']][] = (int)$item['store_id'] . ':' . (int)$item['layout_id'];
            }
            foreach ($maps as $productId => $values) { if (isset($rowIndex[$productId])) { $rows[$rowIndex[$productId]]['layouts'] = implode('|', $values); } }
        }

        $commercial = array(
            'discounts' => array('product_discount', array('customer_group_id','quantity','priority','price','date_start','date_end')),
            'specials' => array('product_special', array('customer_group_id','priority','price','date_start','date_end')),
            'rewards' => array('product_reward', array('customer_group_id','points')),
            'recurring' => array('product_recurring', array('recurring_id','customer_group_id'))
        );
        foreach ($commercial as $field => $definition) {
            if (!in_array($field, $headers, true) || !$this->tableExists($definition[0])) { continue; }
            foreach ($rows as &$row) { $row[$field] = ''; } unset($row);
            $columns = array_map(function($column){ return '`' . $column . '`'; }, $definition[1]);
            $collection = array();
            foreach ($this->db->query("SELECT product_id, " . implode(',', $columns) . " FROM `" . DB_PREFIX . $definition[0] . "` WHERE product_id IN (" . $in . ") ORDER BY product_id")->rows as $item) {
                $productId = (int)$item['product_id']; unset($item['product_id']);
                $collection[$productId][] = $item;
            }
            foreach ($collection as $productId => $items) {
                if (isset($rowIndex[$productId])) { $rows[$rowIndex[$productId]][$field] = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); }
            }
        }

        if (in_array('options', $headers, true) && $this->tableExists('product_option')) {
            foreach ($rows as &$row) { $row['options'] = ''; } unset($row);
            $options = array();
            $sql = "SELECT po.product_id, po.product_option_id, po.option_id, po.value, po.required, o.type, od.name AS option_name,
                           pov.product_option_value_id, pov.option_value_id, pov.quantity, pov.subtract, pov.price, pov.price_prefix,
                           pov.points, pov.points_prefix, pov.weight, pov.weight_prefix, ov.image, ov.sort_order, ovd.name AS value_name
                    FROM `" . DB_PREFIX . "product_option` po
                    LEFT JOIN `" . DB_PREFIX . "option` o ON (o.option_id=po.option_id)
                    LEFT JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id=po.option_id AND od.language_id='" . (int)$languageId . "')
                    LEFT JOIN `" . DB_PREFIX . "product_option_value` pov ON (pov.product_option_id=po.product_option_id)
                    LEFT JOIN `" . DB_PREFIX . "option_value` ov ON (ov.option_value_id=pov.option_value_id)
                    LEFT JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id=pov.option_value_id AND ovd.language_id='" . (int)$languageId . "')
                    WHERE po.product_id IN (" . $in . ") ORDER BY po.product_id, po.product_option_id, pov.product_option_value_id";
            foreach ($this->db->query($sql)->rows as $item) {
                $productId = (int)$item['product_id']; $productOptionId = (int)$item['product_option_id'];
                if (!isset($options[$productId][$productOptionId])) {
                    $options[$productId][$productOptionId] = array(
                        'option_id' => (int)$item['option_id'], 'name' => (string)$item['option_name'], 'type' => (string)$item['type'],
                        'value' => (string)$item['value'], 'required' => (int)$item['required'], 'values' => array()
                    );
                }
                if ((int)$item['product_option_value_id'] > 0) {
                    $options[$productId][$productOptionId]['values'][] = array(
                        'option_value_id'=>(int)$item['option_value_id'], 'name'=>(string)$item['value_name'], 'image'=>(string)$item['image'],
                        'sort_order'=>(int)$item['sort_order'], 'quantity'=>(int)$item['quantity'], 'subtract'=>(int)$item['subtract'],
                        'price'=>(float)$item['price'], 'price_prefix'=>(string)$item['price_prefix'], 'points'=>(int)$item['points'],
                        'points_prefix'=>(string)$item['points_prefix'], 'weight'=>(float)$item['weight'], 'weight_prefix'=>(string)$item['weight_prefix']
                    );
                }
            }
            foreach ($options as $productId => $items) {
                if (isset($rowIndex[$productId])) { $rows[$rowIndex[$productId]]['options'] = json_encode(array_values($items), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); }
            }
        }
    }

    public function streamProductsCsv($filters, $output) {
        if (!is_resource($output)) {
            throw new InvalidArgumentException('Недействительный поток CSV-экспорта.');
        }

        $baseOffset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
        $requestedLimit = isset($filters['limit']) ? max(0, (int)$filters['limit']) : 0;
        $processed = 0;
        $firstChunk = true;
        $chunkSize = 500;

        while (true) {
            $remaining = $requestedLimit > 0 ? ($requestedLimit - $processed) : $chunkSize;
            if ($requestedLimit > 0 && $remaining <= 0) {
                break;
            }

            $currentLimit = $requestedLimit > 0 ? min($chunkSize, $remaining) : $chunkSize;
            $chunkFilters = $filters;
            $chunkFilters['offset'] = $baseOffset + $processed;
            $chunkFilters['limit'] = $currentLimit;
            $result = $this->exportProducts('csv', $chunkFilters);
            $content = isset($result['content']) ? (string)$result['content'] : '';
            $rowCount = isset($result['row_count']) ? (int)$result['row_count'] : 0;

            if ($firstChunk) {
                fwrite($output, $content);
                $firstChunk = false;
            } else {
                if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
                    $content = substr($content, 3);
                }
                $newline = strpos($content, "\n");
                if ($newline !== false) {
                    $content = substr($content, $newline + 1);
                } else {
                    $content = '';
                }
                if ($content !== '') {
                    fwrite($output, $content);
                }
            }

            $processed += $rowCount;
            if ($rowCount < $currentLimit || $rowCount === 0) {
                break;
            }
        }

        return $processed;
    }

    private function escapeCsvFormulaValue($value) {
        $value = (string)$value;
        if (preg_match('/^[\x00-\x20]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    private function exportMarketplaceFeed($format, $filters = array()) {
        $language_id = !empty($filters['language_id']) ? (int)$filters['language_id'] : (int)$this->config->get('config_language_id');
        $ua_language_id = $this->getLanguageIdByCodes(array('uk-ua', 'ua-ua', 'uk', 'ua'), $language_id);
        $ru_language_id = $this->getLanguageIdByCodes(array('ru-ru', 'ru'), $language_id);
        $catalog_url = $this->getCatalogUrlForExport();
        $rows = $this->getMarketplaceRows($filters, $language_id, $ua_language_id, $ru_language_id, $catalog_url);
        $category_ids = array();
        foreach ($rows as $row) {
            $category_id = isset($row['main_category_id']) ? (int)$row['main_category_id'] : 0;
            if ($category_id > 0) {
                $category_ids[$category_id] = $category_id;
            }
        }
        $categories = $this->getMarketplaceCategories($category_ids, $language_id);
        $filename = str_replace('_', '-', $format) . '_' . date('Ymd_His');

        if ($format === 'hotline_xml') {
            return array('filename' => $filename . '.xml', 'mime' => 'application/xml', 'content' => $this->buildHotlineXml($rows, $categories));
        }
        if ($format === 'epicentr_xml') {
            return array('filename' => $filename . '.xml', 'mime' => 'application/xml', 'content' => $this->buildEpicentrXml($rows));
        }
        if (in_array($format, array('facebook_csv', 'pinterest_csv', 'tiktok_csv'), true)) {
            return array('filename' => $filename . '.csv', 'mime' => 'text/csv', 'content' => $this->buildSocialCsvFeed($format, $rows, $categories));
        }
        return array('filename' => $filename . '.yml', 'mime' => 'application/xml', 'content' => $this->buildYmlMarketplaceXml($format, $rows, $categories, $catalog_url));
    }

    private function getCatalogUrlForExport() {
        $catalog_url = '';
        if (defined('HTTPS_CATALOG') && HTTPS_CATALOG) {
            $catalog_url = HTTPS_CATALOG;
        } elseif (defined('HTTP_CATALOG') && HTTP_CATALOG) {
            $catalog_url = HTTP_CATALOG;
        } else {
            $catalog_url = (string)$this->config->get('config_url');
        }
        return rtrim($catalog_url, '/') . '/';
    }

    private function getLanguageIdByCodes($codes, $fallback_language_id) {
        $normalized = array();
        foreach ((array)$codes as $code) {
            $normalized[] = $this->db->escape(strtolower(trim((string)$code)));
        }
        if (!$normalized) {
            return (int)$fallback_language_id;
        }
        $query = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE LOWER(code) IN ('" . implode("','", $normalized) . "') ORDER BY language_id ASC LIMIT 1");
        if ($query->num_rows) {
            return (int)$query->row['language_id'];
        }
        return (int)$fallback_language_id;
    }

    private function getMarketplaceRows($filters, $language_id, $ua_language_id, $ru_language_id, $catalog_url) {
        $status_sql = '';
        if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== null) {
            $status_sql = " AND p.status = '" . (int)$filters['status'] . "'";
        }
        $category_join = '';
        $category_sql = '';
        if (!empty($filters['category_id'])) {
            $category_join = " LEFT JOIN `" . DB_PREFIX . "product_to_category` p2cf ON (p.product_id = p2cf.product_id) ";
            $category_sql = " AND p2cf.category_id = '" . (int)$filters['category_id'] . "'";
        }
        $manufacturer_sql = '';
        if (!empty($filters['manufacturer_id'])) {
            $manufacturer_sql = " AND p.manufacturer_id = '" . (int)$filters['manufacturer_id'] . "'";
        }
        $supplier_id = !empty($filters['supplier_id']) ? (int)$filters['supplier_id'] : 0;
        $supplier_exists_sql = '';
        if ($supplier_id > 0) {
            $supplier_exists_sql = " AND EXISTS (SELECT 1 FROM `" . DB_PREFIX . "import_pro_supplier_product` ipsp_filter WHERE ipsp_filter.product_id = p.product_id AND ipsp_filter.supplier_id = '" . (int)$supplier_id . "')";
        }
        $stock_sql = '';
        if (!empty($filters['in_stock_only'])) {
            $stock_sql = " AND p.quantity > 0 ";
        }
        $limit_sql = '';
        $limit = isset($filters['limit']) ? max(0, (int)$filters['limit']) : 0;
        $offset = isset($filters['offset']) ? max(0, (int)$filters['offset']) : 0;
        if ($limit > 0) {
            $limit_sql = " LIMIT " . $offset . "," . $limit;
        }
        $main_category_expr = $this->getMainCategoryExpression('p');

        $sql = "SELECT
                    p.product_id,
                    p.model,
                    p.sku,
                    p.upc,
                    p.ean,
                    p.jan,
                    p.isbn,
                    p.mpn,
                    p.quantity,
                    p.price,
                    p.status,
                    p.image,
                    p.weight,
                    p.length,
                    p.width,
                    p.height,
                    m.name AS manufacturer,
                    pd.name AS name,
                    pd.description AS description,
                    pdu.name AS name_ua,
                    pdu.description AS description_ua,
                    pdr.name AS name_ru,
                    pdr.description AS description_ru,
                    " . $main_category_expr . " AS main_category_id,
                    COALESCE((SELECT IF(su.keyword IS NULL OR su.keyword = '', CONCAT('" . $this->db->escape($catalog_url) . "index.php?route=product/product&product_id=', p.product_id), CONCAT('" . $this->db->escape($catalog_url) . "', su.keyword)) FROM `" . DB_PREFIX . "seo_url` su WHERE su.store_id='0' AND su.language_id='" . (int)$language_id . "' AND su.query = CONCAT('product_id=', p.product_id) LIMIT 1), CONCAT('" . $this->db->escape($catalog_url) . "index.php?route=product/product&product_id=', p.product_id)) AS product_url,
                    (SELECT GROUP_CONCAT(DISTINCT image ORDER BY product_image_id SEPARATOR '|') FROM `" . DB_PREFIX . "product_image` pi WHERE pi.product_id = p.product_id) AS additional_images,
                    (SELECT GROUP_CONCAT(DISTINCT CONCAT(ad.name, ':', pav.text) ORDER BY ad.name SEPARATOR '|') FROM `" . DB_PREFIX . "product_attribute` pav LEFT JOIN `" . DB_PREFIX . "attribute_description` ad ON (pav.attribute_id = ad.attribute_id AND ad.language_id='" . (int)$language_id . "') WHERE pav.product_id = p.product_id AND pav.language_id='" . (int)$language_id . "') AS attributes
                FROM `" . DB_PREFIX . "product` p
                LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id='" . (int)$language_id . "')
                LEFT JOIN `" . DB_PREFIX . "product_description` pdu ON (p.product_id = pdu.product_id AND pdu.language_id='" . (int)$ua_language_id . "')
                LEFT JOIN `" . DB_PREFIX . "product_description` pdr ON (p.product_id = pdr.product_id AND pdr.language_id='" . (int)$ru_language_id . "')
                LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
                " . $category_join . "
                WHERE 1 " . $status_sql . $category_sql . $manufacturer_sql . $supplier_exists_sql . $stock_sql . "
                GROUP BY p.product_id
                ORDER BY p.product_id ASC" . $limit_sql;

        $rows = $this->db->query($sql)->rows;
        foreach ($rows as &$row) {
            $row['image_url'] = $this->formatExportImageValue(isset($row['image']) ? $row['image'] : '', $catalog_url);
            $images = array();
            if (!empty($row['image_url'])) {
                $images[] = $row['image_url'];
            }
            foreach ($this->splitImageList(isset($row['additional_images']) ? $row['additional_images'] : '') as $image) {
                $image_url = $this->formatExportImageValue($image, $catalog_url);
                if ($image_url !== '' && !in_array($image_url, $images, true)) {
                    $images[] = $image_url;
                }
            }
            $row['marketplace_images'] = $images;
        }
        unset($row);
        return $rows;
    }

    private function getMarketplaceCategories($category_ids, $language_id) {
        $categories = array();
        if (!$category_ids) {
            return $categories;
        }
        $ids = array_map('intval', array_values($category_ids));
        $query = $this->db->query("SELECT c.category_id, c.parent_id, cd.name FROM `" . DB_PREFIX . "category` c LEFT JOIN `" . DB_PREFIX . "category_description` cd ON (c.category_id = cd.category_id AND cd.language_id='" . (int)$language_id . "') WHERE c.category_id IN (" . implode(',', $ids) . ") ORDER BY c.sort_order ASC, cd.name ASC");
        foreach ($query->rows as $row) {
            $categories[(int)$row['category_id']] = array(
                'category_id' => (int)$row['category_id'],
                'parent_id' => (int)$row['parent_id'],
                'name' => (string)$row['name']
            );
        }
        return $categories;
    }

    private function buildYmlMarketplaceXml($format, $rows, $categories, $catalog_url) {
        $shop_name = $this->cleanXmlText((string)$this->config->get('config_name'));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<yml_catalog date="' . date('Y-m-d H:i') . '">' . "\n";
        $xml .= "  <shop>\n";
        $xml .= '    <name>' . $this->xmlEscape($shop_name) . '</name>' . "\n";
        $xml .= '    <company>' . $this->xmlEscape($shop_name) . '</company>' . "\n";
        $xml .= '    <url>' . $this->xmlEscape($catalog_url) . '</url>' . "\n";
        $xml .= "    <currencies>\n";
        $xml .= "      <currency id=\"UAH\" rate=\"1\"/>\n";
        $xml .= "    </currencies>\n";
        $xml .= "    <categories>\n";
        foreach ($categories as $category) {
            $xml .= '      <category id="' . (int)$category['category_id'] . '"';
            if (!empty($category['parent_id']) && isset($categories[(int)$category['parent_id']])) {
                $xml .= ' parentId="' . (int)$category['parent_id'] . '"';
            }
            $xml .= '>' . $this->xmlEscape($this->cleanXmlText($category['name'])) . '</category>' . "\n";
        }
        $xml .= "    </categories>\n";
        $xml .= "    <offers>\n";
        foreach ($rows as $row) {
            $available = ((int)$row['status'] === 1 && (int)$row['quantity'] > 0) ? 'true' : 'false';
            $category_id = isset($row['main_category_id']) ? (int)$row['main_category_id'] : 0;
            $xml .= '      <offer id="' . $this->xmlEscape($this->stableMarketplaceId($row)) . '" available="' . $available . '">' . "\n";
            $xml .= '        <price>' . $this->formatMarketplacePrice($row['price']) . '</price>' . "\n";
            $xml .= "        <currencyId>UAH</currencyId>\n";
            if ($category_id > 0) {
                $xml .= '        <categoryId>' . (int)$category_id . '</categoryId>' . "\n";
            }
            foreach ((array)$row['marketplace_images'] as $image_url) {
                $xml .= '        <picture>' . $this->xmlEscape($image_url) . '</picture>' . "\n";
            }
            if (!empty($row['manufacturer'])) {
                $xml .= '        <vendor>' . $this->xmlEscape($this->cleanXmlText($row['manufacturer'])) . '</vendor>' . "\n";
            }
            $article = $this->bestArticleValue($row);
            if ($article !== '') {
                $xml .= '        <article>' . $this->xmlEscape($article) . '</article>' . "\n";
            }
            $xml .= '        <stock_quantity>' . max(0, (int)$row['quantity']) . '</stock_quantity>' . "\n";
            $xml .= '        <name>' . $this->xmlEscape($this->cleanXmlText(!empty($row['name_ru']) ? $row['name_ru'] : $row['name'])) . '</name>' . "\n";
            $xml .= '        <name_ua>' . $this->xmlEscape($this->cleanXmlText(!empty($row['name_ua']) ? $row['name_ua'] : $row['name'])) . '</name_ua>' . "\n";
            $xml .= '        <description>' . $this->xmlCdata($this->cleanCdataText(!empty($row['description_ru']) ? $row['description_ru'] : $row['description'])) . '</description>' . "\n";
            $xml .= '        <description_ua>' . $this->xmlCdata($this->cleanCdataText(!empty($row['description_ua']) ? $row['description_ua'] : $row['description'])) . '</description_ua>' . "\n";
            $xml .= $this->buildYmlParamsXml(isset($row['attributes']) ? $row['attributes'] : '', '        ');
            $xml .= "      </offer>\n";
        }
        $xml .= "    </offers>\n";
        $xml .= "  </shop>\n";
        $xml .= "</yml_catalog>\n";
        return $xml;
    }

    private function buildEpicentrXml($rows) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<yml_catalog date="' . date('Y-m-d H:i') . '">' . "\n";
        $xml .= "  <offers>\n";
        foreach ($rows as $row) {
            $available = ((int)$row['status'] === 1 && (int)$row['quantity'] > 0) ? 'true' : 'false';
            $category_id = isset($row['main_category_id']) ? (int)$row['main_category_id'] : 0;
            $xml .= '    <offer id="' . $this->xmlEscape($this->stableMarketplaceId($row)) . '" available="' . $available . '">' . "\n";
            $xml .= '      <price>' . $this->formatMarketplacePrice($row['price']) . '</price>' . "\n";
            $xml .= '      <availability>' . (((int)$row['quantity'] > 0 && (int)$row['status'] === 1) ? 'in_stock' : 'not_available') . '</availability>' . "\n";
            if ($category_id > 0) {
                $xml .= '      <category code="' . (int)$category_id . '">' . (int)$category_id . '</category>' . "\n";
            }
            foreach ((array)$row['marketplace_images'] as $image_url) {
                $xml .= '      <picture>' . $this->xmlEscape($image_url) . '</picture>' . "\n";
            }
            $xml .= '      <name lang="ru">' . $this->xmlEscape($this->cleanXmlText(!empty($row['name_ru']) ? $row['name_ru'] : $row['name'])) . '</name>' . "\n";
            $xml .= '      <name lang="ua">' . $this->xmlEscape($this->cleanXmlText(!empty($row['name_ua']) ? $row['name_ua'] : $row['name'])) . '</name>' . "\n";
            $xml .= '      <description lang="ru">' . $this->xmlCdata($this->cleanCdataText(!empty($row['description_ru']) ? $row['description_ru'] : $row['description'])) . '</description>' . "\n";
            $xml .= '      <description lang="ua">' . $this->xmlCdata($this->cleanCdataText(!empty($row['description_ua']) ? $row['description_ua'] : $row['description'])) . '</description>' . "\n";
            if ($category_id > 0) {
                $xml .= '      <attribute_set code="' . (int)$category_id . '">' . (int)$category_id . '</attribute_set>' . "\n";
            }
            $xml .= $this->buildEpicentrParamsXml($row, '      ');
            $xml .= "    </offer>\n";
        }
        $xml .= "  </offers>\n";
        $xml .= "</yml_catalog>\n";
        return $xml;
    }

    private function buildHotlineXml($rows, $categories) {
        $shop_name = $this->cleanXmlText((string)$this->config->get('config_name'));
        $firm_id = $this->cleanXmlText((string)$this->config->get('module_import_pro_hotline_firm_id'));
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= "<price>\n";
        $xml .= '  <date>' . date('Y-m-d H:i') . '</date>' . "\n";
        $xml .= '  <firmName>' . $this->xmlEscape($shop_name) . '</firmName>' . "\n";
        if ($firm_id !== '') {
            $xml .= '  <firmId>' . $this->xmlEscape($firm_id) . '</firmId>' . "\n";
        }
        $xml .= "  <categories>\n";
        foreach ($categories as $category) {
            $xml .= "    <category>\n";
            $xml .= '      <id>' . (int)$category['category_id'] . '</id>' . "\n";
            if (!empty($category['parent_id']) && isset($categories[(int)$category['parent_id']])) {
                $xml .= '      <parentId>' . (int)$category['parent_id'] . '</parentId>' . "\n";
            }
            $xml .= '      <name>' . $this->xmlEscape($this->cleanHotlineText($category['name'])) . '</name>' . "\n";
            $xml .= "    </category>\n";
        }
        $xml .= "  </categories>\n";
        $xml .= "  <items>\n";
        foreach ($rows as $row) {
            $category_id = isset($row['main_category_id']) ? (int)$row['main_category_id'] : 0;
            $image_url = !empty($row['marketplace_images'][0]) ? $row['marketplace_images'][0] : '';
            $xml .= "    <item>\n";
            $xml .= '      <id>' . $this->xmlEscape($this->stableMarketplaceId($row)) . '</id>' . "\n";
            if ($category_id > 0) {
                $xml .= '      <categoryId>' . (int)$category_id . '</categoryId>' . "\n";
            }
            $code = $this->bestArticleValue($row);
            if ($code !== '') {
                $xml .= '      <code>' . $this->xmlEscape($code) . '</code>' . "\n";
            }
            $barcode = $this->bestBarcodeValue($row);
            if ($barcode !== '') {
                $xml .= '      <barcode>' . $this->xmlEscape($barcode) . '</barcode>' . "\n";
            }
            if (!empty($row['manufacturer'])) {
                $xml .= '      <vendor>' . $this->xmlEscape($this->cleanHotlineText($row['manufacturer'])) . '</vendor>' . "\n";
            }
            $xml .= '      <name>' . $this->xmlEscape($this->cleanHotlineText(!empty($row['name_ua']) ? $row['name_ua'] : $row['name'])) . '</name>' . "\n";
            $xml .= '      <description>' . $this->xmlEscape($this->cleanHotlineText(!empty($row['description_ua']) ? $row['description_ua'] : $row['description'])) . '</description>' . "\n";
            $xml .= '      <url>' . $this->xmlEscape($row['product_url']) . '</url>' . "\n";
            if ($image_url !== '') {
                $xml .= '      <image>' . $this->xmlEscape($image_url) . '</image>' . "\n";
            }
            $xml .= '      <priceRUAH>' . $this->formatMarketplacePrice($row['price']) . '</priceRUAH>' . "\n";
            $xml .= '      <stock>' . (((int)$row['quantity'] > 0 && (int)$row['status'] === 1) ? 'В наявності' : 'Немає') . '</stock>' . "\n";
            $xml .= "    </item>\n";
        }
        $xml .= "  </items>\n";
        $xml .= "</price>\n";
        return $xml;
    }

    private function buildSocialCsvFeed($format, $rows, $categories) {
        $headers = $this->socialFeedHeaders($format);
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $headers, ',', '"', '\\');
        foreach ($rows as $row) {
            $item = $this->buildSocialFeedRow($format, $row, $categories);
            $line = array();
            foreach ($headers as $header) {
                $line[] = $this->escapeCsvFormulaValue(isset($item[$header]) ? $item[$header] : '');
            }
            fputcsv($stream, $line, ',', '"', '\\');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return $content;
    }

    private function socialFeedHeaders($format) {
        if ($format === 'tiktok_csv') {
            return array('sku_id','title','description','availability','condition','price','link','image_link','brand','google_product_category','product_type','additional_image_link','mpn','gtin');
        }
        if ($format === 'pinterest_csv') {
            return array('id','title','description','link','image_link','price','availability','item_group_id','google_product_category','product_type','brand','mpn','gtin','additional_image_link');
        }
        return array('id','title','description','availability','condition','price','link','image_link','brand','mpn','gtin','google_product_category','additional_image_link','inventory');
    }

    private function buildSocialFeedRow($format, $row, $categories) {
        $id = $this->stableMarketplaceId($row);
        $category_id = isset($row['main_category_id']) ? (int)$row['main_category_id'] : 0;
        $category_name = ($category_id > 0 && isset($categories[$category_id]['name'])) ? $this->cleanSocialFeedText($categories[$category_id]['name'], 750) : '';
        $title = $this->cleanSocialFeedText(!empty($row['name_ua']) ? $row['name_ua'] : $row['name'], 150);
        $description = $this->cleanSocialFeedText(!empty($row['description_ua']) ? $row['description_ua'] : $row['description'], 5000);
        if ($description === '') {
            $description = $title;
        }
        $images = !empty($row['marketplace_images']) && is_array($row['marketplace_images']) ? $row['marketplace_images'] : array();
        $image_link = !empty($images[0]) ? (string)$images[0] : '';
        $additional = array();
        for ($i = 1; $i < count($images); $i++) {
            if (!empty($images[$i])) {
                $additional[] = (string)$images[$i];
            }
        }
        $brand = !empty($row['manufacturer']) ? $this->cleanSocialFeedText($row['manufacturer'], 100) : $this->cleanSocialFeedText((string)$this->config->get('config_name'), 100);
        $mpn = !empty($row['mpn']) ? $this->cleanSocialFeedText($row['mpn'], 100) : $this->bestArticleValue($row);
        $gtin = $this->bestBarcodeValue($row);
        $availability = $this->feedAvailability($row);
        $price = $this->formatMarketplacePrice($row['price']) . ' UAH';
        $inventory = max(0, (int)$row['quantity']);
        $google_product_category = $category_id > 0 ? $this->getGoogleProductCategoryByCategoryId($category_id, 0) : '';
        $base = array(
            'id' => $id,
            'sku_id' => $id,
            'title' => $title,
            'description' => $description,
            'availability' => $availability,
            'condition' => 'new',
            'price' => $price,
            'link' => isset($row['product_url']) ? (string)$row['product_url'] : '',
            'image_link' => $image_link,
            'brand' => $brand,
            'mpn' => $mpn,
            'gtin' => $gtin,
            'google_product_category' => $google_product_category,
            'product_type' => $category_name,
            'item_group_id' => '',
            'additional_image_link' => implode(',', array_slice($additional, 0, $format === 'tiktok_csv' ? 10 : 20)),
            'inventory' => $inventory
        );
        return $base;
    }

    private function feedAvailability($row) {
        return ((int)$row['status'] === 1 && (int)$row['quantity'] > 0) ? 'in stock' : 'out of stock';
    }

    private function cleanSocialFeedText($value, $max = 5000) {
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
        $value = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $value);
        $value = preg_replace('/\s+/u', ' ', $value);
        $value = trim($value);
        if ($max > 0 && function_exists('mb_substr')) {
            $value = mb_substr($value, 0, $max, 'UTF-8');
        } elseif ($max > 0) {
            $value = substr($value, 0, $max);
        }
        return trim($value);
    }

    private function buildYmlParamsXml($attributes, $indent) {
        $xml = '';
        foreach ($this->parseExportAttributes($attributes) as $item) {
            $xml .= $indent . '<param name="' . $this->xmlEscape($this->cleanXmlText($item['name'])) . '">' . $this->xmlEscape($this->cleanXmlText($item['value'])) . '</param>' . "\n";
        }
        return $xml;
    }

    private function buildEpicentrParamsXml($row, $indent) {
        $xml = '';
        $article = $this->bestArticleValue($row);
        if ($article !== '') {
            $xml .= $indent . '<param paramcode="barcodes" name="Штрих код">' . $this->xmlCdata($article) . '</param>' . "\n";
        }
        if (!empty($row['manufacturer'])) {
            $xml .= $indent . '<param paramcode="brand" name="Бренд">' . $this->xmlEscape($this->cleanXmlText($row['manufacturer'])) . '</param>' . "\n";
        }
        if (!empty($row['weight'])) {
            $xml .= $indent . '<param paramcode="weight" name="Вага">' . $this->xmlCdata((string)$row['weight']) . '</param>' . "\n";
        }
        if (!empty($row['width'])) {
            $xml .= $indent . '<param paramcode="width" name="Ширина">' . $this->xmlCdata((string)$row['width']) . '</param>' . "\n";
        }
        if (!empty($row['height'])) {
            $xml .= $indent . '<param paramcode="height" name="Висота">' . $this->xmlCdata((string)$row['height']) . '</param>' . "\n";
        }
        if (!empty($row['length'])) {
            $xml .= $indent . '<param paramcode="length" name="Глибина">' . $this->xmlCdata((string)$row['length']) . '</param>' . "\n";
        }
        foreach ($this->parseExportAttributes(isset($row['attributes']) ? $row['attributes'] : '') as $item) {
            $xml .= $indent . '<param name="' . $this->xmlEscape($this->cleanXmlText($item['name'])) . '">' . $this->xmlCdata($this->cleanCdataText($item['value'])) . '</param>' . "\n";
        }
        return $xml;
    }

    private function parseExportAttributes($attributes) {
        $result = array();
        foreach (explode('|', (string)$attributes) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $bits = explode(':', $part, 2);
            $name = trim(isset($bits[0]) ? $bits[0] : '');
            $value = trim(isset($bits[1]) ? $bits[1] : '');
            if ($name !== '' && $value !== '') {
                $result[] = array('name' => $name, 'value' => $value);
            }
        }
        return $result;
    }

    private function stableMarketplaceId($row) {
        if (!empty($row['product_id'])) {
            return preg_replace('/[^A-Za-z0-9_-]/', '', (string)$row['product_id']);
        }
        $source = !empty($row['sku']) ? $row['sku'] : (!empty($row['model']) ? $row['model'] : md5(json_encode($row)));
        $source = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$source);
        return substr($source, 0, 20);
    }

    private function bestArticleValue($row) {
        foreach (array('sku', 'model', 'mpn', 'ean', 'upc') as $field) {
            if (!empty($row[$field])) {
                return $this->cleanXmlText((string)$row[$field]);
            }
        }
        return '';
    }

    private function bestBarcodeValue($row) {
        foreach (array('ean', 'upc', 'jan', 'isbn') as $field) {
            if (!empty($row[$field])) {
                return $this->cleanXmlText((string)$row[$field]);
            }
        }
        return '';
    }

    private function formatMarketplacePrice($price) {
        $price = (float)$price;
        if ($price < 0) {
            $price = 0;
        }
        return rtrim(rtrim(number_format($price, 2, '.', ''), '0'), '.');
    }

    private function cleanXmlText($value) {
        $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
        return trim($value);
    }

    private function cleanHotlineText($value) {
        $value = $this->cleanXmlText(strip_tags((string)$value));
        $value = preg_replace('/\s+/u', ' ', $value);
        return trim($value);
    }

    private function cleanCdataText($value) {
        $value = (string)$value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
        return trim($value);
    }

    private function xmlCdata($value) {
        return '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', (string)$value) . ']]>';
    }

    private function formatExportImageValue($image, $catalog_url) {
        $image = trim((string)$image);
        if ($image === '' || preg_match('~^https?://~i', $image)) {
            return $image;
        }
        return rtrim((string)$catalog_url, '/') . '/image/' . ltrim($image, '/');
    }

    private function buildXlsxExport($filename, $headers, $rows) {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive is required for XLSX export');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'ipx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new Exception('Cannot create XLSX archive');
        }
        $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Products" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $wb_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
        $sheet_rows = array();
        $all_rows = array_merge(array(array_combine($headers, $headers)), $rows);
        foreach ($all_rows as $r_idx => $row) {
            $cells = '';
            $col = 0;
            foreach ($headers as $header) {
                $col++;
                $value = ($r_idx === 0) ? $header : (isset($row[$header]) ? (string)$row[$header] : '');
                $cell_ref = $this->xlsxCol($col) . ($r_idx + 1);
                $cells .= '<c r="' . $cell_ref . '" t="inlineStr"><is><t>' . $this->xmlEscape($value) . '</t></is></c>';
            }
            $sheet_rows[] = '<row r="' . ($r_idx + 1) . '">' . $cells . '</row>';
        }
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . implode('', $sheet_rows) . '</sheetData></worksheet>';
        $zip->addFromString('[Content_Types].xml', $content_types);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $wb);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wb_rels);
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->close();
        $content = file_get_contents($tmp);
        @unlink($tmp);
        return array('filename' => $filename . '.xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'content' => $content);
    }

    private function xlsxColToIndex($letters) {
        $letters = strtoupper((string)$letters);
        $index = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $index = $index * 26 + (ord($letters[$i]) - 64);
        }
        return $index;
    }

    private function xlsxCol($index) {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = (int)(($index - $mod) / 26);
        }
        return $letters;
    }

    private function xmlEscape($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function sanitizeSafeFieldRules($rules) {
        $allowed = array('preserve','overwrite','fill_empty','merge','replace','clear');
        $result = array();
        foreach ((array)$rules as $field => $policy) {
            $canonical = $this->safeCanonicalTarget($field);
            $policy = strtolower(trim((string)$policy));
            if ($canonical !== '' && in_array($policy, $allowed, true)) {
                $result[(string)$field] = $policy;
            }
        }
        return $result;
    }

    public function createSafeImportBatch($profile_id, $run_mode = 'full') {
        $profile_id = (int)$profile_id;
        $profile = $this->getProfile($profile_id);
        $run_mode = $this->normalizeSafeRunMode($run_mode !== '' ? $run_mode : (isset($profile['settings']['run_mode']) ? $profile['settings']['run_mode'] : 'full'));
        $source_path = $this->prepareSource($profile);
        if (!is_file($source_path) || !is_readable($source_path)) {
            throw new RuntimeException('Источник импорта недоступен для чтения.');
        }

        $normalized = $this->buildSafeNormalizedCsv($profile, $source_path, $run_mode);
        $options = $this->buildSafeQueueOptions($profile, $run_mode);

        $this->load->model('extension/module/import_pro_queue');
        // Idempotent repair guarantees that queue tables also exist after a file-only upgrade.
        $this->model_extension_module_import_pro_queue->install();
        try {
            $result = $this->model_extension_module_import_pro_queue->createBatchFromCsv(
                'product',
                $normalized['path'],
                $normalized['name'],
                $options
            );
        } finally {
            if (!empty($normalized['path']) && is_file($normalized['path'])) {
                @unlink($normalized['path']);
            }
        }

        if (!empty($result['error'])) {
            throw new RuntimeException($result['error']);
        }
        $result['profile_id'] = $profile_id;
        $result['run_mode'] = $run_mode;
        return $result;
    }

    private function normalizeSafeRunMode($run_mode) {
        $run_mode = strtolower(trim((string)$run_mode));
        $aliases = array(
            'price_qty' => 'price_stock',
            'stock_only' => 'quantity_only',
            'update_only' => 'existing_only',
            'full_import' => 'full'
        );
        if (isset($aliases[$run_mode])) {
            $run_mode = $aliases[$run_mode];
        }
        return in_array($run_mode, array('full','price_only','quantity_only','price_stock','new_only','existing_only'), true) ? $run_mode : 'full';
    }

    private function buildSafeQueueOptions($profile, $run_mode) {
        $settings = isset($profile['settings']) && is_array($profile['settings']) ? $profile['settings'] : array();
        $create_new = !empty($settings['create_new']);
        $update_existing = !empty($settings['update_existing']);

        if ($run_mode === 'new_only') {
            $mode = 'add';
            $import_profile = 'new_only';
        } elseif ($run_mode === 'existing_only') {
            $mode = 'update';
            $import_profile = 'update_only';
        } elseif ($run_mode === 'price_only') {
            $mode = 'update';
            $import_profile = 'price_only';
        } elseif ($run_mode === 'quantity_only') {
            $mode = 'update';
            $import_profile = 'stock_only';
        } elseif ($run_mode === 'price_stock') {
            $mode = 'update';
            $import_profile = 'price_stock';
        } else {
            if ($create_new && $update_existing) {
                $mode = 'upsert';
            } elseif ($create_new) {
                $mode = 'add';
            } elseif ($update_existing) {
                $mode = 'update';
            } else {
                throw new RuntimeException('В профиле отключены и создание новых, и обновление существующих товаров.');
            }
            $import_profile = 'full_import';
        }

        $key_field = $this->safeCanonicalKeyField(isset($profile['key_field']) ? $profile['key_field'] : $profile['match_field']);
        $supplier_code = '';
        if (!empty($settings['supplier_mode'])) {
            $supplier_code = isset($settings['supplier_code']) ? strtolower(trim((string)$settings['supplier_code'])) : '';
            if ($supplier_code === '') {
                $supplier_code = 'supplier_' . (int)$profile['profile_id'];
            }
            $supplier_code = preg_replace('/[^a-z0-9._-]+/', '_', $supplier_code);
            $supplier_code = trim(substr($supplier_code, 0, 64), '._-');
            if (strlen($supplier_code) < 2) {
                $supplier_code = 'supplier_' . (int)$profile['profile_id'];
            }
        }

        $missing_action = isset($settings['missing_product_action']) ? (string)$settings['missing_product_action'] : 'none';
        if (!in_array($missing_action, array('none','disable','zero','delete'), true)) {
            $missing_action = 'none';
        }
        if ($supplier_code === '') {
            $missing_action = 'none';
        }

        $legacy_supplier_id = 0;
        if ($supplier_code !== '' && !empty($settings['supplier_mode'])) {
            $legacy_supplier_id = (int)$this->getSupplierIdFromSettings((int)$profile['profile_id'], $settings);
        }

        return array(
            'mode' => $mode,
            'import_profile' => $import_profile,
            'key_field' => $key_field,
            'delimiter' => ';',
            'encoding' => 'UTF-8',
            'language_id' => isset($profile['default_language_id']) ? (int)$profile['default_language_id'] : (int)$this->config->get('config_language_id'),
            'store_ids' => isset($settings['store_ids']) ? $settings['store_ids'] : '0',
            'batch_size' => isset($settings['batch_size']) ? max(1, min(100, (int)$settings['batch_size'])) : 25,
            'dry_run' => '1',
            'empty_field' => !empty($settings['ignore_empty_fields']) || !array_key_exists('ignore_empty_fields', $settings) ? '1' : '0',
            'field_rules' => $this->buildSafeFieldRules($profile, $run_mode),
            'supplier_code' => $supplier_code,
            'legacy_supplier_id' => $legacy_supplier_id,
            'sync_missing_action' => $missing_action,
            'sync_missing_confirm' => !empty($settings['sync_missing_confirm']) ? '1' : '0',
            'copy_default_language' => !empty($settings['fill_missing_languages']) ? '1' : '0',
            'replace_store_links' => '0',
            'replace_seo_url' => '0',
            'download_images' => !empty($settings['download_images']) ? '1' : '0',
            'image_directory' => isset($settings['image_subdir']) ? trim((string)$settings['image_subdir'], '/') : 'catalog/import_pro',
            'max_image_bytes' => 10485760,
            'create_categories' => !empty($settings['create_categories']) ? '1' : '0',
            'create_manufacturers' => !empty($settings['create_manufacturer']) ? '1' : '0',
            'replace_attributes' => '0',
            'create_options' => !empty($settings['create_options']) || !empty($settings['update_options']) ? '1' : '0',
            'create_option_values' => !empty($settings['create_option_values']) || !empty($settings['update_options']) ? '1' : '0',
            'update_option_definitions' => !empty($settings['update_option_definitions']) ? '1' : '0',
            'allow_explicit_id_import' => '0'
        );
    }

    private function buildSafeFieldRules($profile, $run_mode) {
        $settings = isset($profile['settings']) && is_array($profile['settings']) ? $profile['settings'] : array();
        $rules = array();
        $provided = isset($settings['field_rules']) && is_array($settings['field_rules']) ? $settings['field_rules'] : array();
        $allowed = array('preserve','overwrite','fill_empty','merge','replace','clear');
        foreach ($provided as $field => $policy) {
            $canonical = $this->safeCanonicalTarget($field);
            $policy = strtolower(trim((string)$policy));
            if ($canonical !== '' && in_array($policy, $allowed, true)) {
                $rules[strtolower($canonical)] = $policy;
            }
        }

        $legacyGroups = array(
            'update_price' => array('_PRICE_'),
            'update_quantity' => array('_QUANTITY_','_STOCK_STATUS_ID_'),
            'update_images' => array('_IMAGE_','_IMAGES_'),
            'update_descriptions' => array('_NAME_','_DESCRIPTION_','_TAG_','_META_TITLE_','_META_DESCRIPTION_','_META_KEYWORD_','_META_H1_'),
            'update_attributes' => array('_ATTRIBUTES_'),
            'update_seo' => array('_SEO_KEYWORD_'),
            'update_specials' => array('_SPECIALS_'),
            'update_options' => array('_OPTIONS_')
        );
        foreach ($legacyGroups as $setting => $fields) {
            if (!empty($settings[$setting])) {
                continue;
            }
            foreach ($fields as $field) {
                $lookup = strtolower($field);
                if (!isset($rules[$lookup])) {
                    $rules[$lookup] = 'create_only';
                }
            }
        }

        if (isset($settings['image_update_mode'])) {
            $mode = (string)$settings['image_update_mode'];
            if ($mode === 'keep') {
                $rules['_image_'] = 'preserve';
                $rules['_images_'] = 'preserve';
            } elseif ($mode === 'main') {
                $rules['_image_'] = 'overwrite';
                $rules['_images_'] = 'preserve';
            } elseif ($mode === 'append') {
                $rules['_image_'] = 'fill_empty';
                $rules['_images_'] = 'merge';
            } elseif ($mode === 'replace') {
                $rules['_image_'] = 'overwrite';
                $rules['_images_'] = 'replace';
            }
        }

        $key = strtolower($this->safeCanonicalKeyField(isset($profile['key_field']) ? $profile['key_field'] : $profile['match_field']));
        $rules[$key] = 'overwrite';

        if ($run_mode === 'price_only') {
            return array($key => 'overwrite', '_price_' => isset($settings['update_price']) && !$settings['update_price'] ? 'preserve' : 'overwrite');
        }
        if ($run_mode === 'quantity_only') {
            $stock_rule = isset($settings['update_quantity']) && !$settings['update_quantity'] ? 'preserve' : 'overwrite';
            return array($key => 'overwrite', '_quantity_' => $stock_rule, '_stock_status_id_' => $stock_rule);
        }
        if ($run_mode === 'price_stock') {
            $stock_rule = isset($settings['update_quantity']) && !$settings['update_quantity'] ? 'preserve' : 'overwrite';
            return array($key => 'overwrite', '_price_' => isset($settings['update_price']) && !$settings['update_price'] ? 'preserve' : 'overwrite', '_quantity_' => $stock_rule, '_stock_status_id_' => $stock_rule);
        }
        return $rules;
    }

    private function buildSafeNormalizedCsv($profile, $source_path, $run_mode) {
        $directory = (defined('DIR_STORAGE') ? rtrim(DIR_STORAGE, '/\\') : sys_get_temp_dir()) . DIRECTORY_SEPARATOR . 'import_pro_tmp' . DIRECTORY_SEPARATOR;
        if (!is_dir($directory) && !@mkdir($directory, 0750, true)) {
            throw new RuntimeException('Не удалось создать временный каталог безопасного импорта.');
        }
        $target_path = $directory . 'profile_' . (int)$profile['profile_id'] . '_' . bin2hex(random_bytes(8)) . '.csv';
        $output = @fopen($target_path, 'wb');
        if (!$output) {
            throw new RuntimeException('Не удалось создать нормализованный CSV-файл.');
        }

        try {
            $columns = $this->buildSafeCanonicalColumns($profile, $run_mode);
            fputcsv($output, array_keys($columns), ';', '"', '\\');
            $written = 0;
            $format = strtolower((string)$profile['format']);
            if ($format === 'csv') {
                $written = $this->streamSafeCsvRows($source_path, $profile, $columns, $output);
            } else {
                $parsed = $this->parseSourcePath($profile, $source_path, 0, 0);
                foreach ((array)$parsed['rows'] as $row) {
                    if (!$this->safeRawRowPassesFilters($row, $profile)) {
                        continue;
                    }
                    fputcsv($output, $this->buildSafeOutputRow($row, $profile, $columns), ';', '"', '\\');
                    $written++;
                }
            }
        } catch (Throwable $e) {
            fclose($output);
            @unlink($target_path);
            throw $e;
        }
        fclose($output);

        return array(
            'path' => $target_path,
            'name' => 'profile_' . (int)$profile['profile_id'] . '_' . date('Ymd_His') . '.csv',
            'rows' => $written
        );
    }

    private function streamSafeCsvRows($source_path, $profile, $columns, $output) {
        $settings = isset($profile['settings']) ? $profile['settings'] : array();
        $delimiter = isset($settings['delimiter']) && $settings['delimiter'] !== '' ? html_entity_decode((string)$settings['delimiter'], ENT_QUOTES, 'UTF-8') : ',';
        if ($delimiter === 'tab' || $delimiter === '\\t') { $delimiter = "\t"; }
        if (!in_array($delimiter, array(';', ',', "\t", '|'), true)) { $delimiter = ','; }
        $enclosure = isset($settings['enclosure']) && $settings['enclosure'] !== '' ? html_entity_decode((string)$settings['enclosure'], ENT_QUOTES, 'UTF-8') : '"';
        $escape = isset($settings['escape']) && $settings['escape'] !== '' ? html_entity_decode((string)$settings['escape'], ENT_QUOTES, 'UTF-8') : '\\';
        $start_row = isset($settings['start_row']) ? max(1, (int)$settings['start_row']) : 1;
        $handle = @fopen($source_path, 'rb');
        if (!$handle) { throw new RuntimeException('Не удалось открыть CSV-источник.'); }
        $line = 0;
        $headers = array();
        $written = 0;
        try {
            while (($data = fgetcsv($handle, 0, $delimiter, $enclosure, $escape)) !== false) {
                $line++;
                if ($line < $start_row) { continue; }
                if (!$headers) {
                    $headers = $this->normalizeSafeSourceHeaders($data, isset($settings['decode_html_entities']) && $settings['decode_html_entities']);
                    continue;
                }
                $row = array();
                foreach ($headers as $index => $header) {
                    $row[$header] = isset($data[$index]) ? (string)$data[$index] : '';
                }
                if (!$this->safeRawRowPassesFilters($row, $profile)) { continue; }
                fputcsv($output, $this->buildSafeOutputRow($row, $profile, $columns), ';', '"', '\\');
                $written++;
            }
        } finally {
            fclose($handle);
        }
        return $written;
    }

    private function normalizeSafeSourceHeaders($headers, $decode_entities) {
        $result = array();
        $seen = array();
        foreach ((array)$headers as $index => $header) {
            $header = $this->normalizeInputString($header, $decode_entities, true);
            $header = preg_replace('/^\\xEF\\xBB\\xBF/', '', $header);
            if ($header === '') { $header = 'column_' . ($index + 1); }
            $base = $header;
            $suffix = 2;
            while (isset($seen[$this->toLower($header)])) {
                $header = $base . '__' . $suffix++;
            }
            $seen[$this->toLower($header)] = true;
            $result[] = $header;
        }
        return $result;
    }

    private function buildSafeCanonicalColumns($profile, $run_mode) {
        $field_map = isset($profile['field_map']) && is_array($profile['field_map']) ? $profile['field_map'] : array();
        $columns = array();
        foreach ($field_map as $target => $source) {
            $canonical = $this->safeCanonicalTarget($target);
            if ($canonical === '' || trim((string)$source) === '') { continue; }
            if (isset($columns[$canonical])) {
                throw new RuntimeException('Несколько колонок профиля сопоставлены с одним полем ' . $canonical . '.');
            }
            $columns[$canonical] = array('target' => (string)$target, 'source' => (string)$source, 'synthetic' => false);
        }

        $key = $this->safeCanonicalKeyField(isset($profile['key_field']) ? $profile['key_field'] : $profile['match_field']);
        if (!isset($columns[$key])) {
            throw new RuntimeException('В профиле не сопоставлено выбранное ключевое поле ' . $key . '. Резервный поиск запрещён.');
        }

        $allowed = null;
        if ($run_mode === 'price_only') { $allowed = array($key, '_PRICE_'); }
        if ($run_mode === 'quantity_only') { $allowed = array($key, '_QUANTITY_', '_STOCK_STATUS_ID_'); }
        if ($run_mode === 'price_stock') { $allowed = array($key, '_PRICE_', '_QUANTITY_', '_STOCK_STATUS_ID_'); }
        if ($allowed !== null) {
            $columns = array_intersect_key($columns, array_fill_keys($allowed, true));
        }

        $settings = isset($profile['settings']) ? $profile['settings'] : array();
        $statusMode = isset($settings['import_product_status_mode']) ? (string)$settings['import_product_status_mode'] : 'file';
        if (in_array($statusMode, array('enabled','disabled'), true) && in_array($run_mode, array('full','new_only','existing_only'), true)) {
            $columns['_STATUS_'] = array('target' => 'status', 'source' => '', 'synthetic' => $statusMode === 'enabled' ? 'status_enabled' : 'status_disabled');
        }

        $forceCategoryId = isset($settings['force_category_id']) ? (int)$settings['force_category_id'] : 0;
        $categoryMode = isset($settings['category_mode']) ? (string)$settings['category_mode'] : 'file';
        if ($forceCategoryId > 0 && in_array($run_mode, array('full','new_only','existing_only'), true)) {
            if (!$this->safeIdExists('category', 'category_id', $forceCategoryId)) {
                throw new RuntimeException('Принудительная категория с ID ' . $forceCategoryId . ' не найдена.');
            }
            if ($categoryMode === 'force') {
                unset($columns['_CATEGORY_'], $columns['_CATEGORY_IDS_']);
                $columns['_CATEGORY_IDS_'] = array('target' => 'category_ids', 'source' => '', 'synthetic' => 'force_category_replace');
            } elseif ($categoryMode === 'both') {
                $columns['_CATEGORY_ID_'] = array('target' => 'category_ids', 'source' => '', 'synthetic' => 'force_category_merge');
            }
        }

        $importCategoryMode = isset($settings['import_category_mode']) ? (string)$settings['import_category_mode'] : 'none';
        $importCategoryName = isset($settings['import_category_name']) ? trim((string)$settings['import_category_name']) : '';
        if ($importCategoryName !== '' && in_array($importCategoryMode, array('add','replace'), true) && in_array($run_mode, array('full','new_only','existing_only'), true)) {
            if ($importCategoryMode === 'replace') {
                unset($columns['_CATEGORY_'], $columns['_CATEGORIES_'], $columns['_CATEGORY_IDS_'], $columns['_CATEGORY_ID_']);
            }
            $columns[$importCategoryMode === 'replace' ? '_CATEGORY_' : '_CATEGORIES_'] = array('target' => 'categories', 'source' => '', 'synthetic' => $importCategoryMode === 'replace' ? 'import_category_replace' : 'import_category_add');
        }

        $defaultCategoryId = isset($settings['default_category']) ? (int)$settings['default_category'] : 0;
        if ($defaultCategoryId > 0 && !isset($columns['_MAIN_CATEGORY_ID_']) && in_array($run_mode, array('full','new_only','existing_only'), true)) {
            if (!$this->safeIdExists('category', 'category_id', $defaultCategoryId)) {
                throw new RuntimeException('Категория по умолчанию с ID ' . $defaultCategoryId . ' не найдена.');
            }
            $columns['_MAIN_CATEGORY_ID_'] = array('target' => 'main_category_id', 'source' => '', 'synthetic' => 'default_main_category');
        }

        if (!empty($settings['supplier_mode'])) {
            $supplier_price_source = $this->safeFindMappedSource($field_map, 'supplier_price');
            if (!isset($columns['_PRICE_']) && $supplier_price_source !== '' && in_array($run_mode, array('full','price_only','price_stock'), true)) {
                $columns['_PRICE_'] = array('target' => 'supplier_price', 'source' => $supplier_price_source, 'synthetic' => 'supplier_price');
            }
            $supplier_qty_source = $this->safeFindMappedSource($field_map, 'supplier_quantity');
            if (!isset($columns['_QUANTITY_']) && $supplier_qty_source !== '' && in_array($run_mode, array('full','quantity_only','price_stock'), true)) {
                $columns['_QUANTITY_'] = array('target' => 'supplier_quantity', 'source' => $supplier_qty_source, 'synthetic' => 'supplier_quantity');
            }
        }

        if (in_array($run_mode, array('price_only','price_stock'), true) && !isset($columns['_PRICE_'])) {
            throw new RuntimeException('Для профиля обновления цен не сопоставлена колонка цены.');
        }
        if (in_array($run_mode, array('quantity_only','price_stock'), true) && !isset($columns['_QUANTITY_']) && !isset($columns['_STOCK_STATUS_ID_'])) {
            throw new RuntimeException('Для профиля обновления остатков не сопоставлена колонка количества или статуса наличия.');
        }
        return $columns;
    }

    private function safeFindMappedSource($field_map, $wanted) {
        foreach ((array)$field_map as $target => $source) {
            if (strtolower((string)$target) === strtolower((string)$wanted)) { return trim((string)$source); }
        }
        return '';
    }

    private function buildSafeOutputRow($row, $profile, $columns) {
        $settings = isset($profile['settings']) ? $profile['settings'] : array();
        $output = array();
        foreach ($columns as $canonical => $definition) {
            $synthetic = isset($definition['synthetic']) ? $definition['synthetic'] : false;
            $value = $this->safeRowValue($row, $definition['source']);
            if ($synthetic === 'status_enabled') {
                $value = '1';
            } elseif ($synthetic === 'status_disabled') {
                $value = '0';
            } elseif ($synthetic === 'force_category_replace' || $synthetic === 'force_category_merge') {
                $value = (string)(int)$settings['force_category_id'];
            } elseif ($synthetic === 'import_category_replace' || $synthetic === 'import_category_add') {
                $value = trim((string)$settings['import_category_name']);
            } elseif ($synthetic === 'default_main_category') {
                $value = (string)(int)$settings['default_category'];
            } elseif ($synthetic === 'supplier_price') {
                $value = $this->safeApplySupplierPrice((string)$value, $settings);
            } elseif ($synthetic === 'supplier_quantity') {
                $value = $this->normalizeSupplierQuantity($value, $settings);
            } elseif (strtolower((string)$definition['target']) === 'product_url') {
                $value = $this->extractSeoKeywordFromUrl($value);
            } elseif ($canonical === '_SPECIALS_') {
                $value = $this->safeNormalizeSpecials($value);
            }

            if ($canonical === '_QUANTITY_') {
                $value = $this->normalizeSupplierQuantity($value, $settings);
            }
            if (strpos($canonical, '_DESCRIPTION') === 0 && !empty($settings['clean_description_html'])) {
                $value = $this->sanitizeHtmlFragment($value, $settings);
            } elseif (!empty($settings['clean_text_fields']) && preg_match('/^_(NAME|META_|TAG|MANUFACTURER)/', $canonical)) {
                $value = $this->sanitizePlainText($value, $settings, true);
            }
            $output[] = $value;
        }
        return $output;
    }

    private function normalizeSupplierQuantity($value, $settings) {
        $value = $this->toLower($this->normalizeInputString($value, true));
        if (preg_match('/^-?\d+$/D', $value)) { return (string)max(0, (int)$value); }
        if (preg_match('/^[>≥~]\s*(\d+)$/u', $value, $m)) { return (string)(int)$m[1]; }
        if (preg_match('/(?:нет|не)\s+в\s+наличии|нема[є]?\s+в\s+наявност[іi]|відсутн|отсутств|out\s+of\s+stock|not\s+available|unavailable|^false$/u', $value)) { return '0'; }
        if (preg_match('/в\s+наявності|в\s+наличии|in\s+stock|^available$|^true$/u', $value)) {
            return (string)(isset($settings['in_stock_quantity']) ? max(0, min(2147483647, (int)$settings['in_stock_quantity'])) : 100);
        }
        return ''; // An unknown value must preserve stock, including in price+stock mode.
    }

    private function safeRowValue($row, $header) {
        if (array_key_exists($header, $row)) { return $row[$header]; }
        $wanted = $this->toLower(trim((string)$header));
        foreach ((array)$row as $key => $value) {
            if ($this->toLower(trim((string)$key)) === $wanted) { return $value; }
        }
        return '';
    }

    private function safeRawRowPassesFilters($row, $profile) {
        $settings = isset($profile['settings']) ? $profile['settings'] : array();
        $map = isset($profile['field_map']) ? $profile['field_map'] : array();
        $get = function($target) use ($row, $map) {
            foreach ((array)$map as $mappedTarget => $source) {
                if (strtolower((string)$mappedTarget) === strtolower((string)$target)) {
                    return $this->safeRowValue($row, $source);
                }
            }
            return '';
        };
        if (!empty($settings['filter_in_stock_only'])) {
            $quantityValue = (string)$get('quantity');
            if (trim($quantityValue) === '') { $quantityValue = (string)$get('supplier_quantity'); }
            $quantity = str_replace(',', '.', trim($quantityValue));
            if ($quantity === '' || !is_numeric($quantity) || (float)$quantity <= 0) { return false; }
        }
        if (isset($settings['filter_status_value']) && trim((string)$settings['filter_status_value']) !== '') {
            if (trim((string)$get('status')) !== trim((string)$settings['filter_status_value'])) { return false; }
        }
        if (!empty($settings['filter_manufacturer_contains']) && !$this->stringContains((string)$get('manufacturer'), (string)$settings['filter_manufacturer_contains'])) { return false; }
        if (!empty($settings['filter_category_contains']) && !$this->stringContains((string)$get('categories'), (string)$settings['filter_category_contains'])) { return false; }
        return true;
    }

    private function safeApplySupplierPrice($value, $settings) {
        $normalized = str_replace(array("\xc2\xa0", ' '), '', trim((string)$value));
        $normalized = str_replace(',', '.', $normalized);
        if ($normalized === '' || !preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) { return $value; }
        $price = (float)$normalized;
        $type = isset($settings['supplier_markup_type']) ? (string)$settings['supplier_markup_type'] : 'none';
        $percent = isset($settings['supplier_markup_value']) ? (float)$settings['supplier_markup_value'] : 0.0;
        $fixed = isset($settings['supplier_fixed_markup']) ? (float)$settings['supplier_fixed_markup'] : 0.0;
        if ($type === 'percent' || $type === 'percent_fixed') { $price += $price * $percent / 100; }
        if ($type === 'fixed' || $type === 'percent_fixed') { $price += $fixed; }
        $rounding = isset($settings['supplier_price_rounding']) ? (float)$settings['supplier_price_rounding'] : 0.0;
        if ($rounding > 0) { $price = ceil($price / $rounding) * $rounding; }
        return number_format(max(0, $price), 4, '.', '');
    }

    private function safeNormalizeSpecials($value) {
        $value = trim((string)$value);
        if ($value === '' || $value[0] === '[' || $value[0] === '{') { return $value; }
        $records = array();
        foreach (preg_split('/\s*[|;]\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) as $item) {
            $parts = preg_split('/\s*[:=]\s*/', $item);
            if (count($parts) === 2) {
                $group = (int)$parts[0];
                if ($group <= 0) { $group = 1; }
                $records[] = $group . ':0:' . str_replace(',', '.', trim($parts[1])) . ':0000-00-00:0000-00-00';
            } elseif (count($parts) === 1) {
                $records[] = '1:0:' . str_replace(',', '.', trim($parts[0])) . ':0000-00-00:0000-00-00';
            } else {
                $records[] = $item;
            }
        }
        return implode('|', $records);
    }

    private function safeIdExists($table, $column, $id) {
        $allowed = array(
            'category' => array('category_id'),
            'product' => array('product_id'),
            'manufacturer' => array('manufacturer_id'),
            'store' => array('store_id')
        );
        if (!isset($allowed[$table]) || !in_array($column, $allowed[$table], true) || (int)$id <= 0) {
            return false;
        }
        $query = $this->db->query("SELECT `" . $column . "` FROM `" . DB_PREFIX . $table . "` WHERE `" . $column . "`='" . (int)$id . "' LIMIT 1");
        return $query->num_rows > 0;
    }

    private function safeCanonicalKeyField($field) {
        $field = strtolower(trim((string)$field));
        $map = array(
            'product_id' => '_ID_', 'id' => '_ID_',
            'model' => '_MODEL_', 'sku' => '_SKU_', 'upc' => '_UPC_', 'ean' => '_EAN_',
            'jan' => '_JAN_', 'isbn' => '_ISBN_', 'mpn' => '_MPN_', 'name' => '_NAME_'
        );
        return isset($map[$field]) ? $map[$field] : '_MODEL_';
    }

    private function safeCanonicalTarget($target) {
        $target = trim((string)$target);
        if ($target === '') { return ''; }
        if (preg_match('/^(name|description|meta_title|meta_description|meta_h1|meta_keywords|meta_keyword|tag):(.+)$/i', $target, $match)) {
            $base = strtoupper($match[1] === 'meta_keywords' ? 'META_KEYWORD' : $match[1]);
            $code = strtoupper(str_replace('_', '-', trim($match[2])));
            return '_' . $base . '_LANG=' . preg_replace('/[^A-Z0-9-]+/', '', $code) . '_';
        }
        $map = array(
            'product_id'=>'_ID_','id'=>'_ID_','model'=>'_MODEL_','sku'=>'_SKU_','upc'=>'_UPC_','ean'=>'_EAN_','jan'=>'_JAN_','isbn'=>'_ISBN_','mpn'=>'_MPN_',
            'location'=>'_LOCATION_','price'=>'_PRICE_','points'=>'_POINTS_','quantity'=>'_QUANTITY_','minimum'=>'_MINIMUM_','subtract'=>'_SUBTRACT_','status'=>'_STATUS_',
            'stock_status_id'=>'_STOCK_STATUS_ID_','shipping'=>'_SHIPPING_','tax_class_id'=>'_TAX_CLASS_ID_','sort_order'=>'_SORT_ORDER_','date_available'=>'_DATE_AVAILABLE_',
            'weight'=>'_WEIGHT_','weight_class_id'=>'_WEIGHT_CLASS_ID_','length'=>'_LENGTH_','width'=>'_WIDTH_','height'=>'_HEIGHT_','length_class_id'=>'_LENGTH_CLASS_ID_',
            'name'=>'_NAME_','description'=>'_DESCRIPTION_','tag'=>'_TAG_','meta_title'=>'_META_TITLE_','meta_description'=>'_META_DESCRIPTION_','meta_h1'=>'_META_H1_',
            'meta_keywords'=>'_META_KEYWORD_','meta_keyword'=>'_META_KEYWORD_','manufacturer'=>'_MANUFACTURER_','manufacturer_id'=>'_MANUFACTURER_ID_',
            'categories'=>'_CATEGORY_','category_ids'=>'_CATEGORY_IDS_','main_category_id'=>'_MAIN_CATEGORY_ID_','image'=>'_IMAGE_','additional_images'=>'_IMAGES_',
            'attributes'=>'_ATTRIBUTES_','specials'=>'_SPECIALS_','discounts'=>'_DISCOUNTS_','rewards'=>'_REWARDS_','recurring'=>'_RECURRING_',
            'options'=>'_OPTIONS_','seo_keyword'=>'_SEO_KEYWORD_','product_url'=>'_SEO_KEYWORD_','store_ids'=>'_STORE_IDS_','filter_ids'=>'_FILTER_IDS_',
            'download_ids'=>'_DOWNLOAD_IDS_','related_product_ids'=>'_RELATED_PRODUCT_IDS_','related_article_ids'=>'_RELATED_ARTICLE_IDS_','layouts'=>'_LAYOUTS_',
            'noindex'=>'_NOINDEX_','google_product_category'=>'_GOOGLE_PRODUCT_CATEGORY_','supplier_price'=>'_SUPPLIER_PRICE_','supplier_quantity'=>'_SUPPLIER_QUANTITY_',
            'external_product_id'=>'_EXTERNAL_PRODUCT_ID_','supplier_sku'=>'_SUPPLIER_SKU_'
        );
        $lower = strtolower($target);
        if (isset($map[$lower])) { return $map[$lower]; }
        if (preg_match('/^_[A-Z0-9_=\-]+_$/', strtoupper($target))) { return strtoupper($target); }
        return '';
    }

}
?>
