<?php
declare(strict_types=1);
namespace DecisionRules;
use PDO;

final class Auth {
    public const TIMEOUT=1800;
    private UserRepository $users; private AuditLogger $audit;
    public function __construct(PDO $pdo){$this->users=new UserRepository($pdo);$this->audit=new AuditLogger($pdo);}
    public function login(string $username,string $password): bool { $u=$this->users->findByUsername($username);$valid=$u && (int)$u['active']===1 && (!$u['locked_until'] || strtotime($u['locked_until'])<=time()) && password_verify($password,$u['password_hash']);if(!$valid){if($u && (int)$u['active']===1)$this->users->recordFailure((int)$u['id'],(int)$u['failed_login_attempts']+1);$this->audit->log('LOGIN_FAILED',$u?(int)$u['id']:null,$username);return false;}$this->users->recordLogin((int)$u['id']);session_regenerate_id(true);$_SESSION['auth']=['id'=>(int)$u['id'],'username'=>$u['username'],'full_name'=>$u['full_name'],'roles'=>$this->users->roles((int)$u['id']),'must_change_password'=>(bool)$u['must_change_password'],'last_activity'=>time()];$this->audit->log('LOGIN_SUCCESS',(int)$u['id'],$u['username']);return true; }
    public function user(): ?array { $a=$_SESSION['auth']??null;if(!$a)return null;if(time()-(int)$a['last_activity']>self::TIMEOUT){$this->logout(false);return null;}$_SESSION['auth']['last_activity']=time();return $_SESSION['auth']; }
    public function requireLogin(bool $allowPasswordChange=false): array { $u=$this->user();if(!$u){header('Location: /admin/login.php');exit;}if($u['must_change_password']&&!$allowPasswordChange){header('Location: /admin/change_password.php');exit;}return $u; }
    public function hasRole(string $role): bool { $u=$this->user();return $u && in_array($role,$u['roles'],true); }
    public function requireRole(string $role): void { $this->requireLogin();if(!$this->hasRole($role)){http_response_code(403);exit('Forbidden');} }
    public function requireAnyRole(array $roles): void { $this->requireLogin();foreach($roles as $r)if($this->hasRole($r))return;http_response_code(403);exit('Forbidden'); }
    public function logout(bool $log=true): void { $u=$_SESSION['auth']??null;if($log&&$u)$this->audit->log('LOGOUT',$u['id'],$u['username']);$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy(); }
    public function users(): UserRepository{return $this->users;} public function audit(): AuditLogger{return $this->audit;}
}
