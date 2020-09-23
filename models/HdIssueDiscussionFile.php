<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "hd_issue_discussion_file".
 *
 * @property int $id
 * @property int $id_discussion
 * @property string $nama
 * @property string $file_name
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
            [['id_discussion', 'nama', 'file_name', 'id_user_create', 'time_create'], 'required'],
            [['id_discussion', 'id_user_create', 'id_user_delete', 'is_delete'], 'integer'],
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
