<?php

namespace app\controllers;

use Yii;
use app\models\ProductionData;
use yii\web\Controller;

class ProductionDataController extends Controller
{
    public function actionIndex()
    {
        $data = ProductionData::find()
            ->orderBy(['week_no' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'data' => $data
        ]);
    }
}
