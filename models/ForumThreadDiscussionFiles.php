<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_discussion_files".
 *
 * @property int $id_thread_discussion
 * @property int $id_thread_file
 */
class ForumThreadDiscussionFiles extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_discussion_files';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_thread_discussion', 'id_thread_file'], 'required'],
            [['id_thread_discussion', 'id_thread_file'], 'integer'],
            [['id_thread_discussion', 'id_thread_file'], 'unique', 'targetAttribute' => ['id_thread_discussion', 'id_thread_file']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_thread_discussion' => 'Id Thread Discussion',
            'id_thread_file' => 'Id Thread File',
        ];
    }
}
