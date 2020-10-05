<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "hak_akses_roles".
 *
 * @property int $id
 * @property int $id_roles
 * @property string $modules
 * @property int $can_create
 * @property int $can_retrieve
 * @property int $can_update
 * @property int $can_delete
 */
class HakAksesRoles extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hak_akses_roles';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_roles', 'modules', 'can_create', 'can_retrieve', 'can_update', 'can_delete'], 'required'],
            [['id_roles', 'can_create', 'can_retrieve', 'can_update', 'can_delete'], 'integer'],
            [['modules'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_roles' => 'Id Roles',
            'modules' => 'Modules',
            'can_create' => 'Can Create',
            'can_retrieve' => 'Can Retrieve',
            'can_update' => 'Can Update',
            'can_delete' => 'Can Delete',
        ];
    }
}
