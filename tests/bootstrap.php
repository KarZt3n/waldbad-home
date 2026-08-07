<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (!isset($_SERVER['APP_SECRET'])) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if (($_SERVER['APP_DEBUG'] ?? false) === true) {
    umask(0000);
}
