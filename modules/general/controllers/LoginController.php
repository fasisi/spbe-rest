<?php

namespace app\modules\general\controllers;

use Yii;
use yii\helpers\Json;

use app\models\User;

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

      // pastikan method = POST
      if( Yii::$app->request->isPost == true )
      {
        $payload = Yii::$app->request->rawBody;
        $payload = Json::decode($payload);

        // pastikan ada field username dan password
        if( isset($payload["username"]) == true && 
            isset($payload["password"]) == true 
          )
        {
          // validate username dan password

          $test = User::find()
            ->where([
              "username" => $payload["username"]
            ])
            ->one();

          if( is_null($test) == false )
          {
            $test = User::find()
              ->where([
                "username" => $payload["username"],
                "password" => hash("sha512", "123" . $payload["password"] . "123"),
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
            $status = "not ok";
            $pesan = "username does not exist";
          }


          // kembalikan result dalam format JSON
        }
        else
        {
          $status = "not ok";
          $pesan = "Required parameters not found: username, password";
        }

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
      }

    }

}
