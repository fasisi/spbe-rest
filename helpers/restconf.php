<?php

  namespace app\helpers;

  use yii\base\BaseObject;

  class restconf extends BaseObject
  {
    public $ip, $port, $user, $password;
    public $confs;

    public function __construct($config=[])
    {
      parent::__construct($config);
    }

    public function init()
    {
      parent::init();
    }

  }

?>
