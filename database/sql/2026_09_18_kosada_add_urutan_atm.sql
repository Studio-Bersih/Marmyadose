-- Kosada — manual ordering of the Laporan to follow each marketing's ATM book.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- Report@getReport sorts on this column; without it every report request fails.
--
-- The Laporan used to be ordered by when the loan was taken out. The client
-- works the month through the ATM book each marketing keeps, so staff now type
-- the loan's position in that book on the Laporan page and the report (screen
-- and print) follows it.
--
-- Stored per LOAN, not per member: 642 loans have no MEMBER_ID, and the report
-- itself is a list of loans. A member's new loan starts unnumbered and has to be
-- given its number again.
--
-- NULL means "not numbered yet". Those rows sort after the numbered ones, in
-- the old newest-first order, so existing reports look exactly as before until
-- someone starts numbering.
--
-- Do NOT run `php artisan migrate` on this database. See the note in
-- 2026_08_16_kosada_add_member_id.sql.

ALTER TABLE `kosada_kredit`
  ADD COLUMN `URUTAN_ATM` INT UNSIGNED NULL DEFAULT NULL AFTER `HIDDEN_FROM_REPORT`;
