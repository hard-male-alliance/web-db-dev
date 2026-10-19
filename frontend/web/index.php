<?php
/** Public application entry point. */
declare(strict_types=1);
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');
require dirname(__DIR__, 2) . '/common/bootstrap.php';
$config = require dirname(__DIR__, 2) . '/common/config/main.php';
$config['id'] = 'star-frontend';
$config['basePath'] = dirname(__DIR__);
$config['controllerNamespace'] = 'frontend\\controllers';
$config['runtimePath'] = (getenv('APP_RUNTIME_PATH') ?: dirname(__DIR__, 2) . '/.temp/runtime') . '/frontend';
(new yii\web\Application($config))->run();
