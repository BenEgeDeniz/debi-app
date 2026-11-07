ALTER TABLE `events`
    ADD COLUMN `ended_at` datetime DEFAULT NULL AFTER `start_time`;
