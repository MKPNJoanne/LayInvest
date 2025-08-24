<?php
/** @var \app\models\LoginForm $model */
use yii\helpers\Html;
use yii\widgets\ActiveForm;



$this->title = 'Login';
$this->registerCss(<<<CSS
.login-wrap{
  min-height: 100vh; display:grid; grid-template-columns: 1fr 1fr;
}
.login-left{
  display:flex; align-items:center; justify-content:center;
  background:#f7faf8;
}
.login-card{
  width: 360px; background:#fff; border-radius:16px; padding:28px 24px;
  box-shadow: 0 12px 30px rgba(0,0,0,.06);
}
.login-title{ font-size:28px; font-weight:700; color:#143d06; margin-bottom:6px; }
.login-sub{ color:#6d6d6d; margin-bottom:18px; }

.login-right{
  background:#143d06; color:#fff; display:flex; align-items:center; justify-content:center;
}
.brand-box{
  background:#f2f5f3; border-radius:16px; padding:32px 40px; color:#143d06;
  text-align:center; box-shadow: 0 8px 22px rgba(0,0,0,.12);
}
.brand-box .logo{ font-size:54px; margin-bottom:8px; }
.brand-box .name{ font-size:32px; font-weight:700; letter-spacing:.4px; }

.form-control{ border-radius:10px; }
.btn-login{ background:#04390d; color:#fff; border:none; border-radius:10px; padding:10px 16px; }
.btn-login:hover{ background:#075217; }
CSS);
?>
<?php if ($model->hasErrors()): ?>
  <div class="alert alert-danger" style="border-radius:10px">
    <?= Html::errorSummary($model) ?>
  </div>
<?php endif; ?>


<div class="login-wrap">
  <div class="login-left">
    <div class="login-card">
      <div class="login-title">Login</div>
      <div class="login-sub">Enter your account details</div>

      <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>

        <?= $form->field($model, 'username')->textInput([
              'autofocus' => true, 'placeholder' => 'Username',
              'class' => 'form-control'
        ])->label(false) ?>

        <?= $form->field($model, 'password')->passwordInput([
              'placeholder' => 'Password', 'class' => 'form-control'
        ])->label(false) ?>

        <?= $form->field($model, 'rememberMe')->checkbox(['label'=>'Remember me']) ?>

        <div class="form-group mt-3">
          <?= Html::submitButton('Login', ['class' => 'btn btn-login w-100']) ?>
        </div>

      <?php ActiveForm::end(); ?>
    </div>
  </div>

  <div class="login-right">
    <!-- <div class="brand-box"> -->
      <div class="logo"><?= Html::img('@web/assets/images/layinvest-logo.png', [
    'alt' => 'LayInvest Logo',
    'style' => 'max-width:120px; height:auto;'
]) ?>
<!-- </div> -->
      <div class="name">LayInvest</div>
    </div>
  </div>
</div>
