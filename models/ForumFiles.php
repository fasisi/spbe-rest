<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_files".
 *
 * @property int $id
 * @property string $nama
 * @property string $thumbnail
 * @property string|null $deskripsi
 * @property int $is_delete
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property int|null $time_delete
 */
class ForumFiles extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_files';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['nama', 'thumbnail', 'id_user_create', 'time_create'], 'required'],
            [['is_delete', 'id_user_create', 'id_user_delete'], 'integer'],
            [['time_create', 'time_delete'], 'safe'],
            [['nama', 'thumbnail'], 'string', 'max' => 500],
            [['deskripsi'], 'string', 'max' => 1500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nama' => 'Nama',
            'thumbnail' => 'Thumbnail',
            'deskripsi' => 'Deskripsi',
            'is_delete' => 'Is Delete',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
        ];
    }
}
