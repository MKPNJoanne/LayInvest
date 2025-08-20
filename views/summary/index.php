<?php
use yii\helpers\Html;
use yii\grid\GridView;

/** @var int|null $id */
/** @var array $providers */
/** @var array $dist */

$this->title = $id ? "Summary (Scenario #{$id})" : "Summary";
?>

<div class="container mt-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0"><?= Html::encode($this->title) ?></h3>
    <?php if ($id): ?>
      <div>
        <?= Html::a(
            'Download Excel',
            ['summary/export-excel','id'=>$id],
            ['class'=>'btn btn-success me-2', 'data-pjax' => 0]   // <—
        ) ?>

      </div>
    <?php endif; ?>
  </div>

  <?php if (!$id): ?>
    <div class="alert alert-info">No summary available yet. Add operational inputs to create a scenario.</div>
    <?php return; ?>
  <?php endif; ?>

  <!-- Cost distribution (bar) -->
  <div class="card p-3 mb-4">
    <h5 class="mb-2">Cost Distribution (Totals over 100 weeks)</h5>
    <div style="max-width:760px">
      <canvas id="costBar" height="120"></canvas>
    </div>
    <div class="mt-2 small text-muted">
      <?php
        $labels = ['feed','doc','labor','medicine','electricity','transport'];
        $parts = [];
        foreach ($labels as $k) {
          $pct = isset($dist[$k]['pct']) ? $dist[$k]['pct'] : 0;
          $amt = isset($dist[$k]['amount']) ? number_format($dist[$k]['amount'],2) : '0.00';
          $parts[] = ucfirst($k)." LKR {$amt} ({$pct}%)";
        }
        echo implode(' · ', $parts);
      ?>
    </div>
  </div>

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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const el = document.getElementById('costBar');
  const labels = ['Feed','Doc','Labor','Medicine','Electricity','Transport'];
  const data   = [
    <?= (float)($dist['feed']['amount'] ?? 0) ?>,
    <?= (float)($dist['doc']['amount'] ?? 0) ?>,
    <?= (float)($dist['labor']['amount'] ?? 0) ?>,
    <?= (float)($dist['medicine']['amount'] ?? 0) ?>,
    <?= (float)($dist['electricity']['amount'] ?? 0) ?>,
    <?= (float)($dist['transport']['amount'] ?? 0) ?>
  ];
  new Chart(el, {
    type: 'bar',
    data: { labels, datasets: [{ data }] },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { callback: (v)=>v } } }
    }
  });
})();
</script>
