<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "forum_thread_hak_baca".
 *
 * @property int $id
 * @property int $id_thread
 * @property int $id_user
 */
class ForumThreadHakBaca extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread_hak_baca';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_thread', 'id_user'], 'required'],
            [['id_thread', 'id_user'], 'integer'],
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
            'id_user' => 'Id User',
        ];
    }
}
