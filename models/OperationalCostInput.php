<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

class OperationalCostInput extends ActiveRecord
{
    public static function tableName()
    {
        return 'oc.operational_cost_inputs';
    }

    public function rules()
    {
        return [
            [['start_date', 'flock_size'], 'required'],
            [['flock_size'], 'integer', 'min' => 500, 'max' => 5000],
            [
            ['cost_labor_override', 'cost_medicine_override', 'cost_electricity_override', 'cost_transport_override'],
            'number', 'min' => 0  // no negative values
            ],
            ['start_date', 'date', 'format' => 'php:Y-m-d'],
            ['start_date', 'validateNotPast'], 
        ];
    }

    public function validateNotPast($attribute, $params = [])
    {
        if (empty($this->$attribute)) return;

        $tz = new \DateTimeZone(Yii::$app->timeZone ?: 'Asia/Colombo');
        $picked = \DateTime::createFromFormat('Y-m-d', $this->$attribute, $tz);
        $today  = new \DateTime('today', $tz);

        if (!$picked || $picked < $today) {
            $this->addError($attribute, 'Date cannot be in the past.');
        }
    }

   public $space_sqft; // virtual, not in DB

    public function beforeValidate()
    {
        if (parent::beforeValidate()) {
            if ($this->flock_size) {
                $this->space_sqft = $this->flock_size * 2.5;
            }
            return true;
        }
        return false;
    }
    public function afterFind()
    {
        parent::afterFind();
        $this->space_sqft = $this->flock_size ? $this->flock_size * 2.5 : 0;
    }

    public function beforeSave($insert)
{
    if (parent::beforeSave($insert)) {

        // Convert empty string from form into null
        foreach (['cost_labor_override', 'cost_electricity_override', 'cost_medicine_override', 'cost_transport_override'] as $attr) {
            if ($this->$attr === '' || $this->$attr === null) {
                $this->$attr = null;
            }
        }

        $defaults = (new \yii\db\Query())
            ->select(['cost_type', 'base_value'])
            ->from('oc.oc_baselines')
            ->indexBy('cost_type')
            ->all();

        if ($this->cost_labor_override === null && isset($defaults['labor'])) {
            $this->cost_labor_override = $defaults['labor']['base_value'];
        }
        if ($this->cost_electricity_override === null && isset($defaults['electricity'])) {
            $this->cost_electricity_override = $defaults['electricity']['base_value'];
        }
        if ($this->cost_medicine_override === null && isset($defaults['medicine'])) {
            $this->cost_medicine_override = $defaults['medicine']['base_value'];
        }
        if ($this->cost_transport_override === null && isset($defaults['transport'])) {
            $this->cost_transport_override = $defaults['transport']['base_value'];
        }

        return true;
    }
    return false;
}




}
