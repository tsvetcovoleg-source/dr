<?php
declare(strict_types=1);
require __DIR__.'/_auth.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed');}
$auth->requireLogin(true);verify_csrf();$auth->logout();header('Location: /admin/login.php');exit;
