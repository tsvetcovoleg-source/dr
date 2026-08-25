-- Add immutable rule lineage without modifying or removing existing rule data.
ALTER TABLE rules
 ADD COLUMN source_rule_id BIGINT UNSIGNED NULL AFTER rule_set_id,
 ADD INDEX idx_rules_source_rule(source_rule_id),
 ADD CONSTRAINT fk_rules_source_rule FOREIGN KEY(source_rule_id) REFERENCES rules(id) ON DELETE SET NULL;
