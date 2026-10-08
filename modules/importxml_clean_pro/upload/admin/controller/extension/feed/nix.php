<?php

/**
 * @category   OpenCart
 * @package    ImportXML Clean PRO v1.4.2
 * @author     CodeCart PRO
 * @link       https://codecartpro.com
 * @copyright  CodeCart PRO
 */

if (!class_exists('StdE') && is_file(DIR_SYSTEM . 'library/stde.php')) {
	require_once DIR_SYSTEM . 'library/stde.php';
}

if (!class_exists('StdeLog') && is_file(DIR_SYSTEM . 'library/stdelog.php')) {
	require_once DIR_SYSTEM . 'library/stdelog.php';
}

define('NIX_VERSION', '1.4.2');
define('NIX_AUTHOR', 'CodeCart PRO');
define('NIX_AUTHOR_LINK', 'https://codecartpro.com');
define('NIX_UPDATE_LINK', 'https://codecartpro.com');

class ControllerExtensionFeedNix extends Controller {

	public $errors = [];
	public $tags = [];
	public $request_time;
	public $languages = [];
	public $stores = []; // for OC 3 SEO URL
	public $xml = [];
	public $supplier = [];
	public $offers_prepared = [];
	public $correlation = [];
	public $categories = [];
	public $hierarchy = [];
	private $stdelog;
	private $memory_limit = 268435456; // 256M
	private $cron_mode = false;

	function __construct($registry) {
		parent::__construct($registry);
		
		$this->request_time = time(); // for helperHaveTime()
		
		// StdE Require
		if (!class_exists('StdE') || !class_exists('StdeLog')) {
			throw new Exception('ImportXML Clean PRO v1.4.2: required library StdE/StdeLog is not loaded. Reinstall module package.');
		}

		$this->stde = new StdE($registry);
		$this->registry->set('stde', $this->stde);
		$this->stde->setCode('nix');
		$this->stde->setType('feed_monolithic');
		
		// StdeLog require
		$this->stdelog = new StdeLog('nix');		
		$this->registry->set('stdelog', $this->stdelog);
		$this->stdelog->setDebug(2);
		
		// todo...
		//$this->memory_limit = $this->config->get('nix_memory_limit'); //??
		$this->memory_limit = $this->helperMemoryLimit();
		
		// !A  Note-5
		// Каждый импорт выделяю в отдельный лог-файл - но это надо и в конструторе еще проследить, чтобы при создании экземпляра класса сразу присваивалась правильная метка
		// Здесь только те переменные сессии, которые нужны для лог-файла
		// Остальные - в processingImportAjax()
		
		if (isset($this->request->post['nix_new_submit']) && $this->user && $this->user->hasPermission('modify', 'extension/feed/nix')) {
			// При успешном завершении импорта, $this->session->data['nix']['processing_start_time'] обнуляется и так
			// Но в случае ошибки, необходимо обнулить принудительно
			if (isset($this->session->data['nix']['processing_start_time'])) {
				unset($this->session->data['nix']['processing_start_time']);
			}
			
			$this->session->data['nix']['processing_start_time'] = time(); // IT IS NOT required to be time!
			$this->stdelog->write(3, '__construct() :: NEW SESSION');
			
			$this->stdelog->write(2, '__construct() :: SEND LOGS TO FILE `nix_' . date("Y-m-d") . '_' . $this->session->data['nix']['processing_start_time'] . '.log`');
		}
		
		if (isset($this->session->data['nix']['processing_start_time'])) {
			$this->stdelog->setMarker($this->session->data['nix']['processing_start_time']);
		}
	}
	
	public function install() {
		$this->load->model('user/user_group');
		$routes = [
			'extension/feed/nix',
			'extension/feed/nix/partImport',
			'extension/feed/nix/processingImportAjax',
			'extension/feed/nix/supplierForm',
			'extension/feed/nix/supplierEdit',
			'extension/feed/nix/supplierSave',
			'extension/feed/nix/supplierDelete',
			'extension/feed/nix/clearLogs',
			'extension/feed/nix/exportSettings',
			'extension/feed/nix/importSettings',
			'extension/feed/nix/resetDefaults',
			'extension/feed/nix/cron'
		];

		foreach ($routes as $route) {
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $route);
			$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $route);
		}

		$this->load->model('extension/feed/nix');
		$this->model_extension_feed_nix->install();
		$this->model_extension_feed_nix->cleanupLegacyModification();

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('feed_nix', [
			'feed_nix_status' => 0,
			'feed_nix_delete_data_on_uninstall' => 0,
			'feed_nix_cron_status' => 0,
			'feed_nix_cron_token' => $this->generateCronToken(),
			'feed_nix_cron_supplier_id' => 0,
			'feed_nix_cron_language_id' => 0,
			'feed_nix_cron_mode' => 'price_stock',
			'feed_nix_cron_update_if_exist' => 1,
			'feed_nix_cron_copy_description' => 1,
			'feed_nix_cron_copy_attributes' => 1,
			'feed_nix_cron_delete_all' => 0
		]);
	}

	public function uninstall() {
		$this->load->model('extension/feed/nix');
		$this->model_extension_feed_nix->uninstall();
	}
	
	public function index() {
		foreach ($this->load->language('extension/feed/nix') as $key => $value) {
			$data[$key] = $value;
		}

		$this->document->setTitle($this->language->get('heading_title'));

		if (!$this->user->hasPermission('access', 'extension/feed/nix')) {
			return $this->permissionDenied($data);
		}

		if (!$this->validateUserToken()) {
			$data['errors'] = ['warning' => $this->language->get('error_user_token')];
			return $this->permissionDenied($data);
		}

		$this->load->model('extension/feed/nix');
		$this->load->model('setting/setting');
		$this->load->model('localisation/language');
		
		$data['text_copyright'] = sprintf($this->language->get('text_copyright'), NIX_VERSION);
		$data['message_success'] = '';

		$cron_token_current = trim((string)$this->config->get('feed_nix_cron_token'));
		if ($cron_token_current === '') {
			$cron_token_current = $this->generateCronToken();
			$this->saveSettingValue('feed_nix', 'feed_nix_cron_token', $cron_token_current);
		}

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validateSettings()) {
			$status = isset($this->request->post['feed_nix_status']) ? (int)$this->request->post['feed_nix_status'] : 0;
			$delete_data_on_uninstall = isset($this->request->post['feed_nix_delete_data_on_uninstall']) ? 1 : 0;
			$cron_token = trim((string)($this->request->post['feed_nix_cron_token'] ?? ''));
			if ($cron_token === '') {
				$cron_token = $this->generateCronToken();
			}
			$data['message_success'] = $this->language->get('text_success');
			$this->model_setting_setting->editSetting('feed_nix', [
				'feed_nix_status' => $status,
				'feed_nix_delete_data_on_uninstall' => $delete_data_on_uninstall,
				'feed_nix_cron_status' => isset($this->request->post['feed_nix_cron_status']) ? 1 : 0,
				'feed_nix_cron_token' => $cron_token,
				'feed_nix_cron_supplier_id' => (int)($this->request->post['feed_nix_cron_supplier_id'] ?? 0),
				'feed_nix_cron_language_id' => (int)($this->request->post['feed_nix_cron_language_id'] ?? 0),
				'feed_nix_cron_mode' => in_array(($this->request->post['feed_nix_cron_mode'] ?? 'price_stock'), ['price_stock', 'full'], true) ? $this->request->post['feed_nix_cron_mode'] : 'price_stock',
				'feed_nix_cron_update_if_exist' => isset($this->request->post['feed_nix_cron_update_if_exist']) ? 1 : 0,
				'feed_nix_cron_copy_description' => isset($this->request->post['feed_nix_cron_copy_description']) ? 1 : 0,
				'feed_nix_cron_copy_attributes' => isset($this->request->post['feed_nix_cron_copy_attributes']) ? 1 : 0,
				'feed_nix_cron_delete_all' => isset($this->request->post['feed_nix_cron_delete_all']) ? 1 : 0
			]);
		}

		if (isset($this->request->post['feed_nix_status'])) {
			$data['nix_status'] = (int)$this->request->post['feed_nix_status'];
		} else {
			$data['nix_status'] = $this->isEnabled() ? 1 : 0;
		}

		if (isset($this->request->post['feed_nix_delete_data_on_uninstall'])) {
			$data['delete_data_on_uninstall'] = 1;
		} else {
			$data['delete_data_on_uninstall'] = (int)$this->config->get('feed_nix_delete_data_on_uninstall');
		}

		$data['languages'] = $this->stde->languages($this->model_localisation_language->getLanguages());
		$data['cron_status'] = isset($this->request->post['feed_nix_cron_status']) ? 1 : (int)$this->config->get('feed_nix_cron_status');
		$data['cron_token'] = isset($this->request->post['feed_nix_cron_token']) ? trim((string)$this->request->post['feed_nix_cron_token']) : trim((string)$this->config->get('feed_nix_cron_token'));
		if ($data['cron_token'] === '') {
			$data['cron_token'] = $cron_token_current;
		}
		$data['cron_supplier_id'] = isset($this->request->post['feed_nix_cron_supplier_id']) ? (int)$this->request->post['feed_nix_cron_supplier_id'] : (int)$this->config->get('feed_nix_cron_supplier_id');
		$data['cron_language_id'] = isset($this->request->post['feed_nix_cron_language_id']) ? (int)$this->request->post['feed_nix_cron_language_id'] : (int)$this->config->get('feed_nix_cron_language_id');
		$data['cron_mode'] = isset($this->request->post['feed_nix_cron_mode']) ? (string)$this->request->post['feed_nix_cron_mode'] : (string)$this->config->get('feed_nix_cron_mode');
		if (!in_array($data['cron_mode'], ['price_stock', 'full'], true)) {
			$data['cron_mode'] = 'price_stock';
		}
		$data['cron_update_if_exist'] = isset($this->request->post['feed_nix_cron_update_if_exist']) ? 1 : (int)$this->config->get('feed_nix_cron_update_if_exist');
		$data['cron_copy_description'] = isset($this->request->post['feed_nix_cron_copy_description']) ? 1 : (int)$this->config->get('feed_nix_cron_copy_description');
		$data['cron_copy_attributes'] = isset($this->request->post['feed_nix_cron_copy_attributes']) ? 1 : (int)$this->config->get('feed_nix_cron_copy_attributes');
		$data['cron_delete_all'] = isset($this->request->post['feed_nix_cron_delete_all']) ? 1 : (int)$this->config->get('feed_nix_cron_delete_all');
		$data['cron_cli_command'] = 'php ' . DIR_APPLICATION . 'nix_cron.php token=' . $data['cron_token'];
		$data['cron_url'] = (defined('HTTPS_SERVER') ? HTTPS_SERVER : HTTP_SERVER) . 'nix_cron.php?token=' . urlencode($data['cron_token']);

		$data['module_version'] = NIX_VERSION;
		$data['author_name'] = NIX_AUTHOR;
		$data['author_link'] = NIX_AUTHOR_LINK;
		$data['update_link'] = NIX_UPDATE_LINK;
		$data['link_export_settings'] = 'index.php?route=extension/feed/nix/exportSettings&user_token=' . $this->session->data['user_token'];
		$data['link_import_settings'] = 'index.php?route=extension/feed/nix/importSettings&user_token=' . $this->session->data['user_token'];
		$data['link_reset_settings'] = 'index.php?route=extension/feed/nix/resetDefaults&user_token=' . $this->session->data['user_token'];
		$data['diagnostics'] = $this->model_extension_feed_nix->diagnostics($this->isEnabled(), $this->getDiagnosticTexts());
		$data['log_files'] = $this->model_extension_feed_nix->getLogFiles();
		$data['errors'] = $this->errors;
		$data['user_token'] = $this->session->data['user_token'];
		$data['breadcrumbs'] = $this->stde->breadcrumbs();
		$data['action'] = $this->stde->link('action');
		$data['cancel'] = $this->stde->link('cancel');
		$data['link_part_settings'] = $this->stde->link('index');
		$data['link_part_import'] = $this->stde->link('partImport');
		$data['supplier_list'] = $this->model_extension_feed_nix->supplierList();
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/feed/nix_setting', $data));
	}

	public function supplierForm() {
		foreach ($this->load->language('extension/feed/nix') as $key => $value) {
			$data[$key] = $value;
		}

		if (!$this->user->hasPermission('access', 'extension/feed/nix')) {
			$this->response->setOutput('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' . $this->language->get('error_permission') . '</div>');
			return;
		}

		if (!$this->validateUserToken()) {
			$this->response->setOutput('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' . $this->language->get('error_user_token') . '</div>');
			return;
		}
		
		$data['user_token'] = $this->session->data['user_token']; // user_token need in js ajax
		
		$this->load->model('extension/feed/nix');
		
		// default tags
		$data['supplier']['tags'] = [
			'name'							 => 'name',
			'meta_h1'					 => 'meta_h1',
			'meta_title'				 => 'meta_title',
			'meta_description'	 => 'meta_description',
			'meta_keyword'			 => 'meta_keyword',
			'tag'							 => 'tag',
			'model'							 => 'model',
			'sku'								 => 'vendorCode',
			'upc'								 => 'upc',
			'ean'								 => 'ean',
			'jan'								 => 'jan',
			'isbn'							 => 'isbn',
			'mpn'								 => 'mpn',
			'location'					 => 'location',
			'description'				 => 'description',
			'price_purchasing'	 => 'optPrice',
			'price_rrp'					 => 'price',
			'special_price' => 'special_price',
			'currency'					 => 'currencyId',
			'quantity'					 => 'quantity',
			'date_available'	 => 'date_available',
			'points'					 => 'points',
			'minimum'					 => 'minimum',
			'subtract'					 => 'subtract',
			'stock_status_id'	 => 'stock_status_id',
			'shipping'					 => 'shipping',
			'tax_class_id'			 => 'tax_class_id',
			'length'						 => 'length',
			'width'							 => 'width',
			'height'						 => 'height',
			'length_class_id'	 => 'length_class_id',
			'weight'						 => 'weight',
			'weight_class_id'	 => 'weight_class_id',
			'status'						 => 'status',
			'sort_order'				 => 'sort_order',
			'images'						 => 'picture',
			'category'					 => 'categoryId',
			'google_product_category_id' => 'google_product_category_id',
			'manufacturer_name'	 => 'vendor',
			'attributes'				 => 'param',
		];

		// default attributes
		$data['supplier']['attributes'] =  [
			'parent_id'	=> 'parent_id',
		];

		$this->response->setOutput($this->load->view('extension/feed/nix_supplier', $data));
	}
	
	public function supplierEdit() {
		foreach ($this->load->language('extension/feed/nix') as $key => $value) {
			$data[$key] = $value;
		}

		if (!$this->user->hasPermission('access', 'extension/feed/nix')) {
			$this->response->setOutput('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' . $this->language->get('error_permission') . '</div>');
			return;
		}

		if (!$this->validateUserToken()) {
			$this->response->setOutput('<div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> ' . $this->language->get('error_user_token') . '</div>');
			return;
		}
		
		$this->load->model('extension/feed/nix');		
		
		$data['user_token'] = $this->session->data['user_token']; // user_token need in js ajax
		
		$data['supplier_id'] = isset($this->request->get['supplier_id']) ? (int)$this->request->get['supplier_id'] : 0; // for form hidden field
		
		$data['supplier'] = $this->model_extension_feed_nix->supplierGet($data['supplier_id']);

		$this->response->setOutput($this->load->view('extension/feed/nix_supplier', $data));
	}
	
	public function supplierSave() {
		$this->load->language('extension/feed/nix');
		
		$json = [
			'status' => 'Error',
			'msg' => $this->language->get('msg_supplier_error'),
		];

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['msg'] = $this->language->get('error_request_method');
			$json['errors']['warning'] = $this->language->get('error_request_method');
			return $this->json($json);
		}

		if (!$this->validateUserToken()) {
			$json['msg'] = $this->language->get('error_user_token');
			$json['errors']['warning'] = $this->language->get('error_user_token');
			return $this->json($json);
		}

		if (!$this->user->hasPermission('modify', 'extension/feed/nix')) {
			$json['errors']['warning'] = $this->language->get('error_permission');
			return $this->json($json);
		}

		$this->load->model('extension/feed/nix');
		
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$this->request->post['name'] = trim($this->request->post['name'] ?? '');
			$this->request->post['markup'] = trim((string)($this->request->post['markup'] ?? '0'));
			$this->request->post['link_price'] = trim($this->request->post['link_price'] ?? '');
			$this->request->post['tags'] = (isset($this->request->post['tags']) && is_array($this->request->post['tags'])) ? $this->request->post['tags'] : [];
			$this->request->post['attributes'] = (isset($this->request->post['attributes']) && is_array($this->request->post['attributes'])) ? $this->request->post['attributes'] : [];

			if ($this->request->post['name'] === '') {
				$json['errors']['name'] = $this->language->get('error_supplier_name_empty');
			}
			
			if ($this->request->post['markup'] === '' || !is_numeric($this->request->post['markup'])) {
				$json['errors']['markup'] = $this->language->get('error_supplier_markup_empty');
			}
			
			foreach ($this->request->post['tags'] as $key => $value) {
				$this->request->post['tags'][$key] = trim((string)$value);
			}
			
			$tags_required = ['name', 'category'];

			foreach ($tags_required as $key) {
				if (empty($this->request->post['tags'][$key])) {
					$json['errors']['tag-' . str_replace('_', '-', $key)] = sprintf($this->language->get('error_tag_empty'), $this->language->get('entry_tag_product_' . $key));
				}
			}

			if (empty($this->request->post['tags']['model']) && empty($this->request->post['tags']['sku'])) {
				$json['errors']['tag-model'] = $this->language->get('error_model_or_sku_required');
			}

			if (empty($this->request->post['tags']['price_purchasing']) && empty($this->request->post['tags']['price_rrp'])) {
				$json['errors']['tag-price-purchasing'] = $this->language->get('error_price_tag_required');
			}
			
			foreach ($this->request->post['attributes'] as $key => $value) {
				$this->request->post['attributes'][$key] = trim((string)$value);
			}

		}
		
		if (!isset($json['errors'])) {
			if (!empty($this->request->post['supplier_id'])) {
				$json['result'] = $this->model_extension_feed_nix->supplierEdit($this->request->post);
			} else {
				$json['supplier_id'] = $this->model_extension_feed_nix->supplierAdd($this->request->post);
			}
			
			$json['status'] = 'OK';			
			$json['msg'] = $this->language->get('msg_supplier_success');
		}
		
		return $this->json($json);
	}

	public function supplierDelete() {
		$this->load->language('extension/feed/nix');
		
		$json = [
			'status' => 'Error',
			'msg' => $this->language->get('msg_supplier_error'),
		];

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['msg'] = $this->language->get('error_request_method');
			$json['errors']['warning'] = $this->language->get('error_request_method');
			return $this->json($json);
		}

		if (!$this->validateUserToken()) {
			$json['msg'] = $this->language->get('error_user_token');
			$json['errors']['warning'] = $this->language->get('error_user_token');
			return $this->json($json);
		}

		if (!$this->user->hasPermission('modify', 'extension/feed/nix')) {
			$json['errors']['warning'] = $this->language->get('error_permission');
			return $this->json($json);
		}

		$this->load->model('extension/feed/nix');
		
		if (isset($this->request->post['supplier_id'])) {
			$json['result'] = $this->model_extension_feed_nix->supplierDelete((int)$this->request->post['supplier_id']);
		} else {
			$json['errors']['supplier_id'] = $this->language->get('error_supplier_delete_id');
		}
		
		if (!isset($json['errors'])) {
			$json['status'] = 'OK';			
			$json['msg'] = $this->language->get('msg_supplier_delete_success');
		}
		
		return $this->json($json);
	}

	public function partImport() {
		$this->stdelog->write(2, 'partImport() is called');
		
		foreach ($this->load->language('extension/feed/nix') as $key => $value) {
			$data[$key] = $value;
		}

		$this->document->setTitle($this->language->get('heading_title'));

		if (!$this->user->hasPermission('access', 'extension/feed/nix')) {
			return $this->permissionDenied($data);
		}

		$this->load->model('extension/feed/nix');
		$this->model_extension_feed_nix->install();
		$this->load->model('catalog/manufacturer');
		
		$data['text_copyright'] = sprintf($this->language->get('text_copyright'), NIX_VERSION);
		
		$this->load->model('localisation/language');

		$this->languages = $data['languages'] = $this->stde->languages($this->model_localisation_language->getLanguages());
		
		if (count($data['languages']) > 1) {
			$data['is_multilingual'] = true;
		} else {
			$data['is_multilingual'] = false;
		}
		
		$data['config_language_id'] = $this->config->get('config_language_id');
		$data['nix_status'] = $this->isEnabled() ? 1 : 0;

		//$data['lang'] = $this->language->get('lang');
	
		
		if (isset($this->errors)) {
			$data['errors'] = $this->errors;
		}
		
		$data['language_id'] = $this->request->post['language_id'] ?? '*';
//		$data['primary_language'] = $this->request->post['primary_language'] ?? 0;
		
		$data['copy_description'] = $this->request->post['copy_description'] ?? '';
		$data['copy_attributes'] = $this->request->post['copy_attributes'] ?? '';
		
		$data['delete_all'] = $this->request->post['delete_all'] ?? '';
		$data['update_if_exist'] = $this->request->post['update_if_exist'] ?? '';
		
		$data['supplier_list'] = $this->model_extension_feed_nix->supplierList();
		$data['supplier_id'] = (isset($this->request->post['supplier_id'])) ? $this->request->post['supplier_id'] : 0;
		
		$data['user_token'] = $this->session->data['user_token']; // user_token need in js ajax
		
		// Breadcrumbps & Links
		$data['breadcrumbs'] = $this->stde->breadcrumbs();

		$data['action'] = $this->stde->link('partImport');
		$data['cancel'] = $this->stde->link('cancel');

		$data['link_part_settings'] = $this->stde->link('index'); // A!
		$data['link_part_import'] = $this->stde->link('partImport');
		
		$data['header']			 = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer']			 = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/feed/nix_import', $data));
	}
	
	public function processingImportAjax() {
		$this->load->language('extension/feed/nix');

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			return $this->json([
				'status' => 'Error',
				'msg' => $this->language->get('error_request_method'),
				'errors' => ['warning' => $this->language->get('error_request_method')]
			]);
		}

		if (!$this->cron_mode && !$this->validateUserToken()) {
			return $this->json([
				'status' => 'Error',
				'msg' => $this->language->get('error_user_token'),
				'errors' => ['warning' => $this->language->get('error_user_token')]
			]);
		}

		if (!$this->cron_mode && !$this->user->hasPermission('modify', 'extension/feed/nix')) {
			return $this->json([
				'status' => 'Error',
				'msg' => $this->language->get('error_permission'),
				'errors' => ['warning' => $this->language->get('error_permission')]
			]);
		}

		$this->stdelog->write(2, 'processingImportAjax() is called');		
		$this->stdelog->write(3, $this->request->post, 'processingImportAjax() :: $this->request->post');
		$this->stdelog->write(3, $_FILES, 'processingImportAjax() :: $_FILES');
		$this->stdelog->write(3, $this->memory_limit, 'processingImportAjax() :: $this->memory_limit');
		
		$this->load->model('extension/feed/nix');
		$this->model_extension_feed_nix->install();
		$this->load->model('catalog/manufacturer');
		$this->load->model('localisation/language');

		if (!$this->isEnabled()) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode([
				'status' => 'Error',
				'msg' => $this->language->get('error_module_disabled'),
				'errors' => []
			]));
			return;
		}

		$this->languages = $data['languages'] = $this->stde->languages($this->model_localisation_language->getLanguages());

		$data['is_multilingual'] = (count($data['languages']) > 1) ? true : false;
		
		$data['config_language_id'] = $this->config->get('config_language_id');
				
		if (!defined('FILE_PATH_BASE')) {
			$nix_import_marker = session_id();

			if ($nix_import_marker === '') {
				$nix_import_marker = !empty($this->session->data['nix']['processing_start_time']) ? 'cron-' . (int)$this->session->data['nix']['processing_start_time'] : 'manual-' . substr(sha1(uniqid('', true)), 0, 12);
			}

			$nix_import_marker = preg_replace('/[^a-zA-Z0-9_-]/', '', $nix_import_marker);
			define('FILE_PATH_BASE', DIR_CACHE . 'nix-import-' . $nix_import_marker . '-');
		}
		
		$json = [
			'status' => 'Error',
			'msg' => $this->language->get('error_warning'),
		];

		$result = ['status' => 'Finish'];
		
		if (!$this->validateImport()) {
			$json['errors'] = $this->errors;
			
			goto nix_processing_end;
		}		
			
		$this->supplier = $this->model_extension_feed_nix->supplierGet($this->request->post['supplier_id']);
		$this->correlation = [];
		
		// Stores - for OC 3 SEO URL
		$this->load->model('setting/store');

		$this->stores[] = array(
			'store_id' => 0,
			'name'     => $this->language->get('text_default')
		);

		$stores = $this->model_setting_store->getStores();

		foreach ($stores as $store) {
			$this->stores[] = array(
				'store_id' => $store['store_id'],
				'name'     => $store['name']
			);
		}
		
		// XML tags
		$tag_name = $this->supplier['tags']['name'] ?? 'name';
		$tag_model = $this->supplier['tags']['model'] ?? '';	
		$tag_sku = $this->supplier['tags']['sku'] ?? '';
		
		// SESSION DATA
		$this->session->data['nix']['products_processed_in_this_request'] = 0;
		
		if (isset($this->request->post['nix_new_submit'])) {
			$this->session->data['nix']['processing_warnings'] = [];
			$this->session->data['nix']['last_product_item_was'] = false;
			$this->session->data['nix']['products_processed'] = 0;
			$this->session->data['nix']['last_product_id'] = 0;
		} else {
			$this->stdelog->write(3, 'processingImportAjax() :: OLD SESSION');
		}
		
		// check if fields exist
		$this->session->data['nix']['exist_field_meta_h1'] = $this->model_extension_feed_nix->helperExistFieldMetaH1(); // ocStore
		$this->session->data['nix']['exist_field_h1'] = $this->model_extension_feed_nix->helperExistFieldH1(); // OpenCart + My Tag H1
		$this->session->data['nix']['exist_field_main_category'] = $this->model_extension_feed_nix->helperExistFieldMainCategory(); // product_to_category.main_category
		$this->session->data['nix']['exist_field_product_main_category_id'] = $this->model_extension_feed_nix->helperExistFieldProductMainCategoryId(); // ocStore product.main_category_id
		$this->session->data['nix']['exist_table_manufacturer_description'] = $this->model_extension_feed_nix->helperExistTableManufacturerDescription(); // ocStore has
		
		$this->session->data['nix']['markup'] = $this->model_extension_feed_nix->supplierMarkup($this->request->post['supplier_id']);
		
		$this->stdelog->write(3, $this->session->data, 'processingImportAjax() :: $this->session->data');
		
		if (!function_exists('simplexml_load_file')) {
			$json['msg'] = $this->language->get('error_simplexml_missing');
			$json['errors']['simplexml'] = $this->language->get('error_simplexml_missing');
			$this->stdelog->write(1, 'processingImportAjax() :: PHP SimpleXML extension is missing');
			goto nix_processing_end;
		}
		
		// A! Note-4
		// In first request we save file in the filesystem
		// In loopQueries() we not send files again!
		
		// A! Note-4:A	
		if (isset($this->request->post['nix_new_submit'])) {
			if (!empty($this->request->post['nix_cron_submit'])) {
				$cron_file_result = $this->saveCronXmlFiles();
				if (!$cron_file_result['success']) {
					$json['msg'] = $cron_file_result['error'];
					$json['errors']['cron'] = $cron_file_result['error'];
					goto nix_processing_end;
				}
			} else {
				foreach ($this->languages as $language) {
					if (!empty($_FILES['xmlfile']['tmp_name'][$language['language_id']]) && is_uploaded_file($_FILES['xmlfile']['tmp_name'][$language['language_id']])) {
						
						$this->stdelog->write(4, FILE_PATH_BASE . $language['language_id'] . '.xml', 'processingImportAjax() :: try to record file');
						
						$uploaded_content = file_get_contents($_FILES['xmlfile']['tmp_name'][$language['language_id']]);
						$write_result = ($uploaded_content !== false) ? file_put_contents(FILE_PATH_BASE . $language['language_id'] . '.xml', $uploaded_content) : false;
						$this->session->data['nix']['file_names'][(int)$language['language_id']] = basename((string)($_FILES['xmlfile']['name'][$language['language_id']] ?? 'import.xml'));
						
						if ($write_result === false || !is_file(FILE_PATH_BASE . $language['language_id'] . '.xml')) {
							$json['msg'] = sprintf($this->language->get('error_file_write'), FILE_PATH_BASE . $language['language_id'] . '.xml');
							$json['errors']['xmlfile'][(int)$language['language_id']] = $json['msg'];
							$this->stdelog->write(1, 'processingImportAjax() :: ERROR - cannot write file ' . FILE_PATH_BASE . $language['language_id'] . '.xml');
							goto nix_processing_end;
						} else {
							$this->stdelog->write(3, 'processingImportAjax() :: File writed ' . FILE_PATH_BASE . $language['language_id'] . '.xml');
						}			
					}
				}
			}
			if (!$this->cron_mode && empty($this->request->post['nix_preview_only']) && !empty($this->request->post['nix_apply_confirmed'])) {
				$expected_token = (string)($this->session->data['nix_safe_preview_token'] ?? '');
				$current_token = $this->buildSafeImportToken();
				$posted_token = (string)($this->request->post['nix_preview_token'] ?? '');
				$preview_time = (int)($this->session->data['nix_safe_preview_time'] ?? 0);
				$preview_expired = ($preview_time < 1 || (time() - $preview_time) > 1800);

				if ($expected_token === '' || $posted_token === '' || $preview_expired || !hash_equals($expected_token, $posted_token) || !hash_equals($expected_token, $current_token)) {
					$json['msg'] = $preview_expired ? $this->language->get('error_preview_expired') : $this->language->get('error_preview_token');
					$json['errors']['preview_token'] = $json['msg'];
					goto nix_processing_end;
				}
			}
		}	else {
			if (!is_file(FILE_PATH_BASE . $this->request->post['language_id'] . '.xml')) {
				$json['msg'] = $this->language->get('error_file_main_not_saved');
				$json['errors']['main_file'] = $this->language->get('error_file_main_not_saved');

				$this->stdelog->write(1, 'processingImportAjax() :: error_file_main_not_saved GOTO nix_processing_end');				
				goto nix_processing_end;
			}
		} 		
		
		foreach ($this->languages as $language) {
			$language_id = (int)$language['language_id'];
			$xml_file = FILE_PATH_BASE . $language_id . '.xml';

			if (is_file($xml_file)) {
				$parsed = $this->parseImportFile($xml_file, $language_id);

				if (!$parsed['success']) {
					$this->stdelog->write(1, 'processingImportAjax() :: import parse error in ' . $xml_file . ': ' . $parsed['error']);

					if ($language_id === (int)$this->request->post['language_id']) {
						$json['msg'] = $parsed['error'];
						$json['errors']['xmlfile'][$language_id] = $parsed['error'];
						goto nix_processing_end;
					}

					continue;
				}

				$this->xml[$language_id] = $parsed['xml'];
			}
		}

		$main_language_id = (int)$this->request->post['language_id'];

		if (empty($this->xml[$main_language_id]) || false === $this->xml[$main_language_id]) {
			$json['msg'] = $this->language->get('error_import_fatal');
			$json['errors']['main_file'] = $this->language->get('error_import_fatal');
			$this->stdelog->write(1, 'processingImportAjax() :: main XML is missing or invalid');
			goto nix_processing_end;
		}
		
		// Link product data in multiple languages by offer_id
		$this->helperPrepareOffers();


		if (!empty($this->request->post['nix_preview_only'])) {
			$preview = $this->buildImportPreview($this->offers_prepared[$main_language_id] ?? []);
			$preview_token = $this->buildSafeImportToken();
			$this->session->data['nix_safe_preview_token'] = $preview_token;
			$this->session->data['nix_safe_preview_time'] = time();
			$json = [
				'status' => 'Preview',
				'msg' => $this->language->get('text_preview_ready'),
				'statistics' => $this->formatPreviewStatistics($preview),
				'preview_html' => $this->renderPreviewHtml($preview),
				'preview_token' => $preview_token,
				'warnings' => false
			];
			$this->cleanUp();
			return $this->json($json);
		}

		// TODO...
		// Если опция удаления товара включена
		
		// Удалять только при первом запросе
		if (isset($this->request->post['delete_all']) && isset($this->request->post['nix_new_submit']) && empty($this->request->post['nix_price_stock_only'])) {
			$this->model_extension_feed_nix->clearAll();
		}


		// Check required tags of products 
		// 
		// A! Note-1:A
		// Not all vendors have MODEL in XML price
		// Some from them use SKU as the primary product code...

		$main_xml = $this->xml[$main_language_id];
		$main_offers = $main_xml->xpath('shop/offers/offer') ?: [];

		if (!$main_offers) {
			$json['errors']['offers'] = $this->language->get('error_import_no_tags');
			$this->stdelog->write(1, 'processingImportAjax() :: no offer nodes in main XML');
			goto nix_processing_end;
		}

		$first_offer = $main_offers[0];
		$this->stdelog->write(4, $first_offer, 'processingImportAjax() :: first main offer');

		if (isset($first_offer->$tag_model)) {
			$test = (string)$first_offer->$tag_model;
		} elseif (isset($first_offer->$tag_sku)) {
			$test = (string)$first_offer->$tag_sku;
		} else {
			$this->stdelog->write(4, 'processingImportAjax() :: missing model/sku tags in first offer');
			$json['errors']['tags'] = $this->language->get('error_import_no_tags');
			$this->stdelog->write(1, 'processingImportAjax() :: error_import_no_tags');
			goto nix_processing_end;
		}

		// Если есть данные о категориях
		if (isset($main_xml->shop->categories) && empty($this->request->post['nix_price_stock_only'])) {
			//$result = $this->recordCategory($main_xml->shop->categories);
			$result = $this->recordCategory($main_xml->xpath('shop/categories/category'));
			
			if ('Error' == $result['status']) {
				$json['errors'] = $result['errors'];
			}
		}

		// Если есть данные о товаре
		if (isset($main_xml->shop->offers)) {					
			$result = $this->recordProduct($this->offers_prepared[$main_language_id] ?? []);
			
			$this->stdelog->write(3, $result, 'processingImportAjax() :: has $this->recordProduct() $result');
			
			if ('Error' == $result['status']) {
				$json['errors'] = $result['errors'];
			} else {
				$json['last_product_id'] = $this->session->data['nix']['last_product_id'];
				
				$json['statistics'] = sprintf($this->language->get('statistics'), $this->session->data['nix']['products_processed']);
				
				$json['statistics_console'] = sprintf(
					$this->language->get('statistics_console'), 
					$this->session->data['nix']['products_processed_in_this_request'],
					$this->session->data['nix']['products_processed']
				);
			}
		}
		
		nix_processing_end:
			
		if (isset($json['errors'])) {
			
			// Errors in the submit form and initital XML-file validation
			$this->stdelog->write(1, $json['errors'], 'processingImportAjax() :: $json["errors"]');
			
			$this->cleanUp();
			
		} elseif ('Finish' == $result['status']) {
			
			$json['status'] = 'Finish';
			$json['msg'] = $this->language->get('success_import');

			$json['warnings'] = false;
			unset($this->session->data['nix_safe_preview_token'], $this->session->data['nix_safe_preview_time']);

			if (isset($this->session->data['nix']['processing_warnings']) && count($this->session->data['nix']['processing_warnings']) > 0) {
				foreach ($this->session->data['nix']['processing_warnings'] as $value) {
					$json['warnings'][] = $value;
				}
			}

			$this->cleanUp();
			
		} else {
			
			$json['status'] = 'Continue';
			$json['msg'] = $this->language->get('continued_import');
			
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
	
	protected function cleanUp() {
		unset($this->session->data['nix']);
		
		if (!defined('FILE_PATH_BASE')) {
			return;
		}

		// Unlink XML Files
		foreach ($this->languages as $language) {
			if (is_file(FILE_PATH_BASE . $language['language_id'] . '.xml')) {
				unlink(FILE_PATH_BASE . $language['language_id'] . '.xml');
			}
		}
	}
	
	protected function unlinkXMLFiles() {
		if (!defined('FILE_PATH_BASE')) {
			return;
		}

		foreach ($this->languages as $language) {
			if (is_file(FILE_PATH_BASE . $language['language_id'] . '.xml')) {
				unlink(FILE_PATH_BASE . $language['language_id'] . '.xml');
			}
		}
	}

	protected function validateSettings() {
		if (!$this->validateUserToken()) {
			$this->errors['warning'] = $this->language->get('error_user_token');
		}

		if (!$this->user->hasPermission('modify', 'extension/feed/nix')) {
			$this->errors['warning'] = $this->language->get('error_permission');
		}
		
		// If are any errors : common warning
		if ($this->errors && !isset($this->errors['warning'])) {
			$this->errors['warning'] = $this->language->get('error_warning');
		}

		return !$this->errors;
	}
	
	protected function validateImport() {
		$this->stdelog->write(2, 'validateImport() is called');
		$this->stdelog->write(3, $this->request->post, 'validateImport() :: $this->request->post');
		
		if (!$this->cron_mode && !$this->user->hasPermission('modify', 'extension/feed/nix')) {
			$this->errors['warning'] = $this->language->get('error_permission');
		}

		$supplier_id = $this->request->post['supplier_id'] ?? '*';
		$language_id = $this->request->post['language_id'] ?? '*';

		if ($supplier_id === '*' || (int)$supplier_id < 1) {
			$this->errors['supplier_id'] = $this->language->get('error_supplier');
		}
		
		if ($language_id === '*' || (int)$language_id < 1) {
			$this->errors['language_id'] = $this->language->get('error_language');
		}

		if (isset($this->request->post['nix_new_submit']) && empty($this->request->post['nix_cron_submit'])) {
			if (!isset($_FILES['xmlfile']['tmp_name'][(int)$language_id]) || $_FILES['xmlfile']['tmp_name'][(int)$language_id] === '') {
				$this->errors['xmlfile'][(int)$language_id] = $this->language->get('error_file');
			} else {
				$file_error = $this->validateUploadedImportFile((int)$language_id);
				if ($file_error !== '') {
					$this->errors['xmlfile'][(int)$language_id] = $file_error;
				}
			}
		}
		
		if ($this->errors && !isset($this->errors['warning'])) {
			$this->errors['warning'] = $this->language->get('error_warning');
		}
		
		$this->stdelog->write(3, $this->errors, 'validateImport() :: $this->errors');

		return !$this->errors;
	}

	private function validateUploadedImportFile($language_id) {
		$file = $_FILES['xmlfile'] ?? [];
		$error = isset($file['error'][$language_id]) ? (int)$file['error'][$language_id] : UPLOAD_ERR_OK;

		if ($error !== UPLOAD_ERR_OK) {
			return sprintf($this->language->get('error_file_upload_code'), $error);
		}

		$tmp_name = (string)($file['tmp_name'][$language_id] ?? '');
		$name = (string)($file['name'][$language_id] ?? '');
		$size = isset($file['size'][$language_id]) ? (int)$file['size'][$language_id] : 0;

		if ($tmp_name === '' || !is_uploaded_file($tmp_name)) {
			return $this->language->get('error_file');
		}

		$extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
		$allowed = ['xml', 'yml', 'yaml', 'csv', 'txt', 'xlsx'];

		if (!in_array($extension, $allowed, true)) {
			return sprintf($this->language->get('error_file_extension'), implode(', ', $allowed));
		}

		$max_size = 50 * 1024 * 1024;
		if ($size > $max_size) {
			return sprintf($this->language->get('error_file_size'), '50 MB');
		}

		return '';
	}

	/*
	 * In category we didn't have error with different languages
	 */
	private function recordCategory($categories) {
		$result = [];
		
		$this->stdelog->write(2, 'recordCategory() is called');
		
		$this->stdelog->write(4, $categories, 'recordCategory() :: $categories');
					
		//Разбераем массив categories
		foreach ($categories as $node_index => $category) {
			$supplier_category_id = (int)$category['id'];
			
			$name	= trim($category);
			$google_product_category_id = $this->readCategoryGoogleProductCategoryId($category);
			
			$this->stdelog->write(4, $name, 'recordCategory() :: $name for category');
			
			//если parent отсутствует значит это главная категория
			$parent_attribute = $this->supplier['attributes']['parent_id'] ?? 'parent_id';
			$parent_attribute_value = isset($category[$parent_attribute]) ? (string)$category[$parent_attribute] : '';
			$this->stdelog->write(4, $parent_attribute_value, 'recordCategory() :: parent attribute value');
			
			if (isset($category[$parent_attribute]) === false) {
				$parent_id = 0;
				$top = 1;
			} else {
				$parent_id = $this->correlation[(int)$category[$parent_attribute]] ?? 0;
				$top = 0;
				
				$this->hierarchy[$supplier_category_id][] = (int)$category[$parent_attribute];
			}

			//проверяем существование категории
			// todo...
			// test by nix_suplier_id
			$test_category = $this->model_extension_feed_nix->getCategory($name);

			//Если категория не найдена то создаем новую категорию
			if ($test_category == []) {
				$category_description = $this->prepareCategoryDescription($node_index);
				
				// SEO URL For OC 3 
				$category_seo_url = [];

				foreach ($this->stores as $store) {
					foreach ($category_description as $language_id => $value) {
						$category_seo_url[$store['store_id']][$language_id] = $this->helperTranslitUniversal($value['name']);
					}
				}
				
				$data = [
					'nix_supplier_id'			 => $this->request->post['supplier_id'],
					'nix_supplier_category_id' => $supplier_category_id,
					'parent_id'						 => $parent_id,
					'top'									 => $top,
					'column'							 => 0,
					'sort_order'					 => 0,
					'status'							 => 1,
					'category_store'			 => [0],
					'category_description' => $category_description,
					'category_seo_url'		 => $category_seo_url,
					'google_product_category_id' => $google_product_category_id,
				];
				
				$this->stdelog->write(4, $data, 'model->recordCategory() :: $data');

				$category_id = $this->model_extension_feed_nix->addCategory($data);
				
				$this->stdelog->write(4, $category_id, 'model->recordCategory() :: $this->model_extension_feed_nix->addCategory return');

				$this->correlation[$supplier_category_id] = $category_id;
				$this->categories[$category_id] = $name;
				
			} else {
				$this->correlation[$supplier_category_id] = $test_category['category_id'];				
				$this->categories[$test_category['category_id']] = $name;

				if (isset($this->request->post['update_if_exist'])) {
					$category_description = $this->prepareCategoryDescription($node_index);
					
					// SEO URL For OC 3 
					$category_seo_url = [];

					foreach ($this->stores as $store) {
						foreach ($category_description as $language_id => $value) {
							$category_seo_url[$store['store_id']][$language_id] = $this->helperTranslitUniversal($value['name']);
						}
					}
					
					$data = [
						'nix_supplier_id'			 => $this->request->post['supplier_id'],
						'nix_supplier_category_id' => $supplier_category_id,
						'parent_id'						 => $parent_id,
						'top'									 => $top,
						'column'							 => 0,
						'sort_order'					 => 0,
						'status'							 => 1,
						'category_store'			 => [0],
						'category_description' => $category_description,
						'category_seo_url'		 => $category_seo_url,
						'google_product_category_id' => $google_product_category_id,
					];

					$this->model_extension_feed_nix->editCategory($test_category['category_id'], $data);
				}
			}
		}
		
		$this->stdelog->write(4, $this->correlation, 'recordCategory() :: $this->correlation');
		$this->stdelog->write(4, $this->categories, 'recordCategory() :: $this->categories');
		$this->stdelog->write(4, $this->hierarchy, 'recordCategory() :: $this->hierarchy');
		
		if (!isset($result['errors'])) {
			$result['status'] = 'OK';
		} else {
			$result['status'] = 'Error';
		}
		
		return $result;
	}
	
	/*
	 * In products we had errors with different languages when we were using $node_index
	 */
	private function recordProduct($offers) {
		$this->stdelog->write(2, 'recordProduct() is called');

		$result = [];
		
		$tag_name							 = $this->supplier['tags']['name'] ?? 'name';
		$tag_price_purchasing	 = $this->supplier['tags']['price_purchasing'] ?? '';
		$tag_price_rrp				 = $this->supplier['tags']['price_rrp'] ?? '';
		$tag_special_price = $this->supplier['tags']['special_price'] ?? '';
		$tag_currency			 = $this->supplier['tags']['currency'] ?? '';
		$tag_quantity					 = $this->supplier['tags']['quantity'] ?? '';
		$tag_date_available = $this->supplier['tags']['date_available'] ?? '';
		$tag_stock_status_id = $this->supplier['tags']['stock_status_id'] ?? '';
		$tag_status = $this->supplier['tags']['status'] ?? '';
		$tag_points				 = $this->supplier['tags']['points'] ?? '';
		$tag_images						 = $this->supplier['tags']['images'] ?? '';
		$tag_model						 = $this->supplier['tags']['model'] ?? '';
		$tag_sku							 = $this->supplier['tags']['sku'] ?? '';
		$tag_manufacturer_name = $this->supplier['tags']['manufacturer_name'] ?? '';
		$tag_description			 = $this->supplier['tags']['description'] ?? '';
		$tag_category					 = $this->supplier['tags']['category'] ?? '';
		$tag_attributes				 = $this->supplier['tags']['attributes'] ?? '';
		$tag_meta_h1				 = $this->supplier['tags']['meta_h1'] ?? '';
		$tag_meta_title			 = $this->supplier['tags']['meta_title'] ?? '';
		$tag_meta_description = $this->supplier['tags']['meta_description'] ?? '';
		$tag_meta_keyword		 = $this->supplier['tags']['meta_keyword'] ?? '';
		$tag_tag						 = $this->supplier['tags']['tag'] ?? '';
		$tag_upc						 = $this->supplier['tags']['upc'] ?? '';
		$tag_ean						 = $this->supplier['tags']['ean'] ?? '';
		$tag_jan						 = $this->supplier['tags']['jan'] ?? '';
		$tag_isbn					 = $this->supplier['tags']['isbn'] ?? '';
		$tag_mpn						 = $this->supplier['tags']['mpn'] ?? '';
		$tag_location			 = $this->supplier['tags']['location'] ?? '';
		$tag_minimum				 = $this->supplier['tags']['minimum'] ?? '';
		$tag_subtract			 = $this->supplier['tags']['subtract'] ?? '';
		$tag_stock_status_id = $this->supplier['tags']['stock_status_id'] ?? '';
		$tag_shipping			 = $this->supplier['tags']['shipping'] ?? '';
		$tag_tax_class_id	 = $this->supplier['tags']['tax_class_id'] ?? '';
		$tag_length				 = $this->supplier['tags']['length'] ?? '';
		$tag_width					 = $this->supplier['tags']['width'] ?? '';
		$tag_height				 = $this->supplier['tags']['height'] ?? '';
		$tag_length_class_id = $this->supplier['tags']['length_class_id'] ?? '';
		$tag_weight				 = $this->supplier['tags']['weight'] ?? '';
		$tag_weight_class_id = $this->supplier['tags']['weight_class_id'] ?? '';
		$tag_status				 = $this->supplier['tags']['status'] ?? '';
		$tag_sort_order		 = $this->supplier['tags']['sort_order'] ?? '';
		$tag_google_product_category_id = $this->supplier['tags']['google_product_category_id'] ?? '';

		$skip = false;
		
		if ($this->session->data['nix']['last_product_item_was']) {
			$skip = true;
		}
		
		foreach ($offers as $offer_id => $offer) {
			$this->stdelog->write(2, (string)$offer['id'], "\r\n\r\n\r\n>>>>>>>>>>>>>>>>>>>>>>>>\r\n" . 'recordProduct() :: NEW ITTERATION $offer with $offer["id"]');
			
			$this->stdelog->write(3, $offer_id, '$offer_id in main thread');
			
			if ($this->session->data['nix']['last_product_item_was'] == (string)$offer['id']) {
				$skip = false;
				continue;
			}
			
			if ($skip) {
				$this->stdelog->write(3, (string)$offer['id'], 'recordProduct() :: SKIP OFFER');
				continue;
			}
			
			// Check required tags & attributes in the XML
			if (!isset($offer['id'])) {
				$this->stdelog->write(1, $offer, 'recordProduct() :: absent attribute `id` for offer');
				$this->session->data['nix']['processing_warnings'][] = $this->language->get('warning_offer_without_id');
				
				continue;
			}
			
			if (!isset($offer->$tag_name)) {
				$this->stdelog->write(1, (string)$offer['id'], 'recordProduct() :: absent tag `' . $tag_name . '` for offer item ');
				$this->session->data['nix']['processing_warnings'][] = sprintf($this->language->get('warning_offer_missing_tag'), (string)$offer['id'], $tag_name);				
				continue;
			}
			

			/* 
			 * A! Note-2
			 * 
			 * Fatal error: Uncaught exception 'Exception' with message 'Serialization of 'SimpleXMLElement' is not allowed' in [no active file]:0 Stack trace: #0 {main} thrown in [no active file] on line 0
			 *					 
			 * https://www.php.net/manual/en/function.unserialize.php
			 * ... If you store such an object in $_SESSION, you will get a post-execution error ...
			 */	
			
			$this->session->data['nix']['last_product_item_was'] = (string)$offer['id']; // Convert to String! // A! Note-2
			
			$filter = [
//					'name' => trim($offer->$tag_name),
//					'model' => trim($offer->$tag_model),
//					'sku' => trim($offer->$tag_sku),
					'nix_supplier_id' => $this->request->post['supplier_id'],
					'nix_supplier_product_id' => $offer['id'],
				];
			
			$test_product = $this->model_extension_feed_nix->getProduct($filter);
			
			$this->stdelog->write(4, $test_product, 'recordProduct() :: $test_product');

			if (!empty($this->request->post['nix_price_stock_only'])) {
				if ($test_product !== []) {
					$this->updateProductStock($offer, $test_product, $filter);
					$this->session->data['nix']['products_processed']++;
					$this->session->data['nix']['products_processed_in_this_request']++;
					$this->session->data['nix']['last_product_id'] = $test_product['product_id'];
				} else {
					$this->stdelog->write(3, (string)$offer['id'], 'recordProduct() :: PRICE/AVAILABILITY/STOCK MODE -- PRODUCT NOT FOUND, SKIP');
				}

				if (!$this->helperHaveTime()) {
					$result['status'] = 'Continue';
					break;
				}

				continue;
			}
			
			/*
			 * Обновление остатков
			 * Это другой XML-файл, в котором меньше тегов
			 * 
			 * Q?
			 * Не проще ли просто поставить какой-то флаг для этого??
			 * Или отдельную вкладку, где нету возможности добавлять разные языковые файлы?
			 */
			if (!isset($offer->$tag_manufacturer_name) && !isset($offer->$tag_attributes) && $test_product !== []) {
				
				$this->stdelog->write(
					3, [
						'product_id' => $test_product['product_id'],
						'$offer["id]"' => (string)$offer['id']
					], 'recordProduct() UPDATE STOCK for'
				);
				
				$this->updateProductStock($offer, $test_product, $filter);
				
				$this->stdelog->write(2, 'recordProduct() :: $this->updateProductStock() called. + Continue');
				
				continue;
			}
			
			
			
			
			/*
			 * Полноценный импорт
			 */
			
			// Если товара в базе нету, но и тегов в файле нету, то это и не импорт, и не обновление остатков.
			if ($test_product == [] 
				&& !isset($offer->$tag_manufacturer_name) 
				&& !isset($offer->$tag_attributes)
				&& (!isset($offer->$tag_model) && !isset($offer->$tag_sku))
			) {
				$this->stdelog->write(1, $offer, 'recordProduct() :: absent tags reuired for offer' . (string)$offer['id']);
				$this->session->data['nix']['processing_warnings'][] = sprintf($this->language->get('warning_offer_missing_required_tags'), (string)$offer['id']);
				
				$this->stdelog->write(2, 'recordProduct() :: NO XML-attributes. Break itteration');
				
				break;
			}
			
			// получаем производителя
			$manufacturer_id = 0;
			
			if (isset($offer->$tag_manufacturer_name)) {
				$manufacturer_id = $this->recordManufacturer((string)$offer->$tag_manufacturer_name);
			}

			$available = 'true';
			
			if (isset($offer['available'])) {
				$available = $offer['available'];
			}
			
			if ($available == 'true') {
				$remains = 1;
			} else {
				$remains = 0;
			}
			
			if ($tag_quantity && isset($offer->$tag_quantity)) {
				$remains = (int)$offer->$tag_quantity;
			}
			
			$price_purchasing = $this->normalizeImportPrice($this->readOfferValue($offer, $tag_price_purchasing, 0), $offer, $tag_currency);
			$price_rrp = $this->normalizeImportPrice($this->readOfferValue($offer, $tag_price_rrp, 0), $offer, $tag_currency);
			
			$price = 0;
			
			if ($this->session->data['nix']['markup'] && $price_purchasing) {
				$price = $price_purchasing + ($price_purchasing * ($this->session->data['nix']['markup'] / 100));
			} else {
				$price = $price_rrp;
			}
			
			$status = 1;
			
			if (0 == $price || 0 == $remains) {
				$status = 0;
			}

			if ($this->readOfferValue($offer, $tag_status, '') !== '') {
				$status = $this->readOfferBool($offer, $tag_status, $status);
			}

			$special_price_data = $this->prepareSpecialPriceData($offer, $price);
			
			$model = $this->readOfferValue($offer, $tag_model, '');
			$sku = $this->readOfferValue($offer, $tag_sku, '');
			
			// A! Note-1:B
			if ('' == $model) {
				$model = $sku;
			}
			
			$product_card_fields = [
				'upc' => $this->readOfferValue($offer, $tag_upc, ''),
				'ean' => $this->readOfferValue($offer, $tag_ean, ''),
				'jan' => $this->readOfferValue($offer, $tag_jan, ''),
				'isbn' => $this->readOfferValue($offer, $tag_isbn, ''),
				'mpn' => $this->readOfferValue($offer, $tag_mpn, ''),
				'location' => $this->readOfferValue($offer, $tag_location, ''),
				'date_available' => $this->normalizeImportDate($this->readOfferValue($offer, $tag_date_available, ''), date('Y-m-d')),
				'points' => $this->readOfferInt($offer, $tag_points, 0),
				'minimum' => max(1, $this->readOfferInt($offer, $tag_minimum, 1)),
				'subtract' => $this->readOfferBool($offer, $tag_subtract, 0),
				'stock_status_id' => $this->readOfferInt($offer, $tag_stock_status_id, 5),
				'shipping' => $this->readOfferBool($offer, $tag_shipping, 1),
				'tax_class_id' => $this->readOfferInt($offer, $tag_tax_class_id, 0),
				'length' => $this->readOfferFloat($offer, $tag_length, 0),
				'width' => $this->readOfferFloat($offer, $tag_width, 0),
				'height' => $this->readOfferFloat($offer, $tag_height, 0),
				'length_class_id' => $this->readOfferInt($offer, $tag_length_class_id, 0),
				'weight' => $this->readOfferFloat($offer, $tag_weight, 0),
				'weight_class_id' => $this->readOfferInt($offer, $tag_weight_class_id, 1),
				'sort_order' => $this->readOfferInt($offer, $tag_sort_order, 0),
			];
			
			$google_product_category_id = $this->readOfferValue($offer, $tag_google_product_category_id, '');
			
			if ($this->readOfferValue($offer, $tag_status, '') !== '') {
				$status = $this->readOfferBool($offer, $tag_status, $status);
				if (0 == $price || 0 == $remains) {
					$status = 0;
				}
			}
			
			$description = '';
			
			if (isset($offer->$tag_description)) {
				$description = $offer->$tag_description;
			}
						
			$categories = [];
			$category_name_for_images = 'uncategorized';
			
			//$parent_id = $this->correlation[(int)$category[$parent_attribute]] ?? 0;
			if (isset($offer->$tag_category) && isset($this->correlation[(int)$offer->$tag_category])) {		
				$categories[0] = $main_category_id = $this->correlation[(int)$offer->$tag_category];
				
				$category_name_for_images = $this->categories[$main_category_id] ?? 'uncategorized';
				
				// todo...
				// Каждая из родительской категории может иметь еще одну родительскую категорию...
				// А в моем случае рассматирвается только 1 уровень вложенности...
				
				if (isset($this->hierarchy[(int)$offer->$tag_category])) {
					foreach ($this->hierarchy[(int)$offer->$tag_category] as $parent_id) {
						if (isset($this->correlation[$parent_id])) {
							$categories[] = $this->correlation[$parent_id];
						}
					}
				}
			} else {
				$category_value = ($tag_category && isset($offer->$tag_category)) ? (string)$offer->$tag_category : '';
				$this->stdelog->write(4, $category_value, 'recordProduct() :: category tag is absent or not mapped');
			}
			
			$this->stdelog->write(4, $categories, 'recordProduct() :: $categories');
			
			// Images
			$image = '';
			
			$product_images = [];

			if (isset($offer->$tag_images)) {
				$images = (array) $offer->$tag_images;
				
				$image = array_shift($images);

				$image = $this->helperGetImage($image, 0, 'products', $category_name_for_images);
				
				if (isset($images) && count($images) > 0) {
					foreach ($images as $i => $item) {
						$product_images[$i] = [
							'image' => $this->helperGetImage((string)$item, ($i + 1), 'products', $category_name_for_images), 'sort_order' => $i,
						];
						
					}
				}
			}
					
			
			// Check if $offer_id is present in all uploaded xml-files
			foreach ($this->offers_prepared as $language_id => $offers) {
				if (!isset($offers[$offer_id])) {
					$this->stdelog->write(1, $language_id, 'offer_id is absent for language');

					// Write to main log also
					$this->log->write('NIX:: ERROR -- offer_id `' . $offer_id . '` is absent for language ' . $language_id);
				}
			}

			//Атрибуты			
			$product_attribute = [];
			
			if (isset($offer->$tag_attributes)) {
				$this->stdelog->write(3, 'recordProduct() :: going to call $this->prepareAttributes()');
				
				$product_attribute = $this->prepareAttributes($offer_id);
			}

			if ($test_product == []) {
				
				$this->stdelog->write(3, 'recordProduct() :: going to call $this->prepareProductDescription()');
				
				$product_description = $this->prepareProductDescription($offer_id);
				
				// SEO URL For OC 3 
				$product_seo_url = [];
				
				foreach ($this->stores as $store) {
					foreach ($product_description as $language_id => $value) {
						$product_seo_url[$store['store_id']][$language_id] = $this->helperTranslitUniversal($value['name']);
					}
				}
				
				//создаем товар
				$data = [
					'nix_supplier_id'					 => $this->request->post['supplier_id'],
					'nix_supplier_product_id'	 => $offer['id'],
					'nix_supplier_price' => $price_purchasing,
					'image'										 => $image,
					'product_image'						 => $product_images,
					'model'										 => $model,
					'sku'											 => $sku,
					'upc'											 => $product_card_fields['upc'],
					'ean'											 => $product_card_fields['ean'],
					'jan'											 => $product_card_fields['jan'],
					'isbn'										 => $product_card_fields['isbn'],
					'mpn'											 => $product_card_fields['mpn'],
					'location'								 => $product_card_fields['location'],
					'quantity'								 => $remains,
					'minimum'									 => $product_card_fields['minimum'],
					'subtract'								 => $product_card_fields['subtract'],
					'stock_status_id'					 => $product_card_fields['stock_status_id'],
					'date_available'					 => $product_card_fields['date_available'],
					'manufacturer_id'					 => $manufacturer_id,
					'shipping'								 => $product_card_fields['shipping'],
					'price'										 => $price,
					'points'									 => $product_card_fields['points'],
					'weight'									 => $product_card_fields['weight'],
					'weight_class_id'					 => $product_card_fields['weight_class_id'],
					'length'									 => $product_card_fields['length'],
					'width'										 => $product_card_fields['width'],
					'height'									 => $product_card_fields['height'],
					'length_class_id'					 => $product_card_fields['length_class_id'],
					'status'									 => $status,
					'tax_class_id'						 => $product_card_fields['tax_class_id'],
					'sort_order'							 => $product_card_fields['sort_order'],
					'product_category'				 => $categories,
					'main_category_id'				 => $main_category_id ?? 0,
					'google_product_category_id' => $google_product_category_id,
					'product_attribute'				 => $product_attribute,
					'product_description'			 => $product_description,
					'product_seo_url'					 => $product_seo_url,
				];

				if (!empty($special_price_data['has'])) {
					$data['product_special'] = $special_price_data['product_special'];
				}

				$this->stdelog->write(4, $data, 'recordProduct() :: NEW PRODUCT DATA');
				
				$product_id = $this->model_extension_feed_nix->addProduct($data);
				
				$this->stdelog->write(3, $product_id, 'recordProduct() ADD :: $product_id');
				
			} else {
				// обновляем товар
				// Q?
				// А нужно ли обновлять товар???
				// А если человек уже прописал мета-теги?
				// А если поставщик изменил название товара?
				// А если в магазине назание уже отредактировано и так надо?
				// А если фотки случайно удалил?
				
				$this->stdelog->write(2, $this->request->post['update_if_exist'] ?? 0, 'recordProduct() :: $this->request->post["update_if_exist"]');
				
				if (isset($this->request->post['update_if_exist'])) {
					$this->stdelog->write(3, 'recordProduct() :: going to call $this->prepareProductDescription()');
					
					$product_description = $this->prepareProductDescription($offer_id);
					
					// SEO URL For OC 3 
					$product_seo_url = [];

					foreach ($this->stores as $store) {
						foreach ($product_description as $language_id => $value) {
							$product_seo_url[$store['store_id']][$language_id] = $this->helperTranslitUniversal($value['name']);
						}
					}
					
					$data = [
						'nix_supplier_price' => $price_purchasing,
						'image'								 => $image,
						'product_image'				 => $product_images,
						'model'								 => $model,
						'sku'									 => $sku,
						'upc'									 => $product_card_fields['upc'],
						'ean'									 => $product_card_fields['ean'],
						'jan'									 => $product_card_fields['jan'],
						'isbn'								 => $product_card_fields['isbn'],
						'mpn'									 => $product_card_fields['mpn'],
						'location'						 => $product_card_fields['location'],
						'quantity'						 => $remains,
						'minimum'							 => $product_card_fields['minimum'],
						'subtract'						 => $product_card_fields['subtract'],
						'stock_status_id'			 => $product_card_fields['stock_status_id'],
						'date_available'			 => $product_card_fields['date_available'],
						'manufacturer_id'			 => $manufacturer_id,
						'shipping'						 => $product_card_fields['shipping'],
						'price'								 => $price,
						'points'							 => $product_card_fields['points'],
						'weight'							 => $product_card_fields['weight'],
						'weight_class_id'			 => $product_card_fields['weight_class_id'],
						'length'							 => $product_card_fields['length'],
						'width'								 => $product_card_fields['width'],
						'height'							 => $product_card_fields['height'],
						'length_class_id'			 => $product_card_fields['length_class_id'],
						'status'							 => $status,
						'tax_class_id'				 => $product_card_fields['tax_class_id'],
						'sort_order'					 => $product_card_fields['sort_order'],
						'product_category'		 => $categories,
						'main_category_id'		 => $main_category_id ?? 0,
						'google_product_category_id' => $google_product_category_id,
						'product_attribute'		 => $product_attribute,
						'product_description'	 => $product_description,
						'product_seo_url'			 => $product_seo_url,
					];

					if (!isset($offer->$tag_images)) {
						unset($data['image'], $data['product_image']);
					}

					if (!isset($offer->$tag_attributes)) {
						unset($data['product_attribute']);
					}

					if (!isset($offer->$tag_category) || empty($categories)) {
						unset($data['product_category'], $data['main_category_id']);
					}

					if (!empty($special_price_data['has'])) {
						$data['product_special'] = $special_price_data['product_special'];
					}

					$this->stdelog->write(3, $data, 'recordProduct() :: PRODUCT UPDATE DATA');

					$this->model_extension_feed_nix->editProduct($test_product['product_id'], $data);
				} else {
					$this->stdelog->write(3, 'recordProduct() :: PRODUCT ALREADY EXIST -- SKIP');
				}
			}
			
			// Statistics
			$this->session->data['nix']['products_processed']++;	
			$this->session->data['nix']['products_processed_in_this_request']++;			
			$this->session->data['nix']['last_product_id'] = $product_id ?? $test_product['product_id'];
			
			if (!$this->helperHaveTime()) {
				$result['status'] = 'Continue';
				
				$this->stdelog->write(2, 'recordProduct() :: time is running out. Break operation');
				
				break;
			}
			
			$memory_used = memory_get_usage(true);
			$memory_diff = $this->memory_limit - $memory_used;

			$this->stdelog->write(3, $memory_diff, 'recordProduct() :: $memory_diff');
			$this->stdelog->write(3, $memory_used, 'recordProduct() :: $memory_used');
			$this->stdelog->write(3, $memory_diff, 'recordProduct() :: $memory_diff');

			if ($memory_diff < 33554432) {
				$result['status'] = 'Continue';

				$this->stdelog->write(2, $memory_used, 'recordProduct() :: Memory limits. Break operation');

				break;
			}
		}
		
		if (!isset($result['status'])) {
			$result['status'] = 'Finish';
		}
		
		$this->stdelog->write(4, $result, 'recordProduct() ::return $result');
		
		return $result;
	}
	


	private function buildSafeImportToken() {
		$payload = [
			'supplier_id' => (int)($this->request->post['supplier_id'] ?? 0),
			'language_id' => (int)($this->request->post['language_id'] ?? 0),
			'update_if_exist' => !empty($this->request->post['update_if_exist']) ? 1 : 0,
			'delete_all' => !empty($this->request->post['delete_all']) ? 1 : 0,
			'copy_description' => !empty($this->request->post['copy_description']) ? 1 : 0,
			'copy_attributes' => !empty($this->request->post['copy_attributes']) ? 1 : 0,
			'price_stock_only' => !empty($this->request->post['nix_price_stock_only']) ? 1 : 0,
			'files' => []
		];

		foreach ($this->languages as $language) {
			$language_id = (int)$language['language_id'];
			$file = FILE_PATH_BASE . $language_id . '.xml';
			if (is_file($file)) {
				$payload['files'][$language_id] = hash_file('sha256', $file);
			}
		}

		ksort($payload['files']);
		$secret = (string)$this->config->get('config_encryption');
		if ($secret === '') {
			$secret = (string)$this->config->get('feed_nix_cron_token');
		}
		if ($secret === '') {
			$secret = session_id() ?: 'importxml-clean';
		}

		return hash_hmac('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $secret);
	}

	private function buildImportPreview($offers) {
		$report = [
			'total' => 0,
			'existing' => 0,
			'changed_existing' => 0,
			'will_update' => 0,
			'new_products' => 0,
			'not_found' => 0,
			'unchanged' => 0,
			'price_changed' => 0,
			'quantity_changed' => 0,
			'status_changed' => 0,
			'stock_status_changed' => 0,
			'supplier_price_changed' => 0,
			'special_price_changed' => 0,
			'missing_from_supplier' => 0,
			'rows' => [],
			'new_rows' => [],
			'not_found_rows' => [],
			'missing_rows' => [],
		];

		$seen_supplier_product_ids = [];
		$price_stock_only = !empty($this->request->post['nix_price_stock_only']);
		$update_allowed = $price_stock_only || isset($this->request->post['update_if_exist']);

		foreach ($offers as $offer_id => $offer) {
			$offer_id = (string)$offer_id;
			if ($offer_id === '') {
				continue;
			}

			$report['total']++;
			$seen_supplier_product_ids[$offer_id] = true;

			$filter = [
				'nix_supplier_id' => $this->request->post['supplier_id'],
				'nix_supplier_product_id' => $offer_id,
			];
			$test_product = $this->model_extension_feed_nix->getProduct($filter);
			$incoming = $this->getOfferImportSnapshot($offer, $test_product);

			if (!$test_product) {
				$report['not_found']++;
				$row = [
					'offer_id' => $offer_id,
					'product_id' => 0,
					'name' => $incoming['name'],
					'model' => $incoming['model'],
					'sku' => $incoming['sku'],
					'price_old' => '',
					'price_new' => $incoming['price'],
					'quantity_old' => '',
					'quantity_new' => $incoming['quantity'],
					'status_old' => '',
					'status_new' => $incoming['status'],
					'action' => $price_stock_only ? $this->language->get('text_preview_action_skip_not_found') : $this->language->get('text_preview_action_create'),
				];

				if ($price_stock_only) {
					$this->appendPreviewRow($report['not_found_rows'], $row);
				} else {
					$report['new_products']++;
					$this->appendPreviewRow($report['new_rows'], $row);
				}

				continue;
			}

			$report['existing']++;
			$changes = [];
			$this->comparePreviewValue($changes, 'price', (float)$test_product['price'], (float)$incoming['price'], 'price_changed', $report);
			$this->comparePreviewValue($changes, 'quantity', (int)$test_product['quantity'], (int)$incoming['quantity'], 'quantity_changed', $report);
			$this->comparePreviewValue($changes, 'status', (int)$test_product['status'], (int)$incoming['status'], 'status_changed', $report);
			$this->comparePreviewValue($changes, 'stock_status_id', (int)$test_product['stock_status_id'], (int)$incoming['stock_status_id'], 'stock_status_changed', $report);
			$this->comparePreviewValue($changes, 'nix_supplier_price', (float)($test_product['nix_supplier_price'] ?? 0), (float)$incoming['supplier_price'], 'supplier_price_changed', $report);

			if ($incoming['special_price_has']) {
				$current_special = (float)$this->model_extension_feed_nix->getProductSpecialPrice((int)$test_product['product_id']);
				$this->comparePreviewValue($changes, 'special_price', $current_special, (float)$incoming['special_price'], 'special_price_changed', $report);
			}

			if ($changes) {
				$report['changed_existing']++;
				if ($update_allowed) {
					$report['will_update']++;
				}

				$this->appendPreviewRow($report['rows'], [
					'offer_id' => $offer_id,
					'product_id' => (int)$test_product['product_id'],
					'name' => $incoming['name'] ?: ($test_product['name'] ?? ''),
					'model' => $incoming['model'] ?: ($test_product['model'] ?? ''),
					'sku' => $incoming['sku'] ?: ($test_product['sku'] ?? ''),
					'price_old' => (float)$test_product['price'],
					'price_new' => $incoming['price'],
					'quantity_old' => (int)$test_product['quantity'],
					'quantity_new' => $incoming['quantity'],
					'status_old' => (int)$test_product['status'],
					'status_new' => $incoming['status'],
					'action' => $update_allowed ? $this->language->get('text_preview_action_update') : $this->language->get('text_preview_action_skip_update_disabled'),
				]);
			} else {
				$report['unchanged']++;
			}
		}

		$supplier_products = $this->model_extension_feed_nix->getSupplierProducts((int)$this->request->post['supplier_id']);
		foreach ($supplier_products as $product) {
			$supplier_product_id = (string)$product['nix_supplier_product_id'];
			if ($supplier_product_id !== '' && !isset($seen_supplier_product_ids[$supplier_product_id])) {
				$report['missing_from_supplier']++;
				$this->appendPreviewRow($report['missing_rows'], [
					'offer_id' => $supplier_product_id,
					'product_id' => (int)$product['product_id'],
					'name' => $product['name'] ?? '',
					'model' => $product['model'] ?? '',
					'sku' => $product['sku'] ?? '',
					'price_old' => (float)$product['price'],
					'price_new' => '',
					'quantity_old' => (int)$product['quantity'],
					'quantity_new' => '',
					'status_old' => (int)$product['status'],
					'status_new' => '',
					'action' => $this->language->get('text_preview_action_missing'),
				]);
			}
		}

		return $report;
	}

	private function appendPreviewRow(&$rows, array $row) {
		if (count($rows) < 200) {
			$rows[] = $row;
		}
	}

	private function comparePreviewValue(&$changes, $field, $old, $new, $counter, &$report) {
		if (is_float($old) || is_float($new)) {
			$equal = abs((float)$old - (float)$new) < 0.0001;
		} else {
			$equal = ((string)$old === (string)$new);
		}

		if (!$equal) {
			$changes[$field] = ['old' => $old, 'new' => $new];
			$report[$counter]++;
		}
	}

	private function formatPreviewStatistics(array $preview) {
		return sprintf($this->language->get('text_preview_statistics'), (int)$preview['total'], (int)$preview['will_update'], (int)$preview['new_products'], (int)$preview['not_found'], (int)$preview['missing_from_supplier'], (int)$preview['price_changed'], (int)$preview['quantity_changed'], (int)$preview['status_changed'], (int)$preview['supplier_price_changed'], (int)$preview['special_price_changed']);
	}

	private function renderPreviewHtml(array $preview) {
		$html = '<div class="nix-preview-report">';
		$html .= '<div class="alert alert-info"><i class="fa fa-shield"></i> ' . $this->escapeHtml($this->formatPreviewStatistics($preview)) . '</div>';
		$html .= $this->renderPreviewTable($this->language->get('text_preview_changed_products'), $preview['rows']);
		$html .= $this->renderPreviewTable($this->language->get('text_preview_new_products'), $preview['new_rows']);
		$html .= $this->renderPreviewTable($this->language->get('text_preview_not_found_products'), $preview['not_found_rows']);
		$html .= $this->renderPreviewTable($this->language->get('text_preview_missing_products'), $preview['missing_rows']);
		$html .= '<div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> ' . $this->escapeHtml($this->language->get('help_preview_apply_warning')) . '</div>';
		$html .= '</div>';
		return $html;
	}

	private function renderPreviewTable($title, array $rows) {
		if (!$rows) {
			return '<h4>' . $this->escapeHtml($title) . '</h4><div class="well well-sm">' . $this->escapeHtml($this->language->get('text_preview_no_rows')) . '</div>';
		}

		$html = '<h4>' . $this->escapeHtml($title) . '</h4><div class="table-responsive"><table class="table table-bordered table-condensed table-hover"><thead><tr>';
		$headings = [
			$this->language->get('column_offer_id'),
			$this->language->get('column_product_id'),
			$this->language->get('column_name'),
			$this->language->get('column_model'),
			$this->language->get('column_sku'),
			$this->language->get('column_price_old'),
			$this->language->get('column_price_new'),
			$this->language->get('column_quantity_old'),
			$this->language->get('column_quantity_new'),
			$this->language->get('column_status_old'),
			$this->language->get('column_status_new'),
			$this->language->get('column_action')
		];
		foreach ($headings as $heading) {
			$html .= '<th>' . $this->escapeHtml($heading) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ($rows as $row) {
			$html .= '<tr>';
			foreach (['offer_id','product_id','name','model','sku','price_old','price_new','quantity_old','quantity_new','status_old','status_new','action'] as $key) {
				$html .= '<td>' . $this->escapeHtml($row[$key] ?? '') . '</td>';
			}
			$html .= '</tr>';
		}
		$html .= '</tbody></table></div>';
		return $html;
	}

	private function escapeHtml($value) {
		return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
	}

	private function getOfferImportSnapshot($offer, $test_product = []) {
		$tag_name = $this->supplier['tags']['name'] ?? 'name';
		$tag_model = $this->supplier['tags']['model'] ?? '';
		$tag_sku = $this->supplier['tags']['sku'] ?? '';
		$tag_price_purchasing = $this->supplier['tags']['price_purchasing'] ?? '';
		$tag_price_rrp = $this->supplier['tags']['price_rrp'] ?? '';
		$tag_currency = $this->supplier['tags']['currency'] ?? '';
		$tag_quantity = $this->supplier['tags']['quantity'] ?? '';
		$tag_stock_status_id = $this->supplier['tags']['stock_status_id'] ?? '';
		$tag_status = $this->supplier['tags']['status'] ?? '';
		$available = isset($offer['available']) ? (string)$offer['available'] : 'true';
		$remains = ($available === 'true' || $available === '1') ? 1 : 0;
		if ($tag_quantity && $this->offerHasField($offer, $tag_quantity)) { $remains = (int)$this->readOfferValue($offer, $tag_quantity, 0); }
		$price_purchasing = $this->normalizeImportPrice($this->readOfferValue($offer, $tag_price_purchasing, 0), $offer, $tag_currency);
		$price_rrp = $this->normalizeImportPrice($this->readOfferValue($offer, $tag_price_rrp, 0), $offer, $tag_currency);
		$price = ($this->session->data['nix']['markup'] && $price_purchasing) ? $price_purchasing + ($price_purchasing * ($this->session->data['nix']['markup'] / 100)) : $price_rrp;
		$status = $remains > 0 ? 1 : 0;
		if ($this->readOfferValue($offer, $tag_status, '') !== '') { $status = $this->readOfferBool($offer, $tag_status, $status); }
		if (0 == $price) { $status = 0; }
		$stock_status_id = (int)($test_product['stock_status_id'] ?? 0);
		if ($tag_stock_status_id && $this->readOfferValue($offer, $tag_stock_status_id, '') !== '') { $stock_status_id = $this->readOfferInt($offer, $tag_stock_status_id, $stock_status_id); } elseif (!$stock_status_id) { $stock_status_id = (int)$this->config->get('config_stock_status_id'); }
		$special = $this->prepareSpecialPriceData($offer, $price);
		return ['name'=>$this->readOfferValue($offer,$tag_name,''),'model'=>$this->readOfferValue($offer,$tag_model,''),'sku'=>$this->readOfferValue($offer,$tag_sku,''),'price'=>(float)$price,'supplier_price'=>(float)$price_purchasing,'quantity'=>(int)$remains,'stock_status_id'=>(int)$stock_status_id,'status'=>(int)$status,'special_price_has'=>(bool)$special['has'],'special_price'=>(float)$special['price']];
	}

	private function prepareSpecialPriceData($offer, $base_price = 0) {
		$tag_special_price = $this->supplier['tags']['special_price'] ?? '';
		$tags = array_filter([$tag_special_price, 'special_price', 'special', 'sale_price', 'discount_price', 'oldprice']);
		$has = false; $value = '';
		foreach ($tags as $tag) { if ($this->offerHasField($offer, $tag)) { $has = true; $value = $this->readOfferValue($offer, $tag, ''); break; } }
		if (!$has) { return ['has'=>false,'price'=>0,'product_special'=>[]]; }
		$price = $this->normalizeImportPrice($value, $offer, $this->supplier['tags']['currency'] ?? '');
		$product_special = [];
		if ($price > 0) { $product_special[] = ['customer_group_id'=>(int)$this->config->get('config_customer_group_id'),'priority'=>1,'price'=>$price,'date_start'=>'0000-00-00','date_end'=>'0000-00-00']; }
		return ['has'=>true,'price'=>$price,'product_special'=>$product_special];
	}

	private function offerHasField($offer, $tag) {
		$tag = (string)$tag;
		if ($tag === '') { return false; }
		return isset($offer->$tag) || isset($offer[$tag]);
	}

	private function updateProductStock($offer, $test_product, $filter) {
		$this->stdelog->write(3, 'updateProductStock() is called');
		
		$tag_price_purchasing	 = $this->supplier['tags']['price_purchasing'] ?? '';
		$tag_price_rrp				 = $this->supplier['tags']['price_rrp'] ?? '';
		$tag_special_price = $this->supplier['tags']['special_price'] ?? '';
		$tag_currency			 = $this->supplier['tags']['currency'] ?? '';
		$tag_quantity					 = $this->supplier['tags']['quantity'] ?? '';
		$tag_date_available = $this->supplier['tags']['date_available'] ?? '';
		$tag_stock_status_id = $this->supplier['tags']['stock_status_id'] ?? '';
		$tag_status = $this->supplier['tags']['status'] ?? '';
		$tag_points				 = $this->supplier['tags']['points'] ?? '';

		if ($test_product !== []) {
			$available = 'true';
			
			if (isset($offer['available'])) {
				$available = $offer['available'];
			}
			
			if ($available == 'true') {
				$remains = 1;
			} else {
				$remains = 0;
			}
			
			if ($tag_quantity && isset($offer->$tag_quantity)) {
				$remains = (int)$offer->$tag_quantity;
			}
				$price_purchasing = $this->normalizeImportPrice($this->readOfferValue($offer, $tag_price_purchasing, 0), $offer, $tag_currency);
				$price_rrp = $this->normalizeImportPrice($this->readOfferValue($offer, $tag_price_rrp, 0), $offer, $tag_currency);
				$price = 0;
			
			if ($this->session->data['nix']['markup'] && $price_purchasing) {
				$price = $price_purchasing + ($price_purchasing * ($this->session->data['nix']['markup'] / 100));
			} else {
				$price = $price_rrp;
			}
			
			$status = $remains > 0 ? 1 : 0;
			
			if ($this->readOfferValue($offer, $tag_status, '') !== '') {
				$status = $this->readOfferBool($offer, $tag_status, $status);
			}
			
			if (0 == $price) {
				$status = 0;
			}
			
			$stock_status_id = (int)($test_product['stock_status_id'] ?? 0);
			if ($tag_stock_status_id && $this->readOfferValue($offer, $tag_stock_status_id, '') !== '') {
				$stock_status_id = $this->readOfferInt($offer, $tag_stock_status_id, $stock_status_id);
			} elseif (!$stock_status_id) {
				$stock_status_id = (int)$this->config->get('config_stock_status_id');
			}

			$data_update = [
				'nix_supplier_id'					 => $filter['nix_supplier_id'] ?? ($this->request->post['supplier_id'] ?? 0),
				'nix_supplier_product_id'	 => $offer['id'],
				'price'										 => $price,
				'nix_supplier_price' => $price_purchasing,
				'remains'									 => $remains,
				'stock_status_id'				 => $stock_status_id,
				'status'									 => $status,
			];

			$this->model_extension_feed_nix->updateProductStock($data_update);

			$this->stdelog->write(4, 'recordProduct() :: $this->model_extension_feed_nix->updateProduct($data_update);');

			// Q?
			// Is it necessary to add any report?

		}
	}
	
	private function prepareCategoryDescription($node_index) {
		$this->stdelog->write(4, $node_index, 'getCategoryDescription() called with');
		
		$tag_name	= $this->supplier['tags']['name'] ?? 'name';
		$tag_description = $this->supplier['tags']['description'] ?? '';
		
		$category_description = [];
		
		foreach ($this->xml as $language_id => $xml) {
			$this_lang_node = $xml->shop->categories->category[$node_index];
			
			$category_description[$language_id]['name'] = $this->language->get('import_placeholder_name');
			$category_description[$language_id]['description'] = '';
			
			//'tag' => '',
			//'meta_title' => $name,
			//'meta_h1' => $name,
			//'meta_description' => '',
			//'meta_keyword' => '',
			
			$category_description[$language_id]['name'] = trim((string)$this_lang_node);
			$category_description[$language_id]['meta_title'] = $category_description[$language_id]['name'];
			$category_description[$language_id]['meta_h1'] = $category_description[$language_id]['name'];
			$category_description[$language_id]['h1'] = $category_description[$language_id]['name'];
			$category_description[$language_id]['meta_description'] = '';
			$category_description[$language_id]['meta_keyword'] = '';
			
			if (isset($this_lang_node->$tag_description)) {
				$category_description[$language_id]['description'] = trim((string)$this_lang_node->$tag_description);
				$category_description[$language_id]['meta_description'] = mb_substr(trim(strip_tags((string)$this_lang_node->$tag_description)), 0, 250);
			}
		}
		
		// Copy description to other language - if it is choosen
		foreach ($this->languages as $language) {
			if (!isset($category_description[$language['language_id']]) && isset($this->request->post['copy_description'])) {
				$category_description[$language['language_id']] = $category_description[$this->request->post['language_id']];
			}
		}
		
		return $category_description;
	}
	
	private function prepareProductDescription($offer_id) {
		$this->stdelog->write(3, $offer_id, 'getProductDescription() called with $offer_id');
		
		$tag_name	= $this->supplier['tags']['name'] ?? 'name';
		$tag_description = $this->supplier['tags']['description'] ?? '';
		$tag_meta_h1 = $this->supplier['tags']['meta_h1'] ?? '';
		$tag_meta_title = $this->supplier['tags']['meta_title'] ?? '';
		$tag_meta_description = $this->supplier['tags']['meta_description'] ?? '';
		$tag_meta_keyword = $this->supplier['tags']['meta_keyword'] ?? '';
		$tag_tag = $this->supplier['tags']['tag'] ?? '';
		
		$product_description = [];
		$languages_used = [];
		
		foreach ($this->offers_prepared as $language_id => $offers) {
			$languages_used[$language_id] = 1;
		}
		
		foreach ($languages_used as $language_id => $dummy_value) {
			$offer = (isset($this->offers_prepared[$language_id][$offer_id])) ? $this->offers_prepared[$language_id][$offer_id] : $this->offers_prepared[$this->request->post['language_id']][$offer_id];
			$this->stdelog->write(3, $offer, 'prepareProductDescription() :: $offer for $language_id `' . $language_id . '`');
			
			$name = $this->readOfferValue($offer, $tag_name, $this->language->get('import_placeholder_name'));
			$description = $this->readOfferValue($offer, $tag_description, '');
			$meta_h1 = $this->readOfferValue($offer, $tag_meta_h1, $name);
			$meta_title = $this->readOfferValue($offer, $tag_meta_title, $name);
			$meta_description = $this->readOfferValue($offer, $tag_meta_description, '');
			$meta_keyword = $this->readOfferValue($offer, $tag_meta_keyword, '');
			$tag = $this->readOfferValue($offer, $tag_tag, '');
			
			if ($meta_description === '' && $description !== '') {
				$meta_description = mb_substr(trim(strip_tags($description)), 0, 250);
			}
			
			$product_description[$language_id]['name'] = $name;
			$product_description[$language_id]['description'] = $description;
			$product_description[$language_id]['tag'] = $tag;
			$product_description[$language_id]['meta_title'] = $meta_title;
			$product_description[$language_id]['meta_h1'] = $meta_h1;
			$product_description[$language_id]['h1'] = $meta_h1;
			$product_description[$language_id]['meta_description'] = $meta_description;
			$product_description[$language_id]['meta_keyword'] = $meta_keyword;
		}
		
		foreach ($this->languages as $language) {
			if (!isset($product_description[$language['language_id']]) && isset($this->request->post['copy_description'])) {
				$product_description[$language['language_id']] = $product_description[$this->request->post['language_id']];
			}
		}
		
		return $product_description;
	}

	private function recordManufacturer($name) {
		$manufacturer_id = 0;
			
		$test_manufacturer = $this->model_extension_feed_nix->getManufacturer($name);
		
		if ($test_manufacturer === []) {
			//создаем
			$data_new_manufacturer = [
				'name'							 => $name,
				'description'				 => '',
				'meta_keyword'			 => '',
				'sort_order'				 => 0,
				'manufacturer_store' => [0 => 0],
				'keyword'						 => $this->helperTranslitUniversal($name),
			];
			$manufacturer_id = $this->model_extension_feed_nix->addManufacturer($data_new_manufacturer);
		} else {
			$manufacturer_id = $test_manufacturer['manufacturer_id'];
		}
			
		return $manufacturer_id;
	}
	
	private function prepareAttributes($offer_id) {
		$this->stdelog->write(3, $offer_id, 'prepareAttributes() called with $offer_id');
		
		$product_attribute = [];
		$tag_attributes = $this->supplier['tags']['attributes'] ?? 'param';
		
		if (!$tag_attributes || empty($this->offers_prepared[$this->request->post['language_id']][$offer_id])) {
			return $product_attribute;
		}

		$languages_used = [];
		$params = [];

		foreach ($this->offers_prepared as $language_id => $offers) {
			$languages_used[$language_id] = 1;
			
			if (isset($offers[$offer_id]) && isset($offers[$offer_id]->$tag_attributes)) {
				$params[$language_id] = $offers[$offer_id]->$tag_attributes;
			} elseif (isset($this->offers_prepared[$this->request->post['language_id']][$offer_id]->$tag_attributes)) {
				$params[$language_id] = $this->offers_prepared[$this->request->post['language_id']][$offer_id]->$tag_attributes;
			} else {
				$params[$language_id] = [];
			}

			$this->stdelog->write(4, $params[$language_id], 'prepareAttributes() :: params for language ' . $language_id);
		}

		foreach ($this->languages as $language) {
			if (!isset($params[$language['language_id']]) && isset($this->request->post['copy_description']) && isset($this->request->post['copy_attributes'])) {
				$params[$language['language_id']] = $params[$this->request->post['language_id']] ?? [];
				$languages_used[$language['language_id']] = 1;
			}
		}

		$main_params = $params[$this->request->post['language_id']] ?? [];
		if (!$main_params) {
			return $product_attribute;
		}

		$attribute_group_name = 'Default';
		$test_attribute_group = $this->model_extension_feed_nix->getAttributeGroup($attribute_group_name);

		if ($test_attribute_group == []) {
			$attribute_group_data = [];
			foreach ($languages_used as $language_id => $dummy_value) {
				$attribute_group_data['attribute_group_description'][$language_id]['name'] = $attribute_group_name;
			}
			$attribute_group_id = $this->model_extension_feed_nix->addAttributeGroup($attribute_group_data);
		} else {
			$attribute_group_id = (int)$test_attribute_group['attribute_group_id'];
		}

		$i = 0;
		foreach ($main_params as $attribute) {
			$attribute_name = isset($attribute['name']) ? trim((string)$attribute['name']) : '';
			if ($attribute_name === '') {
				$i++;
				continue;
			}

			$test_attribute = $this->model_extension_feed_nix->getAttribute($attribute_name);

			if ($test_attribute !== []) {
				$attribute_id = (int)$test_attribute['attribute_id'];
			} else {
				$dta = ['attribute_group_id' => $attribute_group_id, 'attribute_description' => []];
				foreach ($languages_used as $language_id => $dummy_value) {
					$param_item = $params[$language_id][$i] ?? $attribute;
					$dta['attribute_description'][$language_id]['name'] = isset($param_item['name']) ? trim((string)$param_item['name']) : $attribute_name;
				}
				$attribute_id = $this->model_extension_feed_nix->addAttribute($dta);
			}

			$product_attribute[$i] = ['attribute_id' => $attribute_id];
			foreach ($languages_used as $language_id => $dummy_value) {
				$param_item = $params[$language_id][$i] ?? $attribute;
				$product_attribute[$i]['product_attribute_description'][$language_id]['text'] = trim((string)$param_item);
			}
			$i++;
		}

		$this->stdelog->write(4, $product_attribute, 'prepareAttributes() :: return $product_attribute');
		return $product_attribute;
	}

	private function readOfferValue($offer, $tag, $default = '') {
		if ($tag === null || $tag === '') {
			return $default;
		}
		
		$tag = (string)$tag;
		
		if (isset($offer->$tag)) {
			$value = trim((string)$offer->$tag);
			return $value === '' ? $default : $value;
		}
		
		if (isset($offer[$tag])) {
			$value = trim((string)$offer[$tag]);
			return $value === '' ? $default : $value;
		}
		
		return $default;
	}

	private function readOfferInt($offer, $tag, $default = 0) {
		$value = $this->readOfferValue($offer, $tag, '');
		return $value === '' ? (int)$default : (int)$value;
	}

	private function readOfferFloat($offer, $tag, $default = 0) {
		$value = $this->readOfferValue($offer, $tag, '');
		if ($value === '') {
			return (float)$default;
		}
		$value = str_replace([' ', ','], ['', '.'], (string)$value);
		return (float)$value;
	}

	private function readOfferBool($offer, $tag, $default = 0) {
		$value = strtolower((string)$this->readOfferValue($offer, $tag, ''));
		
		if ($value === '') {
			return (int)$default ? 1 : 0;
		}
		
		return in_array($value, ['1', 'true', 'yes', 'y', 'on', 'так', 'да', 'є', 'есть'], true) ? 1 : 0;
	}

	private function normalizeImportPrice($price, $offer = null, $tag_currency = '') {
		$price = str_replace([' ', ','], ['', '.'], (string)$price);
		$price = (float)$price;
		
		if ($price <= 0) {
			return 0;
		}
		
		$currency_code = '';
		
		if ($offer !== null) {
			$currency_code = $this->readOfferValue($offer, $tag_currency, '');
			
			if ($currency_code === '') {
				$currency_code = $this->readOfferValue($offer, 'currencyId', '');
			}
			
			if ($currency_code === '') {
				$currency_code = $this->readOfferValue($offer, 'currency', '');
			}
		}
		
		$currency_code = strtoupper(trim((string)$currency_code));
		$store_currency = strtoupper((string)$this->config->get('config_currency'));
		
		if ($currency_code !== '' && $store_currency !== '' && $currency_code !== $store_currency) {
			try {
				$converted = $this->currency->convert($price, $currency_code, $store_currency);
				if ((float)$converted > 0) {
					return (float)$converted;
				}
			} catch (Exception $e) {
				$this->log->write('ImportXML Clean: currency conversion failed for ' . $currency_code . ' -> ' . $store_currency . '. ' . $e->getMessage());
			}
		}
		
		return $price;
	}

	private function normalizeImportDate($value, $default = '') {
		$value = trim((string)$value);
		if ($value === '') {
			return $default;
		}
		$timestamp = strtotime($value);
		return $timestamp ? date('Y-m-d', $timestamp) : $default;
	}

	private function readCategoryGoogleProductCategoryId($category) {
		foreach (['google_product_category_id', 'google_category_id', 'google_product_category', 'google_category', 'google_taxonomy_id'] as $key) {
			if (isset($category[$key]) && trim((string)$category[$key]) !== '') {
				return trim((string)$category[$key]);
			}
			
			if (isset($category->$key) && trim((string)$category->$key) !== '') {
				return trim((string)$category->$key);
			}
		}
		
		return '';
	}

	public function helperGetImage($url, $index, $essense = 'products', $dirname = 'uncategorized') {
        $url = trim((string)$url);
        if ($url === '') { return ''; }
        require_once DIR_SYSTEM . 'library/importxml_clean_pro/image_downloader.php';
        $essense = in_array($essense, array('products', 'categories', 'manufacturers'), true) ? $essense : 'products';
        $dirname = $this->helperTranslitUniversal(mb_strtolower((string)$dirname));
        $dirname = preg_replace('/[^a-z0-9_-]/i', '_', $dirname);
        $dirs = 'catalog/' . $essense . '/' . ($dirname !== '' ? substr($dirname, 0, 100) : 'uncategorized');
        $base = pathinfo($this->helperPrepareFilename($url, $index), PATHINFO_FILENAME);
        $downloader = new ImportxmlCleanProImageDownloader();
        $filename = $downloader->download($url, DIR_IMAGE . $dirs, $base);
        if ($filename === '') {
            $this->stdelog->write(1, '', 'Image download rejected or failed');
            return '';
        }
        return $dirs . '/' . $filename;
    }

	/*
	 * Create array of objects with offer_id index (!)
	 * Sometimes it is happen errors with ordering of elemens by $node_index
	 * So we have to use good identifier of offer - it is offer_id
	 */

	private function parseImportFile($file, $language_id) {
		$content = file_get_contents($file);
		if ($content === false || trim($content) === '') { return ['success'=>false,'error'=>$this->language->get('error_file')]; }
		$original_name = (string)($this->session->data['nix']['file_names'][(int)$language_id] ?? basename($file));
		$ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
		$trimmed = ltrim($content);
		if ($ext === 'xlsx' || substr($content, 0, 2) === 'PK') { return $this->parseXlsxImportFile($file); }
		if (in_array($ext, ['csv','txt'], true) || ($trimmed !== '' && $trimmed[0] !== '<')) { return $this->parseCsvImportFile($file); }
		$use_errors = libxml_use_internal_errors(true); libxml_clear_errors();
		$xml = simplexml_load_string($content, 'SimpleXMLElement', LIBXML_NONET);
		if ($xml !== false && $xml->getName() === 'shop') { $xml = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?><yml_catalog>' . $content . '</yml_catalog>', 'SimpleXMLElement', LIBXML_NONET); }
		elseif ($xml !== false && $xml->getName() === 'offers') { $xml = simplexml_load_string('<?xml version="1.0" encoding="UTF-8"?><yml_catalog><shop>' . $content . '</shop></yml_catalog>', 'SimpleXMLElement', LIBXML_NONET); }
		$xml_errors = libxml_get_errors();
		if ($xml === false || count($xml_errors) > 0) { libxml_clear_errors(); libxml_use_internal_errors($use_errors); return ['success'=>false,'error'=>$this->language->get('error_import_fatal')]; }
		libxml_clear_errors(); libxml_use_internal_errors($use_errors);
		return ['success'=>true,'xml'=>$xml,'error'=>''];
	}

	private function parseCsvImportFile($file) {
		$handle = fopen($file, 'r');
		if (!$handle) { return ['success'=>false,'error'=>$this->language->get('error_file')]; }
		$first_line = fgets($handle);
		if ($first_line === false) { fclose($handle); return ['success'=>false,'error'=>$this->language->get('error_file')]; }
		rewind($handle);
		$delimiter = $this->detectCsvDelimiter($first_line);
		$headers = fgetcsv($handle, 0, $delimiter, '"', '\\');
		if (!$headers || !is_array($headers)) { fclose($handle); return ['success'=>false,'error'=>$this->language->get('error_import_no_tags')]; }
		$headers = array_map([$this, 'normalizeImportHeader'], $headers);
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><yml_catalog><shop><offers></offers></shop></yml_catalog>');
		$row_number = 0;
		while (($row = fgetcsv($handle, 0, $delimiter)) !== false) { $row_number++; if (!$row || (count($row) === 1 && trim((string)$row[0]) === '')) { continue; } $this->appendArrayRowAsOffer($xml->shop->offers, $headers, $row, $row_number); }
		fclose($handle);
		return ['success'=>true,'xml'=>$xml,'error'=>''];
	}

	private function parseXlsxImportFile($file) {
		if (!class_exists('ZipArchive')) { return ['success'=>false,'error'=>$this->language->get('error_ziparchive_missing')]; }
		$zip = new ZipArchive();
		if ($zip->open($file) !== true) { return ['success'=>false,'error'=>$this->language->get('error_file')]; }
		$shared = [];
		$shared_xml = $zip->getFromName('xl/sharedStrings.xml');
		if ($shared_xml !== false) { $sxml = simplexml_load_string($shared_xml); if ($sxml) { foreach ($sxml->si as $si) { $text = ''; if (isset($si->t)) { $text = (string)$si->t; } elseif (isset($si->r)) { foreach ($si->r as $r) { $text .= (string)$r->t; } } $shared[] = $text; } } }
		$sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml'); $zip->close();
		if ($sheet_xml === false) { return ['success'=>false,'error'=>$this->language->get('error_file')]; }
		$sheet = simplexml_load_string($sheet_xml);
		if (!$sheet || !isset($sheet->sheetData->row)) { return ['success'=>false,'error'=>$this->language->get('error_file')]; }
		$rows = [];
		foreach ($sheet->sheetData->row as $row) { $cells = []; foreach ($row->c as $cell) { $ref = (string)$cell['r']; $col = preg_replace('/[^A-Z]/', '', strtoupper($ref)); $idx = $this->xlsxColumnIndex($col); $type = (string)$cell['t']; $value = ''; if ($type === 's') { $shared_index = (int)$cell->v; $value = $shared[$shared_index] ?? ''; } elseif ($type === 'inlineStr' && isset($cell->is->t)) { $value = (string)$cell->is->t; } else { $value = isset($cell->v) ? (string)$cell->v : ''; } $cells[$idx] = $value; } if ($cells) { ksort($cells); $max = max(array_keys($cells)); $line = []; for ($i=0; $i <= $max; $i++) { $line[] = $cells[$i] ?? ''; } $rows[] = $line; } }
		if (!$rows) { return ['success'=>false,'error'=>$this->language->get('error_import_no_tags')]; }
		$headers = array_map([$this, 'normalizeImportHeader'], array_shift($rows));
		$xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><yml_catalog><shop><offers></offers></shop></yml_catalog>');
		$row_number = 0; foreach ($rows as $row) { $row_number++; $this->appendArrayRowAsOffer($xml->shop->offers, $headers, $row, $row_number); }
		return ['success'=>true,'xml'=>$xml,'error'=>''];
	}

	private function appendArrayRowAsOffer($offers, array $headers, array $row, $row_number) {
		$data = [];
		foreach ($headers as $i => $header) { if ($header === '') { continue; } $data[$header] = isset($row[$i]) ? $this->normalizeImportedText($row[$i]) : ''; }
		$offer_id = $data['id'] ?? $data['offer_id'] ?? $data['nix_supplier_product_id'] ?? $data['vendorCode'] ?? $data['vendorcode'] ?? $data['sku'] ?? $data['model'] ?? '';
		if ($offer_id === '') { $offer_id = 'row-' . (int)$row_number; }
		$offer = $offers->addChild('offer');
		$offer->addAttribute('id', $this->sanitizeXmlText($offer_id));
		if (isset($data['available'])) { $offer->addAttribute('available', $this->sanitizeXmlText($data['available'])); }
		foreach ($data as $header => $value) { if (in_array($header, ['id','offer_id','available'], true)) { continue; } $offer->addChild($this->normalizeImportHeader($header), htmlspecialchars($this->sanitizeXmlText($value), ENT_QUOTES | ENT_XML1, 'UTF-8')); }
	}

	private function normalizeImportedText($value) {
		$value = trim((string)$value);
		$value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
		if ($value !== '' && function_exists('mb_check_encoding') && !mb_check_encoding($value, 'UTF-8')) {
			$converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1251, ISO-8859-1, UTF-8');
			if (is_string($converted) && $converted !== '') {
				$value = $converted;
			}
		}
		return $this->sanitizeXmlText($value);
	}

	private function detectCsvDelimiter($line) { $delimiters = [";", "\t", ",", "|"]; $best = ","; $best_count = -1; foreach ($delimiters as $delimiter) { $count = substr_count((string)$line, $delimiter); if ($count > $best_count) { $best = $delimiter; $best_count = $count; } } return $best; }
	private function normalizeImportHeader($header) { $header = trim((string)$header); $header = preg_replace('/^\xEF\xBB\xBF/', '', $header); $header = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $header); $header = trim($header, '_-.'); if ($header === '' || preg_match('/^[0-9]/', $header)) { $header = 'field_' . $header; } return $header; }
	private function sanitizeXmlText($value) {
		$value = (string)$value;
		$clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $value);
		if ($clean === null) {
			$clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);
		}
		return (string)$clean;
	}
	private function xlsxColumnIndex($col) { $col = strtoupper((string)$col); $num = 0; for ($i=0; $i < strlen($col); $i++) { $num = $num * 26 + (ord($col[$i]) - 64); } return max(0, $num - 1); }

	public function helperPrepareOffers() {
		foreach ($this->xml as $language_id => $xml) {
			$offers_with_nodes = $xml->xpath('shop/offers/offer');
			
			$offers = [];
			
			foreach ($offers_with_nodes as $offer) {
				$offerId = (string)$offer['id'];
				if (!isset($offers[$offerId])) {
					$offers[$offerId] = $offer;
				}
			}
			
			$this->offers_prepared[$language_id] = $offers;
		}
	}
	
	public function helperHaveTime() {
		if ($this->cron_mode) {
			return true;
		}

		$time = time();
		
		$diff = $time - $this->request_time;
		
		$this->stdelog->write(3, $diff, 'helperHaveTime() :: $diff');

		if ($diff > 25) {
			return false;
		}
		return true;
	}
	
		private function helperMemoryLimit() {
		$memory_limit_raw = trim((string)ini_get('memory_limit'));
		if ($memory_limit_raw === '' || $memory_limit_raw === '-1') {
			return PHP_INT_MAX;
		}
		$last_char = strtoupper($memory_limit_raw[strlen($memory_limit_raw) - 1]);
		$memory_limit = (int)$memory_limit_raw;

		if ($last_char === 'G') {
			$memory_limit *= 1024 * 1024 * 1024;
		} elseif ($last_char === 'M') {
			$memory_limit *= 1024 * 1024;
		} elseif ($last_char === 'K') {
			$memory_limit *= 1024;
		}

		return $memory_limit > 0 ? $memory_limit : 268435456;
	}

	public function helperCreateDir($path) {
		mkdir($path, 0755, true);
	}
	
	/*
	 * Based on Foreign Characters class and convert_accented_characters() from CodeIgniter 3
	 */
	public function helperTranslitUniversal($string) {
		$foreign_characters = array(
			'/ä|æ|ǽ/'																											 => 'ae',
			'/ö|œ/'																												 => 'oe',
			'/ü/'																													 => 'ue',
			'/Ä/'																													 => 'Ae',
			'/Ü/'																													 => 'Ue',
			'/Ö/'																													 => 'Oe',
			'/À|Á|Â|Ã|Ä|Å|Ǻ|Ā|Ă|Ą|Ǎ|Α|Ά|Ả|Ạ|Ầ|Ẫ|Ẩ|Ậ|Ằ|Ắ|Ẵ|Ẳ|Ặ|А/'					 => 'A',
			'/à|á|â|ã|å|ǻ|ā|ă|ą|ǎ|ª|α|ά|ả|ạ|ầ|ấ|ẫ|ẩ|ậ|ằ|ắ|ẵ|ẳ|ặ|а/'				 => 'a',
			'/Б/'																													 => 'B',
			'/б/'																													 => 'b',
			'/Ç|Ć|Ĉ|Ċ|Č/'																									 => 'C',
			'/ç|ć|ĉ|ċ|č/'																									 => 'c',
			'/Д|Δ/'																												 => 'D',
			'/д|δ/'																												 => 'd',
			'/Ð|Ď|Đ/'																											 => 'Dj',
			'/ð|ď|đ/'																											 => 'dj',
			'/È|É|Ê|Ë|Ē|Ĕ|Ė|Ę|Ě|Ε|Έ|Ẽ|Ẻ|Ẹ|Ề|Ế|Ễ|Ể|Ệ|Е|Э/'									 => 'E',
			'/è|é|ê|ë|ē|ĕ|ė|ę|ě|έ|ε|ẽ|ẻ|ẹ|ề|ế|ễ|ể|ệ|е|э/'									 => 'e',
			'/Ф/'																													 => 'F',
			'/ф/'																													 => 'f',
			'/Ĝ|Ğ|Ġ|Ģ|Γ|Г|Ґ/'																							 => 'G',
			'/ĝ|ğ|ġ|ģ|γ|г|ґ/'																							 => 'g',
			'/Ĥ|Ħ/'																												 => 'H',
			'/ĥ|ħ/'																												 => 'h',
			'/Ì|Í|Î|Ï|Ĩ|Ī|Ĭ|Ǐ|Į|İ|Η|Ή|Ί|Ι|Ϊ|Ỉ|Ị|И|Ы/'											 => 'I',
			'/ì|í|î|ï|ĩ|ī|ĭ|ǐ|į|ı|η|ή|ί|ι|ϊ|ỉ|ị|и|ы|ї/'										 => 'i',
			'/І/' => 'I', // Customized ukr
			'/і/' => 'i', // Customized ukr
			'/Ĵ/'																													 => 'J',
			'/ĵ/'																													 => 'j',
			'/Θ/'																													 => 'TH',
			'/θ/'																													 => 'th',
			'/Ķ|Κ|К/'																											 => 'K',
			'/ķ|κ|к/'																											 => 'k',
			'/Ĺ|Ļ|Ľ|Ŀ|Ł|Λ|Л/'																							 => 'L',
			'/ĺ|ļ|ľ|ŀ|ł|λ|л/'																							 => 'l',
			'/М/'																													 => 'M',
			'/м/'																													 => 'm',
			'/Ñ|Ń|Ņ|Ň|Ν|Н/'																								 => 'N',
			'/ñ|ń|ņ|ň|ŉ|ν|н/'																							 => 'n',
			'/Ò|Ó|Ô|Õ|Ō|Ŏ|Ǒ|Ő|Ơ|Ø|Ǿ|Ο|Ό|Ω|Ώ|Ỏ|Ọ|Ồ|Ố|Ỗ|Ổ|Ộ|Ờ|Ớ|Ỡ|Ở|Ợ|О/'		 => 'O',
			'/ò|ó|ô|õ|ō|ŏ|ǒ|ő|ơ|ø|ǿ|º|ο|ό|ω|ώ|ỏ|ọ|ồ|ố|ỗ|ổ|ộ|ờ|ớ|ỡ|ở|ợ|о/'	 => 'o',
			'/П/'																													 => 'P',
			'/п/'																													 => 'p',
			'/Ŕ|Ŗ|Ř|Ρ|Р/'																									 => 'R',
			'/ŕ|ŗ|ř|ρ|р/'																									 => 'r',
			'/Ś|Ŝ|Ş|Ș|Š|Σ|С/'																							 => 'S',
			'/ś|ŝ|ş|ș|š|ſ|σ|ς|с/'																					 => 's',
			'/Ț|Ţ|Ť|Ŧ|Τ|Т/'																								 => 'T',
			'/ț|ţ|ť|ŧ|τ|т/'																								 => 't',
			'/Þ|þ/'																												 => 'th',
			'/Ù|Ú|Û|Ũ|Ū|Ŭ|Ů|Ű|Ų|Ư|Ǔ|Ǖ|Ǘ|Ǚ|Ǜ|Ũ|Ủ|Ụ|Ừ|Ứ|Ữ|Ử|Ự|У/'						 => 'U',
			'/ù|ú|û|ũ|ū|ŭ|ů|ű|ų|ư|ǔ|ǖ|ǘ|ǚ|ǜ|υ|ύ|ϋ|ủ|ụ|ừ|ứ|ữ|ử|ự|у/'				 => 'u',
			'/Ƴ|Ɏ|Ỵ|Ẏ|Ӳ|Ӯ|Ў|Ý|Ÿ|Ŷ|Υ|Ύ|Ϋ|Ỳ|Ỹ|Ỷ|Ỵ|Й/'												 => 'Y',
			'/ẙ|ʏ|ƴ|ɏ|ỵ|ẏ|ӳ|ӯ|ў|ý|ÿ|ŷ|ỳ|ỹ|ỷ|ỵ|й/'													 => 'y',
			'/В/'																													 => 'V',
			'/в/'																													 => 'v',
			'/Ŵ/'																													 => 'W',
			'/ŵ/'																													 => 'w',
			'/Φ/'																													 => 'F',
			'/φ/'																													 => 'f',
			'/Χ/'																													 => 'CH',
			'/χ/'																													 => 'ch',
			'/Ź|Ż|Ž|Ζ|З/'																									 => 'Z',
			'/ź|ż|ž|ζ|з/'																									 => 'z',
			'/Æ|Ǽ/'																												 => 'AE',
			'/ß/'																													 => 'ss',
			'/Ĳ/'																													 => 'IJ',
			'/ĳ/'																													 => 'ij',
			'/Œ/'																													 => 'OE',
			'/ƒ/'																													 => 'f',
			'/Ξ/'																													 => 'KS',
			'/ξ/'																													 => 'ks',
			'/Π/'																													 => 'P',
			'/π/'																													 => 'p',
			'/Β/'																													 => 'V',
			'/β/'																													 => 'v',
			'/Μ/'																													 => 'M',
			'/μ/'																													 => 'm',
			'/Ψ/'																													 => 'PS',
			'/ψ/'																													 => 'ps',
			'/Ё/'																													 => 'Yo',
			'/ё/'																													 => 'yo',
			'/Є/'																													 => 'Ye',
			'/є/'																													 => 'ye',
			'/Ї/'																													 => 'Yi',
			'/Ж/'																													 => 'Zh',
			'/ж/'																													 => 'zh',
			'/Х/'																													 => 'Kh',
			'/х/'																													 => 'kh',
			'/Ц/'																													 => 'Ts',
			'/ц/'																													 => 'ts',
			'/Ч/'																													 => 'Ch',
			'/ч/'																													 => 'ch',
			'/Ш/'																													 => 'Sh',
			'/ш/'																													 => 'sh',
			'/Щ/'																													 => 'Shch',
			'/щ/'																													 => 'shch',
			'/Ъ|ъ|Ь|ь/'																										 => '',
			'/Ю/'																													 => 'Yu',
			'/ю/'																													 => 'yu',
			'/Я/'																													 => 'Ya',
			'/я/'																													 => 'ya'
		);

		$array_from	= array_keys($foreign_characters);
		$array_to		= array_values($foreign_characters);

		$string = preg_replace($array_from, $array_to, $string);
		
		$string = preg_replace('/[^a-zA-Z0-9\-_]/', ' ', $string);
		
		$string = preg_replace('/\s+/', '-', $string);
		$string = preg_replace('|-+|', '-', $string);
		$string = preg_replace('|_+|', '-', $string);
		$string = trim($string, '-');

		return $string;
	}
	
	public function helperPrepareFilename($url, $index = 0) {
		$path_parts = pathinfo(parse_url((string)$url, PHP_URL_PATH) ?: 'image.jpg');
		$base = isset($path_parts['filename']) && $path_parts['filename'] !== '' ? $path_parts['filename'] : 'image';
		$extension = isset($path_parts['extension']) ? strtolower($path_parts['extension']) : 'jpg';
        if (!in_array($extension, array('jpg', 'jpeg', 'png', 'gif', 'webp'), true)) { $extension = 'jpg'; }
		$string = $this->helperTranslitUniversal($base);
		if ($string === '') {
			$string = 'image';
		}
		
		if ($index) {
			$string .= '_' . (int)$index;
		}
		
		return $string . '.' . $extension;
	}


	public function exportSettings() {
		$this->load->language('extension/feed/nix');

		if (!$this->validateUserToken()) {
			return $this->json([
				'status' => 'Error',
				'msg' => $this->language->get('error_user_token'),
				'errors' => ['warning' => $this->language->get('error_user_token')]
			]);
		}

		if (!$this->user->hasPermission('modify', 'extension/feed/nix')) {
			return $this->json([
				'status' => 'Error',
				'msg' => $this->language->get('error_permission'),
				'errors' => ['warning' => $this->language->get('error_permission')]
			]);
		}

		$this->load->model('extension/feed/nix');
		$export = $this->model_extension_feed_nix->exportSettings();
		$filename = 'importxml-clean-v' . NIX_VERSION . '-settings-' . date('Y-m-d-H-i-s') . '.json';

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->addHeader('Content-Disposition: attachment; filename="' . $filename . '"');
		$this->response->setOutput(json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
	}

	public function importSettings() {
		$this->load->language('extension/feed/nix');
		$json = ['status' => 'Error', 'msg' => $this->language->get('error_import_settings_failed')];

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['msg'] = $this->language->get('error_request_method');
			return $this->json($json);
		}

		if (!$this->validateUserToken()) {
			$json['msg'] = $this->language->get('error_user_token');
			return $this->json($json);
		}

		if (!$this->user->hasPermission('modify', 'extension/feed/nix')) {
			$json['msg'] = $this->language->get('error_permission');
			return $this->json($json);
		}

		if (empty($_FILES['nix_settings_file']['tmp_name']) || !is_uploaded_file($_FILES['nix_settings_file']['tmp_name'])) {
			$json['msg'] = $this->language->get('error_import_settings_file');
			return $this->json($json);
		}

		if (!empty($_FILES['nix_settings_file']['size']) && (int)$_FILES['nix_settings_file']['size'] > 1048576) {
			$json['msg'] = $this->language->get('error_import_settings_size');
			return $this->json($json);
		}

		$content = file_get_contents($_FILES['nix_settings_file']['tmp_name']);
		$data = json_decode((string)$content, true);

		if (!is_array($data) || empty($data['module']) || empty($data['settings']) || !isset($data['suppliers'])) {
			$json['msg'] = $this->language->get('error_import_settings_json');
			return $this->json($json);
		}

		$this->load->model('extension/feed/nix');
		$result = $this->model_extension_feed_nix->importSettings($data);

		if (!$result['success']) {
			$json['msg'] = $result['error'] ?: $this->language->get('error_import_settings_failed');
			return $this->json($json);
		}

		$json = [
			'status' => 'OK',
			'msg' => sprintf($this->language->get('text_import_settings_success'), (int)$result['suppliers'])
		];

		return $this->json($json);
	}

	public function resetDefaults() {
		$this->load->language('extension/feed/nix');
		$json = ['status' => 'Error', 'msg' => $this->language->get('error_permission')];

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['msg'] = $this->language->get('error_request_method');
			return $this->json($json);
		}

		if (!$this->validateUserToken()) {
			$json['msg'] = $this->language->get('error_user_token');
			return $this->json($json);
		}

		if (!$this->user->hasPermission('modify', 'extension/feed/nix')) {
			return $this->json($json);
		}

		$this->load->model('extension/feed/nix');
		$this->model_extension_feed_nix->resetDefaults($this->generateCronToken());

		return $this->json(['status' => 'OK', 'msg' => $this->language->get('text_defaults_restored')]);
	}

	private function getDiagnosticTexts() {
		return [
			'module_enabled' => $this->language->get('diag_module_enabled'),
			'module_disabled' => $this->language->get('diag_module_disabled'),
			'table_suppliers' => $this->language->get('diag_table_suppliers'),
			'field_product_supplier_id' => $this->language->get('diag_field_product_supplier_id'),
			'field_product_supplier_product_id' => $this->language->get('diag_field_product_supplier_product_id'),
			'field_product_supplier_price' => $this->language->get('field_product_supplier_price'),
			'simplexml' => $this->language->get('diag_simplexml'),
			'cache_writable' => $this->language->get('diag_cache_writable'),
			'logs_writable' => $this->language->get('diag_logs_writable'),
			'main_category_ptc' => $this->language->get('diag_main_category_ptc'),
			'main_category_product' => $this->language->get('diag_main_category_product'),
			'google_category' => $this->language->get('diag_google_category'),
			'php_version' => $this->language->get('diag_php_version'),
		];
	}

	public function cron() {
		$this->cron_mode = true;
		$this->load->language('extension/feed/nix');

		$token = trim((string)($this->request->get['token'] ?? $this->request->post['token'] ?? ''));
		$config_token = trim((string)$this->config->get('feed_nix_cron_token'));

		if ($config_token === '' || !hash_equals($config_token, $token)) {
			return $this->json([
				'status' => 'Error',
				'msg' => $this->language->get('error_cron_token'),
				'errors' => ['token' => $this->language->get('error_cron_token')]
			]);
		}

		if (!$this->isEnabled() || !(int)$this->config->get('feed_nix_cron_status')) {
			return $this->json([
				'status' => 'Error',
				'msg' => $this->language->get('error_cron_disabled'),
				'errors' => ['cron' => $this->language->get('error_cron_disabled')]
			]);
		}

		if (function_exists('set_time_limit')) {
			@set_time_limit(0);
		}

		$supplier_id = (int)$this->config->get('feed_nix_cron_supplier_id');
		$language_id = (int)$this->config->get('feed_nix_cron_language_id');

		if ($language_id < 1) {
			$language_id = (int)$this->config->get('config_language_id');
		}

		if (!isset($this->session->data) || !is_array($this->session->data)) {
			$this->session->data = [];
		}

		$this->session->data['nix']['processing_start_time'] = time();
		$this->stdelog->setMarker($this->session->data['nix']['processing_start_time']);

		$this->request->server['REQUEST_METHOD'] = 'POST';
		$this->request->post = [
			'supplier_id' => $supplier_id,
			'language_id' => $language_id,
			'nix_new_submit' => 1,
			'nix_cron_submit' => 1
		];

		$cron_mode = (string)$this->config->get('feed_nix_cron_mode');
		if ($cron_mode !== 'full') {
			$cron_mode = 'price_stock';
		}

		if ($cron_mode === 'price_stock') {
			$this->request->post['nix_price_stock_only'] = 1;
			$this->request->post['update_if_exist'] = 1;
		} elseif ((int)$this->config->get('feed_nix_cron_update_if_exist')) {
			$this->request->post['update_if_exist'] = 1;
		}
		if ($cron_mode === 'full' && (int)$this->config->get('feed_nix_cron_copy_description')) {
			$this->request->post['copy_description'] = 1;
		}
		if ($cron_mode === 'full' && (int)$this->config->get('feed_nix_cron_copy_attributes')) {
			$this->request->post['copy_attributes'] = 1;
		}
		if ($cron_mode === 'full' && (int)$this->config->get('feed_nix_cron_delete_all')) {
			$this->request->post['delete_all'] = 1;
		}

		return $this->processingImportAjax();
	}

	private function saveCronXmlFiles() {
		$link_price = trim((string)($this->supplier['link_price'] ?? ''));

		if ($link_price === '') {
			return ['success' => false, 'error' => $this->language->get('error_cron_empty_link')];
		}

		$main_language_id = (int)($this->request->post['language_id'] ?? $this->config->get('config_language_id'));
		$links = $this->parseCronXmlLinks($link_price, $main_language_id);

		foreach ($links as $language_id => $url) {
			$content = $this->downloadCronXml($url);

			if ($content === false || trim($content) === '') {
				return ['success' => false, 'error' => sprintf($this->language->get('error_cron_download'), $url)];
			}

			$file = FILE_PATH_BASE . (int)$language_id . '.xml';
			if (false === file_put_contents($file, $content)) {
				return ['success' => false, 'error' => sprintf($this->language->get('error_cron_write_file'), $file)];
			}
		}

		return ['success' => true, 'error' => ''];
	}

	private function parseCronXmlLinks($link_price, $main_language_id) {
		$links = [];
		$lines = preg_split('/\r\n|\r|\n/', (string)$link_price);

		foreach ($lines as $line) {
			$line = trim($line);
			if ($line === '') {
				continue;
			}

			if (preg_match('/^(\d+)\s*[=:]\s*(.+)$/u', $line, $matches)) {
				$links[(int)$matches[1]] = trim($matches[2]);
			} elseif (!$links) {
				$links[(int)$main_language_id] = $line;
			}
		}

		if (!$links && trim((string)$link_price) !== '') {
			$links[(int)$main_language_id] = trim((string)$link_price);
		}

		return $links;
	}

	private function downloadCronXml($url) {
		$url = trim((string)$url);

		if ($url === '') {
			return false;
		}

		if (is_file($url)) {
			return file_get_contents($url);
		}

		if (!preg_match('#^https?://#i', $url)) {
			return false;
		}

		if (function_exists('curl_init')) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);
			curl_setopt($ch, CURLOPT_USERAGENT, 'ImportXML Clean Cron/' . NIX_VERSION);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			$content = curl_exec($ch);
			$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			unset($ch);

			if ($content !== false && ($http_code === 0 || ($http_code >= 200 && $http_code < 400))) {
				return $content;
			}
		}

		$context = stream_context_create([
			'http' => [
				'timeout' => 120,
				'follow_location' => 1,
				'user_agent' => 'ImportXML Clean Cron/' . NIX_VERSION
			],
			'ssl' => [
				'verify_peer' => true,
				'verify_peer_name' => true
			]
		]);

		return @file_get_contents($url, false, $context);
	}

	private function generateCronToken() {
		if (function_exists('random_bytes')) {
			return bin2hex(random_bytes(24));
		}

		return sha1(uniqid('nix-cron-', true) . mt_rand());
	}

	private function saveSettingValue($code, $key, $value) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET `store_id` = '0', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', `serialized` = '0'");
		$this->config->set($key, $value);
	}

	private function isEnabled() {
		return ((int)$this->config->get('feed_nix_status') === 1);
	}

	public function clearLogs() {
		$this->load->language('extension/feed/nix');

		$json = ['status' => 'Error', 'msg' => $this->language->get('error_permission')];

		if ($this->request->server['REQUEST_METHOD'] !== 'POST') {
			$json['msg'] = $this->language->get('error_request_method');
			return $this->json($json);
		}

		if (!$this->validateUserToken()) {
			$json['msg'] = $this->language->get('error_user_token');
			return $this->json($json);
		}

		if ($this->user->hasPermission('modify', 'extension/feed/nix')) {
			$this->load->model('extension/feed/nix');
			$deleted = $this->model_extension_feed_nix->clearLogs();
			$json = ['status' => 'OK', 'msg' => sprintf($this->language->get('text_logs_cleared'), $deleted)];
		}
		return $this->json($json);
	}


	private function validateUserToken() {
		if ($this->cron_mode) {
			return true;
		}

		$expected = isset($this->session->data['user_token']) ? (string)$this->session->data['user_token'] : '';
		$actual = (string)($this->request->get['user_token'] ?? $this->request->post['user_token'] ?? '');

		return ($expected !== '' && $actual !== '' && hash_equals($expected, $actual));
	}

	private function permissionDenied(array $data = []) {
		if (empty($data['errors']['warning'])) {
			$data['errors'] = ['warning' => $this->language->get('error_permission')];
		}
		$data['breadcrumbs'] = $this->stde->breadcrumbs();
		$data['cancel'] = $this->stde->link('cancel');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['btn_cancel'] = $this->language->get('btn_cancel');
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/feed/nix_denied', $data));
	}

	private function json($data) {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE));
	}

}
