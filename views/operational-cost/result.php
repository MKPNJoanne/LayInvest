<?php
use yii\helpers\Html;

/** @var array $summary */
/** @var array $weeks */
/** @var array $laid */
/** @var array $sellable */

$this->title = "Egg Production – 100 Weeks (Scenario #{$summary['scenario_id']})";
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container my-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <!-- <h3 class="mb-0"><?= Html::encode($this->title) ?></h3> -->
    <div>
      <?= Html::a('Back', ['operational-cost/view', 'id' => $summary['scenario_id']], ['class' => 'btn btn-outline-secondary']) ?>
    </div>
  </div>

  <?php if (Yii::$app->session->hasFlash('success')): ?>
    <div class="alert alert-success"><?= Yii::$app->session->getFlash('success') ?></div>
  <?php endif; ?>

  <!-- KPI cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-2">
      <div class="card p-3 text-center">
        <div class="small text-muted">Flock Size</div>
        <div class="h4 mb-0"><?= number_format($summary['flock_size']) ?></div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card p-3 text-center">
        <div class="small text-muted">Total Eggs (Laid)</div>
        <div class="h4 mb-0"><?= number_format($summary['total_eggs_laid']) ?></div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card p-3 text-center">
        <div class="small text-muted">Sellable Eggs</div>
        <div class="h4 mb-0"><?= number_format($summary['total_eggs_sellable']) ?></div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card p-3 text-center">
        <div class="small text-muted">Egg Losses</div>
        <div class="h4 mb-0"><?= number_format($summary['total_egg_losses']) ?></div>
      </div>
    </div>
    <div class="col-md-2">
      <div class="card p-3 text-center">
        <div class="small text-muted">Avg Lay % (100w)</div>
        <div class="h4 mb-0"><?= number_format((float)$summary['avg_lay_pct'], 2) ?>%</div>
      </div>
    </div>
  </div>

  <!-- Chart -->
  <div class="card mb-4 p-3">
    <div class="small text-muted mb-2">Weekly Eggs (Laid vs Sellable)</div>
    <div style="height:400px;">
      <canvas id="eggsChart"></canvas>
    </div>
  </div>
</div>

<script>
const weeks    = <?= json_encode(array_values($weeks)) ?>;
const laid     = <?= json_encode(array_values($laid)) ?>;
const sellable = <?= json_encode(array_values($sellable)) ?>;

new Chart(document.getElementById('eggsChart').getContext('2d'), {
  type: 'line',
  data: {
    labels: weeks,
    datasets: [
      { label: 'Eggs Laid', data: laid, borderColor: '#007bff', backgroundColor: 'rgba(0,123,255,0.1)', fill: true, tension: 0.3 },
      { label: 'Sellable Eggs', data: sellable, borderColor: '#28a745', backgroundColor: 'rgba(40,167,69,0.1)', fill: true, tension: 0.3 }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    scales: {
      x: { title: { display: true, text: 'Week' } },
      y: { title: { display: true, text: 'Eggs' }, beginAtZero: true }
    },
    plugins: {
      legend: { position: 'top' },
      tooltip: { mode: 'index', intersect: false }
    }
  }
});
</script>
