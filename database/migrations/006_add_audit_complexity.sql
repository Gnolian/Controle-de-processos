USE controle_processos;

ALTER TABLE audits
    ADD COLUMN complexity TINYINT NULL AFTER flag_estimated;

CREATE INDEX idx_audits_complexity ON audits (complexity);
