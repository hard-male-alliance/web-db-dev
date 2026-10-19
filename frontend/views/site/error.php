<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '页面暂时无法访问'; ?><section class="page-heading"><span class="eyebrow">LET’S FIND ANOTHER WAY</span><h1><?= Html::encode($name ?? '页面暂时无法访问') ?></h1><p><?= nl2br(Html::encode($message ?? '请返回首页继续探索。')) ?></p><a class="button" href="/">回到首页 →</a></section>
