<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenvPath = dirname(__DIR__, 2);
if (file_exists($dotenvPath . '/.env')) {
    Dotenv\Dotenv::createImmutable($dotenvPath)->safeLoad();
}

define('SMTP_ENC_KEY', getenv('SMTP_ENC_KEY') ?: $_ENV['SMTP_ENC_KEY'] ?? $_SERVER['SMTP_ENC_KEY'] ?? '');


