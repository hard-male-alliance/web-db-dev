<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '操作未完成'; ?><section class="page-heading"><span class="eyebrow">LET’S TRY ANOTHER WAY</span><h1><?= Html::encode($name ?? '操作未完成') ?></h1><p><?= nl2br(Html::encode($message ?? '请返回工作台重试。')) ?></p><a class="button" href="/admin/">返回工作台 →</a></section>
