<?php

namespace app\models;

use Carbon\Carbon;

use Yii;

use yii\db\Query;

/**
 * This is the model class for table "kms_artikel".
 *
 * @property int $id
 * @property int $id_kategori Relasi ke tabel kms_kategori
 * @property int $linked_id_content idcontent pada Confluence
 * @property string $judul
 * @property string $konten
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int|null $id_user_publish
 * @property string|null $time_publish
 * @property int $is_delete
 * @property int $is_publish
 */
class KmsArtikel extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'kms_artikel';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori', 'linked_id_content', 'linked_id_thread', 'id_user_create', 
              'id_user_delete', 'id_user_publish', 'is_delete', 'is_publish'], 'integer'],
            [['linked_id_content', 'id_user_create', 'time_create'], 'required'],
            [['konten'], 'string'],
            [['time_create', 'time_delete', 'time_publish'], 'safe'],
            [['judul'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_kategori' => 'Id Kategori',
            'linked_id_content' => 'Linked Id Content',
            'judul' => 'Judul',
            'konten' => 'Konten',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'id_user_publish' => 'Id User Publish',
            'time_publish' => 'Time Publish',
            'is_delete' => 'Is Delete',
            'is_publish' => 'Is Publish',
        ];
    }


    /*
     *  Mengambil jumlah action yang diterima suatu artikel, dalam rentang waktu
     *  tertentu.
     *
     *  Params:
     *  id_artikel - integer
     *  type_action - integer (1 = like; 2 = dislike; -1 = view)
     *  tanggal_awal - Carbon
     *  tanggal_akhir - Carbom
     * */

    public static function ActionReceivedInRange($id_artikel, $type_action, $tanggal_awal, $tanggal_akhir)
    {
      $q = new Query();
      $hasil = $q->select("count(l.id) as jumlah")
        ->from("kms_artikel_activity_log l")
        ->join("JOIN", "kms_artikel a", "a.id = l.id_artikel")
        ->where([
          "and",
          "l.id_artikel = :id_artikel",
          "l.type_log = 2",
          "l.action = :type_action",
          "l.time_action >= :awal",
          "l.time_action <= :akhir"
        ])
        ->params([
          ":id_artikel" => $id_artikel,
          ":type_action" => $type_action,
          ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp),
          ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp),
        ])
        ->one();

      return $hasil["jumlah"];
    }

    /*
     *  Mengambil jumlah action yang dilakukan oleh suatu user, dalam rentang
     *  waktu tertentu.
     *
     *  Params:
     *  id_user - integer
     *  type_action - integer (1 = like; 2 = dislike; -1 = view)
     *  tanggal_awal - Carbon
     *  tanggal_akhir - Carbon
      * */
    public static function ActionByUserInRange($id_user, $type_action, $tanggal_awal, $tanggal_akhir)
    {
      $q = new Query();
      $q->select("count(l.id) as jumlah");
      $q->from("kms_artikel_activity_log l");
      $q->join("JOIN", "user u", "u.id = l.id_user");
      $q->where([
          "and",
          "u.id = :id_user",
          "l.type_log = 2",
          "l.action = :type_action",
          "l.time_action >= :awal",
          "l.time_action <= :akhir"
        ])
        ->params([
          ":id_user" => $id_user,
          ":type_action" => $type_action,
          ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp),
          ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp),
        ]);
      $hasil = $q->one();

      return $hasil["jumlah"];
    }

    /*
     *  Menghitung jumlah kejadian suatu status atas suatu artikel, dalam rentang waktu tertentu
      * */
    public static function StatusInRange($id_artikel, $type_status, $tanggal_awal, $tanggal_akhir)
    {
      $q = new Query();
      $q->select("count(l.id) as jumlah");
      $q->from("kms_artikel_activity_log l");
      $q->join("JOIN", "kms_artikel a", "a.id = l.id_artikel");
      $q->where([
          "and",
          "l.id_artikel = :id_artikel",
          "l.type_log = 1",
          "l.status = :type_status",
          "l.time_action >= :awal",
          "l.time_action <= :akhir"
        ])
        ->params([
          ":id_artikel" => $id_artikel,
          ":type_status" => $type_status,
          ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp),
          ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp),
        ]);
      $hasil = $q->one();

      return $hasil["jumlah"];

    }

    /*
     * Menghitung jumlah artikel dalam 30 hari terakhir berdasarkan id_kategori
      * */
    public static function RecentCountByCategory($id_kategori)
    {
      //
      $tanggal_b = Carbon::createFromFormat("Y-m-d", date("Y-m-j"));
      $tanggal_a = Carbon::createFromTimeStamp($tanggal_b->timestamp);
      $tanggal_a->addDay(-30);

      $q = new Query();
      $hasil = 
        $q->select("count(a.id) as jumlah")
          ->from("kms_artikel a")
          ->join("join", "kms_artikel_activity_log l", "l.id_artikel = a.id")
          ->where(
            [
              "and",
              "a.is_delete = 0",
              ["in", "a.status", [1]],    // publish
              "a.id_kategori = :id_kategori",
              "l.time_status >= :awal",
              "l.time_status <= :akhir",
              ["in", "l.status", [1]]
            ],
            [
              ":id_kategori" => $id_kategori,
              ":awal" => date("Y-m-j 00:00:00", $tanggal_a->timestamp),
              ":akhir" => date("Y-m-j 23:59:59", $tanggal_b->timestamp)
            ]
          )
          ->one();
      $jumlah = $hasil["jumlah"];

      return $jumlah;
    }


    /*
     * Menghitung jumlah artikel
      * */
    public static function AllCountByCategory($id_kategori)
    {
      //
      $tanggal_b = Carbon::createFromFormat("Y-m-d", date("Y-m-j"));
      $tanggal_a = Carbon::createFromTimeStamp($tanggal_b->timestamp);
      $tanggal_a->addDay(-30);

      $q = new Query();
      $hasil = 
        $q->select("count(a.id) as jumlah")
          ->from("kms_artikel a")
          ->join("join", "kms_artikel_activity_log l", "l.id_artikel = a.id")
          ->where(
            [
              "and",
              "a.is_delete = 0",
              ["in", "a.status", [1]],    // publish
              "a.id_kategori = :id_kategori",
              ["in", "l.status", [1]]
            ],
            [
              ":id_kategori" => $id_kategori,
            ]
          )
          ->one();
      $jumlah = $hasil["jumlah"];

      return $jumlah;
    }

}
