<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "kms_artikel_user_status".
 *
 * @property int $id_user
 * @property int $id_artikel
 * @property int $status 0=netral;1=like;2=dislike
 */
class KmsArtikelUserStatus extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel_user_status';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'id_artikel', 'status'], 'required'],
            [['id_user', 'id_artikel', 'status'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_user' => 'Id User',
            'id_artikel' => 'Id Artikel',
            'status' => 'Status',
        ];
    }
}
