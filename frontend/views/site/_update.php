<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<article class="journal-row"><time><?= Html::encode(substr($update['created_at'] ?? '',0,10)) ?></time><div><span class="eyebrow"><?= Html::encode($update['project_title'] ?? '工坊日常') ?></span><h3><?= Html::encode($update['title']) ?></h3><p><?= nl2br(Html::encode($update['body'] ?? '')) ?></p></div><span class="journal-star" aria-hidden="true">✧</span></article>
