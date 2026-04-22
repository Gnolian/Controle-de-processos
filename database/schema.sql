CREATE DATABASE IF NOT EXISTS controle_processos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE controle_processos;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS sync_logs;
DROP TABLE IF EXISTS process_sync_state;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS integration_settings;
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
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE processes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_number VARCHAR(80) NOT NULL UNIQUE,
    updated_at DATE NULL,
    response_owner VARCHAR(120) NULL,
    deadline_days INT NULL,
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

CREATE TABLE integration_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE process_sync_state (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id INT UNSIGNED NOT NULL UNIQUE,
    excel_row_index INT NULL,
    sync_status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    last_synced_at DATETIME NULL,
    last_error TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sync_status (sync_status),
    CONSTRAINT fk_sync_state_process FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE CASCADE
);

CREATE TABLE sync_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id INT UNSIGNED NULL,
    status ENUM('success', 'failed', 'info') NOT NULL,
    message TEXT NOT NULL,
    payload JSON NULL,
    response JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sync_process (process_id),
    INDEX idx_sync_status_created (status, created_at),
    CONSTRAINT fk_sync_logs_process FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE SET NULL
);

INSERT INTO users (name, email, password_hash, role) VALUES
('Administrador', 'admin@local', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin');

INSERT INTO integration_settings (setting_key, setting_value) VALUES
('sync_enabled', '0'),
('tenant_id', ''),
('client_id', ''),
('client_secret', ''),
('redirect_uri', 'http://localhost:8080/controle-de-processos/public/graph_callback.php'),
('drive_id', ''),
('item_id', ''),
('table_name', 'Tabela1'),
('worksheet_name', ''),
('refresh_token', '');

INSERT INTO processes (
    process_number,
    updated_at,
    response_owner,
    deadline_days,
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
('71000.000001/2026-10', CURDATE(), 'Equipe Tecnica', 10, 'Exemplo de processo aberto', 'Registro de demonstracao para testar o painel.', 'Aguardando minuta da area responsavel.', 'SNBA', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Andrea', 'Em andamento', 'A iniciar', 'N/A', 'N/A', 'Aberto', 'Bloco 123', 1);

INSERT INTO process_sync_state (process_id, sync_status) VALUES (1, 'pending');
