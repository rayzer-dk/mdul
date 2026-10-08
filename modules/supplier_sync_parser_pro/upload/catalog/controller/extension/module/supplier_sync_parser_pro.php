<?php
class ControllerExtensionModuleSupplierSyncParserPro extends Controller {
    public function cron() {
        $this->response->addHeader('Content-Type: application/json');

        $token = isset($this->request->server['HTTP_X_CCP_CRON_KEY']) ? (string)$this->request->server['HTTP_X_CCP_CRON_KEY'] : '';
        if ($token === '' && isset($this->request->get['token'])) {
            // Legacy fallback for existing scheduled jobs. Prefer X-CCP-Cron-Key.
            $token = (string)$this->request->get['token'];
        }
        $saved_token = (string)$this->config->get('module_supplier_sync_parser_pro_cron_token');

        if ($saved_token === '' || $token === '' || !hash_equals($saved_token, $token)) {
            $this->response->setOutput(json_encode(array('ok' => false, 'message' => 'Invalid token')));
            return;
        }

        if (!(int)$this->config->get('module_supplier_sync_parser_pro_status')) {
            $this->response->setOutput(json_encode(array('ok' => false, 'message' => 'Module disabled')));
            return;
        }

        $supplier_id = isset($this->request->get['supplier_id']) ? (int)$this->request->get['supplier_id'] : 0;
        $limit = isset($this->request->get['limit']) ? (int)$this->request->get['limit'] : ((int)$this->config->get('module_supplier_sync_parser_pro_batch_limit') ?: 20);

        require_once(DIR_SYSTEM . 'library/codecart/supplier_sync_parser.php');
        $engine = new CodecartSupplierSyncParser($this->registry);
        $result = $engine->runCronCycle($supplier_id, $limit);
        if (!isset($result['ok'])) {
            $result['ok'] = true;
        }
        $this->response->setOutput(json_encode($result));
    }
}
