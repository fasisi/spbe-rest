<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_discussion_user_action".
 *
 * @property int $id_discussion
 * @property int $id_user
 * @property int $action 0=netral;1=like;2=dislike
 */
class ForumThreadDiscussionUserAction extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_discussion_user_action';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_discussion', 'id_user', 'action'], 'required'],
            [['id_discussion', 'id_user', 'action'], 'integer'],
            [['id_discussion', 'id_user'], 'unique', 'targetAttribute' => ['id_discussion', 'id_user']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_discussion' => 'Id Discussion',
            'id_user' => 'Id User',
            'action' => 'Action',
        ];
    }
}
