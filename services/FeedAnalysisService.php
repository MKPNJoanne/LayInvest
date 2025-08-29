<?php
namespace app\services;

use Yii;
use yii\db\Query;
use app\config\DashboardConfig;
use app\models\FeedConsumption;

class FeedAnalysisService {
    private $schemaConfig = null;

    public function detectFeedSchema(): array {
        if ($this->schemaConfig !== null) {
            return $this->schemaConfig;
        }

        $schema = Yii::$app->db->schema;
        $tableName = FeedConsumption::tableName();
        $columns = $schema->getTableSchema($tableName)->columns;
        $columnNames = array_map('strtolower', array_keys($columns));

        $this->schemaConfig = [
            'has_per_bird' => in_array('grams_per_bird_day', $columnNames) && in_array('female_count_day', $columnNames),
            'has_issued_kg' => in_array('issue_kg', $columnNames) || in_array('total_kg', $columnNames),
            'kg_column' => in_array('issue_kg', $columnNames) ? 'issue_kg' : 'total_kg',
            'birds_column' => in_array('female_count_day', $columnNames) ? 'female_count_day' : 'birds',
        ];

        return $this->schemaConfig;
    }

    public function getWeeklyFeed(int $week, bool $ignorePolicy = false): array {
        $schema = $this->detectFeedSchema();
        $query = (new Query())->from(FeedConsumption::tableName());

        // Default to policy window unless override is requested
        if (!$ignorePolicy) {
            if ($week < DashboardConfig::LAY_START_WEEK || $week > DashboardConfig::LAY_END_WEEK) {
                return ['feed_g' => 0, 'hen_days' => 0];
            }
        }

        $query->where(['week_no' => $week]);

        if ($schema['has_per_bird']) {
            $query->select([
                'feed_g' => new \yii\db\Expression('SUM(grams_per_bird_day * female_count_day)'),
                'hen_days' => new \yii\db\Expression('SUM(female_count_day)')
            ]);
        } elseif ($schema['has_issued_kg']) {
            $kgCol = $schema['kg_column'];
            $query->select([
                'feed_g' => new \yii\db\Expression("SUM($kgCol) * 1000"),
                'hen_days' => new \yii\db\Expression('SUM(birds * 7)')
            ]);
        }

        return $query->one() ?: ['feed_g' => 0, 'hen_days' => 0];
    }
}
