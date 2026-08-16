-- Kosada — let staff drop a settled loan off the Laporan without deleting it.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- Report@getReport filters on this column; without it every report request fails.
--
-- Why a flag instead of a delete: the client asked for paid-off entries to be
-- removable from the monthly report. Deleting the loan would also destroy its
-- installment history and remove it from the Dashboard, which is not what was
-- meant. Hiding is reversible, keeps kosada_kredit and kosada_detail_kredit
-- intact, and leaves the loan visible everywhere else.
--
-- Existing rows default to 0 (visible), which preserves current behaviour exactly.
--
-- Do NOT run `php artisan migrate` on this database. See the note in
-- 2026_08_16_kosada_add_member_id.sql.

ALTER TABLE `kosada_kredit`
  ADD COLUMN `HIDDEN_FROM_REPORT` TINYINT(1) NOT NULL DEFAULT 0 AFTER `STATUS`;
