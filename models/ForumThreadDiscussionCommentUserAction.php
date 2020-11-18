<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_discussion_comment_user_action".
 *
 * @property int $id_comment
 * @property int $id_user
 * @property int $status 0=netral;1=like;2=dislike
 */
class ForumThreadDiscussionCommentUserAction extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_discussion_comment_user_action';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_comment', 'id_user', 'status'], 'required'],
            [['id_comment', 'id_user', 'status'], 'integer'],
            [['id_comment', 'id_user'], 'unique', 'targetAttribute' => ['id_comment', 'id_user']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_comment' => 'Id Comment',
            'id_user' => 'Id User',
            'status' => 'Status',
        ];
    }
}
