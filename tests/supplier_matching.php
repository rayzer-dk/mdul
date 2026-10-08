<?php
define('DB_PREFIX','test_');
require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
class IdentifierConfig { public function get($key) { return 1; } }
class IdentifierDb {
    public $ids = array();
    public $queries = array();
    public function escape($value) { return addslashes($value); }
    public function query($sql) {
        $this->queries[]=$sql; $rows=array();
        foreach ($this->ids as $field=>$ids) {
            if (strpos($sql,'TRIM(p.`'.$field.'`)') !== false) {
                foreach ($ids as $id) { $rows[]=array('product_id'=>$id,'price'=>100,'quantity'=>7,'stock_status_id'=>5,'name'=>'Example'); }
            }
        }
        return (object)array('num_rows'=>count($rows),'rows'=>$rows,'row'=>$rows ? $rows[0] : array());
    }
}
class IdentifierParser extends CodecartSupplierSyncParser {
    public function __construct() { $this->db=new IdentifierDb(); $this->config=new IdentifierConfig(); }
    public function match($data,$settings=array()) { return $this->findProduct(0,$data['sku'] ?? '', '',$data['name'] ?? '',$data['ean'] ?? '',$data['upc'] ?? '',$data['mpn'] ?? '',array('settings'=>json_encode($settings)),$data); }
    public function identifiers($ids) { $this->db->ids=$ids; }
    public function queries() { return implode("\n",$this->db->queries); }
}
$failures=0;
function checkIdentifier($ok,$label) { global $failures; echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL; if (!$ok) {$failures++;} }
foreach (array('sku','model','upc','ean','jan','isbn','mpn') as $target) {
    $p=new IdentifierParser();$p->identifiers(array($target=>array(42)));
    $r=$p->match(array('sku'=>'0001'),array('match_source'=>'sku','match_target'=>$target));
    checkIdentifier($r['product_id']===42 && $r['source']==='product_'.$target,'Supplier SKU maps to '.$target);
    checkIdentifier(strpos($p->queries(),"= '0001'")!==false,'Leading zero remains for '.$target);
}
$p=new IdentifierParser();$p->identifiers(array('sku'=>array(1),'ean'=>array(2)));
checkIdentifier($p->match(array('sku'=>'A','ean'=>'00001'))['product_id']===0,'Conflicting strong identifiers require review');
$p=new IdentifierParser();$p->identifiers(array('mpn'=>array(1,2)));
checkIdentifier($p->match(array('sku'=>'A'),array('match_source'=>'sku','match_target'=>'mpn'))['product_id']===0,'Duplicate selected field requires review');
$p=new IdentifierParser();$p->identifiers(array('sku'=>array(1)));
checkIdentifier($p->match(array('sku'=>'A'),array('match_source'=>'sku','match_target'=>'price'))['product_id']===0,'Invalid matching column fails closed');
foreach (array('jan','isbn','model') as $source) {
    $p=new IdentifierParser();$p->identifiers(array($source=>array(9)));
    checkIdentifier($p->match(array($source=>'000123'),array('match_source'=>$source,'match_target'=>$source))['product_id']===9,'Separate '.$source.' source is supported');
}
foreach (array('jan','isbn','model') as $source) {
    $p=new IdentifierParser();
    $r=$p->match(array($source=>'NOT-FOUND','name'=>'Example'));
    checkIdentifier($r['product_id']===0 && strpos($p->queries(),'LOWER(TRIM(pd.name))')===false,'Unmatched '.$source.' does not fall back to name');
}
exit($failures?1:0);
