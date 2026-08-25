-- In-place, data-preserving approval workflow migration.
START TRANSACTION;
ALTER TABLE rule_sets
 ADD COLUMN submitted_by BIGINT UNSIGNED NULL AFTER comment,
 ADD COLUMN submitted_at DATETIME NULL AFTER submitted_by,
 ADD COLUMN submission_comment TEXT NULL AFTER submitted_at,
 ADD COLUMN approved_by BIGINT UNSIGNED NULL AFTER submission_comment,
 ADD COLUMN approved_at DATETIME NULL AFTER approved_by,
 ADD COLUMN rejected_by BIGINT UNSIGNED NULL AFTER approved_at,
 ADD COLUMN rejected_at DATETIME NULL AFTER rejected_by,
 ADD COLUMN rejection_reason TEXT NULL AFTER rejected_at,
 ADD COLUMN activated_at DATETIME NULL AFTER rejection_reason,
 ADD COLUMN last_modified_by BIGINT UNSIGNED NULL AFTER activated_at,
 ADD COLUMN last_modified_at DATETIME NULL AFTER last_modified_by,
 ADD COLUMN pending_guard TINYINT AS (CASE WHEN status='PENDING_APPROVAL' THEN 1 ELSE NULL END) STORED,
 ADD UNIQUE KEY uq_one_pending(pending_guard),
 ADD CONSTRAINT fk_rule_sets_submitter FOREIGN KEY(submitted_by) REFERENCES users(id) ON DELETE SET NULL,
 ADD CONSTRAINT fk_rule_sets_approver FOREIGN KEY(approved_by) REFERENCES users(id) ON DELETE SET NULL,
 ADD CONSTRAINT fk_rule_sets_rejecter FOREIGN KEY(rejected_by) REFERENCES users(id) ON DELETE SET NULL,
 ADD CONSTRAINT fk_rule_sets_modifier FOREIGN KEY(last_modified_by) REFERENCES users(id) ON DELETE SET NULL;
CREATE TABLE rule_set_contributors (
 rule_set_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 first_contributed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(rule_set_id,user_id),
 CONSTRAINT fk_contributor_set FOREIGN KEY(rule_set_id) REFERENCES rule_sets(id) ON DELETE CASCADE,
 CONSTRAINT fk_contributor_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Existing drafts are attributed to their creators so maker-checker remains safe.
INSERT IGNORE INTO rule_set_contributors(rule_set_id,user_id)
 SELECT id,created_by FROM rule_sets WHERE status='DRAFT' AND created_by IS NOT NULL;
UPDATE rule_sets SET activated_at=created_at WHERE status='ACTIVE' AND activated_at IS NULL;
COMMIT;
