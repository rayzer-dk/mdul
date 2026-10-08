<?php
/**
 * ImportXML Clean PRO cron runner.
 * Usage CLI: php /path/to/admin/nix_cron.php token=YOUR_TOKEN
 * Usage HTTP: https://example.com/admin/nix_cron.php?token=YOUR_TOKEN
 */

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
	foreach (array_slice($argv, 1) as $arg) {
		if (strpos($arg, '=') !== false) {
			list($key, $value) = explode('=', $arg, 2);
			$_GET[$key] = $value;
		}
	}
}

if (!isset($_SERVER['REQUEST_METHOD'])) {
	$_SERVER['REQUEST_METHOD'] = 'GET';
}

if (!defined('VERSION')) {
	define('VERSION', '3.0.4.1');
}

chdir(__DIR__);

if (!is_file(__DIR__ . '/config.php')) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['status' => 'Error', 'msg' => 'admin/config.php not found']);
	exit;
}

require_once __DIR__ . '/config.php';
require_once DIR_SYSTEM . 'startup.php';

try {
	$registry = new Registry();

	$config = new Config();
	$config->load('default');
	$config->load('admin');
	$registry->set('config', $config);

	$loader = new Loader($registry);
	$registry->set('load', $loader);

	$event = new Event($registry);
	$registry->set('event', $event);

	$request = new Request();
	$registry->set('request', $request);

	$response = new Response();
	$response->addHeader('Content-Type: application/json; charset=utf-8');
	$registry->set('response', $response);

	$db_port = defined('DB_PORT') ? DB_PORT : null;
	$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, $db_port);
	$registry->set('db', $db);

	$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `store_id` = '0'");
	foreach ($query->rows as $setting) {
		if (!empty($setting['serialized'])) {
			$config->set($setting['key'], json_decode($setting['value'], true));
		} else {
			$config->set($setting['key'], $setting['value']);
		}
	}

	$session = new stdClass();
	$session->data = [];
	$registry->set('session', $session);

	$cache_engine = $config->get('cache_engine') ?: 'file';
	$cache_expire = $config->get('cache_expire') ?: 3600;
	$registry->set('cache', new Cache($cache_engine, $cache_expire));

	$log_file = $config->get('error_filename') ?: 'error.log';
	$registry->set('log', new Log($log_file));

	$language_code = $config->get('config_admin_language') ?: ($config->get('config_language') ?: 'en-gb');
	$language = new Language($language_code);
	$language->load($language_code);
	$registry->set('language', $language);

	$registry->set('url', new Url(HTTP_SERVER, HTTPS_SERVER));
	$registry->set('document', new Document());

	if (class_exists('Cart\\Currency')) {
		$registry->set('currency', new Cart\Currency($registry));
	} elseif (class_exists('Currency')) {
		$registry->set('currency', new Currency($registry));
	}

	$registry->set('user', new class {
		public function hasPermission($type, $route) { return true; }
		public function getGroupId() { return 1; }
		public function isLogged() { return true; }
	});

	$controller_file = DIR_APPLICATION . 'controller/extension/feed/nix.php';
	if (!is_file($controller_file)) {
		throw new Exception('Controller extension/feed/nix.php not found');
	}

	require_once $controller_file;
	$controller = new ControllerExtensionFeedNix($registry);
	$controller->cron();
	$response->output();
} catch (Throwable $e) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode([
		'status' => 'Error',
		'msg' => 'ImportXML Clean cron fatal error: ' . $e->getMessage()
	]);
}
