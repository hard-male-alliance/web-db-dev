<?php
/**
 * Loopback-only smoke endpoint; not an application entry point.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

header('Content-Type: text/html; charset=UTF-8');
$view = new yii\web\View();
// The standalone fixture has no application context for localized view lookup.
echo $view->renderPhpFile(__DIR__ . '/views/smoke.php', [
    'name' => (string) ($_GET['name'] ?? 'Klee'),
    'phpVersion' => PHP_VERSION,
    'yiiVersion' => Yii::getVersion(),
]);
