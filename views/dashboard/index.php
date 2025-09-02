<?php
/** @var int $scenarioId */
/** @var int $week */
/** @var int $initialFlock */
/** @var array $kpis */
/** @var array $labels */
/** @var array $eggsSeries */
/** @var array $livSeries */
/** @var array $feedKgSeries */
/** @var array $gPerBirdSeries */
/** @var array $opsFixed */
/** @var array $forecastBars */
/** @var array $revSmallSeries */
/** @var array $revWhiteSeries */
/** @var array $revTotalSeries */
/** @var array $weekRevBreakdown */

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = "Dashboard";
$this->registerCssFile('@web/css/revenue.css');
?>

<div class="page-section">
  <!-- Header & controls -->
  <div class="table-card mb-4">
    <div class="card-head" style="background:#f5f7f5;border-radius:12px 12px 0 0;">
      <h3>Overview - Week <?= Html::encode($week) ?></h3>
      <div class="meta muted">
        <form method="get" action="<?= Url::to(['dashboard/index']) ?>" class="d-inline-flex" style="gap:10px;">
          <label class="muted">Week</label>
          <input type="number" name="week" value="<?= (int)$week ?>" min="1" max="100" class="form-control" style="width:80px;">
          <button class="btn btn-success">Go</button>
        </form>
        <!-- <span class="ms-3">Scenario #<?= Html::encode($scenarioId) ?></span> -->
      </div>
    </div>

    <!-- KPI cards -->
    <div class="kpis" style="padding:16px;">
      <div class="kpi green">
        <div class="label">Initial Flock</div>
        <div class="value"><?= number_format($initialFlock) ?> birds</div>
      </div>
      <div class="kpi green">
        <div class="label">Birds (Week <?= (int)$week ?>)</div>
        <div class="value"><?= number_format($kpis['birds_this_week'] ?? 0) ?></div>
      </div>
      <div class="kpi">
        <div class="label">Feed per bird/day</div>
        <div class="value"><?= $kpis['feed_per_bird'] !== null ? number_format($kpis['feed_per_bird'],1) : '—' ?> g</div>
      </div>

      <!-- Livability KPI -->
      <div class="kpi green">
        <div class="label">Livability</div>
        <div class="value"><?= number_format($kpis['livability'] ?? 100, 2) ?> %</div>
      </div>

      <div class="kpi">
        <div class="label">Laying Rate</div>
        <div class="value"><?= number_format($kpis['laying_rate'] ?? 0, 0) ?> %</div>
      </div>
      <div class="kpi green">
        <div class="label">FCR</div>
        <div class="value"><?= $kpis['fcr'] !== null ? $kpis['fcr'] : '—' ?></div>
      </div>
      <div class="kpi">
        <div class="label">Broken Eggs</div>
        <div class="value">
          <?= number_format($kpis['broken_eggs']['broken_percentage'] ?? 0, 2) ?>%
          <div class="muted"><?= number_format($kpis['broken_eggs']['broken_amount'] ?? 0) ?> eggs</div>
        </div>
      </div>
      <div class="kpi green">
        <div class="label">Total Eggs</div>
        <div class="value"><?= number_format($kpis['eggs_total'] ?? 0) ?></div>
      </div>
       <div class="kpi">
        <div class="label">Space Requirement</div>
       <div class="value"><?= number_format($initialFlock * 2.5, 2) ?> sq.ft</div>
      </div>
    </div>
  </div>
  

  <!-- Charts -->
  <div class="grid" style="display:grid;grid-template-columns:1fr;gap:16px;">

    <!-- Eggs -->
    <div class="table-card">
      <div class="card-head"><h3>Egg Production by Week</h3></div>
      <div class="table-wrap" style="padding:16px;">
        <canvas id="eggsByWeek" style="height:360px;"></canvas>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <!-- Livability -->
      <div class="table-card">
        <div class="card-head"><h3>Livability by Week (%)</h3></div>
        <div class="table-wrap" style="padding:16px;">
          <canvas id="livabilityByWeek" style="height:300px;"></canvas>
        </div>
      </div>

      <!-- Feed -->
      <div class="table-card">
        <div class="card-head"><h3>Feed by Week (kg & g/bird/day)</h3></div>
        <div class="table-wrap" style="padding:16px;">
          <canvas id="feedByWeek" style="height:300px;"></canvas>
        </div>
      </div>
    </div>

    <!-- NEW: Revenue charts -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <div class="table-card">
        <div class="card-head"><h3>Egg Revenue by Week (Stacked)</h3></div>
        <div class="table-wrap" style="padding:16px;">
          <canvas id="revTrend" style="height:320px;"></canvas>
        </div>
      </div>

      <div class="table-card">
        <div class="card-head"><h3>Week <?= (int)$week ?> Revenue Breakdown</h3></div>
        <div class="table-wrap" style="padding:16px;">
          <canvas id="revBreakdown" style="height:320px;"></canvas>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
      <!-- Fixed Ops Cost pie -->
      <div class="table-card">
        <div class="card-head"><h3>Operational Cost Breakdown (Fixed) — Week <?= (int)$week ?></h3></div>
        <div class="table-wrap" style="padding:16px;">
          <canvas id="opsPie" style="height:320px;"></canvas>
        </div>
      </div>

      <!-- Forecasted prices for selected week -->
      <div class="table-card">
        <div class="card-head"><h3>Forecasted Prices — Week <?= (int)$week ?></h3></div>
        <div class="table-wrap" style="padding:16px;">
          <canvas id="forecastBars" style="height:320px;"></canvas>
        </div>
      </div>
    </div>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const labels         = <?= json_encode($labels) ?>;
const eggsSeries     = <?= json_encode($eggsSeries) ?>;
const livSeries      = <?= json_encode($livSeries) ?>;
const feedKgSeries   = <?= json_encode($feedKgSeries) ?>;
const gPerBird       = <?= json_encode($gPerBirdSeries) ?>;

const opsLabels      = <?= json_encode(array_keys($opsFixed)) ?>;
const opsValues      = <?= json_encode(array_values($opsFixed)) ?>;

const fpLabels       = <?= json_encode(array_keys($forecastBars)) ?>;
const fpValues       = <?= json_encode(array_values($forecastBars)) ?>;

// NEW revenue data
const revSmallSeries = <?= json_encode($revSmallSeries) ?>;
const revWhiteSeries = <?= json_encode($revWhiteSeries) ?>;
const revTotalSeries = <?= json_encode($revTotalSeries) ?>;
const revBreakLabels = <?= json_encode(array_keys($weekRevBreakdown)) ?>;
const revBreakValues = <?= json_encode(array_values($weekRevBreakdown)) ?>;

const fmtLKR = v => 'LKR ' + (v ?? 0).toLocaleString();

// Eggs (bar)
new Chart(document.getElementById('eggsByWeek'), {
  type: 'bar',
  data: { labels, datasets: [{ label: 'Total Eggs', data: eggsSeries }]},
  options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } }
});

// Livability (line)
new Chart(document.getElementById('livabilityByWeek'), {
  type: 'line',
  data: { labels, datasets: [{ label: 'Livability %', data: livSeries, tension: .25 }]},
  options: {
    responsive:true, maintainAspectRatio:false,
    scales:{ y:{  min: 85, max:100, ticks:{ callback:v => v+'%' } } }
  }
});

// Feed (dual axis)
new Chart(document.getElementById('feedByWeek'), {
  type: 'line',
  data: {
    labels,
    datasets: [
      { label: 'kg/week', data: feedKgSeries, fill:true,  yAxisID:'y1', tension:.25 },
      { label: 'g/bird/day', data: gPerBird,  fill:false, yAxisID:'y2', tension:.25 }
    ]
  },
  options: {
    responsive:true, maintainAspectRatio:false,
    scales:{
      y1:{ type:'linear', position:'left', beginAtZero:true, title:{ display:true, text:'kg/week'} },
      y2:{ type:'linear', position:'right', beginAtZero:true, title:{ display:true, text:'g/bird/day'}, grid:{ drawOnChartArea:false } }
    }
  }
});

// ===== NEW: Revenue charts =====

// Stacked bar + total line
new Chart(document.getElementById('revTrend'), {
  data: {
    labels,
    datasets: [
      { type: 'bar',  label: 'Small Eggs', data: revSmallSeries, stack: 'rev' },
      { type: 'bar',  label: 'White Eggs', data: revWhiteSeries, stack: 'rev' },
      { type: 'line', label: 'Total', data: revTotalSeries, tension: .25, yAxisID: 'y', pointRadius: 0 }
    ]
  },
  options: {
    responsive: true, maintainAspectRatio: false,
    scales: {
      x: { stacked: true },
      y: { stacked: true, beginAtZero: true, ticks: { callback: v => fmtLKR(v) } }
    },
    plugins: {
      tooltip: {
        callbacks: {
          label: ctx => `${ctx.dataset.label}: ${fmtLKR(ctx.parsed.y)}`
        }
      }
    }
  }
});

// Donut breakdown for selected week
new Chart(document.getElementById('revBreakdown'), {
  type: 'doughnut',
  data: { labels: revBreakLabels, datasets: [{ data: revBreakValues }] },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: {
      tooltip: { callbacks: { label: ctx => `${ctx.label}: ${fmtLKR(ctx.parsed)}` } },
      legend: { position: 'bottom' }
    },
    cutout: '65%'
  }
});

// Ops fixed pie
new Chart(document.getElementById('opsPie'), {
  type:'pie',
  data:{ labels: opsLabels, datasets:[{ data: opsValues }] },
  options:{ responsive:true, maintainAspectRatio:false,
    plugins:{ tooltip: { callbacks: { label: ctx => `${ctx.label}: ${fmtLKR(ctx.parsed)}` } } }
  }
});

// Forecasted prices (bars)
new Chart(document.getElementById('forecastBars'), {
  type:'bar',
  data:{ labels: fpLabels, datasets:[{ label:'Price', data: fpValues }] },
  options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } }
});
</script>
