<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php use yii\widgets\ActiveForm; $this->title = '团队信息'; ?><div class="page-heading compact"><span class="eyebrow">STUDIO CONTENT</span><h1>团队信息</h1><p>公开介绍将展示在工坊主页。</p></div><section class="panel editor-panel"><?php $activeForm = ActiveForm::begin(['enableClientScript'=>false,'enableClientValidation'=>false]); ?><?= $activeForm->errorSummary($model) ?><?= $activeForm->field($model,'name')->input('text')->label('团队名称') ?><?= $activeForm->field($model,'tagline')->input('text')->label('团队标语') ?><?= $activeForm->field($model,'description')->textarea(['rows'=>7])->label('团队介绍') ?><?= $activeForm->field($model,'email')->input('email')->label('联系邮箱') ?><?= $activeForm->field($model,'location')->input('text')->label('所在地') ?><div class="button-row"><?= Html::submitButton('保存更改 ✓',['class'=>'button']) ?><a class="text-link" href="/admin/">返回工作台</a></div><?php ActiveForm::end(); ?></section>
