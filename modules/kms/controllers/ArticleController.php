<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\helpers\Json;
use yii\db\Query;

use app\models\KmsArtikel;
use app\models\KmsArtikelActivityLog;

class ArticleController extends \yii\rest\Controller
{
  public function behaviors()
  {
    $behaviors = parent::behaviors();
    $behaviors['verbs'] = [
      'class' => \yii\filters\VerbFilter::className(),
      'actions' => [
        'create'            => ['POST'],
        'retrieve'          => ['GET'],
        'update'            => ['PUT'],
        'delete'            => ['DELETE'],
        'list'              => ['GET'],

        'items'             => ['GET'],
        'attachments'       => ['POST'],
        'itemsbytags'       => ['GET'],
        'categoriesbytags'  => ['GET'],
        'itemtag'           => ['POST'],
        'itemsbyfilter'     => ['GET'],
      ]
    ];
    return $behaviors;
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
    $payload = Yii::$app->request->rawBody;
    $payload = Json::decode($payload);

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
   *  Mengambil object berdasarkan filter yang dapat disetup secara dinamis.
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

  public function actionItemsbyfilter()
  {
    // pastikan request parameter lengkap
    $payload = Yii::$app->request->rawBody;

    try
    {
      $payload = Json::decode($payload);
    }
    catch(yii\base\InvalidArgumentException $e)
    {
      return [
        "status"=> "not ok",
        "pesan"=> "Failed on JSON parsing",
        "payload" => $payload
      ];
    }

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


}
