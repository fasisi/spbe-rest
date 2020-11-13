<?php

namespace app\controllers;

use Yii;
use yii\helpers\Json;
use yii\db\Query;

use app\models\KmsKategori;

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

        if (isset($payload["table_name"]) == true) 
        {
            $query = new Query;
            $query->select('*')->from($payload["table_name"])->where("is_delete = 0");
            $command = $query->createCommand();
            $temp_data = $command->queryAll();

            $data = [];
            foreach($temp_data as $a_data)
            {
              $path_items = KmsKategori::CategoryPath($a_data["id"]);
              $path_name = "";
              foreach($path_items as $item)
              {
                $path_name = $path_name .  ($path_name == "" ? $item["nama"] : " > ") . $item["nama"];
              }

              $temp = KmsKategori::findOne($a_data["id"]);

              if( isset($payload["short_names"]) == false )
              {
                $temp["nama"] = $path_name;
              }

              $data[] = $temp;

            }

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
        } 
        else 
        {
            return [
                "status" => "not ok",
                "pesan" => "Required parameter: table_name",
            ];
        }
    }

    public function actionDropdownsolver()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["id_role"]) == true) {
            $query = new Query;
            $query->select('u.id AS id_user,u.nama AS nama_user')
            ->from("user u")
            ->join('LEFT JOIN', 'user_roles ur','u.id = ur.id_user')
            ->join('LEFT JOIN', 'roles r','r.id = ur.id_roles')	
            ->where("r.id =" .$payload['id_role']);
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
