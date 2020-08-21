<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_discussion".
 *
 * @property int $id
 * @property int $id_thread
 * @property string $judul
 * @property string $konten
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int $is_delete
 * @property int $is_answer
 * @property int|null $id_user_answer
 * @property string|null $time_answer
 */
class ForumThreadDiscussion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_discussion';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_thread', 'judul', 'konten', 'id_user_create', 'time_create'], 'required'],
            [['id_thread', 'id_user_create', 'id_user_delete', 'is_delete', 'is_answer', 'id_user_answer'], 'integer'],
            [['konten'], 'string'],
            [['time_create', 'time_delete', 'time_answer'], 'safe'],
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
            'id_thread' => 'Id Thread',
            'judul' => 'Judul',
            'konten' => 'Konten',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'is_delete' => 'Is Delete',
            'is_answer' => 'Is Answer',
            'id_user_answer' => 'Id User Answer',
            'time_answer' => 'Time Answer',
        ];
    }
}
