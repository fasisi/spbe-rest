<?php

namespace app\modules\general\controllers;

use Yii;
use yii\helpers\Json;
use yii\db\Query;

use app\models\KmsTags;
use app\models\KmsKategori;
use app\models\KategoriUser;
use app\models\User;

class GeneralController extends \yii\rest\Controller
{
  public function behaviors()
  {
    date_default_timezone_set("Asia/Jakarta");
    $behaviors = parent::behaviors();
    $behaviors['verbs'] = [
      'class' => \yii\filters\VerbFilter::className(),
      'actions' => [
        'gettags'            => ['GET'],
        'kategori'           => ['POST', 'GET', 'PUT', 'DELETE'],
        'kategorilist'       => ['GET'],
        'kategoriparent'     => ['PUT'],

        'taglist'            => ['GET'],
        'tagcreate'          => ['POST'],
        'tagget'             => ['GET'],
        'tagupdate'          => ['PUT'],
        'tagdelete'          => ['DELETE']
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


  // ==========================================================================
  // Kategori management
  // ==========================================================================

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
       *    "max_child_depth": "-1/123",
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
          $is_max_child_depth_valid = isset($payload["max_child_depth"]);
          $is_max_child_depth_valid = $is_max_child_depth_valid && is_numeric($payload["max_child_depth"]);

          if(
              $is_nama_valid == true &&
              $is_deskripsi_valid == true &&
              $is_id_parent_valid == true
            )
          {
            $test = KmsKategori::CheckDepthValidity($payload["id_parent"]);
            if( $test["hasil"] == true)
            {
              $new = new KmsKategori();
              $new["id_parent"] = $payload["id_parent"];
              $new["max_child_depth"] = $payload["max_child_depth"];
              $new["nama"] = $payload["nama"];
              $new["deskripsi"] = $payload["deskripsi"];
              $new["id_user_create"] = 123;
              $new["time_create"] = date("Y-m-j H:i:s");
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
                "pesan" => $test["pesan"],
                "payload" => $payload
              ];
            }

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
              $is_id_valid == true
            )
          {
            $record = KmsKategori::findOne($payload["id"]);

            if( is_null($record) == false )
            {
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
          $is_max_child_depth_valid = isset($payload["max_child_depth"]);
          $is_max_child_depth_valid = $is_max_child_depth_valid && is_numeric($payload["max_child_depth"]);

          if(
              $is_nama_valid == true &&
              $is_deskripsi_valid == true &&
              $is_id_parent_valid == true &&
              $is_id_valid == true
            )
          {
            $record = KmsKategori::findOne($payload["id"]);

            if( is_null($record) == false )
            {
              $test = KmsKategori::CheckDepthValidity($payload["id_parent"]);
              if( $test["hasil"] == true)
              {
                $record["id_parent"] = $payload["id_parent"];
                $record["max_child_depth"] = $payload["max_child_depth"];
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
                  "status" => "not ok",
                  "pesan" => $test["pesan"],
                ];
              }

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
              $is_id_valid == true
            )
          {
            $record = KmsKategori::findOne($payload["id"]);

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

      /*
       *  Mengembalikan semua record kategori
       *
       *  Method: GET
       *  Request type: JSON
       *  Request format: 
       *  {
       *  },
       *  Response type: JSON
       *  Response format:
       *  {
       *    "status": "ok/not ok",
       *    "pesan": "",
       *    "result": 
       *    [
       *      {
       *        "id": 123,
       *        "id_parent": 123,
       *        "status": "abc",
       *        "level": 123
       *      }, ...
       *    ]
       *  }
        * */
      public function actionKategorilist()
      {
        $list = KmsKategori::GetList();

        return [
          "status" => "ok",
          "pesan" => "Daftar kategori berhasil diambil",
          "result" => $list,
        ];
      }

      public function actionCategoriesByUser()
      {
        $payload = $this->GetPayload();

        $iduser = ($payload["iduser"]);

        $q = new Query();
        $q->select("c.*")
          ->from("kms_kategori c")
          ->join("JOIN", "kategori_user ku", "ku.id_kategori = c.id")
          ->where(
            [
              "and",
              "ku.id_user = :iduser"
            ],
            [
              ":iduser" => $iduser,
            ]
          )
          ->orderBy("c.nama asc");
        $categories = $q->all();

        $hasil = [];
        foreach( $categories as $category )
        {
          $temp = KmsKategori::CategoryPath($category["id"]);
          $text = "";
          foreach($temp as $a_temp)
          {
            $text = $text . ($text == "" ? $a_temp["nama"] : " > " . $a_temp["nama"]); 
          }

          $temp = [];
          $temp["value"] = $category["id"];
          $temp["text"] = $text;

          $hasil[] = $temp;
        }

        return [
          "records" => $hasil,
        ];
          
      }

      // Mengembalikan daftar user berdasarkan string pencarian, instansi dan kategori
      public function actionUsersForHakBaca()
      {
        $payload = $this->GetPayload();

        $iduser = ($payload["iduser"]);
        $nama = ($payload["nama"]);

        $user = User::findOne($iduser);

        $temp_list_kategori = KategoriUser::findAll(["id_user" => $user["id"]]);
        $list_kategori = [];
        foreach($temp_list_kategori as $a_kategori)
        {
          $list_kategori[] = $a_kategori["id_kategori"];
        }

        $q = new Query();
        $q->select("u.*")
          ->from("user u")
          ->join("join", "kategori_user ku", "ku.id_user = u.id")
          ->where(
            [
              "and",
              ["in", "ku.id_kategori", $list_kategori],
              "u.id_departments = :idinstansi",
              "u.id <> :iduser",
              ["like", "u.nama", $nama]
            ],
            [
              ":iduser" => $user["id"],
              ":idinstansi" => $user["id_departments"],
            ]
          )
          ->orderBy("u.nama asc")
          ->groupBy("u.id");

        $daftar_user = $q->all();

        $hasil = [];
        foreach( $daftar_user as $a_user )
        {
          $temp = [];
          $temp["value"] = $a_user["id"];
          $temp["text"] = $a_user["nama"];

          $hasil[] = $temp;
        }

        return [
          "records" => $hasil,
        ];

      }

      /*
       *  Mengupdate parent suatu kategori
       *
       *  Method: PUT
       *  Request type: JSON
       *  Request format:
       *  {
       *    id_kategori: 123,
       *    id_parent: 123
       *  }
       *  Reponse type: JSON
       *  Response format:
       *  {
       *    status: ok,
       *    result:
       *    {
       *      object of record
       *    }
       *  }
        * */
      public function actionKategoriparent()
      {
        $payload = $this->GetPayload();

        $is_id_kategori_valid = isset($payload["id_kategori"]);
        $is_id_parent_valid = isset($payload["id_parent"]);

        $test = KmsKategori::findOne($payload["id_kategori"]);
        if( is_null($test) == true )
        {
          return [
            "status" => "not ok",
            "pesan" => "id_kategori tidak dikenal"
          ];
        }

        $test = KmsKategori::findOne($payload["id_parent"]);
        if( is_null($test) == true )
        {
          return [
            "status" => "not ok",
            "pesan" => "id_parent tidak dikenal"
          ];
        }

        if( $is_id_kategori_valid == true && $is_id_parent_valid == true )
        {
          $test = KmsKategori::CheckDepthValidity($payload["id_parent"]);

          if( $test["hasil"] == true )
          {
            // lakukan update
            $kategori = KmsKategori::findOne($payload["id_kategori"]);
            $kategori["id_parent"] = $payload["id_parent"];
            $kategori->save();

            return [
              "status" => "ok",
              "pesan" => "Record berhasil di-update",
              "result" => $kategori
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => $test["pesan"],
            ];
          }

        }
        else
        {

          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak valid: id_kategori (integer), id_parent (integer)",
            "payload" => $payload
          ];
        }

      }

  // ==========================================================================
  // Kategori management
  // ==========================================================================




  // ==========================================================================
  // Tag management
  // ==========================================================================



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
       *  Mengembalikan daftar tags
       *  
       *  Method: GET
       *  Request type: JSON
       *  Request format :
       *  {
       *  }
       *  Response type: JSON
       *  Response format:
       *  {
       *    "status": "ok",
       *    "pesan": "",
       *    "result": 
       *    [
       *      { record_of_tag }, ...
       *    ],
       *
       *  }
        * */
      public function actionTaglist()
      {
        $hasil = KmsTags::find()
          ->where("is_delete = 0")
          ->orderBy("nama asc")
          ->all();

        return [
          "status" => "ok",
          "pesan" => "Record berhasil diambil",
          "result" => $hasil
        ];
      }

      /*
       *  Membuat record tag
       *
       *  Method: POST
       *  Request type: JSON
       *  Request format:
       *  {
       *    "nama": "sdf",
       *    "deskripsi": "asdfa",
       *    "id_user": 123
       *  }
       *  Response type: JSON
       *  Response format:
       *  {
       *    "status": "ok",
       *    "pesan": "sadfalskj",
       *    "result": 
       *    {
       *      record tag
       *    }
       *  }
        * */
      public function actionTagcreate()
      {
        $payload = $this->GetPayload();
        $jira_conf = Yii::$app->restconf->confs['confluence'];

        $is_id_user_valid = isset($payload["id_user"]);
        $is_nama_valid = isset($payload["nama"]);
        $is_deskripsi_valid = isset($payload["deskripsi"]);

        $test = User::findOne($payload["id_user"]);
        $is_id_user_valid = $is_id_user_valid && is_null($test);

        if(
            $is_id_user_valid == true &&
            $is_nama_valid == true &&
            $is_deskripsi_valid == true
          )
        {
          $new = new KmsTag();
          $new["nama"] = $payload["nama"];
          $new["deskripsi"] = $payload["deskripsi"];
          $new["id_user_create"] = $payload["id_user"];
          $new["time_create"] = date("Y-m-j H:i:s");
          $new->save();

          return [
            "status" => "ok",
            "pesan" => "Record berhasil dibikin",
            "result" => $new
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak valid: nama, deskripsi, id_user (integer)",
            "payload" => $payload
          ];
        }

      }

      /*
       *  Mengambil record tag berdasarkan id
       *
       *  Method: GET
       *  Request type: JSON
       *  Request format:
       *  {
       *    "id": 123
       *  }
       *  Response type: JSON
       *  Response format:
       *  {
       *    "status": "ok",
       *    "pesan": "sadfasd",
       *    "result":
       *    {
       *      record of tag
       *    }
       *  }
        * */
      public function actionTagget()
      {
        $payload = $this->GetPayload();

        $is_id_valid = isset($payload["id"]);
        $is_id_valid = $is_id_valid && is_numeric($payload["id"]);

        if($is_id_valid == true)
        {
          $test = KmsTag::findOne($payload["id"]);

          if( is_null($test) == false )
          {
            return [
              "status" => "ok",
              "pesan" => "Record ditemukan",
              "result" => $test,
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Record tag tidak ditemukan",
            ];
          }

        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak valid: id (integer)",
          ];
        }
      }

      /*
       *  Mengupdate record tag
       *
       *  Method: PUT
       *  Request type: JSON
       *  Request format:
       *  {
       *    "id": 123,
       *    "id_user": 123,
       *    "nama": "asdf",
       *    "deskripsi": "asdf"
       *  }
       *  Response type: JSON
       *  Response format:
       *  {
       *    "status": "ok",
       *    "": "",
       *    "": "",
       *  }
        * */
      public function actionTagupdate()
      {
        $payload = $this->GetPayload();
        $jira_conf = Yii::$app->restconf->confs['confluence'];

        $is_id_valid = isset($payload["id"]);
        $is_id_user_valid = isset($payload["id_user"]);
        $is_nama_valid = isset($payload["nama"]);
        $is_deskripsi_valid = isset($payload["deskripsi"]);

        $test = User::findOne($payload["id_user"]);
        $is_id_user_valid = $is_id_user_valid && is_null($test);

        if(
            $is_id_valid == true &&
            $is_id_user_valid == true &&
            $is_nama_valid == true &&
            $is_deskripsi_valid == true
          )
        {
          $tag = KmsTag::findOne($payload["id"]);

          if( is_null($tag) == false )
          {
            $tag["nama"] = $payload["nama"];
            $tag["deskripsi"] = $payload["deskripsi"];
            $tag["id_user_update"] = $payload["id_user"];
            $tag["time_update"] = date("Y-m-j H:i:s");
            $tag->save();

            return [
              "status" => "ok",
              "pesan" => "Record berhasil diupdate",
              "result" => $tag
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Record tidak ditemukan",
            ];
          }

        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak valid: nama, deskripsi, id_user (integer)",
            "payload" => $payload
          ];
        }
      }

      /*
       *  Menghapus record tag
       *
       *  Method: DELETE
       *  Request type: JSON
       *  Request format:
       *  {
       *    "id": 123,
       *    "id_user": 123
       *  }
       *  Response type: JSON
       *  Response format:
       *  {
       *    "status": "ok",
       *    "pesan": "sadfas",
       *    "result": 
       *    {
       *      record of tag
       *    }
       *  }
        * */
      public function actionTagdelete()
      {
        $payload = $this->GetPayload();
        $jira_conf = Yii::$app->restconf->confs['confluence'];

        $is_id_valid = isset($payload["id"]);
        $is_id_user_valid = isset($payload["id_user"]);

        $test = User::findOne($payload["id_user"]);
        $is_id_user_valid = $is_id_user_valid && is_null($test);

        if(
            $is_id_valid == true &&
            $is_id_user_valid == true
          )
        {
          $tag = KmsTag::findOne($payload["id"]);

          if( is_null($tag) == false )
          {
            $tag["is_delete"] = 1;
            $tag["id_user_delete"] = $payload["id_user"];
            $tag["time_delete"] = date("Y-m-j H:i:s");
            $tag->save();

            return [
              "status" => "ok",
              "pesan" => "Record berhasil didelete",
              "result" => $tag
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Record tidak ditemukan",
            ];
          }

        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak valid: id, id_user (integer)",
            "payload" => $payload
          ];
        }
      }


  // ==========================================================================
  // Tag management
  // ==========================================================================
}
