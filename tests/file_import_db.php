<?php
// Included by the dedicated MariaDB integration test; no live database is used.
if (!defined('DIR_SYSTEM')) { define('DIR_SYSTEM',dirname(__DIR__).'/modules/import_export_pro/upload/system/'); }
if (!defined('DIR_STORAGE')) { define('DIR_STORAGE',dirname(__DIR__).'/.local/import_test_storage/'); }
if (!defined('DIR_DOWNLOAD')) { define('DIR_DOWNLOAD',DIR_STORAGE); }
if (!is_dir(DIR_STORAGE.'import_pro')) { mkdir(DIR_STORAGE.'import_pro',0750,true); }
require dirname(__DIR__).'/modules/import_export_pro/upload/catalog/model/extension/module/import_pro.php';
require dirname(__DIR__).'/modules/import_export_pro/upload/admin/model/extension/module/import_pro_queue.php';
class ImportIntegrationLog { public function write($s) {} }
class ImportIntegrationLanguages { public $db; public function getLanguages() { return $this->db->query('SELECT * FROM oc_language WHERE status=1')->rows; } }
$importer=new ModelExtensionModuleImportPro();$importer->db=$db;$importer->config=new IntegrationConfig();$importer->load=new IntegrationLoader();$importer->model_setting_setting=$settings_model;$importer->log=new ImportIntegrationLog();
$queue_model=new ModelExtensionModuleImportProQueue();$queue_model->db=$db;$queue_model->config=new IntegrationConfig();$importer->model_extension_module_import_pro_queue=$queue_model;
$languages_model=new ImportIntegrationLanguages();$languages_model->db=$db;$importer->model_localisation_language=$languages_model;
$importer->install();
$file=DIR_STORAGE.'import_pro/fixture.csv';file_put_contents($file,"code;price;qty\n0001;1000;0\n0002;900;0\nUNKNOWN;800;0\n");
$profile=$importer->saveProfile(array('name'=>'Price fixture','format'=>'csv','source_type'=>'file','source_path'=>$file,'match_field'=>'sku','key_field'=>'sku','delimiter'=>';','start_row'=>1,'default_language_id'=>1,'create_new'=>0,'update_existing'=>1,'update_price'=>1,'update_quantity'=>0,'field_map_json'=>json_encode(array('sku'=>'code','price'=>'price','quantity'=>'qty'))));
$before=$db->query('SELECT product_id,quantity FROM oc_product ORDER BY product_id')->rows;
$a=$importer->runScheduledImport($profile,false,1,'price_only');
file_put_contents($file,"code;price;qty\n0001;5000;0\n0002;5000;0\nUNKNOWN;5000;0\n");
$b=$importer->runScheduledImport($profile,false,1,'price_only');
$c=$importer->runScheduledImport($profile,false,1,'price_only');
$checks['Real file cron updates all batches from frozen price file'] = !$c['has_more'] && (float)$db->query("SELECT price FROM oc_product WHERE sku='0001'")->row['price']===1000.0 && (float)$db->query("SELECT price FROM oc_product WHERE sku='0002'")->row['price']===900.0;
if (!$checks['Real file cron updates all batches from frozen price file']) { echo 'DEBUG file cron '.json_encode(array($a,$b,$c,$db->query('SELECT sku,price FROM oc_product')->rows),JSON_UNESCAPED_UNICODE).PHP_EOL; }
$checks['Real file cron preserves quantity and never creates missing products'] = $before===$db->query('SELECT product_id,quantity FROM oc_product ORDER BY product_id')->rows && !$db->query("SELECT product_id FROM oc_product WHERE sku='UNKNOWN'")->num_rows;
$importer->runScheduledImport($profile,false,100,'price_stock');
$checks['Price-stock cron honors disabled quantity setting'] = $before===$db->query('SELECT product_id,quantity FROM oc_product ORDER BY product_id')->rows;
