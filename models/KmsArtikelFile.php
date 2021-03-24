<?php

namespace app\models;

use Yii;

use app\models\KmsFiles;
use yii\helpers\BaseUrl;

/**
 * This is the model class for table "kms_artikel_file".
 *
 * @property int $id
 * @property int $id_artikel
 * @property int $id_file
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int $is_delete
 */
class KmsArtikelFile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel_file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_artikel', 'id_file', 'id_user_create', 'time_create'], 'required'],
            [['id_artikel', 'id_file', 'id_user_create', 'id_user_delete', 'is_delete'], 'integer'],
            [['time_create', 'time_delete'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_artikel' => 'Id Artikel',
            'id_file' => 'Id File',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'is_delete' => 'Is Delete',
        ];
    }


    public static function GetFiles($id_artikel)
    {
      $temp_list = KmsArtikelFile::findAll(["id_artikel" => $id_artikel]);

      $hasil = [];
      foreach($temp_list as $item)
      {
        $file = KmsFiles::findOne($item["id_file"]);

        $hasil[] = [
          "KmsFiles" => $file,
          "link" => Yii::$app->urlManager->baseUrl . "/files/" . $file["nama"]
        ];
      }

      return $hasil;
    }
}
