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
                return $this->redirect(['result', 'scenario_id' => $id]);
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

        // also fetch the scenario input (contains user overrides)
        $input = (new \yii\db\Query())
            ->from('oc.operational_cost_inputs')
            ->where(['id' => $scenario_id])
            ->one($db);


        $summary = (new \yii\db\Query())
            ->from('oc.scenario_egg_summary')
            ->where(['scenario_id' => $scenario_id])
            ->one($db);

        if (!$summary) {
            throw new NotFoundHttpException('Summary not found. Please run calculation first.');
        }

        $weekly = (new \yii\db\Query())
            ->from('oc.scenario_egg_production')
            ->where(['scenario_id' => $scenario_id])
            ->orderBy('week_no')
            ->all($db);

        $baseline = (new \yii\db\Query())
            ->select(['cost_type', 'base_value', 'monthly_increment_pct'])
            ->from('oc.oc_baselines')
            ->indexBy('cost_type')
            ->all($db);
        $baseline = array_change_key_case($baseline, CASE_LOWER);

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

        $week100 = (new \yii\db\Query())
            ->from('oc.scenario_operational_costs')
            ->where(['scenario_id' => $scenario_id, 'week_no' => 100])
            ->one($db) ?: [];

        $weeks    = array_column($weekly, 'week_no');
        $laid     = array_map('intval', array_column($weekly, 'eggs_laid'));
        $sellable = array_map('intval', array_column($weekly, 'eggs_sellable'));

        return $this->render('result', [
            'summary'     => $summary,
            'weeks'       => $weeks,
            'laid'        => $laid,
            'sellable'    => $sellable,
            'baseline'    => $baseline,
            'week100'     => $week100,
            'totalCosts'  => $totalCosts,
            'input'       => $input,
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
