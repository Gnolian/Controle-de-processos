USE controle_processos;

SET @user_col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'audit_access'
);

SET @user_ddl := IF(
    @user_col_exists = 0,
    'ALTER TABLE users ADD COLUMN audit_access TINYINT(1) NOT NULL DEFAULT 0 AFTER active',
    'SELECT 1'
);

PREPARE stmt_user FROM @user_ddl;
EXECUTE stmt_user;
DEALLOCATE PREPARE stmt_user;

CREATE TABLE IF NOT EXISTS audits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_code VARCHAR(40) NOT NULL UNIQUE,
    audit_nup VARCHAR(80) NOT NULL,
    audit_year YEAR NULL,
    process_status VARCHAR(80) NULL,
    requesting_body VARCHAR(80) NULL,
    audit_type VARCHAR(160) NULL,
    objective LONGTEXT NULL,
    theme TEXT NULL,
    classification VARCHAR(80) NULL,
    audit_phase VARCHAR(120) NULL,
    current_owner VARCHAR(120) NULL,
    start_date DATE NULL,
    has_diligence TINYINT(1) NOT NULL DEFAULT 0,
    last_response_sent_date DATE NULL,
    preliminary_document VARCHAR(255) NULL,
    stage2_deadline_days INT NULL,
    comments_due_date DATE NULL,
    stage2_final_response DATE NULL,
    final_report VARCHAR(255) NULL,
    stage2_service_deadline_days INT NULL,
    stage2_final_deadline DATE NULL,
    stage2_final_answer TEXT NULL,
    stage2_status VARCHAR(120) NULL,
    accord_report VARCHAR(255) NULL,
    accord_report_date DATE NULL,
    stage3_status VARCHAR(120) NULL,
    control_point TEXT NULL,
    related_processes TEXT NULL,
    notes LONGTEXT NULL,
    imported_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_audits_body (requesting_body),
    INDEX idx_audits_type (audit_type),
    INDEX idx_audits_phase (audit_phase),
    INDEX idx_audits_diligence (has_diligence),
    CONSTRAINT fk_audits_user FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS audit_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    audit_id INT UNSIGNED NOT NULL,
    item_code VARCHAR(80) NULL,
    item_kind VARCHAR(40) NOT NULL,
    item_description LONGTEXT NULL,
    compliance_deadline_days INT NULL,
    compliance_start_date DATE NULL,
    control_body_status VARCHAR(120) NULL,
    dgba_status VARCHAR(120) NULL,
    item_control_point LONGTEXT NULL,
    stage3_status VARCHAR(120) NULL,
    monitor_1_start_date DATE NULL,
    monitor_1_deadline_days INT NULL,
    monitor_1_final_deadline DATE NULL,
    monitor_1_response LONGTEXT NULL,
    monitor_2_start_date DATE NULL,
    monitor_2_deadline_days INT NULL,
    monitor_2_final_deadline DATE NULL,
    monitor_2_response LONGTEXT NULL,
    monitor_3_start_date DATE NULL,
    monitor_3_deadline_days INT NULL,
    monitor_3_final_deadline DATE NULL,
    monitor_3_response LONGTEXT NULL,
    monitor_4_start_date DATE NULL,
    monitor_4_deadline_days INT NULL,
    monitor_4_final_deadline DATE NULL,
    monitor_4_response LONGTEXT NULL,
    item_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_audit_items_audit (audit_id),
    INDEX idx_audit_items_kind (item_kind),
    CONSTRAINT fk_audit_items_audit FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

