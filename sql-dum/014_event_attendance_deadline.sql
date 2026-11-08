ALTER TABLE `events`
    ADD COLUMN `cancelled_at` datetime DEFAULT NULL AFTER `ended_at`,
    ADD KEY `idx_events_cancelled_at` (`cancelled_at`);
