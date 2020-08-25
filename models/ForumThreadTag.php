<?php

namespace app\models;

use Yii;

use yii\db\Query

/**
 * This is the model class for table "forum_thread_tag".
 *
 * @property int $id_thread
 * @property int $id_tag
 */
class ForumThreadTag extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_tag';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_thread', 'id_tag'], 'required'],
            [['id_thread', 'id_tag'], 'integer'],
            [['id_thread', 'id_tag'], 'unique', 'targetAttribute' => ['id_thread', 'id_tag']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_thread' => 'Id Thread',
            'id_tag' => 'Id Tag',
        ];
    }


    public static function GetThreadTags($id_thread)
    {
      $q = new Query();
      $q->select("t.*")
        ->from("forum_tag t")
        ->join("JOIN", "forum_thread_tag ft", "ft.id_tag = t.id")
        ->where("ft.id_thread = :id", [":id" => $id_thread]);

      return $q->all();
    }
}
