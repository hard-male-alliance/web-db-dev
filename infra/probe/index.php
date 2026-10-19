<?php
/**
 * Readiness landing page for the local infrastructure only.
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

header('Content-Type: text/html; charset=UTF-8');
try {
    $database = infrastructureDatabase();
    $version = (string) $database->createCommand('SELECT VERSION()')->queryScalar();
    $engine = (string) $database->createCommand('SELECT @@default_storage_engine')->queryScalar();
    // This fixed PHP template does not require application-level localized lookup.
    echo (new yii\web\View())->renderPhpFile(__DIR__ . '/views/index.php', [
        'php' => PHP_VERSION,
        'yii' => Yii::getVersion(),
        'mysql' => $version,
        'engine' => $engine,
    ]);
} catch (Throwable $error) {
    error_log((string) $error);
    http_response_code(503);
    echo '<h1>Database not ready</h1><p>See docker compose logs app db.</p>';
}
