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

$fmt2 = fn($v) => number_format((float)($v ?? 0), 2);
$fmt3 = fn($v) => number_format((float)($v ?? 0), 3);
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container my-4">
  <div class="mb-3">
    <?= Html::a('Back', ['operational-cost/view', 'id' => $summary['scenario_id']], ['class' => 'btn btn-outline-secondary']) ?>
  </div>

  <!-- KPI Cards -->
  <div class="row g-3 mb-4">
    <div class="col-md-2"><div class="card p-3 text-center"><div class="small text-muted">Flock Size</div><div class="h4 mb-0"><?= number_format($summary['flock_size']) ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 text-center"><div class="small text-muted">Total Eggs (Laid)</div><div class="h4 mb-0"><?= number_format($summary['total_eggs_laid']) ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 text-center"><div class="small text-muted">Sellable Eggs</div><div class="h4 mb-0"><?= number_format($summary['total_eggs_sellable']) ?></div></div></div>
    <div class="col-md-2"><div class="card p-3 text-center"><div class="small text-muted">Egg Losses</div><div class="h4 mb-0"><?= number_format($summary['total_egg_losses']) ?></div></div></div>
  </div>

  <!-- Chart: Eggs -->
  <div class="card mb-4 p-3">
    <div class="small text-muted mb-2">Weekly Eggs (Laid vs Sellable)</div>
    <div style="height:400px;">
      <canvas id="eggsChart"></canvas>
    </div>
  </div>

  <!-- Fixed Costs -->
  <div class="card p-4 mb-4 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="mb-0">Fixed Costs Overview for 100 weeks <small class="text-muted">(LKR)</small></h5>
      <span class="badge bg-secondary">100 Weeks</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
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
        <tfoot class="fw-bold table-secondary">
          <tr>
            <td>Total</td>
            <td>-</td>
            <td><?= number_format($totalCosts['eggs_total']) ?></td>
            <td><?= number_format($totalCosts['eggs_sellable']) ?></td>
            <!-- Use the SAME synced total everywhere -->
            <td><?= $fmt2($fixedCostTotal) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Feed & DOC Costs -->
  <div class="card p-4 mb-4 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Feed & DOC Costs Overview</h5>
      <span class="badge bg-secondary">100 Weeks</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Feed Type</th>
            <th>Weeks</th>
            <th class="text-end">Total Feed (kg)</th>
            <th class="text-end">Weighted Price (LKR/kg)</th>
            <th class="text-end">Total Feed Cost (LKR)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($feedByType as $row): ?>
            <tr>
              <td><?= ucfirst($row['feed_type']) ?></td>
              <td><?= (int)$row['wmin'] ?>–<?= (int)$row['wmax'] ?></td>
              <td class="text-end"><?= $fmt3($row['kg']) ?></td>
              <td class="text-end"><?= $fmt2($row['wavg_price']) ?></td>
              <td class="text-end"><?= $fmt2($row['cost']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="fw-bold">
          <tr class="table-secondary">
            <td colspan="2">Total Feed Cost</td>
            <td class="text-end"><?= $fmt3($feedTotals['kg']) ?></td>
            <td class="text-end">—</td>
            <td class="text-end"><?= $fmt2($feedTotals['cost']) ?></td>
          </tr>
          <tr>
            <td colspan="4">
              DOC one time Purchase (Week 1<?= isset($docCost['ds']) ? ', ' . htmlspecialchars($docCost['ds']) : '' ?>)
              <?php if (!empty($docCost['doc_price'])): ?>
                <small class="text-muted">(<?= $fmt2($docCost['doc_price']) ?> LKR/bird)</small>
              <?php endif; ?>
            </td>
            <td class="text-end"><?= $fmt2($docCost['cost_doc_lkr']) ?></td>
          </tr>
          <!-- Total Feed + DOC from controller (synced) -->
          <tr class="table-dark">
            <td colspan="4">Feed + Day Old Chick Cost</td>
            <td class="text-end"><?= $fmt2($feedDocTotal) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Operational Cost Summary -->
  <div class="card p-4 mb-4 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h5 class="mb-0">Operational Cost Summary (100 Weeks)</h5>
      <span class="badge bg-dark">Summary</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Bucket</th>
            <th class="text-end">Amount (LKR)</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Fixed Costs (Labor + Medicine + Transport + Electricity)</td>
            <!-- Same number as Fixed table footer -->
            <td class="text-end"><?= $fmt2($fixedCostTotal) ?></td>
          </tr>
          <tr>
            <td>Feed + DOC</td>
            <td class="text-end"><?= $fmt2($feedDocTotal) ?></td>
          </tr>
        </tbody>
        <tfoot class="fw-bold table-dark">
          <tr>
            <td>Estimated Operational Cost for 100 Weeks</td>
            <!--  Derived once in controller -->
            <td class="text-end"><?= $fmt2($grandTotal) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>

    <?php if (!empty($grand['eggs_sellable'])): ?>
      <div class="row text-center mt-2">
        <div class="col-md-4">
          <div class="small text-muted">Total Sellable Eggs</div>
          <div class="h5 mb-0"><?= number_format($grand['eggs_sellable']) ?></div>
        </div>
        <div class="col-md-4">
          <div class="small text-muted">Cost / Sellable Egg</div>
          <div class="h5 mb-0"><?= $fmt2($grandTotal / max(1, (float)$grand['eggs_sellable'])) ?></div>
        </div>
        <div class="col-md-4">
          <div class="small text-muted">Cost / Laid Egg</div>
          <div class="h5 mb-0"><?= $fmt2($grandTotal / max(1, (float)$grand['eggs_total'])) ?></div>
        </div>
      </div>
    <?php endif; ?>
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
