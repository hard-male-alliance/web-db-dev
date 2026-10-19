<?php
/** Administration application entry point, mounted at /admin. */
declare(strict_types=1);
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');
require dirname(__DIR__, 2) . '/common/bootstrap.php';
$config = require dirname(__DIR__, 2) . '/common/config/main.php';
$config['id'] = 'star-backend';
$config['basePath'] = dirname(__DIR__);
$config['controllerNamespace'] = 'backend\\controllers';
$config['runtimePath'] = (getenv('APP_RUNTIME_PATH') ?: dirname(__DIR__, 2) . '/.temp/runtime') . '/backend';
$config['components']['request']['baseUrl'] = '/admin';
$config['components']['request']['scriptUrl'] = '/admin/index.php';
$config['components']['user']['loginUrl'] = '/?r=site/login';
(new yii\web\Application($config))->run();
