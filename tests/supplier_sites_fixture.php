<?php
require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
class SitesConfig { public function get($key) { return $key === 'config_currency' ? 'UAH' : 1; } }
class SitesRegistry { public function get($key) { return $key === 'config' ? new SitesConfig() : null; } }
class SitesParser extends CodecartSupplierSyncParser {
    protected function isSupplierCurrencyValid($currency) { return true; }
    protected function getCurrencyRate($currency) { return 1; }
    protected function checkExclusionRules($data,$supplier,$url) { return array('excluded'=>false); }
    public function fetchUrl($url,$supplier=array()) {
        return array('ok'=>true,'body'=>file_get_contents(dirname(__DIR__).'/.local/'.(strpos($url,'sazagro')!==false?'sazagro':'rewolt').'_product.html'));
    }
}
$failed=0;
foreach (array('sazagro','rewolt') as $code) {
    $s=json_decode(file_get_contents(dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/presets/'.($code==='sazagro'?'prom_modern':'opencart_html').'.json'),true);
    $s['base_url']=$code==='sazagro'?'https://sazagro.com.ua':'https://rewolt.com.ua';
    $s['currency_code']='UAH';
    $s['source_language_code']='uk-ua';
    $s['settings']=json_encode($s['settings']);
    $s+=array('supplier_id'=>1,'default_stock_status_id'=>5,'default_category_id'=>0,'discount_percent'=>0,'markup_percent'=>0,'rounding_mode'=>'two','target_language_id'=>1);
    $url=$code==='sazagro'?'https://sazagro.com.ua/ua/p1192356509-kollektor-molochnyj-poparnyj.html':'https://rewolt.com.ua/ua/mini_azs/zapravka_na_220v_50l_min';
    $r=(new SitesParser(new SitesRegistry()))->parseProduct($s,$url);
    if (empty($r['ok'])) { echo 'FAIL '.$code.' '.json_encode($r,JSON_UNESCAPED_UNICODE).PHP_EOL; $failed++; continue; }
    $d=$r['data'];
    if ($code==='rewolt') { $category_ok=$d['category_text']==='Міні АЗС'; echo ($category_ok?'PASS ':'FAIL ').'rewolt product category'.PHP_EOL; if (!$category_ok) {$failed++;} }
    $checks=array('identifier'=>$d['sku']===($code==='sazagro'?'8680640083476':'RE SL011-220V'),'visible UAH price'=>$d['sale_price']===($code==='sazagro'?600.0:9094.0),'availability'=>$d['quantity']===100,'description'=>strlen($d['description'])>100,'attributes'=>count($d['attributes'])>=5,'gallery'=>count($d['additional_image_urls'])>=1,'main image'=>$d['main_image_url']!=='' && strpos($d['main_image_url'],'logo')===false);
    foreach ($checks as $name=>$ok) { echo ($ok?'PASS ':'FAIL ').$code.' '.$name.PHP_EOL; if (!$ok) {$failed++;} }
    if (in_array('--debug',$argv,true)) { echo json_encode($d,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL; }
}
exit($failed?1:0);
