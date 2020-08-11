<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\helpers\Json;
use yii\db\Query;

use app\models\KmsArtikel;
use app\models\KmsArtikelActivityLog;
use app\models\KmsArtikelUserStatus;
use app\models\User;
use app\models\KmsKategori;

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
        'itemsbyfilter'         => ['GET'],
        'artikeluserstatus'     => ['POST'],
        'itemkategori'          => ['PUT'],
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

  private function ActivityLog($id_artikel, $id_user, $type_action)
  {
    $log = new KmsArtikelActivityLog();
    $log["id_artikel"] = $id_artikel;
    $log["id_user"] = $id_user;
    $log["time_action"] = date("Y-m-j H:i:s");
    $log["type_action"] = $type_action;
    $log->save();
  }

  //  Membuat record artikel
  //
  //  Method : POST
  //  Request type: JSON
  //  Request format:
  //  {
  //    "judul": "",
  //    "body": "",
  //    "id_kategori": "",
  //    "tags": [
  //      id1, id2, ...
  //    ],
  //    "": "",
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //    "result": {
  //      record_object
  //    }
  //  }
  public function actionCreate()
  {
    $judul_valid = true;
    $body_valid = true;
    $kategori_valid = true;
    $tags_valid = true;

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

    if( $judul_valid == true && $body_valid == true &&
        $kategori_valid == true && $tags_valid == true )
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
      Yii::info("base_url = $base_url");
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


            // bikin record kms_artikel
            $artikel = new KmsArtikel();
            $artikel['linked_id_content'] = $linked_id_artikel;
            $artikel['time_create'] = date("Y-m-j H:i:s");
            $artikel['id_user_create'] = 123;
            $artikel['status'] = 1;
            $artikel->save();
            $id_artikel = $artikel->primaryKey;

            $this->ActivityLog($id_artikel, 123, 1);

            // kembalikan response
            return [
              'status' => 'ok',
              'pesan' => 'Record artikel telah dibikin',
              'result' => $artikel
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
  //    "id": 123
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
    //
  }

  public function actionRetrieve()
  {
      return $this->render('retrieve');
  }

  public function actionUpdate()
  {
      return $this->render('update');
  }

  /*
   *  Mengambil kms_artikel_activity_log berdasarkan filter yang dapat disetup secara dinamis.
   *
   *  Method: GET
   *  Request type: JSON
   *  Request format:
   *  {
   *    "object_type": "a/u",
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

    $q = new Query();

    switch( true )
    {
      case $payload["object_type"] == "a" :
        $q->select("a.*");
        $q->from("kms_artikel a");
        $q->join("join", "kms_artikel_activity_log l", "l.id_artikel = a.id");
        break;

      case $payload["object_type"] == "u" :
        $q->select("u.*");
        $q->from("user u");
        $q->join("join", "kms_artikel_activity_log l", "l.id_user = u.id");
        break;

      default :
        $q->select("a.*");
        $q->from("kms_artikel a");
        $q->join("join", "kms_artikel_activity_log l", "l.id_artikel = a.id");
        break;
    }

    Yii::info("filter = " . print_r($payload["filter"], true));

    $where[] = "and";
    foreach( $payload["filter"] as $key => $value )
    {
      switch(true)
      {
        case $key == "waktu_awal":
          $where[] = "l.time_action >= '$value'";
        break;

        case $key == "waktu_akhir":
          $where[] = "l.time_action <= '$value'";
        break;

        case $key == "actions":
          $temp = [];
          foreach( $value as $type_action )
          {
            $temp[] = $type_action;
          }
          $where[] = ["in", "l.type_action", $temp];
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

    //execute the query
    $hasil = $q->all();

    return [
      "status" => "ok",
      "pesan" => "...",
      "result" => $hasil
    ];
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
            $temp["confluence"]["status"] = "ok";
            $temp["confluence"]["linked_id_content"] = $response_payload["id"];
            $temp["confluence"]["judul"] = $response_payload["title"];
            $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];

            $hasil[] = $temp;
            break;

          default:
            // kembalikan response
            $temp = [];
            $temp["kms_artikel"] = $artikel;
            $temp["confluence"]["status"] = "not ok";
            $temp["confluence"]["judul"] = $response_payload["title"];
            $temp["confluence"]["konten"] = $response_payload["body"]["view"]["value"];

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

      //  lakukan query dari Confluence
      $jira_conf = Yii::$app->restconf->confs['confluence'];
      $base_url = "HTTP://{$jira_conf["ip"]}:{$jira_conf["port"]}/";
      Yii::info("base_url = $base_url");
      $client = new \GuzzleHttp\Client([
        'base_uri' => $base_url
      ]);

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
        $hasil["confluence"]["status"] = "ok";
        $hasil["confluence"]["linked_id_content"] = $response_payload["id"];
        $hasil["confluence"]["judul"] = $response_payload["title"];
        $hasil["confluence"]["konten"] = $response_payload["body"]["view"]["value"];
        break;

      default:
        // kembalikan response
        $hasil = [];
        $hasil["kms_artikel"] = $artikel;
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

      if( $payload["status"] != 1 && $payload["status"] != 2 && $payload["status"] != 3 )
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

        //  simpan history pada tabel kms_artikel_activity_log
        $log = new KmsArtikelActivityLog();
        $log["id_artikel"] = $payload["id_artikel"];
        $log["id_user"] = $payload["id_user"];
        $log["type_action"] = $payload["status"];
        $log["time_action"] = date("Y-m-j H:i:s");
        $log->save();

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
        "result" => $artikel
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

}
