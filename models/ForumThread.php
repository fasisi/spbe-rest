<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread".
 *
 * @property int $id
 * @property int $id_kategori Relasi ke tabel kms_kategori
 * @property int $linked_id_question idquestion pada Confluence-Question
 * @property string|null $judul
 * @property string|null $konten
 * @property int $view
 * @property int $like
 * @property int $dislike
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_update
 * @property string|null $time_update
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int|null $id_user_publish
 * @property string|null $time_publish
 * @property int $is_delete
 * @property int $is_publish
 * @property int|null $status 0=new;1=publish;2=un-publish;3=reject;4=freeze
 */
class ForumThread extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori', 'linked_id_question', 'view', 'like', 'dislike', 'id_user_create', 'id_user_update', 'id_user_delete', 'id_user_publish', 'is_delete', 'is_publish', 'status'], 'integer'],
            [['linked_id_question', 'id_user_create', 'time_create'], 'required'],
            [['konten'], 'string'],
            [['time_create', 'time_update', 'time_delete', 'time_publish'], 'safe'],
            [['judul'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_kategori' => 'Id Kategori',
            'linked_id_question' => 'Linked Id Question',
            'judul' => 'Judul',
            'konten' => 'Konten',
            'view' => 'View',
            'like' => 'Like',
            'dislike' => 'Dislike',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_update' => 'Id User Update',
            'time_update' => 'Time Update',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'id_user_publish' => 'Id User Publish',
            'time_publish' => 'Time Publish',
            'is_delete' => 'Is Delete',
            'is_publish' => 'Is Publish',
            'status' => 'Status',
        ];
    }
}
