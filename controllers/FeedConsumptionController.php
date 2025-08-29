<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\models\FeedConsumption;

class FeedConsumptionController extends Controller
{
    public function actionIndex(): string
    {
        // Fetch all feed consumption records ordered by week_no
        $feedData = FeedConsumption::find()
            ->orderBy(['week_no' => SORT_ASC])
            ->all();

        return $this->render('index', [
            'feedData' => $feedData
        ]);
    }
}
