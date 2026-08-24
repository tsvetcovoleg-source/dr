<?php
declare(strict_types=1);

use DecisionRules\Database;
use DecisionRules\RuleEngine;
use DecisionRules\RuleRepository;

header('Content-Type: application/json; charset=utf-8');
ini_set('serialize_precision', '-1');
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/RuleRepository.php';
require_once __DIR__ . '/src/RuleEngine.php';

try {
    $engine = new RuleEngine(new RuleRepository(Database::connect(__DIR__)));
    echo json_encode($engine->evaluate($_GET), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    $code = $e instanceof RuntimeException && str_contains($e->getMessage(), 'Configuration') ? 'CONFIGURATION_ERROR' : 'DATABASE_ERROR';
    echo json_encode(['success'=>false,'error'=>['code'=>$code,'message'=>$code === 'CONFIGURATION_ERROR' ? $e->getMessage() : 'Unable to evaluate rules']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

