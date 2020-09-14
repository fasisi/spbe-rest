<?php

namespace app\models;

use Yii;

use yii\db\Query;

/**
 * This is the model class for table "kms_artikel_activity_log".
 *
 * @property int $id
 * @property int $id_artikel
 * @property int $id_user
 * @property int $type_action
 * @property string $time_action
 */
class KmsArtikelActivityLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel_activity_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_artikel', 'id_user', 'type_log'], 'required'],
            [['id_artikel', 'id_user', 'action'], 'integer'],
            [['time_action'], 'safe'],
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
            'id_user' => 'Id User',
            'action' => 'Type Action',
            'status' => 'Type Status',
            'time_action' => 'Time Action',
        ];
    }

  
    /*
     *  Merangkum jumlah like / dislike / view atas suatu thread
      * */
    public static function Summarize($id_artikel)
    {
      $q = new Query();
      $hasil = $q->select("count(s.id_user) as jumlah")
        ->from("kms_artikel_user_status s")
        ->join("JOIN", "kms_artikel a", "a.id = s.id_artikel")
        ->where(
          [
            "and",
            "a.id = :id_artikel",
            "s.status = 1"
          ],
          [":id_artikel" => $id_artikel]
        )
        ->one();
      $like = $hasil["jumlah"];


      $q = new Query();
      $hasil = $q->select("count(s.id_user) as jumlah")
        ->from("kms_artikel_user_status s")
        ->join("JOIN", "kms_artikel a", "a.id = s.id_artikel")
        ->where(
          [
            "and",
            "a.id = :id_artikel",
            "s.status = 2"
          ],
          [":id_artikel" => $id_artikel]
        )
        ->one();
      $dislike = $hasil["jumlah"];


      $q = new Query();
      $hasil = $q->select("count(l.id) as jumlah")
        ->from("kms_artikel_activity_log l")
        ->join("JOIN", "kms_artikel a", "a.id = l.id_artikel")
        ->where(
          [
            "and",
            "l.type_log = 2",
            "a.id = :id_artikel",
            "l.action = -1"
          ],
          [":id_artikel" => $id_artikel]
        )
        ->one();
      $view = $hasil["jumlah"];


      // update record forum_thread
      $artikel = KmsArtikel::findOne($id_artikel);
      $artikel["like"] = $like;
      $artikel["dislike"] = $dislike;
      $artikel["view"] = $view;
      $artikel->save();
    }


}
