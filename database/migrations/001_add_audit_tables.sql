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

