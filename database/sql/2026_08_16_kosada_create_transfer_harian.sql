-- Kosada — Transfer Harian (daily transfer recap).
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- Requires 2026_08_16_kosada_add_member_id.sql to have been applied first.
--
-- Replaces a handwritten daily sheet listing which nasabah need money
-- transferred that day. It is a PURE LOG: nothing here writes back to
-- kosada_kredit or kosada_detail_kredit.
--
--
-- TANGGAL_TRANSFER vs CREATED_AT — the important part
--
-- TANGGAL_TRANSFER is the BUSINESS date and is chosen by the user.
-- CREATED_AT is when the row was actually inserted and is set by the server.
--
-- They are separate on purpose. Staff sometimes forget to enter a day's
-- transfers and do it the next morning; that is allowed, and the entry is
-- correctly recorded against the day it belongs to. But management wants to see
-- when it happens, so the page renders any row where
--   TANGGAL_TRANSFER <> DATE(CREATED_AT)
-- in red. No flag column is stored for this: deriving it from the two dates
-- means it can never drift out of sync with reality, and it cannot be faked by
-- editing a flag.
--
-- CREATED_AT must therefore never be settable by the client.
--
--
-- NAMA and INSTANSI are snapshots taken at entry time, so a printed historical
-- sheet still reads correctly if the member is later renamed or deleted.
--
-- KREDIT_ID is nullable: a Top Up is typed manually and need not reference a
-- loan. Kasbon and Pinjaman Baru auto-fill from the selected loan.
--
-- Do NOT run `php artisan migrate` on this database. See the note in
-- 2026_08_16_kosada_add_member_id.sql.

CREATE TABLE IF NOT EXISTS `kosada_transfer_harian` (
  `ID`               BIGINT        NOT NULL AUTO_INCREMENT,
  `TANGGAL_TRANSFER` DATE          NOT NULL,
  `MEMBER_ID`        BIGINT        NULL DEFAULT NULL,
  `KREDIT_ID`        BIGINT        NULL DEFAULT NULL,
  `NAMA`             VARCHAR(255)  NOT NULL,
  `INSTANSI`         VARCHAR(255)  NULL DEFAULT NULL,
  `JENIS`            ENUM('Kasbon','Top Up','Pinjaman Baru') NOT NULL,
  `NOMINAL`          BIGINT        NOT NULL DEFAULT 0,
  `KETERANGAN`       TEXT          NULL DEFAULT NULL,
  `CREATED_AT`       TIMESTAMP     NULL DEFAULT NULL,
  `UPDATED_AT`       TIMESTAMP     NULL DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_transfer_tanggal` (`TANGGAL_TRANSFER`),
  KEY `idx_transfer_member` (`MEMBER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
