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
        $mail->isSMTP();                                            // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                    // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                   // Enable SMTP authentication
        $mail->Username   = 'frans.indroyono@gmail.com';                     // SMTP username
        $mail->Password   = 'ujwxdobmlzzubyyy';                               // SMTP password
        $mail->SMTPSecure = 'ssl';  //'tls/ssl';         // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
        $mail->Port       = 465;  //587 / 465;                                    // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

        $mail->setFrom('frans.indroyono@gmail.com', 'Frans Indroyono');

        switch(true)
        {
          case $params["type"] == "topik_puas" :

            $mail->Subject = 'Notifikasi : ';

            foreach($params["daftar_email"] as $send_item)
            {
              $mail->addAddress(
                $send_item["email"],
                $send_item["nama"]
              );
            }

            // Content
            $html = Yii::$app->controller->renderPartial(
              "@app/modules/general/views/general/emails/notifikasi/topik_puas",
              [
                "thread" => $params["thread"],
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
