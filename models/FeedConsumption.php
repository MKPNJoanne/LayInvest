<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "core.feed_consumption".
 *
 * @property int $id
 * @property int $week_no
 * @property string $feed_type
 * @property float $std_weight_g
 * @property float $feed_g
 * @property float|null $gain_g
 * @property float|null $laying_percentage
 * @property float|null $egg_weight_g
 * @property int|null $flock_id
 */
class FeedConsumption extends \yii\db\ActiveRecord
{


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'core.feed_consumption';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['gain_g', 'laying_percentage', 'egg_weight_g'], 'default', 'value' => null],
            [['flock_id'], 'default', 'value' => 1],
            [['week_no', 'feed_type', 'std_weight_g', 'feed_g'], 'required'],
            [['week_no', 'flock_id'], 'default', 'value' => null],
            [['week_no', 'flock_id'], 'integer'],
            [['std_weight_g', 'feed_g', 'gain_g', 'laying_percentage', 'egg_weight_g'], 'number'],
            [['feed_type'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'week_no' => 'Week No',
            'feed_type' => 'Feed Type',
            'std_weight_g' => 'Std Weight G',
            'feed_g' => 'Feed G',
            'gain_g' => 'Gain G',
            'laying_percentage' => 'Laying Percentage',
            'egg_weight_g' => 'Egg Weight G',
            'flock_id' => 'Flock ID',
        ];
    }

}
