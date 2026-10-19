<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php use yii\widgets\ActiveForm; $this->title = '编辑动态'; ?><div class="page-heading compact"><span class="eyebrow">STUDIO CONTENT</span><h1>编辑动态</h1><p>记录团队与项目的重要进展。</p></div><section class="panel editor-panel"><?php $activeForm = ActiveForm::begin(['enableClientScript'=>false,'enableClientValidation'=>false]); ?><?= $activeForm->errorSummary($model) ?><?= $activeForm->field($model,'title')->input('text')->label('动态标题') ?><?= $activeForm->field($model,'body')->textarea(['rows'=>7])->label('动态正文') ?><?= $activeForm->field($model,'project_id')->dropDownList(\yii\helpers\ArrayHelper::map($projects,'id','title'),['prompt'=>'工坊动态（不关联项目）'])->label('所属项目') ?><?= $activeForm->field($model,'published')->checkbox()->label('公开发布') ?><div class="button-row"><?= Html::submitButton('保存更改 ✓',['class'=>'button']) ?><a class="text-link" href="/admin/">返回工作台</a></div><?php ActiveForm::end(); ?></section>
