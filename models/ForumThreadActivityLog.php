<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_activity_log".
 *
 * @property int $id
 * @property int $id_thread
 * @property int $id_user id_user si pelaku action / status
 * @property int $type_log 1=status log;2=action log
 * @property int|null $status 0=new;1=publish;2=un-publish;3=reject;4=freeze
 * @property int|null $action 0=neutral;1=like;2=dislike
 * @property string|null $time_action
 * @property string|null $time_status
 */
class ForumThreadActivityLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_activity_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_thread', 'id_user', 'type_log'], 'required'],
            [['id_thread', 'id_user', 'type_log', 'status', 'action'], 'integer'],
            [['time_action', 'time_status'], 'safe'],
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
            'id_user' => 'Id User',
            'type_log' => 'Type Log',
            'status' => 'Status',
            'action' => 'Action',
            'time_action' => 'Time Action',
            'time_status' => 'Time Status',
        ];
    }
}
