-- AI değerleri değiştirilen faturalar için insan inceleme akışı
ALTER TABLE users
    ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Fatura incelemesi yapabilen yönetici hesabı';

ALTER TABLE water_logs
    ADD COLUMN review_status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'approved'
        COMMENT 'Fatura değerlerinin insan inceleme durumu',
    ADD COLUMN reviewed_by_user_id INT UNSIGNED NULL,
    ADD COLUMN reviewed_at DATETIME NULL,
    ADD COLUMN review_note VARCHAR(255) NULL;

CREATE INDEX idx_water_logs_review_status
    ON water_logs (review_status, period_year, period_month);
