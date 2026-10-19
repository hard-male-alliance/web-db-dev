<?php
/**
 * Verify the local runtime and framework before application scaffolding.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';

$lock = json_decode(file_get_contents(dirname(__DIR__) . '/toolchain.lock.json'), true, flags: JSON_THROW_ON_ERROR);
$version = $lock['php']['version'];
if (getenv('PHPENV') && PHP_OS_FAMILY !== 'Windows') {
    $arch = in_array(php_uname('m'), ['aarch64', 'arm64'], true) ? 'arm64' : 'x64';
    $version = $lock['php']['platforms'][strtolower(PHP_OS_FAMILY) . '-' . $arch]['version'];
}
if (PHP_VERSION !== $version) {
    throw new RuntimeException('PHP runtime does not match toolchain.lock.json.');
}
foreach (['curl', 'fileinfo', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql'] as $extension) {
    if (!extension_loaded($extension)) {
        throw new RuntimeException("Missing extension: $extension");
    }
}
if (Yii::getVersion() !== '2.0.55') {
    throw new RuntimeException('Unexpected Yii2 version.');
}
$model = new yii\base\DynamicModel(['name' => 'Klee']);
$model->addRule('name', 'required');
if (!$model->validate()) {
    throw new RuntimeException('Yii2 model validation failed.');
}
echo 'PASS PHP ', PHP_VERSION, ', Yii2 ', Yii::getVersion(), ', required extensions and model validation.', PHP_EOL;
