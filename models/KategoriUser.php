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
    public static function Reset($id_user, $daftar_kategori)
    {
      KategoriUser::deleteAll("id_user = :id", [":id" => $id_user]);

      foreach($daftar_kategori as $id_kategori)
      {
        $temp = [];
        $temp["id"] = $id_kategori;

        // ambil daftar children dari id_kategori ini
        $children = [];
        $children = KmsKategori::GetList($id_kategori);
        $all_nodes = array_merge($temp, $children);

        foreach($all_nodes as $a_node)
        {
          //pastikan validitas id_kategori
          $test = KmsKategori::find()
            ->where(
              [
                "and",
                "id = :id",
                "is_delete = 0" 
              ],
              [
                ":id" => $a_node["id"] 
              ]
            )
            ->one();

          if( is_null($test) == false )
          {
            $new = new KategoriUser();
            $new["id_user"] = $id_user;
            $new["id_kategori"] = $a_node["id"];
            $new->save();
          }
        }
      }
    }

    // Mengembalikan daftar kategori berdasarkan id_user
    public static function ListByUser($id_user)
    {
      $temps = KategoriUser::findAll(["id_user" => $id_user]);

      $hasil = [];
      foreach($temps as $temp)
      {
        $kategori = KmsKategori::findOne($temp["id_kategori"]);

        $hasil[] = $kategori;
      }

      return $hasil;
    }
}
