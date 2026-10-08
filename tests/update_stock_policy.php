<?php
class TestConfig { public function get($key) { return 1; } }
class TestResult {
    public $num_rows = 1;
    public $row = array('price'=>1420, 'quantity'=>37, 'stock_status_id'=>7);
}
class TestDb {
    public $queries = array();
    public function query($sql) { $this->queries[] = $sql; return new TestResult(); }
    public function escape($value) { return addslashes($value); }
}
define('DB_PREFIX', 'test_');
require dirname(__DIR__) . '/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
class StockPolicyParser extends CodecartSupplierSyncParser {
    public function __construct() { $this->db = new TestDb(); $this->config = new TestConfig(); }
    public function apply($data, $supplier, $stock) { return $this->updateExistingProduct(1, $data, $supplier, '', false, $stock); }
    public function sql() { return implode("\n", $this->db->queries); }
    protected function addHistory($supplier_id, $product_id, $url, $action, $field, $old_price, $new_price, $old_quantity, $new_quantity, $old_stock_status_id, $new_stock_status_id, $supplier_price, $sale_price, $note) {}
}
$failures = 0;
foreach (array('unknown', 'off', 'quantity_only', 'supplier_off') as $case) {
    $parser = new StockPolicyParser();
    $data = array('quantity'=>$case === 'unknown' ? null : 100, 'stock_status_id'=>$case === 'unknown' ? null : 5);
    $parser->apply($data, array('supplier_id'=>1, 'update_stock'=>$case === 'supplier_off' ? 0 : 1, 'settings'=>json_encode(array('update_stock_status'=>0))), $case !== 'off');
    $sql = $parser->sql();
    $ok = $case === 'quantity_only' ? strpos($sql, "quantity = '100'") !== false && strpos($sql, "stock_status_id = '5'") === false : strpos($sql, 'UPDATE') === false;
    echo ($ok ? 'PASS ' : 'FAIL ') . $case . PHP_EOL;
    if (!$ok) { $failures++; }
}
exit($failures ? 1 : 0);
