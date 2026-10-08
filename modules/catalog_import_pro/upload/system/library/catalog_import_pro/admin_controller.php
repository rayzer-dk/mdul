<?php
abstract class ControllerExtensionModuleScrapesmartCatalogImportBase extends Controller {
    protected $entity = '';
    protected $route = 'extension/module/scrapesmart_catalog_import';
    protected $modelRoute = 'extension/module/scrapesmart_catalog_import';
    protected $modelProperty = 'model_extension_module_scrapesmart_catalog_import';
    protected $error = array();

    public function entity() {
        $this->entity = $this->resolveEntity();
        $this->load->language('extension/module/scrapesmart_catalog_import');
        if ((string)$this->config->get('module_scrapesmart_catalog_import_version') !== '2.3.1') {
            $this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true));
            return;
        }
        $this->document->setTitle($this->language->get('heading_' . $this->entity));
        $this->load->model($this->modelRoute);
        $this->load->model('localisation/language');
        $this->load->model('setting/store');

        $data = $this->languageData();
        $data['heading_title'] = $this->language->get('heading_' . $this->entity);
        $data['entity_type'] = $this->entity;
        $data['enabled'] = (bool)$this->config->get('module_scrapesmart_catalog_import_status');
        $data['version'] = '2.3.1';
        $data['user_token'] = $this->session->data['user_token'];
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)),
            array('text' => $data['heading_title'], 'href' => $this->url->link($this->route . '/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true))
        );
        $data['back'] = $this->url->link('extension/module/scrapesmart_catalog_import', 'user_token=' . $this->session->data['user_token'], true);
        $data['ajax_upload'] = $this->url->link($this->route . '/ajaxUpload', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_prepare'] = $this->url->link($this->route . '/ajaxPrepare', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_process'] = $this->url->link($this->route . '/ajaxProcess', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_results'] = $this->url->link($this->route . '/ajaxResults', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_summary'] = $this->url->link($this->route . '/ajaxSummary', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_recover'] = $this->url->link($this->route . '/ajaxRecover', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_cancel'] = $this->url->link($this->route . '/ajaxCancel', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_selection'] = $this->url->link($this->route . '/ajaxSelection', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_apply_review'] = $this->url->link($this->route . '/ajaxApplyReview', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['ajax_retry_errors'] = $this->url->link($this->route . '/ajaxRetryErrors', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['report_url'] = $this->url->link($this->route . '/report', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['export_url'] = $this->url->link($this->route . '/export', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true);
        $data['languages'] = $this->model_localisation_language->getLanguages();
        $data['stores'] = array(array('store_id' => 0, 'name' => $this->config->get('config_name')));
        foreach ($this->model_setting_store->getStores() as $store) {
            $data['stores'][] = array('store_id' => (int)$store['store_id'], 'name' => $store['name']);
        }
        $data['default_language_id'] = (int)$this->config->get('config_language_id');
        $data['recent_batches'] = $data['enabled'] ? $this->model()->getRecentBatches($this->entity, 20) : array();
        $data['entity_options'] = $this->getEntityOptions();
        $data['field_rule_fields'] = $this->getFieldRuleFields();
        $data['sample_headers'] = $this->getSampleHeaders();
        $configuredMaxFile = (int)$this->config->get('module_scrapesmart_catalog_import_max_file_size_mb');
        $data['max_file_size_mb'] = $configuredMaxFile > 0 ? max(1, min(500, $configuredMaxFile)) : 50;
        $data['file_limit_text'] = sprintf($this->language->get('text_file_limit'), $data['max_file_size_mb']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/scrapesmart_catalog_import_entity', $data));
    }

    public function ajaxUpload() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            if (!isset($this->request->files['csv_file'])) {
                $this->json(array('error' => $this->language->get('error_file_missing')));
                return;
            }
            $file = $this->request->files['csv_file'];
            if ((int)$file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
                $this->json(array('error' => $this->language->get('error_upload')));
                return;
            }
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, array('csv', 'txt'), true)) {
                $this->json(array('error' => $this->language->get('error_file_type')));
                return;
            }
            $maxMb = (int)$this->config->get('module_scrapesmart_catalog_import_max_file_size_mb');
            $maxMb = $maxMb > 0 ? max(1, min(500, $maxMb)) : 50;
            if ((int)$file['size'] <= 0 || (int)$file['size'] > $maxMb * 1024 * 1024) {
                $this->json(array('error' => sprintf($this->language->get('error_file_size'), $maxMb)));
                return;
            }
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = (string)$finfo->file($file['tmp_name']);
                $allowed = array('text/plain','text/csv','application/csv','application/vnd.ms-excel','application/octet-stream');
                if ($mime !== '' && !in_array($mime, $allowed, true)) {
                    $this->json(array('error' => $this->language->get('error_file_type')));
                    return;
                }
            }
            $result = $this->model()->createBatchFromCsv($this->entity, $file['tmp_name'], $file['name'], $this->getSanitizedOptions());
            $this->json($result);
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO upload: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxPrepare() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            $batchId = $this->getBatchId();
            $limit = isset($this->request->post['prepare_limit']) ? (int)$this->request->post['prepare_limit'] : 250;
            $this->json($this->model()->prepareQueueBatch($this->entity, $batchId, $limit));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO prepare: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxProcess() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            $batchId = $this->getBatchId();
            $limit = isset($this->request->post['batch_size']) ? (int)$this->request->post['batch_size'] : 10;
            $this->json($this->model()->processQueueBatch($this->entity, $batchId, $limit));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO process: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxSummary() {
        if (!$this->validateAjax(false, true)) { return; }
        try {
            $summary = $this->getValidatedBatchSummary(300);
            $this->json($summary !== null ? $summary : array('error' => $this->language->get('error_batch')));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO summary: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxResults() {
        if (!$this->validateAjax(false, true)) { return; }
        try {
            $summary = $this->getValidatedBatchSummary(1);
            if ($summary === null) { $this->json(array('error' => $this->language->get('error_batch'))); return; }
            $status = isset($this->request->post['status']) ? trim((string)$this->request->post['status']) : '';
            $search = isset($this->request->post['search']) ? trim((string)$this->request->post['search']) : '';
            $page = isset($this->request->post['page']) ? (int)$this->request->post['page'] : 1;
            $limit = isset($this->request->post['limit']) ? (int)$this->request->post['limit'] : 50;
            $this->json($this->model()->getBatchResultPage($this->entity, $this->getBatchId(), $status, $search, $page, $limit));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO results: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxRecover() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            $summary = $this->getValidatedBatchSummary(300);
            if ($summary === null) {
                $this->json(array('error' => $this->language->get('error_batch')));
                return;
            }
            $batchId = $this->getBatchId();
            $count = $this->model()->recoverStuckProcessing($batchId, 120);
            $this->json(array('success' => sprintf($this->language->get('text_recovered'), $count), 'summary' => $this->model()->getBatchSummary($batchId, 300)));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO recover: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxCancel() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            $ok = $this->model()->cancelBatch($this->entity, $this->getBatchId());
            $this->json($ok ? array('success' => $this->language->get('text_cancelled')) : array('error' => $this->language->get('error_batch')));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO cancel: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxSelection() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            $summary = $this->getValidatedBatchSummary(10);
            if ($summary === null) { $this->json(array('error' => $this->language->get('error_batch'))); return; }
            $selected = !empty($this->request->post['selected']);
            $all = !empty($this->request->post['all']);
            $queueIds = isset($this->request->post['queue_ids']) ? (array)$this->request->post['queue_ids'] : array();
            $count = $this->model()->setReviewSelection($this->entity, $this->getBatchId(), $queueIds, $selected, $all);
            $this->json(array('success' => sprintf($this->language->get('text_selection_updated'), $count), 'summary' => $this->model()->getBatchSummary($this->getBatchId(), 300)));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO selection: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxApplyReview() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            $this->json($this->model()->applySelectedReview($this->entity, $this->getBatchId()));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO apply review: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function ajaxRetryErrors() {
        if (!$this->validateAjax(true, true)) { return; }
        try {
            $summary = $this->getValidatedBatchSummary(10);
            if ($summary === null) { $this->json(array('error' => $this->language->get('error_batch'))); return; }
            $count = $this->model()->retryErrors($this->entity, $this->getBatchId());
            $this->json(array('success' => sprintf($this->language->get('text_errors_requeued'), $count), 'summary' => $this->model()->getBatchSummary($this->getBatchId(), 300)));
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO retry errors: ' . $e->getMessage());
            $this->json(array('error' => $this->language->get('error_internal')));
        }
    }

    public function report() {
        $this->entity = $this->resolveEntity();
        $this->load->language('extension/module/scrapesmart_catalog_import');
        if (!$this->hasValidUserToken() || !$this->user->hasPermission('access', $this->route) || !$this->config->get('module_scrapesmart_catalog_import_status')) {
            $this->response->redirect($this->url->link($this->route . '/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true));
            return;
        }
        $this->load->model($this->modelRoute);
        $batchId = $this->getBatchId();
        $summary = $batchId !== '' ? $this->model()->getBatchSummary($batchId, 1) : array();
        if (empty($summary['batch']) || $summary['batch']['entity_type'] !== $this->entity) {
            $this->response->redirect($this->url->link($this->route . '/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true));
            return;
        }
        $delimiterValue = isset($this->request->get['delimiter']) ? $this->request->get['delimiter'] : ';';
        $delimiter = $delimiterValue === 'tab' ? "\t" : (in_array($delimiterValue, array(';', ',', '|'), true) ? $delimiterValue : ';');
        $filename = 'catalog_import_report_' . $this->entity . '_' . date('Y-m-d_H-i-s') . '.csv';
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        $this->model()->streamBatchReport($this->entity, $batchId, $output, $delimiter);
        fclose($output);
        exit;
    }

    public function export() {
        $this->entity = $this->resolveEntity();
        $this->load->language('extension/module/scrapesmart_catalog_import');
        if (!$this->hasValidUserToken()) {
            $this->response->redirect($this->url->link('error/permission', '', true));
            return;
        }
        if (!$this->user->hasPermission('access', $this->route)) {
            $this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }
        if ((string)$this->config->get('module_scrapesmart_catalog_import_version') !== '2.3.1') {
            $this->session->data['error_warning'] = $this->language->get('error_migration_required');
            $this->response->redirect($this->url->link($this->route, 'user_token=' . $this->session->data['user_token'], true));
            return;
        }
        if (!$this->config->get('module_scrapesmart_catalog_import_status')) {
            $this->session->data['error_warning'] = $this->language->get('error_disabled');
            $this->response->redirect($this->url->link($this->route . '/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=' . $this->entity, true));
            return;
        }
        $this->load->model($this->modelRoute);
        $delimiterValue = isset($this->request->get['delimiter']) ? $this->request->get['delimiter'] : ';';
        $delimiter = $delimiterValue === 'tab' ? "\t" : (in_array($delimiterValue, array(';', ',', '|'), true) ? $delimiterValue : ';');
        $filename = 'catalog_' . $this->entity . '_' . date('Y-m-d_H-i-s') . '.csv';
        while (ob_get_level()) { ob_end_clean(); }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('X-Content-Type-Options: nosniff');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        try {
            $this->model()->streamExport($this->entity, $this->getSanitizedExportOptions(), $output, $delimiter);
        } catch (Throwable $e) {
            $this->log->write('Catalog Import PRO export: ' . $e->getMessage());
            fputcsv($output, array('__ERROR__', $this->language->get('error_export_failed')), $delimiter, '"', '\\');
        }
        fclose($output);
        exit;
    }

    protected function validateAjax($modify, $requireEnabled) {
        $this->entity = $this->resolveEntity();
        $this->load->language('extension/module/scrapesmart_catalog_import');
        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST') {
            $this->json(array('error' => $this->language->get('error_request_method')));
            return false;
        }
        if (!$this->hasValidUserToken()) {
            $this->json(array('error' => $this->language->get('error_permission')));
            return false;
        }
        if (!$this->user->hasPermission($modify ? 'modify' : 'access', $this->route)) {
            $this->json(array('error' => $this->language->get('error_permission')));
            return false;
        }
        if ((string)$this->config->get('module_scrapesmart_catalog_import_version') !== '2.3.1') {
            $this->json(array('error' => $this->language->get('error_migration_required')));
            return false;
        }
        if ($requireEnabled && !$this->config->get('module_scrapesmart_catalog_import_status')) {
            $this->json(array('error' => $this->language->get('error_disabled')));
            return false;
        }
        $this->load->model($this->modelRoute);
        return true;
    }

    protected function getSanitizedOptions() {
        $post = $this->request->post;
        $allowed = array(
            'mode','import_profile','key_field','delimiter','encoding','language_id','store_ids','batch_size','dry_run','empty_field','import_id','column_map_text','field_rules_text','supplier_code','sync_missing_action','sync_missing_confirm',
            'copy_default_language','replace_store_links','replace_seo_url','download_images','image_directory','create_categories',
            'create_manufacturers','replace_attributes','delete_category_children','create_global_options','create_option_values','update_option_definitions',
            'minimum','subtract','stock_status_id','shipping','weight_class_id','length_class_id','status','sort_order'
        );
        $options = array();
        foreach ($allowed as $key) {
            if (isset($post[$key])) { $options[$key] = is_array($post[$key]) ? $post[$key] : trim((string)$post[$key]); }
        }
        $profiles = array('custom','price_only','stock_only','price_stock','new_only','update_only','full_import');
        $options['import_profile'] = isset($options['import_profile']) && in_array($options['import_profile'], $profiles, true) ? $options['import_profile'] : 'custom';
        $options['mode'] = isset($options['mode']) && in_array($options['mode'], array('add','update','upsert','delete'), true) ? $options['mode'] : 'upsert';
        $options['key_field'] = isset($options['key_field']) && in_array($options['key_field'], $this->getKeyFields(), true) ? $options['key_field'] : $this->getKeyFields()[0];
        $options['key_strategy'] = 'strict';
        if (in_array($options['import_profile'], array('price_only','stock_only','price_stock','update_only'), true)) { $options['mode'] = 'update'; }
        if ($options['import_profile'] === 'new_only') { $options['mode'] = 'add'; }
        if ($options['import_profile'] === 'full_import') { $options['mode'] = 'upsert'; }
        $options['delimiter'] = isset($options['delimiter']) && in_array($options['delimiter'], array('auto',';',',','tab','|'), true) ? $options['delimiter'] : 'auto';
        $options['encoding'] = isset($options['encoding']) && in_array(strtoupper($options['encoding']), array('UTF-8','WINDOWS-1251','CP1251'), true) ? strtoupper($options['encoding']) : 'UTF-8';
        $options['language_id'] = isset($options['language_id']) ? max(1, (int)$options['language_id']) : (int)$this->config->get('config_language_id');
        $options['batch_size'] = isset($options['batch_size']) ? max(1, min(50, (int)$options['batch_size'])) : 10;
        $options['column_map'] = $this->parseColumnMap(isset($post['column_map_text']) ? $post['column_map_text'] : '');
        $options['field_rules'] = $this->parseFieldRules(isset($post['field_rules_text']) ? $post['field_rules_text'] : '');
        $options['field_rules'] = array_merge($options['field_rules'], $this->parseFieldPolicyArray(isset($post['field_policy']) ? $post['field_policy'] : array()));
        unset($options['column_map_text'], $options['field_rules_text']);
        $supplierCode = isset($post['supplier_code']) ? strtolower(trim((string)$post['supplier_code'])) : '';
        $options['supplier_code'] = substr($supplierCode, 0, 128);
        $missingActions = array('none','disable','zero','delete');
        $options['sync_missing_action'] = isset($post['sync_missing_action']) && in_array((string)$post['sync_missing_action'], $missingActions, true) ? (string)$post['sync_missing_action'] : 'none';
        $options['sync_missing_confirm'] = !empty($post['sync_missing_confirm']) ? '1' : '0';
        if ($this->entity !== 'product') {
            $options['supplier_code'] = '';
            $options['sync_missing_action'] = 'none';
            $options['sync_missing_confirm'] = '0';
        }
        $options['image_directory'] = isset($options['image_directory']) ? trim(str_replace('\\', '/', $options['image_directory']), '/') : 'catalog/import';
        if ($options['image_directory'] === '' || strpos($options['image_directory'], '..') !== false || !preg_match('#^[a-zA-Z0-9_./-]+$#', $options['image_directory'])) {
            $options['image_directory'] = 'catalog/import';
        }
        foreach (array('dry_run','empty_field','import_id','copy_default_language','replace_store_links','replace_seo_url','download_images','create_categories','create_manufacturers','replace_attributes','delete_category_children','create_global_options','create_option_values','update_option_definitions') as $flag) {
            $options[$flag] = !empty($post[$flag]) ? '1' : '0';
        }
        $stores = isset($post['store_ids']) ? (array)$post['store_ids'] : array(0);
        $options['store_ids'] = array_values(array_unique(array_map('intval', $stores)));
        $imageMb = max(1, min(50, (int)$this->config->get('module_scrapesmart_catalog_import_max_image_size_mb')));
        if (!$imageMb) { $imageMb = 10; }
        $options['max_image_bytes'] = $imageMb * 1024 * 1024;
        return $options;
    }


    protected function parseColumnMap($text) {
        $result = array();
        $lines = preg_split('/\r\n|\r|\n/', (string)$text);
        $count = 0;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || substr($line, 0, 1) === '#') { continue; }
            if (++$count > 200) { break; }
            $position = strpos($line, '=');
            if ($position === false) { continue; }
            $source = trim(substr($line, 0, $position));
            $target = trim(substr($line, $position + 1));
            if ($source === '' || $target === '' || strlen($source) > 255 || strlen($target) > 128) { continue; }
            if (!preg_match('/^_[A-Za-z0-9=:_-]+_$|^[A-Za-z][A-Za-z0-9_]*$/', $target)) { continue; }
            $target = substr($target, 0, 1) === '_' ? strtoupper($target) : strtolower($target);
            $result[$this->textLower($source)] = $target;
        }
        return $result;
    }

    protected function parseFieldRules($text) {
        $result = array();
        $allowed = array('preserve','overwrite','fill_empty','merge','replace','clear');
        $lines = preg_split('/\r\n|\r|\n/', (string)$text);
        $count = 0;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || substr($line, 0, 1) === '#') { continue; }
            if (++$count > 200) { break; }
            $position = strpos($line, '=');
            if ($position === false) { continue; }
            $field = trim(substr($line, 0, $position));
            $policy = strtolower(trim(substr($line, $position + 1)));
            if (!in_array($policy, $allowed, true)) { continue; }
            if (!preg_match('/^_[A-Za-z0-9=:_-]+_$|^[A-Za-z][A-Za-z0-9_]*$/', $field)) { continue; }
            $field = substr($field, 0, 1) === '_' ? strtoupper($field) : strtolower($field);
            $result[strtolower($field)] = $policy;
        }
        return $result;
    }

    protected function parseFieldPolicyArray($input) {
        $allowedPolicies = array('preserve','overwrite','fill_empty','merge','replace','clear');
        $allowedFields = array();
        foreach ($this->getFieldRuleFields() as $definition) {
            $field = isset($definition['field']) ? (string)$definition['field'] : '';
            if ($field !== '') { $allowedFields[$this->textLower($field)] = $field; }
        }

        $result = array();
        foreach ((array)$input as $field => $policy) {
            $fieldKey = $this->textLower(trim((string)$field));
            $policy = strtolower(trim((string)$policy));
            if (!isset($allowedFields[$fieldKey]) || !in_array($policy, $allowedPolicies, true)) { continue; }
            $result[$this->textLower($allowedFields[$fieldKey])] = $policy;
        }
        return $result;
    }

    protected function textLower($value) {
        $value = (string)$value;
        if (function_exists('mb_strtolower')) { return mb_strtolower($value, 'UTF-8'); }
        $value = strtr($value, array(
            'А'=>'а','Б'=>'б','В'=>'в','Г'=>'г','Д'=>'д','Е'=>'е','Ё'=>'ё','Ж'=>'ж','З'=>'з','И'=>'и','Й'=>'й','К'=>'к','Л'=>'л','М'=>'м','Н'=>'н','О'=>'о','П'=>'п','Р'=>'р','С'=>'с','Т'=>'т','У'=>'у','Ф'=>'ф','Х'=>'х','Ц'=>'ц','Ч'=>'ч','Ш'=>'ш','Щ'=>'щ','Ъ'=>'ъ','Ы'=>'ы','Ь'=>'ь','Э'=>'э','Ю'=>'ю','Я'=>'я','І'=>'і','Ї'=>'ї','Є'=>'є','Ґ'=>'ґ'
        ));
        return strtolower($value);
    }

    protected function getSanitizedExportOptions() {
        return array('language_id' => isset($this->request->get['language_id']) ? max(1, (int)$this->request->get['language_id']) : (int)$this->config->get('config_language_id'));
    }

    protected function hasValidUserToken() {
        $sessionToken = isset($this->session->data['user_token']) ? (string)$this->session->data['user_token'] : '';
        $requestToken = isset($this->request->get['user_token']) ? (string)$this->request->get['user_token'] : (isset($this->request->post['user_token']) ? (string)$this->request->post['user_token'] : '');
        return $sessionToken !== '' && $requestToken !== '' && hash_equals($sessionToken, $requestToken);
    }

    protected function getValidatedBatchSummary($detailLimit) {
        $batchId = $this->getBatchId();
        if ($batchId === '') { return null; }
        $summary = $this->model()->getBatchSummary($batchId, $detailLimit);
        if (empty($summary['batch']) || !isset($summary['batch']['entity_type']) || $summary['batch']['entity_type'] !== $this->entity) {
            return null;
        }
        return $summary;
    }

    protected function getBatchId() {
        $value = isset($this->request->post['batch_id']) ? $this->request->post['batch_id'] : (isset($this->request->get['batch_id']) ? $this->request->get['batch_id'] : '');
        $value = trim((string)$value);
        return preg_match('/^[a-f0-9]{32,40}$/', $value) ? $value : '';
    }

    protected function model() {
        return $this->{$this->modelProperty};
    }

    protected function json($data) {
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    protected function languageData() {
        $keys = array(
            'text_home','text_extension','text_enabled','text_disabled','text_module_disabled','text_csv_file','text_profile','help_profile','text_profile_custom','text_profile_price','text_profile_stock','text_profile_price_stock','text_profile_new','text_profile_update','text_profile_full','text_quick_modes','button_price_only','button_stock_only','button_new_only','button_full_import','text_mode','text_key_field','text_key_strategy','text_key_strict','help_key_strategy','text_column_map','help_column_map','text_field_rules','help_field_rules','text_rule_default','text_rule_preserve','text_rule_overwrite','text_rule_fill_empty','text_rule_merge','text_rule_replace','text_rule_clear','text_supplier_sync','text_supplier_code','help_supplier_code','text_missing_action','text_missing_none','text_missing_disable','text_missing_zero','text_missing_delete','text_missing_confirm','help_missing_sync','text_delimiter','text_encoding','text_language','text_stores','text_batch_size','text_dry_run','text_ignore_empty','text_import_ids','text_copy_languages','text_replace_stores','text_replace_seo','text_download_images','text_image_directory','text_create_categories','text_create_manufacturers','text_replace_attributes','text_delete_children','text_create_options','text_create_option_values','text_update_option_definitions','text_create_queue','text_start','text_pause','text_cancel','text_recover','text_export','text_recent_batches','text_no_batches','text_progress_prepare','text_progress_import','text_results','text_filter','text_all_statuses','text_search','text_previous','text_next','text_page','text_row','text_key','text_entity_id','text_status','text_action','text_message','text_sample_headers','text_safe_defaults','text_add','text_update','text_upsert','text_delete','text_auto','text_semicolon','text_comma','text_tab','text_pipe','text_yes','text_no','button_back','help_dry_run','help_ignore_empty','help_import_ids','help_copy_languages','help_replace_stores','help_replace_seo','help_download_images','help_update_option_definitions','error_file_missing','text_file_limit','count_total','count_pending','count_processing','count_created','count_updated','count_deleted','count_skipped','count_dry_run','count_warning','count_error','count_remaining','status_created','status_updated','status_deleted','status_skipped','status_dry_run','status_warning','status_error','action_create','action_update','action_delete','action_disable','action_zero','action_link','action_unlink','action_skip','action_error','text_queue_ready','text_completed','text_select','text_select_all','text_select_none','text_apply_selected','text_retry_errors','text_download_report','text_selection_updated','text_errors_requeued','text_review_ready','text_apply_started','count_review','status_review','action_cancel'
        );
        $data = array();
        foreach ($keys as $key) { $data[$key] = $this->language->get($key); }
        return $data;
    }

    protected function resolveEntity() {
        $value = isset($this->request->get['entity']) ? $this->request->get['entity'] : (isset($this->request->post['entity']) ? $this->request->post['entity'] : 'product');
        $value = strtolower(trim((string)$value));
        return in_array($value, array('product','category','manufacturer','option'), true) ? $value : 'product';
    }

    protected function getEntityOptions() {
        return array(
            'key_fields' => $this->getKeyFields(),
            'show_product' => $this->entity === 'product',
            'show_category' => $this->entity === 'category',
            'show_option' => $this->entity === 'option'
        );
    }

    protected function getKeyFields() {
        if ($this->entity === 'product') { return array('_SKU_','_MODEL_','_EAN_','_ID_','_NAME_'); }
        if ($this->entity === 'category') { return array('_ID_','_NAME_','_CATEGORY_'); }
        if ($this->entity === 'manufacturer') { return array('_ID_','_NAME_'); }
        return array('_PRODUCT_ID_','_PRODUCT_MODEL_','_PRODUCT_SKU_','_PRODUCT_NAME_');
    }

    protected function getFieldRuleFields() {
        $sets = array(
            'product' => array(
                '_NAME_'=>'scalar','_DESCRIPTION_'=>'text','_TAG_'=>'text','_META_TITLE_'=>'scalar','_META_H1_'=>'scalar','_META_DESCRIPTION_'=>'text','_META_KEYWORD_'=>'text',
                '_MODEL_'=>'scalar','_SKU_'=>'scalar','_UPC_'=>'scalar','_EAN_'=>'scalar','_JAN_'=>'scalar','_ISBN_'=>'scalar','_MPN_'=>'scalar','_LOCATION_'=>'scalar',
                '_PRICE_'=>'scalar','_QUANTITY_'=>'scalar','_MINIMUM_'=>'scalar','_SUBTRACT_'=>'scalar','_STOCK_STATUS_ID_'=>'scalar','_DATE_AVAILABLE_'=>'scalar',
                '_SHIPPING_'=>'scalar','_POINTS_'=>'scalar','_TAX_CLASS_ID_'=>'scalar','_WEIGHT_'=>'scalar','_WEIGHT_CLASS_ID_'=>'scalar','_LENGTH_'=>'scalar',
                '_WIDTH_'=>'scalar','_HEIGHT_'=>'scalar','_LENGTH_CLASS_ID_'=>'scalar','_STATUS_'=>'scalar','_NOINDEX_'=>'scalar','_SORT_ORDER_'=>'scalar',
                '_MANUFACTURER_'=>'scalar','_IMAGE_'=>'scalar','_IMAGES_'=>'list','_CATEGORY_'=>'list','_MAIN_CATEGORY_'=>'scalar','_ATTRIBUTES_'=>'list',
                '_STORE_IDS_'=>'list','_SEO_KEYWORD_'=>'scalar','_RELATED_PRODUCT_IDS_'=>'list','_RELATED_ARTICLE_IDS_'=>'list','_FILTER_IDS_'=>'list',
                '_DOWNLOAD_IDS_'=>'list','_LAYOUTS_'=>'list','_DISCOUNTS_'=>'list','_SPECIALS_'=>'list','_REWARDS_'=>'list','_RECURRING_'=>'list'
            ),
            'category' => array(
                '_NAME_'=>'scalar','_DESCRIPTION_'=>'text','_META_TITLE_'=>'scalar','_META_H1_'=>'scalar','_META_DESCRIPTION_'=>'text','_META_KEYWORD_'=>'text',
                '_PARENT_ID_'=>'scalar','_PARENT_CATEGORY_'=>'scalar','_TOP_'=>'scalar','_COLUMN_'=>'scalar','_IMAGE_'=>'scalar','_STATUS_'=>'scalar','_NOINDEX_'=>'scalar','_SORT_ORDER_'=>'scalar','_STORE_IDS_'=>'list',
                '_FILTER_IDS_'=>'list','_LAYOUTS_'=>'list','_RELATED_PRODUCT_IDS_'=>'list','_RELATED_ARTICLE_IDS_'=>'list','_SEO_KEYWORD_'=>'scalar'
            ),
            'manufacturer' => array(
                '_NAME_'=>'scalar','_DESCRIPTION_'=>'text','_META_TITLE_'=>'scalar','_META_H1_'=>'scalar','_META_DESCRIPTION_'=>'text','_META_KEYWORD_'=>'text',
                '_IMAGE_'=>'scalar','_SORT_ORDER_'=>'scalar','_NOINDEX_'=>'scalar','_STORE_IDS_'=>'list','_LAYOUTS_'=>'list',
                '_RELATED_PRODUCT_IDS_'=>'list','_RELATED_ARTICLE_IDS_'=>'list','_SEO_KEYWORD_'=>'scalar'
            ),
            'option' => array(
                '_VALUE_'=>'text','_QUANTITY_'=>'scalar','_SUBTRACT_'=>'scalar','_PRICE_'=>'scalar','_PRICE_PREFIX_'=>'scalar',
                '_POINTS_'=>'scalar','_POINTS_PREFIX_'=>'scalar','_WEIGHT_'=>'scalar','_WEIGHT_PREFIX_'=>'scalar','_REQUIRED_'=>'scalar'
            )
        );
        $clearRestricted = array(
            'product' => array('_NAME_', '_MODEL_', '_DATE_AVAILABLE_'),
            'category' => array('_NAME_'),
            'manufacturer' => array('_NAME_'),
            'option' => array()
        );
        $result = array();
        foreach ($sets[$this->entity] as $field => $kind) {
            $result[] = array(
                'field' => $field,
                'kind' => $kind,
                'allow_clear' => !in_array($field, $clearRestricted[$this->entity], true)
            );
        }
        return $result;
    }

    protected function getSampleHeaders() {
        if ($this->entity === 'product') { return '_ID_;_MODEL_;_NAME_;_DESCRIPTION_;_PRICE_;_QUANTITY_;_STATUS_;_NOINDEX_;_CATEGORY_;_MANUFACTURER_;_DISCOUNTS_;_SPECIALS_;_REWARDS_;_RECURRING_;_RELATED_ARTICLE_IDS_;_IMAGE_;_SEO_KEYWORD_'; }
        if ($this->entity === 'category') { return '_ID_;_NAME_;_PARENT_CATEGORY_;_DESCRIPTION_;_IMAGE_;_STATUS_;_NOINDEX_;_RELATED_PRODUCT_IDS_;_RELATED_ARTICLE_IDS_;_SEO_KEYWORD_'; }
        if ($this->entity === 'manufacturer') { return '_ID_;_NAME_;_DESCRIPTION_;_META_TITLE_;_IMAGE_;_SORT_ORDER_;_NOINDEX_;_RELATED_PRODUCT_IDS_;_RELATED_ARTICLE_IDS_;_LAYOUTS_;_SEO_KEYWORD_'; }
        return '_PRODUCT_ID_;_OPTION_;_OPTION_VALUE_;_QUANTITY_;_PRICE_;_PRICE_PREFIX_;_REQUIRED_';
    }
}
