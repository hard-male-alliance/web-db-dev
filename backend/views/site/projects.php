<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '项目管理'; ?><div class="section-heading page-heading compact"><div><span class="eyebrow">STUDIO CONTENT</span><h1>项目管理</h1></div><a class="button" href="/admin/?r=site/project">创建项目 ＋</a></div><div class="table-wrap panel"><table><thead><tr><th scope="col">项目名称</th><th scope="col">状态</th><th scope="col">进度</th><th scope="col">公开</th><th scope="col">操作</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= Html::encode($row['title'] ?? '—') ?></td><td><?= Html::encode($row['status'] ?? '—') ?></td><td><?= Html::encode($row['progress'] ?? '—') ?></td><td><?= !empty($row['published']) ? '是' : '否' ?></td><td><a class="text-link" href="/admin/?r=site/project&amp;id=<?= (int)$row['id'] ?>">编辑 →</a></td></tr><?php endforeach ?></tbody></table><?php if (!$rows): ?><p class="empty-state">暂无记录，开始创建第一条内容吧。</p><?php endif ?></div>
