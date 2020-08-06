<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $nama
 * @property string $username
 * @property string $password
 * @property string $jenis_kelamin
 * @property int $id_departments
 * @property string $time_create
 * @property int $id_user_create
 * @property string $time_deleted
 * @property int $id_user_deleted
 * @property string $time_last_activity
 * @property string $time_last_login
 * @property int $is_login 0 = Tidak, 1 = Ya
 * @property int $is_deleted 0 = Tidak, 1 = Ya
 * @property int $is_banned 0 = Tidak, 1 = Ya
 * @property string $nip
 */
class User extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['nama', 'username', 'password', 'id_user_create'], 'required'],
            [['id_departments', 'id_user_create', 'id_user_deleted', 'is_login', 'is_deleted', 'is_banned'], 'integer'],
            [['time_create', 'time_deleted', 'time_last_activity', 'time_last_login'], 'safe'],
            [['nama'], 'string', 'max' => 100],
            [['username', 'password'], 'string', 'max' => 200],
            [['nip'], 'string', 'max' => 20],
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
            'username' => 'Username',
            'password' => 'Password',
            'jenis_kelamin' => 'Jenis Kelamin',
            'id_departments' => 'Id Departments',
            'time_create' => 'Time Create',
            'id_user_create' => 'Id User Create',
            'time_deleted' => 'Time Deleted',
            'id_user_deleted' => 'Id User Deleted',
            'time_last_activity' => 'Time Last Activity',
            'time_last_login' => 'Time Last Login',
            'is_login' => 'Is Login',
            'is_deleted' => 'Is Deleted',
            'is_banned' => 'Is Banned',
            'nip' => 'Nip',
        ];
    }
}
