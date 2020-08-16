<?php

namespace app\modules\general\controllers;

use app\models\KmsTags;

class GeneralController extends \yii\rest\Controller
{
  public function behaviors()
  {
    date_default_timezone_set("Asia/Jakarta");
    $behaviors = parent::behaviors();
    $behaviors['verbs'] = [
      'class' => \yii\filters\VerbFilter::className(),
      'actions' => [
        'gettags'    => ['GET'],
      ]
    ];
    return $behaviors;
  }

  public function actionIndex()
  {
      return $this->render('index');
  }

  /*
   *  Mengembalikan daftar tags yang terdaftar dalam database.
    * */
  public function actionGettags()
  {
    $list = KmsTags::find()
      ->where(
        "status = 1"
      )
      ->orderBy("nama asc")
      ->all();

    return [
      "result" => $list
    ];
  }

}
