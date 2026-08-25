<?php
declare(strict_types=1);
namespace DecisionRules;
use PDO;

final class AuditLogger {
    public function __construct(private PDO $pdo) {}
    public function log(string $event, ?int $userId, ?string $username, ?string $entityType = null, ?int $entityId = null, array $details = []): void {
        $stmt=$this->pdo->prepare('INSERT INTO audit_log(user_id,username,event_type,entity_type,entity_id,details,ip_address) VALUES(?,?,?,?,?,?,?)');
        $stmt->execute([$userId,$username,$event,$entityType,$entityId,$details ? json_encode($details, JSON_THROW_ON_ERROR) : null,substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)?:null]);
    }
}
