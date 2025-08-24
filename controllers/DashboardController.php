<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\db\Query;
use app\services\DashboardService;
use app\models\Summary;

class DashboardController extends Controller
{
    public function actionIndex(?int $week = null)
    {
        // Latest scenario
        $scenario = (new Query())
            ->from('oc.operational_cost_inputs')
            ->orderBy(['id' => SORT_DESC])
            ->one();

        if (!$scenario) {
            return $this->render('index_empty');
        }

        $scenarioId   = (int)$scenario['id'];
        $initialFlock = (int)($scenario['flock_size'] ?? $scenario['birds'] ?? 0);

        // Clamp week
        $week = $week ?? 20;
        $week = max(1, min(100, (int)$week));

        // Service-derived metrics & series 
        $svc        = new DashboardService();
        $kpi        = $svc->getKpiData($initialFlock, $week);
        $eggsRows   = $svc->getEggSeries($initialFlock, 1, 100);
        $livRows    = $svc->getLivabilitySeries(1, 100);
        $feedRows   = $svc->getFeedSeries($initialFlock, 1, 100);

        // Existing Summary helpers 
        $costRows   = Summary::opsWeeklyCosts($scenarioId);
        $priceRows  = Summary::forecastPrices($scenarioId);

       
        $revRows    = Summary::eggRevenue($scenarioId);

        $indexByWeek = function(array $rows) {
            $out=[]; foreach ($rows as $r) { $out[(int)$r['week_no']] = $r; } return $out;
        };
        $costW  = $indexByWeek($costRows);
        $priceW = $indexByWeek($priceRows);
        $revW   = $indexByWeek($revRows);

        // Fixed-cost pie (selected week)
        $c = $costW[$week] ?? [];
        $opsFixed = [
            'Labor'       => (float)($c['labor_cost_lkr']       ?? 0),
            'Medicine'    => (float)($c['medicine_cost_lkr']    ?? 0),
            'Electricity' => (float)($c['electricity_cost_lkr'] ?? 0),
            'Transport'   => (float)($c['transport_cost_lkr']   ?? 0),
        ];

        // Forecasted prices bars (selected week)
        $p = $priceW[$week] ?? [];
        $forecastBars = [
            'Feed Starter' => (float)($p['feed_starter'] ?? 0),
            'Feed Grower'  => (float)($p['feed_grower']  ?? 0),
            'Feed Layer'   => (float)($p['feed_layer']   ?? 0),
            'DOC'          => (float)($p['doc_price']    ?? 0),
            'Egg Small'    => (float)($p['egg_small']    ?? 0),
            'Egg White'    => (float)($p['egg_white']    ?? 0),
            'Cull'         => (float)($p['cull_price']   ?? 0),
        ];

        //Revenue (trend + breakdown for selected week) 
        $labels = range(1, 100);

        $revSmallSeries = [];
        $revWhiteSeries = [];
        $revTotalSeries = [];
        foreach ($labels as $w) {
            $row = $revW[$w] ?? null;
            $revSmallSeries[] = $row ? (float)$row['revenue_small']        : 0.0;
            $revWhiteSeries[] = $row ? (float)$row['revenue_white']        : 0.0;
            $revTotalSeries[] = $row ? (float)$row['total_weekly_revenue'] : 0.0;
        }

        $rw = $revW[$week] ?? [];
        $weekRevBreakdown = [
            'Small Eggs' => (float)($rw['revenue_small'] ?? 0),
            'White Eggs' => (float)($rw['revenue_white'] ?? 0),
        ];

        // Unpack other series for charts
        $eggsSeries     = array_column($eggsRows,  'total');
        $livSeries      = array_map(fn($r) => $r['livability'], $livRows);
        $feedKgSeries   = array_column($feedRows, 'feed_kg');
        $gPerBirdSeries = array_column($feedRows, 'g_per_bird_day');

        return $this->render('index', [
            'scenarioId'       => $scenarioId,
            'week'             => $week,
            'initialFlock'     => $initialFlock,
            'kpis'             => $kpi['metrics'],
            'labels'           => $labels,
            'eggsSeries'       => $eggsSeries,
            'livSeries'        => $livSeries,
            'feedKgSeries'     => $feedKgSeries,
            'gPerBirdSeries'   => $gPerBirdSeries,
            'opsFixed'         => $opsFixed,
            'forecastBars'     => $forecastBars,
            'revSmallSeries'   => $revSmallSeries,
            'revWhiteSeries'   => $revWhiteSeries,
            'revTotalSeries'   => $revTotalSeries,
            'weekRevBreakdown' => $weekRevBreakdown,
        ]);
    }
}
