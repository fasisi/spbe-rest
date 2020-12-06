<?php

namespace app\models;

use Yii;

use yii\db\Query;

/**
 * This is the model class for table "hd_issue_solver".
 *
 * @property int $id_issue
 * @property int $id_user
 */
class HdIssueSolver extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_issue_solver';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_issue', 'id_user'], 'required'],
            [['id_issue', 'id_user'], 'integer'],
            [['id_issue', 'id_user'], 'unique', 'targetAttribute' => ['id_issue', 'id_user']],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id_issue' => 'Id Issue',
            'id_user' => 'Id User',
        ];
    }
    
    public static function GetSolver($id_issue)
    {
    	$query = new Query;
        $query->select('u.id AS id_user,u.nama AS nama_user')
        ->from("user u")
        ->join('LEFT JOIN', 'hd_issue_solver his','his.id_user = u.id')
        ->join('LEFT JOIN', 'hd_issue hi','hi.id = his.id_issue')	
        ->where("hi.id =" .$id_issue);
        $command = $query->createCommand();
        $data = $command->queryAll();

      	return $data;
    }
}
