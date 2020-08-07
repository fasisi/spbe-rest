<?php

namespace app\modules\general\controllers;

use Yii;
use yii\helpers\Json;

use app\models\User;

class UserController extends \yii\rest\Controller
{
  public function behaviors()
  {
    $behaviors = parent::behaviors();
    $behaviors['verbs'] = [
      'class' => \yii\filters\VerbFilter::className(),
      'actions' => [
        'create'    => ['POST'],
        'retrieve'  => ['GET'],
        'update'    => ['PUT'],
        'delete'    => ['DELETE'],
      ]
    ];
    return $behaviors;
  }


  // Membuat record user
  //
  // Method : POST
  // Request type: JSON
  // Request format: 
  // {
  //   username: "", 
  //   password: "", 
  //   ...
  // }
  // Response type: JSON
  // Response format: 
  // {
  //   status: "ok/not ok", 
  //   pesan:"", 
  //   result: {
  //     id: ...,
  //     username: ...,
  //     password: ...,
  //     ...
  //   }
  // }
  public function actionCreate()
  {
    // pastikan method = POST
    // pastikan request parameter terpenuhi
    // insert record
    // jika berhasil, kembalikan record yang baru.
    // jika gagal, kembalikan pesan kesalahan dari database

    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    $new = new User();
    $new["nama"]            = $payload["nama"];
    $new["username"]        = $payload["username"];
    $new["password"]        = hash("sha512", "123" .$payload["password"] . "123");
    $new["jenis_kelamin"]   = $payload["jenis_kelamin"];
    $new["id_departments"]  = $payload["id_departments"];
    $new["time_create"]     = $payload["time_create"];
    $new["id_user_create"]  = $payload["id_user_create"];
    $new["nip"]             = $payload["nip"];
    $new->save();

    if( $new->hasErrors() == false )
    {
      return array(
        "status" => "ok",
        "pesan" => "New record inserted",
        "result" => $new
      );
    }
    else
    {
      return array(
        "status" => "not ok",
        "pesan" => "Fail on insert record",
        "result" => $new->getErrors()
      );
    }
  }

  //  Mengambil record user
  //
  //  Request type: JSON
  //  Request format:
  //  {
  //    id: ...
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "result": "",
  //  }
  public function actionRetrieve()
  {

    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {
      $record = User::findOne($payload["id"]);

      if( is_null($record) == false )
      {
        return [
          "status" => "ok",
          "pesan" => "Record found",
          "result" => $record,
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record not found",
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Required parameter: id",
      ];
    }

  }

  //  Mengambil record User
  //
  //  Request type: JSON
  //  Request format:
  //  {
  //    id: ...,
  //    nama: ...,
  //    username: ...,
  //    id_departments: ...,
  //    jenis_kelamin: ...,
  //  }
  //  Response type: JSON,
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "result": "",
  //  }
  public function actionUpdate()
  {
    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {
      $user = User::findOne($payload["id"]);

      if( is_null($user) == false )
      {
        $user["nama"]           = $payload["nama"];
        $user["id_departments"] = $payload["id_departments"];
        $user["jenis_kelamin"]  = $payload["jenis_kelamis"];
        $user["password"]       = hash("sha512", "123" . $payload["password"] . "123");
        $user->save();

        if( $user->hasErrors() == false )
        {
          return [
            "status" => "ok",
            "pesan" => "Record updated",
            "result" => $user,
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Fail on update record",
            "result" => $user->getErrors(),
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record not found",
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Required parameter not found: id",
      ];
    }

  }

  public function actionSavelastlogin()
  {
    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {
      $user = User::findOne($payload["id"]);

      if( is_null($user) == false )
      {
        $user["time_last_login"]    = date("Y-m-d H:i:s", time());
        $user["is_login"]           = 1;
        $user->save();

        if( $user->hasErrors() == false )
        {
          return [
            "status" => "ok",
            "pesan" => "Record updated",
            "result" => $user,
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Fail on update record",
            "result" => $user->getErrors(),
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record not found",
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Required parameter not found: id",
      ];
    }

  }


  public function actionDelete()
  {
    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {
      $user = User::findOne($payload["id"]);

      if( is_null($user) == false )
      {
        $user["is_deleted"]       = 1;
        $user["time_deleted"]     = date("Y-m-j H:i:s");
        $user["id_user_deleted"]  = $payload["id_user_deleted"];
        $user->save();

        if( $user->hasErrors() == false )
        {
          return [
            "status" => "ok",
            "pesan" => "Record deleted",
            "result" => $user,
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Fail on delete record",
            "result" => $user->getErrors(),
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record not found",
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Required parameter not found: id",
      ];
    }
  }

}
