<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'defaultRoute' => 'dashboard/index',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
            'user' => [
            'identityClass'   => \app\models\User::class,
            'enableAutoLogin' => true,     // for rememberMe
            'loginUrl'        => ['auth/login'],
        ],
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => 'F5Hwgg7aMvpfJ_C-lFrGjc-9npRns3dG',
        ],
        'formatter' => ['class' => \yii\i18n\Formatter::class, 'nullDisplay' => '-'],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'as access' => [
            'class'        => yii\filters\AccessControl::class,
            'denyCallback' => function () { return Yii::$app->response->redirect(['auth/login']); },
            'rules' => [
                ['allow' => true, 'actions' => ['login'],  'controllers' => ['auth'], 'roles' => ['?']],
                ['allow' => true, 'actions' => ['error'],  'controllers' => ['site'], 'roles' => ['?', '@']],
                // Allow everything else only for logged-in users
                ['allow' => true, 'roles' => ['@']],
            ],
        ],

        'errorHandler' => [
            'errorAction' => 'site/error',
            
        ],
        'mailer' => [
            'class' => \yii\symfonymailer\Mailer::class,
            'viewPath' => '@app/mail',
            // send all mails to a file by default.
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                '' => 'site/index',

                // Existing shortcuts
                'operational-cost' => 'operational-cost/create',
                'operational-cost/<id:\d+>' => 'operational-cost/view',
                'revenue/<id:\d+>' => 'revenue/view',

                // NEW: allowing any action on OperationalCostController
                'operational-cost/<action:\w+>' => 'operational-cost/<action>',

                // Optional: short alias "ops/*" → OperationalCostController
                'ops/<action:\w+>' => 'operational-cost/<action>',
                'summary' => 'summary/index',
                'summary/<id:\d+>' => 'summary/index',
                'summary/<id:\d+>/excel' => 'summary/export-excel',
                'summary/<id:\d+>/pdf'   => 'summary/export-pdf',
            ],
        ],
                
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
