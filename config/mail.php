<?php

$config = [
    'host'       => 'smtp.gmail.com',
    'username'   => '',
    'password'   => '',
    'port'       => 587,
];

$localConfig = __DIR__ . '/mail.local.php';

if (is_file($localConfig)) {
    $local = require $localConfig;

    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

return $config;
