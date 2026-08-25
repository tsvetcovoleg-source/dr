SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_log;
DROP TABLE IF EXISTS rule_set_contributors;
DROP TABLE IF EXISTS user_roles;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS rule_conditions;
DROP TABLE IF EXISTS rules;
DROP TABLE IF EXISTS rule_sets;
SET FOREIGN_KEY_CHECKS = 1;
CREATE TABLE users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(64) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, full_name VARCHAR(191) NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
 must_change_password TINYINT(1) NOT NULL DEFAULT 1, failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
 locked_until DATETIME NULL, last_login_at DATETIME NULL, password_changed_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(32) NOT NULL UNIQUE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO roles(name) VALUES ('ADMIN'),('RULE_EDITOR'),('RULE_APPROVER'),('VIEWER');
CREATE TABLE user_roles (
 user_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(user_id,role_id),
 CONSTRAINT fk_user_roles_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_user_roles_role FOREIGN KEY(role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NULL, username VARCHAR(64) NULL,
 event_type VARCHAR(64) NOT NULL, entity_type VARCHAR(64) NULL, entity_id BIGINT UNSIGNED NULL,
 details JSON NULL, ip_address VARCHAR(45) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_created(created_at), INDEX idx_audit_user(user_id),
 CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rule_sets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, version INT UNSIGNED NOT NULL UNIQUE,
 name VARCHAR(191) NULL, status VARCHAR(32) NOT NULL, created_by BIGINT UNSIGNED NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 comment TEXT NULL, submitted_by BIGINT UNSIGNED NULL, submitted_at DATETIME NULL, submission_comment TEXT NULL,
 approved_by BIGINT UNSIGNED NULL, approved_at DATETIME NULL, rejected_by BIGINT UNSIGNED NULL, rejected_at DATETIME NULL, rejection_reason TEXT NULL,
 activated_at DATETIME NULL, last_modified_by BIGINT UNSIGNED NULL, last_modified_at DATETIME NULL, active_guard TINYINT AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 draft_guard TINYINT AS (CASE WHEN status='DRAFT' THEN 1 ELSE NULL END) STORED,
 pending_guard TINYINT AS (CASE WHEN status='PENDING_APPROVAL' THEN 1 ELSE NULL END) STORED,
 UNIQUE KEY uq_one_active(active_guard), UNIQUE KEY uq_one_draft(draft_guard), UNIQUE KEY uq_one_pending(pending_guard), INDEX idx_rule_sets_status(status),
 CONSTRAINT fk_rule_sets_creator FOREIGN KEY(created_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_rule_sets_submitter FOREIGN KEY(submitted_by) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT fk_rule_sets_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_rule_sets_rejecter FOREIGN KEY(rejected_by) REFERENCES users(id) ON DELETE SET NULL, CONSTRAINT fk_rule_sets_modifier FOREIGN KEY(last_modified_by) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT chk_rule_sets_status CHECK(status IN ('DRAFT','PENDING_APPROVAL','ACTIVE','REJECTED','ARCHIVED'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO rule_sets(version,name,status) VALUES(1,'Initial Rule Set','ACTIVE');
CREATE TABLE rule_set_contributors (rule_set_id BIGINT UNSIGNED NOT NULL,user_id BIGINT UNSIGNED NOT NULL,first_contributed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY(rule_set_id,user_id),CONSTRAINT fk_contributor_set FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id) ON DELETE CASCADE,CONSTRAINT fk_contributor_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rules (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rule_set_id BIGINT UNSIGNED NOT NULL,
 source_rule_id BIGINT UNSIGNED NULL,
 rule_code VARCHAR(50) NOT NULL,
 stage_name VARCHAR(64) NOT NULL,
 avg_actual_pd DECIMAL(8,4) NOT NULL,
 priority INT NOT NULL DEFAULT 10,
 active TINYINT(1) NOT NULL DEFAULT 1,
 description TEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 INDEX idx_rules_stage_active_priority (stage_name, active, priority),
 INDEX idx_rules_source_rule(source_rule_id),
 UNIQUE KEY uq_rules_set_code(rule_set_id,rule_code), INDEX idx_rules_rule_set(rule_set_id),
 CONSTRAINT fk_rules_rule_set FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id),
 CONSTRAINT fk_rules_source_rule FOREIGN KEY(source_rule_id) REFERENCES rules(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE rule_conditions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 rule_id BIGINT UNSIGNED NOT NULL,
 field_name VARCHAR(191) NOT NULL,
 operator VARCHAR(20) NOT NULL,
 value TEXT NULL,
 sort_order INT NOT NULL DEFAULT 1,
 INDEX idx_conditions_rule_order (rule_id, sort_order),
 INDEX idx_conditions_field (field_name),
 CONSTRAINT fk_conditions_rule FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'HR_001','HARD_REFUSAL_STAGE',30.47,10,1,'Demo decision rule HR_001');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'AGE','>','31.03',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_ACCOUNTS_OPENED_24M_BEFORE_LOAN_DATE_COUNT','>','4.5',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'DEBT_SERVICE_TO_INCOME_RATIO_PERCENT','>','23.375',3);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'MONTHS_SINCE_LAST_OVERDUE_30_PLUS_DAYS_BEFORE_LOAN_DATE','<=','19.5',4);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'PRIMARY_INCOME_SOURCE','NOT_IN','["SALARY_OFFICIALLY_VERIFIED"]',5);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'HR_002','HARD_REFUSAL_STAGE',51.28,20,1,'Demo decision rule HR_002');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','39',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT','>','1.5',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'UNCLOSED_OTHER_CREDIT_ACCOUNTS_COUNT','>','0.5',3);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'HR_003','HARD_REFUSAL_STAGE',25.85,30,1,'Demo decision rule HR_003');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'AGE','<=','28.21',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'MONTHS_SINCE_LAST_OVERDUE_30_PLUS_DAYS_BEFORE_LOAN_DATE_STATUS','NOT_IN','["NO_HISTORICAL_OVERDUE_30_PLUS_DAYS"]',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'UNCLOSED_NONBANKING_CREDIT_ACCOUNTS_COUNT','>','0.5',3);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'HR_004','HARD_REFUSAL_STAGE',24.17,40,1,'Demo decision rule HR_004');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT','>','2.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','32.5',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'DEBT_SERVICE_TO_INCOME_RATIO_PERCENT','>','32.24',3);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'NONBANKING_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','22',4);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'UNCLOSED_NONBANKING_CREDIT_ACCOUNTS_COUNT','>','0.5',5);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'HR_005','HARD_REFUSAL_STAGE',36.48,50,1,'Demo decision rule HR_005');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','61.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','NOT_IN','["Yes"]',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'MONTHS_SINCE_LAST_OVERDUE_30_PLUS_DAYS_BEFORE_LOAN_DATE','<=','1.5',3);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'HR_006','HARD_REFUSAL_STAGE',33.06,60,1,'Demo decision rule HR_006');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','61.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT','>','4.5',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','NOT_IN','["Yes"]',3);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'OUR_BANK_CREDIT_ACCOUNTS_COUNT','<=','0.5',4);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'HR_007','HARD_REFUSAL_STAGE',34.93,70,1,'Demo decision rule HR_007');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE_STATUS','NOT_IN','["HAS_BANK_CREDIT_HISTORY"]',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'DEBT_SERVICE_TO_INCOME_RATIO_PERCENT','>','13.375',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','IN','["No"]',3);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'MONTHS_SINCE_LAST_OVERDUE_30_PLUS_DAYS_BEFORE_LOAN_DATE_STATUS','IN','["HAS_HISTORICAL_OVERDUE_30_PLUS_DAYS"]',4);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'UNCLOSED_NONBANKING_CREDIT_ACCOUNTS_COUNT','>','1.5',5);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'RR_001','RISK_REVIEW_STAGE',10.14,10,1,'Demo decision rule RR_001');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_ACCOUNTS_OPENED_12M_BEFORE_LOAN_DATE_COUNT','>','1.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_HISTORY_MONTHS_BEFORE_LOAN_DATE','<=','65.5',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'DEBT_SERVICE_TO_INCOME_RATIO_PERCENT','>','11.445',3);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','NOT_IN','["Yes"]',4);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'OUR_BANK_CREDIT_ACCOUNTS_COUNT','<=','0.5',5);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'RR_002','RISK_REVIEW_STAGE',9.42,20,1,'Demo decision rule RR_002');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'AGE','<=','63.115',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'AGE','>','52.945',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_ACCOUNTS_COUNT','<=','0.5',3);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'DEBT_SERVICE_TO_INCOME_RATIO_PERCENT','>','13.36',4);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','IN','["No"]',5);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'RR_003','RISK_REVIEW_STAGE',11.77,30,1,'Demo decision rule RR_003');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'GENDER','IN','["MALE"]',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','NOT_IN','["Yes"]',2);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'PRIMARY_INCOME_SOURCE','IN','["OTHER"]',3);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'RR_004','RISK_REVIEW_STAGE',12.88,40,1,'Demo decision rule RR_004');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'AGE','>','38.235',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'MONTHS_SINCE_LAST_OVERDUE_30_PLUS_DAYS_BEFORE_LOAN_DATE','<=','4.5',2);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'RR_005','RISK_REVIEW_STAGE',9.87,50,1,'Demo decision rule RR_005');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'AGE','<=','38.235',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'CREDIT_ACCOUNTS_OPENED_6M_BEFORE_LOAN_DATE_COUNT','>','2.5',2);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'PS_001','PORTFOLIO_SEGMENTATION_STAGE',4.07,10,1,'Demo decision rule PS_001');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_ACCOUNTS_COUNT','<=','2.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','NOT_IN','["Yes"]',2);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'PS_002','PORTFOLIO_SEGMENTATION_STAGE',2.88,20,1,'Demo decision rule PS_002');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_ACCOUNTS_COUNT','>','2.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'UNCLOSED_NONBANKING_CREDIT_ACCOUNTS_COUNT','>','0.5',2);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'PS_003','PORTFOLIO_SEGMENTATION_STAGE',2.15,30,1,'Demo decision rule PS_003');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_ACCOUNTS_COUNT','<=','2.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'HAS_ESTATE','IN','["Yes"]',2);
INSERT INTO rules(rule_set_id,rule_code,stage_name,avg_actual_pd,priority,active,description) VALUES(1,'PS_004','PORTFOLIO_SEGMENTATION_STAGE',1.06,40,1,'Demo decision rule PS_004');
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'BANK_CREDIT_ACCOUNTS_COUNT','>','2.5',1);
INSERT INTO rule_conditions(rule_id,field_name,operator,value,sort_order) VALUES(LAST_INSERT_ID(),'UNCLOSED_NONBANKING_CREDIT_ACCOUNTS_COUNT','<=','0.5',2);
