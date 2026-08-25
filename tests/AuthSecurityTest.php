<?php
declare(strict_types=1);
require_once __DIR__.'/../src/UserRepository.php';

use DecisionRules\UserRepository;

function assertSecurity(bool $condition,string $message): void { if(!$condition)throw new RuntimeException($message); }

$password='a temporary password 123!';
$hash=password_hash($password,PASSWORD_DEFAULT);
assertSecurity($hash!==$password,'Password was not hashed');
assertSecurity(password_verify($password,$hash),'Password hash cannot be verified');
assertSecurity(!password_verify('wrong password',$hash),'Invalid password was accepted');
assertSecurity(UserRepository::ROLES===['ADMIN','RULE_EDITOR','RULE_APPROVER','VIEWER'],'Unexpected role definitions');

$authSource=file_get_contents(__DIR__.'/../src/Auth.php');
assertSecurity(str_contains($authSource,'session_regenerate_id(true)'), 'Session ID is not rotated');
assertSecurity(str_contains(file_get_contents(__DIR__.'/../src/UserRepository.php'),'failed_login_attempts'), 'Login failure counter is not used');
assertSecurity(str_contains($authSource,'http_response_code(403)'), 'RBAC does not return 403');

$mutatingPages=['rule_action.php','user_toggle.php','user_password_reset.php','user_edit.php','change_password.php','logout.php'];
foreach($mutatingPages as $page){$source=file_get_contents(__DIR__.'/../admin/'.$page);assertSecurity(str_contains($source,'verify_csrf()'),$page.' lacks CSRF verification');}

echo "Auth security checks passed.\n";
