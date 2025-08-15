<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use app\models\OperationalCostInput;

class OperationalCostController extends Controller
{
    /**
     * Create form: saves an input row into oc.operational_cost_inputs
     * and immediately populates the scenario outputs.
     */
    public function actionCreate()
    {
        $model = new OperationalCostInput();

        // Load defaults from oc_baselines table
        $defaults = (new \yii\db\Query())
            ->select(['cost_type', 'base_value', 'monthly_increment_pct'])
            ->from('oc.oc_baselines')
            ->indexBy('cost_type')
            ->all();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {

            // Validate flock size
            if ($model->flock_size < 500 || $model->flock_size > 5000) {
                $model->addError('flock_size', 'Flock size must be between 500 and 5000.');
            } else {
                // Fill defaults for empty inputs
                $model->cost_labor_override       = $this->useDefaultIfEmpty($model->cost_labor_override,       $defaults, 'labor');
                $model->cost_electricity_override = $this->useDefaultIfEmpty($model->cost_electricity_override, $defaults, 'electricity');
                $model->cost_medicine_override    = $this->useDefaultIfEmpty($model->cost_medicine_override,    $defaults, 'medicine');
                $model->cost_transport_override   = $this->useDefaultIfEmpty($model->cost_transport_override,   $defaults, 'transport');

                // Save input
                Yii::$app->db->createCommand()->insert('oc.operational_cost_inputs', [
                    'start_date'                => $model->start_date,
                    'flock_size'                => $model->flock_size,
                    'cost_labor_override'       => $model->cost_labor_override,
                    'cost_electricity_override' => $model->cost_electricity_override,
                    'cost_medicine_override'    => $model->cost_medicine_override,
                    'cost_transport_override'   => $model->cost_transport_override,
                    'created_at'                => date('Y-m-d H:i:s'),
                ])->execute();

                $id = (int)Yii::$app->db->getLastInsertID();

                // Populate outputs
                $this->runCalculations($id, $model->start_date, (int)$model->flock_size);

                Yii::$app->session->setFlash('success', 'Saved and calculated for 100 weeks.');
                return $this->redirect(['view', 'id' => $id]);
            }
        }

        return $this->render('create', [
            'model'    => $model,
            'defaults' => $defaults
        ]);
    }

    /**
     * Helper to replace empty values with defaults.
     */
    private function useDefaultIfEmpty($value, $defaults, $key)
    {
        return (empty($value) && isset($defaults[$key]))
            ? $defaults[$key]['base_value']
            : $value;
    }

    /**
     * View single input row + lifecycle totals.
     */
    public function actionView($id)
    {
        $id = (int)$id;

        $model = (new \yii\db\Query())
            ->from('oc.operational_cost_inputs')
            ->where(['id' => $id])
            ->one();

        if (!$model) {
            throw new NotFoundHttpException('Record not found.');
        }

        $baseline = (new \yii\db\Query())
            ->select(['cost_type', 'base_value', 'monthly_increment_pct'])
            ->from('oc.oc_baselines')
            ->indexBy('cost_type')
            ->all();

        $hasResults = (new \yii\db\Query())
            ->from('oc.scenario_egg_summary')
            ->where(['scenario_id' => $id])
            ->exists();

        // Lifecycle totals
        $totalCosts = (new \yii\db\Query())
            ->select([
                'eggs_total'           => 'SUM(eggs_total)',
                'eggs_sellable'        => 'SUM(eggs_sellable)',
                'labor_cost_lkr'       => 'SUM(labor_cost_lkr)',
                'medicine_cost_lkr'    => 'SUM(medicine_cost_lkr)',
                'transport_cost_lkr'   => 'SUM(transport_cost_lkr)',
                'electricity_cost_lkr' => 'SUM(electricity_cost_lkr)',
                'total_cost_lkr'       => 'SUM(total_cost_lkr)'
            ])
            ->from('oc.scenario_operational_costs')
            ->where(['scenario_id' => $id])
            ->one();

        $lastRunAt = (new \yii\db\Query())
            ->select(['max_created' => 'MAX(created_at)'])
            ->from('oc.scenario_operational_costs')
            ->where(['scenario_id' => $id])
            ->scalar();

        return $this->render('view', [
            'model'      => $model,
            'totalCosts' => $totalCosts,
            'baseline'   => $baseline,
            'hasResults' => $hasResults,
            'lastRunAt'  => $lastRunAt,
        ]);
    }

    /**
     * Recalculate data manually.
     */
    public function actionCalculate($id)
    {
        $input = (new \yii\db\Query())
            ->from('oc.operational_cost_inputs')
            ->where(['id' => (int)$id])
            ->one();

        if (!$input) {
            throw new NotFoundHttpException('Operational input not found.');
        }

        $this->runCalculations((int)$id, $input['start_date'], (int)$input['flock_size']);

        Yii::$app->session->setFlash('success', 'Egg production & operational costs recalculated for 100 weeks.');
        return $this->redirect(['result', 'scenario_id' => $id]);
    }

    /**
     * Results page with KPIs + lifecycle totals.
     */
    public function actionResult($scenario_id)
{
    $scenario_id = (int)$scenario_id;
    $db = Yii::$app->db;

    // Scenario input (with overrides)
    $input = (new \yii\db\Query())
        ->from('oc.operational_cost_inputs')
        ->where(['id' => $scenario_id])
        ->one($db);

    // Summary
    $summary = (new \yii\db\Query())
        ->from('oc.scenario_egg_summary')
        ->where(['scenario_id' => $scenario_id])
        ->one($db);

    if (!$summary) {
        throw new NotFoundHttpException('Summary not found. Please run calculation first.');
    }

    // Weekly data
    $weekly = (new \yii\db\Query())
        ->from('oc.scenario_egg_production')
        ->where(['scenario_id' => $scenario_id])
        ->orderBy('week_no')
        ->all($db);

    // Baselines
    $baseline = (new \yii\db\Query())
        ->select(['cost_type', 'base_value', 'monthly_increment_pct'])
        ->from('oc.oc_baselines')
        ->indexBy('cost_type')
        ->all($db);
    $baseline = array_change_key_case($baseline, CASE_LOWER);

    // Totals (raw operational costs table)
    $totalCosts = (new \yii\db\Query())
        ->select([
            'eggs_total'           => 'SUM(eggs_total)',
            'eggs_sellable'        => 'SUM(eggs_sellable)',
            'labor_cost_lkr'       => 'SUM(labor_cost_lkr)',
            'medicine_cost_lkr'    => 'SUM(medicine_cost_lkr)',
            'transport_cost_lkr'   => 'SUM(transport_cost_lkr)',
            'electricity_cost_lkr' => 'SUM(electricity_cost_lkr)',
            'total_cost_lkr'       => 'SUM(total_cost_lkr)'
        ])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenario_id])
        ->one();

    // FEED breakdown
    $feedByType = (new \yii\db\Query())
        ->select([
            'feed_type',
            'kg'         => 'ROUND(SUM(feed_kg), 3)',
            'wavg_price' => "ROUND(CASE WHEN SUM(feed_kg)>0 THEN SUM(cost_feed_lkr)/SUM(feed_kg) ELSE 0 END, 2)",
            'cost'       => 'ROUND(SUM(cost_feed_lkr), 2)',
            'wmin'       => 'MIN(week_no)',
            'wmax'       => 'MAX(week_no)',
        ])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenario_id])
        ->andWhere('feed_kg IS NOT NULL')
        ->groupBy('feed_type')
        ->orderBy(new \yii\db\Expression('MIN(week_no)'))
        ->all();

    // FEED totals
    $feedTotals = (new \yii\db\Query())
        ->select([
            'kg'   => 'ROUND(SUM(feed_kg), 3)',
            'cost' => 'ROUND(SUM(cost_feed_lkr), 2)',
        ])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenario_id])
        ->one();

    // DOC
    $docCost = (new \yii\db\Query())
        ->select(['ds','cost_doc_lkr' => 'ROUND(cost_doc_lkr,2)'])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenario_id, 'week_no' => 1])
        ->one();

    if ($docCost && !empty($docCost['ds'])) {
        $docUnit = (new \yii\db\Query())
            ->select(['unit_price_lkr' => 'value'])
            ->from('oc.price_forecasts')
            ->where([
                'series_name' => 'doc_price',
                'ds'          => $docCost['ds'],
            ])
            ->scalar();
        $docCost['doc_price'] = $docUnit !== false ? (float)$docUnit : null;
    }

    // Weekly feed chart data
    $weeklyFeed = (new \yii\db\Query())
        ->select(['week_no','feed_kg','cost_feed_lkr'])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenario_id])
        ->orderBy('week_no')
        ->all();

    // Grand summary from DB view
    $grand = (new \yii\db\Query())
        ->from('oc.vw_scenario_cost_summary')
        ->where(['scenario_id' => $scenario_id])
        ->one();

    // === Force synced variables for all tables ===
    $fixedCostTotal = (float)($grand['fixed_total_lkr'] ?? $totalCosts['total_cost_lkr'] ?? 0);
    $feedDocTotal   = (float)($grand['feed_doc_total_lkr'] ?? 0);
    $grandTotal     = (float)($grand['grand_total_lkr'] ?? ($fixedCostTotal + $feedDocTotal));

    return $this->render('result', [
        'summary'         => $summary,
        'weeks'           => array_column($weekly, 'week_no'),
        'laid'            => array_map('intval', array_column($weekly, 'eggs_laid')),
        'sellable'        => array_map('intval', array_column($weekly, 'eggs_sellable')),
        'baseline'        => $baseline,
        'totalCosts'      => $totalCosts,
        'input'           => $input,
        'feedByType'      => $feedByType,
        'feedTotals'      => $feedTotals,
        'docCost'         => $docCost,
        'weeklyFeed'      => $weeklyFeed,
        'grand'           => $grand,
        'fixedCostTotal'  => $fixedCostTotal, // synced
        'feedDocTotal'    => $feedDocTotal,   // synced
        'grandTotal'      => $grandTotal,     // synced
    ]);
}


    /**
     * Run SQL functions to populate scenario tables.
     */
    private function runCalculations(int $scenarioId, string $startDate, int $flockSize): void
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $db->createCommand("
                SELECT oc.populate_scenario_eggs(:sid, :start_date, :birds)
            ")->bindValues([
                ':sid'        => $scenarioId,
                ':start_date' => $startDate,
                ':birds'      => $flockSize,
            ])->queryScalar();

            $db->createCommand("
                SELECT oc.populate_scenario_operational_costs(:sid)
            ")->bindValues([
                ':sid' => $scenarioId,
            ])->queryScalar();

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            throw $e;
        }
    }
}
