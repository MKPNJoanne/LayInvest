<?php
use yii\helpers\Html;
use yii\helpers\Url;

function warningBadge($value, $warnings, $keyword, $unit = '') {
    foreach ($warnings as $warn) {
        if (stripos($warn, $keyword) !== false) {
            $colorClass = stripos($warn, 'critical') !== false ? 'badge-critical' : 'badge-warning';
            return "<span class='{$colorClass}'>⚠ " . Html::encode($value) . "{$unit}</span>";
        }
    }
    return Html::encode($value) . $unit;
}
?>

<link rel="stylesheet" href="<?= Yii::getAlias('@web/css/dashboard.css') ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="container-fluid py-3 dashboard-container">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between sticky-topbar">
        <h2 class="mb-0">Overview - Week <?= Html::encode($week) ?></h2>
        <form method="get" action="<?= Url::to(['dashboard/index']) ?>" id="dashboard-filter-form" class="d-flex gap-3">
            <?= Html::hiddenInput('r', 'dashboard/index') ?>

            <div>
                <label for="week" class="form-label small text-muted">Week</label>
                <?= Html::dropDownList('week', $week, array_combine(range(1, 100), range(1, 100)), [
                    'id' => 'week',
                    'class' => 'form-control',
                    'onchange' => 'this.form.submit();'
                ]) ?>
            </div>

            <div>
                <label for="flock_size" class="form-label small text-muted">Flock Size</label>
                <?= Html::input('number', 'flock_size', $flockSize, [
                    'id' => 'flock_size',
                    'class' => 'form-control',
                    'min' => 500,
                    'max' => 5000,
                    'onchange' => 'this.form.submit();'
                ]) ?>
            </div>
        </form>
    </div>

    <!-- KPI Row -->
    <div class="row g-3 mt-1">

        <?php
        $kpis = [
                ['label' => 'Initial Flock', 'value' => Html::encode($data['metrics']['initial_flock']) . ' birds', 'highlight' => true],
                ['label' => 'Birds (Week ' . Html::encode($week) . ')', 'value' => number_format($data['metrics']['birds_this_week']), 'highlight' => true],
                ['label' => 'Feed per bird/day', 'value' => warningBadge($data['metrics']['feed_per_bird'] ?? 0, $warnings ?? [], 'Feed', ' g')],
                ['label' => 'Mortality (this week)', 'value' => Html::encode($data['metrics']['mortality']['deaths'] ?? 0) . ' birds (' . Html::encode($data['metrics']['mortality']['percent'] ?? 0) . '%)', 'highlight' => true],
                ['label' => 'Laying Rate', 'value' => warningBadge($data['metrics']['laying_rate'] ?? 0, $warnings ?? [], 'Laying rate', ' %')],
                ['label' => 'FCR', 'value' => $data['metrics']['fcr'] !== null ? Html::encode($data['metrics']['fcr']) : 'N/A', 'highlight' => true],
                ['label' => 'Broken Eggs', 'value' => number_format($broken_eggs_pct, 2) . '%<br><small>' . number_format($broken_eggs_amount) . ' eggs</small>'],
                ['label' => 'Total Eggs', 'value' => Html::encode($data['metrics']['eggs_total'] ?? 0)],
            ];

        foreach ($kpis as $kpi): ?>
            <div class="col-md-2 col-sm-6">
                <div class="card kpi p-3 text-center <?= !empty($kpi['highlight']) ? 'light-green' : '' ?>">
                    <div class="small-muted"><?= $kpi['label'] ?></div>
                    <div class="value"><?= $kpi['value'] ?></div>
                </div>
            </div>
        <?php endforeach; ?>

    </div>

    <!-- Charts -->
    <div class="row g-3 mt-3">
        <div class="col-12">
            <div class="card p-3">
                <div class="small-muted">Egg Production by Week</div>
                <canvas id="eggsChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <div class="small-muted">Mortality by Week (%)</div>
                <canvas id="mortChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card p-3">
                <div class="small-muted">Feed by Week (kg & g/bird/day)</div>
                <canvas id="feedChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
const eggSeries = <?= json_encode($eggSeries ?? []) ?>;
const mortSeries = <?= json_encode($mortalitySeries ?? []) ?>;
const feedSeries = <?= json_encode($feedSeries ?? []) ?>;

new Chart(document.getElementById('eggsChart'), {
    type: 'bar',
    data: {
        labels: eggSeries.map(r => r.week_no),
        datasets: [{ label:'Total Eggs', data: eggSeries.map(r=>+r.total), backgroundColor:'rgba(54,162,235,.6)' }]
    },
    options: { plugins: { legend: { position:'top' } } }
});

new Chart(document.getElementById('mortChart'), {
    type: 'line',
    data: {
        labels: mortSeries.map(r=>r.week_no),
        datasets: [{ label:'Mortality %', data: mortSeries.map(r=>+r.mortality), tension:.25, borderWidth:2, fill:false, borderColor:'#e74c3c' }]
    },
    options: { scales: { y: { beginAtZero: true, suggestedMax: 5 } } }
});

new Chart(document.getElementById('feedChart'), {
    type: 'line',
    data: {
        labels: feedSeries.map(r => r.week_no),
        datasets: [{
            label: 'Total feed (kg/week)',
            data: feedSeries.map(r => r.feed_kg),
            borderColor: '#FFA500',
            backgroundColor: 'rgba(255,165,0,0.1)',
            fill: true,
            tension: 0.3,
            pointRadius: 2,
            pointBackgroundColor: '#FFA500'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { title: { display: true, text: 'kg/week' } } }
    }
});
</script>
