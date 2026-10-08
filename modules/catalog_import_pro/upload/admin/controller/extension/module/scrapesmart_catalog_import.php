<?php
require_once(DIR_SYSTEM . 'library/catalog_import_pro/admin_controller.php');
class ControllerExtensionModuleScrapesmartCatalogImport extends ControllerExtensionModuleScrapesmartCatalogImportBase {
    const VERSION = '2.3.1';

    public function index() {
        $this->load->language('extension/module/scrapesmart_catalog_import');
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/module/scrapesmart_catalog_import');

        if ((string)$this->config->get('module_scrapesmart_catalog_import_version') !== self::VERSION && $this->user->hasPermission('modify', 'extension/module/scrapesmart_catalog_import')) {
            try {
                $this->model_extension_module_scrapesmart_catalog_import->install();
                $this->model_setting_setting->editSetting('module_scrapesmart_catalog_import', array(
                    'module_scrapesmart_catalog_import_status' => 0,
                    'module_scrapesmart_catalog_import_max_file_size_mb' => (int)$this->config->get('module_scrapesmart_catalog_import_max_file_size_mb') > 0 ? max(1, min(500, (int)$this->config->get('module_scrapesmart_catalog_import_max_file_size_mb'))) : 50,
                    'module_scrapesmart_catalog_import_max_image_size_mb' => (int)$this->config->get('module_scrapesmart_catalog_import_max_image_size_mb') > 0 ? max(1, min(50, (int)$this->config->get('module_scrapesmart_catalog_import_max_image_size_mb'))) : 10,
                    'module_scrapesmart_catalog_import_retention_days' => (int)$this->config->get('module_scrapesmart_catalog_import_retention_days') > 0 ? max(1, min(3650, (int)$this->config->get('module_scrapesmart_catalog_import_retention_days'))) : 90,
                    'module_scrapesmart_catalog_import_version' => self::VERSION
                ));
                $this->session->data['success'] = $this->language->get('text_migration_success');
                $this->response->redirect($this->url->link('extension/module/scrapesmart_catalog_import', 'user_token=' . $this->session->data['user_token'], true));
                return;
            } catch (Throwable $e) {
                $this->log->write('Catalog Import PRO migration: ' . $e->getMessage());
                $this->error['warning'] = $this->language->get('error_migration');
            }
        }

        if ($this->config->get('module_scrapesmart_catalog_import_status') && empty($this->session->data['catalog_import_pro_cleanup_done'])) {
            try {
                $days = max(1, min(3650, (int)$this->config->get('module_scrapesmart_catalog_import_retention_days')));
                if (!$days) { $days = 90; }
                $this->model_extension_module_scrapesmart_catalog_import->cleanup($days);
                $this->session->data['catalog_import_pro_cleanup_done'] = 1;
            } catch (Throwable $e) {
                $this->log->write('Catalog Import PRO automatic cleanup: ' . $e->getMessage());
            }
        }

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $settings = array(
                'module_scrapesmart_catalog_import_status' => !empty($this->request->post['module_scrapesmart_catalog_import_status']) ? 1 : 0,
                'module_scrapesmart_catalog_import_max_file_size_mb' => max(1, min(500, (int)$this->request->post['module_scrapesmart_catalog_import_max_file_size_mb'])),
                'module_scrapesmart_catalog_import_max_image_size_mb' => max(1, min(50, (int)$this->request->post['module_scrapesmart_catalog_import_max_image_size_mb'])),
                'module_scrapesmart_catalog_import_retention_days' => max(1, min(3650, (int)$this->request->post['module_scrapesmart_catalog_import_retention_days'])),
                'module_scrapesmart_catalog_import_version' => self::VERSION
            );
            $this->model_setting_setting->editSetting('module_scrapesmart_catalog_import', $settings);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/module/scrapesmart_catalog_import', 'user_token=' . $this->session->data['user_token'], true));
            return;
        }

        $data = array();
        foreach (array('heading_title','text_home','text_extension','text_edit','text_enabled','text_disabled','text_dashboard_intro','text_settings','text_status','text_max_file_size','text_max_image_size','text_retention_days','text_cleanup','text_cleanup_help','text_modules','text_product','text_category','text_manufacturer','text_option','text_product_help','text_category_help','text_manufacturer_help','text_option_help','text_about','text_author','text_version','text_compatibility','text_compatibility_value','text_backup_warning','button_save','button_cancel','button_open','button_cleanup','error_warning') as $key) {
            $data[$key] = $this->language->get($key);
        }

        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : (isset($this->session->data['error_warning']) ? $this->session->data['error_warning'] : '');
        unset($this->session->data['error_warning']);
        $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
        unset($this->session->data['success']);

        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/scrapesmart_catalog_import', 'user_token=' . $this->session->data['user_token'], true))
        );
        $data['action'] = $this->url->link('extension/module/scrapesmart_catalog_import', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['cleanup_url'] = $this->url->link('extension/module/scrapesmart_catalog_import/cleanup', 'user_token=' . $this->session->data['user_token'], true);
        $data['product_url'] = $this->url->link('extension/module/scrapesmart_catalog_import/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=product', true);
        $data['category_url'] = $this->url->link('extension/module/scrapesmart_catalog_import/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=category', true);
        $data['manufacturer_url'] = $this->url->link('extension/module/scrapesmart_catalog_import/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=manufacturer', true);
        $data['option_url'] = $this->url->link('extension/module/scrapesmart_catalog_import/entity', 'user_token=' . $this->session->data['user_token'] . '&entity=option', true);

        $data['status'] = $this->value('module_scrapesmart_catalog_import_status', 0);
        $data['max_file_size_mb'] = $this->value('module_scrapesmart_catalog_import_max_file_size_mb', 50);
        $data['max_image_size_mb'] = $this->value('module_scrapesmart_catalog_import_max_image_size_mb', 10);
        $data['retention_days'] = $this->value('module_scrapesmart_catalog_import_retention_days', 90);
        $data['version'] = self::VERSION;
        $data['author'] = 'CodeCart PRO';
        $data['author_url'] = 'https://codecartpro.com';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/scrapesmart_catalog_import_dashboard', $data));
    }

    public function cleanup() {
        $this->load->language('extension/module/scrapesmart_catalog_import');
        if (!isset($this->request->server['REQUEST_METHOD']) || strtoupper((string)$this->request->server['REQUEST_METHOD']) !== 'POST' || !$this->hasValidUserToken()) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
        } elseif (!$this->user->hasPermission('modify', 'extension/module/scrapesmart_catalog_import')) {
            $this->session->data['error_warning'] = $this->language->get('error_permission');
        } elseif (!$this->config->get('module_scrapesmart_catalog_import_status')) {
            $this->session->data['error_warning'] = $this->language->get('error_disabled');
        } else {
            $this->load->model('extension/module/scrapesmart_catalog_import');
            $days = max(1, min(3650, (int)$this->config->get('module_scrapesmart_catalog_import_retention_days')));
            if (!$days) { $days = 90; }
            $count = $this->model_extension_module_scrapesmart_catalog_import->cleanup($days);
            $this->session->data['success'] = sprintf($this->language->get('text_cleanup_success'), $count);
        }
        $this->response->redirect($this->url->link('extension/module/scrapesmart_catalog_import', 'user_token=' . $this->session->data['user_token'], true));
    }

    public function install() {
        $this->load->model('extension/module/scrapesmart_catalog_import');
        $this->model_extension_module_scrapesmart_catalog_import->install();
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('module_scrapesmart_catalog_import', array(
            'module_scrapesmart_catalog_import_status' => 0,
            'module_scrapesmart_catalog_import_max_file_size_mb' => 50,
            'module_scrapesmart_catalog_import_max_image_size_mb' => 10,
            'module_scrapesmart_catalog_import_retention_days' => 90,
            'module_scrapesmart_catalog_import_version' => self::VERSION
        ));
        if (isset($this->user) && method_exists($this->user, 'getGroupId')) {
            $this->load->model('user/user_group');
            $route = 'extension/module/scrapesmart_catalog_import';
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $route);
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $route);
        }
    }

    public function uninstall() {
        $this->load->model('extension/module/scrapesmart_catalog_import');
        $this->model_extension_module_scrapesmart_catalog_import->uninstall();
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_scrapesmart_catalog_import');
    }

    private function value($key, $default) {
        if (isset($this->request->post[$key])) { return $this->request->post[$key]; }
        $value = $this->config->get($key);
        return $value === null || $value === '' ? $default : $value;
    }

    private function validate() {
        if (!$this->hasValidUserToken()) {
            $this->error['warning'] = $this->language->get('error_permission');
        } elseif (!$this->user->hasPermission('modify', 'extension/module/scrapesmart_catalog_import')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
}
