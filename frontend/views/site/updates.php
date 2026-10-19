<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '工坊动态 · 星河工坊'; ?><div class="page-heading"><span class="eyebrow">STUDIO JOURNAL</span><h1>成长，正在发生。</h1><p>记录一次发布、一份收获，以及每个值得纪念的协作瞬间。</p></div><?php foreach ($updates as $update): ?><?= $this->render('_update',compact('update')) ?><?php endforeach ?><?php if (!$updates): ?><p class="empty-state">暂无已发布的动态。</p><?php endif ?>
