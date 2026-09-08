ALTER TABLE studies
    ADD COLUMN IF NOT EXISTS pdf_file VARCHAR(255) NULL AFTER access_link,
    ADD COLUMN IF NOT EXISTS pdf_original_name VARCHAR(255) NULL AFTER pdf_file,
    ADD COLUMN IF NOT EXISTS pdf_size INT UNSIGNED NULL AFTER pdf_original_name;
