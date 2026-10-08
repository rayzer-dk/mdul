<?php
$autoload = getenv('CCP_TEST_TWIG_AUTOLOAD');
if (!$autoload || !is_file($autoload)) { fwrite(STDERR, 'Set CCP_TEST_TWIG_AUTOLOAD to a compatible OpenCart vendor/autoload.php'.PHP_EOL); exit(2); }
require $autoload;
$twig = new Twig\Environment(new Twig\Loader\ArrayLoader(), array('cache'=>false));
$failures = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__).'/modules', FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'twig') { continue; }
    try {
        $source = file_get_contents($file->getPathname());
        $twig->setLoader(new Twig\Loader\ArrayLoader(array($file->getPathname()=>$source)));
        $twig->compileSource(new Twig\Source($source, $file->getPathname()));
        echo 'PASS '.$file->getFilename().PHP_EOL;
    }
    catch (Throwable $e) { echo 'FAIL '.$file->getFilename().': '.$e->getMessage().PHP_EOL; $failures++; }
}
exit($failures ? 1 : 0);
