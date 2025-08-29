<?php
namespace app\services;

use Yii;

class PricingService
{
    /**
     * Weekly feed prices (strict; 100 rows)
     * Columns: week_no, ds, feed_type, value, unit
     */
    public function getFeedWeekly(string $startDate): array
    {
        $sql = "SELECT * FROM oc.get_feed_price_weekly(:ds)";
        return Yii::$app->db->createCommand($sql, [':ds' => $startDate])->queryAll();
    }

    /**
     * Weekly cull prices (strict; 100 rows)
     * Columns: week_no, ds, value, unit
     */
    public function getCullWeekly(string $startDate): array
    {
        $sql = "SELECT * FROM oc.get_cull_price_weekly(:ds)";
        return Yii::$app->db->createCommand($sql, [':ds' => $startDate])->queryAll();
    }

    /**
     * Weekly per-egg baselines with monthly compounding from base_date
     * Columns: week_no, ds, cost_type, value, unit
     */
    public function getBaselinesPerEggWeekly(string $startDate): array
    {
        $sql = "SELECT * FROM oc.get_baseline_per_egg_weekly(:ds)";
        return Yii::$app->db->createCommand($sql, [':ds' => $startDate])->queryAll();
    }
}
