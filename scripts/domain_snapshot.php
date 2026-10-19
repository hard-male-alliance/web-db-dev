<?php
/** Fingerprint every domain row to verify persistence and actual SQL restoration. */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/infra/probe/bootstrap.php';

$db = infrastructureDatabase();
$tables = ['teams', 'members', 'users', 'skills', 'member_skills', 'projects',
    'project_members', 'updates', 'messages', 'message_replies', 'milestones',
    'submissions', 'audit_logs', 'login_attempts'];
$snapshot = [];
$db->transaction(function () use ($db, $tables, &$snapshot): void {
    foreach ($tables as $table) {
        $schema = $db->getTableSchema($table);
        if ($schema === null || $schema->primaryKey === []) {
            throw new RuntimeException('Missing domain table or primary key: ' . $table);
        }
        $order = implode(', ', array_map($db->quoteColumnName(...), $schema->primaryKey));
        $rows = $db->createCommand('SELECT * FROM ' . $db->quoteTableName($table) . ' ORDER BY ' . $order)->queryAll();
        $snapshot[$table] = ['rows' => count($rows), 'sha256' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR))];
    }
}, yii\db\Transaction::REPEATABLE_READ);
echo json_encode($snapshot, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
