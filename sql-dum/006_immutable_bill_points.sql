-- Fatura puanları verildikten sonra kalıcıdır.
-- Uygulama ledger'ı append-only kullanır; mevcut point_logs satırları
-- fatura düzenlense veya silinse dahi geri alınmaz.
ALTER TABLE point_logs
    ADD COLUMN ledger_locked TINYINT(1) NOT NULL DEFAULT 1
        COMMENT 'Verilen puan kaydı değiştirilemez veya geri alınamaz';
