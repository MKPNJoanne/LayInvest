<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "core.production_data".
 *
 * @property int $id
 * @property string $ddate
 * @property int $week_no
 * @property int $female_count
 * @property int $f_died_count
 * @property int $f_cul_count
 * @property float $female_feed
 * @property int $egg_small
 * @property int $egg_broken
 * @property int $white_eggs
 * @property int $brown_eggs
 * @property float $egg_weight
 * @property int|null $flock_id
 */
class ProductionData extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'core.production_data';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['flock_id'], 'default', 'value' => 1],
            [['ddate', 'week_no', 'female_count', 'f_died_count', 'f_cul_count', 'female_feed', 'egg_small', 'egg_broken', 'white_eggs', 'brown_eggs', 'egg_weight'], 'required'],
            [['ddate'], 'safe'],
            [['week_no', 'female_count', 'f_died_count', 'f_cul_count', 'egg_small', 'egg_broken', 'white_eggs', 'brown_eggs', 'flock_id'], 'default', 'value' => null],
            [['week_no', 'female_count', 'f_died_count', 'f_cul_count', 'egg_small', 'egg_broken', 'white_eggs', 'brown_eggs', 'flock_id'], 'integer'],
            [['female_feed', 'egg_weight'], 'number'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'ddate' => 'Ddate',
            'week_no' => 'Week No',
            'female_count' => 'Female Count',
            'f_died_count' => 'F Died Count',
            'f_cul_count' => 'F Cul Count',
            'female_feed' => 'Female Feed',
            'egg_small' => 'Egg Small',
            'egg_broken' => 'Egg Broken',
            'white_eggs' => 'White Eggs',
            'brown_eggs' => 'Brown Eggs',
            'egg_weight' => 'Egg Weight',
            'flock_id' => 'Flock ID',
        ];
    }

}
