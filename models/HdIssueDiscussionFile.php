<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "hd_issue_discussion_file".
 *
 * @property int $id
 * @property int $id_discussion Relasi ke tabel hd_issue
 * @property int $id_file Relasi ke tabel kms_files
 * @property string|null $nama
 * @property string|null $file_name
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int $is_delete
 */
class HdIssueDiscussionFile extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_issue_discussion_file';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_discussion', 'id_file', 'id_user_create', 'time_create'], 'required'],
            [['id_discussion', 'id_file', 'id_user_create', 'id_user_delete', 'is_delete'], 'integer'],
            [['time_create', 'time_delete'], 'safe'],
            [['nama'], 'string', 'max' => 500],
            [['file_name'], 'string', 'max' => 1500],
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
            'id_file' => 'Id File',
            'nama' => 'Nama',
            'file_name' => 'File Name',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'is_delete' => 'Is Delete',
        ];
    }


    public static function StoreAttachments($id, $id_user, $files)
    {
      HdIssueDiscussionFile::deleteAll(["id_discussion" => $id]);

      foreach($files as $item_file)
      {
        $new = new HdIssueDiscussionFile();
        $new["id_discussion"] = $id;
        $new["id_file"] = $item_file;
        $new["time_create"] = date("Y-m-j H:i:s");
        $new["id_user_create"] = $id_user;
        $new['nama'] = '---';
        $new['file_name'] = '---';
        $new->save();
      }
    }
}
