<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_discussion_comment".
 *
 * @property int $id
 * @property int $id_discussion
 * @property string $judul
 * @property string $konten
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int $is_delete
 */
class ForumThreadDiscussionComment extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_discussion_comment';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_discussion', 'judul', 'konten', 'id_user_create', 'time_create'], 'required'],
            [['id_discussion', 'id_user_create', 'id_user_delete', 'is_delete'], 'integer'],
            [['konten'], 'string'],
            [['time_create', 'time_delete'], 'safe'],
            [['judul'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_discussion' => 'Id Discussion',
            'judul' => 'Judul',
            'konten' => 'Konten',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'is_delete' => 'Is Delete',
        ];
    }
}
