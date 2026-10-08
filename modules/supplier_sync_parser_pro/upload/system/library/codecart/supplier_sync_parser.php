<?php
class CodecartSupplierSyncParser {
    protected $registry;
    protected $db;
    protected $config;
    protected $description_h1_exists;

    public function __construct($registry) {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');
    }

    protected function isQueueStopped() {
        if ((int)$this->config->get('module_supplier_sync_parser_pro_stop_queue')) {
            return true;
        }
        $q = $this->db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `key` = 'module_supplier_sync_parser_pro_stop_queue' LIMIT 1");
        return ($q->num_rows && (int)$q->row['value'] === 1);
    }

    public function buildQueue($supplier_id, $mode = 'scan', $limit_pages = 200) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok' => false, 'created' => 0, 'message' => 'Module is disabled');
        }
        if ($this->isQueueStopped()) {
            return array('ok' => false, 'created' => 0, 'message' => 'Queue is stopped manually');
        }
        $supplier_id = (int)$supplier_id;
        $supplier = $this->getSupplier($supplier_id);
        if (!$supplier) {
            return array('ok' => false, 'message' => 'Supplier not found');
        }

        $validation = $this->validateSupplierForMassRun($supplier, $mode);
        if (!$validation['ok']) {
            return $validation;
        }

        $created = 0;
        if ($mode === 'feed_file') {
            $this->clearQueueJobsForNewSource($supplier_id);
            $feed = $this->parseSupplierFeedItems($supplier, (int)$limit_pages);
            if (empty($feed['ok'])) {
                $this->addLog($supplier_id, 'error', 'Feed import failed', isset($feed['message']) ? $feed['message'] : 'Unknown feed error', '');
                return array('ok' => false, 'created' => 0, 'message' => isset($feed['message']) ? $feed['message'] : 'Unknown feed error');
            }
            foreach ($feed['items'] as $item) {
                if ($this->isQueueStopped()) {
                    return array('ok' => false, 'created' => $created, 'message' => 'Queue is stopped manually');
                }
                $url = !empty($item['url']) ? (string)$item['url'] : ('feed://' . $supplier_id . '/' . substr(sha1(json_encode($item)), 0, 24));
                if ($this->addQueueJob($supplier_id, 'import_item', $url, array('mode' => 'feed_file', 'parsed_data' => $item))) {
                    $created++;
                }
            }
            return array('ok' => true, 'created' => $created, 'message' => 'Feed item queue created');
        }
        $list_urls = $this->splitLines($supplier['list_urls']);

        if (in_array($mode, array('scan', 'scan_site', 'sitemap', 'new_only'), true)) {
            $this->clearQueueJobsForNewSource($supplier_id);
        }

        if ($mode === 'linked_products') {
            $links = $this->db->query("SELECT supplier_product_url FROM `" . DB_PREFIX . "ccp_ssp_product_link` WHERE supplier_id = '" . (int)$supplier_id . "' AND product_id > 0 AND supplier_product_url <> '' ORDER BY last_checked ASC");
            foreach ($links->rows as $link) {
                if ($this->addQueueJob($supplier_id, 'check_product', $link['supplier_product_url'], array('mode' => 'linked_products'), false)) {
                    $created++;
                }
            }
            return array('ok' => true, 'created' => $created, 'message' => 'Linked product check queue created');
        }

        if ($mode === 'scan_site') {
            $list_urls = $this->prepareSiteScanStartUrls($supplier, $list_urls);
            $count = 0;
            foreach ($list_urls as $url) {
                if ($count >= (int)$limit_pages) {
                    break;
                }
                $url = $this->normalizeUrl($url, $supplier['base_url']);
                if (!$this->isSupplierHtmlUrlAllowed($url, $supplier)) {
                    $this->addLog($supplier_id, 'warning', 'URL skipped by domain policy', $url, $url);
                    continue;
                }
                if ($url && $this->addQueueJob($supplier_id, 'scan_list', $url, array('depth' => 0, 'mode' => $mode, 'crawler' => 1))) {
                    $created++;
                    $count++;
                }
            }
            return array('ok' => true, 'created' => $created, 'message' => 'Site scan queue created');
        }

        if ($mode === 'sitemap') {
            $urls = $this->discoverSitemapProductUrls($supplier, (int)$limit_pages);
            foreach ($urls as $url) {
                if ($this->addQueueJob($supplier_id, 'check_product', $url, array('mode' => 'sitemap'))) {
                    $created++;
                }
            }
            if (!$created) {
                $this->addLog($supplier_id, 'warning', 'Sitemap scan found no product URLs', 'Check sitemap availability or use category scan.', isset($supplier['base_url']) ? $supplier['base_url'] : '');
            }
            return array('ok' => true, 'created' => $created, 'message' => 'Sitemap product queue created');
        }

        if ($mode === 'product_urls') {
            foreach ($list_urls as $url) {
                $url = $this->normalizeUrl($url, $supplier['base_url']);
                if (!$this->isSupplierHtmlUrlAllowed($url, $supplier)) {
                    $this->addLog($supplier_id, 'warning', 'URL skipped by domain policy', $url, $url);
                    continue;
                }
                if ($url && $this->addQueueJob($supplier_id, 'check_product', $url, array('mode' => $mode))) {
                    $created++;
                }
            }
            return array('ok' => true, 'created' => $created, 'message' => 'Product URL queue created');
        }

        $count = 0;
        foreach ($list_urls as $url) {
            if ($count >= (int)$limit_pages) {
                break;
            }
            $url = $this->normalizeUrl($url, $supplier['base_url']);
            if (!$this->isSupplierHtmlUrlAllowed($url, $supplier)) {
                $this->addLog($supplier_id, 'warning', 'URL skipped by domain or language policy', $url, $url);
                continue;
            }
            if ($this->looksLikeProductUrl($url)) {
                $this->addLog($supplier_id, 'warning', 'Category scan skipped product URL', 'Use direct product URL mode for this link: ' . $url, $url);
                continue;
            }
            if ($url && $this->addQueueJob($supplier_id, 'scan_list', $url, array('depth' => 0, 'mode' => $mode))) {
                $created++;
                $count++;
            }
        }

        return array('ok' => true, 'created' => $created, 'message' => 'List scan queue created');
    }


    protected function clearQueueJobsForNewSource($supplier_id) {
        $supplier_id = (int)$supplier_id;
        if ($supplier_id <= 0) {
            return;
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_queue` WHERE supplier_id = '" . (int)$supplier_id . "'");
    }

    protected function prepareSiteScanStartUrls($supplier, $list_urls) {
        $base_url = isset($supplier['base_url']) ? trim((string)$supplier['base_url']) : '';
        $starts = array();
        foreach ((array)$list_urls as $url) {
            $url = $this->normalizeUrl($url, $base_url);
            if (!$url || !$this->isSupplierHtmlUrlAllowed($url, $supplier)) {
                continue;
            }
            if ($this->looksLikeProductUrl($url)) {
                continue;
            }
            $starts[$url] = $url;
        }
        if ($base_url !== '') {
            $root = $this->siteRootUrl($base_url);
            if ($root !== '') {
                $starts = array($root => $root) + $starts;
                foreach ($this->commonCatalogStartUrls($root) as $candidate) {
                    if ($this->isSupplierHtmlUrlAllowed($candidate, $supplier)) {
                        $starts[$candidate] = $candidate;
                    }
                }
            }
        }
        return array_values($starts);
    }

    protected function commonCatalogStartUrls($root) {
        $root = rtrim((string)$root, '/') . '/';
        $paths = array(
            'product_list', 'ua/product_list', 'ru/product_list', 'en/product_list',
            'products', 'ua/products', 'catalog', 'ua/catalog', 'category', 'ua/category',
            'shop', 'ua/shop', 'tovary', 'ua/tovary', 'katalog', 'ua/katalog'
        );
        $urls = array();
        foreach ($paths as $path) {
            $urls[] = $root . $path;
        }
        return $urls;
    }


    protected function siteRootUrl($url) {
        $parts = parse_url((string)$url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }
        return strtolower($parts['scheme']) . '://' . $parts['host'] . '/';
    }

    protected function validateSupplierForMassRun($supplier, $mode) {
        if (!empty($supplier['require_test_success']) && empty($supplier['last_test_ok']) && $mode !== 'feed_file') {
            return array('ok' => false, 'message' => 'Mass import is blocked until supplier test succeeds');
        }
        if ($mode === 'feed_file') {
            if ((isset($supplier['source_type']) ? (string)$supplier['source_type'] : 'html') !== 'feed') {
                return array('ok' => false, 'message' => 'Supplier source type must be File/XML/CSV for this mode');
            }
            if (trim((string)(isset($supplier['feed_url']) ? $supplier['feed_url'] : '')) === '' && trim((string)(isset($supplier['feed_file']) ? $supplier['feed_file'] : '')) === '') {
                return array('ok' => false, 'message' => 'Feed URL or uploaded feed file is required');
            }
            if (trim((string)(isset($supplier['feed_name_path']) ? $supplier['feed_name_path'] : '')) === '') {
                $this->addLog((int)$supplier['supplier_id'], 'warning', 'Feed name mapping is empty', 'The engine will try common aliases: name, title, model.', '');
            }
            if (trim((string)(isset($supplier['feed_price_path']) ? $supplier['feed_price_path'] : '')) === '') {
                $this->addLog((int)$supplier['supplier_id'], 'warning', 'Feed price mapping is empty', 'The engine will try common aliases: price, cost, cena.', '');
            }
            $currency = strtoupper(trim((string)(isset($supplier['currency_code']) ? $supplier['currency_code'] : '')));
            if ($currency !== '' && !$this->isSupplierCurrencyValid($currency)) {
                return array('ok' => false, 'message' => 'Supplier currency is not active in OpenCart: ' . $currency);
            }
            return array('ok' => true, 'message' => 'OK');
        }
        if (empty($supplier['name_xpath'])) {
            return array('ok' => false, 'message' => 'Required XPath is missing: product name');
        }
        if (empty($supplier['price_xpath'])) {
            return array('ok' => false, 'message' => 'Required XPath is missing: product price');
        }
        if ($mode !== 'linked_products' && $mode !== 'product_urls' && $mode !== 'scan_site' && $mode !== 'sitemap' && empty($supplier['product_url_xpath'])) {
            return array('ok' => false, 'message' => 'Required XPath is missing: product URL on list page');
        }
        if ($mode !== 'linked_products' && $mode !== 'scan_site' && $mode !== 'sitemap' && trim((string)$supplier['list_urls']) === '') {
            return array('ok' => false, 'message' => 'Supplier list URLs are empty');
        }
        if ($mode === 'scan_site' && trim((string)$supplier['list_urls']) === '' && trim((string)$supplier['base_url']) === '') {
            return array('ok' => false, 'message' => 'Supplier list URLs are empty');
        }
        if ((isset($supplier['missing_policy']) && $supplier['missing_policy'] === 'out_of_stock') && (int)$supplier['default_stock_status_id'] <= 0) {
            return array('ok' => false, 'message' => 'Missing product policy requires default stock status');
        }
        $currency = strtoupper(trim((string)(isset($supplier['currency_code']) ? $supplier['currency_code'] : '')));
        if ($currency !== '' && !$this->isSupplierCurrencyValid($currency)) {
            return array('ok' => false, 'message' => 'Supplier currency is not active in OpenCart: ' . $currency);
        }
        if (empty($supplier['sku_xpath']) && empty($supplier['ean_xpath']) && empty($supplier['upc_xpath']) && empty($supplier['mpn_xpath'])) {
            $this->addLog((int)$supplier['supplier_id'], 'warning', 'Supplier unique key warning', 'No SKU/EAN/UPC/MPN XPath configured. Matching by title is unsafe and should be manual.', '');
        }
        return array('ok' => true, 'message' => 'OK');
    }

    protected function queueMutexName() {
        return 'ccp_ssp_' . substr(sha1(DB_PREFIX . (defined('DB_DATABASE') ? DB_DATABASE : '')), 0, 32);
    }

    public function processQueueBatch($supplier_id = 0, $limit = 20, $source = 'manual', $action_filter = '', $queue_ids = array()) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) { return array('ok'=>false,'message'=>'Module is disabled','processed'=>0,'updated'=>0,'created'=>0,'errors'=>0); }
        $mutex = $this->queueMutexName();
        $lock = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($mutex) . "', 0) AS acquired");
        if (empty($lock->row['acquired'])) { return array('ok'=>false,'message'=>'Queue is already processing','processed'=>0,'updated'=>0,'created'=>0,'errors'=>0); }
        try {
            return $this->processQueueBatchUnlocked($supplier_id, $limit, $source, $action_filter, $queue_ids);
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($mutex) . "')");
        }
    }

    public function runCronCycle($supplier_id = 0, $limit = 20) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) { return array('ok'=>false,'message'=>'Module is disabled'); }
        if ($this->isQueueStopped()) { return array('ok'=>false,'message'=>'Queue is stopped manually'); }
        $mutex = $this->queueMutexName();
        $lock = $this->db->query("SELECT GET_LOCK('" . $this->db->escape($mutex) . "', 0) AS acquired");
        if (empty($lock->row['acquired'])) { return array('ok'=>false,'message'=>'Queue is already processing'); }
        try {
            $where = (int)$supplier_id > 0 ? " AND supplier_id = '" . (int)$supplier_id . "'" : '';
            $profiles = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_supplier` WHERE status = '1'" . $where . " ORDER BY supplier_id ASC");
            foreach ($profiles->rows as $profile) {
                $settings = $this->jsonDecode($profile['settings']);
                if (empty($settings['cron_enabled'])) { continue; }
                $id = (int)$profile['supplier_id'];
                $active = $this->db->query("SELECT lock_name FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name IN ('queue_0','queue_" . $id . "') AND expires_at > NOW() LIMIT 1");
                if ($active->num_rows) { continue; }
                // After an interrupted worker, reparse an item; product links keep retries idempotent.
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_queue` SET status = 'pending', date_modified = NOW() WHERE supplier_id = '" . $id . "' AND status = 'processing'");
                $stats = $this->getQueueStats($id);
                if (!$stats['pending']) {
                    $key = 'ccp_ssp_cron_started_' . $id;
                    $last = $this->db->query("SELECT value FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'supplier_sync_parser_pro_runtime' AND `key` = '" . $key . "' LIMIT 1");
                    $interval = max(5, min(10080, isset($settings['cron_interval_minutes']) ? (int)$settings['cron_interval_minutes'] : 60)) * 60;
                    if ($last->num_rows && (int)$last->row['value'] + $interval > time()) { continue; }
                    $queue = $this->buildQueue($id, 'linked_products');
                    if (empty($queue['ok'])) { continue; }
                    $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND code = 'supplier_sync_parser_pro_runtime' AND `key` = '" . $key . "'");
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', code = 'supplier_sync_parser_pro_runtime', `key` = '" . $key . "', value = '" . time() . "', serialized = '0'");
                    if (empty($queue['created'])) { continue; }
                }
                return $this->processQueueBatchUnlocked($id, $limit, 'cron');
            }
            return array('ok'=>true,'processed'=>0,'updated'=>0,'message'=>'No scheduled supplier work is due');
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . $this->db->escape($mutex) . "')");
        }
    }

    protected function processQueueBatchUnlocked($supplier_id = 0, $limit = 20, $source = 'manual', $action_filter = '', $queue_ids = array()) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $supplier_id = (int)$supplier_id;
        $limit = max(1, min(100, (int)$limit));
        if ($this->isQueueStopped()) {
            return array('ok' => false, 'processed' => 0, 'done' => 0, 'created' => 0, 'updated' => 0, 'preview' => 0, 'errors' => 0, 'skipped' => 0, 'message' => 'Queue is stopped manually', 'stats' => $this->getQueueStats($supplier_id));
        }
        $lock = $this->acquireProcessLock($supplier_id, $source, 360);
        if (!$lock['ok']) {
            return array('ok' => false, 'processed' => 0, 'done' => 0, 'created' => 0, 'updated' => 0, 'preview' => 0, 'errors' => 0, 'skipped' => 0, 'message' => $lock['message'], 'stats' => $this->getQueueStats($supplier_id));
        }
        $where = "status = 'pending'";
        $action_filter = in_array((string)$action_filter, array('scan_list','check_product','import_item'), true) ? (string)$action_filter : '';
        $selected_queue_ids = array();
        foreach ((array)$queue_ids as $queue_id) {
            $queue_id = (int)$queue_id;
            if ($queue_id > 0) {
                $selected_queue_ids[$queue_id] = $queue_id;
            }
        }
        if ($supplier_id > 0) {
            $where .= " AND supplier_id = '" . $supplier_id . "'";
        }
        if ($action_filter !== '') {
            $where .= " AND action = '" . $this->db->escape($action_filter) . "'";
        }
        if ($selected_queue_ids) {
            $where .= " AND queue_id IN (" . implode(',', $selected_queue_ids) . ")";
            $limit = min(100, max($limit, count($selected_queue_ids)));
        }

        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_queue` WHERE " . $where . " ORDER BY queue_id ASC LIMIT " . (int)$limit);
        $result = array('processed' => 0, 'selected' => count($selected_queue_ids), 'done' => 0, 'created' => 0, 'updated' => 0, 'price_updated' => 0, 'stock_updated' => 0, 'no_change' => 0, 'warnings' => 0, 'duplicates' => 0, 'excluded' => 0, 'preview' => 0, 'found_urls' => 0, 'found_list_pages' => 0, 'errors' => 0, 'skipped' => 0, 'messages' => array());

        foreach ($query->rows as $job) {
            if (!$this->refreshProcessLock($supplier_id, isset($lock['owner']) ? $lock['owner'] : '', 360)) {
                $result['message'] = 'Queue lock was lost; processing stopped to prevent concurrent updates';
                $result['errors']++;
                break;
            }
            if ($this->isQueueStopped()) {
                $result['message'] = 'Queue was stopped manually';
                break;
            }
            $result['processed']++;
            $queue_id = (int)$job['queue_id'];
            $this->setQueueStatus($queue_id, 'processing', '');
            $supplier = $this->getSupplier((int)$job['supplier_id']);

            if (!$supplier || !(int)$supplier['status']) {
                $this->setQueueStatus($queue_id, 'skipped', 'Supplier disabled or not found');
                $result['skipped']++;
                continue;
            }

            try {
                if ($job['action'] === 'scan_list') {
                    $payload = $this->jsonDecode($job['payload']);
                    $scan = $this->parseListPage($supplier, $job['supplier_product_url']);
                    if (!$scan['ok']) {
                        $this->setQueueStatus($queue_id, 'error', $scan['message']);
                        $result['errors']++;
                        $this->addLog((int)$supplier['supplier_id'], 'error', 'List scan failed', $scan['message'], $job['supplier_product_url']);
                        continue;
                    }

                    $added = 0;
                    $items = !empty($scan['product_items']) ? $scan['product_items'] : array();
                    if (!$items && !empty($scan['product_urls'])) {
                        foreach ($scan['product_urls'] as $product_url) {
                            $items[] = array('url' => $product_url, 'supplier_category' => '');
                        }
                    }
                    foreach ($items as $item) {
                        if ($this->isQueueStopped()) {
                            $result['message'] = 'Queue was stopped manually';
                            break;
                        }
                        $product_url = isset($item['url']) ? $item['url'] : '';
                        if (!$this->isSupplierHtmlUrlAllowed($product_url, $supplier)) {
                            $this->addLog((int)$supplier['supplier_id'], 'warning', 'Product URL skipped by domain policy', $product_url, $product_url);
                            continue;
                        }
                        $item_payload = array();
                        if (!empty($payload['mode'])) { $item_payload['mode'] = $payload['mode']; }
                        if (!empty($item['supplier_category'])) {
                            $item_payload['supplier_category'] = $item['supplier_category'];
                        }
                        if (!empty($item['title'])) {
                            $item_payload['title'] = $this->cleanText($item['title']);
                        }
                        if ($this->addQueueJob((int)$supplier['supplier_id'], 'check_product', $product_url, $item_payload)) {
                            $added++;
                        }
                    }
                    if ($this->isQueueStopped()) {
                        $this->setQueueStatus($queue_id, 'pending', 'Queue was stopped manually');
                        $result['message'] = 'Queue was stopped manually';
                        break;
                    }

                    $current_depth = isset($payload['depth']) ? (int)$payload['depth'] : 0;
                    $scan_mode = isset($payload['mode']) ? $payload['mode'] : 'scan';
                    $is_crawler = !empty($payload['crawler']);
                    $max_depth = ($scan_mode === 'scan_site') ? 120 : 40;
                    if (!empty($scan['next_page_url']) && $current_depth < $max_depth && $this->isSupplierHtmlUrlAllowed($scan['next_page_url'], $supplier)) {
                        $this->addQueueJob((int)$supplier['supplier_id'], 'scan_list', $scan['next_page_url'], array('depth' => ($current_depth + 1), 'mode' => $scan_mode, 'crawler' => $is_crawler ? 1 : 0));
                    }
                    $added_lists = 0;
                    $follow_list_depth = $is_crawler ? 12 : 40;
                    $follow_list_limit = $is_crawler ? 80 : 60;
                    if ($current_depth < $follow_list_depth && !empty($scan['list_urls'])) {
                        $followed_on_page = 0;
                        foreach ($scan['list_urls'] as $list_url) {
                            if ($this->isQueueStopped()) {
                                $result['message'] = 'Queue was stopped manually';
                                break;
                            }
                            if ($followed_on_page >= $follow_list_limit) {
                                break;
                            }
                            if (!$this->isSupplierHtmlUrlAllowed($list_url, $supplier)) {
                                continue;
                            }
                            if (!$is_crawler && !$this->isSameListSectionUrl($job['supplier_product_url'], $list_url)) {
                                continue;
                            }
                            if ($this->addQueueJob((int)$supplier['supplier_id'], 'scan_list', $list_url, array('depth' => ($current_depth + 1), 'mode' => $scan_mode, 'crawler' => $is_crawler ? 1 : 0))) {
                                $added_lists++;
                                $followed_on_page++;
                            }
                        }
                    }
                    if ($this->isQueueStopped()) {
                        $this->setQueueStatus($queue_id, 'pending', 'Queue was stopped manually');
                        $result['message'] = 'Queue was stopped manually';
                        break;
                    }

                    $this->setQueueStatus($queue_id, 'done', 'Added product jobs: ' . $added . ', list jobs: ' . $added_lists);
                    $result['found_urls'] += (int)$added;
                    $result['found_list_pages'] += (int)$added_lists;
                    $result['done']++;
                    continue;
                }

                if ($job['action'] === 'check_product') {
                    $job_payload = $this->jsonDecode($job['payload']);
                    if (!$this->isSupplierHtmlUrlAllowed($job['supplier_product_url'], $supplier) || $this->isBlockedNonCatalogUrl($job['supplier_product_url'])) {
                        $this->setQueueStatus($queue_id, 'skipped', 'URL is not a supplier product page');
                        $result['skipped']++;
                        continue;
                    }
                    $parsed = $this->parseProduct($supplier, $job['supplier_product_url'], is_array($job_payload) ? $job_payload : array());
                    if ($this->isQueueStopped()) {
                        $this->setQueueStatus($queue_id, 'pending', 'Queue was stopped manually');
                        $result['message'] = 'Queue was stopped manually';
                        break;
                    }
                    if (!$parsed['ok']) {
                        $this->setQueueStatus($queue_id, 'error', $parsed['message']);
                        $result['errors']++;
                        if (!empty($parsed['missing_confirmed'])) {
                            $this->handleMissingSupplierProduct((int)$supplier['supplier_id'], $job['supplier_product_url'], $supplier, $parsed['message']);
                        }
                        $this->saveProductLink((int)$supplier['supplier_id'], 0, $job['supplier_product_url'], '', '', 0, 0, '', 0, 'error', $parsed['message']);
                        $this->addLog((int)$supplier['supplier_id'], 'error', 'Product parse failed', $parsed['message'], $job['supplier_product_url']);
                        continue;
                    }

                    $data = $parsed['data'];
                    $match = $this->findProduct((int)$supplier['supplier_id'], $data['sku'], $job['supplier_product_url'], $data['name'], isset($data['ean']) ? $data['ean'] : '', isset($data['upc']) ? $data['upc'] : '', isset($data['mpn']) ? $data['mpn'] : '', $supplier, $data);
                    if (empty($match['product_id'])) {
                        $data = $this->addMatchCandidatesToParsedData((int)$supplier['supplier_id'], $job['supplier_product_url'], $data);
                        if (!empty($data['match_candidates']) && empty($match['source'])) {
                            $match = $this->prepareNoAutoMatch('candidate_name_90_manual');
                        }
                    }
                    if (!empty($job_payload['mode']) && $job_payload['mode'] === 'new_only' && !empty($match['product_id'])) {
                        $this->setQueueStatus($queue_id, 'skipped', 'Existing linked product skipped in new-only scan');
                        $result['skipped']++;
                        continue;
                    }
                    $review = $this->saveReviewProduct((int)$supplier['supplier_id'], $job['supplier_product_url'], $data, $match, 'pending');
                    $result['preview']++;
                    if (isset($review['status']) && $review['status'] === 'price_warning') { $result['warnings']++; }
                    if (isset($review['status']) && $review['status'] === 'excluded') { $result['excluded']++; }

                    $this->applyScheduledReview($source, $review, $match, $supplier, $result);
                    $message = !empty($match['product_id']) ? 'Preview saved for existing product' : 'Preview saved for new product';
                    $this->setQueueStatus($queue_id, 'done', $message);
                    $result['done']++;
                    if (empty($match['product_id']) && empty($data['category_allowed'])) {
                        $result['excluded']++;
                    }
                    continue;
                }

                if ($job['action'] === 'import_item') {
                    $job_payload = $this->jsonDecode($job['payload']);
                    $data = isset($job_payload['parsed_data']) && is_array($job_payload['parsed_data']) ? $job_payload['parsed_data'] : array();
                    if (empty($data['name']) || empty($data['sale_price'])) {
                        $this->setQueueStatus($queue_id, 'error', 'Feed item has no valid name or calculated price');
                        $result['errors']++;
                        continue;
                    }
                    $url = !empty($data['url']) ? (string)$data['url'] : $job['supplier_product_url'];
                    $match = $this->findProduct((int)$supplier['supplier_id'], isset($data['sku']) ? $data['sku'] : '', $url, isset($data['name']) ? $data['name'] : '', isset($data['ean']) ? $data['ean'] : '', isset($data['upc']) ? $data['upc'] : '', isset($data['mpn']) ? $data['mpn'] : '', $supplier, $data);
                    if (empty($match['product_id'])) {
                        $data = $this->addMatchCandidatesToParsedData((int)$supplier['supplier_id'], $url, $data);
                        if (!empty($data['match_candidates']) && empty($match['source'])) {
                            $match = $this->prepareNoAutoMatch('candidate_name_90_manual');
                        }
                    }
                    $review = $this->saveReviewProduct((int)$supplier['supplier_id'], $url, $data, $match, 'pending');
                    $this->applyScheduledReview($source, $review, $match, $supplier, $result);
                    $result['preview']++;
                    if (isset($review['status']) && $review['status'] === 'price_warning') { $result['warnings']++; }
                    if (isset($review['status']) && $review['status'] === 'excluded') { $result['excluded']++; }
                    $message = !empty($match['product_id']) ? 'Feed preview saved for existing product' : 'Feed preview saved for new product';
                    $this->setQueueStatus($queue_id, 'done', $message);
                    $result['done']++;
                    if (empty($match['product_id']) && empty($data['category_allowed'])) {
                        $result['excluded']++;
                    }
                    continue;
                }

                $this->setQueueStatus($queue_id, 'skipped', 'Unknown action');
                $result['skipped']++;
            } catch (Exception $e) {
                $this->setQueueStatus($queue_id, 'error', $e->getMessage());
                $result['errors']++;
                $this->addLog((int)$job['supplier_id'], 'error', 'Exception', $e->getMessage(), $job['supplier_product_url']);
            } catch (Throwable $e) {
                $this->setQueueStatus($queue_id, 'error', $e->getMessage());
                $result['errors']++;
                $this->addLog((int)$job['supplier_id'], 'error', 'Runtime error', $e->getMessage(), $job['supplier_product_url']);
            }

            if ((int)$supplier['request_delay_ms'] > 0) {
                usleep(min(3000000, (int)$supplier['request_delay_ms'] * 1000));
            }
        }

        $result['ok'] = true;
        $result['stats'] = $this->getQueueStats($supplier_id);
        $this->saveRunSummary($supplier_id, $result, $source);
        $this->releaseProcessLock($supplier_id, isset($lock['owner']) ? $lock['owner'] : '');
        return $result;
    }

    protected function applyScheduledReview($source, $review, $match, $supplier, &$result) {
        if ($source !== 'cron' || empty($supplier['auto_apply_existing']) || empty($match['product_id']) || $review['status'] !== 'pending') { return; }
        $strong_sources = array('supplier_url_link','supplier_sku_link','product_sku','product_model','product_ean','product_upc','product_jan','product_isbn','product_mpn');
        if (!in_array(isset($match['source']) ? $match['source'] : '', $strong_sources, true)) { return; }
        $price = !empty($supplier['update_price']);
        $stock = !empty($supplier['update_stock']);
        if (!$price && !$stock) { return; }
        $mode = $price && $stock ? 'update_price_stock' : ($price ? 'update_price' : 'update_stock');
        $applied = $this->applySelectedReviews(array((int)$review['review_id']), $mode, (int)$supplier['supplier_id']);
        foreach (array('updated','price_updated','stock_updated','no_change','errors') as $key) {
            $result[$key] += isset($applied[$key]) ? (int)$applied[$key] : 0;
        }
    }

    public function testProductUrl($supplier_id, $url) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $supplier = $this->getSupplier((int)$supplier_id);
        if (!$supplier) {
            return array('ok' => false, 'message' => 'Supplier not found');
        }
        $parsed = $this->parseProduct($supplier, $url);
        if ($parsed['ok']) {
            $match = $this->findProduct((int)$supplier['supplier_id'], $parsed['data']['sku'], $url, $parsed['data']['name'], isset($parsed['data']['ean']) ? $parsed['data']['ean'] : '', isset($parsed['data']['upc']) ? $parsed['data']['upc'] : '', isset($parsed['data']['mpn']) ? $parsed['data']['mpn'] : '', $supplier, $parsed['data']);
            if (empty($match['product_id'])) {
                $parsed['data'] = $this->addMatchCandidatesToParsedData((int)$supplier['supplier_id'], $url, $parsed['data']);
                if (!empty($parsed['data']['match_candidates']) && empty($match['source'])) {
                    $match = $this->prepareNoAutoMatch('candidate_name_90_manual');
                }
            }
            $parsed['match_candidates'] = !empty($parsed['data']['match_candidates']) ? $parsed['data']['match_candidates'] : array();
            $parsed['match'] = $this->prepareMatchPreview($match);
            $parsed['xpath_report'] = $this->buildXPathReport($parsed['data']);
            $this->markSupplierTest((int)$supplier['supplier_id'], 1);
        } else {
            $this->markSupplierTest((int)$supplier['supplier_id'], 0);
        }
        return $parsed;
    }



    public function autoDetectRules($supplier_id, $url) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $supplier = $this->getSupplier((int)$supplier_id);
        if (!$supplier) {
            $supplier = array('user_agent' => '', 'base_url' => '');
        }
        $url = trim((string)$url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return array('ok' => false, 'message' => 'Invalid product URL');
        }
        if (!empty($supplier['supplier_id']) && !$this->isSupplierHtmlUrlAllowed($url, $supplier)) {
            return array('ok' => false, 'message' => 'URL is outside supplier domain: ' . $url);
        }
        $fetch = $this->fetchUrl($url, $supplier);
        if (empty($fetch['ok'])) {
            return $fetch;
        }
        $xpath = $this->createXPath($fetch['body']);
        if (!$xpath) {
            return array('ok' => false, 'message' => 'Cannot parse HTML');
        }
        if (($this->looksLikeCategoryUrl($url) || $this->looksLikeListUrl($url)) && !$this->looksLikeProductUrl($url)) {
            return array(
                'ok' => false,
                'message' => 'This URL looks like a category/list page. Paste a real product card URL for product rule detection; use list rule detection for category pages.',
                'url' => $url
            );
        }
        $product_name_hint = $this->cleanImportedProductName($this->cleanText($this->xpathFirst($xpath, "//h1")));
        if ($product_name_hint === '') {
            $product_name_hint = $this->cleanImportedProductName($this->cleanText($this->xpathFirst($xpath, "//meta[@property='og:title']/@content")));
        }

        $fields = $this->getAutoDetectFieldDefinitions();
        $detected = array();
        foreach ($fields as $field => $meta) {
            $detected[$field] = array(
                'label' => $meta['label'],
                'target' => $meta['target'],
                'required' => !empty($meta['required']),
                'candidates' => array()
            );
            foreach ($meta['candidates'] as $candidate) {
                $value = $this->autoDetectValue($xpath, $candidate, $url);
                if ($value === '') {
                    continue;
                }
                if ($field === 'price_xpath' && $this->extractPrice($value) <= 0) {
                    continue;
                }
                if ($field === 'image_xpath' || $field === 'additional_images_xpath') {
                    $value = $this->normalizeUrl($value, $url);
                }
                $display_value = $this->autoDetectDisplayValue($field, $value);
                if ($field === 'category_xpath') {
                    $display_value = $this->normalizeSupplierCategoryTextForProduct($display_value, $product_name_hint, '', '');
                }
                if ($display_value === '') {
                    continue;
                }
                if ($field === 'price_xpath' && (float)$display_value <= 0) {
                    continue;
                }
                if (in_array($field, array('sku_xpath','ean_xpath','upc_xpath','mpn_xpath'), true) && $display_value === '') {
                    continue;
                }
                $detected[$field]['candidates'][] = array(
                    'xpath' => $candidate['xpath'],
                    'attr' => isset($candidate['attr']) ? $candidate['attr'] : '',
                    'value' => $this->shortenAutoValue($display_value),
                    'source' => $candidate['source'],
                    'confidence' => (int)$candidate['confidence'],
                    'confidence_label' => $this->confidenceLabel((int)$candidate['confidence'])
                );
            }
            usort($detected[$field]['candidates'], array($this, 'sortAutoCandidates'));
            if (count($detected[$field]['candidates']) > 6) {
                $detected[$field]['candidates'] = array_slice($detected[$field]['candidates'], 0, 6);
            }
        }
        return array(
            'ok' => true,
            'message' => 'Auto-detection completed. Review candidates before saving rules.',
            'url' => $url,
            'fields' => $detected
        );
    }

    public function autoDetectListRules($supplier_id, $url) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $supplier = $this->getSupplier((int)$supplier_id);
        if (!$supplier) {
            $supplier = array('user_agent' => '', 'base_url' => '');
        }
        $url = trim((string)$url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return array('ok' => false, 'message' => 'Invalid URL');
        }
        if ($this->looksLikeProductUrl($url)) {
            return array('ok' => false, 'message' => 'This URL looks like a product card. Paste a category or product list URL for list link detection.');
        }
        $fetch = $this->fetchUrl($url, $supplier);
        if (empty($fetch['ok'])) {
            return $fetch;
        }
        $xpath = $this->createXPath($fetch['body']);
        if (!$xpath) {
            return array('ok' => false, 'message' => 'Cannot parse HTML');
        }

        $ci = "translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $ii = "translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $candidates = array(
            array('xpath' => "//a[contains(@href,'product_id=')]", 'attr' => 'href', 'source' => 'OpenCart product links', 'confidence' => 92),
            array('xpath' => "//*[contains($ci,'product-thumb') or contains($ci,'product-item') or contains($ci,'product-layout') or contains($ci,'product-card')]//a[@href]", 'attr' => 'href', 'source' => 'Product card links', 'confidence' => 86),
            array('xpath' => "//*[contains($ci,'product') and (contains($ci,'name') or contains($ci,'title'))]//a[@href]", 'attr' => 'href', 'source' => 'Product title links', 'confidence' => 82),
            array('xpath' => "//h4/a[@href]", 'attr' => 'href', 'source' => 'Product heading links', 'confidence' => 72),
            array('xpath' => "//h3/a[@href]", 'attr' => 'href', 'source' => 'Product heading links', 'confidence' => 70),
            array('xpath' => "//*[contains($ii,'content')]//a[@href]", 'attr' => 'href', 'source' => 'Content area links', 'confidence' => 45)
        );

        $link_candidates = array();
        foreach ($candidates as $candidate) {
            $sample = $this->autoDetectManyUrls($xpath, $candidate, $url, 8);
            if (!$sample['count']) {
                continue;
            }
            $score = (int)$candidate['confidence'] + min(10, (int)$sample['count']);
            $link_candidates[] = array(
                'xpath' => $candidate['xpath'],
                'attr' => $candidate['attr'],
                'value' => implode(' | ', array_slice($sample['urls'], 0, 3)) . ($sample['count'] > 3 ? ' +' . ($sample['count'] - 3) : ''),
                'source' => $candidate['source'],
                'confidence' => $score,
                'confidence_label' => $this->confidenceLabel($score)
            );
        }
        usort($link_candidates, array($this, 'sortAutoCandidates'));
        $link_candidates = array_slice($link_candidates, 0, 6);

        $next_candidates = array();
        $next_defs = array(
            array('xpath' => "//a[@rel='next']", 'attr' => 'href', 'source' => 'Pagination next rel', 'confidence' => 86),
            array('xpath' => "//*[contains($ci,'pagination')]//a[contains(.,'>') or contains(.,'›') or contains(.,'Next') or contains(.,'Наступ') or contains(.,'След')]", 'attr' => 'href', 'source' => 'Pagination next link', 'confidence' => 62)
        );
        foreach ($next_defs as $candidate) {
            $sample = $this->autoDetectManyUrls($xpath, $candidate, $url, 2, false);
            if (!$sample['count']) {
                continue;
            }
            $next_candidates[] = array(
                'xpath' => $candidate['xpath'],
                'attr' => $candidate['attr'],
                'value' => implode(' | ', $sample['urls']),
                'source' => $candidate['source'],
                'confidence' => (int)$candidate['confidence'],
                'confidence_label' => $this->confidenceLabel((int)$candidate['confidence'])
            );
        }

        return array(
            'ok' => true,
            'message' => 'List link auto-detection completed. Review product links before saving rules.',
            'url' => $url,
            'fields' => array(
                'product_url_xpath' => array('label' => 'Product links on list page', 'target' => 'product_url_xpath', 'required' => true, 'candidates' => $link_candidates),
                'next_page_xpath' => array('label' => 'Next page link', 'target' => 'next_page_xpath', 'required' => false, 'candidates' => $next_candidates)
            )
        );
    }

    protected function getAutoDetectFieldDefinitions() {
        $ci = "translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $ii = "translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        return array(
            'name_xpath' => array('label' => 'Product name', 'target' => 'name_xpath', 'required' => true, 'candidates' => array(
                array('xpath' => "//*[@itemprop='name']", 'source' => 'schema.org itemprop=name', 'confidence' => 94),
                array('xpath' => "//h1", 'source' => 'Main H1 heading', 'confidence' => 90),
                array('xpath' => "//meta[@property='og:title']/@content", 'source' => 'OpenGraph title', 'confidence' => 82),
                array('xpath' => "//*[contains($ci,'product') and (contains($ci,'title') or contains($ci,'name'))]", 'source' => 'Product title class', 'confidence' => 76),
                array('xpath' => "//*[contains($ii,'product') and (contains($ii,'title') or contains($ii,'name'))]", 'source' => 'Product title id', 'confidence' => 72)
            )),
            'price_xpath' => array('label' => 'Price', 'target' => 'price_xpath', 'required' => true, 'candidates' => array(
                array('xpath' => "//*[contains($ci,'product-page__price')]/@data-price", 'source' => 'UniShop product price data-price', 'confidence' => 98),
                array('xpath' => "//*[contains($ci,'product-page__price')]", 'source' => 'UniShop product price block', 'confidence' => 94),
                array('xpath' => "//*[@itemprop='price']/@content", 'source' => 'schema.org itemprop=price content', 'confidence' => 96),
                array('xpath' => "//*[@itemprop='price']", 'source' => 'schema.org itemprop=price text', 'confidence' => 92),
                array('xpath' => "//meta[@property='product:price:amount']/@content", 'source' => 'product:price:amount meta', 'confidence' => 92),
                array('xpath' => "//*[contains($ci,'price') and not(contains($ci,'old')) and not(contains($ci,'regular')) and not(contains($ci,'strike'))]", 'source' => 'Visible price block', 'confidence' => 70),
                array('xpath' => "//*[contains($ii,'price') and not(contains($ii,'old')) and not(contains($ii,'regular'))]", 'source' => 'Visible price id', 'confidence' => 66)
            )),
            'stock_xpath' => array('label' => 'Stock', 'target' => 'stock_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//li[contains($ci,'product-data__item') and contains($ci,'stock')]", 'source' => 'UniShop product stock row', 'confidence' => 94),
                array('xpath' => "//*[@itemprop='availability']/@href", 'source' => 'schema.org availability href', 'confidence' => 90),
                array('xpath' => "//*[@itemprop='availability']", 'source' => 'schema.org availability text', 'confidence' => 86),
                array('xpath' => "//link[@itemprop='availability']/@href", 'source' => 'availability link', 'confidence' => 86),
                array('xpath' => "//*[contains($ci,'stock') or contains($ci,'availability') or contains($ci,'available')]", 'source' => 'Stock/availability class', 'confidence' => 68),
                array('xpath' => "//*[contains($ii,'stock') or contains($ii,'availability') or contains($ii,'available')]", 'source' => 'Stock/availability id', 'confidence' => 64)
            )),
            'sku_xpath' => array('label' => 'SKU / supplier article', 'target' => 'sku_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//li[contains($ci,'product-data__item') and contains($ci,'sku')]", 'source' => 'UniShop article/SKU row', 'confidence' => 94),
                array('xpath' => "//*[@itemprop='sku']", 'source' => 'schema.org sku', 'confidence' => 90),
                array('xpath' => "//*[contains($ci,'sku') or contains($ci,'article') or contains($ci,'code') or contains($ci,'model')]", 'source' => 'SKU/model class', 'confidence' => 64),
                array('xpath' => "//*[contains($ii,'sku') or contains($ii,'article') or contains($ii,'code') or contains($ii,'model')]", 'source' => 'SKU/model id', 'confidence' => 62)
            )),
            'model_xpath' => array('label' => 'OpenCart product code / model', 'target' => 'model_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//div[contains($ci,'rating-model__model')]", 'source' => 'UniShop product code row', 'confidence' => 98),
                array('xpath' => "//li[contains($ci,'product-data__item') and contains($ci,'sku')]", 'source' => 'UniShop article/SKU as model', 'confidence' => 92),
                array('xpath' => "//*[@itemprop='model']", 'source' => 'schema.org model', 'confidence' => 90),
                array('xpath' => "//*[@itemprop='sku']", 'source' => 'schema.org sku as model', 'confidence' => 86),
                array('xpath' => "__url_model__", 'source' => 'Model from product URL', 'confidence' => 70)
            )),
            'ean_xpath' => array('label' => 'EAN', 'target' => 'ean_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//*[@itemprop='gtin13']", 'source' => 'schema.org gtin13', 'confidence' => 92),
                array('xpath' => "//*[@itemprop='gtin']", 'source' => 'schema.org gtin', 'confidence' => 86),
                array('xpath' => "//*[contains($ci,'ean') or contains($ci,'gtin')]", 'source' => 'EAN/GTIN class', 'confidence' => 62)
            )),
            'upc_xpath' => array('label' => 'UPC', 'target' => 'upc_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//*[@itemprop='gtin12']", 'source' => 'schema.org gtin12', 'confidence' => 90),
                array('xpath' => "//*[contains($ci,'upc')]", 'source' => 'UPC class', 'confidence' => 62)
            )),
            'mpn_xpath' => array('label' => 'MPN', 'target' => 'mpn_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//*[@itemprop='mpn']", 'source' => 'schema.org mpn', 'confidence' => 90),
                array('xpath' => "//*[contains($ci,'mpn') or contains($ci,'manufacturer-code')]", 'source' => 'MPN class', 'confidence' => 62)
            )),
            'manufacturer_xpath' => array('label' => 'Manufacturer / brand', 'target' => 'manufacturer_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//li[contains($ci,'product-data__item') and contains($ci,'manufacturer')]//a", 'source' => 'UniShop product manufacturer link', 'confidence' => 96),
                array('xpath' => "//li[contains($ci,'product-data__item') and contains($ci,'manufacturer')]", 'source' => 'UniShop product manufacturer row', 'confidence' => 88),
                array('xpath' => "//*[@itemprop='brand']", 'source' => 'schema.org brand', 'confidence' => 88),
                array('xpath' => "//meta[@property='product:brand']/@content", 'source' => 'product:brand meta', 'confidence' => 84),
                array('xpath' => "//*[contains($ci,'brand') or contains($ci,'manufacturer')]", 'source' => 'Brand/manufacturer class', 'confidence' => 62)
            )),
            'category_xpath' => array('label' => 'Category / breadcrumbs', 'target' => 'category_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//ul[contains($ci,'breadcrumb')]//li[position()>1 and position()<last()]//a", 'source' => 'Breadcrumb category links', 'confidence' => 90),
                array('xpath' => "//nav[contains($ci,'breadcrumb')]//a[position()>1 and position()<last()]", 'source' => 'Breadcrumb category nav links', 'confidence' => 86),
                array('xpath' => "//*[@itemtype='https://schema.org/BreadcrumbList']", 'source' => 'schema.org BreadcrumbList', 'confidence' => 78),
                array('xpath' => "//*[@itemtype='http://schema.org/BreadcrumbList']", 'source' => 'schema.org BreadcrumbList', 'confidence' => 78),
                array('xpath' => "//*[contains($ci,'breadcrumb')]", 'source' => 'Breadcrumb class', 'confidence' => 72),
                array('xpath' => "//*[contains($ii,'breadcrumb')]", 'source' => 'Breadcrumb id', 'confidence' => 68)
            )),
            'description_xpath' => array('label' => 'Description', 'target' => 'description_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//*[@id='tab-description']/div[1]", 'source' => 'OpenCart/UniShop description content', 'confidence' => 99),
                array('xpath' => "//*[@id='tab-description']", 'source' => 'OpenCart/UniShop description tab', 'confidence' => 96),
                array('xpath' => "//*[contains($ci,'tab-description')]", 'source' => 'Description tab class', 'confidence' => 88),
                array('xpath' => "//*[@itemprop='description']", 'source' => 'schema.org description', 'confidence' => 86),
                array('xpath' => "//*[contains($ci,'product') and contains($ci,'description')]", 'source' => 'Product description class', 'confidence' => 72),
                array('xpath' => "//*[contains($ci,'description') or contains($ci,'desc')]", 'source' => 'Description class', 'confidence' => 62),
                array('xpath' => "//*[contains($ii,'description') or contains($ii,'desc')]", 'source' => 'Description id', 'confidence' => 60)
            )),
            'meta_description_xpath' => array('label' => 'Meta description', 'target' => 'meta_description_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//meta[@name='description']/@content", 'source' => 'Meta description', 'confidence' => 82)
            )),
            'meta_keyword_xpath' => array('label' => 'Meta keywords', 'target' => 'meta_keyword_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//meta[@name='keywords']/@content", 'source' => 'Meta keywords', 'confidence' => 76)
            )),
            'image_xpath' => array('label' => 'Main image', 'target' => 'image_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//img[contains($ci,'product-page__image-main-img')]/@data-full", 'source' => 'UniShop main image data-full', 'confidence' => 98),
                array('xpath' => "//img[contains($ci,'product-page__image-main-img')]/@src", 'source' => 'UniShop main image src', 'confidence' => 94),
                array('xpath' => "//meta[@property='og:image']/@content", 'source' => 'OpenGraph image', 'confidence' => 86),
                array('xpath' => "//*[@itemprop='image']/@content", 'source' => 'schema.org image content', 'confidence' => 84),
                array('xpath' => "//*[@itemprop='image']/@src", 'source' => 'schema.org image src', 'confidence' => 82),
                array('xpath' => "//img[contains($ci,'product') or contains($ci,'main') or contains($ci,'primary')]/@src", 'source' => 'Product image src', 'confidence' => 70),
                array('xpath' => "//img[contains($ci,'product') or contains($ci,'main') or contains($ci,'primary')]/@data-src", 'source' => 'Lazy product image data-src', 'confidence' => 70)
            )),
            'additional_images_xpath' => array('label' => 'Additional images', 'target' => 'additional_images_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//*[contains($ci,'product-page__image-addit')]//img/@data-full", 'source' => 'UniShop additional images data-full', 'confidence' => 78),
                array('xpath' => "//*[contains($ci,'product-page__image-addit')]//img/@src", 'source' => 'UniShop additional images src', 'confidence' => 76),
                array('xpath' => "//*[contains($ci,'gallery')]//img/@src", 'source' => 'Gallery image src', 'confidence' => 68),
                array('xpath' => "//*[contains($ci,'gallery')]//img/@data-src", 'source' => 'Gallery lazy image', 'confidence' => 68),
                array('xpath' => "//*[contains($ci,'thumb')]//img/@src", 'source' => 'Thumbnail image src', 'confidence' => 58)
            )),
            'attribute_row_xpath' => array('label' => 'Attribute rows', 'target' => 'attribute_row_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//table[contains($ci,'attribute') or contains($ci,'spec')]//tr", 'source' => 'Attribute/specification table rows', 'confidence' => 70),
                array('xpath' => "//*[contains($ci,'specification') or contains($ci,'attribute')]//tr", 'source' => 'Specification rows', 'confidence' => 62)
            )),
            'option_row_xpath' => array('label' => 'Option rows', 'target' => 'option_row_xpath', 'required' => false, 'candidates' => array(
                array('xpath' => "//*[contains($ci,'option') or contains($ci,'variant')]//option", 'source' => 'Select options/variants', 'confidence' => 60),
                array('xpath' => "//*[contains($ci,'option') or contains($ci,'variant')]", 'source' => 'Option/variant blocks', 'confidence' => 50)
            ))
        );
    }

    protected function autoDetectValue($xpath, $candidate, $base_url) {
        if (isset($candidate['xpath']) && $candidate['xpath'] === '__url_model__') {
            return $this->extractModelFromUrl($base_url);
        }
        $nodes = $xpath->query($candidate['xpath']);
        if (!$nodes || !$nodes->length) {
            return '';
        }
        $values = array();
        $max = min(4, $nodes->length);
        for ($i = 0; $i < $max; $i++) {
            $value = $this->cleanText($this->nodeValue($nodes->item($i), isset($candidate['attr']) ? $candidate['attr'] : ''));
            if ($value !== '' && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }
        return trim(implode(' | ', $values));
    }

    protected function autoDetectDisplayValue($field, $value) {
        $value = $this->cleanText($value);
        if ($field === 'price_xpath') {
            $price = $this->extractPrice($value);
            return $price > 0 ? (string)$price : '';
        }
        if ($field === 'sku_xpath') {
            return $this->extractIdentifier($value, array('артикул', 'sku', 'supplier sku', 'vendor code'));
        }
        if ($field === 'model_xpath') {
            return $this->extractIdentifier($value, array('код товару', 'код товара', 'код', 'model', 'модель', 'артикул', 'sku'));
        }
        if ($field === 'ean_xpath') {
            return $this->extractIdentifier($value, array('ean', 'штрихкод', 'barcode'));
        }
        if ($field === 'upc_xpath') {
            return $this->extractIdentifier($value, array('upc'));
        }
        if ($field === 'mpn_xpath') {
            return $this->extractIdentifier($value, array('mpn', 'part number'));
        }
        if ($field === 'manufacturer_xpath') {
            return $this->cleanManufacturerName($value);
        }
        if ($field === 'stock_xpath') {
            return $this->cleanStockTextForDisplay($value);
        }
        if ($field === 'category_xpath') {
            return $this->cleanCategoryPathText($value);
        }
        if ($field === 'description_xpath') {
            $plain = $this->cleanText(strip_tags($value));
            if ($plain !== '' && $this->looksLikeSpecsOnlyDescription($plain, '')) {
                return '';
            }
        }
        return $value;
    }

    protected function normalizeIdentifier($value) {
        $value = $this->cleanText((string)$value);
        $value = trim($value, " \t\n\r\0\x0B.:;,#№");
        if ($value === '') { return ''; }
        $lower = $this->lower($value);
        if (preg_match('/^(грн|грн\.|uah|₴|usd|eur|цена|ціна|price)$/iu', $lower)) { return ''; }
        if (preg_match('/[₴€$]|\d\s+(?:грн\.?|uah|usd|eur)(?:\s|$)/iu', $value)) { return ''; }
        if (!preg_match('/[a-zа-яієїґ0-9]/iu', $value)) { return ''; }
        return substr($value, 0, 80);
    }

    protected function cleanImportedProductName($name) {
        $name = $this->cleanText((string)$name);
        $name = preg_replace('/\s+[-–—]\s+(купити|купить|ціна|цена|доставка).*$/iu', '', $name);
        $name = preg_replace('/^(купити|купить)\s+/iu', '', $name);
        return trim($name);
    }

    protected function filterProductImageUrls($urls, $main_image, $page_url, $name = '', $limit = 6) {
        $out = array();
        $seen = array();
        $main_image = trim((string)$main_image);
        if ($main_image !== '') { $seen[$main_image] = true; }
        $page_host = $this->normalizeHost(parse_url((string)$page_url, PHP_URL_HOST));
        foreach ((array)$urls as $url) {
            $url = trim((string)$url);
            if ($url === '' || isset($seen[$url])) { continue; }
            if (!preg_match('#^https?://#i', $url)) { continue; }
            $path = strtolower((string)parse_url($url, PHP_URL_PATH));
            if (preg_match('/(logo|favicon|banner|slider|sprite|placeholder|no[_-]?image|payment|delivery|icon|avatar)/i', $path)) { continue; }
            if (!preg_match('/\.(jpg|jpeg|png|webp|gif)(\?.*)?$/i', $path)) { continue; }
            $host = $this->normalizeHost(parse_url($url, PHP_URL_HOST));
            if ($page_host !== '' && $host !== '' && $host !== $page_host && !$this->isSubdomainOf($host, $page_host) && !$this->isSubdomainOf($page_host, $host)) {
                // CDN images are allowed later by downloadImage; keep only likely product CDN hosts.
                if (!preg_match('/(image|img|cdn|prom)/i', $host)) { continue; }
            }
            $seen[$url] = true;
            $out[] = $url;
            if (count($out) >= (int)$limit) { break; }
        }
        return $out;
    }

    protected function extractIdentifier($text, $labels = array()) {
        $text = $this->cleanText($text);
        if ($text === '') {
            return '';
        }
        foreach ((array)$labels as $label) {
            $label = preg_quote($label, '/');
            if (preg_match('/' . $label . '\s*[:№#-]?\s*([A-ZА-ЯІЇЄҐ0-9][A-ZА-ЯІЇЄҐ0-9\._\-\/]{1,80})/iu', $text, $m)) {
                return trim($m[1]);
            }
        }
        // A selected identifier node may contain a meaningful prefix separated by a space.
        if (strlen($text) <= 80 && preg_match('/^[A-Z0-9][A-Z0-9 ._\-\/]*$/iu', $text) && preg_match('/[0-9]/', $text)) {
            return $text;
        }
        if (preg_match('/\b([A-Z]{1,8}[-_\/]?[0-9][A-Z0-9\._\-\/]{1,60})\b/u', $text, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/\b([0-9]{5,14})\b/u', $text, $m)) {
            return trim($m[1]);
        }
        $parts = preg_split('/\s*\|\s*/u', $text);
        foreach ($parts as $part) {
            $part = trim($part);
            $part_len = function_exists('mb_strlen') ? mb_strlen($part, 'UTF-8') : strlen($part);
            if ($part !== '' && $part_len <= 80 && preg_match('/[0-9]/u', $part)) {
                return $part;
            }
        }
        $text_len = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
        return $text_len <= 80 ? $text : '';
    }

    protected function autoDetectManyUrls($xpath, $candidate, $base_url, $limit = 8, $product_only = true) {
        $nodes = $xpath->query($candidate['xpath']);
        $urls = array();
        if ($nodes) {
            foreach ($nodes as $node) {
                $value = $this->nodeValue($node, isset($candidate['attr']) ? $candidate['attr'] : 'href');
                $value = $this->normalizeUrl($value, $base_url);
                if (!$value || isset($urls[$value])) {
                    continue;
                }
                if ($product_only && !$this->looksLikeProductUrl($value)) {
                    continue;
                }
                $urls[$value] = $value;
                if (count($urls) >= (int)$limit) {
                    break;
                }
            }
        }
        return array('count' => count($urls), 'urls' => array_values($urls));
    }

    protected function looksLikeProductUrl($url) {
        if ($this->isBlockedNonCatalogUrl($url)) {
            return false;
        }
        $path = (string)parse_url($url, PHP_URL_PATH);
        $query = (string)parse_url($url, PHP_URL_QUERY);
        $full = strtolower($path . '?' . $query);
        if (strpos($full, 'product_id=') !== false || strpos($full, '/product/') !== false) {
            return true;
        }
        if (preg_match('#/(cart|checkout|account|compare|wishlist|login|register|contact|blog|news|category|manufacturer|delivery|oplata|about|testimonials)(/|$)#', $full)) {
            return false;
        }
        $segments = array_values(array_filter(explode('/', trim((string)$path, '/'))));
        if (!$segments) {
            return false;
        }
        $last = end($segments);
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg|css|js|pdf|zip|rar|xml)$/i', $last)) {
            return false;
        }
        if (preg_match('/^(filtry|filters|nasosy|pumpy|shlangi|hoses|schetchiki|meters|pistolety|komplekty|baki|emkosti|aksessuary|zapchasti|oborudovanie)[-_]?(page|p)?[0-9]+$/iu', $last)) {
            return false;
        }
        if (preg_match('/[0-9]/u', $last) && preg_match('/[a-zа-яієїґ]/iu', $last) && preg_match('/[-_]/u', $last)) {
            return true;
        }
        if (preg_match('/^p[0-9]{4,}[-_a-z0-9]*\.html$/i', $last) || preg_match('/\/p[0-9]{4,}/i', $path)) {
            return true;
        }
        if (preg_match('/(sku|product|tovar|item|model|artik|artikul|goods|prod)[-_]?[0-9a-z]/i', $full)) {
            return true;
        }
        if (preg_match('/[a-zа-яієїґ]{2,}[0-9]{2,}|[0-9]{2,}[a-zа-яієїґ]{2,}/iu', $last)) {
            return true;
        }
        if (count($segments) >= 2 && preg_match('/[a-zа-яієїґ]+[-_][a-zа-яієїґ0-9]+[-_][a-zа-яієїґ0-9]+/iu', $last)) {
            if (preg_match('/(contact|about|delivery|payment|oplata|news|blog|article|information|info|review|testimonial|category|catalog|katalog|product_list|filter|filtr|filtry)$/i', $last)) {
                return false;
            }
            return true;
        }
        return false;
    }


    protected function shortenAutoValue($value) {
        $value = $this->cleanText($value);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > 180 ? mb_substr($value, 0, 180, 'UTF-8') . '...' : $value;
        }
        return strlen($value) > 180 ? substr($value, 0, 180) . '...' : $value;
    }

    protected function confidenceLabel($score) {
        if ($score >= 80) {
            return 'high';
        }
        if ($score >= 60) {
            return 'medium';
        }
        return 'low';
    }

    public function sortAutoCandidates($a, $b) {
        if ((int)$a['confidence'] === (int)$b['confidence']) {
            return 0;
        }
        return ((int)$a['confidence'] > (int)$b['confidence']) ? -1 : 1;
    }

    public function applySelectedReviews($review_ids, $mode, $supplier_id = 0) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $mode = preg_replace('/[^a-z_]/', '', (string)$mode);
        $ids = array();
        foreach ((array)$review_ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return array('ok' => false, 'message' => 'No selected rows');
        }

        $updated = 0;
        $price_updated = 0;
        $stock_updated = 0;
        $no_change = 0;
        $created = 0;
        $moved_new = 0;
        $created_ids = array();
        $skipped = 0;
        $errors = 0;

        $where_supplier = ((int)$supplier_id > 0) ? " AND supplier_id = '" . (int)$supplier_id . "'" : '';
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_review` WHERE review_id IN (" . implode(',', $ids) . ")" . $where_supplier . " ORDER BY review_id ASC");
        foreach ($query->rows as $row) {
            $supplier = $this->getSupplier((int)$row['supplier_id']);
            $data = $this->jsonDecode($row['parsed_data']);
            if (!$supplier || !$data) {
                $this->setReviewStatus((int)$row['review_id'], 'error', 'Missing supplier or parsed data');
                $errors++;
                continue;
            }

            try {
                if ($row['status'] === 'excluded' && $mode !== 'skip') {
                    $this->setReviewStatus((int)$row['review_id'], 'excluded', 'Row is excluded by supplier rules');
                    $skipped++;
                    continue;
                }
                if ($mode === 'skip') {
                    $this->setReviewStatus((int)$row['review_id'], 'skipped', 'Skipped manually');
                    $skipped++;
                    continue;
                }
                if ($mode === 'skip_exclude') {
                    $exists = $this->db->query("SELECT exclusion_id FROM `" . DB_PREFIX . "ccp_ssp_exclusion` WHERE supplier_id = '" . (int)$row['supplier_id'] . "' AND ((product_id > 0 AND product_id = '" . (int)$row['product_id'] . "') OR (supplier_product_url <> '' AND supplier_product_url = '" . $this->db->escape($row['supplier_product_url']) . "') OR (supplier_sku <> '' AND supplier_sku = '" . $this->db->escape($row['supplier_sku']) . "')) LIMIT 1");
                    if (!$exists->num_rows) {
                        $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_exclusion` SET supplier_id = '" . (int)$row['supplier_id'] . "', product_id = '" . (int)$row['product_id'] . "', supplier_product_url = '" . $this->db->escape($row['supplier_product_url']) . "', supplier_sku = '" . $this->db->escape($row['supplier_sku']) . "', type = 'manual', reason = 'Skipped and excluded from preview', date_added = NOW()");
                    }
                    $this->setReviewStatus((int)$row['review_id'], 'excluded', 'Skipped and excluded manually');
                    $skipped++;
                    continue;
                }

                if ($mode === 'move_to_new') {
                    if ((int)$row['product_id'] > 0) {
                        $this->setReviewStatus((int)$row['review_id'], 'skipped', 'This row is already linked to product ID ' . (int)$row['product_id']);
                        $skipped++;
                        continue;
                    }
                    if (empty($data['name']) || empty($data['sale_price'])) {
                        $this->setReviewStatus((int)$row['review_id'], 'error', 'Missing product name or calculated price');
                        $errors++;
                        continue;
                    }
                    $new_status = !empty($data['category_allowed']) ? 'pending' : 'excluded';
                    $new_error = $new_status === 'excluded' ? 'Supplier category is not allowed by rules' : 'Moved from preview for manual creation';
                    $this->saveNewProduct((int)$row['supplier_id'], $row['supplier_product_url'], $data, $new_status, 0, $new_error);
                    $this->setReviewStatus((int)$row['review_id'], 'pending', 'Moved to New Products tab');
                    $moved_new++;
                    continue;
                }

                if ($mode === 'create_new') {
                    if ((int)$row['product_id'] > 0) {
                        $this->setReviewStatus((int)$row['review_id'], 'skipped', 'This row is already linked to product ID ' . (int)$row['product_id']);
                        $skipped++;
                        continue;
                    }
                    if (empty($supplier['create_new'])) {
                        $this->setReviewStatus((int)$row['review_id'], 'error', 'New product creation is disabled for this supplier');
                        $errors++;
                        continue;
                    }
                    if (empty($data['category_allowed'])) {
                        $this->setReviewStatus((int)$row['review_id'], 'excluded', 'Supplier category is not allowed by rules');
                        $skipped++;
                        continue;
                    }
                    $duplicate = $this->findPotentialDuplicateForCreate($data, (int)$row['supplier_id'], $row['supplier_product_url'], $supplier);
                    if (!empty($duplicate['product_id'])) {
                        $this->setReviewProductId((int)$row['review_id'], (int)$duplicate['product_id'], 'duplicate', 'Potential duplicate found by ' . $duplicate['source']);
                        $this->saveProductLink((int)$row['supplier_id'], (int)$duplicate['product_id'], $row['supplier_product_url'], $data['sku'], $data['name'], $data['supplier_price'], $data['sale_price'], $data['stock_text'], $data['quantity'], 'duplicate', 'Potential duplicate found by ' . $duplicate['source'], isset($data['purchase_price']) ? (float)$data['purchase_price'] : 0);
                        $skipped++;
                        continue;
                    }
                    $product_id = $this->createNewProduct($data, $supplier, $row['supplier_product_url']);
                    if ($product_id > 0) {
                        $this->setReviewProductId((int)$row['review_id'], $product_id, 'created', '');
                        $this->saveProductLink((int)$row['supplier_id'], $product_id, $row['supplier_product_url'], $data['sku'], $data['name'], $data['supplier_price'], $data['sale_price'], $data['stock_text'], $data['quantity'], 'created', '', isset($data['purchase_price']) ? (float)$data['purchase_price'] : 0);
                        $this->saveNewProduct((int)$row['supplier_id'], $row['supplier_product_url'], $data, 'created', $product_id, '');
                        $created++;
                        $created_ids[] = (int)$product_id;
                    } else {
                        $this->setReviewStatus((int)$row['review_id'], 'error', 'Product was not created');
                        $errors++;
                    }
                    continue;
                }

                if ((int)$row['product_id'] <= 0) {
                    $this->setReviewStatus((int)$row['review_id'], 'skipped', 'No linked OpenCart product');
                    $skipped++;
                    continue;
                }

                $force_price = ($mode === 'update_price_force' || $mode === 'update_price_stock_force');
                $update_price = ($mode === 'update_price' || $mode === 'update_price_stock' || $mode === 'update_price_force' || $mode === 'update_price_stock_force');
                $update_stock = ($mode === 'update_stock' || $mode === 'update_price_stock' || $mode === 'update_price_stock_force' || $mode === 'full_update');
                if ($mode === 'full_update') { $update_price = true; $update_stock = true; }
                if (!$force_price && $update_price && $row['status'] === 'price_warning') {
                    $this->setReviewStatus((int)$row['review_id'], 'price_warning', 'Price warning: use forced price update only after manual check');
                    $skipped++;
                    continue;
                }
                if (!$update_price && !$update_stock) {
                    $this->setReviewStatus((int)$row['review_id'], 'skipped', 'Unknown apply mode');
                    $skipped++;
                    continue;
                }

                $update = $this->updateExistingProduct((int)$row['product_id'], $data, $supplier, $row['supplier_product_url'], $update_price, $update_stock, $mode === 'full_update');
                if ($update['ok']) {
                    $this->setReviewStatus((int)$row['review_id'], 'applied', '');
                    if (!empty($update['changed_fields']) && in_array('price', $update['changed_fields'])) { $price_updated++; }
                    if (!empty($update['changed_fields']) && (in_array('quantity', $update['changed_fields']) || in_array('stock_status', $update['changed_fields']))) { $stock_updated++; }
                    if (!empty($update['no_change'])) { $no_change++; }
                    $this->saveProductLink((int)$row['supplier_id'], (int)$row['product_id'], $row['supplier_product_url'], $data['sku'], $data['name'], $data['supplier_price'], $data['sale_price'], $data['stock_text'], $data['quantity'], 'linked', '', isset($data['purchase_price']) ? (float)$data['purchase_price'] : 0);
                    $updated++;
                } else {
                    $this->setReviewStatus((int)$row['review_id'], 'error', $update['message']);
                    $errors++;
                }
            } catch (Exception $e) {
                $this->setReviewStatus((int)$row['review_id'], 'error', $e->getMessage());
                $errors++;
            }
        }

        return array('ok' => true, 'message' => 'Done', 'updated' => $updated, 'price_updated' => $price_updated, 'stock_updated' => $stock_updated, 'no_change' => $no_change, 'created' => $created, 'moved_new' => $moved_new, 'created_ids' => $created_ids, 'skipped' => $skipped, 'errors' => $errors);
    }


    protected function setReviewStatus($review_id, $status, $error = '') {
        $review_id = (int)$review_id;
        if ($review_id <= 0) {
            return;
        }
        $status = preg_replace('/[^a-z0-9_\-]/i', '', (string)$status);
        if ($status === '') {
            $status = 'pending';
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_review` SET status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape((string)$error) . "', date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'");
    }

    protected function setReviewProductId($review_id, $product_id, $status = 'pending', $error = '') {
        $review_id = (int)$review_id;
        $product_id = (int)$product_id;
        if ($review_id <= 0) {
            return;
        }
        $status = preg_replace('/[^a-z0-9_\-]/i', '', (string)$status);
        if ($status === '') {
            $status = 'pending';
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_review` SET product_id = '" . (int)$product_id . "', status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape((string)$error) . "', date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'");
    }


    public function applySelectedNewProducts($new_ids, $category_overrides = array(), $supplier_id = 0) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $ids = array();
        foreach ((array)$new_ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (!$ids) {
            return array('ok' => false, 'message' => 'No selected rows', 'created' => 0, 'skipped' => 0, 'errors' => 0);
        }
        $created = 0;
        $created_ids = array();
        $skipped = 0;
        $errors = 0;
        $row_errors = array();
        $where_supplier = ((int)$supplier_id > 0) ? " AND supplier_id = '" . (int)$supplier_id . "'" : '';
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_new_product` WHERE new_id IN (" . implode(',', $ids) . ")" . $where_supplier . " ORDER BY new_id ASC");
        foreach ($q->rows as $row) {
            $supplier = $this->getSupplier((int)$row['supplier_id']);
            $data = $this->jsonDecode($row['parsed_data']);
            if (!$supplier || !$data) {
                $message = 'Missing supplier or parsed data';
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'error', last_error = '" . $this->db->escape($message) . "', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                $this->addLog((int)$row['supplier_id'], 'error', 'New product creation failed', $message . ' #' . (int)$row['new_id'], $row['supplier_product_url']);
                $row_errors[] = array('new_id' => (int)$row['new_id'], 'message' => $message);
                $errors++;
                continue;
            }
            if ((int)$row['product_id'] > 0) {
                $skipped++;
                continue;
            }
            if (isset($row['status']) && $row['status'] === 'skipped') {
                $skipped++;
                continue;
            }
            $override_category_id = 0;
            if (is_array($category_overrides) && isset($category_overrides[$row['new_id']])) {
                $override_category_id = (int)$category_overrides[$row['new_id']];
            }
            if ($override_category_id > 0) {
                $data['target_category_id'] = $override_category_id;
                $data['category_allowed'] = true;
                $data['category_rule'] = 'manual_preview_category';
            }
            if (($row['status'] === 'excluded' || empty($data['category_allowed'])) && $override_category_id <= 0) {
                $message = 'Supplier category is not allowed by rules';
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'excluded', last_error = '" . $this->db->escape($message) . "', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                $this->addLog((int)$row['supplier_id'], 'warning', 'New product creation skipped', $message . ' #' . (int)$row['new_id'], $row['supplier_product_url']);
                $row_errors[] = array('new_id' => (int)$row['new_id'], 'message' => $message);
                $skipped++;
                continue;
            }
            if (empty($supplier['create_new'])) {
                $message = 'New product creation is disabled for this supplier';
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'pending', last_error = '" . $this->db->escape($message) . "', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                $this->addLog((int)$row['supplier_id'], 'warning', 'New product creation blocked', $message . ' #' . (int)$row['new_id'], $row['supplier_product_url']);
                $row_errors[] = array('new_id' => (int)$row['new_id'], 'message' => $message);
                $errors++;
                continue;
            }
            if ($row['status'] === 'error' && stripos((string)$row['last_error'], 'New product creation is disabled') !== false) {
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'pending', last_error = '', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                $row['status'] = 'pending';
                $row['last_error'] = '';
            }
            try {
                $duplicate = $this->findPotentialDuplicateForCreate($data, (int)$row['supplier_id'], $row['supplier_product_url'], $supplier);
                if (!empty($duplicate['product_id'])) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'duplicate', product_id = '" . (int)$duplicate['product_id'] . "', last_error = 'Potential duplicate found by " . $this->db->escape($duplicate['source']) . "', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                    if (!isset($duplicate['auto_link']) || !empty($duplicate['auto_link'])) {
                        $this->saveProductLink((int)$row['supplier_id'], (int)$duplicate['product_id'], $row['supplier_product_url'], isset($data['sku']) ? $data['sku'] : '', isset($data['name']) ? $data['name'] : '', isset($data['supplier_price']) ? (float)$data['supplier_price'] : 0, isset($data['sale_price']) ? (float)$data['sale_price'] : 0, isset($data['stock_text']) ? $data['stock_text'] : '', isset($data['quantity']) ? (int)$data['quantity'] : 0, 'duplicate', 'Potential duplicate found by ' . $duplicate['source'], isset($data['purchase_price']) ? (float)$data['purchase_price'] : 0);
                    }
                    $this->addLog((int)$row['supplier_id'], 'warning', 'New product duplicate candidate', 'Potential duplicate found by ' . $duplicate['source'] . ' #' . (int)$row['new_id'], $row['supplier_product_url']);
                    $row_errors[] = array('new_id' => (int)$row['new_id'], 'message' => 'Potential matches found; choose manually before creating a new product');
                    $skipped++;
                    continue;
                }
                $product_id = $this->createNewProduct($data, $supplier, $row['supplier_product_url']);
                if ($product_id > 0) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'created', product_id = '" . (int)$product_id . "', last_error = '', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                    $this->saveProductLink((int)$row['supplier_id'], $product_id, $row['supplier_product_url'], isset($data['sku']) ? $data['sku'] : '', isset($data['name']) ? $data['name'] : '', isset($data['supplier_price']) ? (float)$data['supplier_price'] : 0, isset($data['sale_price']) ? (float)$data['sale_price'] : 0, isset($data['stock_text']) ? $data['stock_text'] : '', isset($data['quantity']) ? (int)$data['quantity'] : 0, 'created', '', isset($data['purchase_price']) ? (float)$data['purchase_price'] : 0);
                    $created++;
                    $created_ids[] = (int)$product_id;
                } else {
                    $message = 'Product was not created';
                    $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'error', last_error = '" . $this->db->escape($message) . "', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                    $this->addLog((int)$row['supplier_id'], 'error', 'New product creation failed', $message . ' #' . (int)$row['new_id'], $row['supplier_product_url']);
                    $row_errors[] = array('new_id' => (int)$row['new_id'], 'message' => $message);
                    $errors++;
                }
            } catch (Exception $e) {
                $message = $e->getMessage();
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'error', last_error = '" . $this->db->escape($message) . "', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                $this->addLog((int)$row['supplier_id'], 'error', 'New product creation exception', $message . ' #' . (int)$row['new_id'], $row['supplier_product_url']);
                $row_errors[] = array('new_id' => (int)$row['new_id'], 'message' => $message);
                $errors++;
            } catch (Throwable $e) {
                $message = $e->getMessage();
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET status = 'error', last_error = '" . $this->db->escape($message) . "', date_modified = NOW() WHERE new_id = '" . (int)$row['new_id'] . "'");
                $this->addLog((int)$row['supplier_id'], 'error', 'New product creation runtime error', $message . ' #' . (int)$row['new_id'], $row['supplier_product_url']);
                $row_errors[] = array('new_id' => (int)$row['new_id'], 'message' => $message);
                $errors++;
            }
        }
        $ok = ($errors <= 0);
        return array('ok' => $ok, 'message' => 'Done', 'created' => $created, 'created_ids' => $created_ids, 'skipped' => $skipped, 'errors' => $errors, 'row_errors' => $row_errors);
    }

    public function manualMatchReviewProduct($review_id, $product_id, $supplier_id = 0) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $review_id = (int)$review_id;
        $product_id = (int)$product_id;
        if ($review_id <= 0 || $product_id <= 0) {
            return array('ok' => false, 'message' => 'Review ID and product ID are required');
        }
        $where_supplier = ((int)$supplier_id > 0) ? " AND supplier_id = '" . (int)$supplier_id . "'" : '';
        $row_q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_review` WHERE review_id = '" . (int)$review_id . "'" . $where_supplier . " LIMIT 1");
        if (!$row_q->num_rows) {
            return array('ok' => false, 'message' => 'Review row not found');
        }
        $language_id = $this->getLanguageId();
        $product_q = $this->db->query("SELECT p.product_id, p.price, p.quantity, p.stock_status_id, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE p.product_id = '" . (int)$product_id . "' LIMIT 1");
        if (!$product_q->num_rows) {
            return array('ok' => false, 'message' => 'OpenCart product was not found');
        }
        $match = $this->prepareProductMatch($product_q->row, 'manual');
        if (empty($match['product_id'])) {
            return array('ok' => false, 'message' => 'OpenCart product was not found');
        }
        $row = $row_q->row;
        $supplier = $this->getSupplier((int)$row['supplier_id']);
        $data = $this->jsonDecode($row['parsed_data']);
        if (!$supplier || !$data) {
            return array('ok' => false, 'message' => 'Missing supplier or parsed data');
        }
        $status = 'pending';
        $last_error = '';
        $delta = $this->calculatePriceDelta((float)$match['price'], (float)$data['sale_price']);
        $max_delta = isset($supplier['max_price_change_percent']) ? (float)$supplier['max_price_change_percent'] : 0;
        if ($max_delta > 0 && $match['price'] > 0 && abs((float)$delta['percent']) > $max_delta) {
            $status = 'price_warning';
            $last_error = 'Calculated price change is higher than allowed supplier limit';
        }
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_review` SET product_id = '" . (int)$match['product_id'] . "', local_name = '" . $this->db->escape($match['name']) . "', local_price = '" . (float)$match['price'] . "', local_quantity = '" . (int)$match['quantity'] . "', local_stock_status_id = '" . (int)$match['stock_status_id'] . "', price_delta_abs = '" . (float)$delta['abs'] . "', price_delta_percent = '" . (float)$delta['percent'] . "', match_source = 'manual', review_type = 'existing', status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape($last_error) . "', date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'");
        $this->saveProductLink((int)$row['supplier_id'], (int)$match['product_id'], $row['supplier_product_url'], $row['supplier_sku'], $row['supplier_name'], (float)$row['supplier_price'], (float)$row['sale_price'], $row['supplier_stock_text'], (int)$row['supplier_quantity'], 'manual_link', '');
        return array(
            'ok' => true,
            'message' => 'Product linked manually',
            'status' => $status,
            'review_type' => 'existing',
            'match_source' => 'manual',
            'product_id' => (int)$match['product_id'],
            'product_name' => (string)$match['name'],
            'local_price' => round((float)$match['price'], 2),
            'local_quantity' => (int)$match['quantity'],
            'local_stock_status_id' => (int)$match['stock_status_id'],
            'price_delta_abs' => round((float)$delta['abs'], 2),
            'price_delta_percent' => round((float)$delta['percent'], 2)
        );
    }


    protected function discoverProductLinksOnPage($xpath, $base_url, $supplier, $limit = 300) {
        $items = $this->discoverProductLinkItemsOnPage($xpath, $base_url, $supplier, $limit);
        $urls = array();
        foreach ($items as $item) {
            if (!empty($item['url'])) {
                $urls[] = $item['url'];
            }
        }
        return $urls;
    }

    protected function discoverProductLinkItemsOnPage($xpath, $base_url, $supplier, $limit = 300) {
        $ci = "translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $ii = "translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $candidates = array(
            array('xpath' => "//a[contains(@href,'product_id=')]", 'attr' => 'href', 'context' => 'explicit'),
            array('xpath' => "//a[contains(@href,'/p') and contains(@href,'.html')]", 'attr' => 'href', 'context' => 'explicit'),
            array('xpath' => "//*[contains($ci,'product-thumb') or contains($ci,'product-item') or contains($ci,'product-layout') or contains($ci,'product-card') or contains($ci,'product__') or contains($ci,'products__item') or contains($ci,'catalog-item') or contains($ci,'goods-item') or contains($ci,'item-product')]//a[@href]", 'attr' => 'href', 'context' => 'product_block'),
            array('xpath' => "//*[contains($ci,'name') or contains($ci,'title')]//a[@href]", 'attr' => 'href', 'context' => 'name_link'),
            array('xpath' => "//h1/a[@href] | //h2/a[@href] | //h3/a[@href] | //h4/a[@href]", 'attr' => 'href', 'context' => 'heading'),
            array('xpath' => "//*[contains($ii,'content')]//a[@href]", 'attr' => 'href', 'context' => 'content')
        );
        $items = array();
        foreach ($candidates as $candidate) {
            $nodes = $xpath->query($candidate['xpath']);
            if (!$nodes) { continue; }
            foreach ($nodes as $node) {
                $value = $this->normalizeUrl($this->nodeValue($node, $candidate['attr']), $base_url);
                if (!$value || isset($items[$value])) { continue; }
                if (!$this->isSupplierHtmlUrlAllowed($value, $supplier)) { continue; }
                $title = $this->cleanText($node->textContent);
                $context = isset($candidate['context']) ? $candidate['context'] : '';
                if (!$this->isLikelyProductLinkCandidate($value, $title, $context)) { continue; }
                $items[$value] = array('url' => $value, 'title' => $title);
                if (count($items) >= (int)$limit) { break 2; }
            }
        }
        return array_values($items);
    }

    protected function isLikelyProductLinkCandidate($url, $title = '', $context = '') {
        $title = $this->cleanText($title);
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        if ($title !== '' && $this->looksLikeCategoryTitle($title)) {
            return false;
        }
        if ($this->looksLikeCategoryUrl($url)) {
            return false;
        }
        if ($this->looksLikeProductUrl($url)) {
            return true;
        }
        if (preg_match('#/(cart|checkout|account|compare|wishlist|login|register|contact|blog|news|category|manufacturer|delivery|oplata|about|testimonials)(/|$)#', $path)) {
            return false;
        }
        if ($context === 'product_block' && $title !== '' && $this->looksLikeProductTitle($title)) {
            return true;
        }
        if (($context === 'name_link' || $context === 'heading') && $title !== '' && $this->looksLikeProductTitle($title) && !$this->looksLikeCategoryTitle($title)) {
            return true;
        }
        return false;
    }

    protected function looksLikeProductTitle($title) {
        $title = $this->cleanText($title);
        if ($title === '') { return false; }
        $len = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
        if ($len < 4 || $len > 180) { return false; }
        if (preg_match('/(купити|цена|ціна|грн|uah|usd|eur|код|sku|артикул|модель|model|насос|комплект|генератор|фільтр|фильтр|шланг|пістолет|пистолет|лічильник|счетчик|бак|модуль|станція|станция)/iu', $title)) {
            return true;
        }
        if (preg_match('/[0-9][a-zа-яієїґ]|[a-zа-яієїґ][0-9]/iu', $title)) {
            return true;
        }
        return str_word_count(str_replace(array('-', '_', '/'), ' ', $title)) >= 3;
    }

    protected function looksLikeCategoryTitle($title) {
        $title = $this->cleanText($title);
        if ($title === '') { return false; }
        $lc = $this->lower($title);
        if (preg_match('/^(каталог|категорії|категории|товари|товары|все товары|усі товари|акції|акции|новинки|бренди|бренды)$/iu', $title)) {
            return true;
        }
        $has_digits = preg_match('/\d/u', $title);
        if (!$has_digits && preg_match('/(насоси|насосы|фільтри|фильтры|шланги|лічильники|счетчики|пістолети|пистолеты|комплекти|комплекты|колонки|ємності|емкости|баки|аксесуари|аксессуары|запчастини|запчасти|обладнання|оборудование|механические счетчики|механічні лічильники)/iu', $lc)) {
            return true;
        }
        return false;
    }


    protected function discoverListLinksOnPage($xpath, $base_url, $supplier, $limit = 120) {
        $nodes = $xpath->query("//a[@href]");
        $urls = array();
        if (!$nodes) { return array(); }
        foreach ($nodes as $node) {
            $value = $this->normalizeUrl($this->nodeValue($node, 'href'), $base_url);
            if (!$value || isset($urls[$value])) { continue; }
            if (!$this->isSupplierHtmlUrlAllowed($value, $supplier)) { continue; }
            if (!$this->looksLikeListUrl($value)) { continue; }
            $text = $this->cleanText($node->textContent);
            if ($this->looksLikeProductUrl($value) && $this->looksLikeProductTitle($text)) { continue; }
            $urls[$value] = $value;
            if (count($urls) >= (int)$limit) { break; }
        }
        return array_values($urls);
    }


    protected function autoDetectNextListPage($xpath, $base_url, $supplier) {
        $ci = "translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $defs = array(
            "//a[@rel='next']",
            "//*[contains($ci,'pagination') or contains($ci,'pager') or contains($ci,'pages')]//a[contains(.,'>') or contains(.,'›') or contains(.,'Next') or contains(.,'Наступ') or contains(.,'След') or contains(.,'Далі') or contains(.,'Далее') or contains(@class,'next') or contains(@aria-label,'Next') or contains(@aria-label,'Наступ') or contains(@aria-label,'След')]"
        );
        foreach ($defs as $def) {
            $nodes = $xpath->query($def);
            if ($nodes && $nodes->length) {
                $next = $this->normalizeUrl($this->nodeValue($nodes->item(0), 'href'), $base_url);
                if ($next && $this->isSupplierHtmlUrlAllowed($next, $supplier)) {
                    return $next;
                }
            }
        }
        return '';
    }

    protected function looksLikeListUrl($url) {
        if ($this->isBlockedNonCatalogUrl($url)) { return false; }
        $path = strtolower((string)parse_url($url, PHP_URL_PATH));
        $query = strtolower((string)parse_url($url, PHP_URL_QUERY));
        if ($path === '' || $path === '/') { return true; }
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg|css|js|pdf|zip|rar|xml)$/i', $path)) { return false; }
        if (preg_match('#/(cart|checkout|account|compare|wishlist|login|register|contact|terms|privacy|delivery|oplata|about|blog|news|testimonials)(/|$)#', $path)) { return false; }
        if (strpos($query, 'page=') !== false || strpos($query, 'p=') !== false) { return true; }
        if (preg_match('#/(category|catalog|product_list|products|ua/product_list)(/|$)#', $path)) { return true; }
        if (preg_match('#/(g|ps)[0-9]+#', $path)) { return true; }
        if ($this->looksLikeProductUrl($url)) { return false; }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        return count($segments) >= 1 && count($segments) <= 4;
    }

    protected function guessPaginationUrls($xpath, $base_url, $supplier, $limit = 12) {
        $urls = array();
        $nodes = $xpath->query("//a[@href]");
        if ($nodes) {
            foreach ($nodes as $node) {
                $text = $this->cleanText($node->textContent);
                $href = $this->normalizeUrl($this->nodeValue($node, 'href'), $base_url);
                if (!$href || isset($urls[$href]) || !$this->isSupplierHtmlUrlAllowed($href, $supplier)) { continue; }
                if ($this->isSameListSectionUrl($base_url, $href) || preg_match('/^(\d+|>|›|next|далі|далее|наступ|след)$/iu', $text)) {
                    if ($this->looksLikeListUrl($href)) {
                        $urls[$href] = $href;
                    }
                }
                if (count($urls) >= (int)$limit) { break; }
            }
        }
        return array_values($urls);
    }

    protected function isSameListSectionUrl($current_url, $candidate_url) {
        $current = parse_url((string)$current_url);
        $candidate = parse_url((string)$candidate_url);
        if (empty($current['host']) || empty($candidate['host']) || strtolower($current['host']) !== strtolower($candidate['host'])) {
            return false;
        }
        if ($this->looksLikeProductUrl($candidate_url)) {
            return false;
        }
        $current_path = trim(isset($current['path']) ? (string)$current['path'] : '/', '/');
        $candidate_path = trim(isset($candidate['path']) ? (string)$candidate['path'] : '/', '/');
        if ($current_path === $candidate_path) {
            return true;
        }
        $current_parent = preg_replace('#/(page|page-[0-9]+|p[0-9]+)$#i', '', $current_path);
        $candidate_parent = preg_replace('#/(page|page-[0-9]+|p[0-9]+)$#i', '', $candidate_path);
        if ($current_parent !== '' && $current_parent === $candidate_parent) {
            return true;
        }
        $cq = isset($current['query']) ? (string)$current['query'] : '';
        $pq = isset($candidate['query']) ? (string)$candidate['query'] : '';
        if ($current_path === $candidate_path && (strpos($pq, 'page=') !== false || strpos($pq, 'p=') !== false || strpos($cq, 'page=') !== false || strpos($cq, 'p=') !== false)) {
            return true;
        }
        return false;
    }

    public function parseListPage($supplier, $url) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        if (!$this->isSupplierHtmlUrlAllowed($url, $supplier)) {
            return array('ok' => false, 'message' => 'URL is outside supplier domain: ' . $url);
        }
        $fetch = $this->fetchUrl($url, $supplier);
        if (!$fetch['ok']) {
            return $fetch;
        }

        $xpath = $this->createXPath($fetch['body']);
        if (!$xpath) {
            return array('ok' => false, 'message' => 'Cannot parse HTML');
        }

        $urls = array();
        $product_items = array();
        $list_category_text = '';
        if (!empty($supplier['list_category_xpath'])) {
            $list_category_text = $this->cleanText($this->xpathFirst($xpath, $supplier['list_category_xpath']));
        }
        $product_xpath = trim($supplier['product_url_xpath']);
        $attr = trim($supplier['product_url_attr']) ? trim($supplier['product_url_attr']) : 'href';

        if ($product_xpath) {
            $nodes = $xpath->query($product_xpath);
            if ($nodes) {
                foreach ($nodes as $node) {
                    $value = $this->nodeValue($node, $attr);
                    $value = $this->normalizeUrl($value, $url);
                    $title = $this->cleanText($node->textContent);
                    if ($value && $this->isSupplierHtmlUrlAllowed($value, $supplier) && !in_array($value, $urls, true) && $this->isLikelyProductLinkCandidate($value, $title, 'product_block')) {
                        $urls[] = $value;
                        $product_items[] = array('url' => $value, 'supplier_category' => $list_category_text, 'title' => $title);
                    }
                }
            }
        }

        if (!$urls) {
            $auto_products = $this->discoverProductLinkItemsOnPage($xpath, $url, $supplier, 300);
            foreach ($auto_products as $item) {
                $value = isset($item['url']) ? $item['url'] : '';
                if ($value && !in_array($value, $urls, true)) {
                    $urls[] = $value;
                    $product_items[] = array('url' => $value, 'supplier_category' => $list_category_text, 'title' => isset($item['title']) ? $item['title'] : '');
                }
            }
            if ($urls) {
                $this->addLog((int)$supplier['supplier_id'], 'info', 'Product links auto-detected on list page', 'Found: ' . count($urls), $url);
            }
        }

        $list_urls = $this->discoverListLinksOnPage($xpath, $url, $supplier, 120);

        $next = '';
        if (trim($supplier['next_page_xpath'])) {
            $nodes = $xpath->query(trim($supplier['next_page_xpath']));
            if ($nodes && $nodes->length) {
                $next = $this->normalizeUrl($this->nodeValue($nodes->item(0), 'href'), $url);
                if (!$this->isSupplierHtmlUrlAllowed($next, $supplier)) {
                    $next = '';
                }
            }
        }
        if ($next === '') {
            $next = $this->autoDetectNextListPage($xpath, $url, $supplier);
        }
        foreach ($this->guessPaginationUrls($xpath, $url, $supplier, !empty($urls) ? 20 : 8) as $guessed_page_url) {
            if (!in_array($guessed_page_url, $list_urls, true) && $guessed_page_url !== $url) {
                $list_urls[] = $guessed_page_url;
            }
        }

        $self_name = $this->cleanImportedProductName($this->cleanText($this->xpathFirst($xpath, isset($supplier['name_xpath']) ? $supplier['name_xpath'] : '')));
        $self_price = $this->cleanText($this->xpathFirst($xpath, isset($supplier['price_xpath']) ? $supplier['price_xpath'] : ''));
        $self_sku = $this->normalizeIdentifier($this->extractIdentifier($this->cleanText($this->xpathFirst($xpath, isset($supplier['sku_xpath']) ? $supplier['sku_xpath'] : '')), array('код товару', 'код товара', 'артикул', 'sku', 'model', 'модель')));
        if ($this->isCurrentPageLikelyProduct($xpath, $url, $supplier, $self_name, $self_price, $self_sku)) {
            if (!in_array($url, $urls, true)) {
                array_unshift($urls, $url);
                array_unshift($product_items, array('url' => $url, 'supplier_category' => $list_category_text));
            }
        }

        return array('ok' => true, 'product_urls' => $urls, 'product_items' => $product_items, 'list_urls' => $list_urls, 'next_page_url' => $next, 'message' => 'OK');
    }

    public function parseProduct($supplier, $url, $context = array()) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        if (!$this->isSupplierHtmlUrlAllowed($url, $supplier)) {
            return array('ok' => false, 'message' => 'URL is outside supplier domain: ' . $url);
        }
        $fetch = $this->fetchUrl($url, $supplier);
        if (!$fetch['ok']) {
            return $fetch;
        }

        $xpath = $this->createXPath($fetch['body']);
        if (!$xpath) {
            return array('ok' => false, 'message' => 'Cannot parse HTML');
        }

        $name = $this->cleanImportedProductName($this->cleanText($this->xpathFirst($xpath, $supplier['name_xpath'])));
        $sku = $this->normalizeIdentifier($this->extractIdentifier($this->cleanText($this->xpathFirst($xpath, $supplier['sku_xpath'])), array('артикул', 'sku', 'supplier sku', 'vendor code')));
        $identifier_settings = $this->jsonDecode(isset($supplier['settings']) ? $supplier['settings'] : '');
        $extra_identifiers = array();
        foreach (array('jan','isbn') as $field) {
            $path = isset($identifier_settings[$field.'_xpath']) ? $identifier_settings[$field.'_xpath'] : '';
            $extra_identifiers[$field] = $this->normalizeIdentifier($this->extractIdentifier($this->cleanText($this->xpathFirst($xpath, $path)), array($field)));
        }
        $model_raw = '';
        if (!empty($supplier['model_xpath'])) {
            if (trim((string)$supplier['model_xpath']) === '__url_model__') {
                $model_raw = $this->extractModelFromUrl($url);
            } else {
                $model_raw = $this->normalizeIdentifier($this->extractIdentifier($this->cleanText($this->xpathFirst($xpath, $supplier['model_xpath'])), array('код товару', 'код товара', 'код', 'model', 'модель', 'артикул', 'sku')));
            }
        }
        $ean = $this->normalizeIdentifier($this->extractIdentifier($this->cleanText($this->xpathFirst($xpath, isset($supplier['ean_xpath']) ? $supplier['ean_xpath'] : '')), array('ean', 'штрихкод', 'barcode')));
        $upc = $this->normalizeIdentifier($this->extractIdentifier($this->cleanText($this->xpathFirst($xpath, isset($supplier['upc_xpath']) ? $supplier['upc_xpath'] : '')), array('upc')));
        $mpn = $this->normalizeIdentifier($this->extractIdentifier($this->cleanText($this->xpathFirst($xpath, isset($supplier['mpn_xpath']) ? $supplier['mpn_xpath'] : '')), array('mpn', 'part number')));
        $price_text = $this->cleanText($this->xpathFirst($xpath, $supplier['price_xpath']));
        $stock_text_raw = $this->cleanText($this->xpathFirst($xpath, $supplier['stock_xpath']));
        $stock_text = $this->cleanStockTextForDisplay($stock_text_raw);
        $category_text = $this->cleanText($this->xpathFirst($xpath, isset($supplier['category_xpath']) ? $supplier['category_xpath'] : ''));
        $adapter_settings = $this->jsonDecode(isset($supplier['settings']) ? $supplier['settings'] : '');
        if (isset($adapter_settings['source_adapter']) && $adapter_settings['source_adapter'] === 'prom') {
            $category_text = $this->promCategoryPath($category_text);
        }
        if ($category_text === '' && !empty($context['supplier_category'])) {
            $category_text = $this->cleanText($context['supplier_category']);
        }
        $category_text = $this->cleanCategoryPathText($category_text);
        $category_text = $this->normalizeSupplierCategoryTextForProduct($category_text, $name, $sku, !empty($context['supplier_category']) ? $context['supplier_category'] : '');
        $description = $this->htmlByXPath($xpath, $supplier['description_xpath']);
        $meta_description = $this->cleanText($this->xpathFirst($xpath, isset($supplier['meta_description_xpath']) ? $supplier['meta_description_xpath'] : ''));
        $meta_keyword = $this->cleanText($this->xpathFirst($xpath, isset($supplier['meta_keyword_xpath']) ? $supplier['meta_keyword_xpath'] : ''));
        $manufacturer = $this->cleanManufacturerName($this->xpathFirst($xpath, $supplier['manufacturer_xpath']));
        $main_image = $this->xpathFirst($xpath, $supplier['image_xpath'], trim($supplier['image_attr']) ? trim($supplier['image_attr']) : 'src');
        $main_image = $this->normalizeUrl($main_image, $url);
        $additional_images = $this->xpathMany($xpath, $supplier['additional_images_xpath'], trim($supplier['image_attr']) ? trim($supplier['image_attr']) : 'src', $url);
        $additional_images = $this->filterProductImageUrls($additional_images, $main_image, $url, $name, 6);
        $description = $this->normalizeImportedDescription($description, $meta_description, $name);

        if (!$name) {
            return array('ok' => false, 'message' => 'Product name was not found');
        }
        if (!$price_text) {
            return array('ok' => false, 'message' => 'Product price was not found');
        }

        $supplier_price = $this->extractPrice($price_text);
        if ($supplier_price <= 0) {
            return array('ok' => false, 'message' => 'Product price is invalid: ' . $price_text);
        }

        $page_check = $this->validateParsedProductPage($xpath, $url, $supplier, $name, $sku, $price_text, $category_text);
        if (empty($page_check['ok'])) {
            return array('ok' => false, 'message' => $page_check['message']);
        }

        $prices = $this->calculatePrices($supplier_price, $supplier);
        if (!$this->isSupplierCurrencyValid(isset($supplier['currency_code']) ? $supplier['currency_code'] : '')) {
            return array('ok' => false, 'message' => 'Supplier currency is not active in OpenCart: ' . strtoupper(trim((string)$supplier['currency_code'])));
        }
        if ((float)$prices['sale_price'] <= 0) {
            return array('ok' => false, 'message' => 'Calculated sale price is invalid');
        }
        $price_guard_warning = '';
        if (isset($supplier['min_new_price']) && (float)$supplier['min_new_price'] > 0 && (float)$prices['sale_price'] < (float)$supplier['min_new_price']) {
            $price_guard_warning = 'Calculated sale price is lower than supplier minimum price guard';
        }
        if (isset($supplier['max_new_price']) && (float)$supplier['max_new_price'] > 0 && (float)$prices['sale_price'] > (float)$supplier['max_new_price']) {
            $price_guard_warning = 'Calculated sale price is higher than supplier maximum price guard';
        }
        $stock = $this->parseStock($stock_text, $supplier);
        $category = $this->resolveCategory($category_text, $supplier);
        $attributes = $this->parseAttributes($xpath, $supplier);
        $options = $this->parseOptions($xpath, $supplier);

        $data = array(
            'url' => $url,
            'name' => $name,
            'sku' => $sku,
            'ean' => $ean,
            'upc' => $upc,
            'mpn' => $mpn,
            'model' => $model_raw !== '' ? substr($model_raw, 0, 64) : $this->selectBestProductModel($sku, $ean, $upc, $mpn, $name, $url),
            'model_is_generated' => $model_raw === '',
            'jan' => $extra_identifiers['jan'],
            'isbn' => $extra_identifiers['isbn'],
            'price_text' => $price_text,
            'supplier_raw_price' => round((float)$supplier_price, 2),
            'supplier_currency' => strtoupper(trim((string)(isset($supplier['currency_code']) ? $supplier['currency_code'] : ''))),
            'base_currency' => strtoupper((string)$this->config->get('config_currency')),
            'supplier_price_base' => $prices['supplier_price'],
            'supplier_price' => $prices['supplier_price'],
            'purchase_price' => $prices['purchase_price'],
            'sale_price' => $prices['sale_price'],
            'price_guard_warning' => $price_guard_warning,
            'stock_text' => $stock_text,
            'quantity' => $stock['quantity'],
            'stock_status_id' => $stock['stock_status_id'],
            'category_text' => $category_text,
            'target_category_id' => $category['category_id'],
            'category_allowed' => $category['allowed'],
            'category_rule' => $category['rule'],
            'description' => $description,
            'meta_description' => $meta_description,
            'meta_keyword' => $meta_keyword,
            'attributes' => $attributes,
            'options' => $options,
            'manufacturer' => $manufacturer,
            'main_image_url' => $main_image,
            'additional_image_urls' => $additional_images,
            'parsed_at' => date('Y-m-d H:i:s')
        );
        if (isset($adapter_settings['source_adapter']) && $adapter_settings['source_adapter'] === 'prom') {
            $data = $this->applyPromSourceData($data, $xpath, $supplier, $url);
        }
        $exclusion = $this->checkExclusionRules($data, $supplier, $url);
        if ($exclusion['excluded']) {
            $data['is_excluded'] = 1;
            $data['exclude_reason'] = $exclusion['reason'];
            $data['category_allowed'] = false;
        } else {
            $data['is_excluded'] = 0;
            $data['exclude_reason'] = '';
        }

        return array('ok' => true, 'data' => $data, 'message' => 'OK');
    }

    protected function promCategoryPath($value) {
        $crumbs = json_decode($value, true);
        if (!is_array($crumbs)) { return ''; }
        $names = array();
        foreach ($crumbs as $crumb) {
            if (empty($crumb['url']) || !preg_match('#/(?:ua/)?g\d+#', $crumb['url']) || empty($crumb['name'])) { continue; }
            $name = preg_replace_callback('/\\\\u([0-9a-f]{4})/i', static function ($m) { return html_entity_decode('&#x'.$m[1].';', ENT_QUOTES, 'UTF-8'); }, $crumb['name']);
            $names[] = $this->cleanText($name);
        }
        return implode(' / ', $names);
    }

    protected function applyPromSourceData($data, $xpath, $supplier, $url) {
        if (strtolower((string)parse_url($data['main_image_url'], PHP_URL_HOST)) === 'images.prom.ua') {
            $data['main_image_url'] = preg_replace('/_w\d+_h\d+(?=_)/', '', $data['main_image_url']);
        }
        foreach ($data['additional_image_urls'] as &$image) {
            if (strtolower((string)parse_url($image, PHP_URL_HOST)) === 'images.prom.ua') {
                $image = preg_replace('/_w\d+_h\d+(?=_)/', '', $image);
            }
        }
        unset($image);
        $data['localized'] = array();
        $code = isset($supplier['source_language_code']) ? $supplier['source_language_code'] : '';
        if (in_array($code, array('uk-ua', 'ru-ru', 'en-gb'), true)) {
            $data['localized'][$code] = array_intersect_key($data, array_flip(array('name', 'description', 'meta_description', 'meta_keyword', 'attributes')));
        }
        $nodes = $xpath->query("//link[@rel='alternate' and (@hreflang='uk' or @hreflang='ru')]");
        $requested = array();
        foreach ($nodes as $node) {
            $alt_code = $node->getAttribute('hreflang') === 'uk' ? 'uk-ua' : 'ru-ru';
            $alt_url = $this->normalizeUrl($node->getAttribute('href'), $url);
            if ($alt_code === $code || isset($requested[$alt_code]) || !$this->isSupplierHtmlUrlAllowed($alt_url, $supplier)) { continue; }
            // Language variants must refer to the same Prom product, not another card.
            if (!preg_match('#/p(\d+)-#', $url, $primary) || !preg_match('#/p(\d+)-#', $alt_url, $alternate) || $primary[1] !== $alternate[1]) { continue; }
            $requested[$alt_code] = true;
            $fetch = $this->fetchUrl($alt_url, $supplier);
            if (empty($fetch['ok'])) { continue; }
            $alt = $this->createXPath($fetch['body']);
            if (!$alt) { continue; }
            $actual_language = $this->lower($this->xpathFirst($alt, '//html/@lang'));
            if (($alt_code === 'ru-ru' && strpos($actual_language, 'ru') !== 0) || ($alt_code === 'uk-ua' && strpos($actual_language, 'uk') !== 0)) { continue; }
            $alt_sku = $this->normalizeIdentifier($this->extractIdentifier($this->xpathFirst($alt, $supplier['sku_xpath']), array('код', 'код товара', 'код товару', 'sku', 'артикул')));
            if ($data['sku'] !== '' && $alt_sku !== $data['sku']) { continue; }
            $name = $this->cleanImportedProductName($this->xpathFirst($alt, $supplier['name_xpath']));
            if ($name === '') { continue; }
            $data['localized'][$alt_code] = array(
                'name'=>$name, 'description'=>$this->htmlByXPath($alt, $supplier['description_xpath']),
                'meta_description'=>$this->cleanText($this->xpathFirst($alt, $supplier['meta_description_xpath'] ?? '')),
                'meta_keyword'=>$this->cleanText($this->xpathFirst($alt, $supplier['meta_keyword_xpath'] ?? '')),
                'attributes'=>$this->parseAttributes($alt, $supplier)
            );
        }
        return $data;
    }


    public function autoDetectFeedRules($supplier_id) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        $supplier = $this->getSupplier((int)$supplier_id);
        if (!$supplier) {
            return array('ok' => false, 'message' => 'Supplier not found', 'fields' => array());
        }
        $source = $this->getSupplierFeedSource($supplier);
        if (empty($source['ok'])) {
            return $source + array('fields' => array());
        }
        $format = strtolower(trim((string)(isset($supplier['feed_format']) ? $supplier['feed_format'] : 'auto')));
        if ($format === 'auto' || $format === '') {
            $format = $this->detectFeedFormat($source['body'], $source['source_name']);
        }
        if ($format === 'csv') {
            return $this->autoDetectCsvFeedRules($source['body'], $source['source_name']);
        }
        return $this->autoDetectXmlFeedRules($source['body'], $source['source_name']);
    }

    protected function autoDetectCsvFeedRules($body, $source_name = '') {
        $lines = preg_split('/\r\n|\r|\n/', (string)$body);
        $lines = array_values(array_filter($lines, function($line) { return trim((string)$line) !== ''; }));
        if (count($lines) < 2) {
            return array('ok' => false, 'message' => 'CSV feed has no data rows', 'fields' => array());
        }
        $delimiter = $this->detectCsvDelimiter($lines[0]);
        $headers = str_getcsv($lines[0], $delimiter);
        $values = str_getcsv($lines[1], $delimiter);
        $sample = array();
        foreach ($headers as $i => $header) {
            $key = trim((string)$header);
            if ($key !== '') { $sample[$key] = isset($values[$i]) ? $this->cleanText($values[$i]) : ''; }
        }
        $fields = $this->buildFeedMappingCandidatesFromSample($sample, false);
        return array('ok' => true, 'message' => 'Feed mapping auto-detection completed. Review standard OpenCart fields before saving.', 'format' => 'csv', 'source' => $source_name, 'fields' => $fields);
    }

    protected function autoDetectXmlFeedRules($body, $source_name = '') {
        if (!function_exists('simplexml_load_string')) {
            return array('ok' => false, 'message' => 'PHP SimpleXML extension is required for XML/YML feeds', 'fields' => array());
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            $message = 'Cannot parse XML/YML feed';
            $errors = libxml_get_errors();
            if ($errors) { $message .= ': ' . trim($errors[0]->message); }
            libxml_clear_errors();
            return array('ok' => false, 'message' => $message, 'fields' => array());
        }
        $nodes = $this->xmlFindItemNodes($xml, '');
        if (!$nodes) {
            return array('ok' => false, 'message' => 'No product nodes found in XML/YML feed', 'fields' => array());
        }
        $node = $nodes[0];
        $sample = $this->xmlNodeToMappingSample($node);
        $fields = $this->buildFeedMappingCandidatesFromSample($sample, true);
        $item_name = $this->xmlQualifiedName($node);
        $item_path = $item_name !== '' ? '//' . $item_name : '//item';
        $fields = array('feed_item_path' => array('label' => 'Путь товара', 'target' => 'feed_item_path', 'required' => true, 'candidates' => array(array('xpath' => $item_path, 'attr' => '', 'value' => $item_path, 'source' => 'Detected XML product node', 'confidence' => 96, 'confidence_label' => $this->confidenceLabel(96))))) + $fields;
        return array('ok' => true, 'message' => 'Feed mapping auto-detection completed. Review standard OpenCart fields before saving.', 'format' => 'xml', 'source' => $source_name, 'fields' => $fields);
    }

    protected function buildFeedMappingCandidatesFromSample($sample, $xml = false) {
        $definitions = array(
            'feed_url_path' => array('label' => 'URL', 'aliases' => array('g:link','link','url','product_url')),
            'feed_sku_path' => array('label' => 'SKU / артикул', 'aliases' => array('g:mpn','mpn','vendorCode','vendor_code','sku','article','articul','code','model','g:id','id')),
            'feed_name_path' => array('label' => 'Название товара', 'aliases' => array('g:title','title','name','product_name','model')),
            'feed_price_path' => array('label' => 'Цена', 'aliases' => array('g:price','price','cost','cena','sale_price','special_price','oldprice')),
            'feed_stock_path' => array('label' => 'Наличие', 'aliases' => array('g:availability','availability','stock','available','status')),
            'feed_quantity_path' => array('label' => 'Количество', 'aliases' => array('g:quantity','quantity','qty','count','stock_quantity')),
            'feed_category_path' => array('label' => 'Категория', 'aliases' => array('g:product_type','product_type','category','category_name','categoryName','categoryId','category_id','g:google_product_category','google_product_category')),
            'feed_description_path' => array('label' => 'Описание', 'aliases' => array('g:description','description','desc','body','text')),
            'feed_manufacturer_path' => array('label' => 'Производитель', 'aliases' => array('g:brand','brand','vendor','manufacturer','producer')),
            'feed_image_path' => array('label' => 'Фото', 'aliases' => array('g:image_link','image_link','picture','image','image_url','photo','photos')),
            'feed_ean_path' => array('label' => 'EAN', 'aliases' => array('g:gtin','gtin','ean','barcode','bar_code')),
            'feed_upc_path' => array('label' => 'UPC', 'aliases' => array('upc')),
            'feed_mpn_path' => array('label' => 'MPN', 'aliases' => array('g:mpn','mpn','part_number','partnumber'))
        );
        $fields = array();
        foreach ($definitions as $field => $def) {
            $candidates = array();
            foreach ($sample as $key => $value) {
                $score = $this->feedMappingScore($key, $def['aliases'], $field, $value);
                if ($score <= 0) { continue; }
                $candidates[] = array('xpath' => $key, 'attr' => '', 'value' => $this->shortenAutoValue($value), 'source' => ($xml ? 'XML/YML field' : 'CSV column') . ': ' . $key, 'confidence' => $score, 'confidence_label' => $this->confidenceLabel($score));
            }
            usort($candidates, array($this, 'sortAutoCandidates'));
            $fields[$field] = array('label' => $def['label'], 'target' => $field, 'required' => in_array($field, array('feed_name_path','feed_price_path'), true), 'candidates' => array_slice($candidates, 0, 8));
        }
        return $fields;
    }

    protected function feedMappingScore($key, $aliases, $field, $value) {
        $lower = strtolower(trim((string)$key));
        $plain = preg_replace('/^.*:/', '', $lower);
        $base = 0;
        foreach ((array)$aliases as $alias) {
            $alias_l = strtolower((string)$alias);
            $alias_plain = preg_replace('/^.*:/', '', $alias_l);
            if ($lower === $alias_l || $lower === '@' . $alias_l) { $base = max($base, 96); }
            elseif ($plain !== '' && $plain === $alias_plain) { $base = max($base, 94); }
            elseif ($alias_plain !== '' && strpos($plain, $alias_plain) !== false) { $base = max($base, 78); }
            elseif (strpos($lower, $alias_l) !== false) { $base = max($base, 76); }
        }
        if ($field === 'feed_sku_path') {
            if ($plain === 'mpn' || $lower === 'g:mpn') { $base = max($base, 97); }
            if ($plain === 'id' || $lower === 'g:id') { $base = min($base > 0 ? $base : 72, 72); }
        }
        if ($field === 'feed_category_path') {
            if ($plain === 'product_type' || $lower === 'g:product_type') { $base = max($base, 97); }
            if ($plain === 'google_product_category' || $lower === 'g:google_product_category') { $base = min($base > 0 ? $base : 62, 62); }
        }
        if ($field === 'feed_image_path' && ($plain === 'image_link' || $lower === 'g:image_link')) {
            $base = max($base, 97);
        }
        $value = $this->cleanText($value);
        if ($field === 'feed_price_path' && $this->extractPrice($value) > 0) { $base += 8; }
        if ($field === 'feed_url_path' && preg_match('#^https?://#i', $value)) { $base += 8; }
        if ($field === 'feed_image_path' && preg_match('#^https?://#i', $value) && preg_match('/\.(jpg|jpeg|png|webp|avif|gif)(\?|$)/i', (string)parse_url($value, PHP_URL_PATH))) { $base += 8; }
        if ($field === 'feed_stock_path' && preg_match('/in_stock|out_of_stock|available|нет|немає|наяв|налич|stock/iu', $value)) { $base += 6; }
        if ($field === 'feed_description_path') {
            $len = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($len > 120) { $base += 8; }
        }
        return min(99, (int)$base);
    }

    protected function parseSupplierFeedItems($supplier, $limit = 10000) {
        $source = $this->getSupplierFeedSource($supplier);
        if (empty($source['ok'])) {
            return $source + array('items' => array());
        }
        $format = strtolower(trim((string)(isset($supplier['feed_format']) ? $supplier['feed_format'] : 'auto')));
        if (!in_array($format, array('auto', 'xml', 'yml', 'csv'), true)) {
            $format = 'auto';
        }
        if ($format === 'auto') {
            $format = $this->detectFeedFormat($source['body'], $source['source_name']);
        }
        if ($format === 'csv') {
            return $this->parseSupplierCsvFeed($supplier, $source['body'], $limit, $source['source_name']);
        }
        return $this->parseSupplierXmlFeed($supplier, $source['body'], $limit, $source['source_name']);
    }

    protected function getSupplierFeedSource($supplier) {
        $file = trim((string)(isset($supplier['feed_file']) ? $supplier['feed_file'] : ''));
        if ($file !== '') {
            $path = '';
            $storage_root = rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'supplier_sync_parser_pro';
            $storage_real = is_dir($storage_root) ? realpath($storage_root) : false;
            $relative = str_replace('\\', '/', $file);
            if (strpos($relative, 'supplier_sync_parser_pro/') === 0) {
                $relative = substr($relative, strlen('supplier_sync_parser_pro/'));
            }
            $relative = ltrim($relative, '/');
            if ($storage_real !== false && $relative !== '' && !preg_match('#(^|/)\.\.(/|$)#', $relative)) {
                $candidate = $storage_real . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $candidate_real = is_file($candidate) ? realpath($candidate) : false;
                if ($candidate_real !== false && strpos($candidate_real, $storage_real . DIRECTORY_SEPARATOR) === 0) {
                    $path = $candidate_real;
                }
            }
            if ($path !== '') {
                if (@filesize($path) > 64 * 1024 * 1024) {
                    return array('ok' => false, 'message' => 'Uploaded feed file is larger than 64 MB');
                }
                $body = file_get_contents($path);
                if ($body === false || trim((string)$body) === '') {
                    return array('ok' => false, 'message' => 'Uploaded feed file is empty or unreadable');
                }
                return array('ok' => true, 'body' => $body, 'source_name' => $file);
            }
            $this->addLog((int)$supplier['supplier_id'], 'warning', 'Feed file not found', $file, '');
        }

        $url = trim((string)(isset($supplier['feed_url']) ? $supplier['feed_url'] : ''));
        if ($url === '') {
            return array('ok' => false, 'message' => 'Feed URL or uploaded feed file is required');
        }
        if (!preg_match('#^https?://#i', $url)) {
            return array('ok' => false, 'message' => 'Feed URL must start with http:// or https://');
        }
        $fetch = $this->fetchUrl($url, $supplier);
        if (empty($fetch['ok'])) {
            return $fetch;
        }
        return array('ok' => true, 'body' => $fetch['body'], 'source_name' => $url);
    }

    protected function detectFeedFormat($body, $source_name = '') {
        $source_name = strtolower((string)$source_name);
        if (preg_match('/\.(csv|txt)(\?|$)/i', $source_name)) {
            return 'csv';
        }
        $start = ltrim((string)$body);
        if ($start !== '' && $start[0] === '<') {
            return 'xml';
        }
        return 'csv';
    }

    protected function parseSupplierXmlFeed($supplier, $body, $limit, $source_name = '') {
        if (!function_exists('simplexml_load_string')) {
            return array('ok' => false, 'message' => 'PHP SimpleXML extension is required for XML/YML feeds', 'items' => array());
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);
        if (!$xml) {
            $message = 'Cannot parse XML/YML feed';
            $errors = libxml_get_errors();
            if ($errors) {
                $message .= ': ' . trim($errors[0]->message);
            }
            libxml_clear_errors();
            return array('ok' => false, 'message' => $message, 'items' => array());
        }
        $item_path = trim((string)(isset($supplier['feed_item_path']) ? $supplier['feed_item_path'] : ''));
        $nodes = $this->xmlFindItemNodes($xml, $item_path);
        $items = array();
        $i = 0;
        foreach ($nodes as $node) {
            if ($i >= (int)$limit) {
                break;
            }
            $item = $this->normalizeFeedItemData($supplier, $this->xmlNodeToFeedRow($node, $supplier), $source_name, $i);
            if (!empty($item['ok'])) {
                $items[] = $item['data'];
                $i++;
            } else {
                $this->addLog((int)$supplier['supplier_id'], 'warning', 'Feed item skipped', isset($item['message']) ? $item['message'] : 'Invalid feed item', $source_name);
            }
        }
        return array('ok' => true, 'items' => $items, 'created' => count($items), 'message' => 'Feed parsed: ' . count($items));
    }

    protected function xmlFindItemNodes($xml, $item_path) {
        $paths = array();
        if ($item_path !== '') {
            $paths[] = $item_path;
        }
        $paths = array_merge($paths, array('//offer', '//item', '//product', '//goods', '//entry'));
        foreach ($paths as $path) {
            $xpath = trim($path);
            if ($xpath === '') {
                continue;
            }
            if (strpos($xpath, '//') !== 0 && strpos($xpath, '/') !== 0) {
                $xpath = '//' . $xpath;
            }
            $nodes = @$xml->xpath($xpath);
            if (is_array($nodes) && count($nodes)) {
                return $nodes;
            }
        }
        return array();
    }

    protected function xmlNodeToFeedRow($node, $supplier) {
        $main_image = $this->xmlValueByPath($node, isset($supplier['feed_image_path']) ? $supplier['feed_image_path'] : '', array('g:image_link','image_link','picture','image','image_url','photo'));
        $additional_images = $this->xmlValuesByPath($node, '', array('g:additional_image_link','additional_image_link'));
        $images = array();
        if ($main_image !== '') { $images[] = $main_image; }
        foreach ($additional_images as $image) {
            if ($image !== '' && !in_array($image, $images, true)) { $images[] = $image; }
        }
        return array(
            'url' => $this->xmlValueByPath($node, isset($supplier['feed_url_path']) ? $supplier['feed_url_path'] : '', array('g:link','link','url')),
            'sku' => $this->xmlValueByPath($node, isset($supplier['feed_sku_path']) ? $supplier['feed_sku_path'] : '', array('g:mpn','mpn','vendorCode','vendor_code','sku','article','articul','code','model','g:id','id')),
            'name' => $this->xmlValueByPath($node, isset($supplier['feed_name_path']) ? $supplier['feed_name_path'] : '', array('g:title','title','name','model')),
            'price' => $this->xmlValueByPath($node, isset($supplier['feed_price_path']) ? $supplier['feed_price_path'] : '', array('g:price','price','cost','cena')),
            'stock' => $this->xmlValueByPath($node, isset($supplier['feed_stock_path']) ? $supplier['feed_stock_path'] : '', array('g:availability','availability','stock','available','status')),
            'quantity' => $this->xmlValueByPath($node, isset($supplier['feed_quantity_path']) ? $supplier['feed_quantity_path'] : '', array('g:quantity','quantity','qty','count','stock_quantity')),
            'category' => $this->xmlValueByPath($node, isset($supplier['feed_category_path']) ? $supplier['feed_category_path'] : '', array('g:product_type','product_type','category','category_name','categoryName','categoryId','g:google_product_category','google_product_category')),
            'description' => $this->xmlValueByPath($node, isset($supplier['feed_description_path']) ? $supplier['feed_description_path'] : '', array('g:description','description','desc')),
            'manufacturer' => $this->xmlValueByPath($node, isset($supplier['feed_manufacturer_path']) ? $supplier['feed_manufacturer_path'] : '', array('g:brand','brand','vendor','manufacturer')),
            'image' => implode('|', $images),
            'ean' => $this->xmlValueByPath($node, isset($supplier['feed_ean_path']) ? $supplier['feed_ean_path'] : '', array('g:gtin','gtin','ean','barcode')),
            'upc' => $this->xmlValueByPath($node, isset($supplier['feed_upc_path']) ? $supplier['feed_upc_path'] : '', array('upc')),
            'mpn' => $this->xmlValueByPath($node, isset($supplier['feed_mpn_path']) ? $supplier['feed_mpn_path'] : '', array('g:mpn','mpn','part_number'))
        );
    }

    protected function xmlValueByPath($node, $path, $aliases = array()) {
        $values = $this->xmlValuesByPath($node, $path, $aliases, 1);
        return $values ? $values[0] : '';
    }

    protected function xmlValuesByPath($node, $path, $aliases = array(), $limit = 20) {
        $paths = array();
        $path = trim((string)$path);
        if ($path !== '') { $paths[] = $path; }
        foreach ((array)$aliases as $alias) {
            $alias = trim((string)$alias);
            if ($alias !== '') { $paths[] = $alias; }
        }
        $out = array();
        foreach ($paths as $candidate) {
            $candidate = trim((string)$candidate);
            if ($candidate === '') { continue; }
            $found_values = $this->xmlCandidateValues($node, $candidate, $limit);
            foreach ($found_values as $value) {
                $value = $this->cleanText($value);
                if ($value !== '' && !in_array($value, $out, true)) {
                    $out[] = $value;
                    if (count($out) >= (int)$limit) { return $out; }
                }
            }
            if ($out) { return $out; }
        }
        return $out;
    }

    protected function xmlCandidateValues($node, $candidate, $limit = 20) {
        $values = array();
        $candidate = trim((string)$candidate);
        if ($candidate === '') { return $values; }
        if ($candidate[0] === '@') {
            $attr = substr($candidate, 1);
            return array_slice($this->xmlAttributeValues($node, $attr), 0, (int)$limit);
        }
        $this->xmlRegisterNamespaces($node);
        $xp = (strpos($candidate, '/') === 0 || strpos($candidate, '.') === 0) ? $candidate : './' . $candidate;
        $found = @$node->xpath($xp);
        if (is_array($found) && count($found)) {
            foreach ($found as $item) {
                $value = $this->cleanText((string)$item);
                if ($value !== '') { $values[] = $value; }
                if (count($values) >= (int)$limit) { return $values; }
            }
        }
        foreach ($this->xmlChildValuesByName($node, $candidate) as $value) {
            if ($value !== '' && !in_array($value, $values, true)) { $values[] = $value; }
            if (count($values) >= (int)$limit) { break; }
        }
        return $values;
    }

    protected function xmlChildValuesByName($node, $candidate) {
        $candidate = trim((string)$candidate);
        $out = array();
        if ($candidate === '' || strpos($candidate, '/') !== false) { return $out; }
        $wanted_prefix = '';
        $wanted_local = $candidate;
        if (strpos($candidate, ':') !== false) {
            list($wanted_prefix, $wanted_local) = explode(':', $candidate, 2);
            $wanted_prefix = strtolower($wanted_prefix);
        }
        $wanted_local_l = strtolower($wanted_local);
        foreach ($this->xmlChildElements($node) as $child) {
            $local = strtolower($child['local']);
            $prefix = strtolower($child['prefix']);
            if ($wanted_prefix !== '' && $prefix !== $wanted_prefix) { continue; }
            if ($local !== $wanted_local_l) { continue; }
            $value = $this->cleanText((string)$child['node']);
            if ($value !== '') { $out[] = $value; }
        }
        return $out;
    }

    protected function xmlAttributeValues($node, $attr) {
        $attr = trim((string)$attr);
        $out = array();
        if ($attr === '') { return $out; }
        $attrs = $node->attributes();
        if (isset($attrs[$attr])) { $out[] = $this->cleanText((string)$attrs[$attr]); }
        foreach ((array)$node->getDocNamespaces(true) as $prefix => $uri) {
            if ($uri === '') { continue; }
            foreach ($node->attributes($uri) as $key => $value) {
                $qname = ($prefix !== '' ? $prefix . ':' : '') . $key;
                if (strtolower($qname) === strtolower($attr) || strtolower((string)$key) === strtolower($attr)) {
                    $out[] = $this->cleanText((string)$value);
                }
            }
        }
        return array_values(array_filter(array_unique($out), function($v) { return $v !== ''; }));
    }

    protected function xmlRegisterNamespaces($node) {
        if (!is_object($node) || !method_exists($node, 'getDocNamespaces') || !method_exists($node, 'registerXPathNamespace')) { return; }
        foreach ((array)$node->getDocNamespaces(true) as $prefix => $uri) {
            if ($uri === '') { continue; }
            $safe_prefix = $prefix !== '' ? $prefix : 'def';
            $node->registerXPathNamespace($safe_prefix, $uri);
        }
    }

    protected function xmlChildElements($node) {
        $items = array();
        foreach ($node->children() as $child) {
            $items[] = array('prefix' => '', 'local' => $child->getName(), 'node' => $child);
        }
        foreach ((array)$node->getDocNamespaces(true) as $prefix => $uri) {
            if ($uri === '') { continue; }
            foreach ($node->children($uri) as $child) {
                $items[] = array('prefix' => (string)$prefix, 'local' => $child->getName(), 'node' => $child);
            }
        }
        return $items;
    }

    protected function xmlQualifiedName($node) {
        $local = is_object($node) && method_exists($node, 'getName') ? $node->getName() : '';
        return $local !== '' ? $local : '';
    }

    protected function xmlNodeToMappingSample($node) {
        $sample = array();
        foreach ($node->attributes() as $key => $value) {
            $value = $this->cleanText((string)$value);
            if ($value !== '') { $sample['@' . $key] = $value; }
        }
        foreach ($this->xmlChildElements($node) as $child) {
            $prefix = (string)$child['prefix'];
            $local = (string)$child['local'];
            $key = $prefix !== '' ? $prefix . ':' . $local : $local;
            $value = $this->cleanText((string)$child['node']);
            if ($key !== '' && $value !== '') {
                if (isset($sample[$key]) && $sample[$key] !== $value) { $sample[$key] .= ' | ' . $value; } else { $sample[$key] = $value; }
                if ($prefix !== '' && !isset($sample[$local])) { $sample[$local] = $value; }
            }
            foreach ($child['node']->attributes() as $akey => $avalue) {
                $attr_value = $this->cleanText((string)$avalue);
                if ($attr_value !== '') { $sample[$key . '/@' . $akey] = $attr_value; }
            }
        }
        return $sample;
    }

    protected function parseSupplierCsvFeed($supplier, $body, $limit, $source_name = '') {
        $lines = preg_split('/\r\n|\r|\n/', (string)$body);
        $lines = array_values(array_filter($lines, function($line) { return trim((string)$line) !== ''; }));
        if (count($lines) < 2) {
            return array('ok' => false, 'message' => 'CSV feed has no data rows', 'items' => array());
        }
        $delimiter = $this->detectCsvDelimiter($lines[0]);
        $headers = str_getcsv($lines[0], $delimiter);
        $items = array();
        for ($i = 1; $i < count($lines) && count($items) < (int)$limit; $i++) {
            $cols = str_getcsv($lines[$i], $delimiter);
            $row = array();
            foreach ($headers as $idx => $header) {
                $row[trim((string)$header)] = isset($cols[$idx]) ? $cols[$idx] : '';
            }
            $item = $this->normalizeFeedItemData($supplier, $this->csvRowToFeedRow($row, $supplier), $source_name, $i);
            if (!empty($item['ok'])) {
                $items[] = $item['data'];
            } else {
                $this->addLog((int)$supplier['supplier_id'], 'warning', 'CSV item skipped', isset($item['message']) ? $item['message'] : 'Invalid CSV row', $source_name);
            }
        }
        return array('ok' => true, 'items' => $items, 'created' => count($items), 'message' => 'Feed parsed: ' . count($items));
    }

    protected function detectCsvDelimiter($line) {
        $candidates = array(';', ',', "\t", '|');
        $best = ';';
        $best_count = 0;
        foreach ($candidates as $delimiter) {
            $count = substr_count((string)$line, $delimiter);
            if ($count > $best_count) {
                $best_count = $count;
                $best = $delimiter;
            }
        }
        return $best;
    }

    protected function csvRowToFeedRow($row, $supplier) {
        return array(
            'url' => $this->csvValueByPath($row, isset($supplier['feed_url_path']) ? $supplier['feed_url_path'] : '', array('url','link')),
            'sku' => $this->csvValueByPath($row, isset($supplier['feed_sku_path']) ? $supplier['feed_sku_path'] : '', array('sku','article','articul','vendorCode','vendor_code','code','id')),
            'name' => $this->csvValueByPath($row, isset($supplier['feed_name_path']) ? $supplier['feed_name_path'] : '', array('name','title','model')),
            'price' => $this->csvValueByPath($row, isset($supplier['feed_price_path']) ? $supplier['feed_price_path'] : '', array('price','cost','cena')),
            'stock' => $this->csvValueByPath($row, isset($supplier['feed_stock_path']) ? $supplier['feed_stock_path'] : '', array('stock','available','availability','status')),
            'quantity' => $this->csvValueByPath($row, isset($supplier['feed_quantity_path']) ? $supplier['feed_quantity_path'] : '', array('quantity','qty','count','stock_quantity')),
            'category' => $this->csvValueByPath($row, isset($supplier['feed_category_path']) ? $supplier['feed_category_path'] : '', array('category','category_name','categoryName','categoryId')),
            'description' => $this->csvValueByPath($row, isset($supplier['feed_description_path']) ? $supplier['feed_description_path'] : '', array('description','desc')),
            'manufacturer' => $this->csvValueByPath($row, isset($supplier['feed_manufacturer_path']) ? $supplier['feed_manufacturer_path'] : '', array('vendor','manufacturer','brand')),
            'image' => $this->csvValueByPath($row, isset($supplier['feed_image_path']) ? $supplier['feed_image_path'] : '', array('picture','image','image_url','photo')),
            'ean' => $this->csvValueByPath($row, isset($supplier['feed_ean_path']) ? $supplier['feed_ean_path'] : '', array('ean','barcode')),
            'upc' => $this->csvValueByPath($row, isset($supplier['feed_upc_path']) ? $supplier['feed_upc_path'] : '', array('upc')),
            'mpn' => $this->csvValueByPath($row, isset($supplier['feed_mpn_path']) ? $supplier['feed_mpn_path'] : '', array('mpn','part_number'))
        );
    }

    protected function csvValueByPath($row, $path, $aliases = array()) {
        $lookup = array();
        foreach ((array)$row as $key => $value) {
            $lookup[strtolower(trim((string)$key))] = $value;
        }
        $paths = array();
        if (trim((string)$path) !== '') { $paths[] = trim((string)$path); }
        foreach ($aliases as $alias) { $paths[] = $alias; }
        foreach ($paths as $candidate) {
            $key = strtolower(trim((string)$candidate));
            if ($key !== '' && isset($lookup[$key])) {
                return $this->cleanText((string)$lookup[$key]);
            }
        }
        return '';
    }

    protected function normalizeFeedItemData($supplier, $row, $source_name, $index) {
        $name = $this->cleanImportedProductName($this->cleanText(isset($row['name']) ? $row['name'] : ''));
        $price_text = $this->cleanText(isset($row['price']) ? $row['price'] : '');
        if ($name === '') {
            return array('ok' => false, 'message' => 'Product name is empty');
        }
        $supplier_price = $this->extractPrice($price_text);
        if ($supplier_price <= 0) {
            return array('ok' => false, 'message' => 'Product price is invalid: ' . $price_text);
        }
        if (!$this->isSupplierCurrencyValid(isset($supplier['currency_code']) ? $supplier['currency_code'] : '')) {
            return array('ok' => false, 'message' => 'Supplier currency is not active in OpenCart: ' . strtoupper(trim((string)$supplier['currency_code'])));
        }
        $prices = $this->calculatePrices($supplier_price, $supplier);
        $stock_text = $this->cleanStockTextForDisplay($this->cleanText(isset($row['stock']) ? $row['stock'] : ''));
        if ($stock_text === '' && isset($row['quantity']) && $row['quantity'] !== '') {
            $stock_text = (string)$row['quantity'];
        }
        $stock = $this->parseStock($stock_text, $supplier);
        if (isset($row['quantity']) && trim((string)$row['quantity']) !== '' && preg_match('/\d+/', (string)$row['quantity'], $m)) {
            $stock['quantity'] = (int)$m[0];
        }
        $category_text = $this->cleanCategoryPathText($this->cleanText(isset($row['category']) ? $row['category'] : ''));
        $category_text = $this->normalizeSupplierCategoryTextForProduct($category_text, $name, $this->normalizeIdentifier(isset($row['sku']) ? $row['sku'] : ''), '');
        $category = $this->resolveCategory($category_text, $supplier);
        $url_raw = $this->cleanText(isset($row['url']) ? $row['url'] : '');
        $url = $url_raw !== '' ? $this->normalizeUrl($url_raw, isset($supplier['base_url']) ? $supplier['base_url'] : '') : '';
        if ($url === '') {
            $identity = trim((string)(isset($row['sku']) ? $row['sku'] : '')) . '|' . $name . '|' . (int)$index;
            $url = 'feed://' . (int)$supplier['supplier_id'] . '/' . substr(sha1($identity), 0, 24);
        }
        $main_image = $this->cleanText(isset($row['image']) ? $row['image'] : '');
        $image_urls = array();
        if ($main_image !== '') {
            foreach (preg_split('/[|;,]+/', $main_image) as $img) {
                $img = trim($img);
                if ($img !== '') {
                    $image_urls[] = $this->normalizeUrl($img, $url_raw !== '' ? $url : (isset($supplier['base_url']) ? $supplier['base_url'] : ''));
                }
            }
        }
        $main = $image_urls ? array_shift($image_urls) : '';
        $data = array(
            'url' => $url,
            'name' => $name,
            'sku' => $this->normalizeIdentifier(isset($row['sku']) ? $row['sku'] : ''),
            'jan' => $this->normalizeIdentifier(isset($row['jan']) ? $row['jan'] : ''),
            'isbn' => $this->normalizeIdentifier(isset($row['isbn']) ? $row['isbn'] : ''),
            'model_is_generated' => empty($row['model']),
            'ean' => $this->normalizeIdentifier(isset($row['ean']) ? $row['ean'] : ''),
            'upc' => $this->normalizeIdentifier(isset($row['upc']) ? $row['upc'] : ''),
            'mpn' => $this->normalizeIdentifier(isset($row['mpn']) ? $row['mpn'] : ''),
            'model' => $this->selectBestProductModel(isset($row['sku']) ? $row['sku'] : '', isset($row['ean']) ? $row['ean'] : '', isset($row['upc']) ? $row['upc'] : '', isset($row['mpn']) ? $row['mpn'] : '', $name, $url),
            'price_text' => $price_text,
            'supplier_raw_price' => round((float)$supplier_price, 2),
            'supplier_currency' => strtoupper(trim((string)(isset($supplier['currency_code']) ? $supplier['currency_code'] : ''))),
            'base_currency' => strtoupper((string)$this->config->get('config_currency')),
            'supplier_price_base' => $prices['supplier_price'],
            'supplier_price' => $prices['supplier_price'],
            'purchase_price' => $prices['purchase_price'],
            'sale_price' => $prices['sale_price'],
            'price_guard_warning' => '',
            'stock_text' => $stock_text,
            'quantity' => $stock['quantity'],
            'stock_status_id' => $stock['stock_status_id'],
            'category_text' => $category_text,
            'target_category_id' => $category['category_id'],
            'category_allowed' => $category['allowed'],
            'category_rule' => $category['rule'],
            'description' => $this->normalizeImportedDescription(isset($row['description']) ? (string)$row['description'] : '', '', $name),
            'meta_description' => '',
            'meta_keyword' => '',
            'attributes' => array(),
            'options' => array(),
            'manufacturer' => $this->cleanManufacturerName(isset($row['manufacturer']) ? $row['manufacturer'] : ''),
            'main_image_url' => $main,
            'additional_image_urls' => $image_urls,
            'feed_source' => $source_name,
            'parsed_at' => date('Y-m-d H:i:s')
        );
        if (isset($supplier['min_new_price']) && (float)$supplier['min_new_price'] > 0 && (float)$data['sale_price'] < (float)$supplier['min_new_price']) {
            $data['price_guard_warning'] = 'Calculated sale price is lower than supplier minimum price guard';
        }
        if (isset($supplier['max_new_price']) && (float)$supplier['max_new_price'] > 0 && (float)$data['sale_price'] > (float)$supplier['max_new_price']) {
            $data['price_guard_warning'] = 'Calculated sale price is higher than supplier maximum price guard';
        }
        $exclusion = $this->checkExclusionRules($data, $supplier, $url);
        if ($exclusion['excluded']) {
            $data['is_excluded'] = 1;
            $data['exclude_reason'] = $exclusion['reason'];
            $data['category_allowed'] = false;
        } else {
            $data['is_excluded'] = 0;
            $data['exclude_reason'] = '';
        }
        return array('ok' => true, 'data' => $data);
    }

    protected function isSupplierHtmlUrlAllowed($url, $supplier) {
        $url = trim((string)$url);
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        $host = $this->normalizeHost(parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        $base_host = $this->normalizeHost(parse_url(isset($supplier['base_url']) ? $supplier['base_url'] : '', PHP_URL_HOST));
        if ($base_host === '') {
            return true;
        }
        if ($host === $base_host || $host === 'www.' . $base_host || 'www.' . $host === $base_host) {
            return $this->isSupplierLanguageUrlAllowed($url, $supplier);
        }
        $settings = $this->jsonDecode(isset($supplier['settings']) ? $supplier['settings'] : '');
        $policy = isset($settings['domain_policy']) ? (string)$settings['domain_policy'] : 'strict_host';
        if ($policy === 'allow_subdomains' && $this->isSubdomainOf($host, $base_host)) {
            return $this->isSupplierLanguageUrlAllowed($url, $supplier);
        }
        if ($policy === 'allow_extra_hosts' || $policy === 'allow_subdomains') {
            $allowed_hosts = isset($settings['allowed_hosts']) && is_array($settings['allowed_hosts']) ? $settings['allowed_hosts'] : array();
            foreach ($allowed_hosts as $allowed_host) {
                $allowed_host = $this->normalizeHost($allowed_host);
                if ($allowed_host !== '' && ($host === $allowed_host || $host === 'www.' . $allowed_host || 'www.' . $host === $allowed_host)) {
                    return $this->isSupplierLanguageUrlAllowed($url, $supplier);
                }
            }
        }
        return false;
    }

    protected function isSupplierLanguageUrlAllowed($url, $supplier) {
        if ($this->isBlockedNonCatalogUrl($url)) {
            return false;
        }
        $prefixes = $this->getAllowedLanguagePrefixes($supplier);
        if (!$prefixes) {
            return true;
        }
        $path = trim((string)parse_url((string)$url, PHP_URL_PATH), '/');
        if ($path === '') {
            return true;
        }
        $segments = array_values(array_filter(explode('/', strtolower($path))));
        $first = isset($segments[0]) ? $segments[0] : '';
        $known = array('ua','uk','ru','en','de','pl','da','es','fr','it','ro','cs','sk');
        if ($first !== '' && in_array($first, $known, true) && !in_array($first, $prefixes, true)) {
            return false;
        }
        return true;
    }

    protected function getAllowedLanguagePrefixes($supplier) {
        $code = '';
        if (isset($supplier['source_language_code'])) {
            $code = strtolower(trim((string)$supplier['source_language_code']));
        }
        if ($code === '' && !empty($supplier['target_language_id'])) {
            $q = $this->db->query("SELECT code FROM `" . DB_PREFIX . "language` WHERE language_id = '" . (int)$supplier['target_language_id'] . "' LIMIT 1");
            if ($q->num_rows) {
                $code = strtolower(trim((string)$q->row['code']));
            }
        }
        if ($code === '') {
            return array();
        }
        if (strpos($code, 'uk') === 0 || strpos($code, 'ua') === 0) { return array('ua','uk'); }
        if (strpos($code, 'ru') === 0) { return array('ru'); }
        if (strpos($code, 'en') === 0) { return array('en'); }
        if (strpos($code, 'de') === 0) { return array('de'); }
        if (strpos($code, 'pl') === 0) { return array('pl'); }
        if (strpos($code, 'da') === 0 || strpos($code, 'dk') === 0) { return array('da'); }
        $short = preg_replace('/[^a-z]/', '', substr($code, 0, 2));
        return $short !== '' ? array($short) : array();
    }

    protected function isBlockedNonCatalogUrl($url) {
        $path = strtolower((string)parse_url((string)$url, PHP_URL_PATH));
        $query = strtolower((string)parse_url((string)$url, PHP_URL_QUERY));
        $full = $path . '?' . $query;
        if (preg_match('/\.(jpg|jpeg|png|gif|webp|avif|svg|css|js|pdf|zip|rar|xml|ico|woff|woff2|ttf)$/i', $path)) {
            return true;
        }
        if (preg_match('#/(cdn-cgi|email-protection|cart|checkout|account|compare|wishlist|login|register|contact|terms|privacy|delivery|dostav|payment|oplata|about|blog|news|novosti|stati|article|articles|information|info|testimonials|reviews)(/|$)#', $path)) {
            return true;
        }
        if (preg_match('/route=(information|blog|news|account|checkout|common|affiliate|extension\/module|product\/compare|product\/search)/', $query)) {
            return true;
        }
        if (strpos($full, 'manufacturer_id=') !== false || strpos($full, 'information_id=') !== false) {
            return true;
        }
        return false;
    }

    protected function normalizeHost($host) {
        $host = strtolower(trim((string)$host));
        $host = preg_replace('/:\d+$/', '', $host);
        $host = rtrim($host, '.');
        return preg_replace('/[^a-z0-9\.-]/', '', $host);
    }

    protected function isSubdomainOf($host, $base_host) {
        $host = $this->normalizeHost($host);
        $base_host = $this->normalizeHost($base_host);
        if ($host === '' || $base_host === '' || $host === $base_host) {
            return false;
        }
        return substr($host, -strlen('.' . $base_host)) === '.' . $base_host;
    }

    public function fetchUrl($url, $supplier = array()) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'created'=>0, 'updated'=>0, 'processed'=>0, 'skipped'=>0, 'errors'=>0);
        }
        return $this->fetchRemoteBody($url, $supplier, 64 * 1024 * 1024, true);
    }

    protected function fetchRemoteBody($url, $supplier = array(), $max_bytes = 67108864, $enforce_supplier_host = true) {
        $url = trim((string)$url);
        $max_bytes = max(1024, min(128 * 1024 * 1024, (int)$max_bytes));
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return array('ok' => false, 'message' => 'Invalid URL', 'http_code' => 0);
        }

        $user_agent = !empty($supplier['user_agent']) ? trim((string)$supplier['user_agent']) : 'Mozilla/5.0 SupplierSyncParserPro/1.6.3';
        $current_url = $url;
        $redirects = 0;

        while ($redirects <= 5) {
            if (!$this->isSafeRemoteUrl($current_url)) {
                return array('ok' => false, 'message' => 'Remote URL is not allowed', 'http_code' => 0);
            }
            if ($enforce_supplier_host && !empty($supplier['supplier_id']) && !$this->isSupplierHtmlUrlAllowed($current_url, $supplier) && !$this->isSupplierSitemapUrlAllowed($current_url, $supplier)) {
                return array('ok' => false, 'message' => 'URL is outside supplier domain: ' . $current_url, 'http_code' => 0);
            }

            $response = $this->requestRemoteUrlOnce($current_url, $user_agent, $max_bytes);
            if (empty($response['ok_transport'])) {
                return array('ok' => false, 'message' => $response['message'], 'http_code' => isset($response['http_code']) ? (int)$response['http_code'] : 0);
            }

            $http_code = isset($response['http_code']) ? (int)$response['http_code'] : 0;
            if (in_array($http_code, array(301, 302, 303, 307, 308), true) && !empty($response['location'])) {
                $next = $this->normalizeUrl($response['location'], $current_url);
                if ($next === '' || $next === $current_url) {
                    return array('ok' => false, 'message' => 'Invalid redirect target', 'http_code' => $http_code);
                }
                $current_url = $next;
                $redirects++;
                continue;
            }

            if ($http_code === 404 || $http_code === 410) {
                return array('ok' => false, 'message' => 'HTTP error: ' . $http_code, 'http_code' => $http_code, 'missing_confirmed' => true);
            }
            if ($http_code >= 400 || $http_code === 0) {
                return array('ok' => false, 'message' => 'HTTP error: ' . $http_code, 'http_code' => $http_code);
            }
            if (!isset($response['body']) || $response['body'] === '') {
                return array('ok' => false, 'message' => 'Fetch failed: empty response', 'http_code' => $http_code);
            }
            return array('ok' => true, 'body' => $response['body'], 'http_code' => $http_code, 'message' => 'OK', 'final_url' => $current_url);
        }

        return array('ok' => false, 'message' => 'Too many redirects', 'http_code' => 0);
    }

    protected function requestRemoteUrlOnce($url, $user_agent, $max_bytes) {
        $timeout = 25;
        if (function_exists('curl_init')) {
            $body = '';
            $location = '';
            $too_large = false;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
                curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
            }
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$location) {
                if (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }
                return strlen($header);
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$body, &$too_large, $max_bytes) {
                if (strlen($body) + strlen($chunk) > $max_bytes) {
                    $too_large = true;
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            });
            $ok = curl_exec($ch);
            $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = $ok === false ? curl_error($ch) : '';
            if ($too_large) {
                return array('ok_transport' => false, 'message' => 'Remote response exceeds safety limit', 'http_code' => $http_code);
            }
            if ($ok === false) {
                return array('ok_transport' => false, 'message' => 'Fetch failed: ' . $error, 'http_code' => $http_code);
            }
            return array('ok_transport' => true, 'body' => $body, 'http_code' => $http_code, 'location' => $location);
        }

        $context = stream_context_create(array(
            'http' => array('timeout' => $timeout, 'header' => 'User-Agent: ' . $user_agent . "\r\n", 'follow_location' => 0, 'ignore_errors' => true),
            'ssl' => array('verify_peer' => true, 'verify_peer_name' => true)
        ));
        $body = @file_get_contents($url, false, $context, 0, $max_bytes + 1);
        if ($body === false) {
            return array('ok_transport' => false, 'message' => 'Fetch failed: cannot read remote URL', 'http_code' => 0);
        }
        if (strlen($body) > $max_bytes) {
            return array('ok_transport' => false, 'message' => 'Remote response exceeds safety limit', 'http_code' => 0);
        }
        $http_code = 200;
        $location = '';
        $legacy_header_name = 'http_response_header';
        $response_headers = function_exists('http_get_last_response_headers') ? http_get_last_response_headers() : (isset($$legacy_header_name) ? $$legacy_header_name : array());
        if (is_array($response_headers)) {
            foreach ($response_headers as $header) {
                if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#i', $header, $m)) {
                    $http_code = (int)$m[1];
                } elseif (stripos($header, 'Location:') === 0) {
                    $location = trim(substr($header, 9));
                }
            }
        }
        return array('ok_transport' => true, 'body' => $body, 'http_code' => $http_code, 'location' => $location);
    }

    protected function isSafeRemoteUrl($url) {
        $parts = @parse_url((string)$url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }
        $scheme = strtolower((string)$parts['scheme']);
        if (!in_array($scheme, array('http', 'https'), true) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $host = strtolower(rtrim((string)$parts['host'], '.'));
        if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local' || substr($host, -9) === '.internal') {
            return false;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIpAddress($host);
        }
        if (preg_match('/^(?:0x[0-9a-f]+|[0-9]+|[0-9.]+)$/i', $host)) {
            return false;
        }

        $resolved = array();
        if (function_exists('gethostbynamel')) {
            $a = @gethostbynamel($host);
            if (is_array($a)) {
                $resolved = array_merge($resolved, $a);
            }
        }
        if (function_exists('dns_get_record') && defined('DNS_AAAA')) {
            $aaaa = @dns_get_record($host, DNS_AAAA);
            if (is_array($aaaa)) {
                foreach ($aaaa as $row) {
                    if (!empty($row['ipv6'])) {
                        $resolved[] = $row['ipv6'];
                    }
                }
            }
        }
        foreach (array_unique($resolved) as $ip) {
            if (!$this->isPublicIpAddress($ip)) {
                return false;
            }
        }
        return true;
    }

    protected function isPublicIpAddress($ip) {
        return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    protected function createXPath($html) {
        if (!class_exists('DOMDocument')) {
            return false;
        }
        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $html = $this->convertToUtf8($html);
        $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        if (!$loaded) {
            return false;
        }
        return new DOMXPath($dom);
    }

    protected function convertToUtf8($html) {
        if (function_exists('mb_detect_encoding') && function_exists('mb_convert_encoding')) {
            $encoding = mb_detect_encoding($html, 'UTF-8, Windows-1251, ISO-8859-1', true);
            if ($encoding && strtoupper($encoding) !== 'UTF-8') {
                $html = mb_convert_encoding($html, 'UTF-8', $encoding);
            }
        }
        return $html;
    }

    protected function xpathFirst($xpath, $expression, $attr = '') {
        $expression = trim((string)$expression);
        if ($expression === '') {
            return '';
        }
        $nodes = $xpath->query($expression);
        if (!$nodes || !$nodes->length) {
            return '';
        }
        return $this->nodeValue($nodes->item(0), $attr);
    }

    protected function xpathMany($xpath, $expression, $attr = '', $base_url = '') {
        $expression = trim((string)$expression);
        $values = array();
        if ($expression === '') {
            return $values;
        }
        $nodes = $xpath->query($expression);
        if (!$nodes) {
            return $values;
        }
        foreach ($nodes as $node) {
            $value = $this->nodeValue($node, $attr);
            if ($base_url) {
                $value = $this->normalizeUrl($value, $base_url);
            }
            if ($value && !in_array($value, $values, true)) {
                $values[] = $value;
            }
        }
        return $values;
    }

    protected function htmlByXPath($xpath, $expression) {
        $expression = trim((string)$expression);
        if ($expression === '') {
            return '';
        }
        $nodes = $xpath->query($expression);
        if (!$nodes || !$nodes->length) {
            return '';
        }
        $node = $nodes->item(0);
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return trim($html);
    }

    protected function nodeValue($node, $attr = '') {
        $attr = trim((string)$attr);
        if ($attr && $node instanceof DOMElement && $node->hasAttribute($attr)) {
            return trim($node->getAttribute($attr));
        }
        return trim($node->textContent);
    }

    protected function extractPrice($text) {
        $text = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
        $text = str_replace(array("\xc2\xa0", "\xe2\x80\xaf", "\t", "\r", "\n"), ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $candidates = array();

        $money_pattern = '/(?:(?:ціна|цена|price|вартість|стоимость)\s*[:\-]?\s*)?([-]?\d[\d\s,\.\'’`]{0,24})\s*(грн\.?|uah|₴|eur|€|usd|\$)/iu';
        if (preg_match_all($money_pattern, $text, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($m as $match) {
                $raw = $match[1][0];
                $offset = isset($match[0][1]) ? (int)$match[0][1] : 0;
                $value = $this->normalizePriceNumber($raw);
                if ($value > 0) {
                    $score = 80;
                    $near = $this->lower(substr($text, max(0, $offset - 40), 80));
                    if (preg_match('/ціна|цена|price|вартість|стоимость/u', $near)) { $score += 20; }
                    if ($value > 1000000) { $score -= 50; }
                    $candidates[] = array('value' => $value, 'score' => $score, 'raw' => $raw);
                }
            }
        }

        if (preg_match_all('/[-]?\d[\d\s,\.\'’`]{0,24}/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $match) {
                $raw = trim($match[0]);
                $offset = (int)$match[1];
                $digits = preg_replace('/\D/u', '', $raw);
                if (strlen($digits) < 1) { continue; }
                $value = $this->normalizePriceNumber($raw);
                if ($value <= 0) { continue; }
                $near = $this->lower(substr($text, max(0, $offset - 45), 100));
                $score = 10;
                if (preg_match('/ціна|цена|price|вартість|стоимость|грн|uah|₴|eur|€|usd|\$/u', $near)) { $score += 50; }
                if (strpos($raw, ',') !== false || strpos($raw, '.') !== false || preg_match('/\s\d{3}/u', $raw)) { $score += 15; }
                if (preg_match('/^\d{1,2}$/', $digits)) { $score -= 20; }
                if ($value > 1000000) { $score -= 80; }
                $candidates[] = array('value' => $value, 'score' => $score, 'raw' => $raw);
            }
        }

        if (!$candidates) {
            return 0;
        }
        usort($candidates, function($a, $b) {
            if ($a['score'] == $b['score']) {
                return $a['value'] <=> $b['value'];
            }
            return $b['score'] <=> $a['score'];
        });
        return round((float)$candidates[0]['value'], 2);
    }

    protected function normalizePriceNumber($raw) {
        $raw = trim((string)$raw);
        if ($raw === '') { return 0; }
        $negative = (strpos($raw, '-') === 0);
        $raw = preg_replace('/[\s\'’`]/u', '', $raw);
        $raw = str_replace('-', '', $raw);
        $last_comma = strrpos($raw, ',');
        $last_dot = strrpos($raw, '.');
        $decimal_separator = '';
        if ($last_comma !== false || $last_dot !== false) {
            if ($last_comma !== false && $last_dot !== false) {
                $decimal_separator = ($last_comma > $last_dot) ? ',' : '.';
            } else {
                $sep = ($last_comma !== false) ? ',' : '.';
                $pos = ($sep === ',') ? $last_comma : $last_dot;
                $after = strlen($raw) - $pos - 1;
                $before = $pos;
                $count_sep = substr_count($raw, $sep);
                if ($after > 0 && $after <= 2) {
                    $decimal_separator = $sep;
                } elseif ($after === 3 && $before <= 3 && $count_sep === 1) {
                    $decimal_separator = '';
                } elseif ($count_sep > 1 && $after === 3) {
                    $decimal_separator = '';
                } elseif ($after === 4 && $count_sep === 1 && $before <= 5) {
                    $decimal_separator = $sep;
                } else {
                    $decimal_separator = '';
                }
            }
        }
        if ($decimal_separator !== '') {
            $thousand_separator = ($decimal_separator === ',') ? '.' : ',';
            $raw = str_replace($thousand_separator, '', $raw);
            $raw = str_replace($decimal_separator, '.', $raw);
        } else {
            $raw = str_replace(array(',', '.'), '', $raw);
        }
        $raw = preg_replace('/[^0-9\.]/', '', $raw);
        if ($raw === '' || $raw === '.') { return 0; }
        $value = (float)$raw;
        return $negative ? -$value : $value;
    }

    protected function calculatePrices($supplier_price, $supplier) {
        $supplier_price = round((float)$supplier_price, 2);
        $supplier_price_base = round($this->convertSupplierCurrencyToBase($supplier_price, isset($supplier['currency_code']) ? $supplier['currency_code'] : ''), 2);
        $discount = (float)$supplier['discount_percent'];
        $markup = (float)$supplier['markup_percent'];
        $min_margin = isset($supplier['min_margin_percent']) ? (float)$supplier['min_margin_percent'] : 0;
        $mode = !empty($supplier['price_formula_mode']) ? $supplier['price_formula_mode'] : 'retail_discount_markup';

        if ($mode === 'same_as_supplier') {
            $purchase_price = $supplier_price_base;
            $sale_price = $supplier_price_base;
        } elseif ($mode === 'purchase_markup') {
            $purchase_price = $supplier_price_base;
            $sale_price = $purchase_price + ($purchase_price * $markup / 100);
        } else {
            $purchase_price = $supplier_price_base;
            if ($discount > 0) {
                $purchase_price = $purchase_price - ($purchase_price * $discount / 100);
            }
            $sale_price = $purchase_price;
            if ($markup > 0) {
                $sale_price = $sale_price + ($sale_price * $markup / 100);
            }
        }

        if ($min_margin > 0) {
            $min_sale = $purchase_price + ($purchase_price * $min_margin / 100);
            if ($sale_price < $min_sale) {
                $sale_price = $min_sale;
            }
        }

        $rounding = trim($supplier['rounding_mode']);
        if ($rounding === 'integer') {
            $sale_price = round($sale_price, 0);
        } elseif ($rounding === 'up_integer') {
            $sale_price = ceil($sale_price);
        } elseif ($rounding === 'two') {
            $sale_price = round($sale_price, 2);
        }
        return array('supplier_price' => round($supplier_price_base, 2), 'purchase_price' => round($purchase_price, 2), 'sale_price' => round($sale_price, 2));
    }

    protected function parseStock($stock_text, $supplier) {
        $stock_text = $this->lower($this->cleanText($stock_text));
        $quantity = 0;
        $stock_status_id = (int)$supplier['default_stock_status_id'];
        $settings = $this->jsonDecode($supplier['settings']);

        if (!empty($settings['stock_map']) && is_array($settings['stock_map'])) {
            // Specific phrases must precede substrings, irrespective of UI order.
            uksort($settings['stock_map'], static function ($a, $b) { return strlen($b) <=> strlen($a); });
            foreach ($settings['stock_map'] as $needle => $map) {
                if ($needle !== '' && $this->strpos($stock_text, $this->lower($needle)) !== false) {
                    return array('quantity' => isset($map['quantity']) ? (int)$map['quantity'] : 0, 'stock_status_id' => isset($map['stock_status_id']) ? (int)$map['stock_status_id'] : $stock_status_id);
                }
            }
        }

        if (preg_match('/(?:нет|не)\s+в\s+наличии|нема[є]?\s+в\s+наявност[іi]|відсутн|отсутств|not\s+available|out\s+of\s+stock|schema\.org\/outofstock|unavailable|sold\s+out|не\s+доступ|^false$/u', $stock_text)) {
            $quantity = 0;
        } elseif (preg_match('/^(?:залишок|остаток|quantity|stock)?\s*[:=]?\s*[><≥≤~]?\s*(\d+)\s*(?:шт\.?|pcs\.?|од\.?|units)?\s*$/u', $stock_text, $m)) {
            $quantity = (int)$m[1];
        } elseif (preg_match('/в наявності|в наличии|in stock|available|lager|på lager|есть|готов[оийа]*\s+(?:до\s+відправки|к\s+отправке)|schema\.org\/instock|^true$/u', $stock_text)) {
            $quantity = isset($settings['in_stock_quantity']) ? max(0, min(2147483647, (int)$settings['in_stock_quantity'])) : 100;
        } else {
            $keep = !isset($settings['unknown_stock_policy']) || $settings['unknown_stock_policy'] !== 'zero';
            return array('quantity' => $keep ? null : 0, 'stock_status_id' => $keep ? null : $stock_status_id);
        }

        return array('quantity' => $quantity, 'stock_status_id' => $stock_status_id);
    }

    protected function resolveCategory($category_text, $supplier) {
        $default_category_id = (int)$supplier['default_category_id'];
        $force_category_id = isset($supplier['force_new_category_id']) ? (int)$supplier['force_new_category_id'] : 0;
        $result = array('category_id' => $default_category_id, 'allowed' => true, 'rule' => 'default');
        $settings = $this->jsonDecode($supplier['settings']);

        if (!empty($settings['category_map']) && is_array($settings['category_map'])) {
            $category_lc = $this->lower($category_text);
            foreach ($settings['category_map'] as $rule) {
                $needle = isset($rule['needle']) ? trim($rule['needle']) : '';
                if ($needle === '') {
                    continue;
                }
                if ($this->strpos($category_lc, $this->lower($needle)) !== false) {
                    $result = array(
                        'category_id' => isset($rule['category_id']) ? (int)$rule['category_id'] : $default_category_id,
                        'allowed' => !empty($rule['allow']),
                        'rule' => $needle
                    );
                    break;
                }
            }
        }

        if ($force_category_id > 0 && !empty($result['allowed'])) {
            $result['category_id'] = $force_category_id;
            $result['rule'] = $result['rule'] === 'default' ? 'force_new_category' : ($result['rule'] . ' + force_new_category');
        }

        return $result;
    }


    protected function cleanCategoryPathText($text) {
        $text = $this->cleanText($text);
        if ($text === '') { return ''; }
        $parts = preg_split('/\s*(?:\/|>|»|›|\||→|\n)\s*/u', $text);
        $out = array();
        $seen = array();
        foreach ($parts as $part) {
            $part = $this->cleanText($part);
            if ($part === '') { continue; }
            $part = $this->collapseRepeatedWords($part);
            $key = $this->lower($part);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $part;
            }
        }
        return $this->collapseRepeatedWords($out ? implode(' / ', $out) : $text);
    }

    protected function cleanStockTextForDisplay($text) {
        $text = $this->cleanText($text);
        if ($text === '') { return ''; }
        $text = preg_replace('/^\s*(наявність|наличие|availability|stock)\s*[:\-–—]\s*/iu', '', $text);
        $text = preg_replace('/\s+\/\s+/u', ' / ', $text);
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    protected function normalizeSupplierCategoryTextForProduct($category_text, $product_name, $sku = '', $fallback = '') {
        $category_text = $this->cleanCategoryPathText($category_text);
        $fallback = $this->cleanCategoryPathText($fallback);
        $product_name = $this->cleanImportedProductName($product_name);
        $sku = $this->normalizeIdentifier($sku);
        if ($this->isInvalidSupplierCategoryTextForProduct($category_text, $product_name, $sku)) {
            if (!$this->isInvalidSupplierCategoryTextForProduct($fallback, $product_name, $sku)) {
                return $fallback;
            }
            return '';
        }
        return $category_text;
    }

    protected function isInvalidSupplierCategoryTextForProduct($category_text, $product_name, $sku = '') {
        $category_text = $this->cleanText($category_text);
        if ($category_text === '') { return false; }
        $product_name = $this->cleanImportedProductName($product_name);
        $category_norm = $this->normalizeComparableName($category_text);
        $product_norm = $this->normalizeComparableName($product_name);
        if ($category_norm !== '' && $product_norm !== '') {
            // A category often prefixes a legitimate product title (for example Mini AZS).
            if ($category_norm === $product_norm || strpos($category_norm, $product_norm) !== false) {
                return true;
            }
            if ($this->calculateNameSimilarityPercent($category_norm, $product_norm) >= 82) {
                return true;
            }
        }
        $sku = $this->normalizeIdentifier($sku);
        if ($sku !== '' && $this->strpos($this->lower($category_text), $this->lower($sku)) !== false) {
            return true;
        }
        return false;
    }

    protected function validateParsedProductPage($xpath, $url, $supplier, $name, $sku, $price_text, $category_text = '') {
        if ($this->isCurrentPageLikelyProduct($xpath, $url, $supplier, $name, $price_text, $sku)) {
            return array('ok' => true, 'message' => 'OK');
        }
        return array('ok' => false, 'message' => 'URL looks like a category/list page, not a product page. It was skipped to prevent category duplicates in preview.');
    }

    protected function isCurrentPageLikelyProduct($xpath, $url, $supplier, $name, $price_text, $sku = '') {
        $name = $this->cleanImportedProductName($name);
        $sku = $this->normalizeIdentifier($sku);
        if ($name === '' || $this->extractPrice($price_text) <= 0) {
            return false;
        }
        if ($this->hasProductStructuredSignals($xpath)) {
            return true;
        }
        $product_link_count = count($this->discoverProductLinkItemsOnPage($xpath, $url, $supplier, 20));
        $category_title = $this->looksLikeCategoryTitle($name);
        $product_url = $this->looksLikeProductUrl($url);
        $list_url = $this->looksLikeListUrl($url) || $this->looksLikeCategoryUrl($url);
        if ($category_title && $product_link_count >= 2 && $sku === '') {
            return false;
        }
        if ($list_url && !$product_url && $product_link_count >= 2 && $sku === '') {
            return false;
        }
        if ($product_link_count >= 4 && $sku === '' && !$this->hasStrongSingleProductSignals($xpath)) {
            return false;
        }
        if ($product_url && $product_link_count >= 2 && $sku === '' && !$this->hasStrongSingleProductSignals($xpath) && !$this->hasProductStructuredSignals($xpath)) {
            return false;
        }
        return $product_url || $sku !== '' || $this->hasStrongSingleProductSignals($xpath) || $this->hasProductStructuredSignals($xpath);
    }

    protected function hasProductStructuredSignals($xpath) {
        $og_nodes = $xpath->query("//meta[@property='og:type' and contains(translate(@content,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'product')]");
        if ($og_nodes && $og_nodes->length) { return true; }
        $schema_nodes = $xpath->query("//*[@itemscope and contains(translate(@itemtype,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'schema.org/product')]");
        if ($schema_nodes && $schema_nodes->length === 1) { return true; }
        if ($schema_nodes && $schema_nodes->length > 1) { return false; }
        $single_item_queries = array(
            "//*[@itemprop='sku']",
            "//*[@itemprop='mpn']",
            "//*[@itemprop='gtin']",
            "//*[@itemprop='gtin13']"
        );
        foreach ($single_item_queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length === 1) { return true; }
            if ($nodes && $nodes->length > 1) { return false; }
        }
        return false;
    }

    protected function hasStrongSingleProductSignals($xpath) {
        $ci = "translate(@class,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $ii = "translate(@id,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
        $queries = array(
            "//form[contains($ci,'product') or contains($ii,'product')]//input[@name='quantity' or @name='product_id']",
            "//*[contains($ci,'product-info') or contains($ci,'product-detail') or contains($ci,'product-page') or contains($ii,'product-info') or contains($ii,'product-detail')]",
            "//*[contains($ci,'product-info') or contains($ci,'product-detail') or contains($ci,'product-page') or contains($ii,'product-info') or contains($ii,'product-detail')]//button[contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'купить') or contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'купити') or contains(translate(.,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz'),'add to cart')]"
        );
        foreach ($queries as $query) {
            $nodes = $xpath->query($query);
            if ($nodes && $nodes->length) { return true; }
        }
        return false;
    }

    protected function looksLikeCategoryUrl($url) {
        $path = strtolower((string)parse_url((string)$url, PHP_URL_PATH));
        if ($path === '' || $path === '/') { return true; }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!$segments) { return false; }
        $last = end($segments);
        if (preg_match('/\d/u', $last)) { return false; }
        if (preg_match('#/(category|catalog|katalog|product_list|products|shop|tovary|goods)(/|$)#', $path)) { return true; }
        if (preg_match('/(nasos|nasosy|pump|pumpy|filtr|filtry|filter|filters|shlang|shlangi|hose|hoses|schetchik|schetchiki|lichilnyk|lichylnyky|meter|meters|pistolet|pistolety|komplekt|komplekty|bak|baki|emkost|emkosti|accessories|aksessuary|zapchasti|oborudovanie|catalog)/iu', $last) && count($segments) <= 4) {
            return true;
        }
        return false;
    }

    protected function collapseRepeatedWords($text) {
        $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
        if ($text === '') { return ''; }
        $words = preg_split('/\s+/u', $text);
        $max = (int)floor(count($words) / 2);
        for ($size = $max; $size >= 1; $size--) {
            for ($i = 0; $i + 2 * $size <= count($words); $i++) {
                $a = $this->lower(implode(' ', array_slice($words, $i, $size)));
                $b = $this->lower(implode(' ', array_slice($words, $i + $size, $size)));
                if ($a === $b) {
                    array_splice($words, $i + $size, $size);
                    $i = max(-1, $i - 1);
                }
            }
        }
        return trim(implode(' ', $words));
    }

    protected function isSupplierCurrencyValid($currency_code) {
        $currency_code = strtoupper(trim((string)$currency_code));
        $base = strtoupper((string)$this->config->get('config_currency'));
        if ($currency_code === '' || $currency_code === $base) {
            return true;
        }
        $q = $this->db->query("SELECT currency_id FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($currency_code) . "' AND status = '1' AND value > 0 LIMIT 1");
        return $q->num_rows > 0;
    }

    protected function convertSupplierCurrencyToBase($amount, $currency_code) {
        $amount = (float)$amount;
        $currency_code = strtoupper(trim((string)$currency_code));
        $base = strtoupper((string)$this->config->get('config_currency'));
        if ($amount <= 0 || $currency_code === '' || $currency_code === $base) {
            return $amount;
        }

        $q = $this->db->query("SELECT value FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($currency_code) . "' AND status = '1' LIMIT 1");
        $b = $this->db->query("SELECT value FROM `" . DB_PREFIX . "currency` WHERE code = '" . $this->db->escape($base) . "' AND status = '1' LIMIT 1");
        $source_value = ($q->num_rows && (float)$q->row['value'] > 0) ? (float)$q->row['value'] : 0;
        $base_value = ($b->num_rows && (float)$b->row['value'] > 0) ? (float)$b->row['value'] : 1;

        if ($source_value > 0) {
            return ($amount / $source_value) * $base_value;
        }

        return $amount;
    }

    protected function parseAttributes($xpath, $supplier) {
        $rows = array();
        $row_expr = isset($supplier['attribute_row_xpath']) ? trim($supplier['attribute_row_xpath']) : '';
        if ($row_expr === '') { return $rows; }
        $nodes = $xpath->query($row_expr);
        if (!$nodes) { return $rows; }
        foreach ($nodes as $node) {
            $name = $this->cleanText($this->queryNodeValue($xpath, $node, isset($supplier['attribute_name_xpath']) ? $supplier['attribute_name_xpath'] : ''));
            $value = $this->cleanText($this->queryNodeValue($xpath, $node, isset($supplier['attribute_value_xpath']) ? $supplier['attribute_value_xpath'] : ''));
            if ($name === '' && $value === '') {
                $text = $this->cleanText($node->textContent);
                if (strpos($text, ':') !== false) {
                    list($name, $value) = array_map('trim', explode(':', $text, 2));
                }
            }
            if ($name !== '' && $value !== '') { $rows[] = array('name' => $name, 'value' => $value); }
        }
        return $rows;
    }

    protected function parseOptions($xpath, $supplier) {
        $rows = array();
        $row_expr = isset($supplier['option_row_xpath']) ? trim($supplier['option_row_xpath']) : '';
        if ($row_expr === '') { return $rows; }
        $nodes = $xpath->query($row_expr);
        if (!$nodes) { return $rows; }
        foreach ($nodes as $node) {
            $name = $this->cleanText($this->queryNodeValue($xpath, $node, isset($supplier['option_name_xpath']) ? $supplier['option_name_xpath'] : ''));
            $value = $this->cleanText($this->queryNodeValue($xpath, $node, isset($supplier['option_value_xpath']) ? $supplier['option_value_xpath'] : ''));
            if ($name === '' && $value === '') {
                $text = $this->cleanText($node->textContent);
                if (strpos($text, ':') !== false) {
                    list($name, $value) = array_map('trim', explode(':', $text, 2));
                }
            }
            if ($name !== '' && $value !== '') { $rows[] = array('name' => $name, 'value' => $value); }
        }
        return $rows;
    }

    protected function queryNodeValue($xpath, $node, $expression) {
        $expression = trim((string)$expression);
        if ($expression === '') { return ''; }
        $nodes = $xpath->query($expression, $node);
        if (!$nodes || !$nodes->length) { return ''; }
        return $this->nodeValue($nodes->item(0));
    }


    protected function prepareProductMatch($row, $source) {
        if (!$row || empty($row['product_id'])) {
            return array('product_id' => 0, 'source' => '', 'name' => '', 'price' => 0, 'quantity' => 0, 'stock_status_id' => 0);
        }
        return array(
            'product_id' => (int)$row['product_id'],
            'source' => (string)$source,
            'name' => isset($row['name']) ? (string)$row['name'] : '',
            'price' => isset($row['price']) ? (float)$row['price'] : 0,
            'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 0,
            'stock_status_id' => isset($row['stock_status_id']) ? (int)$row['stock_status_id'] : 0
        );
    }

    protected function prepareMatchPreview($match) {
        if (!is_array($match) || empty($match['product_id'])) {
            return array('product_id' => 0, 'source' => '', 'name' => '', 'price' => 0, 'quantity' => 0, 'stock_status_id' => 0);
        }
        return array(
            'product_id' => (int)$match['product_id'],
            'source' => isset($match['source']) ? $match['source'] : '',
            'name' => isset($match['name']) ? $match['name'] : '',
            'price' => isset($match['price']) ? (float)$match['price'] : 0,
            'quantity' => isset($match['quantity']) ? (int)$match['quantity'] : 0,
            'stock_status_id' => isset($match['stock_status_id']) ? (int)$match['stock_status_id'] : 0
        );
    }

    protected function findProduct($supplier_id, $sku, $url, $name, $ean = '', $upc = '', $mpn = '', $supplier = array(), $data = array()) {
        $supplier_id = (int)$supplier_id;
        $sku = $this->normalizeIdentifier($sku);
        $ean = $this->normalizeIdentifier($ean);
        $upc = $this->normalizeIdentifier($upc);
        $mpn = $this->normalizeIdentifier($mpn);
        $url = trim((string)$url);
        $language_id = $this->getLanguageId();

        if ($supplier_id > 0 && $url !== '') {
            $q = $this->db->query("SELECT p.product_id, p.price, p.quantity, p.stock_status_id, pd.name FROM `" . DB_PREFIX . "ccp_ssp_product_link` l LEFT JOIN `" . DB_PREFIX . "product` p ON (l.product_id = p.product_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE l.supplier_id = '" . (int)$supplier_id . "' AND l.supplier_product_url = '" . $this->db->escape($url) . "' AND l.product_id > 0 LIMIT 2");
            if ($q->num_rows === 1 && !empty($q->row['product_id'])) {
                return $this->prepareProductMatch($q->row, 'supplier_url_link');
            }
            if ($q->num_rows > 1) {
                return $this->prepareNoAutoMatch('multiple_supplier_url_links');
            }
        }

        if ($supplier_id > 0 && $sku !== '') {
            $q = $this->db->query("SELECT p.product_id, p.price, p.quantity, p.stock_status_id, pd.name FROM `" . DB_PREFIX . "ccp_ssp_product_link` l LEFT JOIN `" . DB_PREFIX . "product` p ON (l.product_id = p.product_id) LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE l.supplier_id = '" . (int)$supplier_id . "' AND l.supplier_sku = '" . $this->db->escape($sku) . "' AND l.product_id > 0 LIMIT 2");
            if ($q->num_rows === 1 && !empty($q->row['product_id'])) {
                return $this->prepareProductMatch($q->row, 'supplier_sku_link');
            }
            if ($q->num_rows > 1) {
                return $this->prepareNoAutoMatch('multiple_supplier_sku_links');
            }
        }

        $settings = $this->jsonDecode(isset($supplier['settings']) ? $supplier['settings'] : '');
        $allowed = array('sku','model','upc','ean','jan','isbn','mpn');
        $source_field = isset($settings['match_source']) ? (string)$settings['match_source'] : 'auto';
        $target_field = isset($settings['match_target']) ? (string)$settings['match_target'] : 'sku';
        $values = array('sku'=>$sku, 'ean'=>$ean, 'upc'=>$upc, 'mpn'=>$mpn);
        foreach (array('model','jan','isbn') as $field) {
            $values[$field] = isset($data[$field]) ? $this->normalizeIdentifier($data[$field]) : '';
        }
        // Generated model fallbacks are not evidence for automatic matching.
        if (isset($data['model_is_generated']) && $data['model_is_generated']) { $values['model'] = ''; }
        if ($source_field !== 'auto') {
            if (!in_array($source_field, $allowed, true) || !in_array($target_field, $allowed, true)) {
                return $this->prepareNoAutoMatch('invalid_match_mapping');
            }
            $identifier_map = array($target_field => $values[$source_field]);
        } else {
            $identifier_map = array('sku'=>$sku,'model'=>$values['model'] !== '' ? $values['model'] : $sku,'ean'=>$ean,'upc'=>$upc,'mpn'=>$mpn,'jan'=>$values['jan'],'isbn'=>$values['isbn']);
        }
        $matches = array();
        foreach ($identifier_map as $field => $value) {
            if ($value === '') { continue; }
            $q = $this->db->query("SELECT p.product_id, p.price, p.quantity, p.stock_status_id, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE TRIM(p.`" . $this->db->escape($field) . "`) = '" . $this->db->escape($value) . "' LIMIT 2");
            if ($q->num_rows === 1) {
                $matches[(int)$q->row['product_id']] = $this->prepareProductMatch($q->row, 'product_' . $field);
            }
            if ($q->num_rows > 1) {
                return $this->prepareNoAutoMatch('multiple_product_' . $field);
            }
        }
        if (count($matches) > 1) { return $this->prepareNoAutoMatch('conflicting_product_identifiers'); }
        if ($matches) { return reset($matches); }
        if ($source_field !== 'auto') { return $this->prepareNoAutoMatch('selected_identifier_not_found_manual'); }

        $has_strong_identifier = (bool)array_filter($values, static function ($value) { return $value !== ''; });
        if ($has_strong_identifier) {
            return $this->prepareNoAutoMatch('identifier_not_found_manual');
        }

        $clean_name = $this->cleanImportedProductName($name);
        if ($clean_name !== '') {
            $q = $this->db->query("SELECT p.product_id, p.price, p.quantity, p.stock_status_id, pd.name FROM `" . DB_PREFIX . "product_description` pd LEFT JOIN `" . DB_PREFIX . "product` p ON (pd.product_id = p.product_id) WHERE pd.language_id = '" . (int)$language_id . "' AND LOWER(TRIM(pd.name)) = '" . $this->db->escape($this->lower($clean_name)) . "' LIMIT 2");
            if ($q->num_rows === 1) {
                return $this->prepareProductMatch($q->row, 'product_name_exact');
            }
            if ($q->num_rows > 1) {
                return $this->prepareNoAutoMatch('multiple_product_name_exact');
            }
        }

        return $this->prepareNoAutoMatch('');
    }

    protected function prepareNoAutoMatch($source = '') {
        return array('product_id' => 0, 'source' => (string)$source, 'name' => '', 'price' => 0, 'quantity' => 0, 'stock_status_id' => 0);
    }

    protected function addMatchCandidatesToParsedData($supplier_id, $url, $data) {
        if (!is_array($data)) { $data = array(); }
        $candidates = $this->findProductCandidates(
            isset($data['sku']) ? $data['sku'] : '',
            isset($data['name']) ? $data['name'] : '',
            isset($data['ean']) ? $data['ean'] : '',
            isset($data['upc']) ? $data['upc'] : '',
            isset($data['mpn']) ? $data['mpn'] : '',
            90,
            5
        );
        if ($candidates) {
            $data['match_candidates'] = $candidates;
            $data['match_candidate_threshold'] = 90;
        }
        return $data;
    }

    protected function findProductCandidates($sku, $name, $ean = '', $upc = '', $mpn = '', $threshold = 90, $limit = 5) {
        $language_id = $this->getLanguageId();
        $limit = max(1, min(20, (int)$limit));
        $threshold = max(60, min(100, (int)$threshold));
        $sku = $this->normalizeIdentifier($sku);
        $ean = $this->normalizeIdentifier($ean);
        $upc = $this->normalizeIdentifier($upc);
        $mpn = $this->normalizeIdentifier($mpn);
        $name = $this->cleanImportedProductName($name);
        $found = array();

        $identifier_map = array('sku' => $sku, 'model' => $sku, 'ean' => $ean, 'upc' => $upc, 'mpn' => $mpn);
        foreach ($identifier_map as $field => $value) {
            if ($value === '') { continue; }
            $q = $this->db->query("SELECT p.product_id, p.model, p.sku, p.ean, p.upc, p.mpn, p.price, p.quantity, p.stock_status_id, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE TRIM(p.`" . $this->db->escape($field) . "`) = '" . $this->db->escape($value) . "' LIMIT 20");
            foreach ($q->rows as $row) {
                $this->addCandidateRow($found, $row, 100, 'identifier_' . $field);
            }
        }

        $needle = $this->normalizeComparableProductName($name);
        if ($needle !== '') {
            $tokens = $this->extractComparableTokens($needle, 8);
            if ($tokens) {
                $where = array();
                foreach ($tokens as $token) {
                    $token = $this->db->escape($token);
                    $where[] = "pd.name LIKE '%" . $token . "%'";
                    $where[] = "p.model LIKE '%" . $token . "%'";
                    $where[] = "p.sku LIKE '%" . $token . "%'";
                }
                $q = $this->db->query("SELECT p.product_id, p.model, p.sku, p.ean, p.upc, p.mpn, p.price, p.quantity, p.stock_status_id, pd.name FROM `" . DB_PREFIX . "product` p LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . (int)$language_id . "') WHERE (" . implode(' OR ', $where) . ") ORDER BY p.product_id DESC LIMIT 120");
                foreach ($q->rows as $row) {
                    $score = $this->calculateNameSimilarityPercent($needle, $this->normalizeComparableProductName(isset($row['name']) ? $row['name'] : ''));
                    if ($score >= $threshold) {
                        $this->addCandidateRow($found, $row, $score, 'name_similarity_' . (int)$score);
                    }
                }
            }
        }

        usort($found, array($this, 'sortProductCandidates'));
        return array_slice(array_values($found), 0, $limit);
    }

    protected function addCandidateRow(&$found, $row, $score, $reason) {
        if (empty($row['product_id'])) { return; }
        $product_id = (int)$row['product_id'];
        if (isset($found[$product_id]) && (int)$found[$product_id]['similarity'] >= (int)$score) { return; }
        $found[$product_id] = array(
            'product_id' => $product_id,
            'name' => isset($row['name']) ? (string)$row['name'] : '',
            'model' => isset($row['model']) ? (string)$row['model'] : '',
            'sku' => isset($row['sku']) ? (string)$row['sku'] : '',
            'price' => isset($row['price']) ? round((float)$row['price'], 2) : 0,
            'quantity' => isset($row['quantity']) ? (int)$row['quantity'] : 0,
            'similarity' => (int)$score,
            'match_reason' => (string)$reason
        );
    }

    protected function sortProductCandidates($a, $b) {
        $sa = isset($a['similarity']) ? (int)$a['similarity'] : 0;
        $sb = isset($b['similarity']) ? (int)$b['similarity'] : 0;
        if ($sa === $sb) {
            return ((int)$a['product_id'] < (int)$b['product_id']) ? -1 : 1;
        }
        return ($sa > $sb) ? -1 : 1;
    }

    protected function normalizeComparableProductName($name) {
        $name = $this->lower($this->cleanImportedProductName($name));
        $name = preg_replace('/\b(купити|купить|ціна|цена|доставка|доставкою|uah|грн|грн\.|price|buy|delivery|sale|акція|акция)\b/iu', ' ', $name);
        $name = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $name);
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        return $name;
    }

    protected function normalizeComparableName($text) {
        $text = $this->cleanImportedProductName($this->cleanText((string)$text));
        if ($text === '') { return ''; }
        $text = $this->toAsciiComparable($text);
        $stop = array('kupit','kupyty','tsina','cena','price','dostavka','uah','usd','eur','grn');
        $parts = preg_split('/\s+/u', $text);
        $out = array();
        foreach ((array)$parts as $part) {
            $part = trim($part);
            if ($part === '' || in_array($part, $stop, true)) { continue; }
            $out[] = $part;
        }
        return trim(implode(' ', $out));
    }

    protected function extractComparableTokens($normalized_name, $limit = 8) {
        $parts = preg_split('/\s+/u', trim((string)$normalized_name));
        $tokens = array();
        foreach ($parts as $token) {
            $token = trim($token);
            if ($token === '') { continue; }
            $len = function_exists('mb_strlen') ? mb_strlen($token, 'UTF-8') : strlen($token);
            if ($len < 3) { continue; }
            if (preg_match('/^(для|the|and|with|без|при|грн|uah)$/iu', $token)) { continue; }
            $tokens[$token] = $len;
        }
        arsort($tokens);
        return array_slice(array_keys($tokens), 0, max(1, (int)$limit));
    }

    protected function calculateNameSimilarityPercent($a, $b) {
        $a = trim((string)$a);
        $b = trim((string)$b);
        if ($a === '' || $b === '') { return 0; }
        if ($a === $b) { return 100; }
        $a_ascii = $this->toAsciiComparable($a);
        $b_ascii = $this->toAsciiComparable($b);
        $percent1 = 0;
        $percent2 = 0;
        similar_text($a_ascii, $b_ascii, $percent1);
        similar_text($b_ascii, $a_ascii, $percent2);
        $lev = $this->levenshteinSimilarityPercent($a_ascii, $b_ascii);
        return (int)round(max($percent1, $percent2, $lev));
    }

    protected function levenshteinSimilarityPercent($a, $b) {
        $a = (string)$a;
        $b = (string)$b;
        $max = max(strlen($a), strlen($b));
        if ($max <= 0) { return 0; }
        $distance = levenshtein(substr($a, 0, 255), substr($b, 0, 255));
        $distance = min($distance, $max);
        return max(0, min(100, (int)round((1 - ($distance / $max)) * 100)));
    }

    protected function toAsciiComparable($text) {
        $text = $this->lower((string)$text);
        $map = array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ь'=>'','ы'=>'y','ъ'=>'','э'=>'e','ю'=>'yu','я'=>'ya','ø'=>'o','æ'=>'ae','å'=>'a','ä'=>'a','ö'=>'o','ü'=>'u','ß'=>'ss','é'=>'e','è'=>'e','ê'=>'e','á'=>'a','à'=>'a','ó'=>'o','ò'=>'o','í'=>'i','ì'=>'i','ñ'=>'n');
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/u', ' ', $text);
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    protected function calculatePriceDelta($local_price, $sale_price) {
        $local_price = round((float)$local_price, 2);
        $sale_price = round((float)$sale_price, 2);
        $delta_abs = round($sale_price - $local_price, 2);
        $delta_percent = 0;
        if ($local_price > 0) {
            $delta_percent = round(($delta_abs / $local_price) * 100, 2);
        }
        return array('abs' => $delta_abs, 'percent' => $delta_percent);
    }

    protected function saveReviewProduct($supplier_id, $url, $data, $match, $default_status = 'pending') {
        $supplier_id = (int)$supplier_id;
        $product_id = !empty($match['product_id']) ? (int)$match['product_id'] : 0;
        $supplier_price = isset($data['supplier_price']) ? round((float)$data['supplier_price'], 2) : 0;
        $sale_price = isset($data['sale_price']) ? round((float)$data['sale_price'], 2) : 0;
        $local_price = isset($match['price']) ? round((float)$match['price'], 2) : 0;
        $local_quantity = isset($match['quantity']) ? (int)$match['quantity'] : 0;
        $local_stock_status_id = isset($match['stock_status_id']) ? (int)$match['stock_status_id'] : 0;
        $supplier_quantity = isset($data['quantity']) ? (int)$data['quantity'] : $local_quantity;
        $supplier_stock_status_id = isset($data['stock_status_id']) ? (int)$data['stock_status_id'] : $local_stock_status_id;
        $delta = $this->calculatePriceDelta($local_price, $sale_price);
        $status = preg_replace('/[^a-z0-9_\-]/i', '', (string)$default_status);
        if ($status === '') { $status = 'pending'; }
        $last_error = '';
        if (!empty($data['is_excluded']) || empty($data['category_allowed'])) {
            $status = 'excluded';
            $last_error = !empty($data['exclude_reason']) ? (string)$data['exclude_reason'] : 'Supplier category is not allowed by rules';
        } elseif ($product_id > 0) {
            $supplier = $this->getSupplier($supplier_id);
            $max_change = isset($supplier['max_price_change_percent']) ? (float)$supplier['max_price_change_percent'] : 0;
            if ($max_change > 0 && abs((float)$delta['percent']) > $max_change) {
                $status = 'price_warning';
                $last_error = 'Calculated price change is higher than allowed supplier limit';
            }
            if (!empty($data['price_guard_warning'])) {
                $status = 'price_warning';
                $last_error = (string)$data['price_guard_warning'];
            }
        }
        if ($product_id <= 0 && !empty($data['match_candidates']) && is_array($data['match_candidates'])) {
            $last_error = 'Potential matches found; choose manually before creating a new product';
        }
        $review_type = $product_id > 0 ? 'existing' : 'new';
        $parsed_json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $exists = $this->db->query("SELECT review_id FROM `" . DB_PREFIX . "ccp_ssp_review` WHERE supplier_id = '" . (int)$supplier_id . "' AND supplier_product_url = '" . $this->db->escape($url) . "' LIMIT 1");
        $set = "supplier_id = '" . (int)$supplier_id . "', product_id = '" . (int)$product_id . "', supplier_product_url = '" . $this->db->escape($url) . "', supplier_sku = '" . $this->db->escape(isset($data['sku']) ? $data['sku'] : '') . "', supplier_name = '" . $this->db->escape(isset($data['name']) ? $data['name'] : '') . "', supplier_category = '" . $this->db->escape(isset($data['category_text']) ? $data['category_text'] : '') . "', supplier_price = '" . (float)$supplier_price . "', sale_price = '" . (float)$sale_price . "', supplier_stock_text = '" . $this->db->escape(isset($data['stock_text']) ? $data['stock_text'] : '') . "', supplier_quantity = '" . (int)$supplier_quantity . "', supplier_stock_status_id = '" . (int)$supplier_stock_status_id . "', local_name = '" . $this->db->escape(isset($match['name']) ? $match['name'] : '') . "', local_price = '" . (float)$local_price . "', local_quantity = '" . (int)$local_quantity . "', local_stock_status_id = '" . (int)$local_stock_status_id . "', price_delta_abs = '" . (float)$delta['abs'] . "', price_delta_percent = '" . (float)$delta['percent'] . "', match_source = '" . $this->db->escape(isset($match['source']) ? $match['source'] : '') . "', review_type = '" . $this->db->escape($review_type) . "', parsed_data = '" . $this->db->escape($parsed_json) . "', status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape($last_error) . "', date_modified = NOW()";
        if ($exists->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_review` SET " . $set . " WHERE review_id = '" . (int)$exists->row['review_id'] . "'");
            $review_id = (int)$exists->row['review_id'];
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_review` SET " . $set . ", date_added = NOW()");
            $review_id = (int)$this->db->getLastId();
        }
        return array('review_id' => $review_id, 'status' => $status);
    }

    protected function findPotentialDuplicateForCreate($data, $supplier_id, $url, $supplier) {
        $match = $this->findProduct((int)$supplier_id, isset($data['sku']) ? $data['sku'] : '', $url, isset($data['name']) ? $data['name'] : '', isset($data['ean']) ? $data['ean'] : '', isset($data['upc']) ? $data['upc'] : '', isset($data['mpn']) ? $data['mpn'] : '', $supplier, $data);
        if (!empty($match['product_id'])) {
            return array('product_id' => (int)$match['product_id'], 'source' => !empty($match['source']) ? $match['source'] : 'existing_product', 'auto_link' => true);
        }
        $candidates = $this->findProductCandidates(isset($data['sku']) ? $data['sku'] : '', isset($data['name']) ? $data['name'] : '', isset($data['ean']) ? $data['ean'] : '', isset($data['upc']) ? $data['upc'] : '', isset($data['mpn']) ? $data['mpn'] : '', 90, 3);
        if ($candidates) {
            $top = $candidates[0];
            return array('product_id' => (int)$top['product_id'], 'source' => 'candidate_' . (isset($top['match_reason']) ? $top['match_reason'] : 'name_similarity') . '_manual_check', 'auto_link' => false);
        }
        return array('product_id' => 0, 'source' => '', 'auto_link' => false);
    }

    protected function createNewProduct($data, $supplier, $url) {
        $this->db->query('START TRANSACTION');
        try {
            $product_id = $this->insertNewProduct($data, $supplier, $url);
            $this->db->query($product_id > 0 ? 'COMMIT' : 'ROLLBACK');
            return $product_id;
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    protected function insertNewProduct($data, $supplier, $url) {
        $name = isset($data['name']) ? $this->cleanImportedProductName($data['name']) : '';
        if ($name === '' || empty($data['sale_price'])) {
            return 0;
        }
        $sku = !empty($data['sku']) ? $this->normalizeIdentifier($data['sku']) : '';
        $ean = !empty($data['ean']) ? $this->normalizeIdentifier($data['ean']) : '';
        $upc = !empty($data['upc']) ? $this->normalizeIdentifier($data['upc']) : '';
        $mpn = !empty($data['mpn']) ? $this->normalizeIdentifier($data['mpn']) : '';
        $jan = isset($data['jan']) ? $this->normalizeIdentifier($data['jan']) : '';
        $isbn = isset($data['isbn']) ? $this->normalizeIdentifier($data['isbn']) : '';
        $model = !empty($data['model']) ? $this->normalizeIdentifier($data['model']) : $this->selectBestProductModel($sku, $ean, $upc, $mpn, $name, $url);
        if ($model === '') { $model = $this->safeModelFromName($name); }
        $manufacturer_id = !empty($data['manufacturer']) ? $this->getOrCreateManufacturer($this->cleanManufacturerName($data['manufacturer'])) : 0;
        $price = round((float)$data['sale_price'], 2);
        $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 0;
        $stock_status_id = isset($data['stock_status_id']) ? (int)$data['stock_status_id'] : 0;
        $status = !empty($supplier['create_new_status']) ? 1 : 0;
        $tax_class_id = isset($supplier['new_tax_class_id']) ? (int)$supplier['new_tax_class_id'] : 0;
        $minimum = isset($supplier['new_minimum']) ? max(1, (int)$supplier['new_minimum']) : 1;
        $subtract = isset($supplier['new_subtract']) ? (int)$supplier['new_subtract'] : 1;
        $shipping = isset($supplier['new_shipping']) ? (int)$supplier['new_shipping'] : 1;
        $sort_order = isset($supplier['new_sort_order']) ? (int)$supplier['new_sort_order'] : 0;
        $category_id = !empty($supplier['force_new_category_id']) ? (int)$supplier['force_new_category_id'] : (isset($data['target_category_id']) ? (int)$data['target_category_id'] : 0);
        if ($category_id <= 0 && !empty($supplier['default_category_id'])) { $category_id = (int)$supplier['default_category_id']; }
        $new_settings = $this->jsonDecode(isset($supplier['settings']) ? $supplier['settings'] : '');
        if (empty($supplier['force_new_category_id']) && !empty($new_settings['new_category_name'])) {
            $category_id = $this->getOrCreateReviewCategory($new_settings['new_category_name'], $supplier);
        }

        $this->db->query("INSERT INTO `" . DB_PREFIX . "product` SET model = '" . $this->db->escape($model) . "', sku = '" . $this->db->escape($sku) . "', upc = '" . $this->db->escape($upc) . "', ean = '" . $this->db->escape($ean) . "', jan = '" . $this->db->escape($jan) . "', isbn = '" . $this->db->escape($isbn) . "', mpn = '" . $this->db->escape($mpn) . "', location = '', quantity = '" . (int)$quantity . "', stock_status_id = '" . (int)$stock_status_id . "', image = '', manufacturer_id = '" . (int)$manufacturer_id . "', shipping = '" . (int)$shipping . "', price = '" . (float)$price . "', points = '0', tax_class_id = '" . (int)$tax_class_id . "', date_available = CURDATE(), weight = '0.00000000', weight_class_id = '" . (int)$this->config->get('config_weight_class_id') . "', length = '0.00000000', width = '0.00000000', height = '0.00000000', length_class_id = '" . (int)$this->config->get('config_length_class_id') . "', subtract = '" . (int)$subtract . "', minimum = '" . (int)$minimum . "', sort_order = '" . (int)$sort_order . "', status = '" . (int)$status . "', viewed = '0', date_added = NOW(), date_modified = NOW()" . $this->newProductCompatibilityFields($data));
        $product_id = (int)$this->db->getLastId();
        if ($product_id <= 0) { return 0; }

        $description = isset($data['description']) ? (string)$data['description'] : '';
        $meta_description = isset($data['meta_description']) ? (string)$data['meta_description'] : '';
        $meta_keyword = isset($data['meta_keyword']) ? (string)$data['meta_keyword'] : '';
        $target_language_id = $this->getTargetLanguageId($supplier);
        foreach ($this->getLanguages() as $language) {
            $localized = isset($data['localized'][$language['code']]) ? $data['localized'][$language['code']] : array();
            $can_copy = (int)$language['language_id'] === $target_language_id || !empty($supplier['fill_all_languages']);
            $language_name = !empty($localized['name']) ? $localized['name'] : $name;
            $language_description = isset($localized['description']) ? $localized['description'] : ($can_copy ? $description : '');
            $language_meta_description = isset($localized['meta_description']) ? $localized['meta_description'] : ($can_copy ? $meta_description : '');
            $language_meta_keyword = isset($localized['meta_keyword']) ? $localized['meta_keyword'] : ($can_copy ? $meta_keyword : '');
            $h1 = $this->productDescriptionH1Exists() ? ", meta_h1 = '" . $this->db->escape($language_name) . "'" : '';
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_description` SET product_id = '" . (int)$product_id . "', language_id = '" . (int)$language['language_id'] . "', name = '" . $this->db->escape($language_name) . "', description = '" . $this->db->escape($language_description) . "', tag = '', meta_title = '" . $this->db->escape($language_name) . "', meta_description = '" . $this->db->escape($language_meta_description) . "', meta_keyword = '" . $this->db->escape($language_meta_keyword) . "'" . $h1);
        }

        $store_id = isset($supplier['new_store_id']) ? (int)$supplier['new_store_id'] : 0;
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_store` SET product_id = '" . (int)$product_id . "', store_id = '" . (int)$store_id . "'");
        if ($category_id > 0) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "product_to_category` SET product_id = '" . (int)$product_id . "', category_id = '" . (int)$category_id . "'");
            $this->setProductMainCategory($product_id, $category_id);
        }
        $this->importProductImages($product_id, $data, $supplier, $url);
        $this->createProductSeoUrls($product_id, $name, $store_id, $supplier);
        if (!empty($supplier['import_attributes'])) {
            $this->saveProductAttributes($product_id, isset($data['attributes']) ? $data['attributes'] : array(), !empty($supplier['fill_all_languages']), $this->getTargetLanguageId($supplier));
        }
        if (!empty($supplier['import_options'])) {
            $this->saveProductOptions($product_id, isset($data['options']) ? $data['options'] : array(), !empty($supplier['fill_all_languages']), $this->getTargetLanguageId($supplier));
        }
        $this->savePurchasePrice((int)$supplier['supplier_id'], $product_id, $url, isset($data['purchase_price']) ? (float)$data['purchase_price'] : 0, isset($data['supplier_price']) ? (float)$data['supplier_price'] : 0, $price);
        $this->addHistory((int)$supplier['supplier_id'], $product_id, $url, 'created', 'product', 0, $price, 0, $quantity, 0, $stock_status_id, isset($data['supplier_price']) ? (float)$data['supplier_price'] : 0, $price, 'Product was created from supplier preview');
        return $product_id;
    }

    protected function getOrCreateReviewCategory($name, $supplier) {
        $name = mb_substr(trim(strip_tags((string)$name)), 0, 255, 'UTF-8');
        if ($name === '') { return 0; }
        $language_id = $this->getTargetLanguageId($supplier);
        $q = $this->db->query("SELECT c.category_id FROM `" . DB_PREFIX . "category` c INNER JOIN `" . DB_PREFIX . "category_description` cd ON cd.category_id = c.category_id WHERE c.parent_id = '0' AND cd.language_id = '" . (int)$language_id . "' AND cd.name = '" . $this->db->escape($name) . "' LIMIT 2");
        if ($q->num_rows > 1) { throw new Exception('Ambiguous review category; select a category ID'); }
        if ($q->num_rows) { return (int)$q->row['category_id']; }
        $custom = '';
        $columns = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "category`");
        foreach ($columns->rows as $column) {
            if (in_array($column['Field'], array('nix_supplier_id', 'nix_supplier_category_id'), true)) {
                $custom .= ", `" . $column['Field'] . "` = '0'";
            }
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category` SET parent_id = '0', image = '', top = '0', `column` = '1', sort_order = '0', status = '0', date_added = NOW(), date_modified = NOW()" . $custom);
        $category_id = (int)$this->db->getLastId();
        $h1 = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "category_description` LIKE 'meta_h1'");
        foreach ($this->getLanguages() as $language) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "category_description` SET category_id = '" . (int)$category_id . "', language_id = '" . (int)$language['language_id'] . "', name = '" . $this->db->escape($name) . "', description = '', meta_title = '" . $this->db->escape($name) . "', meta_description = '', meta_keyword = ''" . ($h1->num_rows ? ", meta_h1 = '" . $this->db->escape($name) . "'" : ''));
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_path` SET category_id = '" . (int)$category_id . "', path_id = '" . (int)$category_id . "', level = '0'");
        $this->db->query("INSERT INTO `" . DB_PREFIX . "category_to_store` SET category_id = '" . (int)$category_id . "', store_id = '" . (int)($supplier['new_store_id'] ?? 0) . "'");
        return $category_id;
    }

    protected function productDescriptionH1Exists() {
        if ($this->description_h1_exists === null) {
            $q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product_description` LIKE 'meta_h1'");
            $this->description_h1_exists = $q->num_rows > 0;
        }
        return $this->description_h1_exists;
    }

    protected function newProductCompatibilityFields($data) {
        // SQL identifiers are taken only from this fixed compatibility whitelist.
        $known = array('nix_supplier_id'=>0, 'nix_supplier_product_id'=>0,
            'price_purchasing'=>(float)($data['purchase_price'] ?? 0),
            'price_rrp'=>(float)($data['supplier_price'] ?? 0));
        $q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product`");
        $fields = '';
        foreach ($q->rows as $column) {
            if (array_key_exists($column['Field'], $known)) {
                $fields .= ", `" . $column['Field'] . "` = '" . (float)$known[$column['Field']] . "'";
            }
        }
        return $fields;
    }

    protected function createProductSeoUrls($product_id, $name, $store_id, $supplier) {
        $q = $this->db->query("SHOW TABLES LIKE '" . $this->db->escape(DB_PREFIX . "seo_url") . "'");
        if (!$q->num_rows) { return; }
        $base_keyword = $this->slugify($name);
        if ($base_keyword === '') { $base_keyword = 'product-' . (int)$product_id; }
        $mode = isset($supplier['seo_url_mode']) ? (string)$supplier['seo_url_mode'] : 'all_languages';
        foreach ($this->getLanguages() as $language) {
            if ($mode !== 'all_languages' && (int)$language['language_id'] !== (int)$this->getTargetLanguageId($supplier)) { continue; }
            $keyword = $base_keyword;
            $i = 2;
            while (true) {
                $exists = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url` WHERE store_id = '" . (int)$store_id . "' AND language_id = '" . (int)$language['language_id'] . "' AND keyword = '" . $this->db->escape($keyword) . "' AND query <> 'product_id=" . (int)$product_id . "' LIMIT 1");
                if (!$exists->num_rows) { break; }
                $keyword = $base_keyword . '-' . $i;
                $i++;
                if ($i > 200) { $keyword = $base_keyword . '-' . (int)$product_id; break; }
            }
            $exists_query = $this->db->query("SELECT seo_url_id FROM `" . DB_PREFIX . "seo_url` WHERE store_id = '" . (int)$store_id . "' AND language_id = '" . (int)$language['language_id'] . "' AND query = 'product_id=" . (int)$product_id . "' LIMIT 1");
            if ($exists_query->num_rows) {
                $this->db->query("UPDATE `" . DB_PREFIX . "seo_url` SET keyword = '" . $this->db->escape($keyword) . "' WHERE seo_url_id = '" . (int)$exists_query->row['seo_url_id'] . "'");
            } else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "seo_url` SET store_id = '" . (int)$store_id . "', language_id = '" . (int)$language['language_id'] . "', query = 'product_id=" . (int)$product_id . "', keyword = '" . $this->db->escape($keyword) . "'");
            }
        }
    }

    protected function updateExistingProduct($product_id, $data, $supplier, $url, $update_price = true, $update_stock = true, $full_update = false) {
        $this->db->query('START TRANSACTION');
        try {
            $result = $this->writeExistingProduct($product_id, $data, $supplier, $url, $update_price, $update_stock, $full_update);
            $this->db->query(!empty($result['ok']) ? 'COMMIT' : 'ROLLBACK');
            return $result;
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }

    protected function writeExistingProduct($product_id, $data, $supplier, $url, $update_price, $update_stock, $full_update) {
        $update_price = $update_price && (!isset($supplier['update_price']) || !empty($supplier['update_price']));
        $update_stock = $update_stock && (!isset($supplier['update_stock']) || !empty($supplier['update_stock']));
        $product_id = (int)$product_id;
        $q = $this->db->query("SELECT product_id, price, quantity, stock_status_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$product_id . "' LIMIT 1");
        if (!$q->num_rows) { return array('ok' => false, 'message' => 'No linked OpenCart product'); }
        $old_price = (float)$q->row['price'];
        $old_quantity = (int)$q->row['quantity'];
        $old_stock_status_id = (int)$q->row['stock_status_id'];
        $new_price = isset($data['sale_price']) ? round((float)$data['sale_price'], 2) : $old_price;
        $new_quantity = isset($data['quantity']) ? (int)$data['quantity'] : $old_quantity;
        $new_stock_status_id = isset($data['stock_status_id']) ? (int)$data['stock_status_id'] : $old_stock_status_id;
        $stock_settings = $this->jsonDecode(isset($supplier['settings']) ? $supplier['settings'] : '');
        if (isset($stock_settings['update_stock_status']) && !$stock_settings['update_stock_status']) {
            $new_stock_status_id = $old_stock_status_id;
        }
        if (!$update_price) { $new_price = $old_price; }
        if (!$update_stock) { $new_quantity = $old_quantity; $new_stock_status_id = $old_stock_status_id; }
        $changed = array();
        $set = array();
        if ($update_price && abs($new_price - $old_price) > 0.0001) {
            $set[] = "price = '" . (float)$new_price . "'";
            $changed[] = 'price';
        }
        if ($update_stock && $new_quantity !== $old_quantity) {
            $set[] = "quantity = '" . (int)$new_quantity . "'";
            $changed[] = 'quantity';
        }
        if ($update_stock && $new_stock_status_id !== $old_stock_status_id) {
            $set[] = "stock_status_id = '" . (int)$new_stock_status_id . "'";
            $changed[] = 'stock_status';
        }
        if ($set) {
            $set[] = "date_modified = NOW()";
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET " . implode(', ', $set) . " WHERE product_id = '" . (int)$product_id . "'");
        }
        if ($full_update) {
            $this->applyExistingContentRules($product_id, $data, $supplier);
        }
        if ($update_price && isset($data['purchase_price'])) {
            $this->savePurchasePrice((int)$supplier['supplier_id'], $product_id, $url, (float)$data['purchase_price'], isset($data['supplier_price']) ? (float)$data['supplier_price'] : 0, isset($data['sale_price']) ? (float)$data['sale_price'] : $new_price);
        }
        if ($changed) {
            $this->addHistory((int)$supplier['supplier_id'], $product_id, $url, 'updated', implode(',', $changed), $old_price, $new_price, $old_quantity, $new_quantity, $old_stock_status_id, $new_stock_status_id, isset($data['supplier_price']) ? (float)$data['supplier_price'] : 0, isset($data['sale_price']) ? (float)$data['sale_price'] : 0, 'Existing product updated');
        }
        return array('ok' => true, 'changed_fields' => $changed, 'no_change' => $changed ? 0 : 1, 'message' => $changed ? 'Existing product updated' : 'No changes');
    }

    public function rollbackPriceStockHistory($history_id, $supplier_id) {
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            return array('ok'=>false, 'message'=>'Module is disabled', 'code'=>'disabled');
        }
        if ((int)$history_id <= 0 || (int)$supplier_id <= 0) { return array('ok'=>false, 'code'=>'rollback_unavailable'); }
        $this->db->query('START TRANSACTION');
        try {
            $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_history` WHERE history_id = '" . (int)$history_id . "' AND supplier_id = '" . (int)$supplier_id . "' FOR UPDATE");
            if (!$q->num_rows || $q->row['action'] !== 'updated') { throw new RuntimeException('rollback_unavailable'); }
            $history = $q->row;
            $fields = explode(',', $history['field_changed']);
            $definitions = array('price'=>array('price','old_price','new_price'), 'quantity'=>array('quantity','old_quantity','new_quantity'), 'stock_status'=>array('stock_status_id','old_stock_status_id','new_stock_status_id'));
            foreach ($fields as $field) { if (!isset($definitions[$field])) { throw new RuntimeException('rollback_unavailable'); } }
            $q = $this->db->query("SELECT price, quantity, stock_status_id FROM `" . DB_PREFIX . "product` WHERE product_id = '" . (int)$history['product_id'] . "' FOR UPDATE");
            if (!$q->num_rows) { throw new RuntimeException('rollback_unavailable'); }
            $current = $q->row;
            $restored = $current;
            $set = array();
            foreach ($fields as $field) {
                list($column, $old_key, $new_key) = $definitions[$field];
                if (abs((float)$current[$column] - (float)$history[$new_key]) > 0.00005) { throw new RuntimeException('rollback_conflict'); }
                $restored[$column] = $history[$old_key];
                $set[] = "`" . $column . "` = '" . (float)$history[$old_key] . "'";
            }
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET " . implode(', ', $set) . ", date_modified = NOW() WHERE product_id = '" . (int)$history['product_id'] . "'");
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_history` SET action = 'rolled_back' WHERE history_id = '" . (int)$history_id . "'");
            $this->addHistory($supplier_id, $history['product_id'], $history['supplier_product_url'], 'rollback', $history['field_changed'], $current['price'], $restored['price'], $current['quantity'], $restored['quantity'], $current['stock_status_id'], $restored['stock_status_id'], $history['supplier_price'], $history['sale_price'], 'Price/stock rollback of history #' . (int)$history_id);
            $this->db->query('COMMIT');
            return array('ok'=>true, 'code'=>'rollback_success');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            $code = in_array($e->getMessage(), array('rollback_unavailable','rollback_conflict'), true) ? $e->getMessage() : 'rollback_failed';
            return array('ok'=>false, 'code'=>$code);
        }
    }

    protected function checkExclusionRules($data, $supplier, $url) {
        $settings = $this->jsonDecode(isset($supplier['settings']) ? $supplier['settings'] : '');
        $checks = array(
            'excluded_skus' => isset($data['sku']) ? $data['sku'] : '',
            'excluded_urls' => $url,
            'excluded_categories' => isset($data['category_text']) ? $data['category_text'] : ''
        );
        foreach ($checks as $key => $value) {
            $value = $this->lower(trim((string)$value));
            if ($value === '' || empty($settings[$key]) || !is_array($settings[$key])) { continue; }
            foreach ($settings[$key] as $rule) {
                $rule = $this->lower(trim((string)$rule));
                if ($rule === '') { continue; }
                if ($key === 'excluded_skus') {
                    if ($value === $rule) { return array('excluded' => true, 'reason' => 'Excluded by SKU rule'); }
                } else {
                    if ($this->strpos($value, $rule) !== false) { return array('excluded' => true, 'reason' => $key === 'excluded_urls' ? 'Excluded by URL rule' : 'Excluded by category rule'); }
                }
            }
        }
        $supplier_id = isset($supplier['supplier_id']) ? (int)$supplier['supplier_id'] : 0;
        if ($supplier_id > 0) {
            $sku = isset($data['sku']) ? $this->normalizeIdentifier($data['sku']) : '';
            $product_url = trim((string)$url);
            $where = array("supplier_id = '" . (int)$supplier_id . "'");
            if ($sku !== '') { $where[] = "supplier_sku = '" . $this->db->escape($sku) . "'"; }
            if ($product_url !== '') { $where[] = "supplier_product_url = '" . $this->db->escape($product_url) . "'"; }
            if (count($where) > 1) {
                $q = $this->db->query("SELECT exclusion_id, reason FROM `" . DB_PREFIX . "ccp_ssp_exclusion` WHERE " . $where[0] . " AND (" . implode(' OR ', array_slice($where, 1)) . ") LIMIT 1");
                if ($q->num_rows) { return array('excluded' => true, 'reason' => !empty($q->row['reason']) ? $q->row['reason'] : 'Manual exclusion'); }
            }
        }
        return array('excluded' => false, 'reason' => '');
    }

    protected function applyExistingContentRules($product_id, $data, $supplier) {
        $mode = isset($supplier['existing_description_mode']) ? $supplier['existing_description_mode'] : 'keep';
        $language_id = $this->getTargetLanguageId($supplier);
        if (($mode === 'update_empty' || $mode === 'overwrite') && !empty($data['description'])) {
            $q = $this->db->query("SELECT description FROM `" . DB_PREFIX . "product_description` WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "' LIMIT 1");
            if ($q->num_rows && ($mode === 'overwrite' || trim(strip_tags($q->row['description'])) === '')) {
                $this->db->query("UPDATE `" . DB_PREFIX . "product_description` SET description = '" . $this->db->escape($data['description']) . "' WHERE product_id = '" . (int)$product_id . "' AND language_id = '" . (int)$language_id . "'");
            }
        }
        if (!empty($supplier['import_attributes'])) { $this->saveProductAttributes((int)$product_id, isset($data['attributes']) ? $data['attributes'] : array(), false, $this->getTargetLanguageId($supplier)); }
        if (!empty($supplier['import_options'])) { $this->saveProductOptions((int)$product_id, isset($data['options']) ? $data['options'] : array(), false, $this->getTargetLanguageId($supplier)); }
    }

    protected function saveProductAttributes($product_id, $attributes, $fill_all_languages = false, $target_language_id = 0) {
        if (!$attributes || !is_array($attributes)) { return; }
        $target_language_id = (int)$target_language_id > 0 ? (int)$target_language_id : $this->getLanguageId();
        $group_id = $this->getOrCreateAttributeGroup('Supplier attributes', $fill_all_languages, $target_language_id);
        foreach ($attributes as $row) {
            $name = isset($row['name']) ? trim($row['name']) : '';
            $value = isset($row['value']) ? trim($row['value']) : '';
            if ($name === '' || $value === '') { continue; }
            $attribute_id = $this->getOrCreateAttribute($group_id, $name, $fill_all_languages, $target_language_id);
            $languages = $this->getLanguages();
            foreach ($languages as $language) {
                if (!$fill_all_languages && (int)$language['language_id'] !== (int)$target_language_id) { continue; }
                $exists = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "product_attribute` WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language['language_id'] . "' LIMIT 1");
                if ($exists->num_rows) {
                    $this->db->query("UPDATE `" . DB_PREFIX . "product_attribute` SET text = '" . $this->db->escape($value) . "' WHERE product_id = '" . (int)$product_id . "' AND attribute_id = '" . (int)$attribute_id . "' AND language_id = '" . (int)$language['language_id'] . "'");
                } else {
                    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_attribute` SET product_id = '" . (int)$product_id . "', attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language['language_id'] . "', text = '" . $this->db->escape($value) . "'");
                }
            }
        }
    }

    protected function getOrCreateAttributeGroup($name, $fill_all_languages = false, $target_language_id = 0) {
        $language_id = (int)$target_language_id > 0 ? (int)$target_language_id : $this->getLanguageId();
        $q = $this->db->query("SELECT agd.attribute_group_id FROM `" . DB_PREFIX . "attribute_group_description` agd WHERE agd.language_id = '" . (int)$language_id . "' AND agd.name = '" . $this->db->escape($name) . "' LIMIT 1");
        if ($q->num_rows) { return (int)$q->row['attribute_group_id']; }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_group` SET sort_order = '0'");
        $group_id = (int)$this->db->getLastId();
        foreach ($this->getLanguages() as $language) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_group_description` SET attribute_group_id = '" . (int)$group_id . "', language_id = '" . (int)$language['language_id'] . "', name = '" . $this->db->escape($name) . "'");
        }
        return $group_id;
    }

    protected function getOrCreateAttribute($group_id, $name, $fill_all_languages = false, $target_language_id = 0) {
        $language_id = (int)$target_language_id > 0 ? (int)$target_language_id : $this->getLanguageId();
        $q = $this->db->query("SELECT ad.attribute_id FROM `" . DB_PREFIX . "attribute_description` ad WHERE ad.language_id = '" . (int)$language_id . "' AND ad.name = '" . $this->db->escape($name) . "' LIMIT 1");
        if ($q->num_rows) { return (int)$q->row['attribute_id']; }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute` SET attribute_group_id = '" . (int)$group_id . "', sort_order = '0'");
        $attribute_id = (int)$this->db->getLastId();
        foreach ($this->getLanguages() as $language) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "attribute_description` SET attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language['language_id'] . "', name = '" . $this->db->escape($name) . "'");
        }
        return $attribute_id;
    }

    protected function saveProductOptions($product_id, $options, $fill_all_languages = false, $target_language_id = 0) {
        if (!$options || !is_array($options)) { return; }
        $target_language_id = (int)$target_language_id > 0 ? (int)$target_language_id : $this->getLanguageId();
        foreach ($options as $row) {
            $name = isset($row['name']) ? trim($row['name']) : '';
            $value = isset($row['value']) ? trim($row['value']) : '';
            if ($name === '' || $value === '') { continue; }
            $option_id = $this->getOrCreateOption($name, $fill_all_languages, $target_language_id);
            $option_value_id = $this->getOrCreateOptionValue($option_id, $value, $fill_all_languages, $target_language_id);
            $po = $this->db->query("SELECT product_option_id FROM `" . DB_PREFIX . "product_option` WHERE product_id = '" . (int)$product_id . "' AND option_id = '" . (int)$option_id . "' LIMIT 1");
            if ($po->num_rows) { $product_option_id = (int)$po->row['product_option_id']; }
            else {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_option` SET product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', value = '', required = '0'");
                $product_option_id = (int)$this->db->getLastId();
            }
            $exists = $this->db->query("SELECT product_option_value_id FROM `" . DB_PREFIX . "product_option_value` WHERE product_id = '" . (int)$product_id . "' AND product_option_id = '" . (int)$product_option_id . "' AND option_id = '" . (int)$option_id . "' AND option_value_id = '" . (int)$option_value_id . "' LIMIT 1");
            if (!$exists->num_rows) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_option_value` SET product_option_id = '" . (int)$product_option_id . "', product_id = '" . (int)$product_id . "', option_id = '" . (int)$option_id . "', option_value_id = '" . (int)$option_value_id . "', quantity = '999', subtract = '0', price = '0.0000', price_prefix = '+', points = '0', points_prefix = '+', weight = '0.00000000', weight_prefix = '+'");
            }
        }
    }

    protected function getOrCreateOption($name, $fill_all_languages = false, $target_language_id = 0) {
        $language_id = (int)$target_language_id > 0 ? (int)$target_language_id : $this->getLanguageId();
        $q = $this->db->query("SELECT od.option_id FROM `" . DB_PREFIX . "option_description` od WHERE od.language_id = '" . (int)$language_id . "' AND od.name = '" . $this->db->escape($name) . "' LIMIT 1");
        if ($q->num_rows) { return (int)$q->row['option_id']; }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "option` SET type = 'select', sort_order = '0'");
        $option_id = (int)$this->db->getLastId();
        foreach ($this->getLanguages() as $language) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "option_description` SET option_id = '" . (int)$option_id . "', language_id = '" . (int)$language['language_id'] . "', name = '" . $this->db->escape($name) . "'");
        }
        return $option_id;
    }

    protected function getOrCreateOptionValue($option_id, $name, $fill_all_languages = false, $target_language_id = 0) {
        $language_id = (int)$target_language_id > 0 ? (int)$target_language_id : $this->getLanguageId();
        $q = $this->db->query("SELECT option_value_id FROM `" . DB_PREFIX . "option_value_description` WHERE option_id = '" . (int)$option_id . "' AND language_id = '" . (int)$language_id . "' AND name = '" . $this->db->escape($name) . "' LIMIT 1");
        if ($q->num_rows) { return (int)$q->row['option_value_id']; }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "option_value` SET option_id = '" . (int)$option_id . "', image = '', sort_order = '0'");
        $option_value_id = (int)$this->db->getLastId();
        foreach ($this->getLanguages() as $language) {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "option_value_description` SET option_value_id = '" . (int)$option_value_id . "', option_id = '" . (int)$option_id . "', language_id = '" . (int)$language['language_id'] . "', name = '" . $this->db->escape($name) . "'");
        }
        return $option_value_id;
    }

    protected function savePurchasePrice($supplier_id, $product_id, $url = '', $purchase_price = 0, $supplier_price = 0, $sale_price = 0) {
        $supplier_id = (int)$supplier_id;
        $product_id = (int)$product_id;
        $url = trim((string)$url);
        if ($supplier_id <= 0 || $url === '') {
            return;
        }
        $supplier_price = round((float)$supplier_price, 2);
        $purchase_price = round((float)$purchase_price, 2);
        $sale_price = round((float)$sale_price, 2);
        $exists = $this->db->query("SELECT link_id FROM `" . DB_PREFIX . "ccp_ssp_product_link` WHERE supplier_id = '" . (int)$supplier_id . "' AND supplier_product_url = '" . $this->db->escape($url) . "' LIMIT 1");
        if ($exists->num_rows) {
            if ((int)$product_id <= 0) {
                // A failed supplier response must not erase a confirmed association or its last prices.
                return;
            }
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_product_link` SET product_id = '" . (int)$product_id . "', last_supplier_price = '" . (float)$supplier_price . "', last_purchase_price = '" . (float)$purchase_price . "', last_sale_price = '" . (float)$sale_price . "', date_modified = NOW() WHERE link_id = '" . (int)$exists->row['link_id'] . "'");
        }
    }

    protected function handleMissingSupplierProduct($supplier_id, $url, $supplier, $message) {
        $link = $this->db->query("SELECT product_id FROM `" . DB_PREFIX . "ccp_ssp_product_link` WHERE supplier_id = '" . (int)$supplier_id . "' AND supplier_product_url = '" . $this->db->escape($url) . "' AND product_id > 0 LIMIT 1");
        if (!$link->num_rows) { return; }
        $product_id = (int)$link->row['product_id'];
        $policy = isset($supplier['missing_policy']) ? $supplier['missing_policy'] : 'report';
        if ($policy === 'disable') {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET status = '0', date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
            $this->addHistory((int)$supplier_id, $product_id, $url, 'missing', 'status', 0, 0, 0, 0, 0, 0, 0, 0, 'Supplier page missing: product disabled');
        } elseif ($policy === 'zero_qty') {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET quantity = '0', date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
            $this->addHistory((int)$supplier_id, $product_id, $url, 'missing', 'quantity', 0, 0, 0, 0, 0, 0, 0, 0, 'Supplier page missing: quantity set to 0');
        } elseif ($policy === 'out_of_stock') {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET quantity = '0', stock_status_id = '" . (int)$supplier['default_stock_status_id'] . "', date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
            $this->addHistory((int)$supplier_id, $product_id, $url, 'missing', 'stock_status', 0, 0, 0, 0, 0, (int)$supplier['default_stock_status_id'], 0, 0, 'Supplier page missing: out of stock policy');
        } else {
            $this->addHistory((int)$supplier_id, $product_id, $url, 'missing', 'report', 0, 0, 0, 0, 0, 0, 0, 0, 'Supplier page missing: ' . $message);
        }
    }

    protected function saveRunSummary($supplier_id, $result, $source) {
        $supplier_id = (int)$supplier_id;
        $price_updated = isset($result['price_updated']) ? (int)$result['price_updated'] : 0;
        $stock_updated = isset($result['stock_updated']) ? (int)$result['stock_updated'] : 0;
        $created = isset($result['created']) ? (int)$result['created'] : 0;
        $errors = isset($result['errors']) ? (int)$result['errors'] : 0;
        $skipped = isset($result['skipped']) ? (int)$result['skipped'] : 0;
        $warnings = isset($result['warnings']) ? (int)$result['warnings'] : 0;
        $duplicates = isset($result['duplicates']) ? (int)$result['duplicates'] : 0;
        $excluded = isset($result['excluded']) ? (int)$result['excluded'] : 0;
        $no_change = isset($result['no_change']) ? (int)$result['no_change'] : 0;
        $checked = isset($result['processed']) ? (int)$result['processed'] : 0;
        if ($checked <= 0 && $price_updated <= 0 && $stock_updated <= 0 && $created <= 0 && $errors <= 0 && $skipped <= 0) { return; }
        $message = isset($result['message']) ? $result['message'] : 'Batch processed';
        $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_run` SET supplier_id = '" . $supplier_id . "', source = '" . $this->db->escape($source) . "', total_found = '" . (isset($result['preview']) ? (int)$result['preview'] : 0) . "', checked = '" . $checked . "', price_updated = '" . $price_updated . "', stock_updated = '" . $stock_updated . "', created = '" . $created . "', skipped = '" . $skipped . "', warnings = '" . $warnings . "', duplicates = '" . $duplicates . "', excluded = '" . $excluded . "', no_change = '" . $no_change . "', errors = '" . $errors . "', message = '" . $this->db->escape($message) . "', date_added = NOW()");
    }

    protected function markSupplierTest($supplier_id, $ok) {
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_supplier` SET last_test_ok = '" . (!empty($ok) ? 1 : 0) . "', last_test_date = NOW(), date_modified = NOW() WHERE supplier_id = '" . (int)$supplier_id . "'");
    }

    protected function buildXPathReport($data) {
        $keys = array('name','sku','ean','upc','mpn','supplier_price','stock_text','category_text','description','attributes','options','main_image_url');
        $report = array();
        foreach ($keys as $key) {
            $value = isset($data[$key]) ? $data[$key] : '';
            if (is_array($value)) { $found = count($value) > 0; }
            else { $found = trim((string)$value) !== ''; }
            $report[$key] = array('found' => $found, 'value' => is_array($value) ? count($value) : $value);
        }
        return $report;
    }

    protected function getSupplier($supplier_id) {
        $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_supplier` WHERE supplier_id = '" . (int)$supplier_id . "'");
        return $q->num_rows ? $q->row : array();
    }

    protected function getTargetLanguageId($supplier = array()) {
        if (is_array($supplier) && !empty($supplier['target_language_id'])) {
            $language_id = (int)$supplier['target_language_id'];
            $q = $this->db->query("SELECT language_id FROM `" . DB_PREFIX . "language` WHERE language_id = '" . (int)$language_id . "' AND status = '1' LIMIT 1");
            if ($q->num_rows) {
                return (int)$q->row['language_id'];
            }
        }
        return $this->getLanguageId();
    }

    protected function productToCategoryMainColumnExists() {
        $q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product_to_category` LIKE 'main_category'");
        return $q->num_rows > 0;
    }

    protected function setProductMainCategory($product_id, $category_id) {
        $product_id = (int)$product_id;
        $category_id = (int)$category_id;
        if ($product_id <= 0 || $category_id <= 0) {
            return;
        }
        $q = $this->db->query("SHOW COLUMNS FROM `" . DB_PREFIX . "product` LIKE 'main_category_id'");
        if ($q->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product` SET main_category_id = '" . (int)$category_id . "' WHERE product_id = '" . (int)$product_id . "'");
        }
        if ($this->productToCategoryMainColumnExists()) {
            $this->db->query("UPDATE `" . DB_PREFIX . "product_to_category` SET main_category = '0' WHERE product_id = '" . (int)$product_id . "'");
            $this->db->query("UPDATE `" . DB_PREFIX . "product_to_category` SET main_category = '1' WHERE product_id = '" . (int)$product_id . "' AND category_id = '" . (int)$category_id . "'");
        }
    }

    protected function getLanguages() {
        $q = $this->db->query("SELECT language_id, code, name FROM `" . DB_PREFIX . "language` WHERE status = '1' ORDER BY sort_order ASC, name ASC");
        return $q->rows;
    }

    protected function getLanguageId() {
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

    protected function getOrCreateManufacturer($name) {
        $name = $this->cleanManufacturerName($name);
        if ($name === '') {
            return 0;
        }
        $q = $this->db->query("SELECT manufacturer_id FROM `" . DB_PREFIX . "manufacturer` WHERE name = '" . $this->db->escape($name) . "' LIMIT 1");
        if ($q->num_rows) {
            return (int)$q->row['manufacturer_id'];
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer` SET name = '" . $this->db->escape($name) . "', image = '', sort_order = '0'");
        $manufacturer_id = (int)$this->db->getLastId();
        $this->db->query("INSERT INTO `" . DB_PREFIX . "manufacturer_to_store` SET manufacturer_id = '" . (int)$manufacturer_id . "', store_id = '0'");
        return $manufacturer_id;
    }

    protected function addQueueJob($supplier_id, $action, $url, $payload, $deduplicate_recent = true) {
        $supplier_id = (int)$supplier_id;
        $action = preg_replace('/[^a-z0-9_]/', '', (string)$action);
        $url = trim((string)$url);
        if (!$url) {
            return false;
        }
        $recent = $deduplicate_recent ? " OR date_modified > DATE_SUB(NOW(), INTERVAL 12 HOUR)" : '';
        $exists = $this->db->query("SELECT queue_id FROM `" . DB_PREFIX . "ccp_ssp_queue` WHERE supplier_id = '" . $supplier_id . "' AND action = '" . $this->db->escape($action) . "' AND supplier_product_url = '" . $this->db->escape($url) . "' AND (status IN ('pending','processing')" . $recent . ") LIMIT 1");
        if ($exists->num_rows) {
            return false;
        }
        $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_queue` SET supplier_id = '" . $supplier_id . "', action = '" . $this->db->escape($action) . "', supplier_product_url = '" . $this->db->escape($url) . "', payload = '" . $this->db->escape(json_encode($payload)) . "', status = 'pending', attempts = '0', last_error = '', date_added = NOW(), date_modified = NOW()");
        return true;
    }

    protected function setQueueStatus($queue_id, $status, $error) {
        $allowed = array('pending', 'processing', 'done', 'error', 'skipped');
        if (!in_array($status, $allowed, true)) {
            $status = 'error';
        }
        $attempt_sql = $status === 'processing' ? ", attempts = attempts + 1" : '';
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_queue` SET status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape((string)$error) . "', date_modified = NOW()" . $attempt_sql . " WHERE queue_id = '" . (int)$queue_id . "'");
    }

    protected function saveProductLink($supplier_id, $product_id, $url, $sku, $name, $supplier_price, $sale_price, $stock_text, $quantity, $status, $error, $purchase_price = 0) {
        $sku = $this->normalizeIdentifier($sku);
        $name = $this->cleanImportedProductName($name);
        $exists = $this->db->query("SELECT link_id FROM `" . DB_PREFIX . "ccp_ssp_product_link` WHERE supplier_id = '" . (int)$supplier_id . "' AND supplier_product_url = '" . $this->db->escape($url) . "' LIMIT 1");
        if ($exists->num_rows) {
            if ((int)$product_id <= 0) {
                $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_product_link` SET last_status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape($error) . "', last_checked = NOW(), date_modified = NOW() WHERE link_id = '" . (int)$exists->row['link_id'] . "'");
                return;
            }
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_product_link` SET product_id = '" . (int)$product_id . "', supplier_sku = '" . $this->db->escape($sku) . "', supplier_name = '" . $this->db->escape($name) . "', last_supplier_price = '" . (float)$supplier_price . "', last_purchase_price = '" . (float)$purchase_price . "', last_sale_price = '" . (float)$sale_price . "', last_stock_text = '" . $this->db->escape($stock_text) . "', last_quantity = '" . (int)$quantity . "', last_status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape($error) . "', last_checked = NOW(), date_modified = NOW() WHERE link_id = '" . (int)$exists->row['link_id'] . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_product_link` SET supplier_id = '" . (int)$supplier_id . "', product_id = '" . (int)$product_id . "', supplier_product_url = '" . $this->db->escape($url) . "', supplier_sku = '" . $this->db->escape($sku) . "', supplier_name = '" . $this->db->escape($name) . "', last_supplier_price = '" . (float)$supplier_price . "', last_purchase_price = '" . (float)$purchase_price . "', last_sale_price = '" . (float)$sale_price . "', last_stock_text = '" . $this->db->escape($stock_text) . "', last_quantity = '" . (int)$quantity . "', last_status = '" . $this->db->escape($status) . "', last_error = '" . $this->db->escape($error) . "', last_checked = NOW(), date_added = NOW(), date_modified = NOW()");
        }
    }

    protected function saveNewProduct($supplier_id, $url, $data, $status, $product_id, $error) {
        $exists = $this->db->query("SELECT new_id FROM `" . DB_PREFIX . "ccp_ssp_new_product` WHERE supplier_id = '" . (int)$supplier_id . "' AND supplier_product_url = '" . $this->db->escape($url) . "' LIMIT 1");
        $json = json_encode($data);
        if ($exists->num_rows) {
            $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_new_product` SET supplier_sku = '" . $this->db->escape($data['sku']) . "', supplier_name = '" . $this->db->escape($data['name']) . "', parsed_data = '" . $this->db->escape($json) . "', status = '" . $this->db->escape($status) . "', product_id = '" . (int)$product_id . "', last_error = '" . $this->db->escape($error) . "', date_modified = NOW() WHERE new_id = '" . (int)$exists->row['new_id'] . "'");
        } else {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_new_product` SET supplier_id = '" . (int)$supplier_id . "', supplier_product_url = '" . $this->db->escape($url) . "', supplier_sku = '" . $this->db->escape($data['sku']) . "', supplier_name = '" . $this->db->escape($data['name']) . "', parsed_data = '" . $this->db->escape($json) . "', status = '" . $this->db->escape($status) . "', product_id = '" . (int)$product_id . "', last_error = '" . $this->db->escape($error) . "', date_added = NOW(), date_modified = NOW()");
        }
    }

    protected function addHistory($supplier_id, $product_id, $url, $action, $field_changed, $old_price, $new_price, $old_quantity, $new_quantity, $old_stock_status_id, $new_stock_status_id, $supplier_price, $sale_price, $note) {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_history` SET supplier_id = '" . (int)$supplier_id . "', product_id = '" . (int)$product_id . "', supplier_product_url = '" . $this->db->escape((string)$url) . "', action = '" . $this->db->escape((string)$action) . "', field_changed = '" . $this->db->escape((string)$field_changed) . "', old_price = '" . (float)$old_price . "', new_price = '" . (float)$new_price . "', old_quantity = '" . (int)$old_quantity . "', new_quantity = '" . (int)$new_quantity . "', old_stock_status_id = '" . (int)$old_stock_status_id . "', new_stock_status_id = '" . (int)$new_stock_status_id . "', supplier_price = '" . (float)$supplier_price . "', sale_price = '" . (float)$sale_price . "', note = '" . $this->db->escape((string)$note) . "', date_added = NOW()");
    }

    protected function addLog($supplier_id, $level, $title, $message, $url = '') {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "ccp_ssp_log` SET supplier_id = '" . (int)$supplier_id . "', level = '" . $this->db->escape($level) . "', title = '" . $this->db->escape($title) . "', message = '" . $this->db->escape($message) . "', related_url = '" . $this->db->escape($url) . "', date_added = NOW()");
    }

    public function getQueueStats($supplier_id = 0) {
        $supplier_id = (int)$supplier_id;
        $where = $supplier_id ? " WHERE supplier_id = '" . $supplier_id . "'" : '';
        $q = $this->db->query("SELECT status, COUNT(*) AS total FROM `" . DB_PREFIX . "ccp_ssp_queue`" . $where . " GROUP BY status");
        $stats = array('pending' => 0, 'processing' => 0, 'done' => 0, 'error' => 0, 'skipped' => 0, 'total' => 0);
        foreach ($q->rows as $row) {
            $stats[$row['status']] = (int)$row['total'];
            $stats['total'] += (int)$row['total'];
        }
        $stats['completed'] = (int)$stats['done'] + (int)$stats['error'] + (int)$stats['skipped'];
        $stats['remaining'] = (int)$stats['pending'] + (int)$stats['processing'];
        $stats['progress_percent'] = $stats['total'] > 0 ? (int)round($stats['completed'] * 100 / $stats['total']) : 0;
        $stats['pending_scan_lists'] = 0;
        $stats['pending_products'] = 0;
        $stats['done_products'] = 0;
        $action_counts = $this->db->query("SELECT action, status, COUNT(*) AS total FROM `" . DB_PREFIX . "ccp_ssp_queue`" . $where . " GROUP BY action, status");
        foreach ($action_counts->rows as $row) {
            if ($row['action'] === 'scan_list' && $row['status'] === 'pending') {
                $stats['pending_scan_lists'] = (int)$row['total'];
            }
            if (($row['action'] === 'check_product' || $row['action'] === 'import_item') && $row['status'] === 'pending') {
                $stats['pending_products'] += (int)$row['total'];
            }
            if (($row['action'] === 'check_product' || $row['action'] === 'import_item') && $row['status'] === 'done') {
                $stats['done_products'] += (int)$row['total'];
            }
        }

        $review_where = $supplier_id ? " WHERE supplier_id = '" . $supplier_id . "'" : '';
        $review = $this->db->query("SELECT COUNT(*) AS found_preview, SUM(CASE WHEN product_id > 0 THEN 1 ELSE 0 END) AS matched, SUM(CASE WHEN product_id <= 0 THEN 1 ELSE 0 END) AS unmatched, SUM(CASE WHEN review_type = 'new' THEN 1 ELSE 0 END) AS new_rows, SUM(CASE WHEN ABS(price_delta_abs) > 0.0001 THEN 1 ELSE 0 END) AS price_changed, SUM(CASE WHEN supplier_quantity <> local_quantity OR supplier_stock_status_id <> local_stock_status_id THEN 1 ELSE 0 END) AS stock_changed, SUM(CASE WHEN ABS(price_delta_abs) > 0.0001 AND (supplier_quantity <> local_quantity OR supplier_stock_status_id <> local_stock_status_id) THEN 1 ELSE 0 END) AS price_stock_changed, SUM(CASE WHEN status = 'price_warning' THEN 1 ELSE 0 END) AS warnings, SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) AS duplicates, SUM(CASE WHEN status = 'excluded' THEN 1 ELSE 0 END) AS excluded FROM `" . DB_PREFIX . "ccp_ssp_review`" . $review_where);
        foreach (array('found_preview','matched','unmatched','new_rows','price_changed','stock_changed','price_stock_changed','warnings','duplicates','excluded') as $key) {
            $stats[$key] = isset($review->row[$key]) ? (int)$review->row[$key] : 0;
        }
        $new = $this->db->query("SELECT COUNT(*) AS total_new, SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS new_pending, SUM(CASE WHEN status = 'created' THEN 1 ELSE 0 END) AS new_created FROM `" . DB_PREFIX . "ccp_ssp_new_product`" . $review_where);
        $stats['total_new'] = isset($new->row['total_new']) ? (int)$new->row['total_new'] : 0;
        $stats['new_pending'] = isset($new->row['new_pending']) ? (int)$new->row['new_pending'] : 0;
        $stats['new_created'] = isset($new->row['new_created']) ? (int)$new->row['new_created'] : 0;
        $last = $this->db->query("SELECT supplier_product_url, date_modified FROM `" . DB_PREFIX . "ccp_ssp_queue`" . $where . " ORDER BY date_modified DESC LIMIT 1");
        $stats['last_url'] = $last->num_rows ? $last->row['supplier_product_url'] : '';
        $recent = $this->db->query("SELECT SUM(checked) AS checked_recent FROM `" . DB_PREFIX . "ccp_ssp_run`" . ($supplier_id ? " WHERE supplier_id = '" . $supplier_id . "' AND" : " WHERE") . " date_added > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
        $checked_recent = isset($recent->row['checked_recent']) ? (int)$recent->row['checked_recent'] : 0;
        $stats['speed_15m'] = $checked_recent;
        $stats['eta_seconds'] = ($checked_recent > 0 && $stats['remaining'] > 0) ? (int)ceil($stats['remaining'] * 900 / max(1, $checked_recent)) : 0;
        $lock = $this->getProcessLock($supplier_id);
        $stats['locked'] = !empty($lock['locked']) ? 1 : 0;
        $stats['lock_owner'] = isset($lock['owner']) ? $lock['owner'] : '';
        $stats['lock_expires_at'] = isset($lock['expires_at']) ? $lock['expires_at'] : '';
        $stats['stopped'] = $this->isQueueStopped() ? 1 : 0;
        return $stats;
    }

    protected function getProcessLock($supplier_id = 0) {
        $supplier_id = (int)$supplier_id;
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE expires_at <= NOW()");
        if ($supplier_id > 0) {
            $specific = 'queue_' . $supplier_id;
            $global = 'queue_0';
            $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name IN ('" . $this->db->escape($specific) . "','" . $this->db->escape($global) . "') AND expires_at > NOW() ORDER BY lock_name = '" . $this->db->escape($specific) . "' DESC LIMIT 1");
        } else {
            $q = $this->db->query("SELECT * FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name LIKE 'queue\_%' AND expires_at > NOW() ORDER BY date_modified DESC LIMIT 1");
        }
        if ($q->num_rows) {
            return array('locked' => 1, 'owner' => $q->row['owner'], 'expires_at' => $q->row['expires_at'], 'lock_name' => $q->row['lock_name']);
        }
        return array('locked' => 0, 'owner' => '', 'expires_at' => '', 'lock_name' => '');
    }

    protected function acquireProcessLock($supplier_id = 0, $source = 'manual', $ttl_seconds = 360) {
        $supplier_id = (int)$supplier_id;
        $lock_name = 'queue_' . $supplier_id;
        $owner = preg_replace('/[^a-z0-9_:-]/i', '', (string)$source) . '_' . substr(sha1(uniqid('', true)), 0, 10);
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE expires_at <= NOW()");
        if ($supplier_id > 0) {
            $global = 'queue_0';
            $exists = $this->db->query("SELECT lock_name, owner, expires_at FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name IN ('" . $this->db->escape($lock_name) . "','" . $this->db->escape($global) . "') LIMIT 1");
        } else {
            $exists = $this->db->query("SELECT lock_name, owner, expires_at FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name LIKE 'queue\_%' LIMIT 1");
        }
        if ($exists->num_rows) {
            return array('ok' => false, 'message' => 'Queue is already processing by ' . $exists->row['owner'] . ' until ' . $exists->row['expires_at']);
        }
        $ttl_seconds = max(60, min(1800, (int)$ttl_seconds));
        $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "ccp_ssp_runtime_lock` SET lock_name = '" . $this->db->escape($lock_name) . "', owner = '" . $this->db->escape($owner) . "', expires_at = DATE_ADD(NOW(), INTERVAL " . (int)$ttl_seconds . " SECOND), date_modified = NOW()");
        $check = $this->db->query("SELECT owner, expires_at FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name = '" . $this->db->escape($lock_name) . "' LIMIT 1");
        if ($check->num_rows && $check->row['owner'] === $owner) {
            return array('ok' => true, 'owner' => $owner, 'lock_name' => $lock_name);
        }
        return array('ok' => false, 'message' => 'Queue is already processing by ' . ($check->num_rows ? $check->row['owner'] . ' until ' . $check->row['expires_at'] : 'another process'));
    }

    protected function refreshProcessLock($supplier_id = 0, $owner = '', $ttl_seconds = 360) {
        $lock_name = 'queue_' . (int)$supplier_id;
        $owner = trim((string)$owner);
        if ($owner === '') {
            return false;
        }
        $ttl_seconds = max(60, min(1800, (int)$ttl_seconds));
        $this->db->query("UPDATE `" . DB_PREFIX . "ccp_ssp_runtime_lock` SET expires_at = DATE_ADD(NOW(), INTERVAL " . (int)$ttl_seconds . " SECOND), date_modified = NOW() WHERE lock_name = '" . $this->db->escape($lock_name) . "' AND owner = '" . $this->db->escape($owner) . "'");
        $check = $this->db->query("SELECT owner FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name = '" . $this->db->escape($lock_name) . "' LIMIT 1");
        return ($check->num_rows && (string)$check->row['owner'] === $owner);
    }

    protected function releaseProcessLock($supplier_id = 0, $owner = '') {
        $lock_name = 'queue_' . (int)$supplier_id;
        $owner = trim((string)$owner);
        if ($owner === '') {
            return;
        }
        $this->db->query("DELETE FROM `" . DB_PREFIX . "ccp_ssp_runtime_lock` WHERE lock_name = '" . $this->db->escape($lock_name) . "' AND owner = '" . $this->db->escape($owner) . "'");
    }

    protected function discoverSitemapProductUrls($supplier, $limit = 1000) {
        $limit = max(1, min(5000, (int)$limit));
        $base = isset($supplier['base_url']) ? trim((string)$supplier['base_url']) : '';
        if ($base === '') {
            return array();
        }
        $parts = parse_url($base);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return array();
        }
        $root = $parts['scheme'] . '://' . $parts['host'];
        $candidates = array(
            rtrim($root, '/') . '/sitemap.xml',
            rtrim($root, '/') . '/sitemap_index.xml',
            rtrim($root, '/') . '/index.php?route=extension/feed/google_sitemap'
        );
        $seen_maps = array();
        $urls = array();
        foreach ($candidates as $sitemap_url) {
            $this->readSitemapUrls($supplier, $sitemap_url, $urls, $seen_maps, 0, $limit);
            if (count($urls) >= $limit) {
                break;
            }
        }
        return array_values($urls);
    }

    protected function readSitemapUrls($supplier, $sitemap_url, &$urls, &$seen_maps, $depth, $limit) {
        if ($depth > 3 || count($urls) >= $limit) {
            return;
        }
        $sitemap_url = $this->normalizeUrl($sitemap_url, isset($supplier['base_url']) ? $supplier['base_url'] : '');
        if (!$sitemap_url || isset($seen_maps[$sitemap_url])) {
            return;
        }
        $seen_maps[$sitemap_url] = true;
        if (!$this->isSupplierHtmlUrlAllowed($sitemap_url, $supplier) && !$this->isSupplierSitemapUrlAllowed($sitemap_url, $supplier)) {
            return;
        }
        $fetch = $this->fetchUrl($sitemap_url, $supplier);
        if (empty($fetch['ok']) || empty($fetch['body'])) {
            return;
        }
        $body = (string)$fetch['body'];
        $locs = array();
        if (preg_match_all('#<loc>\s*([^<]+)\s*</loc>#i', $body, $m)) {
            foreach ($m[1] as $loc) {
                $locs[] = html_entity_decode(trim($loc), ENT_QUOTES, 'UTF-8');
            }
        }
        foreach ($locs as $loc) {
            if (count($urls) >= $limit) {
                break;
            }
            $loc_url = $this->normalizeUrl($loc, $sitemap_url);
            if (!$loc_url) {
                continue;
            }
            if (preg_match('#\.xml(\?.*)?$#i', (string)parse_url($loc_url, PHP_URL_PATH))) {
                $this->readSitemapUrls($supplier, $loc_url, $urls, $seen_maps, $depth + 1, $limit);
                continue;
            }
            if (!$this->isSupplierHtmlUrlAllowed($loc_url, $supplier)) {
                continue;
            }
            if ($this->looksLikeProductUrl($loc_url)) {
                $urls[$loc_url] = $loc_url;
            }
        }
    }

    protected function isSupplierSitemapUrlAllowed($url, $supplier) {
        $base = isset($supplier['base_url']) ? (string)$supplier['base_url'] : '';
        $u = parse_url($url);
        $b = parse_url($base);
        if (empty($u['host']) || empty($b['host'])) {
            return false;
        }
        return strtolower($u['host']) === strtolower($b['host']);
    }

    protected function splitLines($text) {
        $lines = preg_split('/\r\n|\r|\n/', (string)$text);
        $out = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }

    protected function normalizeUrl($url, $base) {
        $url = trim(html_entity_decode((string)$url, ENT_QUOTES, 'UTF-8'));
        if ($url === '' || preg_match('/^(javascript:|mailto:|tel:)/i', $url)) {
            return '';
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        $base_parts = parse_url($base);
        if (empty($base_parts['scheme']) || empty($base_parts['host'])) {
            return $url;
        }
        $root = $base_parts['scheme'] . '://' . $base_parts['host'];
        if (strpos($url, '/') === 0) {
            return $root . $url;
        }
        $path = isset($base_parts['path']) ? $base_parts['path'] : '/';
        $dir = preg_replace('#/[^/]*$#', '/', $path);
        return $root . $dir . $url;
    }

    protected function cleanText($text) {
        $text = html_entity_decode((string)$text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    protected function safeModelFromName($name) {
        $identifier = $this->extractIdentifier($name, array('код товару', 'код товара', 'артикул', 'sku', 'model', 'модель'));
        $identifier = $this->normalizeIdentifier($identifier);
        if ($identifier !== '') {
            return substr($identifier, 0, 64);
        }
        $name = $this->cleanImportedProductName($name);
        $hash = substr(sha1($name !== '' ? $name : microtime(true)), 0, 10);
        return 'SSP-' . strtoupper($hash);
    }


    protected function selectBestProductModel($sku, $ean, $upc, $mpn, $name, $url = '') {
        foreach (array($sku, $mpn, $ean, $upc) as $candidate) {
            $candidate = $this->normalizeIdentifier($candidate);
            if ($candidate !== '') {
                return substr($candidate, 0, 64);
            }
        }
        $from_name = $this->normalizeIdentifier($this->extractIdentifier($name, array('код товару', 'код товара', 'артикул', 'sku', 'model', 'модель')));
        if ($from_name !== '') {
            return substr($from_name, 0, 64);
        }
        $from_url = $this->extractModelFromUrl($url);
        if ($from_url !== '') {
            return substr($from_url, 0, 64);
        }
        return $this->safeModelFromName($name);
    }

    protected function extractModelFromUrl($url) {
        $path = (string)parse_url((string)$url, PHP_URL_PATH);
        $path = trim($path, '/');
        if ($path === '') { return ''; }
        $base = basename($path);
        $base = preg_replace('/\.(html?|php|aspx?)$/i', '', $base);
        $base = urldecode($base);
        $tokens = preg_split('/[^A-Za-z0-9]+/', $base);
        $best = '';
        foreach ((array)$tokens as $token) {
            $token = trim($token);
            if ($token === '') { continue; }
            if (preg_match('/^(ua|ru|en|product|tovar|item|catalog|category)$/i', $token)) { continue; }
            if (preg_match('/[A-Za-z]/', $token) && preg_match('/\d/', $token) && strlen($token) >= 3 && strlen($token) <= 40) {
                $best = $token;
            }
        }
        if ($best !== '') { return strtoupper($best); }
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', $base);
        $slug = trim($slug, '-');
        if ($slug !== '' && preg_match('/[A-Za-z]/', $slug) && preg_match('/\d/', $slug) && strlen($slug) <= 64) {
            return strtoupper($slug);
        }
        return '';
    }

    protected function cleanManufacturerName($value) {
        $value = $this->cleanText(strip_tags((string)$value));
        if ($value === '') { return ''; }
        $value = preg_replace('/^(производитель|виробник|manufacturer|brand|бренд|vendor)\s*[:：\-–—]?\s*/iu', '', $value);
        $value = preg_replace('/\s+(производитель|виробник|manufacturer|brand|бренд|vendor)\s*[:：\-–—]?\s*/iu', ' ', $value);
        $value = preg_replace('/\b(наличие|в наличии|продано|ціна|цена|price|uah|usd|eur)\b.*$/iu', '', $value);
        $value = trim($value, " \t\n\r\0\x0B:;-–—|");
        return substr($value, 0, 255);
    }

    protected function normalizeImportedDescription($description, $meta_description = '', $name = '') {
        $description = trim((string)$description);
        $plain = $this->cleanText(strip_tags($description));
        $meta = $this->cleanText($meta_description);
        if ($description === '' || $this->looksLikeSpecsOnlyDescription($plain, $name)) {
            if ($meta !== '') {
                return '<p>' . htmlspecialchars($meta, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            return '';
        }
        return $description;
    }

    protected function looksLikeSpecsOnlyDescription($plain, $name = '') {
        $plain = $this->cleanText($plain);
        if ($plain === '') { return false; }
        $length = function_exists('mb_strlen') ? mb_strlen($plain, 'UTF-8') : strlen($plain);
        $colon_count = substr_count($plain, ':');
        $label_hits = preg_match_all('/\b(производитель|виробник|производительность|продуктивність|страна|країна|перекачиваемые|рідини|жидкости|код|артикул|модель|sku|ean|upc|mpn)\b/iu', $plain, $m);
        if ($length <= 420 && ($colon_count >= 2 || $label_hits >= 3)) {
            return true;
        }
        if ($name !== '' && $this->lower($plain) === $this->lower($this->cleanText($name))) {
            return true;
        }
        return false;
    }

    protected function importProductImages($product_id, $data, $supplier, $url = '') {
        $product_id = (int)$product_id;
        if ($product_id <= 0 || !defined('DIR_IMAGE')) { return; }
        $main_url = isset($data['main_image_url']) ? trim((string)$data['main_image_url']) : '';
        $additional = isset($data['additional_image_urls']) && is_array($data['additional_image_urls']) ? $data['additional_image_urls'] : array();
        $saved_main = '';
        if ($main_url !== '') {
            $saved_main = $this->downloadSupplierImage($main_url, $supplier, $url, $product_id, 0);
            if ($saved_main !== '') {
                $this->db->query("UPDATE `" . DB_PREFIX . "product` SET image = '" . $this->db->escape($saved_main) . "', date_modified = NOW() WHERE product_id = '" . (int)$product_id . "'");
            }
        }
        if (empty($supplier['import_additional_images'])) { return; }
        $sort = 0;
        foreach ($additional as $image_url) {
            $image_url = trim((string)$image_url);
            if ($image_url === '' || $image_url === $main_url) { continue; }
            $saved = $this->downloadSupplierImage($image_url, $supplier, $url, $product_id, $sort + 1);
            if ($saved === '' || $saved === $saved_main) { continue; }
            $exists = $this->db->query("SELECT product_image_id FROM `" . DB_PREFIX . "product_image` WHERE product_id = '" . (int)$product_id . "' AND image = '" . $this->db->escape($saved) . "' LIMIT 1");
            if (!$exists->num_rows) {
                $this->db->query("INSERT INTO `" . DB_PREFIX . "product_image` SET product_id = '" . (int)$product_id . "', image = '" . $this->db->escape($saved) . "', sort_order = '" . (int)$sort . "'");
                $sort++;
            }
            if ($sort >= 8) { break; }
        }
    }

    protected function downloadSupplierImage($image_url, $supplier, $page_url = '', $product_id = 0, $index = 0) {
        $image_url = $this->normalizeUrl($image_url, $page_url !== '' ? $page_url : (isset($supplier['base_url']) ? $supplier['base_url'] : ''));
        if ($image_url === '' || !preg_match('#^https?://#i', $image_url)) { return ''; }
        $path = strtolower((string)parse_url($image_url, PHP_URL_PATH));
        if (preg_match('/(logo|favicon|banner|slider|sprite|placeholder|no[_-]?image|payment|delivery|icon|avatar)/i', $path)) { return ''; }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($ext, array('jpg','jpeg','png','webp','gif','avif'), true)) { $ext = 'jpg'; }
        $dir_rel = 'catalog/supplier_sync_parser_pro/' . (int)(isset($supplier['supplier_id']) ? $supplier['supplier_id'] : 0) . '/';
        $dir_abs = rtrim(DIR_IMAGE, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir_rel);
        if (!is_dir($dir_abs)) { @mkdir($dir_abs, 0775, true); }
        if (!is_dir($dir_abs) || !is_writable($dir_abs)) { return ''; }
        $name_seed = $this->extractModelFromUrl($image_url);
        if ($name_seed === '') { $name_seed = 'product-' . (int)$product_id . '-' . (int)$index; }
        $filename = strtolower(preg_replace('/[^a-z0-9_-]+/i', '-', $name_seed));
        $filename = trim($filename, '-');
        if ($filename === '') { $filename = substr(sha1($image_url), 0, 16); }
        $filename .= '-' . substr(sha1($image_url), 0, 8) . '.' . $ext;
        $target_abs = $dir_abs . $filename;
        $target_rel = $dir_rel . $filename;
        if (is_file($target_abs) && filesize($target_abs) > 0) { return $target_rel; }
        $body = $this->fetchBinaryUrl($image_url, $supplier);
        if ($body === '' || strlen($body) > 20971520) { return ''; }
        $tmp_abs = $target_abs . '.tmp-' . substr(sha1(uniqid('', true) . mt_rand()), 0, 12);
        if (@file_put_contents($tmp_abs, $body, LOCK_EX) === false) {
            @unlink($tmp_abs);
            return '';
        }
        $info = @getimagesize($tmp_abs);
        if (!$info) {
            @unlink($tmp_abs);
            return '';
        }
        if (!@rename($tmp_abs, $target_abs)) {
            @unlink($tmp_abs);
            if (!is_file($target_abs) || filesize($target_abs) <= 0) {
                return '';
            }
        }
        return $target_rel;
    }

    protected function fetchBinaryUrl($url, $supplier = array()) {
        $result = $this->fetchRemoteBody($url, $supplier, 20 * 1024 * 1024, false);
        return !empty($result['ok']) && isset($result['body']) ? (string)$result['body'] : '';
    }

    protected function slugify($text) {
        $map = array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'ye','ж'=>'zh','з'=>'z','и'=>'y','і'=>'i','ї'=>'yi','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ь'=>'','ы'=>'y','ъ'=>'','э'=>'e','ю'=>'yu','я'=>'ya');
        $text = $this->lower($text);
        $text = strtr($text, $map);
        $text = preg_replace('/[^a-z0-9]+/u', '-', $text);
        $text = trim($text, '-');
        return substr($text, 0, 120);
    }

    protected function jsonDecode($json) {
        $data = json_decode((string)$json, true);
        return is_array($data) ? $data : array();
    }

    protected function lower($text) {
        return function_exists('mb_strtolower') ? mb_strtolower((string)$text, 'UTF-8') : strtolower((string)$text);
    }

    protected function strpos($haystack, $needle) {
        return function_exists('mb_strpos') ? mb_strpos($haystack, $needle, 0, 'UTF-8') : strpos($haystack, $needle);
    }
}
