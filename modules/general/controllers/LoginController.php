<?php

namespace app\modules\general\controllers;

use Yii;
use yii\helpers\Json;

use app\models\User;
use yii\db\Query;

class LoginController extends \yii\rest\Controller
{
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

      // pastikan ada field username dan password
      if (
        isset($payload["username"]) == true &&
        isset($payload["password"]) == true
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

<<<<<<< HEAD
          if( is_null($test) == false )
          {
            $test = User::find()
              ->where([
                "username" => $payload["username"],
                "password" => $payload["password"],
              ])
              ->one();

            if( is_null($test) == false )
            {
              $status = "ok";
              $pesan = "valid";
              $result = $test;
            }
            else
            {
              $status = "not ok";
              $pesan = "invalid";
            }
          }
          else
          {
=======
          $query = new Query;
          $query->select([
            'user.id AS id_user',
            'user.nama AS nama_user',
            'user.time_last_login AS last_login',
            'roles.id AS id_roles',
            'roles.name AS nama_roles'
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
            );
          $command = $query->createCommand();
          $data = $command->queryAll();

          if (is_null($test) == false) {
            $status = "ok";
            $pesan = "valid";
            $result = $data;
          } else {
>>>>>>> tes_api
            $status = "not ok";
            $pesan = "invalid";
            $result = "empty";
          }
        } else {
          $status = "not ok";
          $pesan = "username does not exist";
          $result = "empty";
        }

<<<<<<< HEAD
        return array(
          "status" => $status,
          "pesan" => $pesan,
          "result" => $result,
        );
      }
      else
      {
        return array(
          "status" => "not ok",
          "pesan" => "Wrong method.",
        );
=======

        // kembalikan result dalam format JSON
      } else {
        $status = "not ok";
        $pesan = "Required parameters not found: username, password";
        $result = "empty";
>>>>>>> tes_api
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
}
