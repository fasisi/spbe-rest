<?php

namespace app\modules\general\controllers;

use Yii;
use yii\helpers\Json;
use yii\db\Query;
use yii\db\IntegrityException;
use yii\web\UploadedFile;
use yii\helpers\BaseUrl;

use app\models\User;
use app\models\UserRoles;
use app\models\KategoriUser;
use app\models\Roles;
use app\models\KmsKategori;
use app\models\HakAksesRoles;
use app\models\KmsFiles;
use app\models\Departments;


use Carbon\Carbon;
use WideImage\WideImage;

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
        'getdata'   => ['GET'],

        "hakakses"  => ['POST', 'GET', 'DELETE', 'PUT'],
      ]
    ];
    return $behaviors;
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

    // cek bahwa email harus unik diantara record user yang is_delete = 0
    $test = User::find()
      ->where(
        [
          "and",
          "is_deleted = 0",
          [
            "or",
            "email = :email",
            "hp = :hp",
          ],
        ],
        [
          ":email" => trim($payload["email"]),
          ":hp" => trim($payload["hp"])
        ]
      )
      ->all();

    if( count($test) == 0 )
    {

      $new = new User();
      $new["nama"]              = $payload["nama"];
      $new["username"]          = $payload["username"];
      $new["password"]          = $payload["password"];
      $new["jenis_kelamin"]     = $payload["jenis_kelamin"];
      $new["id_departments"]    = $payload["id_departments"];
      $new["time_create"]       = date("Y-m-j H:i:s");
      $new["id_user_create"]    = $payload["id_user_create"];
      $new["nip"]               = $payload["nip"];
      $new["hp"]                = $payload["hp"];
      $new["email"]             = $payload["email"];
      $new["id_file_profile"]   = $payload["id_file_profile"];
      $new["status_kepegawaian"]   = $payload["status_kepegawaian"];
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
          "pesan" => "Email dan HP telah terdaftar",
          "result" => $new->getErrors()
        );
      }
    }
    else
    {
      return array(
        "status" => "not ok",
        "pesan" => "Email atau hp telah dipakai",
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
        $profile = "";
        if( $record["id_file_profile"] != -1 )
        {
          // ambil record kms_file
          $kf = KmsFiles::find()->where(["id" => $record["id_file_profile"] ])->one();
          $profile = BaseUrl::base(true) . "/files/" . $kf["thumbnail"];
        }

        $dept = Departments::findOne($record['id_departments']);

        $roles = [];
        $temp_roles = $record->getRoles()->all();
        foreach($temp_roles as $key => $item)
        {
          $role = Roles::findOne( $item['id_roles'] );

          $roles[] = $role;
        }

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
            "departments" => $dept,
            "categories" => $categories,
            "profile" => $profile,
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
        $user["nama"]             = $payload["nama"];
        $user["username"]         = $payload["username"];
        $user["email"]            = $payload["email"];
        $user["hp"]               = $payload["hp"];
        $user["nip"]              = $payload["nip"];
        $user["id_departments"]   = $payload["id_departments"];
        $user["jenis_kelamin"]    = $payload["jenis_kelamin"];
        $user["id_file_profile"]  = $payload["id_file_profile"];
        $user["status_kepegawaian"]  = $payload["status_kepegawaian"];
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
          KategoriUser::Reset($payload["id"], $payload["id_kategori"]);

          /* KategoriUser::deleteAll("id_user = :id", [":id" => $payload["id"]]); */
          /* foreach($payload["id_kategori"] as $id_kategori) */
          /* { */
          /*   //periksa validitas id_role */
          /*   $test = KmsKategori::findOne($id_kategori); */

          /*   if( is_null($test) == false ) */
          /*   { */
          /*     $new = new KategoriUser(); */
          /*     $new["id_user"] = $payload["id"]; */
          /*     $new["id_kategori"] = $id_kategori; */
          /*     $new->save(); */
          /*   } */
          /* } */

          $categories = $user->getCategories()->all();
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
    
    if($payload["params"] == 0) {
      $query->select([
        'user.id AS id_user',
        'user.nama AS nama_user',
        'user.username AS username',
        'user.jenis_kelamin AS jk',
        'user.is_deleted AS is_deleted',
        'user.is_banned AS is_banned',
        'user.nip AS nip',
        'user.id_departments',
        'departments.name AS nama_departments',
        'GROUP_CONCAT(roles.name) AS nama_roles',
        '(
          SELECT
          GROUP_CONCAT(kms_kategori.nama)
          FROM 
            kategori_user
          JOIN
            kms_kategori ON kms_kategori.id = kategori_user.id_kategori
          WHERE
            id_user = user.id
          AND
            kms_kategori.is_delete = 0
        ) as nama_kategori'
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
          'is_deleted = 0 AND is_banned = 0'
        )
        ->groupBy(
          'id_user'
        );
    } 
    else if ($payload["params"] == 1) 
    {
      // ambil daftar user yang status == banned
      $query->select([
        'user.id AS id_user',
        'user.nama AS nama_user',
        'user.jenis_kelamin AS jk',
        'user.is_deleted AS is_deleted',
        'user.is_banned AS is_banned',
        'user.nip AS nip',
        'user.id_departments',
        'departments.name AS nama_departments',
        'GROUP_CONCAT(roles.name) AS nama_roles',
        '(
          SELECT
          GROUP_CONCAT(kms_kategori.nama)
          FROM 
            kategori_user
          JOIN
            kms_kategori ON kms_kategori.id = kategori_user.id_kategori
          WHERE
            id_user = user.id
          AND
            kms_kategori.is_delete = 0
        ) as nama_kategori'
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
          'is_banned = 1 AND is_deleted = 0'
        )
        ->groupBy(
          'id_user'
        );
    } 
    else if($payload["params"] == 2)
    {
      // ambil daftar user is_deleted = 1
      $query->select([
          'user.id AS id_user',
          'user.nama AS nama_user',
          'user.jenis_kelamin AS jk',
          'user.is_deleted AS is_deleted',
          'user.is_banned AS is_banned',
          'user.nip AS nip',
          'user.id_departments',
          'departments.name AS nama_departments',
          'GROUP_CONCAT(roles.name) AS nama_roles',
          '(
            SELECT
            GROUP_CONCAT(kms_kategori.nama)
            FROM 
              kategori_user
            JOIN
              kms_kategori ON kms_kategori.id = kategori_user.id_kategori
            WHERE
              id_user = user.id
            AND
              kms_kategori.is_delete = 0
          ) as nama_kategori'
        ])
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
          'is_deleted = 1 AND is_banned = 0'
        )
        ->groupBy(
          'id_user'
        );
    }

    $command = $query->createCommand();
    $record = $command->queryAll();

    if( !empty($record) )
    {
      // sanitasi nama_kategori dari hasil query
      foreach($record as $key => $item)
      {
        $user = User::findOne($item['id_user']);
        $list_kategori = $user->getCategories();

        $path = "";
        foreach($list_kategori as $item2)
        {
          $temp_path = KmsKategori::CategoryPath($item2['id']);

          $teks_path = "";
          foreach( $temp_path as $item3 )
          {
            $teks_path .= (
              $teks_path == "" ?
              $item3['nama'] :
              " >> " . $item3['nama']
            );
          }

          $path .= "
            <li>{$teks_path}</li>
          ";
        }

        $path = "
          <ul>
            $path
          </ul>
        ";

        /* $record[$key]['nama_kategori'] = $path; */
      }

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

  /*
   * API untuk membuat, mengubah, mengambil, menghapus hak akses berdasarkan
   * id_role dan nama module
   *
   * Method: POST
   * Request type: JSON
   * Request format:
   * {
   *   id_role : 123,
   *   setups : 
   *   [
   *     {
   *       module: "",
   *       can_create: 0/1,
   *       can_retrieve: 0/1,
   *       can_update: 0/1,
   *       can_delete: 0/1
   *     },
   *     ...
   *   ]
   * }
   * Response type: JSON,
   * Response format:
   * {
   *   status: "",
   *   pesan : "",
   *   result: 
   *   { 
   *     records : 
   *     [
   *       { object_of_record }, 
   *       ...
   *     ] 
   *   }
   * }
   *
   * Method: GET
   * Request type: JSON
   * Request format:
   * {
   *   id_role: 123,
   *   module: "abc"
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     record:
   *     {
   *       object of record
   *     }
   *   }
   * }
   *
   * Method: DELETE
   * Request type: JSON
   * Request format:
   * {
   *   id_role: 123,
   *   module: ["abc", ...]
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     records:
   *     [
   *       {
   *         object of record
   *       }, ...
   *     ]
   *   }
   * }
    * */
  public function actionHakakses()
  {

    $payload = Yii::$app->request->rawBody;
    $payload = Json::decode($payload);

    if( Yii::$app->request->isPost )
    {
      $is_id_role_valid = isset($payload["id_role"]);
      $is_module_valid = isset($payload["setups"]);
      $is_module_valid = $is_module_valid && is_array($payload["setups"]);

      if( $is_id_role_valid == true && $is_module_valid == true )
      {
        $hasil = [];
        foreach($payload["setups"] as $setup)
        {
          $har = new HakAksesRoles();
          $har["id_roles"] = $payload["id_role"];
          $har["modules"] = $setup["module"];
          $har["can_create"] = $setup["can_create"];
          $har["can_retrieve"] = $setup["can_retrieve"];
          $har["can_update"] = $setup["can_update"];
          $har["can_delete"] = $setup["can_delete"];
          $har["can_solver"] = $setup["can_solver"];

          try
          {
            $har->save();

            $hasil[] = $har;
          }
          catch(\yii\db\IntegrityException $e)
          {
          }
        }

        return [
          "status" => "ok",
          "pesan" => "Hak akses berhasil disimpan",
          "result" => 
          [
            "payload" => $payload,
            "result" => 
            [
              "count" => count($hasil),
              "records" => $hasil
            ]
          ]
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Hak akses gagal disimpan",
          "result" => 
          [
            "payload" => $payload,
          ]
        ];
      }
    }
    elseif( Yii::$app->request->isGet )
    {
      $is_id_role_valid = isset($payload["id_role"]);
      $is_module_valid = isset($payload["module"]);
      $is_module_valid = $is_module_valid && is_array($payload["module"]);

      if( $is_id_role_valid == true && $is_module_valid == true )
      {
        $har = HakAksesRoles::find()
          ->where(
            [
              "and",
              "id_roles = :id_role",
              ["in", "modules", $payload["module"]]
            ],
            [
              ":id_role" => $payload["id_role"],
            ]
          )
          ->all();


        return [
          "status" => "ok",
          "pesan" => "Hak akses berhasil diambil",
          "result" => 
          [
            "payload" => $payload,
            "HakAksesRoles" => 
            [
              "count" => count($har),
              "records" => $har
            ]
          ]
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Hak akses gagal diambil",
          "result" => 
          [
            "payload" => $payload,
          ]
        ];
      }
    }
    elseif( Yii::$app->request->isDelete )
    {
      $is_id_role_valid = isset($payload["id_role"]);
      $is_module_valid = isset($payload["module"]);
      // $is_module_valid = $is_module_valid && is_array($payload["module"]);

      if( $is_id_role_valid == true && $is_module_valid == true )
      {

        $har = HakAksesRoles::find()
          ->where(
            [
              "and",
              "id_roles = :id_role",
              "modules = :modules"
            ],
            [
              ":id_role" => $payload["id_role"],
              ":modules" => $payload["module"],
            ]
          )
          ->one();

        if( is_null($har) == false )
        {
          $har->delete();

          return [
            "status" => "ok",
            "pesan" => "Hak akses berhasil dihapus",
            "result" => 
            [
              "payload" => $payload,
              "HakAksesRoles" => $har,
            ]
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record hak akses tidak ditemukan",
            "result" => 
            [
              "payload" => $payload,
            ]
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang dibutuhkan tidak lengkap",
          "result" => 
          [
            "payload" => $payload,
          ]
        ];
      }
    }
    elseif( Yii::$app->request->isPut)
    {
      $is_id_role_valid = isset($payload["id_role"]);
      $is_module_valid = isset($payload["module"]);
      // $is_module_valid = $is_module_valid && is_array($payload["module"]);

      if( $is_id_role_valid == true && $is_module_valid == true )
      {

        $har = HakAksesRoles::find()
          ->where(
            [
              "and",
              "id_roles = :id_role",
              "modules = :modules"
            ],
            [
              ":id_role" => $payload["id_role"],
              ":modules" => $payload["module"],
            ]
          )
          ->one();

        if( is_null($har) == false )
        {
         
          $har["id_roles"] = $payload["id_role"];
          $har["modules"] = $payload["module"];
          $har["can_create"] = $payload["can_create"];
          $har["can_retrieve"] = $payload["can_retrieve"];
          $har["can_update"] = $payload["can_update"];
          $har["can_delete"] = $payload["can_delete"];
	  $har["can_solver"] = $payload["can_solver"];
          $har->save();
            
          return [
            "status" => "ok",
            "pesan" => "Hak akses berhasil diupdate",
            "result" => 
            [
              "payload" => $payload,
              "HakAksesRoles" => $har,
            ]
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record hak akses tidak ditemukan",
            "result" => 
            [
              "payload" => $payload,
            ]
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang dibutuhkan tidak lengkap",
          "result" => 
          [
            "payload" => $payload,
          ]
        ];
      }
    }
  }
  
  public function actionGetnavigation()
  {

    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    $query = new Query();
    
    
    $query->select([
    '*',
    
    ])
    ->from('hak_akses_roles')
    ->where(["in", "id_roles" , $payload["id_roles"]])
    ->groupBy('modules');

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
  
  public function actionGethakakses()
  {

    $payload = Yii::$app->request->rawBody;
    Yii::info("payload = $payload");
    $payload = Json::decode($payload);

    $query = new Query();
    
    
    $query->select([
    '*',
    
    ])
    ->from('hak_akses_roles')
    ->where(["in", "id_roles" , $payload["id_roles"]])
    ->andWhere(["modules" => $payload["modules"]]);

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


  // menerima file yang menjadi foto profile suatu user.
  // file akan di-standarisasi sehingga ukurannya menjadi 3x4
  public function actionFotoProfile()
  {
    $payload = $this->GetPayload();

    if( Yii::$app->request->isPost )
    {
      $file = UploadedFile::getInstanceByName("file");

      if( is_null($file) == false )
      {
        $deskripsi = "Foto profile user";
        $id_user_actor = Yii::$app->request->post("id_user_actor");
        $is_id_user_valid = isset($id_user_actor);
        $is_file_valid = isset($file);

        if( $is_id_user_valid == true && $is_file_valid == true )
        {
          $path = Yii::$app->basePath . 
            DIRECTORY_SEPARATOR . 'web' .
            DIRECTORY_SEPARATOR . 'files'.
            DIRECTORY_SEPARATOR;

          Yii::info("path = $path");

          $time_hash = date("YmdHis");
          $file_name = $id_user_actor . "-" . $file->baseName . "-" . $time_hash . "." . $file->extension;

          ini_set("display_errors", 1);
          error_reporting(E_ALL);

          if($file->saveAs($path . $file_name) == true)
          {
            $file_name_2 = "";
            $is_image = true;
            if( preg_match_all("/(jpg|jpeg|png)/i", $file->extension) == true )
            {
              $asal = WideImage::loadFromFile($path . $file_name);
              $file_name_2 = $id_user_actor . "-" . $file->baseName . "-" . $time_hash . "-thumb" . "." . $file->extension;

              $resize = $asal->resize("150", "200");
              $resize->saveToFile($path . $file_name_2);
            }
            else
            {
              $file_name_2 = "logo_pdf.png";
              $is_image = false;
            }

            $kf = new KmsFiles();
            $kf["nama"] = $file_name;
            $kf["deskripsi"] = $deskripsi;
            $kf["thumbnail"] = $file_name_2;
            $kf["id_user_create"] = $id_user_actor;
            $kf["time_create"] = date("Y-m-d H:i:s");
            $kf->save();

            return [
              "status" => "ok",
              "pesan" => "Berhasil menyimpan file",
              "result" => $kf,
              "thumbnail" => 
                $is_image == true ? 
                  BaseUrl::base(true) . "/files/" . $kf["thumbnail"] : 
                  BaseUrl::base(true) . "/files/" . "logo_pdf.png", 
              "link" => BaseUrl::base(true) . "/files/" . $kf["nama"], 
            ];

          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Gagal menyimpan file",
              "result" => 
              [
                "path.file_name" => $path . $file_name,
                "UploadedFile" => $file,
                "last error" => error_get_last()
              ]
            ];
          }

        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang diperlukan tidak lengkap: id_user_actor, file",
            "request" =>
            [
              "payload" => $payload,
              "UploadedFile" => $file,
              "full_path" => Yii::$app->request->post("full_path")
            ]
          ];
        }
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "There is something wrong",
          "result" => 
          [
            "UploadedFile" => $file,
            "full_path" => Yii::$app->request->post("full_path")
          ]
        ];
      }

    }
    else if( Yii::$app->request->isPut )
    {
      // update attachment
    }
    else if( Yii::$app->request->isDelete )
    {
      // hapus attachment

      $payload = $this->GetPayload();

      $is_id_file_valid = isset($payload["id_file"]);
      $is_id_artikel_valid = isset($payload["id_artikel"]);
      $is_id_user_valid = isset($payload["id_user_actor"]);

      if( 
          $is_id_file_valid == true && 
          $is_id_artikel_valid == true &&
          $is_id_user_valid == true 
        )
      {
        $test = KmsFiles::findOne($payload["id_file"]);
        $test_artikel = KmsArtikel::findOne($payload["id_artikel"]);

        if( 
            is_null($test) == false 
          )
        {
          // hapus record file
            $test["is_delete"] = 1;
            $test["id_user_delete"] = $payload["id_user_actor"];
            $test["time_delete"] = date("Y-m-d H:i:s");
            $test->save();
          // hapus record file

          // hapus file-nya
            $path = Yii::$app->basePath .
              DIRECTORY_SEPARATOR . "web" .
              DIRECTORY_SEPARATOR. "files" .
              DIRECTORY_SEPARATOR;

            unlink($path . $test["nama"]);

            // jika attachment tipenya JPG, maka hapus juga thumbnail-nya.
            if( preg_match_all("/(jpg|jpeg)/i", $test["nama"]) != false )
            {
              unlink($path . $test["thumbnail"]);
            }

          // hapus file-nya

          // hapus relasinya dengan artikel


            if( is_null($test_artikel) == false )
            {
              $artikel_file = KmsArtikelFile::find()
                ->where(
                  "id_artikel = :id_artikel and id_file = :id_file",
                  [
                    ":id_artikel" => $payload["id_artikel"],
                    ":id_file" => $payload["id_file"],
                  ]
                )
                ->one();

              $artikel_file["is_delete"] = 1;
              $artikel_file["id_user_delete"] = $payload["id_user_actor"];
              $artikel_file["time_delete"] = date("Y-m-d H:i:s");
              $artikel_file->save();
            }
            else
            {
            }

          // hapus relasinya dengan artikel

          return [
            "status" => "ok",
            "pesan" => "File berhasil dihapus",
            "result" => 
            [
              "artikel_file" => $artikel_file,
            ]
          ];

        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record file tidak ditemukan",
            "payload" => $payload
          ];
        }

      }
      else
      {
      }

    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Request hanya menerima method POST, PUT atau DELETE",
      ];
    }
  }

}
