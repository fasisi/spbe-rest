<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "kms_artikel".
 *
 * @property int $id
 * @property int $id_kategori Relasi ke tabel kms_kategori
 * @property int $linked_id_content idcontent pada Confluence
 * @property string $judul
 * @property string $konten
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int|null $id_user_publish
 * @property string|null $time_publish
 * @property int $is_delete
 * @property int $is_publish
 */
class KmsArtikel extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori', 'linked_id_content', 'id_user_create', 
              'id_user_delete', 'id_user_publish', 'is_delete', 'is_publish'], 'integer'],
            [['linked_id_content', 'id_user_create', 'time_create'], 'required'],
            [['konten'], 'string'],
            [['time_create', 'time_delete', 'time_publish'], 'safe'],
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
            'linked_id_content' => 'Linked Id Content',
            'judul' => 'Judul',
            'konten' => 'Konten',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'id_user_publish' => 'Id User Publish',
            'time_publish' => 'Time Publish',
            'is_delete' => 'Is Delete',
            'is_publish' => 'Is Publish',
        ];
    }
}
