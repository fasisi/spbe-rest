<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "kms_kategori".
 *
 * @property int $id
 * @property int $id_parent
 * @property string $nama
 * @property string $deskripsi
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int $is_delete
 */
class KmsKategori extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_kategori';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_parent', 'id_user_create', 'id_user_delete', 'is_delete'], 'integer'],
            [['nama', 'deskripsi', 'id_user_create', 'time_create'], 'required'],
            [['deskripsi'], 'string'],
            [['time_create', 'time_delete'], 'safe'],
            [['nama'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_parent' => 'Id Parent',
            'nama' => 'Nama',
            'deskripsi' => 'Deskripsi',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'is_delete' => 'Is Delete',
        ];
    }
}
