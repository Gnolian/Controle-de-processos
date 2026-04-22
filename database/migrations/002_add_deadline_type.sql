USE controle_processos;

SET @column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'processes'
      AND COLUMN_NAME = 'deadline_type'
);

SET @ddl := IF(
    @column_exists = 0,
    'ALTER TABLE processes ADD COLUMN deadline_type ENUM(''data'', ''tempo_habil'') NOT NULL DEFAULT ''data'' AFTER deadline_days',
    'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE processes
SET deadline_type = 'tempo_habil'
WHERE external_deadline_mds IS NULL
  AND adjusted_internal_deadline IS NULL
  AND internal_deadline_gab IS NULL;

