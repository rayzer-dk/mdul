<?php
require getenv('CCP_TEST_TWIG_AUTOLOAD');
$root = dirname(__DIR__);
$output = $root.'/.local/rendered';
if (!is_dir($output)) { mkdir($output, 0755, true); }
foreach (array('supplier_sync_parser_pro','import_export_pro') as $module) {
    $_ = array();
    $route = $module === 'import_export_pro' ? 'import_pro' : $module;
    include $root.'/modules/'.$module.'/upload/admin/language/ru-ru/extension/module/'.$route.'.php';
    $data = $_;
    $data += array('header'=>'','footer'=>'','column_left'=>'','categories'=>array(),'languages'=>array(),'currencies'=>array(),'suppliers'=>array(),'profiles'=>array(),'stock_statuses'=>array(),'active_tab'=>'suppliers');
    $data['supplier'] = array('settings'=>'{}','in_stock_quantity'=>250,'update_stock_status'=>0,'unknown_stock_policy'=>'keep','new_category_name'=>'Проверка новой категории');
    $data['profiles_json'] = '[]';
    $template = $root.'/modules/'.$module.'/upload/admin/view/template/extension/module/'.$route.'.twig';
    $loader = new Twig\Loader\ArrayLoader(array($route=>file_get_contents($template)));
    $twig = new Twig\Environment($loader, array('cache'=>false));
    file_put_contents($output.'/'.$route.'.html', $twig->render($route, $data));
    echo 'PASS render '.$route.PHP_EOL;
}
