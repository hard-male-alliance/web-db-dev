<?php
/** Public layout with accessible navigation and local static assets. */
use yii\helpers\Html;
$action = Yii::$app->controller->action->id;
$nav = ['index'=>'首页', 'members'=>'团队成员', 'projects'=>'项目探索', 'updates'=>'工坊动态', 'message'=>'留言交流', 'submissions'=>'课程档案'];
$this->beginPage(); ?>
<!DOCTYPE html><html lang="zh-CN"><head><meta charset="<?= Yii::$app->charset ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?= Html::csrfMetaTags() ?><title><?= Html::encode($this->title ?: '星河工坊 · 用好奇心构建未来') ?></title><link rel="stylesheet" href="/assets/site.css"><?php $this->head() ?></head>
<body><?php $this->beginBody() ?><a class="skip-link" href="#main">跳到主要内容</a>
<header class="site-header"><div class="shell header-inner"><a class="brand" href="/?r=site/index"><span class="brand-mark">✳</span><span>星河工坊<small>STAR RIVER STUDIO</small></span></a><nav aria-label="主导航"><?php foreach ($nav as $id=>$label): ?><a <?= $action === $id ? 'class="active" aria-current="page"' : '' ?> href="/?r=site/<?= $id ?>"><?= $label ?></a><?php endforeach ?></nav><a class="admin-link" href="/admin/">工作台 <span aria-hidden="true">↗</span></a></div></header>
<main id="main" class="shell main-content"><?php foreach (Yii::$app->session->getAllFlashes() as $type=>$message): ?><div class="alert <?= Html::encode($type) ?>" role="status"><?= Html::encode(is_array($message) ? implode('；', $message) : $message) ?></div><?php endforeach ?><?= $content ?></main>
<footer class="site-footer shell"><div><a class="brand" href="/?r=site/index">✳ 星河工坊</a><p>让想法落地，让热爱发生。</p></div><div>用代码记录探索 · 用协作连接星河<br><small>© <?= date('Y') ?> 星河工坊 / 互联网数据库课程设计</small></div><a href="/?r=site/submissions">查看课程档案 ↗</a></footer><script src="/assets/site.js" defer></script><?php $this->endBody() ?></body></html><?php $this->endPage() ?>
