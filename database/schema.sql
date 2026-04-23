CREATE DATABASE IF NOT EXISTS controle_processos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE controle_processos;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS audit_items;
DROP TABLE IF EXISTS audits;
DROP TABLE IF EXISTS processes;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('servidor', 'coordenador', 'admin') NOT NULL DEFAULT 'servidor',
    active TINYINT(1) NOT NULL DEFAULT 1,
    audit_access TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE processes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_number VARCHAR(80) NOT NULL UNIQUE,
    updated_at DATE NULL,
    response_owner VARCHAR(120) NULL,
    deadline_days INT NULL,
    deadline_type ENUM('data', 'tempo_habil') NOT NULL DEFAULT 'data',
    general_description TEXT NULL,
    detailed_description TEXT NULL,
    notes TEXT NULL,
    requesting_agency VARCHAR(160) NULL,
    gab_signature_date DATE NULL,
    internal_deadline_gab DATE NULL,
    adjusted_internal_deadline DATE NULL,
    external_deadline_mds DATE NULL,
    review_owner VARCHAR(120) NULL,
    response_status ENUM('A iniciar', 'Em andamento', 'Concluido', 'N/A') NOT NULL DEFAULT 'A iniciar',
    andrea_review_status ENUM('A iniciar', 'Em andamento', 'Concluido', 'N/A') NOT NULL DEFAULT 'N/A',
    signed_status ENUM('A iniciar', 'Em andamento', 'Concluido', 'N/A') NOT NULL DEFAULT 'N/A',
    sent_gab_status ENUM('A iniciar', 'Em andamento', 'Concluido', 'N/A') NOT NULL DEFAULT 'N/A',
    gab_sent_date DATE NULL,
    status ENUM('Aberto', 'Arquivado (concluido)', 'Arquivar') NOT NULL DEFAULT 'Aberto',
    internal_block VARCHAR(120) NULL,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_record_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_process_deadlines (external_deadline_mds, adjusted_internal_deadline, internal_deadline_gab),
    INDEX idx_process_status (status, response_status, andrea_review_status, signed_status, sent_gab_status),
    INDEX idx_process_owner (response_owner),
    CONSTRAINT fk_processes_user FOREIGN KEY (created_by) REFERENCES users(id)
);

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NULL,
    field_name VARCHAR(120) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    origin VARCHAR(80) NOT NULL DEFAULT 'sistema interno',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_process (process_id),
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_created (created_at),
    CONSTRAINT fk_audit_process FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE SET NULL,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE audits (
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
    CONSTRAINT fk_audits_user FOREIGN KEY (imported_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE audit_items (
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
    CONSTRAINT fk_audit_items_audit FOREIGN KEY (audit_id) REFERENCES audits(id) ON DELETE CASCADE
);

INSERT INTO users (name, email, password_hash, role) VALUES
('Administrador', 'admin@local', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin');

INSERT INTO processes (
    process_number,
    updated_at,
    response_owner,
    deadline_days,
    deadline_type,
    general_description,
    detailed_description,
    notes,
    requesting_agency,
    internal_deadline_gab,
    adjusted_internal_deadline,
    external_deadline_mds,
    review_owner,
    response_status,
    andrea_review_status,
    signed_status,
    sent_gab_status,
    status,
    internal_block,
    created_by
) VALUES
('71000.000001/2026-10', CURDATE(), 'Equipe Tecnica', 10, 'data', 'Exemplo de processo aberto', 'Registro de demonstracao para testar o painel.', 'Aguardando minuta da area responsavel.', 'SNBA', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Andrea', 'Em andamento', 'A iniciar', 'N/A', 'N/A', 'Aberto', 'Bloco 123', 1);
