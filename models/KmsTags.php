<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "kms_tags".
 *
 * @property int $id
 * @property int|null $linked_id_label Mapping ke Confluence
 * @property string $nama
 * @property string $deskripsi
 * @property int $status 0=baru;1=approval;2=reject
 * @property int $is_delete
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_approval
 * @property string|null $time_approval
 * @property int|null $id_user_reject
 * @property string|null $time_reject
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 */
class KmsTags extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_tags';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['linked_id_label', 'status', 'is_delete', 'id_user_create', 'id_user_approval', 'id_user_reject', 'id_user_delete'], 'integer'],
            [['nama', 'deskripsi', 'id_user_create', 'time_create'], 'required'],
            [['deskripsi'], 'string'],
            [['time_create', 'time_approval', 'time_reject', 'time_delete'], 'safe'],
            [['nama'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'linked_id_label' => 'Linked Id Label',
            'nama' => 'Nama',
            'deskripsi' => 'Deskripsi',
            'status' => 'Status',
            'is_delete' => 'Is Delete',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_approval' => 'Id User Approval',
            'time_approval' => 'Time Approval',
            'id_user_reject' => 'Id User Reject',
            'time_reject' => 'Time Reject',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
        ];
    }
}
