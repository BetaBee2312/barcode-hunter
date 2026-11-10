-- Jalankan satu kali pada database Barcode Hunting.
-- Pastikan kolom hint belum ada sebelum menjalankan perintah ini.
ALTER TABLE tb_barcode
    ADD COLUMN hint TEXT NULL AFTER number_barcode;
