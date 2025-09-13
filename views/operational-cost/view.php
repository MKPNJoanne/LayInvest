<?php
use yii\helpers\Html;

$this->title = "Operational Cost Input";
?>
<link rel="stylesheet" href="<?= Yii::getAlias('@web/css/operational-cost-view.css') ?>">

<div class="container mt-4">

    <?php if (Yii::$app->session->hasFlash('success')): ?>
        <div class="alert alert-success">
            <?= Yii::$app->session->getFlash('success') ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        Default values are based on 
        <a href="https://arts.cmb.ac.lk/wp-content/uploads/2024/07/CEJ_Vol-21_paper-8.pdf" target="_blank">
            Hector Kobbekaduwa Agrarian Research and Training Institute , 2023 report
        </a> 
    </div>

    <!-- Flock Details -->
    <div class="card p-3 mb-4">
        <h4 class="mb-3">Flock Details</h4>
        <table class="table table-bordered">
            <tr>
                <th>Start Date</th>
                <td><?= Html::encode($model['start_date']) ?></td>
            </tr>
            <tr>
                <th>Flock Size</th>
                <td><?= number_format($model['flock_size']) ?></td>
            </tr>
            <tr>
                <th>Space Requirement (sq.ft)</th>
                <td><?= number_format(($model['flock_size'] ?? 0) * 2.5, 2) ?></td>
            </tr>
        </table>
    </div>

    <!-- Cost Baselines & Overrides -->
    <div class="card p-3 mb-4">
        <h4 class="mb-3">Cost Rates per egg</h4>
        <table class="table table-bordered ">
            <thead class="table-light">
                <tr>
                    <th>Cost Type</th>
                    <th>User Override</th>
                    <th>Default Baseline</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Labor</td>
                    <td><?= $model['cost_labor_override'] !== null ? number_format($model['cost_labor_override'], 3) : '-' ?></td>
                    <td><?= number_format($baseline['labor']['base_value'], 3) ?></td>
                </tr>
                <tr>
                    <td>Electricity</td>
                    <td><?= $model['cost_electricity_override'] !== null ? number_format($model['cost_electricity_override'], 3) : '-' ?></td>
                    <td><?= number_format($baseline['electricity']['base_value'], 3) ?></td>
                </tr>
                <tr>
                    <td>Medicine</td>
                    <td><?= $model['cost_medicine_override'] !== null ? number_format($model['cost_medicine_override'], 3) : '-' ?></td>
                    <td><?= number_format($baseline['medicine']['base_value'], 3) ?></td>
                </tr>
                <tr>
                    <td>Transport</td>
                    <td><?= $model['cost_transport_override'] !== null ? number_format($model['cost_transport_override'], 3) : '-' ?></td>
                    <td><?= number_format($baseline['transport']['base_value'], 3) ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Action Button -->
    <?= Html::a(
        'Calculate Operational Cost',
        ['operational-cost/calculate', 'id' => $model['id']],
        ['class' => 'btn btn-success btn-lg']
    ) ?>

</div>
