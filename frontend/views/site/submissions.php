<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '课程档案 · 星河工坊'; ?><div class="page-heading"><span class="eyebrow">OPEN KNOWLEDGE, SHARED GROWTH</span><h1>让过程，也成为作品。</h1><p>需求、设计、实现与交付：在这里查阅和下载我们的课程成果。</p></div><div class="document-grid"><?php foreach ($submissions as $submission): ?><article class="panel document-card"><span class="document-icon">▤</span><span class="eyebrow"><?= Html::encode($submission['kind'] ?? 'TEAM DOCUMENT') ?></span><h2><?= Html::encode($submission['title'] ?? $submission['name'] ?? '课程文档') ?></h2><p><?= Html::encode($submission['description'] ?? '记录工坊从想法到交付的完整过程。') ?></p><a class="text-link" href="/?r=site/download&amp;id=<?= (int)$submission['id'] ?>">下载文档 ↓</a></article><?php endforeach ?><?php if (!$submissions): ?><p class="empty-state">课程成果整理后将在此公开。</p><?php endif ?></div>
