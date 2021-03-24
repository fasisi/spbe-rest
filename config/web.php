<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';
$restconf = require __DIR__ . '/restconf.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    "modules" => [
      "general" => "app\modules\general\Module",
      "kms" => "app\modules\kms\Module",
    ],
    'components' => [
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'spbespbe2020',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['info', 'error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        /* */
        'urlManager' => [
            /* 
	    'baseUrl' => '/rest/web',
	    'hostInfo' => 'https://simpan-spbe.bppt.go.id/',
	    */
            'enablePrettyUrl' => false,
            'showScriptName' => false,
            'enableStrictParsing' => true,
            'rules' => [
              'class' => 'yii\rest\UrlRule',
              'controller' => 'general, kms',
              'prefix' => 'rest',
              'patterns' => [
                'GET ' => '',
              ]
            ],
        ],
        
        'restconf' => $restconf,
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
