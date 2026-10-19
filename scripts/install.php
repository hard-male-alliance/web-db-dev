<?php
/**
 * Install the non-destructive application schema and seed an empty database.
 * Usage: python scripts/php.py scripts/install.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

// The same DB_* contract is used by the application and Compose deployment.
$db = new yii\db\Connection([
    'dsn' => sprintf(
        'mysql:host=%s;port=%s;dbname=%s',
        getenv('DB_HOST') ?: '127.0.0.1',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_DATABASE') ?: 'team_website',
    ),
    'username' => getenv('DB_USERNAME') ?: 'team',
    'password' => getenv('DB_PASSWORD') === false ? 'local-demo-only' : getenv('DB_PASSWORD'),
    'charset' => 'utf8mb4',
    'attributes' => [PDO::ATTR_TIMEOUT => 10],
]);

try {
    $sql = file_get_contents(dirname(__DIR__) . '/data/schema.sql');
    if ($sql === false) {
        throw new RuntimeException('Cannot read data/schema.sql.');
    }
    $db->open();
    // Serialize installers; MySQL DDL is not transactionally rolled back.
    $lock = (int) $db->createCommand("SELECT GET_LOCK(CONCAT(DATABASE(), ':schema-install'), 30)")->queryScalar();
    if ($lock !== 1) {
        throw new RuntimeException('Another installer holds the database installation lock.');
    }
    try {
        $db->pdo->exec($sql);
    } finally {
        if ($db->pdo->inTransaction()) {
            $db->pdo->rollBack();
        }
        $db->createCommand("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':schema-install'))")->queryScalar();
    }
    echo "Application schema installed; existing data and passwords preserved.\n";
    echo "Empty databases receive demo accounts: admin / admin123456; member / member123456.\n";
    echo "Change demonstration credentials before exposing this application.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Installation failed: ' . $error->getMessage() . "\n");
    exit(1);
}
