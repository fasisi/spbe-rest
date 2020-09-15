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

    /*
     *  Mengembalikan daftar kategori mengikuti struktur parent-child.
     *  [
     *    [id, nama, level], ...
     *  ]
    * */
  public static function GetList($id_parent = -1, $level = 1)
  {
    $hasil = [];

    if( $id_parent == -1 )
    {
      $daftar = KmsKategori::find()
        ->where("id_parent = -1 and is_delete = 0")
        ->orderBy("nama asc")
        ->all();

      foreach($daftar as $item)
      {
        $temp = [];
        $temp["id"] = $item["id"];
        $temp["id_parent"] = $item["id_parent"];
        $temp["nama"] = $item["nama"];
        $temp["level"] = $level;
        $hasil[] = $temp;

        $children = KmsKategori::find()
          ->where("id_parent = {$item["id"]} and is_delete = 0")
          ->orderBy("nama asc")
          ->all();

        // apakah punya children??
        if( count($children) > 0)
        {
          foreach($children as $child)
          {
            $hasil = array_merge($hasil, KmsKategori::GetList($child["id"], $level + 1));
          }
        }
      }
    }
    else
    {
      $current = KmsKategori::find()
        ->where("id = $id_parent")
        ->orderBy("nama asc")
        ->one();

      $temp = [];
      $temp["id"] = $current["id"];
      $temp["id_parent"] = $current["id_parent"];
      $temp["nama"] = $current["nama"];
      $temp["level"] = $level;
      $hasil[] = $temp;

      $children = KmsKategori::find()
        ->where("id_parent = $id_parent and is_delete = 0")
        ->orderBy("nama asc")
        ->all();

      // apakah punya children??
      if( count($children) > 0)
      {
        foreach($children as $child)
        {
          $hasil = array_merge($hasil, KmsKategori::GetList($child["id"], $level + 1));
        }
      }
    }

    return $hasil;
  }


    /*
     * Memeriksa validitas max_child_depth jika suatu node ditambahkan
     * sebagai child dari id_parent.
     *
    * */
  public static function CheckDepthValidity($id_parent)
  {
    $hasil = true;
    $pesan = "";

    if($id_parent != -1)  // bukan root
    {
      $depth = 0;
      $valid = true;
      $terus = true;

      do
      {
        $test = KmsKategori::findOne($id_parent);

        if( is_null($test) == false )
        {
          $depth++;

          if($test["max_child_depth"] > -1)
          {
            if( $depth <= $test["max_child_depth"] )
            {
              $id_parent = $test["id_parent"];

              if($id_parent == -1) //sudah di root.
              {
                $terus = false;
              }
            }
            else
            {
              $terus = false;
              $hasil = false;
              $pesan = "Mencapai kedalaman maksimum pada " . $test["nama"];
            }
          }
          else
          {
            $id_parent = $test["id_parent"];

            if($id_parent == -1) //sudah di root.
            {
              $terus = false;
              $hasil = true;
            }
          }
        }
        else
        {
          $terus = false;
          $hasil = false;
          $pesan = "Parent tidak dikenal";
        }
      }while($terus == true);

      return [
        "hasil" => $hasil,
        "pesan" => $pesan,
      ];
    }
    else
    {
      return [
        "hasil" => true,
      ];
    }
  }

  public static function CategoryPath($id_kategori)
  {
    $path = [];
    $terus = true;

    do
    {

      $test = KmsKategori::findOne($id_kategori);

      if( is_null($test) == false )
      {
        if( $test["id_parent"] != -1 )
        {

          $id_kategori = $test["id_parent"];
        }
        else
        {
          $terus = false;
        }

        $temp = [];
        $temp[] = $test;

        $path = array_merge($temp, $path);
      }
      else
      {
        $path[] = "No path";
        $terus = false;
      }


    } while($terus == true);

    return $path;
  }
}
