<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\base;
use yii\helpers\Json;
use yii\db\Query;

use app\models\ForumThread;
use app\models\ForumThreadActivityLog;
use app\models\ForumThreadUserAction;
use app\models\ForumThreadTag;
use app\models\ForumThreadDiscussion;
use app\models\ForumThreadComment;
use app\models\ForumThreadDiscussionComment;
use app\models\KmsTags;
use app\models\ForumTags;
use app\models\User;
use app\models\KmsKategori;

use Carbon\Carbon;

class ForumController extends \yii\rest\Controller
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
        'threaduseraction'      => ['POST'],
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
        $jira_conf = Yii::$app->restconf->confs['confluence'];
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
      private function ThreadLog($id_thread, $id_user, $type_log, $log_value)
      {
        $new = new ForumThreadActivityLog();
        $new["id_thread"] = $id_thread;
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
        $new["time_{$type_name}"] = date("Y=m-d H:i:s");
        $new->save();

        ForumThreadUserAction::Summarize($id_thread);
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

      private function Conf_GetQuestion($client, $linked_id_question)
      {
        $jira_conf = Yii::$app->restconf->confs['confluence'];
        $res = $client->request(
          'GET',
          "/rest/questions/1.0/question/$linked_id_question",
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
              'spaceKey' => 'PS',
              'expand' => 'history,body.view'
            ],
          ]
        );

        return $res;
      }

  // ==========================================================================
  // private helper functions
  // ==========================================================================





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
  //      "thread": record_object,
  //      "tags": [ <record_of_tag>, .. ]
  //    }
  //  }
  public function actionDraft()
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
        $kategori_valid == true && $tags_valid == true && $status_valid == true )
    {
      // panggil POST /rest/api/content

      $tags = [];
      foreach($payload["tags"] as $tag)
      {
        $temp = [];
        $temp["name"] = $tag;

        $tags[] = $temp;
      }

      $thread = new ForumThread();
      $thread['time_create'] = date("Y-m-j H:i:s");
      $thread['id_user_create'] = $payload['id_user'];
      $thread['id_kategori'] = $payload['id_kategori'];
      $thread['status'] = $payload["status"];
      $thread->save();
      $id_thread = $thread->primaryKey;

      $client = $this->SetupGuzzleClient();
      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $this->UpdateTags($client, $jira_conf, $id_thread, null, $payload);

      // ambil ulang tags atas thread ini. untuk menjadi response.
      $tags = ForumThreadTag::find()
        ->where(
          "id_thread = :id_thread",
          [
            ":id_thread" => $id_thread
          ]
        )
        ->all();

      //$this->ActivityLog($id_artikel, 123, 1);
      $this->ThreadLog($id_thread, $payload["id_user"], 1, -1);

      // kembalikan response
      return 
      [
        'status' => 'ok',
        'pesan' => 'Record thread telah dibikin',
        'result' => 
        [
          "artikel" => $thread,
          "tags" => $tags,
          "category_path" => KmsKategori::CategoryPath($thread["id_kategori"]),
        ]
      ];
    }
    else
    {
      return [
        'status' => 'not ok',
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, konten, id_kategori, tags',
      ];
    }

  }



  //  Membuat record question
  //
  //  Method : POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "id_thread": 123,
  //    "id_user_actor": 123
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "result": 
  //    {
  //      "thread": record_object,
  //      "tags": [ <record_of_tag>, .. ]
  //    }
  //  }
  public function actionCreate()
  {
    $id_thread_valid = true;
    $judul_valid = true;
    $body_valid = true;
    $kategori_valid = true;
    $tags_valid = true;
    $status_valid = true;

    // pastikan request parameter lengkap
    $payload = $this->GetPayload();

    if( isset($payload["id_thread"]) == false )
      $id_thread_valid = false;

    if( $id_thread_valid == true )
    {
      // ambil record thread
      $thread = ForumThread::findOne($payload["id_thread"]);
      $tag_list = ForumThreadTag::findAll(
        "id_thread = :id", 
        [":id" => $payload["id_thread"]]
      );

      // panggil POST /rest/api/content

      $tags = [];
      foreach($tag_list as $tag)
      {
        $temp = [];
        $temp["name"] = $tag;

        $tags[] = $temp;
      }

      $request_payload = [
        "title" => $thread["judul"],
        "body" => $thread["konten"],
        "topics" => $tags,
        "dateAsked" => date("Y-m-d"),
        "spaceKey" => "PS",
      ];

      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $client = $this->SetupGuzzleClient();

      $res = null;
      try
      {
        $res = $client->request(
          'POST',
          "/rest/questions/1.0/question",
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
              'status' => 'current',
              'expand' => 'body.view',
            ],
            'body' => Json::encode($request_payload),
          ]
        );

        switch( $res->getStatusCode() )
        {
          case 200:
            // ambil id dari result
            $response_payload = $res->getBody();
            $response_payload = Json::decode($response_payload);

            $linked_id_question = $response_payload['id'];


            // update linked_id_question pada record kms_artikel
            $thread['linked_id_question'] = $linked_id_question;
            $thread->save();
            $id_thread = $thread->primaryKey;

            // ambil ulang tags atas thread ini. untuk menjadi response.
            $tags = ForumThreadTag::find()
              ->where(
                "id_thread = :id_thread",
                [
                  ":id_thread" => $id_thread
                ]
              )
              ->all();

            //$this->ActivityLog($id_artikel, 123, 1);
            $this->ThreadLog($id_thread, $payload["id_user_actor"], 1, 0);

            // kembalikan response
            return 
            [
              'status' => 'ok',
              'pesan' => 'Record thread telah dibikin',
              'result' => 
              [
                "artikel" => $thread,
                "tags" => $tags,
                "category_path" => KmsKategori::CategoryPath($thread["id_kategori"]),
              ]
            ];
            break;

          default:
            // kembalikan response
            return [
              'status' => 'not ok',
              'pesan' => 'REST API request failed: ' . $res->getBody(),
              'result' => $thread,
              'payload' => $payload,
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

  //  Menghapus (soft delete) suatu thread
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

    $test = ForumThread::findOne($payload["id"]);
    if( is_null($test) == true )
    {
      return[
        "status" => "not ok",
        "pesan" => "Record thread tidak ditemukan",
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
      $thread = ForumThread::findOne($payload["id"]);
      $thread["is_delete"] = 1;
      $thread["time_delete"] = date("Y-m-d H:i:s");
      $thread["id_user_delete"] = $payload["id_user_actor"];
      $thread->save();

      return [
        "status" => "ok",
        "pesan" => "Record berhasil dihapus",
        "result" => $thread
      ];
    }
    else
    {
      return [
        "status" => "ok",
        "pesan" => "Record berhasil dihapus",
        "result" => $thread
      ];
    }
  }

  public function actionRetrieve()
  {
      return $this->render('retrieve');
  }

  //  Mengupdate record thread
  //
  //  Method : POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "id": 123,
  //    "id_user": 123,
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


      // update record forum_thread
      $thread = ForumThread::findOne($payload["id"]);

      if( $thread["status"] == 0 ) // hanya draft yang dapat di-edit
      {
        $thread["judul"] = $payload["judul"];
        $thread["konten"] = $payload["body"];
        $thread["id_kategori"] = $payload["id_kategori"];
        $thread['time_update'] = date("Y-m-j H:i:s");
        $thread['id_user_update'] = $payload["id_user"];
        $thread->save();

        // mengupdate informasi tags

            //hapus label pada confluence
            //  WARNING!!
            //
            //  CQ tidak mendukung API untuk mencopot tag dari thread.
            //  Oleh karena itu proses ini dilakukan di dalam SPBE.

                /* $this->DeleteTags($client, $jira_conf, $artikel["linked_id_question"]); */
            //hapus label pada confluence

            // refresh tag/label
                $this->UpdateTags($client, $jira_conf, $thread["id"], $thread["linked_id_question"], $payload);
            // refresh tag/label
                 
        // mengupdate informasi tags


        // kembalikan response
            $tags = ForumThreadTag::findAll("id_thread = {$thread["id"]}");

            return 
            [
              'status' => 'ok',
              'pesan' => 'Record thread telah diupdate',
              'result' => 
              [
                "forum_thread" => $thread,
                "tags" => $tags
              ]
            ];
        // kembalikan response
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Thread tidak dapat di-edit",
          "payload" => $payload,
          "result" => $thread
        ];
      }

      // mengupdate informasi tags

          //hapus label pada confluence
          //  WARNING!!
          //
          //  CQ tidak mendukung API untuk mencopot tag dari thread.
          //  Oleh karena itu proses ini dilakukan di dalam SPBE.

              /* $this->DeleteTags($client, $jira_conf, $artikel["linked_id_question"]); */
          //hapus label pada confluence

          // refresh tag/label
              $this->UpdateTags($client, $jira_conf, $thread["id"], $thread["linked_id_question"], $payload);
          // refresh tag/label
               
      // mengupdate informasi tags


      //$this->ActivityLog($id_artikel, 123, 1);
      //$this->ArtikelLog($payload["id_artikel"], $payload["id_user"], 1, $payload["status"]);




      // ambil nomor versi bersadarkan id_linked_content
          /* $thread = ForumThread::findOne($payload["id"]); */
          
          /* $jira_conf = Yii::$app->restconf->confs['confluence']; */
          /* $client = $this->SetupGuzzleClient(); */

          /* $res = null; */
          /* $res = $client->request( */
          /*   'GET', */
          /*   "/rest/questions/1.0/question/{$thread["linked_id_question"]}", */
          /*   [ */
          /*     /1* 'sink' => Yii::$app->basePath . "/guzzledump.txt", *1/ */
          /*     /1* 'debug' => true, *1/ */
          /*     'http_errors' => false, */
          /*     'headers' => [ */
          /*       "Content-Type" => "application/json", */
          /*       "accept" => "application/json", */
          /*     ], */
          /*     'auth' => [ */
          /*       $jira_conf["user"], */
          /*       $jira_conf["password"] */
          /*     ], */
          /*     'query' => [ */
          /*       'status' => 'current', */
          /*       'expand' => 'body.view,version', */
          /*     ], */
          /*     'body' => Json::encode($request_payload), */
          /*   ] */
          /* ); */
          /* $response = Json::decode($res->getBody()); */
      // ambil nomor versi bersadarkan id_linked_content

      // WARNING!!
      //
      // berdasarkan dokumentasi Confluence-Question, tidak ada API untuk 
      // melakukan update question. Perlu dipikirkan jalan keluarnya. Apakah
      // thread dikirim ke Confluence-Question saat thread pindah status dari
      // "new" menjadi "publish" ??
      //
      /* // update content */
      /* $request_payload = [ */
      /*   'version' => [ */
      /*     'number' => $version */
      /*   ], */
      /*   'title' => $payload['judul'], */
      /*   'type' => 'page', */
      /*   'space' => [ */
      /*     'key' => 'PS', */
      /*   ], */
      /*   'body' => [ */
      /*     'storage' => [ */
      /*       'value' => $payload['body'], */
      /*       'representation' => 'storage', */
      /*     ], */
      /*   ], */
      /* ]; */


      /* $res = null; */
      /* try */
      /* { */
      /*   // update kontent artikel pada confluence */
      /*   $res = $client->request( */
      /*     'PUT', */
      /*     "/rest/api/content/{$artikel["linked_id_question"]}", */
      /*     [ */
      /*       /1* 'sink' => Yii::$app->basePath . "/guzzledump.txt", *1/ */
      /*       /1* 'debug' => true, *1/ */
      /*       'http_errors' => false, */
      /*       'headers' => [ */
      /*         "Content-Type" => "application/json", */
      /*         "accept" => "application/json", */
      /*       ], */
      /*       'auth' => [ */
      /*         $jira_conf["user"], */
      /*         $jira_conf["password"] */
      /*       ], */
      /*       'query' => [ */
      /*         'status' => 'current', */
      /*       ], */
      /*       'body' => Json::encode($request_payload), */
      /*     ] */
      /*   ); */

      /*   switch( $res->getStatusCode() ) */
      /*   { */
      /*     case 200: */
      /*       // ambil id dari result */
      /*       $response_payload = $res->getBody(); */
      /*       $response_payload = Json::decode($response_payload); */

      /*       $linked_id_question = $response_payload['id']; */



    }
    else
    {
      return [
        'status' => 'not ok',
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, konten, id_kategori, tsgs',
      ];
    }
  }

  /* Menghapus tags dari suatu thread.
   *
   * Tetapi berdasarkan dokumentasi Confluence-Question, tidak ada API untuk
   * menghapus tags (topic) dari suatu thread.
   * Apakah tags akan diterapkan secara eksklusif di dalam SPBE?
   *
    * */
  private function DeleteTags($client, $jira_conf, $linked_id_question)
  {
    $res = $client->request(
      'GET',
      "/rest/api/content/{$linked_id_question}/label",
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
        "/rest/api/content/{$linked_id_question}/label/{$object["name"]}",
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

  private function UpdateTags($client, $jira_conf, $id_thread, $linked_id_question, $payload)
  {

    //hapus label pada spbe
        ForumThreadTag::deleteAll("id_thread = {$id_thread}");
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

      // relate id_thread dengan id_tag
      $new = new ForumThreadTag();
      $new["id_thread"] = $id_thread;
      $new["id_tag"] = $id_tag;
      $new->save();

    } // loop tags

    // kirim tag ke Confluence
    /* $res = $client->request( */
    /*   'POST', */
    /*   "/rest/api/content/{$linked_id_question}/label", */
    /*   [ */
    /*     /1* 'sink' => Yii::$app->basePath . "/guzzledump.txt", *1/ */
    /*     /1* 'debug' => true, *1/ */
    /*     'http_errors' => false, */
    /*     'headers' => [ */
    /*       "Content-Type" => "application/json", */
    /*       "accept" => "application/json", */
    /*     ], */
    /*     'auth' => [ */
    /*       $jira_conf["user"], */
    /*       $jira_conf["password"] */
    /*     ], */
    /*     'body' => Json::encode($tags), */
    /*   ] */
    /* ); */
  }

  /*
   *  Mengambil forum_thread_activity_log berdasarkan filter yang dapat disetup secara dinamis.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "object_type": "t/u",
   *    "filter":
   *    {
   *      "waktu_awal"    : "y-m-j H:i:s",
   *      "waktu_akhir"   : "y-m-j H:i:s",
   *      "actions"       : [1, 2, ...],
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
        $q->join("join", "forum_thread_activity_log l", "l.id_user = u.id");
        break;

      case $payload["object_type"] == "t" :
        $q->select("t.id");
        $q->from("forum_thread t");
        $q->join("join", "forum_thread_activity_log l", "l.id_thread = t.id");
        break;

      default :
        return [
          "status" => "not ok",
          "pesan" => "Parameter tidak valid. object_type diisi dengan 'a' atau 'u'.",
        ];
        break;
    }

    Yii::info("filter = " . print_r($payload["filter"], true));

    $where[] = "and";
    foreach( $payload["filter"] as $key => $value )
    {
      switch(true)
      {
        case $key == "tanggal_awal":
          $value = date("Y-m-d 00:00:00", $tanggal_awal->timestamp);
          $where[] = "l.time_action >= '$value'";
        break;

        case $key == "tanggal_akhir":
          $value = date("Y-m-d 23:59:59", $tanggal_akhir->timestamp);
          $where[] = "l.time_action <= '$value'";
        break;

        case $key == "actions":
          $temp = [];
          foreach( $value as $type_action )
          {
            $temp[] = $type_action["action"];
          }
          $where[] = ["in", "l.action", $temp];
        break;

        case $key == "id_kategori":
          $q->join("join", "forum_thread t2", "t2.id = l.id_thread");

          $temp = [];
          foreach( $value as $id_kategori )
          {
            $temp[] = $id_kategori;
          }
          $where[] = ["in", "t2.id_kategori", $temp];
        break;

        case $key == "id_thread":
          $where[] = "l.id_thread = " . $value;
        break;
      }// switch filter key
    } //loop keys in filter

    $q->where($where);

    if( $payload["object_type"] == 't' )
    {
      $q->distinct()
        ->groupBy("t.id");
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
      if( $payload["object_type"] == 't' )
      {
        $thread = ForumThread::findOne($record["id"]);
        $user = User::findOne($thread["id_user_create"]);

        $response = $this->Conf_GetQuestion($client, $thread["linked_id_question"]);
        $response_payload = $response->getBody();

        $temp = [];
        $temp["forum_thread"] = $thread;
        $temp["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
        $temp["data_user"]["user_create"] = $user;
        $temp["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
        try
        {
          $response_payload = Json::decode($response_payload);

          $temp["confluence"]["id"] = $response_payload["id"];
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = $response_payload["body"]["content"];
        }
        catch(yii\base\InvalidArgumentException $e)
        {
          $temp["confluence"] = "Record not found";
        }



        // filter by action
        // ================
            // berapa banyak action yang diterima suatu artikel dalam rentang waktu tertentu?

            //ambil data view
            $type_action = -1;
            $temp["view"] = ForumThread::ActionReceivedInRange($thread["id"], $type_action, $tanggal_awal, $tanggal_akhir);
            
            //ambil data like
            $type_action = 1;
            $temp["like"] = ForumThread::ActionReceivedInRange($thread["id"], $type_action, $tanggal_awal, $tanggal_akhir);

            //ambil data dislike
            $type_action = 2;
            $temp["dislike"] = ForumThread::ActionReceivedInRange($thread["id"], $type_action, $tanggal_awal, $tanggal_akhir);
        // ================
        // filter by action

        // filter by status
        // ================
            // apakah suatu artikel mengalami status tertentu dalam rentang waktu?

            //ambil data draft
            $type_status = -1;
            $temp["draft"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data new
            $type_status = 0;
            $temp["new"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data publish
            $type_status = 1;
            $temp["publish"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data un-publish
            $type_status = 2;
            $temp["unpublish"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data reject
            $type_status = 3;
            $temp["reject"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data freeze
            $type_status = 4;
            $temp["freeze"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

        // ================
        // filter by status

        $is_valid = true;
        foreach($payload["filter"]["actions"] as $action)
        {
          switch(true)
          {
          case $action["action"] == -1:
            if($action["min"] <= $temp["view"] && $action["max"] >= $temp["view"])
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $action["action"] == 1:
            if($action["min"] <= $temp["like"] && $action["max"] >= $temp["like"])
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $action["action"] == 2:
            if($action["min"] <= $temp["dislike"] && $action["max"] >= $temp["dislike"])
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;
          }
        } // loop check action

        foreach($payload["filter"]["status"] as $status)
        {
          switch(true)
          {
          case $status == -1:  //draft
            if($temp["draft"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $status == 0:  // new
            if($temp["new"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $status == 1:  // publish
            if($temp["publish"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;
          case $status == 2:  // unpublish
            if($temp["unpublish"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;
          case $status == 3:  // reject
            if($temp["reject"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;
          case $status == 4:  // freeze
            if($temp["freeze"] > 0)
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

        //ambil data view
        $type_action = -1;
        $temp["view"] = ForumThread::ActionByUserInRange($user["id"], $type_action, $tanggal_awal, $tanggal_akhir);
        
        //ambil data like
        $type_action = 1;
        $temp["like"] = ForumThread::ActionByUserInRange($user["id"], $type_action, $tanggal_awal, $tanggal_akhir);

        //ambil data dislike
        $type_action = 2;
        $temp["dislike"] = ForumThread::ActionByUserInRange($user["id"], $type_action, $tanggal_awal, $tanggal_akhir);

        $is_valid = true;
        foreach($payload["filter"]["actions"] as $action)
        {
          switch(true)
          {
          case $action["action"] == -1:
            if($action["min"] <= $temp["new"] && $action["max"] >= $temp["new"])
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $action["action"] == 1:
            if($action["min"] <= $temp["like"] && $action["max"] >= $temp["like"])
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $action["action"] == 2:
            if($action["min"] <= $temp["dislike"] && $action["max"] >= $temp["dislike"])
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;
          }
        } // loop check action

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
   *  Mengambil forum_thread atau user berdasarkan filter yang dapat disetup secara dinamis.
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
   *                "forum_thread": { object_of_record_artikel },
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
   *        thread-thread dari suatu kategori
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
   *        Mengambil daftar thread dari suatu kategori dan mengalami STATUS
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
   *                "forum_thread": { object_of_record_artikel },
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
              ->from("forum_thread t")
              ->join("JOIN", "forum_thread_activity_log l", "l.id_thread = t.id");

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
              ->join("JOIN", "forum_thread_activity_log l", "l.id_user = u.id")
              ->join("JOIN", "forum_thread t", "l.id_thread = t.id");

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
          $thread = ForumThread::findOne($record["id"]);
          $category_path = KmsKategori::CategoryPath($record["id_kategori"]);
          $tags = ForumThreadTag::GetThreadTags($thread["id"]);
          $user_create = User::findOne($thread["id_user_create"]);
          $response = $this->Conf_GetQuestion($client, $thread["linked_id_question"]);
          $response_payload = $response->getBody();
          $response_payload = Json::decode($response_payload);

          $temp = [];
          $temp["record"]["forum_thread"] = $thread;
          $temp["record"]["user_create"] = $user_create;
          $temp["record"]["category_path"] = $category_path;
          $temp["record"]["tags"] = $tags;
          $temp["confluence"]["status"] = "ok";
          $temp["confluence"]["linked_id_question"] = $response_payload["id"];
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
   *  Mengambil daftar thread berdasarkan idkategori, page_no, items_per_page.
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
   *            <object dari record thread>
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
    $is_kategori_valid = isset($payload["id_kategori"]);
    $is_page_no_valid = isset($payload["page_no"]);
    $is_items_per_page_valid = isset($payload["items_per_page"]);

    $is_kategori_valid = $is_kategori_valid && is_array($payload["id_kategori"]);
    $is_page_no_valid = $is_page_no_valid && is_numeric($payload["page_no"]);
    $is_items_per_page_valid = $is_items_per_page_valid && is_numeric($payload["items_per_page"]);

    if(
        $is_kategori_valid == true &&
        $is_page_no_valid == true &&
        $is_items_per_page_valid == true
      )
    {
      //  lakukan query dari tabel forum_thread
      $test = ForumThread::find()
        ->where([
          "and",
          "is_delete = 0",
          "status = 1",
          ["in", "id_kategori", $payload["id_kategori"]]
        ])
        ->orderBy("time_create desc")
        ->all();
      $total_rows = count($test);

      $list_thread = ForumThread::find()
        ->where([
          "and",
          "is_delete = 0",
          "status = 1",
          ["in", "id_kategori", $payload["id_kategori"]]
        ])
        ->orderBy("time_create desc")
        ->offset( $payload["items_per_page"] * ($payload["page_no"] - 1) )
        ->limit( $payload["items_per_page"] )
        ->all();

      //  lakukan query dari Confluence
      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $client = $this->SetupGuzzleClient();

      $hasil = [];
      foreach($list_thread as $thread)
      {
        $user = User::findOne($thread["id_user_create"]);
        $jawaban = ForumThreadDiscussion::findAll(
          "id_thread = :id and is_delete = 0",
          [":id" => $thread["id"]]
        );

        $res = $client->request(
          'GET',
          "/rest/questions/1.0/question/{$thread["linked_id_question"]}",
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
              'spaceKey' => 'PS',
              'expand' => 'history,body.view'
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

            $temp = [];
            $temp["forum_thread"] = $thread;
            $temp["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
            $temp["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
            $temp["user_create"] = $user;
            $temp['data_user']['user_create'] = $user->nama;
            $temp["user_actor_status"] = ForumThreadUserAction::GetUserAction($payload["id_thread"], $payload["id_user_actor"]);
            $temp["jawaban"]["count"] = count($jawaban);
            $temp["confluence"]["status"] = "ok";
            $temp["confluence"]["linked_id_question"] = $response_payload["id"];
            $temp["confluence"]["judul"] = $response_payload["title"];
            $temp["confluence"]["konten"] = $response_payload["body"]["content"];

            $hasil[] = $temp;

            $response_payload = [];
            break;

          default:
            // kembalikan response
            $temp = [];
            $temp["forum_thread"] = $thread;
            $temp["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
            $temp["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
            // $hasil["user_create"] = $user;
            $temp["confluence"]["status"] = "not ok";
            $temp["confluence"]["judul"] = $response_payload["title"];
            $temp["confluence"]["konten"] = $response_payload["body"]["content"];
            $temp['data_user']['user_create'] = $user->nama;

            $hasil[] = $temp;

            $response_payload = [];
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
          "count" => count($list_thread),
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
   *  Mengambil record thread berdasarkan id_thread
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_thread": 123,
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
   *        "forum_thread":
   *        {
   *          <object dari record forum_thread>
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
    $is_id_thread_valid = isset($payload["id_thread"]);

    if(
        $is_id_thread_valid == true
      )
    {
      //  lakukan query dari tabel kms_artikel
      $thread = ForumThread::findOne($payload["id_thread"]);

      $user = User::findOne($thread["id_user_create"]);
      $temp_list_komentar = ForumThreadComment::find()
        ->where("id_thread = :id", [":id" => $payload["id_thread"]])
        ->orderBy("time_create asc")
        ->all();

      $list_komentar = [];
      foreach($temp_list_komentar as $komentar_item)
      {
        $user = User::findOne($komentar_item["id_user_create"]);

        $temp = [];
        $temp["record"] = $komentar_item;
        $temp["user_create"] = $user;

        $list_komentar[] = $temp;
      }


      $list_jawaban = ForumThreadDiscussion::find()
        ->where(
          [
            "and",
            "id_thread = :id_thread",
            "is_delete = 0"
          ],
          [":id_thread" => $thread["id"]]
        )
        ->orderBy("time_create asc")
        ->all();

      $jawaban = [];
      foreach($list_jawaban as $item_jawaban)
      {
        $list_komentar_jawaban = ForumThreadDiscussionComment::find()
          ->where("id_discussion = :id", [":id" => $item_jawaban["id"]])
          ->orderBy("time_create asc")
          ->all();

        $temp = [];
        foreach($list_komentar_jawaban as $item_komentar)
        {
          $user = User::findOne($item_komentar["id_user_create"]);

          $temp[] = [
            "komentar" => $item_komentar,
            "user_create" => $user,
          ];
        }

        $user = User::findOne($item_jawaban["id_user_create"]);
        $jawaban[] = [
          "jawaban" => $item_jawaban,
          "user_create" => $user,
          "list_komentar" => $temp,
        ];
      }

      //  lakukan query dari Confluence
      $client = $this->SetupGuzzleClient();
      $jira_conf = Yii::$app->restconf->confs['confluence'];

      $hasil = [];
      $res = $client->request(
        'GET',
        "/rest/questions/1.0/question/{$thread["linked_id_question"]}",
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
            'spaceKey' => 'PS',
            'expand' => 'history,body.view'
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
        $hasil["record"]["forum_thread"] = $thread;
        $hasil["record"]["thread_comments"] = $list_komentar;
        $hasil["record"]["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
        $hasil["record"]["user_create"] = $user;
        $hasil["record"]["user_actor_status"] = ForumThreadUserAction::GetUserAction($payload["id_thread"], $payload["id_user_actor"]);
        $hasil["record"]["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
        $hasil["record"]["confluence"]["status"] = "ok";
        $hasil["record"]["confluence"]["linked_id_question"] = $response_payload["id"];
        $hasil["record"]["confluence"]["judul"] = $response_payload["title"];
        $hasil["record"]["confluence"]["konten"] = $response_payload["body"]["content"];
        $hasil["jawaban"]["count"] = count($jawaban);
        $hasil["jawaban"]["records"] = $jawaban;

        $this->ThreadLog($thread["id"], $payload["id_user_actor"], 2, -1);
        break;

      default:
        // kembalikan response
        $hasil = [];
        $hasil["record"]["forum_thread"] = $thread;
        $hasil["record"]["thread_comments"] = $list_komentar;
        $hasil["record"]["user_create"] = $user;
        $hasil["record"]["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
        $hasil["record"]["confluence"]["status"] = "not ok";
        $hasil["record"]["confluence"]["judul"] = $response_payload["title"];
        $hasil["record"]["confluence"]["konten"] = $response_payload["body"]["content"];
        break;
      }

      return [
        "status" => "ok",
        "pesan" => "Query finished",
        "result" => $hasil
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
   *  Menyimpan action antara user dan thread. Apakah si user menyatakan like,
   *  dislike terhadap suatu thread. Informasi disimpan pada tabel forum_thread_user_action
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_thread": 123,
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
   *      <object record forum_thread_user_action >
   *    }
   *  }
    * */
  public function actionThreaduseraction()
  {
    $payload = $this->GetPayload();

    // cek apakah parameter lengkap
    $is_id_thread_valid = isset($payload["id_thread"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_action_valid = isset($payload["action"]);

    if(
        $is_id_thread_valid == true &&
        $is_id_user_valid == true &&
        $is_action_valid == true
      )
    {
      // memastikan id_thread dan id_user valid
      $test = ForumThread::findOne($payload["id_thread"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Thread's record not found",
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
      $test = ForumThreadUserAction::find()
        ->where(
          [
            "and",
            "id_thread = :idthread",
            "id_user = :iduser"
          ],
          [
            ":idthread" => $payload["id_thread"],
            ":iduser" => $payload["id_user"],
          ]
        )
        ->one();

      if( is_null($test) == true )
      {
        $test = new ForumThreadUserAction();
      }

      if( $test["action"] != $payload["action"] )
      {
        //  Aktifitas akan direkam jika mengakibatkan perubahan status pada
        //  thread.

        $test["id_thread"] = $payload["id_thread"];
        $test["id_user"] = $payload["id_user"];
        $test["action"] = $payload["action"];
        $test->save();

        // tulis log
        $this->ThreadLog($payload["id_thread"], $payload["id_user"], 2, $payload["action"]);
        $thread = ForumThread::findOne($payload["id_thread"]);
        $jawaban = ForumThreadDiscussion::findAll(
          "id_thread = :id and is_delete = 0", 
          [":id" => $payload["id_thread"]]
        );

        // kembalikan response
        return [
          "status" => "ok",
          "pesan" => "Action saved. Log saved.",
          "result" => 
          [
            "forum_thread_user_action" => $test,
            "forum_thread" => $thread,
            "jumlah_jawaban" => count($jawaban),
          ]
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
        "pesan" => "Parameter yang dibutuhkan tidak lengkap: id_thread, id_user, action",
      ];
    }


  }

  /*
   *  Mengganti id_kategori atas suatu thread. Kemudian penyimpan jejak perubahan
   *  ke dalam tabel forum_thread_log
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_thread": 123,
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
   *      <object record thread>
   *    }
   *  }
    * */
  public function actionItemkategori()
  {
    $payload = $this->GetPayload();

    // cek apakah parameter lengkap
    $is_id_thread_valid = isset($payload["id_thread"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_id_kategori_valid = isset($payload["id_kategori"]);

    if(
        $is_id_thread_valid == true &&
        $is_id_user_valid == true &&
        $is_id_kategori_valid == true
      )
    {
      // memastikan id_thread, id_kategori dan id_user valid
      $test = ForumThread::findOne($payload["id_thread"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Thread's record not found",
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

      // update kms_thread
      $thread = ForumThread::findOne($payload["id_thread"]);
      $thread["id_kategori"] = $payload["id_kategori"];
      $thread->save();

      //  simpan history pada tabel forum_thread_activity_log
      /* $log = new KmsArtikelActivityLog(); */
      /* $log["id_artikel"] = $payload["id_artikel"]; */
      /* $log["id_user"] = $payload["id_user"]; */
      /* $log["type_action"] = $payload["status"]; */
      /* $log["time_action"] = date("Y-m-j H:i:s"); */
      /* $log->save(); */

      return [
        "status" => "ok",
        "pesan" => "Kategori thread sudah disimpan",
        "result" => $thread,
        "category_path" => KmsKategori::CategoryPath($thread["id_kategori"])
      ];
    }
    else
    {
      // kembalikan response
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak lengkap: id_thread, id_kategori, id_user",
      ];
    }



  }

  /*
   *  Mencari thread berdasarkan daftar id_kategori dan keywords. Search keywords
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
          $keywords .= " ";
        }

        $keywords .= "$keyword";
      }

      //ambil daftar linked_id_question berdasarkan array id_kategori
      $daftar_thread = ForumThread::find()
        ->where(
          [
            "id_kategori" => $payload["id_kategori"],
            "is_delete" => 0,
            "status" => 1
          ]
        )
        ->all();

      $daftar_id = "";
      foreach($daftar_thread as $thread)
      {
        if($daftar_id != "")
        {
          $daftar_id .= ", ";
        }

        $daftar_id .= $thread["linked_id_question"];
      }
      $daftar_id = "ID IN ($daftar_id)";

      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $client = $this->SetupGuzzleClient();

      $res = $client->request(
        'GET',
        "/rest/questions/1.0/search",
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
            'type' => "question",
            'query' => "$keywords",
            'start' => $payload["items_per_page"] * ($payload["page_no"] - 1),
            'limit' => $payload["items_per_page"],
            'spaceKey' => "PS",
          ],
        ]
      );

      switch( $res->getStatusCode() )
      {
      case 200:
        $response_payload = $res->getBody();
        $response_payload = Json::decode($response_payload);

        $hasil = array();
        foreach($response_payload["results"] as $item)
        {
          $thread = ForumThread::find()
            ->where(
              [
                "linked_id_question" => $item["id"]
              ]
            )
            ->one();
          $user = User::findOne($thread["id_user_create"]);

          $cq_response = $this->Conf_GetQuestion($client, $thread["linked_id_question"]);
          $cq_response_payload = $cq_response->getBody();
          $cq_response_payload = Json::decode($cq_response_payload);

          $temp = array();
          $temp["forum_thread"] = $thread;
          $temp["user_create"] = $user->nama;
          $temp["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);

          $temp["confluence"]["status"] = "ok";
          $temp["confluence"]["linked_id_question"] = $item["id"];
          $temp["confluence"]["judul"] = $cq_response_payload["title"];
          $temp["confluence"]["konten"] = $cq_response_payload["body"]["content"];

          $hasil[] = $temp;
        }

        return [
          "status" => "ok",
          "pesan" => "Search berhasil",
          "result" => 
          [
            "total_rows" => count($response_payload["results"]),
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
          'result' => $thread,
          'category_path' => KmsKategori::CategoryPath($thread["id_kategori"])
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
   *  Mengubah status suatu thread.
   *  Status artikel:
   *  -1 = draft
   *  0 = new
   *  1 = publish
   *  2 = un-publish
   *  3 = reject
   *  4 = freeze
   *  5 = knowledge
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_thread": [123, 124, ...],
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

    $is_id_thread_valid = isset($payload["id_thread"]);
    $is_id_thread_valid = $is_id_thread_valid && is_array($payload["id_thread"]);
    $is_status_valid = isset($payload["status"]);
    $is_status_valid = $is_status_valid && is_numeric($payload["status"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_id_user_valid = $is_id_user_valid && is_numeric($payload["id_user"]);

    if(
        $is_id_thread_valid == true &&
        $is_status_valid == true &&
        $is_id_user_valid == true
      )
    {
      $daftar_sukses = [];
      $daftar_gagal = [];
      foreach($payload["id_thread"] as $id_thread)
      {
        if( is_numeric($id_thread) )
        {
          if( is_numeric($payload["id_user"]) )
          {
            //cek validitas id_user
            $test = User::findOne($payload["id_user"]);

            if( is_null($test) == false )
            {
              $thread = ForumThread::findOne($id_thread);
              if( is_null($thread) == false )
              {
                $thread["status"] = $payload["status"];
                $thread->save();

                $daftar_sukses[] = $thread;

                // tulis log
                $this->ThreadLog($id_thread, $payload["id_user"], 1, $payload["status"]);
              }
              else
              {
                $daftar_gagal[] = $id_thread;
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
          $daftar_gagal[] = $id_thread;
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
        "pesan" => "Parameter yang diperlukan tidak ada: id_thread (array), status",
      ];
    }
  }

  /*
   *  Mengambil daftar thread berdasarkan kesamaan tags yang berasal dari
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
   *        "forum_thread":
   *        {
   *          <object dari record forum_thread>
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
          ->join("JOIN", "forum_thread_tag atag", "atag.id_tag = t.id")
          ->join("JOIN", "forum_thread f", "f.id = atag.id_thread")
          ->where(["in", "f.id_kategori", $payload["id_kategori"]])
          ->distinct()
          ->all();

      $daftar_tag = [];
      foreach($temp_daftar_tag as $item)
      {
        $daftar_tag[] = $item["id"];
      }

      // mengambil daftar thread terkait
      $q = new Query();
      $daftar_thread = 
        $q->select("t.*")
          ->from("forum_thread t")
          ->join("JOIN", "forum_thread_tag atag", "atag.id_thread = t.id")
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
      foreach($daftar_thread as $record)
      {
        $user = User::findOne($record["id_user_create"]);

        //  lakukan query dari Confluence
        $jira_conf = Yii::$app->restconf->confs['confluence'];
        $client = $this->SetupGuzzleClient();

        $res = $client->request(
          'GET',
          "/rest/questions/1.0/question/{$record["linked_id_question"]}",
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

          $temp["forum_thread"] = $record;
          $temp["user_create"] = $user;
          $temp["category_path"] = KmsKategori::CategoryPath($record["id_kategori"]);
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = $response_payload["body"]["content"];
        }
        catch(yii\base\InvalidArgumentException $e)
        {
          $temp["forum_thread"] = $record;
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
          ->join("JOIN", "forum_thread_tag atag", "atag.id_tag = t.id")
          ->join("JOIN", "forum_thread f", "f.id = atag.id_thread")
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
          ->join("JOIN", "forum_thread f", "f.id_kategori = k.id")
          ->join("JOIN", "forum_thread_tag atag", "atag.id_thread = f.id")
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
   *        "new": 123,
   *        "publish": 123,
   *        "unpublish": 123,
   *        "reject": 123,
   *        "freeze": 123,
   *        "like": 123,
   *        "dislike": 123,
   *        "neutral": 123,
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
          ->from("forum_thread_activity_log log")
          ->andWhere("time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $temp_date->timestamp)])
          ->andWhere("time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $temp_date->timestamp)])
          ->distinct()
          ->groupBy("log.status")
          ->orderBy("log.status asc")
          ->all();

        $q = new Query();
        $hasil_action = $q->select("log.action, count(log.id) as jumlah")
          ->from("forum_thread_activity_log log")
          ->andWhere("time_action >= :awal", [":awal" => date("Y-m-j 00:00:00", $temp_date->timestamp)])
          ->andWhere("time_action <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $temp_date->timestamp)])
          ->distinct()
          ->orderBy("log.action")
          ->orderBy("log.action asc")
          ->all();

        $temp = [];
        $temp["tanggal"] = date("Y-m-d", $temp_date->timestamp);

        foreach($hasil_status as $record)
        {
          switch( $record["status"] )
          {
            case -1: //draft
              $temp["data"]["draft"] = $record["jumlah"];
              break;

            case 0: //new
              $temp["data"]["new"] = $record["jumlah"];
              break;

            case 1: //publish
              $temp["data"]["publish"] = $record["jumlah"];
              break;

            case 2: //unpublish
              $temp["data"]["unpublish"] = $record["jumlah"];
              break;

            case 3: //reject
              $temp["data"]["reject"] = $record["jumlah"];
              break;

            case 4: //freeze
              $temp["data"]["freeze"] = $record["jumlah"];
              break;

            case 5: //knowledge
              $temp["data"]["knowledge"] = $record["jumlah"];
              break;
          }
        }

        foreach($hasil_action as $record)
        {
          switch( $record["action"] )
          {
            case -1: //view
              $temp["data"]["view"] = $record["jumlah"];
              break;

            case 0: //neutral
              $temp["data"]["neutral"] = $record["jumlah"];
              break;

            case 1: //like
              $temp["data"]["like"] = $record["jumlah"];
              break;

            case 2: //dislike
              $temp["data"]["dislike"] = $record["jumlah"];
              break;
          }
        }

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
   *          "new" :
   *          {
   *            "artikel": 123,
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
      // ambil jumlah thread per kejadian (new, publish, .., freeze)
      $q = new Query();
      $total_thread = 
        $q->select("t.id")
          ->from("forum_thread t")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_thread = t.id")
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
          ->groupBy("t.id")
          ->all();
      $total_thread = count($total_thread);

      $q = new Query();
      $thread_status = 
        $q->select("log.status, count(t.id) as jumlah")
          ->from("forum_thread t")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_thread = t.id")
          ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status")
          ->all();

      // ambil jumlah thread per action (like/dislike)
      $q = new Query();
      $thread_action = 
        $q->select("log.action, t.id")
          ->from("forum_thread t")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_thread = t.id")
          ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 2")
          ->andWhere("log.time_action >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_action <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.action asc")
          ->groupBy("log.action, t.id")
          ->all();

      // ambil jumlah user per kejadian (new, publish, .., freeze)
      $q = new Query();
      $total_user = 
        $q->select("u.id")
          ->from("user u")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "forum_thread t", "log.id_thread = t.id")
          ->where(
            [
              "and",
              "t.id_kategori = :id_kategori",
              "log.type_log = 1",
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
            ],
            [
              ":id_kategori" => $kategori["id"],
              ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp),
              ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)
            ]
          )
          /* ->andWhere("a.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]]) */
          /* ->andWhere("log.type_log = 1") */
          /* ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)]) */
          /* ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)]) */
          /* ->orderBy("log.status asc") */
          /* ->groupBy("u.id") */
          ->distinct()
          ->all();
      $total_user = count($total_user);

      $q = new Query();
      $user_status = 
        $q->select("log.status, count(u.id) as jumlah")
          ->from("user u")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "forum_thread t", "log.id_thread = t.id")
          ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status")
          ->all();

      // ambil jumlah thread per action (like/dislike)
      $q = new Query();
      $user_action = 
        $q->select("log.action, u.id")
          ->from("user u")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "forum_thread t", "log.id_thread = t.id")
          ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 2")
          ->andWhere("log.time_action >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_action <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.action asc")
          ->groupBy("log.action, u.id")
          ->all();

      $temp = [];
      $indent = "";
      for($i = 1; $i < $kategori["level"]; $i++)
      {
        $indent .= "&nbsp;&nbsp;";
      }
      $index_name = $indent . $kategori["nama"];
      $category_path = KmsKategori::CategoryPath($kategori["id"]);

      $temp["total"]["thread"] = $total_thread;
      $temp["total"]["user"] = $total_user;

      foreach($thread_status as $record)
      {
        switch( $record["status"] )
        {
          case 0: //new
            $temp["new"]["thread"] = $record["jumlah"];
            break;

          case 1: //publish
            $temp["publish"]["thread"] = $record["jumlah"];
            break;

          case 2: //unpublish
            $temp["unpublish"]["thread"] = $record["jumlah"];
            break;

          case 3: //reject
            $temp["reject"]["thread"] = $record["jumlah"];
            break;

          case 4: //freeze
            $temp["freeze"]["thread"] = $record["jumlah"];
            break;
        }
      }

      $temp["neutral"]["thread"] = 0;
      $temp["like"]["thread"] = 0;
      $temp["dislike"]["thread"] = 0;
      $temp["view"]["thread"] = 0;
      foreach($thread_action as $record)
      {
        switch( $record["action"] )
        {
          case 0: //neutral
            $temp["neutral"]["thread"]++;
            break;

          case 1: //like
            $temp["like"]["thread"]++;
            break;

          case 2: //dislike
            $temp["dislike"]["thread"]++;
            break;

          case -1: //view
            $temp["view"]["thread"]++;
            break;
        }
      }

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

      $temp["like"]["user"] = 0;
      $temp["neutral"]["user"] = 0;
      $temp["dislike"]["user"] = 0;
      $temp["view"]["user"] = 0;
      foreach($user_action as $record)
      {
        switch( $record["action"] )
        {
          case 0: //neutral
            $temp["neutral"]["user"]++;
            break;

          case 1: //like
            $temp["like"]["user"]++;
            break;

          case 2: //dislike
            $temp["dislike"]["user"]++;
            break;

          case -1: //view
            $temp["view"]["user"]++;
            break;
        }
      }

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
   *  Membuat atau mengedit comment. Comment punya relasi kepada thread atau 
   *  jawaban. Oleh karena itu, request create atau update harus menyertakan
   *  tipe comment (1 = comment of thread; 2= comment of jawaban)
   *
   *  Method : POST
   *  Request type: JSON
   *  Request format:
   *  {
   *    "type": 1/2,
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
      $is_type_valid = isset($payload["type"]);
      $is_id_parent_valid = isset($payload["id_parent"]);
      $is_id_user_valid = isset($payload["id_user"]);
      $is_konten_valid = isset($payload["konten"]);
      
      if(
          $is_type_valid == true &&
          $is_id_parent_valid == true &&
          $is_id_user_valid == true &&
          $is_konten_valid == true
        )
      {
        //cek type
        if( is_numeric($payload["type"]) == false )
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter tidak valid. type (integer)",
          ];
        }
        
        //cek id_parent
        if( is_numeric($payload["id_parent"]) == false )
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter tidak valid. type (integer)",
          ];
        }
        else
        {
          if( $payload["type"] == 1 ) // comment of thread
          {
            $test = ForumThread::findOne($payload["id_parent"]);

            if( is_null($test) == true )
            {
              return [
                "status" => "not ok",
                "pesan" => "id_parent tidak ditemukan",
              ];
            }
          }
          else if( $payload["type"] == 2 ) // comment of jawaban
          {
            $test = ForumThreadDiscussion::findOne($payload["id_parent"]);

            if( is_null($test) == true )
            {
              return [
                "status" => "not ok",
                "pesan" => "id_parent tidak ditemukan",
              ];
            }
          }
          else
          {
            return [
              "status" => "not ok",
              "pesan" => "Parameter tidak valid. type diisi dengan 1 atau 2",
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
        if( $payload["type"] == 1 ) // comment of thread
        {
          $new = new ForumThreadComment();
          $new["id_user_create"] = $payload["id_user"];
          $new["time_create"] = date("Y-m-d H:i:s");
          $new["id_thread"] = $payload["id_parent"];
          $new["judul"] = "---";
          $new["konten"] = $payload["konten"];
          $new->save();

          $user = User::findOne($payload["id_user"]);

          return [
            "status" => "ok",
            "pesan" => "Record comment berhasil dibikin",
            "result" => 
            [
              "record" => $new,
              "user" => $user
            ]
          ];
        }
        else
        {
          // comment of jawaban

          $new = new ForumThreadDiscussionComment();
          $new["id_user_create"] = $payload["id_user"];
          $new["time_create"] = date("Y-m-d H:i:s");
          $new["id_discussion"] = $payload["id_parent"];
          $new["judul"] = "---";
          $new["konten"] = $payload["konten"];
          $new->save();

          $user = User::findOne($payload["id_user"]);

          return [
            "status" => "ok",
            "pesan" => "Record comment berhasil dibikin",
            "result" => 
            [
              "record" => $new,
              "user" => $user
            ]
          ];
        }
        
      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "Parameter yang diperlukan tidak valid: type, id_user, id_parent, konten",
        ];
      }
    }
    else if( Yii::$app->request->isGet )
    {
      // mengambil record comment

      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);

      if( $payload["type"] == 1 ) // comment of thread
      {
        $test = ForumThreadComment::findOne($payload["id"]);

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
        $test = ForumThreadDiscussionComment::findOne($payload["id"]);

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

      if( $payload["type"] == 1 ) // comment of thread
      {
        $test = ForumThreadComment::findOne($payload["id"]);

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
        $test = ForumThreadDiscussionComment::findOne($payload["id"]);

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

      if( $payload["type"] == 1 ) // comment of thread
      {
        $test = ForumThreadComment::findOne($payload["id"]);

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
        $test = ForumThreadDiscussionComment::findOne($payload["id"]);

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
    $thread = null;
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
          $thread = ForumThread::findOne($payload["id_parent"]);

          if( is_null($thread) == true )
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

            // bikin answer draft
                $client = $this->SetupGuzzleClient();
                $jira_conf = Yii::$app->restconf->confs['confluence'];

                $request_payload = [
                  "questionId" => $thread["linked_id_question"],
                  "body" => 
                  [
                    "content" => $payload["konten"],
                    "bodyFormat" => "VIEW",
                  ]
                ];
                $res = $client->request(
                  'POST',
                  "/rest/questions/1.0/question/{$thread["linked_id_question"]}/answerDrafts",
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

                $id_answer_draft = $response_payload["id"];
            // bikin answer draft




            // kirim ke CQ
                $request_payload = [
                  "body" => $payload["konten"],
                  "draftId" => $id_answer_draft,
                  "dateAnswered" => date("Y-m-d"),
                ];

                $res = $client->request(
                  'POST',
                  "/rest/questions/1.0/question/{$thread["linked_id_question"]}/answers",
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
            // kirim ke CQ



            // simpan di SPBE
                $new = new ForumThreadDiscussion();
                $new["id_user_create"] = $payload["id_user"];
                $new["time_create"] = date("Y-m-j H:i:s");
                $new["id_thread"] = $payload["id_parent"];
                $new["linked_id_answer"] = $response_payload["id"];
                $new["judul"] = "---";
                $new["konten"] = $payload["konten"];
                $new->save();
            // simpan di SPBE

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
          "pesan" => "Parameter yang diperlukan tidak valid: id_user, id_parent, konten",
          "payload" => $payload,
        ];
      }
    }
    else if( Yii::$app->request->isGet )
    {
      // mengambil record jawaban

      $is_type_valid = isset($payload["type"]);
      $is_id_valid = isset($payload["id"]);

      $test = ForumThreadDiscussion::findOne($payload["id"]);

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

      $test = ForumThreadDiscussion::findOne($payload["id"]);

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


      $test = ForumThreadDiscussion::findOne($payload["id"]);

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
  // my threads
  // ==========================================================================
  

      //  Mengembalikan daftar thread berdasarkan
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
      //          object of thread
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
          $list_thread = ForumThread::find()
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
          foreach($list_thread as $thread)
          {
            $user = User::findOne($thread["id_user_create"]);

            $temp = [];
            $temp["record"]["forum_thread"] = $thread;
            $temp["record"]["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
            $temp["record"]["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
            $temp["record"]["user_create"] = $user;

            // get record CQ
            $response = $this->Conf_GetQuestion($client, $thread["linked_id_question"]);
            $response_payload = $response->getBody();

            Yii::info("response_payload = " . $response_payload);

            try
            {
              $response_payload = Json::decode($response_payload);

              $temp["confluence"]["status"] = "ok";
              $temp["confluence"]["linked_id_question"] = $response_payload["id"];
              $temp["confluence"]["judul"] = $response_payload["title"];
              $temp["confluence"]["konten"] = $response_payload["body"]["content"];
            }
            catch(yii\base\InvalidParamException $e)
            {
              $temp["confluence"]["status"] = "not ok";
              $temp["confluence"]["response"] = $response_payload;
            }

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
  // my threads
  // ==========================================================================
}
