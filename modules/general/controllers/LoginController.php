<?php

namespace app\modules\general\controllers;

use Yii;
use yii\helpers\Json;

use app\models\User;
use yii\db\Query;

class LoginController extends \yii\rest\Controller
{
  public function behaviors()
  {
    date_default_timezone_set("Asia/Jakarta");
    $behaviors = parent::behaviors();
    return $behaviors;
  }
  // Memeriksa apakah username dan password valid
  // Method : POST
  // Paylod type : JSON
  // Request : {username: "", password: ""}
  // Response type: JSON
  // Response : { status: "ok/not ok", pesan: "username not found/invalid/valid" }
  public function actionValidate()
  {
    $status = "";
    $pesan = "";
    $result = "";

    // pastikan method = POST
    if (Yii::$app->request->isPost == true) {
      $payload = Yii::$app->request->rawBody;
      $payload = Json::decode($payload);

      // pastikan ada field username,password,id_roles
      if (
        isset($payload["username"]) == true &&
        isset($payload["password"]) == true &&
        isset($payload["id_roles"]) == true
      ) {
        // validate username dan password

        $test = User::find()
          ->where([
            "username" => $payload["username"]
          ])
          ->one();

        if (is_null($test) == false) {
          $test = User::find()
            ->where([
              "username" => $payload["username"],
              "password" => hash("sha512", "123" . $payload["password"] . "123"),
            ])
            ->one();
          
          // Mengambil Data User Tersebut
          $query = new Query;
          $query->select([
            'user.id AS id_user',
            'user.nama AS nama_user',
            'user.time_last_login AS last_login',
            'roles.id AS id_roles',
            'roles.name AS nama_roles',
            'is_deleted AS is_deleted',
	          'is_banned AS is_banned'
            ]
            )
            ->from('user')
            ->join(
              'INNER JOIN',
              'user_roles',
              'user_roles.id_user =user.id'
            )
            ->join(
              'INNER JOIN',
              'roles',
              'roles.id =user_roles.id_roles'
            )
            ->where([
              "id_user" => $test->id,
              "id_roles" => $payload["id_roles"]
            ])
            ->LIMIT(1);
          $command = $query->createCommand();
          $data = $command->queryAll();
          
          // Looping untuk mengambil nilai dari is_deleted dan is_banned
          foreach($data as $val) {
            $is_deleted = $val['is_deleted'];
            $is_banned = $val['is_banned'];
          }

          if (is_null($test) == false) {
            if ($is_deleted == 1) { // jika record user telah di delete / is_deleted bernilai TRUE
              $status = "not ok";
              $pesan = "User telah di delete";
              $result = "empty";
            } else if ($is_banned == 1) { // jika record user telah di banned / is_banned bernilai TRUE
              $status = "not ok";
              $pesan = "User telah di banned";
              $result = "empty";
            } else { // Jika record user tidak di delete ataupun di banned / is_banned dan is_deleted bernilai FALSE
              $status = "ok";
              $pesan = "valid";
              $result = $data;
            }
          } else {
            $status = "not ok";
            $pesan = "invalid";
            $result = "empty";
          }
        } else {
          $status = "not ok";
          $pesan = "username does not exist";
          $result = "empty";
        }


        // kembalikan result dalam format JSON
      } else {
        $status = "not ok";
        $pesan = "Required parameters not found: username, password, id_roles";
        $result = "empty";
      }

      return array(
        "status" => $status,
        "pesan" => $pesan,
        "result" => $result,
      );
    } else {
      return array(
        "status" => "not ok",
        "pesan" => "Wrong method.",
        "result" => "Empty.",
      );
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

  public function actionLogout()
  {
    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {
      $user = User::findOne($payload["id"]);

      if( is_null($user) == false )
      {

        $user["is_login"]           = 0;
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
}
