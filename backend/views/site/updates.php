<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '动态管理'; ?><div class="section-heading page-heading compact"><div><span class="eyebrow">STUDIO CONTENT</span><h1>动态管理</h1></div><a class="button" href="/admin/?r=site/update">创建动态 ＋</a></div><div class="table-wrap panel"><table><thead><tr><th scope="col">标题</th><th scope="col">所属项目</th><th scope="col">公开</th><th scope="col">创建时间</th><th scope="col">操作</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= Html::encode($row['title'] ?? '—') ?></td><td><?= Html::encode($row['project_title'] ?? '—') ?></td><td><?= !empty($row['published']) ? '是' : '否' ?></td><td><?= Html::encode($row['created_at'] ?? '—') ?></td><td><a class="text-link" href="/admin/?r=site/update&amp;id=<?= (int)$row['id'] ?>">编辑 →</a></td></tr><?php endforeach ?></tbody></table><?php if (!$rows): ?><p class="empty-state">暂无记录，开始创建第一条内容吧。</p><?php endif ?></div>
