<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $i = $i ?? 0; $status = ['planning'=>'构思中','active'=>'进行中','completed'=>'已完成']; ?>
<article class="project-card"><a class="project-cover cover-<?= (int)($i % 3) ?>" href="/?r=site/project&amp;id=<?= (int)$project['id'] ?>" aria-label="查看<?= Html::encode($project['title']) ?>"><span class="cover-symbol"><?= ['◈','✳','⌘'][$i % 3] ?></span><span class="cover-index">PROJECT / <?= sprintf('%02d', $i + 1) ?></span><span class="cover-arrow">↗</span></a><div class="card-body"><div class="card-meta"><span class="badge"><?= Html::encode($status[$project['status']] ?? $project['status']) ?></span><span><?= (int)($project['progress'] ?? 0) ?>% 已完成</span></div><h3><a href="/?r=site/project&amp;id=<?= (int)$project['id'] ?>"><?= Html::encode($project['title']) ?></a></h3><p><?= Html::encode($project['summary'] ?? '') ?></p><div class="progress-track" role="progressbar" aria-label="项目完成进度" aria-valuenow="<?= (int)($project['progress'] ?? 0) ?>" aria-valuemin="0" aria-valuemax="100"><span style="width:<?= max(0,min(100,(int)($project['progress'] ?? 0))) ?>%"></span></div></div></article>
