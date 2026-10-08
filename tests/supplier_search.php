<?php
define('DB_PREFIX','test_');
class Model { public $db; public $config; }
class SearchConfig { public function get($key) { return 1; } }
class SearchDb {
    public $field; public $sql;
    public function escape($value) { return addslashes($value); }
    public function query($sql) {
        $this->sql=$sql;
        $row=array('product_id'=>7,'model'=>'','sku'=>'','ean'=>'','upc'=>'','jan'=>'','isbn'=>'','mpn'=>'','price'=>100,'quantity'=>33,'name'=>'Example');
        $row[$this->field]='000123';
        $rows=strpos($sql,"TRIM(p.".$this->field.") = '000123'")!==false?array($row):array();
        return (object)array('rows'=>$rows,'row'=>$rows?$rows[0]:array(),'num_rows'=>count($rows));
    }
}
require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/admin/model/extension/module/supplier_sync_parser_pro.php';
$failures=0;
foreach (array('model','sku','ean','upc','jan','isbn','mpn') as $field) {
    $m=new ModelExtensionModuleSupplierSyncParserPro();$m->config=new SearchConfig();$m->db=new SearchDb();$m->db->field=$field;
    $rows=$m->searchProducts('000123');
    $ok=count($rows)===1 && $rows[0]['similarity']===100;
    echo ($ok?'PASS ':'FAIL ').'Manual selector finds exact '.$field.PHP_EOL;if (!$ok) {$failures++;}
}
exit($failures?1:0);
