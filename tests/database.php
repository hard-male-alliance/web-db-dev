<?php
/**
 * Check real Yii2/MySQL semantics without leaving application data behind.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/infra/probe/bootstrap.php';

$db = infrastructureDatabase();
$suffix = bin2hex(random_bytes(6));
$parent = 'infra_parent_' . $suffix;
$child = 'infra_child_' . $suffix;
$created = [];
try {
    $version = (string) $db->createCommand('SELECT VERSION()')->queryScalar();
    if (!str_starts_with($version, '8.4.11')) {
        throw new RuntimeException('MySQL version does not match the container pin.');
    }
    $db->createCommand("CREATE TABLE `$parent` (id INT PRIMARY KEY, name VARCHAR(100) NOT NULL UNIQUE) ENGINE=InnoDB")->execute();
    $created[] = $parent;
    $db->createCommand("CREATE TABLE `$child` (id INT PRIMARY KEY, parent_id INT NOT NULL, FOREIGN KEY(parent_id) REFERENCES `$parent`(id)) ENGINE=InnoDB")->execute();
    $created[] = $child;
    $transaction = $db->beginTransaction();
    try {
        $db->createCommand()->insert($parent, ['id' => 1, 'name' => '可莉🌟'])->execute();
        $db->createCommand()->insert($child, ['id' => 1, 'parent_id' => 999])->execute();
        throw new RuntimeException('Foreign key violation was not rejected.');
    } catch (yii\db\IntegrityException) {
        $transaction->rollBack();
    }
    if ((int) $db->createCommand("SELECT COUNT(*) FROM `$parent`")->queryScalar() !== 0) {
        throw new RuntimeException('Transaction rollback did not remove the parent write.');
    }
    $db->transaction(function () use ($db, $parent, $child): void {
        $db->createCommand()->insert($parent, ['id' => 1, 'name' => '可莉🌟'])->execute();
        $db->createCommand()->insert($child, ['id' => 1, 'parent_id' => 1])->execute();
    });
    $schema = $db->getTableSchema($child, true);
    if ($schema === null || $schema->foreignKeys === [] || $schema->primaryKey !== ['id']) {
        throw new RuntimeException('Yii2 schema discovery failed.');
    }
    if ($db->createCommand("SELECT name FROM `$parent` WHERE id=1")->queryScalar() !== '可莉🌟') {
        throw new RuntimeException('utf8mb4 round trip failed.');
    }
    echo "PASS MySQL $version / InnoDB, Yii2 transactions, foreign keys, schema discovery, utf8mb4.\n";
} finally {
    if ($db->getTransaction()?->getIsActive()) {
        $db->getTransaction()->rollBack();
    }
    foreach (array_reverse($created) as $table) {
        $db->createCommand("DROP TABLE `$table`")->execute();
    }
}
