<?php
declare(strict_types=1);
namespace DecisionRules;
use PDO;

final class UserRepository {
    public const ROLES=['ADMIN','RULE_EDITOR','RULE_APPROVER','VIEWER'];
    public function __construct(private PDO $pdo) {}
    public function findByUsername(string $username): ?array { $s=$this->pdo->prepare('SELECT * FROM users WHERE username=?');$s->execute([$username]);return $s->fetch()?:null; }
    public function find(int $id): ?array { $s=$this->pdo->prepare('SELECT * FROM users WHERE id=?');$s->execute([$id]);$u=$s->fetch();if(!$u)return null;$u['roles']=$this->roles($id);return $u; }
    public function all(): array { $rows=$this->pdo->query('SELECT * FROM users ORDER BY username')->fetchAll();foreach($rows as &$u)$u['roles']=$this->roles((int)$u['id']);return $rows; }
    public function roles(int $id): array { $s=$this->pdo->prepare('SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id=r.id WHERE ur.user_id=? ORDER BY r.name');$s->execute([$id]);return $s->fetchAll(PDO::FETCH_COLUMN); }
    public function recordFailure(int $id, int $attempts): void { $lock=$attempts>=5?date('Y-m-d H:i:s',time()+900):null;$s=$this->pdo->prepare('UPDATE users SET failed_login_attempts=?,locked_until=? WHERE id=?');$s->execute([$attempts,$lock,$id]); }
    public function recordLogin(int $id): void { $s=$this->pdo->prepare('UPDATE users SET failed_login_attempts=0,locked_until=NULL,last_login_at=NOW() WHERE id=?');$s->execute([$id]); }
    public function create(array $data): int { $s=$this->pdo->prepare('INSERT INTO users(username,password_hash,full_name,active,must_change_password,password_changed_at) VALUES(?,?,?,?,1,NOW())');$s->execute([$data['username'],password_hash($data['password'],PASSWORD_DEFAULT),$data['full_name'],(int)$data['active']]);$id=(int)$this->pdo->lastInsertId();$this->setRoles($id,$data['roles']);return $id; }
    public function update(int $id,string $fullName,bool $active,array $roles): void { $s=$this->pdo->prepare('UPDATE users SET full_name=?,active=? WHERE id=?');$s->execute([$fullName,(int)$active,$id]);$this->setRoles($id,$roles); }
    public function setRoles(int $id,array $roles): void { $roles=array_values(array_intersect(self::ROLES,$roles));$this->pdo->beginTransaction();try{$s=$this->pdo->prepare('DELETE FROM user_roles WHERE user_id=?');$s->execute([$id]);$s=$this->pdo->prepare('INSERT INTO user_roles(user_id,role_id) SELECT ?,id FROM roles WHERE name=?');foreach($roles as $role)$s->execute([$id,$role]);$this->pdo->commit();}catch(\Throwable $e){$this->pdo->rollBack();throw $e;} }
    public function setActive(int $id,bool $active): void { $s=$this->pdo->prepare('UPDATE users SET active=? WHERE id=?');$s->execute([(int)$active,$id]); }
    public function password(int $id,string $password,bool $mustChange): void { $s=$this->pdo->prepare('UPDATE users SET password_hash=?,must_change_password=?,password_changed_at=NOW(),failed_login_attempts=0,locked_until=NULL WHERE id=?');$s->execute([password_hash($password,PASSWORD_DEFAULT),(int)$mustChange,$id]); }
}
