<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '项目探索 · 星河工坊'; ?><div class="page-heading"><span class="eyebrow">BUILT WITH CURIOSITY</span><h1>让想法，有迹可循。</h1><p>从第一个灵感，到每一次迭代。这是我们认真构建的作品集。</p></div><div class="project-grid"><?php foreach ($projects as $i=>$project): ?><?= $this->render('_project',compact('project','i')) ?><?php endforeach ?><?php if (!$projects): ?><p class="empty-state">暂无已公开的项目。</p><?php endif ?></div>
