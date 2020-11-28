<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "roles".
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int|null $linked_id_user
 */
class Roles extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'roles';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name'], 'required'],
            [['linked_id_user'], 'integer'],
            [['name', 'description'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'description' => 'Description',
            'linked_id_user' => 'Linked Id User',
        ];
    }


    public static function IdByCodeName($code_name)
    {
      $roles = Roles::find()
        ->where(
          [
            "code_name = :code_name"
          ],
          [
            ":code_name" => $code_name
          ]
        )
        ->one();

      return $roles["id"];
    }
}
