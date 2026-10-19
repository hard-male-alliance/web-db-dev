<?php
/** Server-rendered presentation for the team archive. */
use yii\helpers\Html;
?>
<?php use yii\widgets\ActiveForm; $this->title = '登录工作台 · 星河工坊'; ?><section class="login-card panel"><span class="eyebrow">WELCOME BACK, BUILDER</span><h1>回到创造的地方。</h1><p class="muted">登录后管理项目、发布动态，与伙伴一起让工坊变得更好。</p><?php $activeForm = ActiveForm::begin(['enableClientScript'=>false,'enableClientValidation'=>false]); ?><?= $activeForm->field($model,'username')->textInput(['autocomplete'=>'username','autofocus'=>true])->label('用户名') ?><?= $activeForm->field($model,'password')->passwordInput(['autocomplete'=>'current-password'])->label('密码') ?><?= Html::submitButton('登录工作台 ↗',['class'=>'button']) ?><?php ActiveForm::end(); ?><div class="demo-notice"><strong>仅限本地演示 · 示例账号</strong><p>管理员：<code>admin / admin123456</code><br>成员：<code>member / member123456</code></p><p>账号及成员均为示例。正式部署前必须修改密码，切勿复用个人密码。</p></div></section>
