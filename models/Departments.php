<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "departments".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $linked_id_user
 */
class Departments extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'departments';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['linked_id_user'], 'integer'],
            [['name', 'description'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'description' => 'Description',
            'linked_id_user' => 'Linked Id User',
        ];
    }


    public static function NameById($id)
    {
      $hasil = Departments::findOne($id);

      return $hasil["name"];
    }


    /*
     *  Mengembalikan daftar instansi mengikuti struktur parent-child.
     *  [
     *    [id, nama, level], ...
     *  ]
    * */
  public static function GetList($id_parent = -1, $level = 1)
  {
    $hasil = [];

    if( $id_parent == -1 )
    {
      $daftar = Departments::find()
        ->where("id_parent = -1 and is_delete = 0")
        ->orderBy("nomor asc")
        ->all();

      foreach($daftar as $item)
      {
        $temp = [];
        $temp["id"] = $item["id"];
        $temp["id_parent"] = $item["id_parent"];
        $temp["nama"] = $item["name"];
        $temp["level"] = $level;
        $hasil[] = $temp;

        $children = Departments::find()
          ->where("id_parent = {$item["id"]} and is_delete = 0")
          ->orderBy("nomor asc")
          ->all();

        // apakah punya children??
        if( count($children) > 0)
        {
          foreach($children as $child)
          {
            $hasil = array_merge($hasil, Departments::GetList($child["id"], $level + 1));
          }
        }
      }
    }
    else
    {
      $current = Departments::find()
        ->where("id = $id_parent")
        ->orderBy("nomor asc")
        ->one();

      $temp = [];
      $temp["id"] = $current["id"];
      $temp["id_parent"] = $current["id_parent"];
      $temp["nama"] = $current["name"];
      $temp["level"] = $level;
      $hasil[] = $temp;

      $children = Departments::find()
        ->where("id_parent = $id_parent and is_delete = 0")
        ->orderBy("nomor asc")
        ->all();

      // apakah punya children??
      if( count($children) > 0)
      {
        foreach($children as $child)
        {
          $hasil = array_merge($hasil, Departments::GetList($child["id"], $level + 1));
        }
      }
    }

    return $hasil;
  }


}
