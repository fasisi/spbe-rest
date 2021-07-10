<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "unit_kerja".
 *
 * @property int $id
 * @property int $id_parent Relasi ke racord parent.
 * @property int $id_departments
 * @property string $nama
 * @property string|null $deskripsi
 * @property int|null $id_user_create
 * @property string|null $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int|null $is_delete
 */
class UnitKerja extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'unit_kerja';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_parent', 'id_departments', 'id_user_create', 'id_user_delete', 'is_delete'], 'integer'],
            [['id_departments', 'nama'], 'required'],
            [['time_create', 'time_delete'], 'safe'],
            [['nama', 'deskripsi'], 'string', 'max' => 100],
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
            'id_departments' => 'Id Departments',
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
