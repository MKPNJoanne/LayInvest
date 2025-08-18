<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\data\ArrayDataProvider;

class RevenueController extends Controller
{
    public function actionView()
    {
        $db = Yii::$app->db;

        // 1) Latest scenario
        $scenarioId = $db->createCommand("
            SELECT id
            FROM oc.operational_cost_inputs
            ORDER BY id DESC
            LIMIT 1
        ")->queryScalar();

        if (!$scenarioId) {
            throw new NotFoundHttpException("No scenarios found. Please create one first.");
        }

        // 2) Egg revenue
        $eggRows = $db->createCommand("
            SELECT *
            FROM oc.calc_egg_revenue_weekly(:sid)
            WHERE week_no >= 19
            ORDER BY week_no
        ")->bindValue(':sid', (int)$scenarioId)->queryAll();

        $eggProvider = new ArrayDataProvider([
            'allModels'  => $eggRows,
            'pagination' => ['pageSize' => 8],
        ]);

        // 3) Cull revenue
        $cullRow = $db->createCommand("
            SELECT *
            FROM oc.calc_cull_revenue(:sid)
        ")->bindValue(':sid', (int)$scenarioId)->queryOne();

        $costSummary = (new \yii\db\Query())
        ->select([
        'total_cost' => 'SUM(total_cost_lkr)',
        'cost_feed' => 'SUM(cost_feed_lkr)',
        'cost_labor' => 'SUM(labor_cost_lkr)',
        'cost_medicine' => 'SUM(medicine_cost_lkr)',
        'cost_transport' => 'SUM(transport_cost_lkr)',
        'cost_electricity' => 'SUM(electricity_cost_lkr)',
        ])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenarioId])
        ->one();

        // Eggs totals for cost-per-egg cards
        $eggTotals = (new \yii\db\Query())
            ->select([
                'eggs_total'     => 'COALESCE(SUM(eggs_total),0)',
                'eggs_sellable'  => 'COALESCE(SUM(eggs_sellable),0)',
            ])
            ->from('oc.scenario_operational_costs')
            ->where(['scenario_id' => $scenarioId])
            ->one();

        $eggsTotal    = (int)$eggTotals['eggs_total'];
        $eggsSellable = (int)$eggTotals['eggs_sellable'];

        $costPerEggLaid = $eggsTotal > 0
            ? (float)$costSummary['total_cost'] / $eggsTotal
            : 0.0;

        $costPerEggSellable = $eggsSellable > 0
            ? (float)$costSummary['total_cost'] / $eggsSellable
            : 0.0;


    // --- Revenue Summary ---
        $totalEggRevenue = array_sum(array_column($eggProvider->allModels, 'total_weekly_revenue'));
        $totalCullRevenue = $cullRow['cull_revenue'] ?? 0;
        $totalRevenue = $totalEggRevenue + $totalCullRevenue;

    // --- Profit Calculation ---
        $grossProfit = $totalRevenue - $costSummary['total_cost'];
        $profitMargin = $totalRevenue > 0
        ? ($grossProfit / $totalRevenue) * 100
        : 0;
        // 5) Break-even curve
        $curve = $db->createCommand("
            SELECT week_no, ds,
                   cum_cost,
                   cum_rev_eggs,
                   cum_rev_with_cull,
                   cum_profit_eggs,
                   cum_margin_eggs,
                   cum_profit_with_cull,
                   cum_margin_with_cull
            FROM oc.vw_break_even_curve
            WHERE scenario_id = :sid
            ORDER BY week_no
        ")->bindValue(':sid', (int)$scenarioId)->queryAll();

        // 6) Break-even weeks
        $breakEven = $db->createCommand("
            SELECT
              MIN(CASE WHEN cum_profit_eggs >= 0 THEN week_no END)      AS break_even_eggs,
              MIN(CASE WHEN cum_profit_with_cull >= 0 THEN week_no END) AS break_even_with_cull
            FROM oc.vw_break_even_curve
            WHERE scenario_id = :sid
        ")->bindValue(':sid', (int)$scenarioId)->queryOne();

        // 7) KPI totals
        $totalEggRevenue  = array_sum(array_column($eggRows, 'total_weekly_revenue'));
        $totalCullRevenue = isset($cullRow['cull_revenue']) ? (float)$cullRow['cull_revenue'] : 0.0;
        $grandTotals = [
            'egg'   => $totalEggRevenue,
            'cull'  => $totalCullRevenue,
            'total' => $totalEggRevenue + $totalCullRevenue,
        ];

        // 8) Final row for gross profit (needed by view)
        $finalRow = !empty($curve) ? end($curve) : null;
        // 8b) Prepare chart data arrays for the view
        $chartData = [
            'weeks'             => array_column($curve, 'week_no'),
            'cum_cost'          => array_column($curve, 'cum_cost'),
            'cum_rev_eggs'      => array_column($curve, 'cum_rev_eggs'),
            'cum_rev_with_cull' => array_column($curve, 'cum_rev_with_cull'),
        ];

        
        // 9) Send to view
        return $this->render('view', [
            'scenarioId'  => (int)$scenarioId,
            'eggProvider' => $eggProvider,
            'cullRow'     => $cullRow,
            'costSummary' => $costSummary,
            'curve'       => $curve,
            'breakEven'   => $breakEven,
            'grand'       => $grandTotals,
            'finalRow'    => $finalRow,
            'chartData'   => $chartData,
            'totalEggRevenue' => $totalEggRevenue,
            'totalCullRevenue' => $totalCullRevenue,
            'totalRevenue' => $totalRevenue,
            'grossProfit' => $grossProfit,
            'profitMargin' => $profitMargin,
            'eggsTotal'     => $eggsTotal,
            'eggsSellable'  => $eggsSellable,
            'costPerEggLaid' => $costPerEggLaid,
            'costPerEggSellable' => $costPerEggSellable,
        ]);
    }
}
