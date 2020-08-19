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
        'kategori'   => ['POST, GET, PUT, DELETE'],
      ]
    ];
    return $behaviors;
  }

  public function actionIndex()
  {
      return $this->render('index');
  }

  private function GetPayload()
  {

    // pastikan request parameter lengkap
    $payload = Yii::$app->request->rawBody;

    try
    {
      return Json::decode($payload);
    }
    catch(yii\base\InvalidArgumentException $e)
    {
      return [
        "status"=> "not ok",
        "pesan"=> "Failed on JSON parsing",
        "payload" => $payload
      ];
    }
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
    $payload = $this->GetPayload();
    $method = Yii::$app->request->method;

    switch(true)
    {
    case $method == 'POST':

      $is_nama_valid = isset($payload["nama"]);
      $is_deskripsi_valid = isset($payload["deskripsi"]);
      $is_id_parent_valid = isset($payload["id_parent"]);
      $is_id_parent_valid = $is_id_parent_valid && is_numeric($payload["id_parent"]);

      if(
          $is_nama_valid == true &&
          $is_deskripsi_valid == true &&
          $id_id_parent_valid == true
        )
      {
        $new = new Kategori();
        $new["id_parent"] = $payload["id_parent"];
        $new["nama"] = $payload["nama"];
        $new["deskripsi"] = $payload["deskripsi"];
        $new->save();
        $id = $new->primaryKey;

        return [
          "status" => "ok",
          "pesan" => "Kategori telah disimpan",
          "result" => $new
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang dibutuhkan tidak lengkap: nama, deskripsi, id_parent",
        ];
      }

      break;
    case $method == 'GET':
      $is_id_valid = isset($payload["id"]);
      $is_id_valid = $is_id_valid && is_numeric($payload["id"]);

      if(
          $id_id_valid == true
        )
      {
        $record = Kategori::findOne($payload["id"]);

        if( is_null($record) == false )
        {
          $record["id_parent"] = $payload["id_parent"];
          $record["nama"] = $payload["nama"];
          $record["deskripsi"] = $payload["deskripsi"];
          $record->save();

          return [
            "status" => "ok",
            "pesan" => "Record Kategori ditemukan",
            "result" => $record
          ];
        }
        else
        {
          return [
            "status" => "ok",
            "pesan" => "Record Kategori tidak ditemukan",
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang dibutuhkan tidak lengkap: id",
        ];
      }
      break;
    case $method == 'PUT':
      $is_nama_valid = isset($payload["nama"]);
      $is_deskripsi_valid = isset($payload["deskripsi"]);
      $is_id_valid = isset($payload["id"]);
      $is_id_valid = $is_id_valid && is_numeric($payload["id"]);
      $is_id_parent_valid = isset($payload["id_parent"]);
      $is_id_parent_valid = $is_id_parent_valid && is_numeric($payload["id_parent"]);

      if(
          $is_nama_valid == true &&
          $is_deskripsi_valid == true &&
          $is_id_parent_valid == true &&
          $id_id_valid == true
        )
      {
        $record = Kategori::findOne($payload["id"]);

        if( is_null($record) == false )
        {
          $record["id_parent"] = $payload["id_parent"];
          $record["nama"] = $payload["nama"];
          $record["deskripsi"] = $payload["deskripsi"];
          $record->save();

          return [
            "status" => "ok",
            "pesan" => "Kategori telah disimpan",
            "result" => $record
          ];
        }
        else
        {
          return [
            "status" => "ok",
            "pesan" => "Record Kategori tidak ditemukan",
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang dibutuhkan tidak lengkap: nama, deskripsi, id",
        ];
      }
      break;
    case $method == 'DELETE':
      $is_id_valid = isset($payload["id"]);
      $is_id_valid = $is_id_valid && is_numeric($payload["id"]);

      if(
          $id_id_valid == true
        )
      {
        $record = Kategori::findOne($payload["id"]);

        if( is_null($record) == false )
        {
          $record["is_delete"] = 1;
          $record["id_user_delete"] = 123;
          $record["time_delete"] = date("Y-m-j H:i:s");
          $record->save();

          return [
            "status" => "ok",
            "pesan" => "Record Kategori telah dihapus",
            "result" => $record
          ];
        }
        else
        {
          return [
            "status" => "ok",
            "pesan" => "Record Kategori tidak ditemukan",
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang dibutuhkan tidak lengkap: id",
        ];
      }
      break;
    }
  }

}
