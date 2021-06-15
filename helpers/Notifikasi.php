<?php

  namespace app\helpers;

  use Yii;
  use yii\base\BaseObject;

  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\SMTP;
  use PHPMailer\PHPMailer\Exception;


  class Notifikasi extends BaseObject
  {

    /*
     * Mengirimkan notifikasi berdasarkan beberapa parameter
     *
     * params = array of param
     *
     * type = menentukan tipe notifikasi
     *
     * "topik_puas"
     *   Required params:
     *   thread - record forum_thread
     *   daftar_email - daftar record user tipe manager_konten
      * */
    public static function Kirim($params)
    {
      $mail = new PHPMailer(true);

      try
      {
        //Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;

        /* $mail->isSMTP();                              // Send using SMTP */
        /* $mail->Host       = 'mail.bppt.go.id';        // Set the SMTP server to send through */
        /* $mail->SMTPAuth   = true;                     // Enable SMTP authentication */
        /* $mail->Username   = 'simpan.ptik@bppt.go.id'; // SMTP username */
        /* $mail->Password   = 'jBAIZXRW';               // SMTP password */
        /* $mail->SMTPSecure = 'ssl';  //'tls/ssl';      // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged */
        /* $mail->Port       = 465;  //587 / 465;        // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above */


        $mail->isSMTP();                                 // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';            // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                        // Enable SMTP authentication
        $mail->Username   = 'frans.indroyono@gmail.com'; // SMTP username
        $mail->Password   = 'sgkrmzhcojjfptid';          // SMTP password
        $mail->SMTPSecure = 'ssl';  //'tls/ssl';         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 465;  //587 / 465;           // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

        $mail->setFrom('frans.indroyono@gmail.com', 'Frans Indroyono');

        switch(true)
        {
          case $params["type"] == "artikel_baru":
            $mail->Subject = 'Notifikasi Artikel Baru';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/artikel_baru",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "artikel_publish":
            $mail->Subject = 'Notifikasi Artikel Diterbitkan';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/artikel_publish",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "artikel_unpublish":
            $mail->Subject = 'Notifikasi Artikel Tidak Terbit';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/artikel_unpublish",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "artikel_reject":
            $mail->Subject = 'Notifikasi Artikel Telah Ditolak';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/artikel_reject",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "topik_baru":
            $mail->Subject = 'Notifikasi Topik Baru';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/topik_baru",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "topik_publish":
            $mail->Subject = 'Notifikasi Topik Diterbitkan';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/topik_publish",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "topik_unpublish":
            $mail->Subject = 'Notifikasi Topik Tidak Diterbitkan';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/topik_unpublish",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "topik_reject":
            $mail->Subject = 'Notifikasi Topik Ditolak';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/topik_reject",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "topik_freeze":
            $mail->Subject = 'Notifikasi Topik Telah Freeze';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );


            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/topik_freeze",
              [
                "artikel" => $params["artikel"],
              ]
            );

            break;

          case $params["type"] == "topik_puas" :

            $mail->Subject = 'Notifikasi topik puas : ';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );

            $mail->addAddress(
              "frans.indroyono@gmail.com",
              "Frans Indroyono"
            );

            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/topik_puas",
              [
                "thread" => $params["thread"],
              ]
            );

            break;

          case $params["type"] == "pic_tiket_baru" :

            $mail->Subject = 'Notifikasi tiket baru : ';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );

            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/pic_topik_baru",
              [
                "tiket" => $params["tiket"],
              ]
            );

            break;

          case $params["type"] == "pic_tiket_complete" :

            $mail->Subject = 'Notifikasi tiket complete : ';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            $mail->addAddress(
              "fasisi2003@yahoo.com",
              "Frans Indroyono"
            );

            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/pic_topik_complete",
              [
                "tiket" => $params["tiket"],
              ]
            );

            break;
        }

        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Body    = $html;
        $mail->AltBody = $html;

        $mail->send();

        return true;
      }
      catch(Exception $e)
      {
        $temp = json_encode($params);
        Yii::info("Params = " . $temp);
        Yii::info("Gagal mengirim notifikasi. Pesan error: " . $mail->ErrorInfo);

        return false;
      }
    }

  }

?>
