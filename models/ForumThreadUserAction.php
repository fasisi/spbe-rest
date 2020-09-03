<?php

namespace app\models;

use Yii;
use yii\db\Query;

use app\models\ForumThread;

/**
 * This is the model class for table "forum_thread_user_action".
 *
 * @property int $id_user
 * @property int $id_thread
 * @property int $action 0=netral;1=like;2=dislike
 */
class ForumThreadUserAction extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_user_action';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'id_thread', 'action'], 'required'],
            [['id_user', 'id_thread', 'action'], 'integer'],
            [['id_user', 'id_thread'], 'unique', 'targetAttribute' => ['id_user', 'id_thread']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_user' => 'Id User',
            'id_thread' => 'Id Thread',
            'action' => 'Action',
        ];
    }


    /*
     *  Merangkum jumlah like / dislike / view atas suatu thread
      * */
    public static function Summarize($id_thread)
    {
      $q = new Query();
      $hasil = $q->select("count(s.id_user) as jumlah")
        ->from("forum_thread_user_action s")
        ->join("JOIN", "forum_thread f", "f.id = s.id_thread")
        ->where(
          [
            "and",
            "f.id = :id_thread",
            "s.action = 1"
          ],
          [":id_thread" => $id_thread]
        )
        ->one();
      $like = $q["jumlah"];


      $q = new Query();
      $hasil = $q->select("count(s.id_user) as jumlah")
        ->from("forum_thread_user_action s")
        ->join("JOIN", "forum_thread f", "f.id = s.id_thread")
        ->where(
          [
            "and",
            "f.id = :id_thread",
            "s.action = 2"
          ],
          [":id_thread" => $id_thread]
        )
        ->one();
      $dislike = $q["jumlah"];


      $q = new Query();
      $hasil = $q->select("count(l.id) as jumlah")
        ->from("forum_thread_activity_log l")
        ->join("JOIN", "forum_thread t", "t.id = l.id_thread")
        ->where(
          [
            "and",
            "t.id = :id_thread",
            "l.action = -1"
          ],
          [":id_thread" => $id_thread]
        )
        ->one();
      $view = $q["jumlah"];


      // update record forum_thread
      $thread = ForumThread::findOne($id_thread);
      $thread["like"] = $like;
      $thread["dislike"] = $dislike;
      $thread["view"] = $view;
      $thread->save();
    }
}
