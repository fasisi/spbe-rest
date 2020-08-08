<?php

namespace app\modules\kms\controllers;

use Yii;
use yii\helpers\Json;

use app\models\KmsArtikel;

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
      ]
    ];
    return $behaviors;
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

}
