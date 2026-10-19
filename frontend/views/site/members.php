<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php $this->title = '团队成员 · 星河工坊'; ?><div class="page-heading"><span class="eyebrow">THE PEOPLE BEHIND THE IDEAS</span><h1>每一颗星，都有自己的光。</h1><p>不同的专长，相同的热爱。以下成员为课程演示用示例档案，不代表真实人员。</p></div><div class="member-grid"><?php foreach ($members as $member): ?><article class="member-card"><div class="avatar" style="--avatar-color:<?= Html::encode(preg_match('/^#[0-9a-fA-F]{6}$/',$member['avatar_color'] ?? '') ? $member['avatar_color'] : '#c5e4cf') ?>"><?= Html::encode(mb_substr($member['name'],0,1)) ?><span>✦</span></div><h2><?= Html::encode($member['name']) ?></h2><span class="eyebrow"><?= Html::encode($member['title'] ?? '工坊成员') ?></span><p><?= Html::encode($member['bio'] ?? '') ?></p><div class="tags"><?php foreach ($member['skills'] ?? [] as $skill): ?><span><?= Html::encode(is_array($skill) ? ($skill['name'] ?? '') : $skill) ?></span><?php endforeach ?></div></article><?php endforeach ?></div>
