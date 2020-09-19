<?php

namespace app\models;

use Yii;
use yii\db\Query;

/**
 * This is the model class for table "hd_issue_user_action".
 *
 * @property int $id_user
 * @property int $id_issue
 * @property int $action 0=netral;1=like;2=dislike
 */
class HdIssueUserAction extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_issue_user_action';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'id_issue', 'action'], 'required'],
            [['id_user', 'id_issue', 'action'], 'integer'],
            [['id_user', 'id_issue'], 'unique', 'targetAttribute' => ['id_user', 'id_issue']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_user' => 'Id User',
            'id_issue' => 'Id Issue',
            'action' => 'Action',
        ];
    }


    /*
     *  Merangkum jumlah like / dislike / view atas suatu thread
      * */
    public static function Summarize($id_issue)
    {
      $q = new Query();
      $hasil = $q->select("count(s.id_user) as jumlah")
        ->from("hd_issue_user_action s")
        ->join("JOIN", "hd_issue i", "i.id = s.id_issue")
        ->where(
          [
            "and",
            "i.id = :id_issue",
            "s.action = 1"
          ],
          [":id_issue" => $id_issue]
        )
        ->one();
      $like = $hasil["jumlah"];


      $q = new Query();
      $hasil = $q->select("count(s.id_user) as jumlah")
        ->from("hd_issue_user_action s")
        ->join("JOIN", "hd_issue i", "i.id = s.id_issue")
        ->where(
          [
            "and",
            "i.id = :id_issue",
            "s.action = 2"
          ],
          [":id_issue" => $id_issue]
        )
        ->one();
      $dislike = $hasil["jumlah"];


      $q = new Query();
      $hasil = $q->select("count(l.id) as jumlah")
        ->from("hd_issue_activity_log l")
        ->join("JOIN", "hd_issue i", "i.id = l.id_issue")
        ->where(
          [
            "and",
            "i.id = :id_issue",
            "l.action = -1"
          ],
          [":id_issue" => $id_issue]
        )
        ->one();
      $view = $hasil["jumlah"];


      // update record hd_issue
      $issue = HdIssue::findOne($id_issue);
      $issue["like"] = $like;
      $issue["dislike"] = $dislike;
      $issue["view"] = $view;
      $issue->save();
    }


    public static function GetUserAction($id_issue, $id_user_actor)
    {
      $hasil = HdIssueUserAction::find()
        ->where(
          [
            "and",
            "id_issue = :id_issue",
            "id_user = :id_user"
          ],
          [
            ":id_issue" => $id_issue,
            ":id_user" => $id_user_actor
          ]
        )
        ->one();

      return $hasil;
    }


}
