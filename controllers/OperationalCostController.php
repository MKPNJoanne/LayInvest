<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use app\models\OperationalCostInput;

class OperationalCostController extends Controller
{
    /**Create form: saves an input row into oc.operational_cost_inputs*/

    public function actionIndex()
    {
        $latestId = (new \yii\db\Query())
            ->from('oc.operational_cost_inputs')
            ->select('id')
            ->where(['is_active' => true])
            ->orderBy(['id' => SORT_DESC])
            ->scalar();

        if ($latestId) {
            // send to result page (which redirects to calculate)
            return $this->redirect(['result', 'scenario_id' => (int)$latestId]);
        }
        // no scenarios yet -> start by creating one
        return $this->redirect(['create']);
    }
    public function actionCreate()
    {
        $model = new OperationalCostInput();

        // Defaults from baselines
        $defaults = (new \yii\db\Query())
            ->select(['cost_type', 'base_value', 'monthly_increment_pct'])
            ->from('oc.oc_baselines')
            ->indexBy('cost_type')
            ->all();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            if ($model->flock_size < 500 || $model->flock_size > 5000) {
                $model->addError('flock_size', 'Flock size must be between 500 and 5000.');
            } else {
                // Fill blank overrides with defaults
                $model->cost_labor_override       = $this->useDefaultIfEmpty($model->cost_labor_override,       $defaults, 'labor');
                $model->cost_electricity_override = $this->useDefaultIfEmpty($model->cost_electricity_override, $defaults, 'electricity');
                $model->cost_medicine_override    = $this->useDefaultIfEmpty($model->cost_medicine_override,    $defaults, 'medicine');
                $model->cost_transport_override   = $this->useDefaultIfEmpty($model->cost_transport_override,   $defaults, 'transport');

                // Insert (let Postgres auto-generate id)
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

                // Call population functions so feed + DOC costs are stored
                Yii::$app->db->createCommand("
                    SELECT oc.populate_scenario_feed_costs(:sid, :start_date, :flock);
                ")->bindValues([
                    ':sid'        => $id,
                    ':start_date' => $model->start_date,
                    ':flock'      => (int)$model->flock_size,
                ])->execute();

                Yii::$app->db->createCommand("
                    SELECT oc.populate_scenario_doc_cost(:sid, :start_date, :flock);
                ")->bindValues([
                    ':sid'        => $id,
                    ':start_date' => $model->start_date,
                    ':flock'      => (int)$model->flock_size,
                ])->execute();

                // Populate full scenario (includes feed, labor, medicine, etc.)
                Yii::$app->db->createCommand("
                    SELECT oc.populate_full_scenario(:sid);
                ")->bindValues([
                    ':sid' => $id,
                ])->execute();

                // Optional: fetch break-even weeks directly
                // $breakEvenWeeks = Yii::$app->db->createCommand("
                //     SELECT * FROM oc.get_break_even_weeks(:sid);
                // ")->bindValues([
                //     ':sid' => $id,
                // ])->queryAll();

                // Run your existing calculation wrapper (if needed for totals)
                $this->runCalculations($id, $model->start_date, (int)$model->flock_size);

                // Redirect to view page
                return $this->redirect(['view', 'id' => $id]);
            }
        }

        return $this->render('create', [
            'model'    => $model,
            'defaults' => $defaults
        ]);
    }

    public function actionBack()
    {
        $latestId = (new \yii\db\Query())
            ->from('oc.operational_cost_inputs')
            ->select('id')
            ->where(['is_active' => true])   // only active flock
            ->orderBy(['id' => SORT_DESC])
            ->scalar();

        if ($latestId) {
            return $this->redirect(['view', 'id' => $latestId]);
        }
        return $this->redirect(['create']);
    }


    private function useDefaultIfEmpty($value, $defaults, $key)
    {
        return (empty($value) && isset($defaults[$key]))
            ? $defaults[$key]['base_value']
            : $value;
    }

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

        // More resilient "has results" — either summary or SOC rows exist
        $hasResults = (new \yii\db\Query())
            ->from('oc.scenario_egg_summary')
            ->where(['scenario_id' => $id])
            ->exists()
            ||
            (new \yii\db\Query())
            ->from('oc.scenario_operational_costs')
            ->where(['scenario_id' => $id])
            ->exists();

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



   public function actionCalculate($id)
{
    $db = Yii::$app->db;

    // 1) Load input
    $input = (new \yii\db\Query())
        ->from('oc.operational_cost_inputs')
        ->where(['id' => (int)$id])
        ->one();

    if (!$input) {
        throw new NotFoundHttpException("Scenario $id not found");
    }

    $scenarioId = (int)$id;
    $startDate  = $input['start_date'];
    $flockSize  = (int)$input['flock_size'];

    // 2) Clean slate
    $db->createCommand("DELETE FROM oc.scenario_operational_costs WHERE scenario_id = :sid")
        ->bindValue(':sid', $scenarioId)
        ->execute();
    $db->createCommand("DELETE FROM oc.scenario_egg_production WHERE scenario_id = :sid")
        ->bindValue(':sid', $scenarioId)
        ->execute();

    // 3) ALWAYS run in the proven order
    $tx = $db->beginTransaction();
    try {
        // 3.1 seed 100 weeks
        $db->createCommand("SELECT oc.ensure_soc_weeks(:sid)")
            ->bindValue(':sid', $scenarioId)
            ->execute();

        // 3.2 eggs first
        $db->createCommand("SELECT oc.populate_scenario_eggs(:sid, :sdate, :flock)")
            ->bindValues([':sid' => $scenarioId, ':sdate' => $startDate, ':flock' => $flockSize])
            ->execute();

        // 3.3 feed
        $db->createCommand("SELECT oc.populate_scenario_feed_costs(:sid, :sdate, :flock)")
            ->bindValues([':sid' => $scenarioId, ':sdate' => $startDate, ':flock' => $flockSize])
            ->execute();

        // 3.4 DOC (week 1)
        $db->createCommand("SELECT oc.populate_scenario_doc_cost(:sid, :sdate, :flock)")
            ->bindValues([':sid' => $scenarioId, ':sdate' => $startDate, ':flock' => $flockSize])
            ->execute();

        // 3.5 fixed + totals
        $db->createCommand("SELECT oc.populate_scenario_operational_costs(:sid)")
            ->bindValue(':sid', $scenarioId)
            ->execute();

        $tx->commit();
    } catch (\Throwable $e) {
        $tx->rollBack();
        Yii::error($e->getMessage(), __METHOD__);
        throw $e;
    }

    // 4) Build everything the result view needs (unchanged)
    $baseline = (new \yii\db\Query())
        ->select(['cost_type', 'base_value', 'monthly_increment_pct'])
        ->from('oc.oc_baselines')
        ->indexBy('cost_type')
        ->all();
    $baseline = array_change_key_case($baseline, CASE_LOWER);

    $summary = (new \yii\db\Query())
        ->from('oc.scenario_egg_summary')
        ->where(['scenario_id' => $scenarioId])
        ->one();

    $weekly = (new \yii\db\Query())
        ->from('oc.scenario_egg_production')
        ->where(['scenario_id' => $scenarioId])
        ->orderBy('week_no')
        ->all();

    $totalCosts = (new \yii\db\Query())
            ->select([
                'eggs_total'            => 'COALESCE(SUM(eggs_total),0)',
                'eggs_sellable'         => 'COALESCE(SUM(eggs_sellable),0)',
                // fixed buckets
                'labor_cost_lkr'        => 'COALESCE(SUM(labor_cost_lkr),0)',
                'medicine_cost_lkr'     => 'COALESCE(SUM(medicine_cost_lkr),0)',
                'transport_cost_lkr'    => 'COALESCE(SUM(transport_cost_lkr),0)',
                'electricity_cost_lkr'  => 'COALESCE(SUM(electricity_cost_lkr),0)',
                // variable + one-time
                'cost_feed_lkr'         => 'COALESCE(SUM(cost_feed_lkr),0)',
                'cost_doc_lkr'          => 'COALESCE(SUM(cost_doc_lkr),0)',
                // optional total from table (not used for buckets)
                'total_cost_lkr'        => 'COALESCE(SUM(total_cost_lkr),0)',
            ])
            ->from('oc.scenario_operational_costs')
            ->where(['scenario_id' => $scenarioId])
            ->one();

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
        ->where(['scenario_id' => $scenarioId])
        ->andWhere('feed_kg IS NOT NULL')
        ->groupBy('feed_type')
        ->orderBy(new \yii\db\Expression('MIN(week_no)'))
        ->all();

        $feedTotals = (new \yii\db\Query())
        ->select([
            'kg'   => 'COALESCE(ROUND(SUM(feed_kg), 3), 0)',
            'cost' => 'COALESCE(ROUND(SUM(cost_feed_lkr), 2), 0)',
        ])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenarioId])
        ->one();

    // DOC forecast cost for week 1
            $docForecast = Yii::$app->db->createCommand("
            SELECT pf.ds, pf.value AS doc_price
            FROM oc.price_forecasts pf
            JOIN oc.operational_cost_inputs oci
            ON pf.ds <= oci.start_date
            WHERE pf.series_name = 'doc_price'
            AND oci.id = :scenario_id
            ORDER BY pf.ds DESC
            LIMIT 1
        ")
        ->bindValue(':scenario_id', $id)   
        ->queryOne();

        $docCost = Yii::$app->db->createCommand("
            SELECT week_no, ds, cost_doc_lkr
            FROM oc.scenario_operational_costs
            WHERE scenario_id = :sid AND week_no = 1
        ")
        ->bindValue(':sid', $scenarioId)
        ->queryOne();

    if ($docCost && !empty($docCost['ds'])) {
        $docUnit = (new \yii\db\Query())
            ->select(['unit_price_lkr' => 'value'])
            ->from('oc.price_forecasts')
            ->where(['series_name' => 'doc_price', 'ds' => $docCost['ds']])
            ->scalar();
        $docCost['doc_price'] = $docUnit !== false ? (float)$docUnit : null;
    }

    $weeklyFeed = (new \yii\db\Query())
        ->select(['week_no','feed_kg','cost_feed_lkr'])
        ->from('oc.scenario_operational_costs')
        ->where(['scenario_id' => $scenarioId])
        ->orderBy('week_no')
        ->all();

    $grand = (new \yii\db\Query())
        ->from('oc.vw_scenario_cost_summary')
        ->where(['scenario_id' => $scenarioId])
        ->one();

    $fixedCostTotal = (float)$totalCosts['labor_cost_lkr']
                + (float)$totalCosts['medicine_cost_lkr']
                + (float)$totalCosts['transport_cost_lkr']
                + (float)$totalCosts['electricity_cost_lkr'];
    $feedDocTotal   = (float)$totalCosts['cost_feed_lkr']
                + (float)$totalCosts['cost_doc_lkr'];
    $grandTotal     = $fixedCostTotal + $feedDocTotal;

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
        'fixedCostTotal'  => $fixedCostTotal,
        'feedDocTotal'    => $feedDocTotal,
        'grandTotal'      => $grandTotal,
        'docForecast'     => $docForecast,
    ]);
}

    public function actionStartFresh()
    {
        Yii::$app->db->createCommand("
            UPDATE oc.operational_cost_inputs 
            SET is_active = false
        ")->execute();

        return $this->redirect(['create']);
    }


    public function actionResult($scenario_id)
    {
        return $this->redirect(['calculate', 'id' => (int)$scenario_id]);
    }
    /**
     * Wrapper to run all calculations in the correct order within a transaction.
     * This ensures data integrity and that all dependent data is populated correctly.
     */
    private function runCalculations(int $scenarioId, string $startDate, int $flockSize): void
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();

        try {
            // 1) seed weeks
            $db->createCommand("SELECT oc.ensure_soc_weeks(:sid)")
                ->bindValue(':sid', $scenarioId)
                ->execute();

            // 2) eggs
            $db->createCommand("SELECT oc.populate_scenario_eggs(:sid, :start_date, :birds)")
                ->bindValues([
                    ':sid'        => $scenarioId,
                    ':start_date' => $startDate,
                    ':birds'      => $flockSize,
                ])
                ->execute();

            // 3) feed
            $db->createCommand("SELECT oc.populate_scenario_feed_costs(:sid, :start_date, :birds)")
                ->bindValues([
                    ':sid'        => $scenarioId,
                    ':start_date' => $startDate,
                    ':birds'      => $flockSize,
                ])
                ->execute();

            // 4) DOC (week 1)
            $db->createCommand("SELECT oc.populate_scenario_doc_cost(:sid, :start_date, :birds)")
                ->bindValues([
                    ':sid'        => $scenarioId,
                    ':start_date' => $startDate,
                    ':birds'      => $flockSize,
                ])
                ->execute();

            // 5) totals
            $db->createCommand("SELECT oc.populate_scenario_operational_costs(:sid)")
                ->bindValue(':sid', $scenarioId)
                ->execute();
            // 6) full scenario (feed + DOC + fixed + totals)
            $db->createCommand("SELECT oc.populate_full_scenario(:sid)")
                ->bindValue(':sid', $scenarioId)
                ->execute();

            // 7) optional: break-even weeks
            // $breakEvenWeeks = $db->createCommand("SELECT * FROM oc.get_break_even_weeks(:sid)")
            //     ->bindValue(':sid', $scenarioId)
            //     ->queryAll();

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            throw $e;
        }
    }




}
