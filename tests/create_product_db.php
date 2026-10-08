<?php
// This test uses a dedicated empty local database created by the test launcher.
define('DB_PREFIX', 'oc_');
require getenv('SSP_TEST_SERVICE') ?: dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
class IntegrationDb {
    public $connection;
    public function __construct() {
        $this->connection = new mysqli('127.0.0.1', 'root', getenv('SSP_TEST_PASSWORD'), 'ccp_import_test', (int)getenv('SSP_TEST_PORT'));
        $this->connection->set_charset('utf8mb4');
        $this->query("SET SESSION sql_mode='STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION'");
    }
    public function query($sql) {
        $result = $this->connection->query($sql);
        $rows = $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : array();
        return (object)array('rows'=>$rows, 'row'=>$rows ? $rows[0] : array(), 'num_rows'=>count($rows));
    }
    public function escape($value) { return $this->connection->real_escape_string((string)$value); }
    public function getLastId() { return $this->connection->insert_id; }
    public function beginTransaction() { $this->query('START TRANSACTION'); }
    public function commit() { $this->query('COMMIT'); }
    public function rollback() { $this->query('ROLLBACK'); }
}
class IntegrationConfig { public function get($key) { if ($key === 'module_supplier_sync_parser_pro_stop_queue') { return 0; } return $key === 'config_currency' ? 'UAH' : 1; } }
class IntegrationRegistry {
    private $db;
    public function __construct($db) { $this->db = $db; }
    public function get($key) { return $key === 'db' ? $this->db : new IntegrationConfig(); }
}
class IntegrationParser extends CodecartSupplierSyncParser {
    public $failAfterInsert = false;
    public $recordHistory = false;
    public $cronFixture = array();
    public $cronParseError = false;
    public function create($data, $supplier) { return $this->createNewProduct($data, $supplier, 'https://example.com/p1'); }
    public function update($id, $data, $supplier) { return $this->updateExistingProduct($id, $data, $supplier, 'https://example.com/p1'); }
    public function queueFixture($supplier_id,$data) { return $this->addQueueJob($supplier_id, 'import_item', 'https://example.com/cron-product', array('parsed_data'=>$data)); }
    public function parseProduct($supplier,$url,$context=array()) { if ($this->cronParseError) { return array('ok'=>false,'message'=>'Supplier temporarily unavailable'); } return $this->cronFixture ? array('ok'=>true,'data'=>$this->cronFixture) : parent::parseProduct($supplier,$url,$context); }
    protected function savePurchasePrice($supplier_id, $product_id, $url = '', $purchase_price = 0, $supplier_price = 0, $sale_price = 0) {
        if ($this->failAfterInsert) { throw new Exception('Injected failure after product creation'); }
    }
    protected function addHistory($supplier_id, $product_id, $url, $action, $field, $old_price, $new_price, $old_quantity, $new_quantity, $old_stock_status_id, $new_stock_status_id, $supplier_price, $sale_price, $note) {
        if ($this->recordHistory) { parent::addHistory($supplier_id, $product_id, $url, $action, $field, $old_price, $new_price, $old_quantity, $new_quantity, $old_stock_status_id, $new_stock_status_id, $supplier_price, $sale_price, $note); }
    }
}
$db = new IntegrationDb();
$parser = new IntegrationParser(new IntegrationRegistry($db));
$data = array('name'=>'Test product', 'sku'=>'0001', 'model'=>'F-105', 'sale_price'=>1420, 'supplier_price'=>1420, 'purchase_price'=>1000, 'quantity'=>100, 'stock_status_id'=>5, 'description'=>'Український опис', 'localized'=>array('ru-ru'=>array('name'=>'Тестовый товар','description'=>'Русское описание')));
$supplier = array('supplier_id'=>1, 'create_new_status'=>0, 'target_language_id'=>1, 'fill_all_languages'=>0, 'settings'=>json_encode(array('new_category_name'=>'Review category')));
try {
    $id = $parser->create($data, $supplier);
    $product = $db->query("SELECT * FROM oc_product WHERE product_id=".(int)$id)->row;
    if (in_array('--debug', $argv, true)) { echo json_encode(array($id, $product['status'], $product['sku'])).PHP_EOL; }
    $descriptions = $db->query("SELECT language_id, description, meta_h1 FROM oc_product_description WHERE product_id=".(int)$id." ORDER BY language_id")->rows;
    $checks = array(
        'Created disabled with leading zero SKU'=>$id > 0 && (int)$product['status'] === 0 && $product['sku'] === '0001',
        'Strict custom product fields'=>(float)$product['price_purchasing'] === 1000.0 && (float)$product['price_rrp'] === 1420.0 && (int)$product['nix_supplier_id'] === 0,
        'Per-language descriptions'=>$descriptions[0]['description'] === 'Український опис' && $descriptions[1]['description'] === 'Русское описание' && $descriptions[2]['description'] === ''
    );
    $category = $db->query("SELECT c.category_id, c.status FROM oc_category c INNER JOIN oc_category_description cd ON cd.category_id=c.category_id WHERE cd.name='Review category' AND cd.language_id=1")->row;
    $checks['New review category stays disabled'] = $category && (int)$category['status'] === 0;
    $checks['Product main category assigned'] = (int)$db->query("SELECT category_id FROM oc_product_to_category WHERE product_id=".(int)$id." AND main_category=1")->row['category_id'] === (int)$category['category_id'];
    $parser->create(array_merge($data,array('sku'=>'0002')), $supplier);
    $checks['Review category reused'] = (int)$db->query("SELECT COUNT(*) AS total FROM oc_category")->row['total'] === 1;
    $parser->failAfterInsert = true;
    try { $parser->create(array_merge($data, array('sku'=>'FAIL')), array_merge($supplier, array('settings'=>json_encode(array('new_category_name'=>'Failed category'))))); } catch (Exception $e) {}
    $checks['Failed creation rolls back'] = !$db->query("SELECT product_id FROM oc_product WHERE sku='FAIL'")->num_rows;
    $checks['Failed category creation rolls back'] = !$db->query("SELECT category_id FROM oc_category_description WHERE name='Failed category'")->num_rows;
    try { $parser->update($id, array_merge($data,array('sale_price'=>1500,'quantity'=>25)), $supplier); } catch (Exception $e) {}
    $after = $db->query('SELECT price, quantity FROM oc_product WHERE product_id='.(int)$id)->row;
    $checks['Failed existing update rolls back'] = (float)$after['price'] === 1420.0 && (int)$after['quantity'] === 100;
    $parser->failAfterInsert = false;
    $parser->update($id, array_merge($data,array('sale_price'=>1500,'quantity'=>25)), array_merge($supplier,array('update_stock'=>0)));
    $after = $db->query('SELECT price, quantity FROM oc_product WHERE product_id='.(int)$id)->row;
    $checks['Price updated with stock preserved'] = (float)$after['price'] === 1500.0 && (int)$after['quantity'] === 100;
    if (getenv('SSP_TEST_OC_ROOT')) {
        class Model { public $db; public $config; public $load; public $model_setting_setting; public $user; public $log; public $language; public $model_extension_module_import_pro_queue; public $model_localisation_language; }
        class IntegrationLoader { public function model($route) {} }
        require getenv('SSP_TEST_OC_ROOT').'/admin/model/setting/setting.php';
        require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/admin/model/extension/module/supplier_sync_parser_pro.php';
        $settings_model = new ModelSettingSetting(); $settings_model->db = $db;
        $module = new ModelExtensionModuleSupplierSyncParserPro();
        $module->db=$db; $module->config=new IntegrationConfig(); $module->load=new IntegrationLoader(); $module->model_setting_setting=$settings_model;
        $module->install('1.6.3');
        $checks['Install keeps module off'] = (int)$settings_model->getSetting('module_supplier_sync_parser_pro')['module_supplier_sync_parser_pro_status'] === 0;
        $supplier_id=$module->saveSupplier(array('name'=>'Test profile','base_url'=>'https://example.com','currency_code'=>'UAH','source_language_code'=>'uk-ua','target_language_id'=>1,'source_adapter'=>'prom','in_stock_quantity'=>250,'unknown_stock_policy'=>'keep','update_stock_status'=>0,'new_category_name'=>'Review category'));
        $saved=json_decode($module->getSupplier($supplier_id)['settings'],true);
        $checks['Supplier policies persist'] = $saved['in_stock_quantity'] === 250 && $saved['update_stock_status'] === 0 && $saved['source_adapter'] === 'prom' && $saved['new_category_name'] === 'Review category';
        $module->install('1.6.3');
        $checks['Repeated install preserves profile'] = json_decode($module->getSupplier($supplier_id)['settings'],true) === $saved;
        $module->uninstall();
        $checks['Uninstall disables and preserves data'] = (int)$settings_model->getSetting('module_supplier_sync_parser_pro')['module_supplier_sync_parser_pro_status'] === 0 && (bool)$module->getSupplier($supplier_id) && (bool)$db->query('SELECT product_id FROM oc_product WHERE product_id='.(int)$id)->num_rows;
        $module->install('1.6.3');
        $checks['Reinstall preserves profile'] = (bool)$module->getSupplier($supplier_id);
        $parser->recordHistory = true;
        $supplier['supplier_id'] = $supplier_id;
        $parser->update($id, array_merge($data,array('sale_price'=>1600,'quantity'=>25)), $supplier);
        $history_id=(int)$db->query('SELECT MAX(history_id) AS id FROM oc_ccp_ssp_history')->row['id'];
        $rollback=$parser->rollbackPriceStockHistory($history_id,$supplier_id);
        $after=$db->query('SELECT price, quantity FROM oc_product WHERE product_id='.(int)$id)->row;
        $checks['Completed price/stock history restores safely'] = $rollback['ok'] && (float)$after['price'] === 1500.0 && (int)$after['quantity'] === 100;
        $checks['History cannot be restored twice'] = !$parser->rollbackPriceStockHistory($history_id,$supplier_id)['ok'];
        $parser->update($id, array_merge($data,array('sale_price'=>1600,'quantity'=>25)), $supplier);
        $history_id=(int)$db->query('SELECT MAX(history_id) AS id FROM oc_ccp_ssp_history')->row['id'];
        $db->query('UPDATE oc_product SET price=1700 WHERE product_id='.(int)$id);
        $rollback=$parser->rollbackPriceStockHistory($history_id,$supplier_id);
        $checks['Conflicting later edits block restoration'] = !$rollback['ok'] && $rollback['code'] === 'rollback_conflict' && (float)$db->query('SELECT price FROM oc_product WHERE product_id='.(int)$id)->row['price'] === 1700.0;
        $cron_supplier=$module->saveSupplier(array('name'=>'Cron profile','status'=>1,'base_url'=>'https://example.com','name_xpath'=>'//h1','price_xpath'=>"//*[@itemprop='price']",'sku_xpath'=>"//*[@itemprop='sku']",'currency_code'=>'UAH','target_language_id'=>1,'auto_apply_existing'=>1,'update_price'=>1,'update_stock'=>0,'match_source'=>'sku','match_target'=>'sku','cron_enabled'=>1,'cron_interval_minutes'=>5));
        $before_cron=$db->query('SELECT quantity FROM oc_product WHERE product_id='.(int)$id)->row;
        $cron_data=array_merge($data,array('category_allowed'=>1,'sale_price'=>1710,'quantity'=>22,'stock_text'=>'In stock','is_excluded'=>0,'url'=>'https://example.com/cron-product'));
        $parser->queueFixture($cron_supplier,$cron_data);
        $run=$parser->processQueueBatch($cron_supplier,5,'cron');
        $after=$db->query('SELECT price,quantity FROM oc_product WHERE product_id='.(int)$id)->row;
        $checks['Actual cron queue applies price and preserves stock'] = (int)$run['updated']===1 && (float)$after['price']===1710.0 && (int)$after['quantity']===(int)$before_cron['quantity'];
        $settings=json_decode($module->getSupplier($cron_supplier)['settings'],true);
        $checks['Matching and cron settings persist'] = $settings['match_source']==='sku' && $settings['match_target']==='sku' && $settings['cron_enabled']===1 && $settings['cron_interval_minutes']===5;
        $checks['Cron cycle API exists'] = method_exists($parser,'runCronCycle');
        $parser->cronFixture=array_merge($cron_data,array('sale_price'=>1720));
        $cycle=$parser->runCronCycle($cron_supplier,5);
        $checks['Due cron cycle rechecks recently finished product'] = !empty($cycle['updated']) && (float)$db->query('SELECT price FROM oc_product WHERE product_id='.(int)$id)->row['price']===1720.0;
        $cycle=$parser->runCronCycle($cron_supplier,5);
        $checks['Repeated cron respects saved interval'] = $cycle['ok'] && (int)$cycle['processed']===0;
        $db->query("UPDATE oc_setting SET value='0' WHERE `key`='ccp_ssp_cron_started_".(int)$cron_supplier."'");
        $parser->cronFixture['sale_price']=1730;
        $cycle=$parser->runCronCycle($cron_supplier,5);
        $checks['Next due cycle updates the same URL again'] = !empty($cycle['updated']) && (float)$db->query('SELECT price FROM oc_product WHERE product_id='.(int)$id)->row['price']===1730.0;
        $db->query("UPDATE oc_setting SET value='0' WHERE `key`='ccp_ssp_cron_started_".(int)$cron_supplier."'");
        $parser->cronParseError=true;
        $parser->runCronCycle($cron_supplier,5);
        $link=$db->query('SELECT product_id FROM oc_ccp_ssp_product_link WHERE supplier_id='.(int)$cron_supplier)->row;
        $checks['Temporary parse error preserves product link'] = (int)$link['product_id']===$id;
        if (!$checks['Temporary parse error preserves product link']) { echo 'DEBUG link '.json_encode($db->query('SELECT link_id,product_id,supplier_product_url,last_status FROM oc_ccp_ssp_product_link WHERE supplier_id='.(int)$cron_supplier)->rows).' expected '.json_encode($id).PHP_EOL; }
        $competing=new IntegrationDb();
        $mutex_method=new ReflectionMethod($parser,'queueMutexName');if (PHP_VERSION_ID<80100) {$mutex_method->setAccessible(true);}$mutex=$mutex_method->invoke($parser);
        $competing->query("SELECT GET_LOCK('".$competing->escape($mutex)."',0)");
        $checks['Concurrent worker is refused'] = !$parser->runCronCycle($cron_supplier,5)['ok'];
        $competing->query("SELECT RELEASE_LOCK('".$competing->escape($mutex)."')");
        require __DIR__.'/file_import_db.php';
    }
    $failures = 0;
    foreach ($checks as $label=>$ok) { echo ($ok ? 'PASS ' : 'FAIL ').$label.PHP_EOL; if (!$ok) {$failures++;} }
    exit($failures ? 1 : 0);
} catch (Throwable $e) { echo 'FAIL Strict schema creation: '.$e->getMessage().PHP_EOL; exit(1); }
