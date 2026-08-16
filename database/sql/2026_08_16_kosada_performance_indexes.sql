-- Kosada — indexes for the list pages.
--
-- Apply this in phpMyAdmin. Pure performance: no column added, removed or
-- retyped, and no data changed. Safe to run at any time, and safe to leave in
-- place if the application code is rolled back.
--
-- Why these four:
--
-- 1. kosada_member had NO index except PRIMARY and the KTP unique key. The member
--    list sorts by CREATED_AT, so every request was a full table scan plus a
--    filesort over all 2,736 rows. EXPLAIN showed `type=ALL ... Using filesort`.
--    With the index it becomes `type=index ... Backward index scan` reading only
--    the rows on the requested page: measured 3.13 ms -> 0.30 ms.
--
-- 2. kosada_kredit is filtered by STATUS and CREATED_AT together on the Dashboard
--    and in the Laporan. A composite covers both, and makes the pagination COUNT
--    a covering-index scan (`Using index`, no table access): 8.24 ms -> 4.59 ms.
--    Column order matters: STATUS first because it is an equality test,
--    CREATED_AT second because it is a range.
--
-- 3./4. MARKETING is the other filter on both tables and had no index.
--
-- Note on name search: the "cari nama" boxes use LIKE '%...%'. A leading wildcard
-- cannot use a B-tree index, so no index is added for it. That is a deliberate
-- trade -- staff need to match on any part of a name, and at these row counts the
-- scan is acceptable. If it ever becomes a problem the answer is a FULLTEXT
-- index, not a plain one.
--
-- Do NOT run `php artisan migrate` on this database. See the note in
-- 2026_08_16_kosada_add_member_id.sql.

ALTER TABLE `kosada_member`
  ADD INDEX `idx_kosada_member_created` (`CREATED_AT`);

ALTER TABLE `kosada_member`
  ADD INDEX `idx_kosada_member_marketing` (`DATA_MARKETING`);

ALTER TABLE `kosada_kredit`
  ADD INDEX `idx_kosada_kredit_status_created` (`STATUS`, `CREATED_AT`);

ALTER TABLE `kosada_kredit`
  ADD INDEX `idx_kosada_kredit_marketing` (`MARKETING`);
