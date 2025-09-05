<?php
use yii\helpers\Html;
use yii\bootstrap5\LinkPager;
use yii\data\Pagination;
use yii\bootstrap5\BootstrapAsset;

$this->title = 'Revenue & Break-even Overview';

$this->registerCssFile('@web/css/revenue.css', [
    'depends' => [BootstrapAsset::class],
]);

$totalCount = count($eggProvider->allModels);
$pageSize   = 8; 
$pagination = new Pagination([
    'totalCount' => $totalCount,
    'pageSize'   => 8, 
]);

$currentPageRows = array_slice(
    $eggProvider->allModels,
    $pagination->offset,
    $pagination->limit
);

$totalEggRevenue  = $grand['egg'];
$totalCullRevenue = $grand['cull'];
$grandTotal       = $grand['total'];
?>

<div class="page-section">
  
    <!-- <span class="text">Estimated Revenue & Break-even (100 weeks)</span> -->
  </h2>

  <!-- KPI Cards -->
  <div class="kpis">
    <div class="kpi ">
      <div class="label">Total Egg Revenue</div>
      <div class="value"><?= number_format($totalEggRevenue, 2) ?> LKR</div>
    </div>

    <div class="kpi green">
      <div class="label">Total Cull Revenue</div>
      <div class="value"><?= number_format($totalCullRevenue, 2) ?> LKR</div>
    </div>

    <div class="kpi ">
      <div class="label">Total Revenue (Egg + Cull)</div>
      <div class="value"><?= number_format($grandTotal, 2) ?> LKR</div>
    </div>

    <div class="kpi green">
      <div class="label">Break-even (White Eggs only)</div>
      <div class="value"><?= $breakEven['break_even_eggs'] ?: 'N/A' ?> week</div>
    </div>

    <!-- <div class="kpi purple">
      <div class="label">Break-even (With Cull)</div>
      <div class="value"><?= $breakEven['break_even_with_cull'] ?: 'N/A' ?> week</div>
    </div> -->

    <?php if ($finalRow): ?>
    <div class="kpi ">
      <div class="label">Gross Profit (White Eggs only)</div>
      <div class="value"><?= number_format($finalRow['cum_profit_eggs'], 2) ?> LKR</div>
      
    </div>

    <div class="kpi green">
      <div class="label">Gross Profit (With Cull)</div>
      <div class="value"><?= number_format($finalRow['cum_profit_with_cull'], 2) ?> LKR</div>
     
    </div>
    <?php endif; ?>
  </div>

  <!-- Egg Revenue Table -->
  <div class="table-card mt-5">
    <div class="card-head">
      <h3>Egg Revenue (Weeks 19–100)</h3>
      <span class="meta">Showing weeks <?= $currentPageRows[0]['week_no'] ?? '' ?> - <?= end($currentPageRows)['week_no'] ?? '' ?></span>
    </div>
    <div class="table-wrap">
      <table class="table-modern">
        <thead>
          <tr>
            <th>Week</th>
            <th>Date</th>
            <th>Sellable Eggs</th>
            <th>Small Eggs</th>
            <th>White Eggs</th>
            <th>Price Small (LKR)</th>
            <th>Price White (LKR)</th>
            <th>Revenue Small (LKR)</th>
            <th>Revenue White (LKR)</th>
            <th>Total Egg Revenue (LKR)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($currentPageRows as $row): ?>
            <tr>
              <td><?= $row['week_no'] ?></td>
              <td><?= Yii::$app->formatter->asDate($row['ds']) ?></td>
              <td><?= number_format($row['eggs_sellable']) ?></td>
              <td><?= number_format($row['eggs_small']) ?></td>
              <td><?= number_format($row['eggs_white']) ?></td>
              <td><?= number_format($row['price_small'], 2) ?></td>
              <td><?= number_format($row['price_white'], 2) ?></td>
              <td><?= number_format($row['revenue_small'], 2) ?></td>
              <td><?= number_format($row['revenue_white'], 2) ?></td>
              <td><?= number_format($row['total_weekly_revenue'], 2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="pagination-wrap">
      <?= LinkPager::widget([
        'pagination' => $pagination,
        'options' => ['class' => 'pagination justify-content-center'],
      ]) ?>
    </div>
  </div>

  <!-- Cull Revenue Table -->
  <div class="table-card mt-5">
    <div class="card-head"><h3>Projected Cull Revenue (Week 100)</h3></div>
    <div class="table-wrap">
      <?php if ($cullRow): ?>
        <table class="table-modern">
          <thead>
            <tr>
              <th>Week</th>
              <th>Date</th>
              <th>Birds Alive</th>
              <th>Std Weight (g)</th>
              <th>Total Weight (kg)</th>
              <th>Cull Price (LKR/kg)</th>
              <th>Total Cull Revenue (LKR)</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?= $cullRow['week_no'] ?></td>
              <td><?= Yii::$app->formatter->asDate($cullRow['ds']) ?></td>
              <td><?= number_format($cullRow['birds_alive']) ?></td>
              <td><?= number_format($cullRow['std_weight_g'], 2) ?></td>
              <td><?= number_format($cullRow['total_weight_kg'], 2) ?></td>
              <td><?= number_format($cullRow['cull_price'], 2) ?></td>
              <td><?= number_format($cullRow['cull_revenue'], 2) ?></td>
            </tr>
          </tbody>
        </table>
      <?php else: ?>
        <p class="muted">No cull revenue data available.</p>
      <?php endif; ?>
    </div>
  </div>

<div class="container mt-5">



    <!-- Revenue Section -->

        <div class="statement-card mb-4">
        <div class="card-header">Financial Summary (100 Weeks)</div>
        <div class="card-body p-0">
            <div class="statement-wrap">
            <table class="statement-table">

        <tbody>

          <!-- Revenue -->
          <tr class="section-row"><td colspan="2">REVENUE</td></tr>
          <tr>
            <td>Egg Revenue</td>
            <td class="amount"><?= number_format($totalEggRevenue, 2) ?> LKR</td>
          </tr>
          <tr>
            <td>Cull Revenue</td>
            <td class="amount"><?= number_format($totalCullRevenue, 2) ?> LKR</td>
          </tr>
          <tr class="total-row">
            <td>Total Revenue</td>
            <td class="amount"><?= number_format($totalRevenue, 2) ?> LKR</td>
          </tr>
        
          <!-- Operational Costs -->
          <tr class="section-row"><td colspan="2">Operational Costs</td></tr>
          <tr>
            <td>Feed</td>
            <td class="amount"><?= number_format($costSummary['cost_feed'], 2) ?> LKR</td>
          </tr>
          <tr>
            <td>Labor</td>
            <td class="amount"><?= number_format($costSummary['cost_labor'], 2) ?> LKR</td>
          </tr>
          <tr>
            <td>Medicine</td>
            <td class="amount"><?= number_format($costSummary['cost_medicine'], 2) ?> LKR</td>
          </tr>
          <tr>
            <td>Transport</td>
            <td class="amount"><?= number_format($costSummary['cost_transport'], 2) ?> LKR</td>
          </tr>
          <tr>
            <td>Electricity</td>
            <td class="amount"><?= number_format($costSummary['cost_electricity'], 2) ?> LKR</td>
          </tr>
          <tr class="total-row">
            <td>Total Operational Cost</td>
            <td class="amount"><?= number_format($costSummary['total_cost'], 2) ?> LKR</td>
          </tr>

          <!-- Profitability -->
          <tr class="section-row"><td colspan="2">PROFITABILITY</td></tr>
          <tr>
            <td>
              Gross Profit 
              <span class="metric-note">(Revenue − COGS)</span>
            </td>
            <td class="amount"><?= number_format($grossProfit, 2) ?> LKR</td>
          </tr>
          <tr>
            <td>
              Gross Profit Margin 
              <span class="metric-note">((Profit ÷ Revenue) × 100)</span>
            </td>
            <td class="amount"><?= number_format($profitMargin, 2) ?>%</td>
          </tr>

          <!-- Unit Economics -->
          <tr class="section-row"><td colspan="2">UNIT ECONOMICS</td></tr>
          <tr>
            <td>
              Cost per  Egg (Laid) 
              <span class="metric-note">
                (Total Cost ÷ Total Eggs Laid → <?= number_format($eggsTotal) ?> eggs)
              </span>
            </td>
            <td class="amount"><?= number_format($costPerEggLaid, 2) ?> LKR</td>
          </tr>
          <tr>
            <td>
              Cost per Sellable Egg 
              <span class="metric-note">
                (Total Cost ÷ Sellable Eggs → <?= number_format($eggsSellable) ?> eggs)
              </span>
            </td>
            <td class="amount"><?= number_format($costPerEggSellable, 2) ?> LKR</td>
          </tr>
          <!-- Disclaimer row -->
          <tr>
            <td colspan="2" class="disclaimer">
              <em>
                Note: Estimates are based on the best-case scenario of egg laying percentages obtained from the farm guide.  
                Maintenance, water, housing, and other utility costs are excluded from cost per egg calculations.
              </em>
            </td>
          </tr>

        </tbody>
      </table>
    </div>
  </div>
</div>


  <!-- Break-even Chart -->
  <div class="table-card mt-5">
    <div class="card-head"><h3>Break-even Analysis</h3></div>
    <canvas id="breakEvenChart" height="100"></canvas>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    const ctx = document.getElementById('breakEvenChart');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartData['weeks']) ?>,
            datasets: [
                {
                    label: 'Cumulative Cost',
                    data: <?= json_encode($chartData['cum_cost']) ?>,
                    borderColor: 'red',
                    fill: false
                },
                {
                    label: 'Cumulative Revenue (Eggs)',
                    data: <?= json_encode($chartData['cum_rev_eggs']) ?>,
                    borderColor: 'green',
                    fill: false
                },
                // {
                //     label: 'Cumulative Revenue (Eggs + Cull)',
                //     data: <?= json_encode($chartData['cum_rev_with_cull']) ?>,
                //     borderColor: 'blue',
                //     fill: false
                // }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'LKR' } },
                x: { title: { display: true, text: 'Week' } }
            }
        }
    });
    </script>
  </div>

</div>
