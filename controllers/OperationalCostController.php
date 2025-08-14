<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\data\ArrayDataProvider;
use app\models\OperationalCostInput;

class OperationalCostController extends Controller
{
    /**
     * Create form: saves an input row into oc.operational_cost_inputs
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

            // Flock size validation
            if ($model->flock_size < 500 || $model->flock_size > 5000) {
                $model->addError('flock_size', 'Flock size must be between 500 and 5000.');
            } else {
                // Fill defaults for any empty inputs
                $model->cost_labor_override       = $this->useDefaultIfEmpty($model->cost_labor_override,       $defaults, 'labor');
                $model->cost_electricity_override = $this->useDefaultIfEmpty($model->cost_electricity_override, $defaults, 'electricity');
                $model->cost_medicine_override    = $this->useDefaultIfEmpty($model->cost_medicine_override,    $defaults, 'medicine');
                $model->cost_transport_override   = $this->useDefaultIfEmpty($model->cost_transport_override,   $defaults, 'transport');

                // Save to DB
                Yii::$app->db->createCommand()->insert('oc.operational_cost_inputs', [
                    'start_date'                => $model->start_date,
                    'flock_size'                => $model->flock_size,
                    'cost_labor_override'       => $model->cost_labor_override,
                    'cost_electricity_override' => $model->cost_electricity_override,
                    'cost_medicine_override'    => $model->cost_medicine_override,
                    'cost_transport_override'   => $model->cost_transport_override,
                    'created_at'                => date('Y-m-d H:i:s'),
                ])->execute();

                // Redirect to view
                $id = Yii::$app->db->getLastInsertID();
                Yii::$app->session->setFlash('success', 'Your inputs were saved successfully.');
                return $this->redirect(['view', 'id' => $id]);
            }
        }

        return $this->render('create', [
            'model'    => $model,
            'defaults' => $defaults
        ]);
    }

    /**
     * Helper to replace empty values with defaults
     */
    private function useDefaultIfEmpty($value, $defaults, $key)
    {
        return (empty($value) && isset($defaults[$key]))
            ? $defaults[$key]['base_value']
            : $value;
    }

    /**
     * Show a single input row
     */
    public function actionView($id)
    {
        $model = (new \yii\db\Query())
            ->from('oc.operational_cost_inputs')
            ->where(['id' => (int)$id])
            ->one();

        if (!$model) {
            throw new NotFoundHttpException('Record not found.');
        }

        // Fetch latest baseline record
        $baseline = (new \yii\db\Query())
            ->select(['cost_type', 'base_value', 'monthly_increment_pct'])
            ->from('oc.oc_baselines')
            ->indexBy('cost_type')
            ->all();

        return $this->render('view', [
            'model'    => $model,
            'baseline' => $baseline
        ]);
    }

    /**
     * Run egg-production calc for 100 weeks and redirect to results.
     * We use the input row's ID as scenario_id.
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

        // Call SQL function to populate scenario egg production
        Yii::$app->db->createCommand("
            SELECT oc.populate_scenario_eggs(:sid, :start_date, :birds)
        ")->bindValues([
            ':sid'        => (int)$id,
            ':start_date' => $input['start_date'],
            ':birds'      => (int)$input['flock_size'],
        ])->queryScalar();

        Yii::$app->session->setFlash('success', 'Egg production calculated for 100 weeks.');
        return $this->redirect(['result', 'scenario_id' => $id]);
    }

    /**
     * Results page with KPI cards + weekly table + chart
     */
    public function actionResult($scenario_id)
    {
        $scenario_id = (int)$scenario_id;
        $db = Yii::$app->db;

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

        $provider = new ArrayDataProvider([
            'allModels'  => $weekly,
            'pagination' => ['pageSize' => 25],
            'sort'       => [
                'attributes'   => ['week_no', 'eggs_laid', 'eggs_sellable', 'lay_pct'],
                'defaultOrder' => ['week_no' => SORT_ASC],
            ],
        ]);

        // Arrays for chart
        $weeks    = array_column($weekly, 'week_no');
        $laid     = array_map('intval', array_column($weekly, 'eggs_laid'));
        $sellable = array_map('intval', array_column($weekly, 'eggs_sellable'));

        return $this->render('result', [
            'summary'   => $summary,
            'provider'  => $provider,
            'weeks'     => $weeks,
            'laid'      => $laid,
            'sellable'  => $sellable,
        ]);
    }
}
