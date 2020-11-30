<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "hd_kategori_sla".
 *
 * @property int $id
 * @property int $id_kategori
 * @property int $sla_open Jumlah satuan waktu batasan sla status open
 * @property int $sla_in_progress
 * @property int $sla_waiting_for_customer
 */
class HdKategoriSla extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'hd_kategori_sla';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id_kategori'], 'required'],
            [['id_kategori', 'sla_open', 'sla_in_progress', 'sla_waiting_for_customer'], 'integer'],
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
            'sla_open' => 'Sla Open',
            'sla_in_progress' => 'Sla In Progress',
            'sla_waiting_for_customer' => 'Sla Waiting For Customer',
        ];
    }
}
