<?php
require dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
class FixtureConfig {
    public function get($key) { return $key === 'config_currency' ? 'UAH' : 1; }
}
class FixtureRegistry { public function get($key) { return $key === 'config' ? new FixtureConfig() : null; } }
class FixtureParser extends CodecartSupplierSyncParser {
    public $syntheticRussian = false;
    public function fetchUrl($url, $supplier = array()) {
        $path = dirname(__DIR__).'/.local/'.(strpos($url, '/ua/') !== false ? 'spilna_product.html' : 'spilna_product_ru.html');
        $body = file_get_contents($path);
        if ($this->syntheticRussian && strpos($url, '/ua/') === false) {
            $body = str_replace('lang="uk"', 'lang="ru"', $body);
            $body = str_replace('Чавунна', 'Чугунная', $body);
        }
        return array('ok'=>true, 'body'=>$body);
    }
    protected function isSupplierCurrencyValid($currency) { return true; }
    protected function getCurrencyRate($currency) { return 1; }
    protected function checkExclusionRules($data, $supplier, $url) { return array('excluded'=>false); }
}
$supplier = json_decode(file_get_contents(dirname(__DIR__).'/modules/supplier_sync_parser_pro/upload/system/library/codecart/presets/spilna_meta.json'), true);
$supplier['settings'] = json_encode($supplier['settings']);
$supplier += array('supplier_id'=>1, 'default_stock_status_id'=>5, 'default_category_id'=>0, 'discount_percent'=>0, 'markup_percent'=>0, 'rounding_mode'=>'two', 'manufacturer_xpath'=>'', 'target_language_id'=>1);
$parser = new FixtureParser(new FixtureRegistry());
$result = $parser->parseProduct($supplier, 'https://spilna-meta.com.ua/ua/p58279077-chugunnaya-chashechnaya-poilka.html');
if (!$result['ok']) { echo $result['message']; exit(1); }
$d = $result['data'];
if (in_array('--debug', $argv, true)) { echo json_encode(array_keys($d['localized'] ?? array()), JSON_UNESCAPED_UNICODE).PHP_EOL; }
$checks = array(
    'SKU without label'=>$d['sku'] === 'F-105',
    'Price'=>$d['sale_price'] === 1420.0,
    'Textual availability'=>$d['quantity'] === 100,
    'Manufacturer'=>$d['manufacturer'] === 'Farma',
    'Attributes'=>count($d['attributes']) >= 8,
    'Description'=>strpos($d['description'], 'Нідерланди') !== false,
    'Category path'=>strpos($d['category_text'], 'Чашкові') !== false && strpos($d['category_text'], '{') === false,
    'Full-size gallery'=>count($d['additional_image_urls']) === 2 && strpos(implode(' ', $d['additional_image_urls']), '_w80_h80') === false,
    'Redirected UA is not mislabeled RU'=>isset($d['localized']['uk-ua']) && !isset($d['localized']['ru-ru'])
);
$parser->syntheticRussian = true;
$synthetic = $parser->parseProduct($supplier, 'https://spilna-meta.com.ua/ua/p58279077-chugunnaya-chashechnaya-poilka.html');
$checks['Separate localized pages'] = isset($synthetic['data']['localized']['ru-ru']) && $synthetic['data']['localized']['ru-ru']['description'] !== $d['description'];
$failed = 0;
foreach ($checks as $label=>$ok) { echo ($ok?'PASS ':'FAIL ').$label.PHP_EOL; if (!$ok) {$failed++;} }
exit($failed?1:0);
