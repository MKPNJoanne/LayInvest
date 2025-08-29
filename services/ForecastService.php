<?php
namespace app\services;

use Yii;
// ForecastService is responsible for managing forecasts
// It ensures that forecasts for a given series are available
//written to avoid recomputing forecasts

final class ForecastService
{
    public function ensureForecast(string $series, string $startDate, string $unit, int $weeks = 100): array
    {
        // 1) read if exists
        $rows = (new \yii\db\Query())
            ->from('oc.price_forecasts')
            ->where(['series_name'=>$series])
            ->orderBy('week_no')->all();

        if (count($rows) >= $weeks) return $rows;

        // 2) build history from your raw table (strict)
        $hist = (new \yii\db\Query())
            ->select(['ds','y'=>'value'])
            ->from('oc.price_history_raw')
            ->where(['series_name'=>$series])
            ->orderBy('ds')->all();

        // 3) run prophet
        $runner = new ForecastRunner();
        $res = $runner->run($series, $startDate, $hist, $unit, $weeks);

        // 4) save
        $batch = [];
        foreach ($res['points'] as $p) {
            $batch[] = [
                'series_name'=>$series, 'week_no'=>$p['week_no'], 'ds'=>$p['ds'],
                'value'=>$p['value'], 'unit'=>$unit, 'model_version'=>$res['model_version']
            ];
        }
        Yii::$app->db->createCommand()
            ->batchInsert('oc.price_forecasts',
                ['series_name','week_no','ds','value','unit','model_version'], $batch
            )->execute();

        return (new \yii\db\Query())
            ->from('oc.price_forecasts')
            ->where(['series_name'=>$series])
            ->orderBy('week_no')->all();
    }
}
