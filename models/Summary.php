<?php
namespace app\models;

use Yii;

class Summary
{
    /* ---------- Shared helpers ---------- */

    public static function getActiveScenarioId(): ?int
    {
        $id = (int)Yii::$app->db->createCommand("
            SELECT id
            FROM oc.operational_cost_inputs
            ORDER BY id DESC
            LIMIT 1
        ")->queryScalar();
        if ($id) return $id;

        $id = (int)Yii::$app->db->createCommand("
            SELECT DISTINCT scenario_id
            FROM oc.scenario_egg_production
            ORDER BY scenario_id DESC
            LIMIT 1
        ")->queryScalar();

        return $id ?: null;
    }

    public static function getStartDateAndFlock(int $scenarioId): array
    {
        $row = Yii::$app->db->createCommand("
            SELECT start_date::date AS start_date, flock_size
            FROM oc.operational_cost_inputs
            WHERE id = :id
        ")->bindValue(':id', $scenarioId)->queryOne();

        if ($row && $row['start_date']) {
            return ['start_date'=>$row['start_date'],'flock_size'=>(int)$row['flock_size']];
        }

        $start = Yii::$app->db->createCommand("
            SELECT MIN(ds)::date
            FROM oc.scenario_egg_production
            WHERE scenario_id = :id
        ")->bindValue(':id', $scenarioId)->queryScalar();

        $flock = Yii::$app->db->createCommand("
            SELECT live_birds
            FROM oc.scenario_egg_production
            WHERE scenario_id = :id
            ORDER BY week_no ASC, ds ASC
            LIMIT 1
        ")->bindValue(':id', $scenarioId)->queryScalar();

        return ['start_date'=>$start,'flock_size'=>(int)$flock];
    }

    /* ---------- 1) Egg Production ---------- */
    public static function eggProduction(int $scenarioId): array
    {
        $sql = "
          SELECT
            week_no,
            ds::date,
            live_birds,
            COALESCE(lay_pct,0)::numeric(6,2)      AS lay_pct,
            COALESCE(eggs_laid,0)                  AS eggs_laid,
            COALESCE(egg_loss_pct,0)::numeric(6,2) AS egg_loss_pct,
            COALESCE(eggs_sellable,0)              AS eggs_sellable,
            COALESCE(small_egg_pct,0)::numeric(6,2) AS small_egg_pct,
            COALESCE(small_eggs,0)                 AS small_eggs,
            GREATEST(COALESCE(eggs_sellable,0)-COALESCE(small_eggs,0),0) AS white_eggs
          FROM oc.scenario_egg_production
          WHERE scenario_id = :id
          ORDER BY week_no
        ";
        return Yii::$app->db->createCommand($sql)->bindValue(':id',$scenarioId)->queryAll();
    }

    /* ---------- 2) Egg Revenue ---------- */
    public static function eggRevenue(int $scenarioId): array
    {
        $sql = "
          SELECT r.*,
                 SUM(r.total_weekly_revenue) OVER(ORDER BY r.week_no) AS cumulative_revenue
          FROM oc.calc_egg_revenue_weekly(:sid) r
          ORDER BY r.week_no
        ";
        return Yii::$app->db->createCommand($sql)->bindValue(':sid',$scenarioId)->queryAll();
    }

    /* ---------- 3) Feed Consumption (one row per week) ---------- */
    public static function feedWeekly(int $scenarioId): array
    {
        // Use what you've persisted: one row per week with correct feed_kg & cost.
        $sql = "
          SELECT
            soc.week_no,
            soc.ds::date AS ds,
            COALESCE(soc.feed_type, fc.feed_type)             AS feed_type,
            fc.feed_g::numeric(8,3)                           AS grams_per_bird_day,
            soc.feed_kg::numeric(12,3)                        AS feed_kg,
            soc.cost_feed_lkr::numeric(14,2)                  AS cost_feed_lkr
          FROM oc.scenario_operational_costs soc
          LEFT JOIN core.feed_consumption fc ON fc.week_no = soc.week_no
          WHERE soc.scenario_id = :sid
          ORDER BY soc.week_no
        ";
        return Yii::$app->db->createCommand($sql)->bindValue(':sid',$scenarioId)->queryAll();
    }
    /* ---------- 4) Operational Costs (prefer DB table; fallback to forecast aggregation) ---------- */
    public static function opsWeeklyCosts(int $scenarioId): array
    {
        // Try the canonical table first
        $rows = Yii::$app->db->createCommand("
          SELECT
            ROW_NUMBER() OVER (ORDER BY ds)::int AS week_no,
            ds::date AS ds,
            COALESCE(cost_doc_lkr, cost_doc_lkr, 0)::numeric(14,2)   AS cost_doc_lkr,
            COALESCE(cost_feed_lkr, cost_feed_lkr, 0)::numeric(14,2) AS cost_feed_lkr,
            COALESCE(labor_cost_lkr,0)::numeric(14,2)                AS labor_cost_lkr,
            COALESCE(medicine_cost_lkr,0)::numeric(14,2)             AS medicine_cost_lkr,
            COALESCE(electricity_cost_lkr,0)::numeric(14,2)          AS electricity_cost_lkr,
            COALESCE(transport_cost_lkr,0)::numeric(14,2)            AS transport_cost_lkr,
            COALESCE(total_cost_lkr,0)::numeric(14,2)                AS total_cost_lkr
          FROM oc.scenario_operational_costs
          WHERE scenario_id = :sid
          ORDER BY ds
        ")->bindValue(':sid',$scenarioId)->queryAll();

        if ($rows && count($rows) > 0) {
            return $rows;
        }

        // Fallback: aggregate the forecast function
        $meta = self::getStartDateAndFlock($scenarioId);
        $sql = "
          SELECT
            f.week_no,
            f.ds::date AS ds,
            SUM(f.cost_doc)         AS cost_doc_lkr,
            SUM(f.cost_feed)        AS cost_feed_lkr,
            SUM(f.cost_labor)       AS labor_cost_lkr,
            SUM(f.cost_medicine)    AS medicine_cost_lkr,
            SUM(f.cost_electricity) AS electricity_cost_lkr,
            SUM(f.cost_transport)   AS transport_cost_lkr,
            SUM(f.total_cost)       AS total_cost_lkr
          FROM oc.calc_operational_weekly_forecast(CAST(:start_date AS date), :flock, :sid, 'full_cycle') f
          GROUP BY f.week_no, f.ds::date
          ORDER BY f.week_no
        ";
        return Yii::$app->db->createCommand($sql)
            ->bindValues([
                ':start_date'=>$meta['start_date'],
                ':flock'=>$meta['flock_size'],
                ':sid'=>$scenarioId
            ])->queryAll();
    }

    /* ---------- 5) Cull Revenue ---------- */
    public static function cullRevenue(int $scenarioId): array
    {
        $sql = "SELECT * FROM oc.calc_cull_revenue(:sid)";
        return Yii::$app->db->createCommand($sql)->bindValue(':sid',$scenarioId)->queryAll();
    }

    /* ---------- 6) Weekly Prices (DB-side v2) ---------- */
    public static function forecastPrices(int $scenarioId): array
    {
        $meta = self::getStartDateAndFlock($scenarioId);
        $sql  = "SELECT * FROM oc.get_full_price_forecast_v2(CAST(:start_date AS date))";
        return Yii::$app->db->createCommand($sql)->bindValue(':start_date',$meta['start_date'])->queryAll();
    }

    /* ---------- Cost distribution ---------- */
    public static function costDistribution(int $scenarioId): array
    {
        try {
            $view = Yii::$app->db->createCommand("
              SELECT
                COALESCE(cost_feed_lkr,0)        AS feed,
                COALESCE(cost_doc_lkr,0)         AS doc,
                COALESCE(cost_labor_lkr,0)       AS labor,
                COALESCE(cost_medicine_lkr,0)    AS medicine,
                COALESCE(cost_electricity_lkr,0) AS electricity,
                COALESCE(cost_transport_lkr,0)   AS transport
              FROM oc.vw_scenario_cost_summary
              WHERE scenario_id = :id
              LIMIT 1
            ")->bindValue(':id',$scenarioId)->queryOne();

            if ($view) {
                $sum = array_sum(array_map('floatval',$view)) ?: 1;
                foreach ($view as $k=>$v) {
                    $view[$k] = ['amount'=>(float)$v,'pct'=>round(((float)$v*100)/$sum,2)];
                }
                return $view;
            }
        } catch (\Throwable $e) { /* fallback below */ }

        $meta = self::getStartDateAndFlock($scenarioId);
        $tot = Yii::$app->db->createCommand("
          SELECT
            SUM(cost_feed)        AS feed,
            SUM(cost_doc)         AS doc,
            SUM(cost_labor)       AS labor,
            SUM(cost_medicine)    AS medicine,
            SUM(cost_electricity) AS electricity,
            SUM(cost_transport)   AS transport
          FROM oc.calc_operational_weekly_forecast(CAST(:start_date AS date), :flock, :sid, 'full_cycle')
        ")->bindValues([
            ':start_date'=>$meta['start_date'],
            ':flock'=>$meta['flock_size'],
            ':sid'=>$scenarioId
        ])->queryOne();

        $sum = array_sum(array_map('floatval',$tot ?: [])) ?: 1;
        foreach ($tot as $k=>$v) {
            $tot[$k] = ['amount'=>(float)$v,'pct'=>round(((float)$v*100)/$sum,2)];
        }
        return $tot;
    }
}
