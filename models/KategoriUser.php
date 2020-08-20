<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "kategori_user".
 *
 * @property int $id
 * @property int $id_kategori
 * @property int $id_user
 */
class KategoriUser extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kategori_user';
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