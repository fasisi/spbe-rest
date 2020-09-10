<?php

namespace app\models;

use Yii;
use yii\db\Query;

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


    public static function GetIssueTags($id_issue)
    {
      $q = new Query();
      $q->select("t.*")
        ->from("kms_tags t")
        ->join("JOIN", "hd_issue_tag ft", "ft.id_tag = t.id")
        ->where("ft.id_issue = :id", [":id" => $id_issue]);

      return $q->all();
    }

}
