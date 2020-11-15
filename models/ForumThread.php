<?php

namespace app\models;

use Yii;

use yii\db\Query;

/**
 * This is the model class for table "forum_thread".
 *
 * @property int $id
 * @property int $id_kategori Relasi ke tabel kms_kategori
 * @property int $linked_id_question idquestion pada Confluence-Question
 * @property string|null $judul
 * @property string|null $konten
 * @property int $view
 * @property int $like
 * @property int $dislike
 * @property int $id_user_create
 * @property string $time_create
 * @property int|null $id_user_update
 * @property string|null $time_update
 * @property int|null $id_user_delete
 * @property string|null $time_delete
 * @property int|null $id_user_publish
 * @property string|null $time_publish
 * @property int $is_delete
 * @property int $is_publish
 * @property int|null $status 0=new;1=publish;2=un-publish;3=reject;4=freeze
 */
class ForumThread extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'forum_thread';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori', 'linked_id_question', 'view', 'like', 'dislike', 'id_user_create', 'id_user_update', 'id_user_delete', 'id_user_publish', 'is_delete', 'is_publish', 'status'], 'integer'],
            [['linked_id_question', 'id_user_create', 'time_create'], 'required'],
            [['konten'], 'string'],
            [['time_create', 'time_update', 'time_delete', 'time_publish'], 'safe'],
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
            'linked_id_question' => 'Linked Id Question',
	    'linked_id_artikel' => 'Linked Id Artikel',
            'judul' => 'Judul',
            'konten' => 'Konten',
            'view' => 'View',
            'like' => 'Like',
            'dislike' => 'Dislike',
            'id_user_create' => 'Id User Create',
            'time_create' => 'Time Create',
            'id_user_update' => 'Id User Update',
            'time_update' => 'Time Update',
            'id_user_delete' => 'Id User Delete',
            'time_delete' => 'Time Delete',
            'id_user_publish' => 'Id User Publish',
            'time_publish' => 'Time Publish',
            'is_delete' => 'Is Delete',
            'is_publish' => 'Is Publish',
            'status' => 'Status',
        ];
    }

    /*
     *  Mengambil jumlah action yang diterima suatu thread, dalam rentang waktu
     *  tertentu.
     *
     *  Params:
     *  id_thread - integer
     *  type_action - integer (1 = like; 2 = dislike; -1 = view)
     *  tanggal_awal - Carbon
     *  tanggal_akhir - Carbom
     * */

    public static function ActionReceivedInRange($id_thread, $type_action, $tanggal_awal, $tanggal_akhir)
    {
      $q = new Query();
      $hasil = $q->select("count(l.id) as jumlah")
        ->from("forum_thread_activity_log l")
        ->join("JOIN", "forum_thread t", "t.id = l.id_thread")
        ->where([
          "and",
          "l.id_thread = :id_thread",
          "l.type_log = 2",
          "l.action = :type_action",
          "l.time_action >= :awal",
          "l.time_action <= :akhir"
        ])
        ->params([
          ":id_thread" => $id_thread,
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
      $q->from("forum_thread_activity_log l");
      $q->join("JOIN", "user u", "u.id = l.id_user");
      $q->where([
          "and",
          "l.id_user = :id_user",
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
     * Mengembalikan informasi penulis pertanyaan dan penulis jawaban yang
     * dipilih. JIKA thread ber-status = -3 (telah dirangkum)
      * */
    public static function GetKontributor($id_thread)
    {
      $test = ForumThread::findOne($id_thread);

      $hasil = [];
      if( $test["status"] == -3 )
      {
        $user = User::findOne( $test["id_user_create"] );
        $hasil["penulis"] = $user;

        // ambil daftar user dari jawaban yang dipilih
        $q = new Query();
        $q->select("u.nama")
          ->from("forum_thread_discussion j")
          ->join("join", "user u", "u.id = j.id_user_create")
          ->where(
            [
              "and",
              "id_thread = :id_thread",
              "is_answer = 1"
            ],
            [
              ":id_thread" => $id_thread
            ]
          )
          ->groupBy("u.nama")
          ->orderBy("u.nama asc");
        $list = $q->all();

        foreach($list as $item)
        {
          $hasil["penjawab"][] = $item["nama"];
        }
      }

      return $hasil;
    }



    /*
     *  Menghitung jumlah kejadian suatu status atas suatu thread, dalam rentang waktu tertentu
      * */
    public static function StatusInRange($id_thread, $type_status, $tanggal_awal, $tanggal_akhir)
    {
      $q = new Query();
      $q->select("count(l.id) as jumlah");
      $q->from("forum_thread_activity_log l");
      $q->join("JOIN", "forum_thread f", "f.id = l.id_thread");
      $q->where([
          "and",
          "l.id_thread = :id_thread",
          "l.type_log = 1",
          "l.status = :type_status",
          "l.time_status >= :awal",
          "l.time_status <= :akhir"
        ])
        ->params([
          ":id_thread" => $id_thread,
          ":type_status" => $type_status,
          ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp),
          ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp),
        ]);
      $hasil = $q->one();

      return $hasil["jumlah"];

    }

    /*
     * Cek hak baca user terhadap suatu thread berdasarkan id_user
      * */
    public static function CekHakBaca($id_thread, $id_user)
    {
      $thread = ForumThread::findOne($id_thread);
      $user = User::findOne($id_user);

      // cek apakah ada pembatasan per user atas id_thread ini ??
      $test1 = ForumThreadHakBaca::find()
        ->where(
          [
            "and",
            "id_thread = :id_thread"
          ],
          [
            ":id_thread" => $id_thread
          ]
        )
        ->all();

      if( count( $test1 ) > 0 )
      {
        //periksa apakah si user adalah si penulis?
        if( $thread["id_user_create"] == $id_user )
        {
          return true;
        }

        // cek id_user di dalam tabel forum_thread_hak_baca
        $test = ForumThreadHakBaca::find()
          ->where(
            [
              "and",
              "id_user = :id_user",
              "id_thread = :id_thread"
            ],
            [
              ":id_user" => $id_user,
              ":id_thread" => $id_thread,
            ]
          )
          ->one();

        if( is_null($test) == false )
        {
          return true;
        }
        else
        {
          return false;
        }
      }
      else
      {
        // tidak ada batasan per user, maka user bisa membaca

        return true;
      }

    }


}
