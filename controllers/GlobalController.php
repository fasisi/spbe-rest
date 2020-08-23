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
}