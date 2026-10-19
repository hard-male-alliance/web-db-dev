<?php
/** Shared application configuration; environment variables are deployment inputs. */
declare(strict_types=1);

$runtime = getenv('APP_RUNTIME_PATH') ?: dirname(__DIR__, 2) . '/.temp/runtime';
foreach ([$runtime, $runtime . '/sessions', $runtime . '/frontend', $runtime . '/backend'] as $directory) {
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Cannot create application runtime directory.');
    }
}
return [
    'name' => '星河工坊',
    'language' => 'zh-CN',
    'timeZone' => 'Asia/Shanghai',
    'vendorPath' => dirname(__DIR__, 2) . '/vendor',
    'components' => [
        'db' => [
            'class' => yii\db\Connection::class,
            'dsn' => sprintf('mysql:host=%s;port=%s;dbname=%s', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_PORT') ?: '3306', getenv('DB_DATABASE') ?: 'team_website'),
            'username' => getenv('DB_USERNAME') ?: 'team',
            'password' => getenv('DB_PASSWORD') === false ? 'local-demo-only' : getenv('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'attributes' => [PDO::ATTR_TIMEOUT => 5],
        ],
        'request' => [
            'cookieValidationKey' => getenv('APP_COOKIE_KEY') ?: 'local-demo-only-change-before-public-hosting-star-workshop',
            'csrfCookie' => ['httpOnly' => true, 'sameSite' => 'Lax', 'path' => '/'],
        ],
        'user' => [
            'identityClass' => common\models\User::class,
            'enableAutoLogin' => false,
            'loginUrl' => ['//site/login'],
            'identityCookie' => ['name' => '_star_identity', 'httpOnly' => true, 'sameSite' => 'Lax', 'path' => '/'],
        ],
        'session' => [
            'name' => 'star_session',
            'savePath' => $runtime . '/sessions',
            'cookieParams' => ['httpOnly' => true, 'sameSite' => 'Lax', 'path' => '/'],
            'timeout' => 3600,
        ],
        'cache' => ['class' => yii\caching\DummyCache::class],
        'errorHandler' => ['errorAction' => 'site/error'],
        'urlManager' => ['enablePrettyUrl' => false, 'showScriptName' => false],
        'log' => [
            'targets' => [[
                'class' => yii\log\FileTarget::class,
                'levels' => ['error', 'warning'],
                'logFile' => $runtime . '/application.log',
                'logVars' => [],
            ]],
        ],
    ],
];
