<?php

namespace app\models;

use Yii;

use yii\db\Query;

/**
 * This is the model class for table "hd_issue".
 *
 * @property int $id
 * @property int $id_kategori Relasi ke tabel kms_kategori
 * @property int $linked_id_issue idquestion pada Confluence-Question
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
 * @property int|null $status 0=draft;1=new;2=publish;3=un-publish;4=reject;5=freeze;6=knowledge
 */
class HdIssue extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_issue';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori', 'linked_id_issue', 'view', 'like', 'dislike', 'id_user_create', 'id_user_update', 'id_user_delete', 'id_user_publish', 'is_delete', 'is_publish', 'status'], 'integer'],
            [['linked_id_issue', 'id_user_create', 'time_create'], 'required'],
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
            'linked_id_issue' => 'Linked Id Issue',
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
    
    public static function StatusInRange($id_issue, $type_status, $tanggal_awal, $tanggal_akhir)
    {
      $q = new Query();
      $q->select("count(l.id) as jumlah");
      $q->from("hd_issue_activity_log l");
      $q->join("JOIN", "hd_issue h", "h.id = l.id_issue");
      $q->where([
          "and",
          "l.id_issue = :id_issue",
          "l.type_log = 1",
          "l.status = :type_status",
          "l.time_status >= :awal",
          "l.time_status <= :akhir"
        ])
        ->params([
          ":id_issue" => $id_issue,
          ":type_status" => $type_status,
          ":awal" => date("Y-m-j 00:00:00", $tanggal_awal->timestamp),
          ":akhir" => date("Y-m-j 23:59:59", $tanggal_akhir->timestamp),
        ]);
      $hasil = $q->one();

      return $hasil["jumlah"];

    }


    public static function UpdateStatus($id, $status)
    {
      $issue = HdIssue::findOne($id);
      if( is_null($issue) == false )
      {
        $issue["status"] = $status;
        $issue["time_status"] = date("Y-m-j H:i:s");
        $issue->save();

        return true;
        $daftar_sukses[] = $issue;

        // tulis log
        $this->IssueLog($id_issue, $payload["id_user"], 1, $payload["status"]);
      }
      else
      {
        return false;

        $daftar_gagal[] = $id_issue;
      }
    }
}
