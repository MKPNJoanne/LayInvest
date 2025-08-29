<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use app\services\PricingService;

class OpsController extends Controller
{
    /**
     * This action runs a smoke test for the forecast service, ensuring that
     * forecasts for feed, doc price, cull price, and egg price are generated
     * and available for the specified start date.
     */
public function actionForecastSmoke(?string $startDate = null): int
{
    $startDate = $startDate ?: date('Y-m-d');
    $svc = new \app\services\ForecastService();

    $feed = $svc->ensureForecast('feed_layer', $startDate, 'LKR/kg');   // or starter/grower
    $doc  = $svc->ensureForecast('doc_price',  $startDate, 'LKR/bird');
    $cull = $svc->ensureForecast('cull_price', $startDate, 'LKR/bird');
    $egg  = $svc->ensureForecast('egg_price_white', $startDate, 'LKR/egg');

    echo json_encode([
      'feed_rows'=>count($feed),'doc_rows'=>count($doc),
      'cull_rows'=>count($cull),'egg_rows'=>count($egg),
      'feed_sample'=>array_slice($feed,0,3)
    ], JSON_PRETTY_PRINT).PHP_EOL;
    return 0;
}
}
