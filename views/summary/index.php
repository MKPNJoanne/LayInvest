<?php
use yii\helpers\Html;
use yii\grid\GridView;

/** @var int|null $id */
/** @var array $providers */
/** @var array $dist */

$this->title = $id ? "Weekly Summary (Scenario #{$id})" : "Summary";
?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <!-- <h3 class="mb-0"><?= Html::encode($this->title) ?></h3> -->
    <?php if ($id): ?>
      <div>
        <?= Html::a(
            'Download Excel',
            ['summary/export-excel','id'=>$id],
            ['class'=>'btn btn-success me-2', 'data-pjax' => 0]   
        ) ?>

      </div>
    <?php endif; ?>
  </div>

  <?php if (!$id): ?>
    <div class="alert alert-info">No summary available yet. Add operational inputs to create a scenario.</div>
    <?php return; ?>
  <?php endif; ?>
  

  <div class="card p-3 mb-4">
    <h5>Egg Production (Weeks 1–100)</h5>
    <?= GridView::widget([
      'dataProvider'=>$providers['production'],
      'columns'=>[
        ['attribute'=>'week_no','label'=>'Week'],
        ['attribute'=>'ds','label'=>'Date'],
        'live_birds','lay_pct','eggs_laid','egg_loss_pct','eggs_sellable',
        'small_egg_pct','small_eggs','white_eggs',
      ],
    ]) ?>
  </div>

  <div class="card p-3 mb-4">
    <h5>Egg Revenue</h5>
    <?= GridView::widget([
      'dataProvider'=>$providers['revenue'],
      'columns'=>[
        ['attribute'=>'week_no','label'=>'Week'],
        ['attribute'=>'ds','label'=>'Date'],
        'eggs_sellable','eggs_small','eggs_white',
        'price_small','price_white',
        'revenue_small','revenue_white','total_weekly_revenue','cumulative_revenue',
      ],
    ]) ?>
  </div>

  <div class="card p-3 mb-4">
    <h5>Feed Consumption</h5>
    <?= GridView::widget([
      'dataProvider'=>$providers['feed'],
      'columns'=>[
        ['attribute'=>'week_no','label'=>'Week'],
        ['attribute'=>'ds','label'=>'Date'],
        'feed_type',
        'grams_per_bird_day',
        'feed_kg',
        'cost_feed_lkr',
      ],
    ]) ?>
  </div>

  <div class="card p-3 mb-4">
    <h5>Operational Costs</h5>
    <?= GridView::widget([
      'dataProvider'=>$providers['costs'],
      'columns'=>[
        ['attribute'=>'week_no','label'=>'Week'],
        ['attribute'=>'ds','label'=>'Date'],
        'cost_doc_lkr','cost_feed_lkr','labor_cost_lkr','medicine_cost_lkr',
        'electricity_cost_lkr','transport_cost_lkr','total_cost_lkr',
      ],
    ]) ?>
  </div>

  <div class="card p-3 mb-4">
    <h5>Cull Revenue</h5>
    <?= GridView::widget([
      'dataProvider'=>$providers['cull'],
      'columns'=>[
        ['attribute'=>'week_no','label'=>'Week'],
        ['attribute'=>'ds','label'=>'Date'],
        'birds_alive','std_weight_g','total_weight_kg','cull_price','cull_revenue',
      ],
      'summary'=>false
    ]) ?>
  </div>

  <div class="card p-3 mb-4">
    <h5>Forecasted Prices</h5>
    <?= GridView::widget([
      'dataProvider'=>$providers['prices'],
      'columns'=>[
        ['attribute'=>'week_no','label'=>'Week'],
        ['attribute'=>'ds','label'=>'Date'],
        'feed_starter','feed_grower','feed_layer',
        'doc_price','egg_small','egg_white','cull_price',
      ],
    ]) ?>
  </div>
</div>

