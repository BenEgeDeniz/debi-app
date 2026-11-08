ALTER TABLE users
    ADD COLUMN is_ai_bot TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
    ADD KEY idx_users_ai_bot (is_ai_bot);

UPDATE users
SET nickname = CONCAT('debi', id)
WHERE LOWER(nickname) = 'debbie';

INSERT INTO users (
    household_id, neighborhood_id, apartment_id, full_name, nickname,
    email, password_hash, role, is_ai_bot, avatar_color
)
SELECT
    h.id, n.id, NULL, 'Debbie', 'debbie',
    'debbie@debi.internal', '$2y$10$7sJ8v7d3r9p4uF5hY2x8Ue1jQ6mN0cB4kL9wZ3aS5dF7gH1jK2lM',
    'resident', 1, '#0ea5e9'
FROM households h
INNER JOIN neighborhoods n ON n.id = h.neighborhood_id
WHERE NOT EXISTS (
    SELECT 1
    FROM users existing
    WHERE existing.nickname = 'debbie' OR existing.email = 'debbie@debi.internal'
)
ORDER BY h.id
LIMIT 1;
