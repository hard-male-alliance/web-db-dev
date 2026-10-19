<?php
/** Shared Yii bootstrap for the public and administration applications. */
declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/vendor/yiisoft/yii2/Yii.php';
Yii::setAlias('@common', __DIR__);
Yii::setAlias('@frontend', dirname(__DIR__) . '/frontend');
Yii::setAlias('@backend', dirname(__DIR__) . '/backend');
