<?php

namespace app\models;

use Yii;

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
}
