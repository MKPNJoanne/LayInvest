<?php
namespace app\services;

use yii\db\Query;
use app\config\DashboardConfig;
use app\models\ProductionData;

//calculates weekly production figures from farm data. It sums white and brown eggs, finds the average egg weight, 
//and calculates mortality percentage for a given week, while following the laying schedule policy.
class ProductionAnalysisService {
    public function getWeeklyProduction(int $week, bool $ignorePolicy = false): array {
        // Enforce policy window unless override
        if (!$ignorePolicy) {
            if ($week < DashboardConfig::LAY_START_WEEK || $week > DashboardConfig::LAY_END_WEEK) {
                return [
                    'eggs_white' => 0,
                    'eggs_brown' => 0,
                    'avg_weight' => 0,
                    'mortality_pct' => 0
                ];
            }
        }

        $query = (new Query())
            ->from(ProductionData::tableName())
            ->where(['week_no' => $week])
            ->select([
                'eggs_white' => new \yii\db\Expression('SUM(white_eggs)'),
                'eggs_brown' => new \yii\db\Expression('SUM(brown_eggs)'),
                'avg_weight' => new \yii\db\Expression('AVG(egg_weight)'),
                'mortality_pct' => new \yii\db\Expression('(SUM(f_died_count) / MAX(female_count) * 100)')
            ]);

        return $query->one() ?: [
            'eggs_white' => 0,
            'eggs_brown' => 0,
            'avg_weight' => 0,
            'mortality_pct' => 0
        ];
    }
}
