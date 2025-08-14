<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Operational Cost Input';
?>
<!-- Link CSS -->
<link rel="stylesheet" href="<?= Yii::getAlias('@web/css/operational-cost-view.css') ?>">

<div class="container mt-4">
    <div class="alert alert-info">
        Default values are based on 
        <a href="https://arts.cmb.ac.lk/wp-content/uploads/2024/07/CEJ_Vol-21_paper-8.pdf" target="_blank">
           Hector Kobbekaduwa Agrarian Research and Training Institute, Colombo, Sri Lanka
        </a> with monthly increment applied.
    </div>

    <?php $form = ActiveForm::begin([
        'id' => 'operational-cost-form',
        'action' => ['operational-cost/create'],
        'method' => 'post',
    ]); ?>

    <?= $form->field($model, 'start_date')->input('date', ['required' => true,
     'class' => 'form-control banner-bg']) ?>

    <?= $form->field($model, 'flock_size')->input('number', [
        'min' => 500,
        'max' => 5000,
        'required' => true,
        'placeholder' => 'Enter a number between 500–5000',
         'class' => 'form-control banner-bg'
    ]) ?>

    <?= $form->field($model, 'cost_labor_override')->input('number', [
        'placeholder' => $defaults['labor']['base_value'] ?? '',
        'step' => '0.001'
    ])->label('Labor (LKR/egg)') ?>

    <?= $form->field($model, 'cost_electricity_override')->input('number', [
        'placeholder' => $defaults['electricity']['base_value'] ?? '',
        'step' => '0.001'
    ])->label('Electricity (LKR/egg)') ?>

    <?= $form->field($model, 'cost_medicine_override')->input('number', [
        'placeholder' => $defaults['medicine']['base_value'] ?? '',
        'step' => '0.001'
    ])->label('Medicine (LKR/egg)') ?>

    <?= $form->field($model, 'cost_transport_override')->input('number', [
        'placeholder' => $defaults['transport']['base_value'] ?? '',
        'step' => '0.001'
    ])->label('Transport (LKR/egg)') ?>

    <div class="form-group mt-4">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success btn-lg']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
