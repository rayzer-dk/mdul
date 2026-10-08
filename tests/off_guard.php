<?php
require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
class OffConfig { public function get($key) { return 0; } }
class OffDb { public function query($sql) { throw new Exception('OFF attempted SQL'); } }
class OffRegistry { public function get($key) { return $key === 'db' ? new OffDb() : new OffConfig(); } }
$parser = new CodecartSupplierSyncParser(new OffRegistry());
$calls = array('buildQueue'=>array(1), 'processQueueBatch'=>array(1), 'testProductUrl'=>array(1,'https://example.com'), 'autoDetectRules'=>array(1,'https://example.com'), 'autoDetectListRules'=>array(1,'https://example.com'), 'applySelectedReviews'=>array(array(1),'update_price'), 'applySelectedNewProducts'=>array(array(1)), 'manualMatchReviewProduct'=>array(1,1), 'parseListPage'=>array(array(),'https://example.com'), 'parseProduct'=>array(array(),'https://example.com'), 'autoDetectFeedRules'=>array(1), 'fetchUrl'=>array('https://example.com'));
$failures = 0;
$calls['rollbackPriceStockHistory'] = array(1,1);
$calls['runCronCycle'] = array(1,5);
foreach ($calls as $method=>$args) {
    try { $result = $parser->$method(...$args); $ok = isset($result['ok']) && $result['ok'] === false && $result['message'] === 'Module is disabled'; }
    catch (Throwable $e) { $ok=false; }
    echo ($ok ? 'PASS ' : 'FAIL ').'OFF '.$method.PHP_EOL;
    if (!$ok) { $failures++; }
}
exit($failures ? 1 : 0);
