<?php
class ControllerExtensionModuleSupplierSyncParserPro extends Controller {
    private $error = array();
    private $version = '1.6.4';
    private $route = 'extension/module/supplier_sync_parser_pro';

    public function index() {
        $this->load->language($this->route);
        $this->document->setTitle($this->language->get('heading_title_text'));
        $this->load->model($this->route);
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            if ((string)$this->config->get('module_supplier_sync_parser_pro_schema_version') !== $this->version) {
                $this->model_extension_module_supplier_sync_parser_pro->install($this->version);
            }
            $redirect_supplier_id = 0;
            if (isset($this->request->post['supplier_id']) && (int)$this->request->post['supplier_id'] > 0) {
                $redirect_supplier_id = (int)$this->request->post['supplier_id'];
            } elseif (isset($this->request->get['supplier_id']) && (int)$this->request->get['supplier_id'] > 0) {
                $redirect_supplier_id = (int)$this->request->get['supplier_id'];
            } elseif (!empty($this->session->data['ccp_ssp_active_supplier_id'])) {
                $redirect_supplier_id = (int)$this->session->data['ccp_ssp_active_supplier_id'];
            }

            if (isset($this->request->post['save_settings'])) {
                $this->model_extension_module_supplier_sync_parser_pro->saveSettings($this->request->post);
                if ($redirect_supplier_id > 0) {
                    $this->session->data['ccp_ssp_active_supplier_id'] = $redirect_supplier_id;
                }
                $this->session->data['success'] = $this->language->get('text_success_settings');
            }
            if (isset($this->request->post['save_supplier'])) {
                $supplier_data = $this->request->post;
                if (empty($supplier_data['supplier_id']) && isset($supplier_data['source_preset']) && in_array($supplier_data['source_preset'], array('prom_classic','prom_modern','opencart_html'), true)) {
                    $preset_path = DIR_SYSTEM . 'library/codecart/presets/' . $supplier_data['source_preset'] . '.json';
                    $preset = is_file($preset_path) ? json_decode(file_get_contents($preset_path), true) : array();
                    if (is_array($preset)) {
                        $preset_defaults = array_merge($preset, isset($preset['settings']) && is_array($preset['settings']) ? $preset['settings'] : array());
                        $supplier_data = array_merge($preset_defaults, $supplier_data);
                    }
                }
                if (!empty($this->request->files['feed_file_upload']) && is_uploaded_file($this->request->files['feed_file_upload']['tmp_name'])) {
                    $upload_result = $this->saveSupplierFeedUpload($this->request->files['feed_file_upload']);
                    if (!empty($upload_result['ok'])) {
                        $supplier_data['feed_file'] = $upload_result['relative_path'];
                    } else {
                        $this->error['warning'] = $upload_result['message'];
                    }
                }
                if (!empty($this->error['warning'])) {
                    $this->session->data['error_warning'] = $this->error['warning'];
                    $active_tab = isset($this->request->post['active_tab']) ? preg_replace('/[^a-z0-9_-]/i', '', (string)$this->request->post['active_tab']) : 'supplier';
                    $redirect_query = 'user_token=' . $this->session->data['user_token'] . '&supplier_id=' . (int)$redirect_supplier_id . '&active_tab=' . $active_tab;
                    $this->response->redirect($this->url->link($this->route, $redirect_query, true));
                    return;
                }
                if (!empty($supplier_data['supplier_id'])) {
                    $existing_supplier = $this->model_extension_module_supplier_sync_parser_pro->getSupplier((int)$supplier_data['supplier_id']);
                    if ($existing_supplier) {
                        if (!isset($supplier_data['stock_map_text'])) {
                            $supplier_data['stock_map_text'] = $this->stockMapToText(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '');
                        }
                        if (!isset($supplier_data['category_map_text'])) {
                            $supplier_data['category_map_text'] = $this->categoryMapToText(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '');
                        }
                        if (!isset($supplier_data['excluded_skus_text'])) {
                            $supplier_data['excluded_skus_text'] = $this->settingsListToText(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '', 'excluded_skus');
                        }
                        if (!isset($supplier_data['excluded_urls_text'])) {
                            $supplier_data['excluded_urls_text'] = $this->settingsListToText(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '', 'excluded_urls');
                        }
                        if (!isset($supplier_data['excluded_categories_text'])) {
                            $supplier_data['excluded_categories_text'] = $this->settingsListToText(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '', 'excluded_categories');
                        }
                        if (!isset($supplier_data['allowed_hosts_text'])) {
                            $supplier_data['allowed_hosts_text'] = $this->settingsListToText(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '', 'allowed_hosts');
                        }
                        if (!isset($supplier_data['domain_policy'])) {
                            $supplier_data['domain_policy'] = $this->settingsValue(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '', 'domain_policy', 'strict_host');
                        }
                        foreach (array('in_stock_quantity'=>100, 'unknown_stock_policy'=>'keep', 'update_stock_status'=>1, 'new_category_name'=>'', 'source_adapter'=>'', 'match_source'=>'auto', 'match_target'=>'sku', 'jan_xpath'=>'', 'isbn_xpath'=>'', 'cron_enabled'=>0, 'cron_interval_minutes'=>60) as $setting_key=>$default_value) {
                            if (!isset($supplier_data[$setting_key])) {
                                $supplier_data[$setting_key] = $this->settingsValue(isset($existing_supplier['settings']) ? $existing_supplier['settings'] : '', $setting_key, $default_value);
                            }
                        }
                        $supplier_data = array_merge($existing_supplier, $supplier_data);
                    }
                }
                $supplier_id = $this->model_extension_module_supplier_sync_parser_pro->saveSupplier($supplier_data);
                $redirect_supplier_id = (int)$supplier_id;
                $this->session->data['ccp_ssp_active_supplier_id'] = (int)$supplier_id;
                $this->session->data['success'] = $this->language->get('text_success_supplier') . ' ID ' . (int)$supplier_id;
            }
            $active_tab = isset($this->request->post['active_tab']) ? preg_replace('/[^a-z0-9_-]/i', '', (string)$this->request->post['active_tab']) : '';
            $redirect_query = 'user_token=' . $this->session->data['user_token'] . '&supplier_id=' . (int)$redirect_supplier_id;
            if ($active_tab !== '') {
                $redirect_query .= '&active_tab=' . $active_tab;
            }
            $this->response->redirect($this->url->link($this->route, $redirect_query, true));
        }

        $data = $this->loadCommonData();
        $supplier_id = isset($this->request->get['supplier_id']) ? (int)$this->request->get['supplier_id'] : 0;
        if ($supplier_id > 0) {
            $this->session->data['ccp_ssp_active_supplier_id'] = $supplier_id;
        } elseif (!isset($this->request->get['supplier_id']) && !empty($this->session->data['ccp_ssp_active_supplier_id'])) {
            $supplier_id = (int)$this->session->data['ccp_ssp_active_supplier_id'];
        }
        $supplier = $supplier_id ? $this->model_extension_module_supplier_sync_parser_pro->getSupplier($supplier_id) : array();
        if (!$supplier && isset($this->request->get['preset']) && in_array($this->request->get['preset'], array('prom_classic','prom_modern','opencart_html'), true) && $this->user->hasPermission('access', $this->route)) {
            $preset_path = DIR_SYSTEM . 'library/codecart/presets/' . $this->request->get['preset'] . '.json';
            $preset = is_file($preset_path) ? json_decode(file_get_contents($preset_path), true) : array();
            if (is_array($preset)) {
                $supplier = $preset;
                $supplier['settings'] = json_encode(isset($preset['settings']) ? $preset['settings'] : array(), JSON_UNESCAPED_UNICODE);
            }
        }
        $data['supplier'] = $this->normalizeSupplier($supplier);
        $data['source_preset'] = !$supplier_id && isset($this->request->get['preset']) && in_array($this->request->get['preset'], array('prom_classic','prom_modern','opencart_html'), true) ? $this->request->get['preset'] : '';
        $data['preset_urls'] = array();
        foreach (array('prom_classic'=>'text_preset_prom_classic','prom_modern'=>'text_preset_prom_modern','opencart_html'=>'text_preset_opencart') as $code=>$name) {
            $data['preset_urls'][] = array('name'=>$this->language->get($name), 'url'=>$this->url->link($this->route, 'user_token=' . $this->session->data['user_token'] . '&supplier_id=0&preset=' . $code . '&active_tab=suppliers', true));
        }
        foreach (array('button_save_module', 'text_section_price', 'text_section_matching', 'text_section_stock', 'text_section_new_products', 'text_section_content', 'text_section_automation', 'text_section_feed', 'entry_in_stock_quantity', 'help_in_stock_quantity', 'entry_unknown_stock_policy', 'text_stock_keep', 'text_stock_zero', 'text_stock_available', 'text_stock_unavailable', 'entry_update_stock_status', 'help_update_stock_status', 'entry_new_category_name', 'help_new_category_name', 'entry_match_source', 'entry_match_target', 'help_match_mapping', 'text_match_auto', 'entry_jan_xpath', 'entry_isbn_xpath', 'entry_cron_enabled', 'entry_cron_interval_minutes', 'help_cron_profile') as $key) {
            $data[$key] = $this->language->get($key);
        }
        $data['supplier'] = $this->formatSupplierNumericFieldsForForm($data['supplier']);
        $data['supplier_first_url'] = $this->getFirstSupplierUrl($data['supplier']);
        $data['supplier_first_product_url'] = $this->getFirstSupplierProductUrl($data['supplier']);
        $data['supplier_first_list_url'] = $this->getFirstSupplierListUrl($data['supplier']);
        $data['stock_map_text'] = $this->stockMapToText($data['supplier']['settings']);
        $data['category_map_text'] = $this->categoryMapToText($data['supplier']['settings']);
        $data['excluded_skus_text'] = $this->settingsListToText($data['supplier']['settings'], 'excluded_skus');
        $data['excluded_urls_text'] = $this->settingsListToText($data['supplier']['settings'], 'excluded_urls');
        $data['excluded_categories_text'] = $this->settingsListToText($data['supplier']['settings'], 'excluded_categories');
        $data['allowed_hosts_text'] = $this->settingsListToText($data['supplier']['settings'], 'allowed_hosts');
        $data['suppliers'] = $this->model_extension_module_supplier_sync_parser_pro->getSuppliers();
        $data['categories'] = $this->model_extension_module_supplier_sync_parser_pro->getCategories();
        $data['currencies'] = $this->model_extension_module_supplier_sync_parser_pro->getCurrencies();
        $data['languages'] = $this->model_extension_module_supplier_sync_parser_pro->getLanguages();
        $data['base_currency'] = (string)$this->config->get('config_currency');
        $data['stock_statuses'] = $this->model_extension_module_supplier_sync_parser_pro->getStockStatuses();
        $data['active_supplier_id'] = (int)$supplier_id;
        if ((int)$supplier_id <= 0 && $data['active_tab'] !== '' && !in_array($data['active_tab'], array('master', 'suppliers', 'settings', 'about'), true)) {
            $data['active_tab'] = 'master';
        }
        $data['supplier_context_id'] = (int)$supplier_id > 0 ? (int)$supplier_id : -1;
        $data['review_filters'] = $this->collectListFilters('review', $data['supplier_context_id'], 100);
        $data['new_filters'] = $this->collectListFilters('new', $data['supplier_context_id'], 100);
        $data['queue_filters'] = $this->collectListFilters('queue', $data['supplier_context_id'], 100);
        $data['log_filters'] = $this->collectListFilters('log', $data['supplier_context_id'], 100);
        $data['history_filters'] = $this->collectListFilters('history', $data['supplier_context_id'], 100);
        $review_page = $this->model_extension_module_supplier_sync_parser_pro->getReviewsPaged($data['review_filters']);
        $review_page['rows'] = $this->localizeRows($review_page['rows'], array('last_error'));
        $data['reviews'] = $review_page['rows'];
        $data['review_page'] = $review_page;
        $data['review_stats'] = $this->model_extension_module_supplier_sync_parser_pro->getReviewStatsDb($data['supplier_context_id']);
        $new_page = $this->model_extension_module_supplier_sync_parser_pro->getNewProductsPaged($data['new_filters']);
        $new_page['rows'] = $this->localizeRows($new_page['rows'], array('last_error'));
        $data['new_products'] = $new_page['rows'];
        $data['new_page'] = $new_page;
        $data['new_stats'] = $this->model_extension_module_supplier_sync_parser_pro->getNewStatsDb($data['supplier_context_id']);
        $queue_page = $this->model_extension_module_supplier_sync_parser_pro->getQueuePaged($data['queue_filters']);
        $queue_page['rows'] = $this->localizeRows($queue_page['rows'], array('last_error'));
        $data['queue'] = $queue_page['rows'];
        $data['queue_page'] = $queue_page;
        $log_page = $this->model_extension_module_supplier_sync_parser_pro->getLogsPaged($data['log_filters']);
        $log_page['rows'] = $this->localizeRows($log_page['rows'], array('title','message'));
        $data['logs'] = $log_page['rows'];
        $data['log_page'] = $log_page;
        $history_page = $this->model_extension_module_supplier_sync_parser_pro->getHistoryPaged($data['history_filters']);
        $history_page['rows'] = $this->localizeRows($history_page['rows'], array('action','field_changed','note'));
        $data['history'] = $history_page['rows'];
        $data['history_page'] = $history_page;
        $data['runs'] = $this->model_extension_module_supplier_sync_parser_pro->getRuns(80, $data['supplier_context_id']);
        $data['exclusions'] = $this->model_extension_module_supplier_sync_parser_pro->getExclusions($data['supplier_context_id'], 200);
        $data['category_map_rows'] = $this->model_extension_module_supplier_sync_parser_pro->getCategoryMapRows($data['supplier_context_id'], 300);
        $data['links'] = $this->model_extension_module_supplier_sync_parser_pro->getLinks(120, $data['supplier_context_id']);
        $data['module_supplier_sync_parser_pro_status'] = (int)$this->config->get('module_supplier_sync_parser_pro_status');
        $data['module_supplier_sync_parser_pro_cron_token'] = (string)$this->config->get('module_supplier_sync_parser_pro_cron_token');
        $data['module_supplier_sync_parser_pro_batch_limit'] = (int)$this->config->get('module_supplier_sync_parser_pro_batch_limit') ?: 20;
        $data['module_supplier_sync_parser_pro_stop_queue'] = (int)$this->config->get('module_supplier_sync_parser_pro_stop_queue');
        $data['module_supplier_sync_parser_pro_review_retention_days'] = (int)$this->config->get('module_supplier_sync_parser_pro_review_retention_days') ?: 14;
        $data['module_supplier_sync_parser_pro_log_retention_days'] = (int)$this->config->get('module_supplier_sync_parser_pro_log_retention_days') ?: 60;
        $data['module_supplier_sync_parser_pro_history_retention_days'] = (int)$this->config->get('module_supplier_sync_parser_pro_history_retention_days') ?: 180;
        $data['cron_url'] = HTTP_CATALOG . 'index.php?route=extension/module/supplier_sync_parser_pro/cron';
        $data['cron_command'] = "curl -fsS --max-time 120 -H " . escapeshellarg('X-CCP-Cron-Key: ' . (string)$data['module_supplier_sync_parser_pro_cron_token']) . " " . escapeshellarg($data['cron_url'] . '&limit=' . (int)$data['module_supplier_sync_parser_pro_batch_limit']) . " >/dev/null";

        $data['queue_stats'] = array('total' => 0, 'pending' => 0, 'processing' => 0, 'done' => 0, 'error' => 0, 'skipped' => 0);
        if ((int)$data['module_supplier_sync_parser_pro_status']) {
            require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
            $engine = new CodecartSupplierSyncParser($this->registry);
            $data['queue_stats'] = ((int)$data['active_supplier_id'] > 0) ? $engine->getQueueStats($data['active_supplier_id']) : $data['queue_stats'];
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view($this->route, $data));
    }

    public function install() {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission('modify', 'marketplace/extension')) { return; }
        $this->load->model($this->route);
        $this->model_extension_module_supplier_sync_parser_pro->install($this->version);

        $this->load->model('user/user_group');
        if (isset($this->user) && method_exists($this->user, 'getGroupId')) {
            $user_group_id = (int)$this->user->getGroupId();
            if ($user_group_id > 0) {
                $this->model_user_user_group->addPermission($user_group_id, 'access', $this->route);
                $this->model_user_user_group->addPermission($user_group_id, 'modify', $this->route);
            }
        }
    }

    public function uninstall() {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission('modify', $this->route)) { return; }
        $this->load->model($this->route);
        $this->model_extension_module_supplier_sync_parser_pro->uninstall();
    }

    public function testProduct() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $url = isset($this->request->post['url']) ? trim((string)$this->request->post['url']) : '';
        $url = $this->resolveSupplierTestUrl($supplier_id, $url);
        if ($url === '') {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_test_url_required')));
        }
        try {
            require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
            $engine = new CodecartSupplierSyncParser($this->registry);
            $result = $engine->testProductUrl($supplier_id, $url);
            $result['url'] = $url;
            return $this->json($this->localizeEngineResult($result));
        } catch (Throwable $e) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_diagnostics_failed') . ': ' . $e->getMessage(), 'url' => $url));
        }
    }

    public function autoDetectRules() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'), 'fields' => array()));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $url = isset($this->request->post['url']) ? trim((string)$this->request->post['url']) : '';
        $url = $this->resolveSupplierTestUrl($supplier_id, $url);
        if ($url === '') {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_test_url_required'), 'fields' => array()));
        }
        try {
            require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
            $engine = new CodecartSupplierSyncParser($this->registry);
            $result = $engine->autoDetectRules($supplier_id, $url);
            $result['url'] = $url;
            return $this->json($this->localizeEngineResult($result));
        } catch (Throwable $e) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_diagnostics_failed') . ': ' . $e->getMessage(), 'fields' => array(), 'url' => $url));
        }
    }

    public function saveDetectedRules() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'), 'saved' => 0));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $rules = isset($this->request->post['rules']) && is_array($this->request->post['rules']) ? $this->request->post['rules'] : array();
        $this->load->model($this->route);
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required'), 'saved' => 0));
        }
        $saved = $this->model_extension_module_supplier_sync_parser_pro->updateDetectedRules($supplier_id, $rules);
        $list_url_saved = false;
        $list_url = isset($this->request->post['list_url']) ? trim((string)$this->request->post['list_url']) : '';
        if ($list_url !== '') {
            $list_url_saved = $this->model_extension_module_supplier_sync_parser_pro->updateSupplierListUrls($supplier_id, $list_url, false);
        }
        return $this->json(array('ok' => true, 'message' => $this->language->get('text_success_detected_rules_saved'), 'saved' => (int)$saved, 'list_url_saved' => $list_url_saved ? 1 : 0));
    }

    public function autoDetectListRules() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'), 'fields' => array()));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $url = isset($this->request->post['url']) ? trim((string)$this->request->post['url']) : '';
        $url = $this->resolveSupplierTestUrl($supplier_id, $url);
        if ($url === '') {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_test_url_required'), 'fields' => array()));
        }
        try {
            require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
            $engine = new CodecartSupplierSyncParser($this->registry);
            $result = $engine->autoDetectListRules($supplier_id, $url);
            $result['url'] = $url;
            return $this->json($this->localizeEngineResult($result));
        } catch (Throwable $e) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_diagnostics_failed') . ': ' . $e->getMessage(), 'fields' => array(), 'url' => $url));
        }
    }


    public function autoDetectFeedRules() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'), 'fields' => array()));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required'), 'fields' => array()));
        }
        try {
            require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
            $engine = new CodecartSupplierSyncParser($this->registry);
            $result = $engine->autoDetectFeedRules($supplier_id);
            return $this->json($this->localizeEngineResult($result));
        } catch (Throwable $e) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_diagnostics_failed') . ': ' . $e->getMessage(), 'fields' => array()));
        }
    }

    public function toggleSupplierStatus() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission')));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $status = !empty($this->request->post['status']) ? 1 : 0;
        $this->load->model($this->route);
        if (!$supplier_id || !$this->model_extension_module_supplier_sync_parser_pro->getSupplier($supplier_id)) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('engine_supplier_not_found')));
        }
        $this->model_extension_module_supplier_sync_parser_pro->setSupplierStatus($supplier_id, $status);
        return $this->json(array('ok' => true, 'status' => $status, 'message' => $this->language->get('text_success_supplier_status_changed')));
    }

    public function saveSupplierNewFlags() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission')));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $create_new = isset($this->request->post['create_new']) ? (int)$this->request->post['create_new'] : 0;
        $create_new_status = isset($this->request->post['create_new_status']) ? (int)$this->request->post['create_new_status'] : 0;
        $this->load->model($this->route);
        if (!$supplier_id || !$this->model_extension_module_supplier_sync_parser_pro->getSupplier($supplier_id)) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('engine_supplier_not_found')));
        }
        $this->model_extension_module_supplier_sync_parser_pro->setSupplierNewFlags($supplier_id, $create_new ? 1 : 0, $create_new_status ? 1 : 0);
        $this->session->data['ccp_ssp_active_supplier_id'] = $supplier_id;
        return $this->json(array('ok' => true, 'supplier_id' => $supplier_id, 'create_new' => $create_new ? 1 : 0, 'create_new_status' => $create_new_status ? 1 : 0, 'message' => $this->language->get('text_success_supplier_new_flags_saved')));
    }

    public function deleteSupplier() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission')));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $this->load->model($this->route);
        if (!$supplier_id || !$this->model_extension_module_supplier_sync_parser_pro->getSupplier($supplier_id)) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('engine_supplier_not_found')));
        }
        $this->model_extension_module_supplier_sync_parser_pro->deleteSupplier($supplier_id);
        return $this->json(array('ok' => true, 'supplier_id' => $supplier_id, 'message' => $this->language->get('text_success_supplier_deleted')));
    }

    public function buildQueue() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'created' => 0, 'message' => $this->language->get('error_supplier_required')));
        }
        $mode = isset($this->request->post['mode']) ? (string)$this->request->post['mode'] : 'scan';
        if (!in_array($mode, array('scan', 'scan_site', 'sitemap', 'linked_products', 'new_only', 'product_urls', 'feed_file'), true)) {
            $mode = 'scan';
        }
        $this->releaseSessionLock();
        require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
        $engine = new CodecartSupplierSyncParser($this->registry);
        $limit_pages = 200;
        if ($mode === 'sitemap') {
            $limit_pages = 5000;
        } elseif ($mode === 'scan_site') {
            $limit_pages = 1000;
        } elseif ($mode === 'scan') {
            $limit_pages = 1000;
        } elseif ($mode === 'product_urls') {
            $limit_pages = 5000;
        } elseif ($mode === 'feed_file') {
            $limit_pages = 10000;
        }
        $json = $engine->buildQueue($supplier_id, $mode, $limit_pages);
        $json['stats'] = $engine->getQueueStats($supplier_id);
        $this->json($this->localizeEngineResult($json));
    }

    public function runQueue() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'processed' => 0, 'message' => $this->language->get('error_supplier_required')));
        }
        $limit = isset($this->request->post['limit']) ? (int)$this->request->post['limit'] : ((int)$this->config->get('module_supplier_sync_parser_pro_batch_limit') ?: 20);
        $action_filter = isset($this->request->post['action_filter']) ? (string)$this->request->post['action_filter'] : '';
        if (!in_array($action_filter, array('scan_list','check_product','import_item'), true)) {
            $action_filter = '';
        }
        $this->releaseSessionLock();
        require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
        $engine = new CodecartSupplierSyncParser($this->registry);
        $queue_ids = isset($this->request->post['queue_ids']) ? $this->request->post['queue_ids'] : array();
        $json = $engine->processQueueBatch($supplier_id, $limit, 'manual', $action_filter, $queue_ids);
        if (!isset($json['ok'])) {
            $json['ok'] = true;
        }
        $this->json($this->localizeEngineResult($json));
    }


    public function queueStatus() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $this->load->model($this->route);
        require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
        $engine = new CodecartSupplierSyncParser($this->registry);
        return $this->json(array('ok' => true, 'stopped' => (int)$this->model_extension_module_supplier_sync_parser_pro->getQueueStopFlag(), 'stats' => $engine->getQueueStats($supplier_id)));
    }

    public function applyReviews() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['review_ids']) ? $this->request->post['review_ids'] : array();
        $mode = isset($this->request->post['mode']) ? $this->request->post['mode'] : '';
        require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
        $engine = new CodecartSupplierSyncParser($this->registry);
        return $this->json($this->localizeEngineResult($engine->applySelectedReviews($ids, $mode, $supplier_id)));
    }

    public function rollbackHistory() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok'=>false, 'message'=>$this->error['warning']));
        }
        require_once DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php';
        $engine = new CodecartSupplierSyncParser($this->registry);
        $result = $engine->rollbackPriceStockHistory((int)($this->request->post['history_id'] ?? 0), (int)($this->request->post['supplier_id'] ?? 0));
        $result['message'] = $this->language->get('text_' . $result['code']);
        return $this->json($result);
    }

    public function applyNewProducts() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['new_ids']) ? $this->request->post['new_ids'] : array();
        $category_ids = isset($this->request->post['category_ids']) ? $this->request->post['category_ids'] : array();
        require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
        $engine = new CodecartSupplierSyncParser($this->registry);
        return $this->json($this->localizeEngineResult($engine->applySelectedNewProducts($ids, $category_ids, $supplier_id)));
    }

    public function searchProducts() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission')), 'products' => array()));
        }
        $query = isset($this->request->post['query']) ? trim($this->request->post['query']) : '';
        $this->load->model($this->route);
        $products = $this->model_extension_module_supplier_sync_parser_pro->searchProducts($query, 20);
        return $this->json(array('ok' => true, 'products' => $products));
    }

    public function matchReviewProduct() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $review_id = isset($this->request->post['review_id']) ? (int)$this->request->post['review_id'] : 0;
        $product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
        require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
        $engine = new CodecartSupplierSyncParser($this->registry);
        $result = $this->localizeEngineResult($engine->manualMatchReviewProduct($review_id, $product_id, $supplier_id));
        if (!empty($result['status'])) {
            $result['status_label'] = $this->getStatusLabel((string)$result['status']);
        }
        return $this->json($result);
    }

    public function clearReviews() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $this->load->model($this->route);
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $status = isset($this->request->post['status']) ? trim($this->request->post['status']) : '';
        $this->model_extension_module_supplier_sync_parser_pro->clearReviews($supplier_id, $status);
        $this->json(array('ok' => true, 'message' => $this->language->get('text_success_reviews_clear')));
    }

    public function clearQueue() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $this->load->model($this->route);
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $status = isset($this->request->post['status']) ? trim($this->request->post['status']) : '';
        $this->model_extension_module_supplier_sync_parser_pro->clearQueue($supplier_id, $status);
        $this->json(array('ok' => true, 'message' => $this->language->get('text_success_queue_clear')));
    }

    public function retrySelectedQueue() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['queue_ids']) ? $this->request->post['queue_ids'] : array();
        $this->load->model($this->route);
        $updated = $this->model_extension_module_supplier_sync_parser_pro->retrySelectedQueueJobs($ids, $supplier_id);
        return $this->json(array('ok' => true, 'updated' => (int)$updated, 'message' => $this->language->get('text_done')));
    }

    public function deleteSelectedQueue() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['queue_ids']) ? $this->request->post['queue_ids'] : array();
        $this->load->model($this->route);
        $deleted = $this->model_extension_module_supplier_sync_parser_pro->deleteSelectedQueueJobs($ids, $supplier_id);
        return $this->json(array('ok' => true, 'deleted' => (int)$deleted, 'message' => $this->language->get('text_done')));
    }

    public function deleteSelectedReviews() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['review_ids']) ? $this->request->post['review_ids'] : array();
        $this->load->model($this->route);
        $deleted = $this->model_extension_module_supplier_sync_parser_pro->deleteSelectedReviewRows($ids, $supplier_id);
        return $this->json(array('ok' => true, 'deleted' => (int)$deleted, 'message' => $this->language->get('text_done')));
    }

    public function deleteSelectedNewProducts() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['new_ids']) ? $this->request->post['new_ids'] : array();
        $this->load->model($this->route);
        $deleted = $this->model_extension_module_supplier_sync_parser_pro->deleteSelectedNewProductRows($ids, $supplier_id);
        return $this->json(array('ok' => true, 'deleted' => (int)$deleted, 'message' => $this->language->get('text_done')));
    }

    public function updateNewProductsStatus() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['new_ids']) ? $this->request->post['new_ids'] : array();
        $status = isset($this->request->post['status']) ? (string)$this->request->post['status'] : 'pending';
        $this->load->model($this->route);
        $updated = $this->model_extension_module_supplier_sync_parser_pro->setSelectedNewProductsStatus($ids, $status, $supplier_id);
        return $this->json(array('ok' => true, 'updated' => (int)$updated, 'message' => $this->language->get('text_done')));
    }

    public function resetProcessing() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $this->load->model($this->route);
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $this->model_extension_module_supplier_sync_parser_pro->resetProcessingJobs($supplier_id);
        $this->json(array('ok' => true, 'message' => $this->language->get('text_success_processing_reset')));
    }


    public function recoverErrors() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $this->load->model($this->route);
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $this->model_extension_module_supplier_sync_parser_pro->recoverErrorJobs($supplier_id);
        $this->json(array('ok' => true, 'message' => $this->language->get('text_success_errors_recovered')));
    }


    public function stopQueue() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $this->load->model($this->route);
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $this->model_extension_module_supplier_sync_parser_pro->setQueueStopFlag(1);
        $this->model_extension_module_supplier_sync_parser_pro->resetProcessingJobs($supplier_id);
        return $this->json(array('ok' => true, 'supplier_id' => $supplier_id, 'message' => $this->language->get('text_success_queue_stopped')));
    }

    public function resumeQueue() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $this->load->model($this->route);
        $this->model_extension_module_supplier_sync_parser_pro->setQueueStopFlag(0);
        return $this->json(array('ok' => true, 'message' => $this->language->get('text_success_queue_resumed')));
    }

    public function excludeSelectedReviews() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $ids = isset($this->request->post['review_ids']) ? $this->request->post['review_ids'] : array();
        $reason = isset($this->request->post['reason']) ? trim($this->request->post['reason']) : 'Manual exclusion';
        $this->load->model($this->route);
        $result = $this->model_extension_module_supplier_sync_parser_pro->saveSelectedReviewExclusions($ids, $reason, $supplier_id);
        return $this->json(array('ok' => true, 'message' => $this->language->get('text_success_excluded'), 'excluded' => (int)$result['excluded']));
    }


    public function cleanupOldData() {
        $this->load->language($this->route);
        if (!$this->validateOperational()) {
            return $this->json(array('ok' => false, 'message' => (isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission'))));
        }
        $this->load->model($this->route);
        $this->model_extension_module_supplier_sync_parser_pro->cleanupOldData();
        return $this->json(array('ok' => true, 'message' => $this->language->get('text_success_cleanup')));
    }

    public function clearLogs() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $this->load->model($this->route);
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        if ($supplier_id <= 0) {
            return $this->json(array('ok' => false, 'message' => $this->language->get('error_supplier_required')));
        }
        $this->model_extension_module_supplier_sync_parser_pro->clearLogs($supplier_id);
        $this->json(array('ok' => true, 'message' => $this->language->get('text_success_logs_clear')));
    }

    public function clearHistory() {
        $this->load->language($this->route);
        $json = array();
        if (!$this->validateOperational()) {
            $json['ok'] = false;
            $json['message'] = isset($this->error['warning']) ? $this->error['warning'] : $this->language->get('error_permission');
            return $this->json($json);
        }
        $this->load->model($this->route);
        $supplier_id = isset($this->request->post['supplier_id']) ? (int)$this->request->post['supplier_id'] : 0;
        $this->model_extension_module_supplier_sync_parser_pro->clearHistory($supplier_id);
        $this->json(array('ok' => true, 'message' => $this->language->get('text_success_history_clear')));
    }

    private function releaseSessionLock() {
        if (function_exists('session_write_close')) {
            @session_write_close();
        }
    }

    private function adminModuleLink($route = '', $args = '') {
        $server = defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '');
        $url = rtrim($server, '/') . '/index.php?route=' . ($route !== '' ? $route : $this->route);
        $args = str_replace('&amp;', '&', (string)$args);
        if ($args !== '') {
            $url .= '&' . ltrim($args, '&');
        }
        return $url;
    }

    private function loadCommonData() {
        $data = array();
        $data['heading_title'] = $this->language->get('heading_title');
        $data['heading_title_text'] = $this->language->get('heading_title_text');
        $data['version'] = $this->version;
        $data['user_token'] = $this->session->data['user_token'];
        $data['route'] = $this->route;
        $server = defined('HTTPS_SERVER') ? HTTPS_SERVER : (defined('HTTP_SERVER') ? HTTP_SERVER : '');
        $data['admin_index_url'] = rtrim($server, '/') . '/index.php';
        $data['user_token'] = isset($this->session->data['user_token']) ? $this->session->data['user_token'] : '';
        $data['route_url'] = $this->adminModuleLink($this->route, 'user_token=' . $this->session->data['user_token']);
        $data['active_tab'] = isset($this->request->get['active_tab']) ? preg_replace('/[^a-z0-9_-]/i', '', (string)$this->request->get['active_tab']) : 'master';
        if ($data['active_tab'] === '') {
            $data['active_tab'] = 'master';
        }
        $data['action'] = $this->adminModuleLink($this->route, 'user_token=' . $this->session->data['user_token']);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['ajax_test_product'] = $this->adminModuleLink($this->route . '/testProduct', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_auto_detect_rules'] = $this->adminModuleLink($this->route . '/autoDetectRules', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_auto_detect_list_rules'] = $this->adminModuleLink($this->route . '/autoDetectListRules', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_auto_detect_feed_rules'] = $this->adminModuleLink($this->route . '/autoDetectFeedRules', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_save_detected_rules'] = $this->adminModuleLink($this->route . '/saveDetectedRules', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_build_queue'] = $this->adminModuleLink($this->route . '/buildQueue', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_run_queue'] = $this->adminModuleLink($this->route . '/runQueue', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_queue_status'] = $this->adminModuleLink($this->route . '/queueStatus', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_apply_reviews'] = $this->adminModuleLink($this->route . '/applyReviews', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_apply_new_products'] = $this->adminModuleLink($this->route . '/applyNewProducts', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_search_products'] = $this->adminModuleLink($this->route . '/searchProducts', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_match_review_product'] = $this->adminModuleLink($this->route . '/matchReviewProduct', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_clear_reviews'] = $this->adminModuleLink($this->route . '/clearReviews', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_clear_queue'] = $this->adminModuleLink($this->route . '/clearQueue', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_retry_queue_selected'] = $this->adminModuleLink($this->route . '/retrySelectedQueue', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_delete_queue_selected'] = $this->adminModuleLink($this->route . '/deleteSelectedQueue', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_delete_review_selected'] = $this->adminModuleLink($this->route . '/deleteSelectedReviews', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_delete_new_selected'] = $this->adminModuleLink($this->route . '/deleteSelectedNewProducts', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_update_new_status'] = $this->adminModuleLink($this->route . '/updateNewProductsStatus', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_reset_processing'] = $this->adminModuleLink($this->route . '/resetProcessing', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_clear_logs'] = $this->adminModuleLink($this->route . '/clearLogs', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_clear_history'] = $this->adminModuleLink($this->route . '/clearHistory', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_recover_errors'] = $this->adminModuleLink($this->route . '/recoverErrors', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_stop_queue'] = $this->adminModuleLink($this->route . '/stopQueue', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_resume_queue'] = $this->adminModuleLink($this->route . '/resumeQueue', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_exclude_selected_reviews'] = $this->adminModuleLink($this->route . '/excludeSelectedReviews', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_cleanup_old_data'] = $this->adminModuleLink($this->route . '/cleanupOldData', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_rollback_history'] = $this->adminModuleLink($this->route . '/rollbackHistory', 'user_token=' . $this->session->data['user_token']);
        $data['button_rollback_price_stock'] = $this->language->get('button_rollback_price_stock');
        $data['confirm_rollback_price_stock'] = $this->language->get('confirm_rollback_price_stock');
        $data['ajax_toggle_supplier_status'] = $this->adminModuleLink($this->route . '/toggleSupplierStatus', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_delete_supplier'] = $this->adminModuleLink($this->route . '/deleteSupplier', 'user_token=' . $this->session->data['user_token']);
        $data['ajax_save_supplier_new_flags'] = $this->adminModuleLink($this->route . '/saveSupplierNewFlags', 'user_token=' . $this->session->data['user_token']);
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)),
            array('text' => $this->language->get('heading_title_text'), 'href' => $this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true))
        );
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : (isset($this->session->data['error_warning']) ? $this->session->data['error_warning'] : '');
        unset($this->session->data['error_warning']);
        $keys = array(
            'button_scan_product_page','button_detect_list_links','text_rules_product_scan_label','text_rules_list_scan_label','help_rules_product_scan','help_rules_list_scan','text_auto_detect_best_hint','text_confirm_delete_supplier','text_success_supplier_deleted','text_success_supplier_status_changed','text_queue_scenario_title','text_queue_scenario_direct','text_queue_scenario_scan','text_queue_scenario_linked','text_queue_scenario_run','text_ajax_working','text_base_currency','text_supplier_next_steps_title','text_supplier_next_steps_desc','text_queue_quick_start','text_queue_mode_scan','text_queue_mode_product_urls','text_queue_mode_linked','text_queue_mode_new_only','text_queue_action_scan_list','text_queue_action_check_product','text_queue_action_import_item','text_queue_open_preview_hint','text_auto_detect_title','text_auto_detect_intro','text_auto_detect_url','text_auto_detect_confidence','text_auto_detect_source','text_auto_detect_value','text_auto_detect_xpath','text_auto_detect_field','text_auto_detect_select','text_detect_xpath_override','help_detect_xpath_override','text_auto_detect_high','text_auto_detect_medium','text_auto_detect_low','text_auto_detect_none','text_success_detected_rules_saved','error_supplier_required','button_auto_detect','button_save_detected_rules','button_apply_detected_to_form','button_scan_save_rules','button_rules_manual_toggle','help_auto_detect','text_rules_quick_setup_title','text_rules_quick_setup_intro','text_rules_quick_setup_note','text_rules_manual_title','text_rules_manual_intro','text_switch_on','text_switch_off','text_ui_workflow_title','text_ui_step_supplier_title','text_ui_step_supplier_desc','text_ui_step_rules_title','text_ui_step_rules_desc','text_ui_step_queue_title','text_ui_step_queue_desc','text_ui_step_apply_title','text_ui_step_apply_desc','text_ui_ajax_status','text_ui_safe_note','text_ui_admin_ready','help_field_base_url','help_field_list_urls','help_field_currency','help_field_language','help_field_force_new_category','help_field_price_formula','help_field_delay','help_field_create_new','help_import_additional_images','help_field_xpath_required','help_field_exclusions','text_supplier_context_title','text_supplier_context_help','text_supplier_context_required','text_domain_policy_title','text_domain_policy_strict','text_domain_policy_subdomains','text_domain_policy_extra','help_domain_policy','entry_domain_policy','entry_allowed_hosts','engine_url_outside_domain','text_rounding_two','text_rounding_integer','text_rounding_up_integer','text_rounding_none','text_no_selected_rows','text_done','text_loading','text_moved_new','text_deleted','text_created_product_ids','text_preview_move_to_new_hint','text_new_create_hint','text_new_creation_disabled_hint','text_error_details','text_success_supplier_new_flags_saved','text_ajax_error','text_created','text_updated','text_skipped','text_errors','text_processed','text_preview','text_price_short','text_stock_short','text_running','text_confirm_force_price','text_confirm_full_update','text_confirm_create_new','text_confirm_clear','text_saved_tab_note','text_queue_loop_already_running','text_seo_all_languages','text_seo_main_language','text_mode_keep','text_mode_update_empty','text_mode_overwrite','text_version','text_license','text_license_note','error_module_disabled','column_opencart_id',
            'text_ui_step_scan_title','text_ui_step_scan_desc','text_ui_step_process_title','text_ui_step_process_desc','text_check_supplier_saved','text_check_base_url','text_check_product_rules','text_check_queue_source','text_ready','text_not_ready','text_ready_category_scan','text_ready_direct_urls','text_rules_step_product_url','text_rules_step_choose_values','text_rules_step_save_rules','text_rules_step_test','text_queue_group_create','text_queue_group_process','text_queue_group_service','text_queue_group_create_help','text_queue_group_process_help','text_queue_group_service_help','text_ajax_table_updated','text_about_benefit_sync_title','text_about_benefit_sync','text_about_benefit_safe_title','text_about_benefit_safe','text_about_benefit_mass_title','text_about_benefit_mass','text_about_benefit_lang_title','text_about_benefit_lang','text_about_workflow_title','text_about_step_1','text_about_step_2','text_about_step_3','text_about_step_4','text_about_step_5','text_edit','text_enabled','text_disabled','text_select','text_none','text_yes','text_no','text_status','text_author','text_compatibility','text_warning_backup','text_cron_hint','text_manual_mode','text_existing','text_new','text_excluded','text_price_changed','text_stock_changed','text_price_stock_changed','text_same','text_rows_shown','text_sort_hint','text_missing_policy_report','text_missing_policy_out','text_missing_policy_disable','text_missing_policy_zero','text_formula_same','text_formula_purchase_markup','text_formula_retail_discount_markup','text_queue_stop_active','text_success_queue_stopped','text_success_queue_resumed','text_success_excluded','text_server_filter_hint','text_success_cleanup','text_formula_preview','text_category_map_visual','text_pagination_summary','text_eta','text_speed_15m','text_matched','text_unmatched','text_last_url','text_locked','text_remaining','text_completed','text_found_preview','text_new_pending','text_new_created',
            'tab_suppliers','tab_rules','tab_mapping','tab_review','tab_queue','tab_new','tab_history','tab_logs','tab_diagnostics','tab_settings','tab_about',
            'button_save','button_cancel','button_delete','button_add_supplier','button_test','button_build_queue','button_scan_site_all','button_scan_sitemap','button_build_product_urls','button_run_queue','button_stop_queue','button_resume_queue','button_exclude_selected','button_clear_queue','button_reset_processing','button_clear_logs','button_clear_history','button_apply_price','button_apply_stock','button_apply_price_stock','button_create_selected','button_skip_selected','button_clear_reviews','button_force_price','button_force_price_stock','button_search_product','button_match_product','button_create_new_tab','button_filter_reset','button_only_changed','button_toggle_match','button_recover_errors','button_full_update','button_run_until_done','button_run_scan_batch','button_run_products_batch','button_run_products_until_done','button_run_selected_scan','button_run_selected_products','button_stop_current_process','text_queue_category_next_step','text_found_urls','text_scan_pages_left','text_products_left','text_master_title','text_master_intro','text_master_supplier','text_master_rules','text_master_find_links','text_master_find_links_help','text_master_check_sample','text_master_check_sample_help','text_master_check_all','text_master_check_all_help','text_master_apply','text_master_queue','text_master_remaining','text_master_create_supplier_title','text_master_create_supplier_help','button_master_create_supplier','button_master_edit_supplier','text_master_supplier_selected','text_master_supplier_selected_help','button_cleanup_old_data','button_check_linked','button_scan_new_only','button_auto_detect_feed','button_apply_feed_detected','text_feed_mapping_title','text_feed_mapping_intro','button_build_feed_file','button_run_feed_batch','button_run_feed_until_done','button_page_first','button_page_prev','button_page_next','button_page_last','button_apply_server_filters','button_retry_selected_queue','button_delete_selected_queue','button_delete_selected_reviews','button_delete_selected_new','button_new_skip_selected','button_new_restore_selected','text_bulk_actions','text_batch_processing_hint','text_limit_explain','text_compare_names_hint','text_list_url_product_warning','text_copy_url','text_copied',
            'entry_source_type','entry_feed_url','entry_feed_file','entry_feed_format','entry_feed_item_path','entry_feed_url_path','entry_feed_sku_path','entry_feed_name_path','entry_feed_price_path','entry_feed_stock_path','entry_feed_quantity_path','entry_feed_category_path','entry_feed_description_path','entry_feed_manufacturer_path','entry_feed_image_path','entry_feed_ean_path','entry_feed_upc_path','entry_feed_mpn_path','text_source_type_html','text_source_type_feed','text_feed_help','text_feed_mapping_help','text_feed_file_current','entry_module_status','entry_cron_token','entry_cron_endpoint','entry_cron_command','text_copy_cron','entry_batch_limit','entry_stop_queue','entry_supplier','entry_supplier_name','entry_supplier_status','entry_base_url','entry_list_urls','entry_product_url_xpath','entry_product_url_attr','entry_next_page_xpath','entry_sku_xpath','entry_model_xpath','entry_name_xpath','entry_price_xpath','entry_stock_xpath','entry_category_xpath','entry_description_xpath','entry_manufacturer_xpath','entry_image_xpath','entry_additional_images_xpath','entry_image_attr','entry_currency','entry_source_language','entry_target_language','entry_force_new_category','entry_discount','entry_markup','entry_rounding','entry_category','entry_stock_status','entry_create_new','entry_create_new_status','entry_auto_apply_existing','entry_auto_create_new','entry_update_price','entry_update_stock','entry_delay','entry_user_agent','entry_stock_map','entry_category_map','entry_test_url','entry_list_category_xpath','entry_max_price_change','entry_filter_status','entry_filter_type','entry_filter_change','entry_filter_text','entry_filter_supplier','entry_filter_stock','entry_product_search','entry_manual_product_id','entry_ean_xpath','entry_upc_xpath','entry_mpn_xpath','entry_attribute_row_xpath','entry_attribute_name_xpath','entry_attribute_value_xpath','entry_option_row_xpath','entry_option_name_xpath','entry_option_value_xpath','entry_meta_description_xpath','entry_meta_keyword_xpath','entry_price_formula_mode','entry_min_margin','entry_missing_policy','entry_existing_description_mode','entry_import_attributes','entry_import_options','entry_import_additional_images','entry_fill_all_languages','entry_require_test_success','entry_excluded_skus','entry_excluded_urls','entry_excluded_categories','entry_new_tax_class_id','entry_new_minimum','entry_new_subtract','entry_new_shipping','entry_new_sort_order','entry_new_store_id','entry_seo_url_mode','entry_min_new_price','entry_max_new_price','entry_review_retention','entry_log_retention','entry_history_retention',
            'column_supplier','column_status','column_url','column_product','column_supplier_product','column_local_product','column_sku','column_price','column_supplier_price','column_local_price','column_new_price','column_stock','column_supplier_stock','column_local_stock','column_quantity','column_checked','column_error','column_action','column_date','column_level','column_message','column_total','column_pending','column_processing','column_done','column_skipped','column_type','column_category','column_match','column_delta','column_old_price','column_new_price_history','column_old_quantity','column_new_quantity','column_field','column_note','column_changed','column_purchase_price','column_run','column_created','column_updated','column_checked',
            'text_ajax_invalid_json','text_ajax_invalid_response','error_test_url_required','error_diagnostics_failed','help_xpath','help_stock_map','help_category_map','help_existing_safe','help_new_disabled','help_cron','help_review','help_manual_match','help_new_seo_url','text_match_search_hint','text_match_selected','text_match_candidates','text_queue_selected_run_hint','help_history','help_price_warning','about_text','diagnostics_text','help_exclusions','help_missing_policy','help_full_update','help_price_guards','help_queue_pause'
        );
        foreach ($keys as $key) {
            $data[$key] = $this->language->get($key);
        }
        $data['status_labels'] = array(
            'pending' => $this->language->get('text_status_pending'),
            'processing' => $this->language->get('text_status_processing'),
            'done' => $this->language->get('text_status_done'),
            'price_warning' => $this->language->get('text_status_price_warning'),
            'duplicate' => $this->language->get('text_status_duplicate'),
            'excluded' => $this->language->get('text_status_excluded'),
            'applied' => $this->language->get('text_status_applied'),
            'created' => $this->language->get('text_status_created'),
            'skipped' => $this->language->get('text_status_skipped'),
            'error' => $this->language->get('text_status_error'),
            'linked' => $this->language->get('text_status_linked')
        );
        $data['type_labels'] = array(
            'existing' => $this->language->get('text_type_existing'),
            'new' => $this->language->get('text_type_new'),
            'excluded' => $this->language->get('text_status_excluded')
        );
        return $data;
    }

    private function saveSupplierFeedUpload($file) {
        $result = array('ok' => false, 'message' => 'Upload failed', 'relative_path' => '');
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $result['message'] = 'Upload file is missing';
            return $result;
        }
        if (!empty($file['error'])) {
            $result['message'] = 'Upload error: ' . (int)$file['error'];
            return $result;
        }
        $max_size = 64 * 1024 * 1024;
        $size = isset($file['size']) ? (int)$file['size'] : (int)@filesize($file['tmp_name']);
        if ($size <= 0 || $size > $max_size) {
            $result['message'] = 'Feed file must be between 1 byte and 64 MB';
            return $result;
        }
        $original = isset($file['name']) ? (string)$file['name'] : 'feed.xml';
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, array('xml','yml','csv','txt'), true)) {
            $result['message'] = 'Allowed feed extensions: xml, yml, csv, txt';
            return $result;
        }
        $dir = rtrim(DIR_STORAGE, '/\\') . DIRECTORY_SEPARATOR . 'supplier_sync_parser_pro';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            $result['message'] = 'Storage directory is not writable';
            return $result;
        }
        $safe = preg_replace('/[^a-z0-9_.-]+/i', '_', pathinfo($original, PATHINFO_FILENAME));
        if ($safe === '') {
            $safe = 'feed';
        }
        $filename = $safe . '_' . date('Ymd_His') . '.' . $ext;
        $target = $dir . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $target)) {
            $result['message'] = 'Cannot save uploaded feed file';
            return $result;
        }
        return array('ok' => true, 'message' => 'OK', 'relative_path' => 'supplier_sync_parser_pro/' . $filename);
    }

    private function formatSupplierNumericFieldsForForm($supplier) {
        if (!is_array($supplier)) {
            return $supplier;
        }

        foreach (array('discount_percent', 'markup_percent', 'min_margin_percent', 'max_price_change_percent') as $field) {
            if (isset($supplier[$field])) {
                $supplier[$field] = $this->formatDecimalInputValue($supplier[$field], 2, true);
            }
        }

        foreach (array('min_new_price', 'max_new_price') as $field) {
            if (isset($supplier[$field])) {
                $supplier[$field] = $this->formatDecimalInputValue($supplier[$field], 2, false);
            }
        }

        return $supplier;
    }

    private function formatDecimalInputValue($value, $decimals = 2, $trim = true) {
        if ($value === null || $value === '') {
            return '';
        }

        $value = str_replace(',', '.', (string)$value);
        $number = (float)$value;
        $formatted = number_format($number, (int)$decimals, '.', '');

        if ($trim) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
            if ($formatted === '-0') {
                $formatted = '0';
            }
            if ($formatted === '') {
                $formatted = '0';
            }
        }

        return $formatted;
    }

    private function normalizeSupplier($supplier) {
        $defaults = array(
            'supplier_id' => 0,
            'name' => '',
            'status' => 0,
            'base_url' => '',
            'source_type' => 'html',
            'list_urls' => '',
            'feed_url' => '',
            'feed_file' => '',
            'feed_format' => 'auto',
            'feed_item_path' => '',
            'feed_url_path' => '',
            'feed_sku_path' => '',
            'feed_name_path' => '',
            'feed_price_path' => '',
            'feed_stock_path' => '',
            'feed_quantity_path' => '',
            'feed_category_path' => '',
            'feed_description_path' => '',
            'feed_manufacturer_path' => '',
            'feed_image_path' => '',
            'feed_ean_path' => '',
            'feed_upc_path' => '',
            'feed_mpn_path' => '',
            'product_url_xpath' => '',
            'list_category_xpath' => '',
            'product_url_attr' => 'href',
            'next_page_xpath' => '',
            'sku_xpath' => '',
            'model_xpath' => '',
            'ean_xpath' => '',
            'upc_xpath' => '',
            'mpn_xpath' => '',
            'name_xpath' => '',
            'price_xpath' => '',
            'stock_xpath' => '',
            'category_xpath' => '',
            'description_xpath' => '',
            'meta_description_xpath' => '',
            'meta_keyword_xpath' => '',
            'manufacturer_xpath' => '',
            'image_xpath' => '',
            'additional_images_xpath' => '',
            'attribute_row_xpath' => '',
            'attribute_name_xpath' => '',
            'attribute_value_xpath' => '',
            'option_row_xpath' => '',
            'option_name_xpath' => '',
            'option_value_xpath' => '',
            'image_attr' => 'src',
            'currency_code' => '',
            'source_language_code' => '',
            'target_language_id' => 0,
            'price_formula_mode' => 'retail_discount_markup',
            'discount_percent' => '0.0000',
            'markup_percent' => '0.0000',
            'min_margin_percent' => '0.0000',
            'max_price_change_percent' => '50.0000',
            'rounding_mode' => 'two',
            'default_category_id' => 0,
            'force_new_category_id' => 0,
            'default_stock_status_id' => 0,
            'create_new' => 0,
            'create_new_status' => 0,
            'new_tax_class_id' => 0,
            'new_minimum' => 1,
            'new_subtract' => 1,
            'new_shipping' => 1,
            'new_sort_order' => 0,
            'new_store_id' => 0,
            'seo_url_mode' => 'all_languages',
            'min_new_price' => 0,
            'max_new_price' => 0,
            'auto_apply_existing' => 0,
            'auto_create_new' => 0,
            'update_price' => 1,
            'update_stock' => 1,
            'missing_policy' => 'report',
            'existing_description_mode' => 'keep',
            'import_attributes' => 0,
            'import_options' => 0,
            'fill_all_languages' => 0,
            'require_test_success' => 0,
            'last_test_ok' => 0,
            'last_test_date' => '',
            'request_delay_ms' => 500,
            'user_agent' => '',
            'domain_policy' => 'strict_host',
            'allowed_hosts_text' => '',
            'settings' => ''
        );
        $normalized = array_merge($defaults, is_array($supplier) ? $supplier : array());
        if ((int)$normalized['target_language_id'] <= 0) {
            $normalized['target_language_id'] = (int)$this->config->get('config_language_id');
        }
        $currency_aliases = array('UAN' => 'UAH', 'GRN' => 'UAH', 'ГРН' => 'UAH', 'ГРН.' => 'UAH');
        $normalized['currency_code'] = strtoupper(trim((string)$normalized['currency_code']));
        if (isset($currency_aliases[$normalized['currency_code']])) {
            $normalized['currency_code'] = $currency_aliases[$normalized['currency_code']];
        }
        if ((string)$normalized['currency_code'] === '') {
            $normalized['currency_code'] = (string)$this->config->get('config_currency');
        }
        $settings = json_decode((string)$normalized['settings'], true);
        $normalized['in_stock_quantity'] = isset($settings['in_stock_quantity']) ? (int)$settings['in_stock_quantity'] : 100;
        $normalized['source_adapter'] = isset($settings['source_adapter']) && $settings['source_adapter'] === 'prom' ? 'prom' : '';
        $normalized['new_category_name'] = isset($settings['new_category_name']) ? $settings['new_category_name'] : '';
        $normalized['unknown_stock_policy'] = isset($settings['unknown_stock_policy']) && $settings['unknown_stock_policy'] === 'zero' ? 'zero' : 'keep';
        $normalized['update_stock_status'] = isset($settings['update_stock_status']) ? (int)$settings['update_stock_status'] : 1;
        $normalized['match_source'] = isset($settings['match_source']) ? $settings['match_source'] : 'auto';
        $normalized['match_target'] = isset($settings['match_target']) ? $settings['match_target'] : 'sku';
        $normalized['jan_xpath'] = isset($settings['jan_xpath']) ? $settings['jan_xpath'] : '';
        $normalized['isbn_xpath'] = isset($settings['isbn_xpath']) ? $settings['isbn_xpath'] : '';
        $normalized['cron_enabled'] = isset($settings['cron_enabled']) ? $settings['cron_enabled'] : 0;
        $normalized['cron_interval_minutes'] = isset($settings['cron_interval_minutes']) ? $settings['cron_interval_minutes'] : 60;
        if (is_array($settings)) {
            $policy = isset($settings['domain_policy']) ? (string)$settings['domain_policy'] : 'strict_host';
            $normalized['domain_policy'] = in_array($policy, array('strict_host', 'allow_subdomains', 'allow_extra_hosts'), true) ? $policy : 'strict_host';
            if (!empty($settings['allowed_hosts']) && is_array($settings['allowed_hosts'])) {
                $normalized['allowed_hosts_text'] = implode("\n", $settings['allowed_hosts']);
            }
        }
        return $normalized;
    }


    private function getFirstSupplierUrl($supplier) {
        if (!is_array($supplier) || empty($supplier['list_urls'])) {
            return '';
        }
        $lines = preg_split('/\r\n|\r|\n/', (string)$supplier['list_urls']);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('#^https?://#i', $line)) {
                return $line;
            }
        }
        return '';
    }

    private function getFirstSupplierProductUrl($supplier) {
        if (!is_array($supplier) || empty($supplier['list_urls'])) {
            return '';
        }
        $lines = preg_split('/\r\n|\r|\n/', (string)$supplier['list_urls']);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('#^https?://#i', $line) && $this->controllerLooksLikeProductUrl($line)) {
                return $line;
            }
        }
        return '';
    }

    private function getFirstSupplierListUrl($supplier) {
        if (!is_array($supplier) || empty($supplier['list_urls'])) {
            return '';
        }
        $lines = preg_split('/\r\n|\r|\n/', (string)$supplier['list_urls']);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || !preg_match('#^https?://#i', $line)) {
                continue;
            }
            if ($this->controllerLooksLikeProductUrl($line)) {
                continue;
            }
            return $line;
        }
        return '';
    }

    private function controllerLooksLikeProductUrl($url) {
        $path = (string)parse_url((string)$url, PHP_URL_PATH);
        $query = (string)parse_url((string)$url, PHP_URL_QUERY);
        $full = strtolower($path . '?' . $query);
        if (strpos($full, 'product_id=') !== false || strpos($full, '/product/') !== false) {
            return true;
        }
        if (preg_match('#/(cart|checkout|account|compare|wishlist|login|register|contact|blog|news|stati|information|manufacturer|delivery|oplata|about|testimonials)(/|$)#', $full)) {
            return false;
        }
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!$segments) {
            return false;
        }
        $last = end($segments);
        if (preg_match('/^p[0-9]{4,}[-_a-z0-9]*\.html$/i', $last) || preg_match('/[a-zа-яієїґ]{2,}[0-9]{2,}|[0-9]{2,}[a-zа-яієїґ]{2,}/iu', $last)) {
            return true;
        }
        if (count($segments) >= 2 && preg_match('/[a-zа-яієїґ]+[-_][a-zа-яієїґ0-9]+[-_][a-zа-яієїґ0-9]+/iu', $last)) {
            return true;
        }
        return false;
    }

    private function stockMapToText($settings) {
        $settings = json_decode((string)$settings, true);
        if (!is_array($settings) || empty($settings['stock_map'])) {
            return "в наличии|99\nв наявності|99\nin stock|99\nout of stock|0\nнет в наличии|0";
        }
        $lines = array();
        foreach ($settings['stock_map'] as $needle => $map) {
            $line = $needle . '|' . (isset($map['quantity']) ? (int)$map['quantity'] : 0);
            if (isset($map['stock_status_id'])) {
                $line .= '|' . (int)$map['stock_status_id'];
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    private function categoryMapToText($settings) {
        $settings = json_decode((string)$settings, true);
        if (!is_array($settings) || empty($settings['category_map'])) {
            return "";
        }
        $lines = array();
        foreach ($settings['category_map'] as $row) {
            $lines[] = (isset($row['needle']) ? $row['needle'] : '') . '|' . (isset($row['category_id']) ? (int)$row['category_id'] : 0) . '|' . (!empty($row['allow']) ? 1 : 0);
        }
        return implode("\n", $lines);
    }


    private function settingsListToText($settings, $key) {
        $settings = json_decode((string)$settings, true);
        if (!is_array($settings) || empty($settings[$key]) || !is_array($settings[$key])) {
            return '';
        }
        return implode("\n", $settings[$key]);
    }

    private function settingsValue($settings, $key, $default = '') {
        $settings = json_decode((string)$settings, true);
        if (!is_array($settings) || !array_key_exists($key, $settings) || !is_scalar($settings[$key])) {
            return $default;
        }
        return (string)$settings[$key];
    }

    private function buildReviewStats($rows) {
        $stats = array(
            'total' => 0,
            'price_changed' => 0,
            'stock_changed' => 0,
            'price_stock_changed' => 0,
            'new_rows' => 0,
            'warnings' => 0,
            'duplicates' => 0,
            'excluded' => 0,
            'same' => 0
        );
        if (!is_array($rows)) {
            return $stats;
        }
        foreach ($rows as $row) {
            $stats['total']++;
            $price_changed = abs((float)(isset($row['price_delta_abs']) ? $row['price_delta_abs'] : 0)) > 0.0001;
            $stock_changed = ((int)(isset($row['supplier_quantity']) ? $row['supplier_quantity'] : 0) !== (int)(isset($row['local_quantity']) ? $row['local_quantity'] : 0)) || ((int)(isset($row['supplier_stock_status_id']) ? $row['supplier_stock_status_id'] : 0) !== (int)(isset($row['local_stock_status_id']) ? $row['local_stock_status_id'] : 0));
            if ($price_changed) {
                $stats['price_changed']++;
            }
            if ($stock_changed) {
                $stats['stock_changed']++;
            }
            if ($price_changed && $stock_changed) {
                $stats['price_stock_changed']++;
            }
            if (!$price_changed && !$stock_changed) {
                $stats['same']++;
            }
            if (isset($row['review_type']) && $row['review_type'] === 'new') {
                $stats['new_rows']++;
            }
            if (isset($row['status']) && $row['status'] === 'price_warning') {
                $stats['warnings']++;
            }
            if (isset($row['status']) && $row['status'] === 'duplicate') {
                $stats['duplicates']++;
            }
            if (isset($row['status']) && $row['status'] === 'excluded') {
                $stats['excluded']++;
            }
        }
        return $stats;
    }

    private function buildNewStats($rows) {
        $stats = array('total' => 0, 'pending' => 0, 'duplicate' => 0, 'excluded' => 0, 'created' => 0, 'error' => 0);
        if (!is_array($rows)) {
            return $stats;
        }
        foreach ($rows as $row) {
            $stats['total']++;
            $status = isset($row['status']) ? $row['status'] : 'pending';
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }
        return $stats;
    }


    private function collectListFilters($prefix, $supplier_id, $default_limit) {
        $filters = array();
        $filters['supplier_id'] = $supplier_id;
        $filters['page'] = isset($this->request->get[$prefix . '_page']) ? (int)$this->request->get[$prefix . '_page'] : 1;
        $filters['limit'] = isset($this->request->get[$prefix . '_limit']) ? (int)$this->request->get[$prefix . '_limit'] : $default_limit;
        $allowed_limits = array(50, 100, 200, 500);
        if (!in_array((int)$filters['limit'], $allowed_limits, true)) {
            $filters['limit'] = in_array((int)$default_limit, $allowed_limits, true) ? (int)$default_limit : 100;
        }
        if ((int)$filters['page'] < 1) {
            $filters['page'] = 1;
        }
        foreach (array('status','type','change','text','level','action','sort','order') as $key) {
            $get_key = $prefix . '_' . $key;
            $filters[$key] = isset($this->request->get[$get_key]) ? trim((string)$this->request->get[$get_key]) : '';
        }
        return $filters;
    }

    private function resolveSupplierTestUrl($supplier_id, $url) {
        $url = trim((string)$url);
        if ($url !== '' && preg_match('#^https?://#i', $url) && stripos($url, 'supplier.com') === false) {
            return $url;
        }
        $this->load->model($this->route);
        $supplier = $supplier_id ? $this->model_extension_module_supplier_sync_parser_pro->getSupplier($supplier_id) : array();
        if (empty($supplier['list_urls'])) {
            return '';
        }
        $lines = preg_split('/\r\n|\r|\n/', (string)$supplier['list_urls']);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('#^https?://#i', $line)) {
                return $line;
            }
        }
        return '';
    }

    private function getStatusLabel($status) {
        $map = array(
            'pending' => 'text_status_pending',
            'processing' => 'text_status_processing',
            'done' => 'text_status_done',
            'price_warning' => 'text_status_price_warning',
            'duplicate' => 'text_status_duplicate',
            'excluded' => 'text_status_excluded',
            'applied' => 'text_status_applied',
            'created' => 'text_status_created',
            'skipped' => 'text_status_skipped',
            'error' => 'text_status_error',
            'linked' => 'text_status_linked'
        );
        if (isset($map[$status])) {
            $label = $this->language->get($map[$status]);
            return ($label && $label !== $map[$status]) ? $label : $status;
        }
        return $status;
    }

    private function localizeRows($rows, $fields) {
        if (!is_array($rows)) {
            return array();
        }
        foreach ($rows as &$row) {
            foreach ($fields as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $row[$field] = $this->localizeEngineMessage($row[$field]);
                }
            }
        }
        unset($row);
        return $rows;
    }

    private function localizeEngineResult($result) {
        if (!is_array($result)) {
            return array('ok' => false, 'message' => $this->language->get('text_ajax_invalid_response'));
        }
        if (isset($result['message'])) {
            $result['message'] = $this->localizeEngineMessage($result['message']);
        }
        if (!empty($result['row_errors']) && is_array($result['row_errors'])) {
            foreach ($result['row_errors'] as &$row_error) {
                if (is_array($row_error) && isset($row_error['message'])) {
                    $row_error['message'] = $this->localizeEngineMessage($row_error['message']);
                }
            }
            unset($row_error);
        }
        if (!empty($result['details']) && is_array($result['details'])) {
            foreach ($result['details'] as &$detail) {
                if (is_string($detail)) {
                    $detail = $this->localizeEngineMessage($detail);
                }
            }
            unset($detail);
        }
        if (!empty($result['fields']) && is_array($result['fields'])) {
            foreach ($result['fields'] as $field => &$item) {
                $key = 'field_label_' . preg_replace('/[^a-z0-9_]/i', '', (string)$field);
                $label = $this->language->get($key);
                if ($label && $label !== $key) {
                    $item['label'] = $label;
                }
                if (!empty($item['candidates']) && is_array($item['candidates'])) {
                    foreach ($item['candidates'] as &$candidate) {
                        if (isset($candidate['source'])) {
                            $candidate['source'] = $this->localizeAutoSource($candidate['source']);
                        }
                    }
                    unset($candidate);
                }
            }
            unset($item);
        }
        return $result;
    }

    private function localizeEngineMessage($message) {
        $message = trim((string)$message);
        $map = array(
            'OK' => 'engine_ok',
            'Done' => 'engine_done',
            'Invalid URL' => 'engine_invalid_url',
            'Invalid product URL' => 'engine_invalid_product_url',
            'Supplier not found' => 'engine_supplier_not_found',
            'Module is disabled' => 'engine_module_disabled',
            'Queue is stopped manually' => 'engine_queue_stopped',
            'Queue was stopped manually' => 'engine_queue_stopped',
            'Linked product check queue created' => 'engine_linked_queue_created',
            'Product URL queue created' => 'engine_product_url_queue_created',
            'List scan queue created' => 'engine_list_scan_queue_created',
            'Site scan queue created' => 'engine_site_scan_queue_created',
            'Sitemap product queue created' => 'engine_sitemap_queue_created',
            'Mass import is blocked until supplier test succeeds' => 'engine_test_required',
            'Required XPath is missing: product name' => 'engine_missing_name_xpath',
            'Required XPath is missing: product price' => 'engine_missing_price_xpath',
            'Required XPath is missing: product URL on list page' => 'engine_missing_product_url_xpath',
            'Supplier list URLs are empty' => 'engine_supplier_urls_empty',
            'Missing product policy requires default stock status' => 'engine_missing_policy_stock_status_required',
            'Cannot parse HTML' => 'engine_cannot_parse_html',
            'Product name was not found' => 'engine_product_name_not_found',
            'Product price was not found' => 'engine_product_price_not_found',
            'Calculated sale price is invalid' => 'engine_calculated_price_invalid',
            'Product parse failed' => 'engine_product_parse_failed',
            'List scan failed' => 'engine_list_scan_failed',
            'Sitemap scan found no product URLs' => 'engine_sitemap_no_products',
            'Check sitemap availability or use category scan.' => 'engine_sitemap_check_hint',
            'Auto-detection completed. Review candidates before saving rules.' => 'engine_auto_detection_completed',
            'List link auto-detection completed. Review product links before saving rules.' => 'engine_list_auto_detection_completed',
            'This URL looks like a product card. Paste a category or product list URL for list link detection.' => 'engine_category_url_is_product',
            'This URL looks like a category/list page. Paste a real product card URL for product rule detection; use list rule detection for category pages.' => 'engine_product_url_is_list',
            'No selected rows' => 'engine_no_selected_rows',
            'Unknown apply mode' => 'engine_unknown_apply_mode',
            'Row is excluded by supplier rules' => 'engine_row_excluded_by_rules',
            'Skipped manually' => 'engine_skipped_manually',
            'Skipped and excluded from preview' => 'engine_skipped_and_excluded_from_preview',
            'Skipped and excluded manually' => 'engine_skipped_and_excluded_manually',
            'New product creation is disabled for this supplier' => 'engine_new_creation_disabled',
            'Supplier category is not allowed by rules' => 'engine_supplier_category_not_allowed',
            'Product was not created' => 'engine_product_was_not_created',
            'No linked OpenCart product' => 'engine_no_linked_product',
            'Price warning: use forced price update only after manual check' => 'engine_price_warning_force_required',
            'Preview saved for existing product' => 'engine_preview_saved_existing',
            'Preview saved for new product' => 'engine_preview_saved_new',
            'Waiting for review' => 'engine_waiting_for_review',
            'Unknown action' => 'engine_unknown_action',
            'Existing linked product skipped in new-only scan' => 'engine_existing_linked_skipped_new_only',
            'Missing supplier or parsed data' => 'engine_missing_supplier_or_data',
            'Product was created from supplier preview' => 'engine_created_from_supplier_preview',
            'Existing product updated' => 'engine_updated_existing_product',
            'No changes' => 'engine_no_changes',
            'Calculated price change is higher than allowed supplier limit' => 'engine_price_change_guard',
            'Excluded by SKU rule' => 'engine_excluded_by_sku_rule',
            'Excluded by URL rule' => 'engine_excluded_by_url_rule',
            'Excluded by category rule' => 'engine_excluded_by_category_rule',
            'Manual exclusion' => 'engine_manual_exclusion',
            'Product linked manually' => 'engine_product_linked_manually',
            'Potential matches found; choose manually before creating a new product' => 'engine_match_candidates_manual',
            'OpenCart product was not found' => 'engine_opencart_product_not_found',
            'Review ID and product ID are required' => 'engine_review_product_required',
            'Review row not found' => 'engine_review_row_not_found',
            'Supplier disabled or not found' => 'engine_supplier_disabled_or_not_found',
            'URL is not a supplier product page' => 'engine_url_not_supplier_product',
            'Feed item queue created' => 'engine_feed_queue_created',
            'Feed preview saved for existing product' => 'engine_feed_preview_saved_existing',
            'Feed preview saved for new product' => 'engine_feed_preview_saved_new',
            'Feed URL or uploaded feed file is required' => 'engine_feed_source_required',
            'Supplier source type must be File/XML/CSV for this mode' => 'engine_feed_source_type_required',
            'CSV feed has no data rows' => 'engine_csv_no_rows',
            'Uploaded feed file is empty or unreadable' => 'engine_feed_file_empty',
            'PHP SimpleXML extension is required for XML/YML feeds' => 'engine_simplexml_required',
            'Feed item has no valid name or calculated price' => 'engine_feed_item_invalid',
            'Feed mapping auto-detection completed. Review standard OpenCart fields before saving.' => 'engine_feed_mapping_detected',
            'No product nodes found in XML/YML feed' => 'engine_feed_no_product_nodes'
        );
        if (isset($map[$message])) {
            $translated = $this->language->get($map[$message]);
            return ($translated && $translated !== $map[$message]) ? $translated : $message;
        }
        $prefix_map = array(
            'Fetch failed:' => 'engine_fetch_failed',
            'HTTP error:' => 'engine_http_error',
            'Product price is invalid:' => 'engine_product_price_invalid',
            'Supplier currency is not active in OpenCart:' => 'engine_supplier_currency_inactive',
            'Calculated sale price is lower than supplier minimum price guard' => 'engine_price_lower_guard',
            'Calculated sale price is higher than supplier maximum price guard' => 'engine_price_higher_guard',
            'URL is outside supplier domain:' => 'engine_url_outside_domain',
            'Potential duplicate found by' => 'engine_duplicate_found_by',
            'This row is already linked to product ID' => 'engine_already_linked_to_product',
            'Queue is already processing by' => 'engine_queue_locked',
            'URL skipped by domain policy' => 'engine_url_skipped_domain_policy',
            'URL skipped by domain or language policy' => 'engine_url_skipped_domain_language_policy',
            'Added product jobs:' => 'engine_added_product_jobs',
            'Feed parsed:' => 'engine_feed_parsed',
            'Cannot parse XML/YML feed' => 'engine_cannot_parse_feed_xml'
        );
        foreach ($prefix_map as $prefix => $key) {
            if (strpos($message, $prefix) === 0) {
                $translated = $this->language->get($key);
                $tail = trim(substr($message, strlen($prefix)));
                return ($translated && $translated !== $key) ? trim($translated . ($tail !== '' ? ' ' . $tail : '')) : $message;
            }
        }
        return $message;
    }

    private function localizeAutoSource($source) {
        $source = trim((string)$source);
        $key = 'auto_source_' . preg_replace('/[^a-z0-9]+/i', '_', strtolower($source));
        $translated = $this->language->get($key);
        return ($translated && $translated !== $key) ? $translated : $source;
    }

    protected function validate() {
        if (!$this->hasValidUserToken() || !$this->user->hasPermission('modify', $this->route)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        if (isset($this->request->post['save_supplier']) && empty($this->request->post['supplier_id']) && empty(trim($this->request->post['name'] ?? ''))) {
            $this->error['warning'] = $this->language->get('error_supplier_name');
        }
        return !$this->error;
    }

    private function hasValidUserToken() {
        $session_token = isset($this->session->data['user_token']) ? (string)$this->session->data['user_token'] : '';
        $request_token = isset($this->request->get['user_token']) ? (string)$this->request->get['user_token'] : '';

        if ($request_token === '' && isset($this->request->post['user_token'])) {
            $request_token = (string)$this->request->post['user_token'];
        }

        return $session_token !== '' && $request_token !== '' && hash_equals($session_token, $request_token);
    }

    protected function validateOperational() {
        if (!$this->validate()) {
            return false;
        }
        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            $this->error['warning'] = $this->language->get('error_module_disabled');
            return false;
        }
        return true;
    }

    private function json($data) {
        if (ob_get_level() && ob_get_length()) {
            @ob_clean();
        }
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = json_encode(array('ok' => false, 'message' => $this->language->get('text_ajax_invalid_response')));
        }
        $this->response->setOutput($encoded);
    }
}
