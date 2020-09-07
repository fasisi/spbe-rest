<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "hd_issue_tag".
 *
 * @property int $id_issue
 * @property int $id_tag
 */
class HdIssueTag extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_issue_tag';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_issue', 'id_tag'], 'required'],
            [['id_issue', 'id_tag'], 'integer'],
            [['id_issue', 'id_tag'], 'unique', 'targetAttribute' => ['id_issue', 'id_tag']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_issue' => 'Id Issue',
            'id_tag' => 'Id Tag',
        ];
    }
}
