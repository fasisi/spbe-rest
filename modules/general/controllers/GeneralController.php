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
        'kategori'   => ['PORT, GET, PUT, DELETE'],
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

  /*
   *  Fungsi untuk melakukan management kategori
   *
   *  --===== Create Kategori =====----
   *  Method: POST
   *  Request type: JSON
   *  Request format:
   *  {
   *    "nama": "abc",
   *    "id_parent": "-1/123",
   *    "deskripsi": "abc"
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": { <object_record_kategori> }
   *  }
   *
   *  --===== Get Kategori =====----
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id": 123,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": { <object_record_kategori> }
   *  }
   *
   *  --===== Update Kategori =====----
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id": 123,
   *    "id_parent": 123,
   *    "nama": "asdfasdf",
   *    "deskripsi": "asdfasd"
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": { <object_record_kategori> }
   *  }
   *
   *  --===== DELETE Kategori =====----
   *  Method: DELETE
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id": 123,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": { <object_record_kategori> }
   *  }
    * */
  public function actionKategori()
  {
  }

}
