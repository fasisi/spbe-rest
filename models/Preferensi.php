<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "preferensi".
 *
 * @property int|null $akses_semua_kategori
 */
class Preferensi extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'preferensi';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['akses_semua_kategori'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'akses_semua_kategori' => 'Akses Semua Kategori',
        ];
    }


    // CekFlag
    public static function CekFlag($flag_name, $value)
    {
      $test = Preferensi::find()->one();

      return $test[$flag_name] == $value;
    }

    // CanAksesKategori
    public static function CanAksesKategori($id_user, $id_kategori)
    {
      if( Preferensi::CekFlag('akses_semua_kategori', 1) == true )
      {
        return true;
      }
      else
      {
        // cek record kategori_user
        $test = KategoriUser::find()
          ->where(
            "
              id_user = :id_user AND
              id_kategori = :id_kategori
            ",
            [
              ":id_user" => $id_user,
              ":id_kategori" => $id_kategori,
            ]
          )
          ->one();

        return is_null($test) == false;
      }
    }
}
