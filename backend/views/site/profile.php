<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php use yii\widgets\ActiveForm; $this->title = '我的资料'; ?><div class="page-heading compact"><span class="eyebrow">STUDIO CONTENT</span><h1>我的资料</h1><p>让伙伴与访客更好地认识你。</p></div><section class="panel editor-panel"><?php $activeForm = ActiveForm::begin(['enableClientScript'=>false,'enableClientValidation'=>false]); ?><?= $activeForm->errorSummary($model) ?><?= $activeForm->field($model,'name')->input('text')->label('姓名') ?><?= $activeForm->field($model,'title')->input('text')->label('角色 / 职责') ?><?= $activeForm->field($model,'bio')->textarea(['rows'=>7])->label('个人简介') ?><?= $activeForm->field($model,'email')->input('email')->label('电子邮箱') ?><div class="button-row"><?= Html::submitButton('保存更改 ✓',['class'=>'button']) ?><a class="text-link" href="/admin/">返回工作台</a></div><?php ActiveForm::end(); ?></section>
