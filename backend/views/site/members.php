<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '成员管理'; ?><div class="section-heading page-heading compact"><div><span class="eyebrow">STUDIO CONTENT</span><h1>成员管理</h1></div><a class="button" href="/admin/?r=site/member">添加成员 ＋</a></div><div class="table-wrap panel"><table><thead><tr><th scope="col">姓名</th><th scope="col">角色</th><th scope="col">邮箱</th><th scope="col">顺序</th><th scope="col">操作</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= Html::encode($row['name'] ?? '—') ?></td><td><?= Html::encode($row['title'] ?? '—') ?></td><td><?= Html::encode($row['email'] ?? '—') ?></td><td><?= Html::encode($row['sort_order'] ?? '—') ?></td><td><a class="text-link" href="/admin/?r=site/member&amp;id=<?= (int)$row['id'] ?>">编辑 →</a></td></tr><?php endforeach ?></tbody></table><?php if (!$rows): ?><p class="empty-state">暂无记录，开始创建第一条内容吧。</p><?php endif ?></div>
