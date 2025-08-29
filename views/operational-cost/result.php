<?php
use yii\helpers\Html;

/** @var array $summary */
/** @var array $weeks */
/** @var array $laid */
/** @var array $sellable */
/** @var array $totalCosts */
/** @var array $baseline */
/** @var array $input */
/** @var array $feedByType */
/** @var array $feedTotals */
/** @var array $docCost */
/** @var array $weeklyFeed */
/** @var array $grand */
/** @var float $fixedCostTotal */
/** @var float $feedDocTotal */
/** @var float $grandTotal */

$this->title = "Cost Analysis for 100 week Lifecycle";
$this->registerCssFile('@web/css/revenue.css', [
    'depends' => [\yii\bootstrap5\BootstrapAsset::class],
]);

$fmt2 = fn($v) => number_format((float)($v ?? 0), 2);
$fmt3 = fn($v) => number_format((float)($v ?? 0), 3);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="page-section">

<!-- Back Button & Start a new flock -->

<div class="d-flex justify-content-end mb-3">
    <?= Html::a('← Back', ['operational-cost/back'], ['class' => 'btn btn-success me-2']) ?>
    <?= Html::a(
    '+ Start Fresh',
    ['operational-cost/start-fresh'],
    [
        'class' => 'btn btn-danger',
        'data' => [
            'confirm' => 'This will archive all previous flocks and start a new one. Continue?',
            'method' => 'post',
          ],
       ]
      )   
    ?>

</div>



  <!-- KPI Row -->
  <div class="kpis">
    <div class="kpi kpi--compact">
      <div class="label">Flock Size</div>
      <div class="value"><?= number_format($summary['flock_size']) ?></div>
    </div>

    <div class="kpi green kpi--compact">
      <div class="label">Total Eggs (Laid) in 100 Weeks</div>
      <div class="value"><?= number_format($summary['total_eggs_laid']) ?></div>
    </div>

    <div class="kpi kpi--compact">
      <div class="label">Sellable Eggs</div>
      <div class="value"><?= number_format($summary['total_eggs_sellable']) ?></div>
    </div>

    <div class="kpi green kpi--compact">
      <div class="label">Egg Losses</div>
      <div class="value"><?= number_format($summary['total_egg_losses']) ?></div>
    </div>

    <!-- NEW: cost KPIs -->
    <div class="kpi kpi--compact">
      <div class="label">Fixed Cost (100 Weeks)</div>
      <div class="value"><?= $fmt2($fixedCostTotal) ?> LKR</div>
    </div>

    <!-- <div class="kpi kpi--compact">
      <div class="label">Feed + DOC Cost</div>
      <div class="value"><?= $fmt2($feedDocTotal) ?> LKR</div>
    </div> -->

    <div class="kpi green kpi--compact">
      <div class="label">Estimated Operational Cost</div>
      <div class="value"><?= $fmt2($grandTotal) ?> LKR</div>
    </div>
  </div>

  <!-- Chart -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Weekly Eggs (Laid vs Sellable)</h3>
      <span class="meta">100 Weeks</span>
    </div>
    <div class="p-3" style="height:400px;">
      <canvas id="eggsChart"></canvas>
    </div>
  </div>

  <!-- Fixed Costs -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Fixed Costs Overview (100 Weeks)</h3>
      <span class="meta">Values in LKR (HARTI baseline)</span>
    </div>
    <div class="table-wrap">
      <table class="table-modern">
        <thead>
          <tr>
            <th>Cost Type</th>
            <th>Rate (LKR/egg)</th>
            <th>Total Eggs</th>
            <th>Sellable Eggs</th>
            <th>Total Cost (LKR)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (['labor', 'medicine', 'transport', 'electricity'] as $type): ?>
            <?php
              $overrideField = "cost_{$type}_override";
              $rate = isset($input[$overrideField]) && $input[$overrideField] !== null
                ? $input[$overrideField]
                : $baseline[$type]['base_value'];
            ?>
            <tr>
              <td><?= ucfirst($type) ?></td>
              <td><?= $fmt3($rate) ?></td>
              <td><?= number_format($totalCosts['eggs_total']) ?></td>
              <td><?= number_format($totalCosts['eggs_sellable']) ?></td>
              <td><?= $fmt2($totalCosts[$type . '_cost_lkr']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td>Total</td>
            <td>-</td>
            <td><?= number_format($totalCosts['eggs_total']) ?></td>
            <td><?= number_format($totalCosts['eggs_sellable']) ?></td>
            <td><?= $fmt2($fixedCostTotal) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Feed & DOC -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Feed & DOC Costs Overview</h3>
      <span class="meta">100 Weeks</span>
    </div>
    <div class="table-wrap">
      <table class="table-modern">
        <thead>
          <tr>
            <th>Feed Type</th>
            <th>Weeks</th>
            <th>Total Feed (kg)</th>
            <th>Weighted Price (LKR/kg)</th>
            <th>Total Feed Cost (LKR)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($feedByType as $row): ?>
            <tr>
              <td><?= ucfirst($row['feed_type']) ?></td>
              <td><?= (int)$row['wmin'] ?>–<?= (int)$row['wmax'] ?></td>
              <td><?= $fmt3($row['kg']) ?></td>
              <td><?= $fmt2($row['wavg_price']) ?></td>
              <td><?= $fmt2($row['cost']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
        <tr>
          <td colspan="3">Total Feed Cost</td>
          <td></td>
          <td><?= $fmt2($feedTotals['cost']) ?></td>
        </tr>
        <tr>
          <td colspan="4" class="muted">
            DOC Purchase (Week <?= $docCost['week_no'] ?? 1 ?>, <?= $docCost['ds'] ?? '' ?>):
            <?= $fmt2($docForecast['doc_price'] ?? 0) ?> LKR
          </td>
          <td><?= $fmt2($docCost['cost_doc_lkr']) ?></td>
        </tr>
        <tr class="fw-bold">
          <td colspan="4">Feed + Day Old Chick Cost</td>
          <td><?= $fmt2($feedDocTotal) ?></td>
        </tr>
      </tfoot>
      </table>
    </div>
  </div>

  <!-- Cost Summary -->
  <div class="table-card mb-4">
    <div class="card-head">
      <h3>Estimated Operational Cost Summary (100 Weeks)</h3>
    </div>
    <div class="table-wrap">
      <table class="table-modern">
        <thead>
          <tr>
            <th>Bucket</th>
            <th>Amount (LKR)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Fixed Costs (Labor, Medicine, Transport, Electricity)</td>
            <td><?= $fmt2($fixedCostTotal) ?></td>
          </tr>
          <tr>
            <td>Feed + DOC</td>
            <td><?= $fmt2($feedDocTotal) ?></td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="fw-bold">
            <td>Estimated Total Operational Cost</td>
            <td><?= $fmt2($grandTotal) ?></td>
          </tr>
        </tfoot>
      </table>
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
    }
  }
});
</script>
