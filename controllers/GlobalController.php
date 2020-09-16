<?php

namespace app\controllers;

use Yii;
use yii\helpers\Json;


use yii\db\Query;

/**
 * Default controller for the `general` module
 */
class GlobalController extends \yii\rest\Controller
{
    public function behaviors()
    {
        date_default_timezone_set("Asia/Jakarta");
        $behaviors = parent::behaviors();
        return $behaviors;
    }

    public function actionDropdown()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["table_name"]) == true) {
            $query = new Query;
            $query->select('*')->from($payload["table_name"]);
            $command = $query->createCommand();
            $data = $command->queryAll();

            if (!empty($data)) {
                return [
                    "status" => "ok",
                    "pesan" => "Record found",
                    "result" => $data
                ];
            } else {
                return [
                    "status" => "not ok",
                    "pesan" => "Record not found",
                ];
            }
        } else {
            return [
                "status" => "not ok",
                "pesan" => "Required parameter: id",
            ];
        }
    }

    public function actionHakakseskategori()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["id_user"]) == true) {
            $query = new Query;
            $query->select('id')
                ->from("kms_kategori")
                ->where('id not in (select id_kategori from kategori_user where id_user ='.$payload['id_user'].')');


            $command = $query->createCommand();
            $data = $command->queryAll();

            $query1 = new Query;
            $query1->select('id')
                ->from("kms_kategori")
                ->where('id in (select id_kategori from kategori_user where id_user ='.$payload['id_user'].')')
                ->limit(1);


            $command1 = $query1->createCommand();
            $data1 = $command1->queryAll();

            if (!empty($data)) {
                return [
                    "status" => "ok",
                    "pesan" => "Record found",
                    "result" => [
                        "all_kategori" => $data,
                        "hak_kategori" => $data1
                    ]
                ];
            } else {
                return [
                    "status" => "not ok",
                    "pesan" => "Record not found",
                ];
            }
        } else {
            return [
                "status" => "not ok",
                "pesan" => "Required parameter: id",
            ];
        }
    }

    public function actionDropdownkategoriwithhakakses()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["id_user"]) == true) {

            $query = new Query;
            $query->select('kms_kategori.*')
            ->from("kms_kategori")
            ->where('id in (select id_kategori from kategori_user where id_user ='.$payload['id_user'].')')
            ->orderBy('nama asc');

            $command = $query->createCommand();
            $data = $command->queryAll();

            if (!empty($data)) {
                return [
                    "status" => "ok",
                    "pesan" => "Record found",
                    "result" => $data
                ];
            } else {
                return [
                    "status" => "not ok",
                    "pesan" => "Record not found",
                ];
            }
        } else {
            return [
                "status" => "not ok",
                "pesan" => "Required parameter: id",
            ];
        }
    }

    public function actionDropdownkategori()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["table_name"]) == true) {
            $query = new Query;
            $query->select('*')->from($payload["table_name"])->where("is_delete = 0");
            $command = $query->createCommand();
            $data = $command->queryAll();

            if (!empty($data)) {
                return [
                    "status" => "ok",
                    "pesan" => "Record found",
                    "result" => $data
                ];
            } else {
                return [
                    "status" => "not ok",
                    "pesan" => "Record not found",
                ];
            }
        } else {
            return [
                "status" => "not ok",
                "pesan" => "Required parameter: id",
            ];
        }
    }
}
