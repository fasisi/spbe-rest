<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "hd_kategori_pic".
 *
 * @property int $id
 * @property int $id_kategori Relasi ke tabel kms_kategori
 * @property int $id_user Relasi ke tabel user
 */
class HdKategoriPic extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_kategori_pic';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori', 'id_user'], 'required'],
            [['id_kategori', 'id_user'], 'integer'],
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
            'id_user' => 'Id User',
        ];
    }
}
