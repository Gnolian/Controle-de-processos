USE controle_processos;

CREATE TABLE IF NOT EXISTS audit_logs (
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

CREATE TABLE IF NOT EXISTS integration_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS process_sync_state (
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

CREATE TABLE IF NOT EXISTS sync_logs (
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
('refresh_token', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;

INSERT IGNORE INTO process_sync_state (process_id, sync_status)
SELECT id, 'pending' FROM processes;

