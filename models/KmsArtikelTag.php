<?php

namespace app\models;

use Yii;
use yii\db\Query;

/**
 * This is the model class for table "kms_artikel_tag".
 *
 * @property int $id_artikel
 * @property int $id_tag
 */
class KmsArtikelTag extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel_tag';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_artikel', 'id_tag'], 'required'],
            [['id_artikel', 'id_tag'], 'integer'],
            [['id_artikel', 'id_tag'], 'unique', 'targetAttribute' => ['id_artikel', 'id_tag']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_artikel' => 'Id Artikel',
            'id_tag' => 'Id Tag',
        ];
    }

    /*
     *  Mengambil daftar tags berdasarkan id_artikel
      * */
    public static function GetArtikelTags($id_artikel)
    {
      $q = new Query();
      $q->select("t.*")
        ->from("kms_tags t")
        ->join("JOIN", "kms_artikel_tag at", "at.id_tag = t.id")
        ->where("at.id_artikel = :id_artikel", [":id_artikel" => $id_artikel])
        ->orderBy("t.nama asc")
        ->all();

      return $q->all();
    }
}
