<?php
use yii\helpers\Html;
use yii\bootstrap5\LinkPager;
use yii\data\Pagination;
use yii\bootstrap5\BootstrapAsset;

// $this->title = 'Revenue Overview';

$this->registerCssFile('@web/css/revenue.css', [
    'depends' => [BootstrapAsset::class],
]);

// ------------------------------
// Pagination for weeks 19–100
// ------------------------------
$totalCount = count($eggProvider->allModels); // total weeks
$pageSize   = 8; 
$pagination = new Pagination([
    'totalCount' => $totalCount,
    'pageSize'   => 8, 
]);

// Slice the array manually
$currentPageRows = array_slice(
    $eggProvider->allModels,
    $pagination->offset,
    $pagination->limit
);

// Totals
$totalEggRevenue  = array_sum(array_column($eggProvider->allModels, 'total_weekly_revenue'));
$totalCullRevenue = $cullRow['cull_revenue'] ?? 0;
$grandTotal       = $totalEggRevenue + $totalCullRevenue;
?>

<div class="page-section">
  <!-- Page Title -->
  <h2 class="h1"><?= Html::encode($this->title) ?>
    <span class="text">Estimated Revenue for 100 weeks</span>
  </h2>

  <!-- KPI Cards -->
  <div class="kpis">
    <div class="kpi blue">
      <div class="label">Total Egg Revenue</div>
      <div class="value"><?= number_format($totalEggRevenue, 2) ?> LKR</div>
    </div>

    <div class="kpi green">
      <div class="label">Total Cull Revenue</div>
      <div class="value"><?= number_format($totalCullRevenue, 2) ?> LKR</div>
    </div>

    <div class="kpi black">
      <div class="label">Total Revenue (Egg + Cull)</div>
      <div class="value"><?= number_format($grandTotal, 2) ?> LKR</div>
    </div>
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

    <!-- Pager -->
    <div class="pagination-wrap">
      <?= LinkPager::widget([
        'pagination' => $pagination,
        'options' => ['class' => 'pagination justify-content-center'],
      ]) ?>
    </div>
  </div>

  <!-- Cull Revenue Table -->
  <div class="table-card mt-5">
    <div class="card-head">
      <h3>Projected Cull Revenue (Week 100)</h3>
    </div>
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
</div>
