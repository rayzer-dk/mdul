<?php
define('DB_PREFIX','test_');
require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
class CronPolicyConfig { public function get($key) { return 1; } }
class CronPolicyParser extends CodecartSupplierSyncParser {
    public $applied = array();
    public function __construct() { $this->config = new CronPolicyConfig(); }
    public function applySelectedReviews($ids,$mode,$supplier_id=0) { $this->applied[]=array($ids,$mode,$supplier_id); return array('updated'=>1,'price_updated'=>$mode!=='update_stock'?1:0,'stock_updated'=>$mode!=='update_price'?1:0,'errors'=>0,'no_change'=>0); }
    public function run($source,$review,$match,$supplier) { $result=array('updated'=>0,'price_updated'=>0,'stock_updated'=>0,'errors'=>0,'no_change'=>0); $this->applyScheduledReview($source,$review,$match,$supplier,$result); return $result; }
}
if (!method_exists('CodecartSupplierSyncParser','applyScheduledReview')) { echo 'FAIL Cron does not apply verified existing rows'.PHP_EOL;exit(1); }
$failures=0;
$cases=array(
    'price only'=>array('cron','pending','product_mpn',1,1,0,42,'update_price'),
    'price and stock'=>array('cron','pending','supplier_url_link',1,1,1,42,'update_price_stock'),
    'stock only'=>array('cron','pending','product_ean',1,0,1,42,'update_stock'),
    'manual stays preview'=>array('manual','pending','product_sku',1,1,1,42,''),
    'automation off'=>array('cron','pending','product_sku',0,1,1,42,''),
    'price warning stays preview'=>array('cron','price_warning','product_sku',1,1,1,42,''),
    'new stays preview'=>array('cron','pending','',1,1,1,0,''),
    'name requires review'=>array('cron','pending','product_name_exact',1,1,1,42,''),
    'excluded stays preview'=>array('cron','excluded','product_sku',1,1,1,42,''),
    'both fields off'=>array('cron','pending','product_sku',1,0,0,42,'')
);
foreach ($cases as $label=>$c) {
    $p=new CronPolicyParser();
    $p->run($c[0],array('review_id'=>7,'status'=>$c[1]),array('product_id'=>$c[6],'source'=>$c[2]),array('supplier_id'=>3,'auto_apply_existing'=>$c[3],'update_price'=>$c[4],'update_stock'=>$c[5]));
    $ok=$c[7]==='' ? !$p->applied : count($p->applied)===1 && $p->applied[0][1]===$c[7];
    echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL;if (!$ok) {$failures++;}
}
exit($failures?1:0);
