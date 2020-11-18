<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_discussion_selected".
 *
 * @property int $id
 * @property int $id_thread
 * @property int $id_discussion
 */
class ForumThreadDiscussionSelected extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_discussion_selected';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_thread', 'id_discussion'], 'required'],
            [['id_thread', 'id_discussion'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_thread' => 'Id Thread',
            'id_discussion' => 'Id Discussion',
        ];
    }
}
