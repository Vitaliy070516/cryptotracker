<?php
namespace app\models;

class WmExchangerSettingsModel extends \yii\db\ActiveRecord
{
    public static $signs = [
        '<'   => '<',
        '>'   => '>',
        '='  => '=',
        'all'  => 'all',
    ];

    public static $fields = [
        'amountin' => 'amountin',
        'amountout' => 'amountout',
        'inoutrate' => 'inoutrate',
        'outinrate' => 'outinrate',
        'procentbankrate' => 'procentbankrate',
        'allamountin' => 'allamountin',
        'querydate' => 'querydate',
    ];

    public static $times = [
        '1'   => '1 minute',
        '5'   => '5 minutes',
        '15'  => '15 minutes',
        '30'  => '30 minutes',
        '40'  => '40 minutes',
        '60'  => '1 hour',
        '120' => '2 hours',
        '180' => '3 hours',
    ];

    public static $isActive = [
        'No',
        'Yes',
    ];

    public static function time() {
        $time = [];
        for ($i = 0; $i <= 24; $i++) {
            if ($i < 10) $time['0' . $i . ':00'] = '0' . $i . ':00';
            else $time[$i . ':00'] = $i . ':00';
        }
        return $time;
    }

    public static function tableName() {
        return 'wm_exchanger_settings';
    }

    public function rules() {
        return [
            [['exchtype', 'rows', 'sign', 'time', 'sms_is_active', 'email_is_active', 'is_active', 'sms_time_from', 'sms_time_to', 'email_time_from', 'email_time_to'], 'required'],
            [['exchtype'], 'unique'],
            [['field', 'sign'], 'string', 'max' => 255],
            [['value'], 'number'],
            [['exchtype', 'rows'], 'integer', 'min' => 1],
            [['is_active', 'time', 'sms_is_active', 'email_is_active'], 'integer'],
            [['sms_time_from', 'sms_time_to', 'email_time_from', 'email_time_to'], 'string', 'max' => 5],
            ['sms_time_from', 'validateSmsTime'],
            ['email_time_from', 'validateEmailTime'],
        ];
    }

    public function validateSmsTime($attribute) {
        $sms_time_from = (int)$this->sms_time_from;
        $sms_time_to = (int)$this->sms_time_to;

        if ($this->sms_is_active && $sms_time_from >= $sms_time_to) {
            $this->addError($attribute, 'Sending start time is longer than end time');
        }
    }

    public function validateEmailTime($attribute) {
        $email_time_from = (int)$this->email_time_from;
        $email_time_to = (int)$this->email_time_to;

        if ($this->email_is_active && $email_time_from >= $email_time_to) {
            $this->addError($attribute, 'Sending start time is longer than end time');
        }
    }

    public function getWmExchangerModel() {
        return $this->hasOne(WmExchangerModel::className(), ['exchtype' => 'exchtype']);
    }

    public function attributeLabels() {
        return [
            'time'            => 'Period',
            'sms_is_active'   => 'SMS activation',
            'email_is_active' => 'Email activation',
            'is_active'       => 'Currency tracking',
            'sms_time_from'   => 'Start of Sending',
            'sms_time_to'     => 'End of Sending',
            'email_time_from'     => 'Start of Sending',
            'email_time_to'     => 'End of Sending',
        ];
    }
}