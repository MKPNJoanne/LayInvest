<?php

namespace app\models;

use yii\base\Model;

class Revenue extends Model
{
    public $week_no;
    public $ds;
    public $egg_revenue;
    public $weekly_cull_revenue;
    public $total_weekly_revenue;
    public $cumulative_egg_revenue;
    public $cumulative_cull_revenue;
    public $cumulative_total_revenue;
    public $cum_profit_eggs;
    public $cum_margin_eggs;
    public $cum_profit_with_cull;
    public $cum_margin_with_cull;

    public function rules()
    {
        return [
            [['week_no'], 'integer'],
            [['ds'], 'safe'],
            [[
                'egg_revenue',
                'weekly_cull_revenue',
                'total_weekly_revenue',
                'cumulative_egg_revenue',
                'cumulative_cull_revenue',
                'cumulative_total_revenue',
                'cum_profit_eggs',
                'cum_margin_eggs',
                'cum_profit_with_cull',
                'cum_margin_with_cull'
            ], 'number'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'week_no' => 'Week',
            'ds' => 'Date',
            'egg_revenue' => 'Egg Revenue (LKR)',
            'weekly_cull_revenue' => 'Cull Revenue (LKR)',
            'total_weekly_revenue' => 'Total Weekly Revenue (LKR)',
            'cumulative_egg_revenue' => 'Cumulative Egg Revenue (LKR)',
            'cumulative_cull_revenue' => 'Cumulative Cull Revenue (LKR)',
            'cumulative_total_revenue' => 'Cumulative Total Revenue (LKR)',
            'cum_profit_eggs' => 'Cumulative Profit (Eggs)',
            'cum_margin_eggs' => 'Margin % (Eggs)',
            'cum_profit_with_cull' => 'Cumulative Profit (Eggs + Cull)',
            'cum_margin_with_cull' => 'Margin % (Eggs + Cull)',
        ];
    }
}
