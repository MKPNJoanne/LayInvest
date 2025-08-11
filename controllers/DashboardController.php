<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use app\services\DashboardService;
use app\config\DashboardConfig;

class DashboardController extends Controller
{
    private $dashboardService;

    public function __construct($id, $module, $config = [])
    {
        $this->dashboardService = new DashboardService();
        parent::__construct($id, $module, $config);
    }

    public function actionIndex()
    {
        $week = (int) Yii::$app->request->get('week', 20);
        $flockSize = (int) Yii::$app->request->get('flock_size', 2000);

        $windowStart = max($week - (DashboardConfig::CHART_WINDOW_WEEKS - 1), 1);

        // Core KPI data
        $kpiData = $this->dashboardService->getKpiData($flockSize, $week);

        // Broken egg data (only call once from service)
        $brokenData = $kpiData['metrics']['broken_eggs'];

        // Chart series
        $eggSeries = $this->dashboardService->getEggSeries($flockSize, $windowStart, $week);
        $mortalitySeries = $this->dashboardService->getMortalitySeries($flockSize, $windowStart, $week);
        $feedSeries = $this->dashboardService->getFeedSeries($flockSize, $windowStart, $week);

        return $this->render('index', [
            'week'               => $week,
            'weeksList'          => range(1, 100),
            'flockSize'          => $flockSize,
            'data'               => $kpiData,
            'eggSeries'          => $eggSeries,
            'mortalitySeries'    => $mortalitySeries,
            'feedSeries'         => $feedSeries,
            'windowStart'        => $windowStart,
            'broken_eggs_amount' => $brokenData['broken_amount'],
            'broken_eggs_pct'    => $brokenData['broken_percentage'],
        ]);
    }
}
