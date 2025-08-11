<?php
namespace app\services;

use Yii;
use yii\db\Query;
use app\models\FeedConsumption;
use app\models\ProductionData;
use app\config\DashboardConfig;

class DashboardService
{
    private function clampFlockSize(int $size): int
    {
        return max(500, min(5000, $size));
    }

    private function getGuideRow(int $week): ?array
    {
        return (new Query())
            ->from(FeedConsumption::tableName())
            ->where(['week_no' => $week])
            ->select(['week_no','feed_g','gain_g','livability','laying_percentage','fcr'])
            ->one();
    }

    private function toPct(?float $v): ?float
    {
        if ($v === null) return null;
        return ($v <= 1.0001) ? $v * 100.0 : $v;
    }

    private function getLivabilityPct(int $week): ?float
    {
        $row = (new Query())
            ->from(FeedConsumption::tableName())
            ->where(['week_no' => $week])
            ->select(['livability'])
            ->one();

        return $this->toPct(isset($row['livability']) ? (float)$row['livability'] : null);
    }

    private function getWeeklyMortalityFromProductionPct(int $week): ?float
    {
        $row = (new Query())
            ->from(ProductionData::tableName())
            ->where(['week_no' => $week])
            ->select([
                'deaths'    => 'COALESCE(SUM(f_died_count),0)',
                'avg_birds' => 'NULLIF(AVG(female_count),0)'
            ])
            ->one();

        if (empty($row) || empty($row['avg_birds'])) return null;
        return max(0.0, ((float)$row['deaths'] / (float)$row['avg_birds']) * 100.0);
    }

    private function getWeeklyMortalityPct(int $week): float
    {
        $Lpre = $this->getLivabilityPct($week - 1);
        $Lw   = $this->getLivabilityPct($week);

        if ($Lpre !== null && $Lw !== null) {
            return round(max(0.0, $Lpre - $Lw), 3);
        }

        $prodPct = $this->getWeeklyMortalityFromProductionPct($week);
        return $prodPct !== null ? round($prodPct, 3) : 0.0;
    }

    private function getProductionRollup(int $week): array
    {
        $table = ProductionData::tableName();
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

    private function getEggsForWeek(int $weekNo, int $birdsForWeek): int
    {
        // Scale eggs using actual birds for that week (derived), not initial flock
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
            return (int)round($perBirdPerDay * $birdsForWeek * 7.0);
        }
        return 0;
    }

    /** ---------- NEW: Birds trajectory (derived from mortality) ---------- */

    private function getBirdsTrajectory(int $initialFlock, int $toWeek = 100): array
    {
        $initialFlock = $this->clampFlockSize($initialFlock);
        $toWeek = max(1, min(100, $toWeek));

        $birds = [];
        $prev  = (float)$initialFlock;

        for ($w = 1; $w <= $toWeek; $w++) {
            $mortPct = $this->getWeeklyMortalityPct($w);
            $deaths  = (int)round($prev * ($mortPct / 100.0));
            $now     = max(0.0, $prev - $deaths);
            $birds[$w] = (int)round($now);
            $prev = $now;
        }
        return $birds; // 1..$toWeek
    }

    private function getBirdsAtWeek(int $initialFlock, int $week): int
    {
        $week  = max(1, min(100, $week));
        $trail = $this->getBirdsTrajectory($initialFlock, $week);
        return $trail[$week] ?? $initialFlock;
    }

    /** PUBLIC: Estimate broken eggs (now scaled by derived birds) */
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
            $scaledBroken  = (int)round($perBirdPerDay * $birdsForWeek * 7.0);
        } else {
            $row = (new Query())
                ->from(ProductionData::tableName())
                ->select([
                    'totalBroken' => 'COALESCE(SUM(egg_broken),0)',
                    'totalEggs'   => 'COALESCE(SUM(white_eggs + brown_eggs),0)'
                ])
                ->one();

            $totalBroken = (float)$row['totalBroken'];
            $totalEggs   = (float)$row['totalEggs'];

            $brokenRate   = $totalEggs > 0 ? ($totalBroken / $totalEggs) : 0.01;
            $scaledEggsWk = $this->getEggsForWeek($weekNo, $birdsForWeek);
            $scaledBroken = (int)round($brokenRate * $scaledEggsWk);
        }

        $scaledEggs = $this->getEggsForWeek($weekNo, $birdsForWeek);
        $brokenPct  = ($scaledEggs > 0) ? round(($scaledBroken / $scaledEggs) * 100, 2) : 0;

        return [
            'broken_amount'     => $scaledBroken,
            'broken_percentage' => $brokenPct
        ];
    }

    private function resolveEggWeightG(array $prod, int $week): float
    {
        if (!empty($prod['avg_egg_weight'])) {
            return (float)$prod['avg_egg_weight'];
        }
        return 60.0;
    }

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

    private function computeFcrEggMass(float $feedGPerBirdDay, float $layPct, float $eggWeightG): ?float
    {
        if ($layPct <= 0.0 || $eggWeightG <= 0.0 || $feedGPerBirdDay <= 0.0) return null;
        return $feedGPerBirdDay / (($layPct / 100.0) * $eggWeightG);
    }

    /** ---------- KPI uses derived birds for the selected week ---------- */
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

        // Derived birds for this week + previous week (for deaths calc)
        $birdsThisWeek = $this->getBirdsAtWeek($initialFlock, $week);
        $birdsPrevWeek = ($week > 1) ? $this->getBirdsAtWeek($initialFlock, $week - 1) : $initialFlock;

        $mortPctWeek = $this->getWeeklyMortalityPct($week);
        $deathsWeek  = (int) round($birdsPrevWeek * ($mortPctWeek / 100.0));

        // Eggs scaled by derived birds for this week
        $eggsScaled = 0;
        if (!empty($prod['avg_birds']) && $prod['avg_birds'] > 0 && !empty($prod['total_eggs'])) {
            $daysRecorded      = max(1, (int)($prod['days'] ?? 0));
            $eggsPerBirdPerDay = ((float)$prod['total_eggs'] / (float)$prod['avg_birds']) / $daysRecorded;
            $eggsScaled        = (int) round($eggsPerBirdPerDay * $birdsThisWeek * 7.0);
        } elseif ($layPct > 0) {
            $eggsScaled = (int) round(($layPct / 100.0) * $birdsThisWeek * 7.0);
        }

        $eggWeightG = $this->resolveEggWeightG($prod, $week);
        $fcr = null;
        if ($week >= DashboardConfig::LAY_START_WEEK && $feedG !== null && $layPct > 0) {
            $fcr = $this->computeFcrEggMass($feedG, $layPct, $eggWeightG);
        }

        return [
            'metrics' => [
                'initial_flock' => $initialFlock,
                'birds_this_week' => $birdsThisWeek,
                'feed_per_bird' => $feedG !== null ? round($feedG, 1) : null,
                'mortality'     => [
                    'percent' => round($mortPctWeek, 2),
                    'deaths'  => $deathsWeek
                ],
                'laying_rate'   => round($layPct, 1),
                'fcr'           => $fcr !== null ? round($fcr, 2) : null,
                'eggs_total'    => $eggsScaled,
                'broken_eggs'   => $this->estimateBrokenEggs($week, $initialFlock),
            ]
        ];
    }

    /** ---------- Series now scaled by derived birds per week ---------- */
    public function getEggSeries(int $initialFlock, int $startWeek, int $endWeek): array
    {
        $initialFlock = $this->clampFlockSize($initialFlock);
        $startWeek = max(1, $startWeek);
        $endWeek   = min(100, $endWeek);

        // Precompute birds trajectory up to endWeek once
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

    public function getMortalitySeries(int $initialFlock, int $startWeek, int $endWeek): array
    {
        $out = [];
        for ($w = $startWeek; $w <= $endWeek; $w++) {
            $out[] = [
                'week_no'   => $w,
                'mortality' => $this->getWeeklyMortalityPct($w)
            ];
        }
        return $out;
    }

    public function getFeedSeries(int $initialFlock, int $startWeek, int $endWeek): array
    {
        $initialFlock = $this->clampFlockSize($initialFlock);
        $startWeek = max(1, $startWeek);
        $endWeek   = min(100, $endWeek);

        // Birds per week for scaling kg
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
