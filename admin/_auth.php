<?php
declare(strict_types=1);
use DecisionRules\Auth;
use DecisionRules\Database;
require_once __DIR__.'/../src/bootstrap.php';
$pdo=Database::connect(dirname(__DIR__));
$auth=new Auth($pdo);
