-- Kosada — Data Macet (bad-debt register).
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- Requires 2026_08_16_kosada_add_member_id.sql to have been applied first.
--
-- What this stores: only the human judgement. Every money figure on the Data
-- Macet page (Total Pinjaman, Sisa Angsuran, the 30% penalty, the total) is
-- computed live from kosada_kredit and kosada_detail_kredit at read time. That
-- was a deliberate decision -- if a borrower makes a partial payment the list
-- must shrink to match, so snapshotting the amounts here would go stale and
-- staff would chase the wrong number.
--
-- One row per LOAN, not per person. A borrower with two bad loans appears twice,
-- which matches the client's "Total Pinjaman" being a single figure and matches
-- the button living in one loan's detail modal.
--
-- Entries are never deleted. Marking a case resolved sets STATUS='Selesai' and
-- records when and why, so the cooperative keeps its history.
--
-- Column types are BIGINT signed to match kosada_kredit.ID and kosada_member.ID,
-- which are both signed `bigint`.
--
-- Do NOT run `php artisan migrate` on this database. See the note in
-- 2026_08_16_kosada_add_member_id.sql.

CREATE TABLE IF NOT EXISTS `kosada_kredit_macet` (
  `ID`              BIGINT       NOT NULL AUTO_INCREMENT,
  `KREDIT_ID`       BIGINT       NOT NULL,
  `NO_KREDIT`       VARCHAR(64)  NULL DEFAULT NULL,
  `MEMBER_ID`       BIGINT       NULL DEFAULT NULL,
  `ALASAN_MACET`    TEXT         NOT NULL,
  `STATUS`          ENUM('Macet','Selesai') NOT NULL DEFAULT 'Macet',
  `TANGGAL_MACET`   DATE         NOT NULL,
  `TANGGAL_SELESAI` DATE         NULL DEFAULT NULL,
  `ALASAN_SELESAI`  TEXT         NULL DEFAULT NULL,
  `CREATED_AT`      TIMESTAMP    NULL DEFAULT NULL,
  `UPDATED_AT`      TIMESTAMP    NULL DEFAULT NULL,
  PRIMARY KEY (`ID`),
  -- Stops the same loan being registered twice. Re-flagging a resolved case
  -- updates this row back to 'Macet' rather than inserting a duplicate.
  UNIQUE KEY `uq_macet_kredit` (`KREDIT_ID`),
  KEY `idx_macet_status` (`STATUS`),
  KEY `idx_macet_member` (`MEMBER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
