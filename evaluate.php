<?php
declare(strict_types=1);

use DecisionRules\Database;
use DecisionRules\EvaluationLogRepository;
use DecisionRules\RuleEngine;
use DecisionRules\RuleRepository;

header('Content-Type: application/json; charset=utf-8');
ini_set('serialize_precision', '-1');
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/RuleRepository.php';
require_once __DIR__ . '/src/RuleEngine.php';
require_once __DIR__ . '/src/EvaluationLogRepository.php';

$started=hrtime(true);$receivedAt=(new DateTimeImmutable())->format('Y-m-d H:i:s.u');$requestId=EvaluationLogRepository::requestId();$log=null;$logId=null;$pdo=null;
try {
    $pdo=Database::connect(__DIR__);$log=new EvaluationLogRepository($pdo);
    try {$logId=$log->start(['request_id'=>$requestId,'received_at'=>$receivedAt,'http_method'=>substr((string)($_SERVER['REQUEST_METHOD']??'GET'),0,10),'endpoint'=>substr((string)(parse_url($_SERVER['REQUEST_URI']??'/evaluate.php',PHP_URL_PATH)?:'/evaluate.php'),0,191),'client_ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null,'user_agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)?:null,'request_payload'=>$_GET]);}catch(Throwable $loggingError){error_log('Evaluation log start failed: '.$loggingError->getMessage());}
    $engine = new RuleEngine(new RuleRepository($pdo));
    $response=$engine->evaluate($_GET);$status=200;
} catch (Throwable $e) {
    $status=500;
    $code = $e instanceof RuntimeException && str_contains($e->getMessage(), 'Configuration') ? 'CONFIGURATION_ERROR' : 'DATABASE_ERROR';
    $response=['success'=>false,'error'=>['code'=>$code,'message'=>$code === 'CONFIGURATION_ERROR' ? $e->getMessage() : 'Unable to evaluate rules']];
}
$completedAt=(new DateTimeImmutable())->format('Y-m-d H:i:s.u');$elapsed=(hrtime(true)-$started)/1e6;
if($log&&$logId){try{$data=['completed_at'=>$completedAt,'response_payload'=>$response,'http_status'=>$status,'execution_time_ms'=>$elapsed];$status<400?$log->complete($logId,$data):$log->fail($logId,$data);}catch(Throwable $loggingError){error_log('Evaluation log completion failed: '.$loggingError->getMessage());}}
http_response_code($status);
echo json_encode($response,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
