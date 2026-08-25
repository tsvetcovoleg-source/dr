<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => '/']);
    session_start();
}
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/RuleRepository.php';
require_once __DIR__ . '/RuleEngine.php';
require_once __DIR__ . '/AuditLogger.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/Auth.php';

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function verify_csrf(): void { if (!isset($_POST['csrf']) || !hash_equals(csrf_token(), (string)$_POST['csrf'])) { http_response_code(403); exit('Invalid CSRF token.'); } }
function redirect(string $path): never { header('Location: ' . $path); exit; }
