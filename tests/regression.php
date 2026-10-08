<?php
// Run with PHP 8.1+ and zip, mbstring, SimpleXML, DOM enabled.
class Model { public $config; }
$regression_side = isset($regression_side) ? $regression_side : 'admin';
require dirname(__DIR__) . '/modules/import_export_pro/upload/' . $regression_side . '/model/extension/module/import_pro.php';
require dirname(__DIR__) . '/modules/supplier_sync_parser_pro/upload/system/library/codecart/supplier_sync_parser.php';
$failures = 0;
function check($condition, $label) {
    global $failures;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . PHP_EOL;
    if (!$condition) { $failures++; }
}
function invokeMethod($object, $name, $args) {
    $method = new ReflectionMethod($object, $name);
    if (PHP_VERSION_ID < 80100) { $method->setAccessible(true); }
    return $method->invokeArgs($object, $args);
}
$model = new ModelExtensionModuleImportPro();
if ($regression_side === 'admin') {
check(invokeMethod($model, 'normalizeSupplierQuantity', array('В наявності', array('in_stock_quantity'=>100))) === '100', 'Import textual quantity uses configured value');
check(invokeMethod($model, 'normalizeSupplierQuantity', array('Немає в наявності', array())) === '0', 'Import negative availability');
check(invokeMethod($model, 'normalizeSupplierQuantity', array('Уточніть', array())) === '', 'Import unknown availability is preserved');
check(invokeMethod($model, 'normalizeSupplierQuantity', array('> 10', array('in_stock_quantity'=>100))) === '10', 'Approximate numeric stock is not replaced by textual default');
$rules = invokeMethod($model, 'buildSafeFieldRules', array(array('match_field'=>'sku','settings'=>array('update_price'=>1,'update_quantity'=>0)), 'price_stock'));
check($rules['_quantity_'] === 'preserve' && $rules['_stock_status_id_'] === 'preserve' && $rules['_price_'] === 'overwrite', 'Price+stock respects stock preservation setting');
}
foreach (array('shared', 'inline', 'sparse', 'named') as $variant) {
    $file = tempnam(sys_get_temp_dir(), 'ccp_xlsx_');
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::OVERWRITE);
    $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
    $col = $variant === 'sparse' ? 'C' : 'B';
    if ($variant === 'inline') {
        $sheet = '<worksheet '.$ns.'><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>sku</t></is></c><c r="B1" t="inlineStr"><is><r><t>pri</t></r><r><t>ce</t></r></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>0001</t></is></c><c r="B2"><v>1420</v></c></row></sheetData></worksheet>';
    } else {
        $sheet = '<worksheet '.$ns.'><sheetData><row r="1"><c r="A1" t="s"><v>0</v></c><c r="'.$col.'1" t="s"><v>1</v></c></row><row r="2"><c r="A2" t="s"><v>2</v></c><c r="'.$col.'2"><v>1420</v></c></row></sheetData></worksheet>';
        $zip->addFromString('xl/sharedStrings.xml', '<sst '.$ns.'><si><t>sku</t></si><si><t>price</t></si><si><t>0001</t></si></sst>');
    }
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    if ($variant === 'named') {
        $zip->addFromString('xl/worksheets/sheet2.xml', str_replace('1420', '999', $sheet));
        $zip->addFromString('xl/workbook.xml', '<workbook '.$ns.' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="First" sheetId="1" r:id="rId1"/><sheet name="Selected" sheetId="2" r:id="rId2"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="/xl/worksheets/sheet1.xml"/><Relationship Id="rId2" Target="/xl/worksheets/sheet2.xml"/></Relationships>');
    }
    $zip->close();
    try {
        $result = invokeMethod($model, 'parseXlsx', array($file, $variant === 'named' ? array('sheet_name'=>'Selected') : array(), 0));
        check(isset($result['rows'][0]['sku'], $result['rows'][0]['price']) && $result['rows'][0]['sku'] === '0001' && $result['rows'][0]['price'] === ($variant === 'named' ? '999' : '1420'), 'XLSX '.$variant.' keeps identifiers and column positions');
        if ($variant === 'named') {
            $rejected = false;
            try { invokeMethod($model, 'parseXlsx', array($file, array('sheet_name'=>'Missing'), 0)); } catch (Exception $e) { $rejected = true; }
            check($rejected, 'Missing named worksheet is rejected');
        }
    } catch (Throwable $e) { check(false, 'XLSX '.$variant.': '.$e->getMessage()); }
    unlink($file);
}
$parser = (new ReflectionClass('CodecartSupplierSyncParser'))->newInstanceWithoutConstructor();
foreach (array('0001', '4821234567890', '1', 'NEURO123') as $identifier) {
    check(invokeMethod($parser, 'normalizeIdentifier', array($identifier)) === $identifier, 'Numeric identifier retained: '.$identifier);
}
$supplier = array('default_stock_status_id'=>5, 'settings'=>json_encode(array('in_stock_quantity'=>100, 'unknown_stock_policy'=>'keep')));
foreach (array('https://schema.org/OutOfStock', 'Нет в наличии', 'Немає в наявності', 'Немає в наявностi', 'not available', 'out of stock', 'false', 'Не в наличии', 'Нема в наявності') as $text) {
    $stock = invokeMethod($parser, 'parseStock', array($text, $supplier));
    check($stock['quantity'] === 0, 'Negative stock: '.$text);
}
foreach (array('В наявності', 'В наличии', 'in stock', 'true', 'Готово до відправки', 'Готов к отправке', 'https://schema.org/InStock') as $text) {
    $text = invokeMethod($parser, 'cleanStockTextForDisplay', array($text));
    $stock = invokeMethod($parser, 'parseStock', array($text, $supplier));
    check($stock['quantity'] === 100, 'Configured textual stock: '.$text);
}
$unknown = invokeMethod($parser, 'parseStock', array('Уточняйте у менеджера', $supplier));
check(array_key_exists('quantity', $unknown) && $unknown['quantity'] === null, 'Unknown stock preserves existing quantity');
$numeric = invokeMethod($parser, 'parseStock', array('12 шт.', $supplier));
check($numeric['quantity'] === 12, 'Explicit quantity wins over default');
$supplier['settings'] = json_encode(array('stock_map'=>array('в наличии'=>array('quantity'=>100), 'нет в наличии'=>array('quantity'=>0))));
check(invokeMethod($parser, 'parseStock', array('Нет в наличии', $supplier))['quantity'] === 0, 'Specific stock map wins over its positive substring');
exit($failures ? 1 : 0);
