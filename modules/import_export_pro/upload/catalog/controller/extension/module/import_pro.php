<?php
class ControllerExtensionModuleImportPro extends Controller {
    public function cronImport() {
        $token = isset($this->request->server['HTTP_X_CCP_CRON_KEY']) ? (string)$this->request->server['HTTP_X_CCP_CRON_KEY'] : (isset($this->request->get['token']) ? (string)$this->request->get['token'] : '');
        $saved = (string)$this->config->get('module_import_pro_cron_token');
        if (!$this->config->get('module_import_pro_status')) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode(array('success' => false, 'error' => 'Module disabled. Enable and save the module settings before running cron.'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }
        if ($saved === '' || !function_exists('hash_equals') || !hash_equals($saved, $token)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode(array('success' => false, 'error' => 'Invalid token'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        $profile_id = isset($this->request->get['profile_id']) ? (int)$this->request->get['profile_id'] : 0;
        if (!$profile_id) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode(array('success' => false, 'error' => 'Missing profile_id'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }

        $dry_run = !empty($this->request->get['dry_run']);
        $limit = isset($this->request->get['limit']) ? max(0, (int)$this->request->get['limit']) : 0;
        $offset = isset($this->request->get['offset']) ? max(0, (int)$this->request->get['offset']) : 0;
        $run_mode = isset($this->request->get['run_mode']) ? strtolower(trim((string)$this->request->get['run_mode'])) : 'price_stock';
        if ($run_mode === 'price_qty') { $run_mode = 'price_stock'; }
        if (!in_array($run_mode, array('price_only', 'quantity_only', 'price_stock'), true)) {
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode(array('success' => false, 'error' => 'Cron import is intentionally restricted to price_only, quantity_only or price_stock. Full catalog imports require administrator dry-run review and confirmation.'), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }
        $session_started_at = isset($this->request->get['session_started_at']) ? (string)$this->request->get['session_started_at'] : date('Y-m-d H:i:s', time() - 1);
        $finalize_missing = false;

        $this->load->model('extension/module/import_pro');
        try {
            $scheduled = !empty($this->request->get['scheduled']);
            $result = $scheduled
                ? $this->model_extension_module_import_pro->runScheduledImport($profile_id, $dry_run, $limit > 0 ? $limit : 100, $run_mode)
                : $this->model_extension_module_import_pro->runImport($profile_id, $dry_run, $limit, $offset, $run_mode, $session_started_at, $finalize_missing);
            $out = array('success' => true, 'result' => $result);
            if (!$scheduled && !empty($result['has_more']) && $limit > 0) {
                $catalog_url = (defined('HTTPS_CATALOG') && HTTPS_CATALOG) ? HTTPS_CATALOG : ((defined('HTTP_CATALOG') && HTTP_CATALOG) ? HTTP_CATALOG : $this->config->get('config_url'));
                $out['next_url'] = rtrim($catalog_url, '/') . '/index.php?route=extension/module/import_pro/cronImport&token=' . urlencode($saved) . '&profile_id=' . (int)$profile_id . '&limit=' . (int)$limit . '&offset=' . (int)$result['next_offset'] . '&run_mode=' . urlencode($run_mode) . '&session_started_at=' . urlencode($session_started_at) . '&finalize_missing=' . ($finalize_missing ? '1' : '0') . ($dry_run ? '&dry_run=1' : '');
            }
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $e) {
            $this->log->write('Import Pro cron error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->response->addHeader('Content-Type: application/json; charset=utf-8');
            $this->response->setOutput(json_encode(array('success' => false, 'error' => $e->getMessage()), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        }
    }

    public function exportFeed() {
        $token = isset($this->request->get['token']) ? (string)$this->request->get['token'] : '';
        $saved = (string)$this->config->get('module_import_pro_cron_token');
        if (!$this->config->get('module_import_pro_status')) {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput('Module disabled. Enable and save the module settings before using public export feeds.');
            return;
        }
        if ($saved === '' || !function_exists('hash_equals') || !hash_equals($saved, $token)) {
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput('Invalid token');
            return;
        }
        $format = isset($this->request->get['format']) ? strtolower((string)$this->request->get['format']) : 'rozetka_yml';
        if (!in_array($format, array('rozetka_yml', 'epicentr_xml', 'hotline_xml', 'prom_yml', 'price_ua_yml', 'facebook_csv', 'pinterest_csv', 'tiktok_csv'), true)) {
            $format = 'rozetka_yml';
        }
        $filters = array(
            'status'          => isset($this->request->get['status']) ? $this->request->get['status'] : '1',
            'category_id'     => isset($this->request->get['category_id']) ? (int)$this->request->get['category_id'] : 0,
            'manufacturer_id' => isset($this->request->get['manufacturer_id']) ? (int)$this->request->get['manufacturer_id'] : 0,
            'supplier_id'     => isset($this->request->get['supplier_id']) ? (int)$this->request->get['supplier_id'] : 0,
            'language_id'     => isset($this->request->get['language_id']) ? (int)$this->request->get['language_id'] : (int)$this->config->get('config_language_id'),
            'in_stock_only'   => isset($this->request->get['in_stock_only']) ? (int)!empty($this->request->get['in_stock_only']) : 0,
            'limit'           => isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : 0,
            'offset'          => isset($this->request->get['offset']) ? (int)$this->request->get['offset'] : 0,
            'image_format'    => 'url'
        );
        $this->load->model('extension/module/import_pro');
        try {
            $result = $this->model_extension_module_import_pro->exportProducts($format, $filters);
            $this->response->addHeader('Content-Type: ' . $result['mime'] . '; charset=utf-8');
            $this->response->addHeader('Cache-Control: no-cache, must-revalidate');
            $this->response->setOutput($result['content']);
        } catch (Throwable $e) {
            $this->log->write('Import Pro export feed error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->response->addHeader('Content-Type: text/plain; charset=utf-8');
            $this->response->setOutput($e->getMessage());
        }
    }
}
?>
