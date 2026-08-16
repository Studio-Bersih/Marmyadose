-- Kosada — index NO_KREDIT on both credit tables.
--
-- Apply this in phpMyAdmin. It is pure performance: no column is added, removed
-- or retyped, and no data changes. Safe to run at any time.
--
-- Why: NO_KREDIT is the link between kosada_kredit and kosada_detail_kredit, and
-- it carried NO index on either table. Every loan-detail lookup and every row of
-- the monthly report scanned all 44,451 detail rows.
--
-- This surfaced when the Laporan page gained its "SEMUA" marketing option: the
-- report went from ~161 loans to ~5,249, and the request died on PHP's 30-second
-- limit. The controller's N+1 loop was fixed at the same time (Report@getReport
-- now uses a single grouped join), but without this index that single query is
-- still scanning both tables in full.
--
-- Prefix length 56 = the longest NO_KREDIT currently stored. Measured on the
-- production copy, a 32-character prefix is already fully selective (9,172
-- distinct prefixes vs 9,172 distinct full values), so 56 loses nothing.
--
-- A prefix index is required because NO_KREDIT is declared TEXT. Retyping it to
-- VARCHAR(64) would be the better long-term fix and would allow a plain index,
-- but that rewrites both tables and is not worth the risk purely for this.
--
-- Do NOT run `php artisan migrate` on this database. See the note in
-- 2026_08_16_kosada_add_member_id.sql.

ALTER TABLE `kosada_kredit`
  ADD INDEX `idx_kosada_kredit_no_kredit` (`NO_KREDIT`(56));

ALTER TABLE `kosada_detail_kredit`
  ADD INDEX `idx_kosada_detail_no_kredit` (`NO_KREDIT`(56));
