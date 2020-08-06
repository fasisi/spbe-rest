<?php

namespace app\modules\kms\controllers;

class ArticleController extends \yii\rest\Controller
{
  public function behaviors()
  {
    return [
      'verbs' => [
        'class' => \yii\filters\VerbFilter::className(),
        'actions' => [
          'create'    => ['POST'],
          'retrieve'  => ['GET'],
          'update'    => ['PUT'],
          'delete'    => ['DELETE'],
        ]
      ]
    ];
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
  //    "": "",
  //    "": "",
  //  }
  //  Response type: JSON
  //  Response format:
  //  {
  //    "status": "",
  //    "pesan": "",
  //  }
  public function actionCreate()
  {
    // pastikan method = POST
    // pastikan request parameter lengkap
    // panggil POST /rest/api/content
    // ambil id dari result
    //
    // bikin record kms_artikel
    // kembalikan response
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
