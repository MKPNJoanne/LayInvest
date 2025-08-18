<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ArrayDataProvider;
use app\models\Revenue;

class RevenueController extends Controller
{
    public function actionView()
    {
        $connection = Yii::$app->db;

        // Always take the latest scenario
        $scenarioId = $connection->createCommand("
            SELECT id 
            FROM oc.operational_cost_inputs 
            ORDER BY id DESC 
            LIMIT 1
        ")->queryScalar();

        if (!$scenarioId) {
            throw new \yii\web\NotFoundHttpException("No scenarios found.");
        }

        // Egg revenue (weeks 19–100 only)
        $eggRows = $connection->createCommand("
            SELECT *
            FROM oc.calc_egg_revenue_weekly(:scenario_id)
            WHERE week_no >= 19
            ORDER BY week_no
        ")->bindValue(':scenario_id', $scenarioId)->queryAll();

        $eggProvider = new ArrayDataProvider([
            'allModels' => $eggRows,
            'pagination' => ['pageSize' => 20],
        ]);

        // -------------------------------
        // Cull revenue (week 100 summary)
        // -------------------------------
        $cullRow = $connection->createCommand("
            SELECT *
            FROM oc.calc_cull_revenue(:scenario_id)
        ")->bindValue(':scenario_id', $scenarioId)->queryOne();

        return $this->render('view', [
            'eggProvider' => $eggProvider,
            'cullRow' => $cullRow,
            'scenarioId' => $scenarioId,
        ]);
    }
}
