<?php

namespace app\controllers;

use Yii;
use yii\helpers\Json;
use yii\helpers\BaseUrl;
use yii\db\Query;

use app\models\KmsKategori;
use app\models\KmsArtikel;
use app\models\ForumThread;
use app\models\Roles;
use app\models\User;

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

    public function actionDropdownkategoriwithcount()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["table_name"]) == true) {
            $query = new Query;
            $query
              ->select('*')
              ->from($payload["table_name"])
              ->where('is_delete = 0');
            $command = $query->createCommand();
            $data = $command->queryAll();

            if (!empty($data)) 
            {
                foreach($data as $key => $a_data)
                {
                  $jumlah = 0;
                  switch( intval($payload["type"]) )
                  {
                    case 1: // artikel
                      $jumlah = KmsArtikel::AllCountByCategory($a_data["id"]);
                      break;

                    case 2: // topik
                      $jumlah = ForumThread::AllCountByCategory($a_data["id"]);
                      break;

                  }

                  $data[$key]["nama"] = $a_data["nama"] . " ($jumlah)";
                }
                
                Yii::info("data = " . Json::encode($data));

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
    
    public function actionDropdownkategoriwithcountv2()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["table_name"]) == true) {
            $query = new Query;
            $query->select('kk.id, kk.id_parent as parent, kk.nama as text')->from($payload["table_name"]);
            $command = $query->createCommand();
            $data = $command->queryAll();

            if (!empty($data)) 
            {
                foreach($data as $key => $a_data)
                {
                  $jumlah = 0;
                  switch( intval($payload["type"]) )
                  {
                    case 1: // artikel
                      $jumlah = KmsArtikel::RecentCountByCategory($a_data["id"]);
                      break;

                    case 2: // topik
                      $jumlah = ForumThread::RecentCountByCategory($a_data["id"]);
                      break;

                  }

                  $data[$key]["text"] = $a_data["text"] . " ($jumlah)";
                }
                
                Yii::info("data = " . Json::encode($data));

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
    
    

    public function actionKategoriwithname()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["table_name"]) == true) {
            $query = new Query;
            $query->select('
	    	a.*,
		(
			select group_concat(nama) from user join hd_kategori_pic on hd_kategori_pic.id_user = user.id where hd_kategori_pic.id_kategori = a.id
		) as nama_pic,
		(
			select group_concat(user.id) from user join hd_kategori_pic on hd_kategori_pic.id_user = user.id where hd_kategori_pic.id_kategori = a.id
		) as id_pic
	    ')->from($payload["table_name"]." a");
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

            // hanya role "user terdaftar" yang tidak dibatasi hak akses kategorinya
            $id_role = $payload["id_role"];

            // daftar kategori yang bukan hak si user
            $query = new Query;
            $query->select('id')
                ->from("kms_kategori")
                ->where('id not in (select id_kategori from kategori_user where id_user ='.$payload['id_user'].')');
            $command = $query->createCommand();
            $data = $command->queryAll();



            // kategori pertama yang menjadi hak si user
            $query1 = new Query;
            $query1->select('id')
                ->from("kms_kategori")
                ->where('id in (select id_kategori from kategori_user where id_user ='.$payload['id_user'].')')
                ->limit(1);
            $command1 = $query1->createCommand();
            $data1 = $command1->queryAll();

            if(Roles::CheckRoleByCodeName($id_role, "user_terdaftar"))
            {
              return [
                  "status" => "ok",
                  "pesan" => "Record found",
                  "result" => [
                      "all_kategori" => [],      // tanpa limitasi
                      "hak_kategori" => $data1   // kategori pertama yang menjadi hak si user
                  ]
              ];
            }
            else
            {
              if (!empty($data)) 
              {
                  return [
                      "status" => "ok",
                      "pesan" => "Record found",
                      "result" => [
                          "all_kategori" => $data,   // kategori yang bukan jadi hak si user
                          "hak_kategori" => $data1   // kategori pertama yang menjadi hak si user
                      ]
                  ];
              } 
              else 
              {
                  return [
                      "status" => "not ok",
                      "pesan" => "Record not found",
                  ];
              }
            }


        } 
        else 
        {
            return [
                "status" => "not ok",
                "pesan" => "Required parameter: id",
            ];
        }
    }

    public function actionHakakseskategoriv2()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        if (isset($payload["id_user"]) == true) {

            // hanya role "user terdaftar" yang tidak dibatasi hak akses kategorinya
            $id_role = $payload["id_role"];
            if($payload["id_user"] != -1) {

            $query = new Query;
            $query->select('kk.id, kk.id_parent as parent, kk.nama as text')
                ->from("kategori_user ku")
                ->join("LEFT JOIN",'kms_kategori kk','kk.id= ku.id_kategori')
                ->where('ku.id_user ='.$payload['id_user']);
            } else {
                $query = new Query;
                $query->select('kk.id, kk.id_parent as parent, kk.nama as text')
                    ->from("kms_kategori kk");
            }
            $command = $query->createCommand();
            $data = $command->queryAll();

            if(Roles::CheckRoleByCodeName($id_role, "user_terdaftar"))
            {
              return [
                  "status" => "ok",
                  "pesan" => "Record found",
                  "result" => [
                      "all_kategori" => [],
                      
                  ]
              ];
            }
            else
            {
              if (!empty($data)) 
              {
                  return [
                      "status" => "ok",
                      "pesan" => "Record found",
                      "result" => [
                          "all_kategori" => $data,
                          
                      ]
                  ];
              } 
              else 
              {
                  return [
                      "status" => "not ok",
                      "pesan" => "Record not found",
                  ];
              }
            }


        } 
        else 
        {
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
        ini_set("display_errors", 1);
        error_reporting(E_ALL);

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
                $path_name = $path_name .  ($path_name == "" ? "" : " > ") . $item["nama"];
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

    public function actionDropdownsolverwithkategori()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);
        $code_name = "'".$payload["code_name"]."'";
        $id_kategori = $payload["id_kategori"];
        $id_instansi = $payload["id_instansi"];
        if(($id_kategori == "") && ($id_instansi == "")) {

            if (isset($payload["code_name"]) == true) {
                $query = new Query;
                $query->select('u.id AS id_user,u.nama AS nama_user')
                ->from("user u")
                ->join('LEFT JOIN', 'user_roles ur','u.id = ur.id_user')
                ->join('LEFT JOIN', 'roles r','r.id = ur.id_roles')	
                ->where("r.code_name =".$code_name);
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
        } else if(($id_kategori != "") && ($id_instansi == "")) {
            $query = new Query;
            $query->select('u.id AS id_user,u.nama AS nama_user')
            ->from("user u")
            ->join('LEFT JOIN', 'user_roles ur','u.id = ur.id_user')
            ->join('LEFT JOIN', 'roles r','r.id = ur.id_roles')	
            ->join('LEFT JOIN', 'kategori_user ku','ku.id_user = u.id')	
            
            ->where("r.code_name =".$code_name)
            ->andWhere("ku.id_kategori =" .$payload['id_kategori'])
	        ->groupBy('id_user');
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
            $query = new Query;
            $query->select('u.id AS id_user,u.nama AS nama_user,d.name AS nama_department,d.id AS id_departments,kk.id AS id_kategori')
            ->from("user u")
            ->join('LEFT JOIN', 'user_roles ur','u.id = ur.id_user')
            ->join('LEFT JOIN', 'roles r','r.id = ur.id_roles')	
            ->join('LEFT JOIN', 'departments d','u.id_departments = d.id')	
            ->join('LEFT JOIN', 'kategori_user ku','ku.id_user = u.id')	
            ->join('LEFT JOIN', 'kms_kategori kk','kk.id = ku.id_kategori')	
            ->where("r.code_name =".$code_name)
            ->andWhere("kk.id =" .$payload['id_kategori'])
            ->andWhere("d.id =" .$payload['id_instansi'])
	        ->groupBy('id_user');
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
        }
    }

    public function actionDropdownsolverwithkategoriandsolver()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);
        $code_name = "'".$payload["code_name"]."'";
        $id_kategori = $payload["id_kategori"];
        $id_solver = $payload["id_solver"];

        $a_solver = User::find()
          ->where([
            "id" => $payload["id_solver"]
          ])
          ->one();

        $query = new Query;
        $query->select('
            u.id AS id_user,
            u.nama AS nama_user,
            d.name AS nama_department,
            d.id AS id_departments,
            kk.id AS id_kategori
          ')
        ->from("user u")
        ->join('LEFT JOIN', 'user_roles ur','u.id = ur.id_user')
        ->join('LEFT JOIN', 'roles r','r.id = ur.id_roles')	
        ->join('LEFT JOIN', 'departments d','u.id_departments = d.id')	
        ->join('LEFT JOIN', 'kategori_user ku','ku.id_user = u.id')	
        ->join('LEFT JOIN', 'kms_kategori kk','kk.id = ku.id_kategori')	
        ->where("r.code_name =".$code_name)
        ->andWhere("kk.id =" .$payload['id_kategori'])
        ->andWhere("d.id =" .$a_solver['id_departments'])
      ->groupBy('id_user');
        $command = $query->createCommand();
        $data = $command->queryAll();

        if (!empty($data)) {
            return [
                "status" => "ok",
                "pesan" => "Record found",
                "result" => $data
            ];
        } 
        else 
        {
            return [
                "status" => "not ok",
                "pesan" => "Record not found",
            ];
        }
    }

    public function actionCekimageuser()
    {
        $payload = Yii::$app->request->rawBody;
        Yii::info("payload = $payload");
        $payload = Json::decode($payload);

        $id_user = $payload["id_user"];

        $query = new Query;
        $query->select('u.id, u.id_file_profile, k.thumbnail, k.nama')
        ->from("user u")
        ->join('LEFT JOIN', 'kms_files k','u.id_file_profile = k.id')
        ->where("u.id =".$id_user);
        $command = $query->createCommand();
        $data = $command->queryAll();

        foreach($data as $val) {
            $thumb = $val['thumbnail'];
	    $nama = $val['nama'];
        }

        return [
            "image_name" => $thumb,
            "image_thumb" => BaseUrl::base(true) . "/files/".$thumb,
	    "image_ori" => BaseUrl::base(true) . "/files/".$nama
        ];
    }
}
