<?php
namespace app\services;

use Yii;
use yii\db\Query;
use app\models\FeedConsumption;
use app\models\ProductionData;
use app\config\DashboardConfig;

//converts raw farm data into weekly KPIs and charts like livability, 
//laying %, eggs, feed use, and FCR for the dashboard.
class DashboardService
{
    /** Clamp flock size to supported range. */
    private function clampFlockSize(int $size): int
    {
        return max(500, min(5000, $size));
    }

    /** Single guide row for a week (feed table). */
    private function getGuideRow(int $week): ?array
    {
        return (new Query())
            ->from(FeedConsumption::tableName())
            ->where(['week_no' => $week])
            ->select(['week_no','feed_g','gain_g','livability','laying_percentage','fcr'])
            ->one();
    }

    /** Convert fraction→% and clamp to [0..100]. */
    private function toPct(?float $v): ?float
    {
        if ($v === null) return null;
        $x = ($v <= 1.0001) ? $v * 100.0 : $v;
        return max(0.0, min(100.0, $x));
    }

    /** Livability (%) for a week, clamped. */
    private function getLivabilityPct(int $week): ?float
    {
        $row = (new Query())
            ->from(FeedConsumption::tableName())
            ->where(['week_no' => $week])
            ->select(['livability'])
            ->one();

        $val = isset($row['livability']) ? (float)$row['livability'] : null;
        return $this->toPct($val);
    }

    /** Rollup of production table for a week. */
    private function getProductionRollup(int $week): array
    {
        $table  = ProductionData::tableName();
        $schema = Yii::$app->db->schema->getTableSchema($table, true);

        $select = [
            'total_deaths' => 'COALESCE(SUM(f_died_count),0)',
            'total_eggs'   => 'COALESCE(SUM(white_eggs + brown_eggs),0)',
            'days'         => 'COUNT(*)',
            'avg_birds'    => 'COALESCE(AVG(female_count),0)',
        ];

        if ($schema && isset($schema->columns['egg_weight'])) {
            $select['avg_egg_weight'] = 'NULLIF(AVG(egg_weight),0)';
        }

        return (new Query())
            ->from($table)
            ->where(['week_no' => $week])
            ->select($select)
            ->one() ?? [];
    }

    /** Scale eggs using birds derived for that week. */
    private function getEggsForWeek(int $weekNo, int $birdsForWeek): int
    {
        $prod = (new Query())
            ->from(ProductionData::tableName())
            ->where(['week_no' => $weekNo])
            ->select([
                'eggs'  => 'COALESCE(SUM(white_eggs + brown_eggs),0)',
                'birds' => 'COALESCE(AVG(female_count),0)',
                'days'  => 'COUNT(*)'
            ])->one();

        $eggs  = (float)$prod['eggs'];
        $birds = (float)$prod['birds'];
        $days  = max(1, (int)$prod['days']);

        if ($eggs > 0 && $birds > 0) {
            $perBirdPerDay = $eggs / $birds / $days;
            return (int) round($perBirdPerDay * $birdsForWeek * 7.0);
        }
        return 0;
    }

    /**  Birds Livability (%)  */
    private function getBirdsTrajectory(int $initialFlock, int $toWeek = 100): array
    {
        $initialFlock = $this->clampFlockSize($initialFlock);
        $toWeek       = max(1, min(100, $toWeek));

        $birds = [];
        for ($w = 1; $w <= $toWeek; $w++) {
            $Lw    = $this->getLivabilityPct($w);       // 0..100 or null
            $ratio = ($Lw !== null) ? ($Lw / 100.0) : 1.0;
            $birds[$w] = (int) round($initialFlock * $ratio);
        }
        return $birds; // keys 1..$toWeek
    }

    private function getBirdsAtWeek(int $initialFlock, int $week): int
    {
        $initialFlock = $this->clampFlockSize($initialFlock);
        $week  = max(1, min(100, $week));
        $Lw    = $this->getLivabilityPct($week);
        $ratio = ($Lw !== null) ? ($Lw / 100.0) : 1.0;
        return (int) round($initialFlock * $ratio);
    }

    /** Estimate broken eggs using derived birds + production ratios. */
    public function estimateBrokenEggs(int $weekNo, int $initialFlock): array
    {
        $birdsForWeek = $this->getBirdsAtWeek($initialFlock, $weekNo);

        $prod = (new Query())
            ->from(ProductionData::tableName())
            ->where(['week_no' => $weekNo])
            ->select([
                'broken' => 'COALESCE(SUM(egg_broken),0)',
                'eggs'   => 'COALESCE(SUM(white_eggs + brown_eggs),0)',
                'birds'  => 'COALESCE(AVG(female_count),0)',
                'days'   => 'COUNT(*)'
            ])
            ->one();

        $broken = (float)$prod['broken'];
        $eggs   = (float)$prod['eggs'];
        $birds  = (float)$prod['birds'];
        $days   = max(1, (int)$prod['days']);

        if ($broken > 0 && $birds > 0) {
            $perBirdPerDay = $broken / $birds / $days;
            $scaledBroken  = (int) round($perBirdPerDay * $birdsForWeek * 7.0);
        } else {
            $row = (new Query())
                ->from(ProductionData::tableName())
                ->select([
                    'totalBroken' => 'COALESCE(SUM(egg_broken),0)',
                    'totalEggs'   => 'COALESCE(SUM(white_eggs + brown_eggs),0)'
                ])
                ->one();

            $totalBroken  = (float)$row['totalBroken'];
            $totalEggs    = (float)$row['totalEggs'];
            $brokenRate   = $totalEggs > 0 ? ($totalBroken / $totalEggs) : 0.01;
            $scaledEggsWk = $this->getEggsForWeek($weekNo, $birdsForWeek);
            $scaledBroken = (int) round($brokenRate * $scaledEggsWk);
        }

        $scaledEggs = $this->getEggsForWeek($weekNo, $birdsForWeek);
        $brokenPct  = ($scaledEggs > 0) ? round(($scaledBroken / $scaledEggs) * 100, 2) : 0;

        return [
            'broken_amount'     => $scaledBroken,
            'broken_percentage' => $brokenPct
        ];
    }

    /** Feed g/bird/day from guide row (with min/max sanity). */
    private function resolveFeedGPerBirdDay(?array $guide, int $week): ?float
    {
        if (!empty($guide['feed_g'])) {
            $g = (float)$guide['feed_g'];
            if ($g >= DashboardConfig::FEED_G_PER_BIRD_MIN && $g <= DashboardConfig::FEED_G_PER_BIRD_MAX) {
                return $g;
            }
        }

        $fallback = (new Query())
            ->from(ProductionData::tableName())
            ->where(['week_no' => $week])
            ->average('female_feed');

        return $fallback ? (float)$fallback : null;
    }

    /** FCR from core.feed_consumption (no computation). */
    private function getFcrForWeek(int $week): ?float
    {
        $row = (new Query())
            ->from(FeedConsumption::tableName())
            ->where(['week_no' => $week])
            ->select(['fcr'])
            ->one();

        if (!$row) return null;
        $v = (float)$row['fcr'];
        return $v > 0 ? round($v, 2) : null;
    }

    /** KPI block (uses livability-derived birds; FCR from DB). */
    public function getKpiData(int $initialFlock, int $week): array
    {
        $initialFlock = $this->clampFlockSize($initialFlock);

        $guide = $this->getGuideRow($week) ?? [];
        $prod  = $this->getProductionRollup($week);

        $layPct = isset($guide['laying_percentage'])
            ? (float)$guide['laying_percentage']
            : ((!empty($prod['avg_birds']) && $prod['avg_birds'] > 0 && !empty($prod['total_eggs']))
                ? (((float)$prod['total_eggs'] / ((float)$prod['avg_birds'] * 7.0)) * 100.0)
                : 0.0);

        $feedG = $this->resolveFeedGPerBirdDay($guide, $week);

        // Livability and birds for this week
        $livabilityPct = $this->getLivabilityPct($week) ?? 100.0;
        $birdsThisWeek = $this->getBirdsAtWeek($initialFlock, $week);

        // Eggs scaled by derived birds for this week
        $eggsScaled = 0;
        if (!empty($prod['avg_birds']) && $prod['avg_birds'] > 0 && !empty($prod['total_eggs'])) {
            $daysRecorded      = max(1, (int)($prod['days'] ?? 0));
            $eggsPerBirdPerDay = ((float)$prod['total_eggs'] / (float)$prod['avg_birds']) / $daysRecorded;
            $eggsScaled        = (int) round($eggsPerBirdPerDay * $birdsThisWeek * 7.0);
        } elseif ($layPct > 0) {
            $eggsScaled = (int) round(($layPct / 100.0) * $birdsThisWeek * 7.0);
        }

        // FCR: strict DB value
        $fcr = $this->getFcrForWeek($week);

        return [
            'metrics' => [
                'initial_flock'   => $initialFlock,
                'birds_this_week' => $birdsThisWeek,
                'feed_per_bird'   => $feedG !== null ? round($feedG, 1) : null,
                'livability'      => round($livabilityPct, 2),
                'laying_rate'     => round($layPct, 1),
                'fcr'             => $fcr, // may be null if 0/empty in DB
                'eggs_total'      => $eggsScaled,
                'broken_eggs'     => $this->estimateBrokenEggs($week, $initialFlock),
            ]
        ];
    }

    /** Eggs series scaled */
    public function getEggSeries(int $initialFlock, int $startWeek, int $endWeek): array
    {
        $initialFlock = $this->clampFlockSize($initialFlock);
        $startWeek = max(1, $startWeek);
        $endWeek   = min(100, $endWeek);

        $birdsTrail = $this->getBirdsTrajectory($initialFlock, $endWeek);

        $out = [];
        for ($w = $startWeek; $w <= $endWeek; $w++) {
            $birdsForWeek = $birdsTrail[$w] ?? $initialFlock;

            $prod = $this->getProductionRollup($w);
            if (!empty($prod['avg_birds']) && $prod['avg_birds'] > 0 && !empty($prod['total_eggs'])) {
                $daysRecorded      = max(1, (int)($prod['days'] ?? 0));
                $eggsPerBirdPerDay = ((float)$prod['total_eggs'] / (float)$prod['avg_birds']) / $daysRecorded;
                $eggs              = (int) round($eggsPerBirdPerDay * $birdsForWeek * 7.0);
            } else {
                $g   = $this->getGuideRow($w);
                $lay = $g ? (float)$g['laying_percentage'] : 0.0;
                $eggs = (int) round(($lay / 100.0) * $birdsForWeek * 7.0);
            }

            $out[] = ['week_no' => $w, 'total' => $eggs];
        }
        return $out;
    }

    /** Livability line series (1..100). */
    public function getLivabilitySeries(int $startWeek, int $endWeek): array
    {
        $startWeek = max(1, $startWeek);
        $endWeek   = min(100, $endWeek);

        $rows = (new Query())
            ->from(FeedConsumption::tableName())
            ->where(['between', 'week_no', $startWeek, $endWeek])
            ->select(['week_no','livability'])
            ->orderBy('week_no')
            ->indexBy('week_no')
            ->all();

        $out = [];
        for ($w = $startWeek; $w <= $endWeek; $w++) {
            $val = null;
            if (isset($rows[$w])) {
                $val = $this->toPct((float)$rows[$w]['livability']);
            }
            $out[] = ['week_no' => $w, 'livability' => $val !== null ? round($val, 2) : null];
        }
        return $out;
    }

    /** Feed kg/week and g/bird/day series. */
    public function getFeedSeries(int $initialFlock, int $startWeek, int $endWeek): array
    {
        $initialFlock = $this->clampFlockSize($initialFlock);
        $startWeek = max(1, $startWeek);
        $endWeek   = min(100, $endWeek);

        $birdsTrail = $this->getBirdsTrajectory($initialFlock, $endWeek);

        $rows = (new Query())
            ->from(FeedConsumption::tableName())
            ->where(['between', 'week_no', $startWeek, $endWeek])
            ->select(['week_no','feed_g'])
            ->orderBy('week_no')
            ->all();

        $out = [];
        foreach ($rows as $r) {
            $w = (int)$r['week_no'];
            $g = (float)$r['feed_g'];
            $birdsForWeek = $birdsTrail[$w] ?? $initialFlock;

            $kgWeek = ($g * $birdsForWeek * 7.0) / 1000.0;
            $out[] = [
                'week_no'        => $w,
                'feed_kg'        => round($kgWeek, 2),
                'g_per_bird_day' => round($g, 1)
            ];
        }
        return $out;
    }
}
