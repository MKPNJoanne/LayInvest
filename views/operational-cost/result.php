<?php
use yii\helpers\Html;

/** @var array $summary */
/** @var array $weeks */
/** @var array $laid */
/** @var array $sellable */
/** @var array $totalCosts */
/** @var array $baseline */

$this->title = "Analysis in week 100 (Scenario #{$summary['scenario_id']})";
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

  <!-- Chart -->
  <div class="card mb-4 p-3">
    <div class="small text-muted mb-2">Weekly Eggs (Laid vs Sellable)</div>
    <div style="height:400px;">
      <canvas id="eggsChart"></canvas>
    </div>
  </div>

  <!-- Lifecycle Cost Table -->
  <div class="card p-4 mb-4 shadow-sm border-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0">Lifecycle Fixed Costs Overview <small class="text-muted">(LKR)</small></h5>
        <span class="badge bg-secondary">100 Weeks</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Cost Type</th>
                    <th>Baseline Rate (LKR/egg)</th>
                    <th>Total Eggs</th>
                    <th>Sellable Eggs</th>
                    <th>Total Cost (LKR)</th>
                </tr>
            </thead>
            <tbody>
              <?php foreach (['labor', 'medicine', 'transport', 'electricity'] as $type): ?>
                <?php
                  // dynamic override field name like cost_labor_override
                  $overrideField = "cost_{$type}_override";
                  $rate = (isset($input[$overrideField]) && $input[$overrideField] !== null)
                    ? (float)$input[$overrideField]                 // user-provided rate
                    : (float)$baseline[$type]['base_value'];        // fallback to default
                ?>
                <tr>
                  <td><?= ucfirst($type) ?></td>
                  <td><?= number_format($rate, 3) ?></td>               <!-- Baseline Rate col now shows override if present -->
                  <td><?= number_format($totalCosts['eggs_total']) ?></td>
                  <td><?= number_format($totalCosts['eggs_sellable']) ?></td>
                  <td><?= number_format($totalCosts[$type . '_cost_lkr'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot class="fw-bold table-secondary">
                <tr>
                    <td>Total</td>
                    <td>-</td>
                    <td><?= number_format($totalCosts['eggs_total']) ?></td>
                    <td><?= number_format($totalCosts['eggs_sellable']) ?></td>
                    <td><?= number_format($totalCosts['total_cost_lkr'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
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
