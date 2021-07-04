<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\base;
use yii\helpers\Json;
use yii\helpers\BaseUrl;
use yii\db\Query;
use yii\web\UploadedFile;

use app\models\Departments;
use app\models\ForumThread;
use app\models\ForumThreadActivityLog;
use app\models\ForumThreadUserAction;
use app\models\ForumThreadTag;
use app\models\ForumThreadFile;
use app\models\ForumThreadDiscussion;
use app\models\ForumThreadDiscussionUserAction;
use app\models\ForumThreadDiscussionSelected;
use app\models\ForumThreadDiscussionFiles;
use app\models\ForumThreadComment;
use app\models\ForumThreadCommentUserAction;
use app\models\ForumThreadDiscussionComment;
use app\models\ForumThreadDiscussionCommentUserAction;
use app\models\ForumThreadHakBaca;
use app\models\KmsArtikel;
use app\models\KmsTags;
use app\models\ForumTags;
use app\models\ForumFiles;
use app\models\User;
use app\models\Roles;
use app\models\KmsKategori;
use app\models\KategoriUser;

use app\helpers\Notifikasi;

use Carbon\Carbon;
use WideImage\WideImage;

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
        'popularitems'          => ['GET'],
        'unfinisheditems'       => ['GET'],
        'othercategories'       => ['GET'],
        'dailystats'            => ['GET'],
        'currentstats'          => ['GET'],
        'comment'               => ['POST', 'GET', 'DELETE', 'PUT'],
        'answer'                => ['POST', 'GET', 'PUT', 'DELETE'],
        'myitems'               => ['GET'],
        'stats'                 => ['GET'],

        'attachments'           => ['POST'],
        'logsbytags'            => ['GET'],
        'categoriesbytags'      => ['GET'],
        'itemtag'               => ['POST'],

        'mtpilihjawaban'        => ['PUT'],
        'hakbacauser'           => ['GET'],
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

      // Membuat record-record yang tidak ada relasinya di
      // database SPBE
      //
      private function SanitasiHasilPencarian($results)
      {
        $hasil = [];

        foreach( $results as $item )
        {
          $test = ForumThread::find()
            ->where([
              "and",
              "linked_id_question = :id",
              "is_delete = 0",
              ["in", "status", [1, 4, -3]]
            ],
            [
              ":id" => $item['id'],
            ]
            )
            ->one();

          if( is_null($test) == false )
          {
            $hasil[] = $item;
          }
        }

        return $hasil;
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
  //    "hak_baca_user": [],
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

    Yii::info("payload = " . Json::encode($payload));

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

      // =========================================================
      // validasi list_hak_baca_user
      // =========================================================
      $tipe_hak_baca = 2;  // hak baca by user
      if( isset( $payload["hak_baca_user"] ) == true )
      {
        if( is_array( $payload["hak_baca_user"] ) == true )
        {
          foreach($payload["hak_baca_user"] as $id_user)
          {
            $test = User::findOne($id_user);

            if( is_null($test) == true )
            {
              $tipe_hak_baca = 1;  // hak baca by kategori. list_hak_baca_user diabaikan.
            }
          }
        }
        else
        {
          // list_hak_baca_user bukan array, maka hak baca = per kategori

          $tipe_hak_baca = 1; // hak baca by kategori
        }
      }
      else
      {
        // list_hak_baca_user tidak terdefinisi, maka hak baca = per kategori

        $tipe_hak_baca = 1; // hak baca by kategori
      }


      $thread = new ForumThread();
      $thread["judul"] = $payload["judul"];
      $thread["konten"] = htmlentities($payload["body"], ENT_QUOTES);
      $thread["linked_id_question"] = -1;
      $thread['time_create'] = date("Y-m-j H:i:s");
      $thread['id_user_create'] = $payload['id_user'];
      $thread['id_kategori'] = $payload['id_kategori'];
      $thread['tipe_hak_baca'] = $tipe_hak_baca;
      $thread['status'] = $payload["status"];
      $thread->save();
      $id_thread = $thread->primaryKey;

      $client = $this->SetupGuzzleClient();
      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $this->UpdateTags($client, $jira_conf, $id_thread, null, $payload);

      if( isset($payload["id_files"]) == true )
      {
        if( is_array( $payload["id_files"] ) == true )
        {
          $this->UpdateFiles($id_thread, $payload);
        }
      }

      $this->UpdateHakBacaUser($id_thread, $payload);

      // ambil ulang tags atas thread ini. untuk menjadi response.
      $tags = ForumThreadTag::find()
        ->where(
          "id_thread = :id_thread",
          [
            ":id_thread" => $id_thread
          ]
        )
        ->all();

      // ambil ulang files atas thread ini. untuk menjadi response.
      $files = ForumThreadFile::find()
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
          "files" => $files,
          "category_path" => KmsKategori::CategoryPath($thread["id_kategori"]),
        ]
      ];
    }
    else
    {
      return [
        'status' => 'not ok',
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, body, id_kategori, tags',
        'request_payload' => $payload
      ];
    }

  }

  private function UpdateHakBacaUser($id_thread, $payload)
  {
    $list_users = $payload["hak_baca_user"];
    $list_users[] = $payload["id_user"]; // menambahkan id_user si pembuat topik

    ForumThreadHakBaca::deleteAll(["id_thread" => $id_thread]);

    foreach($list_users as $id_user)
    {
      $new = new ForumThreadHakBaca();
      $new["id_thread"] = $id_thread;
      $new["id_user"] = $id_user;

      try
      {
        $new->save();
      }
      catch(yii\db\IntegrityException $e)
      {
      }
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
        ["id_thread" => $payload["id_thread"]]
      );

      // panggil POST /rest/api/content

      $tags = [];
      foreach($tag_list as $tag_item)
      {
        $tag = KmsTags::findOne($tag_item["id_tag"]);
        $text = trim($tag["nama"]);
        $text = str_replace(" ", "_", $text);

        $temp = [];
        $temp["name"] = $text;

        $tags[] = $temp;
      }

      /* $thread["konten"] = htmlentities($thread["konten"]); */ 
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
            $thread['status'] = $payload["status"];
            $thread['id_user_update'] = $payload["id_user_actor"];
            $thread['time_update'] = date("Y-m-d H:i:s");
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
              'response_body' => $res,
              'result' => $thread,
              'payload' => $payload,
              'request_payload' => $request_payload,
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
        "payload" => $payload,
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
  //    "id_users": [123, 124, ...],
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
    $hak_baca_user_valid = true;
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

      // hanya draft dan new yang dapat di-edit 
      // draft di-update oleh role user terdaftar
      // new di-update oleh role manager konten
      if( $thread["status"] == -1 ) 
      {
        $thread["judul"] = $payload["judul"];
        $thread["konten"] = htmlentities($payload["body"], ENT_QUOTES);
        $thread["id_kategori"] = $payload["id_kategori"];
        $thread['time_update'] = date("Y-m-j H:i:s");
        $thread['id_user_update'] = $payload["id_user"];
        $thread->save();


        // update daftar hak baca user

          $this->UpdateHakBacaUser($thread["id"], $payload);

        // update daftar hak baca user

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


        // mengupdate daftar files

          $this->UpdateFiles($thread["id"], $payload);

        // mengupdate daftar files



        // kembalikan response
            $tags = ForumThreadTag::findAll(["id_thread" => $thread["id"]]);

            $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

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
      elseif($thread["status"] == 0)
      {
        $thread["judul"] = $payload["judul"];
        $thread["konten"] = htmlentities($payload["body"], ENT_QUOTES);
        $thread["id_kategori"] = $payload["id_kategori"];
        $thread['time_update'] = date("Y-m-j H:i:s");
        $thread['id_user_update'] = $payload["id_user"];
        $thread->save();

        $this->UpdateHakBacaUser($thread["id"], $payload);
        $this->UpdateTags($client, $jira_conf, $thread["id"], $thread["linked_id_question"], $payload);

        // kembalikan response
            $tags = ForumThreadTag::findAll(["id_thread" => $thread["id"]]);

            $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

            return 
            [
              'status' => 'ok',
              'pesan' => 'Record thread telah diupdate',
              'payload' =>$payload,
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
    }
    else
    {
      return [
        'status' => 'not ok',
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, konten, id_kategori, tsgs',
        "payload" => $payload,
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

  private function UpdateFiles($id_thread, $payload)
  {
    ForumThreadFile::deleteAll("id_thread = :id_thread", [":id_thread" => $id_thread]);

    foreach( $payload["id_files"] as $item_file )
    {
      // cek recordnya
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
          // pasangkan file dengan thread
          $new = new ForumThreadFile();
          $new["id_thread"] = $id_thread;
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

    // tanggal awal dan tanggal akhir
        $value_awal = date("Y-m-d 00:00:00", $tanggal_awal->timestamp);
        $value_akhir = date("Y-m-d 23:59:59", $tanggal_akhir->timestamp);
        $where[] = [
          "or", 
          ["and", "l.time_action >= '$value_awal'", "l.time_action <= '$value_akhir'"],
          ["and", "l.time_status >= '$value_awal'", "l.time_status <= '$value_akhir'"],
        ];
    // tanggal awal dan tanggal akhir
       


    // actions and status =====================================================
        $temp_where = [];
        if( count($payload["filter"]["status"]) > 0)
        {
          $temp = [];
          foreach( $payload["filter"]["status"] as $type_status )
          {
            $temp[] = $type_status;
          }

          $temp_where[] = ["in", "l.status", $temp];
        }

        if( count($payload["filter"]["actions"]) > 0)
        {
          $temp = [];
          foreach( $payload["filter"]["actions"] as $type_actions )
          {
            $temp[] = $type_actions["action"];
          }

          $temp_where[] = ["in", "l.action", $temp];
        }

        if( count($temp_where) > 0 )
        {
          if( count($temp_where) == 1 )
          {
            $where[] = $temp_where[0];
          }
          else
          {
            $where[] = ["or", $temp_where[0], $temp_where[1]];
          }
        }
    // actions and status =====================================================


    // id_kategori
        $temp = [];
        $q->join("join", "forum_thread t2", "t2.id = l.id_thread");
        foreach( $payload["filter"]["id_kategori"] as $id_kategori )
        {
          $temp[] = $id_kategori;
        }
        $where[] = ["in", "t2.id_kategori", $temp];
    // id_kategori


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
        $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);
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
          $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
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

            //ambil data rangkumselesai
            $type_status = -3;
            $temp["rangkumselesai"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data rangkumprogress
            $type_status = -2;
            $temp["rangkumprogress"] = ForumThread::StatusInRange($thread["id"], $type_status, $tanggal_awal, $tanggal_akhir);

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
          case $status == -3:  //rangkumselesai
            if($temp["rangkumselesai"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

          case $status == -2:  //rangkumprogress
            if($temp["rangkumprogress"] > 0)
            {
              $is_valid = $is_valid && true;
            }
            else
            {
              $is_valid = $is_valid && false;
            }
            break;

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

          $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

          $temp = [];
          $temp["record"]["forum_thread"] = $thread;
          $temp["record"]["user_create"] = $user_create;
          $temp["record"]["category_path"] = $category_path;
          $temp["record"]["tags"] = $tags;
          $temp["confluence"]["status"] = "ok";
          $temp["confluence"]["linked_id_question"] = $response_payload["id"];
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
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
   *    "id_user": 123,
   *    "id_kategori": [1, 2, ...],
   *    "id_tag": [1, 2, ...],
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
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $payload = $this->GetPayload();

    //  cek parameter
    $is_kategori_valid = isset($payload["id_kategori"]);
    $is_tag_valid = isset($payload["id_tag"]);
    $is_page_no_valid = isset($payload["page_no"]);
    $is_items_per_page_valid = isset($payload["items_per_page"]);

    $is_kategori_valid = $is_kategori_valid && is_array($payload["id_kategori"]);
    $is_tag_valid = $is_tag_valid && is_array($payload["id_tag"]);
    $is_page_no_valid = $is_page_no_valid && is_numeric($payload["page_no"]);
    $is_items_per_page_valid = $is_items_per_page_valid && is_numeric($payload["items_per_page"]);

    if(
        $is_kategori_valid == true &&
        $is_tag_valid == true &&
        $is_page_no_valid == true &&
        $is_items_per_page_valid == true
      )
    {
      $filter_info = [];

      //  lakukan query dari tabel forum_thread
      $active_query = ForumThread::find();

      $where = [];
      $where[] = "and";
      $where[] = "is_delete = 0";
      $where[] = "linked_id_question <> -1";
      $where[] = ["in", "status", [-3, -2, 1, 4]];
      $where[] = ["in", "id_kategori", $payload["id_kategori"]];

      // membuat informasi filter
      $filter_info['by_kategori'] = [];
      foreach( $payload['id_kategori'] as $id_kategori)
      {
        $kategori = KmsKategori::findOne($id_kategori);

        $filter_info['by_kategori'][] = $kategori;
      }


      if( count($payload["id_tag"]) > 0 )
      {
        $where[] = ["in", "ftt.id_tag", $payload["id_tag"]];
        $active_query
          ->join(
            "join", 
            "forum_thread_tag ftt", 
            "ftt.id_thread = forum_thread.id"
          );

        // membuat informasi filter
        $filter_info['by_tag'] = [];
        foreach( $payload['id_tag'] as $id_tag)
        {
          $tag = KmsTags::findOne($id_tag);

          $filter_info['by_tag'][] = $tag;
        }
      }

      $test = $active_query
        ->where($where)
        ->orderBy("time_create desc")
        ->all();

      $total_rows = count($test);
      Yii::info(
        "rows = " . Json::encode($test)
      );

      Yii::info(
        "total_rows = $total_rows"
      );

      $active_query = ForumThread::find();

      if( count($payload["id_tag"]) > 0 )
      {
        $active_query
          ->join(
            "join", 
            "forum_thread_tag ftt", 
            "ftt.id_thread = forum_thread.id"
          );
      }


      $list_thread = $active_query
        ->where($where)
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
        // ====================================================================
        // cek hak baca per user
        // ====================================================================

        
        if(ForumThread::CekHakBaca($thread["id"], $payload["id_user"]) == true)
        {

          $user = User::findOne($thread["id_user_create"]);
          $jawaban = ForumThreadDiscussion::findAll(
            ["id_thread" => $thread["id"], "is_delete" => 0]
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

              $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

              $temp = [];
              $temp["forum_thread"] = $thread;
              $temp["files"] = ForumThreadFile::GetFiles($thread['id']);
              $temp["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
              $temp["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
              $temp["user_create"] = $user;
              $temp['data_user']['user_create'] = $user;
              $temp["user_actor_status"] = ForumThreadUserAction::GetUserAction($thread["id"], $payload["id_user"]);
              $temp["jawaban"]["count"] = count($jawaban);
              $temp["confluence"]["status"] = "ok";
              $temp["confluence"]["linked_id_question"] = $response_payload["id"];
              $temp["confluence"]["judul"] = $response_payload["title"];
              $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
              $temp["data_user"]["departments"] = Departments::NameById($user["id_departments"]);
              $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
              $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

              $hasil[] = $temp;

              $response_payload = [];
              break;

            default:
              // kembalikan response


              $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

              $temp = [];
              $temp["forum_thread"] = $thread;
              $temp["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
              $temp["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
              $temp["user_create"] = $user;
              $temp["confluence"]["status"] = "not ok";
              $temp["confluence"]["judul"] = $response_payload["title"];
              $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
              $temp['data_user']['user_create'] = $user;
              $temp["data_user"]["departments"] = Departments::NameById($user["id_departments"]);
              $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
              $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);
              
              $hasil[] = $temp;

              $response_payload = [];
              break;
          }
        }

        // ====================================================================
        // cek hak baca per user
        // ====================================================================
      } // loop list_thread

      return [
        "status" => "ok",
        "pesan" => "Query finished",
        "result" =>
        [
          "total_rows" => $total_rows,
          "page_no" => $payload["page_no"],
          "items_per_page" => $payload["items_per_page"],
          "count" => count($list_thread),
          "filter_info" => $filter_info,
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

      // =============
      // ambil hak baca yang sudah disimpan
      // =============
      $list_hak_baca = ForumThreadHakBaca::findAll(["id_thread" => $thread["id"]]);


      // =============
      // ambil list komentar
      // =============
      $temp_list_komentar = ForumThreadComment::find()
        ->where("id_thread = :id and is_delete = 0", [":id" => $payload["id_thread"]])
        ->orderBy("time_create asc")
        ->all();

      $list_komentar = [];
      foreach($temp_list_komentar as $komentar_item)
      {
        $temp_user = User::findOne($komentar_item["id_user_create"]);

        $temp = [];
        $temp["record"] = $komentar_item;
        $temp["user_create"] = $temp_user;

        $list_komentar[] = $temp;
      }


      // =============
      // ambil list jawaban
      // =============
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

      // jawaban
        $jawaban = [];
        foreach($list_jawaban as $item_jawaban)
        {
          $item_jawaban["konten"] = html_entity_decode($item_jawaban["konten"], ENT_QUOTES);

          $temp = $this->Get_Jawaban_Komentar($item_jawaban['id']);

          $files = $this->Get_Jawaban_Lampiran($item_jawaban['id']);

          $temp_user = User::findOne($item_jawaban["id_user_create"]);
          $jawaban[] = [
            "jawaban" => $item_jawaban,
            "files" => $files,
            "user_create" => $temp_user,
            "list_komentar" => $temp,
          ];
        }
      // jawaban


      // files
      
        $list_files = ForumThreadFile::findAll(
          ["id_thread" => $thread["id"], "is_delete" => 0]
        );

        $files = [];
        foreach($list_files as $item_file)
        {
          $file = ForumFiles::findOne($item_file["id_file"]);

          $temp = [];
          $temp["ForumFile"] = $file;

          if( preg_match_all("/\.(jpg|jpeg)/i", $file["thumbnail"]) == true )
          {
            $temp["thumbnail"] = BaseUrl::base(true) . "/files/" . $file["thumbnail"];
          }
          else
          {
            $temp["thumbnail"] = BaseUrl::base(true) . "/files/" . "logo_pdf.png";
          }

          $temp["link"] = BaseUrl::base(true) . "/files/" . $file["nama"];

          $files[] = $temp;
        }

      // files

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

      $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

      $hasil = [];
      $hasil["record"]["forum_thread"] = $thread;
      $hasil["record"]["thread_comments"] = $list_komentar;
      $hasil["record"]["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
      $hasil["record"]["user_create"] = $user;
      $hasil["record"]["user_actor_status"] = ForumThreadUserAction::GetUserAction($payload["id_thread"], $payload["id_user_actor"]);
      $hasil["record"]["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
      $hasil["record"]["files"] = $files;
      $hasil["record"]["list_hak_baca"] = $list_hak_baca;
      $hasil["record"]["status_info"] = ForumThread::GetStatusInfo($thread['id']);
      $hasil["jawaban"]["count"] = count($jawaban);
      $hasil["jawaban"]["records"] = $jawaban;
      $hasil["data_user"]["departments"] = Departments::NameById($user["id_departments"]);
      $hasil['data_user']['user_image'] = User::getImage($user->id_file_profile);
      $hasil['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

      switch( $res->getStatusCode() )
      {
        case 200:
          // ambil id dari result
          $response_payload = $res->getBody();
          $response_payload = Json::decode($response_payload);

          $hasil["record"]["confluence"]["status"] = "ok";
          $hasil["record"]["confluence"]["linked_id_question"] = $response_payload["id"];
          $hasil["record"]["confluence"]["judul"] = $response_payload["title"];
          $hasil["record"]["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);

          $this->ThreadLog($thread["id"], $payload["id_user_actor"], 2, -1);
          break;

        default:
          // kembalikan response
          $hasil["record"]["confluence"]["status"] = "not ok";
          $hasil["record"]["confluence"]["judul"] = $response_payload["title"];
          $hasil["record"]["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
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

        $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

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
   *    "id_user_actor": 123,
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
    $hasil = [];
    $gagal = [];

    $payload = $this->GetPayload();

    $is_keyword_valid = isset($payload["search_keyword"]);
    $is_id_user_actor_valid = isset($payload["id_user_actor"]);
    $is_start_valid = is_numeric($payload["page_no"]);
    $is_limit_valid = is_numeric($payload["items_per_page"]);
    $is_id_kategori_valid = isset($payload["id_kategori"]);
    $is_id_kategori_valid = $is_id_kategori_valid && is_array($payload["id_kategori"]);

    if(
        $is_keyword_valid == true &&
        $is_id_kategori_valid == true
      )
    {
      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $client = $this->SetupGuzzleClient();
      $query = [
        'type' => 'question',
        'query' => $payload["search_keyword"],
        'limit' => 10000,
        'start' => 0,
        'spaceKey' => 'PS',
      ];


      $res = $client->request(
        'GET',
        "/rest/questions/1.0/search",
        [
          /* 'sink' => Yii::$app->basePath . "/guzzledump_search.txt", */
          /* 'debug' => true, */
          /* 'http_errors' => true, */
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
            'type' => 'question',
            'query' => $payload["search_keyword"],
            'limit' => 10000,
            'start' => 0,
            'spaceKey' => 'PS',
          ],
        ]
      );

      switch( $res->getStatusCode() )
      {
        case 200:
          $response_payload = $res->getBody();
          $response_payload = Json::decode($response_payload);

          $results = $response_payload["results"];
          $results = $this->SanitasiHasilPencarian($results);
          $total_rows = count($results);

          $pages = ceil($total_rows / $payload['items_per_page']);
          if( $payload['page_no'] > $pages )
          {
            $payload['page_no'] = $pages;
          }

          $start = $payload["items_per_page"] * ($payload["page_no"] - 1);
          $end = $start + $payload["items_per_page"] - 1;
          if( $end >= $total_rows )
          {
            $end = $total_rows - 1;
          }

          $i = $start;
          $count = 0;
          $fail_count = 0;
          $terus = true;

          if($i <= $total_rows - 1)
          {
            do
            {
              $result_item = $results[$i];

              // ambil record confluence
              $linked_id_question = $result_item["id"];
              $response = $this->Conf_GetQuestion($client, $linked_id_question);

              try
              {
                $response_payload = $response->getBody();
                $response_payload = Json::decode($response_payload);

                $thread = ForumThread::find()
                  ->where([
                    "and",
                    "linked_id_question = :id",
                    "is_delete = 0",
                    ["in", "status", [1, 4, -3]]
                  ],
                  [
                    ":id" => $linked_id_question,
                  ]
                  )
                  ->one();

                if( is_null($thread) == false)
                {
                  $user_actor = User::findOne($payload["id_user_actor"]);

                  // cek hak baca user terhadap thread. hak baca diperiksa 
                  // berdasarkan kesamaan id_kategori

                  if(ForumThread::CekHakBaca($thread["id"], $user_actor["id"]) == true)
                  {
                    // ambil record SPBE - begin

                      $short_konten = "";
                      if( strlen($response_payload["body"]["content"]) < 300 )
                      {
                        $short_konten = strip_tags(
                          $response_payload["body"]["content"]
                        );
                      }
                      else
                      {
                        $short_konten = strip_tags(
                          $response_payload["body"]["content"]
                        );
                        $short_konten = substr($short_konten, 0, 300);
                      }

                      $user_create = User::findOne($thread["id_user_create"]);

                      $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

                      $temp = [];
                      $temp["forum_thread"] = $thread;
                      $temp["files"] = ForumThreadFile::GetFiles($thread['id']);
                      $temp["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
                      $temp["data_user"]["user_create"] = $user_create;
                      $temp["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
                      $temp["confluence"]["id"] = $response_payload["id"];
                      $temp["confluence"]["judul"] = $response_payload["title"];
                      $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
                      $temp["confluence"]["short_konten"] = $short_konten;

                      if( $is_id_user_actor_valid == true )
                      {
                        /* // periksa hak akses kategori bagi user ini */
                        /* $test = KategoriUser::find() */
                        /*   ->where( */
                        /*     [ */
                        /*       "and", */
                        /*       "id_user = :id_user", */
                        /*       "id_kategori = :id_kategori" */
                        /*     ], */
                        /*     [ */
                        /*       ":id_user" => $payload["id_user_actor"], */
                        /*       ":id_kategori" => $thread["id_kategori"] */
                        /*     ] */
                        /*   ) */
                        /*   ->one(); */

                        /* if( is_null($test) == false ) */
                        /* { */
                        /* } */

                        $hasil[] = $temp;
                        $count++;
                      }
                      else
                      {
                        $hasil[] = $temp;
                      }
                    }
                    else
                    {
                      $gagal["can not read"][] = $linked_id_question;
                      $fail_count++;
                    }

                  }
                  else
                  {
                    $gagal["thread not found"][] = $linked_id_question;
                    $fail_count++;
                  }
                }
                catch(yii\base\InvalidArgumentException $e)
                {
                  $gagal["CQ failed"][] = $linked_id_question;
                }

              // ambil record SPBE - end


              if( $count == $payload["items_per_page"] || $i == $total_rows - 1 )
              {
                $terus = false;
              }
              else
              {
                $i++;
              }

            } while( $terus == true );
          }

          // kembalikan hasilnya
          return [
            "status" => "ok",
            "pesan" => "Pencarian berhasil dilakukan",
            "query" => $query,
            "result" => 
            [
              "total_rows" => $total_rows,
              "counts" => $count,
              "start" => $start,
              "end" => $end,
              "i" => $i,
              "fail counts" => $fail_count,
              "page_no" => $payload["page_no"],
              "items_per_page" => $payload["items_per_page"],
              "records" => $hasil,
              "failed" => $gagal,
            ]
          ];
          break;

        default:
          return [
            "status" => "not ok",
            "pesan" => "Pencarian tidak berhasil dilakukan",
            "query" => $query,
            "payload" => $payload,
            "guzzle response" => $res
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
   * Mengambil daftar hak baca user berdasarkan idkategori
   *
   * Method GET
   * Request type: JSON
   * Request format:
   * {
   *   id_kategori: 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "ok/not ok",
   *   pesan: "abc",
   *   result:
   *   {
   *     count: 123,
   *     records:
   *     [
   *       {
   *         object of user record
   *       }, ...
   *     ]
   *   }
   * }
    * */
  public function actionHakBacaUser()
  {
    $payload = $this->GetPayload();

    $is_id_user_valid = isset($payload["id_user"]);
    $test = User::findOne($payload["id_user"]);
    $is_id_user_valid = $is_id_user_valid && is_null($test) == false;

    $is_id_kategori_valid = isset($payload["id_kategori"]);
    $is_id_kategori_valid = $is_id_kategori_valid && is_numeric($payload["id_kategori"]);
    
    if( $is_id_kategori_valid == true && $is_id_user_valid == true )
    {
      $user_actor = User::findOne($payload["id_user"]);

      $q = new Query();
      $list_user = $q->select("u.*")
        ->from("user u")
        ->join("join", "kategori_user ku", "ku.id_user = u.id")
        ->where(
          [
            "and",
            "ku.id_kategori = :id_kategori",
            "u.id_departments = :id_departments"
          ],
          [
            ":id_kategori" => $payload["id_kategori"],
            ":id_departments" => $user_actor["id_departments"]
          ]
        )
        ->orderBy("u.nama asc")
        ->all();

      $hasil = [];
      foreach($list_user as $a_user)
      {
        $user = User::findOne($a_user["id"]);

        $hasil[] = [
          "User" => $user
        ];
      }

      return [
        "status" => "ok",
        "pesan" => "Query berhasil dijalankan",
        "result" => 
        [
          "count" => count($hasil),
          "records" => $hasil
        ]
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak lengkap",
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
              $detail_thread = $this->GetDetail($thread);

              if( is_null($thread) == false )
              {
                $thread["status"] = $payload["status"];
                $thread->save();

                $daftar_sukses[] = $thread;

                // tulis log
                $this->ThreadLog($id_thread, $payload["id_user"], 1, $payload["status"]);

                // kirim notifikasi
                  switch(true)
                  {
                    case $payload['status'] == 0 : // baru
                      $creator = User::findOne($thread['id_user_create']);

                      $q = new Query();
                      $daftar_manager = 
                        $q->select("u.*")
                          ->from("user u")
                          ->join("join", "kategori_user ku", "ku.id_user = u.id")
                          ->join("join", "user_roles ur", "ur.id_user = u.id")
                          ->where(
                            [
                              "and",
                              "u.id_departments = :id_instansi",
                              "ku.id_kategori = :id_kategori",
                              "ur.id_roles = :id_role"
                            ],
                            [
                              ":id_instansi" => $creator["id_departments"],
                              ":id_kategori" => $thread["id_kategori"],
                              ":id_role" => Roles::IdByCodeName("manager_konten")
                            ]
                          )
                          ->all();

                      $daftar_email = [];
                      foreach($daftar_manager as $manager)
                      {
                        if( $manager["email"] != "" )
                        {
                          $temp = [];
                          $temp["email"] = $manager["email"];
                          $temp["nama"] = $manager["nama"];

                          $daftar_email[] = $temp;
                        }
                      }

                      Notifikasi::Kirim(
                        [
                          "type" => "topik_baru",
                          "daftar_email" => $daftar_email,
                          "thread" => $thread,
                          "detail_thread" => $detail_thread,
                        ]
                      );
                      break;

                    case $payload['status'] == 1 : // publish
                      $creator = User::findOne($thread['id_user_create']);

                      $q = new Query();
                      $daftar_manager = 
                        $q->select("u.*")
                          ->from("user u")
                          ->join("join", "kategori_user ku", "ku.id_user = u.id")
                          ->join("join", "user_roles ur", "ur.id_user = u.id")
                          ->where(
                            [
                              "and",
                              "u.id_departments = :id_instansi",
                              "ku.id_kategori = :id_kategori",
                              "ur.id_roles = :id_role"
                            ],
                            [
                              ":id_instansi" => $creator["id_departments"],
                              ":id_kategori" => $thread["id_kategori"],
                              ":id_role" => Roles::IdByCodeName("manager_konten")
                            ]
                          )
                          ->all();

                      $daftar_email = [];
                      foreach($daftar_manager as $manager)
                      {
                        if( $manager["email"] != "" )
                        {
                          $temp = [];
                          $temp["email"] = $manager["email"];
                          $temp["nama"] = $manager["nama"];

                          $daftar_email[] = $temp;
                        }
                      }

                      // kirim notifikasi ke si pembuat artikel
                      $daftar_email[] = [
                        "email" => $creator['email'],
                        "nama" => $creator['nama'],
                      ];

                      Notifikasi::Kirim(
                        [
                          "type" => "topik_publish",
                          "daftar_email" => $daftar_email,
                          "thread" => $thread,
                          "detail_thread" => $detail_thread,
                        ]
                      );
                      break;

                    case $payload['status'] == 2 : // un-publish
                      $creator = User::findOne($thread['id_user_create']);

                      $q = new Query();
                      $daftar_manager = 
                        $q->select("u.*")
                          ->from("user u")
                          ->join("join", "kategori_user ku", "ku.id_user = u.id")
                          ->join("join", "user_roles ur", "ur.id_user = u.id")
                          ->where(
                            [
                              "and",
                              "u.id_departments = :id_instansi",
                              "ku.id_kategori = :id_kategori",
                              "ur.id_roles = :id_role"
                            ],
                            [
                              ":id_instansi" => $creator["id_departments"],
                              ":id_kategori" => $thread["id_kategori"],
                              ":id_role" => Roles::IdByCodeName("manager_konten")
                            ]
                          )
                          ->all();

                      $daftar_email = [];
                      foreach($daftar_manager as $manager)
                      {
                        if( $manager["email"] != "" )
                        {
                          $temp = [];
                          $temp["email"] = $manager["email"];
                          $temp["nama"] = $manager["nama"];

                          $daftar_email[] = $temp;
                        }
                      }

                      // kirim notifikasi ke si pembuat artikel
                      $daftar_email[] = [
                        "email" => $creator['email'],
                        "nama" => $creator['nama'],
                      ];

                      Notifikasi::Kirim(
                        [
                          "type" => "topik_unpublish",
                          "daftar_email" => $daftar_email,
                          "thread" => $thread,
                          "detail_thread" => $detail_thread,
                        ]
                      );
                      break;

                    case $payload['status'] == 3 : // reject
                      $creator = User::findOne($thread['id_user_create']);

                      $q = new Query();
                      $daftar_manager = 
                        $q->select("u.*")
                          ->from("user u")
                          ->join("join", "kategori_user ku", "ku.id_user = u.id")
                          ->join("join", "user_roles ur", "ur.id_user = u.id")
                          ->where(
                            [
                              "and",
                              "u.id_departments = :id_instansi",
                              "ku.id_kategori = :id_kategori",
                              "ur.id_roles = :id_role"
                            ],
                            [
                              ":id_instansi" => $creator["id_departments"],
                              ":id_kategori" => $thread["id_kategori"],
                              ":id_role" => Roles::IdByCodeName("manager_konten")
                            ]
                          )
                          ->all();

                      $daftar_email = [];
                      foreach($daftar_manager as $manager)
                      {
                        if( $manager["email"] != "" )
                        {
                          $temp = [];
                          $temp["email"] = $manager["email"];
                          $temp["nama"] = $manager["nama"];

                          $daftar_email[] = $temp;
                        }
                      }

                      // kirim notifikasi ke si pembuat artikel
                      $daftar_email[] = [
                        "email" => $creator['email'],
                        "nama" => $creator['nama'],
                      ];

                      Notifikasi::Kirim(
                        [
                          "type" => "topik_reject",
                          "daftar_email" => $daftar_email,
                          "thread" => $thread,
                          "detail_thread" => $detail_thread,
                        ]
                      );
                      break;

                    case $payload['status'] == 4 : // freeze
                      $creator = User::findOne($thread['id_user_create']);

                      $q = new Query();
                      $daftar_manager = 
                        $q->select("u.*")
                          ->from("user u")
                          ->join("join", "kategori_user ku", "ku.id_user = u.id")
                          ->join("join", "user_roles ur", "ur.id_user = u.id")
                          ->where(
                            [
                              "and",
                              "u.id_departments = :id_instansi",
                              "ku.id_kategori = :id_kategori",
                              "ur.id_roles = :id_role"
                            ],
                            [
                              ":id_instansi" => $creator["id_departments"],
                              ":id_kategori" => $thread["id_kategori"],
                              ":id_role" => Roles::IdByCodeName("manager_konten")
                            ]
                          )
                          ->all();

                      $daftar_email = [];
                      foreach($daftar_manager as $manager)
                      {
                        if( $manager["email"] != "" )
                        {
                          $temp = [];
                          $temp["email"] = $manager["email"];
                          $temp["nama"] = $manager["nama"];

                          $daftar_email[] = $temp;
                        }
                      }

                      // kirim notifikasi ke si pembuat artikel
                      $daftar_email[] = [
                        "email" => $creator['email'],
                        "nama" => $creator['nama'],
                      ];

                      Notifikasi::Kirim(
                        [
                          "type" => "topik_freeze",
                          "daftar_email" => $daftar_email,
                          "thread" => $thread,
                          "detail_thread" => $detail_thread,
                        ]
                      );
                      break;

                  }
                // kirim notifikasi
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
   *  Mengambil daftar thread berdasarkan popularitasnya.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_user": 12
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
  public function actionPopularitems()
  {
    $payload = $this->GetPayload();

    $is_id_user_valid = isset($payload["id_user"]);

    if( $is_id_user_valid == true )
    {
      $q = new Query();

      $daftar_thread = 
        $q->select("t.*")
          ->from("forum_thread t")
          ->where(
            [
              "and",
              "is_delete = 0",
              "id_user_create = :id_user"
            ],
            [
              ":id_user" => $payload['id_user'],
            ]
          )
          ->orderBy("t.view desc")
          ->limit(5)
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

        $record["konten"] = html_entity_decode($record["konten"], ENT_QUOTES);

        try
        {
          $response_payload = Json::decode($response_payload);

          $temp["forum_thread"] = $record;
          $temp["user_create"] = $user;
          $temp["category_path"] = KmsKategori::CategoryPath($record["id_kategori"]);
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
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
        "payload" => $payload,
        "records" => $hasil,
      ];

    }
    else
    {
      return [
        "status" => "not ok",
        "payload" => $payload,
        "pesan" => "Parameter yang diperlukan tidak lengkap: id_kategori (array)",
      ];

    }
  }

  /*
   *  Mengambil daftar thread berdasarkan kesamaan tags dan kategori.
   *  Kesamaan tags diukur dari tags yang menempel pada suatu topik.
   *  Kesamaan kategori diukur dari kategori yang melekat pada user
   *  yang sedang login.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_topik": 123,
   *    "id_user": 12
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

    $is_id_topik_valid = isset($payload["id_thread"]);

    if( $is_id_topik_valid == true )
    {
      // ambil daftar tags yang berasal dari id_kategori
      $q  = new Query();
      $temp_daftar_tag = 
        $q->select("t.*")
          ->from("kms_tags t")
          ->join("JOIN", "forum_thread_tag atag", "atag.id_tag = t.id")
          ->where(
            [
              "and",
              "atag.id_thread = :id_topik"
            ],
            [
              ":id_topik" => $payload["id_thread"],
            ]
          )
          ->distinct()
          ->all();

      $daftar_tag = [];
      foreach($temp_daftar_tag as $item)
      {
        $daftar_tag[] = $item["id"];
      }

      // ambil daftar kategori yang melekat pada user
      $my_categories = [];
      $temp_where = [];
      if( isset($payload["id_user"]) == true )
      {
        $test = User::findOne($payload["id_user"]);

        if( is_null($test) == false )
        {
          $list_temp = KategoriUser::findAll(["id_user" => $payload["id_user"]]);

          foreach($list_temp as $item)
          {
            $my_categories[] = $item["id_kategori"];
          }

          $temp_where = ["in", "t.id_kategori", $my_categories];
        }
      }

      // mengambil daftar thread terkait
      $q = new Query();
      $where = [];
      $where[] = "and";
      $where[] = ["in", "atag.id_tag", $daftar_tag];
      $where[] = "t.is_delete = 0";
      $where[] = "t.status = 1";
      $where[] = ["<>", "t.id", $payload["id_thread"]];

      if( count($temp_where) > 0 )
      {
        $where[] = $temp_where;
      }

      $daftar_thread = 
        $q->select("t.*")
          ->from("forum_thread t")
          ->join("JOIN", "forum_thread_tag atag", "atag.id_thread = t.id")
          ->where($where)
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

        $record["konten"] = html_entity_decode($record["konten"], ENT_QUOTES);

        try
        {
          $response_payload = Json::decode($response_payload);

          $temp["forum_thread"] = $record;
          $temp["user_create"] = $user;
          $temp["category_path"] = KmsKategori::CategoryPath($record["id_kategori"]);
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
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
        "payload" => $payload,
        "records" => $hasil,
      ];

    }
    else
    {
      return [
        "status" => "not ok",
        "payload" => $payload,
        "pesan" => "Parameter yang diperlukan tidak lengkap: id_kategori (array)",
      ];

    }
  }


  /*
   *  Mengambil daftar thread yang belum puas berdasarkan kesamaan
   *  kategori dengan kategori-kategori yang menempel pada si user.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_user": 12
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
  public function actionUnfinishedItems()
  {
    $payload = $this->GetPayload();

    $is_id_user_valid = isset($payload["id_user"]);


    if( $is_id_user_valid == true )
    {
      // ambil daftar tags yang berasal dari id_kategori
      $s_q = (new Query())
        ->select("
          t.id,
          count(c.id) as jumlah
        ")
        ->from("forum_thread t")
        ->join("left join", "forum_thread_discussion c", "c.id_thread = t.id")
        ->join("inner join", "kategori_user ku", "ku.id_kategori = t.id_kategori")
        ->where(
          [
            "and",
            "t.linked_id_question <> -1",
            "t.is_delete = 0",
            "t.is_puas = 0",
            "ku.id_user = :id_user"
          ],
          [
            ":id_user" => $payload['id_user'],
          ]
        )
        ->groupBy("t.id")
        ->orderBy("t.time_create asc");

      $m_q = (new Query())
        ->select("a.*")
        ->from(
          [
            "a" => $s_q
          ]
        )
        ->limit(5);

      $temp_daftar_thread = $m_q->all();

      // ambil informasi dari confluence
      $hasil = [];
      foreach($temp_daftar_thread as $item)
      {
        $record = ForumThread::findOne($item['id']);

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

        $record["konten"] = html_entity_decode($record["konten"], ENT_QUOTES);
        $user = User::findOne( $record['id_user_create'] );

        try
        {
          $response_payload = Json::decode($response_payload);

          $temp["forum_thread"] = $record;
          $temp["user_create"] = $user;
          $temp["category_path"] = KmsKategori::CategoryPath($record["id_kategori"]);
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
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
      // ambil daftar kategori yang melekat pada user
      $my_categories = [];
      $temp_where = [];
      if( isset($payload["id_user"]) == true )
      {
        $test = User::findOne($payload["id_user"]);

        if( is_null($test) == false )
        {
          $list_temp = KategoriUser::findAll(["id_user" => $payload["id_user"]]);

          foreach($list_temp as $item)
          {
            $my_categories[] = $item["id_kategori"];
          }

          $temp_where = ["in", "t.id_kategori", $my_categories];
        }
      }

      // ambil daftar tags yang berasal dari id_kategori
      $q  = new Query();
      $temp_daftar_tag = 
        $q->select("t.*")
          ->from("kms_tags t")
          ->join("JOIN", "forum_thread_tag atag", "atag.id_tag = t.id")
          ->join("JOIN", "forum_thread f", "f.id = atag.id_thread")
          ->where([
            "and",
            "f.status = 1",
            "f.is_delete = 0",
            ["in", "f.id_kategori", $payload["id_kategori"]]
          ])
          ->distinct()
          ->all();

      $daftar_tag = [];
      foreach($temp_daftar_tag as $item)
      {
        $daftar_tag[] = $item["id"];
      }


      // mengambil daftar thread terkait
      $q = new Query();
      $where = [];
      $where[] = "and";
      $where[] = "t.status = 1";
      $where[] = "t.is_delete = 0";
      $where[] = ["in", "atag.id_tag", $daftar_tag];
      $where[] = ["not", ["in", "t.id_kategori", $payload["id_kategori"]]];

      if( count($temp_where) > 0 )
      {
        $where[] = $temp_where;
      }


      // mengambil daftar kategori terkait
      $hasil = 
        $q->select("k.*")
          ->from("kms_kategori k")
          ->join("JOIN", "forum_thread t", "t.id_kategori = k.id")
          ->join("JOIN", "forum_thread_tag atag", "atag.id_thread = t.id")
          ->where($where)
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
            case -3: //rangkumselesai
              $temp["data"]["rangkumselesai"] = $record["jumlah"];
              break;

            case -2: //prosesrangkum
              $temp["data"]["rangkumprogress"] = $record["jumlah"];
              break;

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

    $id_user = $payload["id_user"];
    $is_tanggal_awal_valid = isset($payload["tanggal_awal"]);
    $is_tanggal_akhir_valid = isset($payload["tanggal_akhir"]);
    $tanggal_awal = Carbon::createFromFormat("Y-m-d", $payload["tanggal_awal"]);
    $tanggal_akhir = Carbon::createFromFormat("Y-m-d", $payload["tanggal_akhir"]);

    // ambil data kejadian dari tiap kategori
    $daftar_kategori = KmsKategori::GetList();
    $daftar_kategori = KategoriUser::ListByUser($id_user);

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
        $q->select("log.status, t.id")
          ->from("forum_thread t")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_thread = t.id")
          ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status, t.id")
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
        $q->select("log.status, u.id")
          ->from("user u")
          ->join("JOIN", "forum_thread_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "forum_thread t", "log.id_thread = t.id")
          ->andWhere("t.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status, u.id")
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
      /* for($i = 1; $i < $kategori["level"]; $i++) */
      /* { */
      /*   $indent .= "&nbsp;&nbsp;"; */
      /* } */
      $index_name = $indent . $kategori["nama"];
      $category_path = KmsKategori::CategoryPath($kategori["id"]);

      $temp["total"]["thread"] = $total_thread;
      $temp["total"]["user"] = $total_user;

      $temp["rangkumselesai"]["thread"] = 0;
      $temp["rangkumprogress"]["thread"] = 0;
      $temp["draft"]["thread"] = 0;
      $temp["new"]["thread"] = 0;
      $temp["publish"]["thread"] = 0;
      $temp["unpublish"]["thread"] = 0;
      $temp["reject"]["thread"] = 0;
      $temp["freeze"]["thread"] = 0;
      foreach($thread_status as $record)
      {
        switch( $record["status"] )
        {
          case -3: //rangkumselesai
            $temp["rangkumselesai"]["thread"]++;
            break;

          case -2: //rangkumprogress
            $temp["rangkumprogress"]["thread"]++;
            break;

          case -1: //draft
            $temp["draft"]["thread"]++;
            break;

          case 0: //new
            $temp["new"]["thread"]++;
            break;

          case 1: //publish
            $temp["publish"]["thread"]++;
            break;

          case 2: //unpublish
            $temp["unpublish"]["thread"]++;
            break;

          case 3: //reject
            $temp["reject"]["thread"]++;
            break;

          case 4: //freeze
            $temp["freeze"]["thread"]++;
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

      $temp["rangkumselesai"]["user"] = 0;
      $temp["rangkumprogress"]["user"] = 0;
      $temp["draft"]["user"] = 0;
      $temp["new"]["user"] = 0;
      $temp["publish"]["user"] = 0;
      $temp["unpublish"]["user"] = 0;
      $temp["reject"]["user"] = 0;
      $temp["freeze"]["user"] = 0;
      foreach($user_status as $record)
      {
        switch( $record["status"] )
        {
          case -3: //rangkumselesai
            $temp["rangkumselesai"]["user"]++;
            break;

          case -2: //rangkumprogress
            $temp["rangkumprogress"]["user"]++;
            break;

          case -1: //draft
            $temp["draft"]["user"]++;
            break;

          case 0: //new
            $temp["new"]["user"]++;
            break;

          case 1: //publish
            $temp["publish"]["user"]++;
            break;

          case 2: //unpublish
            $temp["unpublish"]["user"]++;
            break;

          case 3: //reject
            $temp["reject"]["user"]++;
            break;

          case 4: //freeze
            $temp["freeze"]["user"]++;
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
            else
            {
              if(
                $test['status'] == -3 ||  // telah selesai dirangkum
                $test['status'] == -2 ||  // sedang dirangkum
                $test['status'] == -1 ||  // draft
                $test['status'] == 0  ||  // baru
                $test['status'] == 2  ||  // un-publish
                $test['status'] == 3  ||  // reject
                $test['status'] == 4  ||  // freeze
                $test['status'] == 6      // pengetahuan
              )
              {
                return [
                  "status" => "not ok",
                  "pesan" => "Topik tidak dapat menerima komentar",
                ];
              }
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
            else
            {
              $thread = ForumThread::findOne($test['id_thread']);

              if(
                $thread['status'] == -3 ||  // telah selesai dirangkum
                $thread['status'] == -2 ||  // sedang dirangkum
                $thread['status'] == -1 ||  // draft
                $thread['status'] == 0  ||  // baru
                $thread['status'] == 2  ||  // un-publish
                $thread['status'] == 3  ||  // reject
                $thread['status'] == 4  ||  // freeze
                $thread['status'] == 6      // pengetahuan
              )
              {
                return [
                  "status" => "not ok",
                  "pesan" => "Topik tidak dapat menerima komentar",
                ];
              }
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
          else
          {
            if(
              $thread['status'] == -3 ||  // telah selesai dirangkum
              $thread['status'] == -2 ||  // sedang dirangkum
              $thread['status'] == -1 ||  // draft
              $thread['status'] == 0  ||  // baru
              $thread['status'] == 2  ||  // un-publish
              $thread['status'] == 3  ||  // reject
              $thread['status'] == 4  ||  // freeze
              $thread['status'] == 6      // pengetahuan
            )
            {
            return [
              "status" => "not ok",
              "pesan" => "Topik tidak dapat menerima jawaban.",
            ];
            }
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


                $konten = htmlentities($payload["konten"], ENT_QUOTES);

                $client = $this->SetupGuzzleClient();
                $jira_conf = Yii::$app->restconf->confs['confluence'];

                $request_payload = [
                  "questionId" => $thread["linked_id_question"],
                  "body" => 
                  [
                    "content" => $konten,
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
                  "body" => $konten,
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
                $new["konten"] = $konten;
                $new->save();

                //jika ada array id_files, maka pasangkan file-file dengan jawaban
                if( isset($payload["id_files"]) == true )
                {
                  if( is_array($payload["id_files"]) == true )
                  {
                    foreach($payload["id_files"] as $id_file)
                    {
                      $df = new ForumThreadDiscussionFiles();
                      $df["id_thread_discussion"] = $new->primaryKey;
                      $df["id_thread_file"] = $id_file;

                      $df->save();
                    }
                  }
                }
            // simpan di SPBE

        //eksekusi

        // ====================================================================
        // menyusun response
        // ====================================================================
        $user = User::findOne($payload["id_user"]);
        $thread = ForumThread::findOne($payload["id_parent"]);

        $files = [];
        $temp_files = ForumThreadDiscussionFiles::findAll(["id_thread_discussion" => $new["id"]]);
        foreach($temp_files as $a_file)
        {
          $file = ForumFiles::findOne($a_file["id_thread_file"]);

          $is_image = true;
          if( preg_match_all("/(jpg|jpeg)/i", $file["nama"]) == true )
          {
          }
          else
          {
            $is_image = false;
          }

          $temp = [
            "ForumFiles" => $file,
            "thumbnail" => 
              $is_image == true ? 
                BaseUrl::base(true) . "/files/" . $file["thumbnail"] : 
                BaseUrl::base(true) . "/files/" . "logo_pdf.png", 
            "link" => BaseUrl::base(true) . "/files/" . $file["nama"], 
          ];  

          $files[] = $temp;
        }
        // ====================================================================
        // menyusun response
        // ====================================================================

        return [
          "status" => "ok",
          "pesan" => "Record jawaban berhasil dibikin",
          "result" => 
          [
            "ForumThread" => $thread,
            "record" => $new,
            "files" => $files,
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
      $test["konten"] = html_entity_decode($test["konten"], ENT_QUOTES);

      $user = User::findOne($test["id_user_create"]);

      if( is_null($test) == false )
      {
        $ForumThread = ForumThread::findOne($test['id_thread']);

        // ambil daftar komentar - begin
          $daftar_komentar = $this->Get_Jawaban_Komentar($test['id']);
        // ambil daftar komentar - begin


        // ambil daftar lampiran jawaban - begin
          $files = $this->Get_Jawaban_Lampiran($payload['id']);
        // ambil daftar lampiran jawaban - end


        // hitung jawaban_index dan jawaban_count - begin
          $info = $this->Get_JawabanCount_Index($jawaban['id_thread']);
          $jawaban_count = $info['count'];
          $jawaban_index = $info['index'];
        // hitung jawaban_index dan jawaban_count - end


        return [
          "status" => "ok",
          "pesan" => "Record jawaban ditemukan",
          "result" => [
            'ForumThread' => $ForumThread,
            'jawaban_count' => $jawaban_count,
            'jawaban_index' => $jawaban_index,
            'jawaban' => $test,
            'files' => $files,
            "user_create" => $temp_user,
            "user" => $user,
            "list_komentar" => $daftar_komentar,
            "payload" => $payload,
          ]
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

        $test["konten"] = htmlentities($payload["konten"], ENT_QUOTES);
        $test["id_user_update"] = $payload["id_user"];
        $test["time_update"] = date("Y-m-j H:i:s");
        $test->save();

        //jika ada array id_files, maka pasangkan file-file dengan jawaban
        if( isset($payload["id_files"]) == true )
        {
          if( is_array($payload["id_files"]) == true )
          {
            // reset relasi antara record forum_thread dengan forum_thread_files
            //
            ForumThreadDiscussionFiles::deleteAll(
              "id_thread_discussion = :id",
              [
                ":id" => $test['id'],
              ]
            );

            foreach($payload["id_files"] as $id_file)
            {
              $df = new ForumThreadDiscussionFiles();
              $df["id_thread_discussion"] = $test['id'];
              $df["id_thread_file"] = $id_file;

              $df->save();

              if( $df->hasErrors() )
              {
                Yii::info(
                  "errors = " . Json::encode($df->getErrors())
                );
              }

            }
          }
        }

        // menyusun response
        // ==========================

        $jawaban = ForumThreadDiscussion::findOne($payload['id']);

        // hitung jawaban_index dan jawaban_count - begin
          $info = $this->Get_JawabanCount_Index($jawaban['id_thread']);
          $jawaban_count = $info['count'];
          $jawaban_index = $info['index'];
        // hitung jawaban_index dan jawaban_count - end

        // ambil daftar lampiran jawaban
        //
        $files = $this->Get_Jawaban_Lampiran($payload['id']);

        $thread = ForumThread::findOne($jawaban['id_thread']);

        $user = User::findOne($payload["id_user"]);

        return [
          "status" => "ok",
          "pesan" => "Record jawaban telah disimpan",
          "payload" => $payload,
          "result" => [
            "ForumThread" => $thread,
            "record" => $jawaban,
            "jawaban_count" => $jawaban_count,
            "jawaban_index" => $jawaban_index,
            "files" => $files,
            "user" => $user,
          ]
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
  
  private function Get_Jawaban_Komentar($id_jawaban)
  {
    $list_komentar_jawaban = ForumThreadDiscussionComment::find()
      ->where(
        "id_discussion = :id AND is_delete = 0", 
        [":id" => $id_jawaban]
      )
      ->orderBy("time_create asc")
      ->all();

    $daftar_komentar = [];
    foreach($list_komentar_jawaban as $item_komentar)
    {
      $temp_user = User::findOne($item_komentar["id_user_create"]);

      $daftar_komentar[] = [
        "komentar" => $item_komentar,
        "user_create" => $temp_user,
      ];
    }

    return $daftar_komentar;
  }

  private function Get_Jawaban_Lampiran($id_jawaban)
  {
    $files = [];
    $temp_files = ForumThreadDiscussionFiles::findAll(
      ["id_thread_discussion" => $id_jawaban]
    );
    foreach($temp_files as $a_file)
    {
      $file = ForumFiles::findOne($a_file["id_thread_file"]);

      $is_image = true;
      if( preg_match_all("/(jpg|jpeg)/i", $file["nama"]) == true )
      {
      }
      else
      {
        $is_image = false;
      }

      $temp = [
        "ForumFiles" => $file,
        "thumbnail" => 
          $is_image == true ? 
            BaseUrl::base(true) . "/files/" . $file["thumbnail"] : 
            BaseUrl::base(true) . "/files/" . "logo_pdf.png", 
        "link" => BaseUrl::base(true) . "/files/" . $file["nama"], 
      ];  

      $files[] = $temp;
    }

    return $files;
  }
  
  private function Get_JawabanCount_Index($id_thread)
  {
    $list = ForumThreadDiscussion::find()
      ->where(
        "id_thread = :id_thread AND
        is_delete = 0",
        [
          ":id_thread" => $id_thread
        ]
      )
      ->orderBy('time_create asc')
      ->all();
    $jawaban_count = count($list);

    $jawaban_index = -1;
    foreach($list as $item)
    {
      if( $item['id'] == $jawaban['id'] )
      {
        break;
      }
      else
      {
        $jawaban_index++;
      }
    }

    return [
      'count' => $jawaban_count,
      'index' => $jawaban_index,
    ];
  }


  /*
   * Membuat, mengupdate, menghapus attachment dari thread
   * Harus merupakan multipart request. Siapkan parameter dengan nama 'file'
   * untuk menyimpan data byte dari file yang dikirim.
   *
   * Method: POST
   * Request type: JSON,
   * Request format:
   * {
   *   "id_user_actor": 123
   * }
   * Reponse type: JSON
   * Reponse format:
   * {
   *   "status": "ok/ not ok",
   *   "pesan": "",
   *   "result": { object of record attachment }
   * }
   *
   * Method: PUT
   * Request type: JSON
   * Request format:
   * {
   *   "id_file": 123,
   *   "id_user_actor": 123
   * }
   * Response type: JSON,
   * Response format:
   * {
   *   "status": "ok/not ok",
   *   "pesan": "",
   *   "result": { object of attachment record }
   * }
   *
   * Method: DELETE
   * Request type: JSON
   * Request format:
   * {
   *   "id_file": 123,
   *   "id_user_actor": 123
   * }
   * Response type: JSON,
   * Response format:
   * {
   *   "status": "ok/not ok",
   *   "pesan": "",
   *   "result": { object of attachment record }
   * }
   *
    * */
  public function actionAttachment()
  {
    $payload = $this->GetPayload();

    if( Yii::$app->request->isPost )
    {
      $file = UploadedFile::getInstanceByName("file");

      if( is_null($file) == false )
      {
        $deskripsi = Yii::$app->request->post("deskripsi");
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
            if( preg_match_all("/(jpg|jpeg)/i", $file->extension) == true )
            {
              $asal = WideImage::loadFromFile($path . $file_name);
              $file_name_2 = $id_user_actor . "-" . $file->baseName . "-" . $time_hash . "-thumb" . "." . $file->extension;

              $resize = $asal->resize("150");
              $resize->saveToFile($path . $file_name_2);
            }
            else
            {
              $file_name_2 = "logo_pdf.png";
              $is_image = false;
            }

            $ff = new ForumFiles();
            $ff["nama"] = $file_name;
            $ff["deskripsi"] = $deskripsi;
            $ff["thumbnail"] = $file_name_2;
            $ff["id_user_create"] = $id_user_actor;
            $ff["time_create"] = date("Y-m-d H:i:s");
            $ff->save();

            return [
              "status" => "ok",
              "pesan" => "Berhasil menyimpan file",
              "result" => $ff,
              "thumbnail" => 
                $is_image == true ? 
                  BaseUrl::base(true) . "/files/" . $ff["thumbnail"] : 
                  BaseUrl::base(true) . "/files/" . "logo_pdf.png", 
              "link" => BaseUrl::base(true) . "/files/" . $ff["nama"], 
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
      $is_id_thread_valid = isset($payload["id_thread"]);
      $is_id_user_valid = isset($payload["id_user_actor"]);

      if( 
          $is_id_file_valid == true && 
          $is_id_thread_valid == true &&
          $is_id_user_valid == true 
        )
      {
        $test = ForumFiles::findOne($payload["id_file"]);
        $test_thread = ForumThread::findOne($payload["id_thread"]);

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

          // hapus relasinya dengan thread


            if( is_null($test_thread) == false )
            {
              $thread_file = ForumThreadFile::find()
                ->where(
                  "id_thread = :id_thread and id_file = :id_file",
                  [
                    ":id_thread" => $payload["id_thread"],
                    ":id_file" => $payload["id_file"],
                  ]
                )
                ->one();

              $thread_file["is_delete"] = 1;
              $thread_file["id_user_delete"] = $payload["id_user_actor"];
              $thread_file["time_delete"] = date("Y-m-d H:i:s");
              $thread_file->save();
            }
            else
            {
            }

          // hapus relasinya dengan thread

          return [
            "status" => "ok",
            "pesan" => "File berhasil dihapus",
            "result" => 
            [
              "forum_file" => $test,
              "thread_file" => $thread_file,
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


  /*
   * Menghapus relasi antara record forum_thread dengan record kms_artikel
   *
   * Request type; JSON,
   * Request format:
   * {
   *   "id_thread": 123,
   * }
   * Response type; JSON,
   * Response format:
   * {
   *   "status": "ok",
   *   "pesan": "",
   *   "result":
   *   {
   *     object of thread record
   *   }
   * }
    * */
  public function actionCancelrangkuman()
  {
    $payload = $this->GetPayload();

    $is_id_thread_valid = isset($payload["id_thread"]);
    $is_linked_id_article = isset($payload["linked_id_article"]);

    if( $is_id_thread_valid == true && $is_linked_id_article == true )
    {
      $test = ForumThread::findOne($payload["id_thread"]);

      if( is_null($test) == true )
      {
        return [
          "status" => "not ok",
          "pesan" => "Record forum_thread tidak ditemukan",
        ];
      }

      $test = KmsArtikel::findOne($payload["linked_id_article"]);

      if( is_null($test) == true )
      {
        return [
          "status" => "not ok",
          "pesan" => "Record kms_article tidak ditemukan",
        ];
      }

      $thread = ForumThread::findOne($payload["id_thread"]);
      $thread["linked_id_artikel"] = -1;
      $thread["status"] = 4;  // kembalikan status topik kembali menjadi freeze
      $thread->save();

      $article = KmsArtikel::findOne($payload["linked_id_article"]);

      return [
        "status" => "ok",
        "pesan" => "Relasi antara ForumThread dan KmsArticle telah disimpan",
        "result" => 
        [
          "ForumThread" => $thread,
          "KmsArticle" => $article,
        ]

      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak lengkap",
        "payload" => $payload
      ];
    }
  }


  /*
   * Merelasikan record forum_thread dengan record kms_artikel
   *
   * Request type; JSON,
   * Request format:
   * {
   *   "id_thread": 123,
   *   "linked_id_artikel": 123,
   * }
   * Response type; JSON,
   * Response format:
   * {
   *   "status": "ok",
   *   "pesan": "",
   *   "result":
   *   {
   *     object of thread record
   *   }
   * }
    * */
  public function actionMakerangkuman()
  {
    $payload = $this->GetPayload();

    $is_id_thread_valid = isset($payload["id_thread"]);
    $is_linked_id_article = isset($payload["linked_id_article"]);

    if( $is_id_thread_valid == true && $is_linked_id_article == true )
    {
      $test = ForumThread::findOne($payload["id_thread"]);

      if( is_null($test) == true )
      {
        return [
          "status" => "not ok",
          "pesan" => "Record forum_thread tidak ditemukan",
        ];
      }

      $test = KmsArtikel::findOne($payload["linked_id_article"]);

      if( is_null($test) == true )
      {
        return [
          "status" => "not ok",
          "pesan" => "Record kms_article tidak ditemukan",
        ];
      }

      $thread = ForumThread::findOne($payload["id_thread"]);
      $thread["linked_id_artikel"] = $payload["linked_id_article"];
      $thread["status"] = -2;
      $thread->save();

      $article = KmsArtikel::findOne($payload["linked_id_article"]);
      $article['linked_id_thread'] = $thread['id'];
      $article->save();

      return [
        "status" => "ok",
        "pesan" => "Relasi antara ForumThread dan KmsArticle telah disimpan",
        "result" => 
        [
          "ForumThread" => $thread,
          "KmsArticle" => $article,
        ]

      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak lengkap",
        "payload" => $payload
      ];
    }
  }


  public function actionLikeThread()
  {
    $payload = $this->GetPayload();

    $is_id_thread_valid = isset($payload["id_thread"]);
    $is_value_valid = isset($payload["value"]);
    $is_id_user_valid = isset($payload["id_user"]);

    $is_value_valid = $is_value_valid && ( $payload["value"] == 1 || $payload["value"] == 2);

    $test = User::findOne( $pauload["id_user"] );
    $is_id_user_valid = $is_id_user_valid && is_null( $test );

    $test = ForumThread::findOne( $pauload["id_thread"] );
    $is_id_thread_valid = $is_id_thread_valid && is_null( $test );

    if( $is_id_user_valid && $is_id_thread_valid && $is_value_valid )
    {
      // cek apakah sudah ada record forum_thread_user_action
      $test = ForumThreadUserAction::find()
        ->where(
          [
            "and",
            "id_user = :id_user",
            "id_thread = :id_thread"
          ],
          [
            ":id_user" => $payload["id_user"],
            ":id_thread" => $payload["id_thread"],
          ]
        )
        ->one();

      if( is_null($test) == true )
      {
        $new = new ForumThreadUserAction();
        $new["id_thread"] = $payload["id_thread"];
        $new["id_user"] = $payload["id_user"];
        $new["action"] = $payload["value"];
        $new->save();
      }
      else
      {
        $test["action"] = $payload["value"];
        $test->save();
      }

      //Rangkum forum_thread_user_action menjadi total like dan dislike
      $q = new Query();
      $hasil = $q->select(" count(a.id_user) as jumlah ")
        ->from("forum_thread_user_action a")
        ->where(
          "id_thread = :id_thread AND
           action = 1
          ",
          [":id_thread" => $payload["id_thread"]]
        )
        ->one();
      $like = $hasil["jumlah"];

      $q = new Query();
      $hasil = $q->select(" count(a.id_user) as jumlah ")
        ->from("forum_thread_user_action a")
        ->where(
          "id_thread = :id_thread AND
           action = 2
          ",
          [":id_thread" => $payload["id_thread"]]
        )
        ->one();
      $dislike = $hasil["jumlah"];

      // update record forum_thread
      $thread = ForumThread::findOne($payload["id_thread"]);
      $thread["like"] = $like;
      $thread["dislike"] = $dislike;
      $thread->save();

      return [
        "status" => "ok",
        "pesan" => "Informasi like/dislike berhasil disimpan",
        "result" => $thread
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Informasi like/dislike berhasil disimpan",
        "payload" => $payload
      ];
    }

  }

  public function actionLikeJawaban()
  {
    $payload = $this->GetPayload();

    $is_id_jawaban_valid = isset($payload["id_jawaban"]);
    $is_value_valid = isset($payload["value"]);
    $is_id_user_valid = isset($payload["id_user"]);

    $is_value_valid = $is_value_valid && ( $payload["value"] == 1 || $payload["value"] == 2);

    $test = User::findOne( $pauload["id_user"] );
    $is_id_user_valid = $is_id_user_valid && is_null( $test );

    $test = ForumThreadDiscussion::findOne( $pauload["id_jawaban"] );
    $is_id_jawaban_valid = $is_id_jawaban_valid && is_null( $test );

    if( $is_id_user_valid && $is_id_jawaban_valid && $is_value_valid )
    {
      // cek apakah sudah ada record forum_thread_user_action
      $test = ForumThreadDiscussionUserAction::find()
        ->where(
          [
            "and",
            "id_user = :id_user",
            "id_discussion = :id_discussion"
          ],
          [
            ":id_user" => $payload["id_user"],
            ":id_discussion" => $payload["id_jawaban"],
          ]
        )
        ->one();

      if( is_null($test) == true )
      {
        $new = new ForumThreadDiscussionUserAction();
        $new["id_discussion"] = $payload["id_jawaban"];
        $new["id_user"] = $payload["id_user"];
        $new["action"] = $payload["value"];
        $new->save();
      }
      else
      {
        $test["action"] = $payload["value"];
        $test->save();
      }

      //Rangkum forum_thread_user_action menjadi total like dan dislike
      $q = new Query();
      $hasil = $q->select(" count(a.id_user) as jumlah ")
        ->from("forum_thread_discussion_user_action a")
        ->where(
          "id_discussion = :id_jawaban AND
           action = 1
          ",
          [":id_jawaban" => $payload["id_jawaban"]]
        )
        ->one();
      $like = $hasil["jumlah"];

      $q = new Query();
      $hasil = $q->select(" count(a.id_user) as jumlah ")
        ->from("forum_thread_discussion_user_action a")
        ->where(
          "id_discussion = :id_jawaban AND
           action = 2
          ",
          [":id_jawaban" => $payload["id_jawaban"]]
        )
        ->one();
      $dislike = $hasil["jumlah"];

      // update record forum_thread
      $thread = ForumThreadDiscussion::findOne($payload["id_jawaban"]);
      $thread["like"] = $like;
      $thread["dislike"] = $dislike;
      $thread->save();

      return [
        "status" => "ok",
        "pesan" => "Informasi like/dislike berhasil disimpan",
        "result" => $thread
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Informasi like/dislike gagal disimpan",
        "payload" => $payload
      ];
    }
  }

  public function actionLikeComment()
  {
    $payload = $this->GetPayload();

    $is_id_comment_valid = isset($payload["id_comment"]);
    $is_value_valid = isset($payload["value"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_type_valid = isset($payload["type"]);

    $is_value_valid = $is_value_valid && ( $payload["value"] == 1 || $payload["value"] == 2);
    $is_type_valid = $is_type_valid && ( $payload["type"] == 1 || $payload["type"] == 2);

    $test = User::findOne( $pauload["id_user"] );
    $is_id_user_valid = $is_id_user_valid && is_null( $test );

    $test = ForumThreadDiscussion::findOne( $pauload["id_jawaban"] );
    $is_id_jawaban_valid = $is_id_jawaban_valid && is_null( $test );

    if( $is_value_valid && $is_type_valid )
    {
      if( $type == 1 )  // comment of pertanyaan
      {
        $comment = ForumThreadComment::findOne($payload["id_comment"]);

        if( is_null($comment) == true )
        {
          return [
            "status" => "not ok",
            "pesan" => "id_comment tidak dikenal",
            "payload" => $payload
          ];
        }
        else
        {
          // cek record forum_thread_comment_user_action
          $test = ForumThreadCommentUserAction::find()
            ->where(
              "id_comment = :id_comment AND
               id_user = :id_user",
              [
                ":id_comment" => $payload["id_comment"],
                ":id_user" => $payload["id_user"],
              ]
            )
            ->one();

          if( is_null($test) == true )
          {
            $new = new ForumThreadCommentUserAction();
            $new["id_comment"] = $payload["id_comment"];
            $new["id_user"] = $payload["id_user"];
            $new["action"] = $payload["value"];
            $new->save();
          }
          else
          {
            $test["action"] = $payload["value"];
            $test->save();
          }
        }

        // rangkum
        $q = new Query();
        $hasil = $q->select(" count(a.id_user) as jumlah ")
          ->from("forum_thread_comment_user_action a")
          ->where(
            "
              id_comment = :id_comment AND
              id_user = :id_user AND
              action = 1
            ",
            [
              ":id_comment" => $payload["id_comment"],
              ":id_user" => $payload["id_user"],
            ]
          )
          ->one();
        $like = $hasil["jumlah"];

        $q = new Query();
        $hasil = $q->select(" count(a.id_user) as jumlah ")
          ->from("forum_thread_comment_user_action a")
          ->where(
            "
              id_comment = :id_comment AND
              id_user = :id_user AND
              action = 2
            ",
            [
              ":id_comment" => $payload["id_comment"],
              ":id_user" => $payload["id_user"],
            ]
          )
          ->one();
        $dislike = $hasil["jumlah"];

        // update
        $comment = ForumThreadComment::findOne($payload["id_comment"]);
        $comment["like"] = $like;
        $comment["dislike"] = $dislike;
        $comment->save();


        return [
          "status" => "ok",
          "pesan" => "Like / dislike telah disimpan",
          "result" => $comment
        ];
      }
      else
      {
        $comment = ForumThreadDiscussionComment::findOne($payload["id_comment"]);

        if( is_null($comment) == true )
        {
          return [
            "status" => "not ok",
            "pesan" => "id_comment tidak dikenal",
            "payload" => $payload
          ];
        }
        else
        {
          // cek record forum_thread_comment_user_action
          $test = ForumThreadDiscussionCommentUserAction::find()
            ->where(
              "id_comment = :id_comment AND
               id_user = :id_user",
              [
                ":id_comment" => $payload["id_comment"],
                ":id_user" => $payload["id_user"],
              ]
            )
            ->one();

          if( is_null($test) == true )
          {
            $new = new ForumThreadDiscussionCommentUserAction();
            $new["id_comment"] = $payload["id_comment"];
            $new["id_user"] = $payload["id_user"];
            $new["action"] = $payload["action"];
            $new->save();
          }
          else
          {
            $test["action"] = $payload["action"];
            $test->save();
          }
        }

        // rangkum
        $q = new Query();
        $hasil = $q->select(" count(a.id_user) as jumlah ")
          ->from("forum_thread_discussion_comment_user_action a")
          ->where(
            "
              id_comment = :id_comment AND
              id_user = :id_user AND
              action = 1
            ",
            [
              ":id_comment" => $payload["id_comment"],
              ":id_user" => $payload["id_user"],
            ]
          )
          ->one();
        $like = $hasil["jumlah"];

        $q = new Query();
        $hasil = $q->select(" count(a.id_user) as jumlah ")
          ->from("forum_thread_discussion_comment_user_action a")
          ->where(
            "
              id_comment = :id_comment AND
              id_user = :id_user AND
              action = 2
            ",
            [
              ":id_comment" => $payload["id_comment"],
              ":id_user" => $payload["id_user"],
            ]
          )
          ->one();
        $dislike = $hasil["jumlah"];

        // update
        $comment = ForumThreadDiscussionComment::findOne($payload["id_comment"]);
        $comment["like"] = $like;
        $comment["dislike"] = $dislike;
        $comment->save();

        return [
          "status" => "ok",
          "pesan" => "Like / dislike telah disimpan",
          "result" => $comment
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Value dan type tidak valid",
        "payload" => $payload
      ];
    }
  }


  /*
   * Menyatakan bahwa si penerbit topik telah puas dengan jawaban yang didapat.
   *
   * Method : POST
   * Request type: JSON
   * Request format:
   * {
   *   id_thread: 123,
   *   id_user : 123
   * }
   * Response type: JSON
   * Response format:
   * {
   *   status: "",
   *   pesan: "",
   *   result:
   *   {
   *   }
   * }
    * */
  public function actionPuas()
  {
    $payload = $this->GetPayload();

    $is_id_thread_valid = isset( $payload["id_thread"] );
    $is_id_user_valid = isset( $payload["id_user"] );

    $test_thread = ForumThread::findOne($payload["id_thread"]);
    $is_id_thread_valid = $is_id_thread_valid && (is_null( $test_thread ) == false);

    $test_user = User::findOne($payload["id_user"]);
    $is_id_user_valid = $is_id_user_valid && (is_null( $test_user ) == false);

    if( $is_id_thread_valid && $is_id_user_valid == true )
    {
      $detail_thread = $this->GetDetail($test_thread);

      // pastikan si user adalah penerbit topik
      if( $test_thread["id_user_create"] == $test_user["id"] )
      {
        // kirim notifikasi kepada para manajer konten
        // dilakukan berdasarkan id_kategori dan id_instansi
        $id_kategori = $test_thread["id_kategori"];
        $id_department = $test_user["id_departments"];

        $q = new Query();
        $daftar_manager = 
          $q->select("u.*")
            ->from("user u")
            ->join("join", "kategori_user ku", "ku.id_user = u.id")
            ->join("join", "user_roles ur", "ur.id_user = u.id")
            ->where(
              [
                "and",
                "u.id_departments = :id_instansi",
                "ku.id_kategori = :id_kategori",
                "ur.id_roles = :id_role"
              ],
              [
                ":id_instansi" => $test_user["id_departments"],
                ":id_kategori" => $test_thread["id_kategori"],
                ":id_role" => Roles::IdByCodeName("manager_konten")
              ]
            )
            ->all();

        // kirim notifikasi
        $daftar_email = [];
        foreach($daftar_manager as $manager)
        {
          if( $manager["email"] != "" )
          {
            $temp = [];
            $temp["email"] = $manager["email"];
            $temp["nama"] = $manager["nama"];

            $daftar_email[] = $temp;
          }
        }

        $test = Notifikasi::Kirim(
          [
            "type" => "topik_puas",
            "thread" => $test_thread,
            "daftar_email" => $daftar_email,
            "detail_thread" => $detail_thread,
          ]
        );

        if($test == true)
        {
          // set flag is_puas
          $test_thread["is_puas"] = 1;
          $test_thread["time_puas"] = date("Y-m-j H:i:s");
          $test_thread->save();

          return [
            "status" => "ok",
            "pesan" => "Puas berhasil disimpan. hahahahahahah",
            "result" => [
              "thread" => $test_thread,
            ]
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Gagal saat kirim email. Cek log.",
            "result" => [
              "thread" => $test_thread,
            ]
          ];
        }


      }
      else
      {
        return [
          "status" => "not ok",
          "pesan" => "User bukan penulis thread",
          "payload" => $payload,
        ];
      }
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "id_thread atau id_user tidak dikenal",
        "payload" => $payload,
      ];
    }
  }



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
                ["in", "status", $payload['status']],
                "is_delete = 0"
              ],
              [
                ":id_user" => $payload["id_user"],
              ]
            )
            ->orderBy("time_create desc")
            ->all();

          $hasil = [];
          $client = $this->SetupGuzzleClient();
          foreach($list_thread as $thread)
          {
            $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

            $user = User::findOne($thread["id_user_create"]);

            $temp = [];
            $temp["record"]["forum_thread"] = $thread;
            $temp["record"]["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
            $temp["record"]["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
            $temp["record"]["user_create"] = $user;
            $temp["data_user"]["departments"] = Departments::NameById($user["id_departments"]);
            $temp['data_user']['user_image'] = User::getImage($user->id_file_profile);
            $temp['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

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
              $temp["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
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

      // Menghitung jumlah topik per status berdasarkan iduser.
      public function actionStats()
      {
        $payload = $this->GetPayload();

        $is_id_user_valid = isset( $payload['id_user'] );

        if($is_id_user_valid)
        {
          $q = new Query();
          $hasil = $q
            ->select("t.status, count(t.status) as jumlah")
            ->from("forum_thread t")
            ->where(
              [
                "and",
                "id_user_create = :id_user",
                "is_delete = 0"
              ],
              [
                ":id_user" => $payload['id_user']
              ]
            )
            ->distinct()
            ->groupBy("t.status")
            ->all();

          return [
            "status" => "ok",
            "hasil" => $hasil,
          ];
        }
        else
        {
          return [
            "status" => "not ok",
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

      /*
       * Menandakan suatu jawaban sebagai "dipilih"
       *
       * Request type: JSON
       * Request format:
       * {
       *   "id_thread": 123,
       *   "id_jawaban": 123
       *   "select_type": 1/0 (1=select; 0=unselect)
       * }
       * Response type: JSON
       * Response format:
       * {
       *   "status": "ok",
       *   "pesan": "",
       *   "result":
       *   {
       *     "forum_thread": { object of thread record },
       *     "jawaban": [{}, ...]
       *   } 
       * }
        * */
      public function actionMtpilihjawaban()
      {
        $payload = $this->GetPayload();

        $is_id_thread_valid = isset($payload["id_thread"]);
        $is_id_jawaban_valid = isset($payload["id_jawaban"]);
        $is_select_type_valid = isset($payload["select_type"]);

        if(
            $is_id_thread_valid == true &&
            $is_id_jawaban_valid == true &&
            $is_select_type_valid == true 
          )
        {
          $thread = ForumThread::findOne($payload["id_thread"]);
          if( is_null($thread) == true )
          {
            return [
            "status" => "not ok",
            "pesan" => "Record thread tidak ditemukan",
            "payload" => $payload
            ];
          }

          if( is_numeric($payload["id_jawaban"]) == true )
          {
            if( $payload["id_jawaban"] > -1 )  // id_jawaban = -1 berarti membatalkan jawaban
            {
              $jawaban = ForumThreadDiscussion::findOne(
                [
                  "id_thread" => $payload["id_thread"], 
                  "id" => $payload["id_jawaban"]
                ]
              );

              if( is_null($jawaban) == true )
              {
                return [
                  "status" => "not ok",
                  "pesan" => "Record jawaban tidak ditemukan",
                  "payload" => $payload
                ];
              }
            }
          }

          switch( intval($payload["select_type"]) )
          {
            case 0: //unselect
              /* $test = ForumThreadDiscussionSelected::findOne( */
              /*   [ */
              /*     "id_thread" => $payload["id_thread"], */
              /*     "id_discussion" => $payload["id_jawaban"], */
              /*   ] */
              /* ); */

              /* if( is_null($test) == false ) */
              /* { */
              /*   $test->delete(); */
              /* } */

              $jawaban = ForumThreadDiscussion::findOne($payload["id_jawaban"]);
              $jawaban["is_answer"] = 0;
              $jawaban->save();
              break;

            case 1: //select
              /* $test = ForumThreadDiscussionSelected::findOne( */
              /*   [ */
              /*     "id_thread" => $payload["id_thread"], */
              /*     "id_discussion" => $payload["id_jawaban"], */
              /*   ] */
              /* ); */

              /* if( is_null($test) == true ) */
              /* { */
              /*   $test = new ForumThreadDiscussionSelected(); */
              /*   $test["id_thread"] = $payload["id_thread"]; */
              /*   $test["id_discussion"] = $payload["id_jawaban"]; */
              /*   $test->save(); */
              /* } */

              $jawaban = ForumThreadDiscussion::findOne($payload["id_jawaban"]);
              $jawaban["is_answer"] = 1;
              $jawaban->save();
              break;

            default:
              return [
                "status" => "not ok",
                "pesan" => "select_type value is invalid. Should 0 or 1.",
                "payload" => $payload
              ];
              break;
          }

          // menyiapkan response
          $list_jawaban = [];
          $temp_list_jawaban = ForumThreadDiscussion::findAll(
            ["id_thread" => $thread["id"]]
          );
          foreach($temp_list_jawaban as $item_jawaban)
          {

            $temp = [];
            $temp["jawaban"] = $item_jawaban;

            $list_komentar = ForumThreadDiscussionComment::findAll(
              ["id_discussion" => $item_jawaban["id"]]
            );

            $temp["list_komentar"] = [];
            foreach( $list_komentar as $item_komentar )
            {
              $user = User::findOne($item_komentar["id_user_create"]);

              $temp2 = [];
              $temp2["komentar"] = $item_komentar;
              $temp2["user_create"] = $user;

              $temp["list_komentar"][] = $temp2;
            }

            $list_jawaban[] = $temp;

          }

          $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

          return [
            "status" => "ok",
            "pesan" => "Jawaban sudah dipilih",
            "result" => [
              "forum_thread" => $thread,
              "jawaban" => 
              [
                "count" => count($list_jawaban),
                "records" => $list_jawaban,
              ]
            ]
          ];
        }
        else
        {
          return [
            "status" => "not ok",
            "pesan" => "Parameter yang dibutuhkan tidak lengkap",
            "payload" => $payload
          ];
        }
      }


  // ==========================================================================
  // my threads
  // ==========================================================================


  // Mengambil informasi detail terkait record thread.
  //
  private function GetDetail($thread)
  {
    $user = User::findOne($thread["id_user_create"]);

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

    $thread["konten"] = html_entity_decode($thread["konten"], ENT_QUOTES);

    $hasil = [];
    $hasil["record"]["forum_thread"] = $thread;
    $hasil["record"]["category_path"] = KmsKategori::CategoryPath($thread["id_kategori"]);
    $hasil["record"]["user_create"] = $user;
    $hasil["record"]["tags"] = ForumThreadTag::GetThreadTags($thread["id"]);
    $hasil["record"]["status_info"] = ForumThread::GetStatusInfo($thread['id']);
    $hasil["data_user"]["departments"] = Departments::NameById($user["id_departments"]);
    $hasil['data_user']['user_image'] = User::getImage($user->id_file_profile);
    $hasil['data_user']['thumb_image'] = BaseUrl::base(true) . "/files/" .User::getImage($user->id_file_profile);

    switch( $res->getStatusCode() )
    {
      case 200:
        // ambil id dari result
        $response_payload = $res->getBody();
        $response_payload = Json::decode($response_payload);

        $hasil["record"]["confluence"]["status"] = "ok";
        $hasil["record"]["confluence"]["linked_id_question"] = $response_payload["id"];
        $hasil["record"]["confluence"]["judul"] = $response_payload["title"];
        $hasil["record"]["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
        break;

      default:
        // kembalikan response
        $hasil["record"]["confluence"]["status"] = "not ok";
        $hasil["record"]["confluence"]["judul"] = $response_payload["title"];
        $hasil["record"]["confluence"]["konten"] = html_entity_decode($response_payload["body"]["content"], ENT_QUOTES);
        break;
    }

    return $hasil;
  }
}
