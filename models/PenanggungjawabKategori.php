<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "penanggungjawab_kategori".
 *
 * @property int $id
 * @property int|null $id_kategori
 * @property int|null $id_departments
 */
class PenanggungjawabKategori extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'penanggungjawab_kategori';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori', 'id_departments'], 'integer'],
            [['id_kategori', 'id_departments'], 'unique', 'targetAttribute' => ['id_kategori', 'id_departments']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_kategori' => 'Id Kategori',
            'id_departments' => 'Id Departments',
        ];
    }
}
