<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "kms_artikel_activity_log".
 *
 * @property int $id
 * @property int $id_artikel
 * @property int $id_user
 * @property int $type_action
 * @property string $time_action
 */
class KmsArtikelActivityLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel_activity_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_artikel', 'id_user', 'type_log'], 'required'],
            [['id_artikel', 'id_user', 'action'], 'integer'],
            [['time_action'], 'safe'],
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
            'id_user' => 'Id User',
            'action' => 'Type Action',
            'status' => 'Type Status',
            'time_action' => 'Time Action',
        ];
    }
}
