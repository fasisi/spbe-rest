<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_file".
 *
 * @property int $id
 * @property int $id_thread
 * @property string $nama
 * @property string $file_name
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int $is_delete
 */
class ForumThreadFile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_thread', 'id_file', 'id_user_create', 'time_create'], 'required'],
            [['id_thread', 'id_file', 'id_user_create', 'id_user_delete', 'is_delete'], 'integer'],
            [['time_create', 'time_delete'], 'safe'],
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
            'nama' => 'Nama',
            'file_name' => 'File Name',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'is_delete' => 'Is Delete',
        ];
    }
}
