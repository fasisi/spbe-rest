<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "hd_issue_disposisi".
 *
 * @property int $id
 * @property int $id_issue Relasi ke record tiket
 * @property int $id_sme Relasi ke record SME si penerima disposisi
 * @property string $pesan Pesan mengenai disposisi
 * @property string $time_create
 */
class HdIssueDisposisi extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_issue_disposisi';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_issue', 'id_sme_in', 'id_sme_out', 'pesan', 'time_create'], 'required'],
            [['id_issue', 'id_sme_in', 'id_sme_out'], 'integer'],
            [['pesan'], 'string'],
            [['time_create'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_issue' => 'Id Issue',
            'id_sme' => 'Id Sme',
            'pesan' => 'Pesan',
            'time_create' => 'Time Create',
        ];
    }
}
