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

    /*
     *  Merefresh relasi antara user dan kategori pada tabel kategori_user
      * */
    public static function Refresh($id_user, $daftar_kategori)
    {
      KategoriUser::deleteAll("id_user = :id", [":id" => $id_user]);

      foreach($daftar_kategori as $id_kategori)
      {
        //pastikan validitas id_kategori
        $test = KmsKategori::find()
          ->andWhere(["id = :id"])
          ->andWhere(["is_delete = 0"])
          ->params([":id" => $id_kategori])
          ->one()

        if( is_null($test) == false )
        {
          $new = new KategoriUser();
          $new["id_user"] = $id_user;
          $new["id_kategori"] = $id_kategori;
          $new->save();
        }
      }
    }
}
