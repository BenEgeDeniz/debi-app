-- Fatura puanlarının bir kez bakiyeye yazıldığını fatura kaydında tutar.
-- point_logs bu kararın kaynağı değildir ve hiçbir zaman geri okuma için kullanılmaz.
ALTER TABLE water_logs
    ADD COLUMN points_credited TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'Bu faturanın puanı households.total_points bakiyesine yazıldı mı';

UPDATE water_logs
SET points_credited = 1
WHERE earned_points > 0;
