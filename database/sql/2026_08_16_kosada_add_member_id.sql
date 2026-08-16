-- Kosada — link kosada_kredit back to kosada_member.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
--
-- Why: kosada_kredit copies NAMA and ALAMAT from kosada_member at creation time
-- (Kredit@addKredit) and stores no reference back. That means a loan carries no
-- PEKERJAAN and no TELEPON (the WhatsApp number). The Data Macet and Transfer
-- Harian features both need those fields, so without this column each one would
-- have to guess the member by name at read time.
--
-- MEMBER_ID is NULLABLE on purpose. Loans whose member was renamed or deleted
-- will not match during the backfill, and that is a supported state: the app
-- falls back to the NAMA/ALAMAT already stored on the loan and renders "-" for
-- Pekerjaan and WhatsApp. Nothing breaks, and the gaps stay visible so staff
-- can correct them. Never use an INNER JOIN on this column — that would silently
-- drop unlinked loans from the dashboard.
--
-- Do NOT run `php artisan migrate` on this database. Its migrations table
-- records only the project's original Laravel 9/10-era migrations; the repo's
-- current database/migrations/ holds Laravel 11-style files that are not
-- recorded there, so migrate would attempt create_users_table against the
-- existing `users` table and fail.


-- ---------------------------------------------------------------------------
-- STEP 1 — Before you run anything: check for duplicate member names.
-- ---------------------------------------------------------------------------
-- STEP 3 skips any name shared by more than one member, so the backfill cannot
-- link a loan to the wrong person. This query just tells you which names those
-- are. On the production copy it returns 38 names covering 79 member rows.
-- Nothing here blocks the migration — it is a to-do list for later cleanup.
--
--   SELECT NAMA, COUNT(*) AS JUMLAH
--   FROM kosada_member
--   GROUP BY NAMA
--   HAVING JUMLAH > 1
--   ORDER BY JUMLAH DESC;


-- ---------------------------------------------------------------------------
-- STEP 2 — Add the column.
-- ---------------------------------------------------------------------------
-- BIGINT signed, deliberately: kosada_member.ID is `bigint` (signed), and a join
-- column has to match the type it points at.
ALTER TABLE `kosada_kredit`
  ADD COLUMN `MEMBER_ID` BIGINT NULL DEFAULT NULL AFTER `NO_KREDIT`,
  ADD INDEX `idx_kosada_kredit_member` (`MEMBER_ID`);


-- ---------------------------------------------------------------------------
-- STEP 3 — Backfill, but ONLY where the name is unambiguous.
-- ---------------------------------------------------------------------------
-- Both NAMA columns are utf8mb4_unicode_ci, so the match is case-insensitive and
-- the older all-caps rows still match today's ucwords(strtolower(...)) format.
--
-- The subquery deliberately requires COUNT(*) = 1. A plain `UPDATE ... JOIN`
-- would silently pick one member at random whenever two people share a name, and
-- 38 names in this database are shared. Linking a loan to the wrong person puts
-- someone else's phone number and employer on a Data Macet collections sheet —
-- far worse than showing "-". Ambiguous loans stay NULL and fall back to the
-- NAMA/ALAMAT stored on the loan itself, exactly like unmatched ones.
--
-- Measured against the production copy (9,174 loans):
--   8,532 link cleanly · 174 ambiguous (left NULL) · 468 no match (left NULL)
UPDATE `kosada_kredit` k
  SET k.`MEMBER_ID` = (
    SELECT m.`ID` FROM `kosada_member` m WHERE m.`NAMA` = k.`NAMA`
  )
WHERE k.`MEMBER_ID` IS NULL
  AND (SELECT COUNT(*) FROM `kosada_member` m2 WHERE m2.`NAMA` = k.`NAMA`) = 1;


-- ---------------------------------------------------------------------------
-- STEP 4 — Record how many loans could not be linked.
-- ---------------------------------------------------------------------------
-- Run this and note the number in the delivery log. A non-zero result is not a
-- failure — see the header. It is the count of loans that will show "-" for
-- Pekerjaan and WhatsApp until someone links them.
--
--   SELECT COUNT(*) AS BELUM_TERHUBUNG
--   FROM kosada_kredit
--   WHERE MEMBER_ID IS NULL;
--
-- To see which ones:
--
--   SELECT ID, NO_KREDIT, NAMA, CREATED_AT
--   FROM kosada_kredit
--   WHERE MEMBER_ID IS NULL
--   ORDER BY CREATED_AT DESC;
