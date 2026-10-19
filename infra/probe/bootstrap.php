<?php
/**
 * Infrastructure probe bootstrap, not the future business application.
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/yiisoft/yii2/Yii.php';

function infrastructureDatabase(): yii\db\Connection
{
    $database = new yii\db\Connection([
        'dsn' => sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            getenv('DB_HOST') ?: '127.0.0.1',
            getenv('DB_PORT') ?: '3306',
            getenv('DB_DATABASE') ?: 'team_website',
        ),
        'username' => getenv('DB_USERNAME') ?: 'team',
        'password' => getenv('DB_PASSWORD') === false ? 'local-demo-only' : getenv('DB_PASSWORD'),
        'charset' => 'utf8mb4',
        'attributes' => [PDO::ATTR_TIMEOUT => 3],
    ]);
    return $database;
}
