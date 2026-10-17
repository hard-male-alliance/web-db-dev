<?php
/**
 * Write and verify a marker across container replacement or SQL restoration.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/infra/probe/bootstrap.php';

$operation = $argv[1] ?? '';
if (!in_array($operation, ['mark', 'verify'], true) || !preg_match('/^[a-f0-9]{32}$/', $argv[2] ?? '')) {
    fwrite(STDERR, "Usage: database.php mark TOKEN|verify TOKEN\n");
    exit(2);
}
$db = infrastructureDatabase();
$token = $argv[2];
if ($operation === 'mark') {
    $db->createCommand('CREATE TABLE IF NOT EXISTS `_infrastructure_probe` (token CHAR(32) PRIMARY KEY) ENGINE=InnoDB')->execute();
    $db->createCommand()->upsert('_infrastructure_probe', ['token' => $token], false)->execute();
    echo "Persistence marker written.\n";
    exit;
}
$count = $db->createCommand('SELECT COUNT(*) FROM `_infrastructure_probe` WHERE token=:token', [':token' => $token])->queryScalar();
if ((int) $count !== 1) {
    throw new RuntimeException('Persistence marker did not survive.');
}
$db->createCommand()->delete('_infrastructure_probe', ['token' => $token])->execute();
echo "PASS persistence marker survived container replacement.\n";
