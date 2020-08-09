<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "kms_artikel_log".
 *
 * @property int $id
 * @property int $id_artikel
 * @property string $type_action
 * @property string $value_action
 * @property int $id_user_action
 * @property string $time_action
 */
class KmsArtikelLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_artikel', 'type_action', 'value_action', 'id_user_action', 'time_action'], 'required'],
            [['id_artikel', 'id_user_action'], 'integer'],
            [['value_action'], 'string'],
            [['time_action'], 'safe'],
            [['type_action'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_artikel' => 'Id Artikel',
            'type_action' => 'Type Action',
            'value_action' => 'Value Action',
            'id_user_action' => 'Id User Action',
            'time_action' => 'Time Action',
        ];
    }
}
