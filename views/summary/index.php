<?php
use yii\helpers\Html;
use yii\grid\GridView;
use yii\bootstrap5\BootstrapAsset;

/** @var int|null $id */
/** @var array $providers */
/** @var array $dist */

$this->title = $id ? "Weekly Summary (Scenario #{$id})" : "Summary";
$this->registerCssFile('@web/css/revenue.css', [
    'depends' => [BootstrapAsset::class],
]);

/**
 * Common GridView options to match revenue.css (table-modern + centered green pager)
 */
$gridCommon = [
    'tableOptions' => ['class' => 'table-modern'],
    'options'      => ['class' => 'table-wrap'],        
    'layout'       => "{items}\n{pager}",                 
    'pager'        => [
        'options'               => ['class' => 'pagination'], // uses .pagination from revenue.css
        'prevPageLabel'         => '«',
        'nextPageLabel'         => '»',
        'firstPageLabel'        => false,
        'lastPageLabel'         => false,
        'maxButtonCount'        => 10,
        'disabledListItemSubTagOptions' => ['tag' => 'span'],
    ],
];
?>

<div class="page-section">
  <div class="d-flex justify-content-end mb-3">
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

  <!-- Egg Production -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Egg Production (Weeks 1–100)</h3>
      <div class="meta muted">Auto-calculated from flock & lay %</div>
    </div>
    <?= GridView::widget(array_merge($gridCommon, [
      'dataProvider' => $providers['production'],
      'columns' => [
        ['attribute' => 'week_no', 'label' => 'Week'],
        ['attribute' => 'ds',      'label' => 'Date'],
        'live_birds','lay_pct','eggs_laid','egg_loss_pct','eggs_sellable',
        'small_egg_pct','small_eggs','white_eggs',
      ],
    ])) ?>
  </div>

  <!-- Egg Revenue -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Egg Revenue</h3>
      <div class="meta muted">Weekly & cumulative revenue</div>
    </div>
    <?= GridView::widget(array_merge($gridCommon, [
      'dataProvider' => $providers['revenue'],
      'columns' => [
        ['attribute' => 'week_no', 'label' => 'Week'],
        ['attribute' => 'ds',      'label' => 'Date'],
        'eggs_sellable','eggs_small','eggs_white',
        'price_small','price_white',
        'revenue_small','revenue_white','total_weekly_revenue','cumulative_revenue',
      ],
    ])) ?>
  </div>

  <!-- Feed Consumption -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Feed Consumption</h3>
      <div class="meta muted">Type, grams/bird/day, cost</div>
    </div>
    <?= GridView::widget(array_merge($gridCommon, [
      'dataProvider' => $providers['feed'],
      'columns' => [
        ['attribute' => 'week_no', 'label' => 'Week'],
        ['attribute' => 'ds',      'label' => 'Date'],
        'feed_type',
        'grams_per_bird_day',
        'feed_kg',
        'cost_feed_lkr',
      ],
    ])) ?>
  </div>

  <!-- Operational Costs -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Operational Costs</h3>
      <div class="meta muted">Weekly cost breakdown </div>
    </div>
    <?= GridView::widget(array_merge($gridCommon, [
      'dataProvider' => $providers['costs'],
      'columns' => [
        ['attribute' => 'week_no', 'label' => 'Week'],
        ['attribute' => 'ds',      'label' => 'Date'],
        'cost_doc_lkr','cost_feed_lkr','labor_cost_lkr','medicine_cost_lkr',
        'electricity_cost_lkr','transport_cost_lkr','total_cost_lkr',
      ],
    ])) ?>
  </div>

  <!-- Cull Revenue -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Cull Revenue</h3>
      <div class="meta muted">Final culling income</div>
    </div>
    <?= GridView::widget(array_merge($gridCommon, [
      'dataProvider' => $providers['cull'],
      'columns' => [
        ['attribute' => 'week_no', 'label' => 'Week'],
        ['attribute' => 'ds',      'label' => 'Date'],
        'birds_alive','std_weight_g','total_weight_kg','cull_price','cull_revenue',
      ],
      'summary' => false,
    ])) ?>
  </div>

  <!-- Forecasted Prices -->
  <div class="table-card mb-5">
    <div class="card-head">
      <h3>Forecasted Prices</h3>
      <div class="meta muted">Model-projected input & output prices</div>
    </div>
    <?= GridView::widget(array_merge($gridCommon, [
      'dataProvider' => $providers['prices'],
      'columns' => [
        ['attribute' => 'week_no', 'label' => 'Week'],
        ['attribute' => 'ds',      'label' => 'Date'],
        'feed_starter','feed_grower','feed_layer',
        'doc_price','egg_small','egg_white','cull_price',
      ],
    ])) ?>
  </div>
</div>
