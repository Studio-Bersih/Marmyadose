-- UD84 Sales management — activation status for salespeople.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- The Sales controller reads and writes STATUS; without the column the
-- salesperson list and every update fails.
--
-- Why a status column instead of just deleting rows: ud84_pesanan_rekap.SALES
-- and ud84_member.CREATED_BY both store the salesperson's ID. Deleting a
-- salesperson blanks their name on every order they ever took. Deactivating
-- removes them from the pick-lists while leaving history intact.
--
-- Existing rows default to 'Aktif', which is the correct starting state.
--
-- Do NOT run `php artisan migrate` on this database. Its migrations table
-- records only the project's original Laravel 9/10-era migrations; the repo's
-- current database/migrations/ holds Laravel 11-style files that are not
-- recorded there, so migrate would attempt create_users_table against the
-- existing `users` table and fail.

ALTER TABLE `ud84_sales`
  ADD COLUMN `STATUS` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif' AFTER `NAMA`;
