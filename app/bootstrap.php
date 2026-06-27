<?php

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/config/autoload.php';
require_once APP_ROOT . '/config/session_handler.php';
require_once APP_ROOT . '/config/constants.php';
require_once APP_ROOT . '/config/db_connect.php';
require_once APP_ROOT . '/config/component_helpers.php';
require_once APP_ROOT . '/app/Support/helpers.php';

$pdo = get_pdo_connection();
