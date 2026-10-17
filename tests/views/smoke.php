<?php
/**
 * Exercise server-side HTML rendering and untrusted text escaping.
 */
declare(strict_types=1);

use yii\helpers\Html;
?>
<!doctype html>
<html lang="en">
<meta charset="utf-8">
<title>Toolchain smoke test</title>
<h1><?= Html::encode($name) ?></h1>
<p>PHP <?= Html::encode($phpVersion) ?> / Yii2 <?= Html::encode($yiiVersion) ?></p>
</html>
