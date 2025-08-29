<?php
// config/db.php
return [
    'class' => 'yii\db\Connection',
    'dsn' => sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        getenv('PGHOST') ?: 'localhost',
        getenv('PGPORT') ?: '5432',
        getenv('PGDATABASE') ?: 'layinvest_db'
    ),
    'username' => getenv('PGUSER') ?: 'postgres',
    'password' => getenv('PGPASSWORD') ?: 'admin123',
    'charset'  => 'utf8',
];