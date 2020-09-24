<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\helpers\Json;
use yii\db\Query;

use app\models\KmsArtikel;
use app\models\KmsArtikelActivityLog;
use app\models\KmsArtikelUserStatus;
use app\models\KmsArtikelTag;
use app\models\KmsTags;
use app\models\User;
use app\models\KmsKategori;

use Carbon\Carbon;

class ArticleController extends \yii\rest\Controller
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

        'item, items'           => ['GET'],
        'attachments'           => ['POST'],
        'logsbytags'            => ['GET'],
        'categoriesbytags'      => ['GET'],
        'itemtag'               => ['POST'],
        'logsbyfilter'          => ['GET'],
        'artikeluserstatus'     => ['POST'],
        'itemkategori'          => ['PUT'],
        'search'                => ['GET'],
        'status'                => ['PUT'],
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
  private function ArtikelLog($id_artikel, $id_user, $type_log, $log_value)
  {
    $new = new KmsArtikelActivityLog();
    $new["id_artikel"] = $id_artikel;
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

    KmsArtikelActivityLog::Summarize($id_artikel);
  }

  private function ActivityLog($id_artikel, $id_user, $type_action)
  {
    $log = new KmsArtikelActivityLog();
    $log["id_artikel"] = $id_artikel;
    $log["id_user"] = $id_user;
    $log["time_action"] = date("Y-m-j H:i:s");
    $log["type_action"] = $type_action;
    $log->save();
  }

  private function Conf_GetArtikel($client, $linked_id_content)
  {
    $jira_conf = Yii::$app->restconf->confs['confluence'];
    $res = $client->request(
      'GET',
      "/rest/api/content/$linked_id_content",
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




  //  Membuat record artikel
  //
  //  Method : POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "judul": "",
  //    "body": "",
  //    "id_kategori": "",
  //    "status": -2/-1/0/1/2/3/4 (-2=draft-rangkuman; -1=draft; 0=new; 1=publish; 2=un-publish; 3=reject; 4=freeze)
  //    "linked_id_thread": 123,
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

      $request_payload = [
        'type' => 'page',
        'title' => $payload['judul'],
        'space' => [
          'key' => 'PS',
        ],
        'body' => [
          'storage' => [
            'value' => $payload['body'],
            'representation' => 'storage',
          ],
        ],
      ];

      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";

      $client = new \GuzzleHttp\Client([
        'base_uri' => $base_url
      ]);

      $res = null;
      try
      {
        $res = $client->request(
          'POST',
          "/rest/api/content",
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

            $linked_id_artikel = $response_payload['id'];
            $linked_id_thread = $payload['linked_id_thread'];


            // bikin record kms_artikel
            $artikel = new KmsArtikel();
            $artikel['linked_id_content'] = $linked_id_artikel;
            $artikel['linked_id_thread'] = $linked_id_thread;
            $artikel['time_create'] = date("Y-m-j H:i:s");
            $artikel['id_user_create'] = $payload['id_user'];
            $artikel['id_kategori'] = $payload['id_kategori'];
            $artikel['status'] = $payload["status"];
            $artikel->save();
            $id_artikel = $artikel->primaryKey;


            // menyimpan informasi tags
            $tags = array();
            if( is_array($payload["tags"]) == true )
            {
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
                  $new["id_user_create"] = 123;
                  $new["time_create"] = date("Y-m-j H:i:s");
                  $new->save();

                  $id_tag = $new->primaryKey;
                }

                // relate id_artikel dengan id_tag
                $new = new KmsArtikelTag();
                $new["id_artikel"] = $id_artikel;
                $new["id_tag"] = $id_tag;
                $new->save();

                $temp = [];
                $temp["prefix"] = "global";
                $temp["name"] = $tag["nama"];
                $tags[] = $temp;
              } // loop tags

              // kirim tag ke Confluence
              $res = $client->request(
                'POST',
                "/rest/api/content/$linked_id_artikel/label",
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
                  'body' => Json::encode($tags),
                ]
              );

              $tags = KmsArtikelTag::find()
                ->where(
                  "id_artikel = :id_artikel",
                  [
                    ":id_artikel" => $id_artikel
                  ]
                )
                ->all();
            } // hanya jika tags terdefinisi


            $this->ArtikelLog($id_artikel, $payload["id_user"], 1, $payload["status"]);

            // kembalikan response
            return 
            [
              'status' => 'ok',
              'pesan' => 'Record artikel telah dibikin',
              'result' => 
              [
                "artikel" => $artikel,
                "tags" => $tags,
                "category_path" => KmsKategori::CategoryPath($artikel["id_kategori"]),
              ]
            ];
            break;

          default:
            // kembalikan response
            return [
              'status' => 'not ok',
              'pesan' => 'REST API request failed: ' . $res->getBody(),
              'result' => $artikel
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
        'pesan' => 'Parameter yang dibutuhkan tidak ada: judul, konten, id_kategori, tsgs',
      ];
    }

  }

  //  Menghapus (soft delete) suatu artikel
  //  Hanya dilakukan pada database SPBE
  //
  //  Method: DELETE
  //  Request type: JSON
  //  Request format:
  //  {
  //    "id_artikel": 123,
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

    $is_id_artikel_valid = isset($payload["id_artikel"]);
    $is_id_user_actor_valid = isset($payload["id_user_actor"]);

    $is_id_artikel_valid = $is_id_artikel_valid && is_numeric($payload["id_artikel"]);
    $is_id_user_actor_valid = $is_id_user_actor_valid && is_numeric($payload["id_user_actor"]);

    $test = KmsArtikel::findOne($payload["id_artikel"]);
    if( is_null($test) == true )
    {
      return [
        "status" => "not ok",
        "pesan" => "Record artikel tidak ditemukan",
      ];
    }

    $test = User::findOne($payload["id_user_actor"]);
    if( is_null($test) == true )
    {
      return [
        "status" => "not ok",
        "pesan" => "Record user tidak ditemukan",
      ];
    }

    if( $is_id_artikel_valid == true && $is_id_user_actor_valid == true )
    {
      $artikel = KmsArtikel::findOne($payload["id_artikel"]);
      $artikel["is_delete"] = 1;
      $artikel["time_delete"] = date("Y-m-d H:i:s");
      $artikel["id_user_delete"] = $payload["id_user_actor"];
      $artikel->save();

      $this->ArtikelLog($payload["id_artikel"], $payload["id_user_actor"], 1, 5);

      return [
        "status" => "ok",
        "pesan" => "Record berhasil di-delete",
        "result" => 
        [
          "record" => $artikel,
          "payload" => $payload
        ]
      ];
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Record gagal di-delete",
        "result" => 
        [
          "record" => $artikel,
          "payload" => $payload
        ]
      ];
    }

  }

  public function actionRetrieve()
  {
      return $this->render('retrieve');
  }

  //  Mengupdate record artikel
  //
  //  Method : POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "id": 123,
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
    $id_user = true;
    $id_valid = true;
    $judul_valid = true;
    $body_valid = true;
    $kategori_valid = true;
    $tags_valid = true;

    // pastikan request parameter lengkap
    $payload = $this->GetPayload();

    if( isset($payload["id_user"]) == false )
      $id_user = false;

    if( isset($payload["id"]) == false )
      $id_valid = false;

    if( isset($payload["judul"]) == false )
      $judul_valid = false;

    if( isset($payload["body"]) == false )
      $body_valid = false;

    if( isset($payload["id_kategori"]) == false )
      $kategori_valid = false;

    if( isset($payload["tags"]) == false )
      $tags_valid = false;

    if( 
        $id_valid == true && $judul_valid == true && $body_valid == true &&
        $kategori_valid == true && $tags_valid == true 
      )
    {
      // ambil nomor versi bersadarkan id_linked_content
          $artikel = KmsArtikel::findOne($payload["id"]);
          
          $jira_conf = Yii::$app->restconf->confs['confluence'];
          $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";
          Yii::info("base_url = $base_url");
          $client = new \GuzzleHttp\Client([
            'base_uri' => $base_url
          ]);

          $res = null;
          $res = $client->request(
            'GET',
            "/rest/api/content/{$artikel["linked_id_content"]}",
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
                'status' => 'current',
                'expand' => 'body.view,version',
              ],
              'body' => Json::encode($request_payload),
            ]
          );
          $response = Json::decode($res->getBody());
          $version = $response["version"]["number"] + 1;
      // ambil nomor versi bersadarkan id_linked_content

      // update content
      $request_payload = [
        'version' => [
          'number' => $version
        ],
        'title' => $payload['judul'],
        'type' => 'page',
        'space' => [
          'key' => 'PS',
        ],
        'body' => [
          'storage' => [
            'value' => $payload['body'],
            'representation' => 'storage',
          ],
        ],
      ];


      $res = null;
      try
      {
        // update kontent artikel pada confluence
        $res = $client->request(
          'PUT',
          "/rest/api/content/{$artikel["linked_id_content"]}",
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
              'status' => 'current',
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

            $linked_id_artikel = $response_payload['id'];


            // update record kms_artikel
            $artikel = KmsArtikel::findOne($payload["id"]);
            $artikel['time_update'] = date("Y-m-j H:i:s");
            $artikel['id_user_update'] = $payload["id_user"];
            $artikel['id_kategori'] = $payload["id_kategori"];
            $artikel['linked_id_thread'] = $payload["linked_id_thread"];
            $artikel->save();

            // mengupdate informasi tags

                //hapus label pada confluence
                    $this->DeleteTags($client, $jira_conf, $artikel["linked_id_content"]);
                //hapus label pada confluence

                //hapus label pada spbe
                    KmsArtikelTag::deleteAll("id_artikel = {$artikel["id"]}");
                //hapus label pada spbe


                // refresh tag/label
                    $this->UpdateTags($client, $jira_conf, $artikel["id"], $artikel["linked_id_content"], $payload);
                // refresh tag/label
                     
            // mengupdate informasi tags


            //$this->ArtikelLog($payload["id_artikel"], $payload["id_user"], 1, $payload["status"]);

            // kembalikan response
                $tags = KmsArtikelTag::find()
                  ->where(
                    "id_artikel = :id_artikel",
                    [
                      ":id_artikel" => $artikel["id"]
                    ]
                  )
                  ->all();

                return 
                [
                  'status' => 'ok',
                  'pesan' => 'Record artikel telah diupdate',
                  'result' => 
                  [
                    "artikel" => $artikel,
                    "tags" => $tags
                  ]
                ];
            // kembalikan response
            break;

          default:
            // kembalikan response
            return [
              'status' => 'not ok',
              'pesan' => 'REST API request failed: ' . $res->getBody(),
              'result' => $artikel
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

      // update content

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


  private function DeleteTags($client, $jira_conf, $linked_id_content)
  {
    $res = $client->request(
      'GET',
      "/rest/api/content/{$linked_id_content}/label",
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
        "/rest/api/content/{$linked_id_content}/label/{$object["name"]}",
        [
          /* 'sink' => Yii::$app->basePath . "/guzzledump2.txt", */
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

  private function UpdateTags($client, $jira_conf, $id_artikel, $linked_id_content, $payload)
  {
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
        $new["id_user_create"] = 123;
        $new["time_create"] = date("Y-m-j H:i:s");
        $new->save();

        $id_tag = $new->primaryKey;
      }

      // relate id_artikel dengan id_tag
      $new = new KmsArtikelTag();
      $new["id_artikel"] = $id_artikel;
      $new["id_tag"] = $id_tag;
      $new->save();

      $temp = [];
      $temp["prefix"] = "global";
      $temp["name"] = $tag;
      $tags[] = $temp;
    } // loop tags

    // kirim tag ke Confluence
    $res = $client->request(
      'POST',
      "/rest/api/content/{$linked_id_content}/label",
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
        'body' => Json::encode($tags),
      ]
    );
  }

  /*
   *  Mengambil kms_artikel_activity_log berdasarkan filter yang dapat disetup 
   *  secara dinamis. 
   *
   *  Digunakan untuk menampilkan daftar user beserta informasi statistiknya 
   *  (jumlah create, view, like, dislike). 
   *
   *  Atau menampilkan daftar artikel berserta informasi statistiknya (jumlah 
   *  view, like, dislike).
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "object_type": "a/u",
   *    "filter":
   *    {
   *      "tanggal_awal"    : "y-m-j",
   *      "tanggal_akhir"   : "y-m-j",
   *      "id_kategori"     : 123,
   *      "id_artikel"      : 123,
   *      "status"          : [-1, 0, 1, 2, 3, 4],  
   *      "actions"         : 
   *      [
   *        {
   *          "action": -1,  // -1 = view; 1 = like; 2 = dislike
   *          "min": 123,
   *          "max": 123
   *        }, ...
   *      ],    
   *    }
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan" : "",
   *    "filter" : ...,
   *    "result" :
   *    {
   *      "id_kategori": 123,
   *      "id_artikel": 123
 *        "actions":
 *        [
 *          {
 *            "action": 1,
 *            "count": 123
 *          }, ...
 *        ],
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
      case $payload["object_type"] == "a" :
        $q->select("a.id");
        $q->from("kms_artikel a");
        $q->join("join", "kms_artikel_activity_log l", "l.id_artikel = a.id");
        break;

      case $payload["object_type"] == "u" :
        $q->select("u.id");
        $q->from("user u");
        $q->join("join", "kms_artikel_activity_log l", "l.id_user = u.id");
        break;

      default :
        return [
          "status" => "not ok",
          "pesan" => "Parameter tidak valid. object_type diisi dengan 'a' atau 'u'",
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
          $q->join("join", "kms_artikel a2", "a2.id = l.id_artikel");
          /* $q->join("join", "kms_kategori k", "a2.id_kategori = k.id"); */

          $temp = [];
          foreach( $value as $id_kategori )
          {
            $temp[] = $id_kategori;
          }
          $where[] = ["in", "a2.id_kategori", $temp];
        break;

        case $key == "id_artikel":
          $where[] = "l.id_artikel = " . $value;
        break;
      }// switch filter key
    } //loop keys in filter

    $q->where($where);

    if( $payload["object_type"] == "a" )
    {
      $q->distinct()
        ->groupBy("a.id")
        ->orderBy("l.time_action desc");
    }
    else
    {
      $q->distinct()
        ->groupBy("u.id")
        ->orderBy("l.time_action desc");
    }

    //execute the query
    $records = $q->all();

    $hasil = [];
    foreach($records as $record)
    {
      if( $payload["object_type"] == "a" )
      {
        $artikel = KmsArtikel::findOne($record["id"]);
        $user = User::findOne($artikel["id_user_create"]);

        $client = $this->SetupGuzzleClient();
        $response = $this->Conf_GetArtikel($client, $artikel["linked_id_content"]);
        $response_payload = $response->getBody();
        $response_payload = Json::decode($response_payload);

        $temp = [];
        $temp["kms_artikel"] = $artikel;
        $temp["category_path"] = KmsKategori::CategoryPath($artikel["id_kategori"]);
        $temp["data_user"]["user_create"] = $user["nama"];
        $temp["tags"] = KmsArtikelTag::GetArtikelTags($artikel["id"]);
        $temp["category_path"] = KmsKategori::CategoryPath($artikel["id_kategori"]);
        $temp["confluence"]["id"] = $response_payload["id"];
        $temp["confluence"]["judul"] = $response_payload["title"];
        $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];

        // filter by action
        // ================
            // berapa banyak action yang diterima suatu artikel dalam rentang waktu tertentu?

            //ambil data view
            $type_action = -1;
            $temp["view"] = KmsArtikel::ActionReceivedInRange($artikel["id"], $type_action, $tanggal_awal, $tanggal_akhir);
            
            //ambil data like
            $type_action = 1;
            $temp["like"] = KmsArtikel::ActionReceivedInRange($artikel["id"], $type_action, $tanggal_awal, $tanggal_akhir);

            //ambil data dislike
            $type_action = 2;
            $temp["dislike"] = KmsArtikel::ActionReceivedInRange($artikel["id"], $type_action, $tanggal_awal, $tanggal_akhir);
        // ================
        // filter by action

        // filter by status
        // ================
            // apakah suatu artikel mengalami status tertentu dalam rentang waktu?

            //ambil data draft-rangkuman
            $type_status = -2;
            $temp["draftrangkuman"] = KmsArtikel::StatusInRange($artikel["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data draft
            $type_status = -1;
            $temp["draft"] = KmsArtikel::StatusInRange($artikel["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data new
            $type_status = 0;
            $temp["new"] = KmsArtikel::StatusInRange($artikel["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data publish
            $type_status = 1;
            $temp["publish"] = KmsArtikel::StatusInRange($artikel["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data un-publish
            $type_status = 2;
            $temp["unpublish"] = KmsArtikel::StatusInRange($artikel["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data reject
            $type_status = 3;
            $temp["reject"] = KmsArtikel::StatusInRange($artikel["id"], $type_status, $tanggal_awal, $tanggal_akhir);

            //ambil data freeze
            $type_status = 4;
            $temp["freeze"] = KmsArtikel::StatusInRange($artikel["id"], $type_status, $tanggal_awal, $tanggal_akhir);

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
          case $status == -2:  //draft-rangkuman
            if($temp["draftrangkuman"] > 0)
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

        //ambil data view
        $type_action = -1;
        $temp["view"] = KmsArtikel::ActionByUserInRange($user["id"], $type_action, $tanggal_awal, $tanggal_akhir);
        
        //ambil data like
        $type_action = 1;
        $temp["like"] = KmsArtikel::ActionByUserInRange($user["id"], $type_action, $tanggal_awal, $tanggal_akhir);

        //ambil data dislike
        $type_action = 2;
        $temp["dislike"] = KmsArtikel::ActionByUserInRange($user["id"], $type_action, $tanggal_awal, $tanggal_akhir);

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

        if($is_valid == true)
          $hasil[] = $temp;
      }
    }

    return [
      "status" => "ok",
      "pesan" => "Record berhasil diambil",
      "payload" => $payload,
      "result" => 
      [
        "count" => count($hasil),
        "records" => $hasil
      ]
    ];
  }

  /*
   *  Mengambil kms_artikel atau user berdasarkan filter yang dapat disetup secara dinamis.
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
   *                "kms_artikel": { object_of_record_artikel },
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
   *        artikel-artikel dari suatu kategori
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
   *        Mengambil daftar artikel dari suatu kategori dan mengalami STATUS
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
   *                "kms_artikel": { object_of_record_artikel },
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
              ->from("kms_artikel a")
              ->join("JOIN", "kms_artikel_user_status l", "l.id_artikel = a.id");

            if( isset($payload["filter"]["action"]) )
            {
              $q->andWhere(["in", "l.status", $payload["filter"]["action"]]);
            }
            else
            {
              $q->andWhere(["in", "a.status", $payload["filter"]["status"]]);
            }

            $q->andWhere(["in", "a.id_kategori", $payload["filter"]["id_kategori"]]);

            $q->distinct();
            $q->groupBy("a.id");
          }
          else
          {
            $q->select("u.id")
              ->from("user u")
              ->join("JOIN", "kms_artikel_user_status l", "l.id_user = u.id")
              ->join("JOIN", "kms_artikel a", "l.id_artikel = a.id");

            $q->andWhere(["in", "l.status", $payload["filter"]["action"]]);

            $q->andWhere(["in", "a.id_kategori", $payload["filter"]["id_kategori"]]);

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
          $artikel = KmsArtikel::findOne($record["id"]);
          $tags = KmsArtikelTag::GetArtikelTags($artikel["id"]);
          $user_create = User::findOne($artikel["id_user_create"]);
          $response = $this->Conf_GetArtikel($client, $artikel["linked_id_content"]);
          $response_payload = $response->getBody();
          $response_payload = Json::decode($response_payload);

          $temp = [];
          $temp["kms_artikel"] = $artikel;
          $temp["user_create"] = $user_create;
          $temp["tags"] = $tags;
          $temp["confluence"]["status"] = "ok";
          $temp["confluence"]["linked_id_content"] = $response_payload["id"];
          $temp["confluence"]["judul"] = $response_payload["title"];
          $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];
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
   *  Mengambil daftar artikel berdasarkan idkategori, page_no, items_per_page.
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
   *            <object dari record kms_artikel>
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
      //  lakukan query dari tabel kms_artikel
      $test = KmsArtikel::find()
        ->where([
          "and",
          "is_delete = 0",
          "is_publish = 0",
          ["in", "id_kategori", $payload["id_kategori"]]
        ])
        ->orderBy("time_create desc")
        ->all();
      $total_rows = count($test);

      $list_artikel = KmsArtikel::find()
        ->where([
          "and",
          "is_delete = 0",
          "is_publish = 0",
          ["in", "id_kategori", $payload["id_kategori"]]
        ])
        ->orderBy("time_create desc")
        ->offset( $payload["items_per_page"] * ($payload["page_no"] - 1) )
        ->limit( $payload["items_per_page"] )
        ->all();

      //  lakukan query dari Confluence
      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";
      Yii::info("base_url = $base_url");
      $client = new \GuzzleHttp\Client([
        'base_uri' => $base_url
      ]);

      $hasil = [];
      foreach($list_artikel as $artikel)
      {
        $user = User::findOne($artikel["id_user_create"]);

        $res = $client->request(
          'GET',
          "/rest/api/content/{$artikel["linked_id_content"]}",
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
            $temp["kms_artikel"] = $artikel;
            $temp["category_path"] = KmsKategori::CategoryPath($artikel["id_kategori"]);
            $temp["tags"] = KmsArtikelTag::GetArtikelTags($artikel["id"]);
            // $hasil["user_create"] = $user;
            $temp["confluence"]["status"] = "ok";
            $temp["confluence"]["linked_id_content"] = $response_payload["id"];
            $temp["confluence"]["judul"] = $response_payload["title"];
            $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];
            $temp['data_user']['user_create'] = $user->nama;

            $hasil[] = $temp;
            break;

          default:
            // kembalikan response
            $temp = [];
            $temp["kms_artikel"] = $artikel;
            $temp["category_path"] = KmsKategori::CategoryPath($artikel["id_kategori"]);
            $temp["tags"] = KmsArtikelTag::GetArtikelTags($artikel["id"]);
            // $hasil["user_create"] = $user;
            $temp["confluence"]["status"] = "not ok";
            $temp["confluence"]["judul"] = $response_payload["title"];
            $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];
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
          "count" => count($list_artikel),
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
   *  Mengambil record artikel berdasarkan id_artikel
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_artikel": 123
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok/nor ok",
   *    "pesan": "",
   *    "result":
   *    {
   *      "record":
   *      {
   *        "kms_artikel":
   *        {
   *          <object dari record kms_artikel>
   *        },
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
    $is_id_artikel_valid = isset($payload["id_artikel"]);

    if(
        $is_id_artikel_valid == true
      )
    {
      //  lakukan query dari tabel kms_artikel
      $artikel = KmsArtikel::find()
        ->where([
            "and",
            "id = :id_artikel",
          ],
          [
            ":id_artikel" => $payload["id_artikel"]
          ]
        )
        ->one();

      $user = User::findOne($artikel["id_user_create"]);

      //  lakukan query dari Confluence
      $client = $this->SetupGuzzleClient();
      $jira_conf = Yii::$app->restconf->confs['confluence'];

      $hasil = [];
      $res = $client->request(
        'GET',
        "/rest/api/content/{$artikel["linked_id_content"]}",
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
        $hasil["kms_artikel"] = $artikel;
        $hasil["category_path"] = KmsKategori::CategoryPath($artikel["id_kategori"]);
        $hasil["user_create"] = $user;
        $hasil["tags"] = KmsArtikelTag::GetArtikelTags($artikel["id"]);
        $hasil["confluence"]["status"] = "ok";
        $hasil["confluence"]["linked_id_content"] = $response_payload["id"];
        $hasil["confluence"]["judul"] = $response_payload["title"];
        $hasil["confluence"]["konten"] = $response_payload["body"]["view"]["value"];

        $this->ArtikelLog($payload["id_artikel"], -1, 2, -1);
        break;

      default:
        // kembalikan response
        $hasil = [];
        $hasil["kms_artikel"] = $artikel;
        $hasil["user_create"] = $user;
        $hasil["tags"] = KmsArtikelTag::GetArtikelTags($artikel["id"]);
        $hasil["confluence"]["status"] = "not ok";
        $hasil["confluence"]["judul"] = $response_payload["title"];
        $hasil["confluence"]["konten"] = $response_payload["body"]["view"]["value"];
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
   *  Menyimpan status antara user dan artikel. Apakah si user menyatakan like,
   *  dislike terhadap suatu artikel. Informasi disimpan pada tabel kms_artikel_user_status
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_artikel": 123,
   *    "id_user": 123,
   *    "status": 0/1/2,
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "ok",
   *    "pesan": "",
   *    "result":
   *    {
   *      <object record kms_artikel_user_status>
   *    }
   *  }
    * */
  public function actionArtikeluserstatus()
  {
    $payload = $this->GetPayload();

    // cek apakah parameter lengkap
    $is_id_artikel_valid = isset($payload["id_artikel"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_status_valid = isset($payload["status"]);

    if(
        $is_id_artikel_valid == true &&
        $is_id_user_valid == true &&
        $is_status_valid == true
      )
    {
      // memastikan id_artikel dan id_user valid
      $test = KmsArtikel::findOne($payload["id_artikel"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Artikel's record not found",
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

      if( $payload["status"] != 0 && $payload["status"] != 1 && $payload["status"] != 2 )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Status value is not valid.",
        ];
      }

      // cek record kms_artikel_user_status. insert/update record
      $test = KmsArtikelUserStatus::find()
        ->where(
          [
            "and",
            "id_artikel = :idartikel",
            "id_user = :iduser"
          ],
          [
            ":idartikel" => $payload["id_artikel"],
            ":iduser" => $payload["id_user"],
          ]
        )
        ->one();

      if( is_null($test) == true )
      {
        $test = new KmsArtikelUserStatus();
      }

      if( $test["status"] != $payload["status"] )
      {
        //  Aktifitas akan direkam jika mengakibatkan perubahan status pada
        //  artikel.

        $test["id_artikel"] = $payload["id_artikel"];
        $test["id_user"] = $payload["id_user"];
        $test["status"] = $payload["status"];
        $test->save();

        // tulis log
        $this->ArtikelLog($payload["id_artikel"], $payload["id_user"], 2, $payload["status"]);

        // kembalikan response
        return [
          "status" => "ok",
          "pesan" => "Status saved. Log saved.",
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
        "pesan" => "Parameter yang dibutuhkan tidak lengkap: id_artikel, id_user, status",
      ];
    }


  }

  /*
   *  Mengganti id_kategori atas suatu artikel. Kemudian penyimpan jejak perubahan
   *  ke dalam tabel kms_artikel_log
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_artikel": 123,
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
   *      <object record artikel>
   *    }
   *  }
    * */
  public function actionItemkategori()
  {
    $payload = $this->GetPayload();

    // cek apakah parameter lengkap
    $is_id_artikel_valid = isset($payload["id_artikel"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_id_kategori_valid = isset($payload["id_kategori"]);

    if(
        $is_id_artikel_valid == true &&
        $is_id_user_valid == true &&
        $is_id_kategori_valid == true
      )
    {
      // memastikan id_artikel, id_kategori dan id_user valid
      $test = KmsArtikel::findOne($payload["id_artikel"]);
      if( is_null($test) == true )
      {
        return [
          "status"=> "not ok",
          "pesan"=> "Artikel's record not found",
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

      // update kms_artikel
      $artikel = KmsArtikel::findOne($payload["id_artikel"]);
      $artikel["id_kategori"] = $payload["id_kategori"];
      $artikel->save();

      //  simpan history pada tabel kms_artikel_activity_log
      /* $log = new KmsArtikelActivityLog(); */
      /* $log["id_artikel"] = $payload["id_artikel"]; */
      /* $log["id_user"] = $payload["id_user"]; */
      /* $log["type_action"] = $payload["status"]; */
      /* $log["time_action"] = date("Y-m-j H:i:s"); */
      /* $log->save(); */

      return [
        "status" => "ok",
        "pesan" => "id_kategori artikel sudah disimpan",
        "result" => $artikel,
        "category_path" => KmsKategori::CategoryPath($artikel["id_kategori"])
      ];
    }
    else
    {
      // kembalikan response
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak lengkap: id_artikel, id_kategori, id_user",
      ];
    }



  }

  /*
   *  Mencari artikel berdasarkan daftar id_kategori dan keywords. Search keywords
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

        $keywords .= "(text ~ $keyword)";
      }
      $keywords = "($keywords)";

      //ambil daftar linked_id_content berdasarkan array id_kategori
      $daftar_artikel = KmsArtikel::find()
        ->where(
          [
            "id_kategori" => $payload["id_kategori"],
            "is_delete" => 0,
            "is_publish" => 0,
            ["in", "status", [1]]
          ]
        )
        ->all();

      $daftar_id = "";
      foreach($daftar_artikel as $artikel)
      {
        if($daftar_id != "")
        {
          $daftar_id .= ", ";
        }

        $daftar_id .= $artikel["linked_id_content"];
      }
      $daftar_id = "ID IN ($daftar_id)";

      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";
      $client = new \GuzzleHttp\Client([
        'base_uri' => $base_url
      ]);

      $res = $client->request(
        'GET',
        "/rest/api/content/search",
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
            'cql' => "$keywords AND $daftar_id",
            'expand' => 'body.view',
            'start' => $payload["items_per_page"] * ($payload["page_no"] - 1),
            'limit' => $payload["items_per_page"],
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
          $temp = array();
          $temp["confluence"]["status"] = "ok";
          $temp["confluence"]["linked_id_content"] = $item["id"];
          $temp["confluence"]["judul"] = $item["title"];
          $temp["confluence"]["konten"] = $item["body"]["view"]["value"];

          $artikel = KmsArtikel::find()
            ->where(
              [
                "linked_id_content" => $item["id"]
              ]
            )
            ->one();
          $user = User::findOne($artikel["id_user_create"]);
          $temp["kms_artikel"] = $artikel;
          $temp["data_user"]["user_create"] = $user->nama;
          $temp["category_path"] = KmsKategori::CategoryPath($artikel["id_kategori"]);

          $hasil[] = $temp;
        }

        return [
          "status" => "ok",
          "pesan" => "Search berhasil",
          "cql" => "$keywords AND $daftar_id",
          "result" => 
          [
            "total_rows" => $response_payload["size"],
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
          "cql" => "$keywords AND $daftar_id",
          'pesan' => 'REST API request failed: ' . $res->getBody(),
          'result' => $artikel,
          'category_path' => KmsKategori::CategoryPath($artikel["id_kategori"])
        ];
        break;
      }

    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang dibutuhkan tidak valid. search_keyword (string), id_kategori (array)",
        "payload" => Json::encode($payload)
      ];
    }

  }

  /*
   *  Mengubah status suatu artikel.
   *  Status artikel:
   *  0 = new
   *  1 = publish
   *  2 = un-publish
   *  3 = reject
   *  4 = freeze
   *
   *  Method: PUT
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_artikel": [123, 124, ...],
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

    $is_id_artikel_valid = isset($payload["id_artikel"]);
    $is_id_artikel_valid = $is_id_artikel_valid && is_array($payload["id_artikel"]);
    $is_status_valid = isset($payload["status"]);
    $is_status_valid = $is_status_valid && is_numeric($payload["status"]);
    $is_id_user_valid = isset($payload["id_user"]);
    $is_id_user_valid = $is_id_user_valid && is_numeric($payload["id_user"]);

    if(
        $is_id_artikel_valid == true &&
        $is_status_valid == true &&
        $is_id_user_valid == true
      )
    {
      $daftar_sukses = [];
      $daftar_gagal = [];
      foreach($payload["id_artikel"] as $id_artikel)
      {
        if( is_numeric($id_artikel) )
        {
          if( is_numeric($payload["id_user"]) )
          {
            //cek validitas id_user
            $test = User::findOne($payload["id_user"]);

            if( is_null($test) == false )
            {
              $artikel = KmsArtikel::findOne($id_artikel);
              if( is_null($artikel) == false )
              {
                $artikel["status"] = $payload["status"];
                $artikel->save();

                $daftar_sukses[] = $artikel;

                // tulis log
                $this->ArtikelLog($id_artikel, $payload["id_user"], 1, $payload["status"]);
              }
              else
              {
                $daftar_gagal[] = $id_artikel;
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
          $daftar_gagal[] = $id_artikel;
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

      // tulis log artikel
    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak ada: id_artikel (array), status",
      ];
    }
  }

  /*
   *  Mengambil daftar artikel berdasarkan kesamaan tags yang berasal dari
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
   *        "kms_artikel":
   *        {
   *          <object dari record kms_artikel>
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
          ->join("JOIN", "kms_artikel_tag atag", "atag.id_tag = t.id")
          ->join("JOIN", "kms_artikel a", "a.id = atag.id_artikel")
          ->where(["in", "a.id_kategori", $payload["id_kategori"]])
          ->distinct()
          ->all();

      $daftar_tag = [];
      foreach($temp_daftar_tag as $item)
      {
        $daftar_tag[] = $item["id"];
      }

      // mengambil daftar artikel terkait
      $q = new Query();
      $daftar_artikel = 
        $q->select("a.*")
          ->from("kms_artikel a")
          ->join("JOIN", "kms_artikel_tag atag", "atag.id_artikel = a.id")
          ->where(
            ["in", "atag.id_tag", $daftar_tag]
          )
          ->andWhere(
            ["not", ["in", "a.id_kategori", $payload["id_kategori"]]]
          )
          ->distinct()
          ->orderBy("time_create desc")
          ->limit(10)
          ->all();

      // ambil informasi dari confluence
      $hasil = [];
      foreach($daftar_artikel as $record)
      {
        $user = User::findOne($record["id_user_create"]);

        //  lakukan query dari Confluence
        $jira_conf = Yii::$app->restconf->confs['confluence'];
        $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";
        $client = new \GuzzleHttp\Client([
          'base_uri' => $base_url
        ]);

        $res = $client->request(
          'GET',
          "/rest/api/content/{$record["linked_id_content"]}",
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

        $response_payload = $res->getBody();
        $response_payload = Json::decode($response_payload);

        $temp = [];
        $temp["kms_artikel"] = $record;
        $temp["user_create"] = $user;
        $temp["category_path"] = KmsKategori::CategoryPath($record["id_kategori"]);
        $temp["confluence"]["judul"] = $response_payload["title"];
        $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];

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
   *  Mengambil daftar artikel yang dibikin oleh si user
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "id_user": 123,
   *    "status": 123
   *  }
   *  Response type: JSON
   *  Response format:
   *  {
   *    "status": "",
   *    "pesan": "",
   *    "records" :
   *    {
   *      "kms_artikel":
   *      {
   *        <object dari record kms_artikel>
   *      },
   *      "category_path": [],
   *      "tags": [],
   *      "confluence":
   *      {
   *        <object dari record Confluence>
   *      }
   *    },
   *  }
    * */
  public function actionMyitems()
  {
    $payload = $this->GetPayload();

    $is_id_user_valid = isset($payload["id_user"]);
    $is_status_valid = isset($payload["status"]);

    if( $is_id_user_valid == true && $is_status_valid == true)
    {
      $list_artikel = KmsArtikel::find()
        ->where(
          [
            "and",
            "id_user_create = :id_user",
            "status = :status",
            "is_delete = :is_delete"
          ],
          [
            ":id_user" => $payload["id_user"],
            ":status" => $payload["status"],
            ":is_delete" => 0,
          ]
        )
        ->orderBy("time_create desc")
        ->all();

      $records = [];
      foreach($list_artikel as $artikel)
      {
        // ambil daftar tags yang berasal dari id_kategori
        $q  = new Query();

        //  lakukan query dari Confluence
        $jira_conf = Yii::$app->restconf->confs['confluence'];
        $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";
        $client = new \GuzzleHttp\Client([
          'base_uri' => $base_url
        ]);

        $res = $client->request(
          'GET',
          "/rest/api/content/{$artikel["linked_id_content"]}",
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

        $response_payload = $res->getBody();
        $response_payload = Json::decode($response_payload);

        $temp = [];
        $temp["kms_artikel"] = $artikel;
        $tags = KmsArtikelTag::GetArtikelTags($artikel["id"]);
        $temp["tags"] = $tags;
        $temp["category_path"] = KmsKategori::CategoryPath($artikel["id_kategori"]);
        $temp["confluence"]["id"] = $response_payload["id"];
        $temp["confluence"]["judul"] = $response_payload["title"];
        $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];

        $records[] = $temp;
      } // loop artikel yang dibikin si user


      return [
        "status" => "ok",
        "pesan" => "Berhasil mengambil records",
        "records" => $records,
      ];

    }
    else
    {
      return [
        "status" => "not ok",
        "pesan" => "Parameter yang diperlukan tidak lengkap: id_user (integer), status (integer)",
        "payload" => $payload
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
          ->join("JOIN", "kms_artikel_tag atag", "atag.id_tag = t.id")
          ->join("JOIN", "kms_artikel a", "a.id = atag.id_artikel")
          ->where(["in", "a.id_kategori", $payload["id_kategori"]])
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
          ->join("JOIN", "kms_artikel a", "a.id_kategori = k.id")
          ->join("JOIN", "kms_artikel_tag atag", "atag.id_artikel = a.id")
          ->where(
            ["in", "atag.id_tag", $daftar_tag]
          )
          ->andWhere(
            ["not", ["in", "a.id_kategori", $payload["id_kategori"]]]
          )
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
          ->from("kms_artikel_activity_log log")
          ->andWhere("time_status >= :awal", [":awal" => date("Y-m-d 00:00:00", $temp_date->timestamp)])
          ->andWhere("time_status <= :akhir", [":akhir" => date("Y-m-d 23:59:59", $temp_date->timestamp)])
          ->distinct()
          ->groupBy("log.status")
          ->orderBy("log.status asc")
          ->all();

        $q = new Query();
        $hasil_action = $q->select("log.action, count(log.id) as jumlah")
          ->from("kms_artikel_activity_log log")
          ->andWhere("time_action >= :awal", [":awal" => date("Y-m-d 00:00:00", $temp_date->timestamp)])
          ->andWhere("time_action <= :akhir", [":akhir" => date("Y-m-d 23:59:59", $temp_date->timestamp)])
          ->distinct()
          ->groupBy("log.action")
          ->orderBy("log.action asc")
          ->all();

        $temp = [];
        $temp["tanggal"] = date("Y-m-d", $temp_date->timestamp);

        foreach($hasil_status as $record)
        {
          switch( $record["status"] )
          {
            case -2: //draft-rangkuman
              $temp["data"]["draftrangkuman"] = $record["jumlah"];
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
          }
        }

        foreach($hasil_action as $record)
        {
          switch( $record["action"] )
          {
            case -1: //view
              $temp["data"]["view"] = $record["jumlah"];
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
   *  Mengembalikan total jumlah kejadian yang terjadi dalam rentang waktu tertentu.
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
      // ambil jumlah artikel per kejadian (new, publish, .., freeze)
      $q = new Query();
      $total_artikel = 
        $q->select("a.id")
          ->from("kms_artikel a")
          ->join("JOIN", "kms_artikel_activity_log log", "log.id_artikel = a.id")
          ->where(
            [
              "and",
              "a.id_kategori = :id_kategori",
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
          ->distinct()
          ->groupBy("a.id")
          ->all();
      $total_artikel = count($total_artikel);

      $q = new Query();
      $artikel_status = 
        $q->select("log.status, a.id")
          ->from("kms_artikel a")
          ->join("JOIN", "kms_artikel_activity_log log", "log.id_artikel = a.id")
          ->andWhere("a.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status, a.id")
          ->all();

      // ambil jumlah artikel per action (like/dislike)
      $q = new Query();
      $artikel_action = 
        $q->select("log.action, a.id")
          ->from("kms_artikel a")
          ->join("JOIN", "kms_artikel_activity_log log", "log.id_artikel = a.id")
          ->andWhere("a.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 2")
          ->andWhere("log.time_action >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_action <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.action asc")
          ->groupBy("log.action, a.id")
          ->all();

      // ambil jumlah user per kejadian (new, publish, .., freeze)
      $q = new Query();
      $total_user = 
        $q->select("u.id")
          ->from("user u")
          ->join("JOIN", "kms_artikel_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "kms_artikel a", "log.id_artikel = a.id")
          ->where(
            [
              "and",
              "a.id_kategori = :id_kategori",
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
          ->join("JOIN", "kms_artikel_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "kms_artikel a", "log.id_artikel = a.id")
          ->andWhere("a.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
          ->andWhere("log.type_log = 1")
          ->andWhere("log.time_status >= :awal", [":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp)])
          ->andWhere("log.time_status <= :akhir", [":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp)])
          ->distinct()
          ->orderBy("log.status asc")
          ->groupBy("log.status, u.id")
          ->all();

      // ambil jumlah artikel per action (like/dislike)
      $q = new Query();
      $user_action = 
        $q->select("log.action, u.id")
          ->from("user u")
          ->join("JOIN", "kms_artikel_activity_log log", "log.id_user = u.id")
          ->join("JOIN", "kms_artikel a", "log.id_artikel = a.id")
          ->andWhere("a.id_kategori = :id_kategori", [":id_kategori" => $kategori["id"]])
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

      $temp["total"]["artikel"] = $total_artikel;
      $temp["total"]["user"] = $total_user;

      $temp["draftrangkuman"]["artikel"] = 0;
      $temp["draft"]["artikel"] = 0;
      $temp["new"]["artikel"] = 0;
      $temp["publish"]["artikel"] = 0;
      $temp["unpublish"]["artikel"] = 0;
      $temp["reject"]["artikel"] = 0;
      $temp["freeze"]["artikel"] = 0;
      foreach($artikel_status as $record)
      {
        switch( $record["status"] )
        {
          case -2: //draft-rangkuman
            $temp["draftrangkuman"]["artikel"]++;
            break;

          case -1: //draft
            $temp["draft"]["artikel"]++;
            break;

          case 0: //new
            $temp["new"]["artikel"]++;
            break;

          case 1: //publish
            $temp["publish"]["artikel"]++;
            break;

          case 2: //unpublish
            $temp["unpublish"]["artikel"]++;
            break;

          case 3: //reject
            $temp["reject"]["artikel"]++;
            break;

          case 4: //freeze
            $temp["freeze"]["artikel"]++;
            break;
        }
      }

      $temp["neutral"]["artikel"] = 0;
      $temp["like"]["artikel"] = 0;
      $temp["dislike"]["artikel"] = 0;
      $temp["view"]["artikel"] = 0;
      foreach($artikel_action as $record)
      {
        switch( $record["action"] )
        {
          case 0: //neutral
            $temp["neutral"]["artikel"]++;
            break;

          case 1: //like
            $temp["like"]["artikel"]++;
            break;

          case 2: //dislike
            $temp["dislike"]["artikel"]++;
            break;

          case -1: //view
            $temp["view"]["artikel"]++;
            break;
        }
      }

      $temp["new"]["user"] = 0;
      $temp["publish"]["user"] = 0;
      $temp["unpublish"]["user"] = 0;
      $temp["reject"]["user"] = 0;
      $temp["freeze"]["user"] = 0;
      foreach($user_status as $record)
      {
        switch( $record["status"] )
        {
          case -2: //draft-rangkuman
            $temp["draftrangkuman"]["user"]++;
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
}
