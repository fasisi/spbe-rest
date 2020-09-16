<?php

namespace app\modules\general\controllers;

use Yii;
use yii\helpers\Json;
use yii\db\Query;

use app\models\User;
use app\models\UserRoles;
use app\models\KategoriUser;
use app\models\Roles;
use app\models\KmsKategori;

class UserController extends \yii\rest\Controller
{
  public function behaviors()
  {
    date_default_timezone_set("Asia/Jakarta");
    $behaviors = parent::behaviors();
    $behaviors['verbs'] = [
      'class' => \yii\filters\VerbFilter::className(),
      'actions' => [
        'create'    => ['POST'],
        'retrieve'  => ['GET'],
        'update'    => ['PUT'],
        'delete'    => ['DELETE'],
        'getdata'   => ['GET']
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
  //   id_kategori: [1,2,3,...]
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
    $payload = Json::decode($payload);

    $new = new User();
    $new["nama"]            = $payload["nama"];
    $new["username"]        = $payload["username"];
    $new["password"]        = $payload["password"];
    $new["jenis_kelamin"]   = $payload["jenis_kelamin"];
    $new["id_departments"]  = $payload["id_departments"];
    $new["time_create"]     = date("Y-M-j H:i:s");
    $new["id_user_create"]  = $payload["id_user_create"];
    $new["nip"]             = $payload["nip"];
    $new->save();

    // Mencari record terakhir
    /* $last_record = User::find()->where(['id' => User::find()->max('id')])->one(); */
    $id_user = $new->primaryKey;

    // Insert record ke table user_roles
    foreach($payload["roles"] as $id_role)
    {
      $user_roles = new UserRoles();
      $user_roles['id_user'] = $id_user;
      $user_roles['id_roles'] = $id_role;
      $user_roles->save();
    }

    //insert record kategori ke tabel kategori_user
    KategoriUser::Reset($id_user, $payload["id_kategori"]);

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
        $roles = $record->getRoles()->all();
        $list_kategori_user = KategoriUser::find()
          ->where(["and", "id_user = :id_user"], [":id_user" => $record["id"]])
          ->all();

        $categories = [];
        foreach($list_kategori_user as $kategori_item)
        {
          $temp = [];
          $temp["category"] = KmsKategori::findOne($kategori_item["id_kategori"]);
          $temp["category_path"] = KmsKategori::CategoryPath($kategori_item["id_kategori"]);

          $categories[] = $temp;
        }

        return [
          "status" => "ok",
          "pesan" => "Record found",
          "result" => 
          [
            "record" => $record,
            "roles" => $roles,
            "categories" => $categories,
          ]
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
  //    id_roles: [1,2,...],
  //    id_kategori: [1, 2, 3, ...]
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
        $user["username"]       = $payload["username"];
        $user["id_departments"] = $payload["id_departments"];
        $user["jenis_kelamin"]  = $payload["jenis_kelamin"];
        $user->save();

        if( $user->hasErrors() == false )
        {
          // update roles
          UserRoles::deleteAll("id_user = :id", [":id" => $payload["id"]]);
          foreach($payload["id_roles"] as $id_role)
          {
            //periksa validitas id_role
            $test = Roles::findOne($id_role);

            if( is_null($test) == false )
            {
              $new = new UserRoles();
              $new["id_user"] = $payload["id"];
              $new["id_roles"] = $id_role;
              $new["id_system"] = null;
              $new->save();
            }
          }

          $roles = $user->getRoles()->all();
          // update roles



          // update kategori
          KategoriUser::deleteAll("id_user = :id", [":id" => $payload["id"]]);
          foreach($payload["id_kategori"] as $id_kategori)
          {
            //periksa validitas id_role
            $test = KmsKategori::findOne($id_kategori);

            if( is_null($test) == false )
            {
              $new = new KategoriUser();
              $new["id_user"] = $payload["id"];
              $new["id_kategori"] = $id_kategori;
              $new->save();
            }
          }

          $categories = $user->getKategori()->all();
          // update kategori

          return [
            "status" => "ok",
            "pesan" => "Record updated",
            "result" => 
            [
              "record" => $user,
              "roles" => $roles,
              "categories" => $categories,

            ]
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

  /*
   *  Mengambil daftar user
    * */
  public function actionGetdata()
  {

    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    $query = new Query();
    $query->select([
      'user.id AS id_user',
      'user.nama AS nama_user',
      'user.jenis_kelamin AS jk',
      'user.is_deleted AS is_deleted',
      'user.is_banned AS is_banned',
      'user.nip AS nip',
      'departments.name AS nama_departments',
      'GROUP_CONCAT(roles.name) AS nama_roles'
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
        'departments',
        'user.id_departments =departments.id'
      )
      ->join(
        'INNER JOIN',
        'roles',
        'roles.id =user_roles.id_roles'
      )
      ->where(
      	'is_deleted = 0'
      )
      ->groupBy(
        'id_user'
      );
    $command = $query->createCommand();
    $record = $command->queryAll();

    if( !empty($record) )
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
        "result" => "empty"
      ];
    }
  }

  //  Mengambil record User
  //
  //  Request type: JSON
  //  Request format:
  //  Response type: JSON,
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "result": "",
  //  }

  public function actionUpdatepassword()
  {
    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {
      $user = User::findOne($payload["id"]);

      if( is_null($user) == false )
      {
        $user["password"]           = $payload["password"];
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

  public function actionBanned()
  {
    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {

      User::updateAll(['is_banned' => 1],['in','id',$payload["id"]]);

      return [
        "status" => "ok",
        "pesan" => "Record Banned"
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Required parameter not found: id",
      ];
    }
  }

  public function actionUnbanned()
  {
    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    if( isset($payload["id"]) == true )
    {

      User::updateAll(['is_banned' => 0],['in','id',$payload["id"]]);

      return [
        "status" => "ok",
        "pesan" => "Record Banned"
      ];
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
