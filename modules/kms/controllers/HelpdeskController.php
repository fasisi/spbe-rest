<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\base;
use yii\helpers\Json;
use yii\helpers\BaseUrl;
use yii\db\Query;
use yii\db\IntegrityException;

use app\models\HdIssue;
use app\models\HdIssueActivityLog;
use app\models\HdIssueUserAction;
use app\models\HdIssueTag;
use app\models\HdIssueFile;
use app\models\HdIssueSolver;
use app\models\HdIssueDiscussion;
use app\models\HdIssueDiscussionFile;
use app\models\HdIssueComment;
use app\models\HdIssueDiscussionComment;
use app\models\HdKategoriPic;
use app\models\HdKategoriSla;
use app\models\KmsTags;
use app\models\ForumTags;
use app\models\User;
use app\models\KmsKategori;
use app\models\KmsFiles;
use app\models\ForumFiles;
use app\models\ForumThread;
use app\models\Roles;
use app\models\UserRoles;
use app\models\HdIssueDisposisi;

use app\helpers\Notifikasi;

use Carbon\Carbon;

class HelpdeskController extends \yii\rest\Controller
{
  public function behaviors()
  {
    $behaviors = parent::behaviors();
    $behaviors['verbs'] = [
      'class' => \yii\filters\VerbFilter::className(),
      'actions' => [
        'draft'                 => ['POST'],
        'create'                => ['POST'],
        'retrieve'              => ['GET'],
        'update'                => ['PUT'],
        'delete'                => ['DELETE'],
        'list'                  => ['GET'],

        'logsbyfilter'          => ['GET'],
        'itemsbyfilter'         => ['GET'],
        'item, items'           => ['GET'],
        'issueuseraction'      => ['POST'],
        'itemkategori'          => ['PUT'],
        'search'                => ['GET'],
        'status'                => ['PUT'],
        'otheritems'            => ['GET'],
        'othercategories'       => ['GET'],
        'dailystats'            => ['GET'],
        'currentstats'          => ['GET'],
        'comment'               => ['POST', 'GET', 'DELETE', 'PUT'],
        'answer'                => ['POST', 'GET', 'PUT', 'DELETE'],
        'myitems'               => ['GET'],
        'ceksla'                => ['POST'],

        'attachments'           => ['POST'],
        'logsbytags'            => ['GET'],
        'categoriesbytags'      => ['GET'],
        'itemtag'               => ['POST'],
      ]
    ];
    return $behaviors;
  }

  // ==========================================================================
  // private helper functions
  // ==========================================================================


      private function SetupGuzzleClient()
      {
        $jira_conf = Yii::$app->restconf->confs['jira'];
        $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";
        Yii::info("base_url = $base_url");
        $client = new \GuzzleHttp\Client([
          'base_uri' => $base_url
        ]);

        return $client;
      }

      private function SetupGuzzleClientSPBE()
      {
        $base_url = BaseUrl::base(true);
        

        Yii::info("base_url = $base_url");
        $client = new \GuzzleHttp\Client([
          'base_uri' => $base_url
        ]);

        return $client;
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
       * type_log : 1 = status log; 2 = action log
        * */
      private function IssueLog($id_issue, $id_user, $type_log, $log_value)
      {
        $new = new HdIssueActivityLog();
        $new["id_issue"] = $id_issue;
        $new["id_user"] = $id_user;
        $new["type_log"] = $type_log;

        $type_name = "";
        switch($type_log)
        {
          case 1: //status log
            $type_name = "status";
            break;
          case 2: //status log
            $type_name = "action";
            break;
        }

        $new["{$type_name}"] = $log_value;
        $new["time_{$type_name}"] = date("Y=m-j H:i:s");
        $new->save();

        /* HdIssueUserAction::Summarize($id_issue); */
      }

      /* private function ActivityLog($id_artikel, $id_user, $type_action) */
      /* { */
      /*   $log = new KmsArtikelActivityLog(); */
      /*   $log["id_artikel"] = $id_artikel; */
      /*   $log["id_user"] = $id_user; */
      /*   $log["time_action"] = date("Y-m-j H:i:s"); */
      /*   $log["type_action"] = $type_action; */
      /*   $log->save(); */
      /* } */

      private function Conf_GetQuestion($client, $linked_id_issue)
      {
        $jira_conf = Yii::$app->restconf->confs['jira'];
        $res = $client->request(
          'GET',
          "/rest/servicedeskapi/request/$linked_id_issue",
          [
            /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
            /* 'debug' => true, */
            'http_errors' => false,
            'headers' => [
              "Content-Type" => "application/json",
              "accept" => "application/json",
            ],
            'auth' => [
              $jira_conf["user"],
              $jira_conf["password"]
            ],
            'query' => [
              'expand' => 'participant'
            ],
          ]
        );

        return $res;
      }

  // ==========================================================================
  // private helper functions
  // ==========================================================================


  //  Menyimpan issue sebagai draft
  //  
  //  Method: POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "judul": "",
  //    "body": "",
  //    "id_user": 123,
  //    "id_solvers": [123, ...],
  //    "id_kategori": 123,
  //    "id_files": [1,2,3],
  //    "tags": ["", "", ...]
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "ok/not ok",
  //    "pesan": "",
  //    "result": 
  //    {
  //      object of hd_issue
  //    }
  //  }
  public function actionDraft()
  {
    $payload = $this->GetPayload();

    $is_judul_valid = isset($payload["judul"]);
    $is_body_valid = isset($payload["body"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_id_kategori_valid = isset($payload["id_kategori"]);
    $is_tags_valid = isset($payload["tags"]);

    if(
        $is_judul_valid == true &&
        $is_body_valid == true &&
        $is_id_user_valid == true &&
        $is_id_kategori_valid == true &&
        $is_tags_valid == true
      )
    {
      // periksa id_user
      $user = User::findOne($payload["id_user"]);

      if( is_null($user) == false )
      {
        // periksa id_kategori
        $kategori = KmsKategori::findOne($payload["id_kategori"]);

        if( is_null($kategori) == false)
        {
          // masukkan record
          $issue = new HdIssue();
          $issue["judul"] = $payload["judul"];
          $issue["konten"] = $payload["body"];
          $issue["id_kategori"] = $payload["id_kategori"];
          $issue["linked_id_issue"] = 0;
          $issue["linked_id_thread"] = $payload["id_thread"];
          $issue["id_user_create"] = $payload["id_user"];
          $issue["time_create"] = date("Y-m-d H:i:s");
          $issue["status"] = $payload["status"];
          $issue->save();
          $id_issue = $issue->primaryKey;

          $client = $this->SetupGuzzleClient();
          $jira_conf = Yii::$app->restconf->confs['jira'];
          $this->UpdateTags($client, $jira_conf, $issue["id"], $issue["linked_id_issue"], $payload);

          // pasangkan issue dengan solver
            //   $this->UpdateSolver($issue["id"], $payload["solvers"]);

          if( isset($payload["id_files"]) == true )
          {
            if( is_array( $payload["id_files"] ) == true )
            {
              $this->UpdateFiles($id_issue, $payload);
            }
          }

          // pasangkan dengan record thread, jika id_thread != -1
          if($payload["id_thread"] != -1)
          {
            $thread = ForumThread::findOne($payload["id_thread"]);
            $thread["linked_id_tiket"] = $id_issue;
            $thread->save();
          }

          // ambil ulang tags atas thread ini. untuk menjadi response.
          $tags = HdIssueTag::find()
            ->where(
              "id_issue = :id_issue",
              [
                ":id_issue" => $id_issue
              ]
            )
            ->all();

          // ambil ulang files atas thread ini. untuk menjadi response.
          $files = HdIssueFile::find()
            ->where(
              "id_issue = :id_issue",
              [
                ":id_issue" => $id_issue
              ]
            )
            ->all();

        //   $this->IssueLog($issue["id"], $payload["id_user"], 1, -1);

          return [
            "status" => "ok",
            "pesan" => "Record issue berhasil disimpan",
            "result" => 
            [
              "issue" => $issue,
              "tags" => $tags,
              "files" => $files
            ]
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record kategori tidak ditemukan",
            "payload" => $payload
          ];
        }

      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record user tidak ditemukan",
          "payload" => $payload
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Paramete yang dibutuhkan tidak lengkap/valid: judul, body, id_user (integer), id_kategori (integer), tags (array of string",
        "payload" => $payload
      ];
    }
  }


  // Mengirim record issue ke JIRA
  //
  // Method: POST
  // Request type: JSON
  // Request format:
  // {
  //   "id_issue": 123,
  // }
  // response type: JSON
  // Response format:
  // {
  //   "status": "",
  //   "pesan": "",
  //   "result": {}
  // }
  public function actionCreate()
  {
    $payload = $this->GetPayload();

    $is_id_valid = isset($payload["id"]);

    if(
        $is_id_valid == true 
      )
    {
      $jira_conf = Yii::$app->restconf->confs['jira'];
      $client = $this->SetupGuzzleClient();

      $issue = HdIssue::findOne($payload["id"]);

      if( is_null($issue) == false)
      {
        // kirim record ke JIRA
          $request_payload = [
            "serviceDeskId" => "3",
            "requestTypeId" => "45",  // harus konfirmasi dengan BPPT
            "requestFieldValues" => 
            [
              "summary" => $issue["judul"],
              "description" => $issue["konten"]
            ],
            "requestParticipants" => 
            [
              "ujicoba"
            ]
          ];

          $res = $client->request(
            'POST',
            "/rest/servicedeskapi/request",
            [
              /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
              /* 'debug' => true, */
              'http_errors' => false,
              'headers' => [
                "Content-Type" => "application/json",
                "Media-Type" => "application/json",
                "accept" => "application/json",
              ],
              'auth' => [
                $jira_conf["user"],
                $jira_conf["password"]
              ],
              /* 'query' => [ */
              /*   'status' => 'current', */
              /*   'expand' => 'body.view', */
              /* ], */
              'body' => Json::encode($request_payload),
            ]
          );

          $response_payload = $res->getBody();
          $response_payload = Json::decode($response_payload);

          $id_linked_issue = $response_payload["issueId"];
        // kirim record ke JIRA

        // update record issue
           $issue["linked_id_issue"] = $id_linked_issue;
           $issue["status"] = 1;
           $issue->save();

           // put to log
           $this->IssueLog($issue["id"], $payload["id_user"], 1, 1);

        // update record issue

        // memasangkan tiket ini dengan PIC sebagai solvernya
        $this->BindWithPIC($issue["id"], $issue["id_user_create"]);

        // kirim notifikasi kepada PIC kategori
        $this->NotifikasiPIC($issue["id"], $issue["id_user_create"]);

        return [
          "status" => "ok",
          "pesan" => "Record berhasil dikirim ke JIRA",
          "issue" => $issue,
          "jira_record" => $response_payload
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record issue tidak ditemukan",
          "payload" => $payload
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Paramete yang dibutuhkan tidak lengkap/valid: judul, body, id_user (integer), id_kategori (integer), tags (array of string",
        "payload" => $payload
      ];
    }
  }

  /*
   * Mengembalikan daftar PIC berdasarkan id_issue dan id_user (dipembuat tiket).
   *
   * Jika tidak menemukan PIC, function akan mengembalikan FALSE.
    * */
  private function GetPIC($id_issue, $id_user)
  {
    $user = User::findOne($id_user);
    $id_instansi = $user["id_departments"];

    $tiket = HdIssue::findOne($id_issue);
    $id_kategori = $tiket["id_kategori"];

    $id_role = Roles::IdByCodeName("sme");

    $q = new Query();

    $hasil = null;

    do
    {
      // cari id PIC

      $test = 
        $q->select("u.*")
          ->from("user u")
          ->join("join", "user_roles ur", "ur.id_user = u.id")
          ->join("join", "hd_kategori_pic pic", "pic.id_user = u.id")
          ->where(
            [
              "and",
              "pic.id_kategori = :id_kategori",
              "ur.id_roles = :id_role",
              "u.id_departments = :id_instansi"
            ],
            [
              ":id_kategori" => $id_kategori,
              ":id_role" => $id_role,
              ":id_instansi" => $id_instansi,
            ]
          )
          ->all();

      if( is_null($test) == false )
      {

        $hasil =  $test;

        $terus = false;
      }
      else
      {
        // ambil kategori parent
        $kategori = KmsKategori::findOne($id_kategori);

        // cek apakah ada parent
        if( $kategori["id_parent"] != -1 )
        {
          $id_kategori = $kategori["id_parent"];

          $terus = true;
        }
        else
        {
          // tidak ada parent dan tidak menemukan PIC
          //
          // GAGAL

          $hasil = false;

          $terus = false;
        }
      }

    } while( $terus == true );


    return $hasil;
  }

  /*
   * Memasangkan tiket dengan PIC sebagai solver nya
    * */
  private function BindWithPIC($id_issue, $id_user)
  {
    $temp = $this->GetPIC($id_issue, $id_user);

    if( is_array($temp) == true )
    {
      $pic = $temp[0];

      // reset daftar solver atas suatu tiket
      HdIssueSolver::deleteAll(
        [
          "and", 
          "id_issue = :id_issue"
        ], 
        [
          ":id_issue" => $id_issue
        ]
      );

      $new = new HdIssueSolver();
      $new["id_issue"] = $id_issue;
      $new["id_user"] = $pic["id"];
      $new->save();
    }
  }

  /*
   * Membuat notifikasi kepada PIC mengenai tiket baru.
   * PIC dipilih berdasarkan id_kategori issue dan id_instansi si user.
   *
   * Pencarian PIC mengikuti konsep inheritance.
    * */
  private function NotifikasiPIC($id_issue, $id_user)
  {
    $test = $this->GetPIC($id_issue, $id_user);

    if( is_array($test) == true )
    {
      $daftar_email = [];
      foreach($test as $item)
      {
        $temp = [];
        $temp["nama"] = $item["nama"];
        $temp["email"] = $item["email"];

        $daftar_email[] = $temp;
      }

      //kirim notifikasi kepada PIC
      Notifikasi::Kirim(
        [
          "type" => "pic_tiket_baru", 
          "tiket" => $tiket,
          "daftar_email" => $daftar_email
        ]
      );
    }
    else
    {
    }

  }
  /*
   * Membuat notifikasi kepada PIC mengenai tiket complete.
   * PIC dipilih berdasarkan id_kategori issue dan id_instansi si user.
   *
   * Pencarian PIC mengikuti konsep inheritance.
    * */
  private function NotifikasiPICComplete($id_issue, $id_user)
  {
    $test = $this->GetPIC($id_issue, $id_user);

    if( is_array($test) == true )
    {
      $daftar_email = [];
      foreach($test as $item)
      {
        $temp = [];
        $temp["nama"] = $item["nama"];
        $temp["email"] = $item["email"];

        $daftar_email[] = $temp;
      }

      //kirim notifikasi kepada PIC
      Notifikasi::Kirim(
        [
          "type" => "pic_tiket_complete", 
          "tiket" => $tiket,
          "daftar_email" => $daftar_email
        ]
      );
    }
    else
    {
    }

  }


  //  OBSOLETE
  //
  //  Membuat record question
  //
  //  Method : POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "judul": "",
  //    "body": "",
  //    "id_kategori": "",
  //    "tags": [
  //      "tag1", "tag2", ...
  //    ],
  //    "": "",
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "result": 
  //    {
  //      "issue": record_object,
  //      "tags": [ <record_of_tag>, .. ]
  //    }
  //  }
  public function actionCreate_2()
  {
    $judul_valid = true;
    $body_valid = true;
    $kategori_valid = true;
    $tags_valid = true;
    $status_valid = true;

    // pastikan request parameter lengkap
    $payload = $this->GetPayload();

    if( isset($payload["judul"]) == false )
      $judul_valid = false;

    if( isset($payload["body"]) == false )
      $body_valid = false;

    if( isset($payload["id_kategori"]) == false )
      $kategori_valid = false;

    if( isset($payload["tags"]) == false )
      $tags_valid = false;
    
    if( isset($payload["status"]) == false )
      $status_valid = false;

    if( $judul_valid == true && $body_valid == true &&
        $kategori_valid == true && $tags_valid == true &&
        $status_valid == true 
      )
    {
      // panggil POST /rest/api/content

      $tags = [];
      foreach($payload["tags"] as $tag)
      {
        $temp = [];
        $temp["name"] = $tag;

        $tags[] = $temp;
      }

      $request_payload = [
        "serviceDeskId" => "3",
        "requestTypeId" => "45",  // harus konfirmasi dengan BPPT
        "requestFieldValues" => 
        [
          "summary" => $payload["judul"],
          "description" => $payload["body"]
        ],
        "requestParticipants" => 
        [
          "ujicoba"
        ]
      ];

      $jira_conf = Yii::$app->restconf->confs['jira'];
      $client = $this->SetupGuzzleClient();

      $res = null;
      try
      {
        $res = $client->request(
          'POST',
          "/rest/servicedeskapi/request",
          [
            /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
            /* 'debug' => true, */
            'http_errors' => false,
            'headers' => [
              "Content-Type" => "application/json",
              "Media-Type" => "application/json",
              "accept" => "application/json",
            ],
            'auth' => [
              $jira_conf["user"],
              $jira_conf["password"]
            ],
            /* 'query' => [ */
            /*   'status' => 'current', */
            /*   'expand' => 'body.view', */
            /* ], */
            'body' => Json::encode($request_payload),
          ]
        );

        switch( $res->getStatusCode() )
        {
          case 200:
            // ambil id dari result
            $response_payload = $res->getBody();
            $response_payload = Json::decode($response_payload);

            $linked_id_issue = $response_payload['issueId'];


            // bikin record kms_artikel
            $issue = new HdIssue();
            $issue['linked_id_issue'] = $linked_id_issue;
            $issue['time_create'] = date("Y-m-j H:i:s");
            $issue['id_user_create'] = $payload['id_user'];
            $issue['id_kategori'] = $payload['id_kategori'];
            $issue['status'] = $payload["status"];
            $issue->save();
            $id_issue = $issue->primaryKey;


            // menyimpan informasi tags
            $tags = array();
            foreach( $payload["tags"] as $tag )
            {
              // cek apakah tag sudah ada di dalam tabel
              $test = KmsTags::find()
                ->where(
                  "nama = :nama",
                  [
                    ":nama" => $tag
                  ]
                )
                ->one();

              // jika belum ada, insert new record
              $id_tag = 0;
              if( is_null($test) == false )
              {
                $id_tag = $test["id"];
              }
              else
              {
                $new = new KmsTags();
                $new["nama"] = $tag;
                $new["status"] = 1;
                $new["id_user_create"] = 123;
                $new["time_create"] = date("Y-m-j H:i:s");
                $new->save();

                $id_tag = $new->primaryKey;
              }

              // relate id_artikel dengan id_tag
              $new = new HdIssueTag();
              $new["id_issue"] = $id_issue;
              $new["id_tag"] = $id_tag;
              $new->save();

              $temp = [];
              $temp["prefix"] = "global";
              $temp["name"] = $tag["nama"];
              $tags[] = $temp;
            } // loop tags

            // ambil ulang tags atas issue ini. untuk menjadi response.
            $tags = HdIssueTag::find()
              ->where(
                "id_issue = :id_issue",
                [
                  ":id_issue" => $id_issue
                ]
              )
              ->all();

            //$this->ActivityLog($id_artikel, 123, 1);
            $this->IssueLog($payload["id_issue"], $payload["id_user"], 1, $payload["status"]);

            // kembalikan response
            return 
            [
              'status' => 'ok',
              'pesan' => 'Record issue telah dibikin',
              'result' => 
              [
                "artikel" => $issue,
                "tags" => $tags,
                "category_path" => KmsKategori::CategoryPath($issue["id_kategori"]),
              ]
            ];
            break;

          default:
            // kembalikan response
            return [
              'status' => 'not ok',
              'pesan' => 'REST API request failed: ' . $res->getBody(),
              'result' => $issue
            ];
            break;
        }
      }
      catch(\GuzzleHttp\Exception\BadResponseException $e)
      {
        // kembalikan response
        return [
          'status' => 'not ok',
          'pesan' => 'REST API request failed: ' . $e->getMessage(),
        ];
      }


    }
    else
    {
      return [
        'status' => 'not ok',
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, konten, id_kategori, tags',
      ];
    }

  }

  //  Menghapus (soft delete) suatu issue
  //  Hanya dilakukan pada database SPBE
  //
  //  Method: DELETE
  //  Request type: JSON
  //  Request format:
  //  {
  //    "id": 123
  //    "id_user_actor": 123
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "": "",
  //  }
  public function actionDelete()
  {
    $payload = $this->GetPayload();

    $is_id_valid = isset($payload["id"]);
    $is_id_user_actor_valid = isset($payload["id_user_actor"]);

    $test = HdIssue::findOne($payload["id"]);
    if( is_null($test) == true )
    {
      return[
        "status" => "not ok",
        "pesan" => "Record issue tidak ditemukan",
      ];
    }

    $test = User::findOne($payload["id_user_actor"]);
    if( is_null($test) == true )
    {
      return[
        "status" => "not ok",
        "pesan" => "Record user tidak ditemukan",
      ];
    }


    if( $is_id_valid == true && $is_id_user_actor_valid == true )
    {
      $issue = HdIssue::findOne($payload["id"]);
      $issue["is_delete"] = 1;
      $issue["time_delete"] = date("Y-m-d H:i:s");
      $issue["id_user_delete"] = $payload["id_user_actor"];
      $issue->save();

      return [
        "status" => "ok",
        "pesan" => "Record berhasil dihapus",
        "result" => $issue
      ];
    }
    else
    {
      return [
        "status" => "ok",
        "pesan" => "Record berhasil dihapus",
        "result" => $issue
      ];
    }
  }

  public function actionRetrieve()
  {
      return $this->render('retrieve');
  }

  //  Mengupdate record issue
  //
  //  Method : POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "id": 123,
  //    "id_user": 123,
  //    "judul": "",
  //    "body": "",
  //    "status": 123,
  //    "id_kategori": "",
  //    "solvers": [123, ...],
  //    "id_files": [],
  //    "tags": [
  //      "tag1", "tag2", ...
  //    ],
  //    "": "",
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "result": 
  //    {
  //      "artikel": record_object,
  //      "tags": [ <record_of_tag>, .. ]
  //    }
  //  }
  public function actionUpdate()
  {
    $id_valid = true;
    $id_id_user_valid = true;
    $judul_valid = true;
    $body_valid = true;
    $kategori_valid = true;
    $tags_valid = true;

    // pastikan request parameter lengkap
    $payload = $this->GetPayload();

    if( isset($payload["id"]) == false )
      $id_valid = false;

    if( isset($payload["id_user"]) == false )
      $id_id_user_valid = false;

    if( isset($payload["judul"]) == false )
      $judul_valid = false;

    if( isset($payload["body"]) == false )
      $body_valid = false;

    if( isset($payload["id_kategori"]) == false )
      $kategori_valid = false;

    if( isset($payload["tags"]) == false )
      $tags_valid = false;

    if( 
        $id_valid == true && 
        $id_id_user_valid == true && 
        $judul_valid == true && 
        $body_valid == true &&
        $kategori_valid == true && 
        $tags_valid == true 
      )
    {

      $issue = HdIssue::findOne($payload["id"]);

      if( $issue["status"] == -1 ) // masih draft
      {
        // update record hd_issue

        if( is_null($issue) == false )
        {
          $issue["judul"] = $payload["judul"];
          $issue["konten"] = $payload["body"];
          $issue["status"] = $payload["status"];
          $issue['time_update'] = date("Y-m-j H:i:s");
          $issue['id_user_update'] = $payload["id_user"];
          $issue->save();


          // pasangkan issue dengan solver
          $this->UpdateSolver($payload["id"], $payload["solvers"]);

          /* foreach($payload["solvers"] as $solver) */
          /* { */
          /*   $test = User::findOne($solver); */

          /*   if( is_null($test) == false ) */
          /*   { */
          /*     $new = new HdIssueSolver(); */
          /*     $new["id_issue"] = $issue["id"]; */
          /*     $new["id_user"] = $solver; */
          /*     $new->save(); */
          /*   } */
          /*   else */
          /*   { */
          /*   } */
          /* } */
          
          // mengupdate informasi tags

              // refresh tag/label
                  $this->UpdateTags($client, $jira_conf, $issue["id"], $issue["linked_id_issue"], $payload);
              // refresh tag/label
                   
          // mengupdate informasi tags


          // update informasi files
            if( isset($payload["id_files"]) == true )
            {
              if( is_array( $payload["id_files"] ) == true )
              {
                $this->UpdateFiles($payload["id"], $payload);
              }
            }
          // update informasi files


          // kembalikan response

              /* return */ 
              /* [ */
              /*   'status' => 'ok', */
              /*   'pesan' => 'Record issue telah diupdate', */
              /*   'result' => */ 
              /*   [ */
              /*     "helpdesk_issue" => $issue, */
              /*     "tags" => $tags */
              /*   ] */
              /* ]; */

              $tags = HdIssueTag::findAll("id_issue = {$issue["id"]}");
              $files = HdIssueFile::findAll(["id_issue" => $payload["id"]]);

              return [
                "status" => "ok",
                "pesan" => "Record berhasil diupdate",
                "result" =>
                [
                  "issue" => $issue,
                  "files" => $files,
                  "tags" => $tags,
                ]
              ];

          // kembalikan response

        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record issue tidak bisa di-update",
            "issue" => $issue,
            "payload" => $payload,
          ];
        }
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Hanya bisa mengupdate record issue ber-status draft",
          "issue" => $issue,
          "payload" => $payload,
        ];
      }
    }
    else
    {
      return [
        'status' => 'not ok',
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, konten, id_kategori, tsgs',
        "payload" => $payload
      ];
    }
      return $this->render('update');
  }

  /* OBSOLETE 
   *
   * Menghapus tags dari suatu issue.
   *
   * Tetapi berdasarkan dokumentasi Confluence-Question, tidak ada API untuk
   * menghapus tags (topic) dari suatu issue.
   * Apakah tags akan diterapkan secara eksklusif di dalam SPBE?
   *
    * */
  private function DeleteTags($client, $jira_conf, $linked_id_issue)
  {
    $res = $client->request(
      'GET',
      "/rest/api/content/{$linked_id_issue}/label",
      [
        /* 'sink' => Yii::$app->basePath . "/guzzledump1.txt", */
        /* 'debug' => true, */
        'http_errors' => false,
        'headers' => [
          "Content-Type" => "application/json",
          "accept" => "application/json",
        ],
        'auth' => [
          $jira_conf["user"],
          $jira_conf["password"]
        ],
      ]
    );
    $res = Json::decode($res->getBody());

    foreach($res["results"] as $object)
    {
      $res2 = $client->request(
        'DELETE',
        "/rest/api/content/{$linked_id_issue}/label/{$object["name"]}",
        [
          'sink' => Yii::$app->basePath . "/guzzledump2.txt",
          /* 'debug' => true, */
          'http_errors' => false,
          'headers' => [
            "Content-Type" => "application/json",
            "accept" => "application/json",
          ],
          'auth' => [
            $jira_conf["user"],
            $jira_conf["password"]
          ],
        ]
      );
    }
  }

  private function UpdateTags($client, $jira_conf, $id_issue, $linked_id_issue, $payload)
  {

    //hapus label pada spbe
        HdIssueTag::deleteAll("id_issue = {$id_issue}");
    //hapus label pada spbe


    $tags = array();
    foreach( $payload["tags"] as $tag )
    {
      // cek apakah tag sudah ada di dalam tabel
      $test = KmsTags::find()
        ->where(
          "nama = :nama",
          [
            ":nama" => $tag
          ]
        )
        ->one();

      // jika belum ada, insert new record
      $id_tag = 0;
      if( is_null($test) == false )
      {
        $id_tag = $test["id"];
      }
      else
      {
        $new = new KmsTags();
        $new["nama"] = $tag;
        $new["status"] = 0;
        $new["id_user_create"] = $payload["id_user"];
        $new["time_create"] = date("Y-m-j H:i:s");
        $new->save();

        $id_tag = $new->primaryKey;
      }

      // relate id_artikel dengan id_tag
      $new = new HdIssueTag();
      $new["id_issue"] = $id_issue;
      $new["id_tag"] = $id_tag;
      $new->save();

    } // loop tags

  }


  private function UpdateSolver($id_issue, $solvers)
  {
    // set is_disposisi = 0
    $issue = HdIssue::findOne($id_issue);
    $issue["is_disposisi"] = 0;
    $issue->save();

    HdIssueSolver::deleteAll(["id_issue" => $id_issue]);

    foreach($solvers as $solver)
    {
      $test = User::findOne($solver);

      if( is_null($test) == false )
      {
        $new = new HdIssueSolver();
        $new["id_issue"] = $id_issue;
        $new["id_user"] = $solver;
        $new->save();
      }
      else
      {
      }
    }
  }

  /*
   * Memasangkan tiket dengan solvernya. Atau membatalkan pasangan tiket dengan
   * solvernya.
   *
   * Method: POST
   * Request type: JSON
   * Request format:
   * {
   *   id_issue: 123,
   *   id_user: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     record: { object_of_record }
   *   }
   * }
   *
   * Method: DELETE
   * Request type: JSON
   * Request format: 
   * {
   *   id_issue: 123,
   *   id_user: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     record: { object_of_record }
   *   }
   * }
   * */
  public function actionSolver()
  {
    switch(true)
    {
      case Yii::$app->request->isPost == true:

        $payload = $this->GetPayload();

        $is_id_issue_valid = isset( $payload["id_issue"] );
        $is_id_user_valid = isset( $payload["id_user"] );

        if( $is_id_issue_valid == true && $is_id_user_valid )
        {
          if( UserRoles::CekRole($payload["id_user"], "sme") == true )
          {
            // pasangkan user sebagai solver issue ini
            $this->UpdateSolver($payload["id_issue"], [$payload["id_user"]]);

            return [
              "status" => "ok",
              "pesan" => "SME telah dipasangkan dengan tiket",
            ];
          }
          else
          {
            // gagal memasangkan

            return [
              "status" => "not ok",
              "pesan" => "User yang diberikan bukan SME",
              "payload" => $payload
            ];
          }
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang diberikan tidak valid",
            "payload" => $payload
          ];
        }

        break;

      case Yii::$app->request->isDelete == true:
        $payload = $this->GetPayload();

        $is_id_issue_valid = isset( $payload["id_issue"] );
        $is_id_user_valid = isset( $payload["id_user"] );

        if( $is_id_issue_valid == true && $is_id_user_valid )
        {
          $test = HdIssueSolver::find()
            ->where(
              ["id_issue = :id_issue", "id_user = :id_user"],
              [":id_issue" => $payload["id_issue"], ":id_user" => $payload["id_user"]]
            )
            ->one();

          if( is_null($test) == false )
          {
            $this->UpdateSolver($payload["id_issue"], []);

            return [
              "status" => "ok",
              "pesan" => "Pemasangan SME dan tiket telah dibatalkan",
            ];

          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "User yang diberikan bukan SME atas tiket tersebut",
              "payload" => $payload
            ];
          }
        }

        break;

    }

  }

  private function UpdateFiles($id_issue, $payload)
  {
    HdIssueFile::deleteAll("id_issue = :id_issue", [":id_issue" => $id_issue]);

    foreach( $payload["id_files"] as $item_file )
    {
      // cek record file-nya di kms_files
      $test = ForumFiles::findOne($item_file);

      if( is_null($test) == false ) 
      {
        $path = Yii::$app->basePath .
          DIRECTORY_SEPARATOR . "web" .
          DIRECTORY_SEPARATOR . "files" .
          DIRECTORY_SEPARATOR;

        // cek filenya
        if( is_file($path . $test["nama"]) == true )
        {
          // pasangkan file dengan issue
          $new = new HdIssueFile();
          $new["id_issue"] = $id_issue;
          $new["id_file"] = $item_file;
          $new["id_user_create"] = $payload["id_user"];
          $new["time_create"] = date("Y-m-d H:i:s");
          $new->save();

        }
        else
        {
          // jika file sudah tidak ada, maka hapus recordnya
          $test->delete();
        }
      }
    }
  }

  /*
   *  Mengambil hd_issue_activity_log berdasarkan filter yang dapat disetup secara dinamis.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "object_type": "i/u",
   *    "filter":
   *    {
   *      "waktu_awal"    : "y-m-j H:i:s",
   *      "waktu_akhir"   : "y-m-j H:i:s",
   *      "status"        : [1, 2, ...],
   *      "id_kategori"   : 123,
   *      "id_artikel"    : 123,
   *    }
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan" : "",
   *    "result" :
   *    {
   *      "count": 123,
   *      "records": []
   *    }
   *  }
   * */
  public function actionLogsbyfilter()
  {
    // pastikan request parameter lengkap
    $payload = $this->GetPayload();

    $tanggal_awal = Carbon::createFromFormat("Y-m-d", $payload["filter"]["tanggal_awal"]);
    $tanggal_akhir = Carbon::createFromFormat("Y-m-d", $payload["filter"]["tanggal_akhir"]);

    $q = new Query();

    switch( true )
    {
      case $payload["object_type"] == "u" :
        $q->select("u.id");
        $q->from("user u");
        $q->join("join", "hd_issue_activity_log l", "l.id_user = u.id");
        break;

      case $payload["object_type"] == "i" :
        $q->select("i.id");
        $q->from("hd_issue i");
        $q->join("join", "hd_issue_activity_log l", "l.id_issue = i.id");
        break;

      default :
        return [
          "status" => "not ok",
          "pesan" => "Parameter tidak valid. object_type diisi dengan 'i' atau 'u'.",
        ];
        break;
    }

    Yii::info("filter = " . print_r($payload["filter"], true));

    $where[] = "and";

    // tanggal awal dan tanggal akhir
        $value_awal = date("Y-m-d 00:00:00", $tanggal_awal->timestamp);
        $value_akhir = date("Y-m-d 23:59:59", $tanggal_akhir->timestamp);
        $where[] = [
          "or", 
          ["and", "l.time_action >= '$value_awal'", "l.time_action <= '$value_akhir'"],
          ["and", "l.time_status >= '$value_awal'", "l.time_status <= '$value_akhir'"],
        ];
    // tanggal awal dan tanggal akhir


    foreach( $payload["filter"] as $key => $value )
    {
      switch(true)
      {
        case $key == "status":
          $temp = [];
          foreach( $value as $type_status )
          {
            $temp[] = $type_status;
          }

          if( count($temp) > 0 )
          {
            $where[] = ["in", "l.status", $temp];
          }
        break;

        case $key == "id_kategori":
          $q->join("join", "hd_issue i2", "i2.id = l.id_issue");
          /* $q->join("join", "kms_kategori k", "a2.id_kategori = k.id"); */

          $temp = [];
          foreach( $value as $id_kategori )
          {
            $temp[] = $id_kategori;
          }

          if( count($temp) > 0 )
          {
            $where[] = ["in", "i2.id_kategori", $temp];
          }
        break;

        case $key == "id_issue":
          $where[] = "l.id_issue = " . $value;
        break;
      }// switch filter key
    } //loop keys in filter

    $q->where($where);

    if( $payload["object_type"] == 'i' )
    {
      $q->distinct()
        ->groupBy("i.id");
    }
    else
    {
      $q->distinct()
        ->groupBy("u.id");
    }

    //execute the query
    $records = $q->all();

    $hasil = [];
    $client = $this->SetupGuzzleClient();
    foreach($records as $record)
    {
      if( $payload["object_type"] == 'i' )
      {
        $issue = HdIssue::findOne($record["id"]);
        $user = User::findOne($issue["id_user_create"]);

        $response = $this->Conf_GetQuestion($client, $issue["linked_id_issue"]);
        $response_payload = $response->getBody();
        $response_payload = Json::decode($response_payload);

        $temp = [];
        $temp["hd_issue"] = $issue;
        $temp["solver"] = HdIssueSolver::GetSolver($issue["id"]);
        $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
        $temp["data_user"]["user_create"] = $user;
        $temp["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
        $temp["servicedesk"]["id"] = $response_payload["issueId"];
        $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
        $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];


        // ServiceDesk tidak mengenal konsep action (view, like, dislike, comment).
        //
        /* // filter by action */
        /* // ================ */
        /*     // berapa banyak action yang diterima suatu artikel dalam rentang waktu tertentu? */

        /*     //ambil data view */
        /*     $type_action = -1; */
        /*     $temp["view"] = HdIssue::ActionReceivedInRange($issue["id"], $type_action, $tanggal_awal, $tanggal_akhir); */
            
        /*     //ambil data like */
        /*     $type_action = 1; */
        /*     $temp["like"] = HdIssue::ActionReceivedInRange($issue["id"], $type_action, $tanggal_awal, $tanggal_akhir); */

        /*     //ambil data dislike */
        /*     $type_action = 2; */
        /*     $temp["dislike"] = HdIssue::ActionReceivedInRange($issue["id"], $type_action, $tanggal_awal, $tanggal_akhir); */
        /* // ================ */
        /* // filter by action */

        // filter by status
        // ================
            // apakah suatu issue mengalami status tertentu dalam rentang waktu?
            // status yang dikenal: 
            // -1=draft, 1=new, 2=un-assign, 3=progress, 4=closed/resolved
            // rujuk pada database untuk mendapatkan value.

            //ambil data draft
            $type_status = -1;
            $temp["draft"] = HdIssue::StatusInRange($issue["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data new
            $type_status = 1;
            $temp["new"] = HdIssue::StatusInRange($issue["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data un-assign
            $type_status = 2;
            $temp["unassigned"] = HdIssue::StatusInRange($issue["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data progress
            $type_status = 3;
            $temp["progress"] = HdIssue::StatusInRange($issue["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data solved
            $type_status = 4;
            $temp["solved"] = HdIssue::StatusInRange($issue["id"], $type_status, $tanggal_awal, $tanggal_akhir);

        // ================
        // filter by status

        $is_valid = true;
        foreach($payload["filter"]["status"] as $status)
        {
          switch(true)
          {
          case $status == -1:  //draft
            $is_valid = $is_valid && $temp["draft"] > 0;
            break;

          case $status == 1:  // new
            $is_valid = $is_valid && $temp["new"] > 0;
            break;

          case $status == 2:  // un-assign
            $is_valid = $is_valid && $temp["unassigned"] > 0;
            break;

          case $status == 3:  // progress
            $is_valid = $is_valid && $temp["progress"] > 0;
            break;

          case $status == 4:  // solved
            $is_valid = $is_valid && $temp["solved"] > 0;
            break;
          }
        } // loop check filter status

        if($is_valid == true)
          $hasil[] = $temp;

      }
      else
      {
        $user = User::findOne($record["id"]);

        $temp = [];
        $temp["user"] = $user;

        $hasil[] = $temp;
      }
    }

    return [
      "status" => "ok",
      "pesan" => "Record berhasil diambil",
      "result" => 
      [
        "count" => count($hasil),
        "records" => $hasil
      ]
    ];
  }

  /*
   *  Mengambil hd_issue atau user berdasarkan filter yang dapat disetup secara dinamis.
   *
   *  Method: GET
   *
   *        ===================================================================
   *        Mengambil daftar artikel dari suatu kategori dan mendapatkan 
   *        action tertentu.
   *
   *        Request type: JSON
   *        Request format #01:
   *        {
   *          "object_type": "a",
   *          "filter":
   *          {
   *            "actions":            // pernyataan filter action untuk memilih record artikel
   *            [
   *              {
   *                "id_action": 1,
   *                "min": 0,         // rentang jumlah action yang diterima oleh suatu artikel
   *                "max": 123
   *              },
   *              {
   *                "id_action": 2,
   *                "min": 0,
   *                "max": 123
   *              },
   *            ],
   *            "id_kategori"   : [1, 2, 3, ...],
   *            "page_no": 1,
   *            "items_per_page": 123
   *          }
   *        }
   *        Response type: JSON
   *        Response format #01:
   *        {
   *          "status": "ok",
   *          "pesan" : "",
   *          "result" :
   *          {
   *            "count": 123,
   *            "page_no": 1,
   *            "items_per_page": 123,
   *            "records": 
   *            [
   *              {
   *                "hd_issue": { object_of_record_artikel },
   *                "user_create": { object_of_user },
   *                "confluence": { object_of_confluence},
   *                "category_path": []
   *              }
   *            ]
   *          }
   *        }
   *
   *        ===================================================================
   *        Mengambil daftar user yang melakukan action tertentu terhadap
   *        issue-issue dari suatu kategori
   *
   *        Request format #02:
   *        {
   *          "object_type": "u",
   *          "filter":
   *          {
   *            "actions"        : [1, 2, ...],
   *            "id_kategori"    : [1, 2, 3, ...],
   *            "page_no"        : 1,
   *            "items_per_page" : 123
   *          }
   *        }
   *        Response type: JSON
   *        Response format #01:
   *        {
   *          "status": "ok",
   *          "pesan" : "",
   *          "result" :
   *          {
   *            "count": 123,
   *            "page_no": 1,
   *            "items_per_page": 123,
   *            "records": 
   *            [
   *              {
   *                "user": { object_of_record_artikel },
   *              }
   *            ]
   *          }
   *        }
   *        ===================================================================
   *
   *
   *
   *        ===================================================================
   *        Mengambil daftar issue dari suatu kategori dan mengalami STATUS
   *        tertentu
   *
   *        Request type: JSON
   *        Request format #01:
   *        {
   *          "object_type": "a",
   *          "filter":
   *          {
   *            "status"         : [1, 2, ...],
   *            "id_kategori"    : [1, 2, 3, ...],
   *            "page_no"        : 1,
   *            "items_per_page" : 123
   *          }
   *        }
   *        Response type: JSON
   *        Response format #01:
   *        {
   *          "status": "ok",
   *          "pesan" : "",
   *          "result" :
   *          {
   *            "count": 123,
   *            "page_no": 1,
   *            "items_per_page": 123,
   *            "records": 
   *            [
   *              {
   *                "hd_issue": { object_of_record_artikel },
   *                "user_create": { object_of_user },
   *                "confluence": { object_of_confluence},
   *                "category_path": []
   *              }
   *            ]
   *          }
   *        }
   *
   *
   * */

  public function actionItemsbyfilter()
  {
    // pastikan request parameter lengkap
    $payload = $this->GetPayload();


    $is_object_type_valid = isset($payload["object_type"]);
    $is_filter_valid = isset($payload["filter"]);
    $is_action_status_valid = false;
    $is_id_kategori_valid = false;
    $is_page_no_valid = false;
    $is_items_per_page_valid = false;

    $is_filter_valid = isset($payload["filter"]);

    $is_action_status_valid = 
      (
        $payload["object_type"] == 'a' &&
        ( 
          isset($payload["filter"]["action"]) ||
          isset($payload["filter"]["status"]) 
        )
      ) ||
      (
        $payload["object_type"] == 'u' &&
        isset($payload["filter"]["action"]) 
      );
    $is_id_kategori_valid = isset($payload["filter"]["id_kategori"]);
    $is_id_kategori_valid = $is_id_kategori_valid && is_array($payload["filter"]["id_kategori"]);
    $is_page_no_valid = is_numeric($payload["filter"]["page_no"]);
    $is_items_per_page_valid = is_numeric($payload["filter"]["items_per_page"]);


    if(
        $is_object_type_valid == true &&
        $is_filter_valid == true &&
        $is_action_status_valid == true &&
        $is_page_no_valid == true &&
        $is_items_per_page_valid == true
      )
    {
      $q = new Query();

      // setup select and where statement
          if( $payload["object_type"] == 'a' )
          {
            $q->select("a.id")
              ->from("hd_issue t")
              ->join("JOIN", "hd_issue_activity_log l", "l.id_issue = t.id");

            if( isset($payload["filter"]["action"]) )
            {
              $q->andWhere("l.type_log = 1");
              $q->andWhere(["in", "l.status", $payload["filter"]["action"]]);
            }
            else
            {
              $q->andWhere("l.type_log = 2");
              $q->andWhere(["in", "t.status", $payload["filter"]["status"]]);
            }

            $q->andWhere(["in", "t.id_kategori", $payload["filter"]["id_kategori"]]);

            $q->distinct();
            $q->groupBy("t.id");
          }
          else
          {
            $q->select("u.id")
              ->from("user u")
              ->join("JOIN", "hd_issue_activity_log l", "l.id_user = u.id")
              ->join("JOIN", "hd_issue t", "l.id_issue = t.id");

            if( isset($payload["filter"]["action"]) )
            {
              $q->andWhere("l.type_log = 1");
              $q->andWhere(["in", "l.status", $payload["filter"]["action"]]);
            }
            else
            {
              $q->andWhere("l.type_log = 2");
              $q->andWhere(["in", "t.status", $payload["filter"]["status"]]);
            }

            $q->andWhere(["in", "t.id_kategori", $payload["filter"]["id_kategori"]]);

            $q->distinct();
            $q->groupBy("u.id");
          }
      // setup select statement

      $q->offset
        ( 
          ($payload["filter"]["page_no"] - 1) * $payload["filter"]["items_per_page"] 
        )
        ->limit( $payload["filter"]["items_per_page"] );

      $records = $q->all();
      $hasil = [];
      $client = $this->SetupGuzzleClient();
      foreach($records as $record)
      {
        if( $payload["object_type"] == "a" )
        {
          $issue = HdIssue::findOne($record["id"]);
          $category_path = KmsKategori::CategoryPath($record["id_kategori"]);
          $tags = HdIssueTag::GetTags($issue["id"]);
          $user_create = User::findOne($issue["id_user_create"]);
          $response = $this->Conf_GetQuestion($client, $issue["linked_id_issue"]);
          $response_payload = $response->getBody();
          $response_payload = Json::decode($response_payload);

          $temp = [];
          $temp["record"]["hd_issue"] = $issue;
          $temp["record"]["user_create"] = $user_create;
          $temp["record"]["category_path"] = $category_path;
          $temp["record"]["tags"] = $tags;
          $temp["confluence"]["status"] = "ok";
          $temp["confluence"]["linked_id_issue"] = $response_payload["id"];
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = $response_payload["body"]["content"];
          $hasil[] = $temp;
        }
        else
        {
          $user = User::findOne($record["id"]);

          $temp = [];
          $temp["user"] = $user;
          $hasil[] = $temp;
        }

      }

      return [
        "status" => "ok",
        "pesan" => "Query berhasil di-eksekusi",
        "result" => $hasil,
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak valid",
      ];
    }

  }

  /*
   *  Mengambil daftar issue berdasarkan status, page_no, items_per_page.
   *  Hasil yang dikembalikan diurutkan desc berdasarkan waktu publish.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "status": 123,
   *    "list_mode": "r/s", (r=reporter, s=solver)
   *    "id_user": 123,
   *    "page_no": 123,
   *    "items_per_page": 123
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/nor ok",
   *    "pesan": "",
   *    "result":
   *    {
   *      "page_no": 123,
   *      "items_per_page": 123,
   *      "count": 123,
   *      "records":
   *      [
   *        {
   *          "hd_issue":
   *          {
   *            <object dari record issue>
   *          },
   *          "confluence":
   *          {
   *            <object dari record Confluence>
   *          }
   *        },
   *        ...
   *      ]
   *    }
   *  }
    * */
  public function actionItems()
  {
    $payload = $this->GetPayload();

    //  cek parameter
    // $is_kategori_valid = isset($payload["id_kategori"]);
    
    $is_status_valid = isset($payload["status"]);
    $is_list_mode_valid = isset($payload["list_mode"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_page_no_valid = isset($payload["page_no"]);
    $is_items_per_page_valid = isset($payload["items_per_page"]);

    $is_page_no_valid = $is_page_no_valid && is_numeric($payload["page_no"]);
    $is_items_per_page_valid = $is_items_per_page_valid && is_numeric($payload["items_per_page"]);

    if(
        $is_status_valid == true &&
        $is_list_mode_valid == true &&
        $is_id_user_valid == true &&
        $is_page_no_valid == true &&
        $is_items_per_page_valid == true
      )
    {
      if( $payload["list_mode"] == "r" )
      {

        //  lakukan query dari tabel hd_issue
        $test = HdIssue::find()
          ->where(
            [
              "and",
              "is_delete = 0",
              "status = {$payload['status']}",   // 0=draft; 1=new; 2=un-assigned; 3=progress; 4=solved
              "id_user_create = :id_user"
            ],
            [
              ":id_user" => $payload["id_user"],
            ]
          )
          ->orderBy("time_create desc")
          ->all();
        $total_rows = count($test);

        $list_issue = HdIssue::find()
          ->where(
            [
              "and",
              "is_delete = 0",
              "status = {$payload['status']}",   // 0=draft; 1=new; 2=un-assigned; 3=progress; 4=solved
              "id_user_create = :id_user"
            ],
            [
              ":id_user" => $payload["id_user"],
            ]
          )
          ->orderBy("time_create desc")
          ->offset( $payload["items_per_page"] * ($payload["page_no"] - 1) )
          ->limit( $payload["items_per_page"] )
          ->all();

        //  lakukan query dari Confluence
        $jira_conf = Yii::$app->restconf->confs['jira'];
        $client = $this->SetupGuzzleClient();

        $hasil = [];
        foreach($list_issue as $issue)
        {
          $user = User::findOne($issue["id_user_create"]);
          $jawaban = HdIssueDiscussion::findAll([
            "id_issue" => $issue["id"],
            "is_delete" => 0
          ]);

          $res = $client->request(
            'GET',
            "/rest/servicedeskapi/request/{$issue["linked_id_issue"]}",
            [
              /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
              /* 'debug' => true, */
              'http_errors' => false,
              'headers' => [
                "Content-Type" => "application/json",
                "accept" => "application/json",
              ],
              'auth' => [
                $jira_conf["user"],
                $jira_conf["password"]
              ],
              /* 'query' => [ */
              /*   'spaceKey' => 'PS', */
              /*   'expand' => 'history,body.view' */
              /* ], */
            ]
          );

          //  kembalikan hasilnya
          switch( $res->getStatusCode() )
          {
            case 200:
              // ambil id dari result
              $response_payload = $res->getBody();
              $response_payload = Json::decode($response_payload);

              $temp = [];
              $temp["hd_issue"] = $issue;
              $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
              $temp["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
              $temp["user_create"] = $user;
              $temp["user_actor_status"] = HdIssueUserAction::GetUserAction($payload["id_issue"], $payload["id_user_actor"]);
              $temp["jawaban"]["count"] = count($jawaban);
              $temp["servicedesk"]["status"] = "ok";
              $temp["servicedesk"]["linked_id_issue"] = $response_payload["issueId"];
              $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
              $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
              $temp['data_user']['user_create'] = $user->nama;
              $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
              $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

              $hasil[] = $temp;
              break;

            default:
              // kembalikan response
              $temp = [];
              $temp["hd_issue"] = $issue;
              $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
              $temp["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
              // $hasil["user_create"] = $user;
              $temp["servicedesk"]["status"] = "not ok";
              $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
              $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
              $temp['data_user']['user_create'] = $user->nama;
              $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
              $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

              $hasil[] = $temp;
              break;
          }
        }

        return [
          "status" => "ok",
          "pesan" => "Query finished",
          "result" =>
          [
            "total_rows" => $total_rows,
            "page_no" => $payload["page_no"],
            "items_per_page" => $payload["items_per_page"],
            "count" => count($list_issue),
            "records" => $hasil
          ]
        ];
      }
      else
      {
        // list_mode = 's'


        //  lakukan query dari tabel hd_issue
        $q = new Query();
        $test = $q->select("i.*")
          ->from("hd_issue i")
          ->join("JOIN", "hd_issue_solver s", "s.id_issue = i.id")
          ->where(
            [
              "and",
              "s.id_user = :id_solver",
              "i.is_delete = 0",
              "i.status = :status"
            ],
            [
              ":id_solver" => $payload["id_user"],
              ":status" => $payload["status"]
            ]
          )
          ->all();
        $total_rows = count($test);

        $list_issue = 
          $q->orderBy("i.time_create desc")
            ->offset( $payload["items_per_page"] * ($payload["page_no"] - 1) )
            ->limit( $payload["items_per_page"] )
            ->all();

        //  lakukan query dari Confluence
        $jira_conf = Yii::$app->restconf->confs['jira'];
        $client = $this->SetupGuzzleClient();

        $hasil = [];
        foreach($list_issue as $issue)
        {
          $user = User::findOne($issue["id_user_create"]);
          $jawaban = HdIssueDiscussion::findAll([
            "id_issue" => $issue["id"],
            "is_delete" => 0
          ]);

          $res = $client->request(
            'GET',
            "/rest/servicedeskapi/request/{$issue["linked_id_issue"]}",
            [
              /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
              /* 'debug' => true, */
              'http_errors' => false,
              'headers' => [
                "Content-Type" => "application/json",
                "accept" => "application/json",
              ],
              'auth' => [
                $jira_conf["user"],
                $jira_conf["password"]
              ],
              /* 'query' => [ */
              /*   'spaceKey' => 'PS', */
              /*   'expand' => 'history,body.view' */
              /* ], */
            ]
          );

          //  kembalikan hasilnya
          switch( $res->getStatusCode() )
          {
            case 200:
              // ambil id dari result
              $response_payload = $res->getBody();
              $response_payload = Json::decode($response_payload);

              $temp = [];
              $temp["hd_issue"] = $issue;
              $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
              $temp["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
              $temp["user_create"] = $user;
              $temp["user_actor_status"] = HdIssueUserAction::GetUserAction($payload["id_issue"], $payload["id_user_actor"]);
              $temp["jawaban"]["count"] = count($jawaban);
              $temp["servicedesk"]["status"] = "ok";
              $temp["servicedesk"]["linked_id_issue"] = $response_payload["issueId"];
              $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
              $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
              $temp['data_user']['user_create'] = $user->nama;
              $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
              $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

              $hasil[] = $temp;
              break;

            default:
              // kembalikan response
              $temp = [];
              $temp["hd_issue"] = $issue;
              $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
              $temp["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
              // $hasil["user_create"] = $user;
              $temp["servicedesk"]["status"] = "not ok";
              $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
              $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
              $temp['data_user']['user_create'] = $user->nama;
              $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
              $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

              $hasil[] = $temp;
              break;
          }
        }

        return [
          "status" => "ok",
          "pesan" => "Query finished",
          "result" =>
          [
            "total_rows" => $total_rows,
            "page_no" => $payload["page_no"],
            "items_per_page" => $payload["items_per_page"],
            "count" => count($list_issue),
            "records" => $hasil
          ]
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak valid.",
      ];
    }

  }

  /*
   *  Mengambil record issue berdasarkan id_issue
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_issue": 123,
   *    "id_user_actor": 123
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/nor ok",
   *    "pesan": "",
   *    "result":
   *    {
   *        "jawaban":
   *        {
   *          count: 123,
   *          records:
   *          [
   *            { object_jawaban }, ...
   *          ]
   *        },
   *      "record":
   *      {
   *        "hd_issue":
   *        {
   *          <object dari record hd_issue>
   *        },
   *        "jawaban":
   *        [
   *          { object_jawaban }, ...
   *        ]
   *        "confluence":
   *        {
   *          <object dari record Confluence>
   *        }
   *      }
   *    }
   *  }
    * */
  public function actionItem()
  {
    $payload = $this->GetPayload();

    //  cek parameter
    $is_id_issue_valid = isset($payload["id_issue"]);

    if(
        $is_id_issue_valid == true
      )
    {
      //  lakukan query dari tabel kms_artikel
      $issue = HdIssue::findOne($payload["id_issue"]);
      $user_creator = User::findOne($issue["id_user_create"]);

      /* $temp_list_komentar = HdIssueComment::find() */
      /*   ->where("id_issue = :id", [":id" => $payload["id_issue"]]) */
      /*   ->orderBy("time_create desc") */
      /*   ->all(); */

      /* $list_komentar = []; */
      /* foreach($temp_list_komentar as $komentar_item) */
      /* { */
      /*   $use = User::findOne($komentar_item["id_user_create"]); */

      /*   $temp = []; */
      /*   $temp["record"] = $komentar_item; */
      /*   $temp["user_create"] = $user; */

      /*   $list_komentar[] = $temp; */
      /* } */


      $client = $this->SetupGuzzleClient();
      $jira_conf = Yii::$app->restconf->confs['jira'];
      $res = $client->request(
        'GET',
        "/rest/servicedeskapi/request/{$issue["linked_id_issue"]}/comment",
        [
          /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
          /* 'debug' => true, */
          'http_errors' => false,
          'headers' => [
            "Content-Type" => "application/json",
            "accept" => "application/json",
          ],
          'auth' => [
            $jira_conf["user"],
            $jira_conf["password"]
          ],
          'query' => [
            'expand' => 'participant',
            'start' => 0,
            'limit' => 100,
          ],
        ]
      );
      $response_payload = $res->getBody();

      try
      {
        $response_payload = Json::decode($response_payload);
        $list_linked_jawaban = $response_payload["values"];

        $jawaban = [];
        foreach($list_linked_jawaban as $item_linked_jawaban)
        {
          $record_jawaban = HdIssueDiscussion::find()
            ->where(
              "linked_id_discussion = :id", 
              [":id" => $item_linked_jawaban["id"]]
            )
            ->one();

          $user = User::findOne($record_jawaban["id_user_create"]);

          $temp_files = HdIssueDiscussionFile::findAll(["id_discussion" => $record_jawaban["id"]]);
          $files = [];
          foreach( $temp_files as $a_file )
          {
            $file = KmsFiles::findOne( $a_file["id_file"] );

            if( is_null($file) == false )
            {
              $files[] = [
                "KmsFiles" => $file,
                "link" => BaseUrl::base(true) . "/files/" . $file["nama"]
              ];
            }
          }

          $jawaban[] = [
            "jawaban" => $record_jawaban,
            "user_create" => $user,
            "jira_comment" => $item_linked_jawaban,
            "files" => $files,
          ];
        }

        $temp_files = HdIssueFile::findAll(["id_issue" => $payload["id_issue"]]);
        $files = [];
        foreach( $temp_files as $a_file )
        {
          $file = KmsFiles::findOne( $a_file["id_file"] );

          if( is_null($file) == false )
          {
            $files[] = [
              "KmsFiles" => $file,
              "link" => BaseUrl::base(true) . "/files/" . $file["nama"]
            ];
          }
        }

        //  lakukan query dari Confluence
        $hasil = [];
        $res = $client->request(
          'GET',
          "/rest/servicedeskapi/request/{$issue["linked_id_issue"]}",
          [
            /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
            /* 'debug' => true, */
            'http_errors' => false,
            'headers' => [
              "Content-Type" => "application/json",
              "accept" => "application/json",
            ],
            'auth' => [
              $jira_conf["user"],
              $jira_conf["password"]
            ],
            'query' => [
              /* 'spaceKey' => 'PS', */
              'expand' => 'participants',
              'start' => 0,
              'limit' => 100,
            ],
          ]
        );

        //  kembalikan hasilnya
        switch( $res->getStatusCode() )
        {
        case 200:
          // ambil id dari result
          $response_payload = $res->getBody();
          $response_payload = Json::decode($response_payload);

          $hasil = [];
          $hasil["record"]["hd_issue"] = $issue;
          $hasil["record"]["issue_comments"] = $list_komentar;
          $hasil["record"]["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
          $hasil["record"]["user_create"] = $user_creator;
          $hasil["record"]["user_solver"] = HdIssueSolver::GetSolver($issue["id"]);
          /* $hasil["record"]["user_actor_status"] = HdIssueUserAction::GetUserAction($payload["id_issue"], $payload["id_user_actor"]); */
          $hasil["record"]["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
          $hasil["record"]["servicedesk"]["status"] = "ok";
          $hasil["record"]["servicedesk"]["linked_id_issue"] = $response_payload["issueId"];
          $hasil["record"]["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
          $hasil["record"]["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
          $hasil["jawaban"]["count"] = count($jawaban);
          $hasil["jawaban"]["records"] = $jawaban;
          $hasil["files"] = $files;
          $hasil["is_pic"] = HdKategoriPic::IsPic($payload["id_user_actor"], $issue["id_kategori"]);

        //   $this->IssueLog($issue["id"], $payload["id_user_actor"], 2, -1);
          break;

        default:
          // kembalikan response
          $hasil = [];
          $hasil["record"]["hd_issue"] = $issue;
          $hasil["record"]["issue_comments"] = $list_komentar;
          $hasil["record"]["user_create"] = $user;
          $hasil["record"]["user_solver"] = HdIssueSolver::GetSolver($issue["id"]);
          $hasil["record"]["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
          $hasil["record"]["confluence"]["status"] = "not ok";
          /* $hasil["record"]["servicedesk"]["linked_id_issue"] = $response_payload["issueId"]; */
          /* $hasil["record"]["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"]; */
          /* $hasil["record"]["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"]; */
          /* $hasil["files"]["count"] = count($files); */
          $hasil["files"] = $files;
          break;
        }

        return [
          "status" => "ok",
          "pesan" => "Query finished",
          "result" => $hasil
        ];
      }
      catch(yii\base\InvalidArgumentException $e)
      {
        return [
          "status" => "not ok",
          "pesan" => "Gagal mengambil record JIRA",
          "result" => "$response_payload",
          "payload" => $payload
        ];
      }


    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak valid.",
        "payload" => $payload
      ];
    }

  }

  /*  OBSOLETE
   *
   *  Menyimpan action antara user dan issue. Apakah si user menyatakan like,
   *  dislike terhadap suatu issue. Informasi disimpan pada tabel hd_issue_user_action
   *
   *  WARNING!!
   *  TAPI: SERVICE DESK tidak mengenal action
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_issue": 123,
   *    "id_user": 123,
   *    "action": 0/1/2,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "",
   *    "result":
   *    {
   *      <object record hd_issue_user_action >
   *    }
   *  }
    * */
  public function actionIssueuseraction()
  {
    $payload = $this->GetPayload();

    // cek apakah parameter lengkap
    $is_id_issue_valid = isset($payload["id_issue"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_action_valid = isset($payload["action"]);

    if(
        $is_id_issue_valid == true &&
        $is_id_user_valid == true &&
        $is_action_valid == true
      )
    {
      // memastikan id_issue dan id_user valid
      $test = HdIssue::findOne($payload["id_issue"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Issue's record not found",
        ];
      }

      $test = User::findOne($payload["id_user"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "User's record not found",
        ];
      }

      if( $payload["action"] != -1 && $payload["action"] != 1 && $payload["action"] != 2 )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Status value is not valid.",
        ];
      }

      // cek record kms_artikel_user_status. insert/update record
      $test = HdIssueUserAction::find()
        ->where(
          [
            "and",
            "id_issue = :idissue",
            "id_user = :iduser"
          ],
          [
            ":idissue" => $payload["id_issue"],
            ":iduser" => $payload["id_user"],
          ]
        )
        ->one();

      if( is_null($test) == true )
      {
        $test = new HdIssueUserAction();
      }

      if( $test["action"] != $payload["action"] )
      {
        //  Aktifitas akan direkam jika mengakibatkan perubahan status pada
        //  issue.

        $test["id_issue"] = $payload["id_issue"];
        $test["id_user"] = $payload["id_user"];
        $test["action"] = $payload["action"];
        $test->save();

        // tulis log
        $this->IssueLog($payload["id_issue"], $payload["id_user"], 2, $payload["action"]);

        // kembalikan response
        return [
          "status" => "ok",
          "pesan" => "Action saved. Log saved.",
          "result" => $test
        ];
      }
      else
      {
        // kembalikan response
        return [
          "status" => "ok",
          "pesan" => "Repeated action. Status not saved.",
          "result" => $test
        ];
      }

    }
    else
    {
      // kembalikan response
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak lengkap: id_issue, id_user, action",
      ];
    }


  }

  /*
   *  Mengganti id_kategori atas suatu issue. Kemudian penyimpan jejak perubahan
   *  ke dalam tabel hd_issue_log
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_issue": 123,
   *    "id_kategori": 123,
   *    "id_user": 123
   *  }
   *  Response type: JSON,
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": 
   *    {
   *      <object record issue>
   *    }
   *  }
    * */
  public function actionItemkategori()
  {
    $payload = $this->GetPayload();

    // cek apakah parameter lengkap
    $is_id_issue_valid = isset($payload["id_issue"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_id_kategori_valid = isset($payload["id_kategori"]);

    if(
        $is_id_issue_valid == true &&
        $is_id_user_valid == true &&
        $is_id_kategori_valid == true
      )
    {
      // memastikan id_issue, id_kategori dan id_user valid
      $test = HdIssue::findOne($payload["id_issue"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Issue's record not found",
        ];
      }

      $test = KmsKategori::findOne($payload["id_kategori"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Kategori's record not found",
        ];
      }

      $test = User::findOne($payload["id_user"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "User's record not found",
        ];
      }

      // update kms_issue
      $issue = HdIssue::findOne($payload["id_issue"]);
      $issue["id_kategori"] = $payload["id_kategori"];
      $issue->save();

      //  simpan history pada tabel hd_issue_activity_log
      /* $log = new KmsArtikelActivityLog(); */
      /* $log["id_artikel"] = $payload["id_artikel"]; */
      /* $log["id_user"] = $payload["id_user"]; */
      /* $log["type_action"] = $payload["status"]; */
      /* $log["time_action"] = date("Y-m-j H:i:s"); */
      /* $log->save(); */

      return [
        "status" => "ok",
        "pesan" => "Kategori issue sudah disimpan",
        "result" => $issue,
        "category_path" => KmsKategori::CategoryPath($issue["id_kategori"])
      ];
    }
    else
    {
      // kembalikan response
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak lengkap: id_issue, id_kategori, id_user",
      ];
    }



  }

  /*
   *  TODO!!
   *
   *  API JIRA hanya melakukan pencarian pada summary. Hal ini dianggap kurang
   *  berfaedah. Maka pencarian dilakuka pada database SPBE menggnakan fasilitas
   *  FULLTEXT SEARCH
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format :
   *  {
   *    "search_keyword": "abc abc abc",
   *    "id_kategori": [1, 2, ...],
   *    "page_no": 123,
   *    "items_per_page": 123
   *  }
   *  Response type: JSON,
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": 
   *    [
   *      <object_record_artikel>
   *    ]
   *  }
   * */

  // WARNING!!
  // Confluence-Question support fitur pencarian hanya pada summary (= judul)

  public function actionSearch()
  {
    $payload = $this->GetPayload();

    $is_keyword_valid = isset($payload["search_keyword"]);
    $is_start_valid = is_numeric($payload["page_no"]);
    $is_limit_valid = is_numeric($payload["items_per_page"]);
    $is_id_kategori_valid = isset($payload["id_kategori"]);
    $is_id_kategori_valid = $is_id_kategori_valid && is_array($payload["id_kategori"]);

    if(
        $is_keyword_valid == true &&
        $is_id_kategori_valid == true
      )
    {
      /*
       * Lakukan pencarian terhadap database spbe. Karena pencarian pada JIRA
       * tidak dapat memisahkan antara request milik si A atau pun milik si B.
       *
       * */

      // pecah keyword berdasarkan kata
      $daftar_keyword = explode(" ", $payload["search_keyword"]);
      $keywords = "";
      foreach($daftar_keyword as $keyword)
      {
        if($keywords != "")
        {
          $keywords .= " OR ";
        }

        $keywords .= "$keyword*";
      }

      $jira_conf = Yii::$app->restconf->confs['jira'];
      $client = $this->SetupGuzzleClient();

      $res = $client->request(
        'GET',
        "/rest/servicedeskapi/request",
        [
          /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
          /* 'debug' => true, */
          'http_errors' => false,
          'headers' => [
            "Content-Type" => "application/json",
            "accept" => "application/json",
          ],
          'auth' => [
            $jira_conf["user"],
            $jira_conf["password"]
          ],
          'query' => [
            'start' => $payload["items_per_page"] * ($payload["page_no"] - 1),
            'limit' => $payload["items_per_page"],
            'searchTerm' => "$keywords",
            'serviceDeskId' => 3,  // perlu konfirmasi dari BPPT
          ],
        ]
      );

      switch( $res->getStatusCode() )
      {
        case 200:
          $response_payload = $res->getBody();
          $response_payload = Json::decode($response_payload);

          $hasil = array();
          foreach($response_payload["values"] as $item)
          {
            $temp = array();
            $temp["servicedesk"]["status"] = "ok";
            $temp["servicedesk"]["linked_id_issue"] = $response_payload["issueId"];
            $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
            $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];

            $issue = HdIssue::find()
              ->where(
                [
                  "linked_id_issue" => $item["id"]
                ]
              )
              ->one();
            $user = User::findOne($issue["id_user_create"]);
            $temp["hd_issue"] = $issue;
            $temp["user_create"] = $user->nama;
            $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);

            $hasil[] = $temp;
          }

          return [
            "status" => "ok",
            "pesan" => "Search berhasil",
            "result" => 
            [
              "total_rows" => $response_payload["values"],
              "page_no" => $payload["page_no"],
              "Items_per_page" => $payload["items_per_page"],
              "records" => $hasil
            ]
          ];
          break;

        default:
          // kembalikan response
          return [
            'status' => 'not ok',
            'pesan' => 'REST API request failed: ' . $res->getBody(),
            'payload' => $payload,
            'result' => $issue,
            'category_path' => KmsKategori::CategoryPath($issue["id_kategori"])
          ];
          break;
      }

    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak valid. search_keyword (string), id_kategori (array)",
        "payload" => $payload
      ];
    }

  }

  /*
   *  Mengubah status suatu issue.
   *  Status artikel:
   *  -1 = draft
   *  1 = open
   *  2 = in progress
   *  3 = waiting for customer
   *  4 = close
   *  5 = complete
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_issue": [123, 124, ...],
   *    "status": 123,
   *    "id_user": 123
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result":
   *    {
   *      "berhasil": [],
   *      "gagal": []
   *    }
   *  }
    * */
  public function actionStatus()
  {
    $payload = $this->GetPayload();

    $is_id_issue_valid = isset($payload["id_issue"]);
    $is_id_issue_valid = $is_id_issue_valid && is_array($payload["id_issue"]);
    $is_status_valid = isset($payload["status"]);
    $is_status_valid = $is_status_valid && is_numeric($payload["status"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_id_user_valid = $is_id_user_valid && is_numeric($payload["id_user"]);

    if(
        $is_id_issue_valid == true &&
        $is_status_valid == true &&
        $is_id_user_valid == true
      )
    {
      $daftar_sukses = [];
      $daftar_gagal = [];
      foreach($payload["id_issue"] as $id_issue)
      {
        if( is_numeric($id_issue) )
        {
          if( is_numeric($payload["id_user"]) )
          {
            //cek validitas id_user
            $test = User::findOne($payload["id_user"]);

            if( is_null($test) == false )
            {
              $issue = HdIssue::findOne($id_issue);
              if( is_null($issue) == false )
              {
                $issue["status"] = $payload["status"];
                $issue->save();

                $daftar_sukses[] = $issue;

                // tulis log
                $this->IssueLog($id_issue, $payload["id_user"], 1, $payload["status"]);
              }
              else
              {
                $daftar_gagal[] = $id_issue;
              }
            }
            else
            {
              return [
                "status" => "not ok",
                "pesan" => "Tidak dapat menemukan record user dengan id = {$payload["id_user"]}"
              ];
            }
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Parameter id_user harus dalam bentuk numeric"
            ];
          }
        }
        else
        {
          $daftar_gagal[] = $id_issue;
        }
      }


      return [
        "status" => "ok",
        "pesan" => "Status tersimpan",
        "result" => [
          "berhasil" => $daftar_sukses,
          "gagal" => $daftar_gagal
        ]
      ];

    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak ada: id_issue (array), status",
      ];
    }
  }

  /*
   *  OBSOLETE
   *  Helpdesk sifatnya private maka tidak ada keperluan untuk mencari issue
   *  lain.
   *
   *  Mengambil daftar issue berdasarkan kesamaan tags yang berasal dari
   *  kategori selain id_kategori yang dikirim.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_kategori": [123, 124, ...]
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "": "",
   *    "": "",
   *    "records" :
   *    [
   *      {
   *        "hd_issue":
   *        {
   *          <object dari record hd_issue>
   *        },
   *        "confluence":
   *        {
   *          <object dari record Confluence>
   *        }
   *      },
   *      ...
   *    ]
   *  }
    * */
  public function actionOtheritems()
  {
    $payload = $this->GetPayload();

    $is_id_kategori_valid = isset($payload["id_kategori"]);
    $is_id_kategori_valid = $is_id_kategori_valid && is_array($payload["id_kategori"]);


    if( $is_id_kategori_valid == true )
    {
      // ambil daftar tags yang berasal dari id_kategori
      $q  = new Query();
      $temp_daftar_tag = 
        $q->select("t.*")
          ->from("kms_tags t")
          ->join("JOIN", "hd_issue_tag atag", "atag.id_tag = t.id")
          ->join("JOIN", "hd_issue f", "f.id = atag.id_issue")
          ->where(["in", "f.id_kategori", $payload["id_kategori"]])
          ->distinct()
          ->all();

      $daftar_tag = [];
      foreach($temp_daftar_tag as $item)
      {
        $daftar_tag[] = $item["id"];
      }

      // mengambil daftar issue terkait
      $q = new Query();
      $daftar_issue = 
        $q->select("t.*")
          ->from("hd_issue t")
          ->join("JOIN", "hd_issue_tag atag", "atag.id_issue = t.id")
          ->where([
            "and",
            ["in", "atag.id_tag", $daftar_tag],
            "t.is_delete = 0",
            "t.status = 1",
            ["not", ["in", "t.id_kategori", $payload["id_kategori"]]]
          ])
          ->distinct()
          ->orderBy("time_create desc")
          ->limit(10)
          ->all();

      // ambil informasi dari confluence
      $hasil = [];
      foreach($daftar_issue as $record)
      {
        $user = User::findOne($record["id_user_create"]);

        //  lakukan query dari Confluence
        $jira_conf = Yii::$app->restconf->confs['jira'];
        $client = $this->SetupGuzzleClient();

        $res = $client->request(
          'GET',
          "/rest/questions/1.0/question/{$record["linked_id_issue"]}",
          [
            /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
            /* 'debug' => true, */
            'http_errors' => false,
            'headers' => [
              "Content-Type" => "application/json",
              "Media-Type" => "application/json",
              "accept" => "application/json",
            ],
            'auth' => [
              $jira_conf["user"],
              $jira_conf["password"]
            ],
            'query' => [
              'spaceKey' => 'PS',
              'expand' => 'history,body.view'
            ],
          ]
        );

        $response_payload = $res->getBody();

        $temp = [];

        try
        {
          $response_payload = Json::decode($response_payload);

          $temp["hd_issue"] = $record;
          $temp["user_create"] = $user;
          $temp["category_path"] = KmsKategori::CategoryPath($record["id_kategori"]);
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = $response_payload["body"]["content"];
        }
        catch(yii\base\InvalidArgumentException $e)
        {
          $temp["hd_issue"] = $record;
          $temp["user_create"] = $user;
          $temp["category_path"] = KmsKategori::CategoryPath($record["id_kategori"]);
          $temp["confluence"]["status"] = "not ok";
          $temp["confluence"]["response"] = $response_payload;
        }


        $hasil[] = $temp;
      }

      return [
        "status" => "ok",
        "pesan" => "Berhasil mengambil records",
        "records" => $hasil,
      ];

    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak lengkap: id_kategori (array)",
      ];
        

    }
  }


  /*
   *  OBSOLETE
   *
   *  Mengambil daftar kategori berdasarkan kesamaan tags yang berasal dari
   *  kategori selain id_kategori yang dikirim.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_kategori": [123, 124, ...]
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "": "",
   *    "": "",
   *    "records" :
   *    [
   *      {
   *        <object_of_record_kategori>
   *      },
   *      ...
   *    ]
   *  }
    * */
  public function actionOthercategories()
  {
    $payload = $this->GetPayload();

    $is_id_kategori_valid = isset($payload["id_kategori"]);
    $is_id_kategori_valid = $is_id_kategori_valid && is_array($payload["id_kategori"]);


    if( $is_id_kategori_valid == true )
    {
      // ambil daftar tags yang berasal dari id_kategori
      $q  = new Query();
      $temp_daftar_tag = 
        $q->select("t.*")
          ->from("kms_tags t")
          ->join("JOIN", "hd_issue_tag atag", "atag.id_tag = t.id")
          ->join("JOIN", "hd_issue f", "f.id = atag.id_issue")
          ->where(["in", "f.id_kategori", $payload["id_kategori"]])
          ->distinct()
          ->all();

      $daftar_tag = [];
      foreach($temp_daftar_tag as $item)
      {
        $daftar_tag[] = $item["id"];
      }

      // mengambil daftar kategori terkait
      $q = new Query();
      $hasil = 
        $q->select("k.*")
          ->from("kms_kategori k")
          ->join("JOIN", "hd_issue f", "f.id_kategori = k.id")
          ->join("JOIN", "hd_issue_tag atag", "atag.id_issue = f.id")
          ->where([
            "and",
            ["in", "atag.id_tag", $daftar_tag],
            ["not", ["in", "f.id_kategori", $payload["id_kategori"]]]
          ])
          ->distinct()
          ->orderBy("time_create desc")
          ->limit(10)
          ->all();

      return [
        "status" => "ok",
        "pesan" => "Berhasil mengambil records",
        "records" => $hasil,
      ];

    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak lengkap: id_kategori (array)",
      ];
        

    }
  }

  /*
   *  Mengembalikan jumlah kejadian harian dalam rentang waktu.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "tanggal_awal": "yyyy-mm-dd",
   *    "tanggal_akhir": "yyyy-mm-dd",
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": 
   *    [
   *      {
   *        "tanggal": "yyyy-mm-dd",
   *        "draft": 123,
   *        "new": 123,
   *        "un-assigned": 123,
   *        "progress": 123,
   *        "solved": 123,
   *      }, ...
   *    ]
   *  }
   * */
  public function actionDailystats()
  {
    $payload = $this->GetPayload();

    $is_tanggal_awal_valid = isset($payload["tanggal_awal"]);
    $is_tanggal_akhir_valid = isset($payload["tanggal_akhir"]);
    $tanggal_awal = Carbon::createFromFormat("Y-m-d", $payload["tanggal_awal"]);
    $tanggal_akhir = Carbon::createFromFormat("Y-m-d", $payload["tanggal_akhir"]);

    if(
        $is_tanggal_awal_valid == true &&
        $tanggal_awal != false &&
        $is_tanggal_akhir_valid == true &&
        $tanggal_akhir != false
      )
    {
      // ambil data kejadian dari tanggal_awal hingga tanggal_akhir

      $temp_date = Carbon::createFromTimestamp($tanggal_awal->timestamp);
      $hasil = [];
      while($temp_date <= $tanggal_akhir)
      {
        // ambil jumlah kejadian
        $q = new Query();
        $hasil_status = $q->select("log.status, count(log.id) as jumlah")
          ->from("hd_issue_activity_log log")
          ->andWhere("time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $temp_date->timestamp)])
          ->andWhere("time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $temp_date->timestamp)])
          ->distinct()
          ->groupBy("log.status")
          ->orderBy("log.status asc")
          ->all();

        /* $q = new Query(); */
        /* $hasil_action = $q->select("log.action, count(log.id) as jumlah") */
        /*   ->from("hd_issue_activity_log log") */
        /*   ->andWhere("time_action >= :awal", [":awal" => date("Y-m-j 00:00:00", $temp_date->timestamp)]) */
        /*   ->andWhere("time_action <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $temp_date->timestamp)]) */
        /*   ->distinct() */
        /*   ->orderBy("log.action") */
        /*   ->orderBy("log.action asc") */
        /*   ->all(); */

        $temp = [];
        $temp["tanggal"] = date("Y-m-d", $temp_date->timestamp);

        foreach($hasil_status as $record)
        {
          switch( $record["status"] )
          {
            case -1: //draft
              $temp["data"]["draft"] = $record["jumlah"];
              break;

            case 1: // open
              $temp["data"]["open"] = $record["jumlah"];
              break;

            case 2: //in progress
              $temp["data"]["inprogress"] = $record["jumlah"];
              break;

            case 3: //waiting for customer
              $temp["data"]["waiting"] = $record["jumlah"];
              break;

            case 4: // close
              $temp["data"]["close"] = $record["jumlah"];
              break;

            case 5: // complete
              $temp["data"]["complete"] = $record["jumlah"];
              break;
          }
        }

        /* foreach($hasil_action as $record) */
        /* { */
        /*   switch( $record["action"] ) */
        /*   { */
        /*     case -1: //view */
        /*       $temp["data"]["view"] = $record["jumlah"]; */
        /*       break; */

        /*     case 0: //neutral */
        /*       $temp["data"]["neutral"] = $record["jumlah"]; */
        /*       break; */

        /*     case 1: //like */
        /*       $temp["data"]["like"] = $record["jumlah"]; */
        /*       break; */

        /*     case 2: //dislike */
        /*       $temp["data"]["dislike"] = $record["jumlah"]; */
        /*       break; */
        /*   } */
        /* } */

        $hasil[] = $temp;

        $temp_date->addDay();
      }

      return [
        "status" => "ok",
        "result" => $hasil,
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak valid: tanggal_awal (yyyy-mm-dd), tanggal_akhir (yyyy-mm-dd)",
        "payload" => $payload
      ];
    }

  }

  /*
   *  Mengembalikan total jumlah kejadian saat ini.
   *
   *  Method: GET
   *  Request type: 
   *  Request format:
   *  {
   *    "tanggal_awal": "yyyy-mm-dd",
   *    "tanggal_akhir": "yyyy-mm-dd"
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/not ok",
   *    "pesan": "",
   *    "result": 
   *    [
   *      {
   *        "kategori"; "nama_kategori", 
   *        "data": 
   *        {
   *          "draft" :
   *          {
   *            "issue": 123,
   *            "user": 123
   *          }, ...
   *          "new" :
   *          {
   *            "issue": 123,
   *            "user": 123
   *          }, ...
   *          "unassigned" :
   *          {
   *            "issue": 123,
   *            "user": 123
   *          }, ...
   *        }
   *      }, ...
   *    ]
   *  }
   * */
  public function actionCurrentstats()
  {
    $payload = $this->GetPayload();

    $is_tanggal_awal_valid = isset($payload["tanggal_awal"]);
    $is_tanggal_akhir_valid = isset($payload["tanggal_akhir"]);
    $tanggal_awal = Carbon::createFromFormat("Y-m-d", $payload["tanggal_awal"]);
    $tanggal_akhir = Carbon::createFromFormat("Y-m-d", $payload["tanggal_akhir"]);

    // ambil data kejadian dari tiap kategori
    $daftar_kategori = KmsKategori::GetList();


    foreach($daftar_kategori as $kategori)
    {
      // ambil jumlah issue per kejadian (new, publish, .., freeze)
      $q = new Query();
      $total_issue = 
        $q->select("i.id")
          ->from("hd_issue i")
          ->join("JOIN", "hd_issue_activity_log l", "l.id_issue = i.id")
          ->where(
            [
              "and",
              "i.id_kategori = :id_kategori",
              [
                "or",
                [
                  "and",
                  "l.time_status >= :awal",
                  "l.time_status <= :akhir"
                ],
                [
                  "and",
                  "l.time_action >= :awal",
                  "l.time_action <= :akhir"
                ]
              ]
            ]
          )
          ->params(
            [
              ":id_kategori" => $kategori["id"],
              ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp),
              ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)
            ]
          )
          /* ->andWhere("a.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]]) */
          /* ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)]) */
          /* ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)]) */
          ->orderBy("l.status asc")
          ->groupBy("i.id")
          ->all();
      $total_issue = count($total_issue);

      $q = new Query();
      $issue_status = 
        $q->select("l.status, i.id")
          ->from("hd_issue i")
          ->join("JOIN", "hd_issue_activity_log l", "l.id_issue = i.id")
          ->andWhere("i.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("l.type_log = 1")
          ->andWhere("l.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("l.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("l.status asc")
          ->groupBy("l.status, i.id")
          ->all();

      // ambil jumlah issue per action (like/dislike)
      /* $q = new Query(); */
      /* $issue_action = */ 
      /*   $q->select("log.action, t.id") */
      /*     ->from("hd_issue t") */
      /*     ->join("JOIN", "hd_issue_activity_log log", "log.id_issue = t.id") */
      /*     ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]]) */
      /*     ->andWhere("log.type_log = 2") */
      /*     ->andWhere("log.time_action >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)]) */
      /*     ->andWhere("log.time_action <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)]) */
      /*     ->distinct() */
      /*     ->orderBy("log.action asc") */
      /*     ->groupBy("log.action, t.id") */
      /*     ->all(); */

      // ambil jumlah user per kejadian (new, publish, .., freeze)
      /* $q = new Query(); */
      /* $total_user = */ 
      /*   $q->select("u.id") */
      /*     ->from("user u") */
      /*     ->join("JOIN", "hd_issue_activity_log log", "log.id_user = u.id") */
      /*     ->join("JOIN", "hd_issue t", "log.id_artikel = t.id") */
      /*     ->where( */
      /*       [ */
      /*         "and", */
      /*         "t.id_kategori = :id_kategori", */
      /*         "log.type_log = 1", */
      /*         [ */
      /*           "or", */
      /*           [ */
      /*             "and", */
      /*             "log.time_status >= :awal", */
      /*             "log.time_status <= :akhir" */
      /*           ], */
      /*           [ */
      /*             "and", */
      /*             "log.time_action >= :awal", */
      /*             "log.time_action <= :akhir" */
      /*           ] */
      /*         ] */
      /*       ], */
      /*       [ */
      /*         ":id_kategori" => $kategori["id"], */
      /*         ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp), */
      /*         ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp) */
      /*       ] */
      /*     ) */
      /*     /1* ->andWhere("a.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]]) *1/ */
      /*     /1* ->andWhere("log.type_log = 1") *1/ */
      /*     /1* ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)]) *1/ */
      /*     /1* ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)]) *1/ */
      /*     /1* ->orderBy("log.status asc") *1/ */
      /*     /1* ->groupBy("u.id") *1/ */
      /*     ->distinct() */
      /*     ->all(); */
      /* $total_user = count($total_user); */

      // ambil jumlah user yang melakukan status kepada issue
      $q = new Query();
      $user_status = 
        $q->select("log.status, u.id")
          ->from("user u")
          ->join("JOIN", "hd_issue_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "hd_issue i", "log.id_issue = i.id")
          ->andWhere("i.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status, u.id")
          ->all();

      /* // ambil jumlah user per action (like/dislike) */
      /* $q = new Query(); */
      /* $user_action = */ 
      /*   $q->select("log.action, u.id") */
      /*     ->from("user u") */
      /*     ->join("JOIN", "hd_issue_activity_log log", "log.id_user = u.id") */
      /*     ->join("JOIN", "hd_issue t", "log.id_artikel = t.id") */
      /*     ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]]) */
      /*     ->andWhere("log.type_log = 2") */
      /*     ->andWhere("log.time_action >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)]) */
      /*     ->andWhere("log.time_action <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)]) */
      /*     ->distinct() */
      /*     ->orderBy("log.action asc") */
      /*     ->groupBy("log.action, u.id") */
      /*     ->all(); */

      $temp = [];
      $indent = "";
      for($i = 1; $i < $kategori["level"]; $i++)
      {
        $indent .= "&nbsp;&nbsp;";
      }
      $index_name = $indent . $kategori["nama"];
      $category_path = KmsKategori::CategoryPath($kategori["id"]);

      $temp["total"]["issue"] = $total_issue;
      /* $temp["total"]["user"] = $total_user; */

      $temp["draft"]["issue"] = 0;
      $temp["new"]["issue"] = 0;
      $temp["assigned"]["issue"] = 0;
      $temp["progress"]["issue"] = 0;
      $temp["solved"]["issue"] = 0;
      foreach($issue_status as $record)
      {
        switch( $record["status"] )
        {
          case -1: //draft
            $temp["draft"]["issue"]++;
            break;

          case 1: //new
            $temp["new"]["issue"]++;
            break;

          case 2: //un-assigned
            $temp["assigned"]["issue"]++;
            break;

          case 3: //progress
            $temp["progress"]["issue"]++;
            break;

          case 4: //solved
            $temp["solved"]["issue"]++;
            break;
        }
      }

      /* $temp["neutral"]["issue"] = 0; */
      /* $temp["like"]["issue"] = 0; */
      /* $temp["dislike"]["issue"] = 0; */
      /* $temp["view"]["issue"] = 0; */
      /* foreach($issue_action as $record) */
      /* { */
      /*   switch( $record["action"] ) */
      /*   { */
      /*     case 0: //neutral */
      /*       $temp["neutral"]["issue"]++; */
      /*       break; */

      /*     case 1: //like */
      /*       $temp["like"]["issue"]++; */
      /*       break; */

      /*     case 2: //dislike */
      /*       $temp["dislike"]["issue"]++; */
      /*       break; */

      /*     case -1: //view */
      /*       $temp["view"]["issue"]++; */
      /*       break; */
      /*   } */
      /* } */

      foreach($user_status as $record)
      {
        switch( $record["status"] )
        {
          case -1: //new
            $temp["new"]["user"] = $record["jumlah"];
            break;

          case 1: //publish
            $temp["publish"]["user"] = $record["jumlah"];
            break;

          case 2: //unpublish
            $temp["unpublish"]["user"] = $record["jumlah"];
            break;

          case 3: //reject
            $temp["reject"]["user"] = $record["jumlah"];
            break;

          case 4: //freeze
            $temp["freeze"]["user"] = $record["jumlah"];
            break;
        }
      }

      /* $temp["like"]["user"] = 0; */
      /* $temp["neutral"]["user"] = 0; */
      /* $temp["dislike"]["user"] = 0; */
      /* $temp["view"]["user"] = 0; */
      /* foreach($user_action as $record) */
      /* { */
      /*   switch( $record["action"] ) */
      /*   { */
      /*     case 0: //neutral */
      /*       $temp["neutral"]["user"]++; */
      /*       break; */

      /*     case 1: //like */
      /*       $temp["like"]["user"]++; */
      /*       break; */

      /*     case 2: //dislike */
      /*       $temp["dislike"]["user"]++; */
      /*       break; */

      /*     case -1: //view */
      /*       $temp["view"]["user"]++; */
      /*       break; */
      /*   } */
      /* } */

      $hasil[] = [
        "kategori" => $index_name,
        "category_path" => $category_path,
        "id" => $kategori["id"],
        "data" => $temp
      ];
    } //loop kategori

    return [
      "status" => "ok",
      "pesan" => "Berhasil",
      "daftar_kategori" => $daftar_kategori,
      "result" => $hasil,
    ];
  }


  /*
   *
   *  WARNING!!
   *  JIRA tidak mengenal konsep komen. Hanya mengenal konsep jawaban.
   *
   *  Membuat atau mengedit comment. Comment punya relasi kepada issue atau 
   *  jawaban. Oleh karena itu, request create atau update harus menyertakan
   *  tipe comment (1 = comment of issue; 2= comment of jawaban)
   *  Method : POST
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_parent": 123,
   *    "id_user": 123,
   *    "konten": "asdf"
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of comment
   *    }
   *  }
   *
   *  Method : GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "type": 1/2,
   *    "id": 123,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of comment
   *    }
   *  }
   *
   *  Method : PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "type": 1/2,
   *    "id": 123,
   *    "konten": "asdf"
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of comment
   *    }
   *  }
   *
   *  Method : DELETE
   *  Request type: JSON
   *  Request format:
   *  {
   *    "type": 1/2,
   *    "id": 123,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of comment
   *    }
   *  }
    * */
  public function actionComment()
  {
    $payload = $this->GetPayload();
    
    if( Yii::$app->request->isPost )
    {
      // membuat record comment
      $is_id_parent_valid = isset($payload["id_parent"]);
      $is_id_user_valid = isset($payload["id_user"]);
      $is_konten_valid = isset($payload["konten"]);
      
      if(
          $is_id_parent_valid == true &&
          $is_id_user_valid == true &&
          $is_konten_valid == true
        )
      {
        //cek id_parent
        if( is_numeric($payload["id_parent"]) == false )
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter tidak valid: id_parent (integer)",
          ];
        }
        else
        {
          $issue = HdIssue::findOne($payload["id_parent"]);

          if( is_null($issue) == false )
          {
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "id_parent tidak ditemukan",
            ];

          }
        }
        
        
        //cek id_user
        $test = User::findOne($payload["id_user"]);
        if( is_null($test) == true)
        {
          return [
            "status" => "not ok",
            "pesan" => "id_user tidak dikenal",
          ];
        }
        
        //eksekusi
        $discussion = new HdIssueDiscussion();
        $discussion["id_issue"] = $issue["id"];
        $discussion["judul"] = "---";
        $discussion["konten"] = $payload["body"];
        $discussion["time_create"] = date("Y-m-d H:i:s");
        $discussion["id_user_create"] = $payload["id_usre"];
        $discussion->save();


        $user = User::findOne($payload["id_user"]);

        return [
          "status" => "ok",
          "pesan" => "Record comment berhasil dibikin",
          "result" => 
          [
            "record" => $discussion,
            "user" => $user
          ]
        ];
        
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang diperlukan tidak valid: id_user, id_parent, konten",
        ];
      }
    }
    else if( Yii::$app->request->isGet )
    {
      // mengambil record comment

      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);

      if( $payload["type"] == 1 ) // comment of issue
      {
        $test = HdIssueComment::findOne($payload["id"]);

        if( is_null($test) == false )
        {
          return [
            "status" => "ok",
            "pesan" => "Record comment ditemukan",
            "result" => $test
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record comment tidak ditemukan."
          ];
        }
      }
      else if( $payload["type"] == 2 ) //comment of jawaban
      {
        $test = HdIssueDiscussionComment::findOne($payload["id"]);

        if( is_null($test) == false )
        {
          return [
            "status" => "ok",
            "pesan" => "Record comment ditemukan",
            "result" => $test
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record comment tidak ditemukan."
          ];
        }
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter tidak valid. type harus diisi dengan 1 atau 2",
        ];
      }

    }
    else if( Yii::$app->request->isPut )
    {
      // mengupdate record comment
      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);
      $is_konten_valid = isset($payload["konten"]);

      if( $payload["type"] == 1 ) // comment of issue
      {
        $test = HdIssueComment::findOne($payload["id"]);

        if( is_null($test) == false )
        {

          $test["konten"] = $payload["konten"];
          $test["id_user_update"] = $payload["id_user"];
          $test["time_update"] = date("Y-m-j H:i:s");
          $test->save();

          return [
            "status" => "ok",
            "pesan" => "Record comment ditemukan",
            "result" => $test
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record comment tidak ditemukan."
          ];
        }
      }
      else if( $payload["type"] == 2 ) //comment of jawaban
      {
        $test = HdIssueDiscussionComment::findOne($payload["id"]);

        if( is_null($test) == false )
        {
          $test["konten"] = $payload["konten"];
          $test["id_user_update"] = $payload["id_user"];
          $test["time_update"] = date("Y-m-j H:i:s");
          $test->save();

          return [
            "status" => "ok",
            "pesan" => "Record comment ditemukan",
            "result" => $test
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record comment tidak ditemukan."
          ];
        }
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter tidak valid. type harus diisi dengan 1 atau 2",
        ];
      }
    }
    else if( Yii::$app->request->isDelete )
    {
      // delete record comment
      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);

      if( $payload["type"] == 1 ) // comment of issue
      {
        $test = HdIssueComment::findOne($payload["id"]);

        if( is_null($test) == false )
        {

          $test["is_delete"] = 1;
          $test["id_user_delete"] = $payload["id_user"];
          $test["time_delete"] = date("Y-m-j H:i:s");
          $test->save();

          return [
            "status" => "ok",
            "pesan" => "Record comment ditemukan",
            "result" => $test
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record comment tidak ditemukan."
          ];
        }
      }
      else if( $payload["type"] == 2 ) //comment of jawaban
      {
        $test = HdIssueDiscussionComment::findOne($payload["id"]);

        if( is_null($test) == false )
        {
          $test["is_delete"] = 1;
          $test["id_user_delete"] = $payload["id_user"];
          $test["time_delete"] = date("Y-m-j H:i:s");
          $test->save();

          return [
            "status" => "ok",
            "pesan" => "Record comment ditemukan",
            "result" => $test
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record comment tidak ditemukan."
          ];
        }
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter tidak valid. type harus diisi dengan 1 atau 2",
        ];
      }
    }
  }

  /*
   *  Membuat atau mengedit jawaban.
   *
   *  Method : POST
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_issue": 123,
   *    "id_user": 123,
   *    "konten": "asdf"
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of jawaban
   *    }
   *  }
   *
   *  Method : GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id": 123,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of jawaban
   *    }
   *  }
   *
   *  Method : PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id": 123,
   *    "konten": "asdf"
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of jawaban
   *    }
   *  }
   *
   *  Method : DELETE
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id": 123,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "ok",
   *    "result":
   *    {
   *      record of jawaban
   *    }
   *  }
    * */
  public function actionAnswer()
  {
    $payload = $this->GetPayload();
    $issue = null;
    $user = null;
    
    if( Yii::$app->request->isPost )
    {
      // membuat record jawaban


      $is_id_parent_valid = isset($payload["id_parent"]);
      $is_id_user_valid = isset($payload["id_user"]);
      $is_konten_valid = isset($payload["konten"]);
      
      if(
          $is_id_parent_valid == true &&
          $is_id_user_valid == true &&
          $is_konten_valid == true
        )
      {
        //cek id_parent
        if( is_numeric($payload["id_parent"]) == false )
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter tidak valid. id_parent (integer)",
          ];
        }
        else
        {
          $issue = HdIssue::findOne($payload["id_parent"]);

          if( is_null($issue) == true )
          {
            return [
              "status" => "not ok",
              "pesan" => "id_parent tidak ditemukan",
            ];
          }
        }
        
        
        //cek id_user
        $user = User::findOne($payload["id_user"]);
        if( is_null($user) == true)
        {
          return [
            "status" => "not ok",
            "pesan" => "id_user tidak ditemukan",
          ];
        }
        
        //eksekusi

            // bikin answer 
                $client = $this->SetupGuzzleClient();
                $jira_conf = Yii::$app->restconf->confs['jira'];

                $request_payload = [
                  "body" => $payload["konten"],
                  "public" => true
                ];
                $res = $client->request(
                  'POST',
                  "/rest/servicedeskapi/request/{$issue["linked_id_issue"]}/comment",
                  [
                    /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
                    /* 'debug' => true, */
                    'http_errors' => false,
                    'headers' => [
                      "Content-Type" => "application/json",
                      "Media-Type" => "application/json",
                      "accept" => "application/json",
                    ],
                    'auth' => [
                      $jira_conf["user"],
                      $jira_conf["password"]
                    ],
                    'body' => Json::encode($request_payload),
                  ]
                );

                $response_payload = $res->getBody();
                $response_payload = Json::decode($response_payload);

                $id_answer = $response_payload["id"];
            // bikin answer


            // simpan di SPBE
                $new = new HdIssueDiscussion();
                $new["id_user_create"] = $payload["id_user"];
                $new["time_create"] = date("Y-m-j H:i:s");
                $new["id_issue"] = $payload["id_parent"];
                $new["linked_id_discussion"] = $id_answer;
                $new["judul"] = "---";
                $new["konten"] = $payload["konten"];
                $new->save();
                $id_discussion = $new->primaryKey;
            // simpan di SPBE

            // simpan attachment

                HdIssueDiscussionFile::deleteAll(["id_discussion" => $payload["id_parent"]]);

                foreach($payload["id_files"] as $item_file)
                {
                  $new = new HdIssueDiscussionFile();
                  $new["id_discussion"] = $id_discussion;
                  $new["id_file"] = $item_file;
                  $new["time_create"] = date("Y-m-j H:i:s");
                  $new["id_user_create"] = $payload["id_user"];
                  $new->save();
                }

            // simpan attachment


            switch(true)
            {
              case $issue["status"] == 2 : // in progress
                $this->IssueLog(
                  $payload["id_issue"], 
                  $payload["id_user"], 
                  2, 
                  2   // respon oleh sme
                );

                break;

              case $issue["status"] == 3 : // waiting for customer
                $this->IssueLog(
                  $payload["id_issue"], 
                  $payload["id_user"], 
                  2, 
                  1   // respon oleh mk
                );
                break;

            }

            /* // kirim ke CQ */
            /*     $request_payload = [ */
            /*       "body" => $payload["konten"], */
            /*       "draftId" => $id_answer_draft, */
            /*       "dateAnswered" => date("Y-m-d"), */
            /*     ]; */

            /*     $res = $client->request( */
            /*       'POST', */
            /*       "/rest/questions/1.0/question/{$issue["linked_id_issue"]}/answers", */
            /*       [ */
            /*         /1* 'sink' => Yii::$app->basePath . "/guzzledump.txt", *1/ */
            /*         /1* 'debug' => true, *1/ */
            /*         'http_errors' => false, */
            /*         'headers' => [ */
            /*           "Content-Type" => "application/json", */
            /*           "Media-Type" => "application/json", */
            /*           "accept" => "application/json", */
            /*         ], */
            /*         'auth' => [ */
            /*           $jira_conf["user"], */
            /*           $jira_conf["password"] */
            /*         ], */
            /*         'body' => Json::encode($request_payload), */
            /*       ] */
            /*     ); */

            /*     $response_payload = $res->getBody(); */
            /*     $response_payload = Json::decode($response_payload); */
            /* // kirim ke CQ */


        //eksekusi


        $user = User::findOne($payload["id_user"]);
        $discussion = HdIssueDiscussion::findOne($id_discussion);
        $files = HdIssueDiscussionFile::findAll(["id_discussion" => $id_discussion]);

        return [
          "status" => "ok",
          "pesan" => "Record jawaban berhasil dibikin",
          "result" => 
          [
            "discussion" => $discussion,
            "files" => $files,
            "user" => $user,
          ]
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang diperlukan tidak valid: id_user, id_issue, konten",
        ];
      }
    }
    else if( Yii::$app->request->isGet )
    {
      // mengambil record jawaban

      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);

      $test = HdIssueDiscussion::findOne($payload["id"]);

      if( is_null($test) == false )
      {
        return [
          "status" => "ok",
          "pesan" => "Record jawaban ditemukan",
          "result" => $test
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record jawaban tidak ditemukan."
        ];
      }

    }
    else if( Yii::$app->request->isPut )
    {
      // mengupdate record jawaban
      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);
      $is_konten_valid = isset($payload["konten"]);

      $test = HdIssueDiscussion::findOne($payload["id"]);

      if( is_null($test) == false )
      {

        $test["konten"] = $payload["konten"];
        $test["id_user_update"] = $payload["id_user"];
        $test["time_update"] = date("Y-m-j H:i:s");
        $test->save();

        return [
          "status" => "ok",
          "pesan" => "Record jawaban ditemukan",
          "result" => $test
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record jawaban tidak ditemukan."
        ];
      }
    }
    else if( Yii::$app->request->isDelete )
    {
      // delete record jawaban
      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);


      $test = HdIssueDiscussion::findOne($payload["id"]);

      if( is_null($test) == false )
      {

        $test["is_delete"] = 1;
        $test["id_user_delete"] = $payload["id_user"];
        $test["time_delete"] = date("Y-m-j H:i:s");
        $test->save();

        return [
          "status" => "ok",
          "pesan" => "Record jawaban ditemukan",
          "result" => $test
        ];
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Record jawaban tidak ditemukan."
        ];
      }
    }
  }



  /*
   * Menetapkan user dengan role SME menjadi PIC atas suatu kategori
   *
   * Method: POST
   * Request type: JSON
   * Request format:
   * {
   *   id_kategori: 123,
   *   id_user: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result: 
   *   {
   *     record: { object_of_record }
   *   }
   * }
   *
   * Method: PUT
   * Request type: JSON
   * Request format:
   * {
   *   id_kategori: 123,
   *   id_user: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result: 
   *   {
   *     record: { object_of_record }
   *   }
   * }
   *
   * Method: GET
   * Request type: JSON
   * Request format:
   * {
   *   id_kategori: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     record: { object_of_record }
   *   }
   * }
    * */
  public function actionPic()
  {
    switch( true )
    {
      case Yii::$app->request->isPost == true:
        $payload = $this->GetPayload();

        $is_id_kategori_valid = isset( $payload["id_kategori"] );
        $is_id_user_valid = isset( $payload["id_user"] );

        $test = KmsKategori::findOne( $payload["id_kategori"] );
        $is_id_kategori_valid = $is_id_kategori_valid && (is_null($test) == false);

        $test = User::findOne( $payload["id_user"] );
        $is_id_user_valid = $is_id_user_valid && (is_null($test) == false);


        if( $is_id_kategori_valid == true && $is_id_user_valid == true )
        {
          // bikin record
          $new = new HdKategoriPic();
          $new["id_kategori"] = $payload["id_kategori"];
          $new["id_user"] = $payload["id_user"];
          $new->save();

          return [
            "status" => "ok",
            "pesan" => "Record PIC telah disimpan",
            "result" => [
              "record" => $new
            ]
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record gagal dibikin",
            "result" => [
              "payload" => $payload
            ]
          ];
        }

        break;

      case Yii::$app->request->isPut == true:
        $payload = $this->GetPayload();

        $is_id_kategori_valid = isset( $payload["id_kategori"] );
        $is_id_user_lama_valid = isset( $payload["id_user_lama"] );
        $is_id_user_baru_valid = isset( $payload["id_user_baru"] );

        $test = KmsKategori::findOne( $payload["id_kategori"] );
        $is_id_kategori_valid = $is_id_kategori_valid && is_null($test) == false;

        $test = User::findOne( $payload["id_user_lama"] );
        $is_id_user_lama_valid = $is_id_user_lama_valid && is_null($test) == false;

        $test = User::findOne( $payload["id_user_baru"] );
        $is_id_user_baru_valid = $is_id_user_baru_valid && is_null($test) == false;


        if( $is_id_kategori_valid == true && 
            $is_id_user_lama_valid == true && 
            $is_id_user_baru_valid == true )
        {
          $test = HdKategoriPic::find()
            ->where(
              [
                "and",
                "id_kategori = :id_kategori",
                "id_user = :id_user",
              ],
              [
                ":id_kategori" => $payload["id_kategori"],
                ":id_user" => $payload["id_user_lama"],
              ]
            )
            ->one();

          if( is_null( $test ) == false )
          {
            // bikin record
            $test["id_kategori"] = $payload["id_kategori"];
            $test["id_user"] = $payload["id_user_baru"];
            $test->save();

            return [
              "status" => "ok",
              "pesan" => "Record PIC lama telah diupdate",
              "result" => [
                "record" => $test
              ]
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Record PIC lama tidak ditemukan",
              "result" => [
                "payload" => $payload
              ]
            ];
          }
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record gagal dibikin",
            "result" => [
              "payload" => $payload
            ]
          ];
        }
        break;

      case Yii::$app->request->isGet == true:
        $payload = $this->GetPayload();

        $is_id_kategori_valid = isset( $payload["id_kategori"] );
        $is_id_user_valid = isset( $payload["id_user"] );

        $test = KmsKategori::findOne( $payload["id_kategori"] );
        $is_id_kategori_valid = $is_id_kategori_valid && is_null($test) == false;

        $test = User::findOne( $payload["id_user"] );
        $is_id_user_valid = $is_id_user_valid && is_null($test) == false;


        if( $is_id_kategori_valid == true && $is_id_user_valid == true )
        {

          $test = HdKategoriPic::find()
            ->where(
              [
                "and",
                "id_kategori = :id_kategori",
                "id_user = :id_user",
              ],
              [
                ":id_kategori" => $payload["id_kategori"],
                ":id_user" => $payload["id_user"],
              ]
            )
            ->one();

          if( is_null( $test ) == false )
          {
            return [
              "status" => "ok",
              "pesan" => "Record PIC ditemukan",
              "result" => [
                "record" => $test
              ]
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Record PIC tidak ditemukan",
              "result" => [
                "payload" => $payload
              ]
            ];
          }
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak lengkap",
            "result" => [
              "payload" => $payload
            ]
          ];
        }

        break;

    }
  }

  /*
   * Menetapkan sla  atas suatu kategori
   *
   * Method: POST
   * Request type: JSON
   * Request format:
   * {
   *   id_kategori: 123,
   *   id_user: 123,
   *   sla_open: 123,
   *   sla_in_progress: 123,
   *   sla_waiting_for_customer: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result: 
   *   {
   *     record: { object_of_record }
   *   }
   * }
   *
   * Method: PUT
   * Request type: JSON
   * Request format:
   * {
   *   id_kategori: 123,
   *   id_user: 123,
   *   sla_open: 123,
   *   sla_in_progress: 123,
   *   sla_waiting_for_customer: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result: 
   *   {
   *     record: { object_of_record }
   *   }
   * }
   *
   * Method: GET
   * Request type: JSON
   * Request format:
   * {
   *   id_kategori: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     record: { object_of_record }
   *   }
   * }
    * */
  public function actionSla()
  {
    switch( true )
    {
      case Yii::$app->request->isPost == true:
        $payload = $this->GetPayload();

        $is_id_kategori_valid = isset( $payload["id_kategori"] );
        $is_sla_open_valid = isset( $payload["sla_open"] );
        $is_sla_in_progress_valid = isset( $payload["sla_in_progress"] );
        $is_sla_waiting_for_customer_valid = isset( $payload["sla_waiting_for_customer"] );

        $test = KmsKategori::findOne( $payload["id_kategori"] );
        $is_id_kategori_valid = $is_id_kategori_valid && is_null($test) == false;

        $test = User::findOne( $payload["id_user"] );
        $is_id_user_valid = $is_id_user_valid && is_null($test) == false;


        if( $is_id_kategori_valid == true && 
            $is_sla_open_valid == true &&
            $is_sla_in_progress_valid == true &&
            $is_sla_waiting_for_customer_valid == true
          )
        {
          // bikin / update record

          $test = HdKategoriSla::findOne(["id_kategori" => $payload["id_kategori"]]);

          if( is_null($test) == true )
          {
            $new = new HdKategoriSla();
            $new["id_kategori"] = $payload["id_kategori"];
            $new["sla_open"] = $payload["sla_open"];
            $new["sla_in_progress"] = $payload["sla_in_progress"];
            $new["sla_waiting_for_customer"] = $payload["sla_waiting_for_customer"];

            try
            {
              $new->save();

              return [
                "status" => "ok",
                "pesan" => "Record SLA telah disimpan",
                "result" => [
                  "record" => $new
                ]
              ];
            }
            catch(yii\db\IntegrityException $e)
            {
              return [
                "status" => "ok",
                "pesan" => "Record SLA telah disimpan",
                "result" => [
                  "record" => $new
                ]
              ];
            }
          }
          else
          {
            // update

            $test["sla_open"] = $payload["sla_open"];
            $test["sla_in_progress"] = $payload["sla_in_progress"];
            $test["sla_waiting_for_customer"] = $payload["sla_waiting_for_customer"];
            $test->save();

            return [
              "status" => "ok",
              "pesan" => "Record SLA telah disimpan",
              "result" => [
                "record" => $new
              ]
            ];
          }
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record gagal dibikin",
            "result" => [
              "payload" => $payload
            ]
          ];
        }

        break;

      case Yii::$app->request->isGet == true:
        $payload = $this->GetPayload();

        $is_id_kategori_valid = isset( $payload["id_kategori"] );

        if( $is_id_kategori_valid == true)
        {

          $test = HdKategoriSla::findOne( ["id_kategori" =>  $payload["id_kategori"] ] );

          if( is_null( $test ) == false )
          {
            return [
              "status" => "ok",
              "pesan" => "Record SLA ditemukan",
              "result" => [
                "record" => $test
              ]
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Record SLA tidak ditemukan",
              "result" => [
                "payload" => $payload
              ]
            ];
          }
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak lengkap",
            "result" => [
              "payload" => $payload
            ]
          ];
        }

        break;


      case Yii::$app->request->isDelete == true:
        $payload = $this->GetPayload();

        $is_id_kategori_valid = isset( $payload["id_kategori"] );
        /* $is_sla_open_valid = isset( $payload["sla_open"] ); */
        /* $is_sla_in_progress_valid = isset( $payload["sla_in_progress"] ); */
        /* $is_sla_waiting_for_customer_valid = isset( $payload["sla_waiting_for_customer"] ); */

        if( $is_id_kategori_valid == true  
            /* $is_sla_open_valid == true && */
            /* $is_sla_in_progress_valid == true && */
            /* $is_sla_waiting_for_customer_valid == true */
          )
        {
          $test = HdKategoriSla::findOne(["id_kategori" => $payload["id_kategori"] ] );

          if( is_null( $test ) == false )
          {
            // delete record
            $test["id_kategori"] = $payload["id_kategori"];
            $test["sla_open"] = -1;
            $test["sla_in_progress"] = -1;
            $test["sla_waiting_for_customer"] = -1;
            $test->save();

            return [
              "status" => "ok",
              "pesan" => "Record SLA telah diupdate",
              "result" => [
                "record" => $test
              ]
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Record SLA tidak ditemukan",
              "result" => [
                "payload" => $payload
              ]
            ];
          }
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Record gagal dihapus",
            "result" => [
              "payload" => $payload
            ]
          ];
        }
        break;

    }
  }

  /*
   * Memeriksa SLA pada tiket tiket yang belum close/complete. Jika SLA sudah melewati
   * batasnya, status tiket akan diganti menjadi COMPLETE
    * */
  public function actionCekSla()
  {
    // ambil daftar tiket yang statusnya IN PROGRESS atau WAITING FOR CUSTOMER
    // memperhatikan id_kategori dan id_instansi

    $hasil = [];

    $q = new Query();
    $daftar_tiket =
      $q->select("t.*")
        ->from("hd_issue t")
        ->where(
          [
            "and",
            ["in", "t.status", [2, 3]],
            "is_delete = 0",
            "is_disposisi = 0"
          ]
        )
        ->all();

    if( count($daftar_tiket) > 0 )
    {
      foreach($daftar_tiket as $tiket)
      {
        //cek SLA setiap tiket

        $id_kategori = $tiket["id_kategori"];

        // ambil konfigurasi SLA atas kategori ini
        // jika tidak menemukan, ambil SLA dari parent. mengikuti prinsip 
        // inheritance

        $terus = false;
        $sla = null;
        do
        {
          $sla = HdKategoriSla::find()
            ->where(
              [
                "and",
                "id_kategori = :id_kategori"
              ],
              [
                ":id_kategori" => $id_kategori
              ]
            )
            ->one();

          if( is_null($sla) == false )
          {
            //cek nilai sla_open dll

            if( $sla["sla_open"] == -1 )
            {
              // lanjutkan pencarian SLA
              $terus = true;

              // cek SLA parent. jika tidak ada parent, hentikan pencarian SLA
              $this->GetParent($id_kategori, $terus, $sla);
            }
            else
            {
              // SLA telah ditemukan.
              $terus = false;
            }
          }
          else
          {
            // tidak menemukan record SLA.
            // cek SLA pada parent
            $this->GetParent($id_kategori, $terus, $sla);
          }

        } while($terus == true);

        if( is_null($sla) == false )
        {
          // ambil status terakhir sebuah tiket dan hitung umurnya

          switch( $tiket["status"] )
          {
            case 2: // in progress
              $threshold = $sla["sla_in_progress"];
              break;

            case 3: // waiting for customer
              $threshold = $sla["sla_waiting_for_customer"];
              break;

          }

          $time_a = strtotime($tiket["time_status"]);
          $time_b = time();

          $duration = $time_b - ($time_a / 60 / 60 / 24);  //satuan hari

          if( $duration > $threshold )
          {
            // terbitkan notifikasi
            $this->NotifikasiPICComplete($tiket["id"], $tiket["id_user_create"]);

            // ganti status menjadi COMPLETE. lakukan via API call
            $client = $this->SetupGuzzleClientSPBE();

            $payload = [
              "id_issue" => [ $tiket["id"] ],
              "id_user" => $tiket["id_user_create"],
              "status" => 5,  // complete
            ];

            $res = $client->request(
              "PUT",
               "web/index.php?r=kms/helpdesk/status",
              [
                "json" => $payload
              ]
            );

            $temp = [];
            $temp["result"] = $res;
            $temp["payload"] = $payload;

            $hasil["sukses"][] = $temp;
          }
        }
        else
        {
          $hasil["gagal"][] = $tiket;
        }

      } // loop daftar tiket

      return [
        "status" => "ok",
        "Pesan" => "Cek SLA telah selesai",
        "result" => [
          "sukses" => $hasil["sukses"],
          "gagal" => $hasil["gagal"]
        ]
      ];
    }
    else
    {
      return [
        "status" => "ok",
        "pesan" => "Tidak ada record tiket yang dapat diproses",
      ];
    }

  }

  private function GetParent(&$id_kategori, &$terus, &$sla)
  {
    $kategori = KmsKategori::findOne($id_kategori);
    if( $kategori["id_parent"] != -1 )
    {
      $terus = true;

      $id_kategori = $kategori["id_parent"];
    }
    else
    {
      $terus = false;
      $sla = null;

      $id_kategori = null;
    }
  }

  /*
   * Melakukan disposisi atas suatu tiket.
   *
   * Method: POST
   * Request type: JSON
   * Request format:
   * {
   *   id_issue: 123,
   *   id_sme: 123
   *   pesan: ""
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     record: {}
   *   }
   * }
   *
   * Method: DELETE
   * Request type: JSON
   * Request format:
   * {
   *   id_issue: 123
   *   id_user: 123,
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *     record: {}
   *   }
   * }
   * 
    * */
  public function actionDisposisi()
  {
    switch(true)
    {
      case Yii::$app->request->isPost == true :
        $payload = $this->GetPayload();

        $is_id_issue_valid = isset( $payload["id_issue"] );
        $is_id_user_valid = isset( $payload["id_user"] );
        $is_id_user_actor_valid = isset( $payload["id_user_actor"] );

        $test_issue = HdIssue::findOne($payload["id_issue"]);
        $is_id_issue_valid = $is_id_issue_valid && is_null( $test_issue ) == false;

        $test_user = User::findOne($payload["id_user"]);
        $is_id_user_valid = $is_id_user_valid && is_null( $test_user ) == false;

        if($payload["id_user_actor"] != -1)
        {
          $test_user_actor = User::findOne($payload["id_user_actor"]);
          $is_id_user_actor_valid = $is_id_user_actor_valid && is_null( $test_user_actor ) == false;
        }
        else
        {
          $is_id_user_actor_valid = true;
        }

        $id_role = Roles::IdByCodeName("sme");
        $test_role = UserRoles::find()
          ->where(
            [
              "and",
              "id_user = :id_user",
              "id_roles = :id_role"
            ],
            [
              ":id_user" => $payload["id_user"],
              ":id_role" => $id_role,
            ]
          )
          ->one();

        $is_id_user_valid = $is_id_user_valid && is_null( $test_role ) == false;

        if( $is_id_issue_valid == true  
            && $is_id_user_valid == true 
            && $is_id_user_actor_valid == true )
        {
          // rekam disposisi
          $new = new HdIssueDisposisi();
          $new["id_issue"] = $payload["id_issue"];
          $new["id_sme_out"] = $payload["id_user_actor"];
          $new["id_sme_in"] = $payload["id_user"];
          $new["pesan"] = $payload["pesan"];
          $new["time_create"] = date("Y-m-j H:i:s");
          $new->save();

          // tandai record issuse bahwa sedang dalam keadaan disposisi
          $test_issue["is_disposisi"] = 1;
          $test_issue["time_disposisi"] = date("Y-m-j H:i:s");
          $test_issue["id_user_disposisi"] = $payload["id_user"];
          $test_issue->save();

          return [
            "status" => "ok",
            "pesan" => "Disposisi telah dibikin",
            "result" => [
              "record" => $new
            ]
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Disposisi gagal dibikin",
            "payload" => $payload
          ];
        }
        break;

      case Yii::$app->request->isDelete == true :
        $payload = $this->GetPayload();

        $is_id_issue_valid = isset( $payload["id_issue"] );
        $is_id_user_valid = isset( $payload["id_user"] );

        $test_issue = HdIssue::findOne( $payload["id_issue"] );
        $is_id_issue_valid = $is_id_issue_valid && is_null($test_issue) == false;

        $test_user = User::findOne( $payload["id_user"] );
        $is_id_user_valid = $is_id_user_valid && is_null($test_user) == false;

        if( $is_id_issue_valid == true && $is_id_user_valid == true )
        {
          if( $test_issue["id_user_disposisi"] == $test_user["id"] )
          {
            $test_issue["is_disposisi"] = 0;
            $test_issue["id_user_disposisi"] = -1;
            $test_issue["time_disposisi"] = null;
            $test_issue->save();

            $this->UpdateSolver($payload["id_issue"], [ $payload["id_user"] ]);

            return [
              "status" => "ok",
              "pesan" => "Pembatalan disposis berhasil",
              "result" => [
                "record" => $test_issue,
              ]
            ];
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Gagal membatalkan disposisi ",
              "payload" => $payload
            ];
          }
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang diberikan tidak valid",
            "payload" => $payload
          ];
        }
        break;

    }
  }
  
   public function actionPesanDisposisi()
  {
    $payload = $this->GetPayload();

    $query = new Query;
    $query->select('hid.id,hid.id_issue,hid.id_sme,hid.pesan,hid.time_create,u.nama,u.username')
    ->from('hd_issue_disposisi hid')
    ->join('LEFT JOIN', 'user u','u.id = hid.id_sme')
    ->where('id_issue = '.$payload["id_issue"]);

    $command = $query->createCommand();
    $data = $command->queryAll();

    return [
        "status" => "ok",
        "pesan" => "Data berhasil diambil",
        "record" => $data
    ];
  }

  /*
   * Mengambil daftar tiket yang di-disposisikan kepada si user
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_user": 123,
   *    "page_no": 123,
   *    "items_per_page": 123
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/nor ok",
   *    "pesan": "",
   *    "result":
   *    {
   *      "page_no": 123,
   *      "items_per_page": 123,
   *      "count": 123,
   *      "records":
   *      [
   *        {
   *          "hd_issue":
   *          {
   *            <object dari record issue>
   *          },
   *          "confluence":
   *          {
   *            <object dari record Confluence>
   *          }
   *        },
   *        ...
   *      ]
   *    }
   *  }
   *
    * */
  public function actionDisposisiList()
  {
    $payload = $this->GetPayload();

    //  cek parameter
    // $is_kategori_valid = isset($payload["id_kategori"]);
    
    $is_id_user_valid = isset($payload["id_user"]);
    $is_page_no_valid = isset($payload["page_no"]);
    $is_items_per_page_valid = isset($payload["items_per_page"]);

    $is_page_no_valid = $is_page_no_valid && is_numeric($payload["page_no"]);
    $is_items_per_page_valid = $is_items_per_page_valid && is_numeric($payload["items_per_page"]);

    if(
        $is_id_user_valid == true &&
        $is_page_no_valid == true &&
        $is_items_per_page_valid == true
      )
    {
      //  lakukan query dari tabel hd_issue
      $test = HdIssue::find()
        ->where(
          [
            "and",
            "is_delete = 0",
            "is_disposisi = 1",
            "id_user_disposisi = :id_user"
          ],
          [
            ":id_user" => $payload["id_user"],
          ]
        )
        ->all();
      $total_rows = count($test);

      $list_issue = HdIssue::find()
        ->where(
          [
            "and",
            "is_delete = 0",
            "is_disposisi = 1",
            "id_user_disposisi = :id_user"
          ],
          [
            ":id_user" => $payload["id_user"],
          ]
        )
        ->orderBy("time_create desc")
        ->offset( $payload["items_per_page"] * ($payload["page_no"] - 1) )
        ->limit( $payload["items_per_page"] )
        ->all();

      //  lakukan query dari Confluence
      $jira_conf = Yii::$app->restconf->confs['jira'];
      $client = $this->SetupGuzzleClient();

      $hasil = [];
      foreach($list_issue as $issue)
      {
        $user = User::findOne($issue["id_user_create"]);
        $user_disposisi = User::findOne($issue["id_user_disposisi"]);
        $jawaban = HdIssueDiscussion::findAll([
          "id_issue" => $issue["id"],
          "is_delete" => 0
        ]);

        $res = $client->request(
          'GET',
          "/rest/servicedeskapi/request/{$issue["linked_id_issue"]}",
          [
            /* 'sink' => Yii::$app->basePath . "/guzzledump.txt", */
            /* 'debug' => true, */
            'http_errors' => false,
            'headers' => [
              "Content-Type" => "application/json",
              "accept" => "application/json",
            ],
            'auth' => [
              $jira_conf["user"],
              $jira_conf["password"]
            ],
            /* 'query' => [ */
            /*   'spaceKey' => 'PS', */
            /*   'expand' => 'history,body.view' */
            /* ], */
          ]
        );

        //  kembalikan hasilnya
        switch( $res->getStatusCode() )
        {
          case 200:
            // ambil id dari result
            $response_payload = $res->getBody();
            $response_payload = Json::decode($response_payload);

            $temp = [];
            $temp["hd_issue"] = $issue;
            $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
            $temp["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
            $temp["user_create"] = $user;
            $temp["user_disposisi"] = $user_disposisi;
            $temp["user_actor_status"] = HdIssueUserAction::GetUserAction($payload["id_issue"], $payload["id_user_actor"]);
            $temp["jawaban"]["count"] = count($jawaban);
            $temp["jawaban"]["records"] = $jawaban;
            $temp["servicedesk"]["status"] = "ok";
            $temp["servicedesk"]["linked_id_issue"] = $response_payload["issueId"];
            $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
            $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
            $temp['data_user']['user_create'] = $user->nama;

            $hasil[] = $temp;
            break;

          default:
            // kembalikan response
            $temp = [];
            $temp["hd_issue"] = $issue;
            $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
            $temp["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
            // $hasil["user_create"] = $user;
            $temp["servicedesk"]["status"] = "not ok";
            $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
            $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
            $temp['data_user']['user_create'] = $user->nama;

            $hasil[] = $temp;
            break;
        }
      }

      return [
        "status" => "ok",
        "pesan" => "Query finished",
        "result" =>
        [
          "total_rows" => $total_rows,
          "page_no" => $payload["page_no"],
          "items_per_page" => $payload["items_per_page"],
          "count" => count($list_issue),
          "records" => $hasil
        ]
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak valid.",
        "payload" => $payload
      ];
    }

  }

  ///

  // ==========================================================================
  // my issues
  // ==========================================================================
  

      //  Mengembalikan daftar issue berdasarkan
      //  
      //  Method: GET
      //  Request type: JSON
      //  Request format:
      //  {
      //    id_user: 123,
      //    status: 123
      //  }
      //  Response type: JSON
      //  Response format:
      //  {
      //    "status": "ok/not ok",
      //    "pesan": "",
      //    "result":
      //    {
      //      records:
      //      [
      //        {
      //          object of issue
      //        }, ...
      //      ]
      //    }
      //  }
      public function actionMyitems()
      {
      	$payload = $this->GetPayload();
	
        $is_id_user_valid = isset($payload["id_user"]);
        $is_status_valid = isset($payload["status"]);

        if( $is_id_user_valid == true && $is_status_valid == true )
        {
          $list_issue = HdIssue::find()
            ->where(
              [
                "and",
                "id_user_create = :id_user",
                "status = :status",
                "is_delete = 0"
              ],
              [
                ":id_user" => $payload["id_user"],
                ":status" => $payload["status"],
              ]
            )
            ->orderBy("time_create desc")
            ->all();

          $hasil = [];
          $client = $this->SetupGuzzleClient();
          foreach($list_issue as $issue)
          {
            // get record CQ
            $response = $this->Conf_GetQuestion($client, $issue["linked_id_issue"]);
            $response_payload = $response->getBody();
            $response_payload = Json::decode($response_payload);

            $user = User::findOne($issue["id_user_create"]);

            $temp = [];
            $temp["record"]["hd_issue"] = $issue;
            $temp["record"]["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
            $temp["record"]["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
            $temp["record"]["user_create"] = $user;
            $temp["servicedesk"]["id"] = $response_payload["issueId"];
            $temp["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
            $temp["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
            $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
            $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

            $hasil[] = $temp;
          }

          return [
            "status" => "ok",
            "pesan" => "Record berhasil diambil",
            "result" => $hasil,
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak valid.",
            "payload" => $payload,
          ];
        }
      }


      // mengembalikan navigasi kategori beserta jumlah artikel yang diterbitkan
      // dalam masing masing kategori.
      //
      //  Method: GET
      //  Request type: JSON
      //  Request format:
      //  {
      //    id_user: 123
      //  }
      //  Response type: JSON
      //  Response format:
      //  {
      //    "status": "",
      //    "pesan" : "",
      //    "result":
      //    [
      //      {
      //        "id": 123,
      //        "id_parent": 123,
      //        "nama": "abc abc",
      //        "jumlah_artikel": 123
      //      }, ...
      //    ]
      //  }
      public function actionMtCategoryTree()
      {
        $payload = $this->GetPayload();

        $hasil = KmsKategori::MyThreads_GetNavigation($payload["id_user"]);

        return [
          "status" => "ok",
          "pesan" => "Record berhasil diambil",
          "result" => $hasil
        ];
      }


      //  Mengembalikan tag tag yang dipakai dalam artikel artikel yang diterbitkan
      //  si user.
      //
      //  Method: GET
      //  Request type: JSON
      //  Request format:
      //  {
      //    "id_user": 123
      //  }
      //  Response type: JSON
      //  Response format:
      //  {
      //    "status": "",
      //    "pesan" : "",
      //    "result":
      //    [
      //      {
      //        "id": 123,
      //        "nama": "abc abc",
      //        "jumlah_artikel": 123
      //      }, ...
      //    ]
      //  }
      public function actionMtTags()
      {
        $payload = $this->GetPayload();

        $hasil = KmsTag::MyThreads_GetNavigation($payload["id_user"]);

        return [
          "status" => "ok",
          "pesan" => "Record berhasil diambil",
          "result" => $hasil
        ];
      }
      
      


  // ==========================================================================
  // my issues
  // ==========================================================================
}
