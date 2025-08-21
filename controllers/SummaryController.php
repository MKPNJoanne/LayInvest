<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\ArrayDataProvider;
use app\models\Summary;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use kartik\mpdf\Pdf;
use yii\web\Response;

class SummaryController extends Controller
{
    protected function resolveScenarioId($id): ?int
    {
        if (!empty($id)) return (int)$id;
        return Summary::getActiveScenarioId();
    }

    public function actionIndex($id = null)
    {
        $id = $this->resolveScenarioId($id);
        if (!$id) {
            return $this->render('index', ['id'=>null, 'providers'=>[], 'dist'=>[]]);
        }

        $production = Summary::eggProduction($id);
        $revenue    = Summary::eggRevenue($id);

        // NEW: aggregated feed + costs
        $feedWeekly = Summary::feedWeekly($id);
        $costsAgg   = Summary::opsWeeklyCosts($id);

        $prices     = Summary::forecastPrices($id);
        $cull       = Summary::cullRevenue($id);
        // $dist       = Summary::costDistribution($id);

        $providers = [
            'production' => new ArrayDataProvider(['allModels'=>$production,'pagination'=>['pageSize'=>20]]),
            'revenue'    => new ArrayDataProvider(['allModels'=>$revenue,   'pagination'=>['pageSize'=>20]]),
            'feed'       => new ArrayDataProvider(['allModels'=>$feedWeekly,'pagination'=>['pageSize'=>15]]),
            'costs'      => new ArrayDataProvider(['allModels'=>$costsAgg,  'pagination'=>['pageSize'=>15]]),
            'prices'     => new ArrayDataProvider(['allModels'=>$prices,    'pagination'=>['pageSize'=>15]]),
            'cull'       => new ArrayDataProvider(['allModels'=>$cull,      'pagination'=>false]),
        ];

        return $this->render('index', compact('id','providers'));
    }

public function actionExportExcel($id = null)
{
    $id = $this->resolveScenarioId($id);
    if (!$id) {
        Yii::$app->session->setFlash('warning', 'No scenario to export.');
        return $this->redirect(['index']);
    }

    $prod   = Summary::eggProduction($id);
    $rev    = Summary::eggRevenue($id);
    $feed   = Summary::feedWeekly($id);
    $costs  = Summary::opsWeeklyCosts($id);
    $prices = Summary::forecastPrices($id);
    $cull   = Summary::cullRevenue($id);

    $wb = new Spreadsheet();
    $wb->setActiveSheetIndex(0)->setTitle('Production');
    if ($prod) {
        $wb->getActiveSheet()->fromArray(array_keys($prod[0]), null, 'A1');
        $wb->getActiveSheet()->fromArray(array_map('array_values',$prod), null, 'A2');
    }
    $add = function(string $title, array $rows) use ($wb) {
        $s = $wb->createSheet();
        $s->setTitle(substr($title,0,31));
        if (!$rows) return;
        $s->fromArray(array_keys($rows[0]), null, 'A1');
        $s->fromArray(array_map('array_values',$rows), null, 'A2');
    };
    $add('Revenue', $rev);
    $add('Feed',    $feed);
    $add('Costs',   $costs);
    $add('Prices',  $prices);
    $add('Cull',    $cull);

    // Save to temp file and send (robust for downloads)
    $tmp = tempnam(sys_get_temp_dir(), 'xls');
    (new Xlsx($wb))->save($tmp);

    $filename = "summary_s{$id}.xlsx";
    return Yii::$app->response
        ->sendFile($tmp, $filename, [
            'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'inline'   => false,
        ])
        ->on(Response::EVENT_AFTER_SEND, function() use ($tmp) {
            @unlink($tmp);
        });
}

// public function actionExportPdf($id = null)
// {
//     $id = $this->resolveScenarioId($id);
//     if (!$id) {
//         Yii::$app->session->setFlash('warning', 'No scenario to export.');
//         return $this->redirect(['index']);
//     }

//     $production = Summary::eggProduction($id);
//     $revenue    = Summary::eggRevenue($id);
//     $opsFeed    = Summary::feedWeekly($id);
//     $prices     = Summary::forecastPrices($id);
//     $cull       = Summary::cullRevenue($id);
//     $dist       = Summary::costDistribution($id);

//     $html = $this->renderPartial('pdf', compact('id','production','revenue','opsFeed','prices','cull','dist'));

//     $pdf = new Pdf([
//         'mode'        => Pdf::MODE_UTF8,
//         'format'      => Pdf::FORMAT_A4,
//         'orientation' => Pdf::ORIENT_PORTRAIT,
//         'destination' => Pdf::DEST_DOWNLOAD,   // force download
//         'filename'    => "summary_s{$id}.pdf",
//         'content'     => $html,
//         'cssInline'   => '
//           body{font-family:sans-serif}
//           h3,h4{margin:8px 0}
//           table{width:100%;border-collapse:collapse;margin-bottom:10px}
//           th,td{border:1px solid #ddd;padding:6px;font-size:11px}
//           .bar-wrap{background:#eee;height:12px;width:100%}
//           .bar{background:#1db954;height:12px}
//         ',
//     ]);
//     return $pdf->render();
// }
}
