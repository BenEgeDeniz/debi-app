ALTER TABLE comments
    ADD COLUMN parent_comment_id INT UNSIGNED NULL AFTER post_id,
    ADD KEY idx_comments_parent (parent_comment_id),
    ADD CONSTRAINT fk_comments_parent
        FOREIGN KEY (parent_comment_id) REFERENCES comments(id) ON DELETE CASCADE;
