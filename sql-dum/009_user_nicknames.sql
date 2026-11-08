ALTER TABLE users
    ADD COLUMN nickname VARCHAR(32) NULL AFTER full_name;

UPDATE users
SET nickname = CONCAT('debi', id)
WHERE nickname IS NULL OR nickname = '';

ALTER TABLE users
    MODIFY COLUMN nickname VARCHAR(32) NOT NULL,
    ADD UNIQUE KEY uq_users_nickname (nickname);
