<?php
/** @var \app\models\LoginForm $model */
use yii\helpers\Html;
use yii\widgets\ActiveForm;



$this->title = 'Login';
$this->registerCssFile('@web/css/login.css');

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
  <div class="brand-box">
    <div class="logo">
      <?= Html::img('@web/assets/images/logo.png', [
          'alt' => 'LayInvest Logo',
      ]) ?>
    </div>
    <!-- <div class="name">LayInvest</div> -->
  </div>
</div>


