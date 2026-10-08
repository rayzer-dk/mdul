<?php
class Model { public $db; public $config; }
class MatchingDb {
    public $sql = '';
    public $count = 2;
    public function escape($value) { return addslashes($value); }
    public function query($sql) { $this->sql = $sql; return (object)array('num_rows'=>$this->count,'row'=>array('product_id'=>11)); }
}
class MatchingConfig { public function get($key) { return 1; } }
define('DB_PREFIX', 'oc_');
$side = isset($matching_side) ? $matching_side : 'admin';
require dirname(__DIR__).'/modules/import_export_pro/upload/'.$side.'/model/extension/module/import_pro.php';
$model = new ModelExtensionModuleImportPro();
$model->db = new MatchingDb();
$model->config = new MatchingConfig();
$method = new ReflectionMethod($model, 'findExistingProduct');
if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
$rejected = false;
try { $method->invoke($model, 'model', 'GTR-0888', 1); } catch (Exception $e) { $rejected = true; }
echo ($rejected ? 'PASS ' : 'FAIL ').'Duplicate identifiers never select an arbitrary first product'.PHP_EOL;
$model->db->count = 1;
$method->invoke($model, 'name', 'Example product', 3);
$name = strpos($model->db->sql, 'product_description') !== false && strpos($model->db->sql, "language_id = '3'") !== false;
echo ($name ? 'PASS ' : 'FAIL ').'Name matching uses the selected language'.PHP_EOL;
exit($rejected && $name ? 0 : 1);
