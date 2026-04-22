CREATE DATABASE IF NOT EXISTS controle_processos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE controle_processos;

DROP TABLE IF EXISTS processes;
DROP TABLE IF EXISTS users;

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
    process_number VARCHAR(80) NOT NULL,
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
    CONSTRAINT fk_processes_user FOREIGN KEY (created_by) REFERENCES users(id)
);

INSERT INTO users (name, email, password_hash, role) VALUES
('Administrador', 'admin@local', '240be518fabd2724ddb6f04eeb1da5967448d7e831c08c8fa822809f74c720a9', 'admin');

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
    status,
    internal_block,
    created_by
) VALUES
('71000.000001/2026-10', CURDATE(), 'Equipe Tecnica', 10, 'Exemplo de processo aberto', 'Registro de demonstracao para testar o painel.', 'Aguardando minuta da area responsavel.', 'SNBA', DATE_ADD(CURDATE(), INTERVAL 5 DAY), DATE_ADD(CURDATE(), INTERVAL 7 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'Andrea', 'Em andamento', 'Aberto', 'Bloco 123', 1);
