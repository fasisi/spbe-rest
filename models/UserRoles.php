<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property int $id_user
 * @property int $id_roles
 * @property int $id_system
 */
class UserRoles extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'user_roles';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_user', 'id_roles'], 'required'],
            [['id_user', 'id_roles'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_user' => 'ID User',
            'id_roles' => 'ID Roles',
            'id_system' => 'ID System',
        ];
    }

    public static function CekRole($id_user, $role_code_name)
    {
      $id_role = Roles::IdByCodeName($role_code_name);

      $test = UserRoles::find()
        ->where(
          ["and", "id_user = :id_user", "id_roles = :id_roles"],
          [":id_user" => $id_user, ":id_roles" => $id_role]
        )
        ->one();

      return is_null($test) == false;
    }
}
