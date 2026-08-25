-- Safe, additive indexes for the existing audit_log table. Existing rows are preserved.
ALTER TABLE audit_log
  ADD INDEX idx_audit_event_type (event_type),
  ADD INDEX idx_audit_entity (entity_type, entity_id);
-- created_at and user_id indexes already exist in the original schema.
