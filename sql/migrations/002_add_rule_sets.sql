-- Safe in-place migration: preserves every existing rule and condition.
START TRANSACTION;
CREATE TABLE rule_sets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 version INT UNSIGNED NOT NULL UNIQUE,
 name VARCHAR(191) NULL,
 status VARCHAR(32) NOT NULL,
 created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 comment TEXT NULL,
 active_guard TINYINT AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 draft_guard TINYINT AS (CASE WHEN status='DRAFT' THEN 1 ELSE NULL END) STORED,
 UNIQUE KEY uq_one_active (active_guard), UNIQUE KEY uq_one_draft (draft_guard),
 INDEX idx_rule_sets_status(status),
 CONSTRAINT fk_rule_sets_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_rule_sets_status CHECK(status IN ('DRAFT','PENDING_APPROVAL','ACTIVE','REJECTED','ARCHIVED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO rule_sets(version,name,status) VALUES(1,'Initial Rule Set','ACTIVE');
SET @initial_rule_set_id = LAST_INSERT_ID();
ALTER TABLE rules ADD COLUMN rule_set_id BIGINT UNSIGNED NULL AFTER id;
UPDATE rules SET rule_set_id=@initial_rule_set_id WHERE rule_set_id IS NULL;
ALTER TABLE rules MODIFY rule_set_id BIGINT UNSIGNED NOT NULL;
ALTER TABLE rules DROP INDEX rule_code,
 ADD UNIQUE KEY uq_rules_set_code(rule_set_id,rule_code),
 ADD INDEX idx_rules_rule_set(rule_set_id),
 ADD CONSTRAINT fk_rules_rule_set FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id);
COMMIT;
