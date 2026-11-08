ALTER TABLE users
    ADD COLUMN kvkk_consent_at DATETIME NULL,
    ADD COLUMN kvkk_consent_version VARCHAR(20) NULL,
    ADD COLUMN terms_accepted_at DATETIME NULL,
    ADD COLUMN terms_accepted_version VARCHAR(20) NULL,
    ADD COLUMN privacy_accepted_at DATETIME NULL,
    ADD COLUMN privacy_accepted_version VARCHAR(20) NULL;
