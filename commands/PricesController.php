<?php
namespace app\commands;

use Yii;
use yii\console\Controller;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PricesController extends Controller
{
    // php yii prices/import @app/data/Research_Data.xlsx
    public function actionImport(string $file): int
    {
        $path = Yii::getAlias($file);
        if (!is_file($path)) { $this->stderr("File not found: $path\n"); return 1; }

        $wb = IOFactory::load($path);
        $db = Yii::$app->db;

        $upsert = function(string $series, string $ds, float $value, string $unit) use ($db) {
            $db->createCommand()->upsert(
                'oc.price_history_raw',
                ['series_name'=>$series,'ds'=>$ds,'value'=>$value,'unit'=>$unit,'source'=>'xlsx:Research_Data.xlsx'],
                ['value'=>$value,'unit'=>$unit,'source'=>'xlsx:Research_Data.xlsx']
            )->execute();
        };

        $monthToNum = function($m) {
            if ($m === null || $m === '') return null;
            if (is_numeric($m)) return (int)$m;
            $s = strtolower(trim((string)$m));
            $map = [
                'jan'=>1,'january'=>1,'feb'=>2,'february'=>2,'mar'=>3,'march'=>3,'apr'=>4,'april'=>4,
                'may'=>5,'jun'=>6,'june'=>6,'jul'=>7,'july'=>7,'aug'=>8,'august'=>8,
                'sep'=>9,'sept'=>9,'september'=>9,'oct'=>10,'october'=>10,'nov'=>11,'november'=>11,
                'dec'=>12,'december'=>12
            ];
            return $map[$s] ?? null;
        };

        $parseNumberOrRange = function($v) {
            if ($v === null || $v === '') return null;
            if (is_numeric($v)) return (float)$v;
            $s = str_replace(',', '', trim((string)$v));
            if (strpos($s, '-') !== false) {
                $parts = array_filter(array_map('trim', explode('-', $s)), fn($p)=>$p!=='');
                $nums = array_map('floatval', $parts);
                if ($nums) return array_sum($nums)/count($nums);
            }
            $s = preg_replace('/[^0-9.]/', '', $s);
            return $s === '' ? null : (float)$s;
        };

        $sheetNames = $wb->getSheetNames();
        $norm = fn($n) => strtolower(trim($n));

        /* =========================
         * A) Egg/DOC/Feed (monthly)
         * ========================= */
        $priceSheetName = null;
        foreach ($sheetNames as $n) {
            if (in_array($norm($n), ['egg, doc, feed price', 'egg, doc, feed price'])) {
                $priceSheetName = $n; break;
            }
        }
        if ($priceSheetName === null) {
            // fallback: find the one that contains the three words
            foreach ($sheetNames as $n) {
                $ln = $norm($n);
                if (str_contains($ln,'egg') && str_contains($ln,'doc') && str_contains($ln,'feed')) {
                    $priceSheetName = $n; break;
                }
            }
        }

        $total = 0;
        if ($priceSheetName !== null) {
            $s = $wb->getSheetByName($priceSheetName);
            $rows = $s->toArray(null, true, true, true);
            if ($rows && isset($rows[1])) {
                // Header map
                $hdr = array_map(fn($v)=>trim((string)$v), $rows[1]);
                $idx = array_flip($hdr);

                $colYear  = $idx['Year']  ?? null;
                $colMonth = $idx['Month'] ?? null;
                if ($colYear === null || $colMonth === null) {
                    $this->stderr("Sheet '$priceSheetName' missing Year/Month; aborting that sheet.\n");
                } else {
                    $map = [
                        'egg_price_brown' => ['Brown Egg Price', 'LKR/egg'],
                        'egg_price_white' => ['White Egg Price', 'LKR/egg'],
                        'egg_price_small' => ['Farm egg small price', 'LKR/egg'],
                        'doc_price'       => [['DOC price Avg','DOC price Range'], 'LKR/bird'], // prefer Avg else Range
                        'feed_starter'    => ['Feed Starter Price/kg', 'LKR/kg'],
                        'feed_grower'     => ['Feed Grower Price /kg', 'LKR/kg'],
                        'feed_layer'      => ['Feed Layer Price/kg', 'LKR/kg'],
                    ];

                    $lastYear = null;
                    for ($r = 2; $r <= count($rows); $r++) {
                        $yr = $rows[$r][$colYear] ?? null;
                        $mo = $rows[$r][$colMonth] ?? null;
                        if ($yr === null || $yr === '') $yr = $lastYear; else $lastYear = (int)$yr;
                        $mon = $monthToNum($mo);
                        if (!$yr || !$mon) continue;

                        $ds = sprintf('%04d-%02d-01', (int)$yr, (int)$mon);

                        foreach ($map as $series => [$colNames, $unit]) {
                            $val = null;
                            if (is_array($colNames)) {
                                // DOC: try Avg, then Range
                                foreach ($colNames as $cn) {
                                    $col = $idx[$cn] ?? null;
                                    if ($col !== null) {
                                        $val = $parseNumberOrRange($rows[$r][$col] ?? null);
                                        if ($val !== null) break;
                                    }
                                }
                            } else {
                                $col = $idx[$colNames] ?? null;
                                if ($col !== null) $val = $parseNumberOrRange($rows[$r][$col] ?? null);
                            }
                            if ($val === null) continue;

                            $upsert($series, $ds, $val, $unit);
                            $total++;
                        }
                    }
                }
            }
        } else {
            $this->stderr("Egg/DOC/Feed sheet not found — skipped.\n");
        }

        /* =========================
         * B) Cull (layer live daily)
         * ========================= */
        $cullSheetName = null;
        foreach ($sheetNames as $n) {
            if ($norm($n) === 'layer live price') { $cullSheetName = $n; break; }
        }
        if ($cullSheetName !== null) {
            $s = $wb->getSheetByName($cullSheetName);
            $rows = $s->toArray(null, true, true, true);
            if ($rows && isset($rows[1])) {
                $hdr = array_map(fn($v)=>trim((string)$v), $rows[1]);
                $ix  = array_flip($hdr);
                $cDate = $ix['Date'] ?? null;
                $cAvg  = $ix['Avg Price '] ?? ($ix['Avg Price'] ?? null);
                $cFrom = $ix['From (Rs)'] ?? null;
                $cTo   = $ix['To(Rs)'] ?? null;

                for ($r = 2; $r <= count($rows); $r++) {
                    $dsRaw = $rows[$r][$cDate] ?? null;
                    if (!$dsRaw) continue;
                    $ds = date('Y-m-d', strtotime((string)$dsRaw));

                    $val = null;
                    if ($cAvg !== null) $val = $parseNumberOrRange($rows[$r][$cAvg] ?? null);
                    if ($val === null && $cFrom !== null && $cTo !== null) {
                        $f = $parseNumberOrRange($rows[$r][$cFrom] ?? null);
                        $t = $parseNumberOrRange($rows[$r][$cTo]   ?? null);
                        if ($f !== null && $t !== null) $val = ($f + $t) / 2.0;
                    }
                    if ($val === null) continue;

                    $upsert('cull_price', $ds, $val, 'LKR/bird');
                    $total++;
                }
            }
        } else {
            $this->stderr("Cull sheet not found — skipped.\n");
        }

        $this->stdout("Imported/updated rows: $total\n");
        return 0;
    }
}
