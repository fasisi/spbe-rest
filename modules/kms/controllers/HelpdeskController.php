<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\base;
use yii\helpers\Json;
use yii\db\Query;

use app\models\HdIssue;
use app\models\HdIssueActivityLog;
use app\models\HdIssueUserAction;
use app\models\HdIssueTag;
use app\models\HdIssueDiscussion;
use app\models\HdIssueComment;
use app\models\HdIssueDiscussionComment;
use app\models\KmsTags;
use app\models\ForumTags;
use app\models\User;
use app\models\KmsKategori;

use Carbon\Carbon;

class HelpdeskController extends \yii\rest\Controller
{
  public function behaviors()
  {
    $behaviors = parent::behaviors();
    $behaviors['verbs'] = [
      'class' => \yii\filters\VerbFilter::className(),
      'actions' => [
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
      private function ThreadLog($id_issue, $id_user, $type_log, $log_value)
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

        HdIssueUserAction::Summarize($id_issue);
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
  //    "id_kategori": 123,
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
          $issue["id_user_create"] = $payload["id_user"];
          $issue["time_create"] = date("Y-m-d H:i:s");
          $issue["status"] = $payload["status"];
          $issue->save();

          $this->UpdateTags($client, $jira_conf, $issue["id"], $issue["linked_id_issue"], $payload);

          return [
            "status" => "ok",
            "pesan" => "Record issue berhasil disimpan",
            "result" => $issue
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
  public function actionSubmit()
  {
    $payload = $this->GetPayload();

    $is_id_valid = isset($payload["id_issue"]);

    if(
        $is_id_valid == true 
      )
    {
      $jira_conf = Yii::$app->restconf->confs['jira'];
      $client = $this->SetupGuzzleClient();

      $issue = HdIssue::findOne($payload["id_issue"]);

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
           $this->ThreadLog($issue["id"], $payload["id_user"], 1, 1);

        // update record issue

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
  public function actionCreate()
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
            $this->ThreadLog($payload["id_issue"], $payload["id_user"], 1, $payload["status"]);

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
      // update record hd_issue
        $issue = HdIssue::find($payload["id"])
          ->where(
            [
              "and",
              "id = :id",
              "is_delete = 0",
              "status = 0"
            ],
            [
              ":id" => $payload["id"]
            ]
          )
          ->one();

        if( is_null($issue) == false )
        {
          $issue["judul"] = $payload["judul"];
          $issue["konten"] = $payload["body"];
          $issue["status"] = $payload["status"];
          $issue['time_update'] = date("Y-m-j H:i:s");
          $issue['id_user_update'] = $payload["id_user"];
          $issue->save();
          
          // mengupdate informasi tags

              // refresh tag/label
                  $this->UpdateTags($client, $jira_conf, $issue["id"], $issue["linked_id_issue"], $payload);
              // refresh tag/label
                   
          // mengupdate informasi tags


          // kembalikan response
              $tags = HdIssueTag::findAll("id_issue = {$issue["id"]}");

              return 
              [
                'status' => 'ok',
                'pesan' => 'Record issue telah diupdate',
                'result' => 
                [
                  "helpdesk_issue" => $issue,
                  "tags" => $tags
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
        'status' => 'not ok',
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, konten, id_kategori, tsgs',
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
    foreach( $payload["filter"] as $key => $value )
    {
      switch(true)
      {
        case $key == "waktu_awal":
          $value = date("Y-m-d 00:00:00", $tanggal_awal->timestamp);
          $where[] = "l.time_action >= '$value'";
        break;

        case $key == "waktu_akhir":
          $value = date("Y-m-d 23:59:59", $tanggal_akhir->timestamp);
          $where[] = "l.time_action <= '$value'";
        break;

        case $key == "status":
          $temp = [];
          foreach( $value as $type_status )
          {
            $temp[] = $type_status;
          }
          $where[] = ["in", "l.status", $temp];
        break;

        case $key == "id_kategori":
          $q->join("join", "hd_issue i2", "i2.id = l.id_issue");
          /* $q->join("join", "kms_kategori k", "a2.id_kategori = k.id"); */

          $temp = [];
          foreach( $value as $id_kategori )
          {
            $temp[] = $id_kategori;
          }
          $where[] = ["in", "i2.id_kategori", $temp];
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
        ->groupBy("a.id");
    }

    //execute the query
    $records = $q->all();

    $hasil = [];
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
        $temp["category_path"] = KmsKategori::CategoryPath($issue["id_kategori"]);
        $temp["data_user"]["user_create"] = $user;
        $temp["tags"] = HdIssueTag::GetThreadTags($issue["id"]);
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
            // status yang dikenal: 0=draft, 1=new, 2=un-assign, 3=progress, 4=closed/resolved
            // rujuk pada database untuk mendapatkan value.

            //ambil data draft
            $type_status = 0;
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
          case $status == 0:  //draft
            if($temp["draft"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $status == 1:  // new
            if($temp["new"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $status == 2:  // un-assign
            if($temp["publish"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;
          case $status == 3:  // progress
            if($temp["unpublish"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;
          case $status == 4:  // solved
            if($temp["reject"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
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
          $tags = HdIssueTag::GetThreadTags($issue["id"]);
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
   *  Mengambil daftar issue berdasarkan idkategori, page_no, items_per_page.
   *  Hasil yang dikembalikan diurutkan desc berdasarkan waktu publish.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_kategori": [1, 2, ...],
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
   *          "kms_artikel":
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
    $is_page_no_valid = isset($payload["page_no"]);
    $is_items_per_page_valid = isset($payload["items_per_page"]);

    // $is_kategori_valid = $is_kategori_valid && is_array($payload["id_kategori"]);
    $is_status_valid = $is_status_valid && is_array($payload["status"]);
    $is_page_no_valid = $is_page_no_valid && is_numeric($payload["page_no"]);
    $is_items_per_page_valid = $is_items_per_page_valid && is_numeric($payload["items_per_page"]);

    if(
        // $is_kategori_valid == true &&
	$is_status_valid == true &&
        $is_page_no_valid == true &&
        $is_items_per_page_valid == true
      )
    {
      //  lakukan query dari tabel hd_issue
      $test = HdIssue::find()
        ->where([
          "and",
          "is_delete = 0",
          ["in", "status", $payload["status"]],
          // ["in", "id_kategori", $payload["id_kategori"]]
        ])
        ->orderBy("time_create desc")
        ->all();
      $total_rows = count($test);

      $list_issue = HdIssue::find()
        ->where([
          "and",
          "is_delete = 0",
          ["in", "status", $payload["status"]],
          // ["in", "id_kategori", $payload["id_kategori"]]
        ])
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
            $temp["tags"] = HdIssueTag::GetThreadTags($issue["id"]);
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
      $user = User::findOne($issue["id_user_create"]);

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

          $user = User::findOne($item_jawaban["id_user_create"]);
          $jawaban[] = [
            "jawaban" => $record_jawaban,
            "user_create" => $user,
            "jira_comment" => $item_linked_jawaban,
          ];
        }

        //  lakukan query dari Confluence
        $jira_conf = Yii::$app->restconf->confs['jira'];

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
          $hasil["record"]["user_create"] = $user;
          /* $hasil["record"]["user_actor_status"] = HdIssueUserAction::GetUserAction($payload["id_issue"], $payload["id_user_actor"]); */
          $hasil["record"]["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
          $hasil["record"]["servicedesk"]["status"] = "ok";
          $hasil["record"]["servicedesk"]["linked_id_issue"] = $response_payload["issueId"];
          $hasil["record"]["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
          $hasil["record"]["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
          $hasil["jawaban"]["count"] = count($jawaban);
          $hasil["jawaban"]["records"] = $jawaban;

          $this->ThreadLog($issue["id"], $payload["id_user_actor"], 2, -1);
          break;

        default:
          // kembalikan response
          $hasil = [];
          $hasil["record"]["hd_issue"] = $issue;
          $hasil["record"]["issue_comments"] = $list_komentar;
          $hasil["record"]["user_create"] = $user;
          $hasil["record"]["tags"] = HdIssueTag::GetIssueTags($issue["id"]);
          $hasil["record"]["confluence"]["status"] = "not ok";
          $hasil["record"]["servicedesk"]["linked_id_issue"] = $response_payload["issueId"];
          $hasil["record"]["servicedesk"]["judul"] = $response_payload["requestFieldValues"][0]["value"];
          $hasil["record"]["servicedesk"]["konten"] = $response_payload["requestFieldValues"][1]["value"];
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
        $this->ThreadLog($payload["id_issue"], $payload["id_user"], 2, $payload["action"]);

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
   *  Mencari issue berdasarkan daftar id_kategori dan keywords. Search keywords
   *  yang diterima akan dipisah-pisah berdasarkan penggalan kata dan akan dilakukan
   *  pencarian menggunakan operator '~'.
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
  // Confluence-Question support fitur pencarian hanya pada judul dan konten

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
   *  0 = new
   *  1 = publish
   *  2 = un-assign
   *  3 = progress
   *  4 = solved
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
                $this->ThreadLog($id_issue, $payload["id_user"], 1, $payload["status"]);
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
            case 0: //draft
              $temp["data"]["draft"] = $record["jumlah"];
              break;

            case 1: //new
              $temp["data"]["new"] = $record["jumlah"];
              break;

            case 2: //un-assigned
              $temp["data"]["unassigned"] = $record["jumlah"];
              break;

            case 3: //progress
              $temp["data"]["progress"] = $record["jumlah"];
              break;

            case 4: //solved
              $temp["data"]["solved"] = $record["jumlah"];
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
        $q->select("t.id")
          ->from("hd_issue t")
          ->join("JOIN", "hd_issue_activity_log log", "log.id_issue = t.id")
          ->where(
            [
              "and",
              "t.id_kategori = :id_kategori",
              [
                "or",
                [
                  "and",
                  "log.time_status >= :awal",
                  "log.time_status <= :akhir"
                ],
                [
                  "and",
                  "log.time_action >= :awal",
                  "log.time_action <= :akhir"
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
          ->orderBy("log.status asc")
          ->groupBy("a.id")
          ->all();
      $total_issue = count($total_issue);

      $q = new Query();
      $issue_status = 
        $q->select("log.status, count(t.id) as jumlah")
          ->from("hd_issue t")
          ->join("JOIN", "hd_issue_activity_log log", "log.id_issue = t.id")
          ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status")
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

      /* $q = new Query(); */
      /* $user_status = */ 
      /*   $q->select("log.status, count(u.id) as jumlah") */
      /*     ->from("user u") */
      /*     ->join("JOIN", "hd_issue_activity_log log", "log.id_user = u.id") */
      /*     ->join("JOIN", "hd_issue t", "log.id_issue = t.id") */
      /*     ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]]) */
      /*     ->andWhere("log.type_log = 1") */
      /*     ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)]) */
      /*     ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)]) */
      /*     ->distinct() */
      /*     ->orderBy("log.status asc") */
      /*     ->groupBy("log.status") */
      /*     ->all(); */

      /* // ambil jumlah issue per action (like/dislike) */
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

      foreach($issue_status as $record)
      {
        switch( $record["status"] )
        {
          case 0: //draft
            $temp["draft"]["issue"] = $record["jumlah"];
            break;

          case 1: //new
            $temp["new"]["issue"] = $record["jumlah"];
            break;

          case 2: //un-assigned
            $temp["unassigned"]["issue"] = $record["jumlah"];
            break;

          case 3: //progress
            $temp["progress"]["issue"] = $record["jumlah"];
            break;

          case 4: //solved
            $temp["solved"]["issue"] = $record["jumlah"];
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
          case 0: //new
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
   *  Membuat atau mengedit comment. Comment punya relasi kepada issue atau 
   *  jawaban. Oleh karena itu, request create atau update harus menyertakan
   *  tipe comment (1 = comment of issue; 2= comment of jawaban)
   *
   *  WARNING!!
   *  JIRA tidak mengenal konsep komen. Hanya mengenal konsep jawaban.
   *
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
            // simpan di SPBE



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

        return [
          "status" => "ok",
          "pesan" => "Record jawaban berhasil dibikin",
          "result" => 
          [
            "record" => $new,
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
