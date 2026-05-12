USE controle_processos;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS audit_only TINYINT(1) NOT NULL DEFAULT 0 AFTER audit_access;
