ALTER TABLE events
    ADD COLUMN criteria_sources TEXT NULL
        COMMENT 'Puanlama için kullanılan criteria.json kaynaklarının kısa özeti'
        AFTER points_reason;
