<?php
require dirname(__DIR__) . '/config.php';
require BASE_PATH . '/src/Database.php';
require BASE_PATH . '/src/Auth.php';

session_start();
(new Auth())->logout();
header('Location: ' . app_url('admin/'));
exit;
