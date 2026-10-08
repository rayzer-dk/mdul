<?php
class Controller {}
define('VERSION', '3.0.4.1');
define('DIR_SYSTEM', dirname(__DIR__).'/modules/importxml_clean_pro/upload/system/');
require dirname(__DIR__).'/modules/importxml_clean_pro/upload/admin/controller/extension/feed/nix.php';
$controller = (new ReflectionClass('ControllerExtensionFeedNix'))->newInstanceWithoutConstructor();
$filename = $controller->helperPrepareFilename('https://example.com/payload.php');
$ok = substr($filename, -4) === '.jpg';
echo ($ok ? 'PASS ' : 'FAIL ') . 'Remote executable extension cannot become a stored image filename' . PHP_EOL;
require DIR_SYSTEM.'library/importxml_clean_pro/image_downloader.php';
$downloader = new ImportxmlCleanProImageDownloader();
foreach (array('http://127.0.0.1/x', 'http://10.0.0.1/x', 'http://169.254.169.254/x', 'http://[::1]/x', 'file:///tmp/x', 'https://user:pass@example.com/x', 'http://8.8.8.8:8080/x') as $url) {
    $pass = $downloader->destination($url) === false;
    echo ($pass ? 'PASS ' : 'FAIL ').'Blocked destination '.$url.PHP_EOL;
    $ok = $ok && $pass;
}
$pass = $downloader->imageExtension('<?php echo "bad"; ?>') === '';
echo ($pass ? 'PASS ' : 'FAIL ').'Non-image body rejected'.PHP_EOL;
$ok = $ok && $pass;
exit($ok ? 0 : 1);
